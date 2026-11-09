<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Controllers\IssueCodeController;
use App\Core\Response;

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$controller = new IssueCodeController();

switch ($action) {
    case 'list':
        $controller->index();
        break;
    case 'grouped':
        $controller->grouped();
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
    case 'check_duplicate':
        $controller->checkDuplicate();
        break;
    default:
        Response::error('Invalid issue codes action.', [], 400);
}
