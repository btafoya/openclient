<?php

namespace App\Domain\Pipelines\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;

/**
 * Pipeline Authorization Guard
 *
 * Implements fine-grained authorization logic for pipeline operations.
 * Pipelines are agency-scoped resources.
 *
 * Authorization Rules:
 * - Owner: Full access to all pipelines across all agencies
 * - Agency: Can view/edit pipelines for their agency
 * - Direct Client: No access to pipelines
 * - End Client: No access to pipelines
 */
class PipelineGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific pipeline
     */
    public function canView(array $user, $pipeline): bool
    {
        $pipeline = is_object($pipeline) ? (array) $pipeline : $pipeline;

        // Owner: can view all pipelines
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can view if pipeline belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($pipeline['agency_id']) && $pipeline['agency_id'] === $user['agency_id'];
        }

        // Clients: no access to pipelines
        return false;
    }

    /**
     * Check if user can create pipelines
     */
    public function canCreate(array $user): bool
    {
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific pipeline
     */
    public function canEdit(array $user, $pipeline): bool
    {
        $pipeline = is_object($pipeline) ? (array) $pipeline : $pipeline;

        // Owner: can edit all pipelines
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if pipeline belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($pipeline['agency_id']) && $pipeline['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Check if user can delete a specific pipeline
     */
    public function canDelete(array $user, $pipeline): bool
    {
        // Same rules as edit
        return $this->canEdit($user, $pipeline);
    }

    /**
     * Check if user can manage pipeline stages
     */
    public function canManageStages(array $user, $pipeline): bool
    {
        // Same rules as edit
        return $this->canEdit($user, $pipeline);
    }
}
