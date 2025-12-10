<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Proposal Model
 *
 * Manages sales proposals with e-signature support.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): ProposalGuard provides fine-grained authorization
 *
 * Status Workflow:
 * - draft: Initial creation, can be edited
 * - sent: Sent to client, awaiting response
 * - viewed: Client has viewed the proposal
 * - accepted: Client accepted and signed
 * - rejected: Client rejected
 * - expired: Validity period passed
 */
class ProposalModel extends Model
{
    protected $table = 'proposals';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'client_id',
        'deal_id',
        'template_id',
        'proposal_number',
        'title',
        'introduction',
        'conclusion',
        'terms_conditions',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'valid_until',
        'sent_at',
        'viewed_at',
        'accepted_at',
        'rejected_at',
        'rejection_reason',
        'signature_data',
        'signed_name',
        'signed_email',
        'signed_ip',
        'signed_at',
        'access_token',
        'converted_to_invoice_id',
        'metadata',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'client_id' => 'required|max_length[36]',
        'title' => 'required|max_length[255]',
        'status' => 'permit_empty|in_list[draft,sent,viewed,accepted,rejected,expired]',
    ];

    protected $validationMessages = [
        'client_id' => [
            'required' => 'Client is required for proposal',
        ],
        'title' => [
            'required' => 'Proposal title is required',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'generateProposalNumber', 'generateAccessToken', 'setCreatedBy'];
    protected $afterInsert = ['logProposalCreated'];
    protected $afterUpdate = ['logProposalUpdated'];

    /**
     * Valid status transitions
     */
    private array $statusTransitions = [
        'draft' => ['sent'],
        'sent' => ['viewed', 'accepted', 'rejected', 'expired'],
        'viewed' => ['accepted', 'rejected', 'expired'],
        'accepted' => [], // Terminal state (can convert to invoice)
        'rejected' => ['draft'], // Can revise and resend
        'expired' => ['draft'], // Can revise and resend
    ];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Set agency_id from session
     */
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

    /**
     * Generate unique proposal number (PROP-YYYY-####)
     */
    protected function generateProposalNumber(array $data): array
    {
        if (!isset($data['data']['proposal_number']) || empty($data['data']['proposal_number'])) {
            $year = date('Y');
            $agencyId = $data['data']['agency_id'] ?? session()->get('user')['agency_id'] ?? null;

            $lastProposal = $this->select('proposal_number')
                ->where('agency_id', $agencyId)
                ->like('proposal_number', "PROP-{$year}-", 'after')
                ->orderBy('proposal_number', 'DESC')
                ->first();

            if ($lastProposal) {
                preg_match('/PROP-\d{4}-(\d+)/', $lastProposal['proposal_number'], $matches);
                $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            } else {
                $nextNumber = 1;
            }

            $data['data']['proposal_number'] = sprintf('PROP-%s-%04d', $year, $nextNumber);
        }
        return $data;
    }

    /**
     * Generate secure access token for client viewing
     */
    protected function generateAccessToken(array $data): array
    {
        if (!isset($data['data']['access_token'])) {
            $data['data']['access_token'] = bin2hex(random_bytes(32));
        }
        return $data;
    }

    /**
     * Set created_by from session
     */
    protected function setCreatedBy(array $data): array
    {
        if (!isset($data['data']['created_by'])) {
            $user = session()->get('user');
            if ($user && isset($user['id'])) {
                $data['data']['created_by'] = $user['id'];
            }
        }
        return $data;
    }

    /**
     * Get proposal by access token (for client viewing)
     */
    public function getByAccessToken(string $token): ?array
    {
        return $this->where('access_token', $token)
            ->where('deleted_at', null)
            ->first();
    }

    /**
     * Get proposal with all related data
     */
    public function getWithRelated(string $id): ?array
    {
        $proposal = $this->find($id);
        if (!$proposal) {
            return null;
        }

        // Parse JSONB fields
        if (!empty($proposal['signature_data']) && is_string($proposal['signature_data'])) {
            $proposal['signature_data'] = json_decode($proposal['signature_data'], true);
        }
        if (!empty($proposal['metadata']) && is_string($proposal['metadata'])) {
            $proposal['metadata'] = json_decode($proposal['metadata'], true);
        }

        // Get client
        $clientModel = new ClientModel();
        $proposal['client'] = $clientModel->find($proposal['client_id']);

        // Get sections
        $sectionModel = new ProposalSectionModel();
        $proposal['sections'] = $sectionModel->getByProposalId($id);

        return $proposal;
    }

    /**
     * Update proposal status with workflow validation
     */
    public function updateStatus(string $id, string $newStatus, array $additionalData = []): bool
    {
        $proposal = $this->find($id);
        if (!$proposal) {
            return false;
        }

        $currentStatus = $proposal['status'];

        if (!isset($this->statusTransitions[$currentStatus]) ||
            !in_array($newStatus, $this->statusTransitions[$currentStatus])) {
            return false;
        }

        $updateData = array_merge(['status' => $newStatus], $additionalData);

        switch ($newStatus) {
            case 'sent':
                $updateData['sent_at'] = date('Y-m-d H:i:s');
                break;
            case 'viewed':
                $updateData['viewed_at'] = date('Y-m-d H:i:s');
                break;
            case 'accepted':
                $updateData['accepted_at'] = date('Y-m-d H:i:s');
                break;
            case 'rejected':
                $updateData['rejected_at'] = date('Y-m-d H:i:s');
                break;
        }

        return $this->update($id, $updateData);
    }

    /**
     * Sign proposal with e-signature data
     */
    public function signProposal(string $id, array $signatureData): bool
    {
        $proposal = $this->find($id);
        if (!$proposal || !in_array($proposal['status'], ['sent', 'viewed'])) {
            return false;
        }

        return $this->update($id, [
            'status' => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s'),
            'signature_data' => json_encode($signatureData['signature'] ?? null),
            'signed_name' => $signatureData['name'] ?? null,
            'signed_email' => $signatureData['email'] ?? null,
            'signed_ip' => $signatureData['ip'] ?? null,
            'signed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Convert accepted proposal to invoice
     */
    public function convertToInvoice(string $id): ?string
    {
        $proposal = $this->getWithRelated($id);
        if (!$proposal || $proposal['status'] !== 'accepted') {
            return null;
        }

        $invoiceModel = new InvoiceModel();
        $lineItemModel = new InvoiceLineItemModel();

        // Create invoice
        $invoiceData = [
            'client_id' => $proposal['client_id'],
            'status' => 'draft',
            'subtotal' => $proposal['subtotal'],
            'tax_rate' => $proposal['tax_rate'],
            'tax_amount' => $proposal['tax_amount'],
            'discount_amount' => $proposal['discount_amount'],
            'total' => $proposal['total_amount'],
            'currency' => $proposal['currency'],
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'notes' => "Generated from Proposal: {$proposal['proposal_number']}",
        ];

        $invoiceId = $invoiceModel->insert($invoiceData, true);
        if (!$invoiceId) {
            return null;
        }

        // Create line items from proposal sections
        $sortOrder = 0;
        foreach ($proposal['sections'] as $section) {
            if ($section['is_selected']) {
                $lineItemModel->insert([
                    'invoice_id' => $invoiceId,
                    'description' => $section['title'] . ($section['description'] ? "\n" . $section['description'] : ''),
                    'quantity' => $section['quantity'],
                    'unit_price' => $section['unit_price'],
                    'amount' => $section['total_price'],
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        // Update proposal with invoice reference
        $this->update($id, ['converted_to_invoice_id' => $invoiceId]);

        return $invoiceId;
    }

    /**
     * Get proposals by client
     */
    public function getByClientId(string $clientId): array
    {
        return $this->where('client_id', $clientId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get proposals by status
     */
    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Check and expire overdue proposals
     */
    public function expireOverdueProposals(): int
    {
        $builder = $this->builder();
        return $builder
            ->whereIn('status', ['sent', 'viewed'])
            ->where('valid_until <', date('Y-m-d'))
            ->set('status', 'expired')
            ->update();
    }

    /**
     * Recalculate totals from sections
     */
    public function recalculateTotals(string $id): bool
    {
        $proposal = $this->find($id);
        if (!$proposal) {
            return false;
        }

        $sectionModel = new ProposalSectionModel();
        $sections = $sectionModel->getByProposalId($id, true); // Only selected sections

        $subtotal = array_sum(array_column($sections, 'total_price'));
        $discountAmount = ($subtotal * ($proposal['discount_percent'] ?? 0)) / 100;
        $taxableAmount = $subtotal - $discountAmount;
        $taxAmount = ($taxableAmount * ($proposal['tax_rate'] ?? 0)) / 100;
        $totalAmount = $taxableAmount + $taxAmount;

        return $this->update($id, [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Log proposal creation
     */
    protected function logProposalCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $proposalNumber = $data['data']['proposal_number'] ?? 'Unknown';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'proposal',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created proposal: {$proposalNumber}"
        );

        return $data;
    }

    /**
     * Log proposal updates
     */
    protected function logProposalUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $proposalId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $proposal = $this->find($proposalId);
        if (!$proposal) {
            return $data;
        }

        $timelineModel = new TimelineModel();

        if (isset($data['data']['status'])) {
            $timelineModel->logEvent(
                userId: $user['id'],
                entityType: 'proposal',
                entityId: $proposalId,
                eventType: 'status_changed',
                description: "Proposal {$proposal['proposal_number']} status changed to {$data['data']['status']}"
            );
        }

        return $data;
    }
}
