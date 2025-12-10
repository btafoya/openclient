<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Portal Activity Log Model
 *
 * Tracks client portal activity for security and audit purposes.
 */
class PortalActivityLogModel extends Model
{
    protected $table = 'portal_activity_log';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'client_id',
        'access_token_id',
        'action',
        'resource_type',
        'resource_id',
        'details',
        'ip_address',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = null; // Logs are immutable

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid'];

    /**
     * Common portal actions
     */
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_VIEW_INVOICE = 'view_invoice';
    public const ACTION_DOWNLOAD_INVOICE = 'download_invoice';
    public const ACTION_PAY_INVOICE = 'pay_invoice';
    public const ACTION_VIEW_PROPOSAL = 'view_proposal';
    public const ACTION_ACCEPT_PROPOSAL = 'accept_proposal';
    public const ACTION_REJECT_PROPOSAL = 'reject_proposal';
    public const ACTION_VIEW_PROJECT = 'view_project';
    public const ACTION_DOWNLOAD_FILE = 'download_file';
    public const ACTION_SUBMIT_FEEDBACK = 'submit_feedback';
    public const ACTION_UPDATE_PROFILE = 'update_profile';

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Log a portal activity
     */
    public function logActivity(
        string $clientId,
        string $action,
        ?string $accessTokenId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $details = null,
        ?string $ipAddress = null
    ): ?string {
        $data = [
            'client_id' => $clientId,
            'access_token_id' => $accessTokenId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $ipAddress,
        ];

        return $this->insert($data, true);
    }

    /**
     * Get activity log for a client
     */
    public function getByClientId(string $clientId, int $limit = 100): array
    {
        $activities = $this->where('client_id', $clientId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();

        // Parse JSONB details
        foreach ($activities as &$activity) {
            if (!empty($activity['details']) && is_string($activity['details'])) {
                $activity['details'] = json_decode($activity['details'], true);
            }
        }

        return $activities;
    }

    /**
     * Get recent activity for a specific resource
     */
    public function getByResource(string $resourceType, string $resourceId, int $limit = 50): array
    {
        return $this->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get activity by action type
     */
    public function getByAction(string $action, int $limit = 100): array
    {
        return $this->where('action', $action)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get recent logins for security monitoring
     */
    public function getRecentLogins(int $hours = 24): array
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        return $this->where('action', self::ACTION_LOGIN)
            ->where('created_at >', $since)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get activity summary for a client
     */
    public function getClientSummary(string $clientId): array
    {
        $db = $this->db;

        // Get counts by action type
        $actionCounts = $db->table($this->table)
            ->select('action, COUNT(*) as count')
            ->where('client_id', $clientId)
            ->groupBy('action')
            ->get()
            ->getResultArray();

        // Get last activity
        $lastActivity = $this->where('client_id', $clientId)
            ->orderBy('created_at', 'DESC')
            ->first();

        // Get first login
        $firstLogin = $this->where('client_id', $clientId)
            ->where('action', self::ACTION_LOGIN)
            ->orderBy('created_at', 'ASC')
            ->first();

        return [
            'action_counts' => array_column($actionCounts, 'count', 'action'),
            'last_activity' => $lastActivity,
            'first_login' => $firstLogin,
            'total_activities' => array_sum(array_column($actionCounts, 'count')),
        ];
    }

    /**
     * Clean up old activity logs (retention policy)
     */
    public function cleanupOldLogs(int $retentionDays = 365): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        return $this->where('created_at <', $cutoffDate)->delete();
    }

    /**
     * Export activity log for compliance/audit
     */
    public function exportForClient(string $clientId): array
    {
        return $this->where('client_id', $clientId)
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }
}
