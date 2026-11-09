<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Controllers\AuthController;
use App\Core\Response;

$action = $_GET['action'] ?? ($_POST['action'] ?? 'login');
$controller = new AuthController();

switch ($action) {
    case 'login':
        $controller->login();
        break;
    case 'logout':
        $controller->logout();
        break;
    default:
        Response::error('Invalid auth endpoint action.', [], 400);
}
