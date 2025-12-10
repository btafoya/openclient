<?php

namespace App\Models\Pipelines;

use CodeIgniter\Model;

class PipelineStageModel extends Model
{
    protected $table = 'pipeline_stages';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'pipeline_id',
        'name',
        'color',
        'probability',
        'sort_order',
        'is_won',
        'is_lost',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'pipeline_id' => 'required',
        'name' => 'required|min_length[1]|max_length[255]',
        'color' => 'permit_empty|max_length[7]',
        'probability' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    protected $validationMessages = [
        'pipeline_id' => [
            'required' => 'Pipeline ID is required',
        ],
        'name' => [
            'required' => 'Stage name is required',
        ],
        'probability' => [
            'greater_than_equal_to' => 'Probability must be between 0 and 100',
            'less_than_equal_to' => 'Probability must be between 0 and 100',
        ],
    ];

    protected $beforeInsert = ['generateUuid'];

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
     * Get stages by pipeline
     */
    public function getByPipeline(string $pipelineId): array
    {
        return $this->where('pipeline_id', $pipelineId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

    /**
     * Get won stage for pipeline
     */
    public function getWonStage(string $pipelineId): ?array
    {
        return $this->where('pipeline_id', $pipelineId)
            ->where('is_won', true)
            ->first();
    }

    /**
     * Get lost stage for pipeline
     */
    public function getLostStage(string $pipelineId): ?array
    {
        return $this->where('pipeline_id', $pipelineId)
            ->where('is_lost', true)
            ->first();
    }

    /**
     * Reorder stages
     */
    public function reorder(string $pipelineId, array $order): bool
    {
        $this->db->transStart();

        foreach ($order as $index => $id) {
            $this->where('pipeline_id', $pipelineId)
                ->where('id', $id)
                ->set('sort_order', $index)
                ->update();
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Get next sort order for pipeline
     */
    public function getNextSortOrder(string $pipelineId): int
    {
        $max = $this->selectMax('sort_order')
            ->where('pipeline_id', $pipelineId)
            ->first();

        return ($max['sort_order'] ?? -1) + 1;
    }

    /**
     * Count deals in stage
     */
    public function countDeals(string $stageId): int
    {
        $dealModel = new DealModel();
        return $dealModel->where('stage_id', $stageId)
            ->where('is_active', true)
            ->countAllResults();
    }

    /**
     * Get stage with deal count
     */
    public function getWithDealCount(string $pipelineId): array
    {
        $stages = $this->getByPipeline($pipelineId);

        foreach ($stages as &$stage) {
            $stage['deal_count'] = $this->countDeals($stage['id']);
        }

        return $stages;
    }
}
