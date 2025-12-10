<?php

namespace App\Services;

use App\Models\ActivityLogModel;

/**
 * Activity Log Service
 *
 * Provides a convenient interface for logging system activities.
 */
class ActivityLogService
{
    protected ActivityLogModel $model;
    protected static ?ActivityLogService $instance = null;

    public function __construct()
    {
        $this->model = new ActivityLogModel();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): ActivityLogService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log any activity
     */
    public function log(
        string $entityType,
        ?string $entityId,
        string $action,
        ?string $description = null,
        ?array $metadata = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?string {
        return $this->model->log(
            $entityType,
            $entityId,
            $action,
            $description,
            $metadata,
            $oldValues,
            $newValues
        );
    }

    /**
     * Log entity creation
     */
    public function logCreated(string $entityType, string $entityId, ?string $entityName = null, ?array $data = null): ?string
    {
        return $this->model->logCreated($entityType, $entityId, $entityName, $data);
    }

    /**
     * Log entity update
     */
    public function logUpdated(string $entityType, string $entityId, ?string $entityName = null, ?array $oldValues = null, ?array $newValues = null): ?string
    {
        return $this->model->logUpdated($entityType, $entityId, $entityName, $oldValues, $newValues);
    }

    /**
     * Log entity deletion
     */
    public function logDeleted(string $entityType, string $entityId, ?string $entityName = null): ?string
    {
        return $this->model->logDeleted($entityType, $entityId, $entityName);
    }

    /**
     * Log user login
     */
    public function logLogin(?string $userId = null): ?string
    {
        return $this->model->logLogin($userId);
    }

    /**
     * Log user logout
     */
    public function logLogout(?string $userId = null): ?string
    {
        return $this->model->logLogout($userId);
    }

    /**
     * Log client action
     */
    public function logClientAction(string $clientId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('client', $clientId, $action, $description, $metadata);
    }

    /**
     * Log invoice action
     */
    public function logInvoiceAction(string $invoiceId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('invoice', $invoiceId, $action, $description, $metadata);
    }

    /**
     * Log payment action
     */
    public function logPaymentAction(string $paymentId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('payment', $paymentId, $action, $description, $metadata);
    }

    /**
     * Log project action
     */
    public function logProjectAction(string $projectId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('project', $projectId, $action, $description, $metadata);
    }

    /**
     * Log ticket action
     */
    public function logTicketAction(string $ticketId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('ticket', $ticketId, $action, $description, $metadata);
    }

    /**
     * Log file action
     */
    public function logFileAction(string $fileId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('file', $fileId, $action, $description, $metadata);
    }

    /**
     * Log proposal action
     */
    public function logProposalAction(string $proposalId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('proposal', $proposalId, $action, $description, $metadata);
    }

    /**
     * Log deal action
     */
    public function logDealAction(string $dealId, string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('deal', $dealId, $action, $description, $metadata);
    }

    /**
     * Log system action
     */
    public function logSystemAction(string $action, string $description, ?array $metadata = null): ?string
    {
        return $this->log('system', null, $action, $description, $metadata);
    }

    /**
     * Get recent activity
     */
    public function getRecent(int $limit = 50): array
    {
        return $this->model->getRecent($limit);
    }

    /**
     * Get activity for entity
     */
    public function getForEntity(string $entityType, string $entityId, int $limit = 50): array
    {
        return $this->model->getForEntity($entityType, $entityId, $limit);
    }

    /**
     * Get activity for user
     */
    public function getForUser(string $userId, int $limit = 50): array
    {
        return $this->model->getForUser($userId, $limit);
    }

    /**
     * Get statistics
     */
    public function getStatistics(int $days = 7): array
    {
        return $this->model->getStatistics($days);
    }

    /**
     * Clear old logs
     */
    public function clearOld(int $daysOld = 90): int
    {
        return $this->model->clearOld($daysOld);
    }
}
