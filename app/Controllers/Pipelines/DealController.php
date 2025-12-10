<?php

namespace App\Controllers\Pipelines;

use App\Controllers\BaseController;
use App\Models\Pipelines\DealModel;
use App\Models\Pipelines\DealActivityModel;
use App\Models\Pipelines\PipelineModel;
use App\Models\Pipelines\PipelineStageModel;
use App\Domain\Pipelines\Authorization\DealGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Deal Controller
 *
 * Manages deal CRUD operations with full RBAC integration.
 * Supports Kanban board operations and deal lifecycle management.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): DealGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 */
class DealController extends BaseController
{
    protected DealModel $dealModel;
    protected DealActivityModel $activityModel;
    protected PipelineModel $pipelineModel;
    protected PipelineStageModel $stageModel;
    protected DealGuard $guard;

    public function __construct()
    {
        $this->dealModel = new DealModel();
        $this->activityModel = new DealActivityModel();
        $this->pipelineModel = new PipelineModel();
        $this->stageModel = new PipelineStageModel();
        $this->guard = new DealGuard();
    }

    /**
     * List all deals with filtering
     *
     * GET /api/deals
     * GET /api/deals?pipeline_id=xxx
     * GET /api/deals?client_id=xxx
     * GET /api/deals?search=term
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filters from query params
        $pipelineId = $this->request->getGet('pipeline_id');
        $clientId = $this->request->getGet('client_id');
        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status'); // active, won, lost
        $assignedTo = $this->request->getGet('assigned_to');

        $builder = $this->dealModel->where('is_active', true);

        if ($pipelineId) {
            $builder->where('pipeline_id', $pipelineId);
        }

        if ($clientId) {
            $builder->where('client_id', $clientId);
        }

        if ($assignedTo) {
            $builder->where('assigned_to', $assignedTo);
        }

        if ($search) {
            $deals = $this->dealModel->search($search, $pipelineId);
        } else {
            $deals = $builder->orderBy('created_at', 'DESC')->findAll();
        }

        // Filter by status if specified
        if ($status) {
            $deals = array_filter($deals, function ($deal) use ($status) {
                $stage = $this->stageModel->find($deal['stage_id']);
                if (!$stage) {
                    return false;
                }

                if ($status === 'won') {
                    return $stage['is_won'];
                } elseif ($status === 'lost') {
                    return $stage['is_lost'];
                } else {
                    return !$stage['is_won'] && !$stage['is_lost'];
                }
            });
        }

        // Filter by permissions
        $filteredDeals = array_filter($deals, function ($deal) use ($user) {
            return $this->guard->canView($user, $deal);
        });

        // Add stage info to each deal
        foreach ($filteredDeals as &$deal) {
            $deal['stage'] = $this->stageModel->find($deal['stage_id']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($filteredDeals),
            'count' => count($filteredDeals),
        ]);
    }

    /**
     * Get Kanban board data for a pipeline
     *
     * GET /api/deals/kanban/{pipelineId}
     */
    public function kanban(string $pipelineId): ResponseInterface
    {
        $user = session()->get('user');
        $pipeline = $this->pipelineModel->find($pipelineId);

        if (!$pipeline) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Pipeline not found.']);
        }

        // Check pipeline view permission
        $pipelineGuard = new \App\Domain\Pipelines\Authorization\PipelineGuard();
        if (!$pipelineGuard->canView($user, $pipeline)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this pipeline.']);
        }

        $board = $this->dealModel->getKanbanBoard($pipelineId);

        // Filter deals by permission
        foreach ($board as &$column) {
            $column['deals'] = array_filter($column['deals'], function ($deal) use ($user) {
                return $this->guard->canView($user, $deal);
            });
            $column['deals'] = array_values($column['deals']);
            $column['deal_count'] = count($column['deals']);
            $column['total_value'] = array_sum(array_column($column['deals'], 'value'));
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'pipeline' => $pipeline,
                'columns' => $board,
            ],
        ]);
    }

    /**
     * Show single deal with relations
     *
     * GET /api/deals/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->getWithRelations($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found']);
        }

        if (!$this->guard->canView($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this deal.']);
        }

        // Get activities
        $deal['activities'] = $this->activityModel->getByDeal($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $deal,
        ]);
    }

    /**
     * Store new deal
     *
     * POST /api/deals
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create deals.']);
        }

        $data = $this->request->getJSON(true);

        // Validate pipeline and stage exist
        if (!empty($data['pipeline_id'])) {
            $pipeline = $this->pipelineModel->find($data['pipeline_id']);
            if (!$pipeline) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => 'Invalid pipeline.']);
            }

            // If no stage provided, use first stage
            if (empty($data['stage_id'])) {
                $stages = $this->stageModel->getByPipeline($data['pipeline_id']);
                if (!empty($stages)) {
                    $data['stage_id'] = $stages[0]['id'];
                }
            }
        }

        if (!$this->dealModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->dealModel->errors(),
                ]);
        }

        $dealId = $this->dealModel->insert($data, true);

        if (!$dealId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create deal.']);
        }

        $deal = $this->dealModel->getWithRelations($dealId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Deal created successfully.',
                'data' => $deal,
            ]);
    }

    /**
     * Update deal
     *
     * PUT/PATCH /api/deals/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canEdit($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this deal.']);
        }

        $data = $this->request->getJSON(true);

        // Prevent changing pipeline_id
        unset($data['pipeline_id']);

        if (!$this->dealModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->dealModel->errors(),
                ]);
        }

        $success = $this->dealModel->update($id, $data);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update deal.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deal updated successfully.',
            'data' => $this->dealModel->getWithRelations($id),
        ]);
    }

    /**
     * Move deal to a different stage
     *
     * POST /api/deals/{id}/move
     */
    public function move(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canMoveStage($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to move this deal.']);
        }

        $data = $this->request->getJSON(true);
        $stageId = $data['stage_id'] ?? null;
        $sortOrder = $data['sort_order'] ?? null;

        if (!$stageId) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Stage ID is required.']);
        }

        // Verify stage belongs to same pipeline
        $stage = $this->stageModel->find($stageId);
        if (!$stage || $stage['pipeline_id'] !== $deal['pipeline_id']) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Invalid stage for this pipeline.']);
        }

        // Get old stage for activity log
        $oldStage = $this->stageModel->find($deal['stage_id']);

        $success = $this->dealModel->moveToStage($id, $stageId, $sortOrder);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to move deal.']);
        }

        // Log stage change
        if ($oldStage && $oldStage['id'] !== $stageId) {
            $this->activityModel->logStageChange(
                $id,
                $user['id'],
                $oldStage['name'],
                $stage['name']
            );
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deal moved successfully.',
            'data' => $this->dealModel->getWithRelations($id),
        ]);
    }

    /**
     * Mark deal as won
     *
     * POST /api/deals/{id}/won
     */
    public function markWon(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canCloseDeal($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to close this deal.']);
        }

        $data = $this->request->getJSON(true);
        $reason = $data['reason'] ?? null;

        $success = $this->dealModel->markWon($id, $reason);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to mark deal as won.']);
        }

        // Log activity
        $this->activityModel->insert([
            'deal_id' => $id,
            'user_id' => $user['id'],
            'activity_type' => 'won',
            'subject' => 'Deal won',
            'description' => $reason ?? 'Deal marked as won',
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deal marked as won.',
            'data' => $this->dealModel->getWithRelations($id),
        ]);
    }

    /**
     * Mark deal as lost
     *
     * POST /api/deals/{id}/lost
     */
    public function markLost(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canCloseDeal($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to close this deal.']);
        }

        $data = $this->request->getJSON(true);
        $reason = $data['reason'] ?? null;

        $success = $this->dealModel->markLost($id, $reason);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to mark deal as lost.']);
        }

        // Log activity
        $this->activityModel->insert([
            'deal_id' => $id,
            'user_id' => $user['id'],
            'activity_type' => 'lost',
            'subject' => 'Deal lost',
            'description' => $reason ?? 'Deal marked as lost',
        ]);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deal marked as lost.',
            'data' => $this->dealModel->getWithRelations($id),
        ]);
    }

    /**
     * Convert deal to project
     *
     * POST /api/deals/{id}/convert
     */
    public function convert(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canConvertToProject($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to convert this deal.']);
        }

        $projectId = $this->dealModel->convertToProject($id);

        if (!$projectId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to convert deal to project.']);
        }

        $projectModel = new \App\Models\ProjectModel();
        $project = $projectModel->find($projectId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deal converted to project successfully.',
            'data' => [
                'deal' => $this->dealModel->getWithRelations($id),
                'project' => $project,
            ],
        ]);
    }

    /**
     * Delete deal (soft delete)
     *
     * DELETE /api/deals/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canDelete($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this deal.']);
        }

        $success = $this->dealModel->delete($id);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete deal.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deal deleted successfully.',
        ]);
    }

    /**
     * Add activity to deal
     *
     * POST /api/deals/{id}/activities
     */
    public function addActivity(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canEdit($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to add activities to this deal.']);
        }

        $data = $this->request->getJSON(true);
        $data['deal_id'] = $id;
        $data['user_id'] = $user['id'];

        if (!$this->activityModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->activityModel->errors(),
                ]);
        }

        $activityId = $this->activityModel->insert($data, true);

        if (!$activityId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to add activity.']);
        }

        $activity = $this->activityModel->find($activityId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Activity added successfully.',
                'data' => $activity,
            ]);
    }

    /**
     * Get deal activities
     *
     * GET /api/deals/{id}/activities
     */
    public function getActivities(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $deal = $this->dealModel->find($id);

        if (!$deal) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Deal not found.']);
        }

        if (!$this->guard->canView($user, $deal)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this deal.']);
        }

        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $offset = (int) ($this->request->getGet('offset') ?? 0);

        $activities = $this->activityModel->getByDeal($id, $limit, $offset);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Reorder deals in stage
     *
     * PUT /api/deals/reorder
     */
    public function reorder(): ResponseInterface
    {
        $user = session()->get('user');
        $data = $this->request->getJSON(true);

        $stageId = $data['stage_id'] ?? null;
        $order = $data['order'] ?? [];

        if (!$stageId || empty($order)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Stage ID and order array are required.']);
        }

        // Verify user can edit deals in this stage
        $stage = $this->stageModel->find($stageId);
        if (!$stage) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Stage not found.']);
        }

        $success = $this->dealModel->reorderInStage($stageId, $order);

        if (!$success) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to reorder deals.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Deals reordered successfully.',
        ]);
    }

    /**
     * Get deal statistics
     *
     * GET /api/deals/stats
     * GET /api/deals/stats?pipeline_id=xxx
     */
    public function stats(): ResponseInterface
    {
        $pipelineId = $this->request->getGet('pipeline_id');
        $stats = $this->dealModel->getStats($pipelineId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get deals closing soon
     *
     * GET /api/deals/closing-soon
     */
    public function closingSoon(): ResponseInterface
    {
        $days = (int) ($this->request->getGet('days') ?? 7);
        $pipelineId = $this->request->getGet('pipeline_id');

        $deals = $this->dealModel->getClosingSoon($days, $pipelineId);

        // Filter by permissions
        $user = session()->get('user');
        $filteredDeals = array_filter($deals, function ($deal) use ($user) {
            return $this->guard->canView($user, $deal);
        });

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($filteredDeals),
        ]);
    }

    /**
     * Get overdue deals
     *
     * GET /api/deals/overdue
     */
    public function overdue(): ResponseInterface
    {
        $pipelineId = $this->request->getGet('pipeline_id');

        $deals = $this->dealModel->getOverdue($pipelineId);

        // Filter by permissions
        $user = session()->get('user');
        $filteredDeals = array_filter($deals, function ($deal) use ($user) {
            return $this->guard->canView($user, $deal);
        });

        return $this->response->setJSON([
            'success' => true,
            'data' => array_values($filteredDeals),
        ]);
    }
}
