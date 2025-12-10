<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Proposal Section Model
 *
 * Manages individual sections within proposals (line items with pricing).
 */
class ProposalSectionModel extends Model
{
    protected $table = 'proposal_sections';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'proposal_id',
        'title',
        'description',
        'content',
        'quantity',
        'unit_price',
        'total_price',
        'is_optional',
        'is_selected',
        'sort_order',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'proposal_id' => 'required|max_length[36]',
        'title' => 'required|max_length[255]',
        'quantity' => 'permit_empty|decimal',
        'unit_price' => 'permit_empty|decimal',
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateUuid', 'calculateTotal'];
    protected $beforeUpdate = ['calculateTotal'];
    protected $afterInsert = ['updateProposalTotals'];
    protected $afterUpdate = ['updateProposalTotals'];
    protected $afterDelete = ['updateProposalTotals'];

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
     * Calculate total price from quantity and unit price
     */
    protected function calculateTotal(array $data): array
    {
        if (isset($data['data']['quantity']) && isset($data['data']['unit_price'])) {
            $data['data']['total_price'] = $data['data']['quantity'] * $data['data']['unit_price'];
        }
        return $data;
    }

    /**
     * Update proposal totals after section changes
     */
    protected function updateProposalTotals(array $data): array
    {
        $proposalId = $data['data']['proposal_id'] ?? null;
        if (!$proposalId && isset($data['id'])) {
            $section = $this->find(is_array($data['id']) ? $data['id'][0] : $data['id']);
            $proposalId = $section['proposal_id'] ?? null;
        }

        if ($proposalId) {
            $proposalModel = new ProposalModel();
            $proposalModel->recalculateTotals($proposalId);
        }

        return $data;
    }

    /**
     * Get sections by proposal ID
     */
    public function getByProposalId(string $proposalId, bool $selectedOnly = false): array
    {
        $builder = $this->where('proposal_id', $proposalId);

        if ($selectedOnly) {
            $builder->where('is_selected', true);
        }

        return $builder->orderBy('sort_order', 'ASC')->findAll();
    }

    /**
     * Update section selection (for optional sections)
     */
    public function updateSelection(string $id, bool $isSelected): bool
    {
        return $this->update($id, ['is_selected' => $isSelected]);
    }

    /**
     * Reorder sections
     */
    public function reorder(string $proposalId, array $sectionIds): bool
    {
        $order = 0;
        foreach ($sectionIds as $sectionId) {
            $this->update($sectionId, ['sort_order' => $order++]);
        }
        return true;
    }

    /**
     * Duplicate sections from one proposal to another
     */
    public function duplicateForProposal(string $sourceProposalId, string $targetProposalId): bool
    {
        $sections = $this->getByProposalId($sourceProposalId);

        foreach ($sections as $section) {
            unset($section['id'], $section['created_at'], $section['updated_at']);
            $section['proposal_id'] = $targetProposalId;
            $this->insert($section);
        }

        return true;
    }

    /**
     * Create sections from template default sections
     */
    public function createFromTemplate(string $proposalId, array $defaultSections): bool
    {
        $order = 0;
        foreach ($defaultSections as $section) {
            $this->insert([
                'proposal_id' => $proposalId,
                'title' => $section['title'] ?? 'Section',
                'description' => $section['description'] ?? null,
                'content' => $section['content'] ?? null,
                'quantity' => $section['quantity'] ?? 1,
                'unit_price' => $section['unit_price'] ?? 0,
                'total_price' => ($section['quantity'] ?? 1) * ($section['unit_price'] ?? 0),
                'is_optional' => $section['is_optional'] ?? false,
                'is_selected' => true,
                'sort_order' => $order++,
            ]);
        }

        return true;
    }
}
