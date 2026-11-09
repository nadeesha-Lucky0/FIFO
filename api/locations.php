<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Controllers\LocationController;
use App\Core\Response;

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$controller = new LocationController();

switch ($action) {
    case 'list':
        $controller->index();
        break;
    case 'create':
        $controller->create();
        break;
    case 'update':
        $controller->update();
        break;
    case 'delete':
        $controller->delete();
        break;
    default:
        Response::error('Invalid location action.', [], 400);
}
