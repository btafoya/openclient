<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ticket Message Model
 *
 * Manages ticket conversation messages.
 */
class TicketMessageModel extends Model
{
    protected $table = 'ticket_messages';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'ticket_id',
        'user_id',
        'message',
        'is_internal',
        'is_from_client',
        'attachments',
        'metadata',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'ticket_id' => 'required',
        'message' => 'required',
    ];

    protected $skipValidation = false;
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setUserId'];
    protected $afterInsert = ['updateTicketActivity', 'logMessageCreated'];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
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

    protected function updateTicketActivity(array $data): array
    {
        if (isset($data['data']['ticket_id'])) {
            $this->db->table('tickets')
                ->where('id', $data['data']['ticket_id'])
                ->update(['last_activity_at' => date('Y-m-d H:i:s')]);

            // Set first response time if this is staff response
            if (empty($data['data']['is_from_client'])) {
                $ticket = $this->db->table('tickets')
                    ->where('id', $data['data']['ticket_id'])
                    ->get()
                    ->getRowArray();

                if ($ticket && empty($ticket['first_response_at'])) {
                    $this->db->table('tickets')
                        ->where('id', $data['data']['ticket_id'])
                        ->update(['first_response_at' => date('Y-m-d H:i:s')]);
                }
            }
        }
        return $data;
    }

    protected function logMessageCreated(array $data): array
    {
        if (isset($data['data']['ticket_id'])) {
            $activityModel = new ActivityLogModel();
            $activityModel->log(
                'ticket_message',
                $data['id'],
                'created',
                'Message added to ticket',
                ['ticket_id' => $data['data']['ticket_id']]
            );
        }
        return $data;
    }

    /**
     * Get messages for a ticket
     */
    public function getForTicket(string $ticketId, bool $includeInternal = true): array
    {
        $builder = $this->where('ticket_id', $ticketId);

        if (!$includeInternal) {
            $builder->where('is_internal', false);
        }

        return $builder->orderBy('created_at', 'ASC')->findAll();
    }

    /**
     * Get public messages only (for client portal)
     */
    public function getPublicForTicket(string $ticketId): array
    {
        return $this->getForTicket($ticketId, false);
    }

    /**
     * Add reply to ticket
     */
    public function addReply(string $ticketId, string $message, bool $isInternal = false, ?array $attachments = null): ?string
    {
        $data = [
            'ticket_id' => $ticketId,
            'message' => $message,
            'is_internal' => $isInternal,
            'is_from_client' => false,
        ];

        if ($attachments) {
            $data['attachments'] = json_encode($attachments);
        }

        if ($this->insert($data)) {
            return $this->getInsertID();
        }

        return null;
    }

    /**
     * Add client reply
     */
    public function addClientReply(string $ticketId, string $message, ?array $attachments = null): ?string
    {
        $data = [
            'ticket_id' => $ticketId,
            'message' => $message,
            'is_internal' => false,
            'is_from_client' => true,
        ];

        if ($attachments) {
            $data['attachments'] = json_encode($attachments);
        }

        if ($this->insert($data)) {
            // Reopen ticket if it was resolved/closed
            $this->db->table('tickets')
                ->where('id', $ticketId)
                ->whereIn('status', ['resolved', 'closed'])
                ->update(['status' => 'open']);

            return $this->getInsertID();
        }

        return null;
    }

    /**
     * Get message count for ticket
     */
    public function getCountForTicket(string $ticketId): int
    {
        return $this->where('ticket_id', $ticketId)->countAllResults();
    }
}
