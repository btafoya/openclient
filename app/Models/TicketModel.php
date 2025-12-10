<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ticket Model
 *
 * Manages support tickets with multi-agency isolation via PostgreSQL RLS.
 */
class TicketModel extends Model
{
    protected $table = 'tickets';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'client_id',
        'project_id',
        'created_by',
        'assigned_to',
        'ticket_number',
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'source',
        'due_date',
        'resolved_at',
        'closed_at',
        'first_response_at',
        'last_activity_at',
        'metadata',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'subject' => 'required|max_length[255]',
        'priority' => 'permit_empty|in_list[low,normal,high,urgent]',
        'status' => 'permit_empty|in_list[open,in_progress,waiting,resolved,closed]',
        'category' => 'permit_empty|in_list[general,technical,billing,feature_request,bug]',
    ];

    protected $skipValidation = false;
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'generateTicketNumber', 'setCreatedBy'];
    protected $afterInsert = ['logTicketCreated'];
    protected $afterUpdate = ['logTicketUpdated', 'updateLastActivity'];

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

    protected function setCreatedBy(array $data): array
    {
        if (!isset($data['data']['created_by'])) {
            $user = session()->get('user');
            if ($user && isset($user['id'])) {
                $data['data']['created_by'] = $user['id'];
            }
        }
        return $data;
    }

    protected function generateTicketNumber(array $data): array
    {
        if (!isset($data['data']['ticket_number'])) {
            $year = date('Y');
            $count = $this->where("ticket_number LIKE 'TKT-{$year}-%'")->countAllResults() + 1;
            $data['data']['ticket_number'] = sprintf('TKT-%s-%04d', $year, $count);
        }
        return $data;
    }

    protected function updateLastActivity(array $data): array
    {
        if (isset($data['id'])) {
            $ticketId = is_array($data['id']) ? $data['id'][0] : $data['id'];
            $this->db->table('tickets')
                ->where('id', $ticketId)
                ->update(['last_activity_at' => date('Y-m-d H:i:s')]);
        }
        return $data;
    }

    protected function logTicketCreated(array $data): array
    {
        // Log to activity log
        if (isset($data['id'])) {
            $activityModel = new ActivityLogModel();
            $activityModel->log(
                'ticket',
                $data['id'],
                'created',
                'Ticket created: ' . ($data['data']['subject'] ?? 'Unknown')
            );
        }
        return $data;
    }

    protected function logTicketUpdated(array $data): array
    {
        if (isset($data['id'])) {
            $ticketId = is_array($data['id']) ? $data['id'][0] : $data['id'];
            $activityModel = new ActivityLogModel();
            $activityModel->log(
                'ticket',
                $ticketId,
                'updated',
                'Ticket updated'
            );
        }
        return $data;
    }

    /**
     * Get tickets with filters
     */
    public function getFiltered(array $filters = []): array
    {
        $builder = $this->builder();

        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $builder->where('priority', $filters['priority']);
        }

        if (!empty($filters['assigned_to'])) {
            $builder->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['client_id'])) {
            $builder->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['project_id'])) {
            $builder->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['category'])) {
            $builder->where('category', $filters['category']);
        }

        $builder->where('deleted_at', null);
        $builder->orderBy('created_at', 'DESC');

        return $builder->get()->getResultArray();
    }

    /**
     * Get open tickets count
     */
    public function getOpenCount(): int
    {
        return $this->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->countAllResults();
    }

    /**
     * Get tickets by status
     */
    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('priority', 'DESC')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    /**
     * Assign ticket to user
     */
    public function assignTo(string $ticketId, string $userId): bool
    {
        $updated = $this->update($ticketId, [
            'assigned_to' => $userId,
            'status' => 'in_progress',
        ]);

        if ($updated && !$this->find($ticketId)['first_response_at']) {
            $this->update($ticketId, ['first_response_at' => date('Y-m-d H:i:s')]);
        }

        return $updated;
    }

    /**
     * Resolve ticket
     */
    public function resolve(string $ticketId): bool
    {
        return $this->update($ticketId, [
            'status' => 'resolved',
            'resolved_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Close ticket
     */
    public function close(string $ticketId): bool
    {
        return $this->update($ticketId, [
            'status' => 'closed',
            'closed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Reopen ticket
     */
    public function reopen(string $ticketId): bool
    {
        return $this->update($ticketId, [
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Get ticket with messages
     */
    public function getWithMessages(string $ticketId): ?array
    {
        $ticket = $this->find($ticketId);
        if (!$ticket) {
            return null;
        }

        $messageModel = new TicketMessageModel();
        $ticket['messages'] = $messageModel->getForTicket($ticketId);

        return $ticket;
    }

    /**
     * Get overdue tickets
     */
    public function getOverdue(): array
    {
        return $this->where('due_date <', date('Y-m-d'))
            ->whereIn('status', ['open', 'in_progress', 'waiting'])
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }

    /**
     * Get ticket statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => $this->countAllResults(false),
            'open' => $this->where('status', 'open')->countAllResults(false),
            'in_progress' => $this->where('status', 'in_progress')->countAllResults(false),
            'waiting' => $this->where('status', 'waiting')->countAllResults(false),
            'resolved' => $this->where('status', 'resolved')->countAllResults(false),
            'closed' => $this->where('status', 'closed')->countAllResults(false),
        ];

        // Reset builder
        $this->builder();

        return $stats;
    }
}
