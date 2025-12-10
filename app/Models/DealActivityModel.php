<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Deal Activity Model
 *
 * Manages activity log for deals.
 */
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
        'deal_id' => 'required|max_length[36]',
        'user_id' => 'required|max_length[36]',
        'activity_type' => 'required|max_length[50]',
        'subject' => 'permit_empty|max_length[255]',
        'description' => 'permit_empty',
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setUserId'];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT uuid_generate_v4()::text as id")->getRow()->id;
        }
        return $data;
    }

    protected function setUserId(array $data): array
    {
        if (!isset($data['data']['user_id'])) {
            $user = session()->get('user');
            if ($user && isset($user['id'])) {
                $data['data']['user_id'] = $user['id'];
            }
        }
        return $data;
    }

    /**
     * Get activities for a deal
     */
    public function getByDealId(string $dealId, int $limit = 50): array
    {
        return $this->where('deal_id', $dealId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Log an activity
     */
    public function logActivity(
        string $dealId,
        string $type,
        string $description,
        ?array $metadata = null,
        ?string $subject = null
    ): bool {
        $user = session()->get('user');

        return $this->insert([
            'deal_id' => $dealId,
            'user_id' => $user['id'] ?? null,
            'activity_type' => $type,
            'subject' => $subject,
            'description' => $description,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ]) !== false;
    }

    /**
     * Add note to deal
     */
    public function addNote(string $dealId, string $note): bool
    {
        return $this->logActivity($dealId, 'note', $note);
    }

    /**
     * Log email activity
     */
    public function logEmail(string $dealId, string $subject, string $description): bool
    {
        return $this->logActivity($dealId, 'email', $description, null, $subject);
    }

    /**
     * Log call activity
     */
    public function logCall(string $dealId, string $subject, string $description, ?int $duration = null): bool
    {
        return $this->logActivity($dealId, 'call', $description, ['duration' => $duration], $subject);
    }

    /**
     * Log meeting activity
     */
    public function logMeeting(string $dealId, string $subject, string $description, ?string $scheduledAt = null): bool
    {
        $data = [
            'deal_id' => $dealId,
            'activity_type' => 'meeting',
            'subject' => $subject,
            'description' => $description,
            'scheduled_at' => $scheduledAt,
        ];

        return $this->insert($data) !== false;
    }

    /**
     * Create task activity
     */
    public function createTask(string $dealId, string $subject, string $description, ?string $scheduledAt = null): bool
    {
        $data = [
            'deal_id' => $dealId,
            'activity_type' => 'task',
            'subject' => $subject,
            'description' => $description,
            'scheduled_at' => $scheduledAt,
        ];

        return $this->insert($data) !== false;
    }

    /**
     * Mark activity as completed
     */
    public function markCompleted(string $activityId): bool
    {
        return $this->update($activityId, [
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get pending tasks for a deal
     */
    public function getPendingTasks(string $dealId): array
    {
        return $this->where('deal_id', $dealId)
            ->where('activity_type', 'task')
            ->where('completed_at', null)
            ->orderBy('scheduled_at', 'ASC')
            ->findAll();
    }

    /**
     * Get activities by type
     */
    public function getByType(string $dealId, string $type): array
    {
        return $this->where('deal_id', $dealId)
            ->where('activity_type', $type)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get activity counts by type for a deal
     */
    public function getActivityCounts(string $dealId): array
    {
        return $this->select('activity_type, COUNT(*) as count')
            ->where('deal_id', $dealId)
            ->groupBy('activity_type')
            ->findAll();
    }
}
