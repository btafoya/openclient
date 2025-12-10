<?php

namespace App\Services;

use Config\Stripe as StripeConfig;
use Stripe\Webhook;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\TimelineModel;

/**
 * Stripe Webhook Service
 *
 * Handles incoming Stripe webhook events:
 * - checkout.session.completed
 * - payment_intent.succeeded
 * - payment_intent.payment_failed
 * - charge.refunded
 *
 * Security Notes:
 * - Always verifies webhook signature
 * - Idempotent event handling (safe to receive same event multiple times)
 * - Logs all webhook events for debugging
 */
class StripeWebhookService
{
    protected StripeConfig $config;
    protected InvoiceModel $invoiceModel;
    protected PaymentModel $paymentModel;
    protected TimelineModel $timelineModel;

    public function __construct()
    {
        $this->config = config('Stripe');
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->timelineModel = new TimelineModel();
    }

    /**
     * Verify webhook signature and construct event
     *
     * @param string $payload Raw request body
     * @param string $sigHeader Stripe-Signature header
     * @return Event|null Stripe Event or null if verification fails
     */
    public function verifyWebhook(string $payload, string $sigHeader): ?Event
    {
        if (empty($this->config->webhookSecret)) {
            log_message('warning', 'Stripe webhook secret not configured');
            // In development, we might want to allow unsigned webhooks
            // return json_decode($payload);
            return null;
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->config->webhookSecret
            );

            log_message('info', 'Stripe webhook verified: ' . $event->type);
            return $event;
        } catch (SignatureVerificationException $e) {
            log_message('error', 'Stripe webhook signature verification failed: ' . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            log_message('error', 'Stripe webhook error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Handle a verified Stripe event
     *
     * @param Event $event Stripe Event
     * @return array Result of handling the event
     */
    public function handleEvent(Event $event): array
    {
        $eventType = $event->type;
        $eventData = $event->data->object;

        log_message('info', "Handling Stripe webhook: {$eventType}");

        switch ($eventType) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($eventData);

            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($eventData);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentFailed($eventData);

            case 'charge.refunded':
                return $this->handleChargeRefunded($eventData);

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                // For future subscription support
                return ['handled' => false, 'reason' => 'Subscription events not yet implemented'];

            default:
                log_message('info', "Unhandled Stripe event type: {$eventType}");
                return ['handled' => false, 'reason' => 'Event type not handled'];
        }
    }

    /**
     * Handle checkout.session.completed event
     * This is the primary event for Checkout Session payments
     */
    protected function handleCheckoutSessionCompleted(object $session): array
    {
        $sessionId = $session->id;
        $paymentStatus = $session->payment_status;

        log_message('info', "Checkout session completed: {$sessionId}, status: {$paymentStatus}");

        // Find our payment record by session ID
        $payment = $this->paymentModel->getByCheckoutSessionId($sessionId);

        if (!$payment) {
            log_message('warning', "No payment found for session: {$sessionId}");
            return ['handled' => false, 'reason' => 'Payment record not found'];
        }

        // Check if already processed (idempotency)
        if ($payment['status'] === 'succeeded') {
            return ['handled' => true, 'reason' => 'Already processed'];
        }

        if ($paymentStatus === 'paid') {
            // Payment was successful
            $paymentIntentId = $session->payment_intent;

            $this->paymentModel->update($payment['id'], [
                'status' => 'succeeded',
                'stripe_payment_intent_id' => $paymentIntentId,
                'processed_at' => date('Y-m-d H:i:s'),
                'metadata' => json_encode([
                    'customer_email' => $session->customer_email ?? null,
                    'customer_id' => $session->customer ?? null,
                ]),
            ]);

            // Update invoice status to paid
            $this->invoiceModel->updateStatus($payment['invoice_id'], 'paid');

            return [
                'handled' => true,
                'payment_id' => $payment['id'],
                'invoice_id' => $payment['invoice_id'],
                'status' => 'succeeded',
            ];
        }

        if ($paymentStatus === 'unpaid') {
            // Payment failed or pending
            return ['handled' => true, 'reason' => 'Payment unpaid - waiting for additional events'];
        }

        return ['handled' => false, 'reason' => "Unknown payment status: {$paymentStatus}"];
    }

    /**
     * Handle payment_intent.succeeded event
     * This provides more detailed payment information
     */
    protected function handlePaymentIntentSucceeded(object $paymentIntent): array
    {
        $paymentIntentId = $paymentIntent->id;

        log_message('info', "Payment intent succeeded: {$paymentIntentId}");

        // Find our payment record
        $payment = $this->paymentModel->getByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            // Might be from a checkout session - try to find by metadata
            $metadata = (array) ($paymentIntent->metadata ?? []);
            if (isset($metadata['invoice_id'])) {
                $payments = $this->paymentModel->getByInvoiceId($metadata['invoice_id']);
                $payment = !empty($payments) ? $payments[0] : null;
            }
        }

        if (!$payment) {
            log_message('warning', "No payment found for intent: {$paymentIntentId}");
            return ['handled' => false, 'reason' => 'Payment record not found'];
        }

        // Check if already processed
        if ($payment['status'] === 'succeeded') {
            return ['handled' => true, 'reason' => 'Already processed'];
        }

        // Extract payment method details
        $charge = !empty($paymentIntent->charges->data) ? $paymentIntent->charges->data[0] : null;
        $paymentMethodDetails = [];

        if ($charge && isset($charge->payment_method_details)) {
            $details = $charge->payment_method_details;
            if (isset($details->card)) {
                $paymentMethodDetails = [
                    'type' => 'card',
                    'brand' => $details->card->brand ?? null,
                    'last4' => $details->card->last4 ?? null,
                    'exp_month' => $details->card->exp_month ?? null,
                    'exp_year' => $details->card->exp_year ?? null,
                ];
            }
        }

        // Update payment record with detailed info
        $this->paymentModel->recordSuccess($payment['id'], [
            'charge_id' => $charge->id ?? null,
            'payment_method' => $paymentIntent->payment_method ?? null,
            'payment_method_details' => $paymentMethodDetails,
            'metadata' => (array) ($paymentIntent->metadata ?? []),
        ]);

        // Ensure invoice is marked as paid
        $this->invoiceModel->updateStatus($payment['invoice_id'], 'paid');

        return [
            'handled' => true,
            'payment_id' => $payment['id'],
            'invoice_id' => $payment['invoice_id'],
            'status' => 'succeeded',
        ];
    }

    /**
     * Handle payment_intent.payment_failed event
     */
    protected function handlePaymentIntentFailed(object $paymentIntent): array
    {
        $paymentIntentId = $paymentIntent->id;

        log_message('info', "Payment intent failed: {$paymentIntentId}");

        // Find our payment record
        $payment = $this->paymentModel->getByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            // Try checkout session
            $payment = $this->paymentModel->getByCheckoutSessionId($paymentIntentId);
        }

        if (!$payment) {
            log_message('warning', "No payment found for failed intent: {$paymentIntentId}");
            return ['handled' => false, 'reason' => 'Payment record not found'];
        }

        // Check if already processed
        if (in_array($payment['status'], ['failed', 'succeeded'])) {
            return ['handled' => true, 'reason' => 'Already processed'];
        }

        // Get failure details
        $lastError = $paymentIntent->last_payment_error;
        $failureCode = $lastError->code ?? 'unknown';
        $failureMessage = $lastError->message ?? 'Payment failed';

        // Update payment record
        $this->paymentModel->recordFailure($payment['id'], [
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
        ]);

        return [
            'handled' => true,
            'payment_id' => $payment['id'],
            'invoice_id' => $payment['invoice_id'],
            'status' => 'failed',
            'failure_code' => $failureCode,
            'failure_message' => $failureMessage,
        ];
    }

    /**
     * Handle charge.refunded event
     */
    protected function handleChargeRefunded(object $charge): array
    {
        $chargeId = $charge->id;
        $paymentIntentId = $charge->payment_intent;

        log_message('info', "Charge refunded: {$chargeId}");

        // Find our payment record
        $payment = $this->paymentModel->getByChargeId($chargeId);

        if (!$payment && $paymentIntentId) {
            $payment = $this->paymentModel->getByPaymentIntentId($paymentIntentId);
        }

        if (!$payment) {
            log_message('warning', "No payment found for refunded charge: {$chargeId}");
            return ['handled' => false, 'reason' => 'Payment record not found'];
        }

        // Check if already refunded
        if ($payment['status'] === 'refunded') {
            return ['handled' => true, 'reason' => 'Already refunded'];
        }

        // Get refund details
        $refunds = $charge->refunds->data ?? [];
        $latestRefund = !empty($refunds) ? $refunds[0] : null;

        if ($latestRefund) {
            $refundAmount = $latestRefund->amount / 100; // Convert from cents

            $this->paymentModel->recordRefund($payment['id'], [
                'refund_id' => $latestRefund->id,
                'amount' => $refundAmount,
            ]);
        }

        // Update invoice status
        // Only mark as cancelled if fully refunded
        if ($charge->amount_refunded >= $charge->amount) {
            $this->invoiceModel->updateStatus($payment['invoice_id'], 'cancelled');
        }

        return [
            'handled' => true,
            'payment_id' => $payment['id'],
            'invoice_id' => $payment['invoice_id'],
            'status' => 'refunded',
        ];
    }

    /**
     * Log webhook event for debugging
     */
    public function logWebhookEvent(string $eventType, array $eventData, array $result): void
    {
        log_message('info', json_encode([
            'webhook' => 'stripe',
            'event_type' => $eventType,
            'event_data' => $eventData,
            'result' => $result,
            'timestamp' => date('Y-m-d H:i:s'),
        ]));
    }
}
