<?php
require_once __DIR__ . '/../app/config/config.php';

use App\Config\Database;

echo "Migrating Code Management & Master Item Tables...\n";

function migrate($db, $name, $isMysql = true) {
    if (!$db) return;
    echo "Applying migrations to $name...\n";
    
    $pk = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
    $bigPk = $isMysql ? "BIGINT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
    
    // 1. Tier Lookup Definitions
    if ($isMysql) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tier_categories (
                id $pk,
                tier_level INT NOT NULL,
                code_val INT NOT NULL,
                parent_id INT NULL,
                name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_tier_val (tier_level, code_val, parent_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tier_categories (
                id $pk,
                tier_level INTEGER NOT NULL,
                code_val INTEGER NOT NULL,
                parent_id INTEGER NULL,
                name TEXT NOT NULL,
                description TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (tier_level, code_val, parent_id)
            );
        ");
    }

    // 2. FIFO Central Master Items DB
    if ($isMysql) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS fifo_master_items (
                id $bigPk,
                tier1 INT NOT NULL,
                tier2 INT NOT NULL,
                tier3 INT NOT NULL,
                tier4_seq INT NOT NULL,
                formatted_code VARCHAR(30) NOT NULL,
                raw_code VARCHAR(20) NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                specifications TEXT NULL,
                uom VARCHAR(30) NOT NULL,
                status VARCHAR(20) DEFAULT 'ACTIVE',
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_formatted_code (formatted_code),
                UNIQUE KEY uk_raw_code (raw_code),
                UNIQUE KEY uk_tier_branch_seq (tier1, tier2, tier3, tier4_seq)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS fifo_master_items (
                id $bigPk,
                tier1 INTEGER NOT NULL,
                tier2 INTEGER NOT NULL,
                tier3 INTEGER NOT NULL,
                tier4_seq INTEGER NOT NULL,
                formatted_code TEXT NOT NULL UNIQUE,
                raw_code TEXT NOT NULL UNIQUE,
                item_name TEXT NOT NULL,
                specifications TEXT NULL,
                uom TEXT NOT NULL,
                status TEXT DEFAULT 'ACTIVE',
                created_by INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (tier1, tier2, tier3, tier4_seq)
            );
        ");
    }

    // 3. Code Creation Requests (Approval Staging)
    if ($isMysql) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS code_requests (
                id $bigPk,
                request_no VARCHAR(50) NOT NULL UNIQUE,
                tier1 INT NOT NULL,
                tier2 INT NOT NULL,
                tier3 INT NOT NULL,
                suggested_tier4_seq INT NULL,
                item_name VARCHAR(255) NOT NULL,
                specifications TEXT NULL,
                uom VARCHAR(30) NOT NULL,
                origin_station_id INT NOT NULL,
                requested_by INT NOT NULL,
                status VARCHAR(20) DEFAULT 'PENDING',
                reviewed_by INT NULL,
                review_remarks TEXT NULL,
                assigned_tier4_seq INT NULL,
                generated_master_item_id BIGINT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS code_requests (
                id $bigPk,
                request_no TEXT NOT NULL UNIQUE,
                tier1 INTEGER NOT NULL,
                tier2 INTEGER NOT NULL,
                tier3 INTEGER NOT NULL,
                suggested_tier4_seq INTEGER NULL,
                item_name TEXT NOT NULL,
                specifications TEXT NULL,
                uom TEXT NOT NULL,
                origin_station_id INTEGER NOT NULL,
                requested_by INTEGER NOT NULL,
                status TEXT DEFAULT 'PENDING',
                reviewed_by INTEGER NULL,
                review_remarks TEXT NULL,
                assigned_tier4_seq INTEGER NULL,
                generated_master_item_id INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                reviewed_at DATETIME NULL
            );
        ");
    }

    // 4. Station-Specific Inventory Provisioning (Import Table)
    if ($isMysql) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS station_items (
                id $bigPk,
                station_id INT NOT NULL,
                master_item_id BIGINT NOT NULL,
                local_bay_location VARCHAR(100) DEFAULT 'Bay-A1',
                min_reorder_level DECIMAL(12,2) DEFAULT 0,
                max_stock_level DECIMAL(12,2) DEFAULT 0,
                is_active TINYINT DEFAULT 1,
                imported_by INT NOT NULL,
                imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_station_item (station_id, master_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS station_items (
                id $bigPk,
                station_id INTEGER NOT NULL,
                master_item_id INTEGER NOT NULL,
                local_bay_location TEXT DEFAULT 'Bay-A1',
                min_reorder_level NUMERIC DEFAULT 0,
                max_stock_level NUMERIC DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                imported_by INTEGER NOT NULL,
                imported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (station_id, master_item_id)
            );
        ");
    }

    // 5. Seed Standard Tier 1, Tier 2, Tier 3 Categories if empty
    $count = $db->query("SELECT COUNT(*) FROM tier_categories WHERE tier_level = 1")->fetchColumn();
    if ($count == 0) {
        echo "Seeding default 1-19 Tier categories into $name...\n";
        
        $tier1List = [
            1 => ['Mechanical Equipment & Spares', 'Turbines, pumps, valves, bearings, mechanical seals, gears'],
            2 => ['Electrical & Switchgear', 'Generators, transformers, circuit breakers, relays, switchboards'],
            3 => ['Civil & Structural Materials', 'Cement, steel bars, structural beams, masonry, pipes, fasteners'],
            4 => ['Hydraulic & Pneumatic Systems', 'Hydraulic cylinders, actuators, high-pressure hoses, compressors'],
            5 => ['Control, Instrumentation & SCADA', 'PLC modules, RTUs, sensors, transmitters, gauges, transducers'],
            6 => ['Lubricants, Oils & Chemicals', 'Turbine oil, transformer oil, greases, solvents, cooling chemicals'],
            7 => ['Cables, Conductors & Wiring', 'HT/LT power cables, control cables, busbars, termination kits'],
            8 => ['Safety & Personal Protective Equipment', 'Helmets, safety harnesses, high-voltage gloves, fire gear'],
            9 => ['Hardware, Fasteners & Gaskets', 'High tensile bolts, nuts, washers, flanges, spiral wound gaskets'],
            10 => ['Pipes, Fittings & Valves', 'Gate valves, globe valves, check valves, flanges, bends'],
            11 => ['Tools, Testing & Diagnostic Gear', 'Torque wrenches, insulation testers, thermal imagers, multimeters'],
            12 => ['Power Station Consumables', 'Welding rods, grinding discs, rags, adhesives, cleaning fluids'],
            13 => ['Filters & Strainers', 'Oil filter elements, air intake filters, water strainers, cartridges'],
            14 => ['Batteries, UPS & DC Systems', 'Station battery banks, DC chargers, inverters, solar arrays'],
            15 => ['Telecommunication & Network', 'Optic fiber units, Ethernet switches, microwave links, radio'],
            16 => ['HVAC, Cooling & Ventilation', 'Air handling units, chiller compressors, exhaust blowers, ducting'],
            17 => ['Lighting & Luminaire Fittings', 'Explosion-proof LED luminaires, floodlights, emergency lights'],
            18 => ['Workshop Raw Materials', 'Mild steel plates, bronze rods, stainless steel bars, copper stock'],
            19 => ['General & Operational Spares', 'Office logistics, stationery, barcode tags, general supplies']
        ];

        foreach ($tier1List as $code => $data) {
            $stmt = $db->prepare("INSERT INTO tier_categories (tier_level, code_val, parent_id, name, description) VALUES (1, :code, NULL, :name, :desc)");
            $stmt->execute([':code' => $code, ':name' => $data[0], ':desc' => $data[1]]);
            $t1Id = $db->lastInsertId();

            // Populate sample Tier 2 sub-categories (1 to 19 range)
            for ($t2 = 1; $t2 <= 5; $t2++) {
                $t2Name = sprintf("Sub-Category %02d of %s", $t2, substr($data[0], 0, 20));
                if ($code === 1) {
                    $t2Names = [1 => 'Bearings & Bushings', 2 => 'Valves & Actuators', 3 => 'Pumps & Impellers', 4 => 'Gears & Couplings', 5 => 'Turbine Blades & Runners'];
                    $t2Name = $t2Names[$t2] ?? $t2Name;
                } elseif ($code === 2) {
                    $t2Names = [1 => 'Transformers & Bushings', 2 => 'Circuit Breakers (SF6/Vacuum)', 3 => 'Protection Relays', 4 => 'Excitation Systems', 5 => 'Motors & Drives'];
                    $t2Name = $t2Names[$t2] ?? $t2Name;
                }
                $stmt2 = $db->prepare("INSERT INTO tier_categories (tier_level, code_val, parent_id, name, description) VALUES (2, :code, :pid, :name, '')");
                $stmt2->execute([':code' => $t2, ':pid' => $t1Id, ':name' => $t2Name]);
                $t2Id = $db->lastInsertId();

                // Populate sample Tier 3 classifications (1 to 19 range)
                for ($t3 = 1; $t3 <= 4; $t3++) {
                    $t3Name = sprintf("Class %02d Specification", $t3);
                    if ($code === 1 && $t2 === 1) {
                        $t3Names = [1 => 'Deep Groove Ball Bearings', 2 => 'Spherical Roller Bearings', 3 => 'Thrust Bearings', 4 => 'Angular Contact Bearings'];
                        $t3Name = $t3Names[$t3] ?? $t3Name;
                    } elseif ($code === 6) {
                        $t3Names = [1 => 'ISO VG 46 Turbine Oil', 2 => 'ISO VG 68 Heavy Turbine Oil', 3 => 'IEC 60296 Transformer Oil', 4 => 'Lithium EP2 High Temp Grease'];
                        $t3Name = $t3Names[$t3] ?? $t3Name;
                    }
                    $stmt3 = $db->prepare("INSERT INTO tier_categories (tier_level, code_val, parent_id, name, description) VALUES (3, :code, :pid, :name, '')");
                    $stmt3->execute([':code' => $t3, ':pid' => $t2Id, ':name' => $t3Name]);
                }
            }
        }
    }

    // 6. Seed Sample FIFO Master Items if empty
    $masterCount = $db->query("SELECT COUNT(*) FROM fifo_master_items")->fetchColumn();
    if ($masterCount == 0) {
        echo "Seeding initial Master Item codes into $name...\n";
        $sampleItems = [
            [1, 1, 1, 0, '1-01-01-000', '10101000', 'Standard Ball Bearing Spec Template', 'General master reference', 'Nos'],
            [1, 1, 1, 1, '1-01-01-001', '10101001', 'Deep Groove Ball Bearing 6205-2RS1/C3', 'Bore 25mm, OD 52mm, Width 15mm, SKF', 'Nos'],
            [1, 1, 1, 2, '1-01-01-002', '10101002', 'Deep Groove Ball Bearing 6308-2Z/C3', 'Bore 40mm, OD 90mm, Width 23mm, FAG', 'Nos'],
            [1, 1, 1, 3, '1-01-01-003', '10101003', 'Deep Groove Ball Bearing 6312-C3', 'Bore 60mm, OD 130mm, Width 31mm, NSK', 'Nos'],
            [1, 1, 1, 4, '1-01-01-004', '10101004', 'Deep Groove Ball Bearing 6218/C3 VL0241', 'Insulated Generator Bearing, SKF', 'Nos'],
            [1, 1, 1, 5, '1-01-01-005', '10101005', 'Deep Groove Ball Bearing 6004-2RSH', 'Bore 20mm, OD 42mm, Width 12mm', 'Nos'],
            [1, 1, 2, 1, '1-01-02-001', '10102001', 'Spherical Roller Bearing 22220 EK', 'Bore 100mm, Tapered Bore 1:12', 'Nos'],
            [1, 1, 2, 2, '1-01-02-002', '10102002', 'Spherical Roller Bearing 23136 CC/W33', 'Main Turbine Guide Bearing Assembly', 'Nos'],
            [2, 1, 1, 1, '2-01-01-001', '20101001', 'High Voltage Bushing 145kV 1250A RIP', 'Resin Impregnated Paper, Oil-to-Air', 'Nos'],
            [2, 2, 1, 1, '2-02-01-001', '20201001', 'SF6 Gas Circuit Breaker Contact Kit 145kV', 'Main Arcing Contact Set, ABB HPL', 'Set'],
            [6, 1, 1, 1, '6-01-01-001', '60101001', 'Shell Turbo T 46 Premium Turbine Oil', 'High-performance ISO VG 46 Gas & Steam Turbine Oil', 'Ltr'],
            [6, 1, 3, 1, '6-01-03-001', '60103001', 'Nynas Nytro 10XN Inhibited Transformer Oil', 'IEC 60296 Special Dielectric Fluid', 'Ltr']
        ];

        foreach ($sampleItems as $si) {
            $stmt = $db->prepare("
                INSERT INTO fifo_master_items (tier1, tier2, tier3, tier4_seq, formatted_code, raw_code, item_name, specifications, uom, status, created_by)
                VALUES (:t1, :t2, :t3, :seq, :fcode, :rcode, :iname, :specs, :uom, 'ACTIVE', 1)
            ");
            $stmt->execute([
                ':t1' => $si[0],
                ':t2' => $si[1],
                ':t3' => $si[2],
                ':seq' => $si[3],
                ':fcode' => $si[4],
                ':rcode' => $si[5],
                ':iname' => $si[6],
                ':specs' => $si[7],
                ':uom' => $si[8]
            ]);
            $masterId = $db->lastInsertId();

            // Provision some into Station 1 (Victoria - 831)
            if ($si[3] <= 2) {
                try {
                    $db->exec("INSERT INTO station_items (station_id, master_item_id, local_bay_location, imported_by) VALUES (1, $masterId, 'Bay-M01', 1)");
                } catch (\Exception $e) {}
            }
        }
    }

    // 7. Seed Sample Code Request if empty
    $reqCount = $db->query("SELECT COUNT(*) FROM code_requests")->fetchColumn();
    if ($reqCount == 0) {
        echo "Seeding sample pending code requests into $name...\n";
        $db->exec("
            INSERT INTO code_requests (request_no, tier1, tier2, tier3, suggested_tier4_seq, item_name, specifications, uom, origin_station_id, requested_by, status)
            VALUES 
            ('REQ-20261006-0001', 1, 1, 1, 6, 'Double Row Angular Contact Bearing 3205-A', 'Steel Cage, 25mm Bore, 52mm OD, 20.6mm Width', 'Nos', 1, 2, 'PENDING'),
            ('REQ-20261006-0002', 2, 3, 1, 1, 'Numerical Differential Protection Relay RED670', 'Line differential and distance backup protection', 'Nos', 2, 2, 'PENDING'),
            ('REQ-20261006-0003', 6, 1, 1, 2, 'Mobil DTE 746 High-Viscosity Turbine Lubricant', 'ISO VG 46 synthetic blend for hydro turbine thrust bearings', 'Ltr', 1, 2, 'PENDING')
        ");
    }

    echo "Migration completed for $name.\n";
}

$mysqlDb = Database::getConnection();
migrate($mysqlDb, 'MySQL', true);

$sqlitePath = dirname(__DIR__) . '/database/fifo_system.db';
if (file_exists($sqlitePath)) {
    $sqliteDb = new PDO("sqlite:" . $sqlitePath);
    $sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    migrate($sqliteDb, 'SQLite', false);
}

echo "All code management database migrations successfully applied!\n";
