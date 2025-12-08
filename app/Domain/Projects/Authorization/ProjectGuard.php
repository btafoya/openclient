<?php

namespace App\Domain\Projects\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Project Authorization Guard
 *
 * Implements fine-grained authorization logic for project operations.
 * Projects are tenant-scoped resources with additional member-based access control.
 *
 * Authorization Rules:
 * - Owner: Full access to all projects across all agencies
 * - Agency: Can view/edit projects for their agency
 * - Project Members: Can view projects they're assigned to (regardless of role)
 * - Direct Client: Can view projects where they're a member
 * - End Client: Can view projects where they're a member
 *
 * Project Membership:
 * Projects have a many-to-many relationship with users through project_members table.
 * This allows fine-grained access control beyond agency boundaries.
 */
class ProjectGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific project
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $project Project entity (array or object with 'id', 'agency_id')
     * @return bool True if user can view this project
     */
    public function canView(array $user, $project): bool
    {
        // Convert object to array if needed
        $project = is_object($project) ? (array) $project : $project;

        // Owner: can view all projects
        if ($user['role'] === 'owner') {
            return true;
        }

        // Check if user is assigned as a project member
        // This allows granular access control across roles
        if ($this->isProjectMember($user['id'], $project['id'])) {
            return true;
        }

        // Agency: can view if project belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($project['agency_id']) && $project['agency_id'] === $user['agency_id'];
        }

        // If not a member and not owner/agency, deny access
        return false;
    }

    /**
     * Check if user can create projects
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @return bool True if user can create projects
     */
    public function canCreate(array $user): bool
    {
        // Only Owner and Agency users can create projects
        // Clients (both Direct and End) cannot create projects
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a specific project
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $project Project entity to check
     * @return bool True if user can edit this project
     */
    public function canEdit(array $user, $project): bool
    {
        // Convert object to array if needed
        $project = is_object($project) ? (array) $project : $project;

        // Owner: can edit all projects
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if project belongs to their agency
        if ($user['role'] === 'agency' && isset($project['agency_id'])) {
            return $project['agency_id'] === $user['agency_id'];
        }

        // Project members with manager role can edit
        if ($this->isProjectManager($user['id'], $project['id'])) {
            return true;
        }

        // Regular clients cannot edit projects
        return false;
    }

    /**
     * Check if user can delete a specific project
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $project Project entity to check
     * @return bool True if user can delete this project
     */
    public function canDelete(array $user, $project): bool
    {
        // Only Owner can delete projects
        // This is a destructive operation restricted to platform administrators
        return $user['role'] === 'owner';
    }

    /**
     * Check if user is a member of a specific project
     *
     * @param string $userId User ID to check
     * @param string $projectId Project ID to check membership for
     * @return bool True if user is a project member
     */
    private function isProjectMember(string $userId, string $projectId): bool
    {
        $db = Database::connect();

        $query = $db->table('project_members')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->get();

        return $query->getNumRows() > 0;
    }

    /**
     * Check if user is a project manager
     *
     * Project managers have elevated permissions within a project.
     *
     * @param string $userId User ID to check
     * @param string $projectId Project ID to check manager status for
     * @return bool True if user is a project manager
     */
    private function isProjectManager(string $userId, string $projectId): bool
    {
        $db = Database::connect();

        $query = $db->table('project_members')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->where('role', 'manager')
            ->where('is_active', true)
            ->get();

        return $query->getNumRows() > 0;
    }

    /**
     * Get user's role within a project
     *
     * @param string $userId User ID
     * @param string $projectId Project ID
     * @return string|null Project role ('manager', 'member', etc.) or null if not a member
     */
    public function getProjectRole(string $userId, string $projectId): ?string
    {
        $db = Database::connect();

        $query = $db->table('project_members')
            ->select('role')
            ->where('user_id', $userId)
            ->where('project_id', $projectId)
            ->where('is_active', true)
            ->get();

        $result = $query->getRow();
        return $result ? $result->role : null;
    }

    /**
     * Check if user can manage project members
     *
     * Used for add/remove member operations.
     *
     * @param array $user User array
     * @param mixed $project Project entity
     * @return bool True if user can manage project members
     */
    public function canManageMembers(array $user, $project): bool
    {
        $project = is_object($project) ? (array) $project : $project;

        // Owner can manage all projects
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency can manage projects in their agency
        if ($user['role'] === 'agency' && isset($project['agency_id'])) {
            return $project['agency_id'] === $user['agency_id'];
        }

        // Project managers can manage members
        if ($this->isProjectManager($user['id'], $project['id'])) {
            return true;
        }

        return false;
    }

    /**
     * Get user's permission summary
     *
     * @param array $user User array
     * @param mixed|null $project Optional project entity
     * @return array Permission summary
     */
    public function getPermissionSummary(array $user, $project = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user),
        ];

        if ($project) {
            $summary['canView'] = $this->canView($user, $project);
            $summary['canEdit'] = $this->canEdit($user, $project);
            $summary['canDelete'] = $this->canDelete($user, $project);
            $summary['canManageMembers'] = $this->canManageMembers($user, $project);
            $summary['projectRole'] = $this->getProjectRole($user['id'], $project['id'] ?? null);
        }

        return $summary;
    }
}
