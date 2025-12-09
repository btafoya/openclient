<?php

namespace App\Controllers\Timeline;

use App\Controllers\BaseController;
use App\Models\TimelineModel;
use App\Models\ClientModel;
use App\Models\ContactModel;
use App\Models\ProjectModel;
use App\Models\NoteModel;
use App\Domain\Timeline\Authorization\TimelineGuard;
use CodeIgniter\HTTP\ResponseInterface;

class TimelineController extends BaseController
{
    protected TimelineModel $timelineModel;
    protected ClientModel $clientModel;
    protected ContactModel $contactModel;
    protected ProjectModel $projectModel;
    protected NoteModel $noteModel;
    protected TimelineGuard $guard;

    public function __construct()
    {
        $this->timelineModel = new TimelineModel();
        $this->clientModel = new ClientModel();
        $this->contactModel = new ContactModel();
        $this->projectModel = new ProjectModel();
        $this->noteModel = new NoteModel();
        $this->guard = new TimelineGuard();
    }

    /**
     * Display timeline list with filtering
     */
    public function index(): string
    {
        $user = session()->get('user');

        // Get filter parameters
        $filters = [
            'entity_type' => $this->request->getGet('entity_type'),
            'event_type' => $this->request->getGet('event_type'),
            'user_id' => $this->request->getGet('user_id'),
            'search' => $this->request->getGet('search'),
        ];

        // Get timeline entries
        $timeline = $this->timelineModel->getRecentWithDetails(100);

        // Filter by user permissions
        $timeline = $this->guard->filterViewableTimeline($user, $timeline);

        // Apply additional filters
        if (!empty($filters['entity_type'])) {
            $timeline = array_filter($timeline, fn($entry) => $entry['entity_type'] === $filters['entity_type']);
        }

        if (!empty($filters['event_type'])) {
            $timeline = array_filter($timeline, fn($entry) => $entry['event_type'] === $filters['event_type']);
        }

        if (!empty($filters['user_id'])) {
            $timeline = array_filter($timeline, fn($entry) => $entry['user_id'] === $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $timeline = array_filter($timeline, function ($entry) use ($filters) {
                return stripos($entry['description'], $filters['search']) !== false;
            });
        }

        // Re-index array after filtering
        $timeline = array_values($timeline);

        // Get permissions
        $permissions = [
            'canCreate' => $this->guard->canCreate($user),
        ];

        return view('timeline/index', [
            'title' => 'Timeline',
            'timeline' => $timeline,
            'filters' => $filters,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Display single timeline entry
     */
    public function show(string $id): string|ResponseInterface
    {
        $user = session()->get('user');

        $entry = $this->timelineModel->find($id);

        if (!$entry) {
            return redirect()->to('/timeline')->with('error', 'Timeline entry not found.');
        }

        // Check permissions
        if (!$this->guard->canView($user, $entry)) {
            return redirect()->to('/timeline')->with('error', 'You do not have permission to view this timeline entry.');
        }

        // Get entity details
        $entry['entity_name'] = $this->timelineModel->getEntityName($entry['entity_type'], $entry['entity_id']);
        $entry['entity_url'] = $this->timelineModel->getEntityUrl($entry['entity_type'], $entry['entity_id']);

        // Get permissions
        $permissions = $this->guard->getPermissions($user, $entry);

        return view('timeline/show', [
            'title' => 'Timeline Entry',
            'entry' => $entry,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Display manual timeline entry creation form
     */
    public function create(): string|ResponseInterface
    {
        $user = session()->get('user');

        // Check permissions
        if (!$this->guard->canCreate($user)) {
            return redirect()->to('/timeline')->with('error', 'You do not have permission to create timeline entries.');
        }

        // Get accessible entities for dropdowns
        $clients = $this->getAccessibleClients($user);
        $contacts = $this->getAccessibleContacts($user);
        $projects = $this->getAccessibleProjects($user);
        $notes = $this->getAccessibleNotes($user);

        return view('timeline/create', [
            'title' => 'Create Timeline Entry',
            'clients' => $clients,
            'contacts' => $contacts,
            'projects' => $projects,
            'notes' => $notes,
            'validation' => session()->get('validation') ?? null,
        ]);
    }

    /**
     * Store manual timeline entry
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Check permissions
        if (!$this->guard->canCreate($user)) {
            return redirect()->to('/timeline')->with('error', 'You do not have permission to create timeline entries.');
        }

        $data = $this->request->getPost([
            'entity_type',
            'entity_id',
            'event_type',
            'description',
            'metadata',
        ]);

        // Parse metadata JSON if provided
        if (!empty($data['metadata'])) {
            $metadata = json_decode($data['metadata'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->withInput()
                    ->with('error', 'Invalid JSON in metadata field.');
            }
            $data['metadata'] = $metadata;
        } else {
            $data['metadata'] = null;
        }

        // Add user_id
        $data['user_id'] = $user['id'];

        // Validate and insert
        if (!$this->timelineModel->save($data)) {
            return redirect()->back()->withInput()
                ->with('validation', $this->timelineModel->errors());
        }

        $entryId = $this->timelineModel->getInsertID();

        return redirect()->to('/timeline/' . $entryId)
            ->with('success', 'Timeline entry created successfully.');
    }

    /**
     * Soft delete timeline entry (GDPR compliance)
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $entry = $this->timelineModel->find($id);

        if (!$entry) {
            return $this->response->setJSON([
                'error' => 'Timeline entry not found.',
            ])->setStatusCode(404);
        }

        // Check permissions
        if (!$this->guard->canDelete($user, $entry)) {
            return $this->response->setJSON([
                'error' => 'You do not have permission to delete this timeline entry.',
            ])->setStatusCode(403);
        }

        // Soft delete
        $this->timelineModel->delete($id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'message' => 'Timeline entry deleted successfully.',
            ]);
        }

        return redirect()->to('/timeline')
            ->with('success', 'Timeline entry deleted successfully.');
    }

    /**
     * API endpoint to get timeline entries
     */
    public function apiIndex(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filter parameters
        $filters = [
            'entity_type' => $this->request->getGet('entity_type'),
            'event_type' => $this->request->getGet('event_type'),
            'user_id' => $this->request->getGet('user_id'),
            'search' => $this->request->getGet('search'),
        ];

        $limit = (int) $this->request->getGet('limit') ?: 50;

        // Get timeline entries
        $timeline = $this->timelineModel->getForAgency($limit, $filters);

        // Filter by user permissions
        $timeline = $this->guard->filterViewableTimeline($user, $timeline);

        // Add entity details
        $timeline = array_map(function ($entry) {
            $entry['entity_name'] = $this->timelineModel->getEntityName($entry['entity_type'], $entry['entity_id']);
            $entry['entity_url'] = $this->timelineModel->getEntityUrl($entry['entity_type'], $entry['entity_id']);
            return $entry;
        }, $timeline);

        return $this->response->setJSON($timeline);
    }

    /**
     * API endpoint to get single timeline entry
     */
    public function apiShow(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $entry = $this->timelineModel->find($id);

        if (!$entry) {
            return $this->response->setJSON([
                'error' => 'Timeline entry not found.',
            ])->setStatusCode(404);
        }

        // Check permissions
        if (!$this->guard->canView($user, $entry)) {
            return $this->response->setJSON([
                'error' => 'You do not have permission to view this timeline entry.',
            ])->setStatusCode(403);
        }

        // Add entity details
        $entry['entity_name'] = $this->timelineModel->getEntityName($entry['entity_type'], $entry['entity_id']);
        $entry['entity_url'] = $this->timelineModel->getEntityUrl($entry['entity_type'], $entry['entity_id']);

        return $this->response->setJSON($entry);
    }

    /**
     * Get statistics API endpoint
     */
    public function apiStatistics(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filter parameters
        $filters = [
            'start_date' => $this->request->getGet('start_date'),
            'end_date' => $this->request->getGet('end_date'),
            'entity_type' => $this->request->getGet('entity_type'),
            'user_id' => $this->request->getGet('user_id'),
        ];

        $statistics = $this->timelineModel->getStatistics($filters);

        return $this->response->setJSON($statistics);
    }

    // Helper methods to get accessible entities

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

    private function getAccessibleContacts(array $user): array
    {
        $builder = $this->contactModel->builder();

        if ($user['role'] === 'agency') {
            $builder->where('contacts.agency_id', $user['agency_id']);
        } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
            $builder->join('client_users cu', 'cu.client_id = contacts.client_id')
                ->where('cu.user_id', $user['id']);
        }

        return $builder->select('contacts.*, clients.name as client_name')
            ->join('clients', 'clients.id = contacts.client_id', 'left')
            ->orderBy('contacts.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function getAccessibleProjects(array $user): array
    {
        // Projects table doesn't exist yet, return empty array
        return [];
    }

    private function getAccessibleNotes(array $user): array
    {
        $builder = $this->noteModel->builder();

        if ($user['role'] === 'agency') {
            $builder->where('notes.agency_id', $user['agency_id']);
        } elseif (in_array($user['role'], ['direct_client', 'end_client'])) {
            // Notes can be attached to clients, contacts, or projects
            // Need to check access through parent entities
            $builder->where(function ($query) use ($user) {
                // Client notes
                $query->orWhere('notes.client_id IN (SELECT client_id FROM client_users WHERE user_id = ' . $this->noteModel->db->escape($user['id']) . ')');
                // Contact notes (through client)
                $query->orWhere('notes.contact_id IN (SELECT id FROM contacts WHERE client_id IN (SELECT client_id FROM client_users WHERE user_id = ' . $this->noteModel->db->escape($user['id']) . '))');
            });
        }

        return $builder->select('notes.*, clients.name as client_name')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }
}
