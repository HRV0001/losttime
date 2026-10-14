<?php
/**
 * LostTime - Database Connection & Configuration
 * Supports both local XAMPP and Cloud Databases (Railway, PlanetScale, Supabase, etc.)
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

/**
 * Automatically creates tables and seeds starter data if the target database is fresh
 */
function ensureDatabaseTables($pdo) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if ($check) {
            return; // Already initialized
        }

        $sqlFile = __DIR__ . '/../database/losttime.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            // Strip database-level commands to ensure seamless deployment in cloud databases
            $sql = preg_replace('/CREATE\s+DATABASE\s+[^;]+;/i', '', $sql);
            $sql = preg_replace('/USE\s+[^;]+;/i', '', $sql);
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        error_log("LostTime table initialization error: " . $e->getMessage());
    }
}

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $connected = false;
    $lastException = null;

    // 1. Check for single URL connection string (Railway standard: MYSQL_URL or DATABASE_URL)
    $db_url = getenv('MYSQL_URL') ?: (getenv('DATABASE_URL') ?: ($_ENV['MYSQL_URL'] ?? ($_ENV['DATABASE_URL'] ?? null)));
    
    $host = null;
    $port = null;
    $user = null;
    $pass = null;
    $dbname = null;

    if (!empty($db_url)) {
        $parsed = parse_url($db_url);
        if ($parsed) {
            $host = $parsed['host'] ?? null;
            $port = $parsed['port'] ?? 3306;
            $user = $parsed['user'] ?? 'root';
            $pass = $parsed['pass'] ?? '';
            $dbname = isset($parsed['path']) ? ltrim($parsed['path'], '/') : null;
        }
    }

    // 2. Check for individual environment variables (Railway default provided variables)
    if (!$host) {
        $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: ($_ENV['MYSQLHOST'] ?? ($_ENV['DB_HOST'] ?? null)));
        $port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: ($_ENV['MYSQLPORT'] ?? ($_ENV['DB_PORT'] ?? null)));
        $user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: ($_ENV['MYSQLUSER'] ?? ($_ENV['DB_USER'] ?? null)));
        $pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASSWORD') ?: (getenv('DB_PASS') ?: ($_ENV['MYSQLPASSWORD'] ?? ($_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? null)))));
        $dbname = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: ($_ENV['MYSQLDATABASE'] ?? ($_ENV['DB_NAME'] ?? null)));
    }

    // 3. Connect to cloud MySQL if environment configuration is detected
    if ($host) {
        $port = $port ?: 3306;
        $user = $user ?: 'root';
        $pass = $pass ?? '';
        $dbname = $dbname ?: 'railway';

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->exec("SET time_zone = '+05:30'");
            $connected = true;

            // Automatically check & seed tables on Railway/cloud DB if fresh
            ensureDatabaseTables($pdo);
        } catch (PDOException $e) {
            $lastException = $e;
        }
    } else {
        // 4. Local Development Fallback (XAMPP ports 3307 & 3306)
        $localDbName = 'losttime_db';
        $localUser = 'root';
        $localPass = '';
        $ports = [3307, 3306];

        foreach ($ports as $p) {
            try {
                $dsn = "mysql:host=127.0.0.1;port={$p};dbname={$localDbName};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                $pdo = new PDO($dsn, $localUser, $localPass, $options);
                $pdo->exec("SET time_zone = '+05:30'");
                $connected = true;
                break;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        // If local database does not exist yet, try to create it
        if (!$connected) {
            foreach ($ports as $p) {
                try {
                    $dsnWithoutDb = "mysql:host=127.0.0.1;port={$p};charset=utf8mb4";
                    $tempPdo = new PDO($dsnWithoutDb, $localUser, $localPass);
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$localDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                    $dsn = "mysql:host=127.0.0.1;port={$p};dbname={$localDbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $localUser, $localPass, [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]);
                    ensureDatabaseTables($pdo);
                    $connected = true;
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;
                }
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
