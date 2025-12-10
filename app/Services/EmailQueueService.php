<?php

namespace App\Services;

use App\Models\EmailQueueModel;
use App\Models\ActivityLogModel;
use CodeIgniter\Email\Email;

/**
 * Email Queue Service
 *
 * Handles email queuing, processing, and delivery.
 */
class EmailQueueService
{
    protected EmailQueueModel $queueModel;
    protected ActivityLogModel $activityLog;
    protected Email $email;

    public function __construct()
    {
        $this->queueModel = new EmailQueueModel();
        $this->activityLog = new ActivityLogModel();
        $this->email = service('email');
    }

    /**
     * Queue an email for sending
     */
    public function queue(array $emailData): ?string
    {
        // Set from address if not provided
        if (empty($emailData['from_email'])) {
            $emailData['from_email'] = getenv('email.fromEmail') ?: 'noreply@example.com';
            $emailData['from_name'] = getenv('email.fromName') ?: 'OpenClient';
        }

        return $this->queueModel->queueEmail($emailData);
    }

    /**
     * Queue email using template
     */
    public function queueFromTemplate(
        string $template,
        string $toEmail,
        ?string $toName,
        array $templateData,
        string $priority = 'normal'
    ): ?string {
        return $this->queueModel->queueFromTemplate(
            $template,
            $toEmail,
            $toName,
            $templateData,
            $priority
        );
    }

    /**
     * Process pending emails (run from cron)
     */
    public function processQueue(int $limit = 50): array
    {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $emails = $this->queueModel->getPendingEmails($limit);

        foreach ($emails as $email) {
            $results['processed']++;

            try {
                $sent = $this->sendEmail($email);

                if ($sent) {
                    $this->queueModel->markSent($email['id']);
                    $results['sent']++;
                } else {
                    throw new \Exception('Send failed without exception');
                }

            } catch (\Exception $e) {
                $this->queueModel->markFailed($email['id'], $e->getMessage());
                $results['failed']++;
                $results['errors'][] = [
                    'email_id' => $email['id'],
                    'to' => $email['to_email'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Log processing activity
        if ($results['processed'] > 0) {
            $this->activityLog->log(
                'email_queue',
                null,
                'processed',
                "Email queue processed: {$results['sent']} sent, {$results['failed']} failed",
                $results
            );
        }

        return $results;
    }

    /**
     * Send a single email
     */
    protected function sendEmail(array $emailData): bool
    {
        $this->email->clear();

        // Set from
        $this->email->setFrom($emailData['from_email'], $emailData['from_name'] ?? '');

        // Set to
        $this->email->setTo($emailData['to_email']);

        // Set reply-to
        if (!empty($emailData['reply_to'])) {
            $this->email->setReplyTo($emailData['reply_to']);
        }

        // Set CC
        if (!empty($emailData['cc'])) {
            $ccList = is_string($emailData['cc']) ? json_decode($emailData['cc'], true) : $emailData['cc'];
            if ($ccList) {
                $this->email->setCC($ccList);
            }
        }

        // Set BCC
        if (!empty($emailData['bcc'])) {
            $bccList = is_string($emailData['bcc']) ? json_decode($emailData['bcc'], true) : $emailData['bcc'];
            if ($bccList) {
                $this->email->setBCC($bccList);
            }
        }

        // Set subject
        $this->email->setSubject($emailData['subject']);

        // Set body
        if (!empty($emailData['body_html'])) {
            $this->email->setMailType('html');
            $this->email->setMessage($emailData['body_html']);

            if (!empty($emailData['body_text'])) {
                $this->email->setAltMessage($emailData['body_text']);
            }
        } elseif (!empty($emailData['body_text'])) {
            $this->email->setMailType('text');
            $this->email->setMessage($emailData['body_text']);
        } elseif (!empty($emailData['template'])) {
            // Render template
            $body = $this->renderTemplate($emailData['template'], $emailData['template_data']);
            $this->email->setMailType('html');
            $this->email->setMessage($body);
        }

        // Add attachments
        if (!empty($emailData['attachments'])) {
            $attachments = is_string($emailData['attachments'])
                ? json_decode($emailData['attachments'], true)
                : $emailData['attachments'];

            foreach ($attachments as $attachment) {
                if (is_string($attachment)) {
                    $this->email->attach($attachment);
                } elseif (is_array($attachment)) {
                    $this->email->attach(
                        $attachment['path'] ?? '',
                        $attachment['disposition'] ?? 'attachment',
                        $attachment['newname'] ?? null,
                        $attachment['mime'] ?? ''
                    );
                }
            }
        }

        return $this->email->send();
    }

    /**
     * Render email template
     */
    protected function renderTemplate(string $template, $templateData): string
    {
        $data = is_string($templateData) ? json_decode($templateData, true) : $templateData;
        $data = $data ?? [];

        // Load template file
        $templatePath = APPPATH . 'Views/emails/' . $template . '.php';

        if (!file_exists($templatePath)) {
            // Use default template
            return $this->renderDefaultTemplate($template, $data);
        }

        // Render view
        return view('emails/' . $template, $data);
    }

    /**
     * Render default template for unknown templates
     */
    protected function renderDefaultTemplate(string $template, array $data): string
    {
        $content = $data['content'] ?? $data['message'] ?? '';
        $companyName = $data['company_name'] ?? 'OpenClient';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$companyName}</h1>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>&copy; {$companyName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Queue invoice email
     */
    public function queueInvoiceEmail(array $invoice, string $action = 'created'): ?string
    {
        $template = 'invoice_' . $action;

        return $this->queueFromTemplate(
            $template,
            $invoice['client_email'] ?? $invoice['email'],
            $invoice['client_name'] ?? $invoice['name'],
            [
                'invoice_number' => $invoice['invoice_number'],
                'amount' => $invoice['total'],
                'due_date' => $invoice['due_date'],
                'company_name' => $invoice['agency_name'] ?? 'Our Company',
                'view_url' => site_url('portal/invoices/' . $invoice['id']),
            ],
            $action === 'reminder' ? 'high' : 'normal'
        );
    }

    /**
     * Queue ticket notification
     */
    public function queueTicketNotification(array $ticket, string $action = 'created'): ?string
    {
        $template = 'ticket_' . $action;

        return $this->queueFromTemplate(
            $template,
            $ticket['client_email'] ?? '',
            $ticket['client_name'] ?? null,
            [
                'ticket_number' => $ticket['ticket_number'],
                'subject' => $ticket['subject'],
                'status' => $ticket['status'],
                'view_url' => site_url('portal/tickets/' . $ticket['id']),
            ]
        );
    }

    /**
     * Get queue statistics
     */
    public function getStatistics(): array
    {
        return $this->queueModel->getStatistics();
    }

    /**
     * Retry failed emails
     */
    public function retryFailed(): int
    {
        $failed = $this->queueModel->getFailed();
        $count = 0;

        foreach ($failed as $email) {
            if ($this->queueModel->retry($email['id'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clear old sent emails
     */
    public function clearOldSent(int $daysOld = 30): int
    {
        return $this->queueModel->clearOldSent($daysOld);
    }
}
