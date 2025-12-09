<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Invoice Line Item Model
 *
 * Manages individual line items on invoices.
 *
 * Features:
 * - Automatic amount calculation (quantity * unit_price)
 * - Sort order management for drag-drop reordering
 * - Parent invoice total recalculation triggers
 *
 * Note: Line items inherit authorization from parent invoice.
 * If user can edit invoice, they can edit its line items.
 */
class InvoiceLineItemModel extends Model
{
    protected $table = 'invoice_line_items';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false; // UUID primary key
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    /**
     * Allowed fields for mass assignment
     */
    protected $allowedFields = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    // Timestamp configuration
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation rules
    protected $validationRules = [
        'invoice_id' => 'required|max_length[36]',
        'description' => 'required|min_length[1]',
        'quantity' => 'required|decimal|greater_than[0]',
        'unit_price' => 'required|decimal',
    ];

    protected $validationMessages = [
        'invoice_id' => [
            'required' => 'Invoice ID is required',
        ],
        'description' => [
            'required' => 'Line item description is required',
        ],
        'quantity' => [
            'required' => 'Quantity is required',
            'greater_than' => 'Quantity must be greater than 0',
        ],
        'unit_price' => [
            'required' => 'Unit price is required',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'calculateAmount', 'setDefaultSortOrder'];
    protected $beforeUpdate = ['calculateAmount'];
    protected $afterInsert = ['recalculateInvoiceTotals'];
    protected $afterUpdate = ['recalculateInvoiceTotals'];
    protected $afterDelete = ['recalculateInvoiceTotals'];

    /**
     * Generate UUID for new line item records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Calculate amount from quantity and unit_price
     */
    protected function calculateAmount(array $data): array
    {
        if (isset($data['data']['quantity']) && isset($data['data']['unit_price'])) {
            $data['data']['amount'] = $data['data']['quantity'] * $data['data']['unit_price'];
        }
        return $data;
    }

    /**
     * Set default sort order for new line items
     */
    protected function setDefaultSortOrder(array $data): array
    {
        if (!isset($data['data']['sort_order']) && isset($data['data']['invoice_id'])) {
            // Get the next sort order for this invoice
            $maxOrder = $this->selectMax('sort_order')
                ->where('invoice_id', $data['data']['invoice_id'])
                ->first();

            $data['data']['sort_order'] = ($maxOrder['sort_order'] ?? -1) + 1;
        }
        return $data;
    }

    /**
     * Recalculate parent invoice totals after line item changes
     */
    protected function recalculateInvoiceTotals(array $data): array
    {
        // Get invoice_id from the operation
        $invoiceId = null;

        if (isset($data['data']['invoice_id'])) {
            $invoiceId = $data['data']['invoice_id'];
        } elseif (isset($data['id'])) {
            // For delete operations, we need to get the invoice_id from the record
            // But after delete, the record is gone. We need to handle this differently.
            // This is set in deleteByInvoiceId and other methods
            return $data;
        }

        if ($invoiceId) {
            $invoiceModel = new InvoiceModel();
            $invoiceModel->recalculateTotals($invoiceId);
        }

        return $data;
    }

    /**
     * Get line items for an invoice
     */
    public function getByInvoiceId(string $invoiceId): array
    {
        return $this->where('invoice_id', $invoiceId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

    /**
     * Get subtotal for an invoice (sum of all line item amounts)
     */
    public function getSubtotal(string $invoiceId): float
    {
        $result = $this->selectSum('amount')
            ->where('invoice_id', $invoiceId)
            ->first();

        return (float) ($result['amount'] ?? 0);
    }

    /**
     * Get line item count for an invoice
     */
    public function getCount(string $invoiceId): int
    {
        return $this->where('invoice_id', $invoiceId)->countAllResults();
    }

    /**
     * Delete all line items for an invoice
     */
    public function deleteByInvoiceId(string $invoiceId): bool
    {
        return $this->where('invoice_id', $invoiceId)->delete();
    }

    /**
     * Reorder line items (bulk update sort_order)
     */
    public function reorder(array $itemIds): bool
    {
        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($itemIds as $index => $itemId) {
            $this->update($itemId, ['sort_order' => $index]);
        }

        $db->transComplete();
        return $db->transStatus();
    }

    /**
     * Duplicate line items from one invoice to another
     */
    public function duplicateForInvoice(string $sourceInvoiceId, string $targetInvoiceId): bool
    {
        $sourceItems = $this->getByInvoiceId($sourceInvoiceId);

        foreach ($sourceItems as $item) {
            unset($item['id']); // Remove ID to create new record
            $item['invoice_id'] = $targetInvoiceId;

            if (!$this->insert($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add line item with automatic total recalculation
     */
    public function addLineItem(string $invoiceId, array $data): ?string
    {
        // Check if invoice can be edited (is draft)
        $invoiceModel = new InvoiceModel();
        if (!$invoiceModel->canEdit($invoiceId)) {
            return null;
        }

        $data['invoice_id'] = $invoiceId;
        return $this->insert($data, true);
    }

    /**
     * Update line item with automatic total recalculation
     */
    public function updateLineItem(string $id, array $data): bool
    {
        // Get current line item to check invoice
        $lineItem = $this->find($id);
        if (!$lineItem) {
            return false;
        }

        // Check if invoice can be edited
        $invoiceModel = new InvoiceModel();
        if (!$invoiceModel->canEdit($lineItem['invoice_id'])) {
            return false;
        }

        // Don't allow changing invoice_id
        unset($data['invoice_id']);

        $result = $this->update($id, $data);

        // Trigger invoice recalculation
        if ($result) {
            $invoiceModel->recalculateTotals($lineItem['invoice_id']);
        }

        return $result;
    }

    /**
     * Delete line item with automatic total recalculation
     */
    public function deleteLineItem(string $id): bool
    {
        // Get current line item to check invoice
        $lineItem = $this->find($id);
        if (!$lineItem) {
            return false;
        }

        // Check if invoice can be edited
        $invoiceModel = new InvoiceModel();
        if (!$invoiceModel->canEdit($lineItem['invoice_id'])) {
            return false;
        }

        $invoiceId = $lineItem['invoice_id'];
        $result = $this->delete($id);

        // Trigger invoice recalculation
        if ($result) {
            $invoiceModel->recalculateTotals($invoiceId);
        }

        return $result;
    }

    /**
     * Bulk add line items
     */
    public function addBulk(string $invoiceId, array $items): bool
    {
        // Check if invoice can be edited
        $invoiceModel = new InvoiceModel();
        if (!$invoiceModel->canEdit($invoiceId)) {
            return false;
        }

        $db = \Config\Database::connect();
        $db->transStart();

        $sortOrder = $this->getCount($invoiceId);

        foreach ($items as $item) {
            $item['invoice_id'] = $invoiceId;
            $item['sort_order'] = $sortOrder++;

            if (!$this->insert($item)) {
                $db->transRollback();
                return false;
            }
        }

        $db->transComplete();

        if ($db->transStatus()) {
            $invoiceModel->recalculateTotals($invoiceId);
            return true;
        }

        return false;
    }
}
