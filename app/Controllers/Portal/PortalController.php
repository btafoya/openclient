<?php

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Models\PortalAccessTokenModel;
use App\Models\PortalSessionModel;
use App\Models\PortalActivityLogModel;
use App\Models\ClientModel;
use App\Models\InvoiceModel;
use App\Models\ProposalModel;
use App\Models\ProjectModel;
use App\Domain\Portal\Authorization\PortalGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Client Portal Controller
 *
 * Handles client portal authentication and self-service operations.
 * Uses separate authentication from main user system.
 */
class PortalController extends BaseController
{
    protected PortalGuard $guard;
    protected PortalAccessTokenModel $accessTokenModel;
    protected PortalSessionModel $sessionModel;
    protected PortalActivityLogModel $activityModel;

    public function __construct()
    {
        $this->guard = new PortalGuard();
        $this->accessTokenModel = new PortalAccessTokenModel();
        $this->sessionModel = new PortalSessionModel();
        $this->activityModel = new PortalActivityLogModel();
    }

    /**
     * Get current portal session info
     *
     * GET /portal/me
     */
    public function me(): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        // Get client info
        $clientModel = new ClientModel();
        $client = $clientModel->find($auth['client_id']);

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'email' => $auth['email'],
                'client' => $client,
                'permissions' => $this->guard->getPermissionSummary($auth),
            ],
        ]);
    }

    /**
     * Authenticate with magic link
     *
     * POST /portal/auth/magic-link
     */
    public function authMagicLink(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['token'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Token is required']);
        }

        $auth = $this->guard->authenticateWithMagicLink($data['token']);

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Invalid or expired magic link']);
        }

        // Create session
        $session = $this->guard->createSession(
            $auth['access']['id'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString()
        );

        // Log activity
        $this->activityModel->logActivity(
            $auth['client_id'],
            PortalActivityLogModel::ACTION_LOGIN,
            $auth['access']['id'],
            null,
            null,
            ['method' => 'magic_link'],
            $this->request->getIPAddress()
        );

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'session_token' => $session['session_token'],
                'expires_at' => $session['expires_at'],
                'permissions' => $auth['permissions'],
            ],
        ]);
    }

    /**
     * Request magic link
     *
     * POST /portal/auth/request-link
     */
    public function requestMagicLink(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['email'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Email is required']);
        }

        // Find access token for this email
        $access = $this->accessTokenModel
            ->where('email', $data['email'])
            ->where('is_active', true)
            ->where('token_type', 'access')
            ->first();

        if (!$access) {
            // Don't reveal if email exists
            return $this->response->setJSON([
                'success' => true,
                'message' => 'If an account exists with this email, a login link will be sent.',
            ]);
        }

        // Create magic link
        $magicLink = $this->accessTokenModel->createMagicLink($access['client_id'], $data['email']);

        // TODO: Send email with magic link
        // The link would be: /portal/login?token={$magicLink['token']}

        return $this->response->setJSON([
            'success' => true,
            'message' => 'If an account exists with this email, a login link will be sent.',
        ]);
    }

    /**
     * Logout
     *
     * POST /portal/auth/logout
     */
    public function logout(): ResponseInterface
    {
        $sessionToken = $this->request->getHeaderLine('X-Portal-Session');

        if ($sessionToken) {
            $this->guard->logout($sessionToken);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get client's invoices
     *
     * GET /portal/invoices
     */
    public function invoices(): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        if (!$this->guard->canViewInvoices($auth)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view invoices.']);
        }

        $invoiceModel = new InvoiceModel();
        $invoices = $invoiceModel->getByClientId($auth['client_id']);

        // Filter to only show sent, viewed, paid, overdue invoices (not drafts)
        $invoices = array_filter($invoices, function($invoice) {
            return $invoice['status'] !== 'draft';
        });

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($invoices),
        ]);
    }

    /**
     * Get single invoice
     *
     * GET /portal/invoices/{id}
     */
    public function showInvoice(string $id): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        if (!$this->guard->canViewInvoices($auth)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view invoices.']);
        }

        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->getWithRelated($id);

        if (!$invoice || $invoice['client_id'] !== $auth['client_id']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        if ($invoice['status'] === 'draft') {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Log view
        $this->activityModel->logActivity(
            $auth['client_id'],
            PortalActivityLogModel::ACTION_VIEW_INVOICE,
            $auth['access']['id'] ?? null,
            'invoice',
            $id,
            null,
            $this->request->getIPAddress()
        );

        // Mark as viewed if not already
        if ($invoice['status'] === 'sent') {
            $invoiceModel->updateStatus($id, 'viewed');
            $invoice['status'] = 'viewed';
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Get client's proposals
     *
     * GET /portal/proposals
     */
    public function proposals(): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        if (!$this->guard->canViewProposals($auth)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view proposals.']);
        }

        $proposalModel = new ProposalModel();
        $proposals = $proposalModel->getByClientId($auth['client_id']);

        // Filter to only show sent, viewed, accepted, rejected proposals
        $proposals = array_filter($proposals, function($proposal) {
            return $proposal['status'] !== 'draft';
        });

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($proposals),
        ]);
    }

    /**
     * Get single proposal
     *
     * GET /portal/proposals/{id}
     */
    public function showProposal(string $id): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        if (!$this->guard->canViewProposals($auth)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view proposals.']);
        }

        $proposalModel = new ProposalModel();
        $proposal = $proposalModel->getWithRelated($id);

        if (!$proposal || $proposal['client_id'] !== $auth['client_id']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if ($proposal['status'] === 'draft') {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        // Log view
        $this->activityModel->logActivity(
            $auth['client_id'],
            PortalActivityLogModel::ACTION_VIEW_PROPOSAL,
            $auth['access']['id'] ?? null,
            'proposal',
            $id,
            null,
            $this->request->getIPAddress()
        );

        // Mark as viewed if not already
        if ($proposal['status'] === 'sent') {
            $proposalModel->updateStatus($id, 'viewed');
            $proposal['status'] = 'viewed';
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $proposal,
        ]);
    }

    /**
     * Accept proposal
     *
     * POST /portal/proposals/{id}/accept
     */
    public function acceptProposal(string $id): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        $proposalModel = new ProposalModel();
        $proposal = $proposalModel->find($id);

        if (!$proposal || $proposal['client_id'] !== $auth['client_id']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!in_array($proposal['status'], ['sent', 'viewed'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'This proposal cannot be accepted.']);
        }

        $data = $this->request->getJSON(true);

        // Sign proposal
        $signatureData = [
            'signature' => $data['signature'] ?? null,
            'name' => $data['name'] ?? $auth['email'],
            'email' => $auth['email'],
            'ip' => $this->request->getIPAddress(),
        ];

        $proposalModel->signProposal($id, $signatureData);

        // Log activity
        $this->activityModel->logActivity(
            $auth['client_id'],
            PortalActivityLogModel::ACTION_ACCEPT_PROPOSAL,
            $auth['access']['id'] ?? null,
            'proposal',
            $id,
            ['signed_name' => $signatureData['name']],
            $this->request->getIPAddress()
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Proposal accepted successfully',
        ]);
    }

    /**
     * Reject proposal
     *
     * POST /portal/proposals/{id}/reject
     */
    public function rejectProposal(string $id): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        $proposalModel = new ProposalModel();
        $proposal = $proposalModel->find($id);

        if (!$proposal || $proposal['client_id'] !== $auth['client_id']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Proposal not found']);
        }

        if (!in_array($proposal['status'], ['sent', 'viewed'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'This proposal cannot be rejected.']);
        }

        $data = $this->request->getJSON(true);

        $proposalModel->updateStatus($id, 'rejected', [
            'rejection_reason' => $data['reason'] ?? null,
        ]);

        // Log activity
        $this->activityModel->logActivity(
            $auth['client_id'],
            PortalActivityLogModel::ACTION_REJECT_PROPOSAL,
            $auth['access']['id'] ?? null,
            'proposal',
            $id,
            ['reason' => $data['reason'] ?? null],
            $this->request->getIPAddress()
        );

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Proposal rejected',
        ]);
    }

    /**
     * Get client's projects
     *
     * GET /portal/projects
     */
    public function projects(): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        if (!$this->guard->canViewProjects($auth)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view projects.']);
        }

        $projectModel = new ProjectModel();
        $projects = $projectModel->getByClientId($auth['client_id']);

        return $this->response->setJSON([
            'success' => true,
            'data' => $projects,
        ]);
    }

    /**
     * Get activity log
     *
     * GET /portal/activity
     */
    public function activity(): ResponseInterface
    {
        $auth = $this->getPortalAuth();

        if (!$auth) {
            return $this->response
                ->setStatusCode(401)
                ->setJSON(['error' => 'Not authenticated']);
        }

        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $activities = $this->activityModel->getByClientId($auth['client_id'], $limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get portal authentication from request
     */
    private function getPortalAuth(): ?array
    {
        $sessionToken = $this->request->getHeaderLine('X-Portal-Session');

        if ($sessionToken) {
            return $this->guard->authenticateWithSession($sessionToken);
        }

        $accessToken = $this->request->getHeaderLine('X-Portal-Token');

        if ($accessToken) {
            return $this->guard->authenticateWithToken($accessToken);
        }

        return null;
    }
}
