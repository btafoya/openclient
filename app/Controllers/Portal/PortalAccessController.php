<?php

namespace App\Controllers\Portal;

use App\Controllers\BaseController;
use App\Models\PortalAccessTokenModel;
use App\Models\ClientModel;
use App\Models\ContactModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Portal Access Controller
 *
 * Admin management of client portal access (by agency users).
 */
class PortalAccessController extends BaseController
{
    protected PortalAccessTokenModel $accessTokenModel;

    public function __construct()
    {
        $this->accessTokenModel = new PortalAccessTokenModel();
    }

    /**
     * List portal access tokens for a client
     *
     * GET /api/clients/{clientId}/portal-access
     */
    public function index(string $clientId): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage portal access.']);
        }

        $tokens = $this->accessTokenModel->getByClientId($clientId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    /**
     * Create portal access for a client
     *
     * POST /api/clients/{clientId}/portal-access
     */
    public function store(string $clientId): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage portal access.']);
        }

        $data = $this->request->getJSON(true);

        $rules = [
            'email' => 'required|valid_email|max_length[255]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'Validation failed', 'errors' => $this->validator->getErrors()]);
        }

        // Check if access already exists for this email
        $existing = $this->accessTokenModel
            ->where('client_id', $clientId)
            ->where('email', $data['email'])
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Portal access already exists for this email.']);
        }

        // Find contact if email matches
        $contactModel = new ContactModel();
        $contact = $contactModel
            ->where('client_id', $clientId)
            ->where('email', $data['email'])
            ->first();

        $permissions = $data['permissions'] ?? null;
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;

        $access = $this->accessTokenModel->createAccess(
            $clientId,
            $data['email'],
            $contact['id'] ?? null,
            $permissions,
            $expiresAt
        );

        if (!$access) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create portal access']);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'data' => $access,
                'message' => 'Portal access created successfully',
            ]);
    }

    /**
     * Update portal access permissions
     *
     * PUT /api/clients/{clientId}/portal-access/{id}
     */
    public function update(string $clientId, string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage portal access.']);
        }

        $access = $this->accessTokenModel->find($id);

        if (!$access || $access['client_id'] !== $clientId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Portal access not found']);
        }

        $data = $this->request->getJSON(true);

        if (isset($data['permissions'])) {
            $this->accessTokenModel->updatePermissions($id, $data['permissions']);
        }

        if (isset($data['is_active'])) {
            if (!$data['is_active']) {
                $this->accessTokenModel->revokeAccess($id);
            } else {
                $this->accessTokenModel->update($id, ['is_active' => true]);
            }
        }

        $access = $this->accessTokenModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $access,
            'message' => 'Portal access updated successfully',
        ]);
    }

    /**
     * Revoke portal access
     *
     * DELETE /api/clients/{clientId}/portal-access/{id}
     */
    public function delete(string $clientId, string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage portal access.']);
        }

        $access = $this->accessTokenModel->find($id);

        if (!$access || $access['client_id'] !== $clientId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Portal access not found']);
        }

        $this->accessTokenModel->revokeAccess($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Portal access revoked successfully',
        ]);
    }

    /**
     * Send magic link to portal user
     *
     * POST /api/clients/{clientId}/portal-access/{id}/send-link
     */
    public function sendLink(string $clientId, string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage portal access.']);
        }

        $access = $this->accessTokenModel->find($id);

        if (!$access || $access['client_id'] !== $clientId || !$access['is_active']) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Portal access not found or inactive']);
        }

        // Create magic link
        $magicLink = $this->accessTokenModel->createMagicLink($clientId, $access['email']);

        // TODO: Send email with magic link
        // The link would be: /portal/login?token={$magicLink['token']}

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Login link sent successfully',
        ]);
    }

    /**
     * Revoke all portal access for a client
     *
     * DELETE /api/clients/{clientId}/portal-access
     */
    public function revokeAll(string $clientId): ResponseInterface
    {
        $user = session()->get('user');

        if ($user['role'] !== 'owner') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'Only owners can revoke all portal access.']);
        }

        $count = $this->accessTokenModel->revokeAllClientAccess($clientId);

        return $this->response->setJSON([
            'success' => true,
            'message' => "Revoked {$count} portal access tokens",
        ]);
    }
}
