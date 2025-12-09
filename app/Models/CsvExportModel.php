<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * CSV Export Model
 *
 * Handles CSV export functionality with streaming support for large datasets.
 * Supports clients, contacts, and notes exports with field selection.
 */
class CsvExportModel extends Model
{
    /**
     * Export field definitions for each entity type
     */
    protected $exportFields = [
        'clients' => [
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'company' => 'Company',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => 'Postal Code',
            'country' => 'Country',
            'notes' => 'Notes',
            'is_active' => 'Active',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
        ],
        'contacts' => [
            'id' => 'ID',
            'client_id' => 'Client ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'mobile' => 'Mobile',
            'job_title' => 'Job Title',
            'department' => 'Department',
            'is_primary' => 'Primary Contact',
            'notes' => 'Notes',
            'is_active' => 'Active',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
        ],
        'notes' => [
            'id' => 'ID',
            'user_id' => 'Author ID',
            'client_id' => 'Client ID',
            'contact_id' => 'Contact ID',
            'project_id' => 'Project ID',
            'subject' => 'Subject',
            'content' => 'Content',
            'is_pinned' => 'Pinned',
            'created_at' => 'Created Date',
            'updated_at' => 'Updated Date',
        ],
    ];

    /**
     * Get available export fields for an entity type
     */
    public function getExportFields(string $entityType): ?array
    {
        return $this->exportFields[$entityType] ?? null;
    }

    /**
     * Export data to CSV with streaming
     */
    public function export(string $entityType, array $options = []): bool
    {
        $fields = $options['fields'] ?? array_keys($this->exportFields[$entityType] ?? []);
        $filters = $options['filters'] ?? [];

        $entityModel = $this->getEntityModel($entityType);
        if (!$entityModel) {
            return false;
        }

        // Build query with filters
        $builder = $entityModel->builder();

        if (isset($filters['is_active'])) {
            $builder->where('is_active', $filters['is_active']);
        }

        if (isset($filters['created_after'])) {
            $builder->where('created_at >=', $filters['created_after']);
        }

        if (isset($filters['created_before'])) {
            $builder->where('created_at <=', $filters['created_before']);
        }

        if (isset($filters['search']) && !empty($filters['search'])) {
            $this->applySearchFilter($builder, $entityType, $filters['search']);
        }

        // Set filename
        $filename = $options['filename'] ?? $entityType . '_export_' . date('Y-m-d_His') . '.csv';
        $filepath = WRITEPATH . 'uploads/exports/' . $filename;

        // Ensure export directory exists
        $exportDir = WRITEPATH . 'uploads/exports/';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        // Open file for writing
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            return false;
        }

        // Write CSV header
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = $this->exportFields[$entityType][$field] ?? $field;
        }
        fputcsv($handle, $headers);

        // Stream data in batches
        $batchSize = 1000;
        $offset = 0;

        while (true) {
            $records = $builder->limit($batchSize, $offset)->get()->getResultArray();

            if (empty($records)) {
                break;
            }

            foreach ($records as $record) {
                $row = [];
                foreach ($fields as $field) {
                    $value = $record[$field] ?? '';

                    // Format boolean values
                    if (is_bool($value)) {
                        $value = $value ? 'Yes' : 'No';
                    }

                    // Format dates
                    if (in_array($field, ['created_at', 'updated_at']) && $value) {
                        $value = date('Y-m-d H:i:s', strtotime($value));
                    }

                    $row[] = $value;
                }
                fputcsv($handle, $row);
            }

            $offset += $batchSize;

            // Prevent memory leaks
            if ($offset % 10000 === 0) {
                gc_collect_cycles();
            }
        }

        fclose($handle);

        // Log export to timeline
        $this->logExport($entityType, $filename, $offset);

        return $filepath;
    }

    /**
     * Apply search filter to query builder
     */
    protected function applySearchFilter($builder, string $entityType, string $search): void
    {
        switch ($entityType) {
            case 'clients':
                $builder->groupStart()
                    ->like('name', $search)
                    ->orLike('email', $search)
                    ->orLike('company', $search)
                    ->groupEnd();
                break;

            case 'contacts':
                $builder->groupStart()
                    ->like('first_name', $search)
                    ->orLike('last_name', $search)
                    ->orLike('email', $search)
                    ->groupEnd();
                break;

            case 'notes':
                $builder->groupStart()
                    ->like('subject', $search)
                    ->orLike('content', $search)
                    ->groupEnd();
                break;
        }
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
     * Log export to timeline
     */
    protected function logExport(string $entityType, string $filename, int $rowCount): void
    {
        $user = session()->get('user');
        if (!$user) {
            return;
        }

        $timelineModel = new TimelineModel();

        $timelineModel->logEvent(
            userId: $user['id'],
            entityType: 'csv_export',
            entityId: uniqid('export_'),
            eventType: 'created',
            description: "Exported {$rowCount} {$entityType} to CSV: {$filename}",
            metadata: [
                'entity_type' => $entityType,
                'filename' => $filename,
                'row_count' => $rowCount,
            ]
        );
    }

    /**
     * Generate CSV template for import
     */
    public function generateTemplate(string $entityType): ?string
    {
        $fields = $this->exportFields[$entityType] ?? null;
        if (!$fields) {
            return null;
        }

        $filename = $entityType . '_import_template.csv';
        $filepath = WRITEPATH . 'uploads/templates/' . $filename;

        // Ensure template directory exists
        $templateDir = WRITEPATH . 'uploads/templates/';
        if (!is_dir($templateDir)) {
            mkdir($templateDir, 0755, true);
        }

        // Open file for writing
        $handle = fopen($filepath, 'w');
        if (!$handle) {
            return null;
        }

        // Write header only (no data rows)
        $headers = [];
        foreach (array_keys($fields) as $field) {
            // Skip ID fields in templates
            if ($field !== 'id') {
                $headers[] = $fields[$field];
            }
        }
        fputcsv($handle, $headers);

        fclose($handle);

        return $filepath;
    }

    /**
     * Clean up old export files (older than 7 days)
     */
    public function cleanupOldExports(): int
    {
        $exportDir = WRITEPATH . 'uploads/exports/';
        if (!is_dir($exportDir)) {
            return 0;
        }

        $deleted = 0;
        $cutoffTime = time() - (7 * 24 * 60 * 60); // 7 days ago

        $files = glob($exportDir . '*.csv');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
