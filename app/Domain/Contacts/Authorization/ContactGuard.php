<?php

namespace App\Domain\Contacts\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Contact Authorization Guard
 *
 * Implements fine-grained authorization logic for contact operations.
 * Contacts are client-scoped resources that inherit client access rules.
 *
 * Authorization Rules:
 * - Owner: Full access to all contacts across all agencies
 * - Agency: Can view/edit contacts for clients they manage
 * - Direct Client: Can view contacts for clients they're assigned to (read-only)
 * - End Client: Can view contacts for clients they're assigned to (read-only)
 *
 * Contact-Client Relationship:
 * - Contacts belong to a client (client_id foreign key)
 * - Contact access is derived from client access
 * - Users who can view a client can view its contacts
 * - Only users who can edit a client can manage its contacts
 */
class ContactGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific contact
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $contact Contact entity (array or object with 'id', 'agency_id', 'client_id')
     * @return bool True if user can view this contact
     */
    public function canView(array $user, $contact): bool
    {
        // Convert object to array if needed
        $contact = is_object($contact) ? (array) $contact : $contact;

        // Owner: can view all contacts
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can view if contact belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($contact['agency_id']) && $contact['agency_id'] === $user['agency_id'];
        }

        // Direct Client and End Client: can view if they're assigned to the parent client
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            if (!isset($contact['client_id'])) {
                return false;
            }
            return $this->isUserAssignedToClient($user['id'], $contact['client_id']);
        }

        // Unknown role: deny access
        return false;
    }

    /**
     * Check if user can create contacts
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param string|null $clientId Optional client ID to check if user can create contacts for this client
     * @return bool True if user can create contacts
     */
    public function canCreate(array $user, ?string $clientId = null): bool
    {
        // Only Owner and Agency users can create contacts
        // Direct Clients and End Clients cannot create contact entities
        if (!in_array($user['role'], ['owner', 'agency'])) {
            return false;
        }

        // If specific client is provided, verify user can manage that client
        if ($clientId !== null) {
            // Owner can create contacts for any client
            if ($user['role'] === 'owner') {
                return true;
            }

            // Agency users can only create contacts for clients they manage
            if ($user['role'] === 'agency') {
                return $this->isClientInAgency($clientId, $user['agency_id']);
            }
        }

        return true;
    }

    /**
     * Check if user can edit a specific contact
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $contact Contact entity to check
     * @return bool True if user can edit this contact
     */
    public function canEdit(array $user, $contact): bool
    {
        // Convert object to array if needed
        $contact = is_object($contact) ? (array) $contact : $contact;

        // Owner: can edit all contacts
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if contact belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($contact['agency_id']) && $contact['agency_id'] === $user['agency_id'];
        }

        // Direct Clients and End Clients cannot edit contact records
        // This is by design - contact data management is restricted to Owner/Agency
        return false;
    }

    /**
     * Check if user can delete a specific contact
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $contact Contact entity to check
     * @return bool True if user can delete this contact
     */
    public function canDelete(array $user, $contact): bool
    {
        // Delete permissions same as edit permissions
        // Only users who can edit contacts can delete them
        return $this->canEdit($user, $contact);
    }

    /**
     * Helper: Check if a user is assigned to a client
     *
     * @param string $userId User ID to check
     * @param string $clientId Client ID to check
     * @return bool True if user is assigned to this client
     */
    private function isUserAssignedToClient(string $userId, string $clientId): bool
    {
        $db = Database::connect();

        $result = $db->table('client_users')
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->countAllResults();

        return $result > 0;
    }

    /**
     * Helper: Check if a client belongs to a specific agency
     *
     * @param string $clientId Client ID to check
     * @param string $agencyId Agency ID to check
     * @return bool True if client belongs to the agency
     */
    private function isClientInAgency(string $clientId, string $agencyId): bool
    {
        $db = Database::connect();

        $result = $db->table('clients')
            ->where('id', $clientId)
            ->where('agency_id', $agencyId)
            ->countAllResults();

        return $result > 0;
    }

    /**
     * Batch check: Get list of contact IDs user can view
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param array $contactIds List of contact IDs to filter
     * @return array Filtered list of contact IDs the user can view
     */
    public function filterViewableContacts(array $user, array $contactIds): array
    {
        if (empty($contactIds)) {
            return [];
        }

        $db = Database::connect();

        // Owner: can view all
        if ($user['role'] === 'owner') {
            return $contactIds;
        }

        // Agency: filter by agency_id
        if ($user['role'] === 'agency') {
            $viewable = $db->table('contacts')
                ->select('id')
                ->whereIn('id', $contactIds)
                ->where('agency_id', $user['agency_id'])
                ->get()
                ->getResultArray();

            return array_column($viewable, 'id');
        }

        // Direct/End Client: filter by client assignment
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            $viewable = $db->table('contacts')
                ->select('contacts.id')
                ->join('client_users', 'client_users.client_id = contacts.client_id')
                ->whereIn('contacts.id', $contactIds)
                ->where('client_users.user_id', $user['id'])
                ->get()
                ->getResultArray();

            return array_column($viewable, 'id');
        }

        return [];
    }

    /**
     * Batch check: Get list of contact IDs user can edit
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param array $contactIds List of contact IDs to filter
     * @return array Filtered list of contact IDs the user can edit
     */
    public function filterEditableContacts(array $user, array $contactIds): array
    {
        if (empty($contactIds)) {
            return [];
        }

        $db = Database::connect();

        // Owner: can edit all
        if ($user['role'] === 'owner') {
            return $contactIds;
        }

        // Agency: filter by agency_id
        if ($user['role'] === 'agency') {
            $editable = $db->table('contacts')
                ->select('id')
                ->whereIn('id', $contactIds)
                ->where('agency_id', $user['agency_id'])
                ->get()
                ->getResultArray();

            return array_column($editable, 'id');
        }

        // Direct/End Clients cannot edit
        return [];
    }
}
