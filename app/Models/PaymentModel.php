<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Payment Model
 *
 * Manages payment records linked to Stripe transactions.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): PaymentGuard provides fine-grained authorization
 *
 * Business Logic:
 * - Payment status workflow (pending → processing → succeeded/failed)
 * - Refund tracking
 * - Stripe integration data storage
 *
 * Security Notes:
 * - All queries automatically filtered by RLS based on session variables
 * - Stripe IDs and metadata stored securely
 */
class PaymentModel extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false; // UUID primary key
    protected $returnType = 'array';
    protected $useSoftDeletes = false; // Payments should not be soft deleted for audit trail
    protected $protectFields = true;

    /**
     * Allowed fields for mass assignment
     */
    protected $allowedFields = [
        'agency_id',
        'invoice_id',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_checkout_session_id',
        'paypal_order_id',
        'paypal_capture_id',
        'manual_reference',
        'verified_by',
        'verified_at',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_method_details',
        'failure_code',
        'failure_message',
        'refund_id',
        'refund_amount',
        'refunded_at',
        'metadata',
        'processed_at',
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation rules
    protected $validationRules = [
        'invoice_id' => 'required|max_length[36]',
        'amount' => 'required|decimal',
        'currency' => 'required|max_length[3]',
        'status' => 'permit_empty|in_list[pending,processing,succeeded,failed,refunded,cancelled]',
    ];

    protected $validationMessages = [
        'invoice_id' => [
            'required' => 'Invoice ID is required for payment',
        ],
        'amount' => [
            'required' => 'Payment amount is required',
            'decimal' => 'Payment amount must be a valid decimal',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $afterInsert = ['logPaymentCreated'];
    protected $afterUpdate = ['logPaymentUpdated'];

    /**
     * Valid status transitions
     */
    private array $statusTransitions = [
        'pending' => ['processing', 'succeeded', 'failed', 'cancelled'],
        'processing' => ['succeeded', 'failed'],
        'succeeded' => ['refunded'], // Can only go to refunded
        'failed' => ['pending'], // Can retry
        'refunded' => [], // Terminal state
        'cancelled' => [], // Terminal state
    ];

    /**
     * Generate UUID for new payment records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Automatically set agency_id from invoice if not provided
     */
    protected function setAgencyId(array $data): array
    {
        if (!isset($data['data']['agency_id']) && isset($data['data']['invoice_id'])) {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($data['data']['invoice_id']);
            if ($invoice) {
                $data['data']['agency_id'] = $invoice['agency_id'];
            }
        }

        // Fallback to session if still not set
        if (!isset($data['data']['agency_id'])) {
            $user = session()->get('user');
            if ($user && isset($user['agency_id'])) {
                $data['data']['agency_id'] = $user['agency_id'];
            }
        }

        return $data;
    }

    /**
     * Get payment by ID
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get payment by Stripe Payment Intent ID
     */
    public function getByPaymentIntentId(string $paymentIntentId): ?array
    {
        return $this->where('stripe_payment_intent_id', $paymentIntentId)->first();
    }

    /**
     * Get payment by Stripe Checkout Session ID
     */
    public function getByCheckoutSessionId(string $sessionId): ?array
    {
        return $this->where('stripe_checkout_session_id', $sessionId)->first();
    }

    /**
     * Get payment by Stripe Charge ID
     */
    public function getByChargeId(string $chargeId): ?array
    {
        return $this->where('stripe_charge_id', $chargeId)->first();
    }

    /**
     * Get payments for an invoice
     */
    public function getByInvoiceId(string $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get successful payment for an invoice
     */
    public function getSuccessfulByInvoiceId(string $invoiceId): ?array
    {
        return $this->where('invoice_id', $invoiceId)
            ->where('status', 'succeeded')
            ->first();
    }

    /**
     * Get payments for current agency
     */
    public function getForCurrentAgency(array $filters = []): array
    {
        $builder = $this->builder();

        // Apply status filter
        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $builder->where('created_at >=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $builder->where('created_at <=', $filters['to_date']);
        }

        return $builder->orderBy('created_at', 'DESC')->get()->getResultArray();
    }

    /**
     * Update payment status with workflow validation
     */
    public function updateStatus(string $id, string $newStatus, array $additionalData = []): bool
    {
        $payment = $this->find($id);
        if (!$payment) {
            return false;
        }

        $currentStatus = $payment['status'];

        // Check if transition is allowed
        if (!isset($this->statusTransitions[$currentStatus]) ||
            !in_array($newStatus, $this->statusTransitions[$currentStatus])) {
            return false;
        }

        $updateData = array_merge(['status' => $newStatus], $additionalData);

        // Set processed_at timestamp on success/failure
        if (in_array($newStatus, ['succeeded', 'failed'])) {
            $updateData['processed_at'] = date('Y-m-d H:i:s');
        }

        return $this->update($id, $updateData);
    }

    /**
     * Record payment success from Stripe webhook
     */
    public function recordSuccess(string $id, array $stripeData): bool
    {
        return $this->update($id, [
            'status' => 'succeeded',
            'stripe_charge_id' => $stripeData['charge_id'] ?? null,
            'payment_method' => $stripeData['payment_method'] ?? null,
            'payment_method_details' => json_encode($stripeData['payment_method_details'] ?? []),
            'processed_at' => date('Y-m-d H:i:s'),
            'metadata' => json_encode($stripeData['metadata'] ?? []),
        ]);
    }

    /**
     * Record payment failure from Stripe webhook
     */
    public function recordFailure(string $id, array $stripeData): bool
    {
        return $this->update($id, [
            'status' => 'failed',
            'failure_code' => $stripeData['failure_code'] ?? null,
            'failure_message' => $stripeData['failure_message'] ?? null,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Record refund from Stripe webhook
     */
    public function recordRefund(string $id, array $refundData): bool
    {
        return $this->update($id, [
            'status' => 'refunded',
            'refund_id' => $refundData['refund_id'],
            'refund_amount' => $refundData['amount'],
            'refunded_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create a pending payment for checkout session
     */
    public function createPendingPayment(string $invoiceId, float $amount, string $currency, string $sessionId): ?string
    {
        $paymentId = $this->insert([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'stripe_checkout_session_id' => $sessionId,
        ], true);

        return $paymentId ?: null;
    }

    /**
     * Get summary statistics for current agency
     */
    public function getSummaryStats(): array
    {
        $stats = [
            'total_payments' => $this->countAllResults(false),
            'succeeded' => $this->where('status', 'succeeded')->countAllResults(false),
            'failed' => $this->where('status', 'failed')->countAllResults(false),
            'pending' => $this->where('status', 'pending')->countAllResults(false),
            'refunded' => $this->where('status', 'refunded')->countAllResults(false),
            'total_collected' => 0,
            'total_refunded' => 0,
        ];

        // Calculate total collected
        $collected = $this->select('SUM(amount) as total_collected')
            ->where('status', 'succeeded')
            ->first();
        $stats['total_collected'] = $collected['total_collected'] ?? 0;

        // Calculate total refunded
        $refunded = $this->select('SUM(refund_amount) as total_refunded')
            ->where('status', 'refunded')
            ->first();
        $stats['total_refunded'] = $refunded['total_refunded'] ?? 0;

        return $stats;
    }

    /**
     * Check if invoice has been paid
     */
    public function isInvoicePaid(string $invoiceId): bool
    {
        return $this->where('invoice_id', $invoiceId)
            ->where('status', 'succeeded')
            ->countAllResults() > 0;
    }

    /**
     * Get payment by PayPal Order ID
     */
    public function getByPayPalOrderId(string $orderId): ?array
    {
        return $this->where('paypal_order_id', $orderId)->first();
    }

    /**
     * Create a pending PayPal payment
     */
    public function createPendingPayPalPayment(string $invoiceId, float $amount, string $currency, string $orderId): ?string
    {
        $paymentId = $this->insert([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'payment_method' => 'paypal',
            'paypal_order_id' => $orderId,
        ], true);

        return $paymentId ?: null;
    }

    /**
     * Record PayPal payment success
     */
    public function recordPayPalSuccess(string $id, array $paypalData): bool
    {
        return $this->update($id, [
            'status' => 'succeeded',
            'paypal_capture_id' => $paypalData['capture_id'] ?? null,
            'payment_method_details' => json_encode($paypalData['details'] ?? []),
            'processed_at' => date('Y-m-d H:i:s'),
            'metadata' => json_encode($paypalData['metadata'] ?? []),
        ]);
    }

    /**
     * Create a pending manual payment (Zelle, check, wire, etc.)
     */
    public function createPendingManualPayment(string $invoiceId, float $amount, string $currency, string $paymentMethod, ?string $reference = null): ?string
    {
        $paymentId = $this->insert([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'manual_reference' => $reference,
        ], true);

        return $paymentId ?: null;
    }

    /**
     * Verify a manual payment (mark as succeeded after manual verification)
     */
    public function verifyManualPayment(string $id, string $verifiedBy, ?string $reference = null): bool
    {
        $updateData = [
            'status' => 'succeeded',
            'verified_by' => $verifiedBy,
            'verified_at' => date('Y-m-d H:i:s'),
            'processed_at' => date('Y-m-d H:i:s'),
        ];

        if ($reference) {
            $updateData['manual_reference'] = $reference;
        }

        return $this->update($id, $updateData);
    }

    /**
     * Create a pending Stripe ACH payment
     */
    public function createPendingACHPayment(string $invoiceId, float $amount, string $currency, string $paymentIntentId): ?string
    {
        $paymentId = $this->insert([
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'status' => 'pending',
            'payment_method' => 'stripe_ach',
            'stripe_payment_intent_id' => $paymentIntentId,
        ], true);

        return $paymentId ?: null;
    }

    /**
     * Get payments by payment method
     */
    public function getByPaymentMethod(string $paymentMethod): array
    {
        return $this->where('payment_method', $paymentMethod)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get pending manual payments awaiting verification
     */
    public function getPendingManualPayments(): array
    {
        return $this->whereIn('payment_method', ['zelle', 'check', 'wire', 'other'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'ASC')
            ->findAll();
    }

    /**
     * Log payment creation to timeline
     */
    protected function logPaymentCreated(array $data): array
    {
        $user = session()->get('user');
        if (!isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $payment = $this->find($data['id']);

        if ($payment) {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            $invoiceNumber = $invoice['invoice_number'] ?? 'Unknown';

            $timelineModel->logEvent(
                userId: $user['id'] ?? null,
                entityType: 'payment',
                entityId: $data['id'],
                eventType: 'created',
                description: "Payment initiated for invoice: {$invoiceNumber}"
            );
        }

        return $data;
    }

    /**
     * Log payment updates to timeline
     */
    protected function logPaymentUpdated(array $data): array
    {
        if (!isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $paymentId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $payment = $this->find($paymentId);

        if (!$payment) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($payment['invoice_id']);
        $invoiceNumber = $invoice['invoice_number'] ?? 'Unknown';

        // Determine event type based on status
        $status = $data['data']['status'] ?? $payment['status'];
        $eventType = 'updated';
        $description = "Payment updated for invoice: {$invoiceNumber}";

        switch ($status) {
            case 'succeeded':
                $eventType = 'payment_succeeded';
                $description = "Payment of {$payment['currency']} {$payment['amount']} received for invoice: {$invoiceNumber}";
                break;
            case 'failed':
                $eventType = 'payment_failed';
                $failureMessage = $data['data']['failure_message'] ?? 'Unknown error';
                $description = "Payment failed for invoice: {$invoiceNumber} - {$failureMessage}";
                break;
            case 'refunded':
                $eventType = 'payment_refunded';
                $refundAmount = $data['data']['refund_amount'] ?? $payment['amount'];
                $description = "Payment refunded ({$payment['currency']} {$refundAmount}) for invoice: {$invoiceNumber}";
                break;
        }

        $timelineModel->logEvent(
            userId: session()->get('user')['id'] ?? null,
            entityType: 'payment',
            entityId: $paymentId,
            eventType: $eventType,
            description: $description,
            metadata: [
                'status' => $status,
                'amount' => $payment['amount'],
                'currency' => $payment['currency'],
            ]
        );

        return $data;
    }
}
