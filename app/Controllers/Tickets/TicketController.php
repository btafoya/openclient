<?php

namespace App\Controllers\Tickets;

use App\Controllers\BaseController;
use App\Models\TicketModel;
use App\Models\TicketMessageModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Ticket Controller
 *
 * Handles support ticket operations.
 */
class TicketController extends BaseController
{
    protected TicketModel $ticketModel;
    protected TicketMessageModel $messageModel;

    public function __construct()
    {
        $this->ticketModel = new TicketModel();
        $this->messageModel = new TicketMessageModel();
    }

    /**
     * List tickets with filters
     */
    public function index(): ResponseInterface
    {
        $filters = [
            'status' => $this->request->getGet('status'),
            'priority' => $this->request->getGet('priority'),
            'assigned_to' => $this->request->getGet('assigned_to'),
            'client_id' => $this->request->getGet('client_id'),
            'project_id' => $this->request->getGet('project_id'),
            'category' => $this->request->getGet('category'),
        ];

        // Remove null values
        $filters = array_filter($filters, fn($v) => $v !== null);

        $tickets = $this->ticketModel->getFiltered($filters);

        return $this->response->setJSON([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Create a new ticket
     */
    public function store(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        $required = ['subject'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => ucfirst($field) . ' is required',
                ])->setStatusCode(400);
            }
        }

        // Set defaults
        $data['status'] = $data['status'] ?? 'open';
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['category'] = $data['category'] ?? 'general';
        $data['source'] = $data['source'] ?? 'web';

        if ($this->ticketModel->insert($data)) {
            $ticketId = $this->ticketModel->getInsertID();
            $ticket = $this->ticketModel->find($ticketId);

            // If there's an initial message/description, add it
            if (!empty($data['description'])) {
                $this->messageModel->insert([
                    'ticket_id' => $ticketId,
                    'message' => $data['description'],
                    'is_internal' => false,
                    'is_from_client' => false,
                ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $ticket,
                'message' => 'Ticket created successfully',
            ])->setStatusCode(201);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to create ticket',
            'validation_errors' => $this->ticketModel->errors(),
        ])->setStatusCode(400);
    }

    /**
     * Get ticket details with messages
     */
    public function show(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->getWithMessages($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Update a ticket
     */
    public function update(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        $data = $this->request->getJSON(true);

        // Remove fields that shouldn't be updated directly
        unset($data['id'], $data['ticket_number'], $data['created_by'], $data['created_at']);

        if ($this->ticketModel->update($id, $data)) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->ticketModel->find($id),
                'message' => 'Ticket updated successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to update ticket',
            'validation_errors' => $this->ticketModel->errors(),
        ])->setStatusCode(400);
    }

    /**
     * Delete a ticket
     */
    public function delete(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        if ($this->ticketModel->delete($id)) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Ticket deleted successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to delete ticket',
        ])->setStatusCode(500);
    }

    /**
     * Assign ticket to user
     */
    public function assign(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        $data = $this->request->getJSON(true);

        if (empty($data['user_id'])) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'User ID required',
            ])->setStatusCode(400);
        }

        if ($this->ticketModel->assignTo($id, $data['user_id'])) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->ticketModel->find($id),
                'message' => 'Ticket assigned successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to assign ticket',
        ])->setStatusCode(500);
    }

    /**
     * Resolve ticket
     */
    public function resolve(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        if ($this->ticketModel->resolve($id)) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->ticketModel->find($id),
                'message' => 'Ticket resolved',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to resolve ticket',
        ])->setStatusCode(500);
    }

    /**
     * Close ticket
     */
    public function close(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        if ($this->ticketModel->close($id)) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->ticketModel->find($id),
                'message' => 'Ticket closed',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to close ticket',
        ])->setStatusCode(500);
    }

    /**
     * Reopen ticket
     */
    public function reopen(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        if ($this->ticketModel->reopen($id)) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->ticketModel->find($id),
                'message' => 'Ticket reopened',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to reopen ticket',
        ])->setStatusCode(500);
    }

    /**
     * Add message to ticket
     */
    public function addMessage(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        $data = $this->request->getJSON(true);

        if (empty($data['message'])) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Message is required',
            ])->setStatusCode(400);
        }

        $isInternal = (bool) ($data['is_internal'] ?? false);
        $attachments = $data['attachments'] ?? null;

        $messageId = $this->messageModel->addReply($id, $data['message'], $isInternal, $attachments);

        if ($messageId) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->messageModel->find($messageId),
                'message' => 'Reply added successfully',
            ])->setStatusCode(201);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to add reply',
        ])->setStatusCode(500);
    }

    /**
     * Get ticket messages
     */
    public function getMessages(string $id): ResponseInterface
    {
        $ticket = $this->ticketModel->find($id);

        if (!$ticket) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Ticket not found',
            ])->setStatusCode(404);
        }

        $includeInternal = (bool) ($this->request->getGet('include_internal') ?? true);
        $messages = $this->messageModel->getForTicket($id, $includeInternal);

        return $this->response->setJSON([
            'success' => true,
            'data' => $messages,
        ]);
    }

    /**
     * Get ticket statistics
     */
    public function stats(): ResponseInterface
    {
        $stats = $this->ticketModel->getStatistics();

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get overdue tickets
     */
    public function overdue(): ResponseInterface
    {
        $tickets = $this->ticketModel->getOverdue();

        return $this->response->setJSON([
            'success' => true,
            'data' => $tickets,
        ]);
    }
}
