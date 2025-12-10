<?php

namespace App\Domain\RecurringInvoices\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Recurring Invoice Authorization Guard
 *
 * Implements fine-grained authorization logic for recurring invoice operations.
 * This is RBAC Layer 3 - provides resource-specific permission checks.
 *
 * Authorization Rules:
 * - Owner: Full access to all recurring invoices across all agencies
 * - Agency: Can manage recurring invoices for their agency only
 * - Direct Client: Can view recurring invoices assigned to their client
 * - End Client: CANNOT access recurring invoices
 */
class RecurringInvoiceGuard implements AuthorizationGuardInterface
{
    /**
     * Valid status transitions
     */
    private array $statusTransitions = [
        'active' => ['paused', 'cancelled'],
        'paused' => ['active', 'cancelled'],
        'completed' => [], // Terminal state
        'cancelled' => [], // Terminal state
    ];

    /**
     * Check if user can view a specific recurring invoice
     */
    public function canView(array $user, $recurringInvoice): bool
    {
        $recurringInvoice = is_object($recurringInvoice) ? (array) $recurringInvoice : $recurringInvoice;

        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'end_client') {
            return false;
        }

        if ($user['role'] === 'agency') {
            return isset($recurringInvoice['agency_id']) && $recurringInvoice['agency_id'] === $user['agency_id'];
        }

        if ($user['role'] === 'direct_client') {
            if (!isset($recurringInvoice['client_id'])) {
                return false;
            }
            return $this->isUserAssignedToClient($user['id'], $recurringInvoice['client_id']);
        }

        return false;
    }

    /**
     * Check if user can create recurring invoices
     */
    public function canCreate(array $user): bool
    {
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific recurring invoice
     */
    public function canEdit(array $user, $recurringInvoice): bool
    {
        $recurringInvoice = is_object($recurringInvoice) ? (array) $recurringInvoice : $recurringInvoice;

        // Completed and cancelled recurring invoices cannot be edited
        $nonEditableStatuses = ['completed', 'cancelled'];
        if (isset($recurringInvoice['status']) && in_array($recurringInvoice['status'], $nonEditableStatuses)) {
            return false;
        }

        if ($user['role'] === 'owner') {
            return true;
        }

        if ($user['role'] === 'agency') {
            return isset($recurringInvoice['agency_id']) && $recurringInvoice['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Check if user can delete a specific recurring invoice
     */
    public function canDelete(array $user, $recurringInvoice): bool
    {
        // Only owner can delete
        return $user['role'] === 'owner';
    }

    /**
     * Check if user can pause a recurring invoice
     */
    public function canPause(array $user, $recurringInvoice): bool
    {
        $recurringInvoice = is_object($recurringInvoice) ? (array) $recurringInvoice : $recurringInvoice;

        if ($recurringInvoice['status'] !== 'active') {
            return false;
        }

        return $this->canEdit($user, $recurringInvoice);
    }

    /**
     * Check if user can resume a recurring invoice
     */
    public function canResume(array $user, $recurringInvoice): bool
    {
        $recurringInvoice = is_object($recurringInvoice) ? (array) $recurringInvoice : $recurringInvoice;

        if ($recurringInvoice['status'] !== 'paused') {
            return false;
        }

        return $this->canEdit($user, $recurringInvoice);
    }

    /**
     * Check if user can cancel a recurring invoice
     */
    public function canCancel(array $user, $recurringInvoice): bool
    {
        $recurringInvoice = is_object($recurringInvoice) ? (array) $recurringInvoice : $recurringInvoice;

        $cancellableStatuses = ['active', 'paused'];
        if (!in_array($recurringInvoice['status'], $cancellableStatuses)) {
            return false;
        }

        return $this->canEdit($user, $recurringInvoice);
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
    public function getPermissionSummary(array $user, $recurringInvoice = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user),
            'canView' => $recurringInvoice ? $this->canView($user, $recurringInvoice) : null,
            'canEdit' => $recurringInvoice ? $this->canEdit($user, $recurringInvoice) : null,
            'canDelete' => $recurringInvoice ? $this->canDelete($user, $recurringInvoice) : null,
        ];

        if ($recurringInvoice) {
            $summary['canPause'] = $this->canPause($user, $recurringInvoice);
            $summary['canResume'] = $this->canResume($user, $recurringInvoice);
            $summary['canCancel'] = $this->canCancel($user, $recurringInvoice);
            $summary['allowedTransitions'] = $this->getAllowedTransitions($recurringInvoice['status'] ?? 'active');
        }

        return $summary;
    }
}
