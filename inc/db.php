<?php
// DigitalMax LMS - Database connection (PDO singleton)
require_once __DIR__ . '/../config.php';

// Global safety net: koi unexpected error aaye to technical details user ko NA dikhen
set_exception_handler(function ($ex) {
    error_log('[DigitalMax LMS] ' . get_class($ex) . ': ' . $ex->getMessage());
    if (!headers_sent()) http_response_code(500);
    $is_cli = php_sapi_name() === 'cli';
    if ($is_cli) { fwrite(STDERR, "Internal error. Check logs.\n"); exit(1); }
    $msg = '<div style="font-family:sans-serif;text-align:center;padding:24px;color:#444">'
         . '<h2>Kuch ghalat ho gaya</h2><p>Thodi der baad dobara try karein. Masla barqarar rahe to admin se rabta karein.</p>'
         . '<p><a href="index.php">← Home</a></p></div>';
    if (headers_sent()) {
        // Page aadha render ho chuka (beech me error): nested <body> kabhi na bhejein,
        // warna browser uska style asal body par merge kar deta hai
        die($msg);
    }
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head>'
      . '<body style="font-family:sans-serif;text-align:center;padding:60px;color:#444">' . $msg . '</body></html>');
});

function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
