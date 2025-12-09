<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Invoice Model
 *
 * Manages invoice data with multi-agency isolation via PostgreSQL RLS.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): InvoiceGuard provides fine-grained authorization
 *
 * Business Logic:
 * - Invoice number auto-generation (INV-YYYY-####)
 * - Status workflow enforcement (draft → sent → paid)
 * - Total calculation from line items
 * - Tax calculation support
 *
 * Security Notes:
 * - All queries automatically filtered by RLS based on session variables
 * - Owner role bypasses RLS and sees all invoices across agencies
 * - Agency users only see invoices belonging to their agency
 */
class InvoiceModel extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false; // UUID primary key
    protected $returnType = 'array';
    protected $useSoftDeletes = false; // Invoices should not be soft deleted for audit trail
    protected $protectFields = true;

    /**
     * Allowed fields for mass assignment
     */
    protected $allowedFields = [
        'invoice_number',
        'client_id',
        'project_id',
        'agency_id',
        'status',
        'subtotal',
        'tax_amount',
        'total',
        'currency',
        'issue_date',
        'due_date',
        'notes',
        'terms',
        'tax_rate',
        'discount_amount',
        'sent_at',
        'viewed_at',
        'paid_at',
        'created_by',
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation rules
    protected $validationRules = [
        'client_id' => 'required|max_length[36]',
        'status' => 'permit_empty|in_list[draft,sent,viewed,paid,overdue,cancelled]',
        'subtotal' => 'permit_empty|decimal',
        'tax_amount' => 'permit_empty|decimal',
        'total' => 'permit_empty|decimal',
        'currency' => 'permit_empty|max_length[3]',
        'issue_date' => 'required|valid_date',
        'due_date' => 'required|valid_date',
    ];

    protected $validationMessages = [
        'client_id' => [
            'required' => 'Client is required for invoice',
        ],
        'issue_date' => [
            'required' => 'Issue date is required',
            'valid_date' => 'Please provide a valid issue date',
        ],
        'due_date' => [
            'required' => 'Due date is required',
            'valid_date' => 'Please provide a valid due date',
        ],
        'status' => [
            'in_list' => 'Invalid invoice status. Must be: draft, sent, viewed, paid, overdue, or cancelled',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'generateInvoiceNumber'];
    protected $beforeUpdate = [];
    protected $afterInsert = ['logInvoiceCreated'];
    protected $afterUpdate = ['logInvoiceUpdated'];

    /**
     * Valid status transitions
     */
    private array $statusTransitions = [
        'draft' => ['sent', 'cancelled'],
        'sent' => ['viewed', 'paid', 'overdue', 'cancelled'],
        'viewed' => ['paid', 'overdue', 'cancelled'],
        'paid' => [], // Terminal state
        'overdue' => ['paid', 'cancelled'],
        'cancelled' => [], // Terminal state
    ];

    /**
     * Generate UUID for new invoice records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Automatically set agency_id from session for new invoices
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
     * Generate unique invoice number in format INV-YYYY-####
     */
    protected function generateInvoiceNumber(array $data): array
    {
        if (!isset($data['data']['invoice_number']) || empty($data['data']['invoice_number'])) {
            $year = date('Y');
            $agencyId = $data['data']['agency_id'] ?? session()->get('user')['agency_id'] ?? null;

            // Get the next invoice number for this agency and year
            $lastInvoice = $this->select('invoice_number')
                ->where('agency_id', $agencyId)
                ->like('invoice_number', "INV-{$year}-", 'after')
                ->orderBy('invoice_number', 'DESC')
                ->first();

            if ($lastInvoice) {
                // Extract the sequence number and increment
                preg_match('/INV-\d{4}-(\d+)/', $lastInvoice['invoice_number'], $matches);
                $nextNumber = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
            } else {
                $nextNumber = 1;
            }

            $data['data']['invoice_number'] = sprintf('INV-%s-%04d', $year, $nextNumber);
        }
        return $data;
    }

    /**
     * Get invoice by ID
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get invoice with related data (client, project, line items)
     */
    public function getWithRelated(string $id): ?array
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            return null;
        }

        // Get client info
        $clientModel = new ClientModel();
        $invoice['client'] = $clientModel->find($invoice['client_id']);

        // Get project info if linked
        if (!empty($invoice['project_id'])) {
            $projectModel = new ProjectModel();
            $invoice['project'] = $projectModel->find($invoice['project_id']);
        }

        // Get line items
        $lineItemModel = new InvoiceLineItemModel();
        $invoice['line_items'] = $lineItemModel->getByInvoiceId($id);

        return $invoice;
    }

    /**
     * Get invoices for current agency
     */
    public function getForCurrentAgency(array $filters = []): array
    {
        $builder = $this->builder();

        // Apply status filter
        if (!empty($filters['status'])) {
            $builder->where('status', $filters['status']);
        }

        // Apply client filter
        if (!empty($filters['client_id'])) {
            $builder->where('client_id', $filters['client_id']);
        }

        // Apply date range filter
        if (!empty($filters['from_date'])) {
            $builder->where('issue_date >=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $builder->where('issue_date <=', $filters['to_date']);
        }

        return $builder->orderBy('issue_date', 'DESC')->get()->getResultArray();
    }

    /**
     * Get invoices by client ID
     */
    public function getByClientId(string $clientId): array
    {
        return $this->where('client_id', $clientId)
            ->orderBy('issue_date', 'DESC')
            ->findAll();
    }

    /**
     * Get invoices by project ID
     */
    public function getByProjectId(string $projectId): array
    {
        return $this->where('project_id', $projectId)
            ->orderBy('issue_date', 'DESC')
            ->findAll();
    }

    /**
     * Get invoices by status
     */
    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }

    /**
     * Get overdue invoices
     */
    public function getOverdue(): array
    {
        return $this->where('status', 'sent')
            ->orWhere('status', 'viewed')
            ->where('due_date <', date('Y-m-d'))
            ->orderBy('due_date', 'ASC')
            ->findAll();
    }

    /**
     * Update invoice status with workflow validation
     */
    public function updateStatus(string $id, string $newStatus): bool
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            return false;
        }

        $currentStatus = $invoice['status'];

        // Check if transition is allowed
        if (!isset($this->statusTransitions[$currentStatus]) ||
            !in_array($newStatus, $this->statusTransitions[$currentStatus])) {
            return false;
        }

        $updateData = ['status' => $newStatus];

        // Set timestamps based on status
        switch ($newStatus) {
            case 'sent':
                $updateData['sent_at'] = date('Y-m-d H:i:s');
                break;
            case 'viewed':
                $updateData['viewed_at'] = date('Y-m-d H:i:s');
                break;
            case 'paid':
                $updateData['paid_at'] = date('Y-m-d H:i:s');
                break;
        }

        return $this->update($id, $updateData);
    }

    /**
     * Check if invoice can be edited (only draft invoices)
     */
    public function canEdit(string $id): bool
    {
        $invoice = $this->find($id);
        return $invoice && $invoice['status'] === 'draft';
    }

    /**
     * Recalculate invoice totals from line items
     */
    public function recalculateTotals(string $id): bool
    {
        $invoice = $this->find($id);
        if (!$invoice) {
            return false;
        }

        // Sum line item amounts
        $lineItemModel = new InvoiceLineItemModel();
        $subtotal = $lineItemModel->getSubtotal($id);

        // Calculate tax if tax_rate is set
        $taxRate = $invoice['tax_rate'] ?? 0;
        $taxAmount = $subtotal * ($taxRate / 100);

        // Calculate discount
        $discountAmount = $invoice['discount_amount'] ?? 0;

        // Calculate total
        $total = $subtotal + $taxAmount - $discountAmount;

        return $this->update($id, [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    /**
     * Get summary statistics for current agency
     */
    public function getSummaryStats(): array
    {
        $stats = [
            'total' => $this->countAllResults(false),
            'draft' => $this->where('status', 'draft')->countAllResults(false),
            'sent' => $this->where('status', 'sent')->countAllResults(false),
            'paid' => $this->where('status', 'paid')->countAllResults(false),
            'overdue' => $this->where('status', 'overdue')->countAllResults(false),
            'total_revenue' => 0,
            'outstanding' => 0,
        ];

        // Calculate total revenue (paid invoices)
        $paidInvoices = $this->select('SUM(total) as total_paid')
            ->where('status', 'paid')
            ->first();
        $stats['total_revenue'] = $paidInvoices['total_paid'] ?? 0;

        // Calculate outstanding (sent + viewed + overdue)
        $outstandingInvoices = $this->select('SUM(total) as total_outstanding')
            ->whereIn('status', ['sent', 'viewed', 'overdue'])
            ->first();
        $stats['outstanding'] = $outstandingInvoices['total_outstanding'] ?? 0;

        return $stats;
    }

    /**
     * Create invoice from project time entries
     */
    public function createFromTimeEntries(string $projectId, array $invoiceData): ?string
    {
        $projectModel = new ProjectModel();
        $project = $projectModel->find($projectId);

        if (!$project) {
            return null;
        }

        // Get billable time entries that haven't been invoiced
        $timeEntryModel = new TimeEntryModel();
        $entries = $timeEntryModel->getUnbilledByProjectId($projectId);

        if (empty($entries)) {
            return null;
        }

        // Set defaults from project
        $invoiceData['client_id'] = $project['client_id'];
        $invoiceData['project_id'] = $projectId;
        $invoiceData['status'] = 'draft';

        // Create invoice
        $invoiceId = $this->insert($invoiceData, true);

        if (!$invoiceId) {
            return null;
        }

        // Create line items from time entries
        $lineItemModel = new InvoiceLineItemModel();
        $sortOrder = 0;

        foreach ($entries as $entry) {
            $lineItemModel->insert([
                'invoice_id' => $invoiceId,
                'description' => $entry['description'] ?? "Time entry for {$project['name']}",
                'quantity' => $entry['hours'],
                'unit_price' => $entry['hourly_rate'] ?? $project['hourly_rate'],
                'amount' => $entry['hours'] * ($entry['hourly_rate'] ?? $project['hourly_rate']),
                'sort_order' => $sortOrder++,
            ]);
        }

        // Recalculate totals
        $this->recalculateTotals($invoiceId);

        return $invoiceId;
    }

    /**
     * Search invoices by number or client
     */
    public function search(string $term): array
    {
        return $this->builder()
            ->select('invoices.*, clients.name as client_name')
            ->join('clients', 'clients.id = invoices.client_id')
            ->groupStart()
                ->like('invoices.invoice_number', $term)
                ->orLike('clients.name', $term)
            ->groupEnd()
            ->orderBy('invoices.issue_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Log invoice creation to timeline
     */
    protected function logInvoiceCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $invoiceNumber = $data['data']['invoice_number'] ?? 'Unknown';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'invoice',
            entityId: $data['id'],
            eventType: 'created',
            description: "Created invoice: {$invoiceNumber}"
        );

        return $data;
    }

    /**
     * Log invoice updates to timeline
     */
    protected function logInvoiceUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $invoiceId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $invoice = $this->find($invoiceId);
        if (!$invoice) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $invoiceNumber = $invoice['invoice_number'];

        // Detect what changed
        $changes = [];
        if (isset($data['data'])) {
            foreach ($data['data'] as $field => $value) {
                if (isset($invoice[$field]) && $invoice[$field] != $value) {
                    $changes[] = $field;
                }
            }
        }

        // Determine event type and description
        if (isset($data['data']['status'])) {
            $description = "Invoice {$invoiceNumber} status changed to {$data['data']['status']}";
            $eventType = 'status_changed';
        } elseif (!empty($changes)) {
            $changedFields = implode(', ', $changes);
            $description = "Updated invoice: {$invoiceNumber} (changed: {$changedFields})";
            $eventType = 'updated';
        } else {
            return $data;
        }

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'invoice',
            entityId: $invoiceId,
            eventType: $eventType,
            description: $description,
            metadata: !empty($changes) ? ['changed_fields' => $changes] : null
        );

        return $data;
    }
}
