-- =========================================================================
-- FIFO Enterprise System Database Schema (MySQL 8+ / MariaDB)
-- Database Name: fifo
-- Generated for: Electricity Generation Lanka (Pvt) Ltd
-- =========================================================================

CREATE DATABASE IF NOT EXISTS `fifo` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fifo`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Roles Table
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `display_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Locations / Power Stations Table
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(120) NOT NULL UNIQUE,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `type` VARCHAR(50) DEFAULT 'Hydroelectric',
    `capacity_mw` VARCHAR(50) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `district` VARCHAR(100) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `location_id` INT DEFAULT 1,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `code` VARCHAR(30) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `head_of_department` VARCHAR(100) DEFAULT NULL,
    `status` VARCHAR(20) DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dept_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(120) NOT NULL,
    `department` VARCHAR(100) DEFAULT 'General',
    `phone` VARCHAR(30) DEFAULT NULL,
    `avatar_color` VARCHAR(10) DEFAULT '#6366f1',
    `status` VARCHAR(20) DEFAULT 'active',
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Role Permissions Matrix Table
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `permission_key` VARCHAR(100) NOT NULL,
    `is_granted` TINYINT DEFAULT 1,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_perm` (`role_id`, `permission_key`),
    CONSTRAINT `fk_perm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. FIFO Inventory Batches Table
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
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_batch_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Inventory Transactions Table
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `batch_id` INT NULL,
    `transaction_type` VARCHAR(20) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_value` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `transaction_date` DATE NOT NULL,
    `reference_no` VARCHAR(60) NULL,
    `created_by` INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tx_batch` FOREIGN KEY (`batch_id`) REFERENCES `fifo_batches` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Cash Book Entries Table
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

-- 10. Authorized Tasks Table
CREATE TABLE IF NOT EXISTS `authorized_tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(200) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `priority` VARCHAR(20) DEFAULT 'Standard',
    `status` VARCHAR(30) DEFAULT 'Pending Approval',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Payslip Cancellations Table
CREATE TABLE IF NOT EXISTS `payslip_cancellations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cancellation_ref` VARCHAR(50) NOT NULL UNIQUE,
    `payslip_no` VARCHAR(50) NOT NULL,
    `employee_name` VARCHAR(150) NOT NULL,
    `epf_no` VARCHAR(50) DEFAULT NULL,
    `pay_period` VARCHAR(30) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reason` TEXT NOT NULL,
    `requested_by` INT NULL,
    `status` VARCHAR(30) DEFAULT 'Pending Review',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Stock Transfers Table
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

-- 13. CPC Fuel Procurements Table
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

-- 14. Diesel Price Logs Table
CREATE TABLE IF NOT EXISTS `diesel_price_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `effective_month` VARCHAR(20) NOT NULL,
    `fuel_type` VARCHAR(100) NOT NULL,
    `avg_price_per_litre` DECIMAL(10,2) NOT NULL,
    `total_procured_litres` DECIMAL(14,2) NOT NULL,
    `total_procurement_cost` DECIMAL(14,2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Cost Adjustments Table
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
    `authorized_by` INT NULL,
    `status` VARCHAR(30) DEFAULT 'Approved & Applied',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Quantity Corrections Table
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
    `authorized_by` INT NULL,
    `status` VARCHAR(30) DEFAULT 'Corrected & Unlocked',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
