<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pipeline Model
 *
 * Manages sales pipelines with multi-agency isolation via PostgreSQL RLS.
 */
class PipelineModel extends Model
{
    protected $table = 'pipelines';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'name',
        'description',
        'is_default',
        'sort_order',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'name' => 'required|max_length[255]|min_length[2]',
        'description' => 'permit_empty',
        'is_default' => 'permit_empty|in_list[0,1,true,false]',
        'sort_order' => 'permit_empty|integer',
        'is_active' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Pipeline name is required',
            'min_length' => 'Pipeline name must be at least 2 characters',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT uuid_generate_v4()::text as id")->getRow()->id;
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

    /**
     * Get pipelines for current agency
     */
    public function getForCurrentAgency(bool $activeOnly = true): array
    {
        $builder = $this->builder();

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return $builder->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /**
     * Get pipeline by ID
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get default pipeline
     */
    public function getDefault(): ?array
    {
        return $this->where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Set pipeline as default (unset others)
     */
    public function setAsDefault(string $id): bool
    {
        $this->db->transStart();

        // Unset all defaults
        $this->where('is_default', true)->set('is_default', false)->update();

        // Set new default
        $result = $this->update($id, ['is_default' => true]);

        $this->db->transComplete();

        return $result && $this->db->transStatus();
    }

    /**
     * Get pipeline with stages and deal counts
     */
    public function getWithStages(string $id): ?array
    {
        $pipeline = $this->find($id);
        if (!$pipeline) {
            return null;
        }

        $stageModel = new PipelineStageModel();
        $pipeline['stages'] = $stageModel->getByPipelineId($id);

        // Get deal counts per stage
        foreach ($pipeline['stages'] as &$stage) {
            $stage['deal_count'] = $this->db->table('deals')
                ->where('stage_id', $stage['id'])
                ->where('deleted_at', null)
                ->countAllResults();

            $stage['deal_value'] = $this->db->table('deals')
                ->selectSum('value')
                ->where('stage_id', $stage['id'])
                ->where('deleted_at', null)
                ->get()
                ->getRow()
                ->value ?? 0;
        }

        return $pipeline;
    }

    /**
     * Get pipeline statistics
     */
    public function getStats(string $pipelineId): array
    {
        $deals = $this->db->table('deals')
            ->select('
                COUNT(*) as total_deals,
                SUM(value) as total_value,
                AVG(value) as avg_deal_value,
                SUM(CASE WHEN stage_id IN (SELECT id FROM pipeline_stages WHERE pipeline_id = deals.pipeline_id AND is_won = true) THEN value ELSE 0 END) as won_value,
                SUM(CASE WHEN stage_id IN (SELECT id FROM pipeline_stages WHERE pipeline_id = deals.pipeline_id AND is_lost = true) THEN value ELSE 0 END) as lost_value
            ')
            ->where('pipeline_id', $pipelineId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $deals;
    }

    /**
     * Create pipeline with default stages
     */
    public function createWithDefaultStages(array $data): ?string
    {
        $this->db->transStart();

        $pipelineId = $this->insert($data, true);
        if (!$pipelineId) {
            $this->db->transRollback();
            return null;
        }

        // Create default stages
        $stageModel = new PipelineStageModel();
        $defaultStages = [
            ['name' => 'Lead', 'color' => '#6366f1', 'probability' => 10, 'sort_order' => 0],
            ['name' => 'Qualified', 'color' => '#8b5cf6', 'probability' => 30, 'sort_order' => 1],
            ['name' => 'Proposal', 'color' => '#a855f7', 'probability' => 50, 'sort_order' => 2],
            ['name' => 'Negotiation', 'color' => '#d946ef', 'probability' => 70, 'sort_order' => 3],
            ['name' => 'Won', 'color' => '#22c55e', 'probability' => 100, 'sort_order' => 4, 'is_won' => true],
            ['name' => 'Lost', 'color' => '#ef4444', 'probability' => 0, 'sort_order' => 5, 'is_lost' => true],
        ];

        foreach ($defaultStages as $stage) {
            $stage['pipeline_id'] = $pipelineId;
            $stageModel->insert($stage);
        }

        $this->db->transComplete();

        return $this->db->transStatus() ? $pipelineId : null;
    }
}
