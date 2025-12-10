<?php

namespace App\Controllers\Files;

use App\Controllers\BaseController;
use App\Models\FileModel;
use App\Services\FileService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * File Controller
 *
 * Handles file upload, download, and management operations.
 */
class FileController extends BaseController
{
    protected FileModel $fileModel;
    protected FileService $fileService;

    public function __construct()
    {
        $this->fileModel = new FileModel();
        $this->fileService = new FileService();
    }

    /**
     * List files with optional filters
     */
    public function index(): ResponseInterface
    {
        $folder = $this->request->getGet('folder');
        $entityType = $this->request->getGet('entity_type');
        $entityId = $this->request->getGet('entity_id');
        $type = $this->request->getGet('type');

        if ($entityType && $entityId) {
            $files = $this->fileModel->getForEntity($entityType, $entityId);
        } elseif ($folder !== null) {
            $files = $this->fileModel->getByFolder($folder);
        } elseif ($type) {
            $files = $this->fileModel->getByType($type);
        } else {
            $files = $this->fileModel->orderBy('created_at', 'DESC')->findAll();
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $files,
        ]);
    }

    /**
     * Upload a file
     */
    public function upload(): ResponseInterface
    {
        $file = $this->request->getFile('file');

        if (!$file || !$file->isValid()) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'No valid file uploaded',
            ])->setStatusCode(400);
        }

        $options = [
            'entity_type' => $this->request->getPost('entity_type'),
            'entity_id' => $this->request->getPost('entity_id'),
            'folder' => $this->request->getPost('folder'),
            'description' => $this->request->getPost('description'),
            'is_public' => (bool) $this->request->getPost('is_public'),
        ];

        $result = $this->fileService->upload($file, $options);

        if ($result['success']) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $result['file'],
                'message' => 'File uploaded successfully',
            ])->setStatusCode(201);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => $result['error'],
        ])->setStatusCode(400);
    }

    /**
     * Get file details
     */
    public function show(string $id): ResponseInterface
    {
        $file = $this->fileModel->find($id);

        if (!$file) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'File not found',
            ])->setStatusCode(404);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $file,
        ]);
    }

    /**
     * Download a file
     */
    public function download(string $id): ResponseInterface
    {
        $file = $this->fileModel->find($id);

        if (!$file) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'File not found',
            ])->setStatusCode(404);
        }

        $fullPath = $this->fileService->getFullPath($file);

        if (!file_exists($fullPath)) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'File not found on disk',
            ])->setStatusCode(404);
        }

        // Increment download count
        $this->fileModel->incrementDownloads($id);

        return $this->response
            ->setHeader('Content-Type', $file['mime_type'])
            ->setHeader('Content-Disposition', 'attachment; filename="' . $file['original_name'] . '"')
            ->setHeader('Content-Length', (string) $file['file_size'])
            ->setBody(file_get_contents($fullPath));
    }

    /**
     * Update file metadata
     */
    public function update(string $id): ResponseInterface
    {
        $file = $this->fileModel->find($id);

        if (!$file) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'File not found',
            ])->setStatusCode(404);
        }

        $data = $this->request->getJSON(true);

        // Only allow updating certain fields
        $allowedFields = ['description', 'folder', 'is_public'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'No valid fields to update',
            ])->setStatusCode(400);
        }

        if ($this->fileModel->update($id, $updateData)) {
            return $this->response->setJSON([
                'success' => true,
                'data' => $this->fileModel->find($id),
                'message' => 'File updated successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => 'Failed to update file',
        ])->setStatusCode(500);
    }

    /**
     * Delete a file
     */
    public function delete(string $id): ResponseInterface
    {
        $file = $this->fileModel->find($id);

        if (!$file) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'File not found',
            ])->setStatusCode(404);
        }

        $result = $this->fileService->delete($id);

        if ($result['success']) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'error' => $result['error'],
        ])->setStatusCode(500);
    }

    /**
     * Search files
     */
    public function search(): ResponseInterface
    {
        $term = $this->request->getGet('q');

        if (!$term) {
            return $this->response->setJSON([
                'success' => false,
                'error' => 'Search term required',
            ])->setStatusCode(400);
        }

        $files = $this->fileModel->search($term);

        return $this->response->setJSON([
            'success' => true,
            'data' => $files,
        ]);
    }

    /**
     * Get recent files
     */
    public function recent(): ResponseInterface
    {
        $limit = (int) ($this->request->getGet('limit') ?? 10);
        $files = $this->fileModel->getRecent($limit);

        return $this->response->setJSON([
            'success' => true,
            'data' => $files,
        ]);
    }

    /**
     * Get storage statistics
     */
    public function stats(): ResponseInterface
    {
        $totalSize = $this->fileModel->getTotalStorageUsed();
        $fileCount = $this->fileModel->countAllResults();

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'file_count' => $fileCount,
            ],
        ]);
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
