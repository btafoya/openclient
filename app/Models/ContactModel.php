<?php

namespace App\Models;

use CodeIgniter\Model;

class ContactModel extends Model
{
    protected $table = 'contacts';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'client_id',
        'agency_id',
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
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'client_id' => 'permit_empty|max_length[36]',
        'first_name' => 'required|max_length[100]',
        'last_name' => 'required|max_length[100]',
        'email' => 'permit_empty|valid_email|max_length[255]',
        'phone' => 'permit_empty|max_length[50]',
        'mobile' => 'permit_empty|max_length[50]',
        'job_title' => 'permit_empty|max_length[100]',
        'department' => 'permit_empty|max_length[100]',
        'is_primary' => 'permit_empty|in_list[0,1]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages = [
        'first_name' => [
            'required' => 'First name is required.',
            'max_length' => 'First name cannot exceed 100 characters.',
        ],
        'last_name' => [
            'required' => 'Last name is required.',
            'max_length' => 'Last name cannot exceed 100 characters.',
        ],
        'email' => [
            'valid_email' => 'Please provide a valid email address.',
        ],
    ];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'handlePrimaryContact'];
    protected $afterInsert = ['logContactCreated'];
    protected $beforeUpdate = ['handlePrimaryContact'];
    protected $afterUpdate = ['logContactUpdated'];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = ['logContactDeleted'];

    /**
     * Generate UUID for new contact
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = uuid_create(UUID_TYPE_RANDOM);
        }

        return $data;
    }

    /**
     * Automatically set agency_id from session
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
     * Handle primary contact logic - only one primary contact per client
     */
    protected function handlePrimaryContact(array $data): array
    {
        // If setting as primary contact
        if (isset($data['data']['is_primary']) && $data['data']['is_primary'] == true) {
            // Get client_id (from data or existing record for updates)
            $clientId = $data['data']['client_id'] ?? null;

            if (!$clientId && isset($data['id'])) {
                // For updates, get client_id from existing record
                $existing = $this->find($data['id'][0] ?? $data['id']);
                $clientId = $existing['client_id'] ?? null;
            }

            if ($clientId) {
                // Unset is_primary for all other contacts of this client
                $this->builder()
                    ->where('client_id', $clientId)
                    ->where('id !=', $data['data']['id'] ?? ($data['id'][0] ?? $data['id'] ?? 'none'))
                    ->update(['is_primary' => false]);
            }
        }

        return $data;
    }

    /**
     * Search contacts by name, email, job title, or phone
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $builder = $this->builder();

        $builder->groupStart()
            ->like('first_name', $term)
            ->orLike('last_name', $term)
            ->orLike('email', $term)
            ->orLike('job_title', $term)
            ->orLike('phone', $term)
            ->orLike('mobile', $term)
            ->groupEnd();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('last_name', 'ASC')
            ->orderBy('first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get contacts for a specific client
     */
    public function getByClient(string $clientId, bool $activeOnly = true): array
    {
        $builder = $this->builder();
        $builder->where('client_id', $clientId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('is_primary', 'DESC')
            ->orderBy('last_name', 'ASC')
            ->orderBy('first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get primary contact for a client
     */
    public function getPrimaryContact(string $clientId): ?array
    {
        return $this->builder()
            ->where('client_id', $clientId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->get()
            ->getRowArray();
    }

    /**
     * Get count of active contacts
     */
    public function getActiveCount(): int
    {
        return $this->builder()
            ->where('is_active', true)
            ->countAllResults();
    }

    /**
     * Get count of contacts for a specific client
     */
    public function getClientContactCount(string $clientId): int
    {
        return $this->builder()
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->countAllResults();
    }

    /**
     * Toggle active status
     */
    public function toggleActive(string $id): bool
    {
        $contact = $this->find($id);

        if (!$contact) {
            return false;
        }

        $newStatus = !$contact['is_active'];

        return $this->update($id, ['is_active' => $newStatus]);
    }

    /**
     * Validate if contact can be deleted
     * Contacts can be soft-deleted without restrictions
     */
    public function validateDelete(string $id): array
    {
        $contact = $this->find($id);

        if (!$contact) {
            return [
                'can_delete' => false,
                'blockers' => ['Contact not found'],
            ];
        }

        // Contacts can always be soft-deleted
        // No blocking relationships for contacts
        return [
            'can_delete' => true,
            'blockers' => [],
        ];
    }

    /**
     * Restore soft-deleted contact
     */
    public function restore(string $id): bool
    {
        return $this->builder()
            ->where('id', $id)
            ->update(['deleted_at' => null]);
    }

    /**
     * Get full name for a contact
     */
    public function getFullName(array $contact): string
    {
        return trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
    }

    /**
     * Get contact with client information
     */
    public function getWithClient(string $id): ?array
    {
        return $this->builder()
            ->select('contacts.*, clients.name as client_name, clients.company as client_company')
            ->join('clients', 'clients.id = contacts.client_id', 'left')
            ->where('contacts.id', $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Get all contacts with client information
     */
    public function getAllWithClients(bool $activeOnly = true): array
    {
        $builder = $this->builder()
            ->select('contacts.*, clients.name as client_name, clients.company as client_company')
            ->join('clients', 'clients.id = contacts.client_id', 'left');

        if ($activeOnly) {
            $builder->where('contacts.is_active', true);
        }

        return $builder->orderBy('contacts.last_name', 'ASC')
            ->orderBy('contacts.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Log contact creation to timeline
     */
    protected function logContactCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $contactName = $this->getFullName($data['data']);

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'contact',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created contact: {$contactName}"
        );

        return $data;
    }

    /**
     * Log contact updates to timeline
     */
    protected function logContactUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $contactId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $contact = $this->find($contactId);
        if (!$contact) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $contactName = $this->getFullName($contact);

        // Detect what changed
        $changes = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $field => $value) {
                if (isset($contact[$field]) && $contact[$field] != $value) {
                    $changes[] = $field;
                }
            }
        }

        // Determine event type and description
        if (isset($data['data']['deleted_at']) && $data['data']['deleted_at'] === null) {
            $description = "Restored contact: {$contactName}";
            $eventType = 'restored';
        } elseif (isset($data['data']['is_active'])) {
            $status = $data['data']['is_active'] ? 'activated' : 'deactivated';
            $description = "Contact {$status}: {$contactName}";
            $eventType = 'status_changed';
        } elseif (!empty($changes)) {
            $changedFields = implode(', ', $changes);
            $description = "Updated contact: {$contactName} (changed: {$changedFields})";
            $eventType = 'updated';
        } else {
            return $data;
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'contact',
            entityId: $contactId,
            eventType: $eventType,
            description: $description,
            metadata: !empty($changes) ? ['changed_fields' => $changes] : null
        );

        return $data;
    }

    /**
     * Log contact deletion to timeline
     */
    protected function logContactDeleted(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $contactId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $contact = $this->withDeleted()->find($contactId);
        if (!$contact) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $contactName = $this->getFullName($contact);

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'contact',
            entityId: $contactId,
            eventType: 'deleted',
            description: "Deleted contact: {$contactName}",
            metadata: ['purge' => $data['purge'] ?? false]
        );

        return $data;
    }
}
