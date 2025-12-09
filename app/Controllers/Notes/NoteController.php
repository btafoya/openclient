<?php

namespace App\Controllers\Notes;

use App\Controllers\BaseController;
use App\Models\NoteModel;
use App\Models\ClientModel;
use App\Models\ContactModel;
use App\Models\ProjectModel;
use App\Domain\Notes\Authorization\NoteGuard;
use CodeIgniter\HTTP\ResponseInterface;

class NoteController extends BaseController
{
    protected NoteModel $noteModel;
    protected ClientModel $clientModel;
    protected ContactModel $contactModel;
    protected ProjectModel $projectModel;
    protected NoteGuard $guard;

    public function __construct()
    {
        $this->noteModel = new NoteModel();
        $this->clientModel = new ClientModel();
        $this->contactModel = new ContactModel();
        $this->projectModel = new ProjectModel();
        $this->guard = new NoteGuard();
    }

    /**
     * Display list of notes
     */
    public function index(): string
    {
        $user = session()->get('user');
        $search = $this->request->getGet('search');
        $entityType = $this->request->getGet('entity_type');
        $entityId = $this->request->getGet('entity_id');
        $pinnedOnly = $this->request->getGet('pinned') === '1';

        // Get notes based on filters
        if ($pinnedOnly) {
            $notes = $this->noteModel->getPinned();
        } elseif ($search) {
            $notes = $this->noteModel->search($search);
        } elseif ($entityType && $entityId) {
            $notes = $this->getNotesForEntity($entityType, $entityId);
        } else {
            $notes = $this->noteModel->getAllWithEntities();
        }

        // Apply role-based filtering
        if ($user['role'] !== 'owner') {
            $noteIds = array_column($notes, 'id');
            $viewableIds = $this->guard->filterViewableNotes($user, $noteIds);
            $notes = array_filter($notes, fn($note) => in_array($note['id'], $viewableIds));
        }

        return view('notes/index', [
            'title' => 'Notes',
            'notes' => array_values($notes),
            'search' => $search,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'pinnedOnly' => $pinnedOnly,
            'permissions' => [
                'canCreate' => $this->guard->canCreate($user),
            ],
        ]);
    }

    /**
     * Display single note
     */
    public function show(string $id): string|ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->getWithEntity($id);

        if (!$note) {
            return redirect()->to('/notes')->with('error', 'Note not found.');
        }

        // Authorization check
        if (!$this->guard->canView($user, $note)) {
            return redirect()->to('/notes')->with('error', 'You do not have permission to view this note.');
        }

        return view('notes/show', [
            'title' => $note['subject'] ?: 'Note',
            'note' => $note,
            'permissions' => [
                'canEdit' => $this->guard->canEdit($user, $note),
                'canDelete' => $this->guard->canDelete($user, $note),
            ],
        ]);
    }

    /**
     * Show note creation form
     */
    public function create(): string|ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return redirect()->to('/notes')->with('error', 'You do not have permission to create notes.');
        }

        // Get preselected entity from query string
        $entityType = $this->request->getGet('entity_type');
        $entityId = $this->request->getGet('entity_id');

        // Get available entities for dropdowns
        $clients = $this->getAccessibleClients($user);
        $contacts = $this->getAccessibleContacts($user);
        $projects = $this->getAccessibleProjects($user);

        return view('notes/create', [
            'title' => 'Create Note',
            'clients' => $clients,
            'contacts' => $contacts,
            'projects' => $projects,
            'preselectedEntityType' => $entityType,
            'preselectedEntityId' => $entityId,
        ]);
    }

    /**
     * Process note creation
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        $data = $this->request->getPost([
            'entity_type',
            'entity_id',
            'subject',
            'content',
            'is_pinned',
        ]);

        // Validate entity type and ID
        if (empty($data['entity_type']) || empty($data['entity_id'])) {
            return redirect()->back()->withInput()
                ->with('error', 'Entity type and ID are required.');
        }

        // Authorization check
        if (!$this->guard->canCreate($user, $data['entity_type'], $data['entity_id'])) {
            return redirect()->back()->withInput()
                ->with('error', 'You do not have permission to create notes for this entity.');
        }

        // Map entity_type and entity_id to correct foreign key
        $noteData = [
            'user_id' => $user['id'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'is_pinned' => isset($data['is_pinned']) && $data['is_pinned'] === '1',
        ];

        // Set appropriate foreign key based on entity type
        switch ($data['entity_type']) {
            case 'client':
                $noteData['client_id'] = $data['entity_id'];
                break;
            case 'contact':
                $noteData['contact_id'] = $data['entity_id'];
                break;
            case 'project':
                $noteData['project_id'] = $data['entity_id'];
                break;
            default:
                return redirect()->back()->withInput()
                    ->with('error', 'Invalid entity type.');
        }

        // Validate data
        if (!$this->noteModel->validate($noteData)) {
            return redirect()->back()->withInput()
                ->with('validation', $this->noteModel->errors());
        }

        $noteId = $this->noteModel->insert($noteData);

        if (!$noteId) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to create note.');
        }

        return redirect()->to('/notes/' . $noteId)
            ->with('success', 'Note created successfully.');
    }

    /**
     * Show note edit form
     */
    public function edit(string $id): string|ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->find($id);

        if (!$note) {
            return redirect()->to('/notes')->with('error', 'Note not found.');
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $note)) {
            return redirect()->to('/notes')->with('error', 'You do not have permission to edit this note.');
        }

        return view('notes/edit', [
            'title' => 'Edit Note',
            'note' => $note,
        ]);
    }

    /**
     * Process note update
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->find($id);

        if (!$note) {
            return redirect()->to('/notes')->with('error', 'Note not found.');
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $note)) {
            return redirect()->to('/notes')->with('error', 'You do not have permission to edit this note.');
        }

        $data = $this->request->getPost([
            'subject',
            'content',
            'is_pinned',
        ]);

        // Convert checkbox to boolean
        $data['is_pinned'] = isset($data['is_pinned']) && $data['is_pinned'] === '1';

        // Validate data
        if (!$this->noteModel->validate($data)) {
            return redirect()->back()->withInput()
                ->with('validation', $this->noteModel->errors());
        }

        $success = $this->noteModel->update($id, $data);

        if (!$success) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to update note.');
        }

        return redirect()->to('/notes/' . $id)
            ->with('success', 'Note updated successfully.');
    }

    /**
     * Soft delete note
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->find($id);

        if (!$note) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Note not found.']);
        }

        // Authorization check
        if (!$this->guard->canDelete($user, $note)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this note.']);
        }

        $success = $this->noteModel->delete($id);

        if (!$success) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete note.']);
        }

        return $this->response->setJSON(['message' => 'Note deleted successfully.']);
    }

    /**
     * Restore soft-deleted note
     */
    public function restore(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->withDeleted()->find($id);

        if (!$note) {
            return redirect()->to('/notes')
                ->with('error', 'Note not found.');
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $note)) {
            return redirect()->to('/notes')
                ->with('error', 'You do not have permission to restore this note.');
        }

        $success = $this->noteModel->restore($id);

        if (!$success) {
            return redirect()->to('/notes')
                ->with('error', 'Failed to restore note.');
        }

        return redirect()->to('/notes/' . $id)
            ->with('success', 'Note restored successfully.');
    }

    /**
     * Toggle note pin status
     */
    public function togglePin(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->find($id);

        if (!$note) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Note not found.']);
        }

        // Authorization check
        if (!$this->guard->canEdit($user, $note)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to modify this note.']);
        }

        $success = $this->noteModel->togglePin($id);

        if (!$success) {
            return $this->response->setStatusCode(500)
                ->setJSON(['error' => 'Failed to toggle pin status.']);
        }

        $newStatus = !$note['is_pinned'];

        return $this->response->setJSON([
            'message' => 'Note pin status updated successfully.',
            'is_pinned' => $newStatus,
        ]);
    }

    /**
     * API: Get notes (JSON)
     */
    public function apiIndex(): ResponseInterface
    {
        $user = session()->get('user');
        $entityType = $this->request->getGet('entity_type');
        $entityId = $this->request->getGet('entity_id');

        if ($entityType && $entityId) {
            $notes = $this->getNotesForEntity($entityType, $entityId);
        } else {
            $notes = $this->noteModel->getAllWithEntities();
        }

        // Filter based on user permissions
        if ($user['role'] !== 'owner') {
            $noteIds = array_column($notes, 'id');
            $viewableIds = $this->guard->filterViewableNotes($user, $noteIds);
            $notes = array_filter($notes, fn($note) => in_array($note['id'], $viewableIds));
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($notes),
        ]);
    }

    /**
     * API: Get single note (JSON)
     */
    public function apiShow(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $note = $this->noteModel->getWithEntity($id);

        if (!$note) {
            return $this->response->setStatusCode(404)
                ->setJSON(['error' => 'Note not found.']);
        }

        // Authorization check
        if (!$this->guard->canView($user, $note)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this note.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $note,
        ]);
    }

    /**
     * Helper: Get notes for specific entity
     */
    private function getNotesForEntity(string $entityType, string $entityId): array
    {
        return match ($entityType) {
            'client' => $this->noteModel->getByClient($entityId),
            'contact' => $this->noteModel->getByContact($entityId),
            'project' => $this->noteModel->getByProject($entityId),
            default => [],
        };
    }

    /**
     * Helper: Get accessible clients for user
     */
    private function getAccessibleClients(array $user): array
    {
        $builder = $this->clientModel->builder();

        if ($user['role'] === 'agency') {
            $builder->where('agency_id', $user['agency_id']);
        } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
            $builder->join('client_users cu', 'cu.client_id = clients.id')
                ->where('cu.user_id', $user['id']);
        }

        return $builder->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Helper: Get accessible contacts for user
     */
    private function getAccessibleContacts(array $user): array
    {
        $builder = $this->contactModel->builder()
            ->select('contacts.*, clients.name as client_name');

        if ($user['role'] === 'agency') {
            $builder->where('contacts.agency_id', $user['agency_id']);
        } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
            $builder->join('client_users cu', 'cu.client_id = contacts.client_id')
                ->where('cu.user_id', $user['id']);
        }

        return $builder->join('clients', 'clients.id = contacts.client_id', 'left')
            ->where('contacts.is_active', true)
            ->orderBy('contacts.last_name', 'ASC')
            ->orderBy('contacts.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Helper: Get accessible projects for user
     */
    private function getAccessibleProjects(array $user): array
    {
        $builder = $this->projectModel->builder()
            ->select('projects.*, clients.name as client_name');

        if ($user['role'] === 'agency') {
            $builder->where('projects.agency_id', $user['agency_id']);
        } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
            $builder->join('client_users cu', 'cu.client_id = projects.client_id')
                ->where('cu.user_id', $user['id']);
        }

        return $builder->join('clients', 'clients.id = projects.client_id', 'left')
            ->where('projects.is_active', true)
            ->orderBy('projects.name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
