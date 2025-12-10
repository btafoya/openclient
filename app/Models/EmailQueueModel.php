<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Email Queue Model
 *
 * Manages email queuing and processing.
 */
class EmailQueueModel extends Model
{
    protected $table = 'email_queue';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'to_email',
        'to_name',
        'from_email',
        'from_name',
        'reply_to',
        'cc',
        'bcc',
        'subject',
        'body_html',
        'body_text',
        'template',
        'template_data',
        'attachments',
        'priority',
        'status',
        'attempts',
        'max_attempts',
        'error_message',
        'scheduled_at',
        'sent_at',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'to_email' => 'required|valid_email',
        'subject' => 'required|max_length[500]',
    ];

    protected $skipValidation = false;
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setDefaults'];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    protected function setAgencyId(array $data): array
    {
        if (!isset($data['data']['agency_id'])) {
            $user = session()->get('user');
            if ($user && isset($user['agency_id'])) {
                $data['data']['agency_id'] = $user['agency_id'];
            }
        }
        return $data;
    }

    protected function setDefaults(array $data): array
    {
        if (!isset($data['data']['status'])) {
            $data['data']['status'] = 'pending';
        }
        if (!isset($data['data']['priority'])) {
            $data['data']['priority'] = 'normal';
        }
        if (!isset($data['data']['attempts'])) {
            $data['data']['attempts'] = 0;
        }
        if (!isset($data['data']['max_attempts'])) {
            $data['data']['max_attempts'] = 3;
        }
        return $data;
    }

    /**
     * Queue an email for sending
     */
    public function queueEmail(array $emailData): ?string
    {
        if ($this->insert($emailData)) {
            return $this->getInsertID();
        }
        return null;
    }

    /**
     * Queue email using template
     */
    public function queueFromTemplate(string $template, string $toEmail, ?string $toName, array $templateData, string $priority = 'normal'): ?string
    {
        $data = [
            'to_email' => $toEmail,
            'to_name' => $toName,
            'template' => $template,
            'template_data' => json_encode($templateData),
            'subject' => $this->getTemplateSubject($template, $templateData),
            'priority' => $priority,
        ];

        return $this->queueEmail($data);
    }

    /**
     * Get template subject line
     */
    protected function getTemplateSubject(string $template, array $data): string
    {
        $subjects = [
            'invoice_created' => 'Invoice #{invoice_number} from {company_name}',
            'invoice_reminder' => 'Payment Reminder: Invoice #{invoice_number}',
            'invoice_paid' => 'Payment Received: Invoice #{invoice_number}',
            'proposal_sent' => 'Proposal: {proposal_title}',
            'proposal_accepted' => 'Proposal Accepted: {proposal_title}',
            'ticket_created' => 'Support Ticket #{ticket_number}: {subject}',
            'ticket_reply' => 'Re: Support Ticket #{ticket_number}: {subject}',
            'ticket_resolved' => 'Ticket Resolved: #{ticket_number}',
            'welcome' => 'Welcome to {company_name}',
            'password_reset' => 'Reset Your Password',
        ];

        $subject = $subjects[$template] ?? 'Notification from {company_name}';

        // Replace placeholders
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $subject = str_replace('{' . $key . '}', $value, $subject);
            }
        }

        return $subject;
    }

    /**
     * Get pending emails for processing
     */
    public function getPendingEmails(int $limit = 50): array
    {
        return $this->where('status', 'pending')
            ->where('attempts <', 'max_attempts', false)
            ->groupStart()
                ->where('scheduled_at IS NULL')
                ->orWhere('scheduled_at <=', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('priority', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Mark email as sent
     */
    public function markSent(string $id): bool
    {
        return $this->update($id, [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark email as failed
     */
    public function markFailed(string $id, string $errorMessage): bool
    {
        $email = $this->find($id);
        if (!$email) {
            return false;
        }

        $attempts = $email['attempts'] + 1;
        $status = $attempts >= $email['max_attempts'] ? 'failed' : 'pending';

        return $this->update($id, [
            'status' => $status,
            'attempts' => $attempts,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Retry failed email
     */
    public function retry(string $id): bool
    {
        return $this->update($id, [
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null,
        ]);
    }

    /**
     * Get queue statistics
     */
    public function getStatistics(): array
    {
        return [
            'pending' => $this->where('status', 'pending')->countAllResults(false),
            'sent' => $this->where('status', 'sent')->countAllResults(false),
            'failed' => $this->where('status', 'failed')->countAllResults(false),
            'total' => $this->countAllResults(),
        ];
    }

    /**
     * Get failed emails
     */
    public function getFailed(): array
    {
        return $this->where('status', 'failed')
            ->orderBy('updated_at', 'DESC')
            ->findAll();
    }

    /**
     * Clear old sent emails
     */
    public function clearOldSent(int $daysOld = 30): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        return $this->where('status', 'sent')
            ->where('sent_at <', $date)
            ->delete();
    }
}
