<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * CSV Import Model
 *
 * Manages CSV import operations with validation, batch processing, and error tracking.
 * Supports clients, contacts, and notes imports with field mapping.
 */
class CsvImportModel extends Model
{
    protected $table = 'csv_imports';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'user_id',
        'entity_type',
        'filename',
        'file_path',
        'file_size',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'status',
        'field_mapping',
        'validation_errors',
        'import_options',
        'started_at',
        'completed_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'user_id' => 'required|is_not_unique[users.id]',
        'entity_type' => 'required|in_list[clients,contacts,notes]',
        'filename' => 'required|max_length[255]',
        'file_path' => 'required|max_length[500]',
        'file_size' => 'required|integer',
        'status' => 'permit_empty|in_list[pending,processing,completed,failed,cancelled]',
    ];

    protected $validationMessages = [
        'entity_type' => [
            'in_list' => 'Entity type must be one of: clients, contacts, notes',
        ],
    ];

    // Callbacks
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $afterInsert = ['logImportCreated'];
    protected $afterUpdate = ['logImportUpdated'];

    /**
     * Supported entity types and their field mappings
     */
    protected $entityFieldMappings = [
        'clients' => [
            'required' => ['name'],
            'optional' => ['email', 'phone', 'company', 'address', 'city', 'state', 'postal_code', 'country', 'notes', 'is_active'],
        ],
        'contacts' => [
            'required' => ['first_name', 'last_name'],
            'optional' => ['email', 'phone', 'mobile', 'job_title', 'department', 'is_primary', 'notes', 'is_active', 'client_id'],
        ],
        'notes' => [
            'required' => ['content'],
            'optional' => ['subject', 'client_id', 'contact_id', 'project_id', 'is_pinned'],
        ],
    ];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query('SELECT uuid_generate_v4() as id')->getRow()->id;
        }
        return $data;
    }

    /**
     * Auto-set agency_id from session
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
     * Get import history for current agency
     */
    public function getHistory(int $limit = 50): array
    {
        return $this->select('csv_imports.*, users.first_name as user_first_name, users.last_name as user_last_name')
            ->join('users', 'users.id = csv_imports.user_id', 'left')
            ->orderBy('csv_imports.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get imports by status
     */
    public function getByStatus(string $status): array
    {
        return $this->where('status', $status)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get imports by entity type
     */
    public function getByEntityType(string $entityType): array
    {
        return $this->where('entity_type', $entityType)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get field requirements for an entity type
     */
    public function getEntityFieldMapping(string $entityType): ?array
    {
        return $this->entityFieldMappings[$entityType] ?? null;
    }

    /**
     * Validate CSV headers against entity requirements
     */
    public function validateHeaders(string $entityType, array $headers): array
    {
        $mapping = $this->getEntityFieldMapping($entityType);
        if (!$mapping) {
            return [
                'valid' => false,
                'errors' => ["Invalid entity type: {$entityType}"],
            ];
        }

        $errors = [];
        $missingRequired = array_diff($mapping['required'], $headers);

        if (!empty($missingRequired)) {
            $errors[] = 'Missing required fields: ' . implode(', ', $missingRequired);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'required_fields' => $mapping['required'],
            'optional_fields' => $mapping['optional'],
        ];
    }

    /**
     * Validate a single CSV row
     */
    public function validateRow(string $entityType, array $row, int $rowNumber): array
    {
        $errors = [];
        $mapping = $this->getEntityFieldMapping($entityType);

        if (!$mapping) {
            return [
                'valid' => false,
                'errors' => ["Invalid entity type"],
            ];
        }

        // Check required fields
        foreach ($mapping['required'] as $field) {
            if (empty($row[$field])) {
                $errors[] = "Row {$rowNumber}: Missing required field '{$field}'";
            }
        }

        // Entity-specific validation
        switch ($entityType) {
            case 'clients':
                if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$rowNumber}: Invalid email format";
                }
                break;

            case 'contacts':
                if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row {$rowNumber}: Invalid email format";
                }
                break;

            case 'notes':
                if (empty($row['client_id']) && empty($row['contact_id']) && empty($row['project_id'])) {
                    $errors[] = "Row {$rowNumber}: Note must be associated with client, contact, or project";
                }
                break;
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Process CSV import in batches
     */
    public function processImport(string $importId, int $batchSize = 100): bool
    {
        $import = $this->find($importId);
        if (!$import) {
            return false;
        }

        // Update status to processing
        $this->update($importId, [
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
        ]);

        $filePath = $import['file_path'];
        if (!file_exists($filePath)) {
            $this->update($importId, [
                'status' => 'failed',
                'validation_errors' => ['file' => 'CSV file not found'],
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return false;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->update($importId, [
                'status' => 'failed',
                'validation_errors' => ['file' => 'Unable to open CSV file'],
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return false;
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            $this->update($importId, [
                'status' => 'failed',
                'validation_errors' => ['file' => 'Empty CSV file'],
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return false;
        }

        // Validate headers
        $headerValidation = $this->validateHeaders($import['entity_type'], $headers);
        if (!$headerValidation['valid']) {
            fclose($handle);
            $this->update($importId, [
                'status' => 'failed',
                'validation_errors' => ['headers' => $headerValidation['errors']],
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
            return false;
        }

        // Process rows in batches
        $entityModel = $this->getEntityModel($import['entity_type']);
        $processedRows = 0;
        $failedRows = 0;
        $validationErrors = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Create associative array from CSV row
            $row = array_combine($headers, $data);

            // Validate row
            $rowValidation = $this->validateRow($import['entity_type'], $row, $rowNumber);
            if (!$rowValidation['valid']) {
                $failedRows++;
                $validationErrors["row_{$rowNumber}"] = $rowValidation['errors'];
                continue;
            }

            // Import row
            try {
                if ($import['import_options']['update_existing'] ?? false) {
                    // Check for existing record and update if found
                    $existing = $this->findExistingRecord($import['entity_type'], $row);
                    if ($existing) {
                        $entityModel->update($existing['id'], $row);
                    } else {
                        $entityModel->insert($row);
                    }
                } elseif ($import['import_options']['skip_duplicates'] ?? false) {
                    // Skip if duplicate found
                    $existing = $this->findExistingRecord($import['entity_type'], $row);
                    if (!$existing) {
                        $entityModel->insert($row);
                    }
                } else {
                    // Always insert
                    $entityModel->insert($row);
                }
                $processedRows++;
            } catch (\Exception $e) {
                $failedRows++;
                $validationErrors["row_{$rowNumber}"] = [$e->getMessage()];
            }

            // Update progress periodically
            if ($rowNumber % $batchSize === 0) {
                $this->update($importId, [
                    'processed_rows' => $processedRows,
                    'failed_rows' => $failedRows,
                    'validation_errors' => $validationErrors,
                ]);
            }
        }

        fclose($handle);

        // Final update
        $this->update($importId, [
            'status' => 'completed',
            'total_rows' => $rowNumber - 1,
            'processed_rows' => $processedRows,
            'failed_rows' => $failedRows,
            'validation_errors' => $validationErrors,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Find existing record for duplicate detection
     */
    protected function findExistingRecord(string $entityType, array $row): ?array
    {
        $entityModel = $this->getEntityModel($entityType);

        switch ($entityType) {
            case 'clients':
                return $entityModel->where('email', $row['email'])->first();

            case 'contacts':
                return $entityModel->where('email', $row['email'])->first();

            case 'notes':
                // Notes don't have a unique identifier for duplicate detection
                return null;
        }

        return null;
    }

    /**
     * Get entity model instance
     */
    protected function getEntityModel(string $entityType): ?Model
    {
        switch ($entityType) {
            case 'clients':
                return new ClientModel();

            case 'contacts':
                return new ContactModel();

            case 'notes':
                return new NoteModel();
        }

        return null;
    }

    /**
     * Cancel pending import
     */
    public function cancelImport(string $id): bool
    {
        $import = $this->find($id);
        if (!$import || $import['status'] !== 'pending') {
            return false;
        }

        return $this->update($id, ['status' => 'cancelled']);
    }

    /**
     * Get import statistics
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_imports' => $this->countAllResults(false),
            'by_status' => [],
            'by_entity_type' => [],
            'total_rows_processed' => 0,
            'total_rows_failed' => 0,
        ];

        // Status breakdown
        $statusCounts = $this->select('status, COUNT(*) as count')
            ->groupBy('status')
            ->findAll();

        foreach ($statusCounts as $row) {
            $stats['by_status'][$row['status']] = (int) $row['count'];
        }

        // Entity type breakdown
        $entityCounts = $this->select('entity_type, COUNT(*) as count')
            ->groupBy('entity_type')
            ->findAll();

        foreach ($entityCounts as $row) {
            $stats['by_entity_type'][$row['entity_type']] = (int) $row['count'];
        }

        // Total rows
        $rowTotals = $this->select('SUM(processed_rows) as processed, SUM(failed_rows) as failed')
            ->first();

        $stats['total_rows_processed'] = (int) ($rowTotals['processed'] ?? 0);
        $stats['total_rows_failed'] = (int) ($rowTotals['failed'] ?? 0);

        return $stats;
    }

    /**
     * Log import creation to timeline
     */
    protected function logImportCreated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id'])) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $entityType = $data['data']['entity_type'] ?? 'unknown';

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'csv_import',
            entityId: $data['id'],
            eventType: 'created',
            description: "Started CSV import for {$entityType}: {$data['data']['filename']}"
        );

        return $data;
    }

    /**
     * Log import status updates to timeline
     */
    protected function logImportUpdated(array $data): array
    {
        $user = session()->get('user');
        if (!$user || !isset($data['id']) || empty($data['id'])) {
            return $data;
        }

        $importId = is_array($data['id']) ? $data['id'][0] : $data['id'];
        $import = $this->find($importId);
        if (!$import) {
            return $data;
        }

        // Only log status changes
        if (!isset($data['data']['status']) || $data['data']['status'] === $import['status']) {
            return $data;
        }

        $timelineModel = new TimelineModel();
        $status = $data['data']['status'];
        $entityType = $import['entity_type'];

        $description = match($status) {
            'processing' => "Processing CSV import for {$entityType}",
            'completed' => "Completed CSV import for {$entityType}: {$import['processed_rows']} rows processed, {$import['failed_rows']} failed",
            'failed' => "Failed CSV import for {$entityType}",
            'cancelled' => "Cancelled CSV import for {$entityType}",
            default => "Updated CSV import status to {$status}",
        };

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'csv_import',
            entityId: $importId,
            eventType: 'status_changed',
            description: $description,
            metadata: [
                'status' => $status,
                'processed_rows' => $import['processed_rows'],
                'failed_rows' => $import['failed_rows'],
            ]
        );

        return $data;
    }
}
