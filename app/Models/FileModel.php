<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * File Model
 *
 * Manages file/document storage with multi-agency isolation via PostgreSQL RLS.
 */
class FileModel extends Model
{
    protected $table = 'files';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'uploaded_by',
        'entity_type',
        'entity_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'file_path',
        'disk',
        'folder',
        'description',
        'is_public',
        'download_count',
        'metadata',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'original_name' => 'required|max_length[255]',
        'stored_name' => 'required|max_length[255]',
        'mime_type' => 'required|max_length[100]',
        'file_size' => 'required|integer',
        'file_path' => 'required|max_length[500]',
    ];

    protected $skipValidation = false;
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setUploadedBy'];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

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

    protected function setUploadedBy(array $data): array
    {
        if (!isset($data['data']['uploaded_by'])) {
            $user = session()->get('user');
            if ($user && isset($user['id'])) {
                $data['data']['uploaded_by'] = $user['id'];
            }
        }
        return $data;
    }

    /**
     * Get files for a specific entity (client, project, invoice, etc.)
     */
    public function getForEntity(string $entityType, string $entityId): array
    {
        return $this->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get files by folder
     */
    public function getByFolder(?string $folder = null): array
    {
        $builder = $this->builder();

        if ($folder === null) {
            $builder->where('folder IS NULL');
        } else {
            $builder->where('folder', $folder);
        }

        return $builder->orderBy('original_name', 'ASC')->get()->getResultArray();
    }

    /**
     * Get recent files
     */
    public function getRecent(int $limit = 10): array
    {
        return $this->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Increment download count
     */
    public function incrementDownloads(string $id): bool
    {
        return $this->db->query(
            "UPDATE files SET download_count = download_count + 1 WHERE id = ?",
            [$id]
        );
    }

    /**
     * Get total storage used by current agency
     */
    public function getTotalStorageUsed(): int
    {
        $result = $this->selectSum('file_size', 'total')
            ->get()
            ->getRow();

        return (int) ($result->total ?? 0);
    }

    /**
     * Search files by name
     */
    public function search(string $term): array
    {
        return $this->like('original_name', $term)
            ->orLike('description', $term)
            ->orderBy('original_name', 'ASC')
            ->findAll();
    }

    /**
     * Get files by MIME type category
     */
    public function getByType(string $type): array
    {
        $mimePatterns = [
            'image' => 'image/%',
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats%'],
            'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml%'],
            'video' => 'video/%',
            'audio' => 'audio/%',
        ];

        $pattern = $mimePatterns[$type] ?? $type . '/%';

        if (is_array($pattern)) {
            $builder = $this->builder();
            $builder->groupStart();
            foreach ($pattern as $i => $p) {
                if ($i === 0) {
                    $builder->like('mime_type', str_replace('%', '', $p));
                } else {
                    $builder->orLike('mime_type', str_replace('%', '', $p));
                }
            }
            $builder->groupEnd();
            return $builder->orderBy('original_name', 'ASC')->get()->getResultArray();
        }

        return $this->like('mime_type', str_replace('%', '', $pattern))
            ->orderBy('original_name', 'ASC')
            ->findAll();
    }
}
