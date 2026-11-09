<?php
namespace App\Core;

class Request {
    /**
     * Get a parameter from $_GET
     */
    public static function get(string $key, $default = null) {
        if (!isset($_GET[$key])) {
            return $default;
        }
        $val = $_GET[$key];
        return is_string($val) ? trim($val) : $val;
    }

    /**
     * Get a parameter from $_POST
     */
    public static function post(string $key, $default = null) {
        if (!isset($_POST[$key])) {
            return $default;
        }
        $val = $_POST[$key];
        return is_string($val) ? trim($val) : $val;
    }

    /**
     * Get a parameter from $_REQUEST, $_POST, $_GET, or JSON payload
     */
    public static function input(string $key, $default = null) {
        if (isset($_POST[$key])) {
            $val = $_POST[$key];
            return is_string($val) ? trim($val) : $val;
        }
        if (isset($_GET[$key])) {
            $val = $_GET[$key];
            return is_string($val) ? trim($val) : $val;
        }
        $json = self::json();
        if (isset($json[$key])) {
            return $json[$key];
        }
        return $default;
    }

    /**
     * Get all input data (combining GET, POST and parsed JSON)
     */
    public static function all(): array {
        $json = self::json();
        return array_merge($_GET, $_POST, $json);
    }

    /**
     * Get parsed JSON input body if content-type is application/json
     */
    public static function json(): array {
        static $cachedJson = null;
        if ($cachedJson !== null) {
            return $cachedJson;
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true);
            $cachedJson = is_array($data) ? $data : [];
        } else {
            $cachedJson = [];
        }
        return $cachedJson;
    }

    /**
     * Check if current request is POST
     */
    public static function isPost(): bool {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /**
     * Check if current request is GET
     */
    public static function isGet(): bool {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }

    /**
     * Check if current request is AJAX / Fetch
     */
    public static function isAjax(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }

    /**
     * Get Client IP
     */
    public static function ip(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
