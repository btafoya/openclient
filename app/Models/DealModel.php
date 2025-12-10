<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Deal Model
 *
 * Manages sales deals with multi-agency isolation via PostgreSQL RLS.
 */
class DealModel extends Model
{
    protected $table = 'deals';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'pipeline_id',
        'stage_id',
        'client_id',
        'contact_id',
        'assigned_to',
        'name',
        'description',
        'value',
        'currency',
        'expected_close_date',
        'actual_close_date',
        'won_reason',
        'lost_reason',
        'probability',
        'source',
        'priority',
        'sort_order',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'pipeline_id' => 'required|max_length[36]',
        'stage_id' => 'required|max_length[36]',
        'name' => 'required|max_length[255]|min_length[2]',
        'description' => 'permit_empty',
        'value' => 'permit_empty|decimal',
        'currency' => 'permit_empty|max_length[3]',
        'expected_close_date' => 'permit_empty|valid_date',
        'actual_close_date' => 'permit_empty|valid_date',
        'priority' => 'permit_empty|in_list[low,medium,high]',
        'probability' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Deal name is required',
            'min_length' => 'Deal name must be at least 2 characters',
        ],
        'pipeline_id' => [
            'required' => 'Pipeline is required',
        ],
        'stage_id' => [
            'required' => 'Stage is required',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setProbability'];
    protected $afterInsert = ['logDealCreated'];
    protected $beforeUpdate = ['updateProbability'];
    protected $afterUpdate = ['logDealUpdated'];

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

    protected function setProbability(array $data): array
    {
        if (!isset($data['data']['probability']) && isset($data['data']['stage_id'])) {
            $stageModel = new PipelineStageModel();
            $stage = $stageModel->find($data['data']['stage_id']);
            if ($stage) {
                $data['data']['probability'] = $stage['probability'];
            }
        }
        return $data;
    }

    protected function updateProbability(array $data): array
    {
        if (isset($data['data']['stage_id'])) {
            $stageModel = new PipelineStageModel();
            $stage = $stageModel->find($data['data']['stage_id']);
            if ($stage) {
                $data['data']['probability'] = $stage['probability'];
            }
        }
        return $data;
    }

    /**
     * Get deals for current agency
     */
    public function getForCurrentAgency(array $filters = []): array
    {
        $builder = $this->builder();

        if (!empty($filters['pipeline_id'])) {
            $builder->where('pipeline_id', $filters['pipeline_id']);
        }

        if (!empty($filters['stage_id'])) {
            $builder->where('stage_id', $filters['stage_id']);
        }

        if (!empty($filters['client_id'])) {
            $builder->where('client_id', $filters['client_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $builder->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['priority'])) {
            $builder->where('priority', $filters['priority']);
        }

        if (isset($filters['is_active'])) {
            $builder->where('is_active', $filters['is_active']);
        }

        return $builder->orderBy('sort_order', 'ASC')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get deal by ID with related data
     */
    public function getById(string $id): ?array
    {
        return $this->find($id);
    }

    /**
     * Get deal with all related data
     */
    public function getWithRelated(string $id): ?array
    {
        $deal = $this->find($id);
        if (!$deal) {
            return null;
        }

        // Get pipeline
        $pipelineModel = new PipelineModel();
        $deal['pipeline'] = $pipelineModel->find($deal['pipeline_id']);

        // Get stage
        $stageModel = new PipelineStageModel();
        $deal['stage'] = $stageModel->find($deal['stage_id']);

        // Get client
        if ($deal['client_id']) {
            $clientModel = new ClientModel();
            $deal['client'] = $clientModel->find($deal['client_id']);
        }

        // Get contact
        if ($deal['contact_id']) {
            $contactModel = new ContactModel();
            $deal['contact'] = $contactModel->find($deal['contact_id']);
        }

        // Get activities
        $activityModel = new DealActivityModel();
        $deal['activities'] = $activityModel->getByDealId($id);

        return $deal;
    }

    /**
     * Get deals by pipeline with stages
     */
    public function getByPipelineGroupedByStage(string $pipelineId): array
    {
        $stageModel = new PipelineStageModel();
        $stages = $stageModel->getByPipelineId($pipelineId);

        $result = [];
        foreach ($stages as $stage) {
            $deals = $this->where('pipeline_id', $pipelineId)
                ->where('stage_id', $stage['id'])
                ->where('is_active', true)
                ->orderBy('sort_order', 'ASC')
                ->findAll();

            $result[] = [
                'stage' => $stage,
                'deals' => $deals,
                'total_value' => array_sum(array_column($deals, 'value')),
            ];
        }

        return $result;
    }

    /**
     * Move deal to stage
     */
    public function moveToStage(string $dealId, string $stageId): bool
    {
        $stageModel = new PipelineStageModel();
        $stage = $stageModel->find($stageId);

        if (!$stage) {
            return false;
        }

        $updateData = ['stage_id' => $stageId];

        // If moving to won/lost stage, set actual close date
        if ($stage['is_won'] || $stage['is_lost']) {
            $updateData['actual_close_date'] = date('Y-m-d');
        }

        return $this->update($dealId, $updateData);
    }

    /**
     * Mark deal as won
     */
    public function markWon(string $dealId, ?string $reason = null): bool
    {
        $deal = $this->find($dealId);
        if (!$deal) {
            return false;
        }

        $stageModel = new PipelineStageModel();
        $wonStage = $stageModel->getWonStage($deal['pipeline_id']);

        if (!$wonStage) {
            return false;
        }

        return $this->update($dealId, [
            'stage_id' => $wonStage['id'],
            'actual_close_date' => date('Y-m-d'),
            'won_reason' => $reason,
            'probability' => 100,
        ]);
    }

    /**
     * Mark deal as lost
     */
    public function markLost(string $dealId, ?string $reason = null): bool
    {
        $deal = $this->find($dealId);
        if (!$deal) {
            return false;
        }

        $stageModel = new PipelineStageModel();
        $lostStage = $stageModel->getLostStage($deal['pipeline_id']);

        if (!$lostStage) {
            return false;
        }

        return $this->update($dealId, [
            'stage_id' => $lostStage['id'],
            'actual_close_date' => date('Y-m-d'),
            'lost_reason' => $reason,
            'probability' => 0,
        ]);
    }

    /**
     * Convert deal to project
     */
    public function convertToProject(string $dealId): ?string
    {
        $deal = $this->getWithRelated($dealId);
        if (!$deal) {
            return null;
        }

        $this->db->transStart();

        $projectModel = new ProjectModel();
        $projectData = [
            'client_id' => $deal['client_id'],
            'name' => $deal['name'],
            'description' => $deal['description'],
            'status' => 'active',
            'budget' => $deal['value'],
            'is_billable' => true,
            'is_active' => true,
        ];

        $projectId = $projectModel->insert($projectData, true);

        if ($projectId) {
            // Log activity
            $activityModel = new DealActivityModel();
            $activityModel->logActivity($dealId, 'conversion', 'Converted to Project', [
                'project_id' => $projectId,
            ]);
        }

        $this->db->transComplete();

        return $this->db->transStatus() ? $projectId : null;
    }

    /**
     * Get deal statistics
     */
    public function getStats(array $filters = []): array
    {
        $builder = $this->builder();

        if (!empty($filters['pipeline_id'])) {
            $builder->where('pipeline_id', $filters['pipeline_id']);
        }

        if (!empty($filters['from_date'])) {
            $builder->where('created_at >=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $builder->where('created_at <=', $filters['to_date']);
        }

        $stats = $builder->select('
            COUNT(*) as total_deals,
            SUM(value) as total_value,
            AVG(value) as avg_value,
            SUM(CASE WHEN probability = 100 THEN value ELSE 0 END) as won_value,
            SUM(CASE WHEN probability = 0 AND actual_close_date IS NOT NULL THEN value ELSE 0 END) as lost_value,
            COUNT(CASE WHEN probability = 100 THEN 1 END) as won_count,
            COUNT(CASE WHEN probability = 0 AND actual_close_date IS NOT NULL THEN 1 END) as lost_count
        ')->get()->getRowArray();

        // Calculate weighted pipeline value
        $weighted = $this->builder()
            ->select('SUM(value * probability / 100) as weighted_value')
            ->where('actual_close_date', null)
            ->get()
            ->getRow()
            ->weighted_value ?? 0;

        $stats['weighted_value'] = $weighted;
        $stats['win_rate'] = $stats['total_deals'] > 0
            ? ($stats['won_count'] / ($stats['won_count'] + $stats['lost_count'])) * 100
            : 0;

        return $stats;
    }

    /**
     * Reorder deals within a stage
     */
    public function reorderInStage(string $stageId, array $dealIds): bool
    {
        $this->db->transStart();

        foreach ($dealIds as $order => $dealId) {
            $this->where('id', $dealId)
                ->where('stage_id', $stageId)
                ->set('sort_order', $order)
                ->update();
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    protected function logDealCreated(array $data): array
    {
        if (!isset($data['id'])) {
            return $data;
        }

        $activityModel = new DealActivityModel();
        $activityModel->logActivity($data['id'], 'created', 'Deal created');

        return $data;
    }

    protected function logDealUpdated(array $data): array
    {
        if (!isset($data['id'])) {
            return $data;
        }

        $dealId = is_array($data['id']) ? $data['id'][0] : $data['id'];

        // Check if stage changed
        if (isset($data['data']['stage_id'])) {
            $stageModel = new PipelineStageModel();
            $stage = $stageModel->find($data['data']['stage_id']);

            $activityModel = new DealActivityModel();
            $activityModel->logActivity($dealId, 'stage_change', "Moved to stage: {$stage['name']}", [
                'stage_id' => $data['data']['stage_id'],
            ]);
        }

        return $data;
    }
}
