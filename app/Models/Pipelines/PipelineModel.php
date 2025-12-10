<?php

namespace App\Models\Pipelines;

use CodeIgniter\Model;
use App\Models\Pipelines\PipelineStageModel;

class PipelineModel extends Model
{
    protected $table = 'pipelines';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
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
        'name' => 'required|min_length[1]|max_length[255]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Pipeline name is required',
            'min_length' => 'Pipeline name must be at least 1 character',
            'max_length' => 'Pipeline name cannot exceed 255 characters',
        ],
    ];

    protected $beforeInsert = ['generateUuid', 'setAgencyId'];
    protected $beforeUpdate = [];

    /**
     * Generate UUID for new records
     */
    protected function generateUuid(array $data): array
    {
        if (empty($data['data']['id'])) {
            helper('text');
            $data['data']['id'] = bin2hex(random_bytes(16));
            $data['data']['id'] = sprintf(
                '%s-%s-%s-%s-%s',
                substr($data['data']['id'], 0, 8),
                substr($data['data']['id'], 8, 4),
                substr($data['data']['id'], 12, 4),
                substr($data['data']['id'], 16, 4),
                substr($data['data']['id'], 20, 12)
            );
        }
        return $data;
    }

    /**
     * Set agency_id from session
     */
    protected function setAgencyId(array $data): array
    {
        if (empty($data['data']['agency_id'])) {
            $session = session();
            if ($session->has('agency_id')) {
                $data['data']['agency_id'] = $session->get('agency_id');
            }
        }
        return $data;
    }

    /**
     * Get pipeline with stages
     */
    public function getWithStages(string $id): ?array
    {
        $pipeline = $this->find($id);
        if (!$pipeline) {
            return null;
        }

        $stageModel = new PipelineStageModel();
        $pipeline['stages'] = $stageModel->where('pipeline_id', $id)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        return $pipeline;
    }

    /**
     * Get all pipelines with stages
     */
    public function getAllWithStages(): array
    {
        $pipelines = $this->where('is_active', true)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $stageModel = new PipelineStageModel();
        foreach ($pipelines as &$pipeline) {
            $pipeline['stages'] = $stageModel->where('pipeline_id', $pipeline['id'])
                ->orderBy('sort_order', 'ASC')
                ->findAll();
        }

        return $pipelines;
    }

    /**
     * Get default pipeline
     */
    public function getDefault(): ?array
    {
        return $this->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Set pipeline as default (unset others)
     */
    public function setAsDefault(string $id): bool
    {
        $this->db->transStart();

        // Unset current default
        $this->where('is_default', true)
            ->set('is_default', false)
            ->update();

        // Set new default
        $this->update($id, ['is_default' => true]);

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Create pipeline with default stages
     */
    public function createWithDefaultStages(array $data): ?string
    {
        $this->db->transStart();

        // Create pipeline
        $pipelineId = $this->insert($data, true);
        if (!$pipelineId) {
            $this->db->transRollback();
            return null;
        }

        // Create default stages
        $stageModel = new PipelineStageModel();
        $defaultStages = [
            ['name' => 'Lead', 'color' => '#6366f1', 'probability' => 10, 'sort_order' => 0],
            ['name' => 'Qualified', 'color' => '#8b5cf6', 'probability' => 25, 'sort_order' => 1],
            ['name' => 'Proposal', 'color' => '#a855f7', 'probability' => 50, 'sort_order' => 2],
            ['name' => 'Negotiation', 'color' => '#d946ef', 'probability' => 75, 'sort_order' => 3],
            ['name' => 'Won', 'color' => '#22c55e', 'probability' => 100, 'sort_order' => 4, 'is_won' => true],
            ['name' => 'Lost', 'color' => '#ef4444', 'probability' => 0, 'sort_order' => 5, 'is_lost' => true],
        ];

        foreach ($defaultStages as $stage) {
            $stage['pipeline_id'] = $pipelineId;
            if (!$stageModel->insert($stage)) {
                $this->db->transRollback();
                return null;
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() ? $pipelineId : null;
    }

    /**
     * Get pipeline statistics
     */
    public function getStats(string $id): array
    {
        $dealModel = new DealModel();

        $totalDeals = $dealModel->where('pipeline_id', $id)
            ->where('is_active', true)
            ->countAllResults();

        $totalValue = $dealModel->selectSum('value')
            ->where('pipeline_id', $id)
            ->where('is_active', true)
            ->first();

        $wonDeals = $dealModel->where('pipeline_id', $id)
            ->where('is_active', true)
            ->whereIn('stage_id', function($builder) use ($id) {
                return $builder->select('id')
                    ->from('pipeline_stages')
                    ->where('pipeline_id', $id)
                    ->where('is_won', true);
            })
            ->countAllResults();

        $wonValue = $dealModel->selectSum('value')
            ->where('pipeline_id', $id)
            ->where('is_active', true)
            ->whereIn('stage_id', function($builder) use ($id) {
                return $builder->select('id')
                    ->from('pipeline_stages')
                    ->where('pipeline_id', $id)
                    ->where('is_won', true);
            })
            ->first();

        return [
            'total_deals' => $totalDeals,
            'total_value' => (float) ($totalValue['value'] ?? 0),
            'won_deals' => $wonDeals,
            'won_value' => (float) ($wonValue['value'] ?? 0),
            'win_rate' => $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0,
        ];
    }

    /**
     * Search pipelines
     */
    public function search(string $query): array
    {
        return $this->like('name', $query)
            ->orLike('description', $query)
            ->where('is_active', true)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

    /**
     * Reorder pipelines
     */
    public function reorder(array $order): bool
    {
        $this->db->transStart();

        foreach ($order as $index => $id) {
            $this->update($id, ['sort_order' => $index]);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }
}
