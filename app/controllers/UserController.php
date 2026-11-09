<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;

class UserController {
    private User $userModel;
    private Role $roleModel;
    private Department $deptModel;

    public function __construct() {
        $this->userModel = new User();
        $this->roleModel = new Role();
        $this->deptModel = new Department();
    }

    public function index(): void {
        Auth::requireAdmin();

        $filters = [
            'search' => $_GET['search'] ?? '',
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
        ];

        $users = $this->userModel->getAll($filters);
        $roles = $this->roleModel->all();

        Response::success('Users retrieved', [
            'users' => $users,
            'roles' => $roles
        ]);
    }

    public function get(): void {
        Auth::requireAdmin();

        $userId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($userId <= 0) {
            Response::error('Invalid user ID.');
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::error('User not found.', [], 404);
        }

        // Exclude password hash from response
        unset($user['password_hash']);

        Response::success('User details retrieved', ['user' => $user]);
    }

    public function create(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin'])) {
            Response::error('Access restricted. Only Super Administrators can create user accounts.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $department = trim($_POST['department'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'suspended', 'pending']) ? $_POST['status'] : 'active';

        if (empty($fullName)) {
            $fullName = $username;
        }

        $errors = [];

        if (empty($username) || strlen($username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
            $errors['username'] = 'Username can only contain letters, numbers, dots, dashes, and underscores.';
        } elseif ($this->userModel->isUsernameTaken($username)) {
            $errors['username'] = 'This username is already in use.';
        }

        if (empty($email)) {
            $cleanUser = strtolower(preg_replace('/[^a-z0-9]/', '', $username));
            $email = ($cleanUser ?: 'user') . '@egl.lk';
            if ($this->userModel->isEmailTaken($email)) {
                $email = ($cleanUser ?: 'user') . '_' . time() . '@egl.lk';
            }
        } else {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid corporate email is required.';
            } elseif ($this->userModel->isEmailTaken($email)) {
                $errors['email'] = 'This email is already registered to another account.';
            }
        }

        if (empty($password) || strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        // Validate selected role against defined roles
        $role = null;
        if ($roleId <= 0) {
            $errors['role_id'] = 'Please select a defined user role.';
        } else {
            $role = $this->roleModel->findById($roleId);
            if (!$role) {
                $errors['role_id'] = 'Selected user role is invalid. Please pick a defined role.';
            }
        }

        $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        if (empty($department) && $locationId) {
            $locModel = new \App\Models\Location();
            $loc = $locModel->findById($locationId);
            if ($loc) {
                $department = $loc['name'] . (!empty($loc['code']) ? ' (' . $loc['code'] . ')' : '');
            }
        }

        if (empty($department)) {
            $department = !empty($role['location_name']) ? $role['location_name'] : 'General';
        }

        if (!empty($errors)) {
            Response::error('Validation failed. Please correct the errors below.', $errors, 422);
        }

        $newUserId = $this->userModel->create([
            'role_id' => $roleId,
            'location_id' => $locationId,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'full_name' => $fullName,
            'department' => $department,
            'phone' => $phone,
            'status' => $status,
        ]);

        if ($newUserId) {
            // Automatically inherit and apply role-defined tab access permissions for the newly created user
            $permModel = new \App\Models\Permission();
            $permModel->copyRolePermissionsToUser($roleId, $newUserId);

            $roleDisplayName = isset($role) ? $role['display_name'] : 'Assigned Role';
            Logger::log(
                'user_created',
                'user',
                $newUserId,
                "Admin created user '{$username}' ({$fullName}) with defined role '{$roleDisplayName}' in '{$department}' (role tab permissions applied automatically)."
            );

            $createdUser = $this->userModel->findById($newUserId);

            Response::success('New user account successfully created!', [
                'user' => $createdUser
            ], 201);
        } else {
            Response::error('Failed to create user account. Please try again.');
        }
    }

    public function update(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin'])) {
            Response::error('Access restricted. Only Super Administrators can update user accounts.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $userId = (int)($_POST['id'] ?? 0);
        if ($userId <= 0) {
            Response::error('Invalid user ID.');
        }

        $targetUser = $this->userModel->findById($userId);
        if (!$targetUser) {
            Response::error('User not found.', [], 404);
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);
        $department = trim($_POST['department'] ?? '');
        $locationId = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['active', 'suspended', 'pending']) ? $_POST['status'] : $targetUser['status'];
        $newPassword = trim($_POST['password'] ?? '');

        if (empty($fullName)) {
            $fullName = $targetUser['full_name'] ?: $targetUser['username'];
        }

        $errors = [];

        $role = null;
        if ($roleId <= 0) {
            $errors['role_id'] = 'Please select a defined user role.';
        } else {
            $role = $this->roleModel->findById($roleId);
            if (!$role) {
                $errors['role_id'] = 'Selected user role is invalid.';
            }
        }

        if (empty($department) && $locationId) {
            $locModel = new \App\Models\Location();
            $loc = $locModel->findById($locationId);
            if ($loc) {
                $department = $loc['name'] . (!empty($loc['code']) ? ' (' . $loc['code'] . ')' : '');
            }
        }

        if (empty($department)) {
            $department = !empty($role['location_name']) ? $role['location_name'] : ($targetUser['department'] ?? 'General');
        }

        if (!empty($newPassword) && strlen($newPassword) < 6) {
            $errors['password'] = 'New password must be at least 6 characters.';
        }

        if (!empty($errors)) {
            Response::error('Validation failed. Please correct the errors.', $errors, 422);
        }

        $updateData = [
            'full_name' => $fullName,
            'role_id' => $roleId,
            'location_id' => $locationId,
            'department' => $department,
            'phone' => $phone,
            'status' => $status
        ];

        $success = $this->userModel->update($userId, $updateData);

        if ($success && !empty($newPassword)) {
            $this->userModel->updatePassword($userId, $newPassword);
        }

        if ($success) {
            $roleDisplayName = isset($role) ? $role['display_name'] : 'Updated Role';
            Logger::log(
                'user_updated',
                'user',
                $userId,
                "Admin updated user '{$targetUser['username']}' ({$fullName}): Role set to '{$roleDisplayName}', Department set to '{$department}'."
            );

            $updatedUser = $this->userModel->findById($userId);
            Response::success('User account updated successfully!', ['user' => $updatedUser]);
        } else {
            Response::error('Failed to update user account.');
        }
    }

    public function toggleStatus(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin'])) {
            Response::error('Access restricted. Only Super Administrators can modify account statuses.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $userId = (int)($_POST['id'] ?? 0);
        $newStatus = $_POST['status'] ?? '';

        if (!in_array($newStatus, ['active', 'suspended', 'pending'])) {
            Response::error('Invalid status value.');
        }

        if ($userId === Auth::id()) {
            Response::error('You cannot change your own account status.');
        }

        $targetUser = $this->userModel->findById($userId);
        if (!$targetUser) {
            Response::error('User not found.');
        }

        $success = $this->userModel->update($userId, ['status' => $newStatus]);
        if ($success) {
            Logger::log(
                'user_status_change',
                'user',
                $userId,
                "Changed status of {$targetUser['username']} to '{$newStatus}'."
            );
            Response::success("User status updated to {$newStatus}.");
        } else {
            Response::error('Failed to update status.');
        }
    }

    public function delete(): void {
        Auth::requireAdmin();
        if (!in_array(Auth::role(), ['super_admin', 'admin'])) {
            Response::error('Access restricted. Only Super Administrators can delete user accounts.', [], 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $userId = (int)($_POST['id'] ?? 0);

        if ($userId === Auth::id()) {
            Response::error('You cannot delete your own active administrator account.');
        }

        $targetUser = $this->userModel->findById($userId);
        if (!$targetUser) {
            Response::error('User not found.');
        }

        $success = $this->userModel->delete($userId);
        if ($success) {
            Logger::log(
                'user_deleted',
                'user',
                $userId,
                "Deleted user account: {$targetUser['username']} ({$targetUser['email']})."
            );
            Response::success('User account removed successfully.');
        } else {
            Response::error('Could not delete user. The user may have associated transaction records.');
        }
    }
}
