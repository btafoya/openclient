<?php

namespace App\Controllers;

use App\Domain\Invoices\Authorization\InvoiceGuard;
use App\Models\InvoiceModel;
use App\Helpers\SecurityLogger;

/**
 * Invoice Controller with Authorization Guards
 *
 * Example implementation demonstrating RBAC Layer 3:
 * Service-Level Authorization Guards
 *
 * This controller shows how to:
 * - Inject authorization guards
 * - Check permissions before business logic
 * - Handle authorization failures gracefully
 * - Log security events
 * - Return appropriate HTTP responses
 */
class InvoicesController extends BaseController
{
    private InvoiceGuard $guard;
    private InvoiceModel $model;

    public function __construct()
    {
        $this->guard = new InvoiceGuard();
        $this->model = new InvoiceModel();
    }

    /**
     * List all invoices (with authorization filtering)
     *
     * GET /invoices
     */
    public function index()
    {
        $user = session()->get('user');

        // Check if user can view invoices at all
        if (!$this->guard->canCreate($user)) {
            // If they can't create, they might still be able to view their own
            // This is handled by the model's RLS filtering
        }

        // Get invoices (RLS automatically filters by agency_id)
        $invoices = $this->model->findAll();

        // Additional filtering for Direct Clients
        // (only show invoices for clients they're assigned to)
        if ($user['role'] === 'direct_client') {
            $invoices = array_filter($invoices, function($invoice) use ($user) {
                return $this->guard->canView($user, $invoice);
            });
        }

        return view('invoices/index', [
            'title' => 'Invoices',
            'invoices' => $invoices,
            'permissions' => $this->guard->getPermissionSummary($user),
        ]);
    }

    /**
     * Show single invoice
     *
     * GET /invoices/{id}
     */
    public function show($id)
    {
        $user = session()->get('user');

        // Fetch invoice (RLS automatically filters)
        $invoice = $this->model->find($id);

        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }

        // Check if user can view this specific invoice
        if (!$this->guard->canView($user, $invoice)) {
            SecurityLogger::logAccessDenied(
                $user,
                "Invoice #{$id}",
                "User role '{$user['role']}' attempted to view invoice outside their scope"
            );

            return redirect()->to('/invoices')
                ->with('error', 'You do not have permission to view this invoice.');
        }

        return view('invoices/show', [
            'title' => 'Invoice #' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'permissions' => $this->guard->getPermissionSummary($user, $invoice),
        ]);
    }

    /**
     * Show form to create new invoice
     *
     * GET /invoices/new
     */
    public function new()
    {
        $user = session()->get('user');

        // Check if user can create invoices
        if (!$this->guard->canCreate($user)) {
            SecurityLogger::logAccessDenied(
                $user,
                'Invoice creation form',
                "User role '{$user['role']}' attempted to access invoice creation"
            );

            return redirect()->to('/invoices')
                ->with('error', 'You do not have permission to create invoices.');
        }

        return view('invoices/new', [
            'title' => 'Create Invoice',
        ]);
    }

    /**
     * Create new invoice
     *
     * POST /invoices
     */
    public function create()
    {
        $user = session()->get('user');

        // Check if user can create invoices
        if (!$this->guard->canCreate($user)) {
            SecurityLogger::logAccessDenied(
                $user,
                'Invoice creation',
                "User role '{$user['role']}' attempted to create invoice"
            );

            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'You do not have permission to create invoices.'
            ]);
        }

        // Validate input
        $rules = [
            'client_id' => 'required|is_natural_no_zero',
            'amount' => 'required|decimal',
            'due_date' => 'required|valid_date',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Create invoice
        $data = [
            'client_id' => $this->request->getPost('client_id'),
            'amount' => $this->request->getPost('amount'),
            'due_date' => $this->request->getPost('due_date'),
            'status' => 'draft',
            'agency_id' => $user['agency_id'],  // Automatically set from session
            'created_by' => $user['id'],
        ];

        $invoiceId = $this->model->insert($data);

        if (!$invoiceId) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to create invoice.');
        }

        return redirect()->to("/invoices/{$invoiceId}")
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Show form to edit invoice
     *
     * GET /invoices/{id}/edit
     */
    public function edit($id)
    {
        $user = session()->get('user');

        // Fetch invoice
        $invoice = $this->model->find($id);

        if (!$invoice) {
            return redirect()->back()->with('error', 'Invoice not found.');
        }

        // Check if user can edit this invoice
        if (!$this->guard->canEdit($user, $invoice)) {
            SecurityLogger::logAccessDenied(
                $user,
                "Invoice #{$id} edit form",
                "User role '{$user['role']}' attempted to edit invoice outside their scope"
            );

            return redirect()->to('/invoices')
                ->with('error', 'You do not have permission to edit this invoice.');
        }

        return view('invoices/edit', [
            'title' => 'Edit Invoice #' . $invoice['invoice_number'],
            'invoice' => $invoice,
        ]);
    }

    /**
     * Update invoice
     *
     * PUT /invoices/{id}
     */
    public function update($id)
    {
        $user = session()->get('user');

        // Fetch invoice
        $invoice = $this->model->find($id);

        if (!$invoice) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Invoice not found.'
            ]);
        }

        // Check if user can edit this invoice
        if (!$this->guard->canEdit($user, $invoice)) {
            SecurityLogger::logAccessDenied(
                $user,
                "Invoice #{$id} update",
                "User role '{$user['role']}' attempted to update invoice outside their scope"
            );

            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'You do not have permission to edit this invoice.'
            ]);
        }

        // Validate input
        $rules = [
            'amount' => 'permit_empty|decimal',
            'due_date' => 'permit_empty|valid_date',
            'status' => 'permit_empty|in_list[draft,sent,paid,overdue,cancelled]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Update invoice
        $data = $this->request->getPost();
        $data['updated_by'] = $user['id'];

        if (!$this->model->update($id, $data)) {
            return redirect()->back()->withInput()
                ->with('error', 'Failed to update invoice.');
        }

        return redirect()->to("/invoices/{$id}")
            ->with('success', 'Invoice updated successfully.');
    }

    /**
     * Delete invoice
     *
     * DELETE /invoices/{id}
     */
    public function delete($id)
    {
        $user = session()->get('user');

        // Fetch invoice
        $invoice = $this->model->find($id);

        if (!$invoice) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Invoice not found.'
            ]);
        }

        // Check if user can delete this invoice
        if (!$this->guard->canDelete($user, $invoice)) {
            SecurityLogger::logAccessDenied(
                $user,
                "Invoice #{$id} deletion",
                "User role '{$user['role']}' attempted to delete invoice"
            );

            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'You do not have permission to delete this invoice.'
            ]);
        }

        // Delete invoice
        if (!$this->model->delete($id)) {
            return redirect()->back()->with('error', 'Failed to delete invoice.');
        }

        return redirect()->to('/invoices')
            ->with('success', 'Invoice deleted successfully.');
    }
}
