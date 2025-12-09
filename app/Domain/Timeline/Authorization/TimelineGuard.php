<?php

namespace App\Domain\Timeline\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;

class TimelineGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a timeline entry
     * Users can view timeline entries for entities they have access to
     *
     * @param array $user The authenticated user
     * @param mixed $timelineEntry Timeline entry (array or ID)
     * @return bool
     */
    public function canView(array $user, $timelineEntry): bool
    {
        // Owner can view all timeline entries
        if ($user['role'] === 'owner') {
            return true;
        }

        // If timeline entry is null, return false
        if (empty($timelineEntry)) {
            return false;
        }

        // Load full timeline entry if only ID provided
        if (is_string($timelineEntry)) {
            $model = new \App\Models\TimelineModel();
            $timelineEntry = $model->find($timelineEntry);
            if (!$timelineEntry) {
                return false;
            }
        }

        // Agency staff can view timeline entries in their agency
        if ($user['role'] === 'agency') {
            return isset($timelineEntry['agency_id']) && $timelineEntry['agency_id'] === $user['agency_id'];
        }

        // Client users can view timeline entries for entities they have access to
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            return $this->userHasAccessToEntity(
                $user['id'],
                $timelineEntry['entity_type'],
                $timelineEntry['entity_id']
            );
        }

        return false;
    }

    /**
     * Check if user can create timeline entries
     * Only owner and agency staff can manually create timeline entries
     * (Most timeline entries are created automatically by model hooks)
     *
     * @param array $user The authenticated user
     * @return bool
     */
    public function canCreate(array $user): bool
    {
        // Only owner and agency staff can manually create timeline entries
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit a timeline entry
     * Timeline entries are immutable - they cannot be edited
     *
     * @param array $user The authenticated user
     * @param mixed $timelineEntry Timeline entry (array or ID)
     * @return bool
     */
    public function canEdit(array $user, $timelineEntry): bool
    {
        // Timeline entries are immutable - cannot be edited
        return false;
    }

    /**
     * Check if user can delete a timeline entry
     * Only owner and agency staff can soft-delete timeline entries (for GDPR compliance)
     *
     * @param array $user The authenticated user
     * @param mixed $timelineEntry Timeline entry (array or ID)
     * @return bool
     */
    public function canDelete(array $user, $timelineEntry): bool
    {
        // Only owner and agency can delete timeline entries (for GDPR compliance)
        if (!in_array($user['role'], ['owner', 'agency'])) {
            return false;
        }

        // If timeline entry is null, return false
        if (empty($timelineEntry)) {
            return false;
        }

        // Load full timeline entry if only ID provided
        if (is_string($timelineEntry)) {
            $model = new \App\Models\TimelineModel();
            $timelineEntry = $model->find($timelineEntry);
            if (!$timelineEntry) {
                return false;
            }
        }

        // Owner can delete any timeline entry
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency staff can delete timeline entries in their agency
        if ($user['role'] === 'agency') {
            return isset($timelineEntry['agency_id']) && $timelineEntry['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Filter timeline entries to only those the user can view
     *
     * @param array $user The authenticated user
     * @param array $timelineEntries Array of timeline entries
     * @return array Filtered timeline entries
     */
    public function filterViewableTimeline(array $user, array $timelineEntries): array
    {
        // Owner can view all
        if ($user['role'] === 'owner') {
            return $timelineEntries;
        }

        // Filter based on user permissions
        return array_filter($timelineEntries, fn($entry) => $this->canView($user, $entry));
    }

    /**
     * Get permissions for a specific timeline entry
     *
     * @param array $user The authenticated user
     * @param mixed $timelineEntry Timeline entry (array or ID)
     * @return array Permissions array
     */
    public function getPermissions(array $user, $timelineEntry): array
    {
        return [
            'canView' => $this->canView($user, $timelineEntry),
            'canCreate' => $this->canCreate($user),
            'canEdit' => $this->canEdit($user, $timelineEntry),
            'canDelete' => $this->canDelete($user, $timelineEntry),
        ];
    }

    /**
     * Check if user has access to a specific entity
     * Permission inheritance: contacts/projects inherit from parent client
     *
     * @param string $userId User ID
     * @param string $entityType Entity type (client, contact, project, note)
     * @param string $entityId Entity ID
     * @return bool
     */
    private function userHasAccessToEntity(string $userId, string $entityType, string $entityId): bool
    {
        $db = \Config\Database::connect();

        switch ($entityType) {
            case 'client':
                // Check if user has access to this client
                $result = $db->table('client_users')
                    ->where('user_id', $userId)
                    ->where('client_id', $entityId)
                    ->get()->getRow();
                return $result !== null;

            case 'contact':
                // Contacts inherit access from parent client
                $contact = $db->table('contacts')
                    ->select('client_id')
                    ->where('id', $entityId)
                    ->get()->getRow();

                if (!$contact) {
                    return false;
                }

                return $this->userHasAccessToEntity($userId, 'client', $contact->client_id);

            case 'project':
                // Projects inherit access from parent client
                $project = $db->table('projects')
                    ->select('client_id')
                    ->where('id', $entityId)
                    ->get()->getRow();

                if (!$project) {
                    return false;
                }

                return $this->userHasAccessToEntity($userId, 'client', $project->client_id);

            case 'note':
                // Notes inherit access from parent entity
                $note = $db->table('notes')
                    ->select('client_id, contact_id, project_id')
                    ->where('id', $entityId)
                    ->get()->getRow();

                if (!$note) {
                    return false;
                }

                // Check access to note's parent entity
                if ($note->client_id) {
                    return $this->userHasAccessToEntity($userId, 'client', $note->client_id);
                } elseif ($note->contact_id) {
                    return $this->userHasAccessToEntity($userId, 'contact', $note->contact_id);
                } elseif ($note->project_id) {
                    return $this->userHasAccessToEntity($userId, 'project', $note->project_id);
                }

                return false;

            default:
                return false;
        }
    }

    /**
     * Check if entity belongs to a specific agency
     *
     * @param string $entityType Entity type
     * @param string $entityId Entity ID
     * @param string $agencyId Agency ID
     * @return bool
     */
    private function entityBelongsToAgency(string $entityType, string $entityId, string $agencyId): bool
    {
        $db = \Config\Database::connect();

        $tableName = match ($entityType) {
            'client' => 'clients',
            'contact' => 'contacts',
            'project' => 'projects',
            'note' => 'notes',
            default => null,
        };

        if (!$tableName) {
            return false;
        }

        $result = $db->table($tableName)
            ->where('id', $entityId)
            ->where('agency_id', $agencyId)
            ->get()->getRow();

        return $result !== null;
    }
}
