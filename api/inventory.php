<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Config\Database;

Auth::requireLogin();

$locationId = $_POST['location_id'] ?? $_GET['location_id'] ?? ($_SESSION['user']['location_id'] ?? null);
$db = Database::getStationConnection($locationId);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Validate CSRF for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!Session::validateCsrf($csrfToken)) {
        Response::error('Invalid security token. Please refresh the page.', [], 403);
    }
}

switch ($action) {
    case 'get_unposted_document':
        $docNo = trim($_GET['doc_no'] ?? $_POST['doc_no'] ?? '');
        if (empty($docNo)) {
            Response::error('Document number is required.');
        }
        try {
            $stmt = $db->prepare("SELECT * FROM unposted_documents WHERE doc_no = :doc LIMIT 1");
            $stmt->execute([':doc' => $docNo]);
            $doc = $stmt->fetch();
            if (!$doc) {
                Response::error('Document not found in database.');
            }
            Response::success('Document loaded successfully', ['document' => $doc]);
        } catch (\Exception $e) {
            Response::error('Database Error: ' . $e->getMessage());
        }
        break;

    case 'update_unposted_document':
        $docNo = trim($_POST['doc_no'] ?? '');
        if (empty($docNo)) {
            Response::error('Please select a valid document number to update.');
        }

        $docProfile = trim($_POST['update_doc_profile'] ?? '');
        $status = trim($_POST['update_status'] ?? 'Draft');
        $receiptFrom = trim($_POST['update_receipt_from'] ?? '');
        $deliveryDate = !empty($_POST['update_delivery_date']) ? trim($_POST['update_delivery_date']) : null;
        $sourceDept = trim($_POST['update_source_dept'] ?? '832.00');
        $accDate = !empty($_POST['update_acc_date']) ? trim($_POST['update_acc_date']) : null;
        $dueDate = !empty($_POST['update_due_date']) ? trim($_POST['update_due_date']) : null;
        $warehouse = trim($_POST['update_warehouse'] ?? '');
        $vat = (float)($_POST['val_vat'] ?? 0.0);
        $transport = (float)($_POST['val_transport'] ?? 0.0);
        $nbt = (float)($_POST['val_nbt'] ?? 0.0);
        $otherCost = (float)($_POST['val_other'] ?? 0.0);
        $invoiceNum = trim($_POST['ref_invoice_number'] ?? '');
        $orderRef = trim($_POST['ref_order_reference'] ?? '');
        $otherRef1 = trim($_POST['ref_other_1'] ?? '');
        $otherRef2 = trim($_POST['ref_other_2'] ?? '');
        $remarks = trim($_POST['remarks_text'] ?? '');
        $invoiceTotal = (float)str_replace(',', '', $_POST['invoice_total_without_tax'] ?? 0.0);
        $isSingle = isset($_POST['is_single_material']) ? 1 : 0;
        $discountForAll = (float)str_replace(['%', ','], '', $_POST['discount_for_all'] ?? 0.0);
        $isPercent = isset($_POST['is_percentage']) ? 1 : 0;

        try {
            $stmt = $db->prepare("
                UPDATE unposted_documents SET
                    doc_profile = CASE WHEN :doc_profile != '' THEN :doc_profile_set ELSE doc_profile END,
                    status = :status,
                    receipt_from = CASE WHEN :receipt_from != '' THEN :receipt_from_set ELSE receipt_from END,
                    delivery_date = :deliv_date,
                    source_dept = :source_dept,
                    acc_date = :acc_date,
                    due_date = :due_date,
                    warehouse = CASE WHEN :warehouse != '' THEN :warehouse_set ELSE warehouse END,
                    vat = :vat,
                    transport = :transport,
                    nbt = :nbt,
                    other_cost = :other_cost,
                    invoice_number = :invoice_num,
                    order_reference = :order_ref,
                    other_ref_1 = :other_ref_1,
                    other_ref_2 = :other_ref_2,
                    remarks = :remarks,
                    invoice_total = :invoice_total,
                    is_single_material = :is_single,
                    discount_for_all = :discount_for_all,
                    is_percentage = :is_percent,
                    updated_at = CURRENT_TIMESTAMP
                WHERE doc_no = :doc_no
            ");

            $stmt->execute([
                ':doc_profile' => $docProfile,
                ':doc_profile_set' => $docProfile,
                ':status' => $status,
                ':receipt_from' => $receiptFrom,
                ':receipt_from_set' => $receiptFrom,
                ':deliv_date' => $deliveryDate,
                ':source_dept' => $sourceDept,
                ':acc_date' => $accDate,
                ':due_date' => $dueDate,
                ':warehouse' => $warehouse,
                ':warehouse_set' => $warehouse,
                ':vat' => $vat,
                ':transport' => $transport,
                ':nbt' => $nbt,
                ':other_cost' => $otherCost,
                ':invoice_num' => $invoiceNum,
                ':order_ref' => $orderRef,
                ':other_ref_1' => $otherRef1,
                ':other_ref_2' => $otherRef2,
                ':remarks' => $remarks,
                ':invoice_total' => $invoiceTotal,
                ':is_single' => $isSingle,
                ':discount_for_all' => $discountForAll,
                ':is_percent' => $isPercent,
                ':doc_no' => $docNo
            ]);

            // Retrieve updated document
            $fetchStmt = $db->prepare("SELECT * FROM unposted_documents WHERE doc_no = :doc LIMIT 1");
            $fetchStmt->execute([':doc' => $docNo]);
            $updatedDoc = $fetchStmt->fetch();

            Response::success('Unposted document [' . $docNo . '] successfully updated in database.', [
                'document' => $updatedDoc
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to update unposted document: ' . $e->getMessage());
        }
        break;

    case 'create_receipt':
        $batchNumber = trim($_POST['batch_number'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Standard');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? 0.00);
        $receivedDate = trim($_POST['received_date'] ?? date('Y-m-d'));
        $locationBin = trim($_POST['location_bin'] ?? 'Zone A-01');

        if (empty($batchNumber) || empty($sku) || empty($itemName) || $quantity <= 0) {
            Response::error('Please fill in all required fields with valid quantities.');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO fifo_batches 
                (batch_number, sku, item_name, category, initial_quantity, remaining_quantity, unit_cost, received_date, location_bin, status, created_by)
                VALUES 
                (:batch_number, :sku, :item_name, :category, :initial_qty, :remaining_qty, :unit_cost, :received_date, :location_bin, 'queued', :created_by)
            ");
            $stmt->execute([
                ':batch_number' => $batchNumber,
                ':sku' => strtoupper($sku),
                ':item_name' => $itemName,
                ':category' => $category,
                ':initial_qty' => $quantity,
                ':remaining_qty' => $quantity,
                ':unit_cost' => $unitCost,
                ':received_date' => $receivedDate,
                ':location_bin' => $locationBin,
                ':created_by' => Auth::id() ?: 1
            ]);
            $batchId = (int)$db->lastInsertId();

            // Record transaction
            $totalVal = $quantity * $unitCost;
            $stmtTx = $db->prepare("
                INSERT INTO inventory_transactions 
                (batch_id, transaction_type, quantity, unit_price, total_value, transaction_date, reference_no, created_by)
                VALUES 
                (:batch_id, 'receipt', :qty, :price, :total_val, :tx_date, :ref_no, :created_by)
            ");
            $stmtTx->execute([
                ':batch_id' => $batchId,
                ':qty' => $quantity,
                ':price' => $unitCost,
                ':total_val' => $totalVal,
                ':tx_date' => $receivedDate,
                ':ref_no' => $batchNumber,
                ':created_by' => Auth::id() ?: 1
            ]);

            Response::success('Goods Received Note (GRN) created successfully and queued in FIFO system.', ['batch_id' => $batchId]);
        } catch (\Exception $e) {
            Response::error('Database Error: ' . $e->getMessage());
        }
        break;

    case 'create_issue':
        $batchId = (int)($_POST['batch_id'] ?? 0);
        $issueQty = (int)($_POST['issue_quantity'] ?? 0);
        $department = trim($_POST['issue_department'] ?? 'Operations');
        $issueDate = trim($_POST['issue_date'] ?? date('Y-m-d'));
        $voucherNo = trim($_POST['issue_voucher_no'] ?? 'GIN-' . time());
        $purpose = trim($_POST['purpose'] ?? 'Maintenance Issue');

        if ($batchId <= 0 || $issueQty <= 0) {
            Response::error('Please select a valid batch and issue quantity.');
        }

        try {
            $stmt = $db->prepare("SELECT * FROM fifo_batches WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $batchId]);
            $batch = $stmt->fetch();

            if (!$batch) {
                Response::error('Selected FIFO batch not found.');
            }

            if ($batch['remaining_quantity'] < $issueQty) {
                Response::error('Requested quantity (' . $issueQty . ') exceeds available remaining batch stock (' . $batch['remaining_quantity'] . ').');
            }

            $newRemaining = $batch['remaining_quantity'] - $issueQty;
            $newStatus = ($newRemaining === 0) ? 'depleted' : 'active_dispatch';

            $updateStmt = $db->prepare("UPDATE fifo_batches SET remaining_quantity = :rem, status = :st WHERE id = :id");
            $updateStmt->execute([
                ':rem' => $newRemaining,
                ':st' => $newStatus,
                ':id' => $batchId
            ]);

            $unitCost = (float)$batch['unit_cost'];
            $totalVal = $issueQty * $unitCost;

            $stmtTx = $db->prepare("
                INSERT INTO inventory_transactions 
                (batch_id, transaction_type, quantity, unit_price, total_value, transaction_date, reference_no, created_by)
                VALUES 
                (:batch_id, 'issue', :qty, :price, :total_val, :tx_date, :ref_no, :created_by)
            ");
            $stmtTx->execute([
                ':batch_id' => $batchId,
                ':qty' => $issueQty,
                ':price' => $unitCost,
                ':total_val' => $totalVal,
                ':tx_date' => $issueDate,
                ':ref_no' => $voucherNo,
                ':created_by' => Auth::id() ?: 1
            ]);

            Response::success('Stock Issue Entry created successfully. Dispatched from Batch ' . $batch['batch_number'] . '.', [
                'remaining_quantity' => $newRemaining,
                'status' => $newStatus
            ]);
        } catch (\Exception $e) {
            Response::error('Database Error: ' . $e->getMessage());
        }
        break;

    default:
        Response::error('Invalid inventory endpoint action.', [], 400);
}
