<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\Logger;
use App\Models\IssueDestinationCode;

class IssueCodeController
{
    private IssueDestinationCode $model;

    public function __construct()
    {
        $this->model = new IssueDestinationCode();
    }

    /**
     * List all issue codes, optionally filtered by category and search
     */
    public function index(): void
    {
        Auth::requireLogin();
        $category = $_GET['category'] ?? null;
        $search = trim($_GET['search'] ?? '');
        $status = $_GET['status'] ?? 'all';

        $codes = $this->model->getAll($category, $search, $status);
        $counts = $this->model->getCategoryCounts();

        Response::json([
            'success' => true,
            'data' => $codes,
            'counts' => $counts
        ]);
    }

    /**
     * Get grouped active options for dropdown rendering
     */
    public function grouped(): void
    {
        Auth::requireLogin();
        $grouped = $this->model->getAllGrouped();
        Response::json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * Create new code / item
     */
    public function create(): void
    {
        Auth::requireAdmin();

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Security token invalid or expired.', [], 403);
        }

        $category = trim($_POST['category'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $displayLabel = trim($_POST['display_label'] ?? '');
        $rawStatus = $_POST['status'] ?? 'active';
        $status = in_array($rawStatus, ['active', 'inactive']) ? $rawStatus : 'active';
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        $allowedCategories = ['Section', 'Project', 'Cost Center', 'Warehouse', 'My Project', 'Genaration as Expenditure'];
        if (!in_array($category, $allowedCategories)) {
            Response::error('Please select a valid category from: ' . implode(', ', $allowedCategories));
        }

        if (empty($title) && empty($displayLabel)) {
            Response::error('Code title / description is required.');
        }

        // Duplicate code validation
        if (!empty($code)) {
            $existingCode = $this->model->findByCode($code);
            if ($existingCode) {
                $extCat = $existingCode['category'];
                $extLabel = $existingCode['display_label'] ?? $existingCode['title'];
                if ($extCat === $category) {
                    Response::error("Code '{$code}' already exists in {$category} ('{$extLabel}'). Duplicate codes cannot be added.");
                } else {
                    Response::error("Code '{$code}' already exists under '{$extCat}' category ('{$extLabel}'). Duplicate codes cannot be added.");
                }
            }
        }

        if (empty($displayLabel)) {
            $displayLabel = $code ? ($code . ' -' . $title) : $title;
        }

        $newId = $this->model->create([
            'category' => $category,
            'code' => $code ?: null,
            'title' => $title,
            'display_label' => $displayLabel,
            'sort_order' => $sortOrder,
            'status' => $status
        ]);

        if ($newId > 0) {
            Logger::log('issue_code_create', 'IssueDestinationCode', $newId, "Added new code '{$displayLabel}' under category '{$category}'");
            Response::json([
                'success' => true,
                'message' => 'New code successfully created.',
                'id' => $newId,
                'data' => $this->model->findById($newId)
            ]);
        } else {
            Response::error('Failed to create code entry.');
        }
    }

    /**
     * Update existing code / item
     */
    public function update(): void
    {
        Auth::requireAdmin();

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Security token invalid or expired.', [], 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Invalid code ID.');
        }

        $existing = $this->model->findById($id);
        if (!$existing) {
            Response::error('Code entry not found.');
        }

        $category = trim($_POST['category'] ?? $existing['category']);
        $code = trim($_POST['code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $displayLabel = trim($_POST['display_label'] ?? '');
        $rawStatus = $_POST['status'] ?? ($existing['status'] ?? 'active');
        $status = in_array($rawStatus, ['active', 'inactive']) ? $rawStatus : 'active';
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : (int) $existing['sort_order'];

        if (empty($title) && empty($displayLabel)) {
            Response::error('Code title / description is required.');
        }

        // Duplicate code validation for update
        if (!empty($code)) {
            $existingCode = $this->model->findByCode($code, null, $id);
            if ($existingCode) {
                $extCat = $existingCode['category'];
                $extLabel = $existingCode['display_label'] ?? $existingCode['title'];
                if ($extCat === $category) {
                    Response::error("Code '{$code}' already exists in {$category} ('{$extLabel}'). Duplicate codes cannot be used.");
                } else {
                    Response::error("Code '{$code}' is already used under '{$extCat}' category ('{$extLabel}'). Duplicate codes cannot be used.");
                }
            }
        }

        if (empty($displayLabel)) {
            $displayLabel = $code ? ($code . ' -' . $title) : $title;
        }

        $updated = $this->model->update($id, [
            'category' => $category,
            'code' => $code ?: null,
            'title' => $title,
            'display_label' => $displayLabel,
            'sort_order' => $sortOrder,
            'status' => $status
        ]);

        if ($updated) {
            Logger::log('issue_code_update', 'IssueDestinationCode', $id, "Updated code ID {$id} to '{$displayLabel}' ({$category})");
            Response::json([
                'success' => true,
                'message' => 'Code entry updated successfully.',
                'data' => $this->model->findById($id)
            ]);
        } else {
            Response::error('Failed to update code entry.');
        }
    }

    /**
     * Delete code / item
     */
    public function delete(): void
    {
        Auth::requireAdmin();

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Security token invalid or expired.', [], 403);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Response::error('Invalid code ID.');
        }

        $existing = $this->model->findById($id);
        if (!$existing) {
            Response::error('Code entry not found.');
        }

        $deleted = $this->model->delete($id);
        if ($deleted) {
            Logger::log('issue_code_delete', 'IssueDestinationCode', $id, "Deleted code ID {$id} '{$existing['display_label']}' ({$existing['category']})");
            Response::json([
                'success' => true,
                'message' => 'Code entry deleted successfully.'
            ]);
        } else {
            Response::error('Failed to delete code entry.');
        }
    }

    /**
     * Live duplicate checking endpoint
     */
    public function checkDuplicate(): void
    {
        Auth::requireLogin();
        $code = trim($_GET['code'] ?? $_POST['code'] ?? '');
        $category = trim($_GET['category'] ?? $_POST['category'] ?? '');
        $excludeId = isset($_GET['exclude_id']) ? (int) $_GET['exclude_id'] : (isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : null);

        if ($code === '') {
            Response::json(['exists' => false]);
            return;
        }

        $existing = $this->model->findByCode($code, null, $excludeId);
        if ($existing) {
            Response::json([
                'exists' => true,
                'category' => $existing['category'],
                'display_label' => $existing['display_label'],
                'message' => "Code '{$code}' already exists in {$existing['category']} ('{$existing['display_label']}')."
            ]);
        } else {
            Response::json(['exists' => false]);
        }
    }
}
