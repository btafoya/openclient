<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Task Model
 *
 * Manages task data with multi-agency isolation via PostgreSQL RLS.
 * Supports Kanban board workflow with drag-and-drop ordering.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): TaskGuard provides fine-grained authorization
 *
 * Status Workflow:
 * - todo → in_progress → completed
 * - blocked (can transition from any status)
 *
 * Security Notes:
 * - All queries automatically filtered by RLS based on session variables
 * - Owner role bypasses RLS and sees all tasks across agencies
 * - Agency users only see tasks belonging to their agency
 */
class TaskModel extends Model
{
    protected $table = 'tasks';
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
        'project_id',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'estimated_hours',
        'actual_hours',
        'sort_order',
        'is_active',
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation rules
    protected $validationRules = [
        'project_id' => 'required|max_length[36]',
        'assigned_to' => 'permit_empty|max_length[36]',
        'title' => 'required|max_length[255]|min_length[2]',
        'description' => 'permit_empty',
        'status' => 'permit_empty|in_list[todo,in_progress,completed,blocked]',
        'priority' => 'permit_empty|in_list[low,medium,high,urgent]',
        'due_date' => 'permit_empty|valid_date',
        'estimated_hours' => 'permit_empty|decimal',
        'actual_hours' => 'permit_empty|decimal',
        'sort_order' => 'permit_empty|integer',
        'is_active' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'project_id' => [
            'required' => 'Project is required for task',
        ],
        'title' => [
            'required' => 'Task title is required',
            'min_length' => 'Task title must be at least 2 characters',
            'max_length' => 'Task title cannot exceed 255 characters',
        ],
        'status' => [
            'in_list' => 'Invalid task status. Must be: todo, in_progress, completed, or blocked',
        ],
        'priority' => [
            'in_list' => 'Invalid priority. Must be: low, medium, high, or urgent',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setSortOrder'];
    protected $beforeUpdate = [];
    protected $afterInsert = ['logTaskCreated'];
    protected $afterUpdate = ['logTaskUpdated'];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = ['logTaskDeleted'];

    /**
     * Generate UUID for new task records
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
     * Automatically set agency_id from session for new tasks
     *
     * Security: Ensures tasks are always created with correct agency_id
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
     * Set sort order for new tasks (last position in column)
     *
     * @param array $data
     * @return array
     */
    protected function setSortOrder(array $data): array
    {
        if (!isset($data['data']['sort_order']) && isset($data['data']['project_id'])) {
            $status = $data['data']['status'] ?? 'todo';
            $maxOrder = $this->db->table('tasks')
                ->selectMax('sort_order')
                ->where('project_id', $data['data']['project_id'])
                ->where('status', $status)
                ->where('deleted_at', null)
                ->get()
                ->getRow()
                ->sort_order ?? 0;

            $data['data']['sort_order'] = $maxOrder + 1;
        }
        return $data;
    }

    /**
     * Get tasks for current user's agency
     *
     * @param bool $activeOnly Return only active tasks
     * @return array
     */
    public function getForCurrentAgency(bool $activeOnly = true): array
    {
        $builder = $this->builder();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('sort_order', 'ASC')->get()->getResultArray();
    }

    /**
     * Get task by ID with agency check
     *
     * @param string $id Task UUID
     * @return array|null
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get tasks by project ID (Kanban board data)
     *
     * @param string $projectId Project UUID
     * @param bool $activeOnly Return only active tasks
     * @return array Grouped by status
     */
    public function getByProjectId(string $projectId, bool $activeOnly = true): array
    {
        $builder = $this->where('project_id', $projectId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        $tasks = $builder->orderBy('sort_order', 'ASC')->findAll();

        // Group tasks by status for Kanban board
        $grouped = [
            'todo' => [],
            'in_progress' => [],
            'completed' => [],
            'blocked' => [],
        ];

        foreach ($tasks as $task) {
            $status = $task['status'] ?? 'todo';
            if (isset($grouped[$status])) {
                $grouped[$status][] = $task;
            }
        }

        return $grouped;
    }

    /**
     * Get tasks assigned to specific user
     *
     * @param string $userId User UUID
     * @param bool $activeOnly Return only active tasks
     * @return array
     */
    public function getByAssignedUser(string $userId, bool $activeOnly = true): array
    {
        $builder = $this->where('assigned_to', $userId);

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('due_date', 'ASC')->findAll();
    }

    /**
     * Get tasks by status
     *
     * @param string $status Task status
     * @param string|null $projectId Optional project filter
     * @return array
     */
    public function getByStatus(string $status, ?string $projectId = null): array
    {
        $builder = $this->where('status', $status);

        if ($projectId) {
            $builder->where('project_id', $projectId);
        }

        return $builder->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Search tasks by title or description
     *
     * @param string $term Search term
     * @param bool $activeOnly Limit to active tasks
     * @return array
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $builder = $this->builder();

        $builder->groupStart()
            ->like('title', $term)
            ->orLike('description', $term)
            ->groupEnd();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('sort_order', 'ASC')->get()->getResultArray();
    }

    /**
     * Update task status with workflow validation
     *
     * @param string $id Task UUID
     * @param string $status New status
     * @return bool
     */
    public function updateStatus(string $id, string $status): bool
    {
        if (!in_array($status, ['todo', 'in_progress', 'completed', 'blocked'])) {
            return false;
        }

        return $this->update($id, ['status' => $status]);
    }

    /**
     * Update task sort order (for Kanban drag-and-drop)
     *
     * @param string $id Task UUID
     * @param int $newOrder New sort order
     * @param string|null $newStatus New status (if moving between columns)
     * @return bool
     */
    public function updateSortOrder(string $id, int $newOrder, ?string $newStatus = null): bool
    {
        $task = $this->find($id);
        if (!$task) {
            return false;
        }

        $updateData = ['sort_order' => $newOrder];

        // If moving to different status column, update status
        if ($newStatus && $newStatus !== $task['status']) {
            $updateData['status'] = $newStatus;
        }

        return $this->update($id, $updateData);
    }

    /**
     * Reorder tasks after drag-and-drop operation
     *
     * @param string $projectId Project UUID
     * @param string $status Status column
     * @param array $taskIds Array of task UUIDs in new order
     * @return bool
     */
    public function reorderTasks(string $projectId, string $status, array $taskIds): bool
    {
        $this->db->transStart();

        foreach ($taskIds as $index => $taskId) {
            $this->update($taskId, [
                'sort_order' => $index,
                'status' => $status,
            ]);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Assign task to user
     *
     * @param string $id Task UUID
     * @param string|null $userId User UUID (null to unassign)
     * @return bool
     */
    public function assignToUser(string $id, ?string $userId): bool
    {
        return $this->update($id, ['assigned_to' => $userId]);
    }

    /**
     * Update task hours (estimated or actual)
     *
     * @param string $id Task UUID
     * @param float|null $estimatedHours Estimated hours
     * @param float|null $actualHours Actual hours
     * @return bool
     */
    public function updateHours(string $id, ?float $estimatedHours = null, ?float $actualHours = null): bool
    {
        $updateData = [];

        if ($estimatedHours !== null) {
            $updateData['estimated_hours'] = $estimatedHours;
        }

        if ($actualHours !== null) {
            $updateData['actual_hours'] = $actualHours;
        }

        if (empty($updateData)) {
            return false;
        }

        return $this->update($id, $updateData);
    }

    /**
     * Get task statistics for project
     *
     * @param string $projectId Project UUID
     * @return array
     */
    public function getProjectStats(string $projectId): array
    {
        return $this->db->table('tasks')
            ->select('
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN status = "todo" THEN 1 ELSE 0 END) as todo_tasks,
                SUM(CASE WHEN status = "blocked" THEN 1 ELSE 0 END) as blocked_tasks,
                SUM(COALESCE(estimated_hours, 0)) as total_estimated_hours,
                SUM(COALESCE(actual_hours, 0)) as total_actual_hours
            ')
            ->where('project_id', $projectId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
    }

    /**
     * Get overdue tasks
     *
     * @param string|null $projectId Optional project filter
     * @return array
     */
    public function getOverdueTasks(?string $projectId = null): array
    {
        $builder = $this->where('due_date <', date('Y-m-d H:i:s'))
            ->whereNotIn('status', ['completed'])
            ->where('is_active', true);

        if ($projectId) {
            $builder->where('project_id', $projectId);
        }

        return $builder->orderBy('due_date', 'ASC')->findAll();
    }

    /**
     * Toggle task active status
     *
     * @param string $id Task UUID
     * @return bool
     */
    public function toggleActive(string $id): bool
    {
        $task = $this->find($id);
        if (!$task) {
            return false;
        }

        return $this->update($id, ['is_active' => !$task['is_active']]);
    }

    /**
     * Soft delete task
     *
     * @param string $id Task UUID
     * @param bool $purge Hard delete if true
     * @return bool
     */
    public function deleteTask(string $id, bool $purge = false): bool
    {
        return $purge ? $this->delete($id, true) : $this->delete($id);
    }

    /**
     * Restore soft-deleted task
     *
     * @param string $id Task UUID
     * @return bool
     */
    public function restore(string $id): bool
    {
        return $this->update($id, ['deleted_at' => null]);
    }

    /**
     * Log task creation to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logTaskCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $taskTitle = $data['data']['title'] ?? 'Unknown Task';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'task',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created task: {$taskTitle}"
        );

        return $data;
    }

    /**
     * Log task updates to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logTaskUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $taskId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $task = $this->find($taskId);
        if (!$task) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $taskTitle = $task['title'];

        // Detect what changed
        $changes = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $field => $value) {
                if (isset($task[$field]) && $task[$field] != $value) {
                    $changes[] = $field;
                }
            }
        }

        // Determine event type and description
        if (isset($data['data']['status'])) {
            $description = "Task status changed to {$data['data']['status']}: {$taskTitle}";
            $eventType = 'status_changed';
        } elseif (isset($data['data']['assigned_to'])) {
            $description = $data['data']['assigned_to'] ?
                "Task assigned: {$taskTitle}" :
                "Task unassigned: {$taskTitle}";
            $eventType = 'assigned';
        } elseif (!empty($changes)) {
            $changedFields = implode(', ', $changes);
            $description = "Updated task: {$taskTitle} (changed: {$changedFields})";
            $eventType = 'updated';
        } else {
            return $data;
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'task',
            entityId: $taskId,
            eventType: $eventType,
            description: $description,
            metadata: !empty($changes) ? ['changed_fields' => $changes] : null
        );

        return $data;
    }

    /**
     * Log task deletion to timeline
     *
     * @param array $data
     * @return array
     */
    protected function logTaskDeleted(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $taskId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $task = $this->withDeleted()->find($taskId);
        if (!$task) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $taskTitle = $task['title'];

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'task',
            entityId: $taskId,
            eventType: 'deleted',
            description: "Deleted task: {$taskTitle}",
            metadata: ['purge' => $data['purge'] ?? false]
        );

        return $data;
    }
}
