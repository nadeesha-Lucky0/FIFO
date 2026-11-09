-- FIFO Enterprise System Database Schema (SQLite)

CREATE TABLE IF NOT EXISTS roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    department VARCHAR(100) DEFAULT 'General',
    phone VARCHAR(30) DEFAULT NULL,
    avatar_color VARCHAR(10) DEFAULT '#6366f1',
    status VARCHAR(20) DEFAULT 'active' CHECK(status IN ('active', 'suspended', 'pending')),
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    code VARCHAR(30) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    head_of_department VARCHAR(100) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active' CHECK(status IN ('active', 'inactive')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INTEGER DEFAULT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Future FIFO queue & batch tracking foundation
CREATE TABLE IF NOT EXISTS fifo_batches (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    batch_number VARCHAR(50) NOT NULL UNIQUE,
    sku VARCHAR(50) NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(100) DEFAULT 'Standard',
    initial_quantity INTEGER NOT NULL,
    remaining_quantity INTEGER NOT NULL,
    unit_cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    received_date DATE NOT NULL,
    expiry_date DATE DEFAULT NULL,
    location_bin VARCHAR(50) DEFAULT 'Zone A-01',
    status VARCHAR(20) DEFAULT 'queued' CHECK(status IN ('queued', 'active_dispatch', 'depleted', 'expired')),
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Seed Default Roles
INSERT OR IGNORE INTO roles (id, name, display_name, description) VALUES
(1, 'super_admin', 'Super Administrator', 'Full unrestricted control over company settings, users, and audit logs'),
(2, 'code_admin', 'Code Administrator', 'Administrator dedicated exclusively to location, department, and system code management'),
(3, 'admin', 'System Administrator', 'Manages users, departments, and system operations'),
(4, 'inventory_manager', 'Inventory Manager', 'Manages FIFO batches, stock-in, stock-out queues, and supplier logs'),
(5, 'operator', 'FIFO Operator / Staff', 'Executes stock picking according to FIFO queue rules and updates counts'),
(6, 'viewer', 'Auditor / Viewer', 'Read-only access to inventory reports and batch timestamps');

-- Seed Default Admin Users
INSERT OR IGNORE INTO users (id, role_id, username, email, password_hash, full_name, department, phone, avatar_color, status) VALUES
(1, 1, 'admin', 'admin@egl.lk', '$2y$10$cbHdHrNdIHvAH8hAUvnRAublDyZoMTq292zOj85HCJP/x0VGu6YCe', 'Super Administrator', 'Administration & IT', '+94 (11) 234-5678', '#650b2b', 'active'),
(2, 2, 'admin01', 'admin01@egl.lk', '$2y$10$P.tSQDxo/tKMEoXycY8AVuja.shRvATl2WOScBCiUyyAKKK42Yl/i', 'Code Admin (admin01)', 'Code Management', '0112345678', '#0284c7', 'active');

-- Initial Activity Log
INSERT OR IGNORE INTO activity_logs (id, user_id, action, entity_type, entity_id, details, ip_address) VALUES
(1, 1, 'system_init', 'system', 1, 'EGL Super Admin System initialized ready for enterprise data configuration.', '127.0.0.1');
