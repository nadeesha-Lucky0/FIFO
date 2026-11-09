<?php
namespace App\Config;

use App\Core\StationDatabase;
use App\Models\Location;
use PDO;
use PDOException;
use Exception;

class Database {
    private static ?PDO $instance = null;
    private static array $stationInstances = [];

    /**
     * Get Central Super Admin Database Connection ('fifo')
     */
    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            try {
                self::$instance->query("SELECT 1");
            } catch (\Throwable $e) {
                self::$instance = null;
            }
        }

        if (self::$instance === null) {
            try {
                if (DB_DRIVER === 'sqlite') {
                    $dbFile = SQLITE_FILE;
                    $dbDir = dirname($dbFile);
                    if (!is_dir($dbDir)) {
                        mkdir($dbDir, 0777, true);
                    }

                    $isNew = !file_exists($dbFile) || filesize($dbFile) === 0;
                    
                    self::$instance = new PDO("sqlite:" . $dbFile, null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);

                    self::$instance->exec("PRAGMA foreign_keys = ON;");

                    if ($isNew) {
                        self::migrateSqlite(self::$instance);
                    }
                } else {
                    $dsn = sprintf(
                        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                        DB_HOST,
                        DB_PORT,
                        DB_NAME,
                        DB_CHARSET
                    );

                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                }
            } catch (PDOException $e) {
                error_log("Main Database Connection Error: " . $e->getMessage());
                die("Main database connection failed. Please check application configuration.");
            }
        }

        return self::$instance;
    }

    /**
     * Get Isolated Database Connection for a specific Power Station
     *
     * @param int|string|null $stationIdentifier (Location ID, Location Code, or DB name)
     * @return PDO
     */
    public static function getStationConnection(int|string|null $stationIdentifier = null): PDO {
        $dbName = self::resolveStationDbName($stationIdentifier);

        // If SQLite, return the main instance for local dev compatibility
        if (DB_DRIVER === 'sqlite') {
            return self::getConnection();
        }

        // Return cached pooled connection if already open
        if (isset(self::$stationInstances[$dbName])) {
            return self::$stationInstances[$dbName];
        }

        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                DB_HOST,
                DB_PORT,
                $dbName,
                DB_CHARSET
            );

            $stationPdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Ensure station schema tables exist
            $stationPdo->exec(StationDatabase::getStationSchemaSql());

            // Ensure initial unposted documents in station database
            try {
                $chkDocs = $stationPdo->query("SELECT COUNT(*) FROM unposted_documents")->fetchColumn();
                if ((int)$chkDocs === 0) {
                    $mainPdo = self::getConnection();
                    $initialDocs = $mainPdo->query("SELECT * FROM unposted_documents")->fetchAll() ?: [];
                    if (!empty($initialDocs)) {
                        $ins = $stationPdo->prepare("
                            INSERT INTO unposted_documents 
                            (doc_no, doc_type, doc_profile, doc_profile_code, status, receipt_from, source_dept, receipt_details, delivery_date, acc_date, due_date, warehouse, vat, transport, nbt, other_cost, invoice_number, order_reference, other_ref_1, other_ref_2, remarks, invoice_total, is_single_material, discount_for_all, is_percentage, location_id, created_by)
                            VALUES
                            (:doc_no, :doc_type, :doc_profile, :doc_profile_code, :status, :receipt_from, :source_dept, :receipt_details, :delivery_date, :acc_date, :due_date, :warehouse, :vat, :transport, :nbt, :other_cost, :invoice_number, :order_reference, :other_ref_1, :other_ref_2, :remarks, :invoice_total, :is_single_material, :discount_for_all, :is_percentage, :location_id, :created_by)
                        ");
                        foreach ($initialDocs as $d) {
                            $ins->execute([
                                ':doc_no' => $d['doc_no'],
                                ':doc_type' => $d['doc_type'],
                                ':doc_profile' => $d['doc_profile'],
                                ':doc_profile_code' => $d['doc_profile_code'],
                                ':status' => $d['status'],
                                ':receipt_from' => $d['receipt_from'],
                                ':source_dept' => $d['source_dept'],
                                ':receipt_details' => $d['receipt_details'],
                                ':delivery_date' => $d['delivery_date'],
                                ':acc_date' => $d['acc_date'],
                                ':due_date' => $d['due_date'],
                                ':warehouse' => $d['warehouse'],
                                ':vat' => $d['vat'],
                                ':transport' => $d['transport'],
                                ':nbt' => $d['nbt'],
                                ':other_cost' => $d['other_cost'],
                                ':invoice_number' => $d['invoice_number'],
                                ':order_reference' => $d['order_reference'],
                                ':other_ref_1' => $d['other_ref_1'],
                                ':other_ref_2' => $d['other_ref_2'],
                                ':remarks' => $d['remarks'],
                                ':invoice_total' => $d['invoice_total'],
                                ':is_single_material' => $d['is_single_material'],
                                ':discount_for_all' => $d['discount_for_all'],
                                ':is_percentage' => $d['is_percentage'],
                                ':location_id' => $d['location_id'] ?? 1,
                                ':created_by' => $d['created_by'] ?? 1
                            ]);
                        }
                    }
                }
            } catch (\Exception $ex) {
                // Silently ignore seed errors
            }

            self::$stationInstances[$dbName] = $stationPdo;
            return $stationPdo;
        } catch (PDOException $e) {
            // If the database does not exist, attempt automatic on-demand creation
            if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
                try {
                    $code = is_numeric($stationIdentifier) ? (string)$stationIdentifier : 'STATION';
                    StationDatabase::createStationDb($code, $dbName);
                    
                    $dsn = sprintf(
                        "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                        DB_HOST,
                        DB_PORT,
                        $dbName,
                        DB_CHARSET
                    );

                    $stationPdo = new PDO($dsn, DB_USER, DB_PASS, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);

                    self::$stationInstances[$dbName] = $stationPdo;
                    return $stationPdo;
                } catch (Exception $inner) {
                    error_log("Failed to auto-provision station database [{$dbName}]: " . $inner->getMessage());
                }
            }

            error_log("Station Database Connection Error [{$dbName}]: " . $e->getMessage());
            // Fall back to main connection to prevent hard fatal crash while maintaining system availability
            return self::getConnection();
        }
    }

    /**
     * Resolve the database name for a station
     */
    private static function resolveStationDbName(int|string|null $identifier): string {
        if (!empty($identifier)) {
            if (is_string($identifier) && str_starts_with($identifier, 'fifo_')) {
                return $identifier;
            }

            try {
                $mainDb = self::getConnection();
                if (is_numeric($identifier)) {
                    $stmt = $mainDb->prepare("SELECT code, name, database_name FROM locations WHERE id = :id LIMIT 1");
                    $stmt->execute([':id' => (int)$identifier]);
                } else {
                    $stmt = $mainDb->prepare("SELECT code, name, database_name FROM locations WHERE code = :code LIMIT 1");
                    $stmt->execute([':code' => trim($identifier)]);
                }
                $loc = $stmt->fetch();
                if ($loc) {
                    if (!empty($loc['database_name'])) {
                        return $loc['database_name'];
                    }
                    return StationDatabase::getDbName($loc['code'], $loc['name']);
                }
            } catch (Exception $e) {
                // Ignore lookup failure
            }

            if (is_numeric($identifier)) {
                return "fifo_station_" . $identifier;
            }
        }

        // Check active session context
        if (isset($_SESSION['active_station_db']) && !empty($_SESSION['active_station_db'])) {
            return $_SESSION['active_station_db'];
        }

        if (isset($_SESSION['user']['location_id']) && (int)$_SESSION['user']['location_id'] > 0) {
            return self::resolveStationDbName((int)$_SESSION['user']['location_id']);
        }

        // Default to first active station DB or fallback
        return 'fifo_831_victoria';
    }

    private static function migrateSqlite(PDO $pdo): void {
        $schemaFile = DATABASE_PATH . DIRECTORY_SEPARATOR . 'schema_sqlite.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $pdo->exec($sql);
        }
    }
}
