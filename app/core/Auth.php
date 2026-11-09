<?php
namespace App\Core;

use App\Models\User;

class Auth {
    public static function attempt(string $identifier, string $password, ?string $department = null, ?string $loginYear = null, ?string $loginMonth = null): array {
        $userModel = new User();
        $user = $userModel->findByIdentifier($identifier);

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is currently ' . $user['status'] . '.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            Logger::log('failed_login_attempt', 'user', $user['id'], "Failed login attempt for user: {$identifier}");
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        // Login successful
        Session::set('user_id', $user['id']);
        Session::set('user_name', $user['full_name']);
        Session::set('username', $user['username']);
        Session::set('user_email', $user['email']);
        Session::set('user_role', $user['role_name']);
        Session::set('user_role_display', $user['role_display_name']);
        Session::set('user_department', !empty($department) ? $department : ($user['department'] ?? 'General'));
        Session::set('user_avatar_color', $user['avatar_color']);
        Session::set('login_year', $loginYear ?: date('Y'));
        Session::set('login_month', $loginMonth ?: date('F'));

        $userModel->updateLastLogin($user['id']);
        Logger::log('login_success', 'user', $user['id'], "User logged into EGL system. Year: " . ($loginYear ?: date('Y')) . ", Month: " . ($loginMonth ?: date('F')));

        $redirectUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/admin/index.php';

        return [
            'success' => true,
            'message' => 'Authentication successful. Redirecting...',
            'redirect' => $redirectUrl,
            'user' => [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'role' => $user['role_name'],
                'role_display' => $user['role_display_name']
            ]
        ];
    }

    public static function check(): bool {
        return Session::has('user_id');
    }

    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => Session::get('user_id'),
            'name' => Session::get('user_name'),
            'username' => Session::get('username'),
            'email' => Session::get('user_email'),
            'role' => Session::get('user_role'),
            'role_display' => Session::get('user_role_display'),
            'department' => Session::get('user_department'),
            'avatar_color' => Session::get('user_avatar_color'),
        ];
    }

    public static function id(): ?int {
        return Session::get('user_id');
    }

    public static function role(): ?string {
        return Session::get('user_role');
    }

    public static function isAdmin(): bool {
        $role = self::role();
        return in_array($role, ['super_admin', 'admin', 'code_admin', 'accountant']);
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                Response::error('Authentication required. Please sign in.', [], 401);
                return;
            }
            Session::setFlash('warning', 'Please sign in to access this page.');
            $loginUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/login.php';
            Response::redirect($loginUrl);
        }
    }

    public static function requireAuth(): void {
        self::requireLogin();
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            if ((isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
                Response::error('Access restricted to administrators.', [], 403);
                return;
            }
            Session::setFlash('error', 'Access restricted to administrators.');
            $homeUrl = (defined('BASE_PATH') ? BASE_PATH : '') . '/admin/index.php';
            Response::redirect($homeUrl);
        }
    }

    public static function logout(): void {
        if (self::check()) {
            Logger::log('logout', 'user', self::id(), 'User logged out.');
        }
        Session::destroy();
    }
}
