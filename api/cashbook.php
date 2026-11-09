<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Config\Database;

Auth::requireLogin();

$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');
$db = Database::getConnection();

// Validate CSRF for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!Session::validateCsrf($csrfToken)) {
        Response::error('Invalid security token. Please refresh the page.', [], 403);
    }
}

// Ensure payslip_cancellations table exists
$isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
$pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
$db->exec("
    CREATE TABLE IF NOT EXISTS payslip_cancellations (
        id $pkType,
        cancellation_ref VARCHAR(50) NOT NULL UNIQUE,
        payslip_no VARCHAR(50) NOT NULL,
        employee_name VARCHAR(150) NOT NULL,
        epf_no VARCHAR(50) DEFAULT NULL,
        pay_period VARCHAR(30) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        reason TEXT NOT NULL,
        requested_by INTEGER NULL,
        status VARCHAR(30) DEFAULT 'Pending Review',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

switch ($action) {
    case 'create_payslip_cancellation':
        $cancellationRef = trim($_POST['cancellation_ref'] ?? ('CAN-' . date('Ymd-His')));
        $payslipNo = trim($_POST['payslip_no'] ?? '');
        $employeeName = trim($_POST['employee_name'] ?? '');
        $epfNo = trim($_POST['epf_no'] ?? '');
        $payPeriod = trim($_POST['pay_period'] ?? date('F Y'));
        $amount = (float)($_POST['amount'] ?? 0.00);
        $reason = trim($_POST['reason'] ?? '');

        if (empty($payslipNo) || empty($employeeName) || empty($reason)) {
            Response::error('Please fill in all mandatory fields: Payslip No, Employee Name, and Reason.');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO payslip_cancellations 
                (cancellation_ref, payslip_no, employee_name, epf_no, pay_period, amount, reason, requested_by, status)
                VALUES 
                (:cancellation_ref, :payslip_no, :employee_name, :epf_no, :pay_period, :amount, :reason, :requested_by, 'Pending Review')
            ");
            $stmt->execute([
                ':cancellation_ref' => $cancellationRef,
                ':payslip_no' => $payslipNo,
                ':employee_name' => $employeeName,
                ':epf_no' => $epfNo,
                ':pay_period' => $payPeriod,
                ':amount' => $amount,
                ':reason' => $reason,
                ':requested_by' => Auth::id() ?: 1
            ]);

            // Also record in activity logs
            $userId = Auth::id() ?: 1;
            $stmtLog = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtLog->execute([
                $userId,
                'payslip_cancellation_requested',
                'payslip_cancellation',
                (int)$db->lastInsertId(),
                "Payslip cancellation request $cancellationRef logged for $employeeName ($payslipNo) amounting to Rs. " . number_format($amount, 2),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);

            Response::success('Payslip cancellation request registered successfully with reference: ' . $cancellationRef);
        } catch (\PDOException $e) {
            Response::error('Database error: ' . $e->getMessage());
        }
        break;

    case 'list_cancellations':
        try {
            $stmt = $db->query("SELECT * FROM payslip_cancellations ORDER BY created_at DESC LIMIT 100");
            $rows = $stmt->fetchAll() ?: [];
            Response::success('Payslip cancellations loaded', $rows);
        } catch (\PDOException $e) {
            Response::error('Failed to load payslip cancellations: ' . $e->getMessage());
        }
        break;

    default:
        Response::error('Invalid action requested.');
        break;
}
