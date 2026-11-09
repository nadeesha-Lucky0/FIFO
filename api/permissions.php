<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Session;
use App\Core\Response;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', [], 405);
}

$csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!Session::validateCsrf($csrfToken)) {
    Response::error('Invalid security token.', [], 403);
}

$action = $_POST['action'] ?? '';
$permModel = new Permission();
$roleModel = new Role();
$userModel = new User();

if ($action === 'toggle') {
    $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $permissionKey = trim($_POST['permission_key'] ?? '');
    $isGranted = !empty($_POST['is_granted']) && ($_POST['is_granted'] === '1' || $_POST['is_granted'] === 'true' || $_POST['is_granted'] === 1 || $_POST['is_granted'] === true);

    if (empty($permissionKey) || ($roleId <= 0 && $userId <= 0)) {
        Response::error('Invalid target (role or user) or permission specified.', [], 422);
    }

    if ($userId > 0) {
        $user = $userModel->findById($userId);
        if (!$user) {
            Response::error('User not found.', [], 404);
        }
        $success = $permModel->setUserPermission($userId, $permissionKey, $isGranted);
        if ($success) {
            Response::success('User permission updated successfully.', [
                'user_id' => $userId,
                'permission_key' => $permissionKey,
                'is_granted' => $isGranted ? 1 : 0
            ]);
        } else {
            Response::error('Failed to update user permission.');
        }
    } else {
        $role = $roleModel->findById($roleId);
        if (!$role) {
            Response::error('Role not found.', [], 404);
        }

        $success = $permModel->setPermission($roleId, $permissionKey, $isGranted);
        if ($success) {
            Response::success('Role permission updated successfully.', [
                'role_id' => $roleId,
                'permission_key' => $permissionKey,
                'is_granted' => $isGranted ? 1 : 0
            ]);
        } else {
            Response::error('Failed to update role permission.');
        }
    }
} else {
    Response::error('Invalid permission action.', [], 400);
}
