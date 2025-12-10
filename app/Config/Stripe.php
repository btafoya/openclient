<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Stripe extends BaseConfig
{
    /**
     * Stripe Secret Key
     * Used for server-side API calls
     */
    public string $secretKey;

    /**
     * Stripe Publishable Key
     * Used for client-side integration
     */
    public string $publishableKey;

    /**
     * Stripe Webhook Secret
     * Used to verify webhook signatures
     */
    public string $webhookSecret;

    /**
     * Default Currency
     */
    public string $currency = 'usd';

    /**
     * Stripe API Version (optional)
     * Leave empty to use library default
     */
    public string $apiVersion = '';

    /**
     * Payment success URL path
     */
    public string $successPath = '/invoices/{invoice_id}?payment=success';

    /**
     * Payment cancel URL path
     */
    public string $cancelPath = '/invoices/{invoice_id}?payment=cancelled';

    public function __construct()
    {
        parent::__construct();

        $this->secretKey = getenv('STRIPE_SECRET_KEY') ?: '';
        $this->publishableKey = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
        $this->webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
    }

    /**
     * Check if Stripe is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->secretKey) && !empty($this->publishableKey);
    }
}
