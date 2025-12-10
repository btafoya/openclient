<?php

namespace App\Controllers\Proposals;

use App\Controllers\BaseController;
use App\Models\ProposalTemplateModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Proposal Template Controller
 *
 * Manages reusable proposal templates.
 */
class ProposalTemplateController extends BaseController
{
    protected ProposalTemplateModel $templateModel;

    public function __construct()
    {
        $this->templateModel = new ProposalTemplateModel();
    }

    /**
     * List all templates
     *
     * GET /api/proposal-templates
     */
    public function index(): ResponseInterface
    {
        $activeOnly = $this->request->getGet('active_only') !== 'false';

        if ($activeOnly) {
            $templates = $this->templateModel->getActiveTemplates();
        } else {
            $templates = $this->templateModel->findAll();
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Show single template
     *
     * GET /api/proposal-templates/{id}
     */
    public function show(string $id): ResponseInterface
    {
        $template = $this->templateModel->getWithSections($id);

        if (!$template) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Template not found']);
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * Store new template
     *
     * POST /api/proposal-templates
     */
    public function store(): ResponseInterface
    {
        $user = session()->get('user');

        // Only owner and agency can manage templates
        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to create templates.']);
        }

        $data = $this->request->getJSON(true);

        $rules = [
            'name' => 'required|max_length[255]',
        ];

        if (!$this->validateData($data, $rules)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON(['error' => 'Validation failed', 'errors' => $this->validator->getErrors()]);
        }

        $templateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'content' => isset($data['content']) ? json_encode($data['content']) : null,
            'default_sections' => isset($data['default_sections']) ? json_encode($data['default_sections']) : null,
            'default_terms' => $data['default_terms'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        $templateId = $this->templateModel->insert($templateData, true);

        if (!$templateId) {
            return $this->response
                ->setStatusCode(500)
                ->setJSON(['error' => 'Failed to create template']);
        }

        $template = $this->templateModel->find($templateId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'data' => $template,
                'message' => 'Template created successfully',
            ]);
    }

    /**
     * Update template
     *
     * PUT /api/proposal-templates/{id}
     */
    public function update(string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to update templates.']);
        }

        $template = $this->templateModel->find($id);

        if (!$template) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Template not found']);
        }

        // Agency can only edit their own agency's templates
        if ($user['role'] === 'agency' && $template['agency_id'] !== $user['agency_id']) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to update this template.']);
        }

        $data = $this->request->getJSON(true);

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['content'])) {
            $updateData['content'] = json_encode($data['content']);
        }
        if (isset($data['default_sections'])) {
            $updateData['default_sections'] = json_encode($data['default_sections']);
        }
        if (array_key_exists('default_terms', $data)) {
            $updateData['default_terms'] = $data['default_terms'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }

        if (!empty($updateData)) {
            $this->templateModel->update($id, $updateData);
        }

        $template = $this->templateModel->getWithSections($id);

        return $this->response->setJSON([
            'success' => true,
            'data' => $template,
            'message' => 'Template updated successfully',
        ]);
    }

    /**
     * Delete template
     *
     * DELETE /api/proposal-templates/{id}
     */
    public function delete(string $id): ResponseInterface
    {
        $user = session()->get('user');

        if ($user['role'] !== 'owner') {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'Only owners can delete templates.']);
        }

        $template = $this->templateModel->find($id);

        if (!$template) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Template not found']);
        }

        $this->templateModel->delete($id);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Duplicate template
     *
     * POST /api/proposal-templates/{id}/duplicate
     */
    public function duplicate(string $id): ResponseInterface
    {
        $user = session()->get('user');

        if (!in_array($user['role'], ['owner', 'agency'])) {
            return $this->response
                ->setStatusCode(403)
                ->setJSON(['error' => 'You do not have permission to duplicate templates.']);
        }

        $data = $this->request->getJSON(true);
        $newName = $data['name'] ?? 'Copy of Template';

        $newTemplateId = $this->templateModel->duplicate($id, $newName);

        if (!$newTemplateId) {
            return $this->response
                ->setStatusCode(404)
                ->setJSON(['error' => 'Template not found']);
        }

        $template = $this->templateModel->getWithSections($newTemplateId);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'success' => true,
                'data' => $template,
                'message' => 'Template duplicated successfully',
            ]);
    }
}
