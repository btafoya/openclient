<?php

namespace App\Controllers\Contacts;

use App\Controllers\BaseController;
use App\Models\ContactModel;
use App\Models\ClientModel;
use App\Domain\Contacts\Authorization\ContactGuard;
use CodeIgniter\HTTP\ResponseInterface;

class ContactController extends BaseController
{
    protected ContactModel $contactModel;
    protected ClientModel $clientModel;
    protected ContactGuard $guard;

    public function __construct()
    {
        $this->contactModel = new ContactModel();
        $this->clientModel = new ClientModel();
        $this->guard = new ContactGuard();
    }

    /**
     * Display list of contacts
     */
    public function index(): string
    {
        $user = session()->get('user');
        $search = $this->request->getGet('search');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;
        $clientFilter = $this->request->getGet('client_id');

        $builder = $this->contactModel->builder()
            ->select('contacts.*, clients.name as client_name, clients.company as client_company')
            ->join('clients', 'clients.id = contacts.client_id', 'left');

        // Apply role-based filtering
        if ($user['role'] !== 'owner') {
            if ($user['role'] === 'agency') {
                $builder->where('contacts.agency_id', $user['agency_id']);
            } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
                $builder->join('client_users cu', 'cu.client_id = contacts.client_id')
                    ->where('cu.user_id', $user['id']);
            }
        }

        // Apply search filter
        if ($search) {
            $builder->groupStart()
                ->like('contacts.first_name', $search)
                ->orLike('contacts.last_name', $search)
                ->orLike('contacts.email', $search)
                ->orLike('contacts.job_title', $search)
                ->orLike('contacts.phone', $search)
                ->orLike('contacts.mobile', $search)
                ->orLike('clients.name', $search)
                ->orLike('clients.company', $search)
                ->groupEnd();
        }

        // Apply active filter
        if ($activeOnly) {
            $builder->where('contacts.is_active', true);
        }

        // Apply client filter
        if ($clientFilter) {
            $builder->where('contacts.client_id', $clientFilter);
        }

        $contacts = $builder->orderBy('contacts.last_name', 'ASC')
            ->orderBy('contacts.first_name', 'ASC')
            ->get()
            ->getResultArray();

        // Get clients for filter dropdown (role-based)
        $clientsBuilder = $this->clientModel->builder();
        if ($user['role'] === 'agency') {
            $clientsBuilder->where('agency_id', $user['agency_id']);
        } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
            $clientsBuilder->join('client_users cu', 'cu.client_id = clients.id')
                ->where('cu.user_id', $user['id']);
        }
        $clients = $clientsBuilder->orderBy('name', 'ASC')->get()->getResultArray();

        return view('contacts/index', [
            'title' => 'Contacts',
            'contacts' => $contacts,
            'clients' => $clients,
            'search' => $search,
            'activeOnly' => $activeOnly,
            'clientFilter' => $clientFilter,
            'permissions' => [
                'canCreate' => $this->guard->canCreate($user),
            ],
        ]);
    }

    /**
     * Display single contact
     */
    public function show(string $id): string|ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->getWithClient($id);

        if (!$contact) {
            return redirect()->to('/contacts')->with('error', 'Contact not found.');
        }

        // Authorization check
        if (!$this->guard->canView($user, $contact)) {
            return redirect()->to('/contacts')->with('error', 'You do not have permission to view this contact.');
        }

        return view('contacts/show', [
            'title' => $this->contactModel->getFullName($contact),
            'contact' => $contact,
            'permissions' => [
                'canEdit' => $this->guard->canEdit($user, $contact),
                'canDelete' => $this->guard->canDelete($user, $contact),
            ],
        ]);
    }

    /**
     * Show contact creation form
     */
    public function create(): string|ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return redirect()->to('/contacts')->with('error', 'You do not have permission to create contacts.');
        }

        // Get client_id from query string if provided
        $clientId = $this->request->getGet('client_id');

        // Get accessible clients for dropdown
        $clientsBuilder = $this->clientModel->builder();
        if ($user['role'] === 'agency') {
            $clientsBuilder->where('agency_id', $user['agency_id']);
        }
        $clients = $clientsBuilder->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return view('contacts/create', [
            'title' => 'Create Contact',
            'clients' => $clients,
            'preselectedClientId' => $clientId,
        ]);
    }

    /**
     * Process contact creation
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        $data = $this->request->getPost([
            'client_id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'mobile',
            'job_title',
            'department',
            'is_primary',
            'notes',
        ]);

        // Validate client_id is provided
        if (empty($data['client_id'])) {
            return redirect()->back()->withInput()
                ->with('error', 'Client is required.');
        }

        // Authorization check - can user create contacts for this client?
        if (!$this->guard->canCreate($user, $data['client_id'])) {
            return redirect()->back()->withInput()
                ->with('error', 'You do not have permission to create contacts for this client.');
        }

        // Convert checkbox to boolean
        $data['is_primary'] = isset($data['is_primary']) && $data['is_primary'] === '1';

        // Validate data
        if (!$this->contactModel->validate($data)) {
            return redirect()->back()->withInput()
                ->with('validation', $this->contactModel->errors());
        }

        $contactId = $this->contactModel->insert($data);

        if (!$contactId) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to create contact.');
        }

        return redirect()->to('/contacts/' . $contactId)
            ->with('success', 'Contact created successfully.');
    }

    /**
     * Show contact edit form
     */
    public function edit(string $id): string|ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->find($id);

        if (!$contact) {
            return redirect()->to('/contacts')->with('error', 'Contact not found.');
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $contact)) {
            return redirect()->to('/contacts')->with('error', 'You do not have permission to edit this contact.');
        }

        // Get accessible clients for dropdown
        $clientsBuilder = $this->clientModel->builder();
        if ($user['role'] === 'agency') {
            $clientsBuilder->where('agency_id', $user['agency_id']);
        }
        $clients = $clientsBuilder->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return view('contacts/edit', [
            'title' => 'Edit Contact',
            'contact' => $contact,
            'clients' => $clients,
        ]);
    }

    /**
     * Process contact update
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->find($id);

        if (!$contact) {
            return redirect()->to('/contacts')->with('error', 'Contact not found.');
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $contact)) {
            return redirect()->to('/contacts')->with('error', 'You do not have permission to edit this contact.');
        }

        $data = $this->request->getPost([
            'client_id',
            'first_name',
            'last_name',
            'email',
            'phone',
            'mobile',
            'job_title',
            'department',
            'is_primary',
            'notes',
            'is_active',
        ]);

        // Validate client_id is provided
        if (empty($data['client_id'])) {
            return redirect()->back()->withInput()
                ->with('error', 'Client is required.');
        }

        // Verify client belongs to user's agency (for agency users)
        if ($user['role'] === 'agency') {
            $client = $this->clientModel->find($data['client_id']);
            if (!$client || $client['agency_id'] !== $user['agency_id']) {
                return redirect()->back()->withInput()
                    ->with('error', 'Invalid client selection.');
            }
        }

        // Convert checkboxes to boolean
        $data['is_primary'] = isset($data['is_primary']) && $data['is_primary'] === '1';
        $data['is_active'] = isset($data['is_active']) && $data['is_active'] === '1';

        // Validate data
        if (!$this->contactModel->validate($data)) {
            return redirect()->back()->withInput()
                ->with('validation', $this->contactModel->errors());
        }

        $success = $this->contactModel->update($id, $data);

        if (!$success) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to update contact.');
        }

        return redirect()->to('/contacts/' . $id)
            ->with('success', 'Contact updated successfully.');
    }

    /**
     * Soft delete contact
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->find($id);

        if (!$contact) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Contact not found.']);
        }

        // Authorization check
        if (!$this->guard->canDelete($user, $contact)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this contact.']);
        }

        // Validate deletion
        $validation = $this->contactModel->validateDelete($id);
        if (!$validation['can_delete']) {
            return $this->response->setStatusCode(400)
                ->setJSON([
                    'error' => 'Cannot delete contact',
                    'blockers' => $validation['blockers'],
                ]);
        }

        $success = $this->contactModel->delete($id);

        if (!$success) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete contact.']);
        }

        return $this->response->setJSON(['message' => 'Contact deleted successfully.']);
    }

    /**
     * Restore soft-deleted contact
     */
    public function restore(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->withDeleted()->find($id);

        if (!$contact) {
            return redirect()->to('/contacts')
                ->with('error', 'Contact not found.');
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $contact)) {
            return redirect()->to('/contacts')
                ->with('error', 'You do not have permission to restore this contact.');
        }

        $success = $this->contactModel->restore($id);

        if (!$success) {
            return redirect()->to('/contacts')
                ->with('error', 'Failed to restore contact.');
        }

        return redirect()->to('/contacts/' . $id)
            ->with('success', 'Contact restored successfully.');
    }

    /**
     * Toggle contact active status
     */
    public function toggleActive(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->find($id);

        if (!$contact) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Contact not found.']);
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $contact)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to modify this contact.']);
        }

        $success = $this->contactModel->toggleActive($id);

        if (!$success) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Failed to toggle contact status.']);
        }

        $newStatus = !$contact['is_active'];

        return $this->response->setJSON([
            'message' => 'Contact status updated successfully.',
            'is_active' => $newStatus,
        ]);
    }

    /**
     * API: Get contacts (JSON)
     */
    public function apiIndex(): ResponseInterface
    {
        $user = session()->get('user');
        $clientId = $this->request->getGet('client_id');

        if ($clientId) {
            // Get contacts for specific client
            $contacts = $this->contactModel->getByClient($clientId, true);

            // Filter based on user permissions
            $viewableIds = $this->guard->filterViewableContacts(
                $user,
                array_column($contacts, 'id')
            );

            $contacts = array_filter($contacts, function ($contact) use ($viewableIds) {
                return in_array($contact['id'], $viewableIds);
            });
        } else {
            // Get all contacts (role-filtered)
            $contacts = $this->contactModel->getAllWithClients(true);

            // Apply additional role-based filtering
            if ($user['role'] !== 'owner') {
                if ($user['role'] === 'agency') {
                    $contacts = array_filter($contacts, function ($contact) use ($user) {
                        return $contact['agency_id'] === $user['agency_id'];
                    });
                } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
                    $viewableIds = $this->guard->filterViewableContacts(
                        $user,
                        array_column($contacts, 'id')
                    );
                    $contacts = array_filter($contacts, function ($contact) use ($viewableIds) {
                        return in_array($contact['id'], $viewableIds);
                    });
                }
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($contacts),
        ]);
    }

    /**
     * API: Get single contact (JSON)
     */
    public function apiShow(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $contact = $this->contactModel->getWithClient($id);

        if (!$contact) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Contact not found.']);
        }

        // Authorization check
        if (!$this->guard->canView($user, $contact)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this contact.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $contact,
        ]);
    }
}
