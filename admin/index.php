<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Session;
use App\Models\User;
use App\Models\Role;
use App\Models\Location;
use App\Models\Department;
use App\Models\ActivityLog;
use App\Models\Permission;

Auth::requireLogin();

$userModel = new User();
$roleModel = new Role();
$locModel = new Location();
$deptModel = new Department();
$logModel = new ActivityLog();
$permModel = new Permission();
$currentUser = Auth::user();
$adminDetails = $userModel->findById(Auth::id());

$userRole = $currentUser['role'] ?? 'operator';
$userRoleId = (int) ($adminDetails['role_id'] ?? 4);

$isSuperAdmin = ($userRole === 'super_admin');
$isCodeAdmin = ($userRole === 'code_admin');
$isAccountant = ($userRole === 'accountant');
$isAdmin = in_array($userRole, ['super_admin', 'admin', 'code_admin', 'accountant']);

$issueCodeModel = new \App\Models\IssueDestinationCode();
$allIssueCodes = $isAdmin ? $issueCodeModel->getAll() : [];
$issueCategoryCounts = $isAdmin ? $issueCodeModel->getCategoryCounts() : [];
$groupedIssueCodes = $issueCodeModel->getAllGrouped();

// Check if showing staff portal (non-admins, or admin previewing staff view)
$showStaffView = !$isAdmin || (isset($_GET['view']) && $_GET['view'] === 'staff');

// Strict tab authorization for admin console
$canAccessMasterCodes = in_array($userRole, ['code_admin', 'admin']);
$canAccessIssueCodes = in_array($userRole, ['accountant', 'admin']);
$canAccessLocations = in_array($userRole, ['super_admin', 'admin']);
$canAccessDepartments = in_array($userRole, ['super_admin', 'admin']);
$canAccessUserRoles = in_array($userRole, ['super_admin', 'admin']);
$canAccessUsers = in_array($userRole, ['super_admin', 'admin']);
$canAccessRoles = $isSuperAdmin;

$hasAdminTabs = $isAdmin;
$reqTab = $_GET['tab'] ?? '';
$defaultActiveTab = ($isAccountant || ($reqTab === 'issue-codes' && $canAccessIssueCodes)) ? 'tab-issue-codes' : (($canAccessMasterCodes && $isCodeAdmin) ? 'tab-define-code' : (($canAccessLocations || $canAccessDepartments) ? 'tab-departments' : 'tab-profile'));

$users = $canAccessUsers ? $userModel->getAll() : [];
$allRoles = $roleModel->all();
$roles = $allRoles;
$locations = $canAccessLocations ? $locModel->getAll() : [];
$allStationLocations = $locModel->getAll();
$departments = $canAccessDepartments ? $deptModel->getAll() : [];

// Code Management Model & Data Initialization
$masterItemModel = new \App\Models\MasterItem();
$masterItemStats = $masterItemModel->getStats();
$allTiersGrouped = $masterItemModel->getAllTiersGrouped();
$tier1List = $masterItemModel->getTier1();
$pendingCodeRequests = $masterItemModel->getCodeRequests('PENDING');
$allPendingCount = ($masterItemStats['pending_requests'] ?? 0) + ($masterItemStats['approved_requests'] ?? 0);
$initialPageSize = 50;
$allMasterItems = $masterItemModel->getAllMasterItems('', null, null, null, $initialPageSize);
$totalMasterCodesCount = $masterItemStats['total_master_codes'] ?? 7667;
$totalLibraryCount = $masterItemStats['total_library_codes'] ?? 10960;

// Determine active power station / location to load strictly its added departments for Warehouse
$activeLocationId = null;
if (!empty($adminDetails['location_id'])) {
  $activeLocationId = (int) $adminDetails['location_id'];
} elseif (!empty($_SESSION['location_id'])) {
  $activeLocationId = (int) $_SESSION['location_id'];
} elseif (!empty($currentUser['username']) && preg_match('/^(\d{3})/', $currentUser['username'], $m)) {
  $locByCode = $locModel->findByCode($m[1]);
  if ($locByCode) {
    $activeLocationId = (int) $locByCode['id'];
  }
}
if (!$activeLocationId) {
  $locDefault = $locModel->findByCode('832');
  if ($locDefault) {
    $activeLocationId = (int) $locDefault['id'];
  }
}
$stationWarehouseDepts = $activeLocationId ? $deptModel->getAll($activeLocationId) : [];
if (empty($stationWarehouseDepts)) {
  $stationWarehouseDepts = $deptModel->getAll();
}

// Fetch active power station's added codes catalog (items approved / provisioned to station_items)
$activeStationItems = $masterItemModel->getStationItems((int) ($activeLocationId ?: 1));
$activeStationItemsCompact = array_map(function ($it) {
  return [
    'id' => (int) $it['id'],
    'master_item_id' => (int) $it['master_item_id'],
    'code' => $it['formatted_code'],
    'raw_code' => $it['raw_code'],
    'name' => $it['item_name'],
    'uom' => $it['uom'] ?? 'NO',
    'bay' => $it['local_bay_location'] ?? 'Bay-A1',
    'avail' => (float) ($it['max_stock_level'] ?? 0),
    'reorder' => (float) ($it['min_reorder_level'] ?? 0)
  ];
}, $activeStationItems);

$logs = $isAdmin ? $logModel->getRecent(15) : [];
$permissionCategories = Permission::getAvailablePermissions();
$permissionsMatrix = $permModel->getMatrix();
$userPermissionsMatrix = $permModel->getUserMatrix();

$canUserAccessTab = function (string $permKey) use ($permModel, $currentUser, $userRoleId, $isSuperAdmin) {
  if ($isSuperAdmin)
    return true;
  $uid = (int) ($currentUser['id'] ?? 0);
  return $permModel->hasPermission($userRoleId, $permKey, $uid);
};

$hasAnyAccess = function (array $permKeys) use ($canUserAccessTab) {
  foreach ($permKeys as $k) {
    if ($canUserAccessTab($k))
      return true;
  }
  return false;
};

// =========================================================================
// REAL DATABASE AGGREGATION FOR MONTHLY CUMULATIVE TRANSACTION CHART
// =========================================================================
$chartYear = (int) date('Y'); // Current Year
$monthColors = [
  1 => '#a65b32', // Brown
  2 => '#42a5f5', // Light blue
  3 => '#f4e185', // Pale yellow
  4 => '#1b2a4a', // Midnight dark navy
  5 => '#c5cad1', // Silver gray
  6 => '#1e5a8a', // Deep navy / steel blue
  7 => '#c04a26', // Rust red
  8 => '#e8a238', // Golden yellow
  9 => '#4285f4', // Vibrant blue
  10 => '#8b5cf6', // Purple
  11 => '#06b6d4', // Cyan
  12 => '#10b981'  // Emerald
];

$monthNames = [
  1 => 'January',
  2 => 'February',
  3 => 'March',
  4 => 'April',
  5 => 'May',
  6 => 'June',
  7 => 'July',
  8 => 'August',
  9 => 'September',
  10 => 'October',
  11 => 'November',
  12 => 'December'
];

// Initialize real monthly dataset (Jan to current month of current year)
$currentMonth = (int) date('n'); // Current Month (1-12)
$monthlyTransactionData = [];
for ($m = 1; $m <= $currentMonth; $m++) {
  $monthlyTransactionData[$m] = [
    'month_index' => $m,
    'label' => $m . '-' . $chartYear,
    'month_name' => $monthNames[$m],
    'receipts' => 0.00,
    'issues' => 0.00,
    'color' => $monthColors[$m] ?? '#64748b'
  ];
}

$fifoBatches = [];
$cashBookEntries = [];
$payslipCancellations = [];
$authorizedTasks = [];
$stockTransfers = [];
$cpcProcurements = [];
$dieselPriceLogs = [];
$materialAggregates = [];
$costAdjustments = [];
$qtyCorrections = [];

try {
  $db = \App\Config\Database::getConnection();

  // Ensure supporting tables exist
  $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
  $pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
  $batchFk = $isMysql ? 'FOREIGN KEY (`batch_id`) REFERENCES `fifo_batches`(`id`)' : 'FOREIGN KEY (batch_id) REFERENCES fifo_batches(id)';

  $db->exec("
        CREATE TABLE IF NOT EXISTS inventory_transactions (
            id $pkType,
            batch_id INT NULL,
            transaction_type VARCHAR(20) NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            transaction_date DATE NOT NULL,
            reference_no VARCHAR(60) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            $batchFk
        );
        CREATE TABLE IF NOT EXISTS cash_book_entries (
            id $pkType,
            entry_date DATE NOT NULL,
            voucher_no VARCHAR(50) NOT NULL,
            entry_type VARCHAR(20) NOT NULL,
            account_title VARCHAR(150) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            status VARCHAR(30) DEFAULT 'Verified',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS payslip_cancellations (
            id $pkType,
            cancellation_ref VARCHAR(50) NOT NULL UNIQUE,
            payslip_no VARCHAR(50) NOT NULL,
            employee_name VARCHAR(150) NOT NULL,
            epf_no VARCHAR(50) DEFAULT NULL,
            pay_period VARCHAR(30) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            reason TEXT NOT NULL,
            requested_by INT NULL,
            status VARCHAR(30) DEFAULT 'Pending Review',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS authorized_tasks (
            id $pkType,
            task_code VARCHAR(50) NOT NULL UNIQUE,
            title VARCHAR(200) NOT NULL,
            department VARCHAR(100) NOT NULL,
            priority VARCHAR(20) DEFAULT 'Standard',
            status VARCHAR(30) DEFAULT 'Pending Approval',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS cost_adjustments (
            id $pkType,
            adjustment_ref VARCHAR(50) NOT NULL UNIQUE,
            batch_id INT NULL,
            sku VARCHAR(50) NOT NULL,
            item_name VARCHAR(150) NOT NULL,
            old_unit_cost DECIMAL(10,2) NOT NULL,
            new_unit_cost DECIMAL(10,2) NOT NULL,
            variance_per_unit DECIMAL(10,2) NOT NULL,
            remaining_qty INT NOT NULL,
            total_valuation_delta DECIMAL(12,2) NOT NULL,
            reason TEXT NOT NULL,
            authorized_by INT NULL,
            status VARCHAR(30) DEFAULT 'Approved & Applied',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS qty_corrections (
            id $pkType,
            correction_ref VARCHAR(50) NOT NULL UNIQUE,
            batch_id INT NULL,
            sku VARCHAR(50) NOT NULL,
            item_name VARCHAR(150) NOT NULL,
            allocated_qty_before INT NOT NULL,
            corrected_allocated_qty INT NOT NULL,
            qty_released_to_free_stock INT NOT NULL,
            work_order_ref VARCHAR(60) DEFAULT NULL,
            reason TEXT NOT NULL,
            authorized_by INT NULL,
            status VARCHAR(30) DEFAULT 'Corrected & Unlocked',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS stock_transfers (
            id $pkType,
            transfer_ref VARCHAR(50) NOT NULL UNIQUE,
            from_bay VARCHAR(100) NOT NULL,
            to_department VARCHAR(100) NOT NULL,
            sku VARCHAR(50) NOT NULL,
            item_name VARCHAR(150) NOT NULL,
            quantity INT NOT NULL,
            transfer_date DATE NOT NULL,
            status VARCHAR(30) DEFAULT 'Completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS cpc_procurements (
            id $pkType,
            order_no VARCHAR(50) NOT NULL UNIQUE,
            fuel_type VARCHAR(100) NOT NULL,
            tank_id VARCHAR(50) NOT NULL,
            quantity_litres DECIMAL(12,2) NOT NULL,
            unit_price_lkr DECIMAL(10,2) NOT NULL,
            total_amount_lkr DECIMAL(14,2) NOT NULL,
            consignment_date DATE NOT NULL,
            delivery_ref VARCHAR(60) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS diesel_price_logs (
            id $pkType,
            effective_month VARCHAR(20) NOT NULL,
            fuel_type VARCHAR(100) NOT NULL,
            avg_price_per_litre DECIMAL(10,2) NOT NULL,
            total_procured_litres DECIMAL(14,2) NOT NULL,
            total_procurement_cost DECIMAL(14,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS unposted_documents (
            id $pkType,
            doc_no VARCHAR(60) NOT NULL UNIQUE,
            doc_type VARCHAR(20) NOT NULL DEFAULT 'receipt',
            doc_profile VARCHAR(100) NOT NULL DEFAULT 'Goods Received Note',
            doc_profile_code VARCHAR(30) NOT NULL DEFAULT 'PF-GRN',
            status VARCHAR(30) NOT NULL DEFAULT 'Draft',
            receipt_from VARCHAR(150) NOT NULL DEFAULT 'Purchase Order',
            source_dept VARCHAR(50) NOT NULL DEFAULT '832.00',
            receipt_details TEXT NULL,
            delivery_date DATE NULL,
            acc_date DATE NULL,
            due_date DATE NULL,
            warehouse VARCHAR(150) NOT NULL DEFAULT 'Mechanical Stores',
            vat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            transport DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            nbt DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            other_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            invoice_number VARCHAR(100) NULL,
            order_reference VARCHAR(100) NULL,
            other_ref_1 VARCHAR(100) NULL,
            other_ref_2 VARCHAR(100) NULL,
            remarks TEXT NULL,
            invoice_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            is_single_material TINYINT NOT NULL DEFAULT 0,
            discount_for_all DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            is_percentage TINYINT NOT NULL DEFAULT 0,
            location_id INT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

  // Ensure initial unposted documents in database
  $chkDocs = $db->query("SELECT COUNT(*) FROM unposted_documents")->fetchColumn();
  if ((int)$chkDocs === 0) {
    $db->exec("
      INSERT INTO unposted_documents 
      (doc_no, doc_type, doc_profile, doc_profile_code, status, receipt_from, source_dept, receipt_details, delivery_date, acc_date, due_date, warehouse, vat, transport, nbt, other_cost, invoice_number, order_reference, other_ref_1, other_ref_2, remarks, invoice_total, is_single_material, discount_for_all, is_percentage, location_id, created_by)
      VALUES
      ('GRN-2026-0091', 'receipt', 'Goods Received Note', 'PF-GRN', 'Draft', 'Purchase Order', '832.00', 'Direct Shipment Voucher #91', '2026-09-23', '2026-09-23', '2026-10-23', 'Mechanical Stores', 18.00, 4500.00, 0.00, 0.00, 'INV-LHS-9041', 'PO-2026-0814', 'SHP-0091-M', 'WAYBILL-7712', 'Goods inspected upon delivery. Awaiting quality signoff for unit price adjustments.', 148250.00, 0, 2.50, 1, 1, 1),
      ('GRN-2026-0092', 'receipt', 'Goods Received Note', 'PF-GRN', 'Draft', 'Purchase Order', '832.00', 'Electrical supplies for turbine unit 2', '2026-09-23', '2026-09-23', '2026-10-15', 'Electrical Supplies Sub-Store', 18.00, 1200.00, 0.00, 350.00, 'CIE-2026-552', 'PO-2026-0799', 'ELEC-92-PO', 'QC-PASS-44', 'Urgent procurement for maintenance shutdown.', 82400.00, 0, 0.00, 0, 1, 1),
      ('DPR-2026-0044', 'receipt', 'Direct Material Purchase', 'PF-DIR', 'New', 'Cost Center', '832.00', 'Direct Receipt - Spares for generator cooling', '2026-09-24', '2026-09-24', '2026-10-24', 'Central Warehouse - Main Store', 18.00, 2000.00, 0.00, 0.00, 'INV-DIR-4402', 'PO-DIR-0044', 'SPARE-0044', 'BATCH-998', 'Direct store delivery for immediate maintenance replacement.', 64500.00, 0, 0.00, 0, 1, 1),
      ('SRN-2026-0012', 'receipt', 'Store Transfer Receipt', 'PF-STR', 'Draft', 'Section', '832.00', 'Store Return Voucher - Mechanical Overhaul surplus', '2026-09-25', '2026-09-25', '2026-10-25', 'Mechanical Stores', 0.00, 0.00, 0.00, 0.00, 'SRN-RET-12', 'WO-2026-551', 'RET-VOUCHER-12', 'INSP-OK-12', 'Unused surplus components returned to store after overhaul.', 28900.00, 0, 0.00, 0, 1, 1),
      ('GIN-2026-0041', 'issue', 'TV-ISSUE', 'TV-ISSUE', 'Draft', 'Mechanical Department', '832.00', 'Pump overhaul requisition #41', '2026-09-23', '2026-09-23', NULL, 'Central Warehouse - Main Store', 0.00, 0.00, 0.00, 0.00, 'REQ-MECH-41', 'WO-2026-1102', 'OVERHAUL-41', 'APP-HEAD-01', 'Issued for scheduled plant overhaul.', 94500.00, 0, 0.00, 0, 1, 1),
      ('GIN-2026-0042', 'issue', 'MAT-REQ', 'MAT-REQ', 'Draft', 'Electrical Maintenance Division', '832.00', 'Panel wiring and contactors', '2026-09-23', '2026-09-23', NULL, 'Electrical Supplies Sub-Store', 0.00, 0.00, 0.00, 0.00, 'REQ-ELEC-42', 'WO-2026-1105', 'ELEC-PANEL-42', 'AUTH-SEC-2', 'Emergency breakdown spares.', 38200.00, 0, 0.00, 0, 1, 1),
      ('DMI-2026-0018', 'issue', 'ISSUE', 'ISSUE', 'New', 'Civil Maintenance Division', '832.00', 'Direct Issue - Structural Bolts & Anchors', '2026-09-24', '2026-09-24', NULL, 'Mechanical Stores', 0.00, 0.00, 0.00, 0.00, 'REQ-CIVIL-18', 'WO-2026-0988', 'CIVIL-18-STR', 'SEC-AUTH-3', 'Urgent structural repair anchors issue.', 19400.00, 0, 0.00, 0, 1, 1),
      ('STI-2026-0009', 'issue', 'TV-ISSUE', 'TV-ISSUE', 'Draft', 'Sub Station Warehouse', '832.00', 'Inter-departmental transformer oil transfer', '2026-09-25', '2026-09-25', NULL, 'Central Warehouse - Main Store', 0.00, 0.00, 0.00, 0.00, 'STI-TRANS-09', 'TO-2026-441', 'TRANS-OIL-09', 'GATEPASS-881', 'Approved transfer for sub station maintenance.', 52000.00, 0, 0.00, 0, 1, 1)
    ");
  }

  $stmtUnpostedRx = $db->query("SELECT * FROM unposted_documents WHERE doc_type = 'receipt' ORDER BY id ASC");
  $unpostedReceiptDocs = $stmtUnpostedRx->fetchAll() ?: [];

  $stmtUnpostedIssue = $db->query("SELECT * FROM unposted_documents WHERE doc_type = 'issue' ORDER BY id ASC");
  $unpostedIssueDocs = $stmtUnpostedIssue->fetchAll() ?: [];

  $dbUnpostedMap = [];
  foreach (array_merge($unpostedReceiptDocs, $unpostedIssueDocs) as $dItem) {
    $dbUnpostedMap[$dItem['doc_no']] = [
      'profile' => $dItem['doc_profile'] ?? 'Goods Received Note',
      'profileVal' => $dItem['doc_profile_code'] ?? 'PF-GRN',
      'status' => $dItem['status'] ?? 'Draft',
      'receiptFrom' => $dItem['receipt_from'] ?? 'Purchase Order',
      'sourceDept' => $dItem['source_dept'] ?? '832.00',
      'receiptDetails' => $dItem['receipt_details'] ?? '',
      'warehouse' => $dItem['warehouse'] ?? 'Mechanical Stores',
      'nbt' => number_format((float)($dItem['nbt'] ?? 0), 2),
      'nbtVal' => (float)($dItem['nbt'] ?? 0),
      'vat' => number_format((float)($dItem['vat'] ?? 0), 2) . '%',
      'vatVal' => (float)($dItem['vat'] ?? 0),
      'transport' => number_format((float)($dItem['transport'] ?? 0), 2),
      'transportVal' => (float)($dItem['transport'] ?? 0),
      'other' => number_format((float)($dItem['other_cost'] ?? 0), 2),
      'otherVal' => (float)($dItem['other_cost'] ?? 0),
      'invoiceTotal' => number_format((float)($dItem['invoice_total'] ?? 0), 2),
      'discountForAll' => number_format((float)($dItem['discount_for_all'] ?? 0), 2) . (!empty($dItem['is_percentage']) ? '%' : ''),
      'discountVal' => (float)($dItem['discount_for_all'] ?? 0),
      'isSingleMaterial' => !empty($dItem['is_single_material']),
      'isPercentage' => !empty($dItem['is_percentage']),
      'deliveryDate' => !empty($dItem['delivery_date']) ? date('d-M-Y', strtotime($dItem['delivery_date'])) : '',
      'deliveryDateRaw' => $dItem['delivery_date'] ?? '',
      'accDate' => !empty($dItem['acc_date']) ? date('d-M-Y', strtotime($dItem['acc_date'])) : '',
      'accDateRaw' => $dItem['acc_date'] ?? '',
      'dueDate' => !empty($dItem['due_date']) ? date('d-M-Y', strtotime($dItem['due_date'])) : '',
      'dueDateRaw' => $dItem['due_date'] ?? '',
      'invoiceNum' => $dItem['invoice_number'] ?? '',
      'orderRef' => $dItem['order_reference'] ?? '',
      'otherRef1' => $dItem['other_ref_1'] ?? '',
      'otherRef2' => $dItem['other_ref_2'] ?? '',
      'remarks' => $dItem['remarks'] ?? ''
    ];
  }

  // Fetch real FIFO Batches from database (no sample data)
  $stmtBatches = $db->query("SELECT * FROM fifo_batches ORDER BY received_date ASC");
  $fifoBatches = $stmtBatches->fetchAll() ?: [];

  // Aggregate material-wise quantity on hand
  foreach ($fifoBatches as $b) {
    $sku = $b['sku'];
    if (!isset($materialAggregates[$sku])) {
      $materialAggregates[$sku] = [
        'sku' => $sku,
        'item_name' => $b['item_name'],
        'category' => $b['category'] ?? 'Standard',
        'total_remaining_qty' => 0,
        'total_value' => 0.0,
        'batch_count' => 0,
        'bins' => []
      ];
    }
    $materialAggregates[$sku]['total_remaining_qty'] += (int) $b['remaining_quantity'];
    $materialAggregates[$sku]['total_value'] += (int) $b['remaining_quantity'] * (float) $b['unit_cost'];
    $materialAggregates[$sku]['batch_count']++;
    if (!empty($b['location_bin']) && !in_array($b['location_bin'], $materialAggregates[$sku]['bins'])) {
      $materialAggregates[$sku]['bins'][] = $b['location_bin'];
    }
  }

  // Aggregate receipts from fifo_batches
  if ($isMysql) {
    $stmtRx = $db->prepare("
            SELECT 
                MONTH(received_date) as rx_month,
                SUM(initial_quantity * unit_cost) as total_rx_val
            FROM fifo_batches
            WHERE YEAR(received_date) = :yr
            GROUP BY MONTH(received_date)
        ");
    $stmtRx->execute([':yr' => (int) $chartYear]);
  } else {
    $stmtRx = $db->prepare("
            SELECT 
                CAST(strftime('%m', received_date) AS INTEGER) as rx_month,
                SUM(initial_quantity * unit_cost) as total_rx_val
            FROM fifo_batches
            WHERE strftime('%Y', received_date) = :yr
            GROUP BY rx_month
        ");
    $stmtRx->execute([':yr' => (string) $chartYear]);
  }
  $rxRows = $stmtRx->fetchAll() ?: [];
  foreach ($rxRows as $rx) {
    $mIdx = (int) $rx['rx_month'];
    if (isset($monthlyTransactionData[$mIdx])) {
      $monthlyTransactionData[$mIdx]['receipts'] += (float) $rx['total_rx_val'];
    }
  }

  // Aggregate transactions from inventory_transactions
  if ($isMysql) {
    $stmtTx = $db->prepare("
            SELECT 
                MONTH(transaction_date) as tx_month,
                transaction_type,
                SUM(total_value) as total_val
            FROM inventory_transactions
            WHERE YEAR(transaction_date) = :yr
            GROUP BY MONTH(transaction_date), transaction_type
        ");
    $stmtTx->execute([':yr' => (int) $chartYear]);
  } else {
    $stmtTx = $db->prepare("
            SELECT 
                CAST(strftime('%m', transaction_date) AS INTEGER) as tx_month,
                transaction_type,
                SUM(total_value) as total_val
            FROM inventory_transactions
            WHERE strftime('%Y', transaction_date) = :yr
            GROUP BY tx_month, transaction_type
        ");
    $stmtTx->execute([':yr' => (string) $chartYear]);
  }
  $txRows = $stmtTx->fetchAll() ?: [];
  foreach ($txRows as $tx) {
    $mIdx = (int) $tx['tx_month'];
    if (isset($monthlyTransactionData[$mIdx])) {
      if (strtolower($tx['transaction_type']) === 'receipt') {
        $monthlyTransactionData[$mIdx]['receipts'] += (float) $tx['total_val'];
      } else if (strtolower($tx['transaction_type']) === 'issue') {
        $monthlyTransactionData[$mIdx]['issues'] += (float) $tx['total_val'];
      }
    }
  }

  // Fetch real Cash Book entries from database
  $stmtCash = $db->query("SELECT * FROM cash_book_entries ORDER BY entry_date DESC LIMIT 50");
  $cashBookEntries = $stmtCash->fetchAll() ?: [];

  // Fetch real Payslip Cancellations from database
  $stmtPayslips = $db->query("SELECT * FROM payslip_cancellations ORDER BY created_at DESC LIMIT 50");
  $payslipCancellations = $stmtPayslips->fetchAll() ?: [];

  // Fetch real Authorized Tasks from database
  $stmtTasks = $db->query("SELECT * FROM authorized_tasks ORDER BY id DESC LIMIT 50");
  $authorizedTasks = $stmtTasks->fetchAll() ?: [];

  // Fetch Cost Adjustments
  $stmtCostAdj = $db->query("SELECT * FROM cost_adjustments ORDER BY created_at DESC LIMIT 50");
  $costAdjustments = $stmtCostAdj->fetchAll() ?: [];

  // Fetch Qty Corrections
  $stmtQtyCorr = $db->query("SELECT * FROM qty_corrections ORDER BY created_at DESC LIMIT 50");
  $qtyCorrections = $stmtQtyCorr->fetchAll() ?: [];

  // Fetch Stock Transfers
  $stmtTransfers = $db->query("SELECT * FROM stock_transfers ORDER BY transfer_date DESC LIMIT 50");
  $stockTransfers = $stmtTransfers->fetchAll() ?: [];

  // Fetch CPC Procurements
  $stmtCPC = $db->query("SELECT * FROM cpc_procurements ORDER BY consignment_date DESC LIMIT 50");
  $cpcProcurements = $stmtCPC->fetchAll() ?: [];

  // Fetch Diesel Price Logs
  $stmtDiesel = $db->query("SELECT * FROM diesel_price_logs ORDER BY id DESC LIMIT 50");
  $dieselPriceLogs = $stmtDiesel->fetchAll() ?: [];

} catch (\Exception $e) {
}

// Calculate live summary totals
$totalReceiptsYTD = array_sum(array_column($monthlyTransactionData, 'receipts'));
$totalIssuesYTD = array_sum(array_column($monthlyTransactionData, 'issues'));
$netInventoryValue = $totalReceiptsYTD - $totalIssuesYTD;

// Compute dynamic chart max scaling
$maxChartVal = 20000000.00;
foreach ($monthlyTransactionData as $m) {
  if ($m['receipts'] > $maxChartVal)
    $maxChartVal = $m['receipts'];
  if ($m['issues'] > $maxChartVal)
    $maxChartVal = $m['issues'];
}

$pageTitle = $showStaffView ? 'Operational Portal' : ($isSuperAdmin ? 'Super Admin Console' : ($isCodeAdmin ? 'Code Admin Console' : 'Admin Console'));
$base = defined('BASE_PATH') ? BASE_PATH : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(Session::csrfToken()) ?>">
  <meta name="base-url" content="<?= htmlspecialchars($base) ?>">
  <title><?= $pageTitle ?> | <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= $base ?>/assets/css/design-system.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/dashboard.css?v=<?= time() ?>">
</head>

<body>

  <?php if ($showStaffView): ?>
    <!-- =======================================================================
     NON-ADMIN / ADDED USERS PORTAL (IMAGE 1 & IMAGE 2 COMPLIANT)
     ======================================================================= -->
    <div class="user-portal-shell">

      <!-- Top Corporate Status Bar -->
      <header class="user-portal-topbar">
        <div class="user-brand-group">
          <img src="<?= $base ?>/assets/images/EGL-NEW.png" alt="EGL Logo" class="user-brand-logo">
          <div class="user-brand-text">
            <h1>Electricity Generation Lanka (Pvt) Ltd</h1>
            <span>FIFO Inventory &amp; Operational Ledger Portal &bull;
              <?= htmlspecialchars($adminDetails['department'] ?? 'General') ?></span>
          </div>
        </div>

        <div class="user-topbar-controls">
          <!-- Live Sri Lankan Date and Time -->
          <div class="sri-lanka-clock-pill" id="slClockContainer">
            <span id="slDateDisplay"><?= date('D, d M Y') ?></span>
            <span class="clock-divider" style="color: #cbd5e1;">|</span>
            <span id="slTimeDisplay"
              style="font-weight: 800; color: #650b2b; font-family: monospace; font-size: 0.76rem;"><?= date('h:i:s A') ?></span>
          </div>

          <div class="user-badge-pill">
            <div class="avatar"
              style="background-color: <?= htmlspecialchars($adminDetails['avatar_color'] ?? '#650b2b') ?>; width: 22px; height: 22px; font-size: 0.65rem; flex-shrink: 0;">
              <?= strtoupper(substr($adminDetails['full_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
              <strong
                style="color: #0f172a; font-size: 0.76rem;"><?= htmlspecialchars($adminDetails['full_name'] ?? 'Staff User') ?></strong>
              <span
                style="display: block; font-size: 0.64rem; color: #64748b;"><?= htmlspecialchars($adminDetails['role_display_name'] ?? 'Staff Member') ?></span>
            </div>
          </div>

          <?php if ($isAdmin): ?>
            <a href="<?= $base ?>/admin/index.php" class="btn btn-secondary btn-sm" title="Back to Admin Console">
              Admin Console
            </a>
          <?php endif; ?>



          <a href="<?= $base ?>/logout.php" class="btn btn-danger btn-sm" title="Sign out of system">
            Sign Out
          </a>
        </div>
      </header>

      <!-- =======================================================================
       HORIZONTAL NAVIGATION BAR (EXACT AS IMAGE 1)
       [Home] [Inventory - FIFO ▾] [Cash Book ▾] [Reports ▾] [Authorized Tasks ▾]
       ======================================================================= -->
      <?php
      $receiptsKeys = ['tab-user-receipts-entry', 'tab-user-receipts-validation', 'tab-user-receipts-approval', 'tab-user-receipts-edit', 'tab-user-receipts-posted-cancel', 'tab-user-receipts-unposted-cancel'];
      $issueKeys = ['tab-user-issue-entry', 'tab-user-issue-edit', 'tab-user-issue-validation', 'tab-user-issue-approval', 'tab-user-issue-posted-cancel', 'tab-user-issue-unposted-cancel'];
      $transactionKeys = array_merge($receiptsKeys, $issueKeys, ['tab-user-posting']);
      $pvReportKeys = ['tab-user-pv-report-avg-consumption', 'tab-user-pv-report-final', 'tab-user-pv-report-other', 'tab-user-pv-report-sheet-summary', 'tab-user-pv-report-av7-summary'];
      $pvKeys = array_merge(['tab-user-pv-freeze', 'tab-user-pv-generate-sheet', 'tab-user-pv-entry', 'tab-user-pv-edit', 'tab-user-pv-validation', 'tab-user-pv-approval', 'tab-user-pv-posting', 'tab-user-pv-gl-posting', 'tab-user-pv-unfreeze'], $pvReportKeys);
      $fifoKeys = array_merge($transactionKeys, $pvKeys);
      $cashbookKeys = ['tab-user-payslip-cancellation'];
      $qohKeys = ['tab-user-report-qoh-batch', 'tab-user-report-qoh-material'];
      $reportsKeys = array_merge($qohKeys, ['tab-user-report-monthly-transfers', 'tab-user-report-avg-diesel', 'tab-user-report-cpc-procurement', 'tab-user-report-inventory-inquiry', 'tab-user-report-job-issue', 'tab-user-report-monthly-tx', 'tab-user-report-grn-issue-summary', 'tab-user-report-material-flow']);
      $tasksKeys = ['tab-user-task-cost-adjustment', 'tab-user-task-qty-correction', 'tab-user-issue-codes'];
      $codeMgmtKeys = ['tab-user-code-roles', 'tab-user-code-item-search', 'tab-user-code-locations', 'tab-user-code-departments'];
      ?>
      <nav class="user-navbar" id="userNavbar" aria-label="Main Staff Navigation">
        <ul class="user-nav-list">

          <!-- 1. Home (Active by default) -->
          <?php if ($canUserAccessTab('tab-user-home')): ?>
            <li class="user-nav-item active" data-tab-parent="tab-user-home">
              <button type="button" class="user-nav-link active user-nav-tab-trigger" data-user-tab="tab-user-home">
                <span>Home</span>
              </button>
            </li>
          <?php endif; ?>

          <!-- 2. Inventory - FIFO (Multi-Level Sub-menus Matching Screenshot) -->
          <?php if ($hasAnyAccess($fifoKeys)): ?>
            <li class="user-nav-item" data-tab-parent="tab-user-fifo">
              <button type="button" class="user-nav-link user-dropdown-toggle">
                <span>Inventory - FIFO</span>
                <span class="user-nav-arrow"></span>
              </button>
              <ul class="user-dropdown-menu">
                <!-- Transaction (with sub-menu) -->
                <?php if ($hasAnyAccess($transactionKeys)): ?>
                  <li class="user-dropdown-submenu">
                    <button type="button" class="user-submenu-trigger">
                      <span>Transaction</span>
                      <span class="user-submenu-arrow"></span>
                    </button>
                    <ul class="user-submenu">
                      <!-- Receipts (with 3rd level sub-menu matching user screenshot) -->
                      <?php if ($hasAnyAccess($receiptsKeys)): ?>
                        <li class="user-dropdown-submenu">
                          <button type="button" class="user-submenu-trigger">
                            <span>Reciepts</span>
                            <span class="user-submenu-arrow"></span>
                          </button>
                          <ul class="user-submenu">
                            <?php if ($canUserAccessTab('tab-user-receipts-entry')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-receipts-entry"><span>Entry</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-receipts-validation')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-receipts-validation"><span>Validation</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-receipts-approval')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-receipts-approval"><span>Approval</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-receipts-edit')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-receipts-edit"><span>Unposted Document Edit</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-receipts-posted-cancel')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-receipts-posted-cancel"><span>Posted Cancellation</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-receipts-unposted-cancel')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-receipts-unposted-cancel"><span>Unposted Cancellation</span></button>
                              </li>
                            <?php endif; ?>
                          </ul>
                        </li>
                      <?php endif; ?>
                      <!-- Issue (with 3rd level sub-menu matching user screenshot) -->
                      <?php if ($hasAnyAccess($issueKeys)): ?>
                        <li class="user-dropdown-submenu">
                          <button type="button" class="user-submenu-trigger">
                            <span>Issue</span>
                            <span class="user-submenu-arrow"></span>
                          </button>
                          <ul class="user-submenu">
                            <?php if ($canUserAccessTab('tab-user-issue-entry')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-issue-entry"><span>Entry</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-issue-edit')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-issue-edit"><span>Edit</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-issue-validation')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-issue-validation"><span>Validation</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-issue-approval')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-issue-approval"><span>Approval</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-issue-posted-cancel')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-issue-posted-cancel"><span>Posted Cancellation</span></button></li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-issue-unposted-cancel')): ?>
                              <li><button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-issue-unposted-cancel"><span>UnPosted Cancellation</span></button></li>
                            <?php endif; ?>
                          </ul>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-posting')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-posting">
                            <span>Posting</span>
                          </button>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </li>
                <?php endif; ?>

                <!-- Physical Verification (with multi-level sub-menus matching user screenshot) -->
                <?php if ($hasAnyAccess($pvKeys)): ?>
                  <li class="user-dropdown-submenu">
                    <button type="button" class="user-submenu-trigger">
                      <span>Physical Verification</span>
                      <span class="user-submenu-arrow"></span>
                    </button>
                    <ul class="user-submenu">
                      <?php if ($canUserAccessTab('tab-user-pv-freeze')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-freeze"
                            onclick="switchUserTab('tab-user-pv-freeze')" style="cursor: pointer;">
                            <span>Freeze Batches</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-generate-sheet')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-generate-sheet"
                            onclick="switchUserTab('tab-user-pv-generate-sheet')" style="cursor: pointer;">
                            <span>Generate Sheet</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-entry')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-entry"
                            onclick="switchUserTab('tab-user-pv-entry')" style="cursor: pointer;">
                            <span>Verification Entry</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-edit')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-edit"
                            onclick="switchUserTab('tab-user-pv-edit')" style="cursor: pointer;">
                            <span>Verification Edit</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-validation')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-validation"
                            onclick="switchUserTab('tab-user-pv-validation')" style="cursor: pointer;">
                            <span>Verification Validation</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-approval')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-approval"
                            onclick="switchUserTab('tab-user-pv-approval')" style="cursor: pointer;">
                            <span>Verification Approval</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <!-- PHV Reports (3rd level sub-menu matching screenshot) -->
                      <?php if ($hasAnyAccess($pvReportKeys)): ?>
                        <li class="user-dropdown-submenu">
                          <button type="button" class="user-submenu-trigger" style="cursor: pointer;">
                            <span>PHV Reports</span>
                            <span class="user-submenu-arrow"></span>
                          </button>
                          <ul class="user-submenu">
                            <?php if ($canUserAccessTab('tab-user-pv-report-avg-consumption')): ?>
                              <li>
                                <button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-pv-report-avg-consumption"
                                  onclick="switchUserTab('tab-user-pv-report-avg-consumption')" style="cursor: pointer;">
                                  <span>Avg Consumption</span>
                                </button>
                              </li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-pv-report-final')): ?>
                              <li>
                                <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-report-final"
                                  onclick="switchUserTab('tab-user-pv-report-final')" style="cursor: pointer;">
                                  <span>Final Report</span>
                                </button>
                              </li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-pv-report-other')): ?>
                              <li>
                                <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-report-other"
                                  onclick="switchUserTab('tab-user-pv-report-other')" style="cursor: pointer;">
                                  <span>Other Reports</span>
                                </button>
                              </li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-pv-report-sheet-summary')): ?>
                              <li>
                                <button type="button" class="user-nav-tab-trigger"
                                  data-user-tab="tab-user-pv-report-sheet-summary"
                                  onclick="switchUserTab('tab-user-pv-report-sheet-summary')" style="cursor: pointer;">
                                  <span>PHV Sheet Summary</span>
                                </button>
                              </li>
                            <?php endif; ?>
                            <?php if ($canUserAccessTab('tab-user-pv-report-av7-summary')): ?>
                              <li>
                                <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-report-av7-summary"
                                  onclick="switchUserTab('tab-user-pv-report-av7-summary')" style="cursor: pointer;">
                                  <span>AV 7 Summary</span>
                                </button>
                              </li>
                            <?php endif; ?>
                          </ul>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-posting')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-posting"
                            onclick="switchUserTab('tab-user-pv-posting')" style="cursor: pointer;">
                            <span>Verification Posting</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-gl-posting')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-gl-posting"
                            onclick="switchUserTab('tab-user-pv-gl-posting')" style="cursor: pointer;">
                            <span>Verification GL Posting</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-pv-unfreeze')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-pv-unfreeze"
                            onclick="switchUserTab('tab-user-pv-unfreeze')" style="cursor: pointer;">
                            <span>Unfreeze Batches</span>
                          </button>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>

          <!-- 3. Cash Book (With dropdown arrow matching user screenshot) -->
          <?php if ($hasAnyAccess($cashbookKeys)): ?>
            <li class="user-nav-item" data-tab-parent="tab-user-cashbook">
              <button type="button" class="user-nav-link user-dropdown-toggle">
                <span>Cash Book</span>
                <span class="user-nav-arrow"></span>
              </button>
              <ul class="user-dropdown-menu">
                <?php if ($canUserAccessTab('tab-user-payslip-cancellation')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-payslip-cancellation">
                      <span>Payslip Cancelation</span>
                    </button>
                  </li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>

          <!-- 4. Reports (Multi-Level Matching User Screenshot) -->
          <?php if ($hasAnyAccess($reportsKeys)): ?>
            <li class="user-nav-item" data-tab-parent="tab-user-reports">
              <button type="button" class="user-nav-link user-dropdown-toggle">
                <span>Reports</span>
                <span class="user-nav-arrow"></span>
              </button>
              <ul class="user-dropdown-menu">
                <!-- Quantity On Hand (Flyout Submenu) -->
                <?php if ($hasAnyAccess($qohKeys)): ?>
                  <li class="user-dropdown-submenu">
                    <button type="button" class="user-submenu-trigger">
                      <span>Quantity On Hand</span>
                      <span class="user-submenu-arrow"></span>
                    </button>
                    <ul class="user-submenu">
                      <?php if ($canUserAccessTab('tab-user-report-qoh-batch')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-qoh-batch">
                            <span>Batch wise report</span>
                          </button>
                        </li>
                      <?php endif; ?>
                      <?php if ($canUserAccessTab('tab-user-report-qoh-material')): ?>
                        <li>
                          <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-qoh-material">
                            <span>Material wise report</span>
                          </button>
                        </li>
                      <?php endif; ?>
                    </ul>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-monthly-transfers')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-monthly-transfers">
                      <span>Monthly Transfers</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-avg-diesel')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-avg-diesel">
                      <span>Average Diesel Price</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-cpc-procurement')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-cpc-procurement">
                      <span>CPC Procurements report</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-inventory-inquiry')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-inventory-inquiry">
                      <span>Inventory Inquiry Report</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-job-issue')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-job-issue">
                      <span>Job wise material Issue Report</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-monthly-tx')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-monthly-tx">
                      <span>Monthly Transaction</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-grn-issue-summary')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-grn-issue-summary">
                      <span>GRN - Issue Summary</span>
                    </button>
                  </li>
                <?php endif; ?>

                <?php if ($canUserAccessTab('tab-user-report-material-flow')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-report-material-flow">
                      <span>Material Flow Report</span>
                    </button>
                  </li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>

          <!-- 5. Authorized Tasks (Matching User Screenshot) -->
          <?php if ($hasAnyAccess($tasksKeys)): ?>
            <li class="user-nav-item" data-tab-parent="tab-user-tasks">
              <button type="button" class="user-nav-link user-dropdown-toggle">
                <span>Authorized Tasks</span>
                <span class="user-nav-arrow"></span>
              </button>
              <ul class="user-dropdown-menu">
                <?php if ($canUserAccessTab('tab-user-task-cost-adjustment')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-task-cost-adjustment">
                      <span>Cost Adjustments</span>
                    </button>
                  </li>
                <?php endif; ?>
                <?php if ($canUserAccessTab('tab-user-task-qty-correction')): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-task-qty-correction">
                      <span>Allocated Qty Correction</span>
                    </button>
                  </li>
                <?php endif; ?>
                <?php if ($canUserAccessTab('tab-user-issue-codes') || $isCodeAdmin): ?>
                  <li>
                    <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-issue-codes">
                      <span>Issue Categories &amp; Destination Codes</span>
                    </button>
                  </li>
                <?php endif; ?>
              </ul>
            </li>
          <?php endif; ?>

          <!-- 6. Code Menu with Sub-tabs: Item Search & Add Code -->
          <?php if ($canUserAccessTab('tab-user-code-roles') || $canUserAccessTab('tab-user-code-item-search') || $hasAnyAccess($codeMgmtKeys)): ?>
            <li class="user-nav-item" data-tab-parent="tab-user-code">
              <button type="button" class="user-nav-link user-dropdown-toggle">
                <span>Code</span>
                <span class="user-nav-arrow"></span>
              </button>
              <ul class="user-dropdown-menu">
                <li>
                  <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-code-item-search">
                    <span>Item Search</span>
                  </button>
                </li>
                <li>
                  <button type="button" class="user-nav-tab-trigger" data-user-tab="tab-user-code-roles">
                    <span>Add Code</span>
                  </button>
                </li>
              </ul>
            </li>
          <?php endif; ?>

        </ul>
      </nav>

      <!-- =======================================================================
       USER PORTAL MAIN CONTENT PANES
       ======================================================================= -->
      <main class="user-portal-container">

        <!-- =====================================================================
         PANE 1: HOME TAB (CUMULATIVE TRANSACTION VALUES CHART - IMAGE 2)
         ===================================================================== -->
        <section id="tab-user-home" class="user-tab-pane active">

          <!-- Main Chart Card Matching Image 2 Exact Specifications -->
          <div class="chart-card">

            <div class="chart-card-header">
              <h2 class="chart-italic-title">
                Below Chart Displays the Cumulative Transaction Values for Each Month on the Current Year
              </h2>
              <div class="chart-sub-meta">
                Financial Year <?= $chartYear ?> &bull; Electricity Generation Lanka (Pvt) Ltd Operational Ledger
              </div>
            </div>

            <div class="chart-layout-grid">

              <!-- Interactive SVG Bar Chart -->
              <div class="chart-svg-container" id="svgChartWrapper">

                <!-- Tooltip placeholder -->
                <div id="chartTooltip" class="chart-tooltip"></div>

                <svg viewBox="0 0 740 380" width="100%" height="380"
                  style="overflow: visible; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">

                  <!-- Background / Grid Lines -->
                  <g class="chart-grid" stroke="#d1d5db" stroke-dasharray="2,3" stroke-width="0.8">
                    <!-- $20M Line -->
                    <line x1="120" y1="40" x2="680" y2="40" />
                    <!-- $15M Line -->
                    <line x1="120" y1="105" x2="680" y2="105" />
                    <!-- $10M Line -->
                    <line x1="120" y1="170" x2="680" y2="170" />
                    <!-- $5M Line -->
                    <line x1="120" y1="235" x2="680" y2="235" />
                    <!-- $0.00 Base Line (Solid) -->
                    <line x1="120" y1="300" x2="680" y2="300" stroke="#475569" stroke-dasharray="0" stroke-width="1.5" />
                  </g>

                  <!-- Left Y-Axis Vertical Line -->
                  <line x1="120" y1="35" x2="120" y2="305" stroke="#475569" stroke-width="1.5" />

                  <!-- Y-Axis Ticks & Labels -->
                  <g font-size="12" fill="#1e293b" text-anchor="end" font-weight="500">
                    <text x="112" y="44">$20,000,000.00</text>
                    <line x1="115" y1="40" x2="120" y2="40" stroke="#475569" stroke-width="1.5" />

                    <text x="112" y="109">$15,000,000.00</text>
                    <line x1="115" y1="105" x2="120" y2="105" stroke="#475569" stroke-width="1.5" />

                    <text x="112" y="174">$10,000,000.00</text>
                    <line x1="115" y1="170" x2="120" y2="170" stroke="#475569" stroke-width="1.5" />

                    <text x="112" y="239">$5,000,000.00</text>
                    <line x1="115" y1="235" x2="120" y2="235" stroke="#475569" stroke-width="1.5" />

                    <text x="112" y="304">$0.00</text>
                    <line x1="115" y1="300" x2="120" y2="300" stroke="#475569" stroke-width="1.5" />
                  </g>

                  <!-- Y-Axis Title (Rotated) -->
                  <text transform="rotate(-90)" x="-170" y="20" font-size="12" font-weight="600" fill="#334155"
                    text-anchor="middle">
                    Transaction Value in LKR
                  </text>

                  <!-- X-Axis Groups (Receipts & Issues) -->
                  <!-- ==================== 1. RECEIPTS BARS ==================== -->
                  <g id="receiptsBarsGroup">
                    <?php
                    $barWidth = 14;
                    $barGap = 3;
                    $step = $barWidth + $barGap;
                    $numBars = count($monthlyTransactionData);
                    $groupWidth = ($numBars * $step) - $barGap;
                    $receiptsBaseX = 282 - ($groupWidth / 2);
                    $maxVal = ($maxChartVal > 0) ? $maxChartVal : 20000000.00;
                    $chartHeightPixels = 260; // 300 - 40
                  
                    $posIdx = 0;
                    foreach ($monthlyTransactionData as $m):
                      $val = $m['receipts'];
                      $barHeight = ($val / $maxVal) * $chartHeightPixels;
                      if ($barHeight < 2 && $val > 0)
                        $barHeight = 2;
                      $xPos = $receiptsBaseX + ($posIdx * $step);
                      $yPos = 300 - $barHeight;
                      $posIdx++;
                      ?>
                      <rect x="<?= round($xPos, 1) ?>" y="<?= round($yPos, 1) ?>" width="<?= $barWidth ?>"
                        height="<?= round($barHeight, 1) ?>" fill="<?= $m['color'] ?>" class="chart-bar-rect"
                        data-category="Receipts" data-month="<?= htmlspecialchars($m['label']) ?>"
                        data-month-name="<?= htmlspecialchars($m['month_name']) ?>" data-val="<?= number_format($val, 2) ?>"
                        style="cursor: pointer; transition: opacity 0.15s ease;">
                        <title>Receipts (<?= $m['label'] ?>): Rs. <?= number_format($val, 2) ?></title>
                      </rect>
                    <?php endforeach; ?>
                  </g>

                  <!-- ==================== 2. ISSUES BARS ==================== -->
                  <g id="issuesBarsGroup">
                    <?php
                    $issuesBaseX = 502 - ($groupWidth / 2);

                    $posIdx = 0;
                    foreach ($monthlyTransactionData as $m):
                      $val = $m['issues'];
                      $barHeight = ($val / $maxVal) * $chartHeightPixels;
                      if ($barHeight < 2 && $val > 0)
                        $barHeight = 2;
                      $xPos = $issuesBaseX + ($posIdx * $step);
                      $yPos = 300 - $barHeight;
                      $posIdx++;
                      ?>
                      <rect x="<?= round($xPos, 1) ?>" y="<?= round($yPos, 1) ?>" width="<?= $barWidth ?>"
                        height="<?= round($barHeight, 1) ?>" fill="<?= $m['color'] ?>" class="chart-bar-rect"
                        data-category="Issues" data-month="<?= htmlspecialchars($m['label']) ?>"
                        data-month-name="<?= htmlspecialchars($m['month_name']) ?>" data-val="<?= number_format($val, 2) ?>"
                        style="cursor: pointer; transition: opacity 0.15s ease;">
                        <title>Issues (<?= $m['label'] ?>): Rs. <?= number_format($val, 2) ?></title>
                      </rect>
                    <?php endforeach; ?>
                  </g>

                  <!-- X-Axis Major Category Labels -->
                  <text x="282" y="324" font-size="14" font-weight="600" fill="#1e293b" text-anchor="middle">
                    Receipts
                  </text>
                  <text x="502" y="324" font-size="14" font-weight="600" fill="#1e293b" text-anchor="middle">
                    Issues
                  </text>

                  <!-- X-Axis Bottom Axis Title -->
                  <text x="392" y="354" font-size="13" font-weight="600" fill="#334155" text-anchor="middle">
                    Transaction Type
                  </text>

                </svg>
              </div>

              <!-- Color-Coded Right Legend (Exact order as Image 2: 9-2026 to 1-2026) -->
              <div class="chart-legend-box">
                <?php
                // Reverse to show 9-2026 at the top down to 1-2026 at the bottom
                $reversedMonths = array_reverse($monthlyTransactionData);
                foreach ($reversedMonths as $rm):
                  ?>
                  <div class="legend-item" title="<?= htmlspecialchars($rm['month_name']) ?> <?= $chartYear ?>">
                    <span class="legend-color-box" style="background-color: <?= $rm['color'] ?>;"></span>
                    <span><?= htmlspecialchars($rm['label']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>

            </div>



          </div>

        </section>



        <!-- =====================================================================
         PANE 2.1.1: TRANSACTION -> RECEIPTS -> ENTRY (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-receipts-entry" class="user-tab-pane">
          <div class="card"
            style="padding: 24px 32px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">
            <form id="receiptsEntryForm"
              onsubmit="event.preventDefault(); alert('Receipt voucher draft saved successfully.');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- 1. Header Grid Area (Image 1) -->
              <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">

                <!-- Row 1: Document Profile + Preview + Status -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label
                    style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0; line-height: 1.2;">
                    Document<br>Profile
                  </label>
                  <select name="doc_profile" id="entryDocProfile"
                    onchange="const box = document.getElementById('entryDocPfStatusBox'); box.textContent = this.value ? this.options[this.selectedIndex].text : 'Doc PF Not Selected'; box.style.color = this.value ? '#1e40af' : '#4338ca';"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="GRN">GRN</option>
                    <option value="GRSP">GRSP</option>
                    <option value="MRA">MRA</option>
                  </select>
                  <div id="entryDocPfStatusBox"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 14px; background: #ffffff; color: #4338ca; font-size: 0.92rem; min-width: 250px; height: 32px; display: inline-flex; align-items: center;">
                    Doc PF Not Selected
                  </div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 60px;">Status</label>
                    <select name="status"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; width: 110px; background: #ffffff; color: #64748b;"
                      disabled>
                      <option selected>New</option>
                    </select>
                  </div>
                </div>

                <!-- Row 2: Receipt From + Delivery Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Receipt From
                  </label>
                  <select name="receipt_from"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select Receipt From-</option>
                    <option value="Purchase Order">Purchase Order</option>
                    <option value="Cost Center">Cost Center</option>
                    <option value="Section">Section</option>
                  </select>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 100px; white-space: nowrap;">Delivery
                      Date</label>
                    <input type="date" name="delivery_date" value="<?= date('Y-m-d') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; width: 135px; background: #ffffff; color: #1e293b; font-family: inherit; cursor: pointer;">
                  </div>
                </div>

                <!-- Row 3: (Left empty) + Acc Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <div style="width: 140px; flex-shrink: 0;"></div>
                  <div style="width: 210px;"></div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 100px; white-space: nowrap;">Acc
                      Date</label>
                    <input type="date" name="acc_date" value="<?= date('Y-m-d') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; width: 135px; background: #ffffff; color: #1e293b; font-family: inherit; cursor: pointer;">
                  </div>
                </div>

                <!-- Row 4: Source Dept + Due Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Source Dept
                  </label>
                  <input type="text" name="source_dept" value="832.00" readonly
                    style="border: 1.5px solid #000000; border-radius: 3px; padding: 5px 10px; font-size: 0.95rem; width: 210px; background: #ffffff; color: #003893; font-weight: 600; cursor: not-allowed;" tabindex="-1">

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 100px; white-space: nowrap;">Due
                      Date</label>
                    <input type="date" name="due_date" value=""
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; width: 135px; background: #ffffff; color: #1e293b; font-family: inherit; cursor: pointer;">
                  </div>
                </div>

                <!-- Row 5: Warehouse + Base on -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Warehouse
                  </label>
                  <select name="warehouse"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 520px; max-width: 100%; background: #ffffff;">
                    <option value="" selected>---- Select Warehouse----</option>
                    <?php foreach ($stationWarehouseDepts as $wDept): ?>
                      <option value="<?= htmlspecialchars($wDept['name']) ?>"><?= htmlspecialchars($wDept['name']) ?></option>
                    <?php endforeach; ?>
                  </select>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 70px; white-space: nowrap;">Base
                      on</label>
                    <select name="base_on"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; min-width: 175px; background: #ffffff; color: #1e293b;">
                      <option value="" selected>-Select Type-</option>
                      <option value="Invoice">Invoice</option>
                      <option value="Measurement Report">Measurement Report</option>
                    </select>
                  </div>
                </div>

              </div>

              <!-- 2. Three Light-Blue Collapsible Panes (Image 2) -->

              <!-- Pane 1: Values To Consider for Material Unit Price -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Values To Consider for Material Unit Price
                  </span>
                  <button type="button" onclick="toggleEditPanel('entry-panel-unit-price', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="entry-panel-unit-price"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <!-- NBT -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">NBT</label>
                    <input type="text" name="val_nbt"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                    <label
                      style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 24px; cursor: pointer;">
                      Exclude from Unit Price <input type="checkbox" name="exclude_nbt"
                        style="width: 15px; height: 15px;">
                    </label>
                  </div>
                  <!-- VAT -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">VAT</label>
                    <input type="text" name="val_vat"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                    <label
                      style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 24px; cursor: pointer;">
                      Exclude from Unit Price <input type="checkbox" name="exclude_vat"
                        style="width: 15px; height: 15px;">
                    </label>
                  </div>
                  <!-- Transport -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">Transport</label>
                    <input type="text" name="val_transport"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                    <label
                      style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 24px; cursor: pointer;">
                      Exclude from Unit Price <input type="checkbox" name="exclude_transport"
                        style="width: 15px; height: 15px;">
                    </label>
                  </div>
                  <!-- Other -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">Other</label>
                    <input type="text" name="val_other"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                    <label
                      style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 24px; cursor: pointer;">
                      Exclude from Unit Price <input type="checkbox" name="exclude_other"
                        style="width: 15px; height: 15px;">
                    </label>
                  </div>
                </div>
              </div>

              <!-- Pane 2: Exapand to Add,Edit Or view Reference Data -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Reference Data
                  </span>
                  <button type="button" onclick="toggleEditPanel('entry-panel-ref-data', this);" title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="entry-panel-ref-data"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Invoice No*</label>
                    <input type="text" name="ref_invoice_no"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Order No*</label>
                    <input type="text" name="ref_order_no"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Other Reference
                      1</label>
                    <input type="text" name="ref_other_1"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Other Reference
                      2</label>
                    <input type="text" name="ref_other_2"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                </div>
              </div>

              <!-- Pane 3: Exapand to Add,Edit Or view Remarks -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Remarks
                  </span>
                  <button type="button" onclick="toggleEditPanel('entry-panel-remarks', this);" title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="entry-panel-remarks"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; align-items: flex-start; gap: 12px;">
                  <label
                    style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px; margin-top: 4px;">Remarks</label>
                  <textarea name="remarks"
                    style="border: 1px solid #82a8db; border-radius: 2px; padding: 6px 8px; width: 320px; height: 60px; background: #ffffff; resize: both;"></textarea>
                </div>
              </div>

              <!-- 3. Totals & Checkboxes Area -->
              <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px; margin-bottom: 22px;">
                <!-- Invoice Total (Without Tax) * -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 220px; flex-shrink: 0;">
                    Invoice Total (Without Tax) *
                  </label>
                  <input type="text" name="invoice_total_without_tax"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 10px; width: 220px; background: #ffffff;">
                  <label
                    style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 36px; cursor: pointer;">
                    <input type="checkbox" name="is_single_material" style="width: 15px; height: 15px;">
                    Single Material
                  </label>
                  <button type="button" onclick="alert('Retrieving materials from reference documents...');"
                    style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.92rem; padding: 6px 28px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25); margin-left: auto;">
                    Retrieve Materials
                  </button>
                </div>

                <!-- Discount For All -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 220px; flex-shrink: 0;">
                    Discount For All
                  </label>
                  <input type="text" name="discount_for_all"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 10px; width: 220px; background: #ffffff;">
                  <label
                    style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 36px; cursor: pointer;">
                    <input type="checkbox" name="is_precentage" style="width: 15px; height: 15px;">
                    Precentage
                  </label>
                  <select name="discount_type"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; width: 210px; background: #ffffff; color: #1e293b; margin-left: auto;">
                    <option value="" selected>-Select Discount Type-</option>
                    <option value="For Quantity">For Quantity</option>
                    <option value="For Cost">For Cost</option>
                  </select>
                </div>
              </div>

              <!-- 4. Custom Full-Width Searchable Material Dropdown (Pure White Background, No OS Black Datalist) -->
              <div
                style="display: flex; align-items: center; gap: 16px; margin-top: 24px; width: 100%;">
                <div style="position: relative; flex: 1; width: 100%;" id="receiptMaterialWrapper">
                  <div style="position: relative; width: 100%; display: flex; align-items: center;">
                    <svg style="position: absolute; left: 12px; color: #64748b; pointer-events: none;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="11" cy="11" r="8"></circle>
                      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="text" name="selected_material" id="receiptMaterialSearchInput"
                      placeholder="-- Search or Select Material For Receipt (e.g. MAT-101, Pump, Bearings) --"
                      style="border: 1px solid #b0b8c1; border-radius: 3px; padding: 8px 36px 8px 38px; font-size: 0.92rem; width: 100%; background: #ffffff; color: #1e293b; box-sizing: border-box; outline: none;"
                      autocomplete="off"
                      onclick="openReceiptMaterialDropdown('receiptMaterialDropdownMenu');"
                      oninput="filterReceiptMaterialDropdown(this.value, 'receiptMaterialDropdownMenu');">
                    <button type="button"
                      onclick="toggleReceiptMaterialDropdown('receiptMaterialDropdownMenu');"
                      style="position: absolute; right: 8px; background: transparent; border: none; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; padding: 4px;"
                      title="Toggle Material List">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                      </svg>
                    </button>
                  </div>

                  <!-- Full-Width Pure White Custom Dropdown Menu -->
                  <div id="receiptMaterialDropdownMenu"
                    style="display: none; position: absolute; top: calc(100% + 2px); left: 0; width: 100%; max-height: 240px; overflow-y: auto; background: #ffffff; border: 1px solid #b0b8c1; border-radius: 3px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); z-index: 1050; box-sizing: border-box;">
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-101 • Heavy Duty Hydraulic Pump Seal Kit', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0369a1';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-101 &bull; Heavy Duty Hydraulic Pump Seal Kit
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-102 • Industrial Ball Bearings #6204-2RS', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-102 &bull; Industrial Ball Bearings #6204-2RS
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-103 • Synthetic High Temperature Grease 18kg', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-103 &bull; Synthetic High Temperature Grease 18kg
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-104 • Pneumatic Solenoid Valve 24V DC', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-104 &bull; Pneumatic Solenoid Valve 24V DC
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-105 • High Voltage Insulator Bushing 33kV', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-105 &bull; High Voltage Insulator Bushing 33kV
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-106 • Stainless Steel Hex Bolts M16x50mm', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-106 &bull; Stainless Steel Hex Bolts M16x50mm
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-107 • Turbine Lubricant ISO VG 46 (200L)', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-107 &bull; Turbine Lubricant ISO VG 46 (200L)
                    </div>
                    <div class="material-dropdown-item" onclick="selectReceiptMaterial('MAT-108 • Digital Flow Meter Sensor 4-20mA', 'receiptMaterialSearchInput', 'receiptMaterialDropdownMenu');" style="padding: 10px 16px; font-size: 0.92rem; color: #1e293b; background: #ffffff; cursor: pointer; transition: background 0.12s ease;" onmouseover="this.style.backgroundColor='#e0f2fe'; this.style.color='#0284c7';" onmouseout="this.style.backgroundColor='#ffffff'; this.style.color='#1e293b';">
                      MAT-108 &bull; Digital Flow Meter Sensor 4-20mA
                    </div>
                  </div>
                </div>
                <button type="button" onclick="const mVal = document.getElementById('receiptMaterialSearchInput').value; if(mVal){ alert('Material added to receipt list: ' + mVal); } else { alert('Please search and select a material first.'); }"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.92rem; padding: 7px 32px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25); white-space: nowrap; flex-shrink: 0;">
                  Add Material
                </button>
              </div>

              <!-- 6. Action Footer: Save & Clear Buttons -->
              <div style="display: flex; justify-content: center; gap: 24px; margin-top: 28px; margin-bottom: 12px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 56px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Save
                </button>
                <button type="button"
                  onclick="document.getElementById('receiptsEntryForm').reset(); document.getElementById('entryDocPfStatusBox').textContent = 'Doc PF Not Selected';"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 56px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>

            </form>
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.1.2: TRANSACTION -> RECEIPTS -> VALIDATION (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-receipts-validation" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">

            <div>
              <h4 style="font-size: 1.05rem; font-weight: 700; color: #000000; margin: 0 0 16px 0; font-family: inherit;">
                Following Documents are pending for your validation
              </h4>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: flex-end; padding-top: 40px;">
              <button type="button" onclick="alert('No pending documents selected for validation.');"
                style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 8px 44px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                Validate
              </button>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.1.3: TRANSACTION -> RECEIPTS -> APPROVAL (CLEAN WHITE PAGE)
         ===================================================================== -->
        <section id="tab-user-receipts-approval" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.1.4: TRANSACTION -> RECEIPTS -> UNPOSTED DOCUMENT EDIT
         ===================================================================== -->
        <!-- =====================================================================
         PANE 2.1.4: TRANSACTION -> RECEIPTS -> UNPOSTED DOCUMENT EDIT (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-receipts-edit" class="user-tab-pane">
          <div class="card"
            style="padding: 24px 32px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">
            <form id="receiptsUnpostedEditForm"
              onsubmit="event.preventDefault(); saveUnpostedDocument('receipts', this);">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- 1. Document No Selection Row -->
              <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 18px;">
                <label for="receiptsEditDocNo"
                  style="font-weight: 700; font-size: 0.95rem; color: #000000; min-width: 130px;">
                  Document No
                </label>
                <select id="receiptsEditDocNo" name="doc_no" class="form-select"
                  onchange="updateReceiptCurrentDocDetails(this.value, 'receipts')"
                  style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 220px; background: #ffffff; color: #1e293b;">
                  <option value="">-Select DOC NO-</option>
                  <?php foreach ($unpostedReceiptDocs as $idx => $rDoc): ?>
                    <option value="<?= htmlspecialchars($rDoc['doc_no']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                      <?= htmlspecialchars($rDoc['doc_no']) ?> (<?= htmlspecialchars($rDoc['status'] ?? 'Draft') ?> - <?= htmlspecialchars($rDoc['warehouse'] ?? 'Store') ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- 2. Current Document Details Yellow/Green Info Box -->
              <h4 style="font-size: 0.98rem; font-weight: 700; color: #000000; margin: 0 0 8px 0;">
                Current Document Details
              </h4>

              <div style="background-color: #f7fee7; border: 1px solid #e2f5b8; padding: 8px 12px; margin-bottom: 22px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px;">

                  <!-- Column 1 (Left 11 Rows) -->
                  <div style="display: flex; flex-direction: column; gap: 3px;">
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Document
                        Profile</span>
                      <span id="receipts-cur-doc-profile"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Goods Received Note</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Receipt
                        From</span>
                      <span id="receipts-cur-receipt-from"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Purchase Order</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Source
                        Dept</span>
                      <span id="receipts-cur-source-dept"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">832.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Receipt
                        Details</span>
                      <span id="receipts-cur-receipt-details"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Direct Shipment Voucher #91</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Warehouse</span>
                      <span id="receipts-cur-warehouse"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Mechanical Stores</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">NBT</span>
                      <span id="receipts-cur-nbt" style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">VAT</span>
                      <span id="receipts-cur-vat" style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">18.00%</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Transport</span>
                      <span id="receipts-cur-transport"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">4,500.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Other</span>
                      <span id="receipts-cur-other"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Inovice
                        Total</span>
                      <span id="receipts-cur-invoice-total"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">148,250.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Discount
                        for all</span>
                      <span id="receipts-cur-discount-for-all"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">2.50%</span>
                    </div>
                  </div>

                  <!-- Column 2 (Right Rows + Spanning Remarks Textarea) -->
                  <div style="display: flex; flex-direction: column; gap: 3px;">
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Delivery
                        date</span>
                      <span id="receipts-cur-delivery-date"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">23-Sep-2026</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Acc
                        Date</span>
                      <span id="receipts-cur-acc-date"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">23-Sep-2026</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Due
                        Date</span>
                      <span id="receipts-cur-due-date"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">23-Oct-2026</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Invoice
                        Number</span>
                      <span id="receipts-cur-invoice-num"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">INV-LHS-9041</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Order
                        ref</span>
                      <span id="receipts-cur-order-ref"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">PO-2026-0814</span>
                    </div>
                    <!-- Remarks Row with downward spanning textarea -->
                    <div style="display: flex; align-items: flex-start; gap: 8px; flex: 1; margin-top: 2px;">
                      <div style="display: flex; flex-direction: column; gap: 3px; width: 130px; flex-shrink: 0;">
                        <span
                          style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; display: block;">Remarks</span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                      </div>
                      <textarea id="receipts-cur-remarks" readonly
                        style="width: 100%; height: 138px; border: 1px solid #d1d5db; background: #ffffff; padding: 6px 10px; font-size: 0.88rem; color: #374151; resize: both; border-radius: 2px;">Goods inspected upon delivery. Awaiting quality signoff for unit price adjustments.</textarea>
                    </div>
                  </div>

                </div>
              </div>

              <!-- 3. Update Fields Section -->
              <h4 style="color: #2e7d32; font-weight: 700; font-size: 1.05rem; margin: 20px 0 16px 0;">
                Use Following Feilds To Update The Document
              </h4>

              <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">

                <!-- Row 1: Document Profile + Preview + Status -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Document Profile
                  </label>
                  <select name="update_doc_profile" id="receiptsUpdateDocProfile"
                    onchange="const box = document.getElementById('receiptsDocPfStatusBox'); box.textContent = this.value ? this.options[this.selectedIndex].text : 'Doc PF Not Selected'; box.style.color = this.value ? '#1e40af' : '#4338ca';"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="PF-GRN">Goods Received Note</option>
                    <option value="PF-DIR">Direct Material Purchase</option>
                    <option value="PF-STR">Store Transfer Receipt</option>
                  </select>
                  <div id="receiptsDocPfStatusBox"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 14px; background: #ffffff; color: #4338ca; font-size: 0.92rem; min-width: 250px; height: 32px; display: inline-flex; align-items: center;">
                    Doc PF Not Selected
                  </div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 80px;">Status</label>
                    <select name="update_status"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; width: 110px; background: #ffffff; color: #64748b;">
                      <option selected>New</option>
                      <option>Draft</option>
                      <option>Under Review</option>
                    </select>
                  </div>
                </div>

                <!-- Row 2: Receipt From + Delivery Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Receipt From
                  </label>
                  <select name="update_receipt_from"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select Receipt From-</option>
                    <option value="Purchase Order">Purchase Order</option>
                    <option value="Cost Center">Cost Center</option>
                    <option value="Section">Section</option>
                  </select>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Delivery
                      Date</label>
                    <input type="date" name="update_delivery_date" value="<?= date('Y-m-d') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; width: 135px; background: #ffffff; color: #1e293b; font-family: inherit; cursor: pointer;">
                  </div>
                </div>

                <!-- Row 3: Source Dept + Acc Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Source Dept
                  </label>
                  <input type="text" name="update_source_dept" value="832.00" readonly
                    style="border: 1.5px solid #000000; border-radius: 3px; padding: 5px 10px; font-size: 0.95rem; width: 210px; background: #ffffff; color: #003893; font-weight: 600; cursor: not-allowed;" tabindex="-1">

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Acc
                      Date</label>
                    <input type="date" name="update_acc_date" value="<?= date('Y-m-d') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; width: 135px; background: #ffffff; color: #1e293b; font-family: inherit; cursor: pointer;">
                  </div>
                </div>

                <!-- Row 4: (Left empty) + Due Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <div style="width: 140px; flex-shrink: 0;"></div>
                  <div style="width: 210px;"></div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Due
                      Date</label>
                    <input type="date" name="update_due_date" value=""
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; width: 135px; background: #ffffff; color: #1e293b; font-family: inherit; cursor: pointer;">
                  </div>
                </div>

                <!-- Row 5: Warehouse -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Warehouse
                  </label>
                  <select name="update_warehouse"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 520px; max-width: 100%; background: #ffffff;">
                    <option value="" selected>---- Select Warehouse----</option>
                    <?php foreach ($stationWarehouseDepts as $wDept): ?>
                      <option value="<?= htmlspecialchars($wDept['name']) ?>"><?= htmlspecialchars($wDept['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

              </div>

              <!-- 4. Three Light-Blue Collapsible Panes (Matching Image 2) -->

              <!-- Pane 1: Values To Consider for Material Unit Price -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Values To Consider for Material Unit Price
                  </span>
                  <button type="button" onclick="toggleEditPanel('receipts-panel-unit-price', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="receipts-panel-unit-price"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <!-- VAT -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">VAT</label>
                    <input type="text" name="val_vat"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                  </div>
                  <!-- Transport & NBT -->
                  <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">Transport</label>
                    <input type="text" name="val_transport"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                    <label
                      style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 45px; margin-left: 24px;">NBT</label>
                    <input type="text" name="val_nbt"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                  </div>
                  <!-- Other -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">Other</label>
                    <input type="text" name="val_other"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                  </div>
                </div>
              </div>

              <!-- Pane 2: Exapand to Add,Edit Or view Reference Data -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Reference Data
                  </span>
                  <button type="button" onclick="toggleEditPanel('receipts-panel-ref-data', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="receipts-panel-ref-data"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Invoice
                      Number</label>
                    <input type="text" name="ref_invoice_number"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Order
                      Reference</label>
                    <input type="text" name="ref_order_reference"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Other Reference
                      1</label>
                    <input type="text" name="ref_other_1"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px;">Other Reference
                      2</label>
                    <input type="text" name="ref_other_2"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                </div>
              </div>

              <!-- Pane 3: Exapand to Add,Edit Or view Remarks (Matching Screenshot) -->
              <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Remarks
                  </span>
                  <button type="button" onclick="toggleEditPanel('receipts-panel-remarks', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">+</button>
                </div>
                <div id="receipts-panel-remarks"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: none; align-items: flex-start; gap: 12px;">
                  <label
                    style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px; margin-top: 4px;">Remarks</label>
                  <textarea name="remarks_text"
                    style="border: 1px solid #82a8db; border-radius: 2px; padding: 6px 8px; width: 320px; height: 60px; background: #ffffff; resize: both;"></textarea>
                </div>
              </div>

              <!-- 5. Totals & Checkboxes Area (Matching Screenshot) -->
              <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 14px; margin-bottom: 28px;">
                <!-- Row 1: Invoice Total (Without Tax) -->
                <div style="display: flex; align-items: center; gap: 20px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 220px; flex-shrink: 0;">
                    Invoice Total (Without Tax)
                  </label>
                  <input type="text" name="invoice_total_without_tax"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; width: 220px; background: #ffffff; color: #1e293b;">
                  <label
                    style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 24px; cursor: pointer;">
                    <input type="checkbox" name="is_single_material" style="width: 15px; height: 15px;">
                    Single Material Document
                  </label>
                </div>

                <!-- Row 2: Discount For All -->
                <div style="display: flex; align-items: center; gap: 20px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 220px; flex-shrink: 0;">
                    Discount For All
                  </label>
                  <input type="text" name="discount_for_all"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; width: 220px; background: #ffffff; color: #1e293b;">
                  <label
                    style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 24px; cursor: pointer;">
                    <input type="checkbox" name="is_percentage" style="width: 15px; height: 15px;">
                    Presentage
                  </label>
                </div>
              </div>

              <!-- 6. Material Selection & Add Material Row (Matching Screenshot) -->
              <div
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 20px;">
                <select name="selected_material"
                  style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 12px; font-size: 0.92rem; width: 420px; max-width: 100%; background: #ffffff; color: #1e293b;">
                  <option value="" selected>-- Select Material For Receipt--</option>
                  <option value="MAT-101">MAT-101 &bull; Heavy Duty Hydraulic Pump Seal Kit</option>
                  <option value="MAT-102">MAT-102 &bull; Industrial Ball Bearings #6204-2RS</option>
                  <option value="MAT-103">MAT-103 &bull; Synthetic High Temperature Grease 18kg</option>
                  <option value="MAT-104">MAT-104 &bull; Pneumatic Solenoid Valve 24V DC</option>
                  <option value="MAT-105">MAT-105 &bull; High Voltage Insulator Bushing 33kV</option>
                  <option value="MAT-106">MAT-106 &bull; Stainless Steel Hex Bolts M16x50mm</option>
                </select>
                <button type="button" onclick="alert('Material added to unposted document specification.');"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.92rem; padding: 7px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Add Material
                </button>
              </div>

              <!-- 7. Action Footer: Update & Clear Buttons (Right Aligned Matching Screenshot) -->
              <div style="display: flex; justify-content: flex-end; gap: 16px; margin-top: 48px; margin-bottom: 12px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 56px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Update
                </button>
                <button type="button"
                  onclick="document.getElementById('receiptsUnpostedEditForm').reset(); document.getElementById('receiptsDocPfStatusBox').textContent = 'Doc PF Not Selected'; resetCurrentDocDetails('receipts');"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 56px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>

            </form>
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.1.5: TRANSACTION -> RECEIPTS -> POSTED CANCELLATION (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-receipts-posted-cancel" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 460px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">

            <form id="receiptsPostedCancelForm"
              onsubmit="event.preventDefault(); const no = this.querySelector('select[name=doc_number]').value; if(!no){ alert('Please select a Document Number to cancel.'); return; } if(confirm('Are you sure you want to cancel posted receipt document ' + no + '?')) { alert('Posted GRN [' + no + '] reversal voucher generated successfully.'); this.reset(); }">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- Fields Grid -->
              <div
                style="display: grid; grid-template-columns: 210px 1fr 110px 140px; gap: 14px 18px; align-items: center; max-width: 960px; margin-bottom: 14px;">

                <!-- Row 1 -->
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                  Cancelation Profile
                </label>
                <div>
                  <select name="cancel_profile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; max-width: 280px; background: #ffffff; color: #1e293b;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="Goods Received Note (GRN)">Goods Received Note (GRN)</option>
                    <option value="Direct Material Purchase">Direct Material Purchase</option>
                    <option value="Store Transfer Receipt">Store Transfer Receipt</option>
                  </select>
                </div>
                <label
                  style="font-weight: 700; font-size: 0.95rem; color: #000000; text-align: right; white-space: nowrap;">
                  Cancel Date
                </label>
                <div>
                  <input type="text" name="cancel_date" value="<?= date('d-M-Y') ?>" class="form-control"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; background: #ffffff; text-align: center; color: #1e293b;">
                </div>

                <!-- Row 2 -->
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                  Cancelation Document Number
                </label>
                <div>
                  <input type="text" name="cancel_doc_no" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; max-width: 340px; background: #ffffff; color: #1e293b;">
                </div>
                <label
                  style="font-weight: 700; font-size: 0.95rem; color: #000000; text-align: right; white-space: nowrap;">
                  Acc Date
                </label>
                <div>
                  <input type="text" name="acc_date" value="<?= date('d-M-Y') ?>" class="form-control"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; background: #ffffff; text-align: center; color: #1e293b;">
                </div>

                <!-- Row 3: Cancelation Reason -->
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; align-self: start; padding-top: 8px;">
                  Cancelation Reason
                </label>
                <div style="grid-column: span 3;">
                  <textarea name="cancel_reason" rows="3" class="form-control"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 8px 12px; font-size: 0.92rem; width: 100%; max-width: 680px; height: 75px; background: #ffffff; color: #1e293b; resize: vertical; font-family: inherit;"></textarea>
                </div>
              </div>

              <!-- Bottom Row: Document Profile & Document Number with SVG Search Icon -->
              <div
                style="display: flex; align-items: center; gap: 24px 32px; flex-wrap: wrap; margin-top: 36px; padding-top: 10px;">

                <!-- Document Profile -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                    Document Profile
                  </label>
                  <select name="doc_profile" id="receiptsPostedDocProfile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 190px; background: #ffffff; color: #1e293b;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="Goods Received Note (GRN)">Goods Received Note (GRN)</option>
                    <option value="Direct Material Purchase">Direct Material Purchase</option>
                    <option value="Store Transfer Receipt">Store Transfer Receipt</option>
                  </select>
                </div>

                <!-- Document Number & Search Icon -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                    Document Number
                  </label>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <select name="doc_number" id="receiptsPostedDocNumber" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 220px; background: #ffffff; color: #1e293b;">
                      <option value="" selected>-Select DOC NO-</option>
                      <option value="GRN-2026-0001">GRN-2026-0001 (Posted - Hydraulic Spares)</option>
                      <option value="GRN-2026-0002">GRN-2026-0002 (Posted - Turbine Oil)</option>
                      <option value="DPR-2026-0003">DPR-2026-0003 (Posted - Electrical Cables)</option>
                      <option value="STR-2026-0004">STR-2026-0004 (Posted - Fasteners)</option>
                    </select>

                    <!-- Clickable Search Icon Button (Clean SVG, No Image File) -->
                    <button type="button" id="btnSearchReceiptsPostedDoc" title="Search Document"
                      aria-label="Search Document"
                      onclick="const sel = document.getElementById('receiptsPostedDocNumber'); if(sel.value){ alert('Found posted document: ' + sel.value + '\nLedger Status: Posted & Reconciled\nMaterial Issues: 0'); } else { alert('Please select a Document Profile or Number to search.'); }"
                      style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #ffffff; border: 1px solid #b0b8c1; border-radius: 3px; cursor: pointer; color: #1e5a8a; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.08);"
                      onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#1e5a8a';"
                      onmouseout="this.style.background='#ffffff'; this.style.borderColor='#b0b8c1';">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                      </svg>
                    </button>
                  </div>
                </div>

              </div>

              <!-- Red Notice Text Matching Screenshot -->
              <div
                style="margin-top: 24px; margin-bottom: 12px; color: #cc1818; font-weight: 700; font-size: 0.95rem; line-height: 1.4;">
                Please note that only GL Posted and GRNs which has NO material issues will be allowed for the posted
                cancellations
              </div>

              <!-- Bottom Action Buttons Matching Screenshot -->
              <div
                style="display: flex; justify-content: flex-end; align-items: center; gap: 14px; margin-top: 10px; max-width: 960px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Save
                </button>
                <button type="button" onclick="document.getElementById('receiptsPostedCancelForm').reset();"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.1.6: TRANSACTION -> RECEIPTS -> UNPOSTED CANCELLATION (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-receipts-unposted-cancel" class="user-tab-pane">
          <div class="card"
            style="padding: 0; background: #ffffff; min-height: 440px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;">

            <form id="receiptsUnpostedCancelForm"
              onsubmit="event.preventDefault(); const no = this.querySelector('select[name=doc_number]').value; if(!no){ alert('Please select a Document Number to cancel.'); return; } if(confirm('Are you sure you want to cancel unposted receipt document ' + no + '?')) { alert('Unposted document [' + no + '] cancelled successfully and provisional batch cleared.'); this.reset(); }">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- Fields Area -->
              <div style="padding: 36px 40px;">
                <div style="display: flex; align-items: center; gap: 24px 32px; flex-wrap: wrap;">

                  <!-- Document Profile -->
                  <div style="display: flex; align-items: center; gap: 14px;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                      Document Profile
                    </label>
                    <select name="doc_profile" id="receiptsUnpostedDocProfile" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 190px; background: #ffffff; color: #1e293b;">
                      <option value="" selected>-Select DOC PF-</option>
                      <option value="Goods Received Note (GRN)">Goods Received Note (GRN)</option>
                      <option value="Direct Material Purchase">Direct Material Purchase</option>
                      <option value="Store Transfer Receipt">Store Transfer Receipt</option>
                    </select>
                  </div>

                  <!-- Document Number & Search Icon -->
                  <div style="display: flex; align-items: center; gap: 14px;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                      Document Number
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <select name="doc_number" id="receiptsUnpostedDocNumber" class="form-select"
                        style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 220px; background: #ffffff; color: #1e293b;">
                        <option value="" selected>-Select DOC NO-</option>
                        <option value="GRN-2026-0091">GRN-2026-0091 (Draft - Mechanical)</option>
                        <option value="GRN-2026-0092">GRN-2026-0092 (Draft - Electrical Supplies)</option>
                        <option value="DPR-2026-0044">DPR-2026-0044 (Draft - Spares)</option>
                        <option value="SRN-2026-0012">SRN-2026-0012 (Draft - Store Return)</option>
                      </select>

                      <!-- Clickable Search Icon Button (Clean SVG, No Image File) -->
                      <button type="button" id="btnSearchReceiptsUnpostedDoc" title="Search Document"
                        aria-label="Search Document"
                        onclick="const sel = document.getElementById('receiptsUnpostedDocNumber'); if(sel.value){ alert('Found unposted document: ' + sel.value + '\nStatus: Unposted Draft\nStage: Draft Goods Receipt'); } else { alert('Please select a Document Profile or Number to search.'); }"
                        style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #ffffff; border: 1px solid #b0b8c1; border-radius: 3px; cursor: pointer; color: #1e5a8a; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.08);"
                        onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#1e5a8a';"
                        onmouseout="this.style.background='#ffffff'; this.style.borderColor='#b0b8c1';">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                          stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                          <circle cx="11" cy="11" r="8"></circle>
                          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                      </button>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Bottom Grey Action Bar Matching Screenshot -->
              <div
                style="background: #a3a3a3; padding: 12px 28px; display: flex; justify-content: flex-end; align-items: center; gap: 14px; border-top: 1px solid #737373;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Save
                </button>
                <button type="button" onclick="document.getElementById('receiptsUnpostedCancelForm').reset();"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.2: TRANSACTION -> ISSUE -> ENTRY (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-issue-entry" class="user-tab-pane">
          <div class="card"
            style="padding: 24px 32px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">
            <form id="issueEntryForm" onsubmit="event.preventDefault(); alert('Issue draft saved successfully.');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- 1. Header Grid Area -->
              <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px;">

                <!-- Row 1: Document Profile + Preview + Status -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Document Profile
                  </label>
                  <select name="doc_profile" id="issueDocProfile"
                    onchange="handleIssueDocProfileChange(this.value, 'entry');"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="TV-ISSUE">TV-ISSUE</option>
                    <option value="MAT-REQ">MAT-REQ</option>
                    <option value="ISSUE">ISSUE</option>
                  </select>
                  <div id="issueDocPfStatusBox"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 14px; background: #ffffff; color: #4338ca; font-size: 0.92rem; min-width: 340px; height: 32px; display: inline-flex; align-items: center;">
                    Doc PF Not Selected
                  </div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 60px;">Status</label>
                    <select name="status"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; width: 110px; background: #ffffff; color: #64748b;"
                      disabled>
                      <option selected>New</option>
                    </select>
                  </div>
                </div>

                <!-- Row 2: Issue To -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0; display: flex; align-items: center; justify-content: space-between;">
                    <span>Issue To</span>
                    <?php if ($isAdmin): ?>
                      <a href="<?= $base ?>/admin/index.php?tab=issue-codes"
                        onclick="if(document.querySelector('[data-tab-target=\'tab-issue-codes\']')){ switchTab('tab-issue-codes'); return false; } if(typeof switchUserTab==='function'){ switchUserTab('tab-user-issue-codes'); return false; }"
                        style="font-size: 0.72rem; font-weight: 700; color: #2563eb; text-decoration: underline;" title="Manage Issue Categories &amp; Codes">
                        ⚙ Manage
                      </a>
                    <?php endif; ?>
                  </label>
                  <select name="issue_to" id="issueTo"
                    onchange="handleIssueToChange(this.value);"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select Issue To-</option>
                    <option value="Project">Project</option>
                    <option value="Cost Center">Cost Center</option>
                    <option value="Warehouse">Warehouse</option>
                    <option value="My Project">My Project</option>
                    <option value="Section">Section</option>
                    <option value="Genaration as Expenditure">Genaration as Expenditure</option>
                  </select>
                </div>

                <!-- Row 3: Dynamic Category Destination Field + Trx Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <div id="issueDestFieldContainer" style="display: none; align-items: center; gap: 14px;">
                    <label id="issueDestLabel"
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0; line-height: 1.2;">
                      Project Dest Dept
                    </label>
                    <select name="issue_dest_dept" id="issueDestSelect"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 520px; max-width: 100%; background: #ffffff;">
                      <option value="">---- Select Cost Center----</option>
                    </select>
                  </div>
                  <div id="issueDestEmptyPlaceholder" style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 140px; flex-shrink: 0;"></div>
                    <div style="width: 210px;"></div>
                  </div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Trx
                      Date</label>
                    <input type="text" name="trx_date" value="<?= date('d-M-Y') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 110px; background: #ffffff;">
                  </div>
                </div>

                <!-- Row 4: Warehouse + Acc Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Warehouse
                  </label>
                  <select name="warehouse"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 520px; max-width: 100%; background: #ffffff;">
                    <option value="" selected>---- Select Warehouse----</option>
                    <?php foreach ($stationWarehouseDepts as $wDept): ?>
                      <option value="<?= htmlspecialchars($wDept['name']) ?>"><?= htmlspecialchars($wDept['name']) ?></option>
                    <?php endforeach; ?>
                  </select>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Acc
                      Date</label>
                    <input type="text" name="acc_date" value="<?= date('d-M-Y') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 110px; background: #ffffff;">
                  </div>
                </div>

                <!-- Row 5: Retrieve Materials Button -->
                <div style="margin-top: 6px;">
                  <button type="button" onclick="alert('Retrieving allocated issue materials...');"
                    style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.92rem; padding: 7px 24px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                    Retrieve Materials
                  </button>
                </div>

              </div>

              <!-- 2. Two Collapsible Blue Panes -->

              <!-- Pane 1: Exapand to Add,Edit Or view Reference Data -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Reference Data
                  </span>
                  <button type="button" onclick="toggleEditPanel('issue-entry-panel-ref-data', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="issue-entry-panel-ref-data"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueRefLabel1" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 1</label>
                    <input type="text" name="ref_1" id="issueRefInput1"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueRefLabel2" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 2</label>
                    <input type="text" name="ref_2" id="issueRefInput2"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueRefLabel3" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 3</label>
                    <input type="text" name="ref_3" id="issueRefInput3"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueRefLabel4" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 4</label>
                    <input type="text" name="ref_4" id="issueRefInput4"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                </div>
              </div>

              <!-- Pane 2: Exapand to Add,Edit Or view Remarks -->
              <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Remarks
                  </span>
                  <button type="button" onclick="toggleEditPanel('issue-entry-panel-remarks', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">+</button>
                </div>
                <div id="issue-entry-panel-remarks"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: none; align-items: flex-start; gap: 12px;">
                  <label
                    style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 120px; margin-top: 4px;">Remarks</label>
                  <textarea name="remarks"
                    style="border: 1px solid #82a8db; border-radius: 2px; padding: 6px 8px; width: 320px; height: 60px; background: #ffffff; resize: both;"></textarea>
                </div>
              </div>

              <!-- 3. Material Selection, Avail Qty, Re Order Qty Level, Required Qty -->
              <div style="display: flex; align-items: flex-end; gap: 18px; flex-wrap: wrap; margin-bottom: 12px;">
                <!-- Material Selection (Station-Specific Added Codes with Integrated Search) -->
                <div style="position: relative; min-width: 360px; max-width: 480px; flex: 1;">
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="issueMaterialSearchInput"
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; margin: 0;">
                      Material Selection
                    </label>
                    <span id="issueStationCodeBadge" style="font-size: 0.72rem; font-weight: 700; color: #166534; background: #dcfce7; border: 1px solid #86efac; padding: 2px 7px; border-radius: 3px;">
                      Station Added Codes Only
                    </span>
                  </div>

                  <div style="position: relative; display: flex; align-items: center;">
                    <!-- Magnifying Glass Icon -->
                    <span style="position: absolute; left: 10px; color: #64748b; pointer-events: none; display: flex; align-items: center;">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                      </svg>
                    </span>

                    <!-- Search Input Box -->
                    <input type="text" id="issueMaterialSearchInput" autocomplete="off"
                      placeholder="Search code or material name (e.g. 1-01, Seal, Grease)..."
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 54px 6px 32px; font-size: 0.92rem; width: 100%; background: #ffffff; color: #1e293b; box-sizing: border-box; height: 34px;"
                      onclick="openIssueMaterialDropdown()"
                      onfocus="openIssueMaterialDropdown()"
                      oninput="filterIssueMaterialDropdown(this.value)"
                      onkeydown="handleIssueMaterialKeydown(event)">

                    <!-- Clear selection button (X) -->
                    <button type="button" id="issueMaterialClearBtn"
                      onclick="clearIssueMaterialSelection(true)"
                      style="display: none; position: absolute; right: 28px; background: transparent; border: none; cursor: pointer; color: #94a3b8; font-size: 15px; font-weight: 700; padding: 4px; line-height: 1;"
                      title="Clear Selection">
                      &times;
                    </button>

                    <!-- Dropdown Toggle Button (Chevron) -->
                    <button type="button"
                      onclick="toggleIssueMaterialDropdown()"
                      style="position: absolute; right: 6px; background: transparent; border: none; cursor: pointer; color: #64748b; display: flex; align-items: center; justify-content: center; padding: 4px;"
                      title="Show Station Codes">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                      </svg>
                    </button>
                  </div>

                  <!-- Hidden input to maintain form compatibility and submit selected material code -->
                  <input type="hidden" name="issue_material" id="issueMaterialSelect" value="">

                  <!-- Custom Dropdown Menu for Station Added Codes -->
                  <div id="issueMaterialDropdownMenu"
                    style="display: none; position: absolute; top: calc(100% + 2px); left: 0; width: 100%; min-width: 380px; max-height: 280px; overflow-y: auto; background: #ffffff; border: 1px solid #94a3b8; border-radius: 3px; box-shadow: 0 10px 25px rgba(0,0,0,0.18); z-index: 1050; box-sizing: border-box;">
                    <div id="issueMaterialDropdownHeader"
                      style="padding: 6px 12px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.78rem; font-weight: 700; color: #475569; display: flex; justify-content: space-between; align-items: center;">
                      <span>Power Station Code Table</span>
                      <span id="issueMaterialCountBadge" style="background: #e2e8f0; padding: 1px 6px; border-radius: 3px; font-size: 0.72rem;">0 codes</span>
                    </div>
                    <div id="issueMaterialDropdownList">
                      <!-- Dynamically rendered items -->
                    </div>
                  </div>
                </div>

                <!-- Avail Quantity -->
                <div>
                  <label
                    style="font-weight: 700; font-size: 0.95rem; color: #000000; display: block; margin-bottom: 6px;">
                    Avail Quantity
                  </label>
                  <input type="text" id="issueAvailQty" readonly
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 8px; width: 90px; background: #f8fafc; color: #334155; text-align: center; height: 34px;">
                </div>

                <!-- Re Order Qty Level -->
                <div>
                  <label
                    style="font-weight: 700; font-size: 0.95rem; color: #000000; display: block; margin-bottom: 6px;">
                    Re Order Qty Level
                  </label>
                  <input type="text" id="issueReorderQty" readonly
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 8px; width: 90px; background: #f8fafc; color: #334155; text-align: center; height: 34px;">
                </div>

                <!-- Required Quantity -->
                <div>
                  <label
                    style="font-weight: 700; font-size: 0.95rem; color: #000000; display: block; margin-bottom: 6px;">
                    Required Quantity
                  </label>
                  <input type="number" name="required_quantity" id="issueReqQty" min="1"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 8px; width: 90px; background: #ffffff; text-align: center; height: 34px;">
                </div>
              </div>

              <!-- Add Material To Issue Button -->
              <div style="margin-bottom: 16px;">
                <button type="button" onclick="handleIssueAddMaterial();"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.92rem; padding: 7px 24px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Add Material To Issue
                </button>
              </div>

              <!-- Search Row -->
              <div style="display: flex; align-items: center; gap: 14px; margin-top: 14px;">
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 60px;">Search</label>
                <input type="text" name="issue_search"
                  style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; max-width: 780px; background: #ffffff;">
              </div>

              <!-- Action Buttons: Save & Clear All -->
              <div
                style="display: flex; justify-content: flex-end; align-items: center; gap: 14px; margin-top: 24px; margin-bottom: 10px; max-width: 960px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 56px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Save
                </button>
                <button type="button"
                  onclick="document.getElementById('issueEntryForm').reset(); document.getElementById('issueDocPfStatusBox').textContent = 'Doc PF Not Selected'; document.getElementById('issueAvailQty').value = ''; document.getElementById('issueReorderQty').value = ''; if (typeof handleIssueToChange === 'function') handleIssueToChange(''); if (typeof clearIssueMaterialSelection === 'function') clearIssueMaterialSelection();"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 56px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear All
                </button>
              </div>

            </form>

            <script>
              (function () {
                const dbGrouped = <?= json_encode($groupedIssueCodes ?? []) ?>;
                const costCenters = dbGrouped['Cost Center'] || [];
                const projectDepts = dbGrouped['Project'] || [];
                const warehouses = dbGrouped['Warehouse'] || [];
                const projects = dbGrouped['My Project'] || [];
                const costCodes = dbGrouped['Section'] || [];
                const expenditureDepts = dbGrouped['Genaration as Expenditure'] || [];

                window.issueToCategoryConfig = {
                  'Project': {
                    label: 'Project Dest Dept',
                    placeholder: '---- Select Cost Center----',
                    options: projectDepts
                  },
                  'Cost Center': {
                    label: 'Dept Id',
                    placeholder: '---- Select Cost Center----',
                    options: costCenters
                  },
                  'Warehouse': {
                    label: 'Warehouse to<br>Issue',
                    placeholder: '---- Select Issue To Warehouse----',
                    options: warehouses
                  },
                  'My Project': {
                    label: 'My Prj Dest Dept',
                    placeholder: '---- Select Your Project----',
                    options: projects
                  },
                  'Section': {
                    label: 'Cost Code',
                    placeholder: '---- Select Cost Cd----',
                    options: costCodes
                  },
                  'Genaration as Expenditure': {
                    label: 'Expenditure Dept',
                    placeholder: '---- Select Cost Center----',
                    options: expenditureDepts
                  }
                };

                window.handleIssueToChange = function (val) {
                  const container = document.getElementById('issueDestFieldContainer');
                  const emptySlot = document.getElementById('issueDestEmptyPlaceholder');
                  const label = document.getElementById('issueDestLabel');
                  const select = document.getElementById('issueDestSelect');

                  if (!container || !emptySlot || !label || !select) return;

                  const conf = window.issueToCategoryConfig[val];
                  if (conf) {
                    container.style.display = 'flex';
                    emptySlot.style.display = 'none';
                    label.innerHTML = conf.label;

                    select.innerHTML = '';
                    const defaultOpt = document.createElement('option');
                    defaultOpt.value = '';
                    defaultOpt.textContent = conf.placeholder;
                    select.appendChild(defaultOpt);

                    conf.options.forEach(function (optText) {
                      const opt = document.createElement('option');
                      opt.value = optText;
                      opt.textContent = optText;
                      select.appendChild(opt);
                    });
                  } else {
                    container.style.display = 'none';
                    emptySlot.style.display = 'flex';
                    select.innerHTML = '<option value="">---- Select ----</option>';
                  }
                };

                if (document.readyState === 'loading') {
                  document.addEventListener('DOMContentLoaded', function () {
                    initIssueTo();
                    if (typeof window.initIssueMaterialSelector === 'function') {
                      window.initIssueMaterialSelector();
                    }
                  });
                } else {
                  initIssueTo();
                  if (typeof window.initIssueMaterialSelector === 'function') {
                    window.initIssueMaterialSelector();
                  }
                }

                function initIssueTo() {
                  const issueToEl = document.getElementById('issueTo');
                  if (issueToEl && issueToEl.value) {
                    window.handleIssueToChange(issueToEl.value);
                  }
                }
              })();
            </script>
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.3: TRANSACTION -> ISSUE -> EDIT
         ===================================================================== -->
        <!-- =====================================================================
         PANE 2.3: TRANSACTION -> ISSUE -> UNPOSTED DOCUMENT EDIT (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-issue-edit" class="user-tab-pane">
          <div class="card"
            style="padding: 24px 32px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">
            <form id="issueUnpostedEditForm"
              onsubmit="event.preventDefault(); saveUnpostedDocument('issue', this);">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- 1. Document No Selection Row -->
              <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 18px;">
                <label for="issueEditDocNo"
                  style="font-weight: 700; font-size: 0.95rem; color: #000000; min-width: 130px;">
                  Document No
                </label>
                <select id="issueEditDocNo" name="doc_no" class="form-select"
                  onchange="updateReceiptCurrentDocDetails(this.value, 'issue')"
                  style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 220px; background: #ffffff; color: #1e293b;">
                  <option value="">-Select DOC NO-</option>
                  <?php foreach ($unpostedIssueDocs as $idx => $iDoc): ?>
                    <option value="<?= htmlspecialchars($iDoc['doc_no']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                      <?= htmlspecialchars($iDoc['doc_no']) ?> (<?= htmlspecialchars($iDoc['status'] ?? 'Draft') ?> - <?= htmlspecialchars($iDoc['warehouse'] ?? 'Store') ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- 2. Current Document Details Yellow/Green Info Box -->
              <h4 style="font-size: 0.98rem; font-weight: 700; color: #000000; margin: 0 0 8px 0;">
                Current Document Details
              </h4>

              <div style="background-color: #f7fee7; border: 1px solid #e2f5b8; padding: 8px 12px; margin-bottom: 22px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px;">

                  <!-- Column 1 (Left 11 Rows) -->
                  <div style="display: flex; flex-direction: column; gap: 3px;">
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Document
                        Profile</span>
                      <span id="issue-cur-doc-profile"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Goods Issue Note (GIN)</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Receipt
                        From</span>
                      <span id="issue-cur-receipt-from"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Mechanical Department</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Source
                        Dept</span>
                      <span id="issue-cur-source-dept"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">832.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Receipt
                        Details</span>
                      <span id="issue-cur-receipt-details"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Pump overhaul requisition #41</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Warehouse</span>
                      <span id="issue-cur-warehouse"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">Central Warehouse - Main Store</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">NBT</span>
                      <span id="issue-cur-nbt" style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">VAT</span>
                      <span id="issue-cur-vat" style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00%</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Transport</span>
                      <span id="issue-cur-transport"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Other</span>
                      <span id="issue-cur-other" style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Inovice
                        Total</span>
                      <span id="issue-cur-invoice-total"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">94,500.00</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 140px; display: inline-block;">Discount
                        for all</span>
                      <span id="issue-cur-discount-for-all"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">0.00</span>
                    </div>
                  </div>

                  <!-- Column 2 (Right Rows + Spanning Remarks Textarea) -->
                  <div style="display: flex; flex-direction: column; gap: 3px;">
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Delivery
                        date</span>
                      <span id="issue-cur-delivery-date"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">23-Sep-2026</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Acc
                        Date</span>
                      <span id="issue-cur-acc-date"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">23-Sep-2026</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Due
                        Date</span>
                      <span id="issue-cur-due-date"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;"></span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Invoice
                        Number</span>
                      <span id="issue-cur-invoice-num"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">REQ-MECH-41</span>
                    </div>
                    <div style="display: flex; align-items: center; min-height: 24px;">
                      <span
                        style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; width: 130px; display: inline-block;">Order
                        ref</span>
                      <span id="issue-cur-order-ref"
                        style="padding-left: 12px; font-size: 0.88rem; color: #1f2937;">WO-2026-1102</span>
                    </div>
                    <!-- Remarks Row with downward spanning textarea -->
                    <div style="display: flex; align-items: flex-start; gap: 8px; flex: 1; margin-top: 2px;">
                      <div style="display: flex; flex-direction: column; gap: 3px; width: 130px; flex-shrink: 0;">
                        <span
                          style="background-color: #d9f99d; color: #111827; font-weight: 700; font-size: 0.88rem; padding: 2px 8px; display: block;">Remarks</span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                        <span style="background-color: #d9f99d; height: 18px; display: block; opacity: 0.45;"></span>
                      </div>
                      <textarea id="issue-cur-remarks" readonly
                        style="width: 100%; height: 138px; border: 1px solid #d1d5db; background: #ffffff; padding: 6px 10px; font-size: 0.88rem; color: #374151; resize: both; border-radius: 2px;">Issued for scheduled plant overhaul.</textarea>
                    </div>
                  </div>

                </div>
              </div>

              <!-- 3. Update Fields Section -->
              <h4 style="color: #2e7d32; font-weight: 700; font-size: 1.05rem; margin: 20px 0 16px 0;">
                Use Following Feilds To Update The Document
              </h4>

              <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">

                <!-- Row 1: Document Profile + Preview + Status -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Document Profile
                  </label>
                  <select name="update_doc_profile" id="issueUpdateDocProfile"
                    onchange="handleIssueDocProfileChange(this.value, 'edit');"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="TV-ISSUE">TV-ISSUE</option>
                    <option value="MAT-REQ">MAT-REQ</option>
                    <option value="ISSUE">ISSUE</option>
                  </select>
                  <div id="issueEditDocPfStatusBox"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 14px; background: #ffffff; color: #4338ca; font-size: 0.92rem; min-width: 340px; height: 32px; display: inline-flex; align-items: center;">
                    Doc PF Not Selected
                  </div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 80px;">Status</label>
                    <select name="update_status"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; width: 110px; background: #ffffff; color: #64748b;">
                      <option selected>New</option>
                      <option>Draft</option>
                      <option>Under Review</option>
                    </select>
                  </div>
                </div>

                <!-- Row 2: Receipt From + Delivery Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Receipt From
                  </label>
                  <select name="update_receipt_from"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">
                    <option value="" selected>-Select Receiptient-</option>
                    <option value="REC-01">Mechanical Department</option>
                    <option value="REC-02">Electrical Maintenance Division</option>
                    <option value="REC-03">Civil Works & Infrastructure</option>
                  </select>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Delivery
                      Date</label>
                    <input type="text" name="update_delivery_date" value="<?= date('d-M-Y') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 110px; background: #ffffff;">
                  </div>
                </div>

                <!-- Row 3: Source Dept + Acc Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Source Dept
                  </label>
                  <input type="text" name="update_source_dept" value="832.00"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 210px; background: #ffffff;">

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Acc
                      Date</label>
                    <input type="text" name="update_acc_date" value="<?= date('d-M-Y') ?>"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 110px; background: #ffffff;">
                  </div>
                </div>

                <!-- Row 4: (Left empty) + Due Date -->
                <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                  <div style="width: 140px; flex-shrink: 0;"></div>
                  <div style="width: 210px;"></div>

                  <div style="display: flex; align-items: center; gap: 14px; margin-left: auto;">
                    <label
                      style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 90px; white-space: nowrap;">Due
                      Date</label>
                    <input type="text" name="update_due_date" value=""
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 110px; background: #ffffff;">
                  </div>
                </div>

                <!-- Row 5: Warehouse -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 140px; flex-shrink: 0;">
                    Warehouse
                  </label>
                  <select name="update_warehouse"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 520px; max-width: 100%; background: #ffffff;">
                    <option value="" selected>---- Select Warehouse----</option>
                    <option value="WH-MAIN">Central Warehouse - Main Store (Colombo)</option>
                    <option value="WH-ENG">Engineering & Heavy Spares Warehouse</option>
                    <option value="WH-ELEC">Electrical Supplies Sub-Store</option>
                  </select>
                </div>

              </div>

              <!-- 4. Three Light-Blue Collapsible Panes (Matching Image 2) -->

              <!-- Pane 1: Values To Consider for Material Unit Price -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Values To Consider for Material Unit Price
                  </span>
                  <button type="button" onclick="toggleEditPanel('issue-panel-unit-price', this);"
                    title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="issue-panel-unit-price"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <!-- VAT -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">VAT</label>
                    <input type="text" name="val_vat"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                  </div>
                  <!-- Transport & NBT -->
                  <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">Transport</label>
                    <input type="text" name="val_transport"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                    <label
                      style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 45px; margin-left: 24px;">NBT</label>
                    <input type="text" name="val_nbt"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                  </div>
                  <!-- Other -->
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 80px;">Other</label>
                    <input type="text" name="val_other"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 220px; background: #ffffff;">
                  </div>
                </div>
              </div>

              <!-- Pane 2: Exapand to Add,Edit Or view Reference Data -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Reference Data
                  </span>
                  <button type="button" onclick="toggleEditPanel('issue-panel-ref-data', this);" title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="issue-panel-ref-data"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; flex-direction: column; gap: 10px;">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueEditRefLabel1" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 1</label>
                    <input type="text" name="ref_invoice_number"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueEditRefLabel2" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 2</label>
                    <input type="text" name="ref_order_reference"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueEditRefLabel3" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 3</label>
                    <input type="text" name="ref_other_1"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <label id="issueEditRefLabel4" style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 150px; flex-shrink: 0;">Reference 4</label>
                    <input type="text" name="ref_other_2"
                      style="border: 1px solid #82a8db; border-radius: 2px; padding: 4px 8px; width: 320px; background: #ffffff;">
                  </div>
                </div>
              </div>

              <!-- Pane 3: Exapand to Add,Edit Or view Remarks -->
              <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                  <span style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Exapand to Add,Edit Or view Remarks
                  </span>
                  <button type="button" onclick="toggleEditPanel('issue-panel-remarks', this);" title="Expand / Collapse"
                    style="width: 18px; height: 18px; background: #163660; color: #ffffff; border: 1px solid #0f2544; border-radius: 2px; font-size: 14px; font-weight: 900; line-height: 1; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">-</button>
                </div>
                <div id="issue-panel-remarks"
                  style="background-color: #a0c4f8; padding: 14px 18px; border-radius: 2px; display: flex; align-items: flex-start; gap: 12px;">
                  <label
                    style="font-weight: 700; font-size: 0.92rem; color: #000000; width: 135px; margin-top: 4px;">Remarks</label>
                  <textarea name="remarks_text"
                    style="border: 1px solid #82a8db; border-radius: 2px; padding: 6px 8px; width: 320px; height: 60px; background: #ffffff; resize: both;"></textarea>
                </div>
              </div>

              <!-- 5. Totals & Checkboxes Row -->
              <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 14px; margin-bottom: 22px;">
                <!-- Invoice Total (Without Tax) -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 220px; flex-shrink: 0;">
                    Invoice Total (Without Tax)
                  </label>
                  <input type="text" name="invoice_total_without_tax"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 10px; width: 220px; background: #ffffff;">
                  <label
                    style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 40px; cursor: pointer;">
                    <input type="checkbox" name="is_single_material" style="width: 15px; height: 15px;">
                    Single Material Document
                  </label>
                </div>

                <!-- Discount For All -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; width: 220px; flex-shrink: 0;">
                    Discount For All
                  </label>
                  <input type="text" name="discount_for_all"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 10px; width: 220px; background: #ffffff;">
                  <label
                    style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; font-size: 0.92rem; color: #000000; margin-left: 40px; cursor: pointer;">
                    <input type="checkbox" name="is_percentage" style="width: 15px; height: 15px;">
                    Presentage
                  </label>
                </div>
              </div>

              <!-- 6. Material Selection & Add Material Row -->
              <div
                style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-top: 26px; padding-top: 14px;">
                <select name="selected_material"
                  style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 12px; font-size: 0.92rem; min-width: 420px; background: #ffffff; color: #1e293b;">
                  <option value="" selected>-- Select Material For Receipt--</option>
                  <option value="MAT-101">MAT-101 &bull; Heavy Duty Hydraulic Pump Seal Kit</option>
                  <option value="MAT-102">MAT-102 &bull; Industrial Ball Bearings #6204-2RS</option>
                  <option value="MAT-103">MAT-103 &bull; Synthetic High Temperature Grease 18kg</option>
                  <option value="MAT-104">MAT-104 &bull; Pneumatic Solenoid Valve 24V DC</option>
                </select>
                <button type="button" onclick="alert('Material added to unposted document specification.');"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.92rem; padding: 7px 32px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Add Material
                </button>
              </div>

              <!-- 7. Action Footer: Update & Clear Buttons -->
              <div style="display: flex; justify-content: center; gap: 24px; margin-top: 40px; margin-bottom: 20px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 52px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Update
                </button>
                <button type="button"
                  onclick="document.getElementById('issueUnpostedEditForm').reset(); document.getElementById('issueDocPfStatusBox').textContent = 'Doc PF Not Selected'; resetCurrentDocDetails('issue');"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 52px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>

            </form>
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.4: TRANSACTION -> ISSUE -> VALIDATION (MATCHING USER SCREENSHOT - NO DUMMY DATA)
         ===================================================================== -->
        <section id="tab-user-issue-validation" class="user-tab-pane">
          <div class="card"
            style="padding: 24px 32px; background: #ffffff; min-height: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
              <h4 style="font-size: 1.05rem; font-weight: 700; color: #000000; margin: 0 0 16px 0;">
                Following Documents are pending for your validation
              </h4>

              <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; border: 1px solid #b0b8c1; font-size: 0.9rem;">
                  <thead>
                    <tr style="background-color: #2e5b88; color: #ffffff;">
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: left; border: 1px solid #3d6a97; white-space: nowrap;">
                        Document Profile</th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: left; border: 1px solid #3d6a97; white-space: nowrap;">
                        Document Number</th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: left; border: 1px solid #3d6a97; white-space: nowrap;">
                        Account Date</th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: right; border: 1px solid #3d6a97; white-space: nowrap;">
                        Transaction Value</th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: center; border: 1px solid #3d6a97; white-space: nowrap;">
                        Status</th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: left; border: 1px solid #3d6a97; min-width: 240px; white-space: nowrap;">
                        Approved User</th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: center; border: 1px solid #3d6a97; width: 60px;">
                      </th>
                      <th
                        style="padding: 8px 10px; font-weight: 700; text-align: center; border: 1px solid #3d6a97; width: 80px; white-space: nowrap;">
                        Report</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="8"
                        style="text-align: center; color: #64748b; padding: 48px 16px; font-size: 0.92rem; background: #ffffff; border: 1px solid #e2e8f0;">
                        No documents currently pending for your validation.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Validate Button on Bottom Right -->
            <div style="display: flex; justify-content: flex-end; align-items: flex-end; margin-top: 36px;">
              <button type="button" onclick="alert('No pending documents selected for validation.');"
                style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 7px 44px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                Validate
              </button>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.5: TRANSACTION -> ISSUE -> APPROVAL (CLEAN WHITE PAGE)
         ===================================================================== -->
        <section id="tab-user-issue-approval" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.6: TRANSACTION -> ISSUE -> POSTED CANCELLATION (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-issue-posted-cancel" class="user-tab-pane">
          <div class="card"
            style="padding: 32px 36px 28px; background: #ffffff; min-height: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">

            <form id="issuePostedCancelForm"
              onsubmit="event.preventDefault(); const doc = this.querySelector('select[name=doc_number]').value; if(!doc && !this.querySelector('input[name=cancel_doc_no]').value){ alert('Please select or enter a Document Number to cancel.'); return; } if(confirm('Are you sure you want to process this posted cancellation?')) { alert('Posted document cancellation submitted successfully.'); this.reset(); }">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- Top Grid Section -->
              <div
                style="display: grid; grid-template-columns: 210px 1fr 110px 140px; gap: 14px 18px; align-items: center; max-width: 960px; margin-bottom: 14px;">

                <!-- Row 1 -->
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                  Cancelation Profile
                </label>
                <div>
                  <select name="cancel_profile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; max-width: 280px; background: #ffffff; color: #1e293b;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="Goods Issue Note (GIN)">Goods Issue Note (GIN)</option>
                    <option value="Direct Material Issue">Direct Material Issue</option>
                    <option value="Department Stock Transfer Issue">Department Stock Transfer Issue</option>
                    <option value="Store Return Note (SRN)">Store Return Note (SRN)</option>
                  </select>
                </div>
                <label
                  style="font-weight: 700; font-size: 0.95rem; color: #000000; text-align: right; white-space: nowrap;">
                  Cancel Date
                </label>
                <div>
                  <input type="text" name="cancel_date" value="<?= date('d-M-Y') ?>" class="form-control"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; background: #ffffff; text-align: center; color: #1e293b;">
                </div>

                <!-- Row 2 -->
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                  Cancelation Document Number
                </label>
                <div>
                  <input type="text" name="cancel_doc_no" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; max-width: 340px; background: #ffffff; color: #1e293b;">
                </div>
                <label
                  style="font-weight: 700; font-size: 0.95rem; color: #000000; text-align: right; white-space: nowrap;">
                  Acc Date
                </label>
                <div>
                  <input type="text" name="acc_date" value="<?= date('d-M-Y') ?>" class="form-control"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 10px; font-size: 0.92rem; width: 100%; background: #ffffff; text-align: center; color: #1e293b;">
                </div>

                <!-- Row 3: Cancelation Reason -->
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; align-self: start; padding-top: 8px;">
                  Cancelation Reason
                </label>
                <div style="grid-column: span 3;">
                  <textarea name="cancel_reason" rows="3" class="form-control"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 8px 12px; font-size: 0.92rem; width: 100%; max-width: 680px; height: 75px; background: #ffffff; color: #1e293b; resize: vertical; font-family: inherit;"></textarea>
                </div>
              </div>

              <!-- Bottom Row: Document Profile & Document Number with SVG Search Icon -->
              <div
                style="display: flex; align-items: center; gap: 24px 32px; flex-wrap: wrap; margin-top: 36px; padding-top: 10px;">

                <!-- Document Profile -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                    Document<br>Profile
                  </label>
                  <select name="doc_profile" id="postedDocProfile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 190px; background: #ffffff; color: #1e293b;">
                    <option value="" selected>-Select DOC PF-</option>
                    <option value="TV-ISSUE">TV-ISSUE</option>
                    <option value="MAT-REQ">MAT-REQ</option>
                    <option value="ISSUE">ISSUE</option>
                  </select>
                </div>

                <!-- Document Number & Search Icon -->
                <div style="display: flex; align-items: center; gap: 14px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                    Document<br>Number
                  </label>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <select name="doc_number" id="postedDocNumber" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 220px; background: #ffffff; color: #1e293b;">
                      <option value="" selected>-Select DOC NO-</option>
                      <option value="GIN-2026-0001">GIN-2026-0001 (Posted - Mechanical Spares)</option>
                      <option value="GIN-2026-0002">GIN-2026-0002 (Posted - Turbine Oil)</option>
                      <option value="DMI-2026-0003">DMI-2026-0003 (Posted - Electrical)</option>
                      <option value="STI-2026-0004">STI-2026-0004 (Posted - Fasteners)</option>
                    </select>

                    <!-- Clickable Search Icon Button (Clean SVG, No Image File) -->
                    <button type="button" id="btnSearchPostedDoc" title="Search Document" aria-label="Search Document"
                      onclick="const sel = document.getElementById('postedDocNumber'); if(sel.value){ alert('Found posted document: ' + sel.value + '\nLedger Status: Posted & Reconciled'); } else { alert('Please select a Document Profile or Number to search.'); }"
                      style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #ffffff; border: 1px solid #b0b8c1; border-radius: 3px; cursor: pointer; color: #1e5a8a; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.08);"
                      onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#1e5a8a';"
                      onmouseout="this.style.background='#ffffff'; this.style.borderColor='#b0b8c1';">
                      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                      </svg>
                    </button>
                  </div>
                </div>

              </div>

              <!-- Bottom Action Buttons Matching Screenshot -->
              <div
                style="display: flex; justify-content: flex-end; align-items: center; gap: 14px; margin-top: 40px; max-width: 960px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Save
                </button>
                <button type="button" onclick="document.getElementById('issuePostedCancelForm').reset();"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.7: TRANSACTION -> ISSUE -> UNPOSTED CANCELLATION (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-issue-unposted-cancel" class="user-tab-pane">
          <div class="card"
            style="padding: 0; background: #ffffff; min-height: 440px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;">

            <form id="issueUnpostedCancelForm"
              onsubmit="event.preventDefault(); const no = this.querySelector('select[name=doc_number]').value; if(!no){ alert('Please select a Document Number to cancel.'); return; } if(confirm('Are you sure you want to cancel unposted document ' + no + '?')) { alert('Unposted document [' + no + '] cancelled successfully and reserved stock released.'); this.reset(); }">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- Fields Area -->
              <div style="padding: 36px 40px;">
                <div style="display: flex; align-items: center; gap: 24px 32px; flex-wrap: wrap;">

                  <!-- Document Profile -->
                  <div style="display: flex; align-items: center; gap: 14px;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                      Document Profile
                    </label>
                    <select name="doc_profile" id="unpostedDocProfile" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 190px; background: #ffffff; color: #1e293b;">
                      <option value="" selected>-Select DOC PF-</option>
                      <option value="TV-ISSUE">TV-ISSUE</option>
                      <option value="MAT-REQ">MAT-REQ</option>
                      <option value="ISSUE">ISSUE</option>
                    </select>
                  </div>

                  <!-- Document Number & Search Icon -->
                  <div style="display: flex; align-items: center; gap: 14px;">
                    <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                      Document Number
                    </label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                      <select name="doc_number" id="unpostedDocNumber" class="form-select"
                        style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 5px 12px; font-size: 0.92rem; min-width: 220px; background: #ffffff; color: #1e293b;">
                        <option value="" selected>-Select DOC NO-</option>
                        <option value="GIN-2026-0041">GIN-2026-0041 (Draft - Mechanical)</option>
                        <option value="GIN-2026-0042">GIN-2026-0042 (Draft - Electrical)</option>
                        <option value="DMI-2026-0018">DMI-2026-0018 (Draft - Spares)</option>
                        <option value="STI-2026-0009">STI-2026-0009 (Draft - Turbine Oil)</option>
                      </select>

                      <!-- Clickable Search Icon Button (Clean SVG, No Image File) -->
                      <button type="button" id="btnSearchUnpostedDoc" title="Search Document" aria-label="Search Document"
                        onclick="const sel = document.getElementById('unpostedDocNumber'); if(sel.value){ alert('Found document: ' + sel.value + '\nStatus: Unposted Draft\nReserved Quantity: Active'); } else { alert('Please select a Document Profile or Number to search.'); }"
                        style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background: #ffffff; border: 1px solid #b0b8c1; border-radius: 3px; cursor: pointer; color: #1e5a8a; transition: all 0.15s ease; box-shadow: 0 1px 2px rgba(0,0,0,0.08);"
                        onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#1e5a8a';"
                        onmouseout="this.style.background='#ffffff'; this.style.borderColor='#b0b8c1';">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                          stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                          <circle cx="11" cy="11" r="8"></circle>
                          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                      </button>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Bottom Grey Action Bar Matching Screenshot -->
              <div
                style="background: #a3a3a3; padding: 12px 28px; display: flex; justify-content: flex-end; align-items: center; gap: 14px; border-top: 1px solid #737373;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Save
                </button>
                <button type="button" onclick="document.getElementById('issueUnpostedCancelForm').reset();"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Clear
                </button>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.8: TRANSACTION -> POSTING (MATCHING USER SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-posting" class="user-tab-pane">
          <div class="card"
            style="padding: 36px 44px; background: #ffffff; min-height: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">

            <form id="legacyPostingForm"
              onsubmit="event.preventDefault(); const p = this.querySelector('select[name=doc_profile]').value; if(!p){ alert('Please select a Document Profile first.'); return; } alert('Documents for profile [' + p + '] posted successfully to the ledger.');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <div
                style="display: grid; grid-template-columns: 220px minmax(320px, 620px); align-items: center; gap: 16px 24px; margin-bottom: 36px;">
                <label style="font-weight: 700; font-size: 1.05rem; color: #000000; letter-spacing: -0.01em;">
                  Document Profile
                </label>
                <div>
                  <select name="doc_profile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 6px 12px; font-size: 0.95rem; width: 100%; background: #ffffff; color: #1e293b;"
                    required>
                    <option value="" selected>-Select Document Profile-</option>
                    <option value="TV-ISSUE">TV-ISSUE</option>
                    <option value="MAT-REQ">MAT-REQ</option>
                    <option value="ISSUE">ISSUE</option>
                  </select>
                </div>
              </div>

              <div style="display: flex; justify-content: flex-end; max-width: 864px;">
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 6px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.22); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Post
                </button>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.1: PHYSICAL VERIFICATION -> FREEZE BATCHES
         ===================================================================== -->
        <section id="tab-user-pv-freeze" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.2: PHYSICAL VERIFICATION -> GENERATE SHEET
         ===================================================================== -->
        <section id="tab-user-pv-generate-sheet" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.3: PHYSICAL VERIFICATION -> VERIFICATION ENTRY
         ===================================================================== -->
        <section id="tab-user-pv-entry" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.4: PHYSICAL VERIFICATION -> VERIFICATION EDIT
         ===================================================================== -->
        <section id="tab-user-pv-edit" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.5: PHYSICAL VERIFICATION -> VERIFICATION VALIDATION
         ===================================================================== -->
        <section id="tab-user-pv-validation" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between;">

            <div>
              <h4 style="font-size: 1.05rem; font-weight: 700; color: #000000; margin: 0 0 16px 0; font-family: inherit;">
                Following Documents are pending for your validation
              </h4>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: flex-end; padding-top: 40px;">
              <button type="button" onclick="alert('No pending documents selected for validation.');"
                style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 8px 44px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                Validate
              </button>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.6: PHYSICAL VERIFICATION -> VERIFICATION APPROVAL
         ===================================================================== -->
        <section id="tab-user-pv-approval" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.7a: PHYSICAL VERIFICATION -> PHV REPORTS -> AVG CONSUMPTION
         ===================================================================== -->
        <section id="tab-user-pv-report-avg-consumption" class="user-tab-pane">
          <div
            style="background: #dbeafe; min-height: 480px; padding: 24px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #bfdbfe;">

            <form id="pvAvgConsumptionForm"
              onsubmit="event.preventDefault(); alert('Generating Average Consumption Report...');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <div style="text-align: center; margin-bottom: 24px;">
                <h3
                  style="font-size: 1.15rem; font-weight: 700; color: #000000; font-family: 'Times New Roman', serif; margin: 0;">
                  Average Consumption Report
                </h3>
              </div>

              <div style="display: flex; flex-direction: column; gap: 8px; max-width: 720px; margin-bottom: 24px;">

                <!-- Cost Center -->
                <div style="display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 12px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Cost Center
                  </label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="" selected>-Select Department-</option>
                    <option value="Mechanical">Mechanical</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Civil">Civil</option>
                    <option value="Operations">Operations</option>
                    <option value="Administration">Administration</option>
                  </select>
                </div>

                <!-- Warehouse -->
                <div style="display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 12px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    Warehouse
                  </label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="" selected></option>
                    <option value="Central Main Store">Central Main Store</option>
                    <option value="Mechanical Spares Warehouse">Mechanical Spares Warehouse</option>
                    <option value="Electrical Spares Warehouse">Electrical Spares Warehouse</option>
                    <option value="Turbine & Heavy Equipment Yard">Turbine & Heavy Equipment Yard</option>
                    <option value="Fuel & Lubricant Depot">Fuel & Lubricant Depot</option>
                  </select>
                </div>

                <!-- From -->
                <div style="display: grid; grid-template-columns: 120px 240px 1fr; align-items: center; gap: 12px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    From
                  </label>
                  <input type="text" name="from_date" class="form-control"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                  <span
                    style="font-weight: 700; font-size: 0.95rem; color: #000000; font-family: 'Times New Roman', serif;">
                    Dateformat = YYYY-MM-DD
                  </span>
                </div>

                <!-- To -->
                <div style="display: grid; grid-template-columns: 120px 240px 1fr; align-items: center; gap: 12px;">
                  <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">
                    To
                  </label>
                  <input type="text" name="to_date" class="form-control"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                  <span
                    style="font-weight: 700; font-size: 0.95rem; color: #000000; font-family: 'Times New Roman', serif;">
                    Dateformat = YYYY-MM-DD
                  </span>
                </div>

              </div>

              <!-- Button -->
              <div>
                <button type="submit"
                  style="background: #e2e8f0; border: 1px solid #737373; border-radius: 2px; padding: 4px 14px; font-size: 0.9rem; color: #000000; cursor: pointer; box-shadow: 1px 1px 2px rgba(0,0,0,0.15);">
                  Generate Report
                </button>
              </div>

            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.7b: PHYSICAL VERIFICATION -> PHV REPORTS -> FINAL REPORT (CLEAN WHITE PAGE)
         ===================================================================== -->
        <section id="tab-user-pv-report-final" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.7c: PHYSICAL VERIFICATION -> PHV REPORTS -> OTHER REPORTS
         ===================================================================== -->
        <!-- =====================================================================
         PANE 2.9.7c: PHYSICAL VERIFICATION -> PHV REPORTS -> OTHER REPORTS
         ===================================================================== -->
        <section id="tab-user-pv-report-other" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

            <form id="pvOtherReportsForm"
              onsubmit="event.preventDefault(); const wh = this.querySelector('select[name=warehouse]').value; if(!wh){ alert('Please select a Warehouse.'); return; } alert('Generating other reports for ' + wh + '...');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <div style="display: flex; align-items: center; gap: 24px; max-width: 900px; margin-top: 10px;">
                <label style="font-weight: 700; font-size: 1.05rem; color: #000000; white-space: nowrap;">
                  Selecet Warehouse
                </label>
                <div style="flex-grow: 1; max-width: 580px;">
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 10px; font-size: 0.92rem; width: 100%; background: #ffffff; color: #1e293b;">
                    <option value="" selected>---- Select Warehouse----</option>
                    <option value="Central Main Store">Central Main Store</option>
                    <option value="Mechanical Spares Warehouse">Mechanical Spares Warehouse</option>
                    <option value="Electrical Spares Warehouse">Electrical Spares Warehouse</option>
                    <option value="Turbine & Heavy Equipment Yard">Turbine & Heavy Equipment Yard</option>
                    <option value="Fuel & Lubricant Depot">Fuel & Lubricant Depot</option>
                  </select>
                </div>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.7d: PHYSICAL VERIFICATION -> PHV REPORTS -> PHV SHEET SUMMARY
         ===================================================================== -->
        <!-- =====================================================================
         PANE 2.9.7d: PHYSICAL VERIFICATION -> PHV REPORTS -> PHV SHEET SUMMARY
         ===================================================================== -->
        <section id="tab-user-pv-report-sheet-summary" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

            <form id="pvSheetSummaryForm"
              onsubmit="event.preventDefault(); document.getElementById('pvSheetSummaryMsg').style.display='block';">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <!-- Bordered Options Box matching Screenshot -->
              <div
                style="border: 1px solid #000000; padding: 14px 20px; max-width: 760px; margin-bottom: 20px; display: flex; flex-direction: column; gap: 12px; background: #ffffff;">

                <!-- Row 1: Select Cost Center -->
                <div style="display: grid; grid-template-columns: 170px 1fr; align-items: center; gap: 14px;">
                  <label
                    style="font-weight: 700; font-size: 1.05rem; color: #000000; font-family: 'Times New Roman', serif; white-space: nowrap;">
                    Select Cost Center
                  </label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; color: #1e293b; width: 100%;">
                    <option value="" selected>Select Department</option>
                    <option value="Mechanical">Mechanical</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Civil">Civil</option>
                    <option value="Operations">Operations</option>
                    <option value="Administration">Administration</option>
                  </select>
                </div>

                <!-- Row 2: Select Warehouse -->
                <div style="display: grid; grid-template-columns: 170px 1fr; align-items: center; gap: 14px;">
                  <label
                    style="font-weight: 700; font-size: 1.05rem; color: #000000; font-family: 'Times New Roman', serif; white-space: nowrap;">
                    Select Warehouse
                  </label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; color: #1e293b; width: 100%;">
                    <option value="" selected></option>
                    <option value="Central Main Store">Central Main Store</option>
                    <option value="Mechanical Spares Warehouse">Mechanical Spares Warehouse</option>
                    <option value="Electrical Spares Warehouse">Electrical Spares Warehouse</option>
                    <option value="Turbine & Heavy Equipment Yard">Turbine & Heavy Equipment Yard</option>
                    <option value="Fuel & Lubricant Depot">Fuel & Lubricant Depot</option>
                  </select>
                </div>

                <!-- Row 3: Select PHV Year -->
                <div style="display: grid; grid-template-columns: 170px 1fr; align-items: center; gap: 14px;">
                  <label
                    style="font-weight: 700; font-size: 1.05rem; color: #000000; font-family: 'Times New Roman', serif; white-space: nowrap;">
                    Select PHV Year
                  </label>
                  <select name="phv_year" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; color: #1e293b; width: 100%;">
                    <option value="2021" selected>2021</option>
                    <option value="2022">2022</option>
                    <option value="2023">2023</option>
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026">2026</option>
                  </select>
                </div>

              </div>

              <!-- Action Button -->
              <div style="margin-bottom: 28px;">
                <button type="submit"
                  style="background: #ffffff; border: 1px solid #000000; padding: 3px 12px; font-size: 0.92rem; color: #000000; cursor: pointer; border-radius: 2px; box-shadow: 1px 1px 2px rgba(0,0,0,0.15);">
                  Create Report
                </button>
              </div>

              <!-- Message matching Screenshot -->
              <div id="pvSheetSummaryMsg" style="margin-top: 24px; padding-left: 20px;">
                <p style="font-size: 0.95rem; color: #000000; font-family: inherit; margin: 0;">
                  A data source instance has not been supplied for the data source 'ShtSmry'.
                </p>
              </div>

            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.7e: PHYSICAL VERIFICATION -> PHV REPORTS -> AV 7 SUMMARY
         ===================================================================== -->
        <section id="tab-user-pv-report-av7-summary" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

            <form id="pvAv7ReportForm"
              onsubmit="event.preventDefault(); document.getElementById('pvAv7SummaryMsg').style.display='block';">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <div style="margin-bottom: 24px; padding-left: 120px;">
                <h3
                  style="font-size: 1.15rem; font-weight: 700; color: #000000; font-family: 'Times New Roman', serif; margin: 0;">
                  PHV AV7 Report
                </h3>
              </div>

              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 680px; margin-bottom: 24px;">

                <!-- Row 1: Cost Center -->
                <div style="display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 12px;">
                  <label
                    style="font-weight: 700; font-size: 1.05rem; color: #000000; font-family: 'Times New Roman', serif; white-space: nowrap;">
                    Cost Center
                  </label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; color: #1e293b; width: 100%;">
                    <option value="" selected>-Select Department-</option>
                    <option value="Mechanical">Mechanical</option>
                    <option value="Electrical">Electrical</option>
                    <option value="Civil">Civil</option>
                    <option value="Operations">Operations</option>
                    <option value="Administration">Administration</option>
                  </select>
                </div>

                <!-- Row 2: Year -->
                <div style="display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 12px;">
                  <label
                    style="font-weight: 700; font-size: 1.05rem; color: #000000; font-family: 'Times New Roman', serif; white-space: nowrap;">
                    Year
                  </label>
                  <select name="year" class="form-select"
                    style="border: 1px solid #737373; border-radius: 2px; padding: 3px 8px; font-size: 0.9rem; background: #ffffff; color: #1e293b; width: 100%;">
                    <option value="2021">2021</option>
                    <option value="2022">2022</option>
                    <option value="2023" selected>2023</option>
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026">2026</option>
                  </select>
                </div>

              </div>

              <!-- Button -->
              <div style="padding-left: 120px; margin-bottom: 28px;">
                <button type="submit"
                  style="background: #ffffff; border: 1px solid #000000; padding: 4px 14px; font-size: 0.92rem; color: #000000; cursor: pointer; border-radius: 2px; box-shadow: 1px 1px 2px rgba(0,0,0,0.15);">
                  Generate Report
                </button>
              </div>

              <!-- Message matching Screenshot -->
              <div id="pvAv7SummaryMsg" style="margin-top: 24px; padding-left: 20px;">
                <p style="font-size: 0.95rem; color: #000000; font-family: inherit; margin: 0;">
                  A data source instance has not been supplied for the data source 'OBS_DT'.
                </p>
              </div>

            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.8: PHYSICAL VERIFICATION -> VERIFICATION POSTING
         ===================================================================== -->
        <section id="tab-user-pv-posting" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: flex-end;">

            <div style="display: flex; justify-content: flex-end; align-items: flex-end;">
              <button type="button" onclick="alert('No pending physical verification records selected for posting.');"
                style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 8px 44px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                Post
              </button>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.9: PHYSICAL VERIFICATION -> VERIFICATION GL POSTING
         ===================================================================== -->
        <!-- =====================================================================
         PANE 2.9.9: PHYSICAL VERIFICATION -> VERIFICATION GL POSTING
         ===================================================================== -->
        <section id="tab-user-pv-gl-posting" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 480px; padding: 28px 36px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">

            <form id="pvGlPostingForm"
              onsubmit="event.preventDefault(); const wh = this.querySelector('select[name=warehouse]').value; if(!wh){ alert('Please select a Warehouse.'); return; } alert('Details fetched for ' + wh + '.');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <h4 style="font-size: 1.05rem; font-weight: 700; color: #000000; margin: 0 0 24px 0; font-family: inherit;">
                Physical Verification - Tranfer the values to GL
              </h4>

              <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 24px;">
                <label style="font-weight: 700; font-size: 0.95rem; color: #000000; white-space: nowrap;">
                  Warehouse :
                </label>
                <select name="warehouse" class="form-select"
                  style="border: 1px solid #737373; border-radius: 2px; padding: 4px 10px; font-size: 0.92rem; width: 100%; max-width: 480px; background: #ffffff; color: #1e293b;">
                  <option value="" selected>Select Warehouse</option>
                  <option value="Central Main Store">Central Main Store</option>
                  <option value="Mechanical Spares Warehouse">Mechanical Spares Warehouse</option>
                  <option value="Electrical Spares Warehouse">Electrical Spares Warehouse</option>
                  <option value="Turbine & Heavy Equipment Yard">Turbine & Heavy Equipment Yard</option>
                  <option value="Fuel & Lubricant Depot">Fuel & Lubricant Depot</option>
                </select>
              </div>

              <div>
                <button type="submit"
                  style="background: linear-gradient(180deg, #9bb2cf 0%, #758ea8 100%); border: 1px solid #5a728b; color: #ffffff; font-weight: 700; font-size: 0.95rem; padding: 8px 24px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.25); cursor: pointer; text-shadow: 0 1px 1px rgba(0,0,0,0.25);">
                  Get Details
                </button>
              </div>
            </form>

          </div>
        </section>

        <!-- =====================================================================
         PANE 2.9.10: PHYSICAL VERIFICATION -> UNFREEZE BATCHES
         ===================================================================== -->
        <section id="tab-user-pv-unfreeze" class="user-tab-pane">
          <div
            style="background: #ffffff; min-height: 520px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
          </div>
        </section>



        <!-- =====================================================================
         PANE 3.1: CASH BOOK -> PAYSLIP CANCELATION (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-payslip-cancellation" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 540px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">

            <form id="payslipCancellationLegacyForm"
              onsubmit="event.preventDefault(); App.toast('Payslip cancellation requisition submitted for audit authorization.', 'success');">
              <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

              <div style="display: flex; flex-direction: column; gap: 12px; max-width: 960px;">

                <!-- Row 1: Document Profile & Description -->
                <div style="display: grid; grid-template-columns: 160px 200px 1fr; align-items: center; gap: 12px;">
                  <label style="font-size: 0.95rem; color: #000000;">Document Profile</label>
                  <select name="doc_profile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">-Select DOC PF-</option>
                    <option value="PAY_CAN">PAY_CAN - Payslip Cancellation</option>
                    <option value="SAL_VOID">SAL_VOID - Salary Voucher Void</option>
                    <option value="EPF_REV">EPF_REV - EPF Reversal</option>
                  </select>
                  <input type="text" name="doc_profile_desc" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                </div>

                <!-- Row 2: Document Number -->
                <div style="display: grid; grid-template-columns: 160px 200px; align-items: center; gap: 12px;">
                  <label style="font-size: 0.95rem; color: #000000;">Document Number</label>
                  <input type="text" name="doc_number" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                </div>

                <!-- Row 3: Source Doc Profile & Document Date -->
                <div
                  style="display: grid; grid-template-columns: 160px 200px 1fr 140px 110px; align-items: center; gap: 12px;">
                  <label style="font-size: 0.95rem; color: #000000;">Source Doc Profile</label>
                  <select name="src_doc_profile" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">-Select Src DOC PF-</option>
                    <option value="PAY_VOUCHER">PAY_VOUCHER</option>
                    <option value="SAL_DISBURSE">SAL_DISBURSE</option>
                  </select>
                  <div></div>
                  <label style="font-size: 0.95rem; color: #000000; text-align: right;">Document Date</label>
                  <input type="text" name="doc_date" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                </div>

                <!-- Row 4: Source Doc Number & Accounting Date -->
                <div
                  style="display: grid; grid-template-columns: 160px 200px 1fr 140px 110px; align-items: center; gap: 12px;">
                  <label style="font-size: 0.95rem; color: #000000;">Source Doc Number</label>
                  <select name="src_doc_number" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">-Select Src DOC NO-</option>
                    <option value="DOC-2026-001">DOC-2026-001</option>
                    <option value="DOC-2026-002">DOC-2026-002</option>
                    <option value="DOC-2026-003">DOC-2026-003</option>
                  </select>
                  <div></div>
                  <label style="font-size: 0.95rem; color: #000000; text-align: right;">Accounting Date</label>
                  <input type="text" name="accounting_date" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                </div>

                <!-- Row 5: Cancellation Reason -->
                <div
                  style="display: grid; grid-template-columns: 160px 1fr; align-items: flex-start; gap: 12px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; color: #000000;">Cancellation Reason</label>
                  <textarea name="cancellation_reason" class="form-control" rows="3"
                    style="width: 100%; max-width: 720px; height: 68px; border: 1px solid #71717a; border-radius: 2px; padding: 6px 8px; font-size: 0.9rem; font-family: inherit; background: #ffffff; resize: both;"></textarea>
                </div>

                <!-- Row 6: Non Taxable Amount & Total Payable to Supplier -->
                <div
                  style="display: grid; grid-template-columns: 160px 200px 1fr 180px 160px; align-items: center; gap: 12px;">
                  <label style="font-size: 0.95rem; color: #000000;">Non Taxable Amount</label>
                  <input type="text" name="non_taxable_amount" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                  <div></div>
                  <label style="font-size: 0.95rem; color: #000000; text-align: right;">Total Payable to Supplier</label>
                  <input type="text" name="total_payable" class="form-control" placeholder=""
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                </div>

                <!-- Expand to View Payee Details Trigger -->
                <div style="display: flex; align-items: center; gap: 6px; margin-top: 8px; cursor: pointer;"
                  onclick="const p = document.getElementById('payeeDetailsPanel'); const b = document.getElementById('payeeToggleBtn'); if(p.style.display==='none'||!p.style.display){ p.style.display='block'; b.textContent='−'; } else { p.style.display='none'; b.textContent='+'; }">
                  <span
                    style="font-size: 0.95rem; font-family: 'Segoe UI', Arial, sans-serif; color: #000000; font-weight: 500;">Exapand
                    to View Payee Details</span>
                  <button type="button" id="payeeToggleBtn"
                    style="background: #2b5797; color: #ffffff; border: 1px solid #1e395b; width: 16px; height: 16px; font-size: 13px; font-weight: bold; line-height: 12px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; border-radius: 2px; padding: 0;">
                    +
                  </button>
                </div>

                <!-- Blue Expandable Payee Details Panel -->
                <div id="payeeDetailsPanel"
                  style="display: none; background: #9dc3f8; padding: 14px 20px; border-radius: 2px; margin-top: 4px; max-width: 920px;">
                  <div style="display: flex; flex-direction: column; gap: 10px;">

                    <!-- Payee Name -->
                    <div style="display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 12px;">
                      <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Payee Name</label>
                      <input type="text" name="payee_name" class="form-control"
                        style="width: 100%; max-width: 480px; height: 24px; border: 1px solid #71717a; border-radius: 2px; padding: 2px 6px; font-size: 0.9rem; background: #ffffff;">
                    </div>

                    <!-- Payee Address -->
                    <div style="display: grid; grid-template-columns: 140px 1fr; align-items: flex-start; gap: 12px;">
                      <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Payee Address</label>
                      <textarea name="payee_address" class="form-control" rows="2"
                        style="width: 100%; max-width: 480px; height: 55px; border: 1px solid #71717a; border-radius: 2px; padding: 4px 6px; font-size: 0.9rem; font-family: inherit; background: #ffffff; resize: both;"></textarea>
                    </div>

                  </div>
                </div>

                <!-- Project Costing & Cash Book Payment Details Section Titles -->
                <div style="margin-top: 14px; display: flex; flex-direction: column; gap: 4px;">
                  <div
                    style="font-size: 0.98rem; font-weight: 700; color: #000000; font-family: 'Segoe UI', Arial, sans-serif; cursor: pointer;">
                    Project Costing Details
                  </div>
                  <div
                    style="font-size: 0.98rem; font-weight: 700; color: #000000; font-family: 'Segoe UI', Arial, sans-serif; cursor: pointer;">
                    Cash Book Payment Details
                  </div>
                </div>

                <!-- Action Buttons -->
                <div style="margin-top: 18px; display: flex; gap: 10px;">
                  <button type="submit"
                    style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                    Submit Cancellation
                  </button>
                  <button type="reset" class="btn btn-secondary btn-sm"
                    onclick="document.getElementById('payeeDetailsPanel').style.display='none'; document.getElementById('payeeToggleBtn').textContent='+';">
                    Reset Form
                  </button>
                </div>

              </div>
            </form>

            <!-- Existing Register / History Ledger (Collapsible / Bottom Audit View) -->
            <div style="margin-top: 36px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
              <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin-bottom: 12px;">Payslip Cancellation
                Register &amp; Audit Log</h4>
              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Cancellation Ref</th>
                      <th>Payslip Voucher No</th>
                      <th>Employee / EPF</th>
                      <th>Pay Period</th>
                      <th style="text-align: right;">Amount (LKR)</th>
                      <th>Reason</th>
                      <th>Status</th>
                      <th style="text-align: right;">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($payslipCancellations)): ?>
                      <tr>
                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No payslip cancellation requests recorded in the system.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($payslipCancellations as $p): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($p['cancellation_ref']) ?></code></td>
                          <td><strong><?= htmlspecialchars($p['payslip_no']) ?></strong></td>
                          <td>
                            <div><?= htmlspecialchars($p['employee_name']) ?></div>
                            <small
                              style="color: #64748b; font-size: 0.75rem;"><?= htmlspecialchars($p['epf_no'] ?? '-') ?></small>
                          </td>
                          <td><?= htmlspecialchars($p['pay_period']) ?></td>
                          <td style="text-align: right; font-weight: 700; color: #650b2b;">
                            Rs. <?= number_format($p['amount'], 2) ?>
                          </td>
                          <td><span style="font-size: 0.82rem; color: #475569;"><?= htmlspecialchars($p['reason']) ?></span>
                          </td>
                          <td>
                            <span
                              class="badge <?= $p['status'] === 'Approved & Voided' ? 'badge-success' : ($p['status'] === 'Rejected' ? 'badge-danger' : 'badge-warning') ?>">
                              <?= htmlspecialchars($p['status']) ?>
                            </span>
                          </td>
                          <td style="text-align: right;">
                            <button type="button" class="btn btn-secondary btn-sm"
                              onclick="App.toast('Viewing details for <?= htmlspecialchars($p['cancellation_ref']) ?>', 'info')">
                              View
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>



        <!-- =====================================================================
         PANE 4.1: REPORTS -> QUANTITY ON HAND -> BATCH WISE REPORT (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-qoh-batch" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 520px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="qoh-batch-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.qoh-batch-results'); const msg = this.closest('.card').querySelector('.qoh-batch-status-msg'); res.style.display = 'block'; msg.style.display = 'none';">
              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 860px;">

                <!-- Row 1: Cost Center -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(320px, 580px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000; line-height: 1.2;">
                    Cost<br>Center :
                  </label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Department</option>
                    <?php if (!empty($departments)): ?>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="Turbine Maintenance">Turbine Maintenance</option>
                      <option value="Electrical Engineering">Electrical Engineering</option>
                      <option value="Hydraulic Operations">Hydraulic Operations</option>
                      <option value="Mechanical Workshop">Mechanical Workshop</option>
                    <?php endif; ?>
                  </select>
                </div>

                <!-- Row 2: Warehouse -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(320px, 580px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Warehouse</label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value=""></option>
                    <option value="Central Warehouse - Bay A">Central Warehouse - Bay A</option>
                    <option value="Spares Depot - Bay B">Spares Depot - Bay B</option>
                    <option value="Fuel &amp; Lubricants Depot">Fuel &amp; Lubricants Depot</option>
                    <option value="Heavy Electrical Bay">Heavy Electrical Bay</option>
                  </select>
                </div>

                <!-- Row 3: Material Name + All Materials Checkbox -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(320px, 580px) auto; align-items: center; gap: 10px; margin-top: 2px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000; line-height: 1.2;">
                    Material<br>Name :
                  </label>
                  <select id="qoh_batch_mat_select" name="material_name" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value=""></option>
                    <?php foreach ($materialAggregates as $m): ?>
                      <option value="<?= htmlspecialchars($m['sku']) ?>"><?= htmlspecialchars($m['item_name']) ?>
                        (<?= htmlspecialchars($m['sku']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <div style="display: flex; align-items: center; gap: 6px; padding-left: 6px;">
                    <input type="checkbox" id="qoh_batch_all_mat"
                      onchange="const sel = document.getElementById('qoh_batch_mat_select'); if(this.checked){ sel.value = ''; sel.disabled = true; } else { sel.disabled = false; }"
                      style="cursor: pointer; width: 15px; height: 15px;">
                    <label for="qoh_batch_all_mat"
                      style="font-size: 0.95rem; color: #000000; font-weight: 500; cursor: pointer;">All Materials</label>
                  </div>
                </div>

                <!-- Row 4: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 10px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Bold Grade Code Note -->
            <div
              style="margin-top: 18px; margin-bottom: 24px; font-size: 1.02rem; font-weight: 700; color: #000000; font-family: 'Segoe UI', Arial, sans-serif;">
              Please Note following report contains all the grade code values (NEW,DMG,OBS,UNS)
            </div>

            <!-- Initial Status Message -->
            <div class="qoh-batch-status-msg" style="font-size: 0.92rem; color: #000000; font-family: inherit;">
              A data source instance has not been supplied for the data source 'QTY_BATCH_WISE_DS'.
            </div>

            <!-- Generated Table Container -->
            <div class="qoh-batch-results"
              style="display: none; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Batch Wise Stock Balances &amp; Grade
                  Classifications</h4>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print / Export</button>
              </div>
              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Batch Number</th>
                      <th>SKU</th>
                      <th>Material Description</th>
                      <th>Grade Code</th>
                      <th>Received Date</th>
                      <th>Bin / Location</th>
                      <th style="text-align: right;">Unit Cost (LKR)</th>
                      <th style="text-align: right;">Remaining Qty</th>
                      <th style="text-align: right;">Total Valuation (LKR)</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($fifoBatches)): ?>
                      <tr>
                        <td colspan="10"
                          style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No inventory batches registered in the database.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($fifoBatches as $b): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($b['batch_number']) ?></code></td>
                          <td><strong><?= htmlspecialchars($b['sku']) ?></strong></td>
                          <td><?= htmlspecialchars($b['item_name']) ?></td>
                          <td><span class="badge badge-primary">NEW</span></td>
                          <td><?= htmlspecialchars($b['received_date']) ?></td>
                          <td><?= htmlspecialchars($b['location_bin'] ?? 'Zone A-01') ?></td>
                          <td style="text-align: right;">Rs. <?= number_format($b['unit_cost'], 2) ?></td>
                          <td style="text-align: right; font-weight: 700; color: #0f172a;">
                            <?= (int) $b['remaining_quantity'] ?>
                          </td>
                          <td style="text-align: right; font-weight: 700; color: #10b981;">
                            Rs. <?= number_format((int) $b['remaining_quantity'] * (float) $b['unit_cost'], 2) ?>
                          </td>
                          <td>
                            <span class="badge <?= $b['remaining_quantity'] > 0 ? 'badge-success' : 'badge-secondary' ?>">
                              <?= $b['remaining_quantity'] > 0 ? 'Active Stock' : 'Depleted' ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.2: REPORTS -> QUANTITY ON HAND -> MATERIAL WISE REPORT (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-qoh-material" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 520px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="qoh-material-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.matflow-results-container'); res.style.display = 'block';">
              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 860px;">

                <!-- Row 1: Cost Center -->
                <div
                  style="display: grid; grid-template-columns: 155px minmax(320px, 580px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000; white-space: nowrap;">Cost Center
                    :</label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Department</option>
                    <?php if (!empty($departments)): ?>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="Turbine Maintenance">Turbine Maintenance</option>
                      <option value="Electrical Engineering">Electrical Engineering</option>
                      <option value="Hydraulic Operations">Hydraulic Operations</option>
                      <option value="Mechanical Workshop">Mechanical Workshop</option>
                    <?php endif; ?>
                  </select>
                </div>

                <!-- Row 2: Warehouse -->
                <div
                  style="display: grid; grid-template-columns: 155px minmax(320px, 580px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000; white-space: nowrap;">Warehouse
                    :</label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value=""></option>
                    <option value="Central Warehouse - Bay A">Central Warehouse - Bay A</option>
                    <option value="Spares Depot - Bay B">Spares Depot - Bay B</option>
                    <option value="Fuel &amp; Lubricants Depot">Fuel &amp; Lubricants Depot</option>
                    <option value="Heavy Electrical Bay">Heavy Electrical Bay</option>
                  </select>
                </div>

                <!-- Row 3: Material Name + All Checkbox -->
                <div
                  style="display: grid; grid-template-columns: 155px minmax(320px, 580px) auto; align-items: center; gap: 10px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000; white-space: nowrap;">
                    Material Name :
                  </label>
                  <select id="qoh_material_select" name="material_name" class="form-select"
                    onchange="const val = this.value; if(!val) return; const txt = document.getElementById('qoh_mat_textarea'); const current = txt.value.trim(); if(current){ const list = current.split(',').map(s=>s.trim()); if(!list.includes(val)){ txt.value = current + ', ' + val; } } else { txt.value = val; }"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value=""></option>
                    <?php foreach ($materialAggregates as $m): ?>
                      <option value="<?= htmlspecialchars($m['sku']) ?>"><?= htmlspecialchars($m['item_name']) ?>
                        (<?= htmlspecialchars($m['sku']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <div style="display: flex; align-items: center; gap: 6px; padding-left: 6px;">
                    <input type="checkbox" id="qoh_mat_all_chk"
                      onchange="const txt = document.getElementById('qoh_mat_textarea'); if(this.checked){ const allSkus = Array.from(document.querySelectorAll('#qoh_material_select option')).map(o=>o.value).filter(Boolean).join(', '); txt.value = allSkus; } else { txt.value = ''; }"
                      style="cursor: pointer; width: 15px; height: 15px;">
                    <label for="qoh_mat_all_chk"
                      style="font-size: 0.95rem; color: #000000; font-weight: 500; cursor: pointer;">All</label>
                  </div>
                </div>

                <!-- Instructions Notice -->
                <div
                  style="margin-left: 155px; font-size: 0.92rem; line-height: 1.45; color: #000000; margin-top: 4px; margin-bottom: 8px; max-width: 680px;">
                  If you want to select <strong>multiple items</strong>, select them from the above dropdown list, when
                  you select an Item it <strong>will appear in the below text box</strong>. after complete the selection
                  click Generate Report button to generate the report. <strong style="color: #dc2626;">DO NOT TYPE THE
                    MATERIAL CODES</strong> in below text box, use the dropdown list to add materials
                  <br><br>
                  if any case report is not generated, clear the text box and select the items again
                </div>

                <!-- Row 4: Selected Materials Textarea -->
                <div
                  style="display: grid; grid-template-columns: 155px minmax(320px, 580px); align-items: flex-start; gap: 10px;">
                  <label
                    style="font-size: 0.95rem; font-weight: 500; color: #000000; white-space: nowrap; padding-top: 4px;">
                    Selected Materials :
                  </label>
                  <textarea name="selected_materials" id="qoh_mat_textarea" rows="2"
                    style="width: 100%; height: 55px; border: 1px solid #71717a; border-radius: 2px; padding: 6px 8px; font-size: 0.9rem; font-family: inherit; background: #ffffff; resize: both;"></textarea>
                </div>

                <!-- Row 5: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 155px 1fr; align-items: center; gap: 10px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Generated Table Container -->
            <div class="matflow-results-container"
              style="display: none; margin-top: 36px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Material Wise Inventory Balances</h4>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print / Export</button>
              </div>
              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Material SKU</th>
                      <th>Material Name</th>
                      <th>Category</th>
                      <th style="text-align: right;">Active Batches</th>
                      <th style="text-align: right;">Total On Hand</th>
                      <th style="text-align: right;">Total Valuation (LKR)</th>
                      <th>Storage Bins</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($materialAggregates)): ?>
                      <tr>
                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No material records available in warehouse inventory.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($materialAggregates as $mat): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($mat['sku']) ?></code></td>
                          <td><strong><?= htmlspecialchars($mat['item_name']) ?></strong></td>
                          <td><?= htmlspecialchars($mat['category']) ?></td>
                          <td style="text-align: right;"><?= $mat['batch_count'] ?></td>
                          <td style="text-align: right; font-weight: 700; color: #0f172a;"><?= $mat['total_remaining_qty'] ?>
                            Units</td>
                          <td style="text-align: right; font-weight: 700; color: #10b981;">Rs.
                            <?= number_format($mat['total_value'], 2) ?>
                          </td>
                          <td><?= htmlspecialchars(implode(', ', $mat['bins']) ?: 'Zone A-01') ?></td>
                          <td><span
                              class="badge <?= $mat['total_remaining_qty'] > 0 ? 'badge-success' : 'badge-danger' ?>"><?= $mat['total_remaining_qty'] > 0 ? 'In Stock' : 'Out of Stock' ?></span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.3: REPORTS -> MONTHLY TRANSFERS (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-monthly-transfers" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 500px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="monthly-transfers-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.monthly-transfers-results'); res.style.display = 'block';">
              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 840px;">

                <!-- Row 1: Cost Center -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(220px, 260px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Cost Center :</label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Department</option>
                    <?php if (!empty($departments)): ?>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="Turbine Maintenance">Turbine Maintenance</option>
                      <option value="Electrical Engineering">Electrical Engineering</option>
                      <option value="Hydraulic Operations">Hydraulic Operations</option>
                      <option value="Mechanical Workshop">Mechanical Workshop</option>
                    <?php endif; ?>
                  </select>
                </div>

                <!-- Row 2: Warehouse -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(220px, 260px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Warehouse :</label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Warehouse</option>
                    <option value="Central Warehouse - Bay A">Central Warehouse - Bay A</option>
                    <option value="Spares Depot - Bay B">Spares Depot - Bay B</option>
                    <option value="Fuel &amp; Lubricants Depot">Fuel &amp; Lubricants Depot</option>
                    <option value="Heavy Electrical Bay">Heavy Electrical Bay</option>
                  </select>
                </div>

                <!-- Row 3: Material Name -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(320px, 640px); align-items: center; gap: 10px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Material Name :</label>
                  <select name="material_name" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">-Select Material-</option>
                    <?php foreach ($materialAggregates as $mat): ?>
                      <option value="<?= htmlspecialchars($mat['sku']) ?>"><?= htmlspecialchars($mat['item_name']) ?>
                        (<?= htmlspecialchars($mat['sku']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Row 4: Year -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(130px, 160px); align-items: center; gap: 10px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Year :</label>
                  <select name="year" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Year</option>
                    <option value="<?= $chartYear ?>" selected><?= $chartYear ?></option>
                    <option value="<?= $chartYear - 1 ?>"><?= $chartYear - 1 ?></option>
                    <option value="<?= $chartYear - 2 ?>"><?= $chartYear - 2 ?></option>
                    <option value="<?= $chartYear - 3 ?>"><?= $chartYear - 3 ?></option>
                  </select>
                </div>

                <!-- Row 5: Month -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(130px, 160px); align-items: center; gap: 10px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Month :</label>
                  <select name="month" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">-Select Month-</option>
                    <?php foreach ($monthNames as $idx => $mName): ?>
                      <option value="<?= $idx ?>"><?= htmlspecialchars($mName) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Row 6: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 10px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Generated Table Container -->
            <div class="monthly-transfers-results"
              style="display: none; margin-top: 36px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
              <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                <div>
                  <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Monthly Material Transfers Ledger</h4>
                  <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                    Inter-departmental and inter-station material transfers and bin relocation logs.
                  </p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                  Print Report
                </button>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Transfer Ref</th>
                      <th>Source Location / Bay</th>
                      <th>Destination Department</th>
                      <th>Material SKU</th>
                      <th>Item Description</th>
                      <th style="text-align: right;">Quantity Transferred</th>
                      <th>Transfer Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($stockTransfers)): ?>
                      <tr>
                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No material transfer logs recorded for the selected parameters.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($stockTransfers as $t): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($t['transfer_ref']) ?></code></td>
                          <td><?= htmlspecialchars($t['from_bay']) ?></td>
                          <td><strong><?= htmlspecialchars($t['to_department']) ?></strong></td>
                          <td><code><?= htmlspecialchars($t['sku']) ?></code></td>
                          <td><?= htmlspecialchars($t['item_name']) ?></td>
                          <td style="text-align: right; font-weight: 700;"><?= (int) $t['quantity'] ?></td>
                          <td><?= htmlspecialchars($t['transfer_date']) ?></td>
                          <td><span class="badge badge-success"><?= htmlspecialchars($t['status']) ?></span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.4: REPORTS -> AVERAGE DIESEL PRICE (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-avg-diesel" class="user-tab-pane">
          <div class="card"
            style="padding: 36px 48px; background: #ffffff; min-height: 500px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="avg-diesel-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.avg-diesel-results'); res.style.display = 'block';">
              <div style="display: flex; flex-direction: column; gap: 24px; max-width: 520px;">

                <!-- Row 1: Year -->
                <div
                  style="display: grid; grid-template-columns: 120px minmax(220px, 280px); align-items: center; gap: 16px;">
                  <label style="font-size: 1.05rem; font-weight: 500; color: #000000;">Year :</label>
                  <select name="year" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.95rem; background: #ffffff; width: 100%;">
                    <option value="">~ Select year ~</option>
                    <option value="<?= $chartYear ?>" selected><?= $chartYear ?></option>
                    <option value="<?= $chartYear - 1 ?>"><?= $chartYear - 1 ?></option>
                    <option value="<?= $chartYear - 2 ?>"><?= $chartYear - 2 ?></option>
                    <option value="<?= $chartYear - 3 ?>"><?= $chartYear - 3 ?></option>
                  </select>
                </div>

                <!-- Row 2: Month -->
                <div
                  style="display: grid; grid-template-columns: 120px minmax(220px, 280px); align-items: center; gap: 16px;">
                  <label style="font-size: 1.05rem; font-weight: 500; color: #000000;">Month :</label>
                  <select name="month" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.95rem; background: #ffffff; width: 100%;">
                    <option value=""></option>
                    <?php foreach ($monthNames as $idx => $mName): ?>
                      <option value="<?= $idx ?>"><?= htmlspecialchars($mName) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Row 3: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 120px 1fr; align-items: center; gap: 16px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Generated Table Container -->
            <div class="avg-diesel-results"
              style="display: none; margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
              <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                <div>
                  <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Weighted Average Fuel Price Valuation
                    Report</h4>
                  <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                    Monthly weighted average purchase rates for power generation fuels (Auto Diesel, Heavy Fuel Oil,
                    Lubricants).
                  </p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                  Print Report
                </button>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Effective Month</th>
                      <th>Fuel Classification</th>
                      <th style="text-align: right;">Total Litres Procured</th>
                      <th style="text-align: right;">Total Cost (LKR)</th>
                      <th style="text-align: right;">Weighted Avg Price / Litre (LKR)</th>
                      <th>Audit State</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($dieselPriceLogs)): ?>
                      <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No fuel price calculation logs recorded in the ledger.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($dieselPriceLogs as $d): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($d['effective_month']) ?></strong></td>
                          <td><?= htmlspecialchars($d['fuel_type']) ?></td>
                          <td style="text-align: right;"><?= number_format($d['total_procured_litres'], 2) ?> L</td>
                          <td style="text-align: right;">Rs. <?= number_format($d['total_procurement_cost'], 2) ?></td>
                          <td style="text-align: right; font-weight: 800; color: #650b2b;">Rs.
                            <?= number_format($d['avg_price_per_litre'], 2) ?>
                          </td>
                          <td><span class="badge badge-success">Approved</span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.5: REPORTS -> CPC PROCUREMENTS REPORT (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-cpc-procurement" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 500px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="cpc-procurement-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.cpc-procurement-results'); res.style.display = 'block';">
              <div style="display: flex; flex-direction: column; gap: 18px; max-width: 740px;">

                <!-- Row 1: Year -->
                <div
                  style="display: grid; grid-template-columns: 150px minmax(180px, 200px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Year :</label>
                  <select name="year" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">~Select Year~</option>
                    <option value="<?= $chartYear ?>" selected><?= $chartYear ?></option>
                    <option value="<?= $chartYear - 1 ?>"><?= $chartYear - 1 ?></option>
                    <option value="<?= $chartYear - 2 ?>"><?= $chartYear - 2 ?></option>
                    <option value="<?= $chartYear - 3 ?>"><?= $chartYear - 3 ?></option>
                  </select>
                </div>

                <!-- Row 2: Department IDs -->
                <div style="display: grid; grid-template-columns: 150px 1fr; align-items: flex-start; gap: 10px;">
                  <label style="font-size: 0.95rem; color: #000000; line-height: 1.35;">
                    Department IDs<br>
                    <span style="font-size: 0.85rem; color: #000000;">(Please Enter Comma<br>Separated Dept ids.<br>eg:
                      818.00,816.00)</span>
                  </label>
                  <div>
                    <textarea name="department_ids" class="form-control" rows="2"
                      style="width: 100%; max-width: 540px; height: 55px; border: 1px solid #71717a; border-radius: 2px; padding: 6px 8px; font-size: 0.9rem; font-family: inherit; background: #ffffff; resize: both;"></textarea>
                  </div>
                </div>

                <!-- Row 3: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 150px 1fr; align-items: center; gap: 10px; margin-top: 2px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Generated Table Container -->
            <div class="cpc-procurement-results"
              style="display: none; margin-top: 36px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
              <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                <div>
                  <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">CPC Fuel Procurements &amp; Bowser
                    Delivery Log</h4>
                  <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                    Direct bulk fuel shipments received from Ceylon Petroleum Corporation.
                  </p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                  Print Report
                </button>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>CPC Order No</th>
                      <th>Fuel Category</th>
                      <th>Storage Tank ID</th>
                      <th style="text-align: right;">Quantity (Litres)</th>
                      <th style="text-align: right;">Unit Rate (LKR)</th>
                      <th style="text-align: right;">Total Amount (LKR)</th>
                      <th>Consignment Date</th>
                      <th>Delivery Ref</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($cpcProcurements)): ?>
                      <tr>
                        <td colspan="8" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No CPC fuel consignment deliveries logged.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($cpcProcurements as $cpc): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($cpc['order_no']) ?></code></td>
                          <td><strong><?= htmlspecialchars($cpc['fuel_type']) ?></strong></td>
                          <td><?= htmlspecialchars($cpc['tank_id']) ?></td>
                          <td style="text-align: right;"><?= number_format($cpc['quantity_litres'], 2) ?></td>
                          <td style="text-align: right;">Rs. <?= number_format($cpc['unit_price_lkr'], 2) ?></td>
                          <td style="text-align: right; font-weight: 700; color: #650b2b;">Rs.
                            <?= number_format($cpc['total_amount_lkr'], 2) ?>
                          </td>
                          <td><?= htmlspecialchars($cpc['consignment_date']) ?></td>
                          <td><code><?= htmlspecialchars($cpc['delivery_ref']) ?></code></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.6: REPORTS -> INVENTORY INQUIRY REPORT (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-inventory-inquiry" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 500px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="inventory-inquiry-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.inventory-inquiry-results'); res.style.display = 'block';">
              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 600px;">

                <!-- Row 1: Cost Center -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(230px, 280px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Cost Center :</label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Department</option>
                    <?php if (!empty($departments)): ?>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="Turbine Maintenance">Turbine Maintenance</option>
                      <option value="Electrical Engineering">Electrical Engineering</option>
                      <option value="Hydraulic Operations">Hydraulic Operations</option>
                      <option value="Mechanical Workshop">Mechanical Workshop</option>
                    <?php endif; ?>
                  </select>
                </div>

                <!-- Row 2: From Date -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(230px, 280px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">From Date &nbsp;:</label>
                  <div style="display: flex; align-items: center; gap: 4px;">
                    <input type="text" name="from_date" class="form-control" placeholder=""
                      style="border: 1px solid #71717a; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%; height: 26px;">
                    <button type="button"
                      onclick="const inp = this.previousElementSibling; if(inp.type==='date'){inp.showPicker?inp.showPicker():inp.focus()}else{inp.type='date';inp.showPicker?inp.showPicker():inp.focus()}"
                      style="border: 1px solid #7f9db9; background: #f4f4f4; padding: 0; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;">
                      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="2" width="14" height="13" fill="#ffffff" stroke="#3b5998" stroke-width="1" />
                        <rect x="1" y="2" width="14" height="4" fill="#6d84b4" />
                        <rect x="4" y="8" width="2" height="2" fill="#555" />
                        <rect x="7" y="8" width="2" height="2" fill="#555" />
                        <rect x="10" y="8" width="2" height="2" fill="#d9534f" />
                        <rect x="4" y="11" width="2" height="2" fill="#555" />
                        <rect x="7" y="11" width="2" height="2" fill="#555" />
                        <rect x="10" y="11" width="2" height="2" fill="#555" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Row 3: To Date -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(230px, 280px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">To Date
                    &nbsp;&nbsp;&nbsp;&nbsp;:</label>
                  <div style="display: flex; align-items: center; gap: 4px;">
                    <input type="text" name="to_date" class="form-control" placeholder=""
                      style="border: 1px solid #71717a; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%; height: 26px;">
                    <button type="button"
                      onclick="const inp = this.previousElementSibling; if(inp.type==='date'){inp.showPicker?inp.showPicker():inp.focus()}else{inp.type='date';inp.showPicker?inp.showPicker():inp.focus()}"
                      style="border: 1px solid #7f9db9; background: #f4f4f4; padding: 0; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;">
                      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="2" width="14" height="13" fill="#ffffff" stroke="#3b5998" stroke-width="1" />
                        <rect x="1" y="2" width="14" height="4" fill="#6d84b4" />
                        <rect x="4" y="8" width="2" height="2" fill="#555" />
                        <rect x="7" y="8" width="2" height="2" fill="#555" />
                        <rect x="10" y="8" width="2" height="2" fill="#d9534f" />
                        <rect x="4" y="11" width="2" height="2" fill="#555" />
                        <rect x="7" y="11" width="2" height="2" fill="#555" />
                        <rect x="10" y="11" width="2" height="2" fill="#555" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Row 4: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 10px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Generated Table Container -->
            <div class="inventory-inquiry-results"
              style="display: none; margin-top: 36px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
              <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                <div>
                  <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Inventory Inquiry Balance Report</h4>
                  <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                    Live stock ledger balance with cumulative receipts and issues.
                  </p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                  Print Report
                </button>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Item Code / SKU</th>
                      <th>Material Description</th>
                      <th>Category</th>
                      <th style="text-align: right;">Total Initial Receipts</th>
                      <th style="text-align: right;">Total Dispatched (Issues)</th>
                      <th style="text-align: right;">Current Balance (Units)</th>
                      <th style="text-align: right;">Stock Valuation (LKR)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($fifoBatches)): ?>
                      <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No inventory records match the inquiry parameters.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($fifoBatches as $b): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($b['sku']) ?></code></td>
                          <td><strong><?= htmlspecialchars($b['item_name']) ?></strong></td>
                          <td><?= htmlspecialchars($b['category'] ?? 'Standard') ?></td>
                          <td style="text-align: right;"><?= (int) $b['initial_quantity'] ?></td>
                          <td style="text-align: right; color: #650b2b;">
                            <?= (int) $b['initial_quantity'] - (int) $b['remaining_quantity'] ?>
                          </td>
                          <td style="text-align: right; font-weight: 700; color: #0f172a;">
                            <?= (int) $b['remaining_quantity'] ?>
                          </td>
                          <td style="text-align: right; font-weight: 700; color: #10b981;">
                            Rs. <?= number_format((int) $b['remaining_quantity'] * (float) $b['unit_cost'], 2) ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.7: REPORTS -> JOB WISE MATERIAL ISSUE DETAILS REPORT (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-job-issue" class="user-tab-pane">
          <div class="card"
            style="padding: 30px 40px; background: #ffffff; min-height: 520px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">

            <!-- Centered Report Title -->
            <h3
              style="text-align: center; font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Arial, sans-serif; font-weight: 700; font-size: 1.15rem; color: #000000; margin-bottom: 36px; letter-spacing: -0.2px;">
              Job Wise Material Issue Details Report
            </h3>

            <form class="job-wise-issue-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.job-issue-results-container'); res.style.display = 'block';">

              <div style="max-width: 820px; margin: 0 auto;">

                <!-- From / To & Year / Month Grid -->
                <div
                  style="display: grid; grid-template-columns: 380px 380px; gap: 14px 40px; justify-content: center; align-items: center; margin-bottom: 20px;">

                  <!-- Left: From Year -->
                  <div style="display: flex; align-items: center; gap: 20px;">
                    <span style="font-weight: 700; font-size: 1.05rem; color: #000000; width: 45px;">From</span>
                    <label style="font-size: 0.95rem; font-weight: 500; color: #000000; width: 40px;">Year</label>
                    <select name="from_year" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; flex: 1;">
                      <option value="">Select Year</option>
                      <option value="<?= $chartYear ?>" selected><?= $chartYear ?></option>
                      <option value="<?= $chartYear - 1 ?>"><?= $chartYear - 1 ?></option>
                      <option value="<?= $chartYear - 2 ?>"><?= $chartYear - 2 ?></option>
                    </select>
                  </div>

                  <!-- Right: From Month -->
                  <div style="display: flex; align-items: center; gap: 16px;">
                    <label style="font-size: 0.95rem; font-weight: 500; color: #000000; width: 55px;">Month</label>
                    <select name="from_month" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; flex: 1;">
                      <option value="">Select Month</option>
                      <?php foreach ($monthNames as $idx => $mName): ?>
                        <option value="<?= $idx ?>"><?= htmlspecialchars($mName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <!-- Left: To Year -->
                  <div style="display: flex; align-items: center; gap: 20px;">
                    <span style="font-weight: 700; font-size: 1.05rem; color: #000000; width: 45px;">To</span>
                    <label style="font-size: 0.95rem; font-weight: 500; color: #000000; width: 40px;">Year</label>
                    <select name="to_year" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; flex: 1;">
                      <option value="">Select Year</option>
                      <option value="<?= $chartYear ?>" selected><?= $chartYear ?></option>
                      <option value="<?= $chartYear - 1 ?>"><?= $chartYear - 1 ?></option>
                      <option value="<?= $chartYear - 2 ?>"><?= $chartYear - 2 ?></option>
                    </select>
                  </div>

                  <!-- Right: To Month -->
                  <div style="display: flex; align-items: center; gap: 16px;">
                    <label style="font-size: 0.95rem; font-weight: 500; color: #000000; width: 55px;">Month</label>
                    <select name="to_month" class="form-select"
                      style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; flex: 1;">
                      <option value="">Select Month</option>
                      <?php foreach ($monthNames as $idx => $mName): ?>
                        <option value="<?= $idx ?>"><?= htmlspecialchars($mName) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                </div>

                <!-- Center Get Jobs Button -->
                <div style="text-align: center; margin-bottom: 24px;">
                  <button type="button"
                    onclick="const sel = this.closest('form').querySelector('select[name=job_number]'); sel.innerHTML = '<option value=\'\'>Select Job</option><option value=\'WO-2026-001\'>WO-2026-001 (Turbine Overhaul)</option><option value=\'WO-2026-002\'>WO-2026-002 (Generator Stator Rewind)</option><option value=\'WO-2026-003\'>WO-2026-003 (Switchgear Relay Calibration)</option>'; alert('Jobs loaded successfully for the selected period.');"
                    style="background: #efefef; border: 1px solid #767676; color: #000000; font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; padding: 3px 24px; cursor: pointer; border-radius: 0; box-shadow: 1px 1px 0 #ffffff inset;">
                    Get Jobs
                  </button>
                </div>

                <!-- Job Number Row -->
                <div
                  style="display: flex; align-items: center; justify-content: flex-start; gap: 20px; max-width: 520px; margin-left: 60px; margin-bottom: 28px;">
                  <label style="font-size: 0.95rem; font-weight: 700; color: #000000; width: 110px;">Job Number</label>
                  <select name="job_number" class="form-select"
                    style="border: 1px solid #b0b8c1; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 260px;">
                    <option value=""></option>
                    <option value="WO-2026-001">WO-2026-001 (Turbine Overhaul)</option>
                    <option value="WO-2026-002">WO-2026-002 (Generator Stator Rewind)</option>
                    <option value="WO-2026-003">WO-2026-003 (Switchgear Relay Calibration)</option>
                  </select>
                </div>

                <!-- Generate Report Button -->
                <div style="display: flex; justify-content: flex-end; max-width: 680px; margin-bottom: 24px;">
                  <button type="submit"
                    style="background: #efefef; border: 1px solid #767676; color: #000000; font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; padding: 3px 18px; cursor: pointer; border-radius: 0; box-shadow: 1px 1px 0 #ffffff inset;">
                    Generate Report
                  </button>
                </div>

              </div>
            </form>

            <!-- Generated Results Table Container -->
            <div class="job-issue-results-container"
              style="display: none; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 24px;">
              <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 12px;">
                <div>
                  <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Material Consumption per Job / Work Order
                  </h4>
                  <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                    Breakdown of allocated FIFO batches and issued parts for the selected work order.
                  </p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">
                  Print Report
                </button>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>GIN Ref No</th>
                      <th>Job / Work Order</th>
                      <th>Material Name</th>
                      <th>Target Department</th>
                      <th style="text-align: right;">Quantity Issued</th>
                      <th style="text-align: right;">Unit Price (LKR)</th>
                      <th style="text-align: right;">Total Cost (LKR)</th>
                      <th>Issue Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td colspan="9" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                        No material issue transactions registered for work orders in this range.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.8: REPORTS -> MONTHLY TRANSACTION (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-monthly-tx" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 500px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="monthly-tx-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.monthlytx-results-container'); res.style.display = 'block';">
              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 840px;">

                <!-- Row 1: Cost Center -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(280px, 360px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Cost Center :</label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Department</option>
                    <?php if (!empty($departments)): ?>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="Turbine Maintenance">Turbine Maintenance</option>
                      <option value="Electrical Engineering">Electrical Engineering</option>
                      <option value="Hydraulic Operations">Hydraulic Operations</option>
                      <option value="Mechanical Workshop">Mechanical Workshop</option>
                    <?php endif; ?>
                  </select>
                </div>

                <!-- Row 2: Document Type -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(280px, 360px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Document Type :</label>
                  <select name="document_type" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Document Type</option>
                    <option value="GRN">Goods Received Note (GRN)</option>
                    <option value="GIN">Goods Issue Note (GIN)</option>
                    <option value="MTN">Material Transfer Note (MTN)</option>
                    <option value="ADJ">Physical Verification Adjustment</option>
                    <option value="ALL">All Document Types</option>
                  </select>
                </div>

                <!-- Row 3: Warehouse -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(180px, 220px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Warehouse :</label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Warehouse</option>
                    <option value="Central Warehouse - Bay A">Central Warehouse - Bay A</option>
                    <option value="Spares Depot - Bay B">Spares Depot - Bay B</option>
                    <option value="Fuel &amp; Lubricants Depot">Fuel &amp; Lubricants Depot</option>
                    <option value="Heavy Electrical Bay">Heavy Electrical Bay</option>
                  </select>
                </div>

                <!-- Row 4: Year -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(130px, 150px); align-items: center; gap: 10px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Year :</label>
                  <select name="year" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">Select Year</option>
                    <option value="<?= $chartYear ?>" selected><?= $chartYear ?></option>
                    <option value="<?= $chartYear - 1 ?>"><?= $chartYear - 1 ?></option>
                    <option value="<?= $chartYear - 2 ?>"><?= $chartYear - 2 ?></option>
                    <option value="<?= $chartYear - 3 ?>"><?= $chartYear - 3 ?></option>
                  </select>
                </div>

                <!-- Row 5: Month -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(130px, 150px); align-items: center; gap: 10px; margin-top: 4px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Month :</label>
                  <select name="month" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff; width: 100%;">
                    <option value="">-Select Month-</option>
                    <?php foreach ($monthNames as $idx => $mName): ?>
                      <option value="<?= $idx ?>"><?= htmlspecialchars($mName) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Row 6: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 10px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Generated Table Container -->
            <div class="monthlytx-results-container" style="display: none; margin-top: 36px;">
              <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <div>
                  <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Monthly Transaction Ledger Report</h4>
                  <p style="font-size: 0.82rem; color: #64748b; margin-top: 2px;">
                    Receipts, issues, and net balance audit summary.
                  </p>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print / Export</button>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Month (Period)</th>
                      <th style="text-align: right;">Total Receipts (LKR)</th>
                      <th style="text-align: right;">Total Issues (LKR)</th>
                      <th style="text-align: right;">Net Movement (LKR)</th>
                      <th>Valuation State</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($monthlyTransactionData as $m): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($m['month_name']) ?>     <?= $chartYear ?>
                            (<?= htmlspecialchars($m['label']) ?>)</strong></td>
                        <td style="text-align: right; color: #10b981; font-weight: 600;">Rs.
                          <?= number_format($m['receipts'], 2) ?>
                        </td>
                        <td style="text-align: right; color: #650b2b; font-weight: 600;">Rs.
                          <?= number_format($m['issues'], 2) ?>
                        </td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;">Rs.
                          <?= number_format($m['receipts'] - $m['issues'], 2) ?>
                        </td>
                        <td><span class="badge badge-success">Closed</span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.9: REPORTS -> GRN - ISSUE SUMMARY (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-report-grn-issue-summary" class="user-tab-pane">
          <div class="card"
            style="padding: 24px 30px; background: #ffffff; min-height: 520px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">

            <form class="grn-issue-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.grn-results-container'); const msg = this.closest('.card').querySelector('.grn-status-msg'); res.style.display = 'block'; msg.style.display = 'none';">

              <!-- Top Bordered Box (Select Cost Center & Select Warehouse) -->
              <div
                style="border: 1.5px solid #000000; padding: 12px 16px; max-width: 620px; margin-bottom: 12px; background: #ffffff;">
                <div style="display: grid; grid-template-columns: 150px 1fr; gap: 12px 14px; align-items: center;">

                  <label
                    style="font-family: 'Times New Roman', Times, serif; font-weight: bold; font-size: 15px; color: #000000;">Select
                    Cost Center</label>
                  <div>
                    <select name="cost_center" class="form-select"
                      style="border: 1px solid #7f9db9; border-radius: 0; padding: 2px 6px; font-size: 13px; font-family: Arial, sans-serif; background: #ffffff; width: 100%; height: 24px;">
                      <option value="">Select Department</option>
                      <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $dept): ?>
                          <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <option value="Turbine Maintenance">Turbine Maintenance</option>
                        <option value="Electrical Engineering">Electrical Engineering</option>
                        <option value="Hydraulic Operations">Hydraulic Operations</option>
                        <option value="Mechanical Workshop">Mechanical Workshop</option>
                      <?php endif; ?>
                    </select>
                  </div>

                  <label
                    style="font-family: 'Times New Roman', Times, serif; font-weight: bold; font-size: 15px; color: #000000;">Select
                    Warehouse</label>
                  <div>
                    <select name="warehouse" class="form-select"
                      style="border: 1px solid #7f9db9; border-radius: 0; padding: 2px 6px; font-size: 13px; font-family: Arial, sans-serif; background: #ffffff; width: 100%; height: 24px;">
                      <option value=""></option>
                      <option value="Central Warehouse - Bay A">Central Warehouse - Bay A</option>
                      <option value="Spares Depot - Bay B">Spares Depot - Bay B</option>
                      <option value="Fuel &amp; Lubricants Depot">Fuel &amp; Lubricants Depot</option>
                      <option value="Heavy Electrical Bay">Heavy Electrical Bay</option>
                    </select>
                  </div>

                </div>
              </div>

              <!-- Date Fields Below Box -->
              <div
                style="display: flex; flex-direction: column; gap: 6px; max-width: 620px; margin-bottom: 14px; padding-left: 2px;">
                <div style="display: grid; grid-template-columns: 150px auto; gap: 14px; align-items: center;">
                  <label
                    style="font-family: 'Times New Roman', Times, serif; font-weight: bold; font-size: 15px; color: #000000;">From
                    Date</label>
                  <div style="display: inline-flex; align-items: center;">
                    <input type="text" name="from_date" class="grn-date-input" placeholder=""
                      style="border: 1px solid #7f9db9; border-radius: 0; padding: 2px 6px; font-size: 13px; font-family: Arial, sans-serif; background: #ffffff; width: 190px; height: 22px;">
                    <button type="button" class="grn-calendar-btn"
                      onclick="const inp = this.previousElementSibling; if(inp.type==='date'){inp.showPicker?inp.showPicker():inp.focus()}else{inp.type='date';inp.showPicker?inp.showPicker():inp.focus()}"
                      style="border: 1px solid #7f9db9; background: #f4f4f4; padding: 0; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; margin-left: 2px;">
                      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="2" width="14" height="13" fill="#ffffff" stroke="#3b5998" stroke-width="1" />
                        <rect x="1" y="2" width="14" height="4" fill="#6d84b4" />
                        <rect x="4" y="8" width="2" height="2" fill="#555" />
                        <rect x="7" y="8" width="2" height="2" fill="#555" />
                        <rect x="10" y="8" width="2" height="2" fill="#d9534f" />
                        <rect x="4" y="11" width="2" height="2" fill="#555" />
                        <rect x="7" y="11" width="2" height="2" fill="#555" />
                        <rect x="10" y="11" width="2" height="2" fill="#555" />
                      </svg>
                    </button>
                  </div>
                </div>

                <div style="display: grid; grid-template-columns: 150px auto; gap: 14px; align-items: center;">
                  <label
                    style="font-family: 'Times New Roman', Times, serif; font-weight: bold; font-size: 15px; color: #000000;">To
                    Date</label>
                  <div style="display: inline-flex; align-items: center;">
                    <input type="text" name="to_date" class="grn-date-input" placeholder=""
                      style="border: 1px solid #7f9db9; border-radius: 0; padding: 2px 6px; font-size: 13px; font-family: Arial, sans-serif; background: #ffffff; width: 190px; height: 22px;">
                    <button type="button" class="grn-calendar-btn"
                      onclick="const inp = this.previousElementSibling; if(inp.type==='date'){inp.showPicker?inp.showPicker():inp.focus()}else{inp.type='date';inp.showPicker?inp.showPicker():inp.focus()}"
                      style="border: 1px solid #7f9db9; background: #f4f4f4; padding: 0; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; margin-left: 2px;">
                      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="2" width="14" height="13" fill="#ffffff" stroke="#3b5998" stroke-width="1" />
                        <rect x="1" y="2" width="14" height="4" fill="#6d84b4" />
                        <rect x="4" y="8" width="2" height="2" fill="#555" />
                        <rect x="7" y="8" width="2" height="2" fill="#555" />
                        <rect x="10" y="8" width="2" height="2" fill="#d9534f" />
                        <rect x="4" y="11" width="2" height="2" fill="#555" />
                        <rect x="7" y="11" width="2" height="2" fill="#555" />
                        <rect x="10" y="11" width="2" height="2" fill="#555" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Button -->
              <div style="margin-top: 10px; margin-bottom: 24px; padding-left: 2px;">
                <button type="submit"
                  style="background: #f0f0f0; border: 1px solid #707070; color: #000000; font-family: 'Times New Roman', Times, serif; font-size: 14px; padding: 2px 10px; cursor: pointer; border-radius: 0; box-shadow: 1px 1px 0px #ffffff inset;">
                  Create Report
                </button>
              </div>

            </form>

            <!-- Notice message matching screenshot -->
            <div class="grn-status-msg"
              style="font-size: 13px; color: #000000; font-family: Arial, Helvetica, sans-serif; padding-left: 40px; margin-top: 20px;">
              A data source instance has not been supplied for the data source 'GRN'.
            </div>

            <!-- Generated Table Container -->
            <div class="grn-results-container" style="display: none; margin-top: 30px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Goods Received Notes (GRN) vs. Goods Issue
                  Notes (GIN) Comparison</h4>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print / Export</button>
              </div>

              <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div
                  style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; border-left: 4px solid #650b2b;">
                  <strong style="color: #0f172a; font-size: 0.92rem;">Total Inbound GRN Value</strong>
                  <div style="font-size: 1.25rem; font-weight: 800; color: #650b2b; margin-top: 6px;">Rs.
                    <?= number_format($totalReceiptsYTD, 2) ?>
                  </div>
                </div>
                <div
                  style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; border-left: 4px solid #1e5a8a;">
                  <strong style="color: #0f172a; font-size: 0.92rem;">Total Outbound GIN Value</strong>
                  <div style="font-size: 1.25rem; font-weight: 800; color: #1e5a8a; margin-top: 6px;">Rs.
                    <?= number_format($totalIssuesYTD, 2) ?>
                  </div>
                </div>
                <div
                  style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; border-left: 4px solid #10b981;">
                  <strong style="color: #0f172a; font-size: 0.92rem;">Net FIFO Stock Retained</strong>
                  <div style="font-size: 1.25rem; font-weight: 800; color: #10b981; margin-top: 6px;">Rs.
                    <?= number_format($netInventoryValue, 2) ?>
                  </div>
                </div>
              </div>

              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Period</th>
                      <th style="text-align: right;">GRN Receipts (LKR)</th>
                      <th style="text-align: right;">GIN Issues (LKR)</th>
                      <th style="text-align: right;">Stock Retention (LKR)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($monthlyTransactionData as $m): ?>
                      <tr>
                        <td><strong><?= htmlspecialchars($m['month_name']) ?>     <?= $chartYear ?></strong></td>
                        <td style="text-align: right;">Rs. <?= number_format($m['receipts'], 2) ?></td>
                        <td style="text-align: right;">Rs. <?= number_format($m['issues'], 2) ?></td>
                        <td style="text-align: right; font-weight: 700; color: #0f172a;">Rs.
                          <?= number_format($m['receipts'] - $m['issues'], 2) ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 4.10: REPORTS -> MATERIAL FLOW REPORT
         ===================================================================== -->
        <section id="tab-user-report-material-flow" class="user-tab-pane">
          <div class="card"
            style="padding: 28px 36px; background: #ffffff; min-height: 480px; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
            <form class="mat-flow-filter-form"
              onsubmit="event.preventDefault(); const res = this.closest('.card').querySelector('.matflow-results-container'); const msg = this.closest('.card').querySelector('.matflow-status-msg'); res.style.display = 'block'; msg.style.display = 'none';">
              <div style="display: flex; flex-direction: column; gap: 14px; max-width: 840px;">

                <!-- Row 1: Cost Center -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(220px, 320px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Cost Center :</label>
                  <select name="cost_center" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff;">
                    <option value="">Select Department</option>
                    <?php if (!empty($departments)): ?>
                      <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <option value="Turbine Maintenance">Turbine Maintenance</option>
                      <option value="Electrical Engineering">Electrical Engineering</option>
                      <option value="Hydraulic Operations">Hydraulic Operations</option>
                      <option value="Mechanical Workshop">Mechanical Workshop</option>
                    <?php endif; ?>
                  </select>
                </div>

                <!-- Row 2: Warehouse -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(220px, 320px); align-items: center; gap: 10px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Warehouse :</label>
                  <select name="warehouse" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff;">
                    <option value="">Select Warehouse</option>
                    <option value="Central Warehouse - Bay A">Central Warehouse - Bay A</option>
                    <option value="Spares Depot - Bay B">Spares Depot - Bay B</option>
                    <option value="Fuel &amp; Lubricants Depot">Fuel &amp; Lubricants Depot</option>
                    <option value="Heavy Electrical Bay">Heavy Electrical Bay</option>
                  </select>
                </div>

                <!-- Row 3: Material Name -->
                <div
                  style="display: grid; grid-template-columns: 140px minmax(320px, 620px); align-items: center; gap: 10px; margin-top: 6px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">Material Name :</label>
                  <select name="material_name" class="form-select"
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff;">
                    <option value="">-Select Material-</option>
                    <?php foreach ($materialAggregates as $m): ?>
                      <option value="<?= htmlspecialchars($m['sku']) ?>"><?= htmlspecialchars($m['item_name']) ?>
                        (<?= htmlspecialchars($m['sku']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <!-- Row 4: From Date -->
                <div
                  style="display: grid; grid-template-columns: 140px 140px 1fr; align-items: center; gap: 16px; margin-top: 6px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">From Date :</label>
                  <input type="text" name="from_date" class="form-control" placeholder=""
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff;">
                  <span style="font-size: 0.92rem; color: #000000; font-family: inherit;">Date Format : dd-mmm-yyyy (Ex:
                    01-JAN-2019)</span>
                </div>

                <!-- Row 5: To Date -->
                <div style="display: grid; grid-template-columns: 140px 140px 1fr; align-items: center; gap: 16px;">
                  <label style="font-size: 0.95rem; font-weight: 500; color: #000000;">To Date :</label>
                  <input type="text" name="to_date" class="form-control" placeholder=""
                    style="border: 1px solid #94a3b8; border-radius: 2px; padding: 4px 8px; font-size: 0.9rem; background: #ffffff;">
                  <span style="font-size: 0.92rem; color: #000000; font-family: inherit;">Date Format : dd-mmm-yyyy (Ex:
                    01-JAN-2019)</span>
                </div>

                <!-- Row 6: Submit Button -->
                <div
                  style="display: grid; grid-template-columns: 140px 1fr; align-items: center; gap: 10px; margin-top: 4px;">
                  <div></div>
                  <div>
                    <button type="submit"
                      style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 5px 18px; border-radius: 3px; font-size: 0.85rem; font-weight: 700; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                      Generate Report
                    </button>
                  </div>
                </div>

              </div>
            </form>

            <!-- Notice message matching screenshot -->
            <div class="matflow-status-msg"
              style="margin-top: 44px; font-size: 0.92rem; color: #000000; font-family: inherit;">
              A data source instance has not been supplied for the data source 'MatFlowData'.
            </div>

            <!-- Generated Table Container -->
            <div class="matflow-results-container" style="display: none; margin-top: 30px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h4 style="font-size: 1rem; font-weight: 700; color: #0f172a;">Material Flow &amp; Movement Velocity
                  Ledger</h4>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()">Print / Export</button>
              </div>
              <div style="overflow-x: auto;">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Material SKU</th>
                      <th>Material Description</th>
                      <th>Category</th>
                      <th style="text-align: right;">Total Inward Units</th>
                      <th style="text-align: right;">Available On Hand</th>
                      <th style="text-align: right;">Total Valuation (LKR)</th>
                      <th>Movement Velocity</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($materialAggregates)): ?>
                      <tr>
                        <td colspan="7" style="text-align: center; color: #94a3b8; padding: 28px 16px; font-size: 0.88rem;">
                          No material movement records logged.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($materialAggregates as $mat): ?>
                        <tr>
                          <td><code><?= htmlspecialchars($mat['sku']) ?></code></td>
                          <td><strong><?= htmlspecialchars($mat['item_name']) ?></strong></td>
                          <td><?= htmlspecialchars($mat['category']) ?></td>
                          <td style="text-align: right;"><?= $mat['total_remaining_qty'] ?></td>
                          <td style="text-align: right; font-weight: 700;"><?= $mat['total_remaining_qty'] ?> Units</td>
                          <td style="text-align: right; font-weight: 700; color: #10b981;">Rs.
                            <?= number_format($mat['total_value'], 2) ?>
                          </td>
                          <td><span class="badge badge-success">Optimal In-Flow</span></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

          </div>
        </section>



        <!-- =====================================================================
         PANE 5.1: AUTHORIZED TASKS -> COST ADJUSTMENTS (EMPTY WHITE PAGE)
         ===================================================================== -->
        <section id="tab-user-task-cost-adjustment" class="user-tab-pane">
          <div class="card"
            style="padding: 36px 44px; background: #ffffff; min-height: 520px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">
          </div>
        </section>

        <!-- =====================================================================
         PANE 5.2: AUTHORIZED TASKS -> ALLOCATED QTY CORRECTION (MATCHING SCREENSHOT)
         ===================================================================== -->
        <section id="tab-user-task-qty-correction" class="user-tab-pane">
          <div class="card"
            style="padding: 36px 44px; background: #ffffff; min-height: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; border-radius: 4px;">

            <!-- Section 1: Cost Center Quantity Correction -->
            <div style="margin-bottom: 56px;">
              <h3
                style="color: #dc2626; font-size: 1.15rem; font-weight: 700; margin-bottom: 24px; line-height: 1.4; font-family: inherit;">
                This is a Admin function and you need admin Priviledges to Do Updateds. Please Enter your user name and
                password
              </h3>

              <form id="legacyCostCenterCorrectionForm"
                style="display: grid; grid-template-columns: 200px minmax(280px, 460px); gap: 12px 24px; align-items: center;">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
                <input type="hidden" name="action" value="update_quantities">

                <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">User Name</label>
                <div>
                  <input type="text" name="admin_username" class="form-control"
                    style="border: 1px solid #71717a; border-radius: 2px; padding: 5px 8px; font-size: 0.95rem; width: 100%; background: #ffffff;"
                    required>
                </div>

                <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">Password</label>
                <div>
                  <input type="password" name="admin_password" class="form-control"
                    style="border: 1px solid #71717a; border-radius: 2px; padding: 5px 8px; font-size: 0.95rem; width: 100%; background: #ffffff;"
                    required>
                </div>

                <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">Cost Center to Correct</label>
                <div>
                  <input type="text" name="cost_center" class="form-control"
                    style="border: 1px solid #71717a; border-radius: 2px; padding: 5px 8px; font-size: 0.95rem; width: 100%; background: #ffffff;">
                </div>

                <div></div>
                <div>
                  <button type="submit"
                    style="background: #e2e8f0; border: 1px solid #71717a; color: #000000; font-size: 0.92rem; font-weight: 500; padding: 4px 16px; cursor: pointer; border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
                    Update Quantities
                  </button>
                </div>
              </form>
            </div>

            <!-- Section 2: Verification Tables Update & Batch Creation -->
            <div>
              <h3
                style="color: #dc2626; font-size: 1.15rem; font-weight: 700; margin-bottom: 24px; line-height: 1.4; font-family: inherit;">
                This is a Admin function and you need admin Priviledges to Do Updateds. Please Enter your user name and
                password
              </h3>

              <form id="legacyVerificationTablesForm"
                style="display: grid; grid-template-columns: 200px minmax(280px, 460px); gap: 12px 24px; align-items: center;">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

                <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">Verification Year</label>
                <div>
                  <input type="text" name="verification_year" class="form-control" value="<?= date('Y') ?>"
                    style="border: 1px solid #71717a; border-radius: 2px; padding: 5px 8px; font-size: 0.95rem; width: 100%; background: #ffffff;"
                    required>
                </div>

                <label style="font-weight: 700; font-size: 0.95rem; color: #000000;">Password</label>
                <div>
                  <input type="password" name="admin_password" class="form-control"
                    style="border: 1px solid #71717a; border-radius: 2px; padding: 5px 8px; font-size: 0.95rem; width: 100%; background: #ffffff;"
                    required>
                </div>

                <div></div>
                <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                  <button type="button" id="btnLegacyUpdateVerification"
                    style="background: #e2e8f0; border: 1px solid #71717a; color: #000000; font-size: 0.92rem; font-weight: 500; padding: 4px 16px; cursor: pointer; border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
                    Update Verification Tables
                  </button>
                  <button type="button" id="btnLegacyCreateBatch"
                    style="background: #e2e8f0; border: 1px solid #71717a; color: #000000; font-size: 0.92rem; font-weight: 500; padding: 4px 16px; cursor: pointer; border-radius: 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
                    Create Batch
                  </button>
                </div>
              </form>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE 6: USER PROFILE
         ===================================================================== -->
        <section id="tab-user-profile" class="user-tab-pane">
          <div style="display: grid; grid-template-columns: 280px 1fr; gap: 24px;">

            <!-- Profile Info Card -->
            <div class="card" style="text-align: center;">
              <div class="avatar avatar-lg"
                style="margin: 0 auto 16px; background-color: <?= htmlspecialchars($adminDetails['avatar_color'] ?? '#650b2b') ?>;">
                <?= strtoupper(substr($adminDetails['full_name'] ?? 'U', 0, 1)) ?>
              </div>
              <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
                <?= htmlspecialchars($adminDetails['full_name'] ?? 'User') ?>
              </h2>
              <span
                class="badge badge-primary"><?= htmlspecialchars($adminDetails['role_display_name'] ?? 'Staff Member') ?></span>

              <div
                style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle); text-align: left; font-size: 0.85rem; display: flex; flex-direction: column; gap: 10px;">
                <div><span style="color: #64748b;">Username:</span>
                  <strong>@<?= htmlspecialchars($adminDetails['username'] ?? '') ?></strong>
                </div>
                <div><span style="color: #64748b;">Email:</span>
                  <strong><?= htmlspecialchars($adminDetails['email'] ?? '') ?></strong>
                </div>
                <div><span style="color: #64748b;">Department:</span>
                  <strong><?= htmlspecialchars($adminDetails['department'] ?? 'General') ?></strong>
                </div>
                <div><span style="color: #64748b;">Status:</span> <span
                    class="badge badge-success"><?= ucfirst(htmlspecialchars($adminDetails['status'] ?? 'Active')) ?></span>
                </div>
              </div>
            </div>

            <!-- Password Change Card -->
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Security &amp; Password Update</h3>
              </div>

              <form id="superAdminPasswordForm">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                  <div class="form-group">
                    <label class="form-label">Current Password *</label>
                    <div class="password-input-wrapper">
                      <input type="password" name="current_password" class="form-control"
                        placeholder="Enter current password" required>
                      <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password"
                        tabindex="-1">
                        <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor"
                          stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18"
                          stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"
                          stroke-linejoin="round">
                          <path
                            d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                          </path>
                          <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                      </button>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">New Password *</label>
                    <div class="password-input-wrapper">
                      <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters"
                        required>
                      <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password"
                        tabindex="-1">
                        <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor"
                          stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18"
                          stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"
                          stroke-linejoin="round">
                          <path
                            d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                          </path>
                          <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <div style="text-align: right; margin-top: 10px;">
                  <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
              </form>
            </div>

          </div>
        </section>

        <!-- =====================================================================
         PANE: CODE MANAGEMENT - SUB-TAB 1: ITEM SEARCH
         ===================================================================== -->
        <section id="tab-user-code-item-search" class="user-tab-pane">
          <div style="max-width: 1100px; margin: 0 auto 24px auto;">
            
            <!-- Sub-tab Navigation Bar -->
            <div style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px;">
              <button type="button" class="btn btn-sm" onclick="switchUserCodeSubTab('search');"
                style="font-size: 0.88rem; font-weight: 800; padding: 7px 20px; border-radius: 4px; border: 1px solid #4a071e; background: #650b2b; color: #ffffff; cursor: pointer;">
                Item Search
              </button>
              <button type="button" class="btn btn-sm" onclick="switchUserCodeSubTab('add');"
                style="font-size: 0.88rem; font-weight: 700; padding: 7px 20px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer;">
                Add Code
              </button>
            </div>

            <!-- Item Search Module -->
            <?php
              $searchPrefix = 'user';
              include APP_PATH . '/../views/components/item_search_module.php';
            ?>
          </div>
        </section>

        <!-- =====================================================================
         PANE: CODE MANAGEMENT - SUB-TAB 2: ADD CODE (ITEM CODE LOOKUP & CATALOG)
         ===================================================================== -->
        <section id="tab-user-code-roles" class="user-tab-pane">
          <div class="card" style="padding: 24px 30px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); max-width: 960px; margin: 0 auto 24px auto;">
            
            <!-- Sub-tab Navigation Bar -->
            <div style="display: flex; gap: 8px; margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
              <button type="button" class="btn btn-sm" onclick="switchUserCodeSubTab('search');"
                style="font-size: 0.88rem; font-weight: 700; padding: 7px 20px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer;">
                Item Search
              </button>
              <button type="button" class="btn btn-sm" onclick="switchUserCodeSubTab('add');"
                style="font-size: 0.88rem; font-weight: 800; padding: 7px 20px; border-radius: 4px; border: 1px solid #4a071e; background: #650b2b; color: #ffffff; cursor: pointer;">
                Add Code
              </button>
            </div>
            
            <div style="border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; margin-bottom: 20px;">
              <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin: 0;">Inventory Item Code Lookup &amp; Verification</h3>
              <p style="font-size: 0.82rem; color: #64748b; margin: 4px 0 0 0;">
                Select 1st, 2nd, and 3rd category to check available master codes or submit a code creation request.
              </p>
            </div>

            <!-- Top Selection Form Box Matching Legacy / ERP Form Layout -->
            <form id="simpleCodeLookupForm" onsubmit="event.preventDefault(); updateSimpleCode();">
              <div style="border: 1px solid #b0b8c1; padding: 16px 20px; background: #f8fafc; border-radius: 3px; margin-bottom: 0;">
                
                <!-- Quick Live Search Bar with Instant Details Dropdown -->
                <div style="margin-bottom: 14px; position: relative;">
                  <label style="display: block; font-weight: 700; font-size: 0.86rem; color: #0f172a; margin-bottom: 4px;">
                    Quick Search (Item Description / Name / Raw Code / Item Code):
                  </label>
                  <div style="position: relative; display: flex; align-items: center;">
                    <span style="position: absolute; left: 10px; color: #94a3b8; pointer-events: none; display: flex; align-items: center;">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" id="simpleCodeSearchInput" class="form-control" placeholder="Type any item name (e.g. Bearing, Adhesive), raw code (10101001) or item code..."
                      oninput="onSimpleCodeSearchInput();" onfocus="onSimpleCodeSearchFocus();" autocomplete="off"
                      style="border: 1px solid #71717a; border-radius: 2px; padding: 5px 32px 5px 32px; font-size: 0.88rem; width: 100%; height: 34px; background: #ffffff;">
                    <button type="button" id="clearSimpleSearchBtn" onclick="clearSimpleCodeSearch();"
                      style="display: none; position: absolute; right: 8px; background: transparent; border: none; font-size: 1.15rem; color: #64748b; cursor: pointer; padding: 0 4px; line-height: 1;"
                      title="Clear search">&times;</button>
                  </div>

                  <!-- Instant Search Live Results Dropdown Card -->
                  <div id="simpleSearchResultsDropdown" style="display: none; position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.18), 0 8px 10px -6px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; z-index: 999;">
                    <!-- Dynamically rendered live result items -->
                  </div>
                </div>

                <div style="display: flex; align-items: center; margin-bottom: 12px; gap: 8px;">
                  <div style="flex: 1; height: 1px; background: #cbd5e1;"></div>
                  <span style="font-size: 0.74rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Select By 1st &bull; 2nd &bull; 3rd</span>
                  <div style="flex: 1; height: 1px; background: #cbd5e1;"></div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; align-items: flex-end;">
                  
                  <!-- 1st -->
                  <div>
                    <label style="display: block; font-weight: 700; font-size: 0.92rem; color: #000000; margin-bottom: 6px;">1st</label>
                    <select id="simpleTier1" class="form-select" onchange="onSimpleTier1Change();"
                      style="border: 1px solid #71717a; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; font-weight: 600; width: 100%; height: 34px; background: #ffffff;">
                      <option value="" selected disabled>-- 1st --</option>
                      <?php foreach ($tier1List ?? [] as $t1): ?>
                        <option value="<?= $t1['code_val'] ?>"><?= sprintf('%02d', $t1['code_val']) ?><?= !empty($t1['name']) ? ' - ' . htmlspecialchars($t1['name']) : '' ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <!-- 2nd -->
                  <div>
                    <label style="display: block; font-weight: 700; font-size: 0.92rem; color: #000000; margin-bottom: 6px;">2nd</label>
                    <select id="simpleTier2" class="form-select" onchange="onSimpleTier2Change();" disabled
                      style="border: 1px solid #71717a; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; font-weight: 600; width: 100%; height: 34px; background: #f1f5f9; opacity: 0.6; cursor: not-allowed;">
                      <option value="" selected disabled>-- 2nd --</option>
                    </select>
                  </div>

                  <!-- 3rd -->
                  <div>
                    <label style="display: block; font-weight: 700; font-size: 0.92rem; color: #000000; margin-bottom: 6px;">3rd</label>
                    <select id="simpleTier3" class="form-select" onchange="onSimpleTier3Change();" disabled
                      style="border: 1px solid #71717a; border-radius: 2px; padding: 4px 8px; font-size: 0.92rem; font-weight: 600; width: 100%; height: 34px; background: #f1f5f9; opacity: 0.6; cursor: not-allowed;">
                      <option value="" selected disabled>-- 3rd --</option>
                    </select>
                  </div>

                </div>

                <!-- Form Action Buttons -->
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid #e2e8f0; justify-content: flex-end;">
                  <button type="button" class="btn btn-secondary" onclick="resetSimpleCodeForm();"
                    style="background: #e2e8f0; border: 1px solid #71717a; color: #000000; font-size: 0.88rem; font-weight: 500; padding: 4px 14px; cursor: pointer; border-radius: 2px;">
                    Reset
                  </button>
                  <button type="button" class="btn btn-primary" onclick="openSimpleRequestModal();"
                    style="background: linear-gradient(180deg, #8ba5c4 0%, #617d9e 100%); color: #ffffff; border: 1px solid #486585; padding: 4px 16px; border-radius: 2px; font-size: 0.88rem; font-weight: 700; cursor: pointer;">
                    Request Code
                  </button>
                </div>
              </div>
            </form>

          </div>

          <!-- My Station's Submitted Code Creation Requests Card (Always visible to track requested codes and add approved codes) -->
          <div class="card" id="myStationRequestsCard" style="padding: 0; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 960px; margin: 0 auto 24px auto; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
              <div>
                <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0;">My Station's Code Creation Requests</h4>
                <span style="font-size: 0.75rem; color: #64748b;">
                  Track approval status of requested item codes submitted by <strong><?= htmlspecialchars($adminDetails['location_name'] ?? 'Victoria Power Station (831)') ?></strong>
                </span>
              </div>
              <button type="button" class="btn btn-secondary btn-sm" onclick="if(typeof loadStationRequests==='function') loadStationRequests();"
                style="font-size: 0.75rem; font-weight: 600; padding: 3px 10px; height: 28px; border-radius: 3px; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer;" title="Refresh Requests List">
                Refresh Requests
              </button>
            </div>

            <div style="overflow-x: auto; max-height: 480px; overflow-y: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; margin-bottom: 0;">
                <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 2; border-bottom: 1px solid #cbd5e1;">
                  <tr>
                    <th style="padding: 6px 10px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 140px;">ITEM CODE</th>
                    <th style="padding: 6px 10px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">ITEM DESCRIPTION &amp; SPECS</th>
                    <th style="padding: 6px 10px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 70px;">UOM</th>
                    <th style="padding: 6px 10px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 130px;">REQUESTED AT</th>
                    <th style="padding: 6px 10px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 150px;">STATUS</th>
                    <th style="padding: 6px 10px; text-align: right; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 140px;">ACTION</th>
                  </tr>
                </thead>
                <tbody id="stationRequestsTableBody">
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 20px; color: #64748b; font-size: 0.8125rem;">
                      Loading request status logs...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Issue Categories & Destination Codes Management (Staff View) -->
        <section id="tab-user-issue-codes" class="user-tab-pane">
          <?php include APP_PATH . '/../views/components/issue_codes_manager.php'; ?>
        </section>

      </main>

      <footer style="text-align: center; padding: 20px; font-size: 0.8rem; color: #64748b;">
        &copy; <?= date('Y') ?> Electricity Generation Lanka (Pvt) Ltd. FIFO Inventory &amp; Operations Portal.
      </footer>

    </div>

  <?php else: ?>
    <!-- =======================================================================
     SUPER ADMIN / ADMIN CONSOLE (LEFT SIDEBAR NAVIGATION)
     ======================================================================= -->
    <div class="app-layout">

      <!-- Left Corner Sidebar Navigation -->
      <aside class="app-sidebar" id="appSidebar">
        <script>
          (function() {
            try {
              if (localStorage.getItem('admin_sidebar_collapsed') === '1') {
                document.getElementById('appSidebar')?.classList.add('collapsed');
              }
            } catch (e) {}
          })();
        </script>

        <!-- Sidebar Brand Header -->
        <div class="sidebar-brand-header">
          <div class="sidebar-brand-content">
            <img src="<?= $base ?>/assets/images/EGL-NEW.png" alt="EGL Logo" class="sidebar-brand-logo">
            <div class="sidebar-brand-titles">
              <strong>Electricity Generation</strong>
              <span><?= $isSuperAdmin ? 'Super Admin Hub' : ($isCodeAdmin ? 'Code Management Hub' : ($isAccountant ? 'Accounts & Codes Hub' : 'Admin Console')) ?></span>
            </div>
          </div>
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebarCollapse();" title="Collapse / Expand Sidebar" aria-label="Toggle Sidebar">
            <svg class="toggle-icon-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>
        </div>

        <!-- Vertical Navigation Tabs -->
        <div class="sidebar-nav-container">
          <?php if ($canAccessMasterCodes): ?>
            <button type="button" class="sidebar-tab-btn <?= ($defaultActiveTab === 'tab-define-code') ? 'active' : '' ?>"
              data-tab-target="tab-define-code" title="Add / Update Master Codes">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                  <line x1="12" y1="18" x2="12" y2="12"></line>
                  <line x1="9" y1="15" x2="15" y2="15"></line>
                </svg>
              </span>
              <span class="tab-label">Add / Update Master Codes</span>
              <span class="badge" style="margin-left: auto; background: #ea580c; color: #fff; padding: 2px 6px; font-size: 0.68rem; font-weight: 800; border-radius: 4px;">Direct</span>
            </button>

            <button type="button" class="sidebar-tab-btn <?= ($defaultActiveTab === 'tab-code-approvals') ? 'active' : '' ?>"
              data-tab-target="tab-code-approvals" title="Code Management & Approvals">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                  <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                  <path d="m9 14 2 2 4-4"></path>
                </svg>
              </span>
              <span class="tab-label">Code Management &amp; Approvals</span>
              <?php if ($allPendingCount > 0): ?>
                <span class="badge badge-danger" style="margin-left: auto; padding: 2px 7px; font-size: 0.72rem; border-radius: 10px;"><?= $allPendingCount ?></span>
              <?php endif; ?>
            </button>

            <button type="button" class="sidebar-tab-btn <?= ($defaultActiveTab === 'tab-master-codes') ? 'active' : '' ?>"
              data-tab-target="tab-master-codes" title="Registered Master Codes">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                  <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                  <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                </svg>
              </span>
              <span class="tab-label">Registered Master Codes</span>
            </button>
          <?php endif; ?>

          <?php if ($canAccessIssueCodes): ?>
            <button type="button" class="sidebar-tab-btn <?= ($defaultActiveTab === 'tab-issue-codes') ? 'active' : '' ?>"
              data-tab-target="tab-issue-codes" title="Issue Categories & Codes">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                  <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
              </span>
              <span class="tab-label">Issue Categories &amp; Codes</span>
            </button>
          <?php endif; ?>

          <?php if ($canAccessLocations || $canAccessDepartments): ?>
            <button type="button" class="sidebar-tab-btn <?= ($defaultActiveTab === 'tab-departments') ? 'active' : '' ?>"
              data-tab-target="tab-departments" title="Locations & Departments">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect>
                  <line x1="9" y1="22" x2="9" y2="22.01"></line>
                  <line x1="15" y1="22" x2="15" y2="22.01"></line>
                  <line x1="9" y1="6" x2="9.01" y2="6"></line>
                  <line x1="15" y1="6" x2="15.01" y2="6"></line>
                  <line x1="9" y1="10" x2="9.01" y2="10"></line>
                  <line x1="15" y1="10" x2="15.01" y2="10"></line>
                  <line x1="9" y1="14" x2="9.01" y2="14"></line>
                  <line x1="15" y1="14" x2="15.01" y2="14"></line>
                  <line x1="9" y1="18" x2="9.01" y2="18"></line>
                  <line x1="15" y1="18" x2="15.01" y2="18"></line>
                </svg>
              </span>
              <span class="tab-label">Locations &amp; Departments</span>
            </button>
          <?php endif; ?>

          <?php if ($canAccessUserRoles): ?>
            <button type="button" class="sidebar-tab-btn" data-tab-target="tab-user-roles" title="User Roles">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
              </span>
              <span class="tab-label">User Roles</span>
            </button>
          <?php endif; ?>

          <?php if ($canAccessUsers): ?>
            <button type="button" class="sidebar-tab-btn" data-tab-target="tab-users" title="User Accounts">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
              </span>
              <span class="tab-label">User Accounts</span>
            </button>
          <?php endif; ?>

          <?php if ($canAccessRoles): ?>
            <button type="button" class="sidebar-tab-btn" data-tab-target="tab-roles" title="Role Permissions Matrix">
              <span class="tab-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
              </span>
              <span class="tab-label">Role Permissions Matrix</span>
            </button>
          <?php endif; ?>

          <button type="button" class="sidebar-tab-btn <?= ($defaultActiveTab === 'tab-profile') ? 'active' : '' ?>"
            data-tab-target="tab-profile" title="Admin Profile">
            <span class="tab-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </span>
            <span class="tab-label"><?= $isSuperAdmin ? 'Super Admin Profile' : ($isCodeAdmin ? 'Code Admin Profile' : ($isAccountant ? 'Accountant Profile' : 'Admin Profile')) ?></span>
          </button>
        </div>

        <!-- Sidebar User Footer -->
        <div class="sidebar-footer">
          <div class="sidebar-user-info" title="<?= htmlspecialchars(($adminDetails['full_name'] ?? 'Staff Member') . ' (' . ($adminDetails['role_display_name'] ?? 'Staff User') . ')') ?>">
            <div class="avatar" style="background-color: var(--primary); width: 34px; height: 34px; font-size: 0.85rem; flex-shrink: 0;">
              <?= strtoupper(substr($adminDetails['full_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="sidebar-user-meta">
              <span class="sidebar-user-name"><?= htmlspecialchars($adminDetails['full_name'] ?? 'Staff Member') ?></span>
              <span
                class="sidebar-user-role"><?= htmlspecialchars($adminDetails['role_display_name'] ?? 'Staff User') ?></span>
            </div>
          </div>
          <a href="<?= $base ?>/logout.php" class="btn btn-danger btn-sm sidebar-logout-btn" title="Sign out"
            style="padding: 5px 8px; font-size: 0.75rem; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span class="sidebar-logout-text">Logout</span>
          </a>
        </div>

      </aside>

      <!-- Main Content Body -->
      <div class="app-main">

        <!-- Top App Bar -->
        <header class="app-topbar">
          <div style="display: flex; align-items: center; gap: 12px;">
            <button type="button" class="topbar-toggle-btn" onclick="toggleSidebarCollapse();" title="Toggle Sidebar (Collapse / Expand)" aria-label="Toggle Sidebar">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
              </svg>
            </button>
            <div class="app-topbar-title" id="topbarPageTitle">
              <?= ($defaultActiveTab === 'tab-define-code') ? 'Add & Update Master Codes' : (($defaultActiveTab === 'tab-code-approvals') ? 'Code Management & Approvals' : (($defaultActiveTab === 'tab-departments') ? 'Locations & Departments' : (($defaultActiveTab === 'tab-issue-codes') ? 'Issue Categories & Destination Codes' : ($isSuperAdmin ? 'Super Admin Profile' : ($isCodeAdmin ? 'Code Admin Profile' : ($isAccountant ? 'Accountant Profile' : 'Admin Profile')))))) ?>
            </div>
          </div>

          <div class="app-topbar-right">
            <!-- Live Sri Lankan Date and Time (Hour, Minute, Second) -->
            <div class="sri-lanka-clock-pill" id="slClockContainer">
              <span id="slDateDisplay"><?= date('D, d M Y') ?></span>
              <span class="clock-divider" style="color: #cbd5e1;">|</span>
              <span id="slTimeDisplay"
                style="font-weight: 800; color: #650b2b; font-family: monospace; font-size: 0.9rem;"><?= date('h:i:s A') ?></span>
            </div>

            <a href="<?= $base ?>/logout.php" class="btn btn-danger btn-sm">Sign Out</a>
          </div>
        </header>

        <!-- Tab Content Container -->
        <main class="app-content-body">

          <!-- TAB: CODE MANAGEMENT & APPROVALS (FOR ADMIN CONSOLE) -->
          <?php if ($canAccessMasterCodes): ?>
            <section id="tab-code-approvals"
              class="admin-tab-pane <?= ($defaultActiveTab === 'tab-code-approvals') ? 'active' : '' ?>">

              <!-- KPI Metrics Overview -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div class="card" style="padding: 18px 20px; border-left: 4px solid #650b2b;">
                  <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Master Codes Registered</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 4px;"><?= (int)($masterItemStats['total_master_codes'] ?? 0) ?></div>
                  <div style="font-size: 0.72rem; color: #10b981;">1-19 Tier Taxonomy Active</div>
                </div>

                <div class="card" style="padding: 18px 20px; border-left: 4px solid #dc2626;">
                  <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending Code Approvals</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: <?= ($allPendingCount > 0) ? '#dc2626' : '#0f172a' ?>; margin-top: 4px;">
                    <?= (int)$allPendingCount ?>
                  </div>
                  <div style="font-size: 0.72rem; color: #dc2626;"><?= ($allPendingCount > 0) ? 'Requires Administrator Action' : 'All Clear' ?></div>
                </div>

                <div class="card" style="padding: 18px 20px; border-left: 4px solid #0284c7;">
                  <div style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Station Inventory Allocations</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: #0284c7; margin-top: 4px;"><?= (int)($masterItemStats['station_allocations'] ?? 0) ?></div>
                  <div style="font-size: 0.72rem; color: #64748b;">Deployed across Power Stations</div>
                </div>
              </div>

              <!-- Pending Requests Queue Table -->
              <div class="card" style="margin-bottom: 28px; border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 0;">
                <div style="padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                  <div>
                    <h3 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0;">Code Creation Requests Approval Desk</h3>
                    <div id="adminConsoleReqCount" style="font-size: 0.73rem; color: #64748b; margin-top: 2px;">Managing station code requests</div>
                  </div>

                  <!-- Unified Item Description / Name Live Search Box -->
                  <div style="flex: 1 1 320px; max-width: 480px; position: relative;">
                    <input type="text" id="adminConsoleReqSearchInput"
                      placeholder="Search by item description, code, ref no, station..."
                      oninput="onAdminConsoleReqSearchInput();"
                      style="width: 100%; height: 32px; padding: 4px 30px 4px 10px; font-size: 0.8125rem; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; background: #ffffff; transition: border-color 0.15s;"
                      onfocus="this.style.borderColor='#0284c7';" onblur="this.style.borderColor='#cbd5e1';">
                    <button type="button" onclick="clearAdminConsoleReqSearch();"
                      style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1rem; color: #94a3b8; cursor: pointer; padding: 0; line-height: 1;"
                      title="Clear search">&times;</button>
                  </div>

                  <div style="display: flex; gap: 8px; align-items: center;">
                    <select id="adminConsoleReqFilter" class="form-select" onchange="loadAdminRequestsQueue();"
                      style="font-size: 0.78rem; padding: 3px 8px; height: 32px; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff; font-weight: 600;">
                      <option value="PENDING" selected>Pending Review &amp; Staged</option>
                      <option value="STAGED">Staged (Pending Master)</option>
                      <option value="APPROVED">Approved Requests</option>
                      <option value="REJECTED">Rejected Requests</option>
                      <option value="">All Requests</option>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="loadAdminRequestsQueue();"
                      style="font-size: 0.75rem; font-weight: 600; padding: 0 12px; height: 32px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer;">
                      Refresh Queue
                    </button>
                  </div>
                </div>

                <div style="overflow-x: auto; max-height: 520px; overflow-y: auto;">
                  <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; margin-bottom: 0;">
                    <thead style="position: sticky; top: 0; background: #f1f5f9; z-index: 2; border-bottom: 1px solid #cbd5e1;">
                      <tr>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 140px;">REQUEST REF</th>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 100px;">TIER BRANCH</th>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; width: 90px;">SUGG. SEQ</th>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">ITEM DESCRIPTION / NAME</th>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 70px;">UOM</th>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 160px;">ORIGIN STATION</th>
                        <th style="padding: 5px 8px; text-align: left; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">REQUESTER</th>
                        <th style="padding: 5px 8px; text-align: right; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; width: 110px;">ACTION</th>
                      </tr>
                    </thead>
                    <tbody id="adminConsoleRequestsQueueBody">
                      <tr>
                        <td colspan="8" style="text-align: center; padding: 20px; color: #64748b; font-size: 0.8125rem;">
                          Loading pending approval queue...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

            </section>
          <?php endif; ?>

          <!-- TAB: ADD / UPDATE CODES (EXCEL HIERARCHY MANAGER) -->
          <?php if ($canAccessMasterCodes): ?>
            <section id="tab-define-code"
              class="admin-tab-pane <?= ($defaultActiveTab === 'tab-define-code') ? 'active' : '' ?>">

              <!-- Sub-tab Navigation: Item Search & Add Code -->
              <div style="display: flex; gap: 8px; margin-bottom: 16px; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px;">
                <button type="button" id="adminCodeSubBtnSearch" onclick="switchAdminCodeSubTab('search');" class="btn btn-sm"
                  style="font-size: 0.88rem; font-weight: 700; padding: 7px 20px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; color: #334155; cursor: pointer;">
                  Item Search
                </button>
                <button type="button" id="adminCodeSubBtnAdd" onclick="switchAdminCodeSubTab('add');" class="btn btn-sm"
                  style="font-size: 0.88rem; font-weight: 800; padding: 7px 20px; border-radius: 4px; border: 1px solid #4a071e; background: #650b2b; color: #ffffff; cursor: pointer;">
                  Add Code
                </button>
              </div>

              <!-- Sub-Pane 1: Item Search -->
              <div id="adminCodeSubPaneSearch" style="display: none;">
                <?php
                  $searchPrefix = 'admin';
                  include APP_PATH . '/../views/components/item_search_module.php';
                ?>
              </div>

              <!-- Sub-Pane 2: Add Code (Existing Hierarchy Manager & Wizard) -->
              <div id="adminCodeSubPaneAdd" style="display: block;">

              <!-- Simple Top Toolbar -->
              <div class="card" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px 18px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                  <div>
                    <h2 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">1st, 2nd &amp; 3rd Code Hierarchy Manager</h2>
                    <p style="margin: 2px 0 0 0; font-size: 0.78rem; color: #64748b;">
                      Excel-Format Code Catalog: Define and update 1st, 2nd, and 3rd tier category codes directly in the database without approval.
                    </p>
                  </div>
                  <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <!-- Filter by 1st Code -->
                    <select id="excelTreeTier1Filter" onchange="onExcelTreeFilterChange();" class="form-select"
                      style="font-size: 0.8rem; font-weight: 600; height: 32px; padding: 2px 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff;">
                      <option value="">All 1st Categories</option>
                      <?php foreach ($allTiersGrouped['tier1'] ?? [] as $t1): ?>
                        <option value="<?= (int)$t1['code_val'] ?>">
                          <?= sprintf('%02d.', (int)$t1['code_val']) ?> <?= htmlspecialchars($t1['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>

                    <!-- Quick Search Filter -->
                    <input type="text" id="excelTreeSearchInput" placeholder="Search code or description..." oninput="onExcelTreeSearchInput();"
                      style="font-size: 0.8rem; height: 32px; padding: 4px 10px; width: 220px; border: 1px solid #cbd5e1; border-radius: 4px; outline: none;">

                    <button type="button" class="btn btn-secondary btn-sm" onclick="loadExcelHierarchyTree();"
                      style="font-size: 0.78rem; font-weight: 600; height: 32px; padding: 0 12px; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 4px; cursor: pointer;">
                      Refresh
                    </button>

                    <button type="button" onclick="showSimpleAddTierBar(1);" class="btn btn-sm"
                      style="font-size: 0.8rem; font-weight: 800; height: 32px; padding: 0 14px; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; border-radius: 4px; cursor: pointer;">
                      + Add New Code
                    </button>
                  </div>
                </div>

                <!-- Simple Inline Add / Edit Form Bar with 3-Step Progressive Wizard -->
                <div id="simpleTierEditBar" style="display: none; margin-top: 14px; padding: 18px 20px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                  <form id="simpleTierForm" onsubmit="handleWizardSaveCurrentStep(event);">
                    <input type="hidden" id="tierNodeId" name="id" value="">
                    <input type="hidden" id="tierNodeLevel" name="level" value="1">
                    <input type="hidden" id="tierNodeParent" name="parent_id" value="">
                    
                    <!-- Mode Selection Radio Buttons -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
                      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;" id="tierAddRadioGroup">
                        <span style="font-size: 0.78rem; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">Choose Mode:</span>
                        
                        <label id="lblRadioMode1" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: #ffffff; background: #650b2b; border: 1px solid #4a071e; padding: 5px 14px; border-radius: 4px; transition: all 0.2s ease;">
                          <input type="radio" name="tierAddModeRadio" id="radioTierMode1" value="1" onchange="switchTierAddMode(1);" checked style="accent-color: #650b2b; cursor: pointer; width: 15px; height: 15px;">
                          <span>1. 1st Code (Main Topic)</span>
                        </label>

                        <label id="lblRadioMode2" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: #334155; background: #ffffff; border: 1px solid #cbd5e1; padding: 5px 14px; border-radius: 4px; transition: all 0.2s ease;">
                          <input type="radio" name="tierAddModeRadio" id="radioTierMode2" value="2" onchange="switchTierAddMode(2);" style="accent-color: #650b2b; cursor: pointer; width: 15px; height: 15px;">
                          <span>2. 2nd Code (Sub Topic)</span>
                        </label>

                        <label id="lblRadioMode3" style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.82rem; font-weight: 700; color: #334155; background: #ffffff; border: 1px solid #cbd5e1; padding: 5px 14px; border-radius: 4px; transition: all 0.2s ease;">
                          <input type="radio" name="tierAddModeRadio" id="radioTierMode3" value="3" onchange="switchTierAddMode(3);" style="accent-color: #650b2b; cursor: pointer; width: 15px; height: 15px;">
                          <span>3. 3rd Code (Category Items)</span>
                        </label>
                      </div>

                      <div style="display: flex; gap: 8px; align-items: center;">
                        <span id="tierEditBarTitle" style="font-size: 0.8rem; font-weight: 800; color: #0f172a; text-transform: uppercase;">
                          1ST CODE: DEFINE MAIN TOPIC
                        </span>
                        <span style="font-size: 0.72rem; color: #16a34a; font-weight: 700; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 8px; border-radius: 3px;">Direct Save</span>
                      </div>
                    </div>

                    <!-- Active Saved Context Banner (Shows what 1st / 2nd topic is active) -->
                    <div id="tierWizardContextBanner" style="display: none; margin-bottom: 12px; padding: 8px 14px; background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; border-radius: 4px; font-size: 0.82rem; color: #1e40af;">
                      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div>
                          <strong>Active Hierarchy:</strong> <span id="tierWizardContextText">01. ADHESIVE, SEALANT...</span>
                        </div>
                      </div>
                    </div>

                    <!-- Cascading Fields Grid -->
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
                      
                      <!-- Step 1 Dropdown: Select 1st Code (Visible for Mode 2 and Mode 3) -->
                      <div id="tier1StepContainer" style="display: none; flex: 1 1 230px;">
                        <label for="tier1StepSelect" id="tier1StepSelectLabel" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                          1. Select 1st Code (Main Topic) <span style="color:#dc2626;">*</span>
                        </label>
                        <select id="tier1StepSelect" onchange="onStep1Tier1Change();" class="form-select"
                          style="width: 100%; height: 34px; font-size: 0.82rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff;">
                          <option value="">Select 1st Topic...</option>
                        </select>
                      </div>

                      <!-- Step 2 Dropdown: Select 2nd Code (Visible for Mode 3 only) -->
                      <div id="tier2StepContainer" style="display: none; flex: 1 1 230px;">
                        <label for="tier2StepSelect" id="tier2StepSelectLabel" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                          2. Select 2nd Code (Sub Topic) <span style="color:#dc2626;">*</span>
                        </label>
                        <select id="tier2StepSelect" onchange="onStep2Tier2Change();" class="form-select"
                          style="width: 100%; height: 34px; font-size: 0.82rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; background: #ffffff;">
                          <option value="">Select 2nd Topic...</option>
                        </select>
                      </div>

                      <!-- Code No. Input -->
                      <div style="width: 95px;">
                        <label for="tierNodeCodeVal" id="tierCodeValLabel" style="display: block; font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px;">
                          Code No. <span style="color:#dc2626;">*</span>
                        </label>
                        <input type="number" id="tierNodeCodeVal" name="code_val" min="1" max="99" placeholder="01" required
                          oninput="updateLiveCodePreview();"
                          style="width: 100%; height: 34px; font-family: monospace; font-size: 0.95rem; font-weight: 800; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 4px; background: #ffffff;">
                      </div>

                      <!-- Description / Name Input with Live Spelling Assistant -->
                      <div style="flex: 2 1 280px; position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                          <label for="tierNodeName" id="tierNodeNameLabel" style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin: 0;">
                            1st Main Topic Name <span style="color:#dc2626;">*</span>
                          </label>
                          <span id="tierSpellingIndicator" style="font-size: 0.68rem; font-weight: 700; color: #16a34a;">
                            Clean
                          </span>
                        </div>
                        <input type="text" id="tierNodeName" name="name" placeholder="e.g. ADHESIVE, SEALANT, BONDING COMPOUND" required
                          oninput="onTierNodeNameInput(event);" autocomplete="off"
                          style="width: 100%; height: 34px; font-size: 0.84rem; font-weight: 700; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 10px; text-transform: uppercase; outline: none; background: #ffffff;">

                        <!-- Simple Spelling Suggestion Chip -->
                        <div id="tierSpellingChip" style="display: none; position: absolute; top: 100%; left: 0; margin-top: 2px; z-index: 10; background: #fef3c7; border: 1px solid #fde68a; border-radius: 3px; padding: 2px 8px; font-size: 0.72rem; color: #92400e;">
                          Did you mean: <strong id="tierSpellingSugText" style="cursor: pointer; text-decoration: underline;" onclick="applyTierSpelling();">BEARING</strong>? (Click to apply)
                        </div>
                      </div>

                      <!-- Generated Prefix Badge -->
                      <div style="background: #e2e8f0; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px; text-align: center;">
                        <span style="display: block; font-size: 0.64rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Prefix</span>
                        <span id="tierLivePreviewBadge" style="font-family: monospace; font-size: 0.84rem; font-weight: 800; color: #0f172a;">01.</span>
                      </div>

                      <!-- Wizard Action Buttons -->
                      <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <!-- Primary Save & Advance Button -->
                        <button type="submit" id="btnSaveTierNode" class="btn btn-sm"
                          style="height: 34px; padding: 0 16px; background: #16a34a; color: #ffffff; font-weight: 800; border: 1px solid #15803d; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                          Save 1st Code &amp; Next ➔
                        </button>

                        <!-- Save & Finish Button (Allows saving 1st code only or 1st + 2nd code only) -->
                        <button type="button" id="btnSaveOnlyDone" onclick="handleSaveAndFinish(event);" class="btn btn-sm"
                          style="height: 34px; padding: 0 14px; background: #0284c7; color: #ffffff; font-weight: 700; border: 1px solid #0369a1; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                          Save 1st Code Only (Done)
                        </button>

                        <!-- Submit All & Done Button (For Step 3) -->
                        <button type="button" id="btnSubmitAllDone" onclick="finishCodeWizard();" class="btn btn-sm"
                          style="display: none; height: 34px; padding: 0 16px; background: #650b2b; color: #ffffff; font-weight: 800; border: 1px solid #4a071e; border-radius: 4px; cursor: pointer; white-space: nowrap;">
                          Done / Finish
                        </button>

                        <button type="button" onclick="hideSimpleAddTierBar();" class="btn btn-secondary btn-sm"
                          style="height: 34px; padding: 0 10px; font-size: 0.78rem; font-weight: 600; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 4px; cursor: pointer;">
                          Cancel
                        </button>
                      </div>

                    </div>
                  </form>
                </div>
              </div>

              <!-- Excel Spreadsheet Table Container -->
              <div class="card" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 0; overflow: hidden; margin-bottom: 24px;">
                <div style="overflow-x: auto; max-height: 720px; overflow-y: auto;">
                  <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                    <thead style="position: sticky; top: 0; background: #000000; color: #ffffff; z-index: 2; border-bottom: 2px solid #334155;">
                      <tr>
                        <th style="padding: 6px 8px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 80px; white-space: nowrap;">1ST CODE</th>
                        <th style="padding: 6px 8px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 80px; white-space: nowrap;">2ND CODE</th>
                        <th style="padding: 6px 8px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 80px; white-space: nowrap;">3RD CODE</th>
                        <th style="padding: 6px 10px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 120px; white-space: nowrap;">CODE</th>
                        <th style="padding: 6px 12px; text-align: left; font-size: 0.75rem; font-weight: 800; color: #ffffff; border: 1px solid #334155;">CATEGORY / ITEM DESCRIPTION</th>
                        <th style="padding: 6px 10px; text-align: center; font-size: 0.75rem; font-weight: 800; color: #ffffff; border: 1px solid #334155; width: 110px; white-space: nowrap;">ACTION</th>
                      </tr>
                    </thead>
                    <tbody id="excelHierarchyTableBody">
                      <tr>
                        <td colspan="6" style="text-align: center; padding: 28px; color: #64748b; font-size: 0.84rem;">
                          Loading Excel code hierarchy...
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              </div> <!-- End #adminCodeSubPaneAdd -->

              <!-- Confirm Delete Modal (Type DELETE to Confirm) -->
              <div class="modal-backdrop" id="deleteTierCategoryModal">
                <div class="modal-dialog" style="max-width: 460px;">
                  <div class="modal-header" style="background: #fef2f2; border-bottom: 1px solid #fecaca; padding: 14px 18px;">
                    <h3 class="modal-title" style="color: #991b1b; font-size: 1rem; font-weight: 800; display: flex; align-items: center; gap: 8px; margin: 0;">
                      <span style="font-size: 1.1rem;">⚠️</span> Confirm Permanent Deletion
                    </h3>
                    <button type="button" class="modal-close" onclick="closeDeleteTierModal();" style="border: none; background: transparent; font-size: 1.4rem; color: #991b1b; cursor: pointer;">&times;</button>
                  </div>

                  <div class="modal-body" style="padding: 18px 20px;">
                    <input type="hidden" id="deleteTierTargetId" value="">
                    <p style="font-size: 0.84rem; color: #334155; margin: 0 0 12px 0;">
                      You are about to permanently delete the following category code:
                    </p>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; border-radius: 4px; padding: 10px 14px; margin-bottom: 16px;">
                      <div id="deleteTierTargetPrefix" style="font-family: monospace; font-size: 0.82rem; font-weight: 800; color: #ea580c;">01.01.01.</div>
                      <div id="deleteTierTargetName" style="font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-top: 2px;">INSTANT ADHESIVE</div>
                    </div>

                    <p style="font-size: 0.78rem; color: #dc2626; margin: 0 0 10px 0; font-weight: 600;">
                      ⚠️ Warning: This action cannot be undone. If this code contains sub-categories, deletion will be blocked.
                    </p>

                    <div style="margin-bottom: 6px;">
                      <label for="deleteTierConfirmInput" style="display: block; font-size: 0.76rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">
                        To confirm, type <span style="color: #dc2626; font-weight: 800; letter-spacing: 0.5px;">DELETE</span> below:
                      </label>
                      <input type="text" id="deleteTierConfirmInput" class="form-control" placeholder="Type DELETE" autocomplete="off" oninput="onDeleteTierConfirmInput();"
                        style="width: 100%; height: 38px; font-family: monospace; font-size: 0.95rem; font-weight: 800; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 10px; text-transform: uppercase; outline: none; background: #ffffff;">
                    </div>
                  </div>

                  <div class="modal-footer" style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeDeleteTierModal();"
                      style="padding: 6px 14px; font-size: 0.82rem; font-weight: 600; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 4px; cursor: pointer;">
                      Cancel
                    </button>
                    <button type="button" id="btnConfirmDeleteTier" class="btn btn-danger btn-sm" onclick="executeConfirmedTierDelete();" disabled
                      style="padding: 6px 16px; font-size: 0.82rem; font-weight: 800; background: #ef4444; border: 1px solid #dc2626; color: #ffffff; border-radius: 4px; opacity: 0.4; cursor: not-allowed;">
                      🗑 Delete Code
                    </button>
                  </div>
                </div>
              </div>

            </section>
          <?php endif; ?>

          <!-- TAB: REGISTERED MASTER CODES -->
          <?php if ($canAccessMasterCodes): ?>
            <section id="tab-master-codes"
              class="admin-tab-pane <?= ($defaultActiveTab === 'tab-master-codes') ? 'active' : '' ?>">
              
              <!-- Stats Row -->
              <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                  <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Registered Master Codes</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: #650b2b; margin-top: 4px;" id="statTotalMasterCodes"><?= $totalMasterCodesCount ?></div>
                  <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">Central Master Library</div>
                </div>
                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                  <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">1st Category Groups</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: #0284c7; margin-top: 4px;"><?= count($allTiersGrouped['tier1'] ?? []) ?></div>
                  <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">Main classification categories</div>
                </div>
                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                  <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Pending Approvals</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: #d97706; margin-top: 4px;"><?= $allPendingCount ?></div>
                  <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">Awaiting Code Admin review</div>
                </div>
                <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                  <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Station Allocations</div>
                  <div style="font-size: 1.6rem; font-weight: 800; color: #16a34a; margin-top: 4px;"><?= $masterItemStats['station_allocations'] ?? 0 ?></div>
                  <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">Allocated in power stations</div>
                </div>
              </div>

              <!-- Master Codes Card with Search & Filters -->
              <div class="card" style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 24px; padding: 0 !important; width: 100%;">
                <div class="card-header" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                  <div>
                    <h3 class="card-title" style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a;">Registered Master Codes Library</h3>
                  </div>
                  <div style="display: flex; gap: 8px; align-items: center;">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="reloadMasterCodesTable();"
                      style="font-size: 0.78rem; font-weight: 600; padding: 4px 12px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer;">
                      Refresh List
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="document.querySelector('[data-tab-target=\'tab-code-approvals\']')?.click();"
                      style="font-size: 0.78rem; font-weight: 700; padding: 4px 14px; border-radius: 4px; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; cursor: pointer;">
                      + Go to Generator / Approvals
                    </button>
                  </div>
                </div>

                <!-- Filter Toolbar -->
                <div style="padding: 12px 18px; background: #ffffff; border-bottom: 1px solid #f1f5f9; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
                  <div style="flex: 1 1 240px; position: relative;">
                    <input type="text" id="masterCodeSearchInput" placeholder="Search by item description, code, raw code, station..."
                      oninput="onMasterCodeSearchInput();"
                      style="width: 100%; height: 34px; padding: 6px 12px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 4px; outline: none;">
                  </div>
                  <div style="width: 140px;">
                    <select id="masterCodeFilterTier1" class="form-select" onchange="onMasterCodeTier1FilterChange();"
                      style="width: 100%; height: 34px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px;">
                      <option value="">All 1st</option>
                      <?php foreach ($allTiersGrouped['tier1'] ?? [] as $t1Item): ?>
                        <option value="<?= $t1Item['code_val'] ?>" data-db-id="<?= $t1Item['id'] ?>"><?= sprintf('%02d', $t1Item['code_val']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div style="width: 140px;">
                    <select id="masterCodeFilterTier2" class="form-select" onchange="onMasterCodeTier2FilterChange();" disabled
                      style="width: 100%; height: 34px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 8px; background: #f1f5f9; opacity: 0.7;">
                      <option value="">All 2nd</option>
                    </select>
                  </div>
                  <div>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="resetMasterCodeFilters();"
                      style="font-size: 0.78rem; height: 34px; padding: 0 12px; border: 1px solid #cbd5e1; background: #f8fafc; cursor: pointer; border-radius: 4px;">
                      Reset Filters
                    </button>
                  </div>
                  <!-- 3 Radio Filter Options (Master Added / Not Added / Both) -->
                  <div style="display: inline-flex; align-items: center; gap: 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 12px; height: 34px; font-size: 0.8125rem; font-weight: 600;">
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #c2410c;" title="Show only codes added to Central Master DB (Orange)">
                      <input type="radio" name="masterCodeStatusRadio" value="master_added" onchange="onMasterCodeStatusFilterChange();" style="cursor: pointer; accent-color: #ea580c;">
                      Master Added
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #475569;" title="Show only approved codes not added to master table">
                      <input type="radio" name="masterCodeStatusRadio" value="pending_master" onchange="onMasterCodeStatusFilterChange();" style="cursor: pointer; accent-color: #0284c7;">
                      Not Added
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; color: #0f172a;" title="Show both master added and pending codes">
                      <input type="radio" name="masterCodeStatusRadio" value="both" checked onchange="onMasterCodeStatusFilterChange();" style="cursor: pointer; accent-color: #0284c7;">
                      Both
                    </label>
                  </div>
                  <div style="margin-left: auto; font-size: 0.82rem; color: #475569;" id="masterCodeResultCount">
                    Showing <strong id="masterCodeResultCountText">1 - <?= min(count($allMasterItems), $initialPageSize) ?> of <?= number_format($totalLibraryCount) ?></strong> master codes
                  </div>
                </div>

                <style>
                  #masterCodesTableBody tr:hover {
                    background-color: #e2e8f0 !important;
                  }
                  .master-page-btn {
                    padding: 4px 10px;
                    min-width: 32px;
                    height: 30px;
                    font-size: 0.78rem;
                    border: 1px solid #cbd5e1;
                    background: #ffffff;
                    color: #334155;
                    border-radius: 4px;
                    cursor: pointer;
                    font-weight: 600;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.15s ease;
                  }
                  .master-page-btn:hover:not(:disabled) {
                    background: #f1f5f9;
                    border-color: #94a3b8;
                    color: #0f172a;
                  }
                  .master-page-btn.active {
                    background: #0284c7 !important;
                    border-color: #0284c7 !important;
                    color: #ffffff !important;
                    font-weight: 700;
                  }
                  .master-page-btn:disabled {
                    opacity: 0.45;
                    cursor: not-allowed;
                    background: #f8fafc;
                  }
                </style>

                <!-- Table Container -->
                <div style="overflow-x: auto; max-height: 620px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 4px 4px 0 0; width: 100%;">
                  <table class="data-table" id="masterCodesTable" style="width: 100%; border-collapse: collapse; font-size: 0.8125rem; margin: 0; background: #ffffff;">
                    <thead style="position: sticky; top: 0; background: #facc15; z-index: 2; border-bottom: 2px solid #ca8a04;">
                      <tr>
                        <th style="padding: 6px 4px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 45px; white-space: nowrap;">First C... <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 4px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 48px; white-space: nowrap;">Second C... <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 4px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 45px; white-space: nowrap;">Third C... <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 4px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 48px; white-space: nowrap;">Forth C... <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 8px; text-align: left; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; min-width: 170px;">Description <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 4px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 44px; white-space: nowrap;">UoM <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 6px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 95px; white-space: nowrap; background: #eab308;">New Code <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 8px; text-align: left; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; min-width: 170px;">Master Database Name <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 4px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 55px; white-space: nowrap;">Master DB Uom <span style="font-size:0.65rem;">▼</span></th>
                        <th style="padding: 6px 6px; text-align: center; font-size: 0.74rem; font-weight: 800; color: #000000; border: 1px solid #eab308; width: 135px; white-space: nowrap;">Action</th>
                      </tr>
                    </thead>
                    <tbody id="masterCodesTableBody">
                      <?php if (empty($allMasterItems)): ?>
                        <tr>
                          <td colspan="10" style="text-align: center; padding: 28px; color: #94a3b8; font-size: 0.88rem;">
                            No master codes registered in the system yet.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php 
                        $rowIdx = 0;
                        foreach ($allMasterItems as $it): 
                          $rowIdx++;
                          $t1Str = sprintf('%02d', $it['tier1']);
                          $t2Str = sprintf('%02d', $it['tier2']);
                          $t3Str = sprintf('%02d', $it['tier3']);
                          $t4Str = sprintf('%03d', $it['tier4_seq']);
                          $rawCode = !empty($it['raw_code']) ? $it['raw_code'] : ($t1Str . $t2Str . $t3Str . $t4Str);
                          $isMasterAdded = !empty($it['is_added_to_master']);
                          $reqId = $it['request_id'] ?? 0;
                          $bgRow = ($rowIdx % 2 === 0) ? '#eef6fc' : '#f8fafc';
                        ?>
                          <tr style="border-bottom: 1px solid #d1d5db; background: <?= $bgRow ?>;">
                            <!-- FIRST CODE -->
                            <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #334155; border: 1px solid #cbd5e1;">
                              <?= $t1Str ?>
                            </td>
                            <!-- SECOND CODE -->
                            <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #334155; border: 1px solid #cbd5e1;">
                              <?= $t2Str ?>
                            </td>
                            <!-- THIRD CODE -->
                            <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #334155; border: 1px solid #cbd5e1;">
                              <?= $t3Str ?>
                            </td>
                            <!-- FORTH CODE (with Excel text marker) -->
                            <td style="padding: 4px 6px; text-align: center; font-family: monospace; font-size: 0.8rem; font-weight: 600; color: #0f172a; border: 1px solid #cbd5e1; background: linear-gradient(135deg, #16a34a 4px, transparent 4px);">
                              <?= $t4Str ?>
                            </td>
                            <!-- DESCRIPTION -->
                            <td style="padding: 4px 8px; font-weight: 600; color: #0f172a; border: 1px solid #cbd5e1; text-transform: uppercase; word-break: break-word;">
                              <div style="display: flex; justify-content: space-between; align-items: center; gap: 4px;">
                                <span><?= htmlspecialchars($it['orig_description'] ?? $it['item_name'] ?? '') ?></span>
                                <?php if ($isCodeAdmin): ?>
                                  <button type="button" class="btn-search-globe"
                                    data-query="<?= htmlspecialchars($it['orig_description'] ?? $it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    onclick="openGoogleSearch(this, event);"
                                    style="background: transparent; border: none; cursor: pointer; font-size: 0.78rem; padding: 0 2px; color: #0284c7;"
                                    title="Search original description on Google in a new tab">
                                    🌐
                                  </button>
                                <?php endif; ?>
                              </div>
                            </td>
                            <!-- UOM -->
                            <td style="padding: 4px 6px; text-align: center; color: #475569; font-size: 0.78rem; border: 1px solid #cbd5e1; text-transform: uppercase;">
                              <?= htmlspecialchars($it['orig_uom'] ?? $it['uom'] ?? 'NO') ?>
                            </td>
                            <!-- NEW CODE (ORANGE HIGHLIGHT FOR MASTER ADDED ITEMS ONLY) -->
                            <td style="padding: 3px 6px; text-align: center; border: 1px solid #cbd5e1; background: <?= $isMasterAdded ? '#ea580c' : 'transparent' ?>;">
                              <?php if ($isMasterAdded): ?>
                                <span style="display: block; width: 100%; color: #ffffff; font-family: monospace; font-size: 0.82rem; font-weight: 700; padding: 2px 4px; letter-spacing: 0.5px;">
                                  <?= htmlspecialchars($rawCode) ?>
                                </span>
                              <?php else: ?>
                                <span style="display: block; width: 100%; color: #94a3b8; font-family: monospace; font-size: 0.82rem; font-weight: 500;">
                                  <?= htmlspecialchars($rawCode) ?>
                                </span>
                              <?php endif; ?>
                            </td>
                            <!-- MASTER DATABASE NAME -->
                            <td style="padding: 4px 8px; font-weight: 600; color: #1e293b; border: 1px solid #cbd5e1; text-transform: uppercase; word-break: break-word;">
                              <?php if ($isMasterAdded): ?>
                                <?= htmlspecialchars($it['master_db_name'] ?? $it['item_name'] ?? '') ?>
                              <?php else: ?>
                                <span style="color: #94a3b8; font-style: italic;">(Pending Master Commit)</span>
                              <?php endif; ?>
                            </td>
                            <!-- MASTER DB UOM -->
                            <td style="padding: 4px 6px; text-align: center; color: #475569; font-size: 0.78rem; border: 1px solid #cbd5e1; text-transform: uppercase;">
                              <?= htmlspecialchars($it['master_db_uom'] ?? ($isMasterAdded ? ($it['uom'] ?? 'NO') : '-')) ?>
                            </td>
                            <!-- ACTION / STATUS -->
                            <td style="padding: 4px 6px; text-align: center; border: 1px solid #cbd5e1; white-space: nowrap;">
                              <div style="display: inline-flex; gap: 4px; align-items: center; justify-content: center;">
                                <?php if ($isMasterAdded): ?>
                                  <span style="font-size: 0.7rem; font-weight: 700; color: #15803d; background: #dcfce7; border: 1px solid #bbf7d0; padding: 2px 6px; border-radius: 3px;">
                                    ACTIVE
                                  </span>
                                <?php else: ?>
                                  <button type="button" class="btn btn-sm" onclick="executeDirectAddToMaster(<?= (int)$reqId ?>);"
                                    style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #ea580c; color: #ffffff; border: 1px solid #c2410c; cursor: pointer; white-space: nowrap;" title="Click to officially commit this code into the Central Master Database">
                                    Add to Master
                                  </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm"
                                  data-master-id="<?= (int)($it['master_id'] ?? 0) ?>"
                                  data-request-id="<?= (int)($it['request_id'] ?? 0) ?>"
                                  data-code="<?= htmlspecialchars($rawCode, ENT_QUOTES, 'UTF-8') ?>"
                                  data-name="<?= htmlspecialchars($it['master_db_name'] ?? $it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                  data-uom="<?= htmlspecialchars($it['master_db_uom'] ?? ($isMasterAdded ? ($it['uom'] ?? 'NOS') : ($it['orig_uom'] ?? 'NOS')), ENT_QUOTES, 'UTF-8') ?>"
                                  data-orig-desc="<?= htmlspecialchars($it['orig_description'] ?? $it['item_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                  onclick="openUpdateMasterItemModal(this);"
                                  style="font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 3px; background: #0284c7; color: #ffffff; border: 1px solid #0369a1; cursor: pointer; white-space: nowrap;" title="Update Name and Unit of Measurement">
                                  Update
                                </button>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <!-- Master Codes Pagination Bar -->
                <div id="masterCodesPaginationContainer" style="padding: 10px 18px; background: #f8fafc; border-top: 1px solid #cbd5e1; border-radius: 0 0 4px 4px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; font-size: 0.82rem;">
                  <div style="display: flex; align-items: center; gap: 10px; color: #475569;">
                    <span>Rows per page:</span>
                    <select id="masterCodePageSizeSelect" onchange="onMasterCodePageSizeChange(this.value);" style="padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; background: #ffffff; cursor: pointer;">
                      <option value="50" selected>50</option>
                      <option value="100">100</option>
                      <option value="250">250</option>
                      <option value="500">500</option>
                    </select>
                    <span id="masterCodePaginationInfo" style="font-weight: 600; color: #1e293b;">Showing 1 - <?= min(count($allMasterItems), $initialPageSize) ?> of <?= number_format($totalLibraryCount) ?></span>
                  </div>
                  <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;" id="masterCodePaginationButtons">
                    <!-- Pagination buttons injected by JS -->
                  </div>
                  <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="color: #64748b;">Jump to page:</span>
                    <input type="number" id="masterCodeJumpPageInput" min="1" max="<?= ceil($totalLibraryCount / $initialPageSize) ?>" style="width: 65px; height: 30px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem;" placeholder="1" onkeydown="if(event.key==='Enter') jumpToMasterCodePage();">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="jumpToMasterCodePage();" style="padding: 3px 10px; font-size: 0.78rem; height: 30px; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer; border-radius: 4px; font-weight: 600;">Go</button>
                  </div>
                </div>
              </div>

            </section>
          <?php endif; ?>

          <!-- TAB: ISSUE CATEGORIES & DESTINATION CODES (ACCOUNTANT) -->
          <?php if ($canAccessIssueCodes): ?>
            <section id="tab-issue-codes" class="admin-tab-pane <?= ($defaultActiveTab === 'tab-issue-codes') ? 'active' : '' ?>">
              <?php include APP_PATH . '/../views/components/issue_codes_manager.php'; ?>
            </section>
          <?php endif; ?>

          <!-- TAB: LOCATIONS & DEPARTMENTS -->
          <?php if ($canAccessLocations || $canAccessDepartments): ?>
            <section id="tab-departments"
              class="admin-tab-pane <?= ($defaultActiveTab === 'tab-departments') ? 'active' : '' ?>">

              <!-- Locations Table -->
              <div class="card" style="margin-bottom: 28px;">
                <div class="card-header">
                  <h3 class="card-title">Power Stations &amp; Operational Locations</h3>
                  <button type="button" class="btn btn-secondary" data-open-modal="createLocationModal"
                    onclick="window.openModal('createLocationModal')">
                    + Add Location
                  </button>
                </div>

                <div style="overflow-x: auto;">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Station / Location Name</th>
                        <th>Code</th>
                        <th>Assigned Depts</th>
                        <th>Staff</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="locationsTableBody">
                      <?php if (empty($locations)): ?>
                        <tr id="emptyLocationsRow">
                          <td colspan="6" style="text-align: center; padding: 24px 16px; color: #94a3b8; font-size: 0.88rem;">
                            No locations or power stations found.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($locations as $loc): ?>
                          <tr id="loc-row-<?= $loc['id'] ?>">
                            <td><strong
                                style="color: #0f172a; font-size: 0.9rem;"><?= htmlspecialchars($loc['name']) ?></strong></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($loc['code']) ?></span></td>
                            <td><span style="font-weight: 700; color: #650b2b;"><?= (int) $loc['department_count'] ?></span>
                              Depts</td>
                            <td><span><?= (int) $loc['staff_count'] ?></span> Users</td>
                            <td>
                              <span class="badge <?= ($loc['status'] === 'active') ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst(htmlspecialchars($loc['status'])) ?>
                              </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                              <button class="btn btn-secondary btn-sm"
                                onclick="editLocation(<?= $loc['id'] ?>, '<?= addslashes(htmlspecialchars($loc['name'])) ?>', '<?= addslashes(htmlspecialchars($loc['code'])) ?>', '', '<?= htmlspecialchars($loc['status']) ?>')">
                                Edit
                              </button>
                              <button class="btn btn-danger btn-sm" style="margin-left: 4px;"
                                onclick="deleteLocation(<?= $loc['id'] ?>, '<?= addslashes(htmlspecialchars($loc['name'])) ?>')">
                                Delete
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Departments Table -->
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Departments</h3>
                  <div style="display: flex; gap: 10px; align-items: center;">
                    <select id="filterDeptLocation" class="form-select"
                      style="width: auto; font-size: 0.85rem; padding: 6px 12px;">
                      <option value="">All Stations &amp; Locations</option>
                      <?php foreach ($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <button type="button" class="btn btn-primary" data-open-modal="createDepartmentModal"
                      onclick="window.openModal('createDepartmentModal')">
                      + Add Department
                    </button>
                  </div>
                </div>

                <div style="overflow-x: auto;">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Power Station / Location</th>
                        <th>Department Name</th>
                        <th>Code</th>
                        <th>Assigned Staff</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="departmentsTableBody">
                      <?php if (empty($departments)): ?>
                        <tr id="emptyDepartmentsRow">
                          <td colspan="6" style="text-align: center; padding: 24px 16px; color: #94a3b8; font-size: 0.88rem;">
                            No departments defined yet.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                          <tr id="dept-row-<?= $dept['id'] ?>" data-location-id="<?= $dept['location_id'] ?>">
                            <td>
                              <span class="badge badge-primary" style="font-size: 0.75rem; font-weight: 700;">
                                <?= htmlspecialchars($dept['location_name'] ?? 'General Headquarters') ?>
                              </span>
                            </td>
                            <td><strong
                                style="color: #0f172a; font-size: 0.9rem;"><?= htmlspecialchars($dept['name']) ?></strong></td>
                            <td><code><?= htmlspecialchars($dept['code']) ?></code></td>
                            <td style="font-weight: 600; color: #650b2b;"><?= (int) ($dept['user_count'] ?? 0) ?> Members</td>
                            <td>
                              <span class="badge <?= ($dept['status'] === 'active') ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst(htmlspecialchars($dept['status'])) ?>
                              </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                              <button class="btn btn-secondary btn-sm"
                                onclick="editDepartment(<?= $dept['id'] ?>, <?= (int) $dept['location_id'] ?>, '<?= addslashes(htmlspecialchars($dept['name'])) ?>', '<?= addslashes(htmlspecialchars($dept['code'])) ?>', '<?= htmlspecialchars($dept['status']) ?>')">
                                Edit
                              </button>
                              <button class="btn btn-danger btn-sm" style="margin-left: 4px;"
                                onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= addslashes(htmlspecialchars($dept['name'])) ?>')">
                                Delete
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          <?php endif; ?>

          <!-- TAB: USER ROLES & CODE MANAGEMENT -->
          <?php if ($canAccessUserRoles): ?>
            <section id="tab-user-roles" class="admin-tab-pane">
              <div class="card">
                <div class="card-header"
                  style="flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
                  <div>
                    <h3 class="card-title">Defined User Roles &amp; Operational Designations</h3>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">
                      Manage user roles, designations, unique role codes, and tab permissions.
                    </p>
                  </div>

                  <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn btn-primary" data-open-modal="createRoleModal"
                      onclick="window.openModal('createRoleModal')">
                      + Add Role
                    </button>
                  </div>
                </div>

                <div style="overflow-x: auto;">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>Role Title / Name</th>
                        <th>Role Code</th>
                        <th>Role Identifier</th>
                        <th>Assigned Station / Dept</th>
                        <th>Tab Access</th>
                        <th>Assigned Staff</th>
                        <th style="text-align: right;">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="rolesTableBody">
                      <?php if (empty($allRoles)): ?>
                        <tr id="emptyRolesRow">
                          <td colspan="7" style="text-align: center; padding: 24px 16px; color: #94a3b8; font-size: 0.88rem;">
                            No user roles defined yet.
                          </td>
                        </tr>
                      <?php else: ?>
                        <?php
                        $allPermKeys = Permission::getAllPermissionKeys();
                        $totalPerms = count($allPermKeys);
                        foreach ($allRoles as $r):
                          $isCore = in_array((int) $r['id'], [1, 8]);
                          $roleId = (int) $r['id'];
                          $enabledCount = 0;
                          foreach ($allPermKeys as $k) {
                            if (isset($permissionsMatrix[$k][$roleId])) {
                              if ($permissionsMatrix[$k][$roleId] === 1)
                                $enabledCount++;
                            } else {
                              $enabledCount++;
                            }
                          }
                          $roleCodeVal = !empty($r['code']) ? $r['code'] : strtoupper($r['name']);
                        ?>
                          <tr id="role-row-<?= $r['id'] ?>">
                            <td>
                              <strong style="color: #0f172a; font-size: 0.92rem;">
                                <?= htmlspecialchars($r['display_name']) ?>
                              </strong>
                              <?php if (!empty($r['description'])): ?>
                                <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;"><?= htmlspecialchars($r['description']) ?></div>
                              <?php endif; ?>
                            </td>
                            <td>
                              <span class="badge" style="font-family: monospace; font-size: 0.82rem; font-weight: 700; letter-spacing: 0.5px; background: #650b2b; color: #ffffff; padding: 3px 8px; border-radius: 4px;">
                                <?= htmlspecialchars($roleCodeVal) ?>
                              </span>
                            </td>
                            <td>
                              <code><?= htmlspecialchars($r['name']) ?></code>
                            </td>
                            <td>
                              <span style="font-size: 0.82rem; color: #475569;">
                                <?= htmlspecialchars($r['location_name'] ?? 'All Stations (Global)') ?>
                              </span>
                            </td>
                            <td>
                              <button type="button" class="btn btn-secondary btn-sm"
                                style="font-size: 0.76rem; padding: 3px 8px; border-radius: 6px; font-weight: 600;"
                                onclick="openEditRoleModal(<?= htmlspecialchars(json_encode([
                                  'id' => $r['id'],
                                  'name' => $r['name'],
                                  'code' => $roleCodeVal,
                                  'display_name' => $r['display_name'],
                                  'description' => $r['description'] ?? '',
                                  'location_id' => $r['location_id'] ?? '',
                                  'department_id' => $r['department_id'] ?? ''
                                ])) ?>)"
                                title="Configure Tab Access Permissions for this Role">
                                <span style="color: #650b2b; font-weight: 700;"><?= $enabledCount ?>/<?= $totalPerms ?></span>
                                Tabs &bull; Configure
                              </button>
                            </td>
                            <td>
                              <span style="font-weight: 600; color: #650b2b;">
                                <?= (int) ($r['user_count'] ?? 0) ?> Members
                              </span>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                              <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                                <button type="button" class="btn btn-secondary btn-sm"
                                  onclick="openEditRoleModal(<?= htmlspecialchars(json_encode([
                                    'id' => $r['id'],
                                    'name' => $r['name'],
                                    'code' => $roleCodeVal,
                                    'display_name' => $r['display_name'],
                                    'description' => $r['description'] ?? '',
                                    'location_id' => $r['location_id'] ?? '',
                                    'department_id' => $r['department_id'] ?? ''
                                  ])) ?>)">
                                  Edit
                                </button>
                                <?php if ($r['id'] != 1 && (int) ($r['user_count'] ?? 0) === 0): ?>
                                  <button type="button" class="btn btn-danger btn-sm"
                                    onclick="deleteRole(<?= $r['id'] ?>, '<?= addslashes(htmlspecialchars($r['display_name'])) ?>')">
                                    Delete
                                  </button>
                                <?php endif; ?>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          <?php endif; ?>

          <!-- TAB 3: USER MANAGEMENT & ROLES -->
          <?php if ($canAccessUsers): ?>
            <section id="tab-users" class="admin-tab-pane">
              <div class="card">
                <div class="card-header">
                  <div>
                    <h3 class="card-title">User Accounts &amp; Access</h3>
                  </div>

                  <button type="button" class="btn btn-primary" data-open-modal="createUserModal"
                    onclick="window.openModal('createUserModal')">
                    + Add User
                  </button>
                </div>

                <div style="overflow-x: auto;">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th>User Profile</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th style="text-align: right;">Action</th>
                      </tr>
                    </thead>
                    <tbody id="usersTableBody">
                      <?php foreach ($users as $u): ?>
                        <tr id="user-row-<?= $u['id'] ?>">
                          <td>
                            <div class="user-cell">
                              <div class="avatar"
                                style="background-color: <?= htmlspecialchars($u['avatar_color'] ?? '#650b2b') ?>;">
                                <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                              </div>
                              <div>
                                <div class="user-cell-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                <div class="user-cell-email">@<?= htmlspecialchars($u['username']) ?></div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <?php if ($u['role_name'] === 'super_admin'): ?>
                              <span class="badge badge-super-admin"><?= htmlspecialchars($u['role_display_name']) ?></span>
                            <?php elseif ($u['role_name'] === 'code_admin'): ?>
                              <span class="badge" style="background: #0284c7; color: #fff;"><?= htmlspecialchars($u['role_display_name']) ?></span>
                            <?php else: ?>
                              <span class="badge badge-primary"><?= htmlspecialchars($u['role_display_name']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td><span class="badge badge-success"><?= ucfirst(htmlspecialchars($u['status'])) ?></span></td>
                          <td style="color: #64748b; font-size: 0.82rem;"><?= substr($u['created_at'], 0, 10) ?></td>
                          <td style="text-align: right;">
                            <div style="display: inline-flex; gap: 6px; justify-content: flex-end; align-items: center;">
                              <button type="button" class="btn btn-secondary btn-sm" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                                'id' => $u['id'],
                                'full_name' => $u['full_name'],
                                'username' => $u['username'],
                                'email' => $u['email'],
                                'role_id' => $u['role_id'],
                                'department' => $u['department'],
                                'phone' => $u['phone'] ?? '',
                                'status' => $u['status']
                              ]), ENT_QUOTES, 'UTF-8') ?>)">
                                Edit
                              </button>
                              <?php if ($u['id'] !== Auth::id()): ?>
                                <button type="button" class="btn btn-danger btn-sm"
                                  onclick="deleteUser(<?= $u['id'] ?>, '<?= addslashes(htmlspecialchars($u['full_name'])) ?>')">
                                  Delete
                                </button>
                              <?php else: ?>
                                <span
                                  style="font-size: 0.75rem; color: #650b2b; font-weight: 700; padding: 2px 6px; background: #fdf2f5; border-radius: 4px;">Active</span>
                              <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </section>
          <?php endif; ?>

          <!-- TAB: ROLES & PERMISSIONS MATRIX -->
          <?php if ($canAccessRoles): ?>
            <section id="tab-roles" class="admin-tab-pane">

              <div class="card" style="margin-bottom: 24px;">
                <div class="card-header"
                  style="flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between;">
                  <div>
                    <h3 class="card-title">User Permissions &amp; Access Matrix</h3>
                    <div style="display: flex; gap: 8px; align-items: center;">
                      <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Horizontal Scroll:</span>
                      <button type="button" class="btn btn-secondary btn-sm" id="btnScrollMatrixLeft"
                        title="Scroll Matrix Left" style="padding: 4px 10px; font-weight: 700; font-size: 0.8rem;">
                        &larr; Left
                      </button>
                      <button type="button" class="btn btn-secondary btn-sm" id="btnScrollMatrixRight"
                        title="Scroll Matrix Right" style="padding: 4px 10px; font-weight: 700; font-size: 0.8rem;">
                        Right &rarr;
                      </button>
                    </div>

                    <?php
                    $rawMatrixUsers = array_values(array_filter($users, fn($u) => $u['role_name'] !== 'super_admin' && $u['role_id'] != 1));

                    // Build location index maps
                    $locMapById = [];
                    $locMapByCode = [];
                    foreach ($locations as $loc) {
                      $locMapById[(int) $loc['id']] = $loc;
                      if (!empty($loc['code'])) {
                        $locMapByCode[strtoupper(trim($loc['code']))] = $loc;
                      }
                    }

                    // Group users by Station / Location
                    $groupedUsersByStation = [];
                    foreach ($rawMatrixUsers as $u) {
                      $stName = '';

                      // 1. Direct location_id match
                      if (!empty($u['location_id']) && isset($locMapById[(int) $u['location_id']])) {
                        $l = $locMapById[(int) $u['location_id']];
                        $stName = $l['name'] . (!empty($l['code']) ? ' (' . $l['code'] . ')' : '');
                      }

                      // 2. Direct location_name match
                      if (empty($stName) && !empty($u['location_name']) && $u['location_name'] !== 'General') {
                        $stName = $u['location_name'] . (!empty($u['location_code']) ? ' (' . $u['location_code'] . ')' : '');
                      }

                      // 3. Match department field against locations
                      if (empty($stName) && !empty($u['department']) && $u['department'] !== 'General') {
                        $dTrim = strtoupper(trim($u['department']));
                        if (isset($locMapByCode[$dTrim])) {
                          $l = $locMapByCode[$dTrim];
                          $stName = $l['name'] . (!empty($l['code']) ? ' (' . $l['code'] . ')' : '');
                        } else {
                          $stName = $u['department'];
                        }
                      }

                      // 4. Match username prefix (e.g. 834CE -> 834, 833CC -> 833, 832SK -> 832)
                      if (empty($stName) || $stName === 'General') {
                        if (preg_match('/^(\d+)/', $u['username'], $m)) {
                          $uCode = strtoupper(trim($m[1]));
                          if (isset($locMapByCode[$uCode])) {
                            $l = $locMapByCode[$uCode];
                            $stName = $l['name'] . (!empty($l['code']) ? ' (' . $l['code'] . ')' : '');
                          } else {
                            $stName = 'Station ' . $uCode;
                          }
                        }
                      }

                      if (empty($stName)) {
                        $stName = 'General / Headquarters';
                      }

                      if (!isset($groupedUsersByStation[$stName])) {
                        $groupedUsersByStation[$stName] = [];
                      }
                      $groupedUsersByStation[$stName][] = $u;
                    }

                    // Flatten back in station group order
                    $matrixUsers = [];
                    foreach ($groupedUsersByStation as $stName => $stUsers) {
                      foreach ($stUsers as $su) {
                        $matrixUsers[] = $su;
                      }
                    }
                    ?>
                  </div>

                  <!-- USER ACCOUNTS MATRIX TABLE -->
                  <div id="usersMatrixWrap" class="matrix-table-wrap">
                    <table class="matrix-table">
                      <thead>
                        <!-- Tier 1: Station / Location Colspan Headers -->
                        <tr>
                          <th rowspan="2" class="matrix-nav-col"
                            style="vertical-align: middle; background: #f8fafc; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #e2e8f0; font-weight: 700; color: #1e293b;">
                            System Navigation Tab / Sub-Tab
                          </th>
                          <?php if (empty($groupedUsersByStation)): ?>
                            <th style="text-align: center; color: #94a3b8; padding: 12px;">No active users</th>
                          <?php else: ?>
                            <?php foreach ($groupedUsersByStation as $stationName => $stUsers): ?>
                              <th colspan="<?= count($stUsers) ?>"
                                style="text-align: center; background: #f1f5f9; color: #334155; font-weight: 700; font-size: 0.74rem; letter-spacing: 0.5px; border-bottom: 1px solid #cbd5e1; border-left: 2px solid #cbd5e1; padding: 6px 8px; white-space: nowrap;">
                                <?= htmlspecialchars($stationName ?: 'All Stations (Global)') ?>
                              </th>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </tr>
                        <!-- Tier 2: User Account Headers -->
                        <tr>
                          <?php foreach ($groupedUsersByStation as $stationName => $stUsers): ?>
                            <?php foreach ($stUsers as $idx => $u): ?>
                              <th class="matrix-user-col"
                                style="text-align: center; background: #ffffff; padding: 6px 4px; white-space: nowrap; border-bottom: 1px solid #cbd5e1; <?= ($idx === 0) ? 'border-left: 2px solid #cbd5e1;' : '' ?>">
                                <div
                                  style="font-size: 0.82rem; font-weight: 700; color: #0f172a; margin-bottom: 2px; text-transform: uppercase;">
                                  <?= htmlspecialchars($u['username']) ?>
                                </div>
                                <div>
                                  <span class="badge badge-secondary" style="font-size: 0.62rem; padding: 1px 4px;">
                                    <?= htmlspecialchars($u['role_display_name'] ?? 'User') ?>
                                  </span>
                                </div>
                              </th>
                            <?php endforeach; ?>
                          <?php endforeach; ?>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($permissionCategories as $categoryName => $perms): ?>
                          <tr class="matrix-category-row">
                            <td class="matrix-nav-col"
                              style="background: #fdf2f5 !important; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
                              <strong style="color: #650b2b;"><?= htmlspecialchars($categoryName) ?></strong>
                            </td>
                            <?php foreach ($groupedUsersByStation as $stationName => $stUsers): ?>
                              <?php foreach ($stUsers as $idx => $u): ?>
                                <td class="matrix-user-col"
                                  style="background: #fdf2f5 !important; border-bottom: 1px solid #e2e8f0; <?= ($idx === 0) ? 'border-left: 2px solid #cbd5e1;' : 'border-left: 1px solid #f1f5f9;' ?>">
                                </td>
                              <?php endforeach; ?>
                            <?php endforeach; ?>
                          </tr>

                          <?php foreach ($perms as $permKey => $permMeta):
                            $level = $permMeta['level'] ?? 1;
                            $levelClass = 'matrix-level-' . $level;
                            ?>
                            <tr>
                              <td class="<?= $levelClass ?> matrix-nav-col" style="border-right: 1px solid #e2e8f0;">
                                <span class="matrix-perm-title"><?= htmlspecialchars($permMeta['label']) ?></span>
                              </td>
                              <?php foreach ($groupedUsersByStation as $stationName => $stUsers): ?>
                                <?php foreach ($stUsers as $idx => $u):
                                  if (isset($userPermissionsMatrix[$permKey][$u['id']])) {
                                    $isGranted = ($userPermissionsMatrix[$permKey][$u['id']] === 1);
                                  } elseif (isset($permissionsMatrix[$permKey][$u['role_id']])) {
                                    $isGranted = ($permissionsMatrix[$permKey][$u['role_id']] === 1);
                                  } else {
                                    $isGranted = true;
                                  }
                                  ?>
                                  <td class="matrix-user-col"
                                    style="text-align: center; padding: 4px 3px; <?= ($idx === 0) ? 'border-left: 2px solid #f1f5f9;' : '' ?>">
                                    <label class="matrix-switch"
                                      title="Toggle <?= htmlspecialchars($permMeta['label']) ?> for <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($stationName) ?>)">
                                      <input type="checkbox" class="matrix-perm-toggle" data-user-id="<?= $u['id'] ?>"
                                        data-permission-key="<?= htmlspecialchars($permKey) ?>"
                                        data-target-name="<?= htmlspecialchars($u['username']) ?>"
                                        data-perm-label="<?= htmlspecialchars($permMeta['label']) ?>" <?= $isGranted ? 'checked' : '' ?>>
                                      <span class="matrix-slider"></span>
                                    </label>
                                  </td>
                                <?php endforeach; ?>
                              <?php endforeach; ?>
                            </tr>
                          <?php endforeach; ?>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>

                </div>

            </section>
          <?php endif; ?>

          <!-- TAB: ADMIN PROFILE & SECURITY -->
          <section id="tab-profile" class="admin-tab-pane <?= ($defaultActiveTab === 'tab-profile') ? 'active' : '' ?>">
            <div style="display: grid; grid-template-columns: 280px 1fr; gap: 24px;">

              <div class="card" style="text-align: center;">
                <div class="avatar avatar-lg" style="margin: 0 auto 16px; background-color: var(--primary);">
                  <?= strtoupper(substr($adminDetails['full_name'] ?? 'A', 0, 1)) ?>
                </div>
                <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
                  <?= htmlspecialchars($adminDetails['full_name'] ?? 'User') ?>
                </h2>
                <span
                  class="badge badge-super-admin"><?= htmlspecialchars($adminDetails['role_display_name'] ?? 'Staff Member') ?></span>

                <div
                  style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-subtle); text-align: left; font-size: 0.85rem; display: flex; flex-direction: column; gap: 10px;">
                  <div><span style="color: #64748b;">Username:</span>
                    <strong>@<?= htmlspecialchars($adminDetails['username'] ?? '') ?></strong>
                  </div>
                  <div><span style="color: #64748b;">Email:</span>
                    <strong><?= htmlspecialchars($adminDetails['email'] ?? '') ?></strong>
                  </div>
                  <div><span style="color: #64748b;">Department:</span>
                    <strong><?= htmlspecialchars($adminDetails['department'] ?? 'General') ?></strong>
                  </div>
                  <div><span style="color: #64748b;">Status:</span> <span
                      class="badge badge-success"><?= ucfirst(htmlspecialchars($adminDetails['status'] ?? 'Active')) ?></span>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Security &amp; Password Update</h3>
                </div>

                <form id="superAdminPasswordForm">
                  <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

                  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                      <label class="form-label">Current Password *</label>
                      <div class="password-input-wrapper">
                        <input type="password" name="current_password" class="form-control"
                          placeholder="Enter current password" required>
                        <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password"
                          tabindex="-1">
                          <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor"
                            stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18"
                            stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                              d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                            </path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                          </svg>
                        </button>
                      </div>
                    </div>

                    <div class="form-group">
                      <label class="form-label">New Password *</label>
                      <div class="password-input-wrapper">
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters"
                          required>
                        <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password"
                          tabindex="-1">
                          <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor"
                            stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18"
                            stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                              d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                            </path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                          </svg>
                        </button>
                      </div>
                    </div>
                  </div>

                  <div style="text-align: right; margin-top: 10px;">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                  </div>
                </form>
              </div>

            </div>
          </section>

        </main>

      </div>

    </div>
  <?php endif; ?>

  <!-- =======================================================================
     MODALS
     ======================================================================= -->
  <?php if ($isAdmin): ?>

    <!-- 1. Add Location Modal -->
    <div class="modal-backdrop" id="createLocationModal">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Add Location</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="createLocationForm">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Station / Location Name *</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Victoria Power Station" required>
            </div>

            <div class="form-group">
              <label class="form-label">Location Code</label>
              <input type="text" name="code" class="form-control" placeholder="e.g. 833">
            </div>

            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Location</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 2. Edit Location Modal -->
    <div class="modal-backdrop" id="editLocationModal">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Edit Location</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="editLocationForm">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
          <input type="hidden" name="id" value="">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Station / Location Name *</label>
              <input type="text" name="name" class="form-control" required>
            </div>

            <div class="form-group">
              <label class="form-label">Location Code</label>
              <input type="text" name="code" class="form-control" placeholder="e.g. 833">
            </div>

            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Update Location</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 3. Add Department Modal -->
    <div class="modal-backdrop" id="createDepartmentModal">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Add Department</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="createDepartmentForm">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Select Power Station / Location *</label>
              <select name="location_id" id="createDeptLocationSelect" class="form-select" required>
                <?php if (empty($locations)): ?>
                  <option value="" disabled selected>Please add a Location first</option>
                <?php else: ?>
                  <option value="" disabled selected>-- Select Station / Location --</option>
                  <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" data-code="<?= htmlspecialchars($loc['code']) ?>"
                      data-name="<?= htmlspecialchars($loc['name']) ?>">
                      <?= htmlspecialchars($loc['name']) ?> (<?= htmlspecialchars($loc['code']) ?>)
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Department Name *</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Mechanical" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div class="form-group">
                <label class="form-label">Department Code</label>
                <input type="text" name="code" class="form-control" placeholder="e.g. 833">
              </div>

              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="active" selected>Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Department</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 4. Edit Department Modal -->
    <div class="modal-backdrop" id="editDepartmentModal">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Edit Department</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="editDepartmentForm">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
          <input type="hidden" name="id" value="">

          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Power Station / Location *</label>
              <select name="location_id" id="editDeptLocationSelect" class="form-select" required>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= $loc['id'] ?>" data-code="<?= htmlspecialchars($loc['code']) ?>"
                    data-name="<?= htmlspecialchars($loc['name']) ?>">
                    <?= htmlspecialchars($loc['name']) ?> (<?= htmlspecialchars($loc['code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Department Name *</label>
              <input type="text" name="name" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
              <div class="form-group">
                <label class="form-label">Department Code</label>
                <input type="text" name="code" class="form-control">
              </div>

              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Update Department</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 5. Add User Modal -->
    <div class="modal-backdrop" id="createUserModal">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Add New User Account</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="createUserForm">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

          <div class="modal-body">
            <!-- 1. Location Selection -->
            <div class="form-group">
              <label class="form-label">Power Station / Location *</label>
              <select name="location_id" id="createUserLocation" class="form-select" required>
                <option value="" disabled selected>-- Select Location --</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= $loc['id'] ?>">
                    <?= htmlspecialchars($loc['name']) ?> (<?= htmlspecialchars($loc['code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- 2. Role & Account Status Selection -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div class="form-group">
                <label class="form-label">User Role / Designation *</label>
                <select name="role_id" id="createUserRole" class="form-select" required>
                  <option value="" disabled selected>-- Select Role --</option>
                  <?php foreach ($allRoles as $r): ?>
                    <?php if ($r['name'] !== 'super_admin' && $r['id'] != 1): ?>
                      <option value="<?= $r['id'] ?>">
                        <?= htmlspecialchars($r['display_name']) ?>
                      </option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Account Status</label>
                <select name="status" class="form-select">
                  <option value="active" selected>Active</option>
                  <option value="pending">Pending</option>
                  <option value="suspended">Suspended</option>
                </select>
              </div>
            </div>

            <!-- 3. User Credentials -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div class="form-group">
                <label class="form-label">Corporate Username *</label>
                <input type="text" name="username" class="form-control" placeholder="e.g. rsilva" required>
              </div>

              <div class="form-group">
                <label class="form-label">Access Password *</label>
                <div class="password-input-wrapper">
                  <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                  <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password"
                    tabindex="-1">
                    <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor"
                      stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18"
                      stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path
                        d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                      </path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Create User</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 6. Edit User Modal -->
    <div class="modal-backdrop" id="editUserModal" data-keep-values="true">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Edit User Account</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="editUserForm">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
          <input type="hidden" name="id" id="editUserId">

          <div class="modal-body">
            <!-- 1. Location Selection -->
            <div class="form-group">
              <label class="form-label">Power Station / Location *</label>
              <select name="location_id" id="editUserLocation" class="form-select" required>
                <option value="" disabled>-- Select Location --</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= $loc['id'] ?>">
                    <?= htmlspecialchars($loc['name']) ?> (<?= htmlspecialchars($loc['code']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- 2. Role & Account Status Selection -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div class="form-group">
                <label class="form-label">User Role / Designation *</label>
                <select name="role_id" id="editUserRoleId" class="form-select" required>
                  <option value="" disabled>-- Select Role --</option>
                  <?php foreach ($allRoles as $r): ?>
                    <option value="<?= $r['id'] ?>">
                      <?= htmlspecialchars($r['display_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Account Status</label>
                <select name="status" id="editUserStatus" class="form-select">
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                  <option value="suspended">Suspended</option>
                </select>
              </div>
            </div>

            <!-- 3. User Details -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div class="form-group">
                <label class="form-label">Username</label>
                <input type="text" name="username" id="editUserUsername" class="form-control" readonly
                  style="background-color: #f1f5f9; color: #1e293b; font-weight: 600; cursor: not-allowed; border: 1px solid #cbd5e1;">
              </div>

              <div class="form-group">
                <label class="form-label">Change Password</label>
                <div class="password-input-wrapper">
                  <input type="password" name="password" class="form-control"
                    placeholder="Enter new password (min 6 characters)">
                  <button type="button" class="btn-toggle-password" aria-label="Show password" title="Show password"
                    tabindex="-1">
                    <svg class="eye-show" viewBox="0 0 24 24" width="18" height="18" stroke="currentColor"
                      stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="eye-hide" style="display:none;" viewBox="0 0 24 24" width="18" height="18"
                      stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
                      <path
                        d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24">
                      </path>
                      <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 7. Define New Role Modal -->
    <div class="modal-backdrop" id="createRoleModal">
      <div class="modal-dialog" style="max-width: 680px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
          <h3 class="modal-title">Define New Role &amp; Tab Permissions</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="createRoleForm" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">

          <div class="modal-body" style="overflow-y: auto; max-height: calc(90vh - 140px); padding-right: 14px;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 20px;">
              <div class="form-group">
                <label class="form-label">Role Name / Designation *</label>
                <input type="text" name="display_name" id="roleDisplayNameInput" class="form-control"
                  placeholder="e.g. Store Keeper, Shift Engineer, Station Incharge" required>
              </div>
              <div class="form-group">
                <label class="form-label">Role Code *</label>
                <input type="text" name="code" id="roleCodeInput" class="form-control"
                  placeholder="e.g. SK, CC, CE, ENG" style="text-transform: uppercase;" required>
              </div>
            </div>

            <!-- Tab Access Permissions Header & Actions -->
            <div
              style="margin-top: 16px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
              <div>
                <strong style="font-size: 0.92rem; color: #0f172a;">Fixed Tab Access Permissions</strong>
                <div style="font-size: 0.78rem; color: #64748b;">Set which tabs are enabled (ON) or restricted (OFF) for
                  users created under this role.</div>
              </div>
              <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="btnRolePermSelectAll"
                  style="font-size: 0.75rem; padding: 4px 10px; font-weight: 600;">
                  ✓ Select All
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnRolePermDeselectAll"
                  style="font-size: 0.75rem; padding: 4px 10px; font-weight: 600;">
                  ✗ Deselect All
                </button>
              </div>
            </div>

            <!-- Categorized Permissions List -->
            <div class="role-permissions-container" style="display: flex; flex-direction: column; gap: 14px;">
              <?php foreach ($permissionCategories as $categoryName => $perms): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                  <div
                    style="font-size: 0.82rem; font-weight: 700; color: #650b2b; text-transform: uppercase; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span><?= htmlspecialchars($categoryName) ?></span>
                  </div>
                  <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                    <?php foreach ($perms as $permKey => $permMeta): ?>
                      <div
                        style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <div style="padding-right: 12px;">
                          <div style="font-weight: 600; font-size: 0.84rem; color: #1e293b;">
                            <?= htmlspecialchars($permMeta['label']) ?>
                          </div>
                          <div style="font-size: 0.74rem; color: #64748b;"><?= htmlspecialchars($permMeta['desc'] ?? '') ?>
                          </div>
                        </div>
                        <label class="matrix-switch" style="margin: 0; flex-shrink: 0;">
                          <input type="checkbox" name="permissions[<?= htmlspecialchars($permKey) ?>]"
                            data-perm-key="<?= htmlspecialchars($permKey) ?>" value="1" class="role-perm-cb-create" checked>
                          <span class="matrix-slider"></span>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Define Role &amp; Permissions</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 8. Edit Role Modal -->
    <div class="modal-backdrop" id="editRoleModal" data-keep-values="true">
      <div class="modal-dialog" style="max-width: 680px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header">
          <h3 class="modal-title">Edit Role &amp; Tab Permissions</h3>
          <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>

        <form id="editRoleForm" style="display: flex; flex-direction: column; overflow: hidden; flex: 1;">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
          <input type="hidden" name="id" id="editRoleId">

          <div class="modal-body" style="overflow-y: auto; max-height: calc(90vh - 140px); padding-right: 14px;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-bottom: 20px;">
              <div class="form-group">
                <label class="form-label">Role Name / Designation *</label>
                <input type="text" name="display_name" id="editRoleDisplayName" class="form-control"
                  placeholder="e.g. Store Keeper, Shift Engineer, Station Incharge" required>
              </div>
              <div class="form-group">
                <label class="form-label">Role Code *</label>
                <input type="text" name="code" id="editRoleCode" class="form-control"
                  placeholder="e.g. SK, CC, CE, ENG" style="text-transform: uppercase;" required>
              </div>
            </div>

            <!-- Tab Access Permissions Header & Actions -->
            <div
              style="margin-top: 16px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
              <div>
                <strong style="font-size: 0.92rem; color: #0f172a;">Fixed Tab Access Permissions</strong>
                <div style="font-size: 0.78rem; color: #64748b;">Configure which tabs are ON or OFF for users assigned to
                  this role.</div>
              </div>
              <div style="display: flex; gap: 8px;">
                <button type="button" class="btn btn-secondary btn-sm" id="btnEditRolePermSelectAll"
                  style="font-size: 0.75rem; padding: 4px 10px; font-weight: 600;">
                  ✓ Select All
                </button>
                <button type="button" class="btn btn-secondary btn-sm" id="btnEditRolePermDeselectAll"
                  style="font-size: 0.75rem; padding: 4px 10px; font-weight: 600;">
                  ✗ Deselect All
                </button>
              </div>
            </div>

            <!-- Categorized Permissions List -->
            <div class="role-permissions-container" style="display: flex; flex-direction: column; gap: 14px;">
              <?php foreach ($permissionCategories as $categoryName => $perms): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px;">
                  <div
                    style="font-size: 0.82rem; font-weight: 700; color: #650b2b; text-transform: uppercase; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span><?= htmlspecialchars($categoryName) ?></span>
                  </div>
                  <div style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                    <?php foreach ($perms as $permKey => $permMeta): ?>
                      <div
                        style="display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">
                        <div style="padding-right: 12px;">
                          <div style="font-weight: 600; font-size: 0.84rem; color: #1e293b;">
                            <?= htmlspecialchars($permMeta['label']) ?>
                          </div>
                          <div style="font-size: 0.74rem; color: #64748b;"><?= htmlspecialchars($permMeta['desc'] ?? '') ?>
                          </div>
                        </div>
                        <label class="matrix-switch" style="margin: 0; flex-shrink: 0;">
                          <input type="checkbox" name="permissions[<?= htmlspecialchars($permKey) ?>]"
                            data-perm-key="<?= htmlspecialchars($permKey) ?>" value="1" class="role-perm-cb-edit" checked>
                          <span class="matrix-slider"></span>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Save Role &amp; Permissions</button>
          </div>
        </form>
      </div>
    </div>

    <!-- 7. Review & Approve Code Creation Request Modal -->
    <div class="modal-backdrop" id="reviewCodeRequestModal" data-keep-values="true">
      <div class="modal-dialog" style="max-width: 640px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.18); border: 1px solid #cbd5e1; overflow: hidden;">
        <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 class="modal-title" style="font-size: 0.94rem; font-weight: 700; color: #0f172a; margin: 0;">Review Code Creation Request</h3>
            <span style="font-size: 0.74rem; color: #64748b; font-family: monospace;" id="modalReqRefTitle">REQ-20261006-0001</span>
          </div>
          <button type="button" class="modal-close" data-close-modal style="font-size: 1.25rem; line-height: 1; color: #64748b; border: none; background: none; cursor: pointer;">&times;</button>
        </div>

        <form id="reviewCodeRequestForm" onsubmit="executeApproveCodeRequest(event);">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
          <input type="hidden" name="request_id" id="modalReviewReqId" value="">

          <div class="modal-body" style="padding: 14px 16px; background: #ffffff;">
            <!-- Hierarchy & Computed Atomic Sequence Display -->
            <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 3px; padding: 8px 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
              <div>
                <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Category Branch (1st &bull; 2nd &bull; 3rd)</div>
                <div style="font-size: 0.85rem; font-weight: 700; color: #0f172a; margin-top: 1px;" id="modalBranchLabel">
                  Tier 1 (01) &bull; Tier 2 (01) &bull; Tier 3 (01)
                </div>
                <div style="font-size: 0.72rem; color: #64748b; margin-top: 1px;" id="modalRequesterMeta">
                  Requested by 831SK &bull; Victoria Power Station
                </div>
              </div>

              <div style="text-align: right; flex-shrink: 0;">
                <div style="font-size: 0.68rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Assigned Code Preview</div>
                <div style="font-family: monospace; font-size: 1.15rem; font-weight: 800; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 2px 8px; border-radius: 3px; display: inline-block; margin-top: 2px;" id="modalComputedCode">
                  1-01-01-001
                </div>
              </div>
            </div>

            <!-- Admin 4th Sequence Number Assignment -->
            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 4px; padding: 8px 12px; margin-bottom: 10px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <label style="font-size: 0.72rem; font-weight: 800; color: #0369a1; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
                  ★ Admin 4th Sequence Number Assignment *
                </label>
                <span style="font-size: 0.7rem; color: #0284c7; font-weight: 600;">(Only Admin Can Give This 4th Number)</span>
              </div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-family: monospace; font-size: 0.9rem; font-weight: 700; color: #334155; background: #e2e8f0; padding: 5px 8px; border-radius: 3px; border: 1px solid #cbd5e1;" id="modalReviewBranchPrefix">1-01-01-</span>
                <input type="number" name="assigned_tier4_seq" id="modalReviewAssignedSeq" min="0" max="999" class="form-control" required
                  style="font-family: monospace; font-size: 0.95rem; font-weight: 800; color: #0f172a; width: 90px; padding: 5px 8px; border: 1px solid #0284c7; border-radius: 3px; background: #ffffff; text-align: center;"
                  oninput="updateReviewComputedCodePreview();">
                <span style="font-size: 0.72rem; color: #64748b;">Sequence integer (e.g. 0, 1, 2... format as 000, 001, 002)</span>
              </div>
            </div>

            <!-- Editable Item Details -->
            <div class="form-group" style="margin-bottom: 10px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">Item Description / Name *</label>
                <div style="display: flex; gap: 6px; align-items: center;">
                  <button type="button" onclick="openGoogleSearch(document.getElementById('modalReviewItemName').value, event);"
                    style="background: #f0f9ff; border: 1px solid #bae6fd; color: #0284c7; font-size: 0.71rem; font-weight: 700; cursor: pointer; padding: 2px 8px; border-radius: 3px; display: inline-flex; align-items: center; gap: 4px; transition: all 0.15s ease;"
                    onmouseenter="this.style.background='#e0f2fe';" onmouseleave="this.style.background='#f0f9ff';"
                    title="Search item name on Google in a new tab">
                    🌐 Search Google
                  </button>
                  <button type="button" onclick="SmartSpellChecker.autoFixAll('modalReviewItemName')" style="background: none; border: none; color: #0284c7; font-size: 0.71rem; font-weight: 700; cursor: pointer; padding: 0;" title="Auto-detect & fix typos">
                    ✨ Auto-Fix Spelling
                  </button>
                </div>
              </div>
              <input type="text" name="item_name" id="modalReviewItemName" class="form-control" required spellcheck="true" autocomplete="on" lang="en" oninput="this.value = this.value.toUpperCase();" style="font-size: 0.82rem; font-weight: 600; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 3px; text-transform: uppercase;">
            </div>

            <input type="hidden" name="specifications" id="modalReviewSpecs" value="">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
              <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: block;">Unit of Measure (UoM)</label>
                <input type="text" name="uom" id="modalReviewUom" class="form-control" readonly style="background: #f8fafc; color: #475569; font-size: 0.82rem; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 3px;">
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: block;">Origin Station</label>
                <input type="text" id="modalReviewStation" class="form-control" readonly style="background: #f8fafc; color: #475569; font-size: 0.82rem; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 3px;">
              </div>
            </div>

            <input type="hidden" name="remarks" id="modalReviewRemarks" value="Approved and committed to FIFO Master Database.">
          </div>

          <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #cbd5e1; padding: 8px 16px; display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn btn-sm" onclick="executeRejectCodeRequest();"
              style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 3px; cursor: pointer;">
              Reject Request
            </button>
            <div style="display: flex; gap: 8px;">
              <button type="button" class="btn btn-sm" data-close-modal
                style="background: #e2e8f0; border: 1px solid #94a3b8; color: #1e293b; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 3px; cursor: pointer;">
                Cancel
              </button>
              <button type="button" class="btn btn-sm" onclick="executeApproveOnly(event);"
                style="background: #0284c7; border: 1px solid #0369a1; color: #ffffff; font-size: 0.75rem; font-weight: 700; padding: 4px 12px; border-radius: 3px; cursor: pointer;" title="Approve and reserve code without adding to Master yet">
                ✓ Approve (Stage)
              </button>
              <button type="submit" class="btn btn-sm" id="btnConfirmApproval"
                style="background: #ea580c; border: 1px solid #c2410c; color: #ffffff; font-size: 0.75rem; font-weight: 700; padding: 4px 14px; border-radius: 3px; cursor: pointer;" title="Approve and directly commit as Orange Master Code">
                ✓ Approve &amp; Add to Master DB
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Update Master Item Name & Unit of Measurement Modal -->
    <div class="modal-backdrop" id="updateMasterItemModal" data-keep-values="true">
      <div class="modal-dialog" style="max-width: 520px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.18); border: 1px solid #cbd5e1; overflow: hidden;">
        <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <h3 class="modal-title" style="font-size: 0.94rem; font-weight: 700; color: #0f172a; margin: 0;">Update Master Item Name &amp; Unit of Measurement</h3>
            <span style="font-size: 0.74rem; color: #64748b; font-family: monospace;" id="modalUpdateItemCodeBadge">Code: -</span>
          </div>
          <button type="button" class="modal-close" data-close-modal style="font-size: 1.25rem; line-height: 1; color: #64748b; border: none; background: none; cursor: pointer;">&times;</button>
        </div>

        <form id="updateMasterItemForm" onsubmit="executeSaveMasterItemUpdate(event);">
          <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
          <input type="hidden" name="master_id" id="modalUpdateMasterId" value="">
          <input type="hidden" name="request_id" id="modalUpdateRequestId" value="">

          <div class="modal-body" style="padding: 16px; background: #ffffff;">
            <!-- Reference Info -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px 12px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
              <div style="min-width: 0; flex: 1;">
                <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 2px;">Original Requested Description (Reference)</div>
                <div id="modalUpdateOrigDesc" style="font-size: 0.8rem; font-weight: 600; color: #334155; word-break: break-word;">-</div>
              </div>
              <button type="button" onclick="openGoogleSearch(document.getElementById('modalUpdateOrigDesc').textContent, event);"
                style="background: #f0f9ff; border: 1px solid #bae6fd; color: #0284c7; font-size: 0.72rem; font-weight: 700; cursor: pointer; padding: 4px 10px; border-radius: 3px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; flex-shrink: 0;"
                title="Search original description on Google in a new tab">
                🌐 Search Google
              </button>
            </div>

            <!-- Master Database Name -->
            <div class="form-group" style="margin-bottom: 14px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <label for="modalUpdateItemName" style="font-size: 0.74rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">
                  Master Database Name *
                </label>
                <button type="button" onclick="openGoogleSearch(document.getElementById('modalUpdateItemName').value, event);"
                  style="background: none; border: none; color: #0284c7; font-size: 0.72rem; font-weight: 700; cursor: pointer; padding: 0; display: inline-flex; align-items: center; gap: 3px;"
                  title="Search entered master name on Google in a new tab">
                  🌐 Search on Google
                </button>
              </div>
              <input type="text" name="item_name" id="modalUpdateItemName" class="form-control" required
                style="width: 100%; font-size: 0.85rem; font-weight: 600; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px; text-transform: uppercase;"
                oninput="this.value = this.value.toUpperCase();" placeholder="ENTER STANDARDIZED MASTER ITEM NAME">
            </div>

            <!-- Unit of Measurement (UoM) -->
            <div class="form-group" style="margin-bottom: 6px;">
              <label for="modalUpdateUom" style="font-size: 0.74rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">
                Unit of Measurement (UoM) *
              </label>
              <div style="display: flex; gap: 8px;">
                <input type="text" name="uom" id="modalUpdateUom" class="form-control" required
                  style="flex: 1; font-size: 0.85rem; font-weight: 700; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 4px; text-transform: uppercase;"
                  oninput="this.value = this.value.toUpperCase();" placeholder="E.G. NOS, SET, KG, MTR">
                <select onchange="if(this.value){ document.getElementById('modalUpdateUom').value = this.value; }"
                  style="font-size: 0.8rem; font-weight: 600; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #f8fafc; color: #334155;">
                  <option value="">Quick Select...</option>
                  <option value="NOS">NOS</option>
                  <option value="SET">SET</option>
                  <option value="PCS">PCS</option>
                  <option value="KG">KG</option>
                  <option value="MTR">MTR</option>
                  <option value="LTR">LTR</option>
                  <option value="PACK">PACK</option>
                  <option value="BOX">BOX</option>
                  <option value="PAIR">PAIR</option>
                  <option value="ROLL">ROLL</option>
                  <option value="CAN">CAN</option>
                  <option value="DRUM">DRUM</option>
                  <option value="BOTTLE">BOTTLE</option>
                </select>
              </div>
              <span style="font-size: 0.72rem; color: #64748b; margin-top: 3px; display: block;">Type custom UoM or pick from common units.</span>
            </div>
          </div>

          <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #cbd5e1; padding: 10px 16px; display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary btn-sm" data-close-modal
              style="font-size: 0.75rem; font-weight: 600; padding: 5px 14px; border-radius: 4px; border: 1px solid #cbd5e1; background: #ffffff; cursor: pointer;">
              Cancel
            </button>
            <button type="submit" id="btnSaveMasterItemUpdate" class="btn btn-primary btn-sm"
              style="font-size: 0.75rem; font-weight: 700; padding: 5px 16px; border-radius: 4px; background: #650b2b; color: #ffffff; border: 1px solid #4a071e; cursor: pointer;">
              Save &amp; Update
            </button>
          </div>
        </form>
      </div>
    </div>

  <?php endif; ?>

  <!-- 8. Import Master Item to Station Inventory Modal (Accessible to All Users) -->
  <div class="modal-backdrop" id="importStationModal">
    <div class="modal-dialog" style="max-width: 500px; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.18); border: 1px solid #cbd5e1; overflow: hidden;">
      <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <h3 class="modal-title" style="font-size: 0.94rem; font-weight: 700; color: #0f172a; margin: 0;">Add Code to Power Station Table</h3>
          <span style="font-size: 0.74rem; color: #64748b;">Save approved item code into Power Station catalog</span>
        </div>
        <button type="button" class="modal-close" data-close-modal style="font-size: 1.25rem; line-height: 1; color: #64748b; border: none; background: none; cursor: pointer;">&times;</button>
      </div>

      <form id="importStationForm" onsubmit="executeImportToStation(event);">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
        <input type="hidden" name="master_item_id" id="modalImportItemId" value="">

        <div class="modal-body" style="padding: 14px 16px; background: #ffffff;">
          <div style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 3px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; gap: 12px;">
            <div>
              <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Approved Item Code</div>
              <div style="font-family: monospace; font-size: 1.1rem; font-weight: 800; color: #650b2b; background: #fdf2f5; border: 1px solid #fbcfe8; padding: 2px 8px; border-radius: 3px; display: inline-block; margin-top: 2px;" id="modalImportCodeLabel">1-01-01-001</div>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 0.7rem; color: #64748b; text-transform: uppercase; font-weight: 700;">Item Name</div>
              <div style="font-size: 0.85rem; font-weight: 700; color: #0f172a; margin-top: 2px;" id="modalImportNameLabel">Deep Groove Ball Bearing 6205</div>
            </div>
          </div>

          <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; display: block;">Destination Power Station</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($adminDetails['location_name'] ?? 'Victoria Power Station (831)') ?>" readonly style="background: #f8fafc; font-size: 0.82rem; font-weight: 600; color: #0f172a; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 3px;">
            <input type="hidden" name="station_id" value="<?= (int)($activeLocationId ?: 1) ?>">
            <input type="hidden" name="bay_location" value="Bay-01">
          </div>
        </div>

        <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #cbd5e1; padding: 8px 16px; display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" class="btn btn-sm" data-close-modal style="background: #e2e8f0; border: 1px solid #94a3b8; color: #1e293b; font-size: 0.75rem; font-weight: 600; padding: 4px 12px; border-radius: 3px; cursor: pointer;">Cancel</button>
          <button type="submit" class="btn btn-sm" id="btnConfirmImportStation" style="background: #650b2b; color: #ffffff; border: 1px solid #4a071e; padding: 4px 14px; border-radius: 3px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">+ Add Code to Station</button>
        </div>
      </form>
    </div>
  </div>

  <!-- 9. Request New Master Item Code Modal (Full-Screen with Live Word Suggestions & Duplicate Checker) -->
  <style>
    #requestNewCodeModal.active .modal-dialog {
      max-width: 96vw !important;
      width: 96vw !important;
      height: 92vh !important;
      max-height: 92vh !important;
      margin: auto !important;
    }
    @media (max-width: 960px) {
      #requestNewCodeModal .modal-body-split {
        grid-template-columns: 1fr !important;
        overflow-y: auto !important;
      }
    }
  </style>

  <div class="modal-backdrop" id="requestNewCodeModal">
    <div class="modal-dialog" style="max-width: 96vw; width: 96vw; height: 92vh; max-height: 92vh; display: flex; flex-direction: column; border-radius: 6px; box-shadow: 0 10px 40px rgba(0,0,0,0.25); border: 1px solid #cbd5e1; overflow: hidden; margin: auto;">
      <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #cbd5e1; padding: 10px 18px; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <div>
            <h3 class="modal-title" style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">Request Item Code Creation &amp; Live Duplicate Checker</h3>
            <span style="font-size: 0.74rem; color: #64748b;">Live word-matching across Master Added (Orange) &amp; Requested items prevents duplicate codes</span>
          </div>
        </div>
        <button type="button" class="modal-close" data-close-modal style="font-size: 1.35rem; line-height: 1; color: #64748b; border: none; background: none; cursor: pointer;">&times;</button>
      </div>

      <form id="simpleRequestCodeForm" onsubmit="executeSimpleCodeRequest(event);" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
        <input type="hidden" name="origin_station_id" value="<?= (int)($activeLocationId ?: 1) ?>">
        <input type="hidden" name="tier1" id="modalReqTier1" value="1">
        <input type="hidden" name="tier2" id="modalReqTier2" value="1">
        <input type="hidden" name="tier3" id="modalReqTier3" value="1">
        <input type="hidden" name="suggested_tier4_seq" id="modalReqTier4" value="1">

        <div class="modal-body modal-body-split" style="flex: 1; min-height: 0; display: grid; grid-template-columns: 390px 1fr; gap: 16px; padding: 16px; overflow: hidden; background: #f1f5f9;">
          
          <!-- LEFT PANEL: Request Details Form -->
          <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 16px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto;">
            
            <!-- Beautiful Target Classification Card -->
            <div style="background: linear-gradient(135deg, #fdf2f5 0%, #ffffff 100%); border: 1px solid #fbcfe8; border-radius: 8px; padding: 12px 14px; box-shadow: 0 1px 3px rgba(101, 11, 43, 0.05);">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 0.72rem; font-weight: 800; color: #9f1239; text-transform: uppercase; letter-spacing: 0.6px; display: inline-flex; align-items: center; gap: 6px;">
                  <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #9f1239;"></span>
                  TARGET CLASSIFICATION
                </span>
                <span style="font-size: 0.7rem; font-weight: 700; color: #0284c7; background: #e0f2fe; border: 1px solid #bae6fd; padding: 2px 8px; border-radius: 12px;">Assigned on Approval</span>
              </div>
              
              <div style="margin-bottom: 10px;">
                <span id="modalReqFormattedCode" style="font-family: monospace; font-size: 1.25rem; font-weight: 800; color: #650b2b; background: #ffffff; border: 1.5px solid #650b2b; padding: 4px 14px; border-radius: 5px; letter-spacing: 1.5px; white-space: nowrap; display: inline-block; box-shadow: 0 1px 2px rgba(0,0,0,0.06);">
                  1-01-01-XXX
                </span>
              </div>

              <!-- Clean 1st / 2nd / 3rd Category Breadcrumb Stack -->
              <div style="display: flex; flex-direction: column; gap: 5px; font-size: 0.76rem; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 5px; padding: 7px 10px;">
                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                  <span style="font-size: 0.68rem; font-weight: 800; color: #650b2b; background: #fdf2f5; padding: 1px 6px; border-radius: 3px; min-width: 28px; text-align: center;">1st</span>
                  <span id="modalReqPathT1" style="font-weight: 700; color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                  <span style="font-size: 0.68rem; font-weight: 800; color: #475569; background: #f1f5f9; padding: 1px 6px; border-radius: 3px; min-width: 28px; text-align: center;">2nd</span>
                  <span id="modalReqPathT2" style="font-weight: 600; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; overflow: hidden;">
                  <span style="font-size: 0.68rem; font-weight: 800; color: #0284c7; background: #e0f2fe; padding: 1px 6px; border-radius: 3px; min-width: 28px; text-align: center;">3rd</span>
                  <span id="modalReqPathT3" style="font-weight: 600; color: #0369a1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">-</span>
                </div>
              </div>
            </div>

            <!-- Item Name / Description Input -->
            <div class="form-group" style="margin-bottom: 0;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <label style="font-size: 0.76rem; font-weight: 800; color: #0f172a; text-transform: uppercase; margin: 0;">
                  Item Description / Name *
                </label>
                <button type="button" onclick="SmartSpellChecker.autoFixAll('modalReqItemName'); if(typeof onModalReqNameInput === 'function') onModalReqNameInput();" style="background: none; border: none; color: #0284c7; font-size: 0.72rem; font-weight: 700; cursor: pointer; padding: 0;" title="Auto-detect & fix typos">
                  ✨ Auto-Fix Spelling
                </button>
              </div>
              <textarea name="item_name" id="modalReqItemName" class="form-control" rows="3"
                placeholder="E.G. DEEP GROOVE BALL BEARING 6205 2RS C3" required spellcheck="true" autocomplete="off" lang="en"
                oninput="this.value = this.value.toUpperCase(); if(typeof onModalReqNameInput === 'function') onModalReqNameInput();"
                style="font-size: 0.88rem; font-weight: 600; padding: 8px 10px; border: 1px solid #71717a; border-radius: 4px; text-transform: uppercase; width: 100%; resize: vertical; line-height: 1.4;"></textarea>
            </div>

            <!-- Hidden specs field to maintain backward compatibility -->
            <input type="hidden" name="specifications" id="modalReqSpecs" value="">

            <!-- Amount & UoM Row -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
              <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; display: flex; justify-content: space-between; align-items: center;">
                  <span>Amount (LKR)</span>
                  <span style="font-size: 0.68rem; color: #64748b; font-weight: 500;">(Optional)</span>
                </label>
                <div style="position: relative;">
                  <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.76rem; font-weight: 800; color: #64748b; pointer-events: none;">LKR</span>
                  <input type="number" step="0.01" min="0" name="unit_price" id="modalReqUnitPrice" class="form-control" placeholder="0.00"
                    style="font-size: 0.84rem; font-weight: 700; padding: 6px 10px 6px 42px; border: 1px solid #cbd5e1; border-radius: 4px; height: 34px; width: 100%;">
                </div>
              </div>

              <div class="form-group" style="margin-bottom: 0;">
                <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; display: block;">Unit of Measure *</label>
                <select name="uom" id="modalReqUom" class="form-select" required style="font-size: 0.82rem; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; height: 34px;">
                  <option value="Nos" selected>Nos (Numbers)</option>
                  <option value="Mtr">Mtr (Meters)</option>
                  <option value="Kg">Kg (Kilograms)</option>
                  <option value="Ltr">Ltr (Liters)</option>
                  <option value="Set">Set</option>
                  <option value="Pair">Pair</option>
                  <option value="Roll">Roll</option>
                  <option value="Box">Box</option>
                </select>
              </div>
            </div>

            <!-- Add Two Images (Max 2 Photos) - Title front camera removed, camera icon only in both slots -->
            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
                <span>Item Photos (Maximum 2 Images)</span>
                <span style="font-size: 0.68rem; color: #64748b; font-weight: 500;">JPG, PNG, WEBP</span>
              </label>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <!-- Image 1 Slot -->
                <div id="modalReqImg1Box" style="border: 1.5px dashed #cbd5e1; border-radius: 6px; padding: 10px 8px; background: #f8fafc; text-align: center; cursor: pointer; position: relative; transition: all 0.15s ease;"
                     onclick="document.getElementById('modalReqImg1Input').click();"
                     onmouseover="this.style.borderColor='#650b2b'; this.style.background='#fdf2f5';"
                     onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';">
                  <input type="file" name="image_1" id="modalReqImg1Input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAddCodeImage(1, this);">
                  <div id="modalReqImg1Placeholder">
                    <div style="font-size: 1.3rem; line-height: 1; margin-bottom: 3px;">📷</div>
                    <div style="font-size: 0.74rem; font-weight: 700; color: #334155;">Image 1</div>
                    <div style="font-size: 0.64rem; color: #94a3b8;">Click to upload</div>
                  </div>
                  <div id="modalReqImg1Preview" style="display: none; position: relative;">
                    <img id="modalReqImg1Tag" src="" alt="Image 1" style="max-height: 65px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                    <button type="button" onclick="event.stopPropagation(); removeAddCodeImage(1);" title="Remove Image"
                      style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: #ffffff; border: 1px solid #b91c1c; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;">&times;</button>
                  </div>
                </div>

                <!-- Image 2 Slot -->
                <div id="modalReqImg2Box" style="border: 1.5px dashed #cbd5e1; border-radius: 6px; padding: 10px 8px; background: #f8fafc; text-align: center; cursor: pointer; position: relative; transition: all 0.15s ease;"
                     onclick="document.getElementById('modalReqImg2Input').click();"
                     onmouseover="this.style.borderColor='#650b2b'; this.style.background='#fdf2f5';"
                     onmouseout="this.style.borderColor='#cbd5e1'; this.style.background='#f8fafc';">
                  <input type="file" name="image_2" id="modalReqImg2Input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAddCodeImage(2, this);">
                  <div id="modalReqImg2Placeholder">
                    <div style="font-size: 1.3rem; line-height: 1; margin-bottom: 3px;">📷</div>
                    <div style="font-size: 0.74rem; font-weight: 700; color: #334155;">Image 2</div>
                    <div style="font-size: 0.64rem; color: #94a3b8;">Click to upload</div>
                  </div>
                  <div id="modalReqImg2Preview" style="display: none; position: relative;">
                    <img id="modalReqImg2Tag" src="" alt="Image 2" style="max-height: 65px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                    <button type="button" onclick="event.stopPropagation(); removeAddCodeImage(2);" title="Remove Image"
                      style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: #ffffff; border: 1px solid #b91c1c; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;">&times;</button>
                  </div>
                </div>
              </div>
            </div>


            <div style="margin-top: auto; padding-top: 10px; border-top: 1px solid #e2e8f0; display: flex; gap: 8px;">
              <button type="button" class="btn btn-secondary btn-sm" data-close-modal style="flex: 1; padding: 8px; font-weight: 600;">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitSimpleReq" style="flex: 2; padding: 8px; font-weight: 700; background: #650b2b; border: 1px solid #4a071e; color: #ffffff;">
                Submit Code Request
              </button>
            </div>
          </div>

          <!-- RIGHT PANEL: Live Word Matching & Existing Codes Suggestions -->
          <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
            
            <!-- Suggestions Toolbar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
              <div>
                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                  <span>🔍 System-Wide Related Codes &amp; Word Suggestions</span>
                  <span id="modalReqSuggestionsCount" style="font-size: 0.72rem; font-weight: 700; padding: 2px 10px; border-radius: 12px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;">Ready</span>
                </h4>
                <p style="margin: 2px 0 0 0; font-size: 0.74rem; color: #64748b;">
                  Live search across all 10,960+ system codes in all categories &bull; Matches both <strong>Master Added (Orange)</strong> and <strong>Requested / Staged</strong> items.
                </p>
              </div>
            </div>

            <!-- Suggestions Results Table -->
            <div style="flex: 1; overflow-y: auto; overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 4px;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem;">
                <thead style="position: sticky; top: 0; background: #000000; color: #ffffff; z-index: 2;">
                  <tr>
                    <th style="padding: 6px 8px; text-align: left; font-size: 0.72rem; font-weight: 800; color: #ffffff; width: 130px; white-space: nowrap;">ITEM CODE</th>
                    <th style="padding: 6px 8px; text-align: center; font-size: 0.72rem; font-weight: 800; color: #ffffff; width: 85px; white-space: nowrap;">BRANCH</th>
                    <th style="padding: 6px 10px; text-align: left; font-size: 0.72rem; font-weight: 800; color: #ffffff;">DESCRIPTION &amp; SPECS</th>
                    <th style="padding: 6px 8px; text-align: center; font-size: 0.72rem; font-weight: 800; color: #ffffff; width: 60px; white-space: nowrap;">UOM</th>
                    <th style="padding: 6px 8px; text-align: center; font-size: 0.72rem; font-weight: 800; color: #ffffff; width: 140px; white-space: nowrap;">CATALOG STATUS</th>
                    <th style="padding: 6px 8px; text-align: center; font-size: 0.72rem; font-weight: 800; color: #ffffff; width: 130px; white-space: nowrap;">ACTION</th>
                  </tr>
                </thead>
                <tbody id="modalReqSuggestionsBody">
                  <tr>
                    <td colspan="6" style="text-align: center; padding: 30px; color: #64748b; font-size: 0.8125rem;">
                      Type in the item description box on the left to see live matching codes.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>

        </div>
      </form>
    </div>
  </div>

  <!-- =======================================================================
       10. EDIT CODE CREATION REQUEST MODAL (PENDING REQUESTS ONLY)
       ======================================================================= -->
  <div class="modal-backdrop" id="editCodeRequestModal" data-keep-values="true" style="z-index: 10040;" onclick="if(event.target === this) window.closeModal('editCodeRequestModal');">
    <div class="modal-dialog" style="max-width: 620px; width: 95vw; max-height: 90vh; display: flex; flex-direction: column; border-radius: 8px; box-shadow: 0 15px 40px rgba(0,0,0,0.25); border: 1px solid #cbd5e1; overflow: hidden; margin: auto; background: #ffffff;">
      
      <div class="modal-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 1px solid #cbd5e1; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <div>
            <h3 class="modal-title" style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0;">Edit Code Creation Request</h3>
            <span style="font-size: 0.72rem; color: #64748b;">Update pending item details before administrator approval</span>
          </div>
          <span id="editReqStatusBadge" style="font-size: 0.7rem; font-weight: 700; color: #c2410c; background: #fff7ed; border: 1px solid #fed7aa; padding: 2px 8px; border-radius: 12px;">PENDING</span>
        </div>
        <button type="button" class="btn-close" data-close-modal onclick="window.closeModal('editCodeRequestModal');" style="background: none; border: none; font-size: 1.4rem; color: #64748b; cursor: pointer; line-height: 1; padding: 2px 6px;" title="Close Window">&times;</button>
      </div>

      <form id="editCodeRequestForm" onsubmit="executeEditCodeRequest(event);" style="display: flex; flex-direction: column; flex: 1; min-height: 0; overflow: hidden;" enctype="multipart/form-data">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(Session::csrfToken()) ?>">
        <input type="hidden" name="request_id" id="editReqId" value="">
        <input type="hidden" name="delete_image_1" id="editReqDeleteImg1" value="0">
        <input type="hidden" name="delete_image_2" id="editReqDeleteImg2" value="0">

        <div class="modal-body" style="padding: 16px 20px; overflow-y: auto; background: #ffffff; flex: 1; display: flex; flex-direction: column; gap: 14px;">
          
          <!-- Code Info Banner -->
          <div style="background: #fdf2f5; border: 1px solid #fbcfe8; border-radius: 6px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap;">
            <div>
              <span style="font-size: 0.68rem; font-weight: 800; color: #9f1239; text-transform: uppercase;">REQUEST NUMBER</span>
              <div id="editReqNo" style="font-family: monospace; font-size: 0.95rem; font-weight: 800; color: #650b2b;">REQ-0000</div>
            </div>
            <div style="text-align: right;">
              <span style="font-size: 0.68rem; font-weight: 800; color: #9f1239; text-transform: uppercase;">REQUESTED CODE</span>
              <div id="editReqDisplayCode" style="font-family: monospace; font-size: 1.05rem; font-weight: 800; color: #650b2b;">1-01-01-001</div>
            </div>
          </div>

          <!-- Notice Alert -->
          <div style="font-size: 0.73rem; color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd; padding: 6px 12px; border-radius: 4px; display: flex; align-items: center; gap: 6px;">
            <span>ℹ️</span>
            <span>You can edit this request while in <strong>PENDING</strong> or <strong>REJECTED</strong> status. Saving will resubmit it for Administrator Approval. Once approved, editing is disabled.</span>
          </div>

          <!-- Item Description / Name -->
          <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.74rem; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; display: block;">
              Item Description / Name *
            </label>
            <textarea name="item_name" id="editReqItemName" class="form-control" rows="2" required spellcheck="true" autocomplete="off"
              oninput="this.value = this.value.toUpperCase();"
              style="font-size: 0.88rem; font-weight: 600; padding: 8px 10px; border: 1px solid #71717a; border-radius: 4px; text-transform: uppercase; width: 100%; resize: vertical; line-height: 1.4;"></textarea>
          </div>

          <!-- Amount & UoM Row -->
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; display: flex; justify-content: space-between; align-items: center;">
                <span>Amount (LKR)</span>
                <span style="font-size: 0.68rem; color: #64748b; font-weight: 500;">(Optional)</span>
              </label>
              <div style="position: relative;">
                <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.76rem; font-weight: 800; color: #64748b; pointer-events: none;">LKR</span>
                <input type="number" step="0.01" min="0" name="unit_price" id="editReqUnitPrice" class="form-control" placeholder="0.00"
                  style="font-size: 0.84rem; font-weight: 700; padding: 6px 10px 6px 42px; border: 1px solid #cbd5e1; border-radius: 4px; height: 34px; width: 100%;">
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 3px; display: block;">Unit of Measure *</label>
              <select name="uom" id="editReqUom" class="form-select" required style="font-size: 0.82rem; padding: 6px 8px; border: 1px solid #cbd5e1; border-radius: 4px; height: 34px;">
                <option value="Nos">Nos (Numbers)</option>
                <option value="Mtr">Mtr (Meters)</option>
                <option value="Kg">Kg (Kilograms)</option>
                <option value="Ltr">Ltr (Liters)</option>
                <option value="Set">Set</option>
                <option value="Pair">Pair</option>
                <option value="Roll">Roll</option>
                <option value="Box">Box</option>
              </select>
            </div>
          </div>

          <!-- Add Two Images -->
          <div class="form-group" style="margin-bottom: 0;">
            <label style="font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center;">
              <span>Item Photos (Maximum 2 Images)</span>
              <span style="font-size: 0.68rem; color: #64748b; font-weight: 500;">JPG, PNG, WEBP</span>
            </label>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
              <!-- Image 1 Slot -->
              <div id="editReqImg1Box" style="border: 1.5px dashed #cbd5e1; border-radius: 6px; padding: 10px 8px; background: #f8fafc; text-align: center; cursor: pointer; position: relative; transition: all 0.15s ease;"
                   onclick="document.getElementById('editReqImg1Input').click();">
                <input type="file" name="image_1" id="editReqImg1Input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewEditCodeImage(1, this);">
                <div id="editReqImg1Placeholder">
                  <div style="font-size: 1.3rem; line-height: 1; margin-bottom: 3px;">📷</div>
                  <div style="font-size: 0.74rem; font-weight: 700; color: #334155;">Image 1</div>
                  <div style="font-size: 0.64rem; color: #94a3b8;">Click to upload</div>
                </div>
                <div id="editReqImg1Preview" style="display: none; position: relative;">
                  <img id="editReqImg1Tag" src="" alt="Image 1" style="max-height: 70px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                  <button type="button" onclick="event.stopPropagation(); removeEditCodeImage(1);" title="Remove Image"
                    style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: #ffffff; border: 1px solid #b91c1c; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;">&times;</button>
                </div>
              </div>

              <!-- Image 2 Slot -->
              <div id="editReqImg2Box" style="border: 1.5px dashed #cbd5e1; border-radius: 6px; padding: 10px 8px; background: #f8fafc; text-align: center; cursor: pointer; position: relative; transition: all 0.15s ease;"
                   onclick="document.getElementById('editReqImg2Input').click();">
                <input type="file" name="image_2" id="editReqImg2Input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewEditCodeImage(2, this);">
                <div id="editReqImg2Placeholder">
                  <div style="font-size: 1.3rem; line-height: 1; margin-bottom: 3px;">📷</div>
                  <div style="font-size: 0.74rem; font-weight: 700; color: #334155;">Image 2</div>
                  <div style="font-size: 0.64rem; color: #94a3b8;">Click to upload</div>
                </div>
                <div id="editReqImg2Preview" style="display: none; position: relative;">
                  <img id="editReqImg2Tag" src="" alt="Image 2" style="max-height: 70px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                  <button type="button" onclick="event.stopPropagation(); removeEditCodeImage(2);" title="Remove Image"
                    style="position: absolute; top: -6px; right: -6px; background: #dc2626; color: #ffffff; border: 1px solid #b91c1c; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; line-height: 1;">&times;</button>
                </div>
              </div>
            </div>
          </div>

        </div>

        <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #cbd5e1; padding: 10px 18px; display: flex; justify-content: flex-end; gap: 8px;">
          <button type="button" class="btn btn-secondary btn-sm" data-close-modal onclick="window.closeModal('editCodeRequestModal');" style="padding: 6px 14px; font-weight: 600; font-size: 0.78rem;">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitEditReq" style="padding: 6px 18px; font-weight: 700; font-size: 0.78rem; background: #0284c7; border: 1px solid #0369a1; color: #ffffff;">
            Save Changes
          </button>
        </div>
      </form>

    </div>
  </div>

  <!-- =======================================================================
       ITEM SPECIFICATION & IMAGE VIEW POP-UP MODAL (DUAL IMAGES + PRICE + SPECS)
       ======================================================================= -->
  <div class="modal-backdrop" id="viewItemDetailsModal" style="z-index: 10050;" onclick="if (event.target === this) window.closeViewItemModal();">
    <div class="modal-dialog" style="width: 860px; max-width: 95vw; max-height: 92vh; background: #ffffff; border-radius: 10px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); display: flex; flex-direction: column; overflow: hidden; border: 1px solid #cbd5e1;">
      
      <!-- Modal Header -->
      <div class="modal-header" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-bottom: 1px solid #cbd5e1; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <span style="font-size: 0.74rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px;">
            CATALOG ITEM SPECIFICATION
          </span>
          <span id="viewModalFormattedCode" style="font-family: monospace; font-size: 1.15rem; font-weight: 800; color: #650b2b; background: #ffffff; border: 1.5px solid #650b2b; padding: 3px 12px; border-radius: 4px; letter-spacing: 1px;">
            --
          </span>
          <span id="viewModalStatusBadge" style="font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 12px; white-space: nowrap;">
            --
          </span>
        </div>
        <button type="button" class="btn-close" data-close-modal onclick="window.closeViewItemModal();" style="background: none; border: none; font-size: 1.4rem; color: #64748b; cursor: pointer; line-height: 1; padding: 2px 6px;" title="Close Window">&times;</button>
      </div>

      <!-- Modal Body (Scrollable) -->
      <div class="modal-body" style="padding: 18px 20px; overflow-y: auto; background: #f8fafc; flex: 1; display: flex; flex-direction: column; gap: 16px;">
        
        <!-- TOP SECTION: MAXIMUM TWO IMAGES SHOWCASE -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <label style="font-size: 0.74rem; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
              <span>🖼️ Item Images (Maximum 2 Photos)</span>
            </label>
            <span style="font-size: 0.7rem; color: #64748b;">Primary Photo &amp; Technical Diagram</span>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
            
            <!-- IMAGE 1 SLOT -->
            <div id="viewModalImg1Slot" style="background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 8px; min-height: 190px; padding: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
              <!-- Rendered via JS -->
            </div>

            <!-- IMAGE 2 SLOT -->
            <div id="viewModalImg2Slot" style="background: #ffffff; border: 1.5px dashed #cbd5e1; border-radius: 8px; min-height: 190px; padding: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
              <!-- Rendered via JS -->
            </div>

          </div>
        </div>

        <!-- MIDDLE SECTION: DESCRIPTION & FULL HIERARCHY -->
        <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
          
          <div style="margin-bottom: 12px;">
            <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">ITEM DESCRIPTION / NAME</span>
            <div id="viewModalItemName" style="font-size: 1.05rem; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-top: 2px; line-height: 1.35;">
              --
            </div>
          </div>

          <div id="viewModalSpecsContainer" style="margin-bottom: 14px;">
            <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">SPECIFICATIONS &amp; PART NUMBER</span>
            <div id="viewModalSpecs" style="font-size: 0.82rem; color: #334155; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; margin-top: 3px; font-weight: 500;">
              No additional technical specifications provided.
            </div>
          </div>

          <!-- Classification Hierarchy Stack -->
          <div>
            <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; display: block;">CLASSIFICATION PATHWAY</span>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;">
              <div style="background: #fdf2f5; border: 1px solid #fbcfe8; border-radius: 4px; padding: 6px 10px;">
                <span style="font-size: 0.65rem; font-weight: 800; color: #9f1239; text-transform: uppercase; display: block;">1ST TIER (MAIN)</span>
                <span id="viewModalTier1" style="font-size: 0.78rem; font-weight: 700; color: #650b2b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">--</span>
              </div>
              <div style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px;">
                <span style="font-size: 0.65rem; font-weight: 800; color: #475569; text-transform: uppercase; display: block;">2ND TIER (SUB-GROUP)</span>
                <span id="viewModalTier2" style="font-size: 0.78rem; font-weight: 700; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">--</span>
              </div>
              <div style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 4px; padding: 6px 10px;">
                <span style="font-size: 0.65rem; font-weight: 800; color: #0369a1; text-transform: uppercase; display: block;">3RD TIER (SPECIFIC)</span>
                <span id="viewModalTier3" style="font-size: 0.78rem; font-weight: 700; color: #0284c7; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">--</span>
              </div>
            </div>
          </div>

        </div>

        <!-- BOTTOM SECTION: UNIT OF MEASUREMENT & PRICE (WITH DARK TYPE IF NO PRICE) -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
          
          <!-- UOM CARD -->
          <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; display: flex; flex-direction: column; justify-content: center;">
            <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">UNIT OF MEASUREMENT (UOM)</span>
            <div style="margin-top: 4px; display: flex; align-items: baseline; gap: 8px;">
              <span id="viewModalUom" style="font-size: 1.25rem; font-weight: 800; color: #0f172a; text-transform: uppercase;">--</span>
              <span style="font-size: 0.75rem; color: #64748b;">(Standard Inventory Metric)</span>
            </div>
          </div>

          <!-- PRICE CARD (DARK TYPE IF NO PRICE ADDED) -->
          <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 16px; display: flex; flex-direction: column; justify-content: center;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
              <span style="font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">CATALOG UNIT PRICE</span>
              <button type="button" id="viewModalEditPriceBtn" onclick="toggleViewItemPriceInput();" style="display: none; background: none; border: none; font-size: 0.72rem; font-weight: 700; color: #0284c7; cursor: pointer; padding: 0;">
                ✏️ Edit Price
              </button>
            </div>

            <!-- Price Display Area -->
            <div id="viewModalPriceContainer" style="margin-top: 2px;">
              <!-- Either active price or DARK TYPE badge rendered via JS -->
            </div>

            <!-- Inline Price Edit Form (Hidden by default, shown for Code Admin) -->
            <div id="viewModalPriceEditForm" style="display: none; margin-top: 8px; gap: 6px;">
              <div style="display: flex; gap: 6px; align-items: center;">
                <input type="number" step="0.01" min="0" id="viewModalPriceInput" class="form-control" placeholder="Enter Price (LKR)" style="height: 30px; font-size: 0.8rem; padding: 2px 8px;">
                <button type="button" onclick="saveViewItemPrice();" class="btn btn-sm" style="background: #650b2b; color: #ffffff; font-size: 0.72rem; font-weight: 700; height: 30px; padding: 0 10px; border: 1px solid #4a071e; white-space: nowrap;">
                  Save
                </button>
                <button type="button" onclick="toggleViewItemPriceInput(false);" class="btn btn-sm btn-secondary" style="height: 30px; font-size: 0.72rem; padding: 0 8px;">
                  Cancel
                </button>
              </div>
            </div>
          </div>

        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer" style="background: #ffffff; border-top: 1px solid #cbd5e1; padding: 10px 18px; display: flex; justify-content: space-between; align-items: center;">
        <button type="button" id="viewModalGoogleBtn" class="btn btn-sm" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.76rem; font-weight: 700; padding: 6px 12px; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
          🌐 Search Datasheet on Google
        </button>

        <div style="display: flex; gap: 8px; align-items: center;">
          <button type="button" id="viewModalActionBtn" class="btn btn-sm" style="display: none; font-size: 0.76rem; font-weight: 700; padding: 6px 14px; border-radius: 4px;">
            --
          </button>
          <button type="button" class="btn btn-secondary btn-sm" data-close-modal onclick="window.closeViewItemModal();" style="padding: 6px 14px; font-weight: 600; font-size: 0.78rem;">
            Close
          </button>
        </div>
      </div>

    </div>
  </div>

  <!-- Hidden File Input for uploading Image 1 & Image 2 -->
  <input type="file" id="viewItemHiddenFileInput1" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="handleItemImageFileSelected(1, this);">
  <input type="file" id="viewItemHiddenFileInput2" accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="handleItemImageFileSelected(2, this);">

  <?php include APP_PATH . '/../views/components/issue_codes_modals.php'; ?>

  <script>
    window.APP_DATA = {
      locations: <?= json_encode($locations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      departments: <?= json_encode($departments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      roles: <?= json_encode($allRoles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      permissionsMatrix: <?= json_encode($permissionsMatrix, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      permissionCategories: <?= json_encode($permissionCategories, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      tierHierarchy: <?= json_encode($allTiersGrouped, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      tier1List: <?= json_encode($tier1List, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      userStationId: <?= (int)($activeLocationId ?: 1) ?>,
      stationAddedItems: <?= json_encode($activeStationItemsCompact, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
      isAdmin: <?= json_encode($isAdmin) ?>,
      isCodeAdmin: <?= json_encode($isCodeAdmin) ?>,
      userRole: <?= json_encode($userRole) ?>,
      csrfToken: <?= json_encode(Session::csrfToken()) ?>,
      dbUnpostedDocs: <?= json_encode($dbUnpostedMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
    };
    window.dbUnpostedDocs = window.APP_DATA.dbUnpostedDocs || {};
    window.STATION_ADDED_ITEMS = window.APP_DATA.stationAddedItems || [];
  </script>
  <script>
    // Full-Width Pure White Custom Searchable Material Dropdown
    function openReceiptMaterialDropdown(menuId) {
      const menu = document.getElementById(menuId);
      if (menu) menu.style.display = 'block';
    }

    function toggleReceiptMaterialDropdown(menuId) {
      const menu = document.getElementById(menuId);
      if (menu) {
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
      }
    }

    function filterReceiptMaterialDropdown(query, menuId) {
      const menu = document.getElementById(menuId);
      if (!menu) return;
      menu.style.display = 'block';
      const items = menu.querySelectorAll('.material-dropdown-item');
      const q = query.trim().toLowerCase();
      items.forEach(el => {
        const text = el.textContent.toLowerCase();
        if (!q || text.includes(q)) {
          el.style.display = 'block';
        } else {
          el.style.display = 'none';
        }
      });
    }

    function selectReceiptMaterial(val, inputId, menuId) {
      const input = document.getElementById(inputId);
      if (input) input.value = val;
      const menu = document.getElementById(menuId);
      if (menu) menu.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest('#receiptMaterialWrapper')) {
        const m1 = document.getElementById('receiptMaterialDropdownMenu');
        if (m1) m1.style.display = 'none';
      }
      if (!e.target.closest('#editReceiptMaterialWrapper')) {
        const m2 = document.getElementById('editReceiptMaterialDropdownMenu');
        if (m2) m2.style.display = 'none';
      }
    });
  </script>
  <div id="toast-container"></div>
  <script src="<?= $base ?>/assets/js/app.js?v=<?= time() ?>"></script>
  <script src="<?= $base ?>/assets/js/admin.js?v=<?= time() ?>"></script>
  <script src="<?= $base ?>/assets/js/item_search.js?v=<?= time() ?>"></script>
</body>

</html>