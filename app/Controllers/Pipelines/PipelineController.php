<?php

namespace App\Controllers\Pipelines;

use App\Controllers\BaseController;
use App\Models\Pipelines\PipelineModel;
use App\Models\Pipelines\PipelineStageModel;
use App\Domain\Pipelines\Authorization\PipelineGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Pipeline Controller
 *
 * Manages pipeline CRUD operations with full RBAC integration.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): PipelineGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 */
class PipelineController extends BaseController
{
    protected PipelineModel $pipelineModel;
    protected PipelineStageModel $stageModel;
    protected PipelineGuard $guard;

    public function __construct()
    {
        $this->pipelineModel = new PipelineModel();
        $this->stageModel = new PipelineStageModel();
        $this->guard = new PipelineGuard();
    }

    /**
     * List all pipelines
     *
     * GET /api/pipelines
     * GET /api/pipelines?search=term
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filters from query params
        $search = $this->request->getGet('search');
        $activeOnly = $this->request->getGet('active', FILTER_VALIDATE_BOOLEAN) ?? true;

        // Get pipelines (RLS automatically filters by agency)
        if ($search) {
            $pipelines = $this->pipelineModel->search($search);
        } else {
            $pipelines = $activeOnly
                ? $this->pipelineModel->where('is_active', true)->findAll()
                : $this->pipelineModel->findAll();
        }

        // Filter by permissions
        $filteredPipelines = array_filter($pipelines, function ($pipeline) use ($user) {
            return $this->guard->canView($user, $pipeline);
        });

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($filteredPipelines),
            'count' => count($filteredPipelines),
        ]);
    }

    /**
     * Show single pipeline with stages
     *
     * GET /api/pipelines/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->getWithStages($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found']);
        }

        // Layer 3: Service guard authorization
        if (!$this->guard->canView($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this pipeline.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $pipeline,
        ]);
    }

    /**
     * Store new pipeline with default stages
     *
     * POST /api/pipelines
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Layer 3: Authorization check
        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create pipelines.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Validate input
        if (!$this->pipelineModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->pipelineModel->errors(),
                ]);
        }

        // Create pipeline with default stages if no custom stages provided
        $customStages = $data['stages'] ?? null;
        unset($data['stages']);

        if ($customStages && is_array($customStages)) {
            // Insert pipeline first
            $pipelineId = $this->pipelineModel->insert($data, true);

            if (!$pipelineId) {
                return $this->response
                    ->setStatusCode(500)
                    ->setJSON(['error' => 'Failed to create pipeline.']);
            }

            // Insert custom stages
            foreach ($customStages as $index => $stage) {
                $this->stageModel->insert([
                    'pipeline_id' => $pipelineId,
                    'name' => $stage['name'],
                    'color' => $stage['color'] ?? '#6b7280',
                    'probability' => $stage['probability'] ?? 50,
                    'sort_order' => $index,
                    'is_won' => $stage['is_won'] ?? false,
                    'is_lost' => $stage['is_lost'] ?? false,
                ]);
            }
        } else {
            // Create with default stages
            $pipelineId = $this->pipelineModel->createWithDefaultStages($data);
        }

        if (!$pipelineId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create pipeline.']);
        }

        $pipeline = $this->pipelineModel->getWithStages($pipelineId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Pipeline created successfully.',
                'data' => $pipeline,
            ]);
    }

    /**
     * Update pipeline
     *
     * PUT/PATCH /api/pipelines/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this pipeline.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Remove stages from pipeline data (handled separately)
        unset($data['stages']);

        // Validate input
        if (!$this->pipelineModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->pipelineModel->errors(),
                ]);
        }

        // Update pipeline
        $success = $this->pipelineModel->update($id, $data);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update pipeline.']);
        }

        $updated = $this->pipelineModel->getWithStages($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Pipeline updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Delete pipeline
     *
     * DELETE /api/pipelines/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDelete($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this pipeline.']);
        }

        // Check for existing deals
        $dealCount = $this->pipelineModel->getDealCount($id);
        if ($dealCount > 0) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Cannot delete pipeline with existing deals.',
                    'deal_count' => $dealCount,
                ]);
        }

        // Delete pipeline (soft delete)
        $success = $this->pipelineModel->delete($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete pipeline.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Pipeline deleted successfully.',
        ]);
    }

    /**
     * Get pipeline statistics
     *
     * GET /api/pipelines/{id}/stats
     */
    public function stats(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        if (!$this->guard->canView($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this pipeline.']);
        }

        $stats = $this->pipelineModel->getStats($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Add stage to pipeline
     *
     * POST /api/pipelines/{id}/stages
     */
    public function addStage(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        if (!$this->guard->canManageStages($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage stages.']);
        }

        $data = $this->request->getJSON(true);
        $data['pipeline_id'] = $id;
        $data['sort_order'] = $this->stageModel->getNextSortOrder($id);

        if (!$this->stageModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->stageModel->errors(),
                ]);
        }

        $stageId = $this->stageModel->insert($data, true);

        if (!$stageId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create stage.']);
        }

        $stage = $this->stageModel->find($stageId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Stage added successfully.',
                'data' => $stage,
            ]);
    }

    /**
     * Update stage
     *
     * PUT/PATCH /api/pipelines/{id}/stages/{stageId}
     */
    public function updateStage(string $id, string $stageId): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        if (!$this->guard->canManageStages($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage stages.']);
        }

        $stage = $this->stageModel->find($stageId);

        if (!$stage || $stage['pipeline_id'] !== $id) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Stage not found.']);
        }

        $data = $this->request->getJSON(true);
        unset($data['pipeline_id']); // Prevent changing pipeline

        $success = $this->stageModel->update($stageId, $data);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update stage.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Stage updated successfully.',
            'data' => $this->stageModel->find($stageId),
        ]);
    }

    /**
     * Delete stage
     *
     * DELETE /api/pipelines/{id}/stages/{stageId}
     */
    public function deleteStage(string $id, string $stageId): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        if (!$this->guard->canManageStages($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage stages.']);
        }

        $stage = $this->stageModel->find($stageId);

        if (!$stage || $stage['pipeline_id'] !== $id) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Stage not found.']);
        }

        // Check if stage has deals
        $dealCount = $this->stageModel->countDeals($stageId);
        if ($dealCount > 0) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Cannot delete stage with existing deals.',
                    'deal_count' => $dealCount,
                ]);
        }

        $success = $this->stageModel->delete($stageId);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete stage.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Stage deleted successfully.',
        ]);
    }

    /**
     * Reorder stages
     *
     * PUT /api/pipelines/{id}/stages/reorder
     */
    public function reorderStages(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($id);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        if (!$this->guard->canManageStages($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to manage stages.']);
        }

        $data = $this->request->getJSON(true);
        $order = $data['order'] ?? [];

        if (empty($order)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Order array is required.']);
        }

        $success = $this->stageModel->reorder($id, $order);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to reorder stages.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Stages reordered successfully.',
            'data' => $this->stageModel->getByPipeline($id),
        ]);
    }
}
