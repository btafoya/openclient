<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Proposal PDF Service
 *
 * Generates PDF proposals using DomPDF library.
 *
 * Features:
 * - Professional proposal template
 * - Agency branding support
 * - Section-based content rendering
 * - Line item/pricing table support
 * - Signature block
 * - Terms and conditions
 */
class ProposalPdfService
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
        $this->storagePath = WRITEPATH . 'proposals/';

        // Create storage directory if it doesn't exist
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Generate PDF file and return path
     *
     * @param array $proposal Proposal data with sections, line_items, client, etc.
     * @return string Path to generated PDF file
     */
    public function generate(array $proposal): string
    {
        $html = $this->renderHtml($proposal);

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        $filename = "proposal-{$proposal['proposal_number']}-" . time() . ".pdf";
        $filePath = $this->storagePath . $filename;

        file_put_contents($filePath, $this->dompdf->output());

        return $filePath;
    }

    /**
     * Generate PDF content (for streaming/download)
     *
     * @param array $proposal Proposal data with sections, line_items, client, etc.
     * @return string PDF content
     */
    public function generateContent(array $proposal): string
    {
        $html = $this->renderHtml($proposal);

        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return $this->dompdf->output();
    }

    /**
     * Render proposal HTML template
     *
     * @param array $proposal Proposal data
     * @return string HTML content
     */
    protected function renderHtml(array $proposal): string
    {
        $client = $proposal['client'] ?? [];
        $sections = $proposal['sections'] ?? [];
        $lineItems = $proposal['line_items'] ?? [];
        $project = $proposal['project'] ?? null;

        // Format currency
        $currency = $proposal['currency'] ?? 'USD';
        $currencySymbol = $this->getCurrencySymbol($currency);

        // Format dates
        $createdDate = date('F j, Y', strtotime($proposal['created_at'] ?? 'now'));
        $validUntil = !empty($proposal['valid_until'])
            ? date('F j, Y', strtotime($proposal['valid_until']))
            : null;

        // Build sections HTML
        $sectionsHtml = $this->renderSections($sections);

        // Build line items HTML if present
        $lineItemsHtml = $this->renderLineItems($lineItems, $currencySymbol);

        // Format totals
        $subtotal = number_format($proposal['subtotal'] ?? 0, 2);
        $taxAmount = number_format($proposal['tax_amount'] ?? 0, 2);
        $discountAmount = $proposal['discount_amount'] ?? 0;
        $total = number_format($proposal['total'] ?? 0, 2);

        // Build totals section
        $totalsHtml = $this->renderTotals($proposal, $currencySymbol);

        // Client information
        $clientAddress = $this->formatAddress($client);

        // Valid until notice
        $validityNotice = $validUntil
            ? "<p class='validity-notice'>This proposal is valid until <strong>{$validUntil}</strong></p>"
            : '';

        // Valid until row for details section
        $validUntilRow = $validUntil
            ? "<p><strong>Valid Until:</strong> {$validUntil}</p>"
            : '';

        // Summary paragraph
        $summaryParagraph = !empty($proposal['summary'])
            ? "<p>{$proposal['summary']}</p>"
            : '';

        // Terms section
        $termsHtml = '';
        if (!empty($proposal['terms'])) {
            $termsHtml = "<div class='terms-section'><h2>Terms & Conditions</h2><div class='terms-content'>{$proposal['terms']}</div></div>";
        }

        // Signature block
        $signatureHtml = $this->renderSignatureBlock($proposal);

        // Status badge color
        $statusColor = $this->getStatusColor($proposal['status'] ?? 'draft');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Proposal {$proposal['proposal_number']}</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Helvetica Neue', Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.6;
                    color: #333;
                    padding: 40px;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 40px;
                    border-bottom: 3px solid #2563eb;
                    padding-bottom: 20px;
                }
                .company-info {
                    max-width: 50%;
                }
                .company-info h1 {
                    font-size: 28px;
                    color: #2563eb;
                    margin-bottom: 10px;
                }
                .proposal-info {
                    text-align: right;
                }
                .proposal-info h2 {
                    font-size: 32px;
                    color: #111;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                .proposal-number {
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
                    margin-bottom: 40px;
                    background-color: #f8fafc;
                    padding: 20px;
                    border-radius: 8px;
                }
                .prepared-for, .proposal-dates {
                    width: 48%;
                }
                .prepared-for h3, .proposal-dates h3 {
                    font-size: 12px;
                    text-transform: uppercase;
                    color: #2563eb;
                    margin-bottom: 10px;
                    letter-spacing: 0.5px;
                }
                .prepared-for p {
                    margin-bottom: 3px;
                }
                .client-name {
                    font-weight: bold;
                    font-size: 16px;
                    color: #111;
                }
                .proposal-title {
                    margin-bottom: 30px;
                }
                .proposal-title h1 {
                    font-size: 24px;
                    color: #111;
                    margin-bottom: 10px;
                }
                .proposal-title p {
                    color: #64748b;
                    font-size: 14px;
                }
                .validity-notice {
                    background-color: #fef3c7;
                    border: 1px solid #f59e0b;
                    padding: 10px 15px;
                    border-radius: 4px;
                    margin-bottom: 30px;
                    font-size: 13px;
                }
                .section {
                    margin-bottom: 30px;
                    page-break-inside: avoid;
                }
                .section h2 {
                    font-size: 16px;
                    color: #2563eb;
                    margin-bottom: 15px;
                    padding-bottom: 8px;
                    border-bottom: 1px solid #e2e8f0;
                }
                .section-content {
                    padding-left: 15px;
                }
                .section-content p {
                    margin-bottom: 10px;
                }
                .section-content ul, .section-content ol {
                    margin-left: 20px;
                    margin-bottom: 10px;
                }
                .section-content li {
                    margin-bottom: 5px;
                }
                .pricing-section {
                    margin-top: 40px;
                    margin-bottom: 40px;
                }
                .pricing-section h2 {
                    font-size: 18px;
                    color: #111;
                    margin-bottom: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                th {
                    background-color: #2563eb;
                    color: white;
                    padding: 12px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
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
                    border-top: 2px solid #2563eb;
                    font-weight: bold;
                    font-size: 18px;
                    color: #111;
                    background-color: #f8fafc;
                }
                .terms-section {
                    margin-top: 40px;
                    padding: 20px;
                    background-color: #f8fafc;
                    border-radius: 8px;
                    page-break-inside: avoid;
                }
                .terms-section h2 {
                    font-size: 14px;
                    text-transform: uppercase;
                    color: #64748b;
                    margin-bottom: 15px;
                }
                .terms-content {
                    font-size: 11px;
                    color: #64748b;
                    line-height: 1.8;
                }
                .signature-section {
                    margin-top: 50px;
                    page-break-inside: avoid;
                }
                .signature-section h2 {
                    font-size: 14px;
                    text-transform: uppercase;
                    color: #64748b;
                    margin-bottom: 20px;
                }
                .signature-grid {
                    display: flex;
                    justify-content: space-between;
                }
                .signature-box {
                    width: 45%;
                    padding: 20px;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                }
                .signature-box h4 {
                    font-size: 12px;
                    color: #64748b;
                    margin-bottom: 15px;
                }
                .signature-line {
                    border-bottom: 1px solid #333;
                    margin-bottom: 8px;
                    height: 40px;
                }
                .signature-label {
                    font-size: 10px;
                    color: #94a3b8;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    color: #94a3b8;
                    font-size: 10px;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-info">
                    <h1>OpenClient</h1>
                    <p>Professional Services</p>
                </div>
                <div class="proposal-info">
                    <h2>Proposal</h2>
                    <p class="proposal-number">{$proposal['proposal_number']}</p>
                    <span class="status-badge">{$proposal['status']}</span>
                </div>
            </div>

            <div class="details">
                <div class="prepared-for">
                    <h3>Prepared For</h3>
                    <p class="client-name">{$client['name']}</p>
                    {$clientAddress}
                </div>
                <div class="proposal-dates">
                    <h3>Proposal Details</h3>
                    <p><strong>Date:</strong> {$createdDate}</p>
                    {$validUntilRow}
                    <p><strong>Currency:</strong> {$currency}</p>
                </div>
            </div>

            <div class="proposal-title">
                <h1>{$proposal['title']}</h1>
                {$summaryParagraph}
            </div>

            {$validityNotice}

            {$sectionsHtml}

            {$lineItemsHtml}

            {$totalsHtml}

            {$termsHtml}

            {$signatureHtml}

            <div class="footer">
                <p>Thank you for considering our proposal!</p>
                <p>Generated by OpenClient Proposal System</p>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Render proposal sections
     */
    protected function renderSections(array $sections): string
    {
        if (empty($sections)) {
            return '';
        }

        $html = '';
        foreach ($sections as $section) {
            $title = htmlspecialchars($section['title'] ?? '');
            $content = $section['content'] ?? '';

            // Convert markdown-style lists to HTML if needed
            $content = $this->parseSimpleMarkdown($content);

            $html .= <<<HTML
            <div class="section">
                <h2>{$title}</h2>
                <div class="section-content">{$content}</div>
            </div>
            HTML;
        }

        return $html;
    }

    /**
     * Render line items table
     */
    protected function renderLineItems(array $lineItems, string $currencySymbol): string
    {
        if (empty($lineItems)) {
            return '';
        }

        $rows = '';
        foreach ($lineItems as $item) {
            $description = htmlspecialchars($item['description'] ?? '');
            $quantity = number_format($item['quantity'] ?? 1, 2);
            $unitPrice = number_format($item['unit_price'] ?? 0, 2);
            $amount = number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2);

            $rows .= <<<HTML
            <tr>
                <td class="description">{$description}</td>
                <td class="quantity">{$quantity}</td>
                <td class="price">{$currencySymbol}{$unitPrice}</td>
                <td class="amount">{$currencySymbol}{$amount}</td>
            </tr>
            HTML;
        }

        return <<<HTML
        <div class="pricing-section">
            <h2>Pricing</h2>
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
                    {$rows}
                </tbody>
            </table>
        </div>
        HTML;
    }

    /**
     * Render totals section
     */
    protected function renderTotals(array $proposal, string $currencySymbol): string
    {
        $subtotal = number_format($proposal['subtotal'] ?? 0, 2);
        $total = number_format($proposal['total'] ?? 0, 2);

        $taxRow = '';
        if (($proposal['tax_amount'] ?? 0) > 0) {
            $taxAmount = number_format($proposal['tax_amount'], 2);
            $taxRate = $proposal['tax_rate'] ?? 0;
            $taxLabel = $taxRate > 0 ? "Tax ({$taxRate}%)" : "Tax";
            $taxRow = <<<HTML
            <tr>
                <td colspan="3" class="label">{$taxLabel}</td>
                <td class="amount">{$currencySymbol}{$taxAmount}</td>
            </tr>
            HTML;
        }

        $discountRow = '';
        if (($proposal['discount_amount'] ?? 0) > 0) {
            $discountAmount = number_format($proposal['discount_amount'], 2);
            $discountRow = <<<HTML
            <tr>
                <td colspan="3" class="label">Discount</td>
                <td class="amount">-{$currencySymbol}{$discountAmount}</td>
            </tr>
            HTML;
        }

        return <<<HTML
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
        HTML;
    }

    /**
     * Render signature block
     */
    protected function renderSignatureBlock(array $proposal): string
    {
        $clientName = $proposal['client']['name'] ?? 'Client';

        return <<<HTML
        <div class="signature-section">
            <h2>Acceptance</h2>
            <p style="margin-bottom: 20px; color: #64748b;">
                By signing below, you agree to the terms and pricing outlined in this proposal.
            </p>
            <div class="signature-grid">
                <div class="signature-box">
                    <h4>For OpenClient</h4>
                    <div class="signature-line"></div>
                    <p class="signature-label">Signature</p>
                    <div style="margin-top: 15px;"></div>
                    <div class="signature-line"></div>
                    <p class="signature-label">Date</p>
                </div>
                <div class="signature-box">
                    <h4>For {$clientName}</h4>
                    <div class="signature-line"></div>
                    <p class="signature-label">Signature</p>
                    <div style="margin-top: 15px;"></div>
                    <div class="signature-line"></div>
                    <p class="signature-label">Date</p>
                </div>
            </div>
        </div>
        HTML;
    }

    /**
     * Simple markdown parser for basic formatting
     */
    protected function parseSimpleMarkdown(string $content): string
    {
        // Convert line breaks to paragraphs
        $paragraphs = preg_split('/\n\n+/', trim($content));
        $html = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            // Check for bullet lists
            if (preg_match('/^[\-\*]\s/', $para)) {
                $items = preg_split('/\n[\-\*]\s/', $para);
                $items[0] = preg_replace('/^[\-\*]\s/', '', $items[0]);
                $html .= '<ul>';
                foreach ($items as $item) {
                    $html .= '<li>' . htmlspecialchars(trim($item)) . '</li>';
                }
                $html .= '</ul>';
            }
            // Check for numbered lists
            elseif (preg_match('/^\d+\.\s/', $para)) {
                $items = preg_split('/\n\d+\.\s/', $para);
                $items[0] = preg_replace('/^\d+\.\s/', '', $items[0]);
                $html .= '<ol>';
                foreach ($items as $item) {
                    $html .= '<li>' . htmlspecialchars(trim($item)) . '</li>';
                }
                $html .= '</ol>';
            }
            // Regular paragraph
            else {
                $html .= '<p>' . htmlspecialchars($para) . '</p>';
            }
        }

        return $html;
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
            'accepted' => '#16a34a',
            'declined' => '#dc2626',
            'expired' => '#f59e0b',
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
