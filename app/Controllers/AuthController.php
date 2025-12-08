<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Authentication Controller
 *
 * Handles user authentication with:
 * - Login/logout with session management
 * - Brute force protection (5 attempts → 15min lockout)
 * - Password reset flow with tokens
 * - PostgreSQL RLS session variables
 * - Last login tracking
 */
class AuthController extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Show login form
     */
    public function showLogin()
    {
        // Redirect if already logged in
        if (session()->get('logged_in')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }

    /**
     * Process login
     */
    public function login(): RedirectResponse
    {
        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // Validate inputs
        if (!$email || !$password) {
            return redirect()->back()->with('error', 'Email and password are required.');
        }

        // Find user by email
        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            // Don't reveal if email exists (security best practice)
            return redirect()->back()->with('error', 'Invalid email or password.');
        }

        // Check if account is active
        if (!$user['is_active']) {
            return redirect()->back()->with('error', 'Your account has been deactivated. Please contact support.');
        }

        // Check if account is locked
        if ($this->userModel->isLocked($user['id'])) {
            return redirect()->back()->with('error', 'Account locked due to too many failed attempts. Try again in 15 minutes.');
        }

        // Verify password
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            $this->userModel->incrementFailedAttempts($user['id']);
            return redirect()->back()->with('error', 'Invalid email or password.');
        }

        // Reset failed attempts on successful login
        $this->userModel->resetFailedAttempts($user['id']);

        // Update last login info
        $this->userModel->updateLastLogin($user['id'], $this->request->getIPAddress());

        // Set session variables
        session()->set([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'agency_id' => $user['agency_id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
            ],
            'logged_in' => true
        ]);

        // Set PostgreSQL session variables for RLS
        $db = \Config\Database::connect();
        $db->query("SET app.current_user_id = ?", [$user['id']]);
        $db->query("SET app.current_user_role = ?", [$user['role']]);
        if ($user['agency_id']) {
            $db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
        }

        return redirect()->to('/dashboard')->with('success', 'Welcome back, ' . $user['first_name'] . '!');
    }

    /**
     * Process logout
     */
    public function logout(): RedirectResponse
    {
        session()->destroy();
        return redirect()->to('/auth/login')->with('success', 'You have been logged out successfully.');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword()
    {
        return view('auth/forgot_password');
    }

    /**
     * Process forgot password request
     */
    public function forgotPassword(): RedirectResponse
    {
        $email = $this->request->getPost('email');

        if (!$email) {
            return redirect()->back()->with('error', 'Email address is required.');
        }

        $user = $this->userModel->findByEmail($email);

        // Don't reveal if email exists (security best practice)
        if (!$user) {
            return redirect()->back()->with('success', 'If the email exists, a reset link has been sent.');
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        cache()->save("password_reset_{$token}", $user['id'], 3600); // 1 hour expiry

        // Send email with reset link
        $resetUrl = base_url("auth/reset-password/{$token}");

        // TODO: Implement email sending (use CodeIgniter Email library or service like SendGrid)
        // For now, log the reset URL in development
        if (ENVIRONMENT === 'development') {
            log_message('info', "Password reset link for {$email}: {$resetUrl}");
        }

        return redirect()->back()->with('success', 'If the email exists, a reset link has been sent.');
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(string $token)
    {
        $userId = cache()->get("password_reset_{$token}");

        if (!$userId) {
            return redirect()->to('/auth/login')->with('error', 'Invalid or expired reset link.');
        }

        return view('auth/reset_password', ['token' => $token]);
    }

    /**
     * Process password reset
     */
    public function resetPassword(): RedirectResponse
    {
        $token = $this->request->getPost('token');
        $password = $this->request->getPost('password');
        $passwordConfirm = $this->request->getPost('password_confirm');

        // Validate inputs
        if (!$password || !$passwordConfirm) {
            return redirect()->back()->with('error', 'All fields are required.');
        }

        if ($password !== $passwordConfirm) {
            return redirect()->back()->with('error', 'Passwords do not match.');
        }

        // Validate password strength
        if (!$this->isPasswordStrong($password)) {
            return redirect()->back()->with('error', 'Password must be at least 12 characters with uppercase, lowercase, and number.');
        }

        $userId = cache()->get("password_reset_{$token}");

        if (!$userId) {
            return redirect()->to('/auth/login')->with('error', 'Invalid or expired reset link.');
        }

        // Update password (hashPassword hook will bcrypt it)
        $this->userModel->update($userId, ['password' => $password]);

        // Invalidate token
        cache()->delete("password_reset_{$token}");

        return redirect()->to('/auth/login')->with('success', 'Password reset successful. Please log in.');
    }

    /**
     * Validate password strength
     * Requirements: 12+ characters, uppercase, lowercase, number
     */
    private function isPasswordStrong(string $password): bool
    {
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password) // uppercase
            && preg_match('/[a-z]/', $password) // lowercase
            && preg_match('/[0-9]/', $password); // number
    }
}
