<?php

namespace App\Domain\Invoices\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Invoice Authorization Guard
 *
 * Implements fine-grained authorization logic for invoice operations.
 * This is RBAC Layer 3 - provides resource-specific permission checks.
 *
 * Authorization Rules:
 * - Owner: Full access to all invoices across all agencies
 * - Agency: Can view/edit invoices for their agency only
 * - Direct Client: Can view invoices assigned to their client record
 * - End Client: CANNOT access invoices (financial restriction)
 *
 * Status Workflow Rules:
 * - draft: Can edit, delete, send
 * - sent: Cannot edit, can mark as viewed/paid/overdue
 * - viewed: Cannot edit, can mark as paid/overdue
 * - paid: Terminal state, no further actions
 * - overdue: Cannot edit, can mark as paid/cancelled
 * - cancelled: Terminal state, no further actions
 *
 * Defense in Depth:
 * - Layer 1 (Database RLS): Enforces agency_id filtering at SQL level
 * - Layer 2 (HTTP Middleware): Blocks End Clients from /invoices routes
 * - Layer 3 (Service Guards): Fine-grained per-resource checks ← THIS CLASS
 */
class InvoiceGuard implements AuthorizationGuardInterface
{
    /**
     * Valid status transitions for invoice workflow
     */
    private array $statusTransitions = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['viewed', 'paid', 'overdue', 'cancelled'],
        'viewed' => ['paid', 'overdue', 'cancelled'],
        'paid' => [], // Terminal state
        'overdue' => ['paid', 'cancelled'],
        'cancelled' => [], // Terminal state
    ];
    /**
     * Check if user can view a specific invoice
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $invoice Invoice entity (array or object with 'id', 'agency_id', 'client_id')
     * @return bool True if user can view this invoice
     */
    public function canView(array $user, $invoice): bool
    {
        // Convert object to array if needed
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        // Owner: always can view all invoices
        if ($user['role'] === 'owner') {
            return true;
        }

        // End Client: CANNOT view invoices (financial restriction)
        // This should have been blocked by HTTP middleware, but defense in depth
        if ($user['role'] === 'end_client') {
            return false;
        }

        // Agency: can view if invoice belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($invoice['agency_id']) && $invoice['agency_id'] === $user['agency_id'];
        }

        // Direct Client: can view if invoice is for a client they're assigned to
        if ($user['role'] === 'direct_client') {
            if (!isset($invoice['client_id'])) {
                return false;
            }
            return $this->isUserAssignedToClient($user['id'], $invoice['client_id']);
        }

        // Unknown role: deny access
        return false;
    }

    /**
     * Check if user can create invoices
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @return bool True if user can create invoices
     */
    public function canCreate(array $user): bool
    {
        // Only Owner and Agency users can create invoices
        // Direct Clients and End Clients cannot create invoices
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific invoice
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $invoice Invoice entity to check
     * @return bool True if user can edit this invoice
     */
    public function canEdit(array $user, $invoice): bool
    {
        // Convert object to array if needed
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        // Owner: can edit all invoices
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if invoice belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($invoice['agency_id']) && $invoice['agency_id'] === $user['agency_id'];
        }

        // Direct Clients and End Clients cannot edit invoices
        return false;
    }

    /**
     * Check if user can delete a specific invoice
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $invoice Invoice entity to check
     * @return bool True if user can delete this invoice
     */
    public function canDelete(array $user, $invoice): bool
    {
        // Only Owner can delete invoices
        // This is a destructive operation restricted to platform administrators
        return $user['role'] === 'owner';
    }

    /**
     * Check if a user is assigned to a specific client
     *
     * Used for Direct Client authorization - checks if user has relationship to client.
     *
     * @param string $userId User ID to check
     * @param string $clientId Client ID to check relationship with
     * @return bool True if user is assigned to this client
     */
    private function isUserAssignedToClient(string $userId, string $clientId): bool
    {
        // Get database connection
        $db = Database::connect();

        // Check if user is directly assigned to this client
        // Assumes a client_users junction table exists
        $query = $db->table('client_users')
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return $query->getNumRows() > 0;
    }

    /**
     * Check if invoice belongs to user's agency
     *
     * Helper method for agency-scoped authorization checks.
     *
     * @param array $user User array with 'agency_id'
     * @param mixed $invoice Invoice entity with 'agency_id'
     * @return bool True if invoice belongs to user's agency
     */
    private function belongsToUsersAgency(array $user, $invoice): bool
    {
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        if (!isset($user['agency_id']) || !isset($invoice['agency_id'])) {
            return false;
        }

        return $invoice['agency_id'] === $user['agency_id'];
    }

    /**
     * Check if user can send an invoice (draft → sent transition)
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $invoice Invoice entity to check
     * @return bool True if user can send this invoice
     */
    public function canSend(array $user, $invoice): bool
    {
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        // Must have edit permission
        if (!$this->canEdit($user, $invoice)) {
            return false;
        }

        // Only draft invoices can be sent
        return isset($invoice['status']) && $invoice['status'] === 'draft';
    }

    /**
     * Check if user can mark an invoice as paid
     *
     * @param array $user User array
     * @param mixed $invoice Invoice entity
     * @return bool True if user can mark as paid
     */
    public function canMarkAsPaid(array $user, $invoice): bool
    {
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        // Must have edit permission base
        if ($user['role'] !== 'owner' && $user['role'] !== 'agency') {
            return false;
        }

        // Agency must be same
        if ($user['role'] === 'agency' && $invoice['agency_id'] !== $user['agency_id']) {
            return false;
        }

        // Check valid status transition
        $currentStatus = $invoice['status'] ?? 'draft';
        return in_array('paid', $this->statusTransitions[$currentStatus] ?? []);
    }

    /**
     * Check if a status transition is valid
     *
     * @param string $fromStatus Current status
     * @param string $toStatus Target status
     * @return bool True if transition is valid
     */
    public function isValidStatusTransition(string $fromStatus, string $toStatus): bool
    {
        return in_array($toStatus, $this->statusTransitions[$fromStatus] ?? []);
    }

    /**
     * Get allowed status transitions for current status
     *
     * @param string $currentStatus Current invoice status
     * @return array List of allowed next statuses
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return $this->statusTransitions[$currentStatus] ?? [];
    }

    /**
     * Check if user can edit line items on an invoice
     * (Line items can only be edited on draft invoices)
     *
     * @param array $user User array
     * @param mixed $invoice Invoice entity
     * @return bool True if user can edit line items
     */
    public function canEditLineItems(array $user, $invoice): bool
    {
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        // Must have basic edit permission
        if (!$this->canEdit($user, $invoice)) {
            return false;
        }

        // Only draft invoices allow line item editing
        return isset($invoice['status']) && $invoice['status'] === 'draft';
    }

    /**
     * Check if user can download PDF of an invoice
     *
     * @param array $user User array
     * @param mixed $invoice Invoice entity
     * @return bool True if user can download PDF
     */
    public function canDownloadPdf(array $user, $invoice): bool
    {
        // Same as view permission - anyone who can see it can download PDF
        return $this->canView($user, $invoice);
    }

    /**
     * Check if user can resend an invoice email
     *
     * @param array $user User array
     * @param mixed $invoice Invoice entity
     * @return bool True if user can resend
     */
    public function canResend(array $user, $invoice): bool
    {
        $invoice = is_object($invoice) ? (array) $invoice : $invoice;

        // Must have edit permission
        if (!$this->canEdit($user, $invoice)) {
            return false;
        }

        // Only sent, viewed, or overdue invoices can be resent
        $resendableStatuses = ['sent', 'viewed', 'overdue'];
        return isset($invoice['status']) && in_array($invoice['status'], $resendableStatuses);
    }

    /**
     * Get user's permission summary for debugging/auditing
     *
     * Returns array of permissions user has for invoice operations.
     * Useful for UI (show/hide buttons) and debugging.
     *
     * @param array $user User array
     * @param mixed|null $invoice Optional invoice entity for resource-specific permissions
     * @return array Permission summary ['canView' => bool, 'canCreate' => bool, ...]
     */
    public function getPermissionSummary(array $user, $invoice = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user),
            'canView' => $invoice ? $this->canView($user, $invoice) : null,
            'canEdit' => $invoice ? $this->canEdit($user, $invoice) : null,
            'canDelete' => $invoice ? $this->canDelete($user, $invoice) : null,
        ];

        if ($invoice) {
            $summary['canSend'] = $this->canSend($user, $invoice);
            $summary['canMarkAsPaid'] = $this->canMarkAsPaid($user, $invoice);
            $summary['canEditLineItems'] = $this->canEditLineItems($user, $invoice);
            $summary['canDownloadPdf'] = $this->canDownloadPdf($user, $invoice);
            $summary['canResend'] = $this->canResend($user, $invoice);
            $summary['allowedTransitions'] = $this->getAllowedTransitions($invoice['status'] ?? 'draft');
        }

        return $summary;
    }
}
