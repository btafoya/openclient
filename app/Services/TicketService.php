<?php

namespace App\Services;

use App\Models\TicketModel;
use App\Models\TicketMessageModel;

/**
 * Ticket Service
 *
 * Handles ticket creation, updates, and notifications.
 */
class TicketService
{
    protected TicketModel $ticketModel;
    protected TicketMessageModel $messageModel;
    protected EmailQueueService $emailService;
    protected ActivityLogService $activityLog;

    public function __construct()
    {
        $this->ticketModel = new TicketModel();
        $this->messageModel = new TicketMessageModel();
        $this->emailService = new EmailQueueService();
        $this->activityLog = new ActivityLogService();
    }

    /**
     * Create a new ticket
     */
    public function create(array $data, bool $sendNotification = true): array
    {
        // Set defaults
        $data['status'] = $data['status'] ?? 'open';
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['category'] = $data['category'] ?? 'general';
        $data['source'] = $data['source'] ?? 'web';

        if (!$this->ticketModel->insert($data)) {
            return [
                'success' => false,
                'error' => 'Failed to create ticket',
                'validation_errors' => $this->ticketModel->errors(),
            ];
        }

        $ticketId = $this->ticketModel->getInsertID();
        $ticket = $this->ticketModel->find($ticketId);

        // Add initial message if description provided
        if (!empty($data['description'])) {
            $this->messageModel->insert([
                'ticket_id' => $ticketId,
                'message' => $data['description'],
                'is_internal' => false,
                'is_from_client' => $data['is_from_client'] ?? false,
            ]);
        }

        // Log activity
        $this->activityLog->logTicketAction(
            $ticketId,
            'created',
            "Ticket created: {$ticket['subject']}"
        );

        // Send notification
        if ($sendNotification && !empty($data['client_email'])) {
            $this->emailService->queueTicketNotification($ticket, 'created');
        }

        return [
            'success' => true,
            'ticket' => $ticket,
        ];
    }

    /**
     * Add reply to ticket
     */
    public function addReply(string $ticketId, string $message, bool $isInternal = false, bool $isFromClient = false): array
    {
        $ticket = $this->ticketModel->find($ticketId);

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found',
            ];
        }

        $messageData = [
            'ticket_id' => $ticketId,
            'message' => $message,
            'is_internal' => $isInternal,
            'is_from_client' => $isFromClient,
        ];

        if (!$this->messageModel->insert($messageData)) {
            return [
                'success' => false,
                'error' => 'Failed to add reply',
            ];
        }

        $messageId = $this->messageModel->getInsertID();

        // Log activity
        $this->activityLog->logTicketAction(
            $ticketId,
            'reply_added',
            $isInternal ? 'Internal note added to ticket' : 'Reply added to ticket'
        );

        // Send notification for non-internal replies
        if (!$isInternal) {
            $this->emailService->queueTicketNotification($ticket, 'reply');
        }

        return [
            'success' => true,
            'message' => $this->messageModel->find($messageId),
        ];
    }

    /**
     * Assign ticket to user
     */
    public function assign(string $ticketId, string $userId, bool $sendNotification = true): array
    {
        $ticket = $this->ticketModel->find($ticketId);

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found',
            ];
        }

        if (!$this->ticketModel->assignTo($ticketId, $userId)) {
            return [
                'success' => false,
                'error' => 'Failed to assign ticket',
            ];
        }

        $updatedTicket = $this->ticketModel->find($ticketId);

        // Log activity
        $this->activityLog->logTicketAction(
            $ticketId,
            'assigned',
            "Ticket assigned to user {$userId}"
        );

        return [
            'success' => true,
            'ticket' => $updatedTicket,
        ];
    }

    /**
     * Resolve ticket
     */
    public function resolve(string $ticketId, ?string $resolutionMessage = null): array
    {
        $ticket = $this->ticketModel->find($ticketId);

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found',
            ];
        }

        if (!$this->ticketModel->resolve($ticketId)) {
            return [
                'success' => false,
                'error' => 'Failed to resolve ticket',
            ];
        }

        // Add resolution message if provided
        if ($resolutionMessage) {
            $this->messageModel->addReply($ticketId, $resolutionMessage, false);
        }

        $updatedTicket = $this->ticketModel->find($ticketId);

        // Log activity
        $this->activityLog->logTicketAction(
            $ticketId,
            'resolved',
            "Ticket resolved: {$ticket['subject']}"
        );

        // Send notification
        $this->emailService->queueTicketNotification($updatedTicket, 'resolved');

        return [
            'success' => true,
            'ticket' => $updatedTicket,
        ];
    }

    /**
     * Close ticket
     */
    public function close(string $ticketId): array
    {
        $ticket = $this->ticketModel->find($ticketId);

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found',
            ];
        }

        if (!$this->ticketModel->close($ticketId)) {
            return [
                'success' => false,
                'error' => 'Failed to close ticket',
            ];
        }

        $updatedTicket = $this->ticketModel->find($ticketId);

        // Log activity
        $this->activityLog->logTicketAction(
            $ticketId,
            'closed',
            "Ticket closed: {$ticket['subject']}"
        );

        return [
            'success' => true,
            'ticket' => $updatedTicket,
        ];
    }

    /**
     * Reopen ticket
     */
    public function reopen(string $ticketId): array
    {
        $ticket = $this->ticketModel->find($ticketId);

        if (!$ticket) {
            return [
                'success' => false,
                'error' => 'Ticket not found',
            ];
        }

        if (!$this->ticketModel->reopen($ticketId)) {
            return [
                'success' => false,
                'error' => 'Failed to reopen ticket',
            ];
        }

        $updatedTicket = $this->ticketModel->find($ticketId);

        // Log activity
        $this->activityLog->logTicketAction(
            $ticketId,
            'reopened',
            "Ticket reopened: {$ticket['subject']}"
        );

        return [
            'success' => true,
            'ticket' => $updatedTicket,
        ];
    }

    /**
     * Get ticket with full details
     */
    public function getTicketDetails(string $ticketId): ?array
    {
        return $this->ticketModel->getWithMessages($ticketId);
    }

    /**
     * Get overdue tickets
     */
    public function getOverdueTickets(): array
    {
        return $this->ticketModel->getOverdue();
    }

    /**
     * Get ticket statistics
     */
    public function getStatistics(): array
    {
        return $this->ticketModel->getStatistics();
    }

    /**
     * Process overdue ticket notifications
     */
    public function processOverdueNotifications(): int
    {
        $overdue = $this->ticketModel->getOverdue();
        $count = 0;

        foreach ($overdue as $ticket) {
            $this->emailService->queueTicketNotification($ticket, 'overdue');
            $count++;
        }

        return $count;
    }
}
