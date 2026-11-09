<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Models\Role;
use App\Models\Location;
use App\Models\Department;

class RoleController {
    private Role $roleModel;

    public function __construct() {
        $this->roleModel = new Role();
    }

    public function index(): void {
        Auth::requireAdmin();
        $locationId = isset($_GET['location_id']) && is_numeric($_GET['location_id']) ? (int)$_GET['location_id'] : null;
        $departmentId = isset($_GET['department_id']) && is_numeric($_GET['department_id']) ? (int)$_GET['department_id'] : null;

        $roles = $this->roleModel->all($locationId, $departmentId);
        Response::success('Roles retrieved', ['roles' => $roles]);
    }

    public function create(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin', 'code_admin'])) {
            Response::error('Access restricted. Only Administrators can configure system roles and codes.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $displayName = trim($_POST['display_name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        if (empty($code)) {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $displayName));
        }

        if (empty($name)) {
            $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $displayName));
            $name = trim($name, '_');
        } else {
            $name = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $name));
            $name = trim($name, '_');
        }

        if (empty($name)) {
            $name = 'role_' . time();
        }

        // Auto-uniquify if slug is already taken
        if ($this->roleModel->isNameTaken($name)) {
            $baseSlug = $name;
            $counter = 1;
            while ($this->roleModel->isNameTaken($name)) {
                $name = $baseSlug . '_' . ($locationId ?? '') . '_' . ($departmentId ?? '') . ($counter > 1 ? '_' . $counter : '');
                $name = trim($name, '_');
                $counter++;
                if ($counter > 20) {
                    $name = $baseSlug . '_' . substr(md5((string)mt_rand()), 0, 6);
                    break;
                }
            }
        }

        $errors = [];
        if (empty($displayName)) {
            $errors['display_name'] = 'Role Name is required.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed. Please correct the errors.', $errors, 422);
        }

        $roleId = $this->roleModel->create([
            'name' => $name,
            'code' => $code,
            'display_name' => $displayName,
            'description' => $description,
            'location_id' => $locationId,
            'department_id' => $departmentId
        ]);

        if ($roleId) {
            // Save tab access permissions for this role
            $permModel = new \App\Models\Permission();
            $allKeys = \App\Models\Permission::getAllPermissionKeys();
            $submittedPerms = $_POST['permissions'] ?? [];
            foreach ($allKeys as $permKey) {
                $isGranted = isset($submittedPerms[$permKey]) && ($submittedPerms[$permKey] == '1' || $submittedPerms[$permKey] === 1 || $submittedPerms[$permKey] === true);
                $permModel->setPermission($roleId, $permKey, $isGranted);
            }

            Logger::log(
                'role_created',
                'role',
                $roleId,
                "Admin defined new role '{$displayName}' ({$name}) with configured tab permissions."
            );

            $newRole = $this->roleModel->findById($roleId);
            Response::success('New role and tab permissions successfully defined!', ['role' => $newRole], 201);
        } else {
            Response::error('Failed to create role. Please try again.');
        }
    }

    public function update(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin', 'code_admin'])) {
            Response::error('Access restricted. Only Administrators can configure system roles and codes.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        $displayName = trim($_POST['display_name'] ?? '');
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $departmentId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

        if ($id <= 0) {
            Response::error('Invalid role ID.');
        }

        $existing = $this->roleModel->findById($id);
        if (!$existing) {
            Response::error('Role not found.');
        }

        $errors = [];
        if (empty($displayName)) {
            $errors['display_name'] = 'Role Title is required.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed. Please correct the errors.', $errors, 422);
        }

        $updateData = [
            'display_name' => $displayName,
            'description' => $description,
            'location_id' => $locationId,
            'department_id' => $departmentId
        ];
        if (!empty($code)) {
            $updateData['code'] = $code;
        }

        $success = $this->roleModel->update($id, $updateData);

        if ($success) {
            // Update tab access permissions for this role if submitted
            if (isset($_POST['permissions']) || isset($_POST['_save_permissions'])) {
                $permModel = new \App\Models\Permission();
                $allKeys = \App\Models\Permission::getAllPermissionKeys();
                $submittedPerms = $_POST['permissions'] ?? [];
                foreach ($allKeys as $permKey) {
                    $isGranted = isset($submittedPerms[$permKey]) && ($submittedPerms[$permKey] == '1' || $submittedPerms[$permKey] === 1 || $submittedPerms[$permKey] === true);
                    $permModel->setPermission($id, $permKey, $isGranted);
                }
            }

            Logger::log(
                'role_updated',
                'role',
                $id,
                "Admin updated role definition and tab permissions for '{$displayName}'."
            );
            Response::success('Role and tab permissions updated successfully.');
        } else {
            Response::error('Failed to update role.');
        }
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin', 'code_admin'])) {
            Response::error('Access restricted. Only Administrators can delete roles.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id === 1) {
            Response::error('Super Administrator core role cannot be deleted.');
        }

        $existing = $this->roleModel->findById($id);
        if (!$existing) {
            Response::error('Role not found.');
        }

        $success = $this->roleModel->delete($id);
        if ($success) {
            Logger::log(
                'role_deleted',
                'role',
                $id,
                "Admin deleted custom role '{$existing['display_name']}'."
            );
            Response::success('Role deleted successfully.');
        } else {
            Response::error('Cannot delete role. Active users may be assigned to this role.');
        }
    }
}
