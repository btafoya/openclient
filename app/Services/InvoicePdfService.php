<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Invoice PDF Service
 *
 * Generates PDF invoices using DomPDF library.
 *
 * Features:
 * - Professional invoice template
 * - Agency branding support
 * - Line item rendering
 * - Tax and discount display
 * - Configurable paper size and orientation
 */
class InvoicePdfService
{
    protected Dompdf $dompdf;
    protected string $storagePath;

    public function __construct()
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');

        $this->dompdf = new Dompdf($options);
        $this->storagePath = WRITEPATH . 'invoices/';

        // Create storage directory if it doesn't exist
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Generate PDF file and return path
     *
     * @param array $invoice Invoice data with line_items, client, etc.
     * @return string Path to generated PDF file
     */
    public function generate(array $invoice): string
    {
        $html = $this->renderHtml($invoice);

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        $filename = "invoice-{$invoice['invoice_number']}-" . time() . ".pdf";
        $filePath = $this->storagePath . $filename;

        file_put_contents($filePath, $this->dompdf->output());

        return $filePath;
    }

    /**
     * Generate PDF content (for streaming/download)
     *
     * @param array $invoice Invoice data with line_items, client, etc.
     * @return string PDF content
     */
    public function generateContent(array $invoice): string
    {
        $html = $this->renderHtml($invoice);

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return $this->dompdf->output();
    }

    /**
     * Render invoice HTML template
     *
     * @param array $invoice Invoice data
     * @return string HTML content
     */
    protected function renderHtml(array $invoice): string
    {
        $client = $invoice['client'] ?? [];
        $lineItems = $invoice['line_items'] ?? [];
        $project = $invoice['project'] ?? null;

        // Format currency
        $currency = $invoice['currency'] ?? 'USD';
        $currencySymbol = $this->getCurrencySymbol($currency);

        // Format dates
        $issueDate = date('F j, Y', strtotime($invoice['issue_date']));
        $dueDate = date('F j, Y', strtotime($invoice['due_date']));

        // Build line items HTML
        $lineItemsHtml = '';
        foreach ($lineItems as $item) {
            $amount = number_format($item['amount'], 2);
            $unitPrice = number_format($item['unit_price'], 2);
            $quantity = number_format($item['quantity'], 2);

            $lineItemsHtml .= <<<HTML
            <tr>
                <td class="description">{$item['description']}</td>
                <td class="quantity">{$quantity}</td>
                <td class="price">{$currencySymbol}{$unitPrice}</td>
                <td class="amount">{$currencySymbol}{$amount}</td>
            </tr>
            HTML;
        }

        // Format totals
        $subtotal = number_format($invoice['subtotal'] ?? 0, 2);
        $taxAmount = number_format($invoice['tax_amount'] ?? 0, 2);
        $discountAmount = $invoice['discount_amount'] ?? 0;
        $total = number_format($invoice['total'] ?? 0, 2);

        // Tax row (only show if there's tax)
        $taxRow = '';
        if (($invoice['tax_amount'] ?? 0) > 0) {
            $taxRate = $invoice['tax_rate'] ?? 0;
            $taxLabel = $taxRate > 0 ? "Tax ({$taxRate}%)" : "Tax";
            $taxRow = <<<HTML
            <tr>
                <td colspan="3" class="label">{$taxLabel}</td>
                <td class="amount">{$currencySymbol}{$taxAmount}</td>
            </tr>
            HTML;
        }

        // Discount row (only show if there's discount)
        $discountRow = '';
        if ($discountAmount > 0) {
            $discountFormatted = number_format($discountAmount, 2);
            $discountRow = <<<HTML
            <tr>
                <td colspan="3" class="label">Discount</td>
                <td class="amount">-{$currencySymbol}{$discountFormatted}</td>
            </tr>
            HTML;
        }

        // Client address
        $clientAddress = $this->formatAddress($client);

        // Project info
        $projectInfo = '';
        if ($project) {
            $projectInfo = "<p><strong>Project:</strong> {$project['name']}</p>";
        }

        // Notes and Terms
        $notesHtml = '';
        if (!empty($invoice['notes'])) {
            $notesHtml = "<div class='notes'><h3>Notes</h3><p>{$invoice['notes']}</p></div>";
        }

        $termsHtml = '';
        if (!empty($invoice['terms'])) {
            $termsHtml = "<div class='terms'><h3>Terms & Conditions</h3><p>{$invoice['terms']}</p></div>";
        }

        // Status badge color
        $statusColor = $this->getStatusColor($invoice['status'] ?? 'draft');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice {$invoice['invoice_number']}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Helvetica Neue', Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                    color: #333;
                    padding: 40px;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 40px;
                    border-bottom: 2px solid #2563eb;
                    padding-bottom: 20px;
                }
                .company-info {
                    max-width: 50%;
                }
                .company-info h1 {
                    font-size: 24px;
                    color: #2563eb;
                    margin-bottom: 10px;
                }
                .invoice-info {
                    text-align: right;
                }
                .invoice-info h2 {
                    font-size: 28px;
                    color: #111;
                    margin-bottom: 10px;
                }
                .invoice-number {
                    font-size: 14px;
                    color: #666;
                    margin-bottom: 5px;
                }
                .status-badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 4px;
                    font-weight: bold;
                    font-size: 11px;
                    text-transform: uppercase;
                    color: white;
                    background-color: {$statusColor};
                }
                .details {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                }
                .bill-to, .invoice-dates {
                    width: 48%;
                }
                .bill-to h3, .invoice-dates h3 {
                    font-size: 12px;
                    text-transform: uppercase;
                    color: #666;
                    margin-bottom: 10px;
                    letter-spacing: 0.5px;
                }
                .bill-to p {
                    margin-bottom: 3px;
                }
                .client-name {
                    font-weight: bold;
                    font-size: 14px;
                    color: #111;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                th {
                    background-color: #f8fafc;
                    padding: 12px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
                    color: #64748b;
                    border-bottom: 2px solid #e2e8f0;
                }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #e2e8f0;
                }
                td.description {
                    width: 50%;
                }
                td.quantity, td.price, td.amount {
                    text-align: right;
                }
                th.quantity, th.price, th.amount {
                    text-align: right;
                }
                .totals-table {
                    width: 300px;
                    margin-left: auto;
                }
                .totals-table td {
                    padding: 8px 12px;
                }
                .totals-table td.label {
                    text-align: right;
                    color: #64748b;
                }
                .totals-table tr.total-row td {
                    border-top: 2px solid #e2e8f0;
                    font-weight: bold;
                    font-size: 16px;
                    color: #111;
                }
                .notes, .terms {
                    margin-top: 30px;
                    padding: 15px;
                    background-color: #f8fafc;
                    border-radius: 4px;
                }
                .notes h3, .terms h3 {
                    font-size: 12px;
                    text-transform: uppercase;
                    color: #64748b;
                    margin-bottom: 8px;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    color: #94a3b8;
                    font-size: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-info">
                    <h1>OpenClient</h1>
                    <p>Invoice Management System</p>
                </div>
                <div class="invoice-info">
                    <h2>INVOICE</h2>
                    <p class="invoice-number">{$invoice['invoice_number']}</p>
                    <span class="status-badge">{$invoice['status']}</span>
                </div>
            </div>

            <div class="details">
                <div class="bill-to">
                    <h3>Bill To</h3>
                    <p class="client-name">{$client['name']}</p>
                    {$clientAddress}
                    {$projectInfo}
                </div>
                <div class="invoice-dates">
                    <h3>Invoice Details</h3>
                    <p><strong>Issue Date:</strong> {$issueDate}</p>
                    <p><strong>Due Date:</strong> {$dueDate}</p>
                    <p><strong>Currency:</strong> {$currency}</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="quantity">Qty</th>
                        <th class="price">Unit Price</th>
                        <th class="amount">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    {$lineItemsHtml}
                </tbody>
            </table>

            <table class="totals-table">
                <tbody>
                    <tr>
                        <td colspan="3" class="label">Subtotal</td>
                        <td class="amount">{$currencySymbol}{$subtotal}</td>
                    </tr>
                    {$taxRow}
                    {$discountRow}
                    <tr class="total-row">
                        <td colspan="3" class="label">Total</td>
                        <td class="amount">{$currencySymbol}{$total}</td>
                    </tr>
                </tbody>
            </table>

            {$notesHtml}
            {$termsHtml}

            <div class="footer">
                <p>Thank you for your business!</p>
                <p>Generated by OpenClient Invoice System</p>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Get currency symbol
     */
    protected function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'JPY' => '¥',
            'CNY' => '¥',
            'INR' => '₹',
            'MXN' => '$',
            'BRL' => 'R$',
        ];

        return $symbols[$currency] ?? $currency . ' ';
    }

    /**
     * Get status badge color
     */
    protected function getStatusColor(string $status): string
    {
        $colors = [
            'draft' => '#64748b',
            'sent' => '#2563eb',
            'viewed' => '#8b5cf6',
            'paid' => '#16a34a',
            'overdue' => '#dc2626',
            'cancelled' => '#6b7280',
        ];

        return $colors[$status] ?? '#64748b';
    }

    /**
     * Format client address for display
     */
    protected function formatAddress(array $client): string
    {
        $parts = [];

        if (!empty($client['address'])) {
            $parts[] = "<p>{$client['address']}</p>";
        }

        $cityStateZip = [];
        if (!empty($client['city'])) {
            $cityStateZip[] = $client['city'];
        }
        if (!empty($client['state'])) {
            $cityStateZip[] = $client['state'];
        }
        if (!empty($client['postal_code'])) {
            $cityStateZip[] = $client['postal_code'];
        }

        if (!empty($cityStateZip)) {
            $parts[] = '<p>' . implode(', ', $cityStateZip) . '</p>';
        }

        if (!empty($client['country'])) {
            $parts[] = "<p>{$client['country']}</p>";
        }

        if (!empty($client['email'])) {
            $parts[] = "<p>{$client['email']}</p>";
        }

        return implode('', $parts);
    }

    /**
     * Delete generated PDF file
     */
    public function deletePdf(string $filePath): bool
    {
        if (file_exists($filePath) && strpos($filePath, $this->storagePath) === 0) {
            return unlink($filePath);
        }
        return false;
    }

    /**
     * Clean up old PDF files (older than days)
     */
    public function cleanupOldPdfs(int $days = 30): int
    {
        $count = 0;
        $cutoff = time() - ($days * 24 * 60 * 60);

        $files = glob($this->storagePath . '*.pdf');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
