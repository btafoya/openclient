<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Client Model
 *
 * Manages client (customer company) data with multi-agency isolation via PostgreSQL RLS.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): ClientGuard provides fine-grained authorization
 *
 * Security Notes:
 * - All queries automatically filtered by RLS based on session variables
 * - Owner role bypasses RLS and sees all clients across agencies
 * - Agency users only see clients belonging to their agency
 */
class ClientModel extends Model
{
    protected $table = 'clients';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false; // UUID primary key
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    /**
     * Allowed fields for mass assignment
     *
     * Note: agency_id is intentionally excluded from direct mass assignment
     * and should be set explicitly in controllers using setAgency() method
     */
    protected $allowedFields = [
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
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'name' => 'required|max_length[255]|min_length[2]',
        'email' => 'permit_empty|valid_email|max_length[255]',
        'phone' => 'permit_empty|max_length[50]',
        'company' => 'permit_empty|max_length[255]',
        'address' => 'permit_empty',
        'city' => 'permit_empty|max_length[100]',
        'state' => 'permit_empty|max_length[50]',
        'postal_code' => 'permit_empty|max_length[20]',
        'country' => 'permit_empty|max_length[100]',
        'notes' => 'permit_empty',
        'is_active' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Client name is required',
            'min_length' => 'Client name must be at least 2 characters',
            'max_length' => 'Client name cannot exceed 255 characters',
        ],
        'email' => [
            'valid_email' => 'Please provide a valid email address',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $beforeUpdate = [];
    protected $afterInsert = ['logClientCreated'];
    protected $afterUpdate = ['logClientUpdated'];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = ['logClientDeleted'];

    /**
     * Generate UUID for new client records
     *
     * @param array $data
     * @return array
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT uuid_generate_v4()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Automatically set agency_id from session for new clients
     *
     * Security: Ensures clients are always created with correct agency_id
     * Owner role can override by explicitly setting agency_id before insert
     *
     * @param array $data
     * @return array
     */
    protected function setAgencyId(array $data): array
    {
        // If agency_id not explicitly set, use current user's agency
        if (!isset($data['data']['agency_id'])) {
            $user = session()->get('user');
            if ($user && isset($user['agency_id'])) {
                $data['data']['agency_id'] = $user['agency_id'];
            }
        }
        return $data;
    }

    /**
     * Get clients for current user's agency
     *
     * Note: RLS automatically filters by agency, this is just explicit for clarity
     *
     * @param bool $activeOnly Return only active clients
     * @return array
     */
    public function getForCurrentAgency(bool $activeOnly = true): array
    {
        $builder = $this->builder();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /**
     * Get client by ID with agency check
     *
     * @param string $id Client UUID
     * @return array|null
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Search clients by name, email, or company
     *
     * @param string $term Search term
     * @param bool $activeOnly Limit to active clients
     * @return array
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $builder = $this->builder();

        $builder->groupStart()
            ->like('name', $term)
            ->orLike('email', $term)
            ->orLike('company', $term)
            ->groupEnd();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /**
     * Get active clients count for current agency
     *
     * @return int
     */
    public function getActiveCount(): int
    {
        return $this->where('is_active', true)->countAllResults();
    }

    /**
     * Soft delete client
     *
     * @param string $id Client UUID
     * @param bool $purge Hard delete if true
     * @return bool
     */
    public function deleteClient(string $id, bool $purge = false): bool
    {
        return $purge ? $this->delete($id, true) : $this->delete($id);
    }

    /**
     * Restore soft-deleted client
     *
     * @param string $id Client UUID
     * @return bool
     */
    public function restore(string $id): bool
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Toggle client active status
     *
     * @param string $id Client UUID
     * @return bool
     */
    public function toggleActive(string $id): bool
    {
        $client = $this->find($id);
        if (!$client) {
            return false;
        }

        return $this->update($id, ['is_active' => !$client['is_active']]);
    }

    /**
     * Get clients with project count
     *
     * @param bool $activeOnly
     * @return array
     */
    public function getWithProjectCount(bool $activeOnly = true): array
    {
        $builder = $this->db->table('clients');
        $builder->select('clients.*, COUNT(projects.id) as project_count');
        $builder->join('projects', 'projects.client_id = clients.id', 'left');
        $builder->groupBy('clients.id');

        if ($activeOnly) {
            $builder->where('clients.is_active', true);
        }

        $builder->where('clients.deleted_at', null);
        $builder->orderBy('clients.name', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Validate client can be deleted
     *
     * Checks for dependent records (projects, invoices) that would prevent deletion
     *
     * @param string $id Client UUID
     * @return array ['can_delete' => bool, 'blockers' => array]
     */
    public function validateDelete(string $id): array
    {
        $blockers = [];

        // Check for projects
        $projectCount = $this->db->table('projects')
            ->where('client_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($projectCount > 0) {
            $blockers[] = "Client has {$projectCount} active project(s)";
        }

        // Check for invoices
        $invoiceCount = $this->db->table('invoices')
            ->where('client_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($invoiceCount > 0) {
            $blockers[] = "Client has {$invoiceCount} invoice(s)";
        }

        return [
            'can_delete' => empty($blockers),
            'blockers' => $blockers,
        ];
    }

    /**
     * Log client creation to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logClientCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $clientName = $data['data']['name'] ?? 'Unknown Client';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'client',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created client: {$clientName}"
        );

        return $data;
    }

    /**
     * Log client updates to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logClientUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $clientId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $client = $this->find($clientId);
        if (!$client) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $clientName = $client['name'];

        // Detect what changed
        $changes = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $field => $value) {
                if (isset($client[$field]) && $client[$field] != $value) {
                    $changes[] = $field;
                }
            }
        }

        // Determine event type and description
        if (isset($data['data']['deleted_at']) && $data['data']['deleted_at'] === null) {
            // Restore operation
            $description = "Restored client: {$clientName}";
            $eventType = 'restored';
        } elseif (isset($data['data']['is_active'])) {
            // Toggle active operation
            $status = $data['data']['is_active'] ? 'activated' : 'deactivated';
            $description = "Client {$status}: {$clientName}";
            $eventType = 'status_changed';
        } elseif (!empty($changes)) {
            // Regular update
            $changedFields = implode(', ', $changes);
            $description = "Updated client: {$clientName} (changed: {$changedFields})";
            $eventType = 'updated';
        } else {
            // No significant changes, skip logging
            return $data;
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'client',
            entityId: $clientId,
            eventType: $eventType,
            description: $description,
            metadata: !empty($changes) ? ['changed_fields' => $changes] : null
        );

        return $data;
    }

    /**
     * Log client deletion to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logClientDeleted(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $clientId = is_array($data['id']) ? $data['id'][0] : $data['id'];

        // Get client name before deletion (use withDeleted to access soft-deleted record)
        $client = $this->withDeleted()->find($clientId);
        if (!$client) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $clientName = $client['name'];

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'client',
            entityId: $clientId,
            eventType: 'deleted',
            description: "Deleted client: {$clientName}",
            metadata: ['purge' => $data['purge'] ?? false]
        );

        return $data;
    }
}
