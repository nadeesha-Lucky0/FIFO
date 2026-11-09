<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Models\MasterItem;
use App\Config\Database;

$masterModel = new MasterItem();
$db = Database::getConnection();

echo "=== 1. CHECK PENDING & REJECTED EXCLUSION FROM getAllMasterItems() ===\n";
// Create a temporary dummy pending request
$db->query("DELETE FROM code_requests WHERE item_name = '__TEST_VERIFY_ITEM__'");
$stmt = $db->prepare("
    INSERT INTO code_requests (
        tier1, tier2, tier3, item_name, uom, status, origin_station_id, requested_by, is_added_to_master, created_at
    ) VALUES (
        1, 1, 1, '__TEST_VERIFY_ITEM__', 'NOS', 'PENDING', 1, 1, 0, NOW()
    )
");
$stmt->execute();
$reqId = (int)$db->lastInsertId();
echo "Inserted test request with ID: $reqId (status: PENDING)\n";

// Search in getAllMasterItems
$results = $masterModel->getAllMasterItems('__TEST_VERIFY_ITEM__', 1, 1, 1);
echo "Search result for PENDING item: " . count($results) . " items (Expected: 0)\n";
if (count($results) > 0) {
    echo "FAIL: Pending item was found in getAllMasterItems!\n";
} else {
    echo "PASS: Pending item correctly excluded from getAllMasterItems.\n";
}

echo "\n=== 2. CHECK REJECTED ITEM EXCLUSION ===\n";
$stmt = $db->prepare("UPDATE code_requests SET status = 'REJECTED', review_remarks = 'Test reject' WHERE id = :id");
$stmt->execute([':id' => $reqId]);

$results = $masterModel->getAllMasterItems('__TEST_VERIFY_ITEM__', 1, 1, 1);
echo "Search result for REJECTED item: " . count($results) . " items (Expected: 0)\n";
if (count($results) > 0) {
    echo "FAIL: Rejected item was found in getAllMasterItems!\n";
} else {
    echo "PASS: Rejected item correctly excluded from getAllMasterItems.\n";
}

echo "\n=== 3. CHECK UPDATE (EDIT & RESUBMIT) ON REJECTED REQUEST ===\n";
$updateResult = $masterModel->updateCodeRequest($reqId, [
    'item_name' => '__TEST_VERIFY_ITEM_EDITED__',
    'uom' => 'SET',
    'unit_price' => 125.50
], 1);
echo "updateCodeRequest returned: " . ($updateResult ? "SUCCESS" : "FAIL") . "\n";

$chkStmt = $db->prepare("SELECT status, item_name, uom, unit_price, review_remarks FROM code_requests WHERE id = :id");
$chkStmt->execute([':id' => $reqId]);
$updatedRow = $chkStmt->fetch(PDO::FETCH_ASSOC);
echo "Updated status: " . $updatedRow['status'] . " (Expected: PENDING)\n";
echo "Updated item_name: " . $updatedRow['item_name'] . "\n";
echo "Updated uom: " . $updatedRow['uom'] . "\n";
echo "Updated unit_price: " . $updatedRow['unit_price'] . "\n";
echo "Updated remarks: " . var_export($updatedRow['review_remarks'], true) . " (Expected: NULL)\n";

if ($updatedRow['status'] === 'PENDING' && $updatedRow['review_remarks'] === null) {
    echo "PASS: Rejected item successfully edited and automatically reset to PENDING.\n";
} else {
    echo "FAIL: Status or remarks not properly reset.\n";
}

echo "\n=== 4. CHECK DELETE ON REJECTED REQUEST ===\n";
// Set to REJECTED again to test delete
$db->query("UPDATE code_requests SET status = 'REJECTED' WHERE id = $reqId");
$delResult = $masterModel->deleteCodeRequest($reqId, 1);
echo "deleteCodeRequest returned: " . ($delResult ? "SUCCESS" : "FAIL") . "\n";

$chkStmt->execute([':id' => $reqId]);
$delRow = $chkStmt->fetch(PDO::FETCH_ASSOC);
if (!$delRow) {
    echo "PASS: Request row was deleted from code_requests completely.\n";
} else {
    echo "FAIL: Request row still exists.\n";
}

echo "\n=== 5. CHECK MASTER ITEM PERSISTENCE & ADD BUTTON VISIBILITY ===\n";
// Check if master items exist
$masterRows = $masterModel->getAllMasterItems('', null, null, null, 5);
echo "Total sample master items returned: " . count($masterRows) . "\n";
if (!empty($masterRows)) {
    $first = $masterRows[0];
    echo "First item raw_code: " . ($first['raw_code'] ?? 'N/A') . "\n";
    echo "First item is_added_to_master: " . ($first['is_added_to_master'] ?? 'N/A') . " (Expected: 1)\n";
    echo "First item approval_status: " . ($first['approval_status'] ?? 'N/A') . "\n";
    if (!empty($first['is_added_to_master'])) {
        echo "PASS: Master item has is_added_to_master = 1.\n";
    }
}

echo "\n=== ALL VERIFICATION CHECKS COMPLETE ===\n";
