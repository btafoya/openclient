<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * PayPal Configuration
 *
 * Stores PayPal API credentials and settings.
 * Values should be set via environment variables.
 */
class PayPal extends BaseConfig
{
    /**
     * PayPal Client ID
     */
    public string $clientId = '';

    /**
     * PayPal Client Secret
     */
    public string $clientSecret = '';

    /**
     * PayPal Mode (sandbox or live)
     */
    public string $mode = 'sandbox';

    /**
     * PayPal API Base URL
     */
    public string $apiBaseUrl = '';

    /**
     * Success redirect path template
     */
    public string $successPath = '/payments/paypal/success/{invoice_id}';

    /**
     * Cancel redirect path template
     */
    public string $cancelPath = '/payments/paypal/cancel/{invoice_id}';

    /**
     * Constructor - load from environment
     */
    public function __construct()
    {
        parent::__construct();

        $this->clientId = env('PAYPAL_CLIENT_ID', '');
        $this->clientSecret = env('PAYPAL_CLIENT_SECRET', '');
        $this->mode = env('PAYPAL_MODE', 'sandbox');

        // Set API base URL based on mode
        $this->apiBaseUrl = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Check if PayPal is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get OAuth token endpoint
     */
    public function getTokenEndpoint(): string
    {
        return $this->apiBaseUrl . '/v1/oauth2/token';
    }

    /**
     * Get orders endpoint
     */
    public function getOrdersEndpoint(): string
    {
        return $this->apiBaseUrl . '/v2/checkout/orders';
    }
}
