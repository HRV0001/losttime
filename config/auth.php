<?php
/**
 * LostTime - Authentication & Session Helper
 * Supports Serverless Vercel Deployments & Local Traditional Environments
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 14,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

function getAuthSecret() {
    return getenv('AUTH_SECRET') ?: 'losttime_cloud_auth_secret_token_key_9824';
}

/**
 * Sets session and issues a cryptographically signed cookie to ensure
 * login persists across ephemeral Vercel serverless function instances.
 */
function setAuthSession($userId, $userName, $userEmail) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_email'] = $userEmail;

    $payload = [
        'id' => $userId,
        'name' => $userName,
        'email' => $userEmail,
        'exp' => time() + (86400 * 14) // 14 days expiration
    ];
    $data = base64_encode(json_encode($payload));
    $sig = hash_hmac('sha256', $data, getAuthSecret());
    $cookieValue = $data . '.' . $sig;

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('losttime_token', $cookieValue, [
        'expires' => time() + (86400 * 14),
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Destroys session and deletes auth cookies
 */
function clearAuthSession() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('losttime_token', '', [
        'expires' => time() - 42000,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

/**
 * Rehydrates session from signed cookie if session is missing (e.g. serverless cold start)
 */
function restoreSessionFromCookie() {
    if (!empty($_SESSION['user_id'])) {
        return;
    }
    if (empty($_COOKIE['losttime_token'])) {
        return;
    }

    $parts = explode('.', $_COOKIE['losttime_token']);
    if (count($parts) !== 2) {
        return;
    }

    list($data, $sig) = $parts;
    $expectedSig = hash_hmac('sha256', $data, getAuthSecret());
    if (!hash_equals($expectedSig, $sig)) {
        return;
    }

    $json = base64_decode($data);
    $payload = json_decode($json, true);
    if (!$payload || !isset($payload['id']) || !isset($payload['exp'])) {
        return;
    }

    if ($payload['exp'] < time()) {
        return; // Token expired
    }

    $_SESSION['user_id'] = $payload['id'];
    $_SESSION['user_name'] = $payload['name'] ?? 'User';
    $_SESSION['user_email'] = $payload['email'] ?? '';
}

// Ensure session is rehydrated automatically
restoreSessionFromCookie();

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 1; // Fallback to demo user (ID 1) if not strictly set
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return [
            'id' => 1,
            'name' => 'Demo User',
            'email' => 'demo@losttime.com',
            'is_demo' => true
        ];
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'is_demo' => false
    ];
}

function requireAuth($isApi = false) {
    if (!isLoggedIn()) {
        if ($isApi) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Authentication required. Please log in.',
                'authenticated' => false
            ], 401);
        } else {
            header('Location: login.php');
            exit;
        }
    }
}
