<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Controllers\CodeManagementController;
use App\Core\Response;

$action = $_GET['action'] ?? ($_POST['action'] ?? 'check_availability');
$controller = new CodeManagementController();

switch ($action) {
    case 'get_tiers':
        $controller->getTiers();
        break;
    case 'check_availability':
        $controller->checkAvailability();
        break;
    case 'create_request':
        $controller->createRequest();
        break;
    case 'list_requests':
        $controller->listRequests();
        break;
    case 'approve_request':
        $controller->approveRequest();
        break;
    case 'reject_request':
        $controller->rejectRequest();
        break;
    case 'import_to_station':
        $controller->importToStation();
        break;
    case 'station_inventory':
        $controller->stationInventory();
        break;
    case 'stats':
        $controller->stats();
        break;
    case 'list_master_items':
        $controller->listMasterItems();
        break;
    case 'add_to_master':
        $controller->addToMaster();
        break;
    case 'update_master_item':
        $controller->updateMasterItem();
        break;
    case 'save_master_code':
        $controller->saveMasterCode();
        break;
    case 'get_master_code':
        $controller->getMasterCode();
        break;
    case 'delete_master_code':
        $controller->deleteMasterCode();
        break;
    case 'upload_item_media':
        $controller->uploadItemMedia();
        break;
    case 'suggest_code_items':
        $controller->suggestCodeItems();
        break;
    case 'get_code_request':
        $controller->getCodeRequest();
        break;
    case 'update_code_request':
        $controller->updateCodeRequest();
        break;
    case 'delete_code_request':
        $controller->deleteCodeRequest();
        break;
    case 'list_tier_hierarchy':
        $controller->listTierHierarchy();
        break;
    case 'save_tier_category':
        $controller->saveTierCategory();
        break;
    case 'get_tier_category':
        $controller->getTierCategory();
        break;
    case 'delete_tier_category':
        $controller->deleteTierCategory();
        break;
    default:
        Response::error('Invalid code management endpoint action.', [], 400);
}
