<?php

namespace App\Domain\Authorization;

/**
 * Authorization Guard Interface
 *
 * Defines the contract for fine-grained authorization checks at the service/business logic layer.
 * This is RBAC Layer 3 - provides resource-specific permission checks beyond HTTP middleware.
 *
 * Implementation Pattern:
 * - Each resource type (Invoice, Project, Client, etc.) has its own Guard implementation
 * - Guards encapsulate all authorization logic for that resource
 * - Guards are used in controllers before executing business logic
 * - Guards work in conjunction with database RLS (Layer 1) and HTTP middleware (Layer 2)
 *
 * Guard Methods:
 * - canView(): Can user see this specific resource?
 * - canCreate(): Can user create new resources of this type?
 * - canEdit(): Can user modify this specific resource?
 * - canDelete(): Can user remove this specific resource?
 *
 * Example Usage:
 * ```php
 * $guard = new InvoiceGuard();
 * $user = session()->get('user');
 * $invoice = $invoiceModel->find($id);
 *
 * if (!$guard->canView($user, $invoice)) {
 *     return $this->response->setStatusCode(403)->setJSON(['error' => 'Access denied']);
 * }
 * ```
 */
interface AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific resource
     *
     * @param array $user User array from session (must have 'id', 'role', 'agency_id')
     * @param mixed $resource Resource entity (array, object, or model) to check access for
     * @return bool True if user can view this resource, false otherwise
     */
    public function canView(array $user, $resource): bool;

    /**
     * Check if user can create new resources of this type
     *
     * @param array $user User array from session (must have 'id', 'role', 'agency_id')
     * @return bool True if user can create resources, false otherwise
     */
    public function canCreate(array $user): bool;

    /**
     * Check if user can edit a specific resource
     *
     * @param array $user User array from session (must have 'id', 'role', 'agency_id')
     * @param mixed $resource Resource entity to check edit permission for
     * @return bool True if user can edit this resource, false otherwise
     */
    public function canEdit(array $user, $resource): bool;

    /**
     * Check if user can delete a specific resource
     *
     * @param array $user User array from session (must have 'id', 'role', 'agency_id')
     * @param mixed $resource Resource entity to check delete permission for
     * @return bool True if user can delete this resource, false otherwise
     */
    public function canDelete(array $user, $resource): bool;
}
