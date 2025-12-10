<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Portal Access Token Model
 *
 * Manages client portal access tokens for secure client self-service.
 * Separate authentication system from main user auth.
 */
class PortalAccessTokenModel extends Model
{
    protected $table = 'portal_access_tokens';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'client_id',
        'contact_id',
        'email',
        'token',
        'token_type',
        'permissions',
        'expires_at',
        'last_used_at',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'client_id' => 'required|max_length[36]',
        'email' => 'required|valid_email|max_length[255]',
        'token_type' => 'permit_empty|in_list[access,magic_link,api]',
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'generateToken'];

    /**
     * Default permissions for new portal access
     */
    private array $defaultPermissions = [
        'view_invoices' => true,
        'view_proposals' => true,
        'view_projects' => true,
        'make_payments' => true,
        'download_files' => true,
        'submit_feedback' => true,
    ];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Generate secure access token
     */
    protected function generateToken(array $data): array
    {
        if (!isset($data['data']['token'])) {
            $data['data']['token'] = bin2hex(random_bytes(32));
        }
        return $data;
    }

    /**
     * Create portal access for a client
     */
    public function createAccess(
        string $clientId,
        string $email,
        ?string $contactId = null,
        ?array $permissions = null,
        ?string $expiresAt = null
    ): ?array {
        $data = [
            'client_id' => $clientId,
            'contact_id' => $contactId,
            'email' => $email,
            'token_type' => 'access',
            'permissions' => json_encode($permissions ?? $this->defaultPermissions),
            'expires_at' => $expiresAt,
            'is_active' => true,
        ];

        $id = $this->insert($data, true);
        return $id ? $this->find($id) : null;
    }

    /**
     * Create magic link token for passwordless login
     */
    public function createMagicLink(string $clientId, string $email): ?array
    {
        // Expire any existing magic links for this email
        $this->where('email', $email)
            ->where('token_type', 'magic_link')
            ->where('is_active', true)
            ->set('is_active', false)
            ->update();

        $data = [
            'client_id' => $clientId,
            'email' => $email,
            'token_type' => 'magic_link',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'is_active' => true,
        ];

        $id = $this->insert($data, true);
        return $id ? $this->find($id) : null;
    }

    /**
     * Validate a token and return access details
     */
    public function validateToken(string $token): ?array
    {
        $access = $this->where('token', $token)
            ->where('is_active', true)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->first();

        if (!$access) {
            return null;
        }

        // Update last used timestamp
        $this->update($access['id'], ['last_used_at' => date('Y-m-d H:i:s')]);

        // Parse permissions
        if (!empty($access['permissions']) && is_string($access['permissions'])) {
            $access['permissions'] = json_decode($access['permissions'], true);
        }

        return $access;
    }

    /**
     * Consume a magic link token (one-time use)
     */
    public function consumeMagicLink(string $token): ?array
    {
        $access = $this->where('token', $token)
            ->where('token_type', 'magic_link')
            ->where('is_active', true)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        if (!$access) {
            return null;
        }

        // Deactivate the magic link
        $this->update($access['id'], ['is_active' => false]);

        // Find or create a regular access token for this client/email
        $existingAccess = $this->where('client_id', $access['client_id'])
            ->where('email', $access['email'])
            ->where('token_type', 'access')
            ->where('is_active', true)
            ->first();

        if ($existingAccess) {
            $this->update($existingAccess['id'], ['last_used_at' => date('Y-m-d H:i:s')]);
            return $existingAccess;
        }

        // Create new access token
        return $this->createAccess($access['client_id'], $access['email'], $access['contact_id']);
    }

    /**
     * Get active access tokens for a client
     */
    public function getByClientId(string $clientId): array
    {
        return $this->where('client_id', $clientId)
            ->where('token_type', 'access')
            ->where('is_active', true)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Revoke access for a specific token
     */
    public function revokeAccess(string $id): bool
    {
        return $this->update($id, ['is_active' => false]);
    }

    /**
     * Revoke all access for a client
     */
    public function revokeAllClientAccess(string $clientId): int
    {
        return $this->where('client_id', $clientId)
            ->where('is_active', true)
            ->set('is_active', false)
            ->update();
    }

    /**
     * Update permissions for an access token
     */
    public function updatePermissions(string $id, array $permissions): bool
    {
        return $this->update($id, ['permissions' => json_encode($permissions)]);
    }

    /**
     * Check if a specific permission is granted
     */
    public function hasPermission(string $tokenId, string $permission): bool
    {
        $access = $this->find($tokenId);
        if (!$access) {
            return false;
        }

        $permissions = is_string($access['permissions'])
            ? json_decode($access['permissions'], true)
            : $access['permissions'];

        return isset($permissions[$permission]) && $permissions[$permission] === true;
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpired(): int
    {
        return $this->where('expires_at <', date('Y-m-d H:i:s'))
            ->where('is_active', true)
            ->set('is_active', false)
            ->update();
    }
}
