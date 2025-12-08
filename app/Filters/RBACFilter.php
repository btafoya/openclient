<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * RBAC Filter (Role-Based Access Control)
 *
 * HTTP middleware that enforces route-level authorization based on user roles.
 * This is RBAC Layer 2 - runs after authentication (LoginFilter) but before controllers.
 *
 * Authorization Rules:
 * - End Clients: Cannot access financial routes (invoices, billing, payments, quotes)
 * - Agency Users: Can access most routes but not admin features
 * - Owner: Can access all routes (superuser)
 *
 * Security Features:
 * - Blocks unauthorized access at HTTP layer (defense in depth)
 * - Logs security violations to audit trail
 * - Provides user-friendly error messages
 * - Validates agency assignment for agency users
 */
class RBACFilter implements FilterInterface
{
    /**
     * Financial routes restricted for End Clients
     *
     * End Clients should not see invoices, quotes, or billing information
     * as they represent direct customers who don't need financial visibility.
     */
    private const FINANCIAL_ROUTES = [
        '/invoices',
        '/quotes',
        '/billing',
        '/payments',
        '/reports/financial',
    ];

    /**
     * Admin routes restricted to Owner only
     *
     * Only the platform Owner can access admin features like
     * user management, system settings, and agency configuration.
     */
    private const ADMIN_ROUTES = [
        '/admin',
        '/settings',
        '/users',
        '/agencies',
    ];

    /**
     * Before filter - runs before controller execution
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return RequestInterface|ResponseInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session()->get('user');
        if (!$user) {
            // Should not reach here (LoginFilter catches unauthenticated first)
            // But defensive programming: redirect to login if session lost
            return redirect()->to('/auth/login');
        }

        $uri = $request->getUri()->getPath();
        $role = $user['role'] ?? null;

        if (!$role) {
            log_message('error', "User {$user['email']} has no role assigned");
            return redirect()->to('/dashboard')
                ->with('error', 'Your account has no role assigned. Please contact the administrator.');
        }

        // End Clients cannot access financial features
        if ($role === 'end_client') {
            foreach (self::FINANCIAL_ROUTES as $route) {
                if (str_starts_with($uri, $route)) {
                    // Log security violation
                    $this->logSecurityViolation($user, $uri, 'End Client attempted to access financial route');

                    return redirect()->to('/dashboard')
                        ->with('error', 'You do not have permission to access financial features.');
                }
            }
        }

        // Only Owner can access admin routes
        if ($role !== 'owner') {
            foreach (self::ADMIN_ROUTES as $route) {
                if (str_starts_with($uri, $route)) {
                    // Log security violation
                    $this->logSecurityViolation($user, $uri, "{$role} user attempted to access admin route");

                    return redirect()->to('/dashboard')
                        ->with('error', 'You do not have permission to access admin features.');
                }
            }
        }

        // Agency users must have agency_id assigned
        if ($role === 'agency' && empty($user['agency_id'])) {
            log_message('warning', "Agency user {$user['email']} has no agency_id assigned");
            return redirect()->to('/dashboard')
                ->with('error', 'Your account is not assigned to an agency. Please contact the administrator.');
        }

        // Direct Client users must have agency_id assigned (they belong to an agency)
        if ($role === 'direct_client' && empty($user['agency_id'])) {
            log_message('warning', "Direct Client {$user['email']} has no agency_id assigned");
            return redirect()->to('/dashboard')
                ->with('error', 'Your account is not assigned to an agency. Please contact your account manager.');
        }

        // Authorization passed - allow request to proceed
        return $request;
    }

    /**
     * After filter - runs after controller execution
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed for RBAC
        return $response;
    }

    /**
     * Log security violation to audit trail
     *
     * @param array $user User who attempted unauthorized access
     * @param string $resource URI that was attempted
     * @param string $reason Human-readable reason for denial
     * @return void
     */
    private function logSecurityViolation(array $user, string $resource, string $reason): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'ACCESS_DENIED',
            'user_id' => $user['id'] ?? 'unknown',
            'user_email' => $user['email'] ?? 'unknown',
            'user_role' => $user['role'] ?? 'unknown',
            'attempted_resource' => $resource,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];

        // Write to daily security log file
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Append to security log
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND);

        // Also log to CodeIgniter standard log for visibility
        log_message('warning', "Security: {$reason} - User {$user['email']} ({$user['role']}) attempted {$resource}");
    }
}
