<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Activity Log Model
 *
 * Manages system-wide activity logging.
 */
class ActivityLogModel extends Model
{
    protected $table = 'activity_log';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'entity_name',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $useTimestamps = false;
    protected $createdField = 'created_at';

    protected $skipValidation = true;
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setUserId', 'setRequestInfo', 'setCreatedAt'];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

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

    protected function setRequestInfo(array $data): array
    {
        $request = service('request');

        if (!isset($data['data']['ip_address'])) {
            $data['data']['ip_address'] = $request->getIPAddress();
        }

        if (!isset($data['data']['user_agent'])) {
            $data['data']['user_agent'] = $request->getUserAgent()->getAgentString();
        }

        return $data;
    }

    protected function setCreatedAt(array $data): array
    {
        if (!isset($data['data']['created_at'])) {
            $data['data']['created_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    /**
     * Log an activity
     */
    public function log(
        string $entityType,
        ?string $entityId,
        string $action,
        ?string $description = null,
        ?array $metadata = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?string {
        $data = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
        ];

        if ($metadata !== null) {
            $data['metadata'] = json_encode($metadata);
        }

        if ($oldValues !== null) {
            $data['old_values'] = json_encode($oldValues);
        }

        if ($newValues !== null) {
            $data['new_values'] = json_encode($newValues);
        }

        if ($this->insert($data)) {
            return $this->getInsertID();
        }

        return null;
    }

    /**
     * Log entity creation
     */
    public function logCreated(string $entityType, string $entityId, ?string $entityName = null, ?array $data = null): ?string
    {
        return $this->log(
            $entityType,
            $entityId,
            'created',
            $entityName ? "Created {$entityType}: {$entityName}" : "Created {$entityType}",
            null,
            null,
            $data
        );
    }

    /**
     * Log entity update
     */
    public function logUpdated(string $entityType, string $entityId, ?string $entityName = null, ?array $oldValues = null, ?array $newValues = null): ?string
    {
        return $this->log(
            $entityType,
            $entityId,
            'updated',
            $entityName ? "Updated {$entityType}: {$entityName}" : "Updated {$entityType}",
            null,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log entity deletion
     */
    public function logDeleted(string $entityType, string $entityId, ?string $entityName = null): ?string
    {
        return $this->log(
            $entityType,
            $entityId,
            'deleted',
            $entityName ? "Deleted {$entityType}: {$entityName}" : "Deleted {$entityType}"
        );
    }

    /**
     * Log user login
     */
    public function logLogin(?string $userId = null): ?string
    {
        return $this->log('user', $userId, 'login', 'User logged in');
    }

    /**
     * Log user logout
     */
    public function logLogout(?string $userId = null): ?string
    {
        return $this->log('user', $userId, 'logout', 'User logged out');
    }

    /**
     * Get activity for entity
     */
    public function getForEntity(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get activity for user
     */
    public function getForUser(string $userId, int $limit = 50): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get recent activity
     */
    public function getRecent(int $limit = 50): array
    {
        return $this->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get activity by action type
     */
    public function getByAction(string $action, int $limit = 50): array
    {
        return $this->where('action', $action)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get activity statistics
     */
    public function getStatistics(int $days = 7): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $builder = $this->builder();
        $builder->select('action, COUNT(*) as count')
            ->where('created_at >=', $startDate)
            ->groupBy('action')
            ->orderBy('count', 'DESC');

        return $builder->get()->getResultArray();
    }

    /**
     * Clear old activity logs
     */
    public function clearOld(int $daysOld = 90): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        return $this->where('created_at <', $date)->delete();
    }

    /**
     * Search activity logs
     */
    public function search(string $term, int $limit = 50): array
    {
        return $this->like('description', $term)
            ->orLike('entity_type', $term)
            ->orLike('action', $term)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
