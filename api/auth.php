<?php
/**
 * LostTime - Authentication API Endpoint
 * Handles User Login, Registration, and Session Verification
 */

require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Read JSON or POST body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!$data) {
    $data = $_POST;
}

$action = $_GET['action'] ?? ($data['action'] ?? '');

if ($method === 'GET' && $action === 'me') {
    if (isLoggedIn()) {
        sendJsonResponse([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email']
            ]
        ]);
    } else {
        sendJsonResponse([
            'success' => true,
            'authenticated' => false,
            'user' => null
        ]);
    }
}

if ($action === 'login') {
    $email = strtolower(trim($data['email'] ?? ''));
    $password = trim($data['password'] ?? '');

    if (empty($email) || empty($password)) {
        sendJsonResponse(['success' => false, 'message' => 'Email and password are required.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            sendJsonResponse([
                'success' => true,
                'message' => 'Welcome back, ' . htmlspecialchars($user['name']) . '!',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email']
                ]
            ]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Invalid email or password.'], 401);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

if ($action === 'register') {
    $name = trim($data['name'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $password = trim($data['password'] ?? '');

    if (empty($name)) {
        sendJsonResponse(['success' => false, 'message' => 'Please provide your full name.'], 400);
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
    }
    if (strlen($password) < 6) {
        sendJsonResponse(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 400);
    }

    try {
        // Check if email already registered
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute(['email' => $email]);
        if ($checkStmt->fetch()) {
            sendJsonResponse(['success' => false, 'message' => 'An account with this email already exists.'], 409);
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $calendarToken = bin2hex(random_bytes(16));

        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, calendar_token)
            VALUES (:name, :email, :password, :calendar_token)
        ");
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashedPassword,
            'calendar_token' => $calendarToken
        ]);

        $userId = $pdo->lastInsertId();

        // Auto-login new user
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;

        // Optionally create welcome starter activity
        $initAct = $pdo->prepare("
            INSERT INTO activities (user_id, activity_name, category_id, duration_minutes, utility_type, log_date, notes)
            VALUES (:user_id, 'Joined LostTime & Planned First Day', 1, 30, 'productive', CURDATE(), 'Welcome to mindful time auditing!')
        ");
        $initAct->execute(['user_id' => $userId]);

        sendJsonResponse([
            'success' => true,
            'message' => 'Account created successfully! Welcome to LostTime.',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email
            ]
        ], 201);
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Registration error: ' . $e->getMessage()], 500);
    }
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    sendJsonResponse(['success' => true, 'message' => 'Logged out successfully.']);
}

sendJsonResponse(['success' => false, 'message' => 'Invalid action.'], 400);

