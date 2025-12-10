<?php

namespace App\Controllers\Payments;

use App\Controllers\BaseController;
use App\Services\StripePaymentService;
use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Payment Controller
 *
 * Handles payment operations including:
 * - Creating Stripe checkout sessions
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
}
