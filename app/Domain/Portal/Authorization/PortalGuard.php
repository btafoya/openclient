<?php

namespace App\Domain\Portal\Authorization;

use App\Models\PortalAccessTokenModel;
use App\Models\PortalSessionModel;

/**
 * Portal Authorization Guard
 *
 * Handles client portal authentication and authorization.
 * Separate from main user auth system.
 *
 * Portal Access Types:
 * - access: Long-lived access token for regular portal use
 * - magic_link: One-time use token for passwordless login
 * - api: API access token for integrations
 */
class PortalGuard
{
    private PortalAccessTokenModel $accessTokenModel;
    private PortalSessionModel $sessionModel;

    public function __construct()
    {
        $this->accessTokenModel = new PortalAccessTokenModel();
        $this->sessionModel = new PortalSessionModel();
    }

    /**
     * Authenticate with access token
     */
    public function authenticateWithToken(string $token): ?array
    {
        $access = $this->accessTokenModel->validateToken($token);
        if (!$access) {
            return null;
        }

        return [
            'type' => 'token',
            'access' => $access,
            'client_id' => $access['client_id'],
            'email' => $access['email'],
            'permissions' => $access['permissions'],
        ];
    }

    /**
     * Authenticate with magic link
     */
    public function authenticateWithMagicLink(string $token): ?array
    {
        $access = $this->accessTokenModel->consumeMagicLink($token);
        if (!$access) {
            return null;
        }

        return [
            'type' => 'magic_link',
            'access' => $access,
            'client_id' => $access['client_id'],
            'email' => $access['email'],
            'permissions' => $access['permissions'] ?? [],
        ];
    }

    /**
     * Authenticate with session token
     */
    public function authenticateWithSession(string $sessionToken): ?array
    {
        $result = $this->sessionModel->validateSession($sessionToken);
        if (!$result) {
            return null;
        }

        return [
            'type' => 'session',
            'session' => $result['session'],
            'access' => $result['access'],
            'client_id' => $result['access']['client_id'],
            'email' => $result['access']['email'],
            'permissions' => is_string($result['access']['permissions'])
                ? json_decode($result['access']['permissions'], true)
                : $result['access']['permissions'],
        ];
    }

    /**
     * Create a new session for an authenticated access
     */
    public function createSession(
        string $accessTokenId,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ?array {
        return $this->sessionModel->createSession($accessTokenId, $ipAddress, $userAgent);
    }

    /**
     * Check if portal user has a specific permission
     */
    public function hasPermission(array $portalAuth, string $permission): bool
    {
        $permissions = $portalAuth['permissions'] ?? [];
        return isset($permissions[$permission]) && $permissions[$permission] === true;
    }

    /**
     * Check if portal user can view invoices
     */
    public function canViewInvoices(array $portalAuth): bool
    {
        return $this->hasPermission($portalAuth, 'view_invoices');
    }

    /**
     * Check if portal user can view proposals
     */
    public function canViewProposals(array $portalAuth): bool
    {
        return $this->hasPermission($portalAuth, 'view_proposals');
    }

    /**
     * Check if portal user can view projects
     */
    public function canViewProjects(array $portalAuth): bool
    {
        return $this->hasPermission($portalAuth, 'view_projects');
    }

    /**
     * Check if portal user can make payments
     */
    public function canMakePayments(array $portalAuth): bool
    {
        return $this->hasPermission($portalAuth, 'make_payments');
    }

    /**
     * Check if portal user can download files
     */
    public function canDownloadFiles(array $portalAuth): bool
    {
        return $this->hasPermission($portalAuth, 'download_files');
    }

    /**
     * Check if portal user can submit feedback
     */
    public function canSubmitFeedback(array $portalAuth): bool
    {
        return $this->hasPermission($portalAuth, 'submit_feedback');
    }

    /**
     * Verify resource belongs to portal user's client
     */
    public function canAccessResource(array $portalAuth, string $resourceClientId): bool
    {
        return $portalAuth['client_id'] === $resourceClientId;
    }

    /**
     * Terminate current session
     */
    public function logout(string $sessionToken): bool
    {
        return $this->sessionModel->terminateSession($sessionToken);
    }

    /**
     * Get permission summary for UI
     */
    public function getPermissionSummary(array $portalAuth): array
    {
        return [
            'canViewInvoices' => $this->canViewInvoices($portalAuth),
            'canViewProposals' => $this->canViewProposals($portalAuth),
            'canViewProjects' => $this->canViewProjects($portalAuth),
            'canMakePayments' => $this->canMakePayments($portalAuth),
            'canDownloadFiles' => $this->canDownloadFiles($portalAuth),
            'canSubmitFeedback' => $this->canSubmitFeedback($portalAuth),
        ];
    }
}
