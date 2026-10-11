<?php
/**
 * LostTime - Authentication & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 1; // Fallback to demo user (ID 1) if not strictly set
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        // Return demo user profile if not logged in
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

