<?php

namespace App\Controllers\Projects;

use App\Controllers\BaseController;
use App\Models\TimeEntryModel;
use App\Models\ProjectModel;
use App\Models\TaskModel;
use App\Domain\Projects\Authorization\TimeEntryGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Time Entry Controller
 *
 * Manages time tracking with timer functionality and full RBAC integration.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): TimeEntryGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 */
class TimeEntryController extends BaseController
{
    protected TimeEntryModel $timeEntryModel;
    protected ProjectModel $projectModel;
    protected TaskModel $taskModel;
    protected TimeEntryGuard $guard;

    public function __construct()
    {
        $this->timeEntryModel = new TimeEntryModel();
        $this->projectModel = new ProjectModel();
        $this->taskModel = new TaskModel();
        $this->guard = new TimeEntryGuard();
    }

    /**
     * List all time entries (with search and filtering)
     *
     * GET /api/time-entries
     * GET /api/time-entries?user_id=uuid
     * GET /api/time-entries?project_id=uuid
     * GET /api/time-entries?task_id=uuid
     * GET /api/time-entries?start_date=Y-m-d&end_date=Y-m-d
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filters from query params
        $userId = $this->request->getGet('user_id');
        $projectId = $this->request->getGet('project_id');
        $taskId = $this->request->getGet('task_id');
        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        // Get time entries (RLS automatically filters by agency)
        if ($startDate && $endDate) {
            $entries = $this->timeEntryModel->getByDateRange($startDate, $endDate, $userId, $projectId);
        } elseif ($projectId) {
            $entries = $this->timeEntryModel->getByProjectId($projectId, $activeOnly);
        } elseif ($taskId) {
            $entries = $this->timeEntryModel->getByTaskId($taskId, $activeOnly);
        } elseif ($userId) {
            $entries = $this->timeEntryModel->getByUserId($userId, $activeOnly);
        } else {
            // Default: return current user's entries
            $entries = $this->timeEntryModel->getByUserId($user['id'], $activeOnly);
        }

        // Get permission summary for UI
        $permissions = $this->guard->getPermissionSummary($user);

        return $this->response->setJSON([
            'success' => true,
            'data' => $entries,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show single time entry details
     *
     * GET /api/time-entries/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $entry = $this->timeEntryModel->getById($id);

        // Check if entry exists (RLS may hide it)
        if (!$entry) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Time entry not found']);
        }

        // Layer 3: Service guard authorization
        if (!$this->guard->canView($user, $entry)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this time entry.']);
        }

        // Get permission summary for this specific entry
        $permissions = $this->guard->getPermissionSummary($user, $entry);

        return $this->response->setJSON([
            'success' => true,
            'data' => $entry,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create manual time entry
     *
     * POST /api/time-entries
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
                ->setJSON(['error' => 'You do not have permission to create time entries.']);
        }

        // Set user_id to current user (users can only create entries for themselves)
        $data['user_id'] = $user['id'];

        // Validate input
        if (!$this->timeEntryModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->timeEntryModel->errors(),
                ]);
        }

        // Insert time entry (agency_id set automatically by model callback)
        $entryId = $this->timeEntryModel->insert($data);

        if (!$entryId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create time entry. Please try again.']);
        }

        $entry = $this->timeEntryModel->find($entryId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Time entry created successfully.',
                'data' => $entry,
            ]);
    }

    /**
     * Update time entry
     *
     * PUT/PATCH /api/time-entries/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $entry = $this->timeEntryModel->find($id);

        // Check if entry exists
        if (!$entry) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Time entry not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $entry)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this time entry.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Prevent changing user_id (time integrity)
        unset($data['user_id']);

        // Validate input
        if (!$this->timeEntryModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->timeEntryModel->errors(),
                ]);
        }

        // Update time entry
        $success = $this->timeEntryModel->update($id, $data);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update time entry. Please try again.']);
        }

        $updated = $this->timeEntryModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Time entry updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Start timer
     *
     * POST /api/time-entries/timer/start
     */
    public function startTimer(): ResponseInterface
    {
        $user = session()->get('user');
        $data = $this->request->getJSON(true);

        $projectId = $data['project_id'] ?? null;
        $taskId = $data['task_id'] ?? null;
        $description = $data['description'] ?? null;

        if (!$projectId) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Project ID is required.']);
        }

        // Authorization check
        if (!$this->guard->canUseTimer($user, $projectId)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to track time on this project.']);
        }

        // Start timer
        $entry = $this->timeEntryModel->startTimer($user['id'], $projectId, $taskId, $description);

        if (!$entry) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'You already have a running timer. Please stop it before starting a new one.']);
        }

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Timer started successfully.',
                'data' => $entry,
            ]);
    }

    /**
     * Stop timer
     *
     * POST /api/time-entries/timer/stop
     */
    public function stopTimer(): ResponseInterface
    {
        $user = session()->get('user');
        $data = $this->request->getJSON(true);
        $description = $data['description'] ?? null;

        // Stop timer
        $entry = $this->timeEntryModel->stopTimer($user['id'], $description);

        if (!$entry) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'No running timer found.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Timer stopped successfully.',
            'data' => $entry,
        ]);
    }

    /**
     * Get running timer
     *
     * GET /api/time-entries/timer/running
     */
    public function getRunningTimer(): ResponseInterface
    {
        $user = session()->get('user');
        $entry = $this->timeEntryModel->getRunningTimer($user['id']);

        return $this->response->setJSON([
            'success' => true,
            'data' => $entry,
        ]);
    }

    /**
     * Get billable summary for project
     *
     * GET /api/projects/{projectId}/time-entries/summary
     */
    public function getBillableSummary(string $projectId): ResponseInterface
    {
        $user = session()->get('user');

        // Verify project exists
        $project = $this->projectModel->find($projectId);
        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found.']);
        }

        $summary = $this->timeEntryModel->getBillableSummary($projectId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get user time tracking statistics
     *
     * GET /api/users/{userId}/time-entries/stats
     */
    public function getUserStats(string $userId): ResponseInterface
    {
        $user = session()->get('user');

        // Users can only view their own stats unless Owner/Agency
        if ($user['id'] !== $userId && !in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view these statistics.']);
        }

        $startDate = $this->request->getGet('start_date');
        $endDate = $this->request->getGet('end_date');

        $stats = $this->timeEntryModel->getUserStats($userId, $startDate, $endDate);

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Toggle time entry billable status
     *
     * PATCH /api/time-entries/{id}/toggle-billable
     */
    public function toggleBillable(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $entry = $this->timeEntryModel->find($id);

        if (!$entry) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Time entry not found.']);
        }

        if (!$this->guard->canToggleBillable($user, $entry)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to change billable status.']);
        }

        $success = $this->timeEntryModel->toggleBillable($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to toggle billable status.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Billable status updated successfully.',
            'data' => $this->timeEntryModel->find($id),
        ]);
    }

    /**
     * Delete time entry (soft delete)
     *
     * DELETE /api/time-entries/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $entry = $this->timeEntryModel->find($id);

        // Check if entry exists
        if (!$entry) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Time entry not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDelete($user, $entry)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this time entry.']);
        }

        // Soft delete time entry
        $success = $this->timeEntryModel->deleteTimeEntry($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete time entry. Please try again.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Time entry deleted successfully.',
        ]);
    }
}
