<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pipeline Stage Model
 *
 * Manages pipeline stages for sales pipelines.
 */
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
        'pipeline_id' => 'required|max_length[36]',
        'name' => 'required|max_length[255]|min_length[1]',
        'color' => 'permit_empty|max_length[7]',
        'probability' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
        'sort_order' => 'permit_empty|integer',
        'is_won' => 'permit_empty|in_list[0,1,true,false]',
        'is_lost' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Stage name is required',
        ],
        'pipeline_id' => [
            'required' => 'Pipeline ID is required',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid'];

    protected function generateUuid(array $data): array
    {
        if (!isset($data['data']['id'])) {
            $data['data']['id'] = $this->db->query("SELECT uuid_generate_v4()::text as id")->getRow()->id;
        }
        return $data;
    }

    /**
     * Get stages for a pipeline
     */
    public function getByPipelineId(string $pipelineId): array
    {
        return $this->where('pipeline_id', $pipelineId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

    /**
     * Get stage by ID
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get won stage for a pipeline
     */
    public function getWonStage(string $pipelineId): ?array
    {
        return $this->where('pipeline_id', $pipelineId)
            ->where('is_won', true)
            ->first();
    }

    /**
     * Get lost stage for a pipeline
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
    public function reorder(string $pipelineId, array $stageIds): bool
    {
        $this->db->transStart();

        foreach ($stageIds as $order => $stageId) {
            $this->where('pipeline_id', $pipelineId)
                ->where('id', $stageId)
                ->set('sort_order', $order)
                ->update();
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Get next sort order for a pipeline
     */
    public function getNextSortOrder(string $pipelineId): int
    {
        $max = $this->selectMax('sort_order')
            ->where('pipeline_id', $pipelineId)
            ->first();

        return ($max['sort_order'] ?? -1) + 1;
    }

    /**
     * Check if stage has deals
     */
    public function hasDeals(string $stageId): bool
    {
        $count = $this->db->table('deals')
            ->where('stage_id', $stageId)
            ->where('deleted_at', null)
            ->countAllResults();

        return $count > 0;
    }

    /**
     * Delete stage and move deals to another stage
     */
    public function deleteAndMoveDeals(string $stageId, string $targetStageId): bool
    {
        $this->db->transStart();

        // Move deals to target stage
        $this->db->table('deals')
            ->where('stage_id', $stageId)
            ->set('stage_id', $targetStageId)
            ->update();

        // Delete the stage
        $this->delete($stageId);

        $this->db->transComplete();

        return $this->db->transStatus();
    }
}
