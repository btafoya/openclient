<?php

namespace App\Services;

use CodeIgniter\Email\Email;

/**
 * Invoice Email Service
 *
 * Handles sending invoice emails with PDF attachments.
 *
 * Features:
 * - Professional email template
 * - PDF attachment
 * - Configurable sender and reply-to
 * - Custom message support
 * - Resend capability
 */
class InvoiceEmailService
{
    protected Email $email;

    public function __construct()
    {
        $this->email = \Config\Services::email();

        // Configure email settings
        $this->email->setMailType('html');
    }

    /**
     * Send invoice email with PDF attachment
     *
     * @param array $invoice Invoice data with client info
     * @param string $pdfPath Path to generated PDF file
     * @param string|null $recipientEmail Override recipient email (optional)
     * @param string $customMessage Custom message to include (optional)
     * @param bool $isResend Whether this is a resend (affects subject line)
     * @return bool Success status
     * @throws \Exception On email sending failure
     */
    public function sendInvoice(
        array $invoice,
        string $pdfPath,
        ?string $recipientEmail = null,
        string $customMessage = '',
        bool $isResend = false
    ): bool {
        $client = $invoice['client'] ?? [];

        // Determine recipient
        $recipient = $recipientEmail ?? $client['email'] ?? null;

        if (empty($recipient)) {
            throw new \Exception('No recipient email address available.');
        }

        // Build subject
        $subject = $isResend
            ? "Reminder: Invoice {$invoice['invoice_number']}"
            : "Invoice {$invoice['invoice_number']}";

        // Build email body
        $body = $this->buildEmailBody($invoice, $customMessage, $isResend);

        // Configure email
        $this->email->clear(true);
        $this->email->setFrom(
            getenv('MAIL_FROM_ADDRESS') ?: 'invoices@openclient.app',
            getenv('MAIL_FROM_NAME') ?: 'OpenClient Invoices'
        );
        $this->email->setTo($recipient);
        $this->email->setSubject($subject);
        $this->email->setMessage($body);

        // Attach PDF
        if (file_exists($pdfPath)) {
            $filename = "invoice-{$invoice['invoice_number']}.pdf";
            $this->email->attach($pdfPath, '', $filename, 'application/pdf');
        }

        // Send email
        if (!$this->email->send()) {
            $error = $this->email->printDebugger(['headers', 'subject', 'body']);
            log_message('error', 'Invoice email failed: ' . $error);
            throw new \Exception('Failed to send invoice email. Please check email configuration.');
        }

        return true;
    }

    /**
     * Build email HTML body
     *
     * @param array $invoice Invoice data
     * @param string $customMessage Custom message from user
     * @param bool $isResend Whether this is a reminder
     * @return string HTML email body
     */
    protected function buildEmailBody(array $invoice, string $customMessage = '', bool $isResend = false): string
    {
        $client = $invoice['client'] ?? [];
        $clientName = $client['name'] ?? 'Valued Customer';

        // Format amounts
        $currency = $invoice['currency'] ?? 'USD';
        $currencySymbol = $this->getCurrencySymbol($currency);
        $total = number_format($invoice['total'] ?? 0, 2);

        // Format dates
        $issueDate = date('F j, Y', strtotime($invoice['issue_date']));
        $dueDate = date('F j, Y', strtotime($invoice['due_date']));

        // Greeting based on resend status
        $greeting = $isResend
            ? "This is a friendly reminder regarding your invoice."
            : "Please find your invoice attached.";

        // Custom message section
        $customMessageHtml = '';
        if (!empty($customMessage)) {
            $customMessage = nl2br(htmlspecialchars($customMessage));
            $customMessageHtml = <<<HTML
            <div style="background-color: #f0f9ff; border-left: 4px solid #2563eb; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0; color: #1e40af;">{$customMessage}</p>
            </div>
            HTML;
        }

        // Status-based message
        $statusMessage = '';
        if ($invoice['status'] === 'overdue') {
            $statusMessage = <<<HTML
            <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0; color: #991b1b; font-weight: bold;">
                    ⚠️ This invoice is past due. Please arrange payment at your earliest convenience.
                </p>
            </div>
            HTML;
        }

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invoice {$invoice['invoice_number']}</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; background-color: #f3f4f6;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 style="color: white; margin: 0; font-size: 28px;">OpenClient</h1>
                    <p style="color: #bfdbfe; margin: 5px 0 0 0; font-size: 14px;">Invoice Management</p>
                </div>

                <!-- Main Content -->
                <div style="background-color: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <p style="margin: 0 0 20px 0; color: #374151;">Dear {$clientName},</p>

                    <p style="margin: 0 0 20px 0; color: #374151;">{$greeting}</p>

                    {$customMessageHtml}
                    {$statusMessage}

                    <!-- Invoice Summary Box -->
                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; color: #64748b;">Invoice Number</td>
                                <td style="padding: 8px 0; text-align: right; font-weight: bold; color: #1e293b;">
                                    {$invoice['invoice_number']}
                                </td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #64748b;">Issue Date</td>
                                <td style="padding: 8px 0; text-align: right; color: #1e293b;">{$issueDate}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #64748b;">Due Date</td>
                                <td style="padding: 8px 0; text-align: right; color: #1e293b;">{$dueDate}</td>
                            </tr>
                            <tr style="border-top: 2px solid #e2e8f0;">
                                <td style="padding: 15px 0 8px 0; color: #64748b; font-size: 16px;">Amount Due</td>
                                <td style="padding: 15px 0 8px 0; text-align: right; font-weight: bold; font-size: 24px; color: #2563eb;">
                                    {$currencySymbol}{$total}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p style="margin: 20px 0; color: #374151;">
                        The invoice PDF is attached to this email for your records. Please review the details and process payment by the due date.
                    </p>

                    <!-- Payment Instructions -->
                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; margin: 20px 0;">
                        <p style="margin: 0; color: #166534; font-weight: bold;">💳 Payment Options</p>
                        <p style="margin: 10px 0 0 0; color: #166534;">
                            Please refer to the attached invoice for payment details and instructions.
                        </p>
                    </div>

                    <p style="margin: 20px 0; color: #374151;">
                        If you have any questions about this invoice, please don't hesitate to contact us.
                    </p>

                    <p style="margin: 20px 0 0 0; color: #374151;">
                        Thank you for your business!
                    </p>

                    <p style="margin: 10px 0 0 0; color: #374151;">
                        Best regards,<br>
                        <strong>The OpenClient Team</strong>
                    </p>
                </div>

                <!-- Footer -->
                <div style="text-align: center; padding: 20px; color: #94a3b8; font-size: 12px;">
                    <p style="margin: 0;">
                        This email was sent by OpenClient Invoice System.<br>
                        Please do not reply directly to this email.
                    </p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Send payment reminder email
     *
     * @param array $invoice Invoice data
     * @return bool Success status
     */
    public function sendReminder(array $invoice): bool
    {
        $pdfService = new InvoicePdfService();
        $pdfPath = $pdfService->generate($invoice);

        try {
            $result = $this->sendInvoice(
                $invoice,
                $pdfPath,
                null,
                'This is a friendly reminder that this invoice is approaching or past its due date.',
                true
            );

            // Clean up PDF
            $pdfService->deletePdf($pdfPath);

            return $result;
        } catch (\Exception $e) {
            $pdfService->deletePdf($pdfPath);
            throw $e;
        }
    }

    /**
     * Send payment confirmation email
     *
     * @param array $invoice Invoice data
     * @return bool Success status
     */
    public function sendPaymentConfirmation(array $invoice): bool
    {
        $client = $invoice['client'] ?? [];
        $recipient = $client['email'] ?? null;

        if (empty($recipient)) {
            return false;
        }

        $clientName = $client['name'] ?? 'Valued Customer';
        $currencySymbol = $this->getCurrencySymbol($invoice['currency'] ?? 'USD');
        $total = number_format($invoice['total'] ?? 0, 2);
        $paidDate = date('F j, Y', strtotime($invoice['paid_at'] ?? 'now'));

        $body = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Payment Received</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; background-color: #f3f4f6;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
                    <h1 style="color: white; margin: 0; font-size: 28px;">✓ Payment Received</h1>
                </div>
                <div style="background-color: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
                    <p style="margin: 0 0 20px 0; color: #374151;">Dear {$clientName},</p>
                    <p style="margin: 0 0 20px 0; color: #374151;">
                        Thank you! We have received your payment for invoice <strong>{$invoice['invoice_number']}</strong>.
                    </p>
                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center;">
                        <p style="margin: 0; color: #166534; font-size: 14px;">Amount Paid</p>
                        <p style="margin: 5px 0 0 0; color: #15803d; font-size: 32px; font-weight: bold;">
                            {$currencySymbol}{$total}
                        </p>
                        <p style="margin: 10px 0 0 0; color: #166534; font-size: 12px;">Received on {$paidDate}</p>
                    </div>
                    <p style="margin: 20px 0; color: #374151;">
                        This email serves as your receipt. Thank you for your prompt payment!
                    </p>
                    <p style="margin: 20px 0 0 0; color: #374151;">
                        Best regards,<br>
                        <strong>The OpenClient Team</strong>
                    </p>
                </div>
            </div>
        </body>
        </html>
        HTML;

        $this->email->clear(true);
        $this->email->setFrom(
            getenv('MAIL_FROM_ADDRESS') ?: 'invoices@openclient.app',
            getenv('MAIL_FROM_NAME') ?: 'OpenClient Invoices'
        );
        $this->email->setTo($recipient);
        $this->email->setSubject("Payment Received - Invoice {$invoice['invoice_number']}");
        $this->email->setMessage($body);

        return $this->email->send();
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
}
