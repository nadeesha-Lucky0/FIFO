<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use Exception;

class MasterItem {
    private PDO $db;

    private static bool $schemaEnsured = false;

    public function __construct() {
        $this->db = Database::getConnection();
        if (!self::$schemaEnsured) {
            $this->ensureSchema();
            self::$schemaEnsured = true;
        }
    }

    /**
     * Ensure database columns for two-step Master addition and Excel tracking exist
     */
    private function ensureSchema(): void {
        try {
            $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');

            // Quick check if columns already exist to avoid unnecessary ALTER TABLE locks
            if ($isMysql) {
                $chk = $this->db->query("SHOW COLUMNS FROM code_requests LIKE 'is_added_to_master'");
                if ($chk && $chk->rowCount() > 0) {
                    return; // Schema is already updated!
                }
            }

            // Columns to ensure on code_requests
            $reqCols = [
                'is_added_to_master' => $isMysql ? 'TINYINT DEFAULT 0' : 'INTEGER DEFAULT 0',
                'master_db_name' => $isMysql ? 'VARCHAR(255) NULL' : 'TEXT NULL',
                'master_db_uom' => $isMysql ? 'VARCHAR(30) NULL' : 'TEXT NULL',
                'name_change' => $isMysql ? "VARCHAR(10) DEFAULT 'NO'" : "TEXT DEFAULT 'NO'",
                'uom_change' => $isMysql ? "VARCHAR(10) DEFAULT 'NO'" : "TEXT DEFAULT 'NO'",
                'assigned_tier4_seq' => $isMysql ? 'INT NULL' : 'INTEGER NULL'
            ];

            foreach ($reqCols as $col => $typeDef) {
                try {
                    $this->db->exec("ALTER TABLE code_requests ADD COLUMN $col $typeDef");
                } catch (\Throwable $e) {
                    // Column already exists, safe to ignore
                }
            }

            // Columns to ensure on fifo_master_items
            $masterCols = [
                'origin_station_id' => $isMysql ? 'INT NULL' : 'INTEGER NULL',
                'code_request_id' => $isMysql ? 'BIGINT NULL' : 'INTEGER NULL',
                'orig_description' => $isMysql ? 'VARCHAR(255) NULL' : 'TEXT NULL',
                'orig_uom' => $isMysql ? 'VARCHAR(30) NULL' : 'TEXT NULL',
                'name_change' => $isMysql ? "VARCHAR(10) DEFAULT 'NO'" : "TEXT DEFAULT 'NO'",
                'uom_change' => $isMysql ? "VARCHAR(10) DEFAULT 'NO'" : "TEXT DEFAULT 'NO'"
            ];

            foreach ($masterCols as $col => $typeDef) {
                try {
                    $this->db->exec("ALTER TABLE fifo_master_items ADD COLUMN $col $typeDef");
                } catch (\Throwable $e) {
                    // Column already exists, safe to ignore
                }
            }
        } catch (\Throwable $t) {
            // Suppress schema ensure errors
        }
    }

    /**
     * Get Tier 1 Categories (1 to 19)
     */
    public function getTier1(): array {
        $stmt = $this->db->query("SELECT id, code_val, name, description FROM tier_categories WHERE tier_level = 1 ORDER BY code_val ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get Child Tiers (Tier 2 or Tier 3) by Parent ID
     */
    public function getTierChildren(int $parentId): array {
        $stmt = $this->db->prepare("SELECT id, code_val, name, description, tier_level FROM tier_categories WHERE parent_id = :pid ORDER BY code_val ASC");
        $stmt->execute([':pid' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all categories grouped for frontend initialization
     */
    public function getAllTiersGrouped(): array {
        $stmt = $this->db->query("SELECT id, tier_level, code_val, parent_id, name FROM tier_categories ORDER BY tier_level ASC, code_val ASC");
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $tier1 = [];
        $tier2 = [];
        $tier3 = [];
        
        foreach ($all as $t) {
            if ($t['tier_level'] == 1) {
                $tier1[] = $t;
            } elseif ($t['tier_level'] == 2) {
                $tier2[$t['parent_id']][] = $t;
            } elseif ($t['tier_level'] == 3) {
                $tier3[$t['parent_id']][] = $t;
            }
        }
        
        return [
            'tier1' => $tier1,
            'tier2' => $tier2,
            'tier3' => $tier3
        ];
    }

    /**
     * Get Hierarchical Excel-style Tree structure for Tier 1, Tier 2, and Tier 3 codes
     * Exactly matches the user's Excel spreadsheet layout:
     * Col 1: 01.
     * Col 2: 01. / 02.
     * Col 3: 01. / 02. / 03.
     * Col 4: Prefix: 01. / 01.01. / 01.01.01.
     * Col 5: Name / Description
     */
    public function getExcelHierarchyTree(?string $search = null, ?int $filterTier1 = null): array {
        $stmtAll = $this->db->query("SELECT id, tier_level, code_val, parent_id, name, description FROM tier_categories ORDER BY tier_level ASC, code_val ASC");
        $all = $stmtAll->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $t1List = [];
        $t2ByParent = [];
        $t3ByParent = [];

        foreach ($all as $item) {
            $lvl = (int)$item['tier_level'];
            if ($lvl === 1) {
                $t1List[] = $item;
            } elseif ($lvl === 2) {
                $t2ByParent[$item['parent_id']][] = $item;
            } elseif ($lvl === 3) {
                $t3ByParent[$item['parent_id']][] = $item;
            }
        }

        $rows = [];
        $searchLower = !empty($search) ? strtolower(trim($search)) : null;

        foreach ($t1List as $t1) {
            if ($filterTier1 && (int)$t1['code_val'] !== $filterTier1) {
                continue;
            }

            $t1CodeStr = sprintf('%02d.', (int)$t1['code_val']);
            $t1Prefix = $t1CodeStr;

            $t1Row = [
                'id' => (int)$t1['id'],
                'level' => 1,
                'tier1_val' => (int)$t1['code_val'],
                'tier2_val' => null,
                'tier3_val' => null,
                'col1' => $t1CodeStr,
                'col2' => '',
                'col3' => '',
                'prefix' => $t1Prefix,
                'name' => $t1['name'],
                'description' => $t1['description'] ?? '',
                'parent_id' => null,
                'parent_name' => '',
                'has_children' => !empty($t2ByParent[$t1['id']])
            ];

            $t2List = $t2ByParent[$t1['id']] ?? [];
            $matchingT2Rows = [];

            foreach ($t2List as $t2) {
                $t2CodeStr = sprintf('%02d.', (int)$t2['code_val']);
                $t2Prefix = sprintf('%02d.%02d.', (int)$t1['code_val'], (int)$t2['code_val']);

                $t2Row = [
                    'id' => (int)$t2['id'],
                    'level' => 2,
                    'tier1_val' => (int)$t1['code_val'],
                    'tier2_val' => (int)$t2['code_val'],
                    'tier3_val' => null,
                    'col1' => $t1CodeStr,
                    'col2' => $t2CodeStr,
                    'col3' => '',
                    'prefix' => $t2Prefix,
                    'name' => $t2['name'],
                    'description' => $t2['description'] ?? '',
                    'parent_id' => (int)$t1['id'],
                    'parent_name' => $t1['name'],
                    'has_children' => !empty($t3ByParent[$t2['id']])
                ];

                $t3List = $t3ByParent[$t2['id']] ?? [];
                $matchingT3Rows = [];

                foreach ($t3List as $t3) {
                    $t3CodeStr = sprintf('%02d.', (int)$t3['code_val']);
                    $t3Prefix = sprintf('%02d.%02d.%02d.', (int)$t1['code_val'], (int)$t2['code_val'], (int)$t3['code_val']);

                    $t3Row = [
                        'id' => (int)$t3['id'],
                        'level' => 3,
                        'tier1_val' => (int)$t1['code_val'],
                        'tier2_val' => (int)$t2['code_val'],
                        'tier3_val' => (int)$t3['code_val'],
                        'col1' => $t1CodeStr,
                        'col2' => $t2CodeStr,
                        'col3' => $t3CodeStr,
                        'prefix' => $t3Prefix,
                        'name' => $t3['name'],
                        'description' => $t3['description'] ?? '',
                        'parent_id' => (int)$t2['id'],
                        'parent_name' => $t2['name'],
                        'has_children' => false
                    ];

                    if (!$searchLower || str_contains(strtolower($t3['name']), $searchLower) || str_contains($t3Prefix, $searchLower)) {
                        $matchingT3Rows[] = $t3Row;
                    }
                }

                $t2Matches = (!$searchLower || str_contains(strtolower($t2['name']), $searchLower) || str_contains($t2Prefix, $searchLower) || !empty($matchingT3Rows));

                if ($t2Matches) {
                    $matchingT2Rows[] = $t2Row;
                    foreach ($matchingT3Rows as $r3) {
                        $matchingT2Rows[] = $r3;
                    }
                }
            }

            $t1Matches = (!$searchLower || str_contains(strtolower($t1['name']), $searchLower) || str_contains($t1Prefix, $searchLower) || !empty($matchingT2Rows));

            if ($t1Matches) {
                $rows[] = $t1Row;
                foreach ($matchingT2Rows as $r2) {
                    $rows[] = $r2;
                }
            }
        }

        return $rows;
    }

    /**
     * Get Single Tier Category by ID
     */
    public function getTierNodeById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tier_categories WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Save or Update Tier Node (Tier 1, Tier 2, or Tier 3)
     */
    public function saveTierNode(array $data): array {
        $id = !empty($data['id']) ? (int)$data['id'] : null;
        $level = (int)($data['level'] ?? 1);
        $codeVal = isset($data['code_val']) && $data['code_val'] !== '' ? (int)$data['code_val'] : null;
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        $name = mb_strtoupper(trim($data['name'] ?? ''), 'UTF-8');
        $desc = !empty($data['description']) ? trim($data['description']) : '';

        if (empty($name)) {
            throw new Exception("Code Description / Name is required.");
        }
        if ($level < 1 || $level > 3) {
            throw new Exception("Invalid tier level (must be 1, 2, or 3).");
        }
        if ($level > 1 && empty($parentId)) {
            throw new Exception("Parent category is required for Level {$level} codes.");
        }

        if ($id) {
            // Update existing tier node
            $stmtChk = $this->db->prepare("SELECT * FROM tier_categories WHERE id = :id");
            $stmtChk->execute([':id' => $id]);
            $existing = $stmtChk->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new Exception("Tier code #{$id} not found.");
            }

            $finalCodeVal = ($codeVal !== null) ? $codeVal : (int)$existing['code_val'];
            $finalParentId = ($level > 1) ? $parentId : null;

            // Check code number uniqueness
            $dupChk = $this->db->prepare("SELECT id FROM tier_categories WHERE tier_level = :lvl AND code_val = :cval AND parent_id <=> :pid AND id != :id");
            $dupChk->execute([':lvl' => $level, ':cval' => $finalCodeVal, ':pid' => $finalParentId, ':id' => $id]);
            if ($dupChk->fetch()) {
                throw new Exception("Code number {$finalCodeVal} is already taken under this parent category.");
            }

            // Check duplicate text name for 1st and 2nd tier levels (1st and 2nd topic texts cannot be added again)
            if ($level === 1) {
                $dupNameChk = $this->db->prepare("SELECT id, code_val FROM tier_categories WHERE tier_level = 1 AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND id != :id LIMIT 1");
                $dupNameChk->execute([':name' => $name, ':id' => $id]);
                if ($existingName = $dupNameChk->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("1st Code Main Topic '{$name}' already exists as Code " . sprintf('%02d.', $existingName['code_val']) . ". Duplicate 1st topic names cannot be added.");
                }
            } elseif ($level === 2) {
                $dupNameChk = $this->db->prepare("SELECT id, code_val FROM tier_categories WHERE tier_level = 2 AND parent_id = :pid AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND id != :id LIMIT 1");
                $dupNameChk->execute([':pid' => $finalParentId, ':name' => $name, ':id' => $id]);
                if ($existingName = $dupNameChk->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("2nd Code Sub Topic '{$name}' already exists under this topic as Code " . sprintf('%02d.', $existingName['code_val']) . ". Duplicate 2nd sub-topic names cannot be added.");
                }
            }

            $stmtUp = $this->db->prepare("
                UPDATE tier_categories 
                SET code_val = :cval, name = :name, description = :desc, parent_id = :pid
                WHERE id = :id
            ");
            $stmtUp->execute([
                ':cval' => $finalCodeVal,
                ':name' => $name,
                ':desc' => $desc,
                ':pid' => $finalParentId,
                ':id' => $id
            ]);

            return [
                'success' => true,
                'action' => 'UPDATED',
                'id' => $id,
                'message' => "Code '{$name}' updated successfully."
            ];
        } else {
            // New tier node - calculate next code_val if not provided
            $finalParentId = ($level > 1) ? $parentId : null;

            // Check duplicate text name for 1st and 2nd tier levels (1st and 2nd topic texts cannot be added again)
            if ($level === 1) {
                $dupNameChk = $this->db->prepare("SELECT id, code_val FROM tier_categories WHERE tier_level = 1 AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
                $dupNameChk->execute([':name' => $name]);
                if ($existingName = $dupNameChk->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("1st Code Main Topic '{$name}' already exists as Code " . sprintf('%02d.', $existingName['code_val']) . ". Duplicate 1st topic names cannot be added again.");
                }
            } elseif ($level === 2) {
                $dupNameChk = $this->db->prepare("SELECT id, code_val FROM tier_categories WHERE tier_level = 2 AND parent_id = :pid AND LOWER(TRIM(name)) = LOWER(TRIM(:name)) LIMIT 1");
                $dupNameChk->execute([':pid' => $finalParentId, ':name' => $name]);
                if ($existingName = $dupNameChk->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception("2nd Code Sub Topic '{$name}' already exists under this topic as Code " . sprintf('%02d.', $existingName['code_val']) . ". Duplicate 2nd sub-topic names cannot be added again.");
                }
            }

            if ($codeVal === null || $codeVal <= 0) {
                $stmtMax = $this->db->prepare("SELECT COALESCE(MAX(code_val), 0) FROM tier_categories WHERE tier_level = :lvl AND parent_id <=> :pid");
                $stmtMax->execute([':lvl' => $level, ':pid' => $finalParentId]);
                $maxCode = (int)$stmtMax->fetchColumn();
                $codeVal = $maxCode + 1;
            } else {
                // Check duplicate
                $dupChk = $this->db->prepare("SELECT id FROM tier_categories WHERE tier_level = :lvl AND code_val = :cval AND parent_id <=> :pid");
                $dupChk->execute([':lvl' => $level, ':cval' => $codeVal, ':pid' => $finalParentId]);
                if ($dupChk->fetch()) {
                    throw new Exception("Code number {$codeVal} is already in use under this parent category. Please select another number.");
                }
            }

            $stmtIn = $this->db->prepare("
                INSERT INTO tier_categories (tier_level, code_val, parent_id, name, description)
                VALUES (:lvl, :cval, :pid, :name, :desc)
            ");
            $stmtIn->execute([
                ':lvl' => $level,
                ':cval' => $codeVal,
                ':pid' => $finalParentId,
                ':name' => $name,
                ':desc' => $desc
            ]);
            $newId = (int)$this->db->lastInsertId();

            return [
                'success' => true,
                'action' => 'CREATED',
                'id' => $newId,
                'code_val' => $codeVal,
                'message' => "New Level {$level} Code (Prefix " . sprintf('%02d.', $codeVal) . ") created successfully."
            ];
        }
    }

    /**
     * Delete Tier Category Node
     */
    public function deleteTierNode(int $id): bool {
        // Check if has children
        $chkChild = $this->db->prepare("SELECT COUNT(*) FROM tier_categories WHERE parent_id = :id");
        $chkChild->execute([':id' => $id]);
        if ((int)$chkChild->fetchColumn() > 0) {
            throw new Exception("Cannot delete this category because it contains sub-categories under it. Please remove or reassign sub-categories first.");
        }

        $stmt = $this->db->prepare("DELETE FROM tier_categories WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Compute next uniquely available 3-digit sequence slot (000 to 999) for a (Tier1, Tier2, Tier3) branch.
     * Guarantees that different items can never receive the same 3-digit sequence.
     */
    public function getNextAvailableSequence(int $tier1, int $tier2, int $tier3, ?int $excludeRequestId = null): int {
        $usedSeqs = [];

        // 1. All sequences in Master Database
        $stmt = $this->db->prepare("
            SELECT DISTINCT tier4_seq 
            FROM fifo_master_items 
            WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND tier4_seq IS NOT NULL
        ");
        $stmt->execute([':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $usedSeqs[(int)$row['tier4_seq']] = true;
        }

        // 2. All sequences in active / staged code requests (only approved sequences assigned by Code Admin)
        $reqSql = "
            SELECT DISTINCT assigned_tier4_seq as req_seq
            FROM code_requests 
            WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 
              AND status = 'APPROVED'
              AND assigned_tier4_seq IS NOT NULL
        ";
        $params = [':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3];
        if ($excludeRequestId) {
            $reqSql .= " AND id != :ex_id";
            $params[':ex_id'] = $excludeRequestId;
        }
        $stmtReq = $this->db->prepare($reqSql);
        $stmtReq->execute($params);
        while ($row = $stmtReq->fetch(PDO::FETCH_ASSOC)) {
            if ($row['req_seq'] !== null && $row['req_seq'] !== '') {
                $usedSeqs[(int)$row['req_seq']] = true;
            }
        }

        // Return first unassigned slot (000 to 999)
        for ($seq = 0; $seq <= 999; $seq++) {
            if (!isset($usedSeqs[$seq])) {
                return $seq;
            }
        }

        throw new Exception("All 1000 sequence slots (000-999) are fully taken for branch {$tier1}-" . sprintf('%02d-%02d', $tier2, $tier3) . ".");
    }

    /**
     * Check if a specific 3-digit sequence slot is available in branch (Tier1, Tier2, Tier3)
     */
    public function isSequenceSlotAvailable(int $tier1, int $tier2, int $tier3, int $seq, ?int $excludeRequestId = null): bool {
        // Check Master DB
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM fifo_master_items 
            WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND tier4_seq = :seq
        ");
        $stmt->execute([':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3, ':seq' => $seq]);
        if ((int)$stmt->fetchColumn() > 0) {
            return false;
        }

        // Check Requests (only approved sequences assigned by Code Admin)
        $reqSql = "
            SELECT COUNT(*) FROM code_requests 
            WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 
              AND assigned_tier4_seq = :seq
              AND status = 'APPROVED'
              AND assigned_tier4_seq IS NOT NULL
        ";
        $params = [':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3, ':seq' => $seq];
        if ($excludeRequestId) {
            $reqSql .= " AND id != :ex_id";
            $params[':ex_id'] = $excludeRequestId;
        }
        $stmtReq = $this->db->prepare($reqSql);
        $stmtReq->execute($params);
        if ((int)$stmtReq->fetchColumn() > 0) {
            return false;
        }

        return true;
    }

    /**
     * Real-time availability check & duplicate search with progressive filtering
     */
    public function checkAvailability(?int $tier1 = null, ?int $tier2 = null, ?int $tier3 = null, ?int $stationId = null, ?string $searchQuery = null, ?int $tier4 = null): array {
        $conditions = ["1=1"];
        $params = [':station_id' => $stationId ?: 0];

        if ($tier1 !== null && $tier1 > 0) {
            $conditions[] = "m.tier1 = :t1";
            $params[':t1'] = $tier1;
        }
        if ($tier2 !== null && $tier2 > 0) {
            $conditions[] = "m.tier2 = :t2";
            $params[':t2'] = $tier2;
        }
        if ($tier3 !== null && $tier3 > 0) {
            $conditions[] = "m.tier3 = :t3";
            $params[':t3'] = $tier3;
        }
        if ($tier4 !== null && $tier4 >= 0) {
            $conditions[] = "m.tier4_seq = :t4";
            $params[':t4'] = $tier4;
        }

        if (!empty($searchQuery)) {
            $conditions[] = "(m.item_name LIKE :q OR m.specifications LIKE :q2 OR m.formatted_code LIKE :q3 OR m.raw_code LIKE :q4)";
            $params[':q'] = "%$searchQuery%";
            $params[':q2'] = "%$searchQuery%";
            $params[':q3'] = "%$searchQuery%";
            $params[':q4'] = "%$searchQuery%";
        }

        $whereClause = implode(" AND ", $conditions);
        $sql = "
            SELECT m.*, 
                   (SELECT COUNT(*) FROM station_items s WHERE s.master_item_id = m.id AND s.station_id = :station_id) as is_in_station
            FROM fifo_master_items m
            WHERE $whereClause
            ORDER BY m.tier1 ASC, m.tier2 ASC, m.tier3 ASC, m.tier4_seq ASC
            LIMIT 200
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $masterItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $existingCodes = [];
        foreach ($masterItems as &$mi) {
            $mi['is_added_to_master'] = 1;
            $mi['is_staged'] = 0;
            $existingCodes[$mi['formatted_code']] = true;
        }
        unset($mi);

        // Fetch staged items from code_requests (already approved, pending master add)
        $stagedConds = ["r.status = 'APPROVED'", "(r.is_added_to_master IS NULL OR r.is_added_to_master = 0)"];
        $stagedParams = [':st_station_id' => $stationId ?: 0];
        if ($tier1 !== null && $tier1 > 0) {
            $stagedConds[] = "r.tier1 = :st1";
            $stagedParams[':st1'] = $tier1;
        }
        if ($tier2 !== null && $tier2 > 0) {
            $stagedConds[] = "r.tier2 = :st2";
            $stagedParams[':st2'] = $tier2;
        }
        if ($tier3 !== null && $tier3 > 0) {
            $stagedConds[] = "r.tier3 = :st3";
            $stagedParams[':st3'] = $tier3;
        }
        if ($tier4 !== null && $tier4 >= 0) {
            $stagedConds[] = "COALESCE(r.assigned_tier4_seq, r.suggested_tier4_seq) = :st4";
            $stagedParams[':st4'] = $tier4;
        }
        if (!empty($searchQuery)) {
            $stagedConds[] = "(r.item_name LIKE :sq OR r.specifications LIKE :sq2 OR r.master_db_name LIKE :sq3)";
            $stagedParams[':sq'] = "%$searchQuery%";
            $stagedParams[':sq2'] = "%$searchQuery%";
            $stagedParams[':sq3'] = "%$searchQuery%";
        }
        $stagedSql = "
            SELECT r.id as request_id,
                   0 as id,
                   r.tier1,
                   r.tier2,
                   r.tier3,
                   COALESCE(r.assigned_tier4_seq, r.suggested_tier4_seq, 0) as tier4_seq,
                   r.item_name,
                   r.specifications,
                   r.uom,
                   r.status,
                   0 as is_added_to_master,
                   1 as is_staged,
                   (SELECT COUNT(*) FROM station_items s WHERE s.master_item_id = r.generated_master_item_id AND s.station_id = :st_station_id) as is_in_station,
                   r.origin_station_id,
                   l.name as station_name,
                   l.code as station_code
            FROM code_requests r
            LEFT JOIN locations l ON r.origin_station_id = l.id
            WHERE " . implode(' AND ', $stagedConds) . "
            ORDER BY r.tier1 ASC, r.tier2 ASC, r.tier3 ASC, tier4_seq ASC
            LIMIT 50
        ";
        $stagedStmt = $this->db->prepare($stagedSql);
        $stagedStmt->execute($stagedParams);
        $stagedRows = $stagedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stagedItems = [];
        foreach ($stagedRows as $si) {
            $seq = (int)$si['tier4_seq'];
            $fCode = sprintf("%d-%02d-%02d-%03d", $si['tier1'], $si['tier2'], $si['tier3'], $seq);
            if (!empty($existingCodes[$fCode])) {
                continue; // Master record takes precedence
            }
            $existingCodes[$fCode] = true;
            $si['formatted_code'] = $fCode;
            $si['raw_code'] = sprintf("%02d%02d%02d%03d", $si['tier1'], $si['tier2'], $si['tier3'], $seq);
            $stagedItems[] = $si;
        }

        $items = array_merge($masterItems, $stagedItems);
        usort($items, function ($a, $b) {
            $t1A = (int)($a['tier1'] ?? 0);
            $t1B = (int)($b['tier1'] ?? 0);
            if ($t1A !== $t1B) return $t1A <=> $t1B;

            $t2A = (int)($a['tier2'] ?? 0);
            $t2B = (int)($b['tier2'] ?? 0);
            if ($t2A !== $t2B) return $t2A <=> $t2B;

            $t3A = (int)($a['tier3'] ?? 0);
            $t3B = (int)($b['tier3'] ?? 0);
            if ($t3A !== $t3B) return $t3A <=> $t3B;

            $t4A = (int)($a['tier4_seq'] ?? 0);
            $t4B = (int)($b['tier4_seq'] ?? 0);
            if ($t4A !== $t4B) return $t4A <=> $t4B;

            return strcmp((string)($a['formatted_code'] ?? ''), (string)($b['formatted_code'] ?? ''));
        });

        // Fetch pending requests under this branch/filter
        $pendingRequests = [];
        if ($tier1 && $tier2 && $tier3) {
            $reqSql = "
                SELECT r.*, u.full_name as requester_name, u.username as requester_username, l.name as station_name
                FROM code_requests r
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN locations l ON r.origin_station_id = l.id
                WHERE r.tier1 = :t1 AND r.tier2 = :t2 AND r.tier3 = :t3 AND r.status = 'PENDING'
                ORDER BY r.suggested_tier4_seq ASC
            ";
            $reqStmt = $this->db->prepare($reqSql);
            $reqStmt->execute([':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3]);
            $pendingRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } elseif ($tier1) {
            $reqConds = ["r.status = 'PENDING'", "r.tier1 = :t1"];
            $reqParams = [':t1' => $tier1];
            if ($tier2) {
                $reqConds[] = "r.tier2 = :t2";
                $reqParams[':t2'] = $tier2;
            }
            $reqSql = "
                SELECT r.*, u.full_name as requester_name, u.username as requester_username, l.name as station_name
                FROM code_requests r
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN locations l ON r.origin_station_id = l.id
                WHERE " . implode(' AND ', $reqConds) . "
                ORDER BY r.tier1 ASC, r.tier2 ASC, r.tier3 ASC, r.suggested_tier4_seq ASC
            ";
            $reqStmt = $this->db->prepare($reqSql);
            $reqStmt->execute($reqParams);
            $pendingRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $nextSeq = ($tier1 && $tier2 && $tier3) ? $this->getNextAvailableSequence($tier1, $tier2, $tier3) : 0;
        $t1Str = $tier1 ? (string)$tier1 : '1';
        $t2Str = $tier2 ? sprintf('%02d', $tier2) : '01';
        $t3Str = $tier3 ? sprintf('%02d', $tier3) : '01';
        $t4Str = sprintf('%03d', $nextSeq);
        $previewCode = "{$t1Str}-{$t2Str}-{$t3Str}-{$t4Str}";
        $rawCode = sprintf('%02d%02d%02d%03d', $tier1 ?: 1, $tier2 ?: 1, $tier3 ?: 1, $nextSeq);

        return [
            'tier_combination' => "{$t1Str}-{$t2Str}-{$t3Str}",
            'next_seq' => $nextSeq,
            'next_sequence' => $nextSeq,
            'suggested_seq_formatted' => $t4Str,
            'preview_formatted_code' => $previewCode,
            'preview_raw_code' => $rawCode,
            'items' => $items,
            'pending_requests' => $pendingRequests,
            'total_items_in_branch' => count($items)
        ];
    }

    /**
     * Create a Code Creation Request from Power Station
     */
    public function createCodeRequest(array $data): array {
        $tier1 = (int)($data['tier1'] ?? 1);
        $tier2 = (int)($data['tier2'] ?? 1);
        $tier3 = (int)($data['tier3'] ?? 1);
        $suggestedSeq = isset($data['suggested_tier4_seq']) && $data['suggested_tier4_seq'] !== '' ? (int)$data['suggested_tier4_seq'] : null;
        $itemName = mb_strtoupper(trim($data['item_name'] ?? ''), 'UTF-8');
        $specs = !empty($data['specifications']) ? mb_strtoupper(trim($data['specifications']), 'UTF-8') : null;
        $uom = mb_strtoupper(trim($data['uom'] ?? 'NOS'), 'UTF-8');
        $unitPrice = isset($data['unit_price']) && is_numeric($data['unit_price']) ? (float)$data['unit_price'] : null;
        $imgUrl1 = $data['image_url_1'] ?? null;
        $imgUrl2 = $data['image_url_2'] ?? null;
        $stationId = (int)($data['origin_station_id'] ?? 1);
        $userId = (int)($data['requested_by'] ?? 1);
        $refRequestId = !empty($data['referenced_request_id']) ? (int)$data['referenced_request_id'] : null;

        if (empty($itemName)) {
            throw new Exception("Item Description / Name is required.");
        }

        // Duplicate check in Master DB
        $dupStmt = $this->db->prepare("SELECT id, formatted_code FROM fifo_master_items WHERE LOWER(TRIM(item_name)) = LOWER(TRIM(:name)) LIMIT 1");
        $dupStmt->execute([':name' => $itemName]);
        if ($existing = $dupStmt->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception("An item with the exact name '{$itemName}' already exists in the Master Database (Code: {$existing['formatted_code']}).");
        }

        // Check if requesting an already approved staged code
        $stagedReq = null;
        if ($refRequestId) {
            $stChk = $this->db->prepare("SELECT * FROM code_requests WHERE id = :rid AND status = 'APPROVED' AND (is_added_to_master IS NULL OR is_added_to_master = 0) LIMIT 1");
            $stChk->execute([':rid' => $refRequestId]);
            $stagedReq = $stChk->fetch(PDO::FETCH_ASSOC);
        }
        if (!$stagedReq && $suggestedSeq !== null) {
            $stChk = $this->db->prepare("SELECT * FROM code_requests WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND COALESCE(assigned_tier4_seq, suggested_tier4_seq) = :seq AND status = 'APPROVED' AND (is_added_to_master IS NULL OR is_added_to_master = 0) LIMIT 1");
            $stChk->execute([':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3, ':seq' => $suggestedSeq]);
            $stagedReq = $stChk->fetch(PDO::FETCH_ASSOC);
        }

        $finalSeq = $suggestedSeq ?? $this->getNextAvailableSequence($tier1, $tier2, $tier3);

        // Check if this station already requested this code and it is still pending/staged
        if ($stagedReq) {
            $seqToCheck = (int)($stagedReq['assigned_tier4_seq'] ?? $finalSeq);
            $chkStation = $this->db->prepare("
                SELECT id, request_no FROM code_requests 
                WHERE origin_station_id = :sid AND tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 
                  AND COALESCE(assigned_tier4_seq, suggested_tier4_seq) = :seq 
                  AND (is_added_to_master IS NULL OR is_added_to_master = 0)
                LIMIT 1
            ");
            $chkStation->execute([':sid' => $stationId, ':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3, ':seq' => $seqToCheck]);
            if ($myExisting = $chkStation->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Your power station has already requested this code (Ref: {$myExisting['request_no']}). It is currently staged, awaiting Administrator to Add to Master.");
            }
        }

        // Generate Unique Request Ref No
        $requestNo = 'REQ-' . date('Ymd') . '-' . sprintf('%04d', rand(1, 9999));

        if ($stagedReq) {
            // Already approved in staging! Save as APPROVED so Admin sees only "+ Add to Master"
            $status = 'APPROVED';
            $assignedSeq = (int)($stagedReq['assigned_tier4_seq'] ?? $finalSeq);
            $masterDbName = !empty($stagedReq['master_db_name']) ? $stagedReq['master_db_name'] : $itemName;
            $masterDbUom = !empty($stagedReq['master_db_uom']) ? $stagedReq['master_db_uom'] : $uom;
            $reviewedBy = $stagedReq['reviewed_by'] ?? $userId;
            $reviewRemarks = "Direct request for already approved staged code (Ref #" . $stagedReq['request_no'] . ")";

            $stmt = $this->db->prepare("
                INSERT INTO code_requests (
                    request_no, tier1, tier2, tier3, suggested_tier4_seq, assigned_tier4_seq,
                    item_name, specifications, uom, master_db_name, master_db_uom,
                    origin_station_id, requested_by, status, is_added_to_master,
                    name_change, uom_change, reviewed_by, review_remarks, reviewed_at,
                    unit_price, image_url_1, image_url_2
                ) VALUES (
                    :req_no, :t1, :t2, :t3, :sseq, :aseq,
                    :iname, :specs, :uom, :mname, :muom,
                    :sid, :uid, 'APPROVED', 0,
                    'NO', 'NO', :rb, :rem, CURRENT_TIMESTAMP,
                    :uprice, :img1, :img2
                )
            ");
            $stmt->execute([
                ':req_no' => $requestNo,
                ':t1' => $tier1,
                ':t2' => $tier2,
                ':t3' => $tier3,
                ':sseq' => $assignedSeq,
                ':aseq' => $assignedSeq,
                ':iname' => $itemName,
                ':specs' => $specs,
                ':uom' => $uom,
                ':mname' => $masterDbName,
                ':muom' => $masterDbUom,
                ':sid' => $stationId,
                ':uid' => $userId,
                ':rb' => $reviewedBy,
                ':rem' => $reviewRemarks,
                ':uprice' => $unitPrice,
                ':img1' => $imgUrl1,
                ':img2' => $imgUrl2
            ]);
        } else {
            $status = 'PENDING';
            $stmt = $this->db->prepare("
                INSERT INTO code_requests (
                    request_no, tier1, tier2, tier3, suggested_tier4_seq, item_name, specifications, uom,
                    origin_station_id, requested_by, status, is_added_to_master, name_change, uom_change,
                    unit_price, image_url_1, image_url_2
                ) VALUES (
                    :req_no, :t1, :t2, :t3, :sseq, :iname, :specs, :uom,
                    :sid, :uid, 'PENDING', 0, 'NO', 'NO',
                    :uprice, :img1, :img2
                )
            ");
            $stmt->execute([
                ':req_no' => $requestNo,
                ':t1' => $tier1,
                ':t2' => $tier2,
                ':t3' => $tier3,
                ':sseq' => $finalSeq,
                ':iname' => $itemName,
                ':specs' => $specs,
                ':uom' => $uom,
                ':sid' => $stationId,
                ':uid' => $userId,
                ':uprice' => $unitPrice,
                ':img1' => $imgUrl1,
                ':img2' => $imgUrl2
            ]);
        }

        return [
            'id' => (int)$this->db->lastInsertId(),
            'request_no' => $requestNo,
            'suggested_code' => sprintf("%d-%02d-%02d-%03d", $tier1, $tier2, $tier3, $stagedReq ? $assignedSeq : $finalSeq),
            'status' => $status
        ];
    }

    /**
     * Get Single Code Creation Request by ID
     */
    public function getCodeRequestById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   u.username as requester_username, u.full_name as requester_name,
                   l.name as station_name, l.code as station_code
            FROM code_requests r
            LEFT JOIN users u ON r.requested_by = u.id
            LEFT JOIN locations l ON r.origin_station_id = l.id
            WHERE r.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Update an existing Code Creation Request
     * STRICT RULE: Can only be edited while status is PENDING or REJECTED.
     * Once Approved or in Master, editing is strictly blocked.
     * Resubmitting a rejected request resets status to PENDING.
     */
    public function updateCodeRequest(int $requestId, array $data, int $userId): array {
        $req = $this->getCodeRequestById($requestId);
        if (!$req) {
            throw new Exception("Code creation request #{$requestId} not found.");
        }

        if (!empty($req['is_added_to_master'])) {
            throw new Exception("This code request has already been added to Master and cannot be edited.");
        }

        if (!in_array($req['status'], ['PENDING', 'REJECTED'], true)) {
            throw new Exception("This code request has already been approved by Administrator and cannot be edited.");
        }

        $itemName = isset($data['item_name']) ? mb_strtoupper(trim((string)$data['item_name']), 'UTF-8') : $req['item_name'];
        if (empty($itemName)) {
            throw new Exception("Item Description / Name cannot be empty.");
        }

        $uom = isset($data['uom']) ? mb_strtoupper(trim((string)$data['uom']), 'UTF-8') : $req['uom'];
        $unitPrice = array_key_exists('unit_price', $data) ? $data['unit_price'] : $req['unit_price'];
        $img1 = array_key_exists('image_url_1', $data) ? $data['image_url_1'] : $req['image_url_1'];
        $img2 = array_key_exists('image_url_2', $data) ? $data['image_url_2'] : $req['image_url_2'];

        $stmt = $this->db->prepare("
            UPDATE code_requests
            SET item_name = :iname,
                uom = :uom,
                unit_price = :price,
                image_url_1 = :img1,
                image_url_2 = :img2,
                status = 'PENDING',
                review_remarks = NULL,
                reviewed_by = NULL,
                reviewed_at = NULL
            WHERE id = :id AND (status = 'PENDING' OR status = 'REJECTED')
        ");
        $stmt->execute([
            ':iname' => $itemName,
            ':uom' => $uom,
            ':price' => $unitPrice,
            ':img1' => $img1,
            ':img2' => $img2,
            ':id' => $requestId
        ]);

        return $this->getCodeRequestById($requestId);
    }

    /**
     * Delete a Code Request
     * - Allows station users to delete their rejected or pending requests.
     * - Safety guarantee: If the item was already added to Master (is_added_to_master = 1),
     *   deleting this station request does NOT touch fifo_master_items.
     *   The master item remains 100% intact in Master DB and continues showing in Item Search!
     */
    public function deleteCodeRequest(int $requestId, int $userId): bool {
        $req = $this->getCodeRequestById($requestId);
        if (!$req) {
            throw new Exception("Code request #{$requestId} not found.");
        }

        $isAdmin = false;
        if (class_exists('\App\Core\Auth')) {
            $isAdmin = \App\Core\Auth::isAdmin();
        }
        if (!$isAdmin && (int)$req['requested_by'] !== $userId) {
            throw new Exception("Unauthorized. You can only delete your own station requests.");
        }

        $stmt = $this->db->prepare("DELETE FROM code_requests WHERE id = :id");
        return $stmt->execute([':id' => $requestId]);
    }

    /**
     * Get all Code Requests (with optional filters)
     */
    public function getCodeRequests(?string $status = null, ?int $stationId = null, bool $activeOnly = false, bool $powerStationOnly = true): array {
        $sql = "
            SELECT r.*, 
                   u.username as requester_username, u.full_name as requester_name,
                   l.name as station_name, l.code as station_code,
                   m.formatted_code as master_formatted_code,
                   m.raw_code as master_raw_code,
                   (SELECT COUNT(*) FROM station_items s WHERE s.master_item_id = r.generated_master_item_id AND s.station_id = r.origin_station_id) as is_added_to_station,
                   (SELECT s.local_bay_location FROM station_items s WHERE s.master_item_id = r.generated_master_item_id AND s.station_id = r.origin_station_id LIMIT 1) as station_bay_location
            FROM code_requests r
            LEFT JOIN users u ON r.requested_by = u.id
            LEFT JOIN locations l ON r.origin_station_id = l.id
            LEFT JOIN fifo_master_items m ON r.generated_master_item_id = m.id
            WHERE 1=1
        ";
        $params = [];

        if ($powerStationOnly) {
            $sql .= " AND r.origin_station_id > 0 AND l.id IS NOT NULL";
        }

        if (!empty($status)) {
            $upperStatus = strtoupper(trim($status));
            if ($upperStatus === 'PENDING') {
                // Pending Review filter includes both pending review requests and staged requests awaiting master add
                $sql .= " AND (r.status = 'PENDING' OR (r.status = 'APPROVED' AND (r.is_added_to_master IS NULL OR r.is_added_to_master = 0)))";
            } elseif ($upperStatus === 'STAGED') {
                $sql .= " AND r.status = 'APPROVED' AND (r.is_added_to_master IS NULL OR r.is_added_to_master = 0)";
            } else {
                $sql .= " AND r.status = :st";
                $params[':st'] = $upperStatus;
            }
        }
        if (!empty($stationId)) {
            $sql .= " AND r.origin_station_id = :sid";
            $params[':sid'] = $stationId;
        }
        if ($activeOnly) {
            $sql .= " AND (r.is_added_to_master IS NULL OR r.is_added_to_master = 0)";
        }

        $sql .= " ORDER BY r.id DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$r) {
            $t1 = (int)($r['tier1'] ?? 1);
            $t2 = (int)($r['tier2'] ?? 1);
            $t3 = (int)($r['tier3'] ?? 1);
            $t4 = (int)($r['assigned_tier4_seq'] ?? $r['suggested_tier4_seq'] ?? 0);
            
            $r['approved_formatted_code'] = !empty($r['master_formatted_code'])
                ? $r['master_formatted_code']
                : sprintf("%d-%02d-%02d-%03d", $t1, $t2, $t3, $t4);
            $r['approved_raw_code'] = !empty($r['master_raw_code'])
                ? $r['master_raw_code']
                : sprintf("%02d%02d%02d%03d", $t1, $t2, $t3, $t4);
        }
        unset($r);

        return $rows;
    }

    /**
     * Admin Approval of Code Request (Allocates code, but stages before adding to master)
     */
    public function approveRequest(int $requestId, int $adminId, string $remarks = '', ?string $editedItemName = null, ?string $editedSpecs = null, ?string $editedUom = null, bool $addToMasterNow = false, ?int $assignedSeq = null): array {
        $this->db->beginTransaction();
        try {
            // Fetch Request
            $stmt = $this->db->prepare("SELECT * FROM code_requests WHERE id = :id");
            $stmt->execute([':id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                throw new Exception("Code request #{$requestId} not found.");
            }
            if ($req['status'] === 'APPROVED' && !empty($req['is_added_to_master'])) {
                throw new Exception("Request is already approved and added to Master Database.");
            }

            $t1 = (int)$req['tier1'];
            $t2 = (int)$req['tier2'];
            $t3 = (int)$req['tier3'];

            $finalItemName = ($editedItemName !== null && trim($editedItemName) !== '') ? mb_strtoupper(trim($editedItemName), 'UTF-8') : mb_strtoupper(trim($req['item_name']), 'UTF-8');
            $finalSpecs = ($editedSpecs !== null) ? mb_strtoupper(trim($editedSpecs), 'UTF-8') : mb_strtoupper(trim($req['specifications'] ?? ''), 'UTF-8');
            $finalUom = ($editedUom !== null && trim($editedUom) !== '') ? mb_strtoupper(trim($editedUom), 'UTF-8') : mb_strtoupper(trim($req['uom'] ?? 'NOS'), 'UTF-8');

            if (empty($finalItemName)) {
                throw new Exception("Item Description / Name cannot be empty.");
            }

            // Determine sequence: Admin assigned seq takes precedence (validated for uniqueness), otherwise fallback to next available sequence
            if ($assignedSeq !== null && $assignedSeq >= 0) {
                $finalSeq = (int)$assignedSeq;
                if (!$this->isSequenceSlotAvailable($t1, $t2, $t3, $finalSeq, $requestId)) {
                    $nextFree = $this->getNextAvailableSequence($t1, $t2, $t3, $requestId);
                    throw new Exception("Sequence slot " . sprintf('%03d', $finalSeq) . " ({$t1}-" . sprintf('%02d-%02d-%03d', $t2, $t3, $finalSeq) . ") is already taken by another item in this branch. Next available slot is " . sprintf('%03d', $nextFree) . ".");
                }
            } elseif ($req['assigned_tier4_seq'] !== null && $req['assigned_tier4_seq'] >= 0 && $this->isSequenceSlotAvailable($t1, $t2, $t3, (int)$req['assigned_tier4_seq'], $requestId)) {
                $finalSeq = (int)$req['assigned_tier4_seq'];
            } else {
                $finalSeq = $this->getNextAvailableSequence($t1, $t2, $t3, $requestId);
            }

            if ($finalSeq > 999) {
                throw new Exception("Sequence capacity (999) reached for branch {$t1}-" . sprintf('%02d-%02d', $t2, $t3) . ".");
            }

            $formattedCode = sprintf("%d-%02d-%02d-%03d", $t1, $t2, $t3, $finalSeq);
            $rawCode = sprintf("%02d%02d%02d%03d", $t1, $t2, $t3, $finalSeq);

            $nameChange = (trim(strtoupper($req['item_name'])) !== trim(strtoupper($finalItemName))) ? 'YES' : 'NO';
            $uomChange = (trim(strtoupper($req['uom'])) !== trim(strtoupper($finalUom))) ? 'YES' : 'NO';

            // Update Request Record as Approved (Staged, not yet in Master DB)
            $upStmt = $this->db->prepare("
                UPDATE code_requests 
                SET status = 'APPROVED', 
                    item_name = :iname,
                    specifications = :specs,
                    uom = :uom,
                    assigned_tier4_seq = :aseq, 
                    master_db_name = :mname,
                    master_db_uom = :muom,
                    name_change = :nchange,
                    uom_change = :uchange,
                    is_added_to_master = 0,
                    reviewed_by = :aid, 
                    review_remarks = :rem, 
                    reviewed_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $upStmt->execute([
                ':iname' => $req['item_name'],
                ':specs' => $finalSpecs,
                ':uom' => $req['uom'],
                ':aseq' => $finalSeq,
                ':mname' => $finalItemName,
                ':muom' => $finalUom,
                ':nchange' => $nameChange,
                ':uchange' => $uomChange,
                ':aid' => $adminId,
                ':rem' => $remarks ?: 'Approved by Administrator.',
                ':id' => $requestId
            ]);

            $this->db->commit();

            if ($addToMasterNow) {
                return $this->addToMaster($requestId, $adminId);
            }

            return [
                'success' => true,
                'message' => "Request #{$req['request_no']} approved successfully. Click '+ Add to Master' to officially commit to Master DB.",
                'request_id' => $requestId,
                'formatted_code' => $formattedCode,
                'raw_code' => $rawCode,
                'is_added_to_master' => 0
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Add an approved Code Request directly into FIFO Central Master DB (Orange Highlighted Code)
     */
    public function addToMaster(int $requestId, int $adminId): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM code_requests WHERE id = :id");
            $stmt->execute([':id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                throw new Exception("Code request #{$requestId} not found.");
            }

            $t1 = (int)$req['tier1'];
            $t2 = (int)$req['tier2'];
            $t3 = (int)$req['tier3'];

            $seq = ($req['assigned_tier4_seq'] !== null) ? (int)$req['assigned_tier4_seq'] : $this->getNextAvailableSequence($t1, $t2, $t3);
            $formattedCode = sprintf("%d-%02d-%02d-%03d", $t1, $t2, $t3, $seq);
            $rawCode = sprintf("%02d%02d%02d%03d", $t1, $t2, $t3, $seq);

            $masterName = !empty($req['master_db_name']) ? $req['master_db_name'] : $req['item_name'];
            $masterUom = !empty($req['master_db_uom']) ? $req['master_db_uom'] : $req['uom'];
            $origDesc = $req['item_name'];
            $origUom = $req['uom'];
            $nameChange = (trim(strtoupper($origDesc)) !== trim(strtoupper($masterName))) ? 'YES' : 'NO';
            $uomChange = (trim(strtoupper($origUom)) !== trim(strtoupper($masterUom))) ? 'YES' : 'NO';

            // Check if already in master items
            $chkStmt = $this->db->prepare("SELECT id FROM fifo_master_items WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND tier4_seq = :seq");
            $chkStmt->execute([':t1' => $t1, ':t2' => $t2, ':t3' => $t3, ':seq' => $seq]);
            $existing = $chkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newMasterId = (int)$existing['id'];
                $upMasterStmt = $this->db->prepare("
                    UPDATE fifo_master_items
                    SET item_name = :iname, specifications = :specs, uom = :uom, origin_station_id = :sid,
                        code_request_id = :crid, orig_description = :odesc, orig_uom = :ouom,
                        name_change = :nchange, uom_change = :uchange,
                        unit_price = COALESCE(:uprice, unit_price),
                        image_url_1 = COALESCE(:img1, image_url_1),
                        image_url_2 = COALESCE(:img2, image_url_2)
                    WHERE id = :mid
                ");
                $upMasterStmt->execute([
                    ':iname' => $masterName,
                    ':specs' => $req['specifications'],
                    ':uom' => $masterUom,
                    ':sid' => $req['origin_station_id'],
                    ':crid' => $requestId,
                    ':odesc' => $origDesc,
                    ':ouom' => $origUom,
                    ':nchange' => $nameChange,
                    ':uchange' => $uomChange,
                    ':uprice' => $req['unit_price'] ?? null,
                    ':img1' => $req['image_url_1'] ?? null,
                    ':img2' => $req['image_url_2'] ?? null,
                    ':mid' => $newMasterId
                ]);
            } else {
                $masterStmt = $this->db->prepare("
                    INSERT INTO fifo_master_items (
                        tier1, tier2, tier3, tier4_seq, formatted_code, raw_code, 
                        item_name, specifications, uom, status, created_by,
                        origin_station_id, code_request_id, orig_description, orig_uom, name_change, uom_change,
                        unit_price, image_url_1, image_url_2
                    ) VALUES (
                        :t1, :t2, :t3, :seq, :fcode, :rcode, 
                        :iname, :specs, :uom, 'ACTIVE', :admin_id,
                        :sid, :crid, :odesc, :ouom, :nchange, :uchange,
                        :uprice, :img1, :img2
                    )
                ");
                $masterStmt->execute([
                    ':t1' => $t1,
                    ':t2' => $t2,
                    ':t3' => $t3,
                    ':seq' => $seq,
                    ':fcode' => $formattedCode,
                    ':rcode' => $rawCode,
                    ':iname' => $masterName,
                    ':specs' => $req['specifications'],
                    ':uom' => $masterUom,
                    ':admin_id' => $adminId,
                    ':sid' => $req['origin_station_id'],
                    ':crid' => $requestId,
                    ':odesc' => $origDesc,
                    ':ouom' => $origUom,
                    ':nchange' => $nameChange,
                    ':uchange' => $uomChange,
                    ':uprice' => $req['unit_price'] ?? null,
                    ':img1' => $req['image_url_1'] ?? null,
                    ':img2' => $req['image_url_2'] ?? null
                ]);
                $newMasterId = (int)$this->db->lastInsertId();
            }

            // Update code_requests record(s) matching this request or same branch and assigned sequence
            $upReqStmt = $this->db->prepare("
                UPDATE code_requests
                SET is_added_to_master = 1,
                    generated_master_item_id = :mid,
                    assigned_tier4_seq = :seq,
                    status = 'APPROVED',
                    reviewed_by = :aid,
                    reviewed_at = CURRENT_TIMESTAMP
                WHERE id = :id 
                   OR (tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND COALESCE(assigned_tier4_seq, suggested_tier4_seq) = :cseq AND (is_added_to_master IS NULL OR is_added_to_master = 0))
            ");
            $upReqStmt->execute([
                ':mid' => $newMasterId,
                ':seq' => $seq,
                ':aid' => $adminId,
                ':id' => $requestId,
                ':t1' => $t1,
                ':t2' => $t2,
                ':t3' => $t3,
                ':cseq' => $seq
            ]);

            // Auto-provision to origin station items for all stations that requested this code
            $stationsStmt = $this->db->prepare("
                SELECT DISTINCT origin_station_id 
                FROM code_requests 
                WHERE (id = :id OR (tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND COALESCE(assigned_tier4_seq, suggested_tier4_seq) = :cseq))
                  AND origin_station_id IS NOT NULL AND origin_station_id > 0
            ");
            $stationsStmt->execute([':id' => $requestId, ':t1' => $t1, ':t2' => $t2, ':t3' => $t3, ':cseq' => $seq]);
            $stationRows = $stationsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($stationRows as $stRow) {
                $sid = (int)$stRow['origin_station_id'];
                try {
                    $stCheck = $this->db->prepare("SELECT id FROM station_items WHERE station_id = :sid AND master_item_id = :mid");
                    $stCheck->execute([':sid' => $sid, ':mid' => $newMasterId]);
                    if (!$stCheck->fetch()) {
                        $stInsert = $this->db->prepare("
                            INSERT INTO station_items (station_id, master_item_id, local_bay_location, imported_by)
                            VALUES (:sid, :mid, 'Bay-A1', :uid)
                        ");
                        $stInsert->execute([
                            ':sid' => $sid,
                            ':mid' => $newMasterId,
                            ':uid' => $adminId
                        ]);
                    }
                } catch (\Throwable $t) {
                    // Ignore duplicate
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Master Code {$rawCode} successfully added to FIFO Master Database.",
                'master_item_id' => $newMasterId,
                'formatted_code' => $formattedCode,
                'raw_code' => $rawCode,
                'is_added_to_master' => 1
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Admin Reject Request
     */
    public function rejectRequest(int $requestId, int $adminId, string $remarks): bool {
        if (empty($remarks)) {
            throw new Exception("Rejection remarks/reason are required.");
        }
        $stmt = $this->db->prepare("
            UPDATE code_requests
            SET status = 'REJECTED',
                reviewed_by = :aid,
                review_remarks = :rem,
                reviewed_at = CURRENT_TIMESTAMP
            WHERE id = :id AND status = 'PENDING'
        ");
        return $stmt->execute([
            ':aid' => $adminId,
            ':rem' => $remarks,
            ':id' => $requestId
        ]);
    }

    /**
     * Import existing Master Item to Station Inventory
     */
    public function importToStation(int $stationId, int $masterItemId, int $userId, string $bay = 'Bay-A1'): array {
        // Check if master item exists
        $mStmt = $this->db->prepare("SELECT * FROM fifo_master_items WHERE id = :id");
        $mStmt->execute([':id' => $masterItemId]);
        $master = $mStmt->fetch(PDO::FETCH_ASSOC);
        if (!$master) {
            throw new Exception("This code is not in the Master Database. Only codes that have been added to Master (Orange Codes) by the Code Admin can be added to power stations.");
        }

        // Check duplicate import
        $dupStmt = $this->db->prepare("SELECT id FROM station_items WHERE station_id = :sid AND master_item_id = :mid");
        $dupStmt->execute([':sid' => $stationId, ':mid' => $masterItemId]);
        if ($dupStmt->fetch()) {
            throw new Exception("Item '{$master['formatted_code']}' is already imported in this Power Station.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO station_items (station_id, master_item_id, local_bay_location, imported_by)
            VALUES (:sid, :mid, :bay, :uid)
        ");
        $stmt->execute([
            ':sid' => $stationId,
            ':mid' => $masterItemId,
            ':bay' => $bay ?: 'Bay-A1',
            ':uid' => $userId
        ]);

        return [
            'success' => true,
            'message' => "Item {$master['formatted_code']} imported into station inventory successfully."
        ];
    }

    /**
     * Get Station's imported inventory
     */
    public function getStationItems(int $stationId, ?string $search = null): array {
        $sql = "
            SELECT s.*, 
                   m.formatted_code, m.raw_code, m.item_name, m.specifications, m.uom, m.tier1, m.tier2, m.tier3, m.tier4_seq,
                   u.full_name as importer_name
            FROM station_items s
            JOIN fifo_master_items m ON s.master_item_id = m.id
            LEFT JOIN users u ON s.imported_by = u.id
            WHERE s.station_id = :sid
        ";
        $params = [':sid' => $stationId];

        if (!empty($search)) {
            $sql .= " AND (m.item_name LIKE :q OR m.formatted_code LIKE :q2 OR m.raw_code LIKE :q3 OR s.local_bay_location LIKE :q4)";
            $params[':q'] = "%$search%";
            $params[':q2'] = "%$search%";
            $params[':q3'] = "%$search%";
            $params[':q4'] = "%$search%";
        }

        $sql .= " ORDER BY m.tier1 ASC, m.tier2 ASC, m.tier3 ASC, m.tier4_seq ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all registered master items for the Register table (matching Excel format)
     * For Admins: Returns Master Added (Orange) + Uncommitted Requests (Non-Orange with Add to Master button)
     * For Power Stations: Returns ONLY Master Added (Orange) items
     */
    public function getAllMasterItems(string $search = '', ?int $tier1 = null, ?int $tier2 = null, ?int $tier3 = null, int $limit = 20000, bool $includeUncommitted = true): array {
        // 1. Fetch Official Master Items (is_added_to_master = 1, ORANGE CODE)
        $masterSql = "
            SELECT m.id as master_id,
                   COALESCE(m.code_request_id, r.id) as request_id,
                   m.tier1, m.tier2, m.tier3, m.tier4_seq,
                   m.formatted_code, m.raw_code,
                   m.item_name as master_db_name,
                   m.uom as master_db_uom,
                   COALESCE(m.orig_description, r.item_name, m.item_name) as orig_description,
                   COALESCE(m.orig_uom, r.uom, m.uom) as orig_uom,
                   COALESCE(m.specifications, r.specifications, '') as specifications,
                   COALESCE(m.name_change, 'NO') as name_change,
                   COALESCE(m.uom_change, 'NO') as uom_change,
                   COALESCE(l_req.code, l_orig.code, l_u.code, 'VIC') as station_code,
                   COALESCE(l_req.name, l_orig.name, l_u.name, 'Victoria Power Station (831)') as station_name,
                   m.unit_price,
                   m.image_url_1,
                   m.image_url_2,
                   1 as is_added_to_master,
                   'APPROVED' as approval_status,
                   0 as station_count
            FROM fifo_master_items m
            LEFT JOIN users u ON m.created_by = u.id
            LEFT JOIN locations l_u ON u.location_id = l_u.id
            LEFT JOIN locations l_orig ON m.origin_station_id = l_orig.id
            LEFT JOIN code_requests r ON m.code_request_id = r.id
            LEFT JOIN locations l_req ON r.origin_station_id = l_req.id
            WHERE 1=1
        ";
        $params = [];
        if ($tier1 !== null && $tier1 !== 0) {
            $masterSql .= " AND m.tier1 = :t1";
            $params[':t1'] = $tier1;
        }
        if ($tier2 !== null && $tier2 !== 0) {
            $masterSql .= " AND m.tier2 = :t2";
            $params[':t2'] = $tier2;
        }
        if ($tier3 !== null && $tier3 !== 0) {
            $masterSql .= " AND m.tier3 = :t3";
            $params[':t3'] = $tier3;
        }
        if (!empty($search)) {
            $masterSql .= " AND (m.item_name LIKE :q1 OR m.formatted_code LIKE :q2 OR m.raw_code LIKE :q3 OR m.specifications LIKE :q4 OR r.item_name LIKE :q5)";
            $params[':q1'] = "%$search%";
            $params[':q2'] = "%$search%";
            $params[':q3'] = "%$search%";
            $params[':q4'] = "%$search%";
            $params[':q5'] = "%$search%";
        }

        $stmt = $this->db->prepare($masterSql);
        $stmt->execute($params);
        $masterItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (!$includeUncommitted) {
            // Power Stations only see Master Added (Orange) items
            usort($masterItems, function($a, $b) {
                if ($a['tier1'] != $b['tier1']) return $a['tier1'] <=> $b['tier1'];
                if ($a['tier2'] != $b['tier2']) return $a['tier2'] <=> $b['tier2'];
                if ($a['tier3'] != $b['tier3']) return $a['tier3'] <=> $b['tier3'];
                return (int)$a['tier4_seq'] <=> (int)$b['tier4_seq'];
            });
            return array_slice($masterItems, 0, $limit);
        }

        // 2. Fetch Uncommitted Approved Requests (is_added_to_master = 0, status = 'APPROVED')
        // STRICT RULE: Pending process requests must NEVER appear in item search!
        $reqSql = "
            SELECT NULL as master_id,
                   r.id as request_id,
                   r.tier1, r.tier2, r.tier3,
                   r.assigned_tier4_seq as tier4_seq,
                   '' as formatted_code,
                   '' as raw_code,
                   r.master_db_name,
                   r.master_db_uom,
                   r.item_name as orig_description,
                   r.uom as orig_uom,
                   COALESCE(r.specifications, '') as specifications,
                   COALESCE(r.name_change, 'NO') as name_change,
                   COALESCE(r.uom_change, 'NO') as uom_change,
                   COALESCE(l.code, 'STN') as station_code,
                   COALESCE(l.name, 'Origin Station') as station_name,
                   r.unit_price,
                   r.image_url_1,
                   r.image_url_2,
                   0 as is_added_to_master,
                   r.status as approval_status,
                   0 as station_count
            FROM code_requests r
            LEFT JOIN locations l ON r.origin_station_id = l.id
            WHERE (r.is_added_to_master IS NULL OR r.is_added_to_master = 0)
              AND r.status = 'APPROVED'
              AND r.assigned_tier4_seq IS NOT NULL
        ";
        $reqParams = [];
        if ($tier1 !== null && $tier1 !== 0) {
            $reqSql .= " AND r.tier1 = :rt1";
            $reqParams[':rt1'] = $tier1;
        }
        if ($tier2 !== null && $tier2 !== 0) {
            $reqSql .= " AND r.tier2 = :rt2";
            $reqParams[':rt2'] = $tier2;
        }
        if ($tier3 !== null && $tier3 !== 0) {
            $reqSql .= " AND r.tier3 = :rt3";
            $reqParams[':rt3'] = $tier3;
        }
        if (!empty($search)) {
            $reqSql .= " AND (r.item_name LIKE :rq1 OR r.specifications LIKE :rq2)";
            $reqParams[':rq1'] = "%$search%";
            $reqParams[':rq2'] = "%$search%";
        }

        $stmtReq = $this->db->prepare($reqSql);
        $stmtReq->execute($reqParams);
        $uncommittedReqs = $stmtReq->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $uniqueMap = [];

        // 1. Process Master Items first (Priority: Master added items have is_added_to_master = 1)
        foreach ($masterItems as $m) {
            $t1 = (int)$m['tier1'];
            $t2 = (int)$m['tier2'];
            $t3 = (int)$m['tier3'];
            $t4 = (int)$m['tier4_seq'];
            $rawCode = sprintf("%02d%02d%02d%03d", $t1, $t2, $t3, $t4);
            $m['raw_code'] = $rawCode;
            $m['formatted_code'] = sprintf("%d-%02d-%02d-%03d", $t1, $t2, $t3, $t4);
            $m['is_added_to_master'] = 1;
            $uniqueMap[$rawCode] = $m;
        }

        // 2. Process Requests: Only add if this code is NOT already in master!
        foreach ($uncommittedReqs as $r) {
            $t1 = (int)$r['tier1'];
            $t2 = (int)$r['tier2'];
            $t3 = (int)$r['tier3'];
            $t4 = (int)$r['tier4_seq'];
            $rawCode = sprintf("%02d%02d%02d%03d", $t1, $t2, $t3, $t4);
            
            // If code already exists in master map, attach request_id if needed, but do NOT create a second row!
            if (isset($uniqueMap[$rawCode])) {
                if (empty($uniqueMap[$rawCode]['request_id']) && !empty($r['request_id'])) {
                    $uniqueMap[$rawCode]['request_id'] = $r['request_id'];
                }
                continue;
            }

            $r['raw_code'] = $rawCode;
            $r['formatted_code'] = sprintf("%d-%02d-%02d-%03d", $t1, $t2, $t3, $t4);
            $r['is_added_to_master'] = 0;
            $uniqueMap[$rawCode] = $r;
        }

        $combined = array_values($uniqueMap);

        // Sort cleanly by Tier 1, Tier 2, Tier 3, Tier 4
        usort($combined, function($a, $b) {
            if ($a['tier1'] != $b['tier1']) return $a['tier1'] <=> $b['tier1'];
            if ($a['tier2'] != $b['tier2']) return $a['tier2'] <=> $b['tier2'];
            if ($a['tier3'] != $b['tier3']) return $a['tier3'] <=> $b['tier3'];
            return (int)$a['tier4_seq'] <=> (int)$b['tier4_seq'];
        });

        return array_slice($combined, 0, $limit);
    }

    /**
     * Get Master Item statistics for dashboard
     */
    public function getStats(): array {
        $totalCodes = (int)$this->db->query("SELECT COUNT(*) FROM fifo_master_items")->fetchColumn();
        $pendingReqs = (int)$this->db->query("SELECT COUNT(*) FROM code_requests WHERE status = 'PENDING' AND origin_station_id > 0")->fetchColumn();
        $approvedReqs = (int)$this->db->query("SELECT COUNT(*) FROM code_requests WHERE status = 'APPROVED' AND (is_added_to_master IS NULL OR is_added_to_master = 0) AND origin_station_id > 0")->fetchColumn();
        $stationImports = (int)$this->db->query("SELECT COUNT(*) FROM station_items")->fetchColumn();
        $totalLibrary = (int)$this->db->query("SELECT COUNT(*) FROM code_requests WHERE status != 'REJECTED' AND origin_station_id > 0")->fetchColumn();

        return [
            'total_master_codes' => $totalCodes,
            'pending_requests' => $pendingReqs,
            'approved_requests' => $approvedReqs,
            'station_allocations' => $stationImports,
            'total_library_codes' => $totalLibrary ?: ($totalCodes + $approvedReqs)
        ];
    }

    /**
     * Get Master Item by primary ID
     */
    public function getMasterItemById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT m.*, 
                   l.name as station_name, l.code as station_code,
                   u.full_name as creator_name
            FROM fifo_master_items m
            LEFT JOIN locations l ON m.origin_station_id = l.id
            LEFT JOIN users u ON m.created_by = u.id
            WHERE m.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Direct Add / Update Master Code for Code Admin (Bypasses approval workflow, writes directly to fifo_master_items)
     */
    public function saveDirectMasterCode(array $data, int $adminId): array {
        $this->db->beginTransaction();
        try {
            $itemId = !empty($data['id']) ? (int)$data['id'] : null;
            $tier1 = (int)($data['tier1'] ?? 1);
            $tier2 = (int)($data['tier2'] ?? 1);
            $tier3 = (int)($data['tier3'] ?? 1);
            $itemName = mb_strtoupper(trim($data['item_name'] ?? ''), 'UTF-8');
            $specs = !empty($data['specifications']) ? mb_strtoupper(trim($data['specifications']), 'UTF-8') : null;
            $uom = mb_strtoupper(trim($data['uom'] ?? 'NOS'), 'UTF-8');
            $stationId = !empty($data['origin_station_id']) ? (int)$data['origin_station_id'] : null;
            $status = !empty($data['status']) ? strtoupper(trim($data['status'])) : 'ACTIVE';
            $autoImportStation = !empty($data['auto_import_station']);

            if (empty($itemName)) {
                throw new Exception("Item Description / Name is required.");
            }
            if ($tier1 < 1 || $tier1 > 19) {
                throw new Exception("1st Code (Tier 1) must be between 1 and 19.");
            }
            if ($tier2 < 1 || $tier2 > 99) {
                throw new Exception("2nd Code (Tier 2) must be between 1 and 99.");
            }
            if ($tier3 < 1 || $tier3 > 99) {
                throw new Exception("3rd Code (Tier 3) must be between 1 and 99.");
            }

            // Determine sequence
            $customSeq = isset($data['tier4_seq']) && $data['tier4_seq'] !== '' ? (int)$data['tier4_seq'] : null;

            if ($itemId) {
                // Update Existing Record
                $stmtCheck = $this->db->prepare("SELECT * FROM fifo_master_items WHERE id = :id");
                $stmtCheck->execute([':id' => $itemId]);
                $existingItem = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                if (!$existingItem) {
                    throw new Exception("Master Item #{$itemId} not found for updating.");
                }

                $seq = ($customSeq !== null) ? $customSeq : (int)$existingItem['tier4_seq'];

                // Check slot collision if tier branch or sequence changed
                if ($tier1 !== (int)$existingItem['tier1'] || $tier2 !== (int)$existingItem['tier2'] || $tier3 !== (int)$existingItem['tier3'] || $seq !== (int)$existingItem['tier4_seq']) {
                    $chkSlot = $this->db->prepare("SELECT id FROM fifo_master_items WHERE tier1 = :t1 AND tier2 = :t2 AND tier3 = :t3 AND tier4_seq = :seq AND id != :id");
                    $chkSlot->execute([':t1' => $tier1, ':t2' => $tier2, ':t3' => $tier3, ':seq' => $seq, ':id' => $itemId]);
                    if ($chkSlot->fetch()) {
                        $nextFree = $this->getNextAvailableSequence($tier1, $tier2, $tier3);
                        throw new Exception("Sequence slot " . sprintf('%03d', $seq) . " in branch {$tier1}-" . sprintf('%02d-%02d', $tier2, $tier3) . " is already occupied. Next available slot is " . sprintf('%03d', $nextFree) . ".");
                    }
                }

                $formattedCode = sprintf("%d-%02d-%02d-%03d", $tier1, $tier2, $tier3, $seq);
                $rawCode = sprintf("%02d%02d%02d%03d", $tier1, $tier2, $tier3, $seq);
                $origDesc = !empty($existingItem['orig_description']) ? $existingItem['orig_description'] : $itemName;
                $origUom = !empty($existingItem['orig_uom']) ? $existingItem['orig_uom'] : $uom;
                $nameChange = (trim(strtoupper($origDesc)) !== trim(strtoupper($itemName))) ? 'YES' : 'NO';
                $uomChange = (trim(strtoupper($origUom)) !== trim(strtoupper($uom))) ? 'YES' : 'NO';

                $upStmt = $this->db->prepare("
                    UPDATE fifo_master_items
                    SET tier1 = :t1, tier2 = :t2, tier3 = :t3, tier4_seq = :seq,
                        formatted_code = :fcode, raw_code = :rcode,
                        item_name = :iname, specifications = :specs, uom = :uom,
                        status = :status, origin_station_id = :sid,
                        orig_description = :odesc, orig_uom = :ouom,
                        name_change = :nchange, uom_change = :uchange
                    WHERE id = :id
                ");
                $upStmt->execute([
                    ':t1' => $tier1,
                    ':t2' => $tier2,
                    ':t3' => $tier3,
                    ':seq' => $seq,
                    ':fcode' => $formattedCode,
                    ':rcode' => $rawCode,
                    ':iname' => $itemName,
                    ':specs' => $specs,
                    ':uom' => $uom,
                    ':status' => $status,
                    ':sid' => $stationId,
                    ':odesc' => $origDesc,
                    ':ouom' => $origUom,
                    ':nchange' => $nameChange,
                    ':uchange' => $uomChange,
                    ':id' => $itemId
                ]);

                $masterId = $itemId;
                $actionType = 'UPDATED';
            } else {
                // Create New Master Code
                if ($customSeq !== null && $customSeq >= 0) {
                    $seq = $customSeq;
                    if (!$this->isSequenceSlotAvailable($tier1, $tier2, $tier3, $seq)) {
                        $nextFree = $this->getNextAvailableSequence($tier1, $tier2, $tier3);
                        throw new Exception("Sequence slot " . sprintf('%03d', $seq) . " in branch {$tier1}-" . sprintf('%02d-%02d', $tier2, $tier3) . " is already occupied. Next available slot is " . sprintf('%03d', $nextFree) . ".");
                    }
                } else {
                    $seq = $this->getNextAvailableSequence($tier1, $tier2, $tier3);
                }

                $formattedCode = sprintf("%d-%02d-%02d-%03d", $tier1, $tier2, $tier3, $seq);
                $rawCode = sprintf("%02d%02d%02d%03d", $tier1, $tier2, $tier3, $seq);

                // Optional duplicate name warning check
                $dupCheck = $this->db->prepare("SELECT id, formatted_code FROM fifo_master_items WHERE LOWER(TRIM(item_name)) = LOWER(TRIM(:iname)) LIMIT 1");
                $dupCheck->execute([':iname' => $itemName]);
                $dup = $dupCheck->fetch(PDO::FETCH_ASSOC);
                if ($dup && empty($data['allow_duplicate_name'])) {
                    throw new Exception("An item with the exact description '{$itemName}' already exists with Code {$dup['formatted_code']}. To update it, please switch to Update Mode or check the allow duplicate flag.");
                }

                $inStmt = $this->db->prepare("
                    INSERT INTO fifo_master_items (
                        tier1, tier2, tier3, tier4_seq, formatted_code, raw_code,
                        item_name, specifications, uom, status, created_by,
                        origin_station_id, orig_description, orig_uom, name_change, uom_change
                    ) VALUES (
                        :t1, :t2, :t3, :seq, :fcode, :rcode,
                        :iname, :specs, :uom, :status, :aid,
                        :sid, :odesc, :ouom, 'NO', 'NO'
                    )
                ");
                $inStmt->execute([
                    ':t1' => $tier1,
                    ':t2' => $tier2,
                    ':t3' => $tier3,
                    ':seq' => $seq,
                    ':fcode' => $formattedCode,
                    ':rcode' => $rawCode,
                    ':iname' => $itemName,
                    ':specs' => $specs,
                    ':uom' => $uom,
                    ':status' => $status,
                    ':aid' => $adminId,
                    ':sid' => $stationId,
                    ':odesc' => $itemName,
                    ':ouom' => $uom
                ]);
                $masterId = (int)$this->db->lastInsertId();
                $actionType = 'CREATED';
            }

            // Provision to Power Station if stationId provided
            if ($stationId && $autoImportStation) {
                try {
                    $stCheck = $this->db->prepare("SELECT id FROM station_items WHERE station_id = :sid AND master_item_id = :mid");
                    $stCheck->execute([':sid' => $stationId, ':mid' => $masterId]);
                    if (!$stCheck->fetch()) {
                        $stInsert = $this->db->prepare("
                            INSERT INTO station_items (station_id, master_item_id, local_bay_location, imported_by)
                            VALUES (:sid, :mid, 'Bay-A1', :uid)
                        ");
                        $stInsert->execute([
                            ':sid' => $stationId,
                            ':mid' => $masterId,
                            ':uid' => $adminId
                        ]);
                    }
                } catch (\Throwable $t) {
                    // Safe ignore duplicate
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'action' => $actionType,
                'master_id' => $masterId,
                'formatted_code' => $formattedCode,
                'raw_code' => $rawCode,
                'tier1' => $tier1,
                'tier2' => $tier2,
                'tier3' => $tier3,
                'tier4_seq' => $seq,
                'item_name' => $itemName,
                'uom' => $uom,
                'message' => ($actionType === 'CREATED') 
                    ? "Master Code {$rawCode} ({$formattedCode}) successfully created and saved directly to Master Database (Orange Code Active)."
                    : "Master Code {$rawCode} ({$formattedCode}) successfully updated in Master Database."
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update Master Item Name and Unit of Measurement (UoM)
     */
    public function updateMasterItemNameAndUom(?int $masterId, ?int $requestId, string $itemName, string $uom, int $userId): array {
        $itemName = mb_strtoupper(trim($itemName), 'UTF-8');
        $uom = mb_strtoupper(trim($uom), 'UTF-8');

        if (empty($itemName)) {
            throw new Exception("Master Database Name / Item Description cannot be empty.");
        }
        if (empty($uom)) {
            throw new Exception("Unit of Measurement (UoM) cannot be empty.");
        }

        $this->db->beginTransaction();
        try {
            $updatedMaster = false;
            $updatedRequest = false;

            if ($masterId && $masterId > 0) {
                $stmt = $this->db->prepare("SELECT * FROM fifo_master_items WHERE id = :id");
                $stmt->execute([':id' => $masterId]);
                $master = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($master) {
                    $origDesc = !empty($master['orig_description']) ? $master['orig_description'] : $itemName;
                    $origUom = !empty($master['orig_uom']) ? $master['orig_uom'] : $uom;
                    $nameChange = (trim(strtoupper($origDesc)) !== trim(strtoupper($itemName))) ? 'YES' : 'NO';
                    $uomChange = (trim(strtoupper($origUom)) !== trim(strtoupper($uom))) ? 'YES' : 'NO';

                    $up = $this->db->prepare("
                        UPDATE fifo_master_items 
                        SET item_name = :iname, 
                            uom = :uom, 
                            name_change = :nchange, 
                            uom_change = :uchange 
                        WHERE id = :id
                    ");
                    $up->execute([
                        ':iname' => $itemName,
                        ':uom' => $uom,
                        ':nchange' => $nameChange,
                        ':uchange' => $uomChange,
                        ':id' => $masterId
                    ]);
                    $updatedMaster = true;

                    $reqIdToUpdate = $requestId ?: ($master['code_request_id'] ?? null);
                    if ($reqIdToUpdate) {
                        $upReq = $this->db->prepare("
                            UPDATE code_requests 
                            SET master_db_name = :iname, 
                                master_db_uom = :uom, 
                                name_change = :nchange, 
                                uom_change = :uchange 
                            WHERE id = :rid
                        ");
                        $upReq->execute([
                            ':iname' => $itemName,
                            ':uom' => $uom,
                            ':nchange' => $nameChange,
                            ':uchange' => $uomChange,
                            ':rid' => $reqIdToUpdate
                        ]);
                        $updatedRequest = true;
                    }
                }
            }

            if (!$updatedMaster && $requestId && $requestId > 0) {
                $stmt = $this->db->prepare("SELECT * FROM code_requests WHERE id = :id");
                $stmt->execute([':id' => $requestId]);
                $req = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($req) {
                    $origDesc = $req['item_name'];
                    $origUom = $req['uom'];
                    $nameChange = (trim(strtoupper($origDesc)) !== trim(strtoupper($itemName))) ? 'YES' : 'NO';
                    $uomChange = (trim(strtoupper($origUom)) !== trim(strtoupper($uom))) ? 'YES' : 'NO';

                    $upReq = $this->db->prepare("
                        UPDATE code_requests 
                        SET master_db_name = :iname, 
                            master_db_uom = :uom, 
                            name_change = :nchange, 
                            uom_change = :uchange 
                        WHERE id = :id
                    ");
                    $upReq->execute([
                        ':iname' => $itemName,
                        ':uom' => $uom,
                        ':nchange' => $nameChange,
                        ':uchange' => $uomChange,
                        ':id' => $requestId
                    ]);
                    $updatedRequest = true;
                }
            }

            if (!$updatedMaster && !$updatedRequest) {
                throw new Exception("Target item record not found.");
            }

            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Master Item Name and Unit of Measurement updated successfully.',
                'master_id' => $masterId,
                'request_id' => $requestId,
                'item_name' => $itemName,
                'uom' => $uom
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete / Deactivate a Master Code
     */
    public function deleteMasterItem(int $id, int $adminId): bool {
        $this->db->beginTransaction();
        try {
            // Delete linked station items first
            $stmtSt = $this->db->prepare("DELETE FROM station_items WHERE master_item_id = :mid");
            $stmtSt->execute([':mid' => $id]);

            // Delete master item
            $stmt = $this->db->prepare("DELETE FROM fifo_master_items WHERE id = :id");
            $res = $stmt->execute([':id' => $id]);

            $this->db->commit();
            return $res;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Live search and spelling correction assistant as the user types word-by-word
     */
    public function searchWithSpellCheck(string $query, ?int $tier1 = null, ?int $tier2 = null, ?int $tier3 = null, int $limit = 8): array {
        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return [
                'matches' => [],
                'spelling_suggestions' => [],
                'corrected_query' => '',
                'has_spelling_corrections' => false,
                'total_found' => 0
            ];
        }

        // 1. Dictionary of Standard Power Plant / Inventory Engineering Terminology
        $dictionary = [
            'BEARING', 'BEARINGS', 'BUSHING', 'BUSHINGS', 'VALVE', 'VALVES', 'PUMP', 'PUMPS', 'IMPELLER', 'IMPELLERS',
            'GEAR', 'GEARS', 'COUPLING', 'COUPLINGS', 'TURBINE', 'TURBINES', 'RUNNER', 'RUNNERS', 'SEAL', 'SEALS',
            'FLANGE', 'FLANGES', 'GASKET', 'GASKETS', 'CYLINDER', 'CYLINDERS', 'ACTUATOR', 'ACTUATORS', 'PISTON',
            'SHAFT', 'SHAFTS', 'HOUSING', 'NOZZLE', 'NOZZLES', 'MANIFOLD', 'ROTOR', 'ROTORS', 'STATOR', 'STATORS',
            'COMPRESSOR', 'COMPRESSORS', 'CONDENSER', 'EXCHANGER', 'STRAINER', 'STRAINERS', 'FILTER', 'FILTERS',
            'PULLEY', 'BELT', 'BELTS', 'CHAIN', 'CHAINS', 'SPROCKET', 'SPROCKETS', 'CLUTCH', 'BRAKE', 'BRAKES',
            'SPRING', 'SPRINGS', 'FASTENER', 'FASTENERS', 'BOLT', 'BOLTS', 'NUT', 'NUTS', 'WASHER', 'WASHERS',
            'STUD', 'STUDS', 'SCREW', 'SCREWS', 'PIN', 'PINS', 'KEYWAY', 'O-RING', 'PACKING', 'DIAPHRAGM',
            'TRANSFORMER', 'TRANSFORMERS', 'GENERATOR', 'GENERATORS', 'BREAKER', 'BREAKERS', 'CONTACTOR', 'CONTACTORS',
            'RELAY', 'RELAYS', 'SWITCH', 'SWITCHES', 'ISOLATOR', 'ISOLATORS', 'BUSBAR', 'BUSBARS', 'CABLE', 'CABLES',
            'CONDUCTOR', 'CONDUCTORS', 'TERMINAL', 'TERMINALS', 'LUG', 'LUGS', 'FUSE', 'FUSES', 'INVERTER', 'INVERTERS',
            'CONVERTER', 'BATTERY', 'BATTERIES', 'CHARGER', 'CHARGERS', 'CAPACITOR', 'CAPACITORS', 'RESISTOR',
            'DIODE', 'DIODES', 'THYRISTOR', 'SOLENOID', 'SOLENOIDS', 'COIL', 'COILS', 'MOTOR', 'MOTORS', 'STARTER',
            'ENCLOSURE', 'CONDUIT', 'LUMINAIRE', 'LUMINAIRES', 'VOLTMETER', 'AMMETER', 'TRANSMITTER', 'TRANSMITTERS',
            'SENSOR', 'SENSORS', 'TRANSDUCER', 'TRANSDUCERS', 'GAUGE', 'GAUGES', 'THERMOCOUPLE', 'THERMOCOUPLES',
            'RTD', 'FLOWMETER', 'POTENTIOMETER', 'ENCODER', 'CONTROLLER', 'CONTROLLERS', 'MODULE', 'MODULES',
            'POSITIONER', 'REGULATOR', 'REGULATORS', 'LUBRICANT', 'LUBRICANTS', 'GREASE', 'GREASES', 'OIL', 'OILS',
            'SOLVENT', 'SOLVENTS', 'ADHESIVE', 'ADHESIVES', 'SEALANT', 'SEALANTS', 'COOLANT', 'ELECTRODE', 'ELECTRODES',
            'WELDING', 'AEROSOL', 'PAINT', 'PRIMER', 'THINNER', 'DETERGENT', 'GLOVES', 'HELMET', 'HARNESS',
            'STAINLESS', 'CARBON', 'MILD', 'BRONZE', 'BRASS', 'COPPER', 'ALUMINUM', 'CAST', 'FORGED', 'DUCTILE',
            'RUBBER', 'TEFLON', 'NYLON', 'POLYETHYLENE', 'HIGH', 'VOLTAGE', 'PRESSURE', 'TEMPERATURE', 'HYDRAULIC',
            'PNEUMATIC', 'MECHANICAL', 'ELECTRICAL', 'ELECTRONIC', 'INSTRUMENTATION', 'AUTOMATION', 'DEEP', 'GROOVE',
            'SPHERICAL', 'ROLLER', 'THRUST', 'ANGULAR', 'CONTACT', 'BALL', 'TAPERED', 'NEEDLE', 'SINGLE', 'DOUBLE',
            'ROW', 'OPEN', 'SHIELDED', 'SEALED', 'INSULATED', 'SYNCHRONOUS', 'ASYNCHRONOUS', 'INDUCTION', 'THREE-PHASE'
        ];

        // Also fetch unique terms from existing database items
        try {
            $dbWordsStmt = $this->db->query("SELECT DISTINCT item_name FROM fifo_master_items LIMIT 500");
            while ($row = $dbWordsStmt->fetch(PDO::FETCH_ASSOC)) {
                $words = preg_split('/[\s,\-\/\(\)]+/', strtoupper($row['item_name']));
                foreach ($words as $w) {
                    $w = trim($w);
                    if (strlen($w) >= 3 && !is_numeric($w) && !in_array($w, $dictionary)) {
                        $dictionary[] = $w;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore if error
        }

        // 2. Spell Check Analysis word-by-word
        $tokens = preg_split('/(\s+)/', $cleanQuery, -1, PREG_SPLIT_DELIM_CAPTURE);
        $spellingSuggestions = [];
        $correctedTokens = [];
        $hasCorrections = false;

        foreach ($tokens as $token) {
            if (trim($token) === '') {
                $correctedTokens[] = $token;
                continue;
            }

            $cleanToken = preg_replace('/[^A-Za-z0-9\-]/', '', strtoupper($token));
            
            // Skip numbers, specs, part numbers, short tokens
            if (strlen($cleanToken) < 3 || is_numeric($cleanToken) || preg_match('/\d/', $cleanToken)) {
                $correctedTokens[] = $token;
                continue;
            }

            if (in_array($cleanToken, $dictionary)) {
                $correctedTokens[] = $token;
                continue;
            }

            // Find closest dictionary word using Levenshtein distance
            $bestMatch = null;
            $shortestDistance = 999;

            foreach ($dictionary as $dictWord) {
                // Only consider if lengths are relatively close
                if (abs(strlen($cleanToken) - strlen($dictWord)) > 2) continue;

                $lev = levenshtein($cleanToken, $dictWord);
                if ($lev <= 2 && $lev < $shortestDistance) {
                    $shortestDistance = $lev;
                    $bestMatch = $dictWord;
                }
            }

            if ($bestMatch && $shortestDistance > 0) {
                $spellingSuggestions[] = [
                    'original' => $token,
                    'suggested' => $bestMatch,
                    'distance' => $shortestDistance
                ];
                $correctedTokens[] = $bestMatch;
                $hasCorrections = true;
            } else {
                $correctedTokens[] = $token;
            }
        }

        $correctedQuery = implode('', $correctedTokens);

        // 3. Database Search for Related Items (Search by query words and corrected query)
        $searchTerms = array_filter(preg_split('/[\s,\-\/]+/', $cleanQuery), fn($t) => strlen(trim($t)) > 1);
        $sqlConds = ["1=1"];
        $params = [];

        if ($tier1 !== null && $tier1 > 0) {
            $sqlConds[] = "m.tier1 = :t1";
            $params[':t1'] = $tier1;
        }
        if ($tier2 !== null && $tier2 > 0) {
            $sqlConds[] = "m.tier2 = :t2";
            $params[':t2'] = $tier2;
        }
        if ($tier3 !== null && $tier3 > 0) {
            $sqlConds[] = "m.tier3 = :t3";
            $params[':t3'] = $tier3;
        }

        // Build flexible multi-term search
        $termConds = [];
        $i = 0;
        foreach ($searchTerms as $term) {
            $i++;
            $pKey1 = ":st_name_$i";
            $pKey2 = ":st_spec_$i";
            $pKey3 = ":st_fmt_$i";
            $pKey4 = ":st_raw_$i";
            $termConds[] = "(m.item_name LIKE $pKey1 OR m.specifications LIKE $pKey2 OR m.formatted_code LIKE $pKey3 OR m.raw_code LIKE $pKey4)";
            $termVal = "%" . trim($term) . "%";
            $params[$pKey1] = $termVal;
            $params[$pKey2] = $termVal;
            $params[$pKey3] = $termVal;
            $params[$pKey4] = $termVal;
        }
        if (!empty($termConds)) {
            $sqlConds[] = "(" . implode(" OR ", $termConds) . ")";
        }

        $whereClause = implode(" AND ", $sqlConds);
        $sql = "
            SELECT m.id, m.tier1, m.tier2, m.tier3, m.tier4_seq,
                   m.formatted_code, m.raw_code, m.item_name, m.specifications, m.uom, m.status,
                   l.name as station_name, l.code as station_code
            FROM fifo_master_items m
            LEFT JOIN locations l ON m.origin_station_id = l.id
            WHERE $whereClause
            ORDER BY 
              CASE WHEN LOWER(m.item_name) = LOWER(:exact_q) THEN 1
                   WHEN m.item_name LIKE :prefix_q THEN 2
                   ELSE 3 END ASC,
              m.tier1 ASC, m.tier2 ASC, m.tier3 ASC, m.tier4_seq ASC
            LIMIT :lim
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':exact_q', $cleanQuery);
        $stmt->bindValue(':prefix_q', $cleanQuery . '%');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'matches' => $matches,
            'spelling_suggestions' => $spellingSuggestions,
            'corrected_query' => $correctedQuery,
            'has_spelling_corrections' => $hasCorrections,
            'total_found' => count($matches)
        ];
    }
}
