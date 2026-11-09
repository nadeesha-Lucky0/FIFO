<?php
require_once dirname(__DIR__) . '/app/config/config.php';
$base = defined('BASE_PATH') ? BASE_PATH : '';
header("Location: " . $base . "/admin/index.php#tab-profile");
exit;
