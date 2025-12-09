<?php

namespace App\Models;

use CodeIgniter\Model;

class TimelineModel extends Model
{
    protected $table = 'timeline';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'agency_id',
        'user_id',
        'entity_type',
        'entity_id',
        'event_type',
        'description',
        'metadata',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'metadata' => 'json',
    ];
    protected $useTimestamps = false;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = '';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'user_id' => 'required|is_not_unique[users.id]',
        'entity_type' => 'required|in_list[client,contact,project,note,task,invoice]',
        'entity_id' => 'required',
        'event_type' => 'required|max_length[50]',
        'description' => 'required|max_length[1000]',
    ];

    protected $validationMessages = [
        'entity_type' => [
            'in_list' => 'Invalid entity type. Must be one of: client, contact, project, note, task, invoice.',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $callbacks = [
        'beforeInsert' => ['setAgencyId'],
    ];

    /**
     * Automatically set agency_id from session before insert
     */
    protected function setAgencyId(array $data): array
    {
        if (!isset($data['data']['agency_id'])) {
            $session = session();
            $user = $session->get('user');
            if ($user && isset($user['agency_id'])) {
                $data['data']['agency_id'] = $user['agency_id'];
            }
        }
        return $data;
    }

    /**
     * Log an event to the timeline
     *
     * @param string $userId User who triggered the event
     * @param string $entityType Type of entity (client, contact, project, note)
     * @param string $entityId ID of the entity
     * @param string $eventType Type of event (created, updated, deleted, etc.)
     * @param string $description Human-readable description
     * @param array|null $metadata Additional event data
     * @return string|false Timeline entry ID on success, false on failure
     */
    public function logEvent(
        string $userId,
        string $entityType,
        string $entityId,
        string $eventType,
        string $description,
        ?array $metadata = null
    ): string|false {
        $data = [
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'event_type' => $eventType,
            'description' => $description,
            'metadata' => $metadata,
        ];

        return $this->insert($data);
    }

    /**
     * Get timeline entries for a specific entity
     *
     * @param string $entityType Type of entity
     * @param string $entityId ID of the entity
     * @param int $limit Maximum number of entries to return
     * @return array Timeline entries
     */
    public function getByEntity(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->select('timeline.*, users.first_name as user_first_name, users.last_name as user_last_name, users.email as user_email')
            ->join('users', 'users.id = timeline.user_id', 'left')
            ->where('timeline.entity_type', $entityType)
            ->where('timeline.entity_id', $entityId)
            ->orderBy('timeline.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get timeline entries for a specific user
     *
     * @param string $userId User ID
     * @param int $limit Maximum number of entries to return
     * @return array Timeline entries
     */
    public function getByUser(string $userId, int $limit = 50): array
    {
        return $this->select('timeline.*, users.first_name as user_first_name, users.last_name as user_last_name, users.email as user_email')
            ->join('users', 'users.id = timeline.user_id', 'left')
            ->where('timeline.user_id', $userId)
            ->orderBy('timeline.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get timeline entries by event type
     *
     * @param string $eventType Event type to filter by
     * @param int $limit Maximum number of entries to return
     * @return array Timeline entries
     */
    public function getByEventType(string $eventType, int $limit = 50): array
    {
        return $this->select('timeline.*, users.first_name as user_first_name, users.last_name as user_last_name, users.email as user_email')
            ->join('users', 'users.id = timeline.user_id', 'left')
            ->where('timeline.event_type', $eventType)
            ->orderBy('timeline.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get all timeline entries for the current agency
     *
     * @param int $limit Maximum number of entries to return
     * @param array $filters Optional filters (entity_type, event_type, user_id)
     * @return array Timeline entries
     */
    public function getForAgency(int $limit = 100, array $filters = []): array
    {
        $builder = $this->select('timeline.*, users.first_name as user_first_name, users.last_name as user_last_name, users.email as user_email')
            ->join('users', 'users.id = timeline.user_id', 'left');

        if (!empty($filters['entity_type'])) {
            $builder->where('timeline.entity_type', $filters['entity_type']);
        }

        if (!empty($filters['event_type'])) {
            $builder->where('timeline.event_type', $filters['event_type']);
        }

        if (!empty($filters['user_id'])) {
            $builder->where('timeline.user_id', $filters['user_id']);
        }

        if (!empty($filters['search'])) {
            $builder->like('timeline.description', $filters['search']);
        }

        return $builder->orderBy('timeline.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get recent timeline entries with entity details
     *
     * @param int $limit Maximum number of entries to return
     * @return array Timeline entries with entity information
     */
    public function getRecentWithDetails(int $limit = 50): array
    {
        $entries = $this->select('timeline.*, users.first_name as user_first_name, users.last_name as user_last_name, users.email as user_email')
            ->join('users', 'users.id = timeline.user_id', 'left')
            ->orderBy('timeline.created_at', 'DESC')
            ->limit($limit)
            ->findAll();

        // Enhance entries with entity details
        return array_map(function ($entry) {
            $entry['entity_name'] = $this->getEntityName($entry['entity_type'], $entry['entity_id']);
            $entry['entity_url'] = $this->getEntityUrl($entry['entity_type'], $entry['entity_id']);
            return $entry;
        }, $entries);
    }

    /**
     * Get entity name for display
     *
     * @param string $entityType Type of entity
     * @param string $entityId ID of the entity
     * @return string Entity name
     */
    public function getEntityName(string $entityType, string $entityId): string
    {
        $db = \Config\Database::connect();

        switch ($entityType) {
            case 'client':
                $result = $db->table('clients')
                    ->select('name')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                return $result ? $result->name : 'Unknown Client';

            case 'contact':
                $result = $db->table('contacts')
                    ->select('first_name, last_name')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                return $result ? "{$result->first_name} {$result->last_name}" : 'Unknown Contact';

            case 'project':
                $result = $db->table('projects')
                    ->select('name')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                return $result ? $result->name : 'Unknown Project';

            case 'note':
                $result = $db->table('notes')
                    ->select('subject, content')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                if ($result) {
                    return $result->subject ?: substr($result->content, 0, 50) . '...';
                }
                return 'Unknown Note';

            default:
                return 'Unknown Entity';
        }
    }

    /**
     * Get entity URL for linking
     *
     * @param string $entityType Type of entity
     * @param string $entityId ID of the entity
     * @return string Entity URL
     */
    public function getEntityUrl(string $entityType, string $entityId): string
    {
        $baseUrl = rtrim(base_url(), '/');

        switch ($entityType) {
            case 'client':
                return "{$baseUrl}/clients/{$entityId}";
            case 'contact':
                return "{$baseUrl}/contacts/{$entityId}";
            case 'project':
                return "{$baseUrl}/projects/{$entityId}";
            case 'note':
                return "{$baseUrl}/notes/{$entityId}";
            default:
                return '#';
        }
    }

    /**
     * Get statistics for timeline activity
     *
     * @param array $filters Optional filters (start_date, end_date, entity_type, user_id)
     * @return array Statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $builder = $this->builder();

        if (!empty($filters['start_date'])) {
            $builder->where('created_at >=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('created_at <=', $filters['end_date']);
        }

        if (!empty($filters['entity_type'])) {
            $builder->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['user_id'])) {
            $builder->where('user_id', $filters['user_id']);
        }

        // Get event type counts
        $eventTypeCounts = $builder->select('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->get()
            ->getResultArray();

        // Get entity type counts
        $builder = $this->builder();
        if (!empty($filters['start_date'])) {
            $builder->where('created_at >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('created_at <=', $filters['end_date']);
        }

        $entityTypeCounts = $builder->select('entity_type, COUNT(*) as count')
            ->groupBy('entity_type')
            ->get()
            ->getResultArray();

        // Get total count
        $builder = $this->builder();
        if (!empty($filters['start_date'])) {
            $builder->where('created_at >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('created_at <=', $filters['end_date']);
        }

        $totalCount = $builder->countAllResults();

        return [
            'total_events' => $totalCount,
            'by_event_type' => $eventTypeCounts,
            'by_entity_type' => $entityTypeCounts,
        ];
    }
}
