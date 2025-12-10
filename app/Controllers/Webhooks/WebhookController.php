<?php

namespace App\Controllers\Webhooks;

use App\Controllers\BaseController;
use App\Services\StripeWebhookService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Webhook Controller
 *
 * Handles incoming webhook events from external services:
 * - Stripe payment notifications
 *
 * Security Notes:
 * - No authentication filter (webhooks use signature verification)
 * - All webhook payloads are logged for debugging
 * - Signatures are verified before processing
 */
class WebhookController extends BaseController
{
    /**
     * Handle Stripe webhook events
     *
     * POST /webhooks/stripe
     *
     * Stripe sends webhook events for:
     * - checkout.session.completed
     * - payment_intent.succeeded
     * - payment_intent.payment_failed
     * - charge.refunded
     */
    public function stripe(): ResponseInterface
    {
        // Get raw payload and signature
        $payload = $this->request->getBody();
        $sigHeader = $this->request->getHeaderLine('Stripe-Signature');

        // Log incoming webhook (without sensitive data)
        log_message('info', 'Stripe webhook received');

        if (empty($payload)) {
            log_message('error', 'Empty webhook payload received');
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Empty payload']);
        }

        if (empty($sigHeader)) {
            log_message('error', 'Missing Stripe-Signature header');
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Missing signature']);
        }

        try {
            $webhookService = new StripeWebhookService();

            // Verify signature and construct event
            $event = $webhookService->verifyWebhook($payload, $sigHeader);

            if (!$event) {
                log_message('error', 'Webhook signature verification failed');
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => 'Invalid signature']);
            }

            // Handle the event
            $result = $webhookService->handleEvent($event);

            // Log the result
            $webhookService->logWebhookEvent(
                $event->type,
                ['event_id' => $event->id],
                $result
            );

            // Stripe expects 200 OK for successfully received webhooks
            return $this->response
                ->setStatusCode(200)
                ->setJSON([
                    'received' => true,
                    'event_type' => $event->type,
                    'handled' => $result['handled'] ?? false,
                ]);

        } catch (\Exception $e) {
            log_message('error', 'Webhook processing error: ' . $e->getMessage());

            // Return 500 so Stripe will retry
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Processing error']);
        }
    }

    /**
     * Webhook health check endpoint
     *
     * GET /webhooks/health
     *
     * Used to verify webhook endpoint is accessible
     */
    public function health(): ResponseInterface
    {
        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'status' => 'ok',
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoints' => [
                    'stripe' => '/webhooks/stripe',
                ],
            ]);
    }
}
