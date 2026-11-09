<?php
require_once dirname(__DIR__) . '/app/config/config.php';

use App\Core\Auth;
use App\Core\Response;

Auth::logout();
Response::redirect('/login.php');
