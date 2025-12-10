<?php

namespace App\Controllers\Activity;

use App\Controllers\BaseController;
use App\Models\ActivityLogModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Activity Controller
 *
 * Handles activity log viewing operations.
 */
class ActivityController extends BaseController
{
    protected ActivityLogModel $activityModel;

    public function __construct()
    {
        $this->activityModel = new ActivityLogModel();
    }

    /**
     * Get recent activity
     */
    public function index(): ResponseInterface
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $activities = $this->activityModel->getRecent($limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get activity for entity
     */
    public function forEntity(): ResponseInterface
    {
        $entityType = $this->request->getGet('entity_type');
        $entityId = $this->request->getGet('entity_id');
        $limit = (int) ($this->request->getGet('limit') ?? 50);

        if (!$entityType || !$entityId) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Entity type and ID required',
            ])->setStatusCode(400);
        }

        $activities = $this->activityModel->getForEntity($entityType, $entityId, $limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get activity for user
     */
    public function forUser(string $userId): ResponseInterface
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $activities = $this->activityModel->getForUser($userId, $limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get activity by action type
     */
    public function byAction(string $action): ResponseInterface
    {
        $limit = (int) ($this->request->getGet('limit') ?? 50);
        $activities = $this->activityModel->getByAction($action, $limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Search activity logs
     */
    public function search(): ResponseInterface
    {
        $term = $this->request->getGet('q');
        $limit = (int) ($this->request->getGet('limit') ?? 50);

        if (!$term) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Search term required',
            ])->setStatusCode(400);
        }

        $activities = $this->activityModel->search($term, $limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $activities,
        ]);
    }

    /**
     * Get activity statistics
     */
    public function stats(): ResponseInterface
    {
        $days = (int) ($this->request->getGet('days') ?? 7);
        $stats = $this->activityModel->getStatistics($days);

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
