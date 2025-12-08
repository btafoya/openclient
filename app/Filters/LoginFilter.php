<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Login Filter
 *
 * Authentication middleware that:
 * - Redirects unauthenticated users to login page
 * - Refreshes PostgreSQL RLS session variables on each request
 * - Maintains user session state
 */
class LoginFilter implements FilterInterface
{
    /**
     * Check if user is authenticated before processing request
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if user is logged in
        if (!session()->get('logged_in')) {
            return redirect()->to('/auth/login')->with('error', 'Please log in to access this page.');
        }

        // Refresh PostgreSQL session variables for RLS on each request
        $user = session()->get('user');

        if ($user) {
            $db = \Config\Database::connect();

            // Set RLS session variables
            $db->query("SET app.current_user_id = ?", [$user['id']]);
            $db->query("SET app.current_user_role = ?", [$user['role']]);

            if (isset($user['agency_id']) && $user['agency_id']) {
                $db->query("SET app.current_agency_id = ?", [$user['agency_id']]);
            }
        }

        // Continue with request
        return null;
    }

    /**
     * Process after request
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No post-processing needed
        return $response;
    }
}
