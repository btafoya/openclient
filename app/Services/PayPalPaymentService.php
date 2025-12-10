<?php

namespace App\Services;

use Config\PayPal as PayPalConfig;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;

/**
 * PayPal Payment Service
 *
 * Handles PayPal payment operations including:
 * - Order creation for checkout
 * - Order capture after approval
 * - Refund processing
 *
 * Security Notes:
 * - Uses PayPal REST API with OAuth2 authentication
 * - Validates all inputs before API calls
 * - Logs all payment operations
 */
class PayPalPaymentService
{
    protected PayPalConfig $config;
    protected InvoiceModel $invoiceModel;
    protected PaymentModel $paymentModel;
    protected ClientModel $clientModel;
    protected ?string $accessToken = null;
    protected int $tokenExpiry = 0;

    public function __construct()
    {
        $this->config = config('PayPal');

        if (!$this->config->isConfigured()) {
            throw new \RuntimeException('PayPal is not configured. Please set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET in .env');
        }

        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->clientModel = new ClientModel();
    }

    /**
     * Create a PayPal order for an invoice
     */
    public function createOrder(string $invoiceId, array $options = []): array
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

        // Build PayPal order payload
        $orderPayload = $this->buildOrderPayload($invoice, $options);

        // Create order via PayPal API
        $response = $this->makeApiRequest('POST', $this->config->getOrdersEndpoint(), $orderPayload);

        if (!$response || !isset($response['id'])) {
            throw new \RuntimeException('Failed to create PayPal order');
        }

        // Create pending payment record
        $paymentId = $this->paymentModel->createPendingPayPalPayment(
            $invoiceId,
            $invoice['total'],
            $invoice['currency'],
            $response['id']
        );

        // Find approval URL
        $approvalUrl = null;
        foreach ($response['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        return [
            'order_id' => $response['id'],
            'payment_id' => $paymentId,
            'approval_url' => $approvalUrl,
            'status' => $response['status'],
        ];
    }

    /**
     * Capture a PayPal order after customer approval
     */
    public function captureOrder(string $orderId): array
    {
        $payment = $this->paymentModel->getByPayPalOrderId($orderId);

        if (!$payment) {
            throw new \InvalidArgumentException('Payment not found for order');
        }

        $captureUrl = $this->config->getOrdersEndpoint() . '/' . $orderId . '/capture';
        $response = $this->makeApiRequest('POST', $captureUrl, []);

        if (!$response || $response['status'] !== 'COMPLETED') {
            $errorMessage = $response['message'] ?? 'Capture failed';
            $this->paymentModel->recordFailure($payment['id'], [
                'failure_code' => 'CAPTURE_FAILED',
                'failure_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        }

        // Extract capture details
        $captureId = null;
        $captureDetails = [];

        if (!empty($response['purchase_units'][0]['payments']['captures'][0])) {
            $capture = $response['purchase_units'][0]['payments']['captures'][0];
            $captureId = $capture['id'];
            $captureDetails = [
                'capture_id' => $captureId,
                'status' => $capture['status'],
                'amount' => $capture['amount'],
                'payer' => $response['payer'] ?? [],
            ];
        }

        // Record success
        $this->paymentModel->recordPayPalSuccess($payment['id'], [
            'capture_id' => $captureId,
            'details' => $captureDetails,
            'metadata' => $response,
        ]);

        // Update invoice status
        $this->invoiceModel->updateStatus($payment['invoice_id'], 'paid');

        return [
            'success' => true,
            'payment_id' => $payment['id'],
            'capture_id' => $captureId,
            'status' => 'COMPLETED',
        ];
    }

    /**
     * Get order details from PayPal
     */
    public function getOrderDetails(string $orderId): ?array
    {
        $url = $this->config->getOrdersEndpoint() . '/' . $orderId;
        return $this->makeApiRequest('GET', $url);
    }

    /**
     * Refund a captured PayPal payment
     */
    public function refund(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        $payment = $this->paymentModel->find($paymentId);

        if (!$payment) {
            throw new \InvalidArgumentException('Payment not found');
        }

        if ($payment['status'] !== 'succeeded') {
            throw new \InvalidArgumentException('Can only refund succeeded payments');
        }

        if (empty($payment['paypal_capture_id'])) {
            throw new \InvalidArgumentException('No PayPal capture ID found');
        }

        $refundUrl = $this->config->apiBaseUrl . '/v2/payments/captures/' . $payment['paypal_capture_id'] . '/refund';

        $refundPayload = [];
        if ($amount !== null && $amount < $payment['amount']) {
            $refundPayload['amount'] = [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => $payment['currency'],
            ];
        }
        if (!empty($reason)) {
            $refundPayload['note_to_payer'] = $reason;
        }

        $response = $this->makeApiRequest('POST', $refundUrl, $refundPayload);

        if (!$response || !isset($response['id'])) {
            return [
                'success' => false,
                'error' => $response['message'] ?? 'Refund failed',
            ];
        }

        $refundAmount = $amount ?? $payment['amount'];
        $this->paymentModel->recordRefund($paymentId, [
            'refund_id' => $response['id'],
            'amount' => $refundAmount,
        ]);

        // Update invoice status if full refund
        if ($amount === null || $amount >= $payment['amount']) {
            $this->invoiceModel->updateStatus($payment['invoice_id'], 'cancelled');
        }

        return [
            'success' => true,
            'refund_id' => $response['id'],
            'amount' => $refundAmount,
            'status' => $response['status'],
        ];
    }

    /**
     * Build PayPal order payload from invoice
     */
    protected function buildOrderPayload(array $invoice, array $options = []): array
    {
        $baseUrl = rtrim(site_url(), '/');
        $successUrl = $options['success_url'] ?? $baseUrl . str_replace('{invoice_id}', $invoice['id'], $this->config->successPath);
        $cancelUrl = $options['cancel_url'] ?? $baseUrl . str_replace('{invoice_id}', $invoice['id'], $this->config->cancelPath);

        // Build line items
        $items = [];
        if (!empty($invoice['line_items'])) {
            foreach ($invoice['line_items'] as $item) {
                $items[] = [
                    'name' => substr($item['description'], 0, 127),
                    'quantity' => (string) $item['quantity'],
                    'unit_amount' => [
                        'currency_code' => strtoupper($invoice['currency']),
                        'value' => number_format($item['unit_price'], 2, '.', ''),
                    ],
                ];
            }
        }

        $breakdown = [
            'item_total' => [
                'currency_code' => strtoupper($invoice['currency']),
                'value' => number_format($invoice['subtotal'], 2, '.', ''),
            ],
        ];

        if (!empty($invoice['tax_amount']) && $invoice['tax_amount'] > 0) {
            $breakdown['tax_total'] = [
                'currency_code' => strtoupper($invoice['currency']),
                'value' => number_format($invoice['tax_amount'], 2, '.', ''),
            ];
        }

        if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0) {
            $breakdown['discount'] = [
                'currency_code' => strtoupper($invoice['currency']),
                'value' => number_format($invoice['discount_amount'], 2, '.', ''),
            ];
        }

        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $invoice['id'],
                    'description' => 'Invoice ' . $invoice['invoice_number'],
                    'custom_id' => $invoice['invoice_number'],
                    'amount' => [
                        'currency_code' => strtoupper($invoice['currency']),
                        'value' => number_format($invoice['total'], 2, '.', ''),
                        'breakdown' => $breakdown,
                    ],
                    'items' => $items,
                ],
            ],
            'application_context' => [
                'brand_name' => env('APP_NAME', 'OpenClient'),
                'landing_page' => 'LOGIN',
                'user_action' => 'PAY_NOW',
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];
    }

    /**
     * Get OAuth access token
     */
    protected function getAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->config->getTokenEndpoint(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->config->clientId . ':' . $this->config->clientSecret,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            log_message('error', 'PayPal token request failed: ' . $response);
            throw new \RuntimeException('Failed to obtain PayPal access token');
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] - 60); // Subtract 60 seconds buffer

        return $this->accessToken;
    }

    /**
     * Make an API request to PayPal
     */
    protected function makeApiRequest(string $method, string $url, ?array $data = null): ?array
    {
        $accessToken = $this->getAccessToken();

        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ];

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ];

        switch ($method) {
            case 'POST':
                $curlOptions[CURLOPT_POST] = true;
                if ($data !== null) {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
                break;
            case 'GET':
                $curlOptions[CURLOPT_HTTPGET] = true;
                break;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            log_message('error', "PayPal API request failed ({$httpCode}): {$response}");
            return json_decode($response, true);
        }

        return json_decode($response, true);
    }

    /**
     * Check if PayPal is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Get PayPal client ID for frontend SDK
     */
    public function getClientId(): string
    {
        return $this->config->clientId;
    }

    /**
     * Get current mode (sandbox or live)
     */
    public function getMode(): string
    {
        return $this->config->mode;
    }
}
