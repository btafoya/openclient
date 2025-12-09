<?php

namespace App\Domain\Notes\Authorization;

use App\Domain\Authorization\AuthorizationGuardInterface;

class NoteGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view a note
     *
     * Rules:
     * - Owner: Can view all notes
     * - Agency: Can view notes for entities in their agency
     * - Client users: Can view notes for entities they're assigned to
     */
    public function canView(array $user, $note): bool
    {
        // Owner can view all notes
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency users: Check agency match
        if ($user['role'] === 'agency') {
            return isset($note['agency_id']) && $note['agency_id'] === $user['agency_id'];
        }

        // Client users: Check entity access
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            return $this->userHasAccessToEntity($user['id'], $note);
        }

        return false;
    }

    /**
     * Check if user can create notes for the specified entity
     *
     * Rules:
     * - Owner: Can create notes for any entity
     * - Agency: Can create notes for entities in their agency
     * - Client users: Can create notes for entities they're assigned to
     */
    public function canCreate(array $user, string $entityType = null, string $entityId = null): bool
    {
        // Basic permission check
        if (!in_array($user['role'], ['owner', 'agency', 'direct_client', 'end_client'])) {
            return false;
        }

        // If no entity specified, check role-level permission
        if ($entityType === null || $entityId === null) {
            return in_array($user['role'], ['owner', 'agency', 'direct_client', 'end_client']);
        }

        // Owner can create notes for any entity
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency users: Check entity belongs to their agency
        if ($user['role'] === 'agency') {
            return $this->entityBelongsToAgency($entityType, $entityId, $user['agency_id']);
        }

        // Client users: Check they have access to entity
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            return $this->userHasAccessToEntityById($user['id'], $entityType, $entityId);
        }

        return false;
    }

    /**
     * Check if user can edit a note
     *
     * Rules:
     * - Owner: Can edit all notes
     * - Agency: Can edit notes they created for entities in their agency
     * - Client users: Can only edit their own notes for entities they're assigned to
     */
    public function canEdit(array $user, $note): bool
    {
        // Owner can edit all notes
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency users: Must be their note and in their agency
        if ($user['role'] === 'agency') {
            return isset($note['agency_id']) &&
                   $note['agency_id'] === $user['agency_id'] &&
                   isset($note['user_id']) &&
                   $note['user_id'] === $user['id'];
        }

        // Client users: Must be their note and have access to entity
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            return isset($note['user_id']) &&
                   $note['user_id'] === $user['id'] &&
                   $this->userHasAccessToEntity($user['id'], $note);
        }

        return false;
    }

    /**
     * Check if user can delete a note
     *
     * Rules:
     * - Owner: Can delete all notes
     * - Agency: Can delete notes they created for entities in their agency
     * - Client users: Can only delete their own notes for entities they're assigned to
     */
    public function canDelete(array $user, $note): bool
    {
        // Same rules as canEdit
        return $this->canEdit($user, $note);
    }

    /**
     * Check if user has access to the entity a note is attached to
     *
     * @param string $userId User ID
     * @param array $note Note with entity foreign keys
     * @return bool
     */
    private function userHasAccessToEntity(string $userId, array $note): bool
    {
        // Determine entity type and ID
        if (!empty($note['client_id'])) {
            return $this->userHasAccessToEntityById($userId, 'client', $note['client_id']);
        }

        if (!empty($note['contact_id'])) {
            return $this->userHasAccessToEntityById($userId, 'contact', $note['contact_id']);
        }

        if (!empty($note['project_id'])) {
            return $this->userHasAccessToEntityById($userId, 'project', $note['project_id']);
        }

        return false;
    }

    /**
     * Check if user has access to a specific entity by type and ID
     *
     * @param string $userId User ID
     * @param string $entityType 'client', 'contact', or 'project'
     * @param string $entityId Entity UUID
     * @return bool
     */
    private function userHasAccessToEntityById(string $userId, string $entityType, string $entityId): bool
    {
        $db = \Config\Database::connect();

        switch ($entityType) {
            case 'client':
                // Check if user is assigned to client via client_users
                $result = $db->table('client_users')
                    ->where('user_id', $userId)
                    ->where('client_id', $entityId)
                    ->get()
                    ->getRow();
                return $result !== null;

            case 'contact':
                // Check if user has access to contact's parent client
                $contact = $db->table('contacts')
                    ->select('client_id')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();

                if (!$contact) {
                    return false;
                }

                return $this->userHasAccessToEntityById($userId, 'client', $contact->client_id);

            case 'project':
                // Check if user has access to project's client
                $project = $db->table('projects')
                    ->select('client_id')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();

                if (!$project) {
                    return false;
                }

                return $this->userHasAccessToEntityById($userId, 'client', $project->client_id);

            default:
                return false;
        }
    }

    /**
     * Check if entity belongs to the specified agency
     *
     * @param string $entityType 'client', 'contact', or 'project'
     * @param string $entityId Entity UUID
     * @param string $agencyId Agency UUID
     * @return bool
     */
    private function entityBelongsToAgency(string $entityType, string $entityId, string $agencyId): bool
    {
        $db = \Config\Database::connect();

        switch ($entityType) {
            case 'client':
                $client = $db->table('clients')
                    ->select('agency_id')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                return $client && $client->agency_id === $agencyId;

            case 'contact':
                $contact = $db->table('contacts')
                    ->select('agency_id')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                return $contact && $contact->agency_id === $agencyId;

            case 'project':
                $project = $db->table('projects')
                    ->select('agency_id')
                    ->where('id', $entityId)
                    ->get()
                    ->getRow();
                return $project && $project->agency_id === $agencyId;

            default:
                return false;
        }
    }

    /**
     * Filter list of note IDs to only those the user can view
     *
     * @param array $user User session data
     * @param array $noteIds Array of note UUIDs
     * @return array Filtered array of note UUIDs
     */
    public function filterViewableNotes(array $user, array $noteIds): array
    {
        if (empty($noteIds)) {
            return [];
        }

        // Owner can view all
        if ($user['role'] === 'owner') {
            return $noteIds;
        }

        $db = \Config\Database::connect();

        // Agency users: Filter by agency_id
        if ($user['role'] === 'agency') {
            $results = $db->table('notes')
                ->select('id')
                ->whereIn('id', $noteIds)
                ->where('agency_id', $user['agency_id'])
                ->get()
                ->getResultArray();

            return array_column($results, 'id');
        }

        // Client users: Filter by entity access
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            $viewable = [];

            foreach ($noteIds as $noteId) {
                $note = $db->table('notes')
                    ->where('id', $noteId)
                    ->get()
                    ->getRowArray();

                if ($note && $this->userHasAccessToEntity($user['id'], $note)) {
                    $viewable[] = $noteId;
                }
            }

            return $viewable;
        }

        return [];
    }

    /**
     * Filter list of note IDs to only those the user can edit
     *
     * @param array $user User session data
     * @param array $noteIds Array of note UUIDs
     * @return array Filtered array of note UUIDs
     */
    public function filterEditableNotes(array $user, array $noteIds): array
    {
        if (empty($noteIds)) {
            return [];
        }

        // Owner can edit all
        if ($user['role'] === 'owner') {
            return $noteIds;
        }

        $db = \Config\Database::connect();
        $editable = [];

        foreach ($noteIds as $noteId) {
            $note = $db->table('notes')
                ->where('id', $noteId)
                ->get()
                ->getRowArray();

            if ($note && $this->canEdit($user, $note)) {
                $editable[] = $noteId;
            }
        }

        return $editable;
    }
}
