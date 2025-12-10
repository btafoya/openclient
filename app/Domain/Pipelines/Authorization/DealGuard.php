<?php

namespace App\Domain\Pipelines\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;

/**
 * Deal Authorization Guard
 *
 * Implements fine-grained authorization logic for deal operations.
 * Deals are agency-scoped resources with optional user assignment.
 *
 * Authorization Rules:
 * - Owner: Full access to all deals across all agencies
 * - Agency: Can view/edit deals for their agency
 * - Direct Client: Can view deals linked to their client record
 * - End Client: Can view deals linked to their client record
 */
class DealGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific deal
     */
    public function canView(array $user, $deal): bool
    {
        $deal = is_object($deal) ? (array) $deal : $deal;

        // Owner: can view all deals
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can view if deal belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($deal['agency_id']) && $deal['agency_id'] === $user['agency_id'];
        }

        // Direct Client and End Client: can view if deal is linked to their client
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            if (!isset($deal['client_id'])) {
                return false;
            }
            return $this->isUserAssignedToClient($user['id'], $deal['client_id']);
        }

        return false;
    }

    /**
     * Check if user can create deals
     */
    public function canCreate(array $user): bool
    {
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific deal
     */
    public function canEdit(array $user, $deal): bool
    {
        $deal = is_object($deal) ? (array) $deal : $deal;

        // Owner: can edit all deals
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if deal belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($deal['agency_id']) && $deal['agency_id'] === $user['agency_id'];
        }

        // Clients cannot edit deals
        return false;
    }

    /**
     * Check if user can delete a specific deal
     */
    public function canDelete(array $user, $deal): bool
    {
        return $this->canEdit($user, $deal);
    }

    /**
     * Check if user can move deal between stages
     */
    public function canMoveStage(array $user, $deal): bool
    {
        return $this->canEdit($user, $deal);
    }

    /**
     * Check if user can mark deal as won/lost
     */
    public function canCloseDeal(array $user, $deal): bool
    {
        return $this->canEdit($user, $deal);
    }

    /**
     * Check if user can convert deal to project
     */
    public function canConvertToProject(array $user, $deal): bool
    {
        return $this->canEdit($user, $deal);
    }

    /**
     * Check if user is assigned to a specific client
     */
    protected function isUserAssignedToClient(string $userId, string $clientId): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('client_users');

        $result = $builder->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->countAllResults();

        return $result > 0;
    }
}
