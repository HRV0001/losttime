<?php
/**
 * LostTime - Database Connection & Configuration
 */

// Synchronize PHP with Local Timezone (Asia/Kolkata, UTC+05:30)
date_default_timezone_set('Asia/Kolkata');

// Global response helper
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Handle preflight OPTIONS request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    exit(0);
}

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db_name = 'losttime_db';
    $db_user = 'root';
    $db_pass = '';
    
    // Check ports (3307 is common in custom XAMPP configs, 3306 is MySQL default)
    $ports = [3307, 3306];
    $connected = false;
    $lastException = null;

    foreach ($ports as $port) {
        try {
            $dsn = "mysql:host=127.0.0.1;port={$port};dbname={$db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
            $pdo->exec("SET time_zone = '+05:30'");
            $connected = true;
            break;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    // If database does not exist yet, try to create it automatically
    if (!$connected) {
        foreach ($ports as $port) {
            try {
                $dsnWithoutDb = "mysql:host=127.0.0.1;port={$port};charset=utf8mb4";
                $tempPdo = new PDO($dsnWithoutDb, $db_user, $db_pass);
                $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // Now connect to the new DB and run schema if available
                $dsn = "mysql:host=127.0.0.1;port={$port};dbname={$db_name};charset=utf8mb4";
                $pdo = new PDO($dsn, $db_user, $db_pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                $sqlFile = __DIR__ . '/../database/losttime.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    $pdo->exec($sql);
                }
                $connected = true;
                break;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }
    }

    if (!$connected) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')
        ], 500);
    }

    return $pdo;
}
