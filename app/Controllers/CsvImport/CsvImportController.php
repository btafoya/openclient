<?php

namespace App\Controllers\CsvImport;

use App\Controllers\BaseController;
use App\Models\CsvImportModel;
use App\Models\ClientModel;
use App\Models\ContactModel;
use App\Models\NoteModel;
use App\Domain\CsvImport\Authorization\CsvImportGuard;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CSV Import Controller
 *
 * Handles CSV file uploads, validation, and import processing.
 * Supports clients, contacts, and notes imports with field mapping.
 */
class CsvImportController extends BaseController
{
    protected $csvImportModel;
    protected $guard;

    public function __construct()
    {
        $this->csvImportModel = new CsvImportModel();
        $this->guard = new CsvImportGuard();
    }

    /**
     * Display import form
     */
    public function index(): string
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return view('errors/403', [
                'message' => 'You do not have permission to import data.',
            ]);
        }

        $data = [
            'title' => 'Import Data',
            'entity_types' => [
                'clients' => 'Clients',
                'contacts' => 'Contacts',
                'notes' => 'Notes',
            ],
        ];

        return view('csv/import', $data);
    }

    /**
     * Handle file upload and create import record
     */
    public function upload(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return redirect()->to('/')->with('error', 'You do not have permission to import data.');
        }

        $entityType = $this->request->getPost('entity_type');
        $file = $this->request->getFile('csv_file');

        // Validate entity type
        if (!in_array($entityType, ['clients', 'contacts', 'notes'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid entity type selected.');
        }

        // Validate file upload
        if (!$file || !$file->isValid()) {
            return redirect()->back()->withInput()->with('error', 'No file was uploaded or file is invalid.');
        }

        // Validate file using guard
        $fileValidation = $this->guard->validateFileUpload([
            'tmp_name' => $file->getTempName(),
            'name' => $file->getName(),
            'size' => $file->getSize(),
        ]);

        if (!$fileValidation['valid']) {
            return redirect()->back()->withInput()->with('error', implode(', ', $fileValidation['errors']));
        }

        // Move file to upload directory
        $uploadDir = $this->guard->getUploadDirectory();
        $safeFilename = $this->guard->getSafeFilename($file->getName());
        $filepath = $uploadDir . $safeFilename;

        if (!$file->move($uploadDir, $safeFilename)) {
            return redirect()->back()->withInput()->with('error', 'Failed to save uploaded file.');
        }

        // Create import record
        $importData = [
            'user_id' => $user['id'],
            'entity_type' => $entityType,
            'filename' => $file->getName(),
            'file_path' => $filepath,
            'file_size' => $file->getSize(),
            'status' => 'pending',
            'import_options' => [
                'skip_duplicates' => (bool) $this->request->getPost('skip_duplicates'),
                'update_existing' => (bool) $this->request->getPost('update_existing'),
            ],
        ];

        if (!$this->csvImportModel->save($importData)) {
            unlink($filepath); // Clean up file
            return redirect()->back()->withInput()->with('error', 'Failed to create import record.');
        }

        $importId = $this->csvImportModel->getInsertID();

        // Redirect to mapping step
        return redirect()->to("/csv/import/{$importId}/mapping")->with('success', 'File uploaded successfully. Please map CSV columns to fields.');
    }

    /**
     * Display field mapping form
     */
    public function mapping(string $id): string
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return view('errors/404', ['message' => 'Import not found.']);
        }

        if (!$this->guard->canView($user, $import)) {
            return view('errors/403', ['message' => 'You do not have permission to view this import.']);
        }

        // Read CSV headers
        $filepath = $import['file_path'];
        if (!file_exists($filepath)) {
            return view('errors/404', ['message' => 'CSV file not found.']);
        }

        $handle = fopen($filepath, 'r');
        $headers = fgetcsv($handle);
        fclose($handle);

        // Get field requirements
        $fieldMapping = $this->csvImportModel->getEntityFieldMapping($import['entity_type']);

        $data = [
            'title' => 'Map CSV Columns',
            'import' => $import,
            'csv_headers' => $headers,
            'required_fields' => $fieldMapping['required'] ?? [],
            'optional_fields' => $fieldMapping['optional'] ?? [],
        ];

        return view('csv/mapping', $data);
    }

    /**
     * Save field mapping and start import process
     */
    public function saveMapping(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return redirect()->to('/csv/history')->with('error', 'Import not found.');
        }

        if (!$this->guard->canEdit($user, $import)) {
            return redirect()->to('/csv/history')->with('error', 'You do not have permission to edit this import.');
        }

        // Get field mapping from POST data
        $fieldMapping = $this->request->getPost('mapping');

        // Validate mapping
        $requiredFields = $this->csvImportModel->getEntityFieldMapping($import['entity_type'])['required'];
        $missingFields = array_diff($requiredFields, array_values($fieldMapping));

        if (!empty($missingFields)) {
            return redirect()->back()->withInput()->with('error', 'Missing required field mappings: ' . implode(', ', $missingFields));
        }

        // Save mapping to import record
        $this->csvImportModel->update($id, [
            'field_mapping' => $fieldMapping,
        ]);

        // Start import process (in real implementation, this would be queued)
        $this->csvImportModel->processImport($id);

        return redirect()->to("/csv/import/{$id}")->with('success', 'Import processing started.');
    }

    /**
     * Display import status and results
     */
    public function show(string $id): string
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return view('errors/404', ['message' => 'Import not found.']);
        }

        if (!$this->guard->canView($user, $import)) {
            return view('errors/403', ['message' => 'You do not have permission to view this import.']);
        }

        // Get user info
        $import['user_name'] = ($import['user_first_name'] ?? '') . ' ' . ($import['user_last_name'] ?? '');

        $data = [
            'title' => 'Import Details',
            'import' => $import,
            'canCancel' => $this->guard->canCancel($user, $import),
            'canDelete' => $this->guard->canDelete($user, $import),
        ];

        return view('csv/show', $data);
    }

    /**
     * Display import history
     */
    public function history(): string
    {
        $user = session()->get('user');

        if (!$this->guard->canViewHistory($user)) {
            return view('errors/403', ['message' => 'You do not have permission to view import history.']);
        }

        $imports = $this->csvImportModel->getHistory();

        // Filter based on permissions
        $imports = $this->guard->filterViewableImports($user, $imports);

        $data = [
            'title' => 'Import History',
            'imports' => $imports,
        ];

        return view('csv/history', $data);
    }

    /**
     * Cancel pending import
     */
    public function cancel(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return redirect()->to('/csv/history')->with('error', 'Import not found.');
        }

        if (!$this->guard->canCancel($user, $import)) {
            return redirect()->back()->with('error', 'You do not have permission to cancel this import.');
        }

        if ($this->csvImportModel->cancelImport($id)) {
            return redirect()->to('/csv/history')->with('success', 'Import cancelled successfully.');
        }

        return redirect()->back()->with('error', 'Failed to cancel import.');
    }

    /**
     * Delete import record
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return redirect()->to('/csv/history')->with('error', 'Import not found.');
        }

        if (!$this->guard->canDelete($user, $import)) {
            return redirect()->back()->with('error', 'You do not have permission to delete this import.');
        }

        // Delete file
        if (file_exists($import['file_path'])) {
            unlink($import['file_path']);
        }

        if ($this->csvImportModel->delete($id)) {
            return redirect()->to('/csv/history')->with('success', 'Import deleted successfully.');
        }

        return redirect()->back()->with('error', 'Failed to delete import.');
    }

    /**
     * Download CSV template
     */
    public function downloadTemplate(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return redirect()->to('/')->with('error', 'You do not have permission to download templates.');
        }

        $entityType = $this->request->getGet('type') ?? 'clients';

        if (!in_array($entityType, ['clients', 'contacts', 'notes'])) {
            return redirect()->back()->with('error', 'Invalid entity type.');
        }

        $exportModel = new \App\Models\CsvExportModel();
        $filepath = $exportModel->generateTemplate($entityType);

        if (!$filepath || !file_exists($filepath)) {
            return redirect()->back()->with('error', 'Failed to generate template.');
        }

        return $this->response->download($filepath, null)->setFileName($entityType . '_import_template.csv');
    }

    /**
     * API: Get import statistics
     */
    public function apiStatistics(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canViewHistory($user)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        $stats = $this->csvImportModel->getStatistics();

        return $this->response->setJSON($stats);
    }

    /**
     * API: Get import history
     */
    public function apiHistory(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canViewHistory($user)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        $imports = $this->csvImportModel->getHistory();
        $imports = $this->guard->filterViewableImports($user, $imports);

        return $this->response->setJSON($imports);
    }

    /**
     * API: Upload CSV file
     */
    public function apiUpload(): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        $entityType = $this->request->getPost('entity_type');
        $file = $this->request->getFile('csv_file');

        if (!in_array($entityType, ['clients', 'contacts', 'notes'])) {
            return $this->response->setJSON([
                'error' => 'Invalid entity type selected.',
            ])->setStatusCode(400);
        }

        if (!$file || !$file->isValid()) {
            return $this->response->setJSON([
                'error' => 'No file was uploaded or file is invalid.',
            ])->setStatusCode(400);
        }

        $fileValidation = $this->guard->validateFileUpload([
            'tmp_name' => $file->getTempName(),
            'name' => $file->getName(),
            'size' => $file->getSize(),
        ]);

        if (!$fileValidation['valid']) {
            return $this->response->setJSON([
                'error' => implode(', ', $fileValidation['errors']),
            ])->setStatusCode(400);
        }

        $uploadDir = $this->guard->getUploadDirectory();
        $safeFilename = $this->guard->getSafeFilename($file->getName());
        $filepath = $uploadDir . $safeFilename;

        if (!$file->move($uploadDir, $safeFilename)) {
            return $this->response->setJSON([
                'error' => 'Failed to save uploaded file.',
            ])->setStatusCode(500);
        }

        $importData = [
            'user_id' => $user['id'],
            'entity_type' => $entityType,
            'filename' => $file->getName(),
            'file_path' => $filepath,
            'file_size' => $file->getSize(),
            'status' => 'pending',
            'import_options' => [
                'skip_duplicates' => (bool) $this->request->getPost('skip_duplicates'),
                'update_existing' => (bool) $this->request->getPost('update_existing'),
            ],
        ];

        if (!$this->csvImportModel->save($importData)) {
            unlink($filepath);
            return $this->response->setJSON([
                'error' => 'Failed to create import record.',
            ])->setStatusCode(500);
        }

        $importId = $this->csvImportModel->getInsertID();

        // Read CSV headers for mapping step
        $handle = fopen($filepath, 'r');
        $headers = fgetcsv($handle);
        fclose($handle);

        $fieldMapping = $this->csvImportModel->getEntityFieldMapping($entityType);

        return $this->response->setJSON([
            'import_id' => $importId,
            'csv_headers' => $headers,
            'required_fields' => $fieldMapping['required'] ?? [],
            'optional_fields' => $fieldMapping['optional'] ?? [],
        ]);
    }

    /**
     * API: Get import details
     */
    public function apiShow(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return $this->response->setJSON([
                'error' => 'Import not found.',
            ])->setStatusCode(404);
        }

        if (!$this->guard->canView($user, $import)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        return $this->response->setJSON($import);
    }

    /**
     * API: Save field mapping and process import
     */
    public function apiSaveMapping(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return $this->response->setJSON([
                'error' => 'Import not found.',
            ])->setStatusCode(404);
        }

        if (!$this->guard->canEdit($user, $import)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        $fieldMapping = $this->request->getJSON(true)['mapping'] ?? [];

        $requiredFields = $this->csvImportModel->getEntityFieldMapping($import['entity_type'])['required'];
        $missingFields = array_diff($requiredFields, array_values($fieldMapping));

        if (!empty($missingFields)) {
            return $this->response->setJSON([
                'error' => 'Missing required field mappings: ' . implode(', ', $missingFields),
            ])->setStatusCode(400);
        }

        $this->csvImportModel->update($id, [
            'field_mapping' => $fieldMapping,
        ]);

        $this->csvImportModel->processImport($id);

        $import = $this->csvImportModel->find($id);

        return $this->response->setJSON($import);
    }

    /**
     * API: Cancel import
     */
    public function apiCancel(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return $this->response->setJSON([
                'error' => 'Import not found.',
            ])->setStatusCode(404);
        }

        if (!$this->guard->canCancel($user, $import)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        if ($this->csvImportModel->cancelImport($id)) {
            return $this->response->setJSON(['success' => true]);
        }

        return $this->response->setJSON([
            'error' => 'Failed to cancel import.',
        ])->setStatusCode(500);
    }

    /**
     * API: Delete import
     */
    public function apiDelete(string $id): ResponseInterface
    {
        $user = session()->get('user');

        $import = $this->csvImportModel->find($id);
        if (!$import) {
            return $this->response->setJSON([
                'error' => 'Import not found.',
            ])->setStatusCode(404);
        }

        if (!$this->guard->canDelete($user, $import)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        if (file_exists($import['file_path'])) {
            unlink($import['file_path']);
        }

        if ($this->csvImportModel->delete($id)) {
            return $this->response->setJSON(['success' => true]);
        }

        return $this->response->setJSON([
            'error' => 'Failed to delete import.',
        ])->setStatusCode(500);
    }

    /**
     * API: Download template
     */
    public function apiDownloadTemplate(string $entityType): ResponseInterface
    {
        $user = session()->get('user');

        if (!$this->guard->canCreate($user)) {
            return $this->response->setJSON([
                'error' => 'Permission denied',
            ])->setStatusCode(403);
        }

        if (!in_array($entityType, ['clients', 'contacts', 'notes'])) {
            return $this->response->setJSON([
                'error' => 'Invalid entity type.',
            ])->setStatusCode(400);
        }

        $exportModel = new \App\Models\CsvExportModel();
        $filepath = $exportModel->generateTemplate($entityType);

        if (!$filepath || !file_exists($filepath)) {
            return $this->response->setJSON([
                'error' => 'Failed to generate template.',
            ])->setStatusCode(500);
        }

        return $this->response->download($filepath, null)->setFileName($entityType . '_import_template.csv');
    }
}
