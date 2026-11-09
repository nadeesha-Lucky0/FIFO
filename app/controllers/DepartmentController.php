<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\Logger;
use App\Models\Department;

class DepartmentController {
    private Department $deptModel;

    public function __construct() {
        $this->deptModel = new Department();
    }

    public function index(): void {
        Auth::requireLogin();
        $locationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : null;
        $departments = $this->deptModel->getAll($locationId);
        Response::json([
            'success' => true,
            'data' => $departments
        ]);
    }

    public function create(): void {
        Auth::requireAdmin();

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Security token invalid or expired.', [], 403);
        }

        $locationId = (int)($_POST['location_id'] ?? 1);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $headOfDept = trim($_POST['head_of_department'] ?? '');
        $status = in_array($_POST['status'] ?? 'active', ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($name)) {
            Response::error('Department name is required.');
        }

        if (empty($code)) {
            // Auto generate code if empty
            $code = 'EGL-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5));
        }

        if ($this->deptModel->isNameTaken($name, null, $locationId)) {
            Response::error('A department with this name already exists for this power station.');
        }

        $id = $this->deptModel->create([
            'location_id' => $locationId,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'head_of_department' => $headOfDept,
            'status' => $status
        ]);

        Logger::log('create_department', 'department', $id, "Super Admin created department: {$name} ({$code}) under location ID: {$locationId}");

        Response::success('Department added successfully.', [
            'id' => $id,
            'department' => $this->deptModel->findById($id)
        ]);
    }

    public function update(): void {
        Auth::requireAdmin();

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Security token invalid or expired.', [], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        $existingDept = $this->deptModel->findById($id);
        if (!$id || !$existingDept) {
            Response::error('Department not found.', [], 404);
        }

        $locationId = isset($_POST['location_id']) ? (int)$_POST['location_id'] : (int)($existingDept['location_id'] ?? 1);
        $name = trim($_POST['name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $headOfDept = trim($_POST['head_of_department'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            Response::error('Department name is required.');
        }

        if ($this->deptModel->isNameTaken($name, $id, $locationId)) {
            Response::error('A department with this name already exists for this power station.');
        }

        $data = [
            'name' => $name,
            'description' => $description,
            'head_of_department' => $headOfDept,
            'status' => $status
        ];
        if ($locationId !== null && $locationId > 0) {
            $data['location_id'] = $locationId;
        }
        if (!empty($code)) {
            $data['code'] = $code;
        }

        $this->deptModel->update($id, $data);
        Logger::log('update_department', 'department', $id, "Super Admin updated department: {$name}");

        Response::success('Department updated successfully.');
    }

    public function delete(): void {
        Auth::requireAdmin();

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Security token invalid or expired.', [], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        $dept = $this->deptModel->findById($id);

        if (!$dept) {
            Response::error('Department not found.', [], 404);
        }

        $this->deptModel->delete($id);
        Logger::log('delete_department', 'department', $id, "Super Admin deleted department: {$dept['name']}");

        Response::success("Department '{$dept['name']}' deleted successfully.");
    }
}
