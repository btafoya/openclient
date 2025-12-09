<?php

namespace App\Controllers\Clients;

use App\Controllers\BaseController;
use App\Models\ClientModel;
use App\Domain\Clients\Authorization\ClientGuard;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Client Controller
 *
 * Manages client CRUD operations with full RBAC integration.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): ClientGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 */
class ClientController extends BaseController
{
    protected ClientModel $clientModel;
    protected ClientGuard $guard;

    public function __construct()
    {
        $this->clientModel = new ClientModel();
        $this->guard = new ClientGuard();
    }

    /**
     * List all clients (with search and filtering)
     *
     * GET /clients
     * GET /clients?search=term
     * GET /clients?active=1
     */
    public function index(): string
    {
        $user = session()->get('user');

        // Get search term and filters from query params
        $search = $this->request->getGet('search');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        // Get clients (RLS automatically filters by agency)
        if ($search) {
            $clients = $this->clientModel->search($search, $activeOnly);
        } else {
            $clients = $this->clientModel->getWithProjectCount($activeOnly);
        }

        // Get permission summary for UI
        $permissions = $this->guard->getPermissionSummary($user);

        return view('clients/index', [
            'title' => 'Clients',
            'clients' => $clients,
            'permissions' => $permissions,
            'search' => $search,
            'activeOnly' => $activeOnly,
        ]);
    }

    /**
     * Show single client details
     *
     * GET /clients/{id}
     */
    public function show(string $id): string
    {
        $user = session()->get('user');
        $client = $this->clientModel->find($id);

        // Check if client exists (RLS may hide it)
        if (!$client) {
            throw PageNotFoundException::forPageNotFound('Client not found');
        }

        // Layer 3: Service guard authorization
        if (!$this->guard->canView($user, $client)) {
            return $this->response
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', [
                    'message' => 'You do not have permission to view this client.',
                ]));
        }

        // Get assigned users if user can manage them
        $assignedUsers = $this->guard->canManageUsers($user, $client)
            ? $this->guard->getAssignedUsers($id)
            : [];

        // Get permission summary for this specific client
        $permissions = $this->guard->getPermissionSummary($user, $client);

        return view('clients/show', [
            'title' => $client['name'],
            'client' => $client,
            'assignedUsers' => $assignedUsers,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show create client form
     *
     * GET /clients/create
     */
    public function create(): string
    {
        $user = session()->get('user');

        // Layer 3: Check if user can create clients
        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', [
                    'message' => 'You do not have permission to create clients.',
                ]));
        }

        return view('clients/create', [
            'title' => 'New Client',
            'validation' => session()->getFlashdata('validation') ?? null,
        ]);
    }

    /**
     * Store new client
     *
     * POST /clients
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Layer 3: Authorization check
        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create clients.']);
        }

        // Get form data
        $data = $this->request->getPost([
            'name',
            'email',
            'phone',
            'company',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'notes',
            'is_active',
        ]);

        // Validate input
        if (!$this->clientModel->validate($data)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('validation', $this->clientModel->errors());
        }

        // Insert client (agency_id set automatically by model callback)
        $clientId = $this->clientModel->insert($data);

        if (!$clientId) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create client. Please try again.');
        }

        return redirect()
            ->to('/clients')
            ->with('success', 'Client created successfully.');
    }

    /**
     * Show edit client form
     *
     * GET /clients/{id}/edit
     */
    public function edit(string $id): string
    {
        $user = session()->get('user');
        $client = $this->clientModel->find($id);

        // Check if client exists
        if (!$client) {
            throw PageNotFoundException::forPageNotFound('Client not found');
        }

        // Layer 3: Check edit permission
        if (!$this->guard->canEdit($user, $client)) {
            return $this->response
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', [
                    'message' => 'You do not have permission to edit this client.',
                ]));
        }

        return view('clients/edit', [
            'title' => 'Edit Client',
            'client' => $client,
            'validation' => session()->getFlashdata('validation') ?? null,
        ]);
    }

    /**
     * Update client
     *
     * PUT/PATCH /clients/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $client = $this->clientModel->find($id);

        // Check if client exists
        if (!$client) {
            return redirect()
                ->to('/clients')
                ->with('error', 'Client not found.');
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $client)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this client.']);
        }

        // Get form data
        $data = $this->request->getPost([
            'name',
            'email',
            'phone',
            'company',
            'address',
            'city',
            'state',
            'postal_code',
            'country',
            'notes',
            'is_active',
        ]);

        // Validate input
        if (!$this->clientModel->validate($data)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('validation', $this->clientModel->errors());
        }

        // Update client
        $success = $this->clientModel->update($id, $data);

        if (!$success) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update client. Please try again.');
        }

        return redirect()
            ->to('/clients/' . $id)
            ->with('success', 'Client updated successfully.');
    }

    /**
     * Delete client (soft delete)
     *
     * DELETE /clients/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $client = $this->clientModel->find($id);

        // Check if client exists
        if (!$client) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Client not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDelete($user, $client)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this client.']);
        }

        // Validate deletion (check for dependent records)
        $validation = $this->clientModel->validateDelete($id);

        if (!$validation['can_delete']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Cannot delete client.',
                    'reasons' => $validation['blockers'],
                ]);
        }

        // Soft delete client
        $success = $this->clientModel->deleteClient($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete client. Please try again.']);
        }

        return $this->response
            ->setJSON(['message' => 'Client deleted successfully.']);
    }

    /**
     * Restore soft-deleted client
     *
     * POST /clients/{id}/restore
     */
    public function restore(string $id): ResponseInterface
    {
        $user = session()->get('user');

        // Only owner can restore deleted clients
        if ($user['role'] !== 'owner') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'Only platform administrators can restore deleted clients.']);
        }

        $success = $this->clientModel->restore($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to restore client.']);
        }

        return $this->response
            ->setJSON(['message' => 'Client restored successfully.']);
    }

    /**
     * Toggle client active status
     *
     * POST /clients/{id}/toggle-active
     */
    public function toggleActive(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $client = $this->clientModel->find($id);

        if (!$client) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Client not found.']);
        }

        // Layer 3: Check edit permission
        if (!$this->guard->canEdit($user, $client)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to modify this client.']);
        }

        $success = $this->clientModel->toggleActive($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to toggle client status.']);
        }

        $newStatus = !$client['is_active'];

        return $this->response
            ->setJSON([
                'message' => 'Client status updated successfully.',
                'is_active' => $newStatus,
            ]);
    }

    /**
     * API endpoint: Get clients as JSON
     *
     * GET /api/clients
     */
    public function apiIndex(): ResponseInterface
    {
        $search = $this->request->getGet('search');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        if ($search) {
            $clients = $this->clientModel->search($search, $activeOnly);
        } else {
            $clients = $this->clientModel->getWithProjectCount($activeOnly);
        }

        return $this->response->setJSON([
            'clients' => $clients,
            'count' => count($clients),
        ]);
    }

    /**
     * API endpoint: Get single client as JSON
     *
     * GET /api/clients/{id}
     */
    public function apiShow(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $client = $this->clientModel->find($id);

        if (!$client) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Client not found.']);
        }

        // Layer 3: Authorization
        if (!$this->guard->canView($user, $client)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this client.']);
        }

        // Include permissions in response
        $permissions = $this->guard->getPermissionSummary($user, $client);

        return $this->response->setJSON([
            'client' => $client,
            'permissions' => $permissions,
        ]);
    }
}
