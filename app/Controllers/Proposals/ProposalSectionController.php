<?php

namespace App\Controllers\Proposals;

use App\Controllers\BaseController;
use App\Models\ProposalModel;
use App\Models\ProposalSectionModel;
use App\Domain\Proposals\Authorization\ProposalGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Proposal Section Controller
 *
 * Manages proposal sections (line items) within proposals.
 */
class ProposalSectionController extends BaseController
{
    protected ProposalModel $proposalModel;
    protected ProposalSectionModel $sectionModel;
    protected ProposalGuard $guard;

    public function __construct()
    {
        $this->proposalModel = new ProposalModel();
        $this->sectionModel = new ProposalSectionModel();
        $this->guard = new ProposalGuard();
    }

    /**
     * List sections for a proposal
     *
     * GET /api/proposals/{proposalId}/sections
     */
    public function index(string $proposalId): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($proposalId);

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

        $sections = $this->sectionModel->getByProposalId($proposalId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $sections,
        ]);
    }

    /**
     * Add section to proposal
     *
     * POST /api/proposals/{proposalId}/sections
     */
    public function store(string $proposalId): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($proposalId);

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

        $rules = [
            'title' => 'required|max_length[255]',
            'quantity' => 'permit_empty|decimal',
            'unit_price' => 'permit_empty|decimal',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'Validation failed', 'errors' => $this->validator->getErrors()]);
        }

        // Get next sort order
        $existingSections = $this->sectionModel->getByProposalId($proposalId);
        $maxOrder = empty($existingSections) ? -1 : max(array_column($existingSections, 'sort_order'));

        $sectionData = [
            'proposal_id' => $proposalId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'content' => $data['content'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $data['unit_price'] ?? 0,
            'is_optional' => $data['is_optional'] ?? false,
            'is_selected' => $data['is_selected'] ?? true,
            'sort_order' => $maxOrder + 1,
        ];

        $sectionId = $this->sectionModel->insert($sectionData, true);

        if (!$sectionId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create section']);
        }

        $section = $this->sectionModel->find($sectionId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'data' => $section,
                'message' => 'Section added successfully',
            ]);
    }

    /**
     * Update section
     *
     * PUT /api/proposals/{proposalId}/sections/{id}
     */
    public function update(string $proposalId, string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($proposalId);

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

        $section = $this->sectionModel->find($id);

        if (!$section || $section['proposal_id'] !== $proposalId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Section not found']);
        }

        $data = $this->request->getJSON(true);

        $updateData = array_filter([
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'content' => $data['content'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'unit_price' => $data['unit_price'] ?? null,
            'is_optional' => isset($data['is_optional']) ? $data['is_optional'] : null,
            'is_selected' => isset($data['is_selected']) ? $data['is_selected'] : null,
        ], fn($v) => $v !== null);

        if (!empty($updateData)) {
            $this->sectionModel->update($id, $updateData);
        }

        $section = $this->sectionModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $section,
            'message' => 'Section updated successfully',
        ]);
    }

    /**
     * Delete section
     *
     * DELETE /api/proposals/{proposalId}/sections/{id}
     */
    public function delete(string $proposalId, string $id): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($proposalId);

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

        $section = $this->sectionModel->find($id);

        if (!$section || $section['proposal_id'] !== $proposalId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Section not found']);
        }

        $this->sectionModel->delete($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Section deleted successfully',
        ]);
    }

    /**
     * Reorder sections
     *
     * POST /api/proposals/{proposalId}/sections/reorder
     */
    public function reorder(string $proposalId): ResponseInterface
    {
        $user = session()->get('user');
        $proposal = $this->proposalModel->find($proposalId);

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

        if (empty($data['section_ids']) || !is_array($data['section_ids'])) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'section_ids array is required']);
        }

        $this->sectionModel->reorder($proposalId, $data['section_ids']);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Sections reordered successfully',
        ]);
    }
}
