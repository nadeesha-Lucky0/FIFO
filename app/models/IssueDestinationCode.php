<?php
namespace App\Models;

use App\Config\Database;
use PDO;
use PDOException;

class IssueDestinationCode
{
    private PDO $db;
    private static bool $schemaEnsured = false;

    public function __construct()
    {
        $this->db = Database::getConnection();
        if (!self::$schemaEnsured) {
            $this->ensureSchema();
            self::$schemaEnsured = true;
        }
    }

    /**
     * Ensure issue_destination_codes table exists and is seeded with all standard CEB codes.
     */
    private function ensureSchema(): void
    {
        $isMysql = (defined('DB_DRIVER') && DB_DRIVER === 'mysql');
        $pkType = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS issue_destination_codes (
                id $pkType,
                category VARCHAR(60) NOT NULL,
                code VARCHAR(60) DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                display_label VARCHAR(255) NOT NULL,
                sort_order INT DEFAULT 0,
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Check if table is empty, if so, seed standard categories and codes
        try {
            $cnt = (int) $this->db->query("SELECT COUNT(*) FROM issue_destination_codes")->fetchColumn();
            if ($cnt === 0) {
                $this->seedInitialData();
            }
        } catch (\Throwable $e) {
            error_log("IssueDestinationCode ensureSchema notice: " . $e->getMessage());
        }
    }

    /**
     * Seed initial categories and standard CEB codes
     */
    private function seedInitialData(): void
    {
        // 1. Section (Cost Codes) - 180+ Standard Codes
        $sectionCodes = [
            "832.00-A3960 -CAPITAL EXPENDITURE",
            "A1001 -LAND",
            "A1003 -BUILDINGS",
            "A1004 -PROVISION FOR DEPRECIATION OF BUILDINGS",
            "A1113 -CONCRETE DAMS TUNNELS/ SPILLWAYS",
            "A1114 -PROVISION FOR DEPRECIATION OF CONCRETE DAMS TUNNELS/SPILLWAY",
            "A1123 -STEEL PIPES & PENSTOCKS",
            "A1124 -PROVISION FOR DEPRECIATION OF STEEL PIPES & PENSTOCKS",
            "A1203 -HYDRO POWER STATIONS",
            "A1204 -PROVISION FOR DEPRECIATION OF HYDRO POWER STATION",
            "A1303 -SWITCHYARDS",
            "A1304 -PROVISION FOR DEPRECIATION OF SWITCHYARDS",
            "A1703 -VALUE OF MOTOR VEHICLE WHICH IN USE",
            "A1704 -PROVISION FOR DEPRECIATION OF VEHICLES",
            "A1901 -OFFICE EQUIPMENT(COMPLETED JOBS)",
            "A1903 -OFFICE EQUIPMENT",
            "A1904 -PROVISION FOR DEPRECIATION OF OFFICE EQUIPMENT",
            "A1913 -COMPUTERS AND IT RELATED EQUIPMENT ACCOUNT",
            "A1923 -OTHER SUNDRY ASSETS",
            "A1924 -PROVISION FOR DEPRECIATION OF OTHER SUNDRY ASSETS",
            "A1933 -FURNITURE & FITTINGS",
            "A1934 -PROVISION FOR DEPRECIATION OF FURNITURE & FITTINGS",
            "A1940 -MACHINARY & TOOLS- WORK IN PROGRESS",
            "A1941 -MACHINARY & TOOLS",
            "A1943 -MACHINERY & TOOLS",
            "A1944 -PROVISION FOR DEPRECIATION OF MACHINERY & TOOLS",
            "A2520 -PREPAID STAFF BENEFIT COST ACCOUNT",
            "A3100 -PETTY CASH & IMPREST",
            "A3300 -BANK",
            "A3305 -BANK",
            "A3510 -TRANSPORT LOAN",
            "A3520 -FESTIVAL ADVANCE",
            "A3530 -DISTRESS LOAN",
            "A3545 -SPECIAL SALARY ADVANCES",
            "A3550 -SALARY ADVANCE",
            "A3555 -SPECIAL SALARY ADVANCE",
            "A3560 -SERVICE CONNECTIONS LOAN",
            "A3565 -ADVANCE - PURCHASE CFL LAMPS",
            "A3570 -SCHOOL BOOK LONAS",
            "A3580 -SPECIAL FESTIVAL ADVANCE",
            "A3585 -EPF ADVANCE ACCOUNT",
            "A3590 -TRAVELLING ADVANCE",
            "A3595 -STAFF - OTHER DEBTORS",
            "A3597 -STAFF LOAN FAIR VALUE ADJUSTMENT ACCOUNT",
            "A3700 -RECEIVABLES FROM CONTRACTORS",
            "A3710 -MOBILIZATION ADVANCE",
            "A3729 -ADVANCE AGAINST SALARY INCREASE",
            "A3750 -PRE PAYMENTS",
            "A3794 -VALUE ADDED TAX",
            "A3795 -VAT",
            "A3800 -OTHERS DEBTORS",
            "A3820 -SMALL SCALL INDUSTRIES",
            "A3850 -D.C.B. FUND",
            "A4000 -INVENTORIES IN STORES",
            "A4002 -STOCK ADJUSTMENT",
            "A4600 -LOCAL PURCHASE CONTROL",
            "E2100 -MANAGEMENT STAFF SALARIES",
            "E2110 -MANAGEMENT STAFF ALLOWANCES",
            "E2200 -OTHER STAFF SALARIES",
            "E2205 -SALARY ARREAS & ALLOWANCES",
            "E2300 -OTHER STAFF OVERTIME",
            "E2310 -OTHER STAFF ALLOWANCE",
            "E2320 -DIRECT LABOUR AT NORMAL RATE",
            "E2324 -DIRECT LABOUR ALLOWANCES ACCOUNT",
            "E2330 -DIRECT LABOUR OVERTIME",
            "E2350 -HOLIDAY PAY - MANAGEMENT STAFF",
            "E2355 -HOLIDAY PAY - OTHER STAFF",
            "E2356 -HOLIDAY PAY-DIRECT LABOUR",
            "E2373 -ETF ARREARS",
            "E2500 -BONUS",
            "E2520 -GRATUITY PAYMENT",
            "E2530 -NON SICK LEAVE INCENTIVE",
            "E2540 -ALLOWANCES TO TRAINEES",
            "E2550 -COMPENSATION TO C.E.B. EMPLOYEES",
            "E2600 -STAFF TRAINING - CEB",
            "E2602 -LOCAL TRAINING - CEB",
            "E2603 -FOREIGN TRAINING - CEB",
            "E2610 -LIBRARY FACILITIES",
            "E2620 -FEES TO PROFESSIONAL INSTITUTIONS",
            "E2630 -STAFF WELFARE",
            "E2631 -STAFF WELFARE - MEDICAL EXPENSES",
            "E2632 -STAFF WELFARE - TRAVELING & CONCESSION",
            "E2635 -EXE. MOBILE BILL ALLOWANCE",
            "E2640 -MEDICAL EXPENSES - INDOOR",
            "E2641 -MEDICAL EXPENSES - OUTDOOR",
            "E2650 -UNIFORMS AND PROTECTIVE CLOTHING",
            "E2660 -REIMBURSEMENT OF LOAN INTEREST",
            "E2670 -P.A.Y.E. TAX",
            "E2680 -CEB PENSION FUND",
            "E2700 -CEB EMPLOYEES TRUST FUND",
            "E2710 -CEB PROVIDENT FUND",
            "E2720 -SALARY ARREAS",
            "E2730 -BONUS ARREAS",
            "E2740 -INCENTIVE ARREAS ON UNAVAILED MEDICAL LEAVE",
            "E2750 -OVERTIME ARREAS",
            "E2760 -HOLIDAY PAY ARREAS",
            "E2771 -PROVIDENT FUND ARREARS",
            "E2772 -PENSION FUND ARREARS",
            "E2780 -INTERIM SALARY",
            "E3200 -COMPONENTS/ROUTINE MAINTENANCE",
            "E3202 -COMPONENTS/ROUTING MAINTANCE ON DISTRIBUTION",
            "E3210 -COMPONENTS/SPECIAL MAINTENANCE",
            "E3213 -COMPONENT MAINTENANCE ON REHABILITATION- DUE TO DISASTERS",
            "E3220 -COMPORNANTS/CONSTRUCTION",
            "E3230 -CONSUMABLES",
            "E3300 -LOOSE TOOLS",
            "E3410 -STORES DISCREPANCIES",
            "E3420 -DAMAGED STOCKS",
            "E3450 -ANNU. PROVI. FOR DAMAGED STOCKS & OBSOLETE STOCKS",
            "E4100 -HOUSING RENT AND RATES",
            "E4110 -BUILDING MAINTENANCE",
            "E4120 -CIRCUIT BANGLOW MAINTENANCE",
            "E4200 -FURNITURE AND EQUIPMENT",
            "E4300 -ELECTRICITY CONSUMPTION",
            "E4310 -LP GAS FOR EMPLOYEE QUARTERS ACCOUNT",
            "E4400 -WATER SUPPLY CHARGES",
            "E5100 -TRAVELLING AND SUBSISTANCE(LOCAL)",
            "E5200 -VEHICLE MAINTENANCE",
            "E5210 -VEHICLE FUEL, OIL AND LICENSES",
            "E5220 -VEHICLE HIRE CHARGES",
            "E5300 -OFFICE SUPPLIES",
            "E5310 -POSTAGE",
            "E5320 -TELECOMMUNICATION",
            "E5322 -EXPENSES ON DATA COMMUNICATION LINKS",
            "E5323 -EXPENSES ON SOFTWARE LICENSES",
            "E5324 -EXPENSES ON MAINTENANCE OF IT RELATED HARDWARE",
            "E5325 -TELECOMMUNICATIONS CHARGES FOR IT RELATED SERVICES",
            "E5330 -STAMP DUTY",
            "E6000 -DEPRECIATION",
            "E7100 -HIRE AND LEASE CHARGES",
            "E7200 -CONTRACTOR PAYMENTS FOR NON CAPITAL JOBS",
            "E7210 -PAYMENT TO SECURITY STAFF ON COTRACT",
            "E7211 -PAYMENT TO MAN POWER AGENCIES",
            "E7410 -AUDIT FEES",
            "E7420 -CONSULTANCY FEES",
            "E7450 -TENDER BOARD MEMBERS & TEC MEMBERS",
            "E7500 -PUBLIC RELATION & ADVERTISING",
            "E7501 -ENERGY SAVING /CONSERVATION",
            "E7510 -ENTERTAINMENT",
            "E7560 -CLEANING SERVICE & PEST CONTROL SERVICES",
            "E7600 -INSURANCE PREMIUMS",
            "E7700 -LOSS ON SCRAP - FIXED ASSETS",
            "E7710 -LOSSES ON SALE - FIXED ASSETS",
            "E7800 -MISCELLANEOUS",
            "E7820 -REPAIRS OF PLANTS MACHINERY & EQUIPMENT",
            "E7851 -PROVISION FOR BAD DEBTS(OTHER THAN ELECRICITY)",
            "E7860 -EXPENSES INCURRED DUE TO COVID -19",
            "E8200 -BANK CHARGES",
            "E8202 -DEBITS TAX",
            "E8400 -INTEREST PAID ON LEASE CONTRACTS",
            "E9100 -DEBIT TAX",
            "LS100 -RETENTION",
            "LS105 -COLLECTION CONTROL",
            "LS150 -COLLECTION CONTROL ACCT",
            "LS200 -OTHERS",
            "LS210 -LOCAL SUPPLIERS",
            "LS224 -REFUND OF VAT COMPONENET",
            "LS226 -1/3 VALUE ADDED TAX",
            "LS227 -WITH HOLDING TAX PAYABLE",
            "LS228 -NATION BUILDING TAX PAYABLE",
            "LS229 -STAMP DUTY PAYABLE ACCOUNT",
            "LS320 -SUPPLIERS CONTROL ACCOUNT",
            "LS500 -WAGES CONTROL",
            "LS521 -SECURITY DEPOSITS OF STAFF",
            "LS640 -TENDER DEPOSITS - REFUNDABLE",
            "LS660 -OTHER MISCELLANEOUS DEPOSITS",
            "LS760 -PRO. FOR DAMAGED/OBSOLETE STOCK",
            "LS776 -PROVISION FOR BAD & DOUBTFUL ON OTHER DEBTORS",
            "LS800 -CASH IN TRANSIT ACCOUNT",
            "LS830 -WITHHOLDING TAX",
            "LS900 -UNPAID SALARIES & WAGES",
            "LS920 -ACCRUED",
            "L6000 -INVENTORY/ STOCK ADJUSTMENT",
            "L6010 -STOCK SHORTAGE",
            "L6016 -FAIR VALUE ADJUSTMENT ACCOUNT",
            "L6020 -STOCK SURPLUS",
            "L8123 -REVALUATION OF ASSETS",
            "L8213 -REVALUATION RESERVE SURPLUSES ON ASSETS DISPOSED",
            "L9000 -CURRENT ACCOUNTS",
            "L9001 -CONTROL ACCOUNT (PAYMENTS-PAY SLIPS)",
            "L9100 -A/C UNIT - COST CENTRE",
            "L9200 -A/C UNIT - A/C UNIT",
            "L9300 -CURRENT A/C - OTHER GROUP CC",
            "L9400 -TRANSIT ACCOUNT",
            "R1100 -ENERGY SALES (GENERATION TO TRANSMISSION)",
            "R1300 -MISCELLANEOUS INCOME",
            "R1350 -TENDER FEE/NON REFUNDABLE DEPOSITS/ FORFEITURE OF GUARANTEES",
            "R1400 -INTEREST ON INVESTMENT",
            "R1420 -INTEREST ON DISTRESS LOAN ACCOUNT",
            "R1510 -RECOVERIES ON HOUSE RENT",
            "R1520 -RECOVERIES ON TELEPHONE",
            "R1530 -RECOVERIES ON USE OF MOTOR VEHICLE",
            "R1540 -RECOVERIES ON CIRCUIT BUNGALOW",
            "R1610 -SALE OF FIXED ASSETS",
            "R1620 -SALE OF SCRAP"
        ];

        $stmt = $this->db->prepare("
            INSERT INTO issue_destination_codes (category, code, title, display_label, sort_order, status)
            VALUES (:category, :code, :title, :display_label, :sort_order, 'active')
        ");

        $order = 0;
        foreach ($sectionCodes as $line) {
            $parts = explode('-', $line, 2);
            $code = trim($parts[0] ?? '');
            $title = trim($parts[1] ?? $code);
            $stmt->execute([
                ':category' => 'Section',
                ':code' => $code,
                ':title' => $title,
                ':display_label' => $line,
                ':sort_order' => ++$order
            ]);
        }
    }

    /**
     * Get all codes, filterable by category and search keyword
     */
    public function getAll(?string $category = null, string $search = '', string $status = 'all'): array
    {
        $sql = "SELECT * FROM issue_destination_codes WHERE 1=1";
        $params = [];

        if ($category && $category !== 'all') {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        if ($status !== 'all') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $sql .= " AND (code LIKE :s1 OR title LIKE :s2 OR display_label LIKE :s3)";
            $params[':s1'] = '%' . $search . '%';
            $params[':s2'] = '%' . $search . '%';
            $params[':s3'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY category ASC, id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Natural alphanumeric sort by code (e.g. 001, 002, 100, 101, 3000, A1001...)
        usort($rows, function ($a, $b) {
            if ($a['category'] !== $b['category']) {
                return strcmp($a['category'], $b['category']);
            }
            $codeA = trim((string) ($a['code'] ?? ''));
            $codeB = trim((string) ($b['code'] ?? ''));
            if ($codeA !== '' && $codeB !== '') {
                $cmp = strnatcasecmp($codeA, $codeB);
                if ($cmp !== 0)
                    return $cmp;
            } elseif ($codeA !== '') {
                return -1;
            } elseif ($codeB !== '') {
                return 1;
            }
            return strnatcasecmp((string) $a['display_label'], (string) $b['display_label']);
        });

        return $rows;
    }

    /**
     * Get active options grouped by category for dropdown rendering
     */
    public function getAllGrouped(): array
    {
        $stmt = $this->db->query("
            SELECT category, display_label, code 
            FROM issue_destination_codes 
            WHERE status = 'active' 
            ORDER BY category ASC, id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Natural alphanumeric sort by code for dropdown options
        usort($rows, function ($a, $b) {
            if ($a['category'] !== $b['category']) {
                return strcmp($a['category'], $b['category']);
            }
            $codeA = trim((string) ($a['code'] ?? ''));
            $codeB = trim((string) ($b['code'] ?? ''));
            if ($codeA !== '' && $codeB !== '') {
                $cmp = strnatcasecmp($codeA, $codeB);
                if ($cmp !== 0)
                    return $cmp;
            } elseif ($codeA !== '') {
                return -1;
            } elseif ($codeB !== '') {
                return 1;
            }
            return strnatcasecmp((string) $a['display_label'], (string) $b['display_label']);
        });

        $grouped = [
            'Project' => [],
            'Cost Center' => [],
            'Warehouse' => [],
            'My Project' => [],
            'Section' => [],
            'Genaration as Expenditure' => []
        ];

        foreach ($rows as $r) {
            $cat = $r['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $r['display_label'];
        }

        return $grouped;
    }

    /**
     * Find single record by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM issue_destination_codes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Check if a code already exists (case-insensitive, trimmed)
     */
    public function findByCode(string $code, ?string $category = null, ?int $excludeId = null): ?array
    {
        $cleanCode = trim($code);
        if ($cleanCode === '') {
            return null;
        }

        $sql = "SELECT * FROM issue_destination_codes WHERE LOWER(TRIM(code)) = LOWER(:code)";
        $params = [':code' => $cleanCode];

        if ($category !== null && $category !== '') {
            $sql .= " AND category = :category";
            $params[':category'] = $category;
        }

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Create new code / option
     */
    public function create(array $data): int
    {
        $category = trim($data['category'] ?? 'Section');
        $code = trim($data['code'] ?? '') ?: null;
        $title = trim($data['title'] ?? '');
        $rawStatus = $data['status'] ?? 'active';
        $status = in_array($rawStatus, ['active', 'inactive']) ? $rawStatus : 'active';
        $sortOrder = (int) ($data['sort_order'] ?? 0);

        // Build full display label
        $displayLabel = trim($data['display_label'] ?? '');
        if (empty($displayLabel)) {
            if ($code && $title) {
                $displayLabel = $code . ' -' . $title;
            } else {
                $displayLabel = $title ?: ($code ?: 'New Entry');
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO issue_destination_codes (category, code, title, display_label, sort_order, status, created_at, updated_at)
            VALUES (:category, :code, :title, :display_label, :sort_order, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':category' => $category,
            ':code' => $code,
            ':title' => $title,
            ':display_label' => $displayLabel,
            ':sort_order' => $sortOrder,
            ':status' => $status
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update existing code / option
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $category = isset($data['category']) ? trim((string) $data['category']) : $existing['category'];
        $code = array_key_exists('code', $data) ? (trim((string) $data['code']) ?: null) : $existing['code'];
        $title = isset($data['title']) ? trim((string) $data['title']) : $existing['title'];
        $status = isset($data['status']) && in_array($data['status'], ['active', 'inactive']) ? $data['status'] : $existing['status'];
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : (int) $existing['sort_order'];

        $displayLabel = isset($data['display_label']) ? trim((string) $data['display_label']) : '';
        if (empty($displayLabel)) {
            if ($code && $title) {
                $displayLabel = $code . ' -' . $title;
            } else {
                $displayLabel = $title ?: ($code ?: $existing['display_label']);
            }
        }

        $sql = "UPDATE issue_destination_codes SET 
                    category = :category,
                    code = :code,
                    title = :title,
                    display_label = :display_label,
                    status = :status,
                    sort_order = :sort_order,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':category' => $category,
            ':code' => $code,
            ':title' => $title,
            ':display_label' => $displayLabel,
            ':status' => $status,
            ':sort_order' => $sortOrder
        ]);
    }

    /**
     * Delete code / option by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM issue_destination_codes WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Category statistics
     */
    public function getCategoryCounts(): array
    {
        $stmt = $this->db->query("
            SELECT category, COUNT(*) as cnt 
            FROM issue_destination_codes 
            WHERE status = 'active'
            GROUP BY category
        ");
        $counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $counts[$row['category']] = (int) $row['cnt'];
        }
        return $counts;
    }
}
