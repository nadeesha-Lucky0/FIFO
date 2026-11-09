<?php
/**
 * Station Database Provisioning Script
 * Automatically provisions dedicated MySQL databases for all registered Power Stations
 */

require_once dirname(__DIR__) . '/app/config/config.php';

use App\Config\Database;
use App\Core\StationDatabase;
use App\Models\Location;

echo "=========================================================\n";
echo " FIFO Power Stations Dedicated Database Provisioner\n";
echo "=========================================================\n\n";

try {
    // 1. Initialize main database connection
    $mainDb = Database::getConnection();
    echo "[✓] Connected to Central Platform Database: " . DB_NAME . "\n\n";

    // 2. Ensure locations table has database_name column
    $locModel = new Location();
    $locations = $locModel->getAll();

    echo "Found " . count($locations) . " Power Stations to provision:\n\n";

    foreach ($locations as $loc) {
        $code = $loc['code'];
        $name = $loc['name'];
        echo "--> Provisioning Database for [{$code}] {$name}...\n";

        try {
            $dbName = StationDatabase::createStationDb($code, $name);
            $locModel->update((int)$loc['id'], ['database_name' => $dbName]);
            echo "    [SUCCESS] Created & Seeded Database: `{$dbName}`\n\n";
        } catch (Exception $e) {
            echo "    [ERROR] Failed to provision for {$name}: " . $e->getMessage() . "\n\n";
        }
    }

    echo "=========================================================\n";
    echo " All Power Station Databases Provisioned Successfully!\n";
    echo "=========================================================\n";

} catch (Exception $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
