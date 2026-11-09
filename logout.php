<?php
require_once __DIR__ . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Response;

$base = defined('BASE_PATH') ? BASE_PATH : '';

Auth::logout();
Response::redirect($base . '/login.php');
