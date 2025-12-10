<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Recurring Invoice Model
 *
 * Manages recurring invoice templates for automated billing.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 *
 * Frequency Options:
 * - daily, weekly, biweekly, monthly, quarterly, yearly
 * - interval_count for custom intervals (e.g., every 2 weeks)
 */
class RecurringInvoiceModel extends Model
{
    protected $table = 'recurring_invoices';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'client_id',
        'project_id',
        'title',
        'description',
        'line_items',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_percent',
        'discount_amount',
        'total_amount',
        'currency',
        'frequency',
        'interval_count',
        'day_of_week',
        'day_of_month',
        'start_date',
        'end_date',
        'next_run_date',
        'last_run_date',
        'status',
        'auto_send',
        'payment_terms_days',
        'last_invoice_id',
        'invoice_count',
        'max_occurrences',
        'email_recipients',
        'notes',
        'metadata',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'client_id' => 'required|max_length[36]',
        'title' => 'required|max_length[255]',
        'frequency' => 'required|in_list[daily,weekly,biweekly,monthly,quarterly,yearly]',
        'start_date' => 'required|valid_date',
        'status' => 'permit_empty|in_list[active,paused,completed,cancelled]',
    ];

    protected $validationMessages = [
        'client_id' => [
            'required' => 'Client is required',
        ],
        'title' => [
            'required' => 'Title is required',
        ],
        'frequency' => [
            'required' => 'Billing frequency is required',
            'in_list' => 'Invalid frequency. Must be: daily, weekly, biweekly, monthly, quarterly, or yearly',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'calculateNextRunDate', 'setCreatedBy'];

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
     * Set agency_id from session
     */
    protected function setAgencyId(array $data): array
    {
        if (!isset($data['data']['agency_id'])) {
            $user = session()->get('user');
            if ($user && isset($user['agency_id'])) {
                $data['data']['agency_id'] = $user['agency_id'];
            }
        }
        return $data;
    }

    /**
     * Set created_by from session
     */
    protected function setCreatedBy(array $data): array
    {
        if (!isset($data['data']['created_by'])) {
            $user = session()->get('user');
            if ($user && isset($user['id'])) {
                $data['data']['created_by'] = $user['id'];
            }
        }
        return $data;
    }

    /**
     * Calculate next run date based on frequency and start date
     */
    protected function calculateNextRunDate(array $data): array
    {
        if (!isset($data['data']['next_run_date']) && isset($data['data']['start_date'])) {
            $startDate = new \DateTime($data['data']['start_date']);
            $today = new \DateTime();

            if ($startDate >= $today) {
                $data['data']['next_run_date'] = $data['data']['start_date'];
            } else {
                // Calculate the next occurrence after today
                $data['data']['next_run_date'] = $this->getNextOccurrence(
                    $data['data']['frequency'],
                    $data['data']['interval_count'] ?? 1,
                    $startDate,
                    $data['data']['day_of_week'] ?? null,
                    $data['data']['day_of_month'] ?? null
                );
            }
        }
        return $data;
    }

    /**
     * Get the next occurrence date based on frequency
     */
    public function getNextOccurrence(
        string $frequency,
        int $intervalCount = 1,
        ?\DateTime $fromDate = null,
        ?int $dayOfWeek = null,
        ?int $dayOfMonth = null
    ): string {
        $fromDate = $fromDate ?? new \DateTime();
        $nextDate = clone $fromDate;

        switch ($frequency) {
            case 'daily':
                $nextDate->modify("+{$intervalCount} days");
                break;

            case 'weekly':
                $nextDate->modify("+{$intervalCount} weeks");
                if ($dayOfWeek !== null) {
                    $currentDay = (int) $nextDate->format('N');
                    $diff = $dayOfWeek - $currentDay;
                    if ($diff <= 0) {
                        $diff += 7;
                    }
                    $nextDate->modify("+{$diff} days");
                }
                break;

            case 'biweekly':
                $nextDate->modify("+" . ($intervalCount * 2) . " weeks");
                break;

            case 'monthly':
                $nextDate->modify("+{$intervalCount} months");
                if ($dayOfMonth !== null) {
                    $lastDay = (int) $nextDate->format('t');
                    $targetDay = min($dayOfMonth, $lastDay);
                    $nextDate->setDate(
                        (int) $nextDate->format('Y'),
                        (int) $nextDate->format('m'),
                        $targetDay
                    );
                }
                break;

            case 'quarterly':
                $nextDate->modify("+" . ($intervalCount * 3) . " months");
                break;

            case 'yearly':
                $nextDate->modify("+{$intervalCount} years");
                break;
        }

        return $nextDate->format('Y-m-d');
    }

    /**
     * Get active recurring invoices that are due to run
     */
    public function getDueForProcessing(): array
    {
        return $this->where('status', 'active')
            ->where('next_run_date <=', date('Y-m-d'))
            ->groupStart()
                ->where('end_date IS NULL')
                ->orWhere('end_date >=', date('Y-m-d'))
            ->groupEnd()
            ->groupStart()
                ->where('max_occurrences IS NULL')
                ->orWhere('invoice_count < max_occurrences', null, false)
            ->groupEnd()
            ->findAll();
    }

    /**
     * Process a recurring invoice and generate a new invoice
     */
    public function processRecurringInvoice(string $id): ?string
    {
        $recurring = $this->find($id);
        if (!$recurring || $recurring['status'] !== 'active') {
            return null;
        }

        // Parse JSONB fields
        $lineItems = is_string($recurring['line_items'])
            ? json_decode($recurring['line_items'], true)
            : $recurring['line_items'];

        $invoiceModel = new InvoiceModel();
        $lineItemModel = new InvoiceLineItemModel();

        // Create the invoice
        $issueDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime("+{$recurring['payment_terms_days']} days"));

        $invoiceData = [
            'client_id' => $recurring['client_id'],
            'project_id' => $recurring['project_id'],
            'agency_id' => $recurring['agency_id'],
            'status' => 'draft',
            'subtotal' => $recurring['subtotal'],
            'tax_rate' => $recurring['tax_rate'],
            'tax_amount' => $recurring['tax_amount'],
            'discount_amount' => $recurring['discount_amount'],
            'total' => $recurring['total_amount'],
            'currency' => $recurring['currency'],
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'notes' => $recurring['notes'],
        ];

        $invoiceId = $invoiceModel->insert($invoiceData, true);
        if (!$invoiceId) {
            return null;
        }

        // Create line items
        if (!empty($lineItems)) {
            $sortOrder = 0;
            foreach ($lineItems as $item) {
                $lineItemModel->insert([
                    'invoice_id' => $invoiceId,
                    'description' => $item['description'] ?? '',
                    'quantity' => $item['quantity'] ?? 1,
                    'unit_price' => $item['unit_price'] ?? 0,
                    'amount' => ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0),
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // Update recurring invoice record
        $newCount = ($recurring['invoice_count'] ?? 0) + 1;
        $nextRunDate = $this->getNextOccurrence(
            $recurring['frequency'],
            $recurring['interval_count'] ?? 1,
            new \DateTime($recurring['next_run_date']),
            $recurring['day_of_week'],
            $recurring['day_of_month']
        );

        $updateData = [
            'last_run_date' => date('Y-m-d'),
            'last_invoice_id' => $invoiceId,
            'invoice_count' => $newCount,
            'next_run_date' => $nextRunDate,
        ];

        // Check if we should complete the recurring invoice
        if ($recurring['max_occurrences'] && $newCount >= $recurring['max_occurrences']) {
            $updateData['status'] = 'completed';
        }
        if ($recurring['end_date'] && $nextRunDate > $recurring['end_date']) {
            $updateData['status'] = 'completed';
        }

        $this->update($id, $updateData);

        // Auto-send if enabled
        if ($recurring['auto_send']) {
            $invoiceModel->updateStatus($invoiceId, 'sent');
            // Email sending would be handled by a service
        }

        return $invoiceId;
    }

    /**
     * Get recurring invoices by client
     */
    public function getByClientId(string $clientId): array
    {
        return $this->where('client_id', $clientId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get recurring invoices by status
     */
    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('next_run_date', 'ASC')
            ->findAll();
    }

    /**
     * Pause a recurring invoice
     */
    public function pause(string $id): bool
    {
        return $this->update($id, ['status' => 'paused']);
    }

    /**
     * Resume a paused recurring invoice
     */
    public function resume(string $id): bool
    {
        $recurring = $this->find($id);
        if (!$recurring || $recurring['status'] !== 'paused') {
            return false;
        }

        // Recalculate next run date if it's in the past
        $nextRunDate = $recurring['next_run_date'];
        if ($nextRunDate < date('Y-m-d')) {
            $nextRunDate = $this->getNextOccurrence(
                $recurring['frequency'],
                $recurring['interval_count'] ?? 1,
                new \DateTime(),
                $recurring['day_of_week'],
                $recurring['day_of_month']
            );
        }

        return $this->update($id, [
            'status' => 'active',
            'next_run_date' => $nextRunDate,
        ]);
    }

    /**
     * Cancel a recurring invoice
     */
    public function cancel(string $id): bool
    {
        return $this->update($id, ['status' => 'cancelled']);
    }

    /**
     * Get with related data
     */
    public function getWithRelated(string $id): ?array
    {
        $recurring = $this->find($id);
        if (!$recurring) {
            return null;
        }

        // Parse JSONB fields
        if (!empty($recurring['line_items']) && is_string($recurring['line_items'])) {
            $recurring['line_items'] = json_decode($recurring['line_items'], true);
        }
        if (!empty($recurring['email_recipients']) && is_string($recurring['email_recipients'])) {
            $recurring['email_recipients'] = json_decode($recurring['email_recipients'], true);
        }
        if (!empty($recurring['metadata']) && is_string($recurring['metadata'])) {
            $recurring['metadata'] = json_decode($recurring['metadata'], true);
        }

        // Get client
        $clientModel = new ClientModel();
        $recurring['client'] = $clientModel->find($recurring['client_id']);

        // Get last invoice if exists
        if (!empty($recurring['last_invoice_id'])) {
            $invoiceModel = new InvoiceModel();
            $recurring['last_invoice'] = $invoiceModel->find($recurring['last_invoice_id']);
        }

        return $recurring;
    }
}
