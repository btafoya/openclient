<?php

namespace App\Models\Pipelines;

use CodeIgniter\Model;

class DealActivityModel extends Model
{
    protected $table = 'deal_activities';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'deal_id',
        'user_id',
        'activity_type',
        'subject',
        'description',
        'scheduled_at',
        'completed_at',
        'metadata',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'deal_id' => 'required',
        'user_id' => 'required',
        'activity_type' => 'required|in_list[note,email,call,meeting,task,stage_change,created,converted,won,lost]',
    ];

    protected $validationMessages = [
        'deal_id' => [
            'required' => 'Deal ID is required',
        ],
        'user_id' => [
            'required' => 'User ID is required',
        ],
        'activity_type' => [
            'required' => 'Activity type is required',
            'in_list' => 'Invalid activity type',
        ],
    ];

    protected $beforeInsert = ['generateUuid'];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['id'])) {
            helper('text');
            $data['data']['id'] = bin2hex(random_bytes(16));
            $data['data']['id'] = sprintf(
                '%s-%s-%s-%s-%s',
                substr($data['data']['id'], 0, 8),
                substr($data['data']['id'], 8, 4),
                substr($data['data']['id'], 12, 4),
                substr($data['data']['id'], 16, 4),
                substr($data['data']['id'], 20, 12)
            );
        }
        return $data;
    }

    /**
     * Get activities by deal
     */
    public function getByDeal(string $dealId, int $limit = 50, int $offset = 0): array
    {
        $activities = $this->where('deal_id', $dealId)
            ->orderBy('created_at', 'DESC')
            ->findAll($limit, $offset);

        // Load user for each activity
        $userModel = new \App\Models\UserModel();
        foreach ($activities as &$activity) {
            $user = $userModel->find($activity['user_id']);
            $activity['user'] = $user ? [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
                'email' => $user['email'],
            ] : null;

            // Parse metadata
            if (is_string($activity['metadata'])) {
                $activity['metadata'] = json_decode($activity['metadata'], true) ?? [];
            }
        }

        return $activities;
    }

    /**
     * Log stage change
     */
    public function logStageChange(string $dealId, string $userId, string $fromStage, string $toStage): bool
    {
        return (bool) $this->insert([
            'deal_id' => $dealId,
            'user_id' => $userId,
            'activity_type' => 'stage_change',
            'subject' => 'Stage changed',
            'description' => "Deal moved from {$fromStage} to {$toStage}",
            'metadata' => json_encode([
                'from_stage' => $fromStage,
                'to_stage' => $toStage,
            ]),
        ]);
    }

    /**
     * Log note
     */
    public function logNote(string $dealId, string $userId, string $subject, string $description): bool
    {
        return (bool) $this->insert([
            'deal_id' => $dealId,
            'user_id' => $userId,
            'activity_type' => 'note',
            'subject' => $subject,
            'description' => $description,
        ]);
    }

    /**
     * Log task
     */
    public function logTask(string $dealId, string $userId, string $subject, ?string $description = null, ?string $scheduledAt = null): bool
    {
        return (bool) $this->insert([
            'deal_id' => $dealId,
            'user_id' => $userId,
            'activity_type' => 'task',
            'subject' => $subject,
            'description' => $description,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    /**
     * Complete activity/task
     */
    public function markCompleted(string $activityId): bool
    {
        return $this->update($activityId, [
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get upcoming tasks for deal
     */
    public function getUpcomingTasks(string $dealId): array
    {
        return $this->where('deal_id', $dealId)
            ->where('activity_type', 'task')
            ->where('completed_at IS NULL')
            ->where('scheduled_at IS NOT NULL')
            ->orderBy('scheduled_at', 'ASC')
            ->findAll();
    }

    /**
     * Get overdue tasks
     */
    public function getOverdueTasks(?string $userId = null): array
    {
        $builder = $this->where('activity_type', 'task')
            ->where('completed_at IS NULL')
            ->where('scheduled_at <', date('Y-m-d H:i:s'));

        if ($userId) {
            $builder->where('user_id', $userId);
        }

        return $builder->orderBy('scheduled_at', 'ASC')
            ->findAll();
    }

    /**
     * Get activity counts by type for deal
     */
    public function getCountsByType(string $dealId): array
    {
        $results = $this->select('activity_type, COUNT(*) as count')
            ->where('deal_id', $dealId)
            ->groupBy('activity_type')
            ->findAll();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['activity_type']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get recent activities across all deals
     */
    public function getRecentActivities(int $limit = 20): array
    {
        $activities = $this->orderBy('created_at', 'DESC')
            ->findAll($limit);

        // Load deal and user for each activity
        $dealModel = new DealModel();
        $userModel = new \App\Models\UserModel();

        foreach ($activities as &$activity) {
            $deal = $dealModel->find($activity['deal_id']);
            $activity['deal'] = $deal ? [
                'id' => $deal['id'],
                'name' => $deal['name'],
            ] : null;

            $user = $userModel->find($activity['user_id']);
            $activity['user'] = $user ? [
                'id' => $user['id'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
            ] : null;

            if (is_string($activity['metadata'])) {
                $activity['metadata'] = json_decode($activity['metadata'], true) ?? [];
            }
        }

        return $activities;
    }
}
