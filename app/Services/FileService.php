<?php

namespace App\Services;

use App\Models\FileModel;
use App\Models\ActivityLogModel;
use CodeIgniter\Files\File;

/**
 * File Service
 *
 * Handles file upload, storage, and management operations.
 */
class FileService
{
    protected FileModel $fileModel;
    protected ActivityLogModel $activityLog;
    protected string $uploadPath;

    public function __construct()
    {
        $this->fileModel = new FileModel();
        $this->activityLog = new ActivityLogModel();
        $this->uploadPath = WRITEPATH . 'uploads/';

        // Ensure upload directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Upload a file
     */
    public function upload($file, array $options = []): array
    {
        try {
            // Validate file
            if (!$file->isValid()) {
                return [
                    'success' => false,
                    'error' => 'Invalid file: ' . $file->getErrorString(),
                ];
            }

            // Check file size (default 50MB max)
            $maxSize = getenv('UPLOAD_MAX_SIZE') ?: 52428800;
            if ($file->getSize() > $maxSize) {
                return [
                    'success' => false,
                    'error' => 'File size exceeds maximum allowed (' . $this->formatBytes($maxSize) . ')',
                ];
            }

            // Check for dangerous file types
            $dangerousTypes = ['application/x-php', 'application/x-httpd-php', 'text/x-php'];
            if (in_array($file->getMimeType(), $dangerousTypes)) {
                return [
                    'success' => false,
                    'error' => 'File type not allowed',
                ];
            }

            // Generate unique filename
            $storedName = $this->generateUniqueFilename($file);

            // Determine storage path
            $folder = $options['folder'] ?? date('Y/m');
            $relativePath = $folder . '/' . $storedName;
            $fullPath = $this->uploadPath . $relativePath;

            // Ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Move file to storage
            $file->move($dir, $storedName);

            // Create database record
            $fileData = [
                'entity_type' => $options['entity_type'] ?? null,
                'entity_id' => $options['entity_id'] ?? null,
                'original_name' => $file->getClientName(),
                'stored_name' => $storedName,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'file_path' => $relativePath,
                'disk' => 'local',
                'folder' => $options['folder'] ?? $folder,
                'description' => $options['description'] ?? null,
                'is_public' => $options['is_public'] ?? false,
            ];

            if ($this->fileModel->insert($fileData)) {
                $fileId = $this->fileModel->getInsertID();
                $savedFile = $this->fileModel->find($fileId);

                // Log activity
                $this->activityLog->logCreated('file', $fileId, $fileData['original_name']);

                return [
                    'success' => true,
                    'file' => $savedFile,
                ];
            }

            // Clean up file if database insert failed
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return [
                'success' => false,
                'error' => 'Failed to save file record',
            ];

        } catch (\Exception $e) {
            log_message('error', 'File upload failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a file
     */
    public function delete(string $fileId): array
    {
        try {
            $file = $this->fileModel->find($fileId);

            if (!$file) {
                return [
                    'success' => false,
                    'error' => 'File not found',
                ];
            }

            // Delete physical file
            $fullPath = $this->uploadPath . $file['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Delete database record
            if ($this->fileModel->delete($fileId, true)) {
                // Log activity
                $this->activityLog->logDeleted('file', $fileId, $file['original_name']);

                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => 'Failed to delete file record',
            ];

        } catch (\Exception $e) {
            log_message('error', 'File deletion failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Delete failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get full path to a file
     */
    public function getFullPath(array $file): string
    {
        return $this->uploadPath . $file['file_path'];
    }

    /**
     * Get URL for a file
     */
    public function getUrl(array $file): string
    {
        if ($file['is_public']) {
            return base_url('uploads/' . $file['file_path']);
        }

        return site_url('api/files/' . $file['id'] . '/download');
    }

    /**
     * Generate unique filename
     */
    protected function generateUniqueFilename($file): string
    {
        $extension = $file->guessExtension() ?: pathinfo($file->getClientName(), PATHINFO_EXTENSION);
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }

    /**
     * Copy file to another entity
     */
    public function copyToEntity(string $fileId, string $entityType, string $entityId): array
    {
        $file = $this->fileModel->find($fileId);

        if (!$file) {
            return [
                'success' => false,
                'error' => 'File not found',
            ];
        }

        // Get source file path
        $sourcePath = $this->uploadPath . $file['file_path'];

        if (!file_exists($sourcePath)) {
            return [
                'success' => false,
                'error' => 'Source file not found on disk',
            ];
        }

        // Generate new stored name
        $extension = pathinfo($file['stored_name'], PATHINFO_EXTENSION);
        $newStoredName = bin2hex(random_bytes(16)) . '.' . $extension;

        // Copy to same folder structure
        $folder = $file['folder'] ?? date('Y/m');
        $newPath = $folder . '/' . $newStoredName;
        $newFullPath = $this->uploadPath . $newPath;

        // Ensure directory exists
        $dir = dirname($newFullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Copy file
        if (!copy($sourcePath, $newFullPath)) {
            return [
                'success' => false,
                'error' => 'Failed to copy file',
            ];
        }

        // Create new database record
        $newFileData = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'original_name' => $file['original_name'],
            'stored_name' => $newStoredName,
            'mime_type' => $file['mime_type'],
            'file_size' => $file['file_size'],
            'file_path' => $newPath,
            'disk' => $file['disk'],
            'folder' => $folder,
            'description' => $file['description'],
            'is_public' => $file['is_public'],
        ];

        if ($this->fileModel->insert($newFileData)) {
            $newFileId = $this->fileModel->getInsertID();
            return [
                'success' => true,
                'file' => $this->fileModel->find($newFileId),
            ];
        }

        // Clean up copied file
        if (file_exists($newFullPath)) {
            unlink($newFullPath);
        }

        return [
            'success' => false,
            'error' => 'Failed to save file record',
        ];
    }

    /**
     * Get allowed MIME types
     */
    public function getAllowedMimeTypes(): array
    {
        return [
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            // Text
            'text/plain',
            'text/csv',
            'text/markdown',
            // Archives
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            // Audio/Video
            'audio/mpeg',
            'audio/wav',
            'video/mp4',
            'video/webm',
        ];
    }
}
