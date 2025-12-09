<?php

namespace App\Models;

use CodeIgniter\Model;

class NoteModel extends Model
{
    protected $table = 'notes';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'agency_id',
        'user_id',
        'client_id',
        'contact_id',
        'project_id',
        'subject',
        'content',
        'is_pinned',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'content' => 'required|max_length[10000]',
        'subject' => 'permit_empty|max_length[255]',
        'user_id' => 'required|is_not_unique[users.id]',
    ];

    protected $validationMessages = [
        'content' => [
            'required' => 'Note content is required',
            'max_length' => 'Note content cannot exceed 10,000 characters',
        ],
        'subject' => [
            'max_length' => 'Subject cannot exceed 255 characters',
        ],
        'user_id' => [
            'required' => 'User ID is required',
            'is_not_unique' => 'Invalid user ID',
        ],
    ];

    // Callbacks
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $afterInsert = ['logNoteCreated'];
    protected $beforeUpdate = [];
    protected $afterUpdate = ['logNoteUpdated'];
    protected $afterDelete = ['logNoteDeleted'];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query('SELECT uuid_generate_v4() as id')->getRow()->id;
        }
        return $data;
    }

    /**
     * Auto-set agency_id from session if not provided
     */
    protected function setAgencyId(array $data): array
    {
        if (!isset($data['data']['agency_id'])) {
            $user = session()->get('user');
            if ($user && isset($user['agency_id'])) {
                $data['data']['agency_id'] = $user['agency_id'];
            }
        }
        return $data;
    }

    /**
     * Get notes for a specific client
     */
    public function getByClient(string $clientId, bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*, users.first_name as user_first_name, users.last_name as user_last_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->where('notes.client_id', $clientId);

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get notes for a specific contact
     */
    public function getByContact(string $contactId, bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*, users.first_name as user_first_name, users.last_name as user_last_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->where('notes.contact_id', $contactId);

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get notes for a specific project
     */
    public function getByProject(string $projectId, bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*, users.first_name as user_first_name, users.last_name as user_last_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->where('notes.project_id', $projectId);

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get all notes with entity information
     */
    public function getAllWithEntities(bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*,
                users.first_name as user_first_name,
                users.last_name as user_last_name,
                clients.name as client_name,
                clients.company as client_company,
                contacts.first_name as contact_first_name,
                contacts.last_name as contact_last_name,
                projects.name as project_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->join('contacts', 'contacts.id = notes.contact_id', 'left')
            ->join('projects', 'projects.id = notes.project_id', 'left');

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Search notes by content or subject
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*,
                users.first_name as user_first_name,
                users.last_name as user_last_name,
                clients.name as client_name,
                contacts.first_name as contact_first_name,
                contacts.last_name as contact_last_name,
                projects.name as project_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->join('contacts', 'contacts.id = notes.contact_id', 'left')
            ->join('projects', 'projects.id = notes.project_id', 'left');

        $builder->groupStart()
            ->like('notes.content', $term)
            ->orLike('notes.subject', $term)
            ->orLike('clients.name', $term)
            ->orLike('contacts.first_name', $term)
            ->orLike('contacts.last_name', $term)
            ->orLike('projects.name', $term)
            ->groupEnd();

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get pinned notes
     */
    public function getPinned(bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*,
                users.first_name as user_first_name,
                users.last_name as user_last_name,
                clients.name as client_name,
                contacts.first_name as contact_first_name,
                contacts.last_name as contact_last_name,
                projects.name as project_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->join('contacts', 'contacts.id = notes.contact_id', 'left')
            ->join('projects', 'projects.id = notes.project_id', 'left')
            ->where('notes.is_pinned', true);

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Toggle pin status
     */
    public function togglePin(string $id): bool
    {
        $note = $this->find($id);
        if (!$note) {
            return false;
        }

        return $this->update($id, [
            'is_pinned' => !$note['is_pinned'],
        ]);
    }

    /**
     * Get notes by user (author)
     */
    public function getByUser(string $userId, bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*,
                clients.name as client_name,
                contacts.first_name as contact_first_name,
                contacts.last_name as contact_last_name,
                projects.name as project_name')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->join('contacts', 'contacts.id = notes.contact_id', 'left')
            ->join('projects', 'projects.id = notes.project_id', 'left')
            ->where('notes.user_id', $userId);

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get recent notes (last N days)
     */
    public function getRecent(int $days = 7, bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('notes.*,
                users.first_name as user_first_name,
                users.last_name as user_last_name,
                clients.name as client_name,
                contacts.first_name as contact_first_name,
                contacts.last_name as contact_last_name,
                projects.name as project_name')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->join('contacts', 'contacts.id = notes.contact_id', 'left')
            ->join('projects', 'projects.id = notes.project_id', 'left')
            ->where('notes.created_at >=', date('Y-m-d H:i:s', strtotime("-{$days} days")));

        if ($activeOnly) {
            $builder->where('notes.deleted_at', null);
        }

        return $builder->orderBy('notes.is_pinned', 'DESC')
            ->orderBy('notes.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get entity type for a note
     */
    public function getEntityType(array $note): string
    {
        if (!empty($note['client_id'])) {
            return 'client';
        }
        if (!empty($note['contact_id'])) {
            return 'contact';
        }
        if (!empty($note['project_id'])) {
            return 'project';
        }
        return 'unknown';
    }

    /**
     * Get entity ID for a note
     */
    public function getEntityId(array $note): ?string
    {
        $type = $this->getEntityType($note);
        return $note[$type . '_id'] ?? null;
    }

    /**
     * Get entity name for a note
     */
    public function getEntityName(array $note): string
    {
        $type = $this->getEntityType($note);

        switch ($type) {
            case 'client':
                return $note['client_name'] ?? 'Unknown Client';
            case 'contact':
                return ($note['contact_first_name'] ?? '') . ' ' . ($note['contact_last_name'] ?? '');
            case 'project':
                return $note['project_name'] ?? 'Unknown Project';
            default:
                return 'Unknown Entity';
        }
    }

    /**
     * Restore soft-deleted note
     */
    public function restore(string $id): bool
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Validate delete operation
     */
    public function validateDelete(string $id): array
    {
        $note = $this->find($id);
        if (!$note) {
            return [
                'can_delete' => false,
                'blockers' => ['Note not found'],
            ];
        }

        // Notes can always be deleted (no dependent records to check)
        return [
            'can_delete' => true,
            'blockers' => [],
        ];
    }

    /**
     * Get note with full entity information
     */
    public function getWithEntity(string $id): ?array
    {
        return $this->builder()
            ->select('notes.*,
                users.first_name as user_first_name,
                users.last_name as user_last_name,
                users.email as user_email,
                clients.name as client_name,
                clients.company as client_company,
                contacts.first_name as contact_first_name,
                contacts.last_name as contact_last_name,
                contacts.email as contact_email,
                projects.name as project_name,
                projects.description as project_description')
            ->join('users', 'users.id = notes.user_id', 'left')
            ->join('clients', 'clients.id = notes.client_id', 'left')
            ->join('contacts', 'contacts.id = notes.contact_id', 'left')
            ->join('projects', 'projects.id = notes.project_id', 'left')
            ->where('notes.id', $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Log note creation to timeline
     */
    protected function logNoteCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $noteTitle = !empty($data['data']['subject'])
            ? $data['data']['subject']
            : substr($data['data']['content'], 0, 50) . '...';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'note',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created note: {$noteTitle}"
        );

        return $data;
    }

    /**
     * Log note updates to timeline
     */
    protected function logNoteUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $noteId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $note = $this->find($noteId);
        if (!$note) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $noteTitle = !empty($note['subject'])
            ? $note['subject']
            : substr($note['content'], 0, 50) . '...';

        // Detect what changed
        $changes = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $field => $value) {
                if (isset($note[$field]) && $note[$field] != $value) {
                    $changes[] = $field;
                }
            }
        }

        // Determine event type and description
        if (isset($data['data']['deleted_at']) && $data['data']['deleted_at'] === null) {
            $description = "Restored note: {$noteTitle}";
            $eventType = 'restored';
        } elseif (isset($data['data']['is_pinned'])) {
            // Toggle pin operation
            $isPinned = $data['data']['is_pinned'];
            $eventType = $isPinned ? 'pinned' : 'unpinned';
            $description = $isPinned
                ? "Pinned note: {$noteTitle}"
                : "Unpinned note: {$noteTitle}";
        } elseif (!empty($changes)) {
            $changedFields = implode(', ', $changes);
            $description = "Updated note: {$noteTitle} (changed: {$changedFields})";
            $eventType = 'updated';
        } else {
            return $data;
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'note',
            entityId: $noteId,
            eventType: $eventType,
            description: $description,
            metadata: !empty($changes) ? ['changed_fields' => $changes] : null
        );

        return $data;
    }

    /**
     * Log note deletion to timeline
     */
    protected function logNoteDeleted(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $noteId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $note = $this->withDeleted()->find($noteId);
        if (!$note) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $noteTitle = !empty($note['subject'])
            ? $note['subject']
            : substr($note['content'], 0, 50) . '...';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'note',
            entityId: $noteId,
            eventType: 'deleted',
            description: "Deleted note: {$noteTitle}",
            metadata: ['purge' => $data['purge'] ?? false]
        );

        return $data;
    }
}
