<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Time Entry Model
 *
 * Manages time tracking entries with multi-agency isolation via PostgreSQL RLS.
 * Supports timer functionality with start/stop tracking.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): Authorization in controllers
 *
 * Timer Logic:
 * - Start timer: Creates entry with start_time, no end_time
 * - Stop timer: Updates entry with end_time, calculates hours
 * - Manual entry: Can create with both times or just hours
 *
 * Security Notes:
 * - All queries automatically filtered by RLS based on session variables
 * - Owner role bypasses RLS and sees all time entries across agencies
 * - Agency users only see time entries belonging to their agency
 */
class TimeEntryModel extends Model
{
    protected $table = 'time_entries';
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
        'user_id',
        'project_id',
        'task_id',
        'description',
        'hours',
        'start_time',
        'end_time',
        'is_billable',
        'hourly_rate',
        'is_active',
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'user_id' => 'required|max_length[36]',
        'project_id' => 'required|max_length[36]',
        'task_id' => 'permit_empty|max_length[36]',
        'description' => 'permit_empty',
        'hours' => 'permit_empty|decimal',
        'start_time' => 'permit_empty|valid_date',
        'end_time' => 'permit_empty|valid_date',
        'is_billable' => 'permit_empty|in_list[0,1,true,false]',
        'hourly_rate' => 'permit_empty|decimal',
        'is_active' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'user_id' => [
            'required' => 'User is required for time entry',
        ],
        'project_id' => [
            'required' => 'Project is required for time entry',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $beforeUpdate = ['calculateHours'];
    protected $afterInsert = ['logTimeEntryCreated'];
    protected $afterUpdate = ['logTimeEntryUpdated'];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = ['logTimeEntryDeleted'];

    /**
     * Generate UUID for new time entry records
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
     * Automatically set agency_id from session for new time entries
     *
     * Security: Ensures time entries are always created with correct agency_id
     *
     * @param array $data
     * @return array
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
     * Calculate hours from start_time and end_time on update
     *
     * @param array $data
     * @return array
     */
    protected function calculateHours(array $data): array
    {
        // If both start and end times are set, calculate hours
        if (isset($data['data']['start_time']) && isset($data['data']['end_time'])) {
            $start = strtotime($data['data']['start_time']);
            $end = strtotime($data['data']['end_time']);

            if ($end > $start) {
                $seconds = $end - $start;
                $data['data']['hours'] = round($seconds / 3600, 2); // Convert to hours
            }
        }
        return $data;
    }

    /**
     * Start a timer for a project/task
     *
     * Creates a new time entry with start_time set to now
     *
     * @param string $userId User UUID
     * @param string $projectId Project UUID
     * @param string|null $taskId Task UUID (optional)
     * @param string|null $description Description (optional)
     * @return array|false New time entry or false on failure
     */
    public function startTimer(string $userId, string $projectId, ?string $taskId = null, ?string $description = null)
    {
        // Check if user already has a running timer
        $running = $this->getRunningTimer($userId);
        if ($running) {
            return false; // Only one timer can run at a time
        }

        $data = [
            'user_id' => $userId,
            'project_id' => $projectId,
            'task_id' => $taskId,
            'description' => $description,
            'start_time' => date('Y-m-d H:i:s'),
            'is_billable' => true,
        ];

        $id = $this->insert($data);
        return $id ? $this->find($id) : false;
    }

    /**
     * Stop the running timer for a user
     *
     * @param string $userId User UUID
     * @param string|null $description Optional description update
     * @return array|false Updated time entry or false on failure
     */
    public function stopTimer(string $userId, ?string $description = null)
    {
        $running = $this->getRunningTimer($userId);
        if (!$running) {
            return false; // No running timer to stop
        }

        $updateData = [
            'end_time' => date('Y-m-d H:i:s'),
        ];

        if ($description !== null) {
            $updateData['description'] = $description;
        }

        // Calculate hours
        $start = strtotime($running['start_time']);
        $end = strtotime($updateData['end_time']);
        $updateData['hours'] = round(($end - $start) / 3600, 2);

        $this->update($running['id'], $updateData);
        return $this->find($running['id']);
    }

    /**
     * Get currently running timer for a user
     *
     * @param string $userId User UUID
     * @return array|null Running time entry or null
     */
    public function getRunningTimer(string $userId): ?array
    {
        return $this->where('user_id', $userId)
            ->where('end_time', null)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get time entry by ID
     *
     * @param string $id Time entry UUID
     * @return array|null
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get time entries by project ID
     *
     * @param string $projectId Project UUID
     * @param bool $activeOnly Return only active entries
     * @return array
     */
    public function getByProjectId(string $projectId, bool $activeOnly = true): array
    {
        $builder = $this->where('project_id', $projectId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('start_time', 'DESC')->findAll();
    }

    /**
     * Get time entries by task ID
     *
     * @param string $taskId Task UUID
     * @param bool $activeOnly Return only active entries
     * @return array
     */
    public function getByTaskId(string $taskId, bool $activeOnly = true): array
    {
        $builder = $this->where('task_id', $taskId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('start_time', 'DESC')->findAll();
    }

    /**
     * Get time entries by user ID
     *
     * @param string $userId User UUID
     * @param bool $activeOnly Return only active entries
     * @return array
     */
    public function getByUserId(string $userId, bool $activeOnly = true): array
    {
        $builder = $this->where('user_id', $userId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('start_time', 'DESC')->findAll();
    }

    /**
     * Get time entries for date range
     *
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @param string|null $userId Optional user filter
     * @param string|null $projectId Optional project filter
     * @return array
     */
    public function getByDateRange(string $startDate, string $endDate, ?string $userId = null, ?string $projectId = null): array
    {
        $builder = $this->where('start_time >=', $startDate . ' 00:00:00')
            ->where('start_time <=', $endDate . ' 23:59:59');

        if ($userId) {
            $builder->where('user_id', $userId);
        }

        if ($projectId) {
            $builder->where('project_id', $projectId);
        }

        return $builder->orderBy('start_time', 'DESC')->findAll();
    }

    /**
     * Get billable hours summary for project
     *
     * @param string $projectId Project UUID
     * @return array
     */
    public function getBillableSummary(string $projectId): array
    {
        return $this->db->table('time_entries')
            ->select('
                SUM(CASE WHEN is_billable = true THEN COALESCE(hours, 0) ELSE 0 END) as billable_hours,
                SUM(CASE WHEN is_billable = false THEN COALESCE(hours, 0) ELSE 0 END) as non_billable_hours,
                SUM(COALESCE(hours, 0)) as total_hours,
                COUNT(*) as total_entries
            ')
            ->where('project_id', $projectId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
    }

    /**
     * Get user time tracking statistics
     *
     * @param string $userId User UUID
     * @param string|null $startDate Optional start date filter
     * @param string|null $endDate Optional end date filter
     * @return array
     */
    public function getUserStats(string $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $builder = $this->db->table('time_entries')
            ->select('
                COUNT(*) as total_entries,
                SUM(COALESCE(hours, 0)) as total_hours,
                SUM(CASE WHEN is_billable = true THEN COALESCE(hours, 0) ELSE 0 END) as billable_hours,
                AVG(COALESCE(hours, 0)) as avg_hours_per_entry
            ')
            ->where('user_id', $userId)
            ->where('deleted_at', null);

        if ($startDate) {
            $builder->where('start_time >=', $startDate . ' 00:00:00');
        }

        if ($endDate) {
            $builder->where('start_time <=', $endDate . ' 23:59:59');
        }

        return $builder->get()->getRowArray();
    }

    /**
     * Toggle time entry billable status
     *
     * @param string $id Time entry UUID
     * @return bool
     */
    public function toggleBillable(string $id): bool
    {
        $entry = $this->find($id);
        if (!$entry) {
            return false;
        }

        return $this->update($id, ['is_billable' => !$entry['is_billable']]);
    }

    /**
     * Update time entry hours manually
     *
     * @param string $id Time entry UUID
     * @param float $hours Hours worked
     * @return bool
     */
    public function updateHours(string $id, float $hours): bool
    {
        return $this->update($id, ['hours' => $hours]);
    }

    /**
     * Soft delete time entry
     *
     * @param string $id Time entry UUID
     * @param bool $purge Hard delete if true
     * @return bool
     */
    public function deleteTimeEntry(string $id, bool $purge = false): bool
    {
        return $purge ? $this->delete($id, true) : $this->delete($id);
    }

    /**
     * Restore soft-deleted time entry
     *
     * @param string $id Time entry UUID
     * @return bool
     */
    public function restore(string $id): bool
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Log time entry creation to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logTimeEntryCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $hours = $data['data']['hours'] ?? 'timer started';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'time_entry',
            entityId: $data['id'],
            eventType: 'created',
            description: "Time entry created: {$hours} hours"
        );

        return $data;
    }

    /**
     * Log time entry updates to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logTimeEntryUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $entryId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $entry = $this->find($entryId);
        if (!$entry) {
            return $data;
        }

        $timelineModel = new TimelineModel();

        // Determine if timer was stopped
        if (isset($data['data']['end_time']) && !$entry['end_time']) {
            $hours = $data['data']['hours'] ?? 0;
            $description = "Timer stopped: {$hours} hours logged";
            $eventType = 'timer_stopped';
        } else {
            $description = "Time entry updated";
            $eventType = 'updated';
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'time_entry',
            entityId: $entryId,
            eventType: $eventType,
            description: $description
        );

        return $data;
    }

    /**
     * Log time entry deletion to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logTimeEntryDeleted(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $entryId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $entry = $this->withDeleted()->find($entryId);
        if (!$entry) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $hours = $entry['hours'] ?? 0;

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'time_entry',
            entityId: $entryId,
            eventType: 'deleted',
            description: "Deleted time entry: {$hours} hours",
            metadata: ['purge' => $data['purge'] ?? false]
        );

        return $data;
    }
}
