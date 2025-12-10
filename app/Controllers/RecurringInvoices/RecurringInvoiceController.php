<?php

namespace App\Controllers\RecurringInvoices;

use App\Controllers\BaseController;
use App\Models\RecurringInvoiceModel;
use App\Models\ClientModel;
use App\Domain\RecurringInvoices\Authorization\RecurringInvoiceGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Recurring Invoice Controller
 *
 * Manages recurring invoice templates for automated billing.
 */
class RecurringInvoiceController extends BaseController
{
    protected RecurringInvoiceModel $recurringModel;
    protected RecurringInvoiceGuard $guard;

    public function __construct()
    {
        $this->recurringModel = new RecurringInvoiceModel();
        $this->guard = new RecurringInvoiceGuard();
    }

    /**
     * List all recurring invoices
     *
     * GET /api/recurring-invoices
     */
    public function index(): ResponseInterface
    {
        $user = session()->get('user');

        $filters = [
            'status' => $this->request->getGet('status'),
            'client_id' => $this->request->getGet('client_id'),
        ];

        if (!empty($filters['client_id'])) {
            $recurringInvoices = $this->recurringModel->getByClientId($filters['client_id']);
        } elseif (!empty($filters['status'])) {
            $recurringInvoices = $this->recurringModel->getByStatus($filters['status']);
        } else {
            $recurringInvoices = $this->recurringModel->findAll();
        }

        // Filter for direct clients
        if ($user['role'] === 'direct_client') {
            $recurringInvoices = array_filter($recurringInvoices, function($ri) use ($user) {
                return $this->guard->canView($user, $ri);
            });
            $recurringInvoices = array_values($recurringInvoices);
        }

        $permissions = $this->guard->getPermissionSummary($user);

        return $this->response->setJSON([
            'success' => true,
            'data' => $recurringInvoices,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show single recurring invoice
     *
     * GET /api/recurring-invoices/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->getWithRelated($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canView($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to view this recurring invoice.']);
        }

        $permissions = $this->guard->getPermissionSummary($user, $recurringInvoice);

        return $this->response->setJSON([
            'success' => true,
            'data' => $recurringInvoice,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store new recurring invoice
     *
     * POST /api/recurring-invoices
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create recurring invoices.']);
        }

        $data = $this->request->getJSON(true);

        $rules = [
            'client_id' => 'required|max_length[36]',
            'title' => 'required|max_length[255]',
            'frequency' => 'required|in_list[daily,weekly,biweekly,monthly,quarterly,yearly]',
            'start_date' => 'required|valid_date',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'Validation failed', 'errors' => $this->validator->getErrors()]);
        }

        $recurringData = [
            'client_id' => $data['client_id'],
            'project_id' => $data['project_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'line_items' => isset($data['line_items']) ? json_encode($data['line_items']) : '[]',
            'subtotal' => $data['subtotal'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'discount_amount' => $data['discount_amount'] ?? 0,
            'total_amount' => $data['total_amount'] ?? 0,
            'currency' => $data['currency'] ?? 'USD',
            'frequency' => $data['frequency'],
            'interval_count' => $data['interval_count'] ?? 1,
            'day_of_week' => $data['day_of_week'] ?? null,
            'day_of_month' => $data['day_of_month'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'auto_send' => $data['auto_send'] ?? true,
            'payment_terms_days' => $data['payment_terms_days'] ?? 30,
            'max_occurrences' => $data['max_occurrences'] ?? null,
            'email_recipients' => isset($data['email_recipients']) ? json_encode($data['email_recipients']) : null,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
        ];

        $recurringId = $this->recurringModel->insert($recurringData, true);

        if (!$recurringId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create recurring invoice']);
        }

        $recurringInvoice = $this->recurringModel->getWithRelated($recurringId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'data' => $recurringInvoice,
                'message' => 'Recurring invoice created successfully',
            ]);
    }

    /**
     * Update recurring invoice
     *
     * PUT /api/recurring-invoices/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->find($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canEdit($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to edit this recurring invoice.']);
        }

        $data = $this->request->getJSON(true);

        $updateData = [];

        $simpleFields = ['title', 'description', 'subtotal', 'tax_rate', 'tax_amount',
                         'discount_percent', 'discount_amount', 'total_amount', 'currency',
                         'frequency', 'interval_count', 'day_of_week', 'day_of_month',
                         'start_date', 'end_date', 'auto_send', 'payment_terms_days',
                         'max_occurrences', 'notes'];

        foreach ($simpleFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['line_items'])) {
            $updateData['line_items'] = json_encode($data['line_items']);
        }
        if (isset($data['email_recipients'])) {
            $updateData['email_recipients'] = json_encode($data['email_recipients']);
        }

        if (!empty($updateData)) {
            $this->recurringModel->update($id, $updateData);
        }

        $recurringInvoice = $this->recurringModel->getWithRelated($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $recurringInvoice,
            'message' => 'Recurring invoice updated successfully',
        ]);
    }

    /**
     * Delete recurring invoice
     *
     * DELETE /api/recurring-invoices/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->find($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canDelete($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to delete this recurring invoice.']);
        }

        $this->recurringModel->delete($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Recurring invoice deleted successfully',
        ]);
    }

    /**
     * Pause recurring invoice
     *
     * POST /api/recurring-invoices/{id}/pause
     */
    public function pause(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->find($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canPause($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You cannot pause this recurring invoice.']);
        }

        $this->recurringModel->pause($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Recurring invoice paused successfully',
        ]);
    }

    /**
     * Resume recurring invoice
     *
     * POST /api/recurring-invoices/{id}/resume
     */
    public function resume(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->find($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canResume($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You cannot resume this recurring invoice.']);
        }

        $this->recurringModel->resume($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Recurring invoice resumed successfully',
        ]);
    }

    /**
     * Cancel recurring invoice
     *
     * POST /api/recurring-invoices/{id}/cancel
     */
    public function cancel(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->find($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canCancel($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You cannot cancel this recurring invoice.']);
        }

        $this->recurringModel->cancel($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Recurring invoice cancelled successfully',
        ]);
    }

    /**
     * Manually process recurring invoice (generate invoice now)
     *
     * POST /api/recurring-invoices/{id}/process
     */
    public function process(string $id): ResponseInterface
    {
        $user = session()->get('user');
        $recurringInvoice = $this->recurringModel->find($id);

        if (!$recurringInvoice) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Recurring invoice not found']);
        }

        if (!$this->guard->canEdit($user, $recurringInvoice)) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You cannot process this recurring invoice.']);
        }

        if ($recurringInvoice['status'] !== 'active') {
            return $this->response
                ->setStatusCode(400)
                ->setJSON(['error' => 'Only active recurring invoices can be processed.']);
        }

        $invoiceId = $this->recurringModel->processRecurringInvoice($id);

        if (!$invoiceId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to generate invoice']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => ['invoice_id' => $invoiceId],
            'message' => 'Invoice generated successfully',
        ]);
    }
}
