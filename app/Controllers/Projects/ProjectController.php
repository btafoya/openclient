<?php

namespace App\Controllers\Projects;

use App\Controllers\BaseController;
use App\Models\ProjectModel;
use App\Domain\Projects\Authorization\ProjectGuard;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Project Controller
 *
 * Manages project CRUD operations with full RBAC integration.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): ProjectGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 */
class ProjectController extends BaseController
{
    protected ProjectModel $projectModel;
    protected ProjectGuard $guard;

    public function __construct()
    {
        $this->projectModel = new ProjectModel();
        $this->guard = new ProjectGuard();
    }

    /**
     * List all projects (with search and filtering)
     *
     * GET /api/projects
     * GET /api/projects?search=term
     * GET /api/projects?status=active
     * GET /api/projects?client_id=uuid
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filters from query params
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');
        $clientId = $this->request->getGet('client_id');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        // Get projects (RLS automatically filters by agency)
        if ($search) {
            $projects = $this->projectModel->search($search, $activeOnly);
        } elseif ($clientId) {
            $projects = $this->projectModel->getByClientId($clientId, $activeOnly);
        } elseif ($status) {
            $projects = $this->projectModel->getByStatus($status);
        } else {
            $projects = $this->projectModel->getForCurrentAgency($activeOnly);
        }

        // Get permission summary for UI
        $permissions = $this->guard->getPermissionSummary($user);

        return $this->response->setJSON([
            'success' => true,
            'data' => $projects,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show single project details with related data
     *
     * GET /api/projects/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $project = $this->projectModel->getWithRelated($id);

        // Check if project exists (RLS may hide it)
        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found']);
        }

        // Layer 3: Service guard authorization
        if (!$this->guard->canView($user, $project)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this project.']);
        }

        // Get permission summary for this specific project
        $permissions = $this->guard->getPermissionSummary($user, $project);

        return $this->response->setJSON([
            'success' => true,
            'data' => $project,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store new project
     *
     * POST /api/projects
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Layer 3: Authorization check
        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create projects.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Validate input
        if (!$this->projectModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->projectModel->errors(),
                ]);
        }

        // Insert project (agency_id set automatically by model callback)
        $projectId = $this->projectModel->insert($data);

        if (!$projectId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create project. Please try again.']);
        }

        $project = $this->projectModel->find($projectId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Project created successfully.',
                'data' => $project,
            ]);
    }

    /**
     * Update project
     *
     * PUT/PATCH /api/projects/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $project = $this->projectModel->find($id);

        // Check if project exists
        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $project)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this project.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Validate input
        if (!$this->projectModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->projectModel->errors(),
                ]);
        }

        // Update project
        $success = $this->projectModel->update($id, $data);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update project. Please try again.']);
        }

        $updated = $this->projectModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Update project status
     *
     * PATCH /api/projects/{id}/status
     */
    public function updateStatus(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $project = $this->projectModel->find($id);

        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found.']);
        }

        if (!$this->guard->canEdit($user, $project)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this project.']);
        }

        $data = $this->request->getJSON(true);
        $status = $data['status'] ?? null;

        if (!$status) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Status is required.']);
        }

        $success = $this->projectModel->updateStatus($id, $status);

        if (!$success) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Invalid status value.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Project status updated successfully.',
            'data' => $this->projectModel->find($id),
        ]);
    }

    /**
     * Delete project (soft delete)
     *
     * DELETE /api/projects/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $project = $this->projectModel->find($id);

        // Check if project exists
        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDelete($user, $project)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this project.']);
        }

        // Validate deletion (check for dependent records)
        $validation = $this->projectModel->validateDelete($id);

        if (!$validation['can_delete']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Cannot delete project.',
                    'reasons' => $validation['blockers'],
                ]);
        }

        // Soft delete project
        $success = $this->projectModel->deleteProject($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete project. Please try again.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Project deleted successfully.',
        ]);
    }

    /**
     * Restore soft-deleted project
     *
     * POST /api/projects/{id}/restore
     */
    public function restore(string $id): ResponseInterface
    {
        $user = session()->get('user');

        // Only owner can restore deleted projects
        if ($user['role'] !== 'owner') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'Only platform administrators can restore deleted projects.']);
        }

        $success = $this->projectModel->restore($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to restore project.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Project restored successfully.',
            'data' => $this->projectModel->find($id),
        ]);
    }

    /**
     * Get project summary statistics
     *
     * GET /api/projects/stats
     */
    public function stats(): ResponseInterface
    {
        $stats = $this->projectModel->getSummaryStats();

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Toggle project active status
     *
     * POST /api/projects/{id}/toggle-active
     */
    public function toggleActive(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $project = $this->projectModel->find($id);

        if (!$project) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Project not found.']);
        }

        if (!$this->guard->canEdit($user, $project)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this project.']);
        }

        $success = $this->projectModel->toggleActive($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to toggle project status.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Project status toggled successfully.',
            'data' => $this->projectModel->find($id),
        ]);
    }
}
