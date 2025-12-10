<?php

namespace App\Services;

use Config\Stripe as StripeConfig;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\PaymentMethod;
use Stripe\Exception\ApiErrorException;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;

/**
 * Stripe ACH Payment Service
 *
 * Handles ACH (bank transfer) payments through Stripe:
 * - Bank account collection via Stripe Elements
 * - Payment Intent creation for ACH debits
 * - Microdeposit verification for bank accounts
 * - Payment processing and status tracking
 *
 * ACH payments typically take 4-5 business days to clear.
 *
 * Security Notes:
 * - Uses Stripe API with secret key (never exposed to client)
 * - Bank account details handled entirely by Stripe
 * - Supports instant verification via Plaid or manual microdeposits
 */
class StripeACHService
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
     * Create a Payment Intent for ACH debit
     *
     * @param string $invoiceId Invoice UUID
     * @param string|null $customerId Stripe customer ID
     * @param array $options Additional options
     * @return array Payment Intent data
     */
    public function createPaymentIntent(string $invoiceId, ?string $customerId = null, array $options = []): array
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
        if (!$customerId) {
            $customerId = $this->getOrCreateCustomer($invoice['client']);
        }

        try {
            // Create Payment Intent for ACH debit
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($invoice['total'] * 100), // Convert to cents
                'currency' => strtolower($invoice['currency']),
                'customer' => $customerId,
                'payment_method_types' => ['us_bank_account'],
                'payment_method_options' => [
                    'us_bank_account' => [
                        'financial_connections' => [
                            'permissions' => ['payment_method'],
                        ],
                        'verification_method' => $options['verification_method'] ?? 'automatic',
                    ],
                ],
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoice['invoice_number'],
                    'agency_id' => $invoice['agency_id'],
                ],
            ]);

            // Create pending payment record
            $paymentId = $this->paymentModel->createPendingACHPayment(
                $invoiceId,
                $invoice['total'],
                $invoice['currency'],
                $paymentIntent->id
            );

            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'payment_id' => $paymentId,
                'publishable_key' => $this->config->publishableKey,
                'customer_id' => $customerId,
                'status' => $paymentIntent->status,
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Stripe ACH payment intent creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Setup Intent for saving bank account without immediate payment
     *
     * @param string $clientId Client UUID
     * @param array $options Additional options
     * @return array Setup Intent data
     */
    public function createSetupIntent(string $clientId, array $options = []): array
    {
        $client = $this->clientModel->find($clientId);

        if (!$client) {
            throw new \InvalidArgumentException('Client not found');
        }

        $customerId = $this->getOrCreateCustomer($client);

        try {
            $setupIntent = $this->stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['us_bank_account'],
                'payment_method_options' => [
                    'us_bank_account' => [
                        'financial_connections' => [
                            'permissions' => ['payment_method'],
                        ],
                        'verification_method' => $options['verification_method'] ?? 'automatic',
                    ],
                ],
                'metadata' => [
                    'client_id' => $clientId,
                ],
            ]);

            return [
                'client_secret' => $setupIntent->client_secret,
                'setup_intent_id' => $setupIntent->id,
                'publishable_key' => $this->config->publishableKey,
                'customer_id' => $customerId,
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Stripe ACH setup intent creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Confirm a Payment Intent with a payment method
     */
    public function confirmPaymentIntent(string $paymentIntentId, string $paymentMethodId): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->confirm($paymentIntentId, [
                'payment_method' => $paymentMethodId,
            ]);

            // Update payment record status
            $payment = $this->paymentModel->getByPaymentIntentId($paymentIntentId);
            if ($payment) {
                $this->paymentModel->updateStatus($payment['id'], 'processing');
            }

            return [
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'next_action' => $paymentIntent->next_action,
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Stripe ACH payment confirmation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Payment Intent status
     */
    public function getPaymentIntentStatus(string $paymentIntentId): ?array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return [
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'payment_method' => $paymentIntent->payment_method,
                'next_action' => $paymentIntent->next_action,
                'last_payment_error' => $paymentIntent->last_payment_error,
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Failed to retrieve payment intent: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify microdeposits for bank account verification
     *
     * @param string $paymentIntentId Payment Intent ID
     * @param array $amounts Two microdeposit amounts in cents [32, 45]
     * @return array Verification result
     */
    public function verifyMicrodeposits(string $paymentIntentId, array $amounts): array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->verifyMicrodeposits($paymentIntentId, [
                'amounts' => $amounts,
            ]);

            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'verified' => $paymentIntent->status !== 'requires_action',
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Microdeposit verification failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * List saved bank accounts for a customer
     */
    public function listBankAccounts(string $customerId): array
    {
        try {
            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'us_bank_account',
            ]);

            return array_map(function ($pm) {
                return [
                    'id' => $pm->id,
                    'bank_name' => $pm->us_bank_account->bank_name,
                    'last4' => $pm->us_bank_account->last4,
                    'account_type' => $pm->us_bank_account->account_type,
                    'account_holder_type' => $pm->us_bank_account->account_holder_type,
                ];
            }, $paymentMethods->data);
        } catch (ApiErrorException $e) {
            log_message('error', 'Failed to list bank accounts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Charge a saved bank account for an invoice
     */
    public function chargeWithSavedBankAccount(string $invoiceId, string $paymentMethodId): array
    {
        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        if (!$invoice) {
            throw new \InvalidArgumentException('Invoice not found');
        }

        $customerId = $this->getOrCreateCustomer($invoice['client']);

        try {
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($invoice['total'] * 100),
                'currency' => strtolower($invoice['currency']),
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'payment_method_types' => ['us_bank_account'],
                'confirm' => true,
                'mandate_data' => [
                    'customer_acceptance' => [
                        'type' => 'online',
                        'online' => [
                            'ip_address' => service('request')->getIPAddress(),
                            'user_agent' => service('request')->getUserAgent()->getAgentString(),
                        ],
                    ],
                ],
                'metadata' => [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoice['invoice_number'],
                    'agency_id' => $invoice['agency_id'],
                ],
            ]);

            // Create payment record
            $paymentId = $this->paymentModel->createPendingACHPayment(
                $invoiceId,
                $invoice['total'],
                $invoice['currency'],
                $paymentIntent->id
            );

            // ACH payments are typically processing initially
            if ($paymentIntent->status === 'processing') {
                $this->paymentModel->updateStatus($paymentId, 'processing');
            }

            return [
                'payment_id' => $paymentId,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'processing_note' => 'ACH payments typically take 4-5 business days to complete.',
            ];
        } catch (ApiErrorException $e) {
            log_message('error', 'Stripe ACH charge failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process ACH payment webhook event
     *
     * @param string $paymentIntentId Payment Intent ID
     * @param string $status New status
     * @param array $eventData Event data from webhook
     * @return bool Success
     */
    public function processWebhookEvent(string $paymentIntentId, string $status, array $eventData = []): bool
    {
        $payment = $this->paymentModel->getByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            log_message('warning', 'Payment not found for ACH webhook: ' . $paymentIntentId);
            return false;
        }

        switch ($status) {
            case 'succeeded':
                $this->paymentModel->recordSuccess($payment['id'], [
                    'charge_id' => $eventData['charges']['data'][0]['id'] ?? null,
                    'payment_method' => 'us_bank_account',
                    'payment_method_details' => $eventData['payment_method_details'] ?? [],
                    'metadata' => $eventData,
                ]);
                $this->invoiceModel->updateStatus($payment['invoice_id'], 'paid');
                break;

            case 'processing':
                $this->paymentModel->updateStatus($payment['id'], 'processing');
                break;

            case 'requires_action':
                // May need microdeposit verification
                $this->paymentModel->update($payment['id'], [
                    'metadata' => json_encode([
                        'requires_action' => true,
                        'next_action' => $eventData['next_action'] ?? null,
                    ]),
                ]);
                break;

            case 'canceled':
            case 'failed':
                $this->paymentModel->recordFailure($payment['id'], [
                    'failure_code' => $eventData['last_payment_error']['code'] ?? 'ACH_FAILED',
                    'failure_message' => $eventData['last_payment_error']['message'] ?? 'ACH payment failed',
                ]);
                break;
        }

        return true;
    }

    /**
     * Get or create a Stripe customer for a client
     */
    protected function getOrCreateCustomer(array $client): string
    {
        // TODO: Store stripe_customer_id in clients table for reuse
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
     * Check if Stripe ACH is available (US only)
     */
    public function isAvailable(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): string
    {
        return $this->config->publishableKey;
    }

    /**
     * Get ACH payment timing information
     */
    public function getTimingInfo(): array
    {
        return [
            'processing_time' => '4-5 business days',
            'verification_options' => [
                'instant' => 'Instant verification via Plaid (recommended)',
                'microdeposits' => 'Two small deposits sent to verify account (1-2 business days)',
            ],
            'failure_window' => 'Up to 5 business days after payment',
            'note' => 'ACH payments may be returned if there are insufficient funds or account issues.',
        ];
    }
}
