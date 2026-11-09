<?php
require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/Database.php';
$db = \App\Core\Database::getInstance()->getConnection();
$cols = $db->query("DESCRIBE code_requests")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
unlink(__FILE__);
