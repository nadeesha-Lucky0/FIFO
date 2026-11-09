<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Controllers\UserController;
use App\Core\Response;

$action = $_GET['action'] ?? ($_POST['action'] ?? 'index');
$controller = new UserController();

switch ($action) {
    case 'index':
    case 'list':
        $controller->index();
        break;
    case 'create':
        $controller->create();
        break;
    case 'status':
        $controller->toggleStatus();
        break;
    case 'delete':
        $controller->delete();
        break;
    default:
        Response::error('Invalid users endpoint action.', [], 400);
}
