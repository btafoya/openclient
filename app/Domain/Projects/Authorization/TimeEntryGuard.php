<?php

namespace App\Domain\Projects\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Time Entry Authorization Guard
 *
 * Implements fine-grained authorization logic for time entry operations.
 * Time entries are tenant-scoped resources tied to users, projects, and optionally tasks.
 *
 * Authorization Rules:
 * - Owner: Full access to all time entries across all agencies
 * - Agency: Can view/edit time entries for their agency
 * - Entry Owner: Can view and edit their own time entries
 * - Project Members: Can view time entries in projects they're assigned to
 * - Managers: Cannot edit other users' time entries (time integrity)
 */
class TimeEntryGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific time entry
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $entry Time entry entity (array or object with 'id', 'agency_id', 'user_id', 'project_id')
     * @return bool True if user can view this time entry
     */
    public function canView(array $user, $entry): bool
    {
        // Convert object to array if needed
        $entry = is_object($entry) ? (array) $entry : $entry;

        // Owner: can view all time entries
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can view if entry belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($entry['agency_id']) && $entry['agency_id'] === $user['agency_id'];
        }

        // Users can view their own time entries
        if (isset($entry['user_id']) && $entry['user_id'] === $user['id']) {
            return true;
        }

        // Project members can view time entries in their projects
        if (isset($entry['project_id']) && $this->isProjectMember($user['id'], $entry['project_id'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can create time entries
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param string|null $projectId Optional project ID for project-specific creation check
     * @return bool True if user can create time entries
     */
    public function canCreate(array $user, ?string $projectId = null): bool
    {
        // All authenticated users can create time entries
        // Agency restriction enforced by RLS at database level
        if ($projectId) {
            // If project specified, verify user has access
            return $this->canAccessProject($user, $projectId);
        }

        return true;
    }

    /**
     * Check if user can edit a specific time entry
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $entry Time entry entity to check
     * @return bool True if user can edit this time entry
     */
    public function canEdit(array $user, $entry): bool
    {
        // Convert object to array if needed
        $entry = is_object($entry) ? (array) $entry : $entry;

        // Owner: can edit all time entries
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if entry belongs to their agency
        if ($user['role'] === 'agency' && isset($entry['agency_id'])) {
            return $entry['agency_id'] === $user['agency_id'];
        }

        // Users can ONLY edit their own time entries (time integrity)
        if (isset($entry['user_id']) && $entry['user_id'] === $user['id']) {
            return true;
        }

        // Managers cannot edit other users' time entries
        return false;
    }

    /**
     * Check if user can delete a specific time entry
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $entry Time entry entity to check
     * @return bool True if user can delete this time entry
     */
    public function canDelete(array $user, $entry): bool
    {
        // Convert object to array if needed
        $entry = is_object($entry) ? (array) $entry : $entry;

        // Owner: can delete all time entries
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can delete if entry belongs to their agency
        if ($user['role'] === 'agency' && isset($entry['agency_id'])) {
            return $entry['agency_id'] === $user['agency_id'];
        }

        // Users can delete their own time entries
        if (isset($entry['user_id']) && $entry['user_id'] === $user['id']) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can start/stop timers
     *
     * @param array $user User array
     * @param string|null $projectId Optional project ID
     * @return bool True if user can use timer
     */
    public function canUseTimer(array $user, ?string $projectId = null): bool
    {
        // All users can use timer for their own entries
        if ($projectId) {
            return $this->canAccessProject($user, $projectId);
        }

        return true;
    }

    /**
     * Check if user can toggle billable status
     *
     * @param array $user User array
     * @param mixed $entry Time entry entity
     * @return bool True if user can toggle billable status
     */
    public function canToggleBillable(array $user, $entry): bool
    {
        $entry = is_object($entry) ? (array) $entry : $entry;

        // Owner and Agency can toggle billable
        if (in_array($user['role'], ['owner', 'agency'])) {
            if ($user['role'] === 'agency' && isset($entry['agency_id'])) {
                return $entry['agency_id'] === $user['agency_id'];
            }
            return true;
        }

        // Regular users cannot toggle billable status
        return false;
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
     * Check if user can access a specific project
     *
     * @param array $user User array
     * @param string $projectId Project ID
     * @return bool True if user can access this project
     */
    private function canAccessProject(array $user, string $projectId): bool
    {
        // Owner can access all projects
        if ($user['role'] === 'owner') {
            return true;
        }

        $db = Database::connect();

        // Check if project belongs to user's agency
        if ($user['role'] === 'agency') {
            $query = $db->table('projects')
                ->where('id', $projectId)
                ->where('agency_id', $user['agency_id'])
                ->where('deleted_at', null)
                ->get();

            if ($query->getNumRows() > 0) {
                return true;
            }
        }

        // Check if user is a project member
        return $this->isProjectMember($user['id'], $projectId);
    }

    /**
     * Get user's permission summary
     *
     * @param array $user User array
     * @param mixed|null $entry Optional time entry entity
     * @param string|null $projectId Optional project ID for creation check
     * @return array Permission summary
     */
    public function getPermissionSummary(array $user, $entry = null, ?string $projectId = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user, $projectId),
            'canUseTimer' => $this->canUseTimer($user, $projectId),
        ];

        if ($entry) {
            $summary['canView'] = $this->canView($user, $entry);
            $summary['canEdit'] = $this->canEdit($user, $entry);
            $summary['canDelete'] = $this->canDelete($user, $entry);
            $summary['canToggleBillable'] = $this->canToggleBillable($user, $entry);
            $summary['isOwnEntry'] = isset($entry['user_id']) && $entry['user_id'] === $user['id'];
        }

        return $summary;
    }
}
