<?php

namespace App\Domain\Projects\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;
use Config\Database;

/**
 * Task Authorization Guard
 *
 * Implements fine-grained authorization logic for task operations.
 * Tasks are tenant-scoped resources tied to projects with assignment-based access control.
 *
 * Authorization Rules:
 * - Owner: Full access to all tasks across all agencies
 * - Agency: Can view/edit tasks for their agency (aligned with RLS)
 * - Project Members: Can view tasks in projects they're assigned to
 * - Assigned User: Can view and update tasks assigned to them
 * - Direct Client: Can view tasks in projects they're a member of
 * - End Client: Can view tasks in projects they're a member of
 *
 * Task-User Relationships:
 * - Tasks have an assigned_to field linking to a user
 * - Task access is also governed by project membership
 * - This allows granular access control within project context
 */
class TaskGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a specific task
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $task Task entity (array or object with 'id', 'agency_id', 'project_id', 'assigned_to')
     * @return bool True if user can view this task
     */
    public function canView(array $user, $task): bool
    {
        // Convert object to array if needed
        $task = is_object($task) ? (array) $task : $task;

        // Owner: can view all tasks
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can view if task belongs to their agency
        if ($user['role'] === 'agency') {
            return isset($task['agency_id']) && $task['agency_id'] === $user['agency_id'];
        }

        // Check if user is assigned to this task
        if (isset($task['assigned_to']) && $task['assigned_to'] === $user['id']) {
            return true;
        }

        // Check if user is a member of the task's project
        if (isset($task['project_id']) && $this->isProjectMember($user['id'], $task['project_id'])) {
            return true;
        }

        // If not assigned and not a project member, deny access
        return false;
    }

    /**
     * Check if user can create tasks
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param string|null $projectId Optional project ID for project-specific creation check
     * @return bool True if user can create tasks
     */
    public function canCreate(array $user, ?string $projectId = null): bool
    {
        // Only Owner and Agency users can create tasks
        // Clients cannot create tasks
        if (!in_array($user['role'], ['owner', 'agency'])) {
            return false;
        }

        // If project ID provided, check if user can access that project
        if ($projectId) {
            return $this->canAccessProject($user, $projectId);
        }

        return true;
    }

    /**
     * Check if user can edit a specific task
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $task Task entity to check
     * @return bool True if user can edit this task
     */
    public function canEdit(array $user, $task): bool
    {
        // Convert object to array if needed
        $task = is_object($task) ? (array) $task : $task;

        // Owner: can edit all tasks
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can edit if task belongs to their agency
        if ($user['role'] === 'agency' && isset($task['agency_id'])) {
            return $task['agency_id'] === $user['agency_id'];
        }

        // Assigned user can edit their own task (status, hours, description)
        if (isset($task['assigned_to']) && $task['assigned_to'] === $user['id']) {
            return true;
        }

        // Project managers can edit all tasks in their project
        if (isset($task['project_id']) && $this->isProjectManager($user['id'], $task['project_id'])) {
            return true;
        }

        // Regular clients cannot edit tasks
        return false;
    }

    /**
     * Check if user can delete a specific task
     *
     * @param array $user User array with 'id', 'role', 'agency_id'
     * @param mixed $task Task entity to check
     * @return bool True if user can delete this task
     */
    public function canDelete(array $user, $task): bool
    {
        // Convert object to array if needed
        $task = is_object($task) ? (array) $task : $task;

        // Owner: can delete all tasks
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency: can delete if task belongs to their agency
        if ($user['role'] === 'agency' && isset($task['agency_id'])) {
            return $task['agency_id'] === $user['agency_id'];
        }

        // Project managers can delete tasks in their project
        if (isset($task['project_id']) && $this->isProjectManager($user['id'], $task['project_id'])) {
            return true;
        }

        // Assigned users and regular members cannot delete tasks
        return false;
    }

    /**
     * Check if user can assign tasks to others
     *
     * @param array $user User array
     * @param mixed $task Task entity
     * @return bool True if user can assign this task
     */
    public function canAssign(array $user, $task): bool
    {
        $task = is_object($task) ? (array) $task : $task;

        // Owner can assign any task
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency can assign tasks in their agency
        if ($user['role'] === 'agency' && isset($task['agency_id'])) {
            return $task['agency_id'] === $user['agency_id'];
        }

        // Project managers can assign tasks in their project
        if (isset($task['project_id']) && $this->isProjectManager($user['id'], $task['project_id'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can update task status
     *
     * @param array $user User array
     * @param mixed $task Task entity
     * @return bool True if user can update task status
     */
    public function canUpdateStatus(array $user, $task): bool
    {
        $task = is_object($task) ? (array) $task : $task;

        // Owner and Agency can update any task status
        if (in_array($user['role'], ['owner', 'agency'])) {
            return $this->canEdit($user, $task);
        }

        // Assigned user can update their own task status
        if (isset($task['assigned_to']) && $task['assigned_to'] === $user['id']) {
            return true;
        }

        // Project managers can update task status
        if (isset($task['project_id']) && $this->isProjectManager($user['id'], $task['project_id'])) {
            return true;
        }

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
     * Check if user is a project manager
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
     * Get tasks user is assigned to
     *
     * @param string $userId User ID
     * @return array Array of task IDs
     */
    public function getAssignedTaskIds(string $userId): array
    {
        $db = Database::connect();

        $query = $db->table('tasks')
            ->select('id')
            ->where('assigned_to', $userId)
            ->where('is_active', true)
            ->where('deleted_at', null)
            ->get();

        return array_column($query->getResultArray(), 'id');
    }

    /**
     * Get user's permission summary
     *
     * @param array $user User array
     * @param mixed|null $task Optional task entity
     * @param string|null $projectId Optional project ID for creation check
     * @return array Permission summary
     */
    public function getPermissionSummary(array $user, $task = null, ?string $projectId = null): array
    {
        $summary = [
            'canCreate' => $this->canCreate($user, $projectId),
        ];

        if ($task) {
            $summary['canView'] = $this->canView($user, $task);
            $summary['canEdit'] = $this->canEdit($user, $task);
            $summary['canDelete'] = $this->canDelete($user, $task);
            $summary['canAssign'] = $this->canAssign($user, $task);
            $summary['canUpdateStatus'] = $this->canUpdateStatus($user, $task);
            $summary['isAssigned'] = isset($task['assigned_to']) && $task['assigned_to'] === $user['id'];
        }

        return $summary;
    }
}
