<?php

namespace App\Controllers\Invoices;

use App\Controllers\BaseController;
use App\Models\InvoiceModel;
use App\Models\InvoiceLineItemModel;
use App\Models\ClientModel;
use App\Models\ProjectModel;
use App\Domain\Invoices\Authorization\InvoiceGuard;
use App\Services\InvoicePdfService;
use App\Services\InvoiceEmailService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Invoice Controller
 *
 * Manages invoice CRUD operations with full RBAC integration.
 *
 * RBAC Integration:
 * - Layer 1 (RLS): Automatic via database policies
 * - Layer 2 (HTTP Filter): LoginFilter + RBACFilter check authentication and role routes
 * - Layer 3 (Service Guard): InvoiceGuard enforces fine-grained permissions
 * - Layer 4 (Frontend): Vue.js Pinia store hides irrelevant UI elements
 *
 * Features:
 * - Full CRUD operations for invoices
 * - Line item management (nested CRUD)
 * - Invoice number auto-generation
 * - Status workflow management
 * - PDF generation and download
 * - Email delivery with PDF attachment
 */
class InvoiceController extends BaseController
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
     * List all invoices (with search and filtering)
     *
     * GET /api/invoices
     * GET /api/invoices?search=term
     * GET /api/invoices?status=draft
     * GET /api/invoices?client_id=uuid
     * GET /api/invoices?from_date=2025-01-01&to_date=2025-12-31
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        // Get filters from query params
        $filters = [
            'search' => $this->request->getGet('search'),
            'status' => $this->request->getGet('status'),
            'client_id' => $this->request->getGet('client_id'),
            'project_id' => $this->request->getGet('project_id'),
            'from_date' => $this->request->getGet('from_date'),
            'to_date' => $this->request->getGet('to_date'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

        // Get invoices (RLS automatically filters by agency)
        if (!empty($filters['search'])) {
            $invoices = $this->invoiceModel->search($filters['search']);
        } elseif (!empty($filters['client_id'])) {
            $invoices = $this->invoiceModel->getByClientId($filters['client_id']);
        } elseif (!empty($filters['project_id'])) {
            $invoices = $this->invoiceModel->getByProjectId($filters['project_id']);
        } elseif (!empty($filters['status'])) {
            $invoices = $this->invoiceModel->getByStatus($filters['status']);
        } else {
            $invoices = $this->invoiceModel->getForCurrentAgency($filters);
        }

        // For direct clients, filter to only show their assigned client invoices
        if ($user['role'] === 'direct_client') {
            $invoices = array_filter($invoices, function($invoice) use ($user) {
                return $this->guard->canView($user, $invoice);
            });
            $invoices = array_values($invoices);
        }

        // Get permission summary for UI
        $permissions = $this->guard->getPermissionSummary($user);

        return $this->response->setJSON([
            'success' => true,
            'data' => $invoices,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show single invoice details with line items
     *
     * GET /api/invoices/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->getWithRelated($id);

        // Check if invoice exists (RLS may hide it)
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Service guard authorization
        if (!$this->guard->canView($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this invoice.']);
        }

        // Get permission summary for this specific invoice
        $permissions = $this->guard->getPermissionSummary($user, $invoice);

        return $this->response->setJSON([
            'success' => true,
            'data' => $invoice,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store new invoice
     *
     * POST /api/invoices
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Layer 3: Authorization check
        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create invoices.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Set defaults
        $data['status'] = 'draft';
        $data['created_by'] = $user['id'];

        // Extract line items if provided
        $lineItems = $data['line_items'] ?? [];
        unset($data['line_items']);

        // Validate input
        if (!$this->invoiceModel->validate($data)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => 'Validation failed',
                    'errors' => $this->invoiceModel->errors(),
                ]);
        }

        // Start transaction for invoice + line items
        $db = \Config\Database::connect();
        $db->transStart();

        // Insert invoice (agency_id and invoice_number set automatically by model)
        $invoiceId = $this->invoiceModel->insert($data);

        if (!$invoiceId) {
            $db->transRollback();
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create invoice. Please try again.']);
        }

        // Add line items if provided
        if (!empty($lineItems)) {
            foreach ($lineItems as $index => $item) {
                $item['invoice_id'] = $invoiceId;
                $item['sort_order'] = $index;

                // Calculate amount if not provided
                if (!isset($item['amount']) && isset($item['quantity']) && isset($item['unit_price'])) {
                    $item['amount'] = $item['quantity'] * $item['unit_price'];
                }

                if (!$this->lineItemModel->insert($item)) {
                    $db->transRollback();
                    return $this->response
                        ->setStatusCode(400)
                        ->setJSON([
                            'error' => 'Failed to create line item',
                            'errors' => $this->lineItemModel->errors(),
                        ]);
                }
            }

            // Recalculate totals
            $this->invoiceModel->recalculateTotals($invoiceId);
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create invoice. Please try again.']);
        }

        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => $invoice,
            ]);
    }

    /**
     * Update invoice
     *
     * PUT /api/invoices/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this invoice.']);
        }

        // Check if invoice can be edited (draft only)
        if ($invoice['status'] !== 'draft') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Only draft invoices can be edited.']);
        }

        // Get JSON data
        $data = $this->request->getJSON(true);

        // Prevent changing certain fields
        unset($data['id'], $data['invoice_number'], $data['agency_id'], $data['created_by'], $data['status']);

        // Extract line items if provided
        $lineItems = $data['line_items'] ?? null;
        unset($data['line_items']);

        // Start transaction
        $db = \Config\Database::connect();
        $db->transStart();

        // Update invoice
        if (!empty($data)) {
            if (!$this->invoiceModel->update($id, $data)) {
                $db->transRollback();
                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        'error' => 'Validation failed',
                        'errors' => $this->invoiceModel->errors(),
                    ]);
            }
        }

        // Update line items if provided (replace all)
        if ($lineItems !== null) {
            // Delete existing line items
            $this->lineItemModel->deleteByInvoiceId($id);

            // Add new line items
            foreach ($lineItems as $index => $item) {
                $item['invoice_id'] = $id;
                $item['sort_order'] = $index;

                if (!isset($item['amount']) && isset($item['quantity']) && isset($item['unit_price'])) {
                    $item['amount'] = $item['quantity'] * $item['unit_price'];
                }

                if (!$this->lineItemModel->insert($item)) {
                    $db->transRollback();
                    return $this->response
                        ->setStatusCode(400)
                        ->setJSON([
                            'error' => 'Failed to update line items',
                            'errors' => $this->lineItemModel->errors(),
                        ]);
                }
            }

            // Recalculate totals
            $this->invoiceModel->recalculateTotals($id);
        }

        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update invoice. Please try again.']);
        }

        $invoice = $this->invoiceModel->getWithRelated($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Invoice updated successfully.',
            'data' => $invoice,
        ]);
    }

    /**
     * Delete invoice (only draft invoices)
     *
     * DELETE /api/invoices/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDelete($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this invoice.']);
        }

        // Only draft invoices can be deleted
        if ($invoice['status'] !== 'draft') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Only draft invoices can be deleted.']);
        }

        // Delete line items first (foreign key constraint)
        $this->lineItemModel->deleteByInvoiceId($id);

        // Delete invoice
        if (!$this->invoiceModel->delete($id)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to delete invoice.']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Invoice deleted successfully.',
        ]);
    }

    /**
     * Update invoice status
     *
     * PATCH /api/invoices/{id}/status
     */
    public function updateStatus(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canEdit($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to update this invoice status.']);
        }

        // Get new status
        $data = $this->request->getJSON(true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Status is required.']);
        }

        // Validate status transition
        if (!$this->guard->isValidStatusTransition($invoice['status'], $newStatus)) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON([
                    'error' => "Invalid status transition from '{$invoice['status']}' to '{$newStatus}'.",
                    'allowed_transitions' => $this->guard->getAllowedTransitions($invoice['status']),
                ]);
        }

        // Update status using model (handles timestamps)
        if (!$this->invoiceModel->updateStatus($id, $newStatus)) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to update invoice status.']);
        }

        $invoice = $this->invoiceModel->getWithRelated($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => "Invoice status updated to '{$newStatus}'.",
            'data' => $invoice,
        ]);
    }

    /**
     * Send invoice via email (draft → sent)
     *
     * POST /api/invoices/{id}/send
     */
    public function send(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->getWithRelated($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canSend($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to send this invoice.']);
        }

        // Get optional email data
        $data = $this->request->getJSON(true);
        $recipientEmail = $data['email'] ?? null;
        $message = $data['message'] ?? '';

        // Validate invoice has line items
        if (empty($invoice['line_items'])) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Cannot send invoice without line items.']);
        }

        // Validate total is greater than 0
        if (($invoice['total'] ?? 0) <= 0) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Cannot send invoice with zero total.']);
        }

        try {
            // Generate PDF
            $pdfService = new InvoicePdfService();
            $pdfPath = $pdfService->generate($invoice);

            // Send email
            $emailService = new InvoiceEmailService();
            $emailService->sendInvoice($invoice, $pdfPath, $recipientEmail, $message);

            // Update status to sent
            $this->invoiceModel->updateStatus($id, 'sent');

            $invoice = $this->invoiceModel->getWithRelated($id);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Invoice sent successfully.',
                'data' => $invoice,
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Invoice send failed: ' . $e->getMessage());

            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to send invoice. ' . $e->getMessage()]);
        }
    }

    /**
     * Resend invoice email
     *
     * POST /api/invoices/{id}/resend
     */
    public function resend(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->getWithRelated($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canResend($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to resend this invoice.']);
        }

        // Get optional email data
        $data = $this->request->getJSON(true);
        $recipientEmail = $data['email'] ?? null;
        $message = $data['message'] ?? '';

        try {
            // Generate PDF
            $pdfService = new InvoicePdfService();
            $pdfPath = $pdfService->generate($invoice);

            // Send email
            $emailService = new InvoiceEmailService();
            $emailService->sendInvoice($invoice, $pdfPath, $recipientEmail, $message, true);

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Invoice resent successfully.',
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Invoice resend failed: ' . $e->getMessage());

            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to resend invoice. ' . $e->getMessage()]);
        }
    }

    /**
     * Download invoice PDF
     *
     * GET /api/invoices/{id}/pdf
     */
    public function pdf(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->getWithRelated($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canDownloadPdf($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to download this invoice.']);
        }

        try {
            // Generate PDF
            $pdfService = new InvoicePdfService();
            $pdfContent = $pdfService->generateContent($invoice);

            $filename = "invoice-{$invoice['invoice_number']}.pdf";

            return $this->response
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->setBody($pdfContent);
        } catch (\Exception $e) {
            log_message('error', 'Invoice PDF generation failed: ' . $e->getMessage());

            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to generate PDF. ' . $e->getMessage()]);
        }
    }

    /**
     * Preview invoice PDF (returns base64 encoded)
     *
     * GET /api/invoices/{id}/preview
     */
    public function preview(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->getWithRelated($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canView($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to preview this invoice.']);
        }

        try {
            // Generate PDF content
            $pdfService = new InvoicePdfService();
            $pdfContent = $pdfService->generateContent($invoice);

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'pdf_base64' => base64_encode($pdfContent),
                    'filename' => "invoice-{$invoice['invoice_number']}.pdf",
                ],
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Invoice preview failed: ' . $e->getMessage());

            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to generate preview. ' . $e->getMessage()]);
        }
    }

    /**
     * Get invoice statistics
     *
     * GET /api/invoices/stats
     */
    public function stats(): ResponseInterface
    {
        $stats = $this->invoiceModel->getSummaryStats();

        return $this->response->setJSON([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Create invoice from project time entries
     *
     * POST /api/invoices/from-project/{project_id}
     */
    public function createFromProject(string $projectId): ResponseInterface
    {
        $user = session()->get('user');

        // Layer 3: Authorization check
        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create invoices.']);
        }

        // Get optional invoice data
        $data = $this->request->getJSON(true) ?? [];

        // Set defaults
        $data['issue_date'] = $data['issue_date'] ?? date('Y-m-d');
        $data['due_date'] = $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'));

        // Create invoice from time entries
        $invoiceId = $this->invoiceModel->createFromTimeEntries($projectId, $data);

        if (!$invoiceId) {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'No billable time entries found for this project.']);
        }

        $invoice = $this->invoiceModel->getWithRelated($invoiceId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'message' => 'Invoice created from project time entries.',
                'data' => $invoice,
            ]);
    }

    /**
     * Get overdue invoices
     *
     * GET /api/invoices/overdue
     */
    public function overdue(): ResponseInterface
    {
        $invoices = $this->invoiceModel->getOverdue();

        return $this->response->setJSON([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Mark invoice as paid
     *
     * POST /api/invoices/{id}/mark-paid
     */
    public function markPaid(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $invoice = $this->invoiceModel->find($id);

        // Check if exists
        if (!$invoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Invoice not found']);
        }

        // Layer 3: Authorization check
        if (!$this->guard->canMarkAsPaid($user, $invoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to mark this invoice as paid.']);
        }

        // Get payment details
        $data = $this->request->getJSON(true);
        $paymentMethod = $data['payment_method'] ?? null;
        $paymentReference = $data['payment_reference'] ?? null;

        // Update status to paid
        if (!$this->invoiceModel->updateStatus($id, 'paid')) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to mark invoice as paid.']);
        }

        $invoice = $this->invoiceModel->getWithRelated($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Invoice marked as paid.',
            'data' => $invoice,
        ]);
    }
}
