<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;

class AuthController {
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', [], 405);
        }

        // Validate CSRF if provided in request
        $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Session::validateCsrf($csrfToken)) {
            Response::error('Invalid security token. Please refresh the page and try again.', [], 403);
        }

        $identifier = trim($_POST['identifier'] ?? $_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $department = trim($_POST['department'] ?? '');
        $loginYear = trim($_POST['login_year'] ?? '');
        $loginMonth = trim($_POST['login_month'] ?? '');

        if (empty($identifier) || empty($password)) {
            Response::error('Please enter both your Username and Password.');
        }

        $result = Auth::attempt($identifier, $password, $department, $loginYear, $loginMonth);

        if ($result['success']) {
            Response::success($result['message'], [
                'redirect' => $result['redirect'],
                'user' => $result['user']
            ]);
        } else {
            Response::error($result['message'], [], 401);
        }
    }

    public function logout(): void {
        Auth::logout();
        Response::redirect('/login.php');
    }
}
