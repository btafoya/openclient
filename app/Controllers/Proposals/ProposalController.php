<?php

namespace App\Controllers\Proposals;

use App\Controllers\BaseController;
use App\Models\ProposalModel;
use App\Models\ProposalSectionModel;
use App\Models\ProposalTemplateModel;
use App\Models\ClientModel;
use App\Domain\Proposals\Authorization\ProposalGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Proposal Controller
 *
 * Manages proposal CRUD operations with full RBAC integration.
 */
class ProposalController extends BaseController
{
    protected ProposalModel $proposalModel;
    protected ProposalSectionModel $sectionModel;
    protected ProposalTemplateModel $templateModel;
    protected ProposalGuard $guard;

    public function __construct()
    {
        $this->proposalModel = new ProposalModel();
        $this->sectionModel = new ProposalSectionModel();
        $this->templateModel = new ProposalTemplateModel();
        $this->guard = new ProposalGuard();
    }

    /**
     * List all proposals
     *
     * GET /api/proposals
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        $filters = [
            'status' => $this->request->getGet('status'),
            'client_id' => $this->request->getGet('client_id'),
        ];

        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        if (!empty($filters['client_id'])) {
            $proposals = $this->proposalModel->getByClientId($filters['client_id']);
        } elseif (!empty($filters['status'])) {
            $proposals = $this->proposalModel->getByStatus($filters['status']);
        } else {
            $proposals = $this->proposalModel->findAll();
        }

        // Filter for direct clients
        if ($user['role'] === 'direct_client') {
            $proposals = array_filter($proposals, function($proposal) use ($user) {
                return $this->guard->canView($user, $proposal);
            });
            $proposals = array_values($proposals);
        }

        $permissions = $this->guard->getPermissionSummary($user);

        return $this->response->setJSON([
            'success' => true,
            'data' => $proposals,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show single proposal with sections
     *
     * GET /api/proposals/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->getWithRelated($id);

        if (!$proposal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!$this->guard->canView($user, $proposal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this proposal.']);
        }

        $permissions = $this->guard->getPermissionSummary($user, $proposal);

        return $this->response->setJSON([
            'success' => true,
            'data' => $proposal,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store new proposal
     *
     * POST /api/proposals
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create proposals.']);
        }

        $data = $this->request->getJSON(true);

        $rules = [
            'client_id' => 'required|max_length[36]',
            'title' => 'required|max_length[255]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'Validation failed', 'errors' => $this->validator->getErrors()]);
        }

        // Prepare proposal data
        $proposalData = [
            'client_id' => $data['client_id'],
            'deal_id' => $data['deal_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'title' => $data['title'],
            'introduction' => $data['introduction'] ?? null,
            'conclusion' => $data['conclusion'] ?? null,
            'terms_conditions' => $data['terms_conditions'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'valid_until' => $data['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
            'status' => 'draft',
        ];

        $proposalId = $this->proposalModel->insert($proposalData, true);

        if (!$proposalId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create proposal']);
        }

        // If template specified, create sections from template
        if (!empty($data['template_id'])) {
            $template = $this->templateModel->getWithSections($data['template_id']);
            if ($template && !empty($template['default_sections'])) {
                $this->sectionModel->createFromTemplate($proposalId, $template['default_sections']);
            }
        }

        // Add sections if provided directly
        if (!empty($data['sections'])) {
            foreach ($data['sections'] as $index => $section) {
                $this->sectionModel->insert([
                    'proposal_id' => $proposalId,
                    'title' => $section['title'],
                    'description' => $section['description'] ?? null,
                    'content' => $section['content'] ?? null,
                    'quantity' => $section['quantity'] ?? 1,
                    'unit_price' => $section['unit_price'] ?? 0,
                    'is_optional' => $section['is_optional'] ?? false,
                    'is_selected' => true,
                    'sort_order' => $index,
                ]);
            }
        }

        // Recalculate totals
        $this->proposalModel->recalculateTotals($proposalId);

        $proposal = $this->proposalModel->getWithRelated($proposalId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'data' => $proposal,
                'message' => 'Proposal created successfully',
            ]);
    }

    /**
     * Update proposal
     *
     * PUT /api/proposals/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($id);

        if (!$proposal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!$this->guard->canEdit($user, $proposal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this proposal.']);
        }

        $data = $this->request->getJSON(true);

        $updateData = array_filter([
            'title' => $data['title'] ?? null,
            'introduction' => $data['introduction'] ?? null,
            'conclusion' => $data['conclusion'] ?? null,
            'terms_conditions' => $data['terms_conditions'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? null,
            'tax_rate' => $data['tax_rate'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->proposalModel->update($id, $updateData);
        }

        // Recalculate totals
        $this->proposalModel->recalculateTotals($id);

        $proposal = $this->proposalModel->getWithRelated($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $proposal,
            'message' => 'Proposal updated successfully',
        ]);
    }

    /**
     * Delete proposal
     *
     * DELETE /api/proposals/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($id);

        if (!$proposal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!$this->guard->canDelete($user, $proposal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this proposal.']);
        }

        $this->proposalModel->delete($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Proposal deleted successfully',
        ]);
    }

    /**
     * Send proposal to client
     *
     * POST /api/proposals/{id}/send
     */
    public function send(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($id);

        if (!$proposal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!$this->guard->canSend($user, $proposal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You cannot send this proposal.']);
        }

        $this->proposalModel->updateStatus($id, 'sent');

        // TODO: Send email notification to client

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Proposal sent successfully',
        ]);
    }

    /**
     * Convert accepted proposal to invoice
     *
     * POST /api/proposals/{id}/convert-to-invoice
     */
    public function convertToInvoice(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($id);

        if (!$proposal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!$this->guard->canConvertToInvoice($user, $proposal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You cannot convert this proposal to an invoice.']);
        }

        $invoiceId = $this->proposalModel->convertToInvoice($id);

        if (!$invoiceId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to convert proposal to invoice']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => ['invoice_id' => $invoiceId],
            'message' => 'Proposal converted to invoice successfully',
        ]);
    }

    /**
     * List templates
     *
     * GET /api/proposals/templates
     */
    public function templates(): ResponseInterface
    {
        $templates = $this->templateModel->getActiveTemplates();

        return $this->response->setJSON([
            'success' => true,
            'data' => $templates,
        ]);
    }
}
