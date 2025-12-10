<?php

namespace App\Models\Pipelines;

use CodeIgniter\Model;

class DealModel extends Model
{
    protected $table = 'deals';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'agency_id',
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
        'pipeline_id' => 'required',
        'stage_id' => 'required',
        'name' => 'required|min_length[1]|max_length[255]',
        'value' => 'permit_empty|decimal',
        'probability' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[100]',
        'priority' => 'permit_empty|in_list[low,medium,high]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Deal name is required',
        ],
        'pipeline_id' => [
            'required' => 'Pipeline is required',
        ],
        'stage_id' => [
            'required' => 'Stage is required',
        ],
    ];

    protected $beforeInsert = ['generateUuid', 'setAgencyId', 'setStageProbability'];
    protected $beforeUpdate = ['updateStageProbability'];
    protected $afterInsert = ['logActivity'];
    protected $afterUpdate = ['logStageChange'];

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
     * Set probability from stage
     */
    protected function setStageProbability(array $data): array
    {
        if (!empty($data['data']['stage_id']) && empty($data['data']['probability'])) {
            $stageModel = new PipelineStageModel();
            $stage = $stageModel->find($data['data']['stage_id']);
            if ($stage) {
                $data['data']['probability'] = $stage['probability'];
            }
        }
        return $data;
    }

    /**
     * Update probability when stage changes
     */
    protected function updateStageProbability(array $data): array
    {
        if (!empty($data['data']['stage_id'])) {
            $stageModel = new PipelineStageModel();
            $stage = $stageModel->find($data['data']['stage_id']);
            if ($stage) {
                $data['data']['probability'] = $stage['probability'];

                // Set actual close date if won or lost
                if ($stage['is_won'] || $stage['is_lost']) {
                    $data['data']['actual_close_date'] = date('Y-m-d');
                }
            }
        }
        return $data;
    }

    /**
     * Log activity on insert
     */
    protected function logActivity(array $data): array
    {
        if (!empty($data['id'])) {
            $activityModel = new DealActivityModel();
            $session = session();

            $activityModel->insert([
                'deal_id' => $data['id'],
                'user_id' => $session->get('user_id'),
                'activity_type' => 'created',
                'subject' => 'Deal created',
                'description' => 'Deal was created',
            ]);
        }
        return $data;
    }

    /**
     * Log stage change
     */
    protected function logStageChange(array $data): array
    {
        // This would need to compare old and new values
        // Implementation deferred for now
        return $data;
    }

    /**
     * Get deal with relationships
     */
    public function getWithRelations(string $id): ?array
    {
        $deal = $this->find($id);
        if (!$deal) {
            return null;
        }

        // Load pipeline
        $pipelineModel = new PipelineModel();
        $deal['pipeline'] = $pipelineModel->find($deal['pipeline_id']);

        // Load stage
        $stageModel = new PipelineStageModel();
        $deal['stage'] = $stageModel->find($deal['stage_id']);

        // Load client
        if ($deal['client_id']) {
            $clientModel = new \App\Models\ClientModel();
            $deal['client'] = $clientModel->find($deal['client_id']);
        }

        // Load contact
        if ($deal['contact_id']) {
            $contactModel = new \App\Models\ContactModel();
            $deal['contact'] = $contactModel->find($deal['contact_id']);
        }

        // Load assigned user
        if ($deal['assigned_to']) {
            $userModel = new \App\Models\UserModel();
            $deal['assigned_user'] = $userModel->find($deal['assigned_to']);
        }

        return $deal;
    }

    /**
     * Get deals by pipeline and stage (for Kanban board)
     */
    public function getKanbanBoard(string $pipelineId): array
    {
        $stageModel = new PipelineStageModel();
        $stages = $stageModel->getByPipeline($pipelineId);

        $board = [];
        foreach ($stages as $stage) {
            $deals = $this->where('pipeline_id', $pipelineId)
                ->where('stage_id', $stage['id'])
                ->where('is_active', true)
                ->orderBy('sort_order', 'ASC')
                ->findAll();

            // Load minimal client info for each deal
            foreach ($deals as &$deal) {
                if ($deal['client_id']) {
                    $clientModel = new \App\Models\ClientModel();
                    $client = $clientModel->find($deal['client_id']);
                    $deal['client_name'] = $client ? $client['name'] : null;
                }
            }

            $board[] = [
                'stage' => $stage,
                'deals' => $deals,
                'total_value' => array_sum(array_column($deals, 'value')),
            ];
        }

        return $board;
    }

    /**
     * Move deal to stage
     */
    public function moveToStage(string $dealId, string $stageId, ?int $sortOrder = null): bool
    {
        $stageModel = new PipelineStageModel();
        $stage = $stageModel->find($stageId);

        if (!$stage) {
            return false;
        }

        $updateData = [
            'stage_id' => $stageId,
            'probability' => $stage['probability'],
        ];

        if ($sortOrder !== null) {
            $updateData['sort_order'] = $sortOrder;
        }

        // Set close date if won or lost
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
            'probability' => 100,
            'actual_close_date' => date('Y-m-d'),
            'won_reason' => $reason,
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
            'probability' => 0,
            'actual_close_date' => date('Y-m-d'),
            'lost_reason' => $reason,
        ]);
    }

    /**
     * Convert deal to project
     */
    public function convertToProject(string $dealId): ?string
    {
        $deal = $this->getWithRelations($dealId);
        if (!$deal) {
            return null;
        }

        $projectModel = new \App\Models\ProjectModel();
        $projectData = [
            'client_id' => $deal['client_id'],
            'name' => $deal['name'],
            'description' => $deal['description'],
            'budget' => $deal['value'],
            'status' => 'planning',
        ];

        $projectId = $projectModel->insert($projectData, true);

        if ($projectId) {
            // Add activity to deal
            $activityModel = new DealActivityModel();
            $session = session();
            $activityModel->insert([
                'deal_id' => $dealId,
                'user_id' => $session->get('user_id'),
                'activity_type' => 'converted',
                'subject' => 'Converted to project',
                'description' => "Deal converted to project: {$deal['name']}",
                'metadata' => json_encode(['project_id' => $projectId]),
            ]);
        }

        return $projectId;
    }

    /**
     * Search deals
     */
    public function search(string $query, ?string $pipelineId = null): array
    {
        $builder = $this->like('name', $query)
            ->orLike('description', $query);

        if ($pipelineId) {
            $builder->where('pipeline_id', $pipelineId);
        }

        return $builder->where('is_active', true)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get deals by client
     */
    public function getByClient(string $clientId): array
    {
        return $this->where('client_id', $clientId)
            ->where('is_active', true)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get deals statistics
     */
    public function getStats(?string $pipelineId = null): array
    {
        $builder = $this->where('is_active', true);

        if ($pipelineId) {
            $builder->where('pipeline_id', $pipelineId);
        }

        $totalDeals = (clone $builder)->countAllResults(false);
        $totalValue = (clone $builder)->selectSum('value')->first();

        // Won deals
        $stageModel = new PipelineStageModel();
        $wonStageIds = $this->db->table('pipeline_stages')
            ->select('id')
            ->where('is_won', true)
            ->get()
            ->getResultArray();
        $wonStageIds = array_column($wonStageIds, 'id');

        $wonBuilder = $this->where('is_active', true);
        if ($pipelineId) {
            $wonBuilder->where('pipeline_id', $pipelineId);
        }
        if (!empty($wonStageIds)) {
            $wonBuilder->whereIn('stage_id', $wonStageIds);
        }
        $wonDeals = $wonBuilder->countAllResults(false);

        $wonValueBuilder = $this->where('is_active', true);
        if ($pipelineId) {
            $wonValueBuilder->where('pipeline_id', $pipelineId);
        }
        if (!empty($wonStageIds)) {
            $wonValueBuilder->whereIn('stage_id', $wonStageIds);
        }
        $wonValue = $wonValueBuilder->selectSum('value')->first();

        // Weighted pipeline value
        $weightedValue = $this->db->table('deals')
            ->selectSum('value * probability / 100', 'weighted')
            ->where('is_active', true)
            ->where($pipelineId ? ['pipeline_id' => $pipelineId] : [])
            ->get()
            ->getRowArray();

        return [
            'total_deals' => $totalDeals,
            'total_value' => (float) ($totalValue['value'] ?? 0),
            'won_deals' => $wonDeals,
            'won_value' => (float) ($wonValue['value'] ?? 0),
            'weighted_value' => (float) ($weightedValue['weighted'] ?? 0),
            'win_rate' => $totalDeals > 0 ? round(($wonDeals / $totalDeals) * 100, 1) : 0,
            'average_deal_value' => $totalDeals > 0 ? round(($totalValue['value'] ?? 0) / $totalDeals, 2) : 0,
        ];
    }

    /**
     * Reorder deals in stage
     */
    public function reorderInStage(string $stageId, array $order): bool
    {
        $this->db->transStart();

        foreach ($order as $index => $id) {
            $this->where('stage_id', $stageId)
                ->where('id', $id)
                ->set('sort_order', $index)
                ->update();
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Get deals closing soon
     */
    public function getClosingSoon(int $days = 7, ?string $pipelineId = null): array
    {
        $builder = $this->where('is_active', true)
            ->where('expected_close_date IS NOT NULL')
            ->where('expected_close_date >=', date('Y-m-d'))
            ->where('expected_close_date <=', date('Y-m-d', strtotime("+{$days} days")));

        if ($pipelineId) {
            $builder->where('pipeline_id', $pipelineId);
        }

        return $builder->orderBy('expected_close_date', 'ASC')
            ->findAll();
    }

    /**
     * Get overdue deals
     */
    public function getOverdue(?string $pipelineId = null): array
    {
        $builder = $this->where('is_active', true)
            ->where('expected_close_date IS NOT NULL')
            ->where('expected_close_date <', date('Y-m-d'))
            ->where('actual_close_date IS NULL');

        if ($pipelineId) {
            $builder->where('pipeline_id', $pipelineId);
        }

        return $builder->orderBy('expected_close_date', 'ASC')
            ->findAll();
    }
}
