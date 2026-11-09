<?php
namespace App\Core;

use App\Config\Database;
use App\Models\Location;
use PDO;
use PDOException;
use Exception;

class StationDatabase {

    /**
     * Generate safe, clean MySQL database name for a station
     */
    public static function getDbName(string $code, string $name): string {
        $cleanCode = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($code));
        // Remove common words like 'power station', 'station' for clean concise naming
        $cleanName = strtolower($name);
        $cleanName = str_replace(['power station', 'power plant', 'station'], '', $cleanName);
        $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', trim($cleanName));
        $cleanName = trim(preg_replace('/_+/', '_', $cleanName), '_');

        if (!empty($cleanCode) && !empty($cleanName)) {
            return "fifo_{$cleanCode}_{$cleanName}";
        } elseif (!empty($cleanCode)) {
            return "fifo_{$cleanCode}";
        } elseif (!empty($cleanName)) {
            return "fifo_{$cleanName}";
        }
        return "fifo_station_" . time();
    }

    /**
     * Get the station-specific DDL schema
     */
    public static function getStationSchemaSql(): string {
        return "
            CREATE TABLE IF NOT EXISTS `fifo_batches` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `batch_number` VARCHAR(50) NOT NULL UNIQUE,
                `sku` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(150) NOT NULL,
                `category` VARCHAR(100) DEFAULT 'Standard',
                `initial_quantity` INT NOT NULL,
                `remaining_quantity` INT NOT NULL,
                `unit_cost` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                `received_date` DATE NOT NULL,
                `expiry_date` DATE DEFAULT NULL,
                `location_bin` VARCHAR(50) DEFAULT 'Zone A-01',
                `status` VARCHAR(20) DEFAULT 'queued',
                `created_by` INT NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `inventory_transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `batch_id` INT NULL,
                `transaction_type` VARCHAR(20) NOT NULL,
                `quantity` INT NOT NULL,
                `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `transaction_date` DATE NOT NULL,
                `reference_no` VARCHAR(60) NULL,
                `created_by` INT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT `fk_tx_batch` FOREIGN KEY (`batch_id`) REFERENCES `fifo_batches` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `cash_book_entries` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `entry_date` DATE NOT NULL,
                `voucher_no` VARCHAR(50) NOT NULL,
                `entry_type` VARCHAR(20) NOT NULL,
                `account_title` VARCHAR(150) NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL,
                `status` VARCHAR(30) DEFAULT 'Verified',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `authorized_tasks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_code` VARCHAR(50) NOT NULL UNIQUE,
                `title` VARCHAR(200) NOT NULL,
                `department` VARCHAR(100) NOT NULL,
                `priority` VARCHAR(20) DEFAULT 'Standard',
                `status` VARCHAR(30) DEFAULT 'Pending Approval',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `payslip_cancellations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `cancellation_ref` VARCHAR(50) NOT NULL UNIQUE,
                `payslip_no` VARCHAR(50) NOT NULL,
                `employee_name` VARCHAR(150) NOT NULL,
                `epf_no` VARCHAR(50) DEFAULT NULL,
                `pay_period` VARCHAR(30) NOT NULL,
                `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `reason` TEXT NOT NULL,
                `requested_by` INT NULL DEFAULT 1,
                `status` VARCHAR(30) DEFAULT 'Pending Review',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `stock_transfers` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `transfer_ref` VARCHAR(50) NOT NULL UNIQUE,
                `from_bay` VARCHAR(100) NOT NULL,
                `to_department` VARCHAR(100) NOT NULL,
                `sku` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(150) NOT NULL,
                `quantity` INT NOT NULL,
                `transfer_date` DATE NOT NULL,
                `status` VARCHAR(30) DEFAULT 'Completed',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `cpc_procurements` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_no` VARCHAR(50) NOT NULL UNIQUE,
                `fuel_type` VARCHAR(100) NOT NULL,
                `tank_id` VARCHAR(50) NOT NULL,
                `quantity_litres` DECIMAL(12,2) NOT NULL,
                `unit_price_lkr` DECIMAL(10,2) NOT NULL,
                `total_amount_lkr` DECIMAL(14,2) NOT NULL,
                `consignment_date` DATE NOT NULL,
                `delivery_ref` VARCHAR(60) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `diesel_price_logs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `effective_month` VARCHAR(20) NOT NULL,
                `fuel_type` VARCHAR(100) NOT NULL,
                `avg_price_per_litre` DECIMAL(10,2) NOT NULL,
                `total_procured_litres` DECIMAL(14,2) NOT NULL,
                `total_procurement_cost` DECIMAL(14,2) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `cost_adjustments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `adjustment_ref` VARCHAR(50) NOT NULL UNIQUE,
                `batch_id` INT NULL,
                `sku` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(150) NOT NULL,
                `old_unit_cost` DECIMAL(10,2) NOT NULL,
                `new_unit_cost` DECIMAL(10,2) NOT NULL,
                `variance_per_unit` DECIMAL(10,2) NOT NULL,
                `remaining_qty` INT NOT NULL,
                `total_valuation_delta` DECIMAL(12,2) NOT NULL,
                `reason` TEXT NOT NULL,
                `authorized_by` INT NULL DEFAULT 1,
                `status` VARCHAR(30) DEFAULT 'Approved & Applied',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `qty_corrections` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `correction_ref` VARCHAR(50) NOT NULL UNIQUE,
                `batch_id` INT NULL,
                `sku` VARCHAR(50) NOT NULL,
                `item_name` VARCHAR(150) NOT NULL,
                `allocated_qty_before` INT NOT NULL,
                `corrected_allocated_qty` INT NOT NULL,
                `qty_released_to_free_stock` INT NOT NULL,
                `work_order_ref` VARCHAR(60) DEFAULT NULL,
                `reason` TEXT NOT NULL,
                `authorized_by` INT NULL DEFAULT 1,
                `status` VARCHAR(30) DEFAULT 'Corrected & Unlocked',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `unposted_documents` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `doc_no` VARCHAR(60) NOT NULL UNIQUE,
                `doc_type` VARCHAR(20) NOT NULL DEFAULT 'receipt',
                `doc_profile` VARCHAR(100) NOT NULL DEFAULT 'Goods Received Note',
                `doc_profile_code` VARCHAR(30) NOT NULL DEFAULT 'PF-GRN',
                `status` VARCHAR(30) NOT NULL DEFAULT 'Draft',
                `receipt_from` VARCHAR(150) NOT NULL DEFAULT 'Purchase Order',
                `source_dept` VARCHAR(50) NOT NULL DEFAULT '832.00',
                `receipt_details` TEXT NULL,
                `delivery_date` DATE NULL,
                `acc_date` DATE NULL,
                `due_date` DATE NULL,
                `warehouse` VARCHAR(150) NOT NULL DEFAULT 'Mechanical Stores',
                `vat` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `transport` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `nbt` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `other_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `invoice_number` VARCHAR(100) NULL,
                `order_reference` VARCHAR(100) NULL,
                `other_ref_1` VARCHAR(100) NULL,
                `other_ref_2` VARCHAR(100) NULL,
                `remarks` TEXT NULL,
                `invoice_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                `is_single_material` TINYINT NOT NULL DEFAULT 0,
                `discount_for_all` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `is_percentage` TINYINT NOT NULL DEFAULT 0,
                `location_id` INT NULL,
                `created_by` INT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
    }

    /**
     * Create and provision a dedicated database for a power station
     */
    public static function createStationDb(string $code, string $name): string {
        $dbName = self::getDbName($code, $name);

        if (DB_DRIVER === 'mysql') {
            // 1. Connect without dbname to execute CREATE DATABASE
            $pdo = new PDO(
                sprintf("mysql:host=%s;port=%s;charset=%s", DB_HOST, DB_PORT, DB_CHARSET),
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // 2. Create the station database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

            // 3. Connect to newly created station database and initialize tables
            $stationPdo = new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s;charset=%s", DB_HOST, DB_PORT, $dbName, DB_CHARSET),
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            // 4. Run schema migration
            $stationPdo->exec(self::getStationSchemaSql());
            
            // 5. Seed default starter batches if empty
            self::seedDefaultBatchesIfEmpty($stationPdo, $code, $name);
        }

        return $dbName;
    }

    /**
     * Safely delete a station database
     */
    public static function deleteStationDb(string $dbName): bool {
        $cleanDb = trim($dbName);
        // Safety checks: ensure it is a station database and not the main 'fifo' database
        if (empty($cleanDb) || $cleanDb === DB_NAME || !str_starts_with($cleanDb, 'fifo_')) {
            return false;
        }

        if (DB_DRIVER === 'mysql') {
            try {
                $pdo = new PDO(
                    sprintf("mysql:host=%s;port=%s;charset=%s", DB_HOST, DB_PORT, DB_CHARSET),
                    DB_USER,
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->exec("DROP DATABASE IF EXISTS `{$cleanDb}`;");
                return true;
            } catch (Exception $e) {
                error_log("Failed to drop station database {$cleanDb}: " . $e->getMessage());
                return false;
            }
        }
        return false;
    }

    /**
     * Provision databases for all locations that do not have one yet
     */
    public static function ensureAllStationDatabases(): array {
        $locModel = new Location();
        $locations = $locModel->getAll();
        $results = [];

        foreach ($locations as $loc) {
            $expectedDbName = !empty($loc['database_name']) 
                ? $loc['database_name'] 
                : self::getDbName($loc['code'], $loc['name']);

            try {
                $createdDb = self::createStationDb($loc['code'], $loc['name']);
                if (empty($loc['database_name']) || $loc['database_name'] !== $createdDb) {
                    $locModel->update((int)$loc['id'], ['database_name' => $createdDb]);
                }
                $results[$loc['code']] = [
                    'station' => $loc['name'],
                    'database' => $createdDb,
                    'status' => 'online'
                ];
            } catch (Exception $e) {
                $results[$loc['code']] = [
                    'station' => $loc['name'],
                    'database' => $expectedDbName,
                    'status' => 'error: ' . $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Seed initial sample batches for the station if empty
     */
    private static function seedDefaultBatchesIfEmpty(PDO $stationPdo, string $code, string $name): void {
        $count = $stationPdo->query("SELECT COUNT(*) FROM fifo_batches")->fetchColumn();
        if ((int)$count === 0) {
            $shortCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $code), 0, 4));
            $stmt = $stationPdo->prepare("
                INSERT INTO `fifo_batches` (`batch_number`, `sku`, `item_name`, `category`, `initial_quantity`, `remaining_quantity`, `unit_cost`, `received_date`, `location_bin`, `status`, `created_by`)
                VALUES 
                (:b1, :sku1, :item1, 'Turbine Spare', 50, 50, 1450.00, '2026-09-01', 'Bay-A1', 'active', 1),
                (:b2, :sku2, :item2, 'Lubricants', 200, 180, 320.00, '2026-09-10', 'Bay-L2', 'active', 1),
                (:b3, :sku3, :item3, 'Heavy Fuel / Diesel', 5000, 4200, 375.50, '2026-09-15', 'Tank-01', 'active', 1)
            ");
            $stmt->execute([
                ':b1' => "BAT-{$shortCode}-001",
                ':sku1' => "SKU-{$shortCode}-TRB-01",
                ':item1' => "Turbine Bearing Assembly - {$name}",
                ':b2' => "BAT-{$shortCode}-002",
                ':sku2' => "SKU-{$shortCode}-LUB-02",
                ':item2' => "Synthetic Transformer Oil ISO-VG46",
                ':b3' => "BAT-{$shortCode}-003",
                ':sku3' => "SKU-{$shortCode}-DSL-01",
                ':item3' => "Industrial Low Sulfur Gas Oil (Lanka Auto Diesel)"
            ]);
        }
    }
}
