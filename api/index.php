<?php
/**
 * LostTime - Vercel Serverless Function & Gateway Router
 * Handles routing of root endpoints and page views on Vercel
 */

// Parse the requested URL path
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = trim($requestUri, '/');

// Strip trailing .php if present
$cleanPath = strtolower(preg_replace('/\.php$/i', '', $path));

// Ensure working directory is project root so relative paths work seamlessly
chdir(__DIR__ . '/..');

switch ($cleanPath) {
    case '':
    case 'index':
        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/../index.php';
        break;

    case 'login':
        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/../login.php';
        break;

    case 'logout':
        require __DIR__ . '/../logout.php';
        break;

    case 'reports':
        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/../reports.php';
        break;

    default:
        // If an api route was mapped here, invoke it directly
        if (strpos($cleanPath, 'api/') === 0) {
            $apiScript = __DIR__ . '/' . substr($cleanPath, 4) . '.php';
            if (file_exists($apiScript)) {
                require $apiScript;
                break;
            }
        }

        // Check if matching root script exists
        $rootScript = __DIR__ . '/../' . $cleanPath . '.php';
        if (file_exists($rootScript)) {
            require $rootScript;
            break;
        }

        // Default fallback to index.php
        require __DIR__ . '/../index.php';
        break;
}
