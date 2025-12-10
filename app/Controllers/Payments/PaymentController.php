<?php

namespace App\Controllers\Payments;

use App\Controllers\BaseController;
use App\Services\StripePaymentService;
use App\Services\PayPalPaymentService;
use App\Services\ManualPaymentService;
use App\Services\StripeACHService;
use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Payment Controller
 *
 * Handles payment operations including:
 * - Creating Stripe checkout sessions
 * - PayPal order creation and capture
 * - Stripe ACH bank transfers
 * - Manual payments (Zelle, check, wire)
 * - Processing payment callbacks
 * - Viewing payment history
 * - Processing refunds
 *
 * All endpoints require authentication and return JSON responses.
 */
class PaymentController extends BaseController
{
    protected PaymentModel $paymentModel;
    protected InvoiceModel $invoiceModel;
    protected ?StripePaymentService $stripeService = null;

    public function __construct()
    {
        $this->paymentModel = new PaymentModel();
        $this->invoiceModel = new InvoiceModel();
    }

    /**
     * Get Stripe service instance (lazy loaded)
     */
    protected function getStripeService(): StripePaymentService
    {
        if ($this->stripeService === null) {
            $this->stripeService = new StripePaymentService();
        }
        return $this->stripeService;
    }

    /**
     * List payments for current agency
     *
     * GET /api/payments
     */
    public function index(): ResponseInterface
    {
        $filters = [
            'status' => $this->request->getGet('status'),
            'from_date' => $this->request->getGet('from_date'),
            'to_date' => $this->request->getGet('to_date'),
        ];

        $payments = $this->paymentModel->getForCurrentAgency(array_filter($filters));

        // Enrich with invoice data
        foreach ($payments as &$payment) {
            $invoice = $this->invoiceModel->find($payment['invoice_id']);
            if ($invoice) {
                $payment['invoice_number'] = $invoice['invoice_number'];
                $payment['client_id'] = $invoice['client_id'];
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Get payment statistics
     *
     * GET /api/payments/stats
     */
    public function stats(): ResponseInterface
    {
        $stats = $this->paymentModel->getSummaryStats();

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Show single payment
     *
     * GET /api/payments/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $payment = $this->paymentModel->find($id);

        if (!$payment) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Payment not found',
            ])->setStatusCode(404);
        }

        // Get related invoice
        $invoice = $this->invoiceModel->getWithRelated($payment['invoice_id']);
        $payment['invoice'] = $invoice;

        return $this->response->setJSON([
            'success' => true,
            'data' => $payment,
        ]);
    }

    /**
     * Get payments for a specific invoice
     *
     * GET /api/invoices/{invoice_id}/payments
     */
    public function getByInvoice(string $invoiceId): ResponseInterface
    {
        $invoice = $this->invoiceModel->find($invoiceId);

        if (!$invoice) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Invoice not found',
            ])->setStatusCode(404);
        }

        $payments = $this->paymentModel->getByInvoiceId($invoiceId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Create a Stripe checkout session for an invoice
     *
     * POST /api/payments/checkout
     * Body: { "invoice_id": "uuid" }
     */
    public function createCheckout(): ResponseInterface
    {
        $invoiceId = $this->request->getJSON(true)['invoice_id'] ?? null;

        if (!$invoiceId) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Invoice ID is required',
            ])->setStatusCode(400);
        }

        // Verify invoice exists and is payable
        $invoice = $this->invoiceModel->find($invoiceId);

        if (!$invoice) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Invoice not found',
            ])->setStatusCode(404);
        }

        if ($invoice['status'] === 'paid') {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Invoice is already paid',
            ])->setStatusCode(400);
        }

        if (in_array($invoice['status'], ['cancelled', 'draft'])) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'This invoice cannot be paid',
            ])->setStatusCode(400);
        }

        try {
            $stripeService = $this->getStripeService();
            $result = $stripeService->createCheckoutSession($invoiceId);

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Checkout session creation failed: ' . $e->getMessage());

            return $this->response->setJSON([
                'success' => false,
                'error' => 'Failed to create checkout session: ' . $e->getMessage(),
            ])->setStatusCode(500);
        }
    }

    /**
     * Handle successful payment redirect (callback)
     *
     * GET /api/payments/success
     */
    public function success(): ResponseInterface
    {
        $sessionId = $this->request->getGet('session_id');

        if (!$sessionId) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Session ID is required',
            ])->setStatusCode(400);
        }

        $payment = $this->paymentModel->getByCheckoutSessionId($sessionId);

        if (!$payment) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Payment not found',
            ])->setStatusCode(404);
        }

        // The actual payment status update happens via webhook
        // This endpoint is for verifying the session and returning status to frontend

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'payment_id' => $payment['id'],
                'invoice_id' => $payment['invoice_id'],
                'status' => $payment['status'],
            ],
        ]);
    }

    /**
     * Handle cancelled payment redirect
     *
     * GET /api/payments/cancel
     */
    public function cancel(): ResponseInterface
    {
        $sessionId = $this->request->getGet('session_id');

        if ($sessionId) {
            $payment = $this->paymentModel->getByCheckoutSessionId($sessionId);

            if ($payment && $payment['status'] === 'pending') {
                $this->paymentModel->update($payment['id'], [
                    'status' => 'cancelled',
                ]);
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Payment cancelled',
        ]);
    }

    /**
     * Process refund for a payment
     *
     * POST /api/payments/{id}/refund
     * Body: { "amount": 50.00, "reason": "requested_by_customer" }
     */
    public function refund(string $id): ResponseInterface
    {
        $payment = $this->paymentModel->find($id);

        if (!$payment) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Payment not found',
            ])->setStatusCode(404);
        }

        if ($payment['status'] !== 'succeeded') {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Only succeeded payments can be refunded',
            ])->setStatusCode(400);
        }

        $body = $this->request->getJSON(true) ?? [];
        $amount = $body['amount'] ?? null;
        $reason = $body['reason'] ?? 'requested_by_customer';

        try {
            $stripeService = $this->getStripeService();
            $result = $stripeService->refund($id, $amount, $reason);

            if ($result['success']) {
                return $this->response->setJSON([
                    'success' => true,
                    'data' => $result,
                ]);
            }

            return $this->response->setJSON([
                'success' => false,
                'error' => $result['error'],
            ])->setStatusCode(400);
        } catch (\Exception $e) {
            log_message('error', 'Refund failed: ' . $e->getMessage());

            return $this->response->setJSON([
                'success' => false,
                'error' => 'Failed to process refund: ' . $e->getMessage(),
            ])->setStatusCode(500);
        }
    }

    /**
     * Get Stripe configuration for frontend
     *
     * GET /api/payments/config
     */
    public function config(): ResponseInterface
    {
        try {
            $stripeService = $this->getStripeService();

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'publishable_key' => $stripeService->getPublishableKey(),
                    'configured' => $stripeService->isConfigured(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'publishable_key' => null,
                    'configured' => false,
                ],
            ]);
        }
    }

    /**
     * Get available payment methods for an invoice
     *
     * GET /api/invoices/{id}/payment-methods
     */
    public function availableMethods(string $invoiceId): ResponseInterface
    {
        $invoice = $this->invoiceModel->find($invoiceId);

        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        $methods = [];

        // Stripe (card)
        try {
            $stripeService = new StripePaymentService();
            if ($stripeService->isConfigured()) {
                $methods['stripe'] = [
                    'available' => true,
                    'name' => 'Credit/Debit Card',
                    'description' => 'Pay securely with your card via Stripe',
                    'publishable_key' => $stripeService->getPublishableKey(),
                ];
            }
        } catch (\Exception $e) {
            $methods['stripe'] = ['available' => false, 'reason' => 'Not configured'];
        }

        // PayPal
        try {
            $paypalService = new PayPalPaymentService();
            if ($paypalService->isConfigured()) {
                $methods['paypal'] = [
                    'available' => true,
                    'name' => 'PayPal',
                    'description' => 'Pay with your PayPal account',
                    'client_id' => $paypalService->getClientId(),
                    'mode' => $paypalService->getMode(),
                ];
            }
        } catch (\Exception $e) {
            $methods['paypal'] = ['available' => false, 'reason' => 'Not configured'];
        }

        // Stripe ACH
        try {
            $achService = new StripeACHService();
            if ($achService->isAvailable()) {
                $methods['stripe_ach'] = [
                    'available' => true,
                    'name' => 'Bank Transfer (ACH)',
                    'description' => 'Pay directly from your US bank account',
                    'publishable_key' => $achService->getPublishableKey(),
                    'timing' => $achService->getTimingInfo(),
                ];
            }
        } catch (\Exception $e) {
            $methods['stripe_ach'] = ['available' => false, 'reason' => 'Not configured'];
        }

        // Manual payment methods
        $manualService = new ManualPaymentService();
        $manualMethods = $manualService->getPaymentMethods();

        foreach ($manualMethods as $key => $method) {
            $methods[$key] = [
                'available' => true,
                'name' => $method['name'],
                'description' => $method['description'],
                'requires_reference' => $method['requires_reference'],
                'reference_label' => $method['reference_label'],
                'type' => 'manual',
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $methods,
        ]);
    }

    /**
     * Create a PayPal order
     *
     * POST /api/payments/paypal/create-order
     */
    public function paypalCreateOrder(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['invoice_id'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Invoice ID is required']);
        }

        try {
            $service = new PayPalPaymentService();
            $result = $service->createOrder($data['invoice_id'], $data);

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Capture a PayPal order
     *
     * POST /api/payments/paypal/capture
     */
    public function paypalCapture(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['order_id'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Order ID is required']);
        }

        try {
            $service = new PayPalPaymentService();
            $result = $service->captureOrder($data['order_id']);

            return $this->response->setJSON([
                'success' => $result['success'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Create a Stripe ACH Payment Intent
     *
     * POST /api/payments/ach/create-intent
     */
    public function achCreateIntent(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['invoice_id'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Invoice ID is required']);
        }

        try {
            $service = new StripeACHService();
            $result = $service->createPaymentIntent($data['invoice_id'], $data['customer_id'] ?? null, $data);

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify ACH microdeposits
     *
     * POST /api/payments/ach/verify-microdeposits
     */
    public function achVerifyMicrodeposits(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['payment_intent_id']) || empty($data['amounts'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Payment Intent ID and amounts are required']);
        }

        try {
            $service = new StripeACHService();
            $result = $service->verifyMicrodeposits($data['payment_intent_id'], $data['amounts']);

            return $this->response->setJSON([
                'success' => $result['success'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Record a manual payment (Zelle, check, etc.)
     *
     * POST /api/payments/manual/record
     */
    public function manualRecord(): ResponseInterface
    {
        $data = $this->request->getJSON(true);

        if (empty($data['invoice_id']) || empty($data['payment_method'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Invoice ID and payment method are required']);
        }

        try {
            $service = new ManualPaymentService();
            $result = $service->recordPayment(
                $data['invoice_id'],
                $data['payment_method'],
                $data['amount'] ?? null,
                $data['reference'] ?? null,
                $data['metadata'] ?? []
            );

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify a manual payment
     *
     * POST /api/payments/manual/{id}/verify
     */
    public function manualVerify(string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to verify payments.']);
        }

        $data = $this->request->getJSON(true);

        try {
            $service = new ManualPaymentService();
            $result = $service->verifyPayment(
                $id,
                $user['id'],
                $data['reference'] ?? null,
                $data['notes'] ?? null
            );

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject a manual payment
     *
     * POST /api/payments/manual/{id}/reject
     */
    public function manualReject(string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to reject payments.']);
        }

        $data = $this->request->getJSON(true);

        if (empty($data['reason'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Rejection reason is required']);
        }

        try {
            $service = new ManualPaymentService();
            $result = $service->rejectPayment($id, $user['id'], $data['reason']);

            return $this->response->setJSON([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get pending manual payments awaiting verification
     *
     * GET /api/payments/manual/pending
     */
    public function pendingManualPayments(): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view pending payments.']);
        }

        $service = new ManualPaymentService();
        $payments = $service->getPendingPayments();

        return $this->response->setJSON([
            'success' => true,
            'data' => $payments,
        ]);
    }

    /**
     * Get payment instructions for a specific method
     *
     * GET /api/invoices/{id}/payment-instructions/{method}
     */
    public function paymentInstructions(string $invoiceId, string $method): ResponseInterface
    {
        $service = new ManualPaymentService();

        try {
            switch ($method) {
                case 'zelle':
                    $instructions = $service->getZelleInstructions($invoiceId);
                    break;
                case 'check':
                    $instructions = $service->getCheckInstructions($invoiceId);
                    break;
                case 'wire':
                    $instructions = $service->getWireInstructions($invoiceId);
                    break;
                default:
                    return $this->response
                        ->setStatusCode(400)
                        ->setJSON(['error' => 'Unknown payment method']);
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => $instructions,
            ]);
        } catch (\Exception $e) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => $e->getMessage()]);
        }
    }
}
