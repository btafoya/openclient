<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Proposal Template Model
 *
 * Manages reusable proposal templates for agencies.
 *
 * RBAC Enforcement:
 * - Layer 1 (PostgreSQL RLS): Database enforces agency_id filtering automatically
 * - Layer 3 (Service Guards): ProposalGuard provides fine-grained authorization
 */
class ProposalTemplateModel extends Model
{
    protected $table = 'proposal_templates';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
        'name',
        'description',
        'content',
        'default_sections',
        'default_terms',
        'is_active',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'name' => 'required|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Template name is required',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setCreatedBy'];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT gen_random_uuid()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Set agency_id from session
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
     * Set created_by from session
     */
    protected function setCreatedBy(array $data): array
    {
        if (!isset($data['data']['created_by'])) {
            $user = session()->get('user');
            if ($user && isset($user['id'])) {
                $data['data']['created_by'] = $user['id'];
            }
        }
        return $data;
    }

    /**
     * Get active templates for current agency
     */
    public function getActiveTemplates(): array
    {
        return $this->where('is_active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Get template with parsed sections
     */
    public function getWithSections(string $id): ?array
    {
        $template = $this->find($id);
        if (!$template) {
            return null;
        }

        // Parse JSONB fields
        if (!empty($template['content']) && is_string($template['content'])) {
            $template['content'] = json_decode($template['content'], true);
        }
        if (!empty($template['default_sections']) && is_string($template['default_sections'])) {
            $template['default_sections'] = json_decode($template['default_sections'], true);
        }

        return $template;
    }

    /**
     * Duplicate a template
     */
    public function duplicate(string $id, string $newName): ?string
    {
        $template = $this->find($id);
        if (!$template) {
            return null;
        }

        unset($template['id'], $template['created_at'], $template['updated_at'], $template['deleted_at']);
        $template['name'] = $newName;

        return $this->insert($template, true);
    }
}
