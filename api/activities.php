<?php
/**
 * LostTime - Activities API Endpoint
 * Handles Activity Logging (Create), Retrieval (Read), and Deletion (Delete)
 */

require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d');
    $fetchAll = isset($_GET['all']) && $_GET['all'] === 'true';

    try {
        // Fetch categories for frontend dropdowns
        $catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $categories = $catStmt->fetchAll();

        // Fetch activities for logged in user
        if ($fetchAll) {
            $stmt = $pdo->prepare("
                SELECT a.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
                FROM activities a
                JOIN categories c ON a.category_id = c.id
                WHERE a.user_id = :user_id
                ORDER BY a.log_date DESC, a.created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $activities = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
                SELECT a.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon
                FROM activities a
                JOIN categories c ON a.category_id = c.id
                WHERE a.user_id = :user_id AND a.log_date = :log_date
                ORDER BY a.start_time ASC, a.created_at ASC
            ");
            $stmt->execute([
                'user_id' => $userId,
                'log_date' => $date
            ]);
            $activities = $stmt->fetchAll();
        }

        // Fetch distinct logged dates for quick date selector dropdown
        $datesStmt = $pdo->prepare("
            SELECT log_date, COUNT(*) AS count
            FROM activities
            WHERE user_id = :user_id
            GROUP BY log_date
            ORDER BY log_date DESC
            LIMIT 30
        ");
        $datesStmt->execute(['user_id' => $userId]);
        $loggedDates = $datesStmt->fetchAll();

        sendJsonResponse([
            'success' => true,
            'user_id' => $userId,
            'date' => $date,
            'categories' => $categories,
            'activities' => $activities,
            'logged_dates' => $loggedDates
        ]);
    } catch (PDOException $e) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Failed to fetch activities: ' . $e->getMessage()
        ], 500);
    }
}

if ($method === 'POST') {
    // Read JSON or POST body
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!$data) {
        $data = $_POST;
    }

    $activity_name = trim($data['activity_name'] ?? '');
    $category_id = intval($data['category_id'] ?? 0);
    $utility_type = trim($data['utility_type'] ?? '');
    $log_date = trim($data['log_date'] ?? date('Y-m-d'));
    $start_time = !empty($data['start_time']) ? trim($data['start_time']) : null;
    $end_time = !empty($data['end_time']) ? trim($data['end_time']) : null;
    $duration_minutes = intval($data['duration_minutes'] ?? 0);
    $notes = trim($data['notes'] ?? '');

    // Calculate duration from start/end if duration wasn't explicitly given
    if ($duration_minutes <= 0 && $start_time && $end_time) {
        $startTs = strtotime($start_time);
        $endTs = strtotime($end_time);
        if ($endTs > $startTs) {
            $duration_minutes = round(($endTs - $startTs) / 60);
        }
    }

    // Basic Validation
    if (empty($activity_name)) {
        sendJsonResponse(['success' => false, 'message' => 'Activity name is required.'], 400);
    }
    if ($category_id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Please select a valid category.'], 400);
    }
    if ($duration_minutes <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Duration must be greater than 0 minutes.'], 400);
    }
    if (!in_array($utility_type, ['productive', 'unproductive'])) {
        // Fallback: look up category default utility
        $catStmt = $pdo->prepare("SELECT default_utility FROM categories WHERE id = :id");
        $catStmt->execute(['id' => $category_id]);
        $cat = $catStmt->fetch();
        $utility_type = $cat['default_utility'] ?? 'productive';
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO activities (user_id, activity_name, category_id, duration_minutes, utility_type, log_date, start_time, end_time, notes)
            VALUES (:user_id, :activity_name, :category_id, :duration_minutes, :utility_type, :log_date, :start_time, :end_time, :notes)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'activity_name' => $activity_name,
            'category_id' => $category_id,
            'duration_minutes' => $duration_minutes,
            'utility_type' => $utility_type,
            'log_date' => $log_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'notes' => $notes ?: null
        ]);

        sendJsonResponse([
            'success' => true,
            'message' => 'Activity logged successfully!',
            'id' => $pdo->lastInsertId()
        ], 201);
    } catch (PDOException $e) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Database error while saving activity: ' . $e->getMessage()
        ], 500);
    }
}

if ($method === 'DELETE') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    $id = intval($_GET['id'] ?? ($data['id'] ?? 0));

    if ($id <= 0) {
        sendJsonResponse(['success' => false, 'message' => 'Invalid activity ID specified.'], 400);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM activities WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId
        ]);

        if ($stmt->rowCount() > 0) {
            sendJsonResponse(['success' => true, 'message' => 'Activity deleted successfully.']);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Activity not found or unauthorized.'], 404);
        }
    } catch (PDOException $e) {
        sendJsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
}

sendJsonResponse(['success' => false, 'message' => 'Method not supported.'], 405);
