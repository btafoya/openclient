<?php

namespace App\Controllers\CsvImport;

use App\Controllers\BaseController;
use App\Models\CsvExportModel;
use App\Domain\CsvImport\Authorization\CsvImportGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CSV Export Controller
 *
 * Handles data export functionality with streaming support for large datasets.
 * Supports clients, contacts, and notes exports with field selection.
 */
class CsvExportController extends BaseController
{
    protected $csvExportModel;
    protected $guard;

    public function __construct()
    {
        $this->csvExportModel = new CsvExportModel();
        $this->guard = new CsvImportGuard();
    }

    /**
     * Display export form
     */
    public function index(): string
    {
        $user = session()->get('user');

        if (!$this->guard->canExport($user)) {
            return view('errors/403', [
                'message' => 'You do not have permission to export data.',
            ]);
        }

        $data = [
            'title' => 'Export Data',
            'entity_types' => [
                'clients' => 'Clients',
                'contacts' => 'Contacts',
                'notes' => 'Notes',
            ],
        ];

        return view('csv/export', $data);
    }

    /**
     * Process export request
     */
    public function export(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canExport($user)) {
            return redirect()->to('/')->with('error', 'You do not have permission to export data.');
        }

        $entityType = $this->request->getPost('entity_type');

        // Validate entity type
        if (!in_array($entityType, ['clients', 'contacts', 'notes'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid entity type selected.');
        }

        // Check entity-specific permission
        if (!$this->guard->canExportEntityType($user, $entityType)) {
            return redirect()->back()->with('error', 'You do not have permission to export this entity type.');
        }

        // Get selected fields
        $fields = $this->request->getPost('fields') ?? [];
        if (empty($fields)) {
            // If no fields selected, use all fields
            $allFields = $this->csvExportModel->getExportFields($entityType);
            $fields = array_keys($allFields);
        }

        // Get export options
        $options = [
            'fields' => $fields,
            'filters' => [],
        ];

        // Apply filters
        if ($this->request->getPost('active_only')) {
            $options['filters']['is_active'] = true;
        }

        if ($this->request->getPost('created_after')) {
            $options['filters']['created_after'] = $this->request->getPost('created_after');
        }

        if ($this->request->getPost('created_before')) {
            $options['filters']['created_before'] = $this->request->getPost('created_before');
        }

        if ($this->request->getPost('search')) {
            $options['filters']['search'] = $this->request->getPost('search');
        }

        // Generate export
        $filepath = $this->csvExportModel->export($entityType, $options);

        if (!$filepath || !file_exists($filepath)) {
            return redirect()->back()->with('error', 'Failed to generate export file.');
        }

        // Download file
        return $this->response->download($filepath, null)->setFileName(basename($filepath));
    }

    /**
     * Get available export fields for entity type (AJAX)
     */
    public function getFields(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canExport($user)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        $entityType = $this->request->getGet('type');

        if (!in_array($entityType, ['clients', 'contacts', 'notes'])) {
            return $this->response->setJSON([
                'error' => 'Invalid entity type',
            ])->setStatusCode(400);
        }

        $fields = $this->csvExportModel->getExportFields($entityType);

        return $this->response->setJSON([
            'fields' => $fields,
        ]);
    }

    /**
     * Clean up old export files
     */
    public function cleanup(): ResponseInterface
    {
        $user = session()->get('user');

        // Only owner can cleanup files
        if ($user['role'] !== 'owner') {
            return redirect()->to('/')->with('error', 'Only owners can cleanup export files.');
        }

        $deleted = $this->csvExportModel->cleanupOldExports();

        return redirect()->back()->with('success', "Deleted {$deleted} old export file(s).");
    }
}
