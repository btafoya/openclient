<?php

namespace App\Controllers\Invoices;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;
use App\Models\InvoiceLineItemModel;
use App\Domain\Invoices\Authorization\InvoiceGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Invoice Line Item Controller
 *
 * Manages individual line items on invoices.
 * Authorization is inherited from parent invoice.
 */
class InvoiceLineItemController extends BaseController
{
    protected InvoiceModel $invoiceModel;
    protected InvoiceLineItemModel $lineItemModel;
    protected InvoiceGuard $guard;

    public function __construct()
    {
        $this->invoiceModel = new InvoiceModel();
        $this->lineItemModel = new InvoiceLineItemModel();
        $this->guard = new InvoiceGuard();
    }

    /**
     * List line items for an invoice
     *
     * GET /api/invoices/{invoice_id}/line-items
     */
    public function index(string $invoiceId): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($invoiceId);

        // Check if invoice exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Check view permission
        if (!$this->guard->canView($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this invoice.']);
        }

        $lineItems = $this->lineItemModel->getByInvoiceId($invoiceId);

        return $this->response->setJSON([
            'success' => true,
            'data' => $lineItems,
        ]);
    }

    /**
     * Add line item to invoice
     *
     * POST /api/invoices/{invoice_id}/line-items
     */
    public function store(string $invoiceId): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($invoiceId);

        // Check if invoice exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Check edit line items permission
        if (!$this->guard->canEditLineItems($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to add line items to this invoice.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);
        $data['invoice_id'] = $invoiceId;

        // Calculate amount if not provided
        if (!isset($data['amount']) && isset($data['quantity']) && isset($data['unit_price'])) {
            $data['amount'] = $data['quantity'] * $data['unit_price'];
        }

        // Validate
        if (!$this->lineItemModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->lineItemModel->errors(),
                ]);
        }

        // Insert line item
        $lineItemId = $this->lineItemModel->insert($data);

        if (!$lineItemId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to add line item.']);
        }

        // Recalculate invoice totals
        $this->invoiceModel->recalculateTotals($invoiceId);

        $lineItem = $this->lineItemModel->find($lineItemId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Line item added successfully.',
                'data' => $lineItem,
            ]);
    }

    /**
     * Update line item
     *
     * PUT /api/invoices/{invoice_id}/line-items/{id}
     */
    public function update(string $invoiceId, string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($invoiceId);

        // Check if invoice exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Check edit line items permission
        if (!$this->guard->canEditLineItems($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit line items on this invoice.']);
        }

        // Check if line item exists and belongs to this invoice
        $lineItem = $this->lineItemModel->find($id);
        if (!$lineItem || $lineItem['invoice_id'] !== $invoiceId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Line item not found']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Don't allow changing invoice_id
        unset($data['invoice_id'], $data['id']);

        // Calculate amount if quantity or unit_price changed
        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $quantity = $data['quantity'] ?? $lineItem['quantity'];
            $unitPrice = $data['unit_price'] ?? $lineItem['unit_price'];
            $data['amount'] = $quantity * $unitPrice;
        }

        // Update line item
        if (!$this->lineItemModel->update($id, $data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->lineItemModel->errors(),
                ]);
        }

        // Recalculate invoice totals
        $this->invoiceModel->recalculateTotals($invoiceId);

        $lineItem = $this->lineItemModel->find($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Line item updated successfully.',
            'data' => $lineItem,
        ]);
    }

    /**
     * Delete line item
     *
     * DELETE /api/invoices/{invoice_id}/line-items/{id}
     */
    public function delete(string $invoiceId, string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($invoiceId);

        // Check if invoice exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Check edit line items permission
        if (!$this->guard->canEditLineItems($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete line items from this invoice.']);
        }

        // Check if line item exists and belongs to this invoice
        $lineItem = $this->lineItemModel->find($id);
        if (!$lineItem || $lineItem['invoice_id'] !== $invoiceId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Line item not found']);
        }

        // Delete line item
        if (!$this->lineItemModel->delete($id)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete line item.']);
        }

        // Recalculate invoice totals
        $this->invoiceModel->recalculateTotals($invoiceId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Line item deleted successfully.',
        ]);
    }

    /**
     * Reorder line items
     *
     * POST /api/invoices/{invoice_id}/line-items/reorder
     */
    public function reorder(string $invoiceId): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($invoiceId);

        // Check if invoice exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Check edit line items permission
        if (!$this->guard->canEditLineItems($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to reorder line items on this invoice.']);
        }

        // Get ordered IDs
        $data = $this->request->getJSON(true);
        $itemIds = $data['item_ids'] ?? [];

        if (empty($itemIds) || !is_array($itemIds)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'item_ids array is required.']);
        }

        // Verify all items belong to this invoice
        foreach ($itemIds as $itemId) {
            $lineItem = $this->lineItemModel->find($itemId);
            if (!$lineItem || $lineItem['invoice_id'] !== $invoiceId) {
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON(['error' => "Line item {$itemId} does not belong to this invoice."]);
            }
        }

        // Reorder items
        if (!$this->lineItemModel->reorder($itemIds)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to reorder line items.']);
        }

        $lineItems = $this->lineItemModel->getByInvoiceId($invoiceId);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Line items reordered successfully.',
            'data' => $lineItems,
        ]);
    }
}
