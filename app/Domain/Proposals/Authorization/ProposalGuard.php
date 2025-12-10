<?php

namespace App\Domain\Proposals\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Proposal Authorization Guard
 *
 * Implements fine-grained authorization logic for proposal operations.
 * This is RBAC Layer 3 - provides resource-specific permission checks.
 *
 * Authorization Rules:
 * - Owner: Full access to all proposals across all agencies
 * - Agency: Can view/edit proposals for their agency only
 * - Direct Client: Can view proposals assigned to their client (via access token)
 * - End Client: Limited view access via access token only
 *
 * Status Workflow Rules:
 * - draft: Can edit, delete, send
 * - sent: Cannot edit, can be viewed, accepted, rejected
 * - viewed: Cannot edit, can be accepted, rejected
 * - accepted: Terminal state (can convert to invoice)
 * - rejected: Can revise (back to draft)
 * - expired: Can revise (back to draft)
 */
class ProposalGuard implements AuthorizationGuardInterface
{
    /**
     * Valid status transitions for proposal workflow
     */
    private array $statusTransitions = [
        'draft' => ['sent'],
        'sent' => ['viewed', 'accepted', 'rejected', 'expired'],
        'viewed' => ['accepted', 'rejected', 'expired'],
        'accepted' => [], // Terminal state
        'rejected' => ['draft'], // Can revise
        'expired' => ['draft'], // Can revise
    ];

    /**
     * Check if user can view a specific proposal
     */
    public function canView(array $user, $proposal): bool
    {
        $proposal = is_object($proposal) ? (array) $proposal : $proposal;

        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'agency') {
            return isset($proposal['agency_id']) && $proposal['agency_id'] === $user['agency_id'];
        }

        // Clients can view via access token (handled separately)
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            if (!isset($proposal['client_id'])) {
                return false;
            }
            return $this->isUserAssignedToClient($user['id'], $proposal['client_id']);
        }

        return false;
    }

    /**
     * Check if user can create proposals
     */
    public function canCreate(array $user): bool
    {
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific proposal
     */
    public function canEdit(array $user, $proposal): bool
    {
        $proposal = is_object($proposal) ? (array) $proposal : $proposal;

        // Only draft and revising proposals can be edited
        $editableStatuses = ['draft', 'rejected', 'expired'];
        if (!isset($proposal['status']) || !in_array($proposal['status'], $editableStatuses)) {
            return false;
        }

        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'agency') {
            return isset($proposal['agency_id']) && $proposal['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Check if user can delete a specific proposal
     */
    public function canDelete(array $user, $proposal): bool
    {
        $proposal = is_object($proposal) ? (array) $proposal : $proposal;

        // Only draft proposals can be deleted
        if (!isset($proposal['status']) || $proposal['status'] !== 'draft') {
            return false;
        }

        return $user['role'] === 'owner' ||
            ($user['role'] === 'agency' && $proposal['agency_id'] === $user['agency_id']);
    }

    /**
     * Check if user can send a proposal
     */
    public function canSend(array $user, $proposal): bool
    {
        $proposal = is_object($proposal) ? (array) $proposal : $proposal;

        if (!$this->canEdit($user, $proposal)) {
            return false;
        }

        return isset($proposal['status']) && $proposal['status'] === 'draft';
    }

    /**
     * Check if client can accept/reject proposal (via access token)
     */
    public function canRespond($proposal): bool
    {
        $proposal = is_object($proposal) ? (array) $proposal : $proposal;

        $respondableStatuses = ['sent', 'viewed'];
        return isset($proposal['status']) && in_array($proposal['status'], $respondableStatuses);
    }

    /**
     * Check if user can convert proposal to invoice
     */
    public function canConvertToInvoice(array $user, $proposal): bool
    {
        $proposal = is_object($proposal) ? (array) $proposal : $proposal;

        if ($proposal['status'] !== 'accepted') {
            return false;
        }

        // Already converted
        if (!empty($proposal['converted_to_invoice_id'])) {
            return false;
        }

        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'agency') {
            return isset($proposal['agency_id']) && $proposal['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Check if status transition is valid
     */
    public function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, $this->statusTransitions[$fromStatus] ?? []);
    }

    /**
     * Get allowed status transitions
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return $this->statusTransitions[$currentStatus] ?? [];
    }

    /**
     * Check if user is assigned to a client
     */
    private function isUserAssignedToClient(string $userId, string $clientId): bool
    {
        $db = Database::connect();

        $query = $db->table('client_users')
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return $query->getNumRows() > 0;
    }

    /**
     * Get permission summary for UI
     */
    public function getPermissionSummary(array $user, $proposal = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user),
            'canView' => $proposal ? $this->canView($user, $proposal) : null,
            'canEdit' => $proposal ? $this->canEdit($user, $proposal) : null,
            'canDelete' => $proposal ? $this->canDelete($user, $proposal) : null,
        ];

        if ($proposal) {
            $summary['canSend'] = $this->canSend($user, $proposal);
            $summary['canConvertToInvoice'] = $this->canConvertToInvoice($user, $proposal);
            $summary['allowedTransitions'] = $this->getAllowedTransitions($proposal['status'] ?? 'draft');
        }

        return $summary;
    }
}
