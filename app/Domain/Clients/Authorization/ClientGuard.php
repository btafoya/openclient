<?php

namespace App\Domain\Clients\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Client Authorization Guard
 *
 * Implements fine-grained authorization logic for client operations.
 * Clients are tenant-scoped resources with additional user assignment rules.
 *
 * Authorization Rules:
 * - Owner: Full access to all clients across all agencies
 * - Agency: Can view/edit clients for their agency (aligned with RLS)
 * - Direct Client: Can view their own client record only
 * - End Client: Can view their own client record only
 *
 * Client-User Relationships:
 * - Direct Clients are assigned to a specific client record via client_users table
 * - End Clients are also assigned to a client record via client_users table
 * - This allows multi-user access to a single client entity
 */
class ClientGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific client
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $client Client entity (array or object with 'id', 'agency_id')
     * @return bool True if user can view this client
     */
    public function canView(array $user, $client): bool
    {
        // Convert object to array if needed
        $client = is_object($client) ? (array) $client : $client;

        // Owner: can view all clients
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can view if client belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($client['agency_id']) && $client['agency_id'] === $user['agency_id'];
        }

        // Direct Client and End Client: can view if they're assigned to this client
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            if (!isset($client['id'])) {
                return false;
            }
            return $this->isUserAssignedToClient($user['id'], $client['id']);
        }

        // Unknown role: deny access
        return false;
    }

    /**
     * Check if user can create clients
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @return bool True if user can create clients
     */
    public function canCreate(array $user): bool
    {
        // Only Owner and Agency users can create clients
        // Direct Clients and End Clients cannot create new client entities
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific client
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $client Client entity to check
     * @return bool True if user can edit this client
     */
    public function canEdit(array $user, $client): bool
    {
        // Convert object to array if needed
        $client = is_object($client) ? (array) $client : $client;

        // Owner: can edit all clients
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if client belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($client['agency_id']) && $client['agency_id'] === $user['agency_id'];
        }

        // Direct Clients and End Clients cannot edit client records
        // This is by design - client data management is restricted to Owner/Agency
        return false;
    }

    /**
     * Check if user can delete a specific client
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $client Client entity to check
     * @return bool True if user can delete this client
     */
    public function canDelete(array $user, $client): bool
    {
        // Only Owner can delete clients
        // This is a destructive operation restricted to platform administrators
        return $user['role'] === 'owner';
    }

    /**
     * Check if a user is assigned to a specific client
     *
     * Used for Direct Client and End Client authorization.
     *
     * @param string $userId User ID to check
     * @param string $clientId Client ID to check relationship with
     * @return bool True if user is assigned to this client
     */
    private function isUserAssignedToClient(string $userId, string $clientId): bool
    {
        // Get database connection
        $db = Database::connect();

        // Check if user is assigned to this client via client_users junction table
        $query = $db->table('client_users')
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->where('is_active', true)
            ->get();

        return $query->getNumRows() > 0;
    }

    /**
     * Check if client belongs to user's agency
     *
     * Helper method for agency-scoped authorization checks.
     *
     * @param array $user User array with 'agency_id'
     * @param mixed $client Client entity with 'agency_id'
     * @return bool True if client belongs to user's agency
     */
    private function belongsToUsersAgency(array $user, $client): bool
    {
        $client = is_object($client) ? (array) $client : $client;

        if (!isset($user['agency_id']) || !isset($client['agency_id'])) {
            return false;
        }

        return $client['agency_id'] === $user['agency_id'];
    }

    /**
     * Get all users assigned to a specific client
     *
     * Useful for client management interfaces showing who has access.
     *
     * @param string $clientId Client ID to get assigned users for
     * @return array Array of user assignments with user_id, role, and status
     */
    public function getAssignedUsers(string $clientId): array
    {
        $db = Database::connect();

        $query = $db->table('client_users')
            ->select('client_users.user_id, client_users.is_active, users.name, users.email, users.role')
            ->join('users', 'users.id = client_users.user_id')
            ->where('client_users.client_id', $clientId)
            ->get();

        return $query->getResultArray();
    }

    /**
     * Check if user can manage client user assignments
     *
     * Used for add/remove user operations on client records.
     *
     * @param array $user User array
     * @param mixed $client Client entity
     * @return bool True if user can manage client user assignments
     */
    public function canManageUsers(array $user, $client): bool
    {
        $client = is_object($client) ? (array) $client : $client;

        // Owner can manage all clients
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency can manage clients in their agency
        if ($user['role'] === 'agency' && isset($client['agency_id'])) {
            return $client['agency_id'] === $user['agency_id'];
        }

        // Direct Clients and End Clients cannot manage user assignments
        return false;
    }

    /**
     * Get user's permission summary
     *
     * Returns array of permissions user has for client operations.
     * Useful for UI (show/hide buttons) and debugging.
     *
     * @param array $user User array
     * @param mixed|null $client Optional client entity for resource-specific permissions
     * @return array Permission summary ['canView' => bool, 'canCreate' => bool, ...]
     */
    public function getPermissionSummary(array $user, $client = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user),
        ];

        if ($client) {
            $summary['canView'] = $this->canView($user, $client);
            $summary['canEdit'] = $this->canEdit($user, $client);
            $summary['canDelete'] = $this->canDelete($user, $client);
            $summary['canManageUsers'] = $this->canManageUsers($user, $client);
            $summary['isAssignedUser'] = in_array($user['role'], ['direct_client', 'end_client'])
                ? $this->isUserAssignedToClient($user['id'], $client['id'] ?? null)
                : null;
        }

        return $summary;
    }
}
