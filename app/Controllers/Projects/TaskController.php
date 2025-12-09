<?php

namespace App\Controllers\Projects;

use App\Controllers\BaseController;
use App\Models\TaskModel;
use App\Models\ProjectModel;
use App\Domain\Projects\Authorization\TaskGuard;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Task Controller
 *
 * Manages task CRUD operations with Kanban board support and full RBAC integration.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): TaskGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 */
class TaskController extends BaseController
{
    protected TaskModel $taskModel;
    protected ProjectModel $projectModel;
    protected TaskGuard $guard;

    public function __construct()
    {
        $this->taskModel = new TaskModel();
        $this->projectModel = new ProjectModel();
        $this->guard = new TaskGuard();
    }

    /**
     * List all tasks (with search and filtering)
     *
     * GET /api/tasks
     * GET /api/tasks?search=term
     * GET /api/tasks?status=in_progress
     * GET /api/tasks?project_id=uuid
     * GET /api/tasks?assigned_to=uuid
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filters from query params
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');
        $projectId = $this->request->getGet('project_id');
        $assignedTo = $this->request->getGet('assigned_to');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        // Get tasks (RLS automatically filters by agency)
        if ($search) {
            $tasks = $this->taskModel->search($search, $activeOnly);
        } elseif ($projectId) {
            $tasks = $this->taskModel->getByProjectId($projectId, $activeOnly);
            // Flatten the grouped array for list view
            $tasks = array_merge(...array_values($tasks));
        } elseif ($assignedTo) {
            $tasks = $this->taskModel->getByAssignedUser($assignedTo, $activeOnly);
        } elseif ($status) {
            $tasks = $this->taskModel->getByStatus($status);
        } else {
            $tasks = $this->taskModel->getForCurrentAgency($activeOnly);
        }

        // Get permission summary for UI
        $permissions = $this->guard->getPermissionSummary($user);

        return $this->response->setJSON([
            'success' => true,
            'data' => $tasks,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Get Kanban board data for a specific project
     *
     * GET /api/projects/{projectId}/kanban
     */
    public function getKanbanBoard(string $projectId): ResponseInterface
    {
        $user = session()->get('user');

        // Verify project exists and user has access
        $project = $this->projectModel->find($projectId);
        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found']);
        }

        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        // Get tasks grouped by status for Kanban board
        $kanbanData = $this->taskModel->getByProjectId($projectId, $activeOnly);

        // Get permission summary for this project
        $permissions = $this->guard->getPermissionSummary($user, null, $projectId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $kanbanData,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show single task details
     *
     * GET /api/tasks/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->getById($id);

        // Check if task exists (RLS may hide it)
        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found']);
        }

        // Layer 3: Service guard authorization
        if (!$this->guard->canView($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this task.']);
        }

        // Get permission summary for this specific task
        $permissions = $this->guard->getPermissionSummary($user, $task);

        return $this->response->setJSON([
            'success' => true,
            'data' => $task,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store new task
     *
     * POST /api/tasks
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Layer 3: Authorization check
        $projectId = $data['project_id'] ?? null;
        if (!$this->guard->canCreate($user, $projectId)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create tasks.']);
        }

        // Validate input
        if (!$this->taskModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->taskModel->errors(),
                ]);
        }

        // Insert task (agency_id and sort_order set automatically by model callbacks)
        $taskId = $this->taskModel->insert($data);

        if (!$taskId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create task. Please try again.']);
        }

        $task = $this->taskModel->find($taskId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Task created successfully.',
                'data' => $task,
            ]);
    }

    /**
     * Update task
     *
     * PUT/PATCH /api/tasks/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->find($id);

        // Check if task exists
        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this task.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Validate input
        if (!$this->taskModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->taskModel->errors(),
                ]);
        }

        // Update task
        $success = $this->taskModel->update($id, $data);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update task. Please try again.']);
        }

        $updated = $this->taskModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Update task status
     *
     * PATCH /api/tasks/{id}/status
     */
    public function updateStatus(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->find($id);

        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found.']);
        }

        if (!$this->guard->canUpdateStatus($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to update this task status.']);
        }

        $data = $this->request->getJSON(true);
        $status = $data['status'] ?? null;

        if (!$status) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Status is required.']);
        }

        $success = $this->taskModel->updateStatus($id, $status);

        if (!$success) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Invalid status value.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Task status updated successfully.',
            'data' => $this->taskModel->find($id),
        ]);
    }

    /**
     * Assign task to user
     *
     * PATCH /api/tasks/{id}/assign
     */
    public function assignToUser(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->find($id);

        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found.']);
        }

        if (!$this->guard->canAssign($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to assign this task.']);
        }

        $data = $this->request->getJSON(true);
        $userId = $data['user_id'] ?? null;

        // Allow null to unassign
        $success = $this->taskModel->assignToUser($id, $userId);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to assign task.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $userId ? 'Task assigned successfully.' : 'Task unassigned successfully.',
            'data' => $this->taskModel->find($id),
        ]);
    }

    /**
     * Update task sort order (for Kanban drag-and-drop)
     *
     * PATCH /api/tasks/{id}/sort-order
     */
    public function updateSortOrder(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->find($id);

        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found.']);
        }

        if (!$this->guard->canEdit($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this task.']);
        }

        $data = $this->request->getJSON(true);
        $newOrder = $data['sort_order'] ?? null;
        $newStatus = $data['status'] ?? null;

        if ($newOrder === null) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Sort order is required.']);
        }

        $success = $this->taskModel->updateSortOrder($id, $newOrder, $newStatus);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update task order.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Task order updated successfully.',
            'data' => $this->taskModel->find($id),
        ]);
    }

    /**
     * Reorder tasks in Kanban column (bulk update)
     *
     * POST /api/projects/{projectId}/tasks/reorder
     */
    public function reorderTasks(string $projectId): ResponseInterface
    {
        $user = session()->get('user');

        // Verify project exists
        $project = $this->projectModel->find($projectId);
        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found.']);
        }

        $data = $this->request->getJSON(true);
        $status = $data['status'] ?? null;
        $taskIds = $data['task_ids'] ?? [];

        if (!$status || empty($taskIds)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Status and task IDs are required.']);
        }

        // Verify user can edit tasks in this project
        // (checking first task as authorization check)
        $firstTask = $this->taskModel->find($taskIds[0]);
        if (!$firstTask || !$this->guard->canEdit($user, $firstTask)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to reorder these tasks.']);
        }

        $success = $this->taskModel->reorderTasks($projectId, $status, $taskIds);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to reorder tasks.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tasks reordered successfully.',
        ]);
    }

    /**
     * Get overdue tasks
     *
     * GET /api/tasks/overdue
     * GET /api/tasks/overdue?project_id=uuid
     */
    public function getOverdue(): ResponseInterface
    {
        $projectId = $this->request->getGet('project_id');
        $tasks = $this->taskModel->getOverdueTasks($projectId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Delete task (soft delete)
     *
     * DELETE /api/tasks/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->find($id);

        // Check if task exists
        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDelete($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this task.']);
        }

        // Soft delete task
        $success = $this->taskModel->deleteTask($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete task. Please try again.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Task deleted successfully.',
        ]);
    }

    /**
     * Toggle task active status
     *
     * POST /api/tasks/{id}/toggle-active
     */
    public function toggleActive(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $task = $this->taskModel->find($id);

        if (!$task) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Task not found.']);
        }

        if (!$this->guard->canEdit($user, $task)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this task.']);
        }

        $success = $this->taskModel->toggleActive($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to toggle task status.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Task status toggled successfully.',
            'data' => $this->taskModel->find($id),
        ]);
    }
}
