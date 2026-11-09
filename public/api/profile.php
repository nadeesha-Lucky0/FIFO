<?php
require_once dirname(__DIR__, 2) . '/app/config/config.php';

use App\Controllers\ProfileController;
use App\Core\Response;

$action = $_GET['action'] ?? ($_POST['action'] ?? 'update');
$controller = new ProfileController();

switch ($action) {
    case 'update':
        $controller->updateInfo();
        break;
    case 'password':
        $controller->changePassword();
        break;
    default:
        Response::error('Invalid profile endpoint action.', [], 400);
}
