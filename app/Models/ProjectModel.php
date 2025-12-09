<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Project Model
 *
 * Manages project data with multi-agency isolation via PostgreSQL RLS.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): ProjectGuard provides fine-grained authorization
 *
 * Security Notes:
 * - All queries automatically filtered by RLS based on session variables
 * - Owner role bypasses RLS and sees all projects across agencies
 * - Agency users only see projects belonging to their agency
 */
class ProjectModel extends Model
{
    protected $table = 'projects';
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
        'client_id',
        'name',
        'description',
        'status',
        'start_date',
        'due_date',
        'budget',
        'hourly_rate',
        'is_billable',
        'is_active',
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'client_id' => 'required|max_length[36]',
        'name' => 'required|max_length[255]|min_length[2]',
        'description' => 'permit_empty',
        'status' => 'permit_empty|in_list[active,completed,on_hold,cancelled]',
        'start_date' => 'permit_empty|valid_date',
        'due_date' => 'permit_empty|valid_date',
        'budget' => 'permit_empty|decimal',
        'hourly_rate' => 'permit_empty|decimal',
        'is_billable' => 'permit_empty|in_list[0,1,true,false]',
        'is_active' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'client_id' => [
            'required' => 'Client is required for project',
        ],
        'name' => [
            'required' => 'Project name is required',
            'min_length' => 'Project name must be at least 2 characters',
            'max_length' => 'Project name cannot exceed 255 characters',
        ],
        'status' => [
            'in_list' => 'Invalid project status. Must be: active, completed, on_hold, or cancelled',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $beforeUpdate = [];
    protected $afterInsert = ['logProjectCreated'];
    protected $afterUpdate = ['logProjectUpdated'];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = ['logProjectDeleted'];

    /**
     * Generate UUID for new project records
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
     * Automatically set agency_id from session for new projects
     *
     * Security: Ensures projects are always created with correct agency_id
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
     * Get projects for current user's agency
     *
     * @param bool $activeOnly Return only active projects
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
     * Get project by ID with agency check
     *
     * @param string $id Project UUID
     * @return array|null
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get projects by client ID
     *
     * @param string $clientId Client UUID
     * @param bool $activeOnly Return only active projects
     * @return array
     */
    public function getByClientId(string $clientId, bool $activeOnly = true): array
    {
        $builder = $this->where('client_id', $clientId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Get projects by status
     *
     * @param string $status Project status
     * @return array
     */
    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Search projects by name or description
     *
     * @param string $term Search term
     * @param bool $activeOnly Limit to active projects
     * @return array
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $builder = $this->builder();

        $builder->groupStart()
            ->like('name', $term)
            ->orLike('description', $term)
            ->groupEnd();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /**
     * Get active projects count for current agency
     *
     * @return int
     */
    public function getActiveCount(): int
    {
        return $this->where('is_active', true)->countAllResults();
    }

    /**
     * Get project with related data (client, tasks, time entries)
     *
     * @param string $id Project UUID
     * @return array|null
     */
    public function getWithRelated(string $id): ?array
    {
        $project = $this->find($id);
        if (!$project) {
            return null;
        }

        // Get client info
        $clientModel = new ClientModel();
        $project['client'] = $clientModel->find($project['client_id']);

        // Get task count and stats
        $project['task_stats'] = $this->db->table('tasks')
            ->select('
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN status = "todo" THEN 1 ELSE 0 END) as todo_tasks,
                SUM(COALESCE(estimated_hours, 0)) as total_estimated_hours,
                SUM(COALESCE(actual_hours, 0)) as total_actual_hours
            ')
            ->where('project_id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        // Get time entry stats
        $project['time_stats'] = $this->db->table('time_entries')
            ->select('
                COUNT(*) as total_entries,
                SUM(COALESCE(hours, 0)) as total_hours,
                SUM(CASE WHEN is_billable = true THEN COALESCE(hours, 0) ELSE 0 END) as billable_hours
            ')
            ->where('project_id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        // Calculate budget vs actual
        if ($project['budget']) {
            $actualCost = ($project['time_stats']['billable_hours'] ?? 0) * ($project['hourly_rate'] ?? 0);
            $project['budget_stats'] = [
                'budget' => $project['budget'],
                'actual_cost' => $actualCost,
                'remaining' => $project['budget'] - $actualCost,
                'percentage_used' => $project['budget'] > 0 ? ($actualCost / $project['budget']) * 100 : 0,
            ];
        }

        return $project;
    }

    /**
     * Get project summary statistics
     *
     * @return array
     */
    public function getSummaryStats(): array
    {
        return [
            'total' => $this->countAllResults(false),
            'active' => $this->where('status', 'active')->countAllResults(false),
            'completed' => $this->where('status', 'completed')->countAllResults(false),
            'on_hold' => $this->where('status', 'on_hold')->countAllResults(false),
            'cancelled' => $this->where('status', 'cancelled')->countAllResults(false),
        ];
    }

    /**
     * Update project status
     *
     * @param string $id Project UUID
     * @param string $status New status
     * @return bool
     */
    public function updateStatus(string $id, string $status): bool
    {
        if (!in_array($status, ['active', 'completed', 'on_hold', 'cancelled'])) {
            return false;
        }

        return $this->update($id, ['status' => $status]);
    }

    /**
     * Toggle project active status
     *
     * @param string $id Project UUID
     * @return bool
     */
    public function toggleActive(string $id): bool
    {
        $project = $this->find($id);
        if (!$project) {
            return false;
        }

        return $this->update($id, ['is_active' => !$project['is_active']]);
    }

    /**
     * Validate project can be deleted
     *
     * Checks for dependent records (tasks, time entries) that would prevent deletion
     *
     * @param string $id Project UUID
     * @return array ['can_delete' => bool, 'blockers' => array]
     */
    public function validateDelete(string $id): array
    {
        $blockers = [];

        // Check for tasks
        $taskCount = $this->db->table('tasks')
            ->where('project_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($taskCount > 0) {
            $blockers[] = "Project has {$taskCount} active task(s)";
        }

        // Check for time entries
        $timeCount = $this->db->table('time_entries')
            ->where('project_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($timeCount > 0) {
            $blockers[] = "Project has {$timeCount} time entr" . ($timeCount === 1 ? 'y' : 'ies');
        }

        return [
            'can_delete' => empty($blockers),
            'blockers' => $blockers,
        ];
    }

    /**
     * Soft delete project
     *
     * @param string $id Project UUID
     * @param bool $purge Hard delete if true
     * @return bool
     */
    public function deleteProject(string $id, bool $purge = false): bool
    {
        return $purge ? $this->delete($id, true) : $this->delete($id);
    }

    /**
     * Restore soft-deleted project
     *
     * @param string $id Project UUID
     * @return bool
     */
    public function restore(string $id): bool
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Log project creation to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logProjectCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $projectName = $data['data']['name'] ?? 'Unknown Project';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'project',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created project: {$projectName}"
        );

        return $data;
    }

    /**
     * Log project updates to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logProjectUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $projectId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $project = $this->find($projectId);
        if (!$project) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $projectName = $project['name'];

        // Detect what changed
        $changes = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $field => $value) {
                if (isset($project[$field]) && $project[$field] != $value) {
                    $changes[] = $field;
                }
            }
        }

        // Determine event type and description
        if (isset($data['data']['status'])) {
            $description = "Project status changed to {$data['data']['status']}: {$projectName}";
            $eventType = 'status_changed';
        } elseif (isset($data['data']['is_active'])) {
            $status = $data['data']['is_active'] ? 'activated' : 'deactivated';
            $description = "Project {$status}: {$projectName}";
            $eventType = 'status_changed';
        } elseif (!empty($changes)) {
            $changedFields = implode(', ', $changes);
            $description = "Updated project: {$projectName} (changed: {$changedFields})";
            $eventType = 'updated';
        } else {
            return $data;
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'project',
            entityId: $projectId,
            eventType: $eventType,
            description: $description,
            metadata: !empty($changes) ? ['changed_fields' => $changes] : null
        );

        return $data;
    }

    /**
     * Log project deletion to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logProjectDeleted(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $projectId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $project = $this->withDeleted()->find($projectId);
        if (!$project) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $projectName = $project['name'];

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'project',
            entityId: $projectId,
            eventType: 'deleted',
            description: "Deleted project: {$projectName}",
            metadata: ['purge' => $data['purge'] ?? false]
        );

        return $data;
    }
}
