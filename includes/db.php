<?php
/**
 * Database connection configuration
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'budgetor');
define('DB_USER', 'budgetor');
define('DB_PASS', 'budgetor_pass_2024');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get PDO database connection
 * Uses Unix socket for local MariaDB connection
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    return $pdo;
}
