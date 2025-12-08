<?php

namespace App\Helpers;

/**
 * Security Logger
 *
 * Centralized security audit logging for tracking access control violations,
 * authentication failures, and other security-relevant events.
 *
 * Log files are stored in writable/logs/ directory with daily rotation.
 * Format: security-YYYY-MM-DD.log (JSON lines format)
 *
 * Usage:
 *   SecurityLogger::logAccessDenied($user, '/invoices', 'End Client blocked from financial route');
 *   SecurityLogger::logAuthenticationFailure('user@example.com', 'Invalid password');
 *   SecurityLogger::logPrivilegeEscalation($user, 'owner', 'Attempted admin access');
 */
class SecurityLogger
{
    /**
     * Log access denial event
     *
     * Called when a user attempts to access a resource they don't have permission for.
     *
     * @param array $user User who attempted access (must have id, email, role)
     * @param string $resource Resource URI or identifier that was attempted
     * @param string $reason Human-readable reason for denial
     * @return void
     */
    public static function logAccessDenied(array $user, string $resource, string $reason): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'ACCESS_DENIED',
            'user_id' => $user['id'] ?? 'unknown',
            'user_email' => $user['email'] ?? 'unknown',
            'user_role' => $user['role'] ?? 'unknown',
            'agency_id' => $user['agency_id'] ?? null,
            'attempted_resource' => $resource,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        ];

        self::writeToSecurityLog($logData);
        log_message('warning', "Security: {$reason} - User {$user['email']} ({$user['role']}) attempted {$resource}");
    }

    /**
     * Log authentication failure event
     *
     * Called when login attempts fail due to invalid credentials or other auth issues.
     *
     * @param string $email Email address used in failed login attempt
     * @param string $reason Why authentication failed
     * @return void
     */
    public static function logAuthenticationFailure(string $email, string $reason): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'AUTHENTICATION_FAILURE',
            'attempted_email' => $email,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];

        self::writeToSecurityLog($logData);
        log_message('warning', "Security: Authentication failure for {$email} - {$reason}");
    }

    /**
     * Log privilege escalation attempt
     *
     * Called when a user attempts to perform an action requiring higher privileges.
     *
     * @param array $user User who attempted escalation
     * @param string $requiredRole Role required for the action
     * @param string $action What action was attempted
     * @return void
     */
    public static function logPrivilegeEscalation(array $user, string $requiredRole, string $action): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'PRIVILEGE_ESCALATION_ATTEMPT',
            'user_id' => $user['id'] ?? 'unknown',
            'user_email' => $user['email'] ?? 'unknown',
            'user_role' => $user['role'] ?? 'unknown',
            'required_role' => $requiredRole,
            'attempted_action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];

        self::writeToSecurityLog($logData);
        log_message('warning', "Security: Privilege escalation attempt by {$user['email']} ({$user['role']}) - {$action} requires {$requiredRole}");
    }

    /**
     * Log data access event (for audit trails)
     *
     * Called when sensitive data is accessed, for compliance and audit purposes.
     *
     * @param array $user User who accessed data
     * @param string $resourceType Type of data accessed (e.g., 'invoice', 'client', 'payment')
     * @param string $resourceId ID of specific resource accessed
     * @param string $action Action performed (view, create, update, delete)
     * @return void
     */
    public static function logDataAccess(array $user, string $resourceType, string $resourceId, string $action): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'DATA_ACCESS',
            'user_id' => $user['id'] ?? 'unknown',
            'user_email' => $user['email'] ?? 'unknown',
            'user_role' => $user['role'] ?? 'unknown',
            'agency_id' => $user['agency_id'] ?? null,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'action' => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        self::writeToSecurityLog($logData);
    }

    /**
     * Log suspicious activity
     *
     * Called when system detects potentially malicious behavior.
     *
     * @param array $user User associated with suspicious activity
     * @param string $activity Description of suspicious activity
     * @param array $metadata Additional context about the activity
     * @return void
     */
    public static function logSuspiciousActivity(array $user, string $activity, array $metadata = []): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => 'SUSPICIOUS_ACTIVITY',
            'user_id' => $user['id'] ?? 'unknown',
            'user_email' => $user['email'] ?? 'unknown',
            'user_role' => $user['role'] ?? 'unknown',
            'activity' => $activity,
            'metadata' => $metadata,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];

        self::writeToSecurityLog($logData);
        log_message('alert', "Security: Suspicious activity by {$user['email']} - {$activity}");
    }

    /**
     * Write security event to log file
     *
     * @param array $logData Event data to log
     * @return void
     */
    private static function writeToSecurityLog(array $logData): void
    {
        // Daily log file rotation
        $logFile = WRITEPATH . 'logs/security-' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);

        // Ensure log directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Write as JSON lines format (one JSON object per line)
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Parse security log file for analysis
     *
     * Reads a security log file and returns events as array of objects.
     * Useful for security dashboards and incident investigation.
     *
     * @param string $date Date of log file to read (YYYY-MM-DD format)
     * @return array Array of log events
     */
    public static function parseSecurityLog(string $date): array
    {
        $logFile = WRITEPATH . 'logs/security-' . $date . '.log';

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $events = [];

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    /**
     * Get security events for a specific user
     *
     * @param string $userId UUID of user to get events for
     * @param int $days Number of days to look back (default: 7)
     * @return array Array of security events
     */
    public static function getUserSecurityEvents(string $userId, int $days = 7): array
    {
        $events = [];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyEvents = self::parseSecurityLog($date);

            foreach ($dailyEvents as $event) {
                if (isset($event['user_id']) && $event['user_id'] === $userId) {
                    $events[] = $event;
                }
            }
        }

        return $events;
    }

    /**
     * Get recent security events by type
     *
     * @param string $eventType Event type to filter (e.g., 'ACCESS_DENIED', 'AUTHENTICATION_FAILURE')
     * @param int $days Number of days to look back (default: 7)
     * @param int $limit Maximum number of events to return (default: 100)
     * @return array Array of security events
     */
    public static function getEventsByType(string $eventType, int $days = 7, int $limit = 100): array
    {
        $events = [];

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $dailyEvents = self::parseSecurityLog($date);

            foreach ($dailyEvents as $event) {
                if (isset($event['event']) && $event['event'] === $eventType) {
                    $events[] = $event;

                    if (count($events) >= $limit) {
                        return $events;
                    }
                }
            }
        }

        return $events;
    }
}
