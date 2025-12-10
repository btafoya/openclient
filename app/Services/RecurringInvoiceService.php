<?php

namespace App\Services;

use App\Models\RecurringInvoiceModel;
use App\Models\InvoiceModel;
use App\Models\InvoiceLineItemModel;
use CodeIgniter\I18n\Time;

/**
 * Recurring Invoice Service
 *
 * Handles automatic invoice generation from recurring schedules.
 */
class RecurringInvoiceService
{
    protected RecurringInvoiceModel $recurringModel;
    protected InvoiceModel $invoiceModel;
    protected InvoiceLineItemModel $lineItemModel;

    public function __construct()
    {
        $this->recurringModel = new RecurringInvoiceModel();
        $this->invoiceModel = new InvoiceModel();
        $this->lineItemModel = new InvoiceLineItemModel();
    }

    /**
     * Process all due recurring invoices
     *
     * @return array Results of processing
     */
    public function processDueInvoices(): array
    {
        $results = [
            'processed' => 0,
            'generated' => 0,
            'failed' => 0,
            'errors' => [],
            'invoices' => [],
        ];

        // Get all active recurring invoices due for generation
        $dueSchedules = $this->recurringModel->getDueForGeneration();

        foreach ($dueSchedules as $schedule) {
            $results['processed']++;

            try {
                $invoice = $this->generateInvoice($schedule);

                if ($invoice) {
                    $results['generated']++;
                    $results['invoices'][] = $invoice;

                    // Auto-send if enabled
                    if ($schedule['auto_send']) {
                        $this->sendInvoice($invoice['id']);
                    }
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to generate invoice for schedule {$schedule['id']}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Schedule {$schedule['id']}: {$e->getMessage()}";
            }
        }

        return $results;
    }

    /**
     * Generate invoice from recurring schedule
     *
     * @param array $schedule Recurring invoice schedule
     * @return array|null Generated invoice or null on failure
     */
    public function generateInvoice(array $schedule): ?array
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber($schedule['agency_id']);

            // Calculate due date based on payment terms
            $dueDate = $this->calculateDueDate($schedule['payment_terms'] ?? 'net_30');

            // Create invoice
            $invoiceData = [
                'agency_id' => $schedule['agency_id'],
                'client_id' => $schedule['client_id'],
                'project_id' => $schedule['project_id'],
                'invoice_number' => $invoiceNumber,
                'issue_date' => date('Y-m-d'),
                'due_date' => $dueDate,
                'status' => 'draft',
                'subtotal' => 0,
                'tax_rate' => $schedule['tax_rate'] ?? 0,
                'tax_amount' => 0,
                'total' => 0,
                'notes' => $schedule['notes'] ?? null,
                'recurring_invoice_id' => $schedule['id'],
            ];

            $invoiceId = $this->invoiceModel->insert($invoiceData, true);
            if (!$invoiceId) {
                throw new \Exception('Failed to create invoice');
            }

            // Copy line items from recurring schedule
            $lineItems = $this->getRecurringLineItems($schedule['id']);
            $subtotal = 0;

            foreach ($lineItems as $item) {
                $lineItemData = [
                    'invoice_id' => $invoiceId,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'amount' => $item['quantity'] * $item['unit_price'],
                ];

                $this->lineItemModel->insert($lineItemData);
                $subtotal += $lineItemData['amount'];
            }

            // Update invoice totals
            $taxAmount = $subtotal * (($schedule['tax_rate'] ?? 0) / 100);
            $total = $subtotal + $taxAmount;

            $this->invoiceModel->update($invoiceId, [
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            // Update recurring schedule
            $this->updateScheduleAfterGeneration($schedule);

            $db->transComplete();

            if (!$db->transStatus()) {
                return null;
            }

            return $this->invoiceModel->find($invoiceId);

        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'RecurringInvoiceService::generateInvoice - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get line items for recurring invoice
     */
    protected function getRecurringLineItems(string $recurringInvoiceId): array
    {
        $db = \Config\Database::connect();
        return $db->table('recurring_invoice_items')
            ->where('recurring_invoice_id', $recurringInvoiceId)
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Update schedule after invoice generation
     */
    protected function updateScheduleAfterGeneration(array $schedule): void
    {
        $nextDate = $this->calculateNextInvoiceDate($schedule);
        $totalGenerated = ($schedule['total_invoices_generated'] ?? 0) + 1;

        $updateData = [
            'last_invoice_date' => date('Y-m-d'),
            'next_invoice_date' => $nextDate,
            'total_invoices_generated' => $totalGenerated,
        ];

        // Check if schedule should be completed
        if ($schedule['end_date'] && $nextDate > $schedule['end_date']) {
            $updateData['status'] = 'completed';
            $updateData['next_invoice_date'] = null;
        }

        if ($schedule['max_invoices'] && $totalGenerated >= $schedule['max_invoices']) {
            $updateData['status'] = 'completed';
            $updateData['next_invoice_date'] = null;
        }

        $this->recurringModel->update($schedule['id'], $updateData);
    }

    /**
     * Calculate next invoice date based on frequency
     */
    public function calculateNextInvoiceDate(array $schedule): string
    {
        $currentDate = Time::parse($schedule['next_invoice_date'] ?? 'now');

        switch ($schedule['frequency']) {
            case 'weekly':
                return $currentDate->addDays(7)->toDateString();

            case 'bi-weekly':
            case 'biweekly':
                return $currentDate->addDays(14)->toDateString();

            case 'monthly':
                $nextDate = $currentDate->addMonths(1);
                // Adjust for day_of_month if set
                if (!empty($schedule['day_of_month'])) {
                    $day = min($schedule['day_of_month'], $nextDate->daysInMonth);
                    $nextDate = $nextDate->setDay($day);
                }
                return $nextDate->toDateString();

            case 'quarterly':
                return $currentDate->addMonths(3)->toDateString();

            case 'semi-annually':
            case 'semiannually':
                return $currentDate->addMonths(6)->toDateString();

            case 'annually':
            case 'yearly':
                return $currentDate->addYears(1)->toDateString();

            default:
                return $currentDate->addMonths(1)->toDateString();
        }
    }

    /**
     * Calculate due date based on payment terms
     */
    protected function calculateDueDate(string $paymentTerms): string
    {
        $issueDate = Time::now();

        switch ($paymentTerms) {
            case 'due_on_receipt':
                return $issueDate->toDateString();
            case 'net_15':
                return $issueDate->addDays(15)->toDateString();
            case 'net_30':
                return $issueDate->addDays(30)->toDateString();
            case 'net_45':
                return $issueDate->addDays(45)->toDateString();
            case 'net_60':
                return $issueDate->addDays(60)->toDateString();
            default:
                return $issueDate->addDays(30)->toDateString();
        }
    }

    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(string $agencyId): string
    {
        $db = \Config\Database::connect();

        // Get current count for agency
        $count = $db->table('invoices')
            ->where('agency_id', $agencyId)
            ->countAllResults();

        $prefix = 'INV';
        $year = date('Y');
        $sequence = str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$sequence}";
    }

    /**
     * Send invoice to client
     */
    protected function sendInvoice(string $invoiceId): bool
    {
        try {
            $emailService = new InvoiceEmailService();
            return $emailService->sendInvoice($invoiceId);
        } catch (\Exception $e) {
            log_message('error', 'Failed to auto-send invoice: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Preview next invoice for a recurring schedule
     */
    public function previewNextInvoice(string $scheduleId): ?array
    {
        $schedule = $this->recurringModel->find($scheduleId);
        if (!$schedule) {
            return null;
        }

        $lineItems = $this->getRecurringLineItems($scheduleId);
        $subtotal = 0;

        foreach ($lineItems as &$item) {
            $item['amount'] = $item['quantity'] * $item['unit_price'];
            $subtotal += $item['amount'];
        }

        $taxAmount = $subtotal * (($schedule['tax_rate'] ?? 0) / 100);
        $total = $subtotal + $taxAmount;

        return [
            'schedule' => $schedule,
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'tax_rate' => $schedule['tax_rate'] ?? 0,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'next_invoice_date' => $schedule['next_invoice_date'],
            'due_date' => $this->calculateDueDate($schedule['payment_terms'] ?? 'net_30'),
        ];
    }

    /**
     * Pause a recurring schedule
     */
    public function pauseSchedule(string $scheduleId): bool
    {
        return $this->recurringModel->update($scheduleId, ['status' => 'paused']);
    }

    /**
     * Resume a recurring schedule
     */
    public function resumeSchedule(string $scheduleId): bool
    {
        $schedule = $this->recurringModel->find($scheduleId);
        if (!$schedule) {
            return false;
        }

        // Recalculate next invoice date if it's in the past
        $nextDate = $schedule['next_invoice_date'];
        if ($nextDate && $nextDate < date('Y-m-d')) {
            $nextDate = $this->calculateNextInvoiceDate($schedule);
        }

        return $this->recurringModel->update($scheduleId, [
            'status' => 'active',
            'next_invoice_date' => $nextDate,
        ]);
    }

    /**
     * Cancel a recurring schedule
     */
    public function cancelSchedule(string $scheduleId): bool
    {
        return $this->recurringModel->update($scheduleId, [
            'status' => 'cancelled',
            'next_invoice_date' => null,
        ]);
    }

    /**
     * Get upcoming invoices for all active schedules
     */
    public function getUpcomingInvoices(int $days = 30): array
    {
        $endDate = Time::now()->addDays($days)->toDateString();

        return $this->recurringModel
            ->where('status', 'active')
            ->where('next_invoice_date <=', $endDate)
            ->orderBy('next_invoice_date', 'ASC')
            ->findAll();
    }
}
