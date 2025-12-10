<?php

namespace App\Services;

use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Models\TimelineModel;

/**
 * Manual Payment Service
 *
 * Handles manual payment methods that require verification:
 * - Zelle
 * - Check
 * - Wire transfer
 * - Other manual payments
 *
 * These payments are recorded as pending and must be manually
 * verified by an agency user before being marked as successful.
 *
 * Security Notes:
 * - All verifications are logged with user ID
 * - Timestamps recorded for audit trail
 * - Reference numbers tracked for reconciliation
 */
class ManualPaymentService
{
    protected InvoiceModel $invoiceModel;
    protected PaymentModel $paymentModel;
    protected ClientModel $clientModel;
    protected TimelineModel $timelineModel;

    /**
     * Supported manual payment methods
     */
    public const PAYMENT_METHODS = [
        'zelle' => [
            'name' => 'Zelle',
            'description' => 'Bank-to-bank instant transfer',
            'requires_reference' => true,
            'reference_label' => 'Zelle Confirmation Number',
        ],
        'check' => [
            'name' => 'Check',
            'description' => 'Physical check payment',
            'requires_reference' => true,
            'reference_label' => 'Check Number',
        ],
        'wire' => [
            'name' => 'Wire Transfer',
            'description' => 'Bank wire transfer',
            'requires_reference' => true,
            'reference_label' => 'Wire Reference Number',
        ],
        'cash' => [
            'name' => 'Cash',
            'description' => 'Cash payment',
            'requires_reference' => false,
            'reference_label' => 'Receipt Number',
        ],
        'other' => [
            'name' => 'Other',
            'description' => 'Other payment method',
            'requires_reference' => false,
            'reference_label' => 'Reference Number',
        ],
    ];

    public function __construct()
    {
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->clientModel = new ClientModel();
        $this->timelineModel = new TimelineModel();
    }

    /**
     * Record a pending manual payment
     *
     * @param string $invoiceId Invoice UUID
     * @param string $paymentMethod Payment method type (zelle, check, wire, cash, other)
     * @param float|null $amount Amount paid (null for full amount)
     * @param string|null $reference Reference/confirmation number
     * @param array $metadata Additional payment metadata
     * @return array Payment record data
     */
    public function recordPayment(
        string $invoiceId,
        string $paymentMethod,
        ?float $amount = null,
        ?string $reference = null,
        array $metadata = []
    ): array {
        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        if (!$invoice) {
            throw new \InvalidArgumentException('Invoice not found');
        }

        if ($invoice['status'] === 'paid') {
            throw new \InvalidArgumentException('Invoice is already paid');
        }

        if ($invoice['status'] === 'cancelled') {
            throw new \InvalidArgumentException('Invoice is cancelled');
        }

        if (!isset(self::PAYMENT_METHODS[$paymentMethod])) {
            throw new \InvalidArgumentException('Invalid payment method: ' . $paymentMethod);
        }

        $paymentAmount = $amount ?? $invoice['total'];

        // Create pending payment record
        $paymentId = $this->paymentModel->createPendingManualPayment(
            $invoiceId,
            $paymentAmount,
            $invoice['currency'],
            $paymentMethod,
            $reference
        );

        if (!$paymentId) {
            throw new \RuntimeException('Failed to create payment record');
        }

        // Store metadata if provided
        if (!empty($metadata)) {
            $this->paymentModel->update($paymentId, [
                'metadata' => json_encode($metadata),
            ]);
        }

        // Log to timeline
        $methodInfo = self::PAYMENT_METHODS[$paymentMethod];
        $this->timelineModel->logEvent(
            userId: session()->get('user')['id'] ?? null,
            entityType: 'payment',
            entityId: $paymentId,
            eventType: 'manual_payment_recorded',
            description: "{$methodInfo['name']} payment of {$invoice['currency']} {$paymentAmount} recorded for invoice {$invoice['invoice_number']} (pending verification)",
            metadata: [
                'payment_method' => $paymentMethod,
                'amount' => $paymentAmount,
                'reference' => $reference,
            ]
        );

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'pending',
            'message' => 'Payment recorded and awaiting verification',
        ];
    }

    /**
     * Verify a pending manual payment
     *
     * @param string $paymentId Payment UUID
     * @param string $verifiedBy User ID who verified the payment
     * @param string|null $reference Updated reference number
     * @param string|null $notes Verification notes
     * @return array Verification result
     */
    public function verifyPayment(
        string $paymentId,
        string $verifiedBy,
        ?string $reference = null,
        ?string $notes = null
    ): array {
        $payment = $this->paymentModel->find($paymentId);

        if (!$payment) {
            throw new \InvalidArgumentException('Payment not found');
        }

        if ($payment['status'] !== 'pending') {
            throw new \InvalidArgumentException('Only pending payments can be verified');
        }

        // Verify the payment
        $success = $this->paymentModel->verifyManualPayment($paymentId, $verifiedBy, $reference);

        if (!$success) {
            throw new \RuntimeException('Failed to verify payment');
        }

        // Update invoice status
        $invoice = $this->invoiceModel->find($payment['invoice_id']);
        if ($invoice) {
            $this->invoiceModel->updateStatus($payment['invoice_id'], 'paid');
        }

        // Add verification notes to metadata if provided
        if ($notes) {
            $metadata = json_decode($payment['metadata'] ?? '{}', true);
            $metadata['verification_notes'] = $notes;
            $this->paymentModel->update($paymentId, [
                'metadata' => json_encode($metadata),
            ]);
        }

        // Log to timeline
        $methodInfo = self::PAYMENT_METHODS[$payment['payment_method']] ?? ['name' => 'Manual'];
        $this->timelineModel->logEvent(
            userId: $verifiedBy,
            entityType: 'payment',
            entityId: $paymentId,
            eventType: 'manual_payment_verified',
            description: "{$methodInfo['name']} payment of {$payment['currency']} {$payment['amount']} verified for invoice {$invoice['invoice_number']}",
            metadata: [
                'payment_method' => $payment['payment_method'],
                'amount' => $payment['amount'],
                'reference' => $reference ?? $payment['manual_reference'],
                'notes' => $notes,
            ]
        );

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'succeeded',
            'message' => 'Payment verified successfully',
        ];
    }

    /**
     * Reject a pending manual payment
     *
     * @param string $paymentId Payment UUID
     * @param string $rejectedBy User ID who rejected the payment
     * @param string $reason Rejection reason
     * @return array Rejection result
     */
    public function rejectPayment(string $paymentId, string $rejectedBy, string $reason): array
    {
        $payment = $this->paymentModel->find($paymentId);

        if (!$payment) {
            throw new \InvalidArgumentException('Payment not found');
        }

        if ($payment['status'] !== 'pending') {
            throw new \InvalidArgumentException('Only pending payments can be rejected');
        }

        // Mark as failed
        $this->paymentModel->recordFailure($paymentId, [
            'failure_code' => 'VERIFICATION_REJECTED',
            'failure_message' => $reason,
        ]);

        // Update metadata
        $metadata = json_decode($payment['metadata'] ?? '{}', true);
        $metadata['rejected_by'] = $rejectedBy;
        $metadata['rejected_at'] = date('Y-m-d H:i:s');
        $metadata['rejection_reason'] = $reason;
        $this->paymentModel->update($paymentId, [
            'metadata' => json_encode($metadata),
        ]);

        // Log to timeline
        $invoice = $this->invoiceModel->find($payment['invoice_id']);
        $methodInfo = self::PAYMENT_METHODS[$payment['payment_method']] ?? ['name' => 'Manual'];
        $this->timelineModel->logEvent(
            userId: $rejectedBy,
            entityType: 'payment',
            entityId: $paymentId,
            eventType: 'manual_payment_rejected',
            description: "{$methodInfo['name']} payment of {$payment['currency']} {$payment['amount']} rejected for invoice {$invoice['invoice_number']}: {$reason}",
            metadata: [
                'payment_method' => $payment['payment_method'],
                'amount' => $payment['amount'],
                'reason' => $reason,
            ]
        );

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'failed',
            'message' => 'Payment rejected',
        ];
    }

    /**
     * Get pending manual payments awaiting verification
     */
    public function getPendingPayments(): array
    {
        $payments = $this->paymentModel->getPendingManualPayments();

        // Enrich with invoice and client data
        foreach ($payments as &$payment) {
            $invoice = $this->invoiceModel->find($payment['invoice_id']);
            if ($invoice) {
                $payment['invoice'] = $invoice;
                $client = $this->clientModel->find($invoice['client_id']);
                $payment['client'] = $client;
            }
            $payment['method_info'] = self::PAYMENT_METHODS[$payment['payment_method']] ?? null;
        }

        return $payments;
    }

    /**
     * Get available payment methods with their configuration
     */
    public function getPaymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    /**
     * Get Zelle payment instructions for a client
     *
     * @param string $invoiceId Invoice UUID
     * @return array Payment instructions
     */
    public function getZelleInstructions(string $invoiceId): array
    {
        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        if (!$invoice) {
            throw new \InvalidArgumentException('Invoice not found');
        }

        // Get Zelle configuration from environment
        $zelleEmail = env('ZELLE_EMAIL', '');
        $zellePhone = env('ZELLE_PHONE', '');
        $zelleRecipient = env('ZELLE_RECIPIENT_NAME', env('APP_NAME', 'OpenClient'));

        return [
            'method' => 'zelle',
            'instructions' => [
                'recipient_name' => $zelleRecipient,
                'recipient_email' => $zelleEmail,
                'recipient_phone' => $zellePhone,
                'amount' => $invoice['total'],
                'currency' => $invoice['currency'],
                'memo' => 'Invoice ' . $invoice['invoice_number'],
            ],
            'steps' => [
                '1. Open your banking app and go to Zelle',
                '2. Send payment to: ' . ($zelleEmail ?: $zellePhone),
                '3. Amount: ' . $invoice['currency'] . ' ' . number_format($invoice['total'], 2),
                '4. Memo: Invoice ' . $invoice['invoice_number'],
                '5. After sending, note the confirmation number',
                '6. Submit the confirmation number to complete payment',
            ],
            'note' => 'Payment will be verified within 1-2 business days.',
        ];
    }

    /**
     * Get check payment instructions
     */
    public function getCheckInstructions(string $invoiceId): array
    {
        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        if (!$invoice) {
            throw new \InvalidArgumentException('Invoice not found');
        }

        $payeeName = env('CHECK_PAYEE_NAME', env('APP_NAME', 'OpenClient'));
        $mailingAddress = env('CHECK_MAILING_ADDRESS', '');

        return [
            'method' => 'check',
            'instructions' => [
                'payee_name' => $payeeName,
                'mailing_address' => $mailingAddress,
                'amount' => $invoice['total'],
                'currency' => $invoice['currency'],
                'memo' => 'Invoice ' . $invoice['invoice_number'],
            ],
            'steps' => [
                '1. Make check payable to: ' . $payeeName,
                '2. Amount: ' . $invoice['currency'] . ' ' . number_format($invoice['total'], 2),
                '3. Write "Invoice ' . $invoice['invoice_number'] . '" in the memo line',
                '4. Mail check to: ' . $mailingAddress,
            ],
            'note' => 'Please allow 7-10 business days for check processing.',
        ];
    }

    /**
     * Get wire transfer instructions
     */
    public function getWireInstructions(string $invoiceId): array
    {
        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        if (!$invoice) {
            throw new \InvalidArgumentException('Invoice not found');
        }

        return [
            'method' => 'wire',
            'instructions' => [
                'bank_name' => env('WIRE_BANK_NAME', ''),
                'account_name' => env('WIRE_ACCOUNT_NAME', ''),
                'account_number' => env('WIRE_ACCOUNT_NUMBER', ''),
                'routing_number' => env('WIRE_ROUTING_NUMBER', ''),
                'swift_code' => env('WIRE_SWIFT_CODE', ''),
                'amount' => $invoice['total'],
                'currency' => $invoice['currency'],
                'reference' => 'Invoice ' . $invoice['invoice_number'],
            ],
            'steps' => [
                '1. Initiate wire transfer through your bank',
                '2. Use the banking details provided above',
                '3. Include "Invoice ' . $invoice['invoice_number'] . '" as reference',
                '4. Amount: ' . $invoice['currency'] . ' ' . number_format($invoice['total'], 2),
                '5. Save the wire confirmation number',
                '6. Submit the confirmation number to complete payment',
            ],
            'note' => 'Domestic wires typically clear in 1 business day. International wires may take 3-5 business days.',
        ];
    }
}
