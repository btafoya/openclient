<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * User Model
 *
 * Handles user authentication and management with:
 * - UUID primary keys
 * - Automatic password hashing with bcrypt
 * - Soft delete support
 * - RBAC role management
 * - Brute force protection fields
 */
class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'id',
        'agency_id',
        'email',
        'password_hash',
        'role',
        'first_name',
        'last_name',
        'phone',
        'avatar',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password' => 'permit_empty|min_length[12]',
        'role' => 'required|in_list[owner,agency,end_client,direct_client]',
        'first_name' => 'required|max_length[100]',
        'last_name' => 'required|max_length[100]',
    ];

    protected $validationMessages = [
        'email' => [
            'required' => 'Email address is required',
            'valid_email' => 'Please provide a valid email address',
            'is_unique' => 'This email address is already registered',
        ],
        'password' => [
            'min_length' => 'Password must be at least 12 characters',
        ],
    ];

    protected $beforeInsert = ['hashPassword', 'generateUUID'];
    protected $beforeUpdate = ['hashPassword'];

    /**
     * Hash password before insert/update
     */
    protected function hashPassword(array $data): array
    {
        if (isset($data['data']['password'])) {
            $data['data']['password_hash'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
            unset($data['data']['password']);
        }

        return $data;
    }

    /**
     * Generate UUID for new users
     */
    protected function generateUUID(array $data): array
    {
        if (!isset($data['data']['id'])) {
            // Use PostgreSQL uuid_generate_v4() function
            $db = \Config\Database::connect();
            $result = $db->query('SELECT uuid_generate_v4() as uuid')->getRow();
            $data['data']['id'] = $result->uuid;
        }

        return $data;
    }

    /**
     * Find user by email address
     */
    public function findByEmail(string $email): ?array
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Check if user account is active
     */
    public function isActive(string $userId): bool
    {
        $user = $this->find($userId);
        return $user && $user['is_active'];
    }

    /**
     * Check if user account is locked due to failed login attempts
     */
    public function isLocked(string $userId): bool
    {
        $user = $this->find($userId);

        if (!$user || !$user['locked_until']) {
            return false;
        }

        $lockedUntil = strtotime($user['locked_until']);
        $now = time();

        // If lock has expired, reset it
        if ($now > $lockedUntil) {
            $this->update($userId, [
                'locked_until' => null,
                'failed_login_attempts' => 0,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Increment failed login attempts and lock account if threshold reached
     */
    public function incrementFailedAttempts(string $userId): void
    {
        $user = $this->find($userId);

        if (!$user) {
            return;
        }

        $attempts = $user['failed_login_attempts'] + 1;
        $updateData = ['failed_login_attempts' => $attempts];

        // Lock account after 5 failed attempts for 15 minutes
        if ($attempts >= 5) {
            $updateData['locked_until'] = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }

        $this->update($userId, $updateData);
    }

    /**
     * Reset failed login attempts on successful login
     */
    public function resetFailedAttempts(string $userId): void
    {
        $this->update($userId, [
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * Update last login timestamp and IP address
     */
    public function updateLastLogin(string $userId, string $ipAddress): void
    {
        $this->update($userId, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ipAddress,
        ]);
    }

    /**
     * Verify password against stored hash
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
