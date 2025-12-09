<?php

namespace App\Domain\CsvImport\Authorization;

use App\Domain\Common\Authorization\AuthorizationGuardInterface;

/**
 * CSV Import Authorization Guard
 *
 * Layer 3 RBAC authorization for CSV import/export operations.
 * Enforces role-based permissions for data import and export.
 *
 * Permission Matrix:
 * - owner: Full access (import, export, view all, delete)
 * - agency: Full access within own agency
 * - direct_client: View own imports, no import/export
 * - end_client: View own imports, no import/export
 */
class CsvImportGuard implements AuthorizationGuardInterface
{
    /**
     * Check if user can view an import record
     */
    public function canView(array $user, $csvImport): bool
    {
        // Owner can view all imports
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency staff can view imports within their agency
        if ($user['role'] === 'agency') {
            return isset($csvImport['agency_id']) && $csvImport['agency_id'] === $user['agency_id'];
        }

        // Clients can view imports they created
        if (in_array($user['role'], ['direct_client', 'end_client'])) {
            return isset($csvImport['user_id']) && $csvImport['user_id'] === $user['id'];
        }

        return false;
    }

    /**
     * Check if user can create a CSV import
     */
    public function canCreate(array $user): bool
    {
        // Only owner and agency staff can import data
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can edit an import record
     * (Note: Imports themselves cannot be edited, but status can be updated)
     */
    public function canEdit(array $user, $csvImport): bool
    {
        // Owner can edit any import
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency staff can edit imports within their agency
        if ($user['role'] === 'agency') {
            return isset($csvImport['agency_id']) && $csvImport['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Check if user can delete an import record
     */
    public function canDelete(array $user, $csvImport): bool
    {
        // Owner can delete any import
        if ($user['role'] === 'owner') {
            return true;
        }

        // Agency staff can delete imports within their agency
        if ($user['role'] === 'agency') {
            return isset($csvImport['agency_id']) && $csvImport['agency_id'] === $user['agency_id'];
        }

        return false;
    }

    /**
     * Check if user can export data
     */
    public function canExport(array $user): bool
    {
        // Only owner and agency staff can export data
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Check if user can cancel an import
     */
    public function canCancel(array $user, $csvImport): bool
    {
        // Must be able to edit the import
        if (!$this->canEdit($user, $csvImport)) {
            return false;
        }

        // Can only cancel pending imports
        return isset($csvImport['status']) && $csvImport['status'] === 'pending';
    }

    /**
     * Check if user can view import history
     */
    public function canViewHistory(array $user): bool
    {
        // All authenticated users can view their own import history
        return in_array($user['role'], ['owner', 'agency', 'direct_client', 'end_client']);
    }

    /**
     * Check if user can download export file
     */
    public function canDownloadExport(array $user): bool
    {
        // Only owner and agency staff can download export files
        return in_array($user['role'], ['owner', 'agency']);
    }

    /**
     * Filter imports list to only show viewable records
     */
    public function filterViewableImports(array $user, array $imports): array
    {
        return array_filter($imports, function ($import) use ($user) {
            return $this->canView($user, $import);
        });
    }

    /**
     * Check if user can import specific entity type
     */
    public function canImportEntityType(array $user, string $entityType): bool
    {
        // Must have general import permission
        if (!$this->canCreate($user)) {
            return false;
        }

        // All entity types allowed for owner and agency
        return true;
    }

    /**
     * Check if user can export specific entity type
     */
    public function canExportEntityType(array $user, string $entityType): bool
    {
        // Must have general export permission
        if (!$this->canExport($user)) {
            return false;
        }

        // All entity types allowed for owner and agency
        return true;
    }

    /**
     * Validate import file upload
     */
    public function validateFileUpload(array $file): array
    {
        $errors = [];

        // Check if file exists
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            $errors[] = 'No file was uploaded';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds 10MB limit';
        }

        // Check file extension
        $allowedExtensions = ['csv', 'txt'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'Only CSV files are allowed';
        }

        // Check MIME type
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes)) {
            $errors[] = 'Invalid file type. Please upload a CSV file.';
        }

        // Check if file is readable
        if (!is_readable($file['tmp_name'])) {
            $errors[] = 'File is not readable';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get safe filename for uploaded file
     */
    public function getSafeFilename(string $originalFilename): string
    {
        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalFilename);

        // Add timestamp to prevent collisions
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        return $basename . '_' . time() . '.' . $extension;
    }

    /**
     * Get upload directory path
     */
    public function getUploadDirectory(): string
    {
        $uploadDir = WRITEPATH . 'uploads/csv/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return $uploadDir;
    }
}
