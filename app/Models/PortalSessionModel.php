<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Portal Session Model
 *
 * Manages active client portal sessions for security tracking.
 */
class PortalSessionModel extends Model
{
    protected $table = 'portal_sessions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'access_token_id',
        'session_token',
        'ip_address',
        'user_agent',
        'expires_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = null; // Sessions don't get updated

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'generateSessionToken'];

    /**
     * Default session duration in hours
     */
    private int $sessionDuration = 24;

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
     * Generate secure session token
     */
    protected function generateSessionToken(array $data): array
    {
        if (!isset($data['data']['session_token'])) {
            $data['data']['session_token'] = bin2hex(random_bytes(32));
        }
        return $data;
    }

    /**
     * Create a new session for an access token
     */
    public function createSession(
        string $accessTokenId,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ?array {
        $data = [
            'access_token_id' => $accessTokenId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$this->sessionDuration} hours")),
        ];

        $id = $this->insert($data, true);
        return $id ? $this->find($id) : null;
    }

    /**
     * Validate a session token
     */
    public function validateSession(string $sessionToken): ?array
    {
        $session = $this->where('session_token', $sessionToken)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();

        if (!$session) {
            return null;
        }

        // Get the associated access token
        $accessModel = new PortalAccessTokenModel();
        $access = $accessModel->find($session['access_token_id']);

        if (!$access || !$access['is_active']) {
            return null;
        }

        return [
            'session' => $session,
            'access' => $access,
        ];
    }

    /**
     * Extend session expiration
     */
    public function extendSession(string $sessionToken): bool
    {
        return $this->where('session_token', $sessionToken)
            ->set('expires_at', date('Y-m-d H:i:s', strtotime("+{$this->sessionDuration} hours")))
            ->update();
    }

    /**
     * Terminate a specific session
     */
    public function terminateSession(string $sessionToken): bool
    {
        return $this->where('session_token', $sessionToken)->delete();
    }

    /**
     * Terminate all sessions for an access token
     */
    public function terminateAllForAccessToken(string $accessTokenId): int
    {
        return $this->where('access_token_id', $accessTokenId)->delete();
    }

    /**
     * Get active sessions for an access token
     */
    public function getActiveByAccessToken(string $accessTokenId): array
    {
        return $this->where('access_token_id', $accessTokenId)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->findAll();
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpired(): int
    {
        return $this->where('expires_at <', date('Y-m-d H:i:s'))->delete();
    }

    /**
     * Count active sessions for security monitoring
     */
    public function countActiveSessions(): int
    {
        return $this->where('expires_at >', date('Y-m-d H:i:s'))->countAllResults();
    }
}
