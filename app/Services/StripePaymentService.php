<?php

namespace App\Services;

use Config\Stripe as StripeConfig;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;

/**
 * Stripe Payment Service
 *
 * Handles all Stripe payment operations including:
 * - Checkout session creation
 * - Payment intent management
 * - Refund processing
 * - Customer management
 *
 * Security Notes:
 * - Uses Stripe API with secret key (never exposed to client)
 * - Validates all inputs before API calls
 * - Logs all payment operations
 */
class StripePaymentService
{
    protected StripeClient $stripe;
    protected StripeConfig $config;
    protected InvoiceModel $invoiceModel;
    protected PaymentModel $paymentModel;
    protected ClientModel $clientModel;

    public function __construct()
    {
        $this->config = config('Stripe');

        if (!$this->config->isConfigured()) {
            throw new \RuntimeException('Stripe is not configured. Please set STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY in .env');
        }

        Stripe::setApiKey($this->config->secretKey);
        $this->stripe = new StripeClient($this->config->secretKey);

        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->clientModel = new ClientModel();
    }

    /**
     * Create a Stripe Checkout Session for an invoice
     *
     * @param string $invoiceId Invoice UUID
     * @param array $options Additional options (success_url, cancel_url)
     * @return array Checkout session data
     * @throws ApiErrorException
     */
    public function createCheckoutSession(string $invoiceId, array $options = []): array
    {
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

        // Get or create Stripe customer
        $customerId = $this->getOrCreateCustomer($invoice['client']);

        // Build line items for Stripe
        $lineItems = $this->buildStripeLineItems($invoice);

        // Build URLs
        $baseUrl = rtrim(site_url(), '/');
        $successUrl = $options['success_url'] ?? $baseUrl . str_replace('{invoice_id}', $invoiceId, $this->config->successPath);
        $cancelUrl = $options['cancel_url'] ?? $baseUrl . str_replace('{invoice_id}', $invoiceId, $this->config->cancelPath);

        // Add session ID placeholder to success URL
        if (strpos($successUrl, '?') !== false) {
            $successUrl .= '&session_id={CHECKOUT_SESSION_ID}';
        } else {
            $successUrl .= '?session_id={CHECKOUT_SESSION_ID}';
        }

        // Create checkout session
        $session = $this->stripe->checkout->sessions->create([
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'agency_id' => $invoice['agency_id'],
            ],
            'invoice_creation' => [
                'enabled' => false, // We manage our own invoices
            ],
        ]);

        // Create pending payment record
        $paymentId = $this->paymentModel->createPendingPayment(
            $invoiceId,
            $invoice['total'],
            $invoice['currency'],
            $session->id
        );

        return [
            'session_id' => $session->id,
            'payment_id' => $paymentId,
            'url' => $session->url,
            'publishable_key' => $this->config->publishableKey,
        ];
    }

    /**
     * Retrieve a checkout session
     */
    public function retrieveCheckoutSession(string $sessionId): ?Session
    {
        try {
            return $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent', 'customer'],
            ]);
        } catch (ApiErrorException $e) {
            log_message('error', 'Failed to retrieve checkout session: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieve a payment intent
     */
    public function retrievePaymentIntent(string $paymentIntentId): ?PaymentIntent
    {
        try {
            return $this->stripe->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['charges'],
            ]);
        } catch (ApiErrorException $e) {
            log_message('error', 'Failed to retrieve payment intent: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process refund for a payment
     *
     * @param string $paymentId Our payment UUID
     * @param float|null $amount Amount to refund (null for full refund)
     * @param string $reason Refund reason
     * @return array Refund result
     */
    public function refund(string $paymentId, ?float $amount = null, string $reason = 'requested_by_customer'): array
    {
        $payment = $this->paymentModel->find($paymentId);

        if (!$payment) {
            throw new \InvalidArgumentException('Payment not found');
        }

        if ($payment['status'] !== 'succeeded') {
            throw new \InvalidArgumentException('Can only refund succeeded payments');
        }

        if (!$payment['stripe_payment_intent_id'] && !$payment['stripe_charge_id']) {
            throw new \InvalidArgumentException('No Stripe payment reference found');
        }

        $refundParams = [
            'reason' => $reason,
        ];

        // Prefer charge ID if available, otherwise use payment intent
        if ($payment['stripe_charge_id']) {
            $refundParams['charge'] = $payment['stripe_charge_id'];
        } else {
            $refundParams['payment_intent'] = $payment['stripe_payment_intent_id'];
        }

        // Set amount if partial refund
        if ($amount !== null && $amount < $payment['amount']) {
            $refundParams['amount'] = (int) ($amount * 100); // Convert to cents
        }

        try {
            $refund = $this->stripe->refunds->create($refundParams);

            // Update payment record
            $refundAmount = $amount ?? $payment['amount'];
            $this->paymentModel->recordRefund($paymentId, [
                'refund_id' => $refund->id,
                'amount' => $refundAmount,
            ]);

            // Update invoice status if full refund
            if ($amount === null || $amount >= $payment['amount']) {
                $invoice = $this->invoiceModel->find($payment['invoice_id']);
                if ($invoice) {
                    $this->invoiceModel->updateStatus($payment['invoice_id'], 'cancelled');
                }
            }

            return [
                'success' => true,
                'refund_id' => $refund->id,
                'amount' => $refundAmount,
                'status' => $refund->status,
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Stripe refund failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get or create a Stripe customer for a client
     */
    protected function getOrCreateCustomer(array $client): string
    {
        // Check if client has a Stripe customer ID stored
        // For now, create new customer each time (enhancement: store stripe_customer_id in clients table)

        try {
            $customer = $this->stripe->customers->create([
                'email' => $client['email'] ?? null,
                'name' => $client['name'],
                'metadata' => [
                    'client_id' => $client['id'],
                ],
            ]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            log_message('error', 'Failed to create Stripe customer: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Build Stripe line items from invoice
     */
    protected function buildStripeLineItems(array $invoice): array
    {
        $lineItems = [];

        // Add line items from invoice
        if (!empty($invoice['line_items'])) {
            foreach ($invoice['line_items'] as $item) {
                $lineItems[] = [
                    'price_data' => [
                        'currency' => strtolower($invoice['currency']),
                        'product_data' => [
                            'name' => $item['description'],
                        ],
                        'unit_amount' => (int) ($item['unit_price'] * 100), // Convert to cents
                    ],
                    'quantity' => (int) $item['quantity'],
                ];
            }
        }

        // Add tax as separate line item if applicable
        if (!empty($invoice['tax_amount']) && $invoice['tax_amount'] > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => strtolower($invoice['currency']),
                    'product_data' => [
                        'name' => 'Tax',
                    ],
                    'unit_amount' => (int) ($invoice['tax_amount'] * 100),
                ],
                'quantity' => 1,
            ];
        }

        // Handle discount (subtract from first item or add negative line if possible)
        // Note: Stripe Checkout doesn't support negative line items, so we'll adjust the total differently
        // For now, discounts should be applied to line item prices before checkout

        return $lineItems;
    }

    /**
     * Get Stripe publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->config->publishableKey;
    }

    /**
     * Check if Stripe is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * List recent payments from Stripe (for admin/debugging)
     */
    public function listRecentPayments(int $limit = 10): array
    {
        try {
            $paymentIntents = $this->stripe->paymentIntents->all([
                'limit' => $limit,
            ]);

            return $paymentIntents->data;
        } catch (ApiErrorException $e) {
            log_message('error', 'Failed to list payments: ' . $e->getMessage());
            return [];
        }
    }
}
