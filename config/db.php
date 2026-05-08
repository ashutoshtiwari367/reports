<?php
// Check if running on localhost
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', '::1'])) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'emi_tracker');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // LIVE SERVER CREDENTIALS (HOSTINGER)
    // Please update these with the database details you created in Hostinger
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u447123054_reports');
    define('DB_USER', 'u447123054_reports');
    define('DB_PASS', 'u447123054_Reports');
}

define('DB_CHARSET', 'utf8mb4');
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

$pdo = getPDO();
