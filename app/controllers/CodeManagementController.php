<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\MasterItem;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Location;
use Exception;

class CodeManagementController {
    private MasterItem $masterModel;
    private ActivityLog $logModel;
    private User $userModel;
    private Location $locationModel;

    public function __construct() {
        Auth::requireAuth();
        $this->masterModel = new MasterItem();
        $this->logModel = new ActivityLog();
        $this->userModel = new User();
        $this->locationModel = new Location();
    }

    /**
     * Get Tier Hierarchy Categories
     */
    public function getTiers(): void {
        try {
            $parentId = Request::get('parent_id');
            if ($parentId !== null && $parentId !== '') {
                $children = $this->masterModel->getTierChildren((int)$parentId);
                Response::json(['tiers' => $children]);
                return;
            }

            $grouped = $this->masterModel->getAllTiersGrouped();
            Response::json($grouped);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Real-time availability check, next sequence generation, and duplicate query with progressive filtering
     */
    public function checkAvailability(): void {
        try {
            $t1Raw = Request::get('tier1');
            $t2Raw = Request::get('tier2');
            $t3Raw = Request::get('tier3');
            $t4Raw = Request::get('tier4');

            $tier1 = ($t1Raw !== null && $t1Raw !== '') ? (int)$t1Raw : null;
            $tier2 = ($t2Raw !== null && $t2Raw !== '') ? (int)$t2Raw : null;
            $tier3 = ($t3Raw !== null && $t3Raw !== '') ? (int)$t3Raw : null;
            $tier4 = ($t4Raw !== null && $t4Raw !== '') ? (int)$t4Raw : null;
            $query = Request::get('q', '');
            
            $currentUser = Auth::user();
            $stationId = (int)Request::get('station_id', 0);
            if (!$stationId && !empty($currentUser['location_id'])) {
                $stationId = (int)$currentUser['location_id'];
            }

            $result = $this->masterModel->checkAvailability($tier1, $tier2, $tier3, $stationId, $query, $tier4);
            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Create New Code Creation Request
     */
    public function createRequest(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token. Please refresh the page.', [], 403);
                return;
            }
            $currentUser = Auth::user();
            $userId = (int)($currentUser['id'] ?? 1);

            $tier1 = (int)Request::post('tier1', 1);
            if ($tier1 < 1 || $tier1 > 19) $tier1 = 1;

            $tier2 = (int)Request::post('tier2', 1);
            if ($tier2 < 1 || $tier2 > 19) $tier2 = 1;

            $tier3 = (int)Request::post('tier3', 1);
            if ($tier3 < 1 || $tier3 > 19) $tier3 = 1;

            $tier4Post = Request::post('suggested_tier4_seq');
            if ($tier4Post === null || $tier4Post === '') {
                $tier4Post = Request::post('tier4');
            }
            $tier4 = ($tier4Post !== null && $tier4Post !== '') ? (int)$tier4Post : 0;

            $itemNameRaw = Request::post('item_name');
            $itemName = !empty($itemNameRaw) ? mb_strtoupper(trim((string)$itemNameRaw), 'UTF-8') : '';
            $specsRaw = Request::post('specifications');
            $specs = !empty($specsRaw) ? mb_strtoupper(trim((string)$specsRaw), 'UTF-8') : '';
            $uomRaw = Request::post('uom', 'NOS');
            $uom = !empty($uomRaw) ? mb_strtoupper(trim((string)$uomRaw), 'UTF-8') : 'NOS';
            $stationId = (int)Request::post('origin_station_id');

            if (!$stationId && !empty($currentUser['location_id'])) {
                $stationId = (int)$currentUser['location_id'];
            }
            if (!$stationId) {
                $stationId = 1; // Default station
            }

            $unitPriceRaw = Request::post('unit_price');
            $unitPrice = ($unitPriceRaw !== null && trim((string)$unitPriceRaw) !== '' && is_numeric($unitPriceRaw)) ? (float)$unitPriceRaw : null;

            // Handle images upload
            $uploadDir = dirname(__DIR__, 2) . '/uploads/items';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $imgUrl1 = null;
            $imgUrl2 = null;

            if (!empty($_FILES['image_1']) && $_FILES['image_1']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_1']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $filename = "req_" . time() . "_1_" . rand(100, 999) . ".{$ext}";
                    if (move_uploaded_file($_FILES['image_1']['tmp_name'], $uploadDir . '/' . $filename)) {
                        $imgUrl1 = "/uploads/items/{$filename}";
                    }
                }
            }

            if (!empty($_FILES['image_2']) && $_FILES['image_2']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_2']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $filename = "req_" . time() . "_2_" . rand(100, 999) . ".{$ext}";
                    if (move_uploaded_file($_FILES['image_2']['tmp_name'], $uploadDir . '/' . $filename)) {
                        $imgUrl2 = "/uploads/items/{$filename}";
                    }
                }
            }

            $reqResult = $this->masterModel->createCodeRequest([
                'tier1' => $tier1,
                'tier2' => $tier2,
                'tier3' => $tier3,
                'suggested_tier4_seq' => $tier4,
                'item_name' => $itemName,
                'specifications' => $specs,
                'uom' => $uom,
                'unit_price' => $unitPrice,
                'image_url_1' => $imgUrl1,
                'image_url_2' => $imgUrl2,
                'origin_station_id' => $stationId,
                'requested_by' => $userId
            ]);

            try {
                $this->logModel->log($userId, 'CREATE_CODE_REQUEST', 'code_requests', $reqResult['id'], "Submitted code creation request {$reqResult['request_no']} for '{$itemName}'");
            } catch (\Throwable $logErr) {
                error_log("Logging error: " . $logErr->getMessage());
            }

            Response::json([
                'success' => true,
                'message' => "Code Creation Request {$reqResult['request_no']} submitted successfully for Administrator Approval.",
                'data' => $reqResult
            ], 201);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * List Code Requests
     */
    public function listRequests(): void {
        try {
            $status = Request::get('status');
            $stationId = Request::get('station_id');
            $activeOnly = !empty(Request::get('active_only')) || !empty(Request::get('uncommitted_only'));
            $currentUser = Auth::user();

            // If station staff, default or filter to their station unless admin
            $isAdmin = Auth::isAdmin();
            if (!$isAdmin && empty($stationId) && !empty($currentUser['location_id'])) {
                $stationId = (int)$currentUser['location_id'];
            }

            $requests = $this->masterModel->getCodeRequests($status, $stationId ? (int)$stationId : null, $activeOnly);
            Response::json(['requests' => $requests]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Admin Approve Request (Stages code with assigned sequence)
     */
    public function approveRequest(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators or Super Admins can approve item codes.', [], 403);
                return;
            }

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 0);
            $requestId = (int)Request::post('request_id');
            $remarks = Request::post('remarks', '');
            $itemNameRaw = Request::post('item_name');
            $itemName = ($itemNameRaw !== null && trim((string)$itemNameRaw) !== '') ? mb_strtoupper(trim((string)$itemNameRaw), 'UTF-8') : null;
            $specsRaw = Request::post('specifications');
            $specs = ($specsRaw !== null) ? mb_strtoupper(trim((string)$specsRaw), 'UTF-8') : null;
            $uomRaw = Request::post('uom');
            $uom = ($uomRaw !== null && trim((string)$uomRaw) !== '') ? mb_strtoupper(trim((string)$uomRaw), 'UTF-8') : null;
            $addToMasterNow = (bool)Request::post('add_to_master', false);
            
            $assignedSeqRaw = Request::post('assigned_tier4_seq');
            $assignedSeq = ($assignedSeqRaw !== null && trim((string)$assignedSeqRaw) !== '') ? (int)$assignedSeqRaw : null;

            if (!$requestId) {
                Response::error('Request ID is required.', [], 400);
                return;
            }

            $result = $this->masterModel->approveRequest($requestId, $adminId, $remarks, $itemName, $specs, $uom, $addToMasterNow, $assignedSeq);

            $this->logModel->log($adminId, 'APPROVE_CODE_REQUEST', 'code_requests', $requestId, "Approved request #{$requestId}, allocated Code: {$result['formatted_code']}");

            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Admin Commit Approved Code into FIFO Master Database (Turns Code ORANGE)
     */
    public function addToMaster(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Administrators can commit codes to Master Database.', [], 403);
                return;
            }

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 0);
            $requestId = (int)Request::post('request_id');

            if (!$requestId) {
                Response::error('Request ID is required.', [], 400);
                return;
            }

            $result = $this->masterModel->addToMaster($requestId, $adminId);

            $this->logModel->log($adminId, 'ADD_TO_MASTER', 'fifo_master_items', $result['master_item_id'], "Added item code {$result['raw_code']} to Central Master Database.");

            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Admin Reject Request
     */
    public function rejectRequest(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators or Super Admins can reject requests.', [], 403);
                return;
            }

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 0);
            $requestId = (int)Request::post('request_id');
            $remarks = Request::post('remarks', '');

            if (!$requestId) {
                Response::error('Request ID is required.', [], 400);
                return;
            }
            if (empty(trim($remarks))) {
                Response::error('Rejection remarks are required.', [], 400);
                return;
            }

            $this->masterModel->rejectRequest($requestId, $adminId, $remarks);

            $this->logModel->log($adminId, 'REJECT_CODE_REQUEST', 'code_requests', $requestId, "Rejected code request #{$requestId}. Reason: {$remarks}");

            Response::json([
                'success' => true,
                'message' => "Request #{$requestId} has been rejected."
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Import Master Item directly to Station without re-approval
     */
    public function importToStation(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            $currentUser = Auth::user();
            $userId = (int)($currentUser['id'] ?? 1);
            
            $masterItemId = (int)Request::post('master_item_id');
            $stationId = (int)Request::post('station_id');
            $bay = Request::post('bay_location', 'Bay-A1');

            if (!$stationId && !empty($currentUser['location_id'])) {
                $stationId = (int)$currentUser['location_id'];
            }
            if (!$stationId) {
                $stationId = 1;
            }

            if (!$masterItemId) {
                Response::error('Master Item ID is required.', [], 400);
                return;
            }

            $result = $this->masterModel->importToStation($stationId, $masterItemId, $userId, $bay);

            $this->logModel->log($userId, 'IMPORT_STATION_ITEM', 'station_items', $masterItemId, "Imported master item #{$masterItemId} to Station #{$stationId}");

            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Get Station's current imported inventory
     */
    public function stationInventory(): void {
        try {
            $currentUser = Auth::user();
            $stationId = (int)Request::get('station_id');
            $search = Request::get('q', '');

            if (!$stationId && !empty($currentUser['location_id'])) {
                $stationId = (int)$currentUser['location_id'];
            }
            if (!$stationId) {
                $stationId = 1;
            }

            $items = $this->masterModel->getStationItems($stationId, $search);
            Response::json(['items' => $items]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Get System Stats
     */
    public function stats(): void {
        try {
            $stats = $this->masterModel->getStats();
            Response::json($stats);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * List all registered master items with optional filters
     */
    public function listMasterItems(): void {
        try {
            $search = Request::get('q', '');
            $t1Raw = Request::get('tier1');
            $t2Raw = Request::get('tier2');
            $t3Raw = Request::get('tier3');
            $t1 = ($t1Raw !== null && $t1Raw !== '') ? (int)$t1Raw : null;
            $t2 = ($t2Raw !== null && $t2Raw !== '') ? (int)$t2Raw : null;
            $t3 = ($t3Raw !== null && $t3Raw !== '') ? (int)$t3Raw : null;
            $limit = Request::get('limit') ? (int)Request::get('limit') : 25000;
            // Always include both Master Added items and Approved (not yet added to master) items
            $includeUncommitted = true;

            $items = $this->masterModel->getAllMasterItems($search, $t1, $t2, $t3, $limit, $includeUncommitted);
            Response::json(['items' => $items, 'total' => count($items)]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Code Admin: Direct Add or Update Master Item Code (No approval required)
     */
    public function saveMasterCode(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators or Super Admins can directly define or update Master Codes.', [], 403);
                return;
            }

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 1);

            $id = Request::post('id');
            $tier1 = (int)Request::post('tier1', 1);
            $tier2 = (int)Request::post('tier2', 1);
            $tier3 = (int)Request::post('tier3', 1);
            $tier4Seq = Request::post('tier4_seq');
            $itemName = Request::post('item_name');
            $specs = Request::post('specifications');
            $uom = Request::post('uom', 'NOS');
            $originStationId = Request::post('origin_station_id');
            $status = Request::post('status', 'ACTIVE');
            $autoImportStation = (bool)Request::post('auto_import_station', false);
            $allowDuplicateName = (bool)Request::post('allow_duplicate_name', false);

            $data = [
                'id' => (!empty($id) ? (int)$id : null),
                'tier1' => $tier1,
                'tier2' => $tier2,
                'tier3' => $tier3,
                'tier4_seq' => ($tier4Seq !== null && trim((string)$tier4Seq) !== '') ? (int)$tier4Seq : null,
                'item_name' => $itemName,
                'specifications' => $specs,
                'uom' => $uom,
                'origin_station_id' => !empty($originStationId) ? (int)$originStationId : null,
                'status' => $status,
                'auto_import_station' => $autoImportStation,
                'allow_duplicate_name' => $allowDuplicateName
            ];

            $result = $this->masterModel->saveDirectMasterCode($data, $adminId);

            $this->logModel->log(
                $adminId,
                $result['action'] === 'CREATED' ? 'CREATE_MASTER_CODE' : 'UPDATE_MASTER_CODE',
                'fifo_master_items',
                $result['master_id'],
                "{$result['action']} master code {$result['raw_code']} ({$result['item_name']})"
            );

            Response::json($result, $result['action'] === 'CREATED' ? 201 : 200);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Code Admin: Get Single Master Code Details
     */
    public function getMasterCode(): void {
        try {
            $id = (int)Request::get('id');
            if (!$id) {
                Response::error('Master Item ID is required.', [], 400);
                return;
            }

            $item = $this->masterModel->getMasterItemById($id);
            if (!$item) {
                Response::error('Master item not found.', [], 404);
                return;
            }

            Response::json(['item' => $item]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Code Admin: Delete Master Code
     */
    public function deleteMasterCode(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators or Super Admins can delete master codes.', [], 403);
                return;
            }

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 1);
            $id = (int)Request::post('id');

            if (!$id) {
                Response::error('Master Item ID is required.', [], 400);
                return;
            }

            $item = $this->masterModel->getMasterItemById($id);
            $this->masterModel->deleteMasterItem($id, $adminId);

            if ($item) {
                $this->logModel->log(
                    $adminId,
                    'DELETE_MASTER_CODE',
                    'fifo_master_items',
                    $id,
                    "Deleted master code {$item['raw_code']} ({$item['item_name']})"
                );
            }

            Response::json(['success' => true, 'message' => "Master Code #{$id} deleted successfully."]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Code Admin: Update Master Item Name and Unit of Measurement (UoM)
     */
    public function updateMasterItem(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators or Super Admins can update master items.', [], 403);
                return;
            }

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 1);

            $masterId = Request::post('master_id') ? (int)Request::post('master_id') : null;
            $requestId = Request::post('request_id') ? (int)Request::post('request_id') : null;
            $itemName = (string)Request::post('item_name', '');
            $uom = (string)Request::post('uom', '');

            $result = $this->masterModel->updateMasterItemNameAndUom($masterId, $requestId, $itemName, $uom, $adminId);

            $this->logModel->log(
                $adminId,
                'UPDATE_MASTER_ITEM',
                'fifo_master_items',
                $masterId ?: $requestId,
                "Updated Master Item: Name='{$itemName}', UoM='{$uom}'"
            );

            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Upload Image or update Unit Price for Master Item
     */
    public function uploadItemMedia(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators or Super Admins can update item media.', [], 403);
                return;
            }

            $masterId = (int)Request::post('master_id');
            if (!$masterId) {
                Response::error('Master Item ID is required.', [], 400);
                return;
            }

            $db = \App\Config\Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM fifo_master_items WHERE id = :id");
            $stmt->execute([':id' => $masterId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                Response::error('Item not found in master database.', [], 404);
                return;
            }

            $updates = [];
            $params = [':id' => $masterId];

            // Unit Price update
            if (isset($_POST['unit_price'])) {
                $rawPrice = trim((string)$_POST['unit_price']);
                if ($rawPrice === '') {
                    $updates[] = "unit_price = NULL";
                } else {
                    $priceVal = (float)$rawPrice;
                    $updates[] = "unit_price = :price";
                    $params[':price'] = $priceVal;
                }
            }

            // Upload directory
            $uploadDir = dirname(__DIR__, 2) . '/uploads/items';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            // Handle Image 1
            if (!empty($_POST['delete_image_1'])) {
                $updates[] = "image_url_1 = NULL";
            } elseif (!empty($_FILES['image_1']) && $_FILES['image_1']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_1']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) {
                    Response::error('Invalid image type for Image 1. Allowed: JPG, PNG, WEBP, GIF.', [], 400);
                    return;
                }
                $filename = "item_{$masterId}_1_" . time() . ".{$ext}";
                $targetPath = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['image_1']['tmp_name'], $targetPath)) {
                    $url = "/uploads/items/{$filename}";
                    $updates[] = "image_url_1 = :img1";
                    $params[':img1'] = $url;
                }
            }

            // Handle Image 2
            if (!empty($_POST['delete_image_2'])) {
                $updates[] = "image_url_2 = NULL";
            } elseif (!empty($_FILES['image_2']) && $_FILES['image_2']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_2']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExts)) {
                    Response::error('Invalid image type for Image 2. Allowed: JPG, PNG, WEBP, GIF.', [], 400);
                    return;
                }
                $filename = "item_{$masterId}_2_" . time() . ".{$ext}";
                $targetPath = $uploadDir . '/' . $filename;
                if (move_uploaded_file($_FILES['image_2']['tmp_name'], $targetPath)) {
                    $url = "/uploads/items/{$filename}";
                    $updates[] = "image_url_2 = :img2";
                    $params[':img2'] = $url;
                }
            }

            if (!empty($updates)) {
                $sql = "UPDATE fifo_master_items SET " . implode(', ', $updates) . " WHERE id = :id";
                $updateStmt = $db->prepare($sql);
                $updateStmt->execute($params);
            }

            // Fetch fresh row
            $freshStmt = $db->prepare("SELECT * FROM fifo_master_items WHERE id = :id");
            $freshStmt->execute([':id' => $masterId]);
            $freshItem = $freshStmt->fetch(PDO::FETCH_ASSOC);

            Response::json([
                'success' => true,
                'message' => 'Item details, pricing, and images updated successfully.',
                'item' => $freshItem
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Word-by-word live autocomplete, spelling corrector, and duplicate advisor
     */
    public function suggestCodeItems(): void {
        try {
            $query = Request::get('q', '');
            $t1Raw = Request::get('tier1');
            $t2Raw = Request::get('tier2');
            $t3Raw = Request::get('tier3');
            $t1 = ($t1Raw !== null && $t1Raw !== '') ? (int)$t1Raw : null;
            $t2 = ($t2Raw !== null && $t2Raw !== '') ? (int)$t2Raw : null;
            $t3 = ($t3Raw !== null && $t3Raw !== '') ? (int)$t3Raw : null;

            $result = $this->masterModel->searchWithSpellCheck($query, $t1, $t2, $t3);
            Response::json($result);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * List Hierarchical Excel Tree Rows (Tier 1, Tier 2, Tier 3)
     */
    public function listTierHierarchy(): void {
        try {
            $search = Request::get('q', '');
            $t1Raw = Request::get('tier1');
            $t1 = ($t1Raw !== null && $t1Raw !== '') ? (int)$t1Raw : null;

            $rows = $this->masterModel->getExcelHierarchyTree($search, $t1);
            Response::json(['rows' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Direct Add / Update Tier Category (No approval required)
     */
    public function saveTierCategory(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators can define or update tier categories.', [], 403);
                return;
            }

            $id = Request::post('id');
            $level = (int)Request::post('level', 1);
            $codeVal = Request::post('code_val');
            $parentId = Request::post('parent_id');
            $name = Request::post('name');
            $description = Request::post('description');

            $data = [
                'id' => (!empty($id) ? (int)$id : null),
                'level' => $level,
                'code_val' => ($codeVal !== null && trim((string)$codeVal) !== '') ? (int)$codeVal : null,
                'parent_id' => (!empty($parentId) ? (int)$parentId : null),
                'name' => $name,
                'description' => $description
            ];

            $result = $this->masterModel->saveTierNode($data);

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 1);
            $this->logModel->log(
                $adminId,
                $result['action'] === 'CREATED' ? 'CREATE_TIER_CATEGORY' : 'UPDATE_TIER_CATEGORY',
                'tier_categories',
                $result['id'],
                "{$result['action']} Tier {$level} category {$name}"
            );

            Response::json($result, $result['action'] === 'CREATED' ? 201 : 200);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Get Single Tier Category by ID
     */
    public function getTierCategory(): void {
        try {
            $id = (int)Request::get('id');
            if (!$id) {
                Response::error('Category ID is required.', [], 400);
                return;
            }

            $node = $this->masterModel->getTierNodeById($id);
            if (!$node) {
                Response::error('Category not found.', [], 404);
                return;
            }

            Response::json(['category' => $node]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Delete Tier Category
     */
    public function deleteTierCategory(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }
            if (!Auth::isAdmin()) {
                Response::error('Unauthorized. Only Code Administrators can delete categories.', [], 403);
                return;
            }

            $id = (int)Request::post('id');
            if (!$id) {
                Response::error('Category ID is required.', [], 400);
                return;
            }

            $this->masterModel->deleteTierNode($id);

            $currentUser = Auth::user();
            $adminId = (int)($currentUser['id'] ?? 1);
            $this->logModel->log($adminId, 'DELETE_TIER_CATEGORY', 'tier_categories', $id, "Deleted Tier category #{$id}");

            Response::json(['success' => true, 'message' => "Category #{$id} deleted successfully."]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Get Single Code Creation Request
     */
    public function getCodeRequest(): void {
        try {
            $id = (int)Request::get('id');
            if (!$id) {
                Response::error('Request ID is required.', [], 400);
                return;
            }
            $req = $this->masterModel->getCodeRequestById($id);
            if (!$req) {
                Response::error('Code request not found.', [], 404);
                return;
            }
            Response::json(['request' => $req]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Update Code Creation Request Details
     * STRICT BUSINESS RULE: Allowed ONLY when status is PENDING.
     * Once approved by Administrator or in Master, editing is blocked.
     */
    public function updateCodeRequest(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }

            $requestId = (int)Request::post('request_id');
            if (!$requestId) {
                Response::error('Request ID is required.', [], 400);
                return;
            }

            $currentUser = Auth::user();
            $userId = (int)($currentUser['id'] ?? 1);

            $req = $this->masterModel->getCodeRequestById($requestId);
            if (!$req) {
                Response::error('Code request not found.', [], 404);
                return;
            }

            // Verify status is PENDING or REJECTED
            if (!empty($req['is_added_to_master']) || !in_array($req['status'], ['PENDING', 'REJECTED'], true)) {
                Response::error('This code creation request has already been approved and cannot be edited.', [], 403);
                return;
            }

            // Verify authorization: requester, station member, or admin
            $isAdmin = Auth::isAdmin();
            if (!$isAdmin && (int)$req['requested_by'] !== $userId && (int)$req['origin_station_id'] !== (int)($currentUser['location_id'] ?? 0)) {
                Response::error('Unauthorized. You can only edit your own station requests.', [], 403);
                return;
            }

            $itemNameRaw = Request::post('item_name');
            $itemName = !empty($itemNameRaw) ? mb_strtoupper(trim((string)$itemNameRaw), 'UTF-8') : $req['item_name'];
            $uomRaw = Request::post('uom');
            $uom = !empty($uomRaw) ? mb_strtoupper(trim((string)$uomRaw), 'UTF-8') : $req['uom'];

            $unitPrice = $req['unit_price'];
            if (isset($_POST['unit_price'])) {
                $rawPrice = trim((string)$_POST['unit_price']);
                $unitPrice = ($rawPrice !== '' && is_numeric($rawPrice)) ? (float)$rawPrice : null;
            }

            $uploadDir = dirname(__DIR__, 2) . '/uploads/items';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            $img1 = $req['image_url_1'];
            if (!empty($_POST['delete_image_1'])) {
                $img1 = null;
            } elseif (!empty($_FILES['image_1']) && $_FILES['image_1']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_1']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $filename = "req_{$requestId}_1_" . time() . ".{$ext}";
                    if (move_uploaded_file($_FILES['image_1']['tmp_name'], $uploadDir . '/' . $filename)) {
                        $img1 = "/uploads/items/{$filename}";
                    }
                }
            }

            $img2 = $req['image_url_2'];
            if (!empty($_POST['delete_image_2'])) {
                $img2 = null;
            } elseif (!empty($_FILES['image_2']) && $_FILES['image_2']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_2']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExts)) {
                    $filename = "req_{$requestId}_2_" . time() . ".{$ext}";
                    if (move_uploaded_file($_FILES['image_2']['tmp_name'], $uploadDir . '/' . $filename)) {
                        $img2 = "/uploads/items/{$filename}";
                    }
                }
            }

            $updated = $this->masterModel->updateCodeRequest($requestId, [
                'item_name' => $itemName,
                'uom' => $uom,
                'unit_price' => $unitPrice,
                'image_url_1' => $img1,
                'image_url_2' => $img2
            ], $userId);

            try {
                $this->logModel->log($userId, 'UPDATE_CODE_REQUEST', 'code_requests', $requestId, "Updated and resubmitted code request {$req['request_no']}");
            } catch (\Throwable $e) {}

            Response::json([
                'success' => true,
                'message' => "Code request {$req['request_no']} updated and resubmitted successfully for Administrator Approval.",
                'data' => $updated
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * Delete Code Request
     * - Safety guarantee: Master DB items are NOT affected.
     */
    public function deleteCodeRequest(): void {
        try {
            $csrfToken = Request::post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!Session::validateCsrf($csrfToken)) {
                Response::error('Invalid or expired CSRF security token.', [], 403);
                return;
            }

            $requestId = (int)Request::post('request_id');
            if (!$requestId) {
                Response::error('Request ID is required.', [], 400);
                return;
            }

            $currentUser = Auth::user();
            $userId = (int)($currentUser['id'] ?? 1);

            $this->masterModel->deleteCodeRequest($requestId, $userId);

            try {
                $this->logModel->log($userId, 'DELETE_CODE_REQUEST', 'code_requests', $requestId, "Deleted code request #{$requestId}");
            } catch (\Throwable $e) {}

            Response::json([
                'success' => true,
                'message' => "Code request deleted successfully."
            ]);
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
