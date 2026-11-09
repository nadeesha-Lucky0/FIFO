<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;

class ProfileController {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function updateInfo(): void {
        Auth::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $currentUserId = Auth::id();
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $avatarColor = trim($_POST['avatar_color'] ?? '#4f46e5');

        $errors = [];
        if (empty($fullName)) {
            $errors['full_name'] = 'Full Name cannot be blank.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid corporate email is required.';
        } elseif ($this->userModel->isEmailTaken($email, $currentUserId)) {
            $errors['email'] = 'This email is already in use by another team member.';
        }

        if (!empty($errors)) {
            Response::error('Please correct the validation errors.', $errors, 422);
        }

        $updateData = [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'department' => $department,
            'avatar_color' => $avatarColor,
        ];

        $updated = $this->userModel->update($currentUserId, $updateData);

        if ($updated) {
            // Update session cache
            Session::set('user_name', $fullName);
            Session::set('user_email', $email);
            Session::set('user_department', $department);
            Session::set('user_avatar_color', $avatarColor);

            Logger::log('profile_updated', 'user', $currentUserId, 'Updated personal profile details.');

            Response::success('Profile updated successfully!', [
                'user' => [
                    'name' => $fullName,
                    'email' => $email,
                    'department' => $department,
                    'phone' => $phone,
                    'avatar_color' => $avatarColor
                ]
            ]);
        } else {
            Response::error('Failed to update profile information.');
        }
    }

    public function changePassword(): void {
        Auth::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token.', [], 403);
        }

        $currentUserId = Auth::id();
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $errors = [];

        if (empty($currentPassword)) {
            $errors['current_password'] = 'Current password is required.';
        }

        if (empty($newPassword) || strlen($newPassword) < 6) {
            $errors['new_password'] = 'New password must be at least 6 characters.';
        }

        if ($newPassword !== $confirmPassword) {
            $errors['confirm_password'] = 'Password confirmation does not match.';
        }

        if (!empty($errors)) {
            Response::error('Password validation failed.', $errors, 422);
        }

        $user = $this->userModel->findById($currentUserId);
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            Response::error('Your current password was incorrect.', ['current_password' => 'Incorrect current password.'], 422);
        }

        $updated = $this->userModel->updatePassword($currentUserId, $newPassword);

        if ($updated) {
            Logger::log('password_changed', 'user', $currentUserId, 'Changed security access password.');
            Response::success('Password has been securely updated.');
        } else {
            Response::error('Unable to update password. Please try again.');
        }
    }
}
