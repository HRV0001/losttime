<?php
/**
 * LostTime - Calendar Synchronization & Aggregation API
 * Supports Month View Data, iCalendar (.ics) Export, and Live Subscription Feed
 */

require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'month';

// 1. Live Calendar Subscription Feed (Authenticated via token or session)
if ($action === 'feed') {
    $token = trim($_GET['token'] ?? '');
    $user = null;

    if (!empty($token)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE calendar_token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();
    } elseif (isLoggedIn()) {
        $user = getCurrentUser();
    }

    if (!$user) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "Unauthorized calendar subscription request.";
        exit;
    }

    $userId = $user['id'];

    // Fetch activities for the past 60 days and future
    $actStmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name
        FROM activities a
        JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = :user_id AND a.log_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        ORDER BY a.log_date DESC, a.start_time ASC
    ");
    $actStmt->execute(['user_id' => $userId]);
    $activities = $actStmt->fetchAll();

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: inline; filename="losttime_feed.ics"');

    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//LostTime//Productivity & Time Audit//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:PUBLISH\r\n";
    echo "X-WR-CALNAME:LostTime (" . htmlspecialchars($user['name']) . ")\r\n";
    echo "X-WR-CALDESC:Daily tracked productive and lost time activities\r\n";

    foreach ($activities as $act) {
        $dateStr = str_replace('-', '', $act['log_date']);
        $startTime = !empty($act['start_time']) ? str_replace(':', '', substr($act['start_time'], 0, 5)) . '00' : '090000';
        $endTime = !empty($act['end_time']) ? str_replace(':', '', substr($act['end_time'], 0, 5)) . '00' : '100000';
        
        $durationHours = round($act['duration_minutes'] / 60, 1);
        $tag = $act['utility_type'] === 'productive' ? 'Productive' : 'Lost Time';
        $prefix = $act['utility_type'] === 'productive' ? '🟢' : '🔴';

        $summary = "{$prefix} [{$tag}] " . addcslashes($act['activity_name'], ",;\\");
        $description = "Category: " . addcslashes($act['category_name'], ",;\\") . "\\n"
                     . "Duration: {$act['duration_minutes']} mins ({$durationHours} hrs)\\n"
                     . "Utility Status: {$tag}\\n"
                     . "Notes: " . addcslashes($act['notes'] ?? 'None', ",;\\");

        $uid = "losttime-act-" . $act['id'] . "@" . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $created = date('Ymd\THis\Z', strtotime($act['created_at']));

        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:{$created}\r\n";
        echo "DTSTART:{$dateStr}T{$startTime}\r\n";
        echo "DTEND:{$dateStr}T{$endTime}\r\n";
        echo "SUMMARY:{$summary}\r\n";
        echo "DESCRIPTION:{$description}\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "CATEGORIES:" . ($act['utility_type'] === 'productive' ? "Productivity" : "Lost Time") . "\r\n";
        echo "END:VEVENT\r\n";
    }

    echo "END:VCALENDAR\r\n";
    exit;
}

// Ensure session authentication for other actions
$userId = getCurrentUserId();

// 2. Downloadable .ICS Calendar File Export
if ($action === 'export_ics') {
    $month = intval($_GET['month'] ?? date('m'));
    $year = intval($_GET['year'] ?? date('Y'));

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $endDate = date('Y-m-t', strtotime($startDate));

    $actStmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name
        FROM activities a
        JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = :user_id AND a.log_date BETWEEN :start_date AND :end_date
        ORDER BY a.log_date ASC, a.start_time ASC
    ");
    $actStmt->execute([
        'user_id' => $userId,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
    $activities = $actStmt->fetchAll();

    $filename = "losttime_calendar_{$year}_{$month}.ics";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//LostTime//Productivity Tracker//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    echo "METHOD:PUBLISH\r\n";
    echo "X-WR-CALNAME:LostTime ({$year}-{$month})\r\n";

    foreach ($activities as $act) {
        $dateStr = str_replace('-', '', $act['log_date']);
        $startTime = !empty($act['start_time']) ? str_replace(':', '', substr($act['start_time'], 0, 5)) . '00' : '090000';
        $endTime = !empty($act['end_time']) ? str_replace(':', '', substr($act['end_time'], 0, 5)) . '00' : '100000';

        $tag = $act['utility_type'] === 'productive' ? 'Productive' : 'Lost Time';
        $prefix = $act['utility_type'] === 'productive' ? '🟢' : '🔴';

        $summary = "{$prefix} [{$tag}] " . addcslashes($act['activity_name'], ",;\\");
        $description = "Category: " . addcslashes($act['category_name'], ",;\\") . "\\n"
                     . "Duration: {$act['duration_minutes']} mins\\n"
                     . "Status: {$tag}\\n"
                     . "Notes: " . addcslashes($act['notes'] ?? '', ",;\\");

        $uid = "losttime-" . $act['id'] . "-" . $dateStr . "@localhost";
        $dtstamp = date('Ymd\THis\Z');

        echo "BEGIN:VEVENT\r\n";
        echo "UID:{$uid}\r\n";
        echo "DTSTAMP:{$dtstamp}\r\n";
        echo "DTSTART:{$dateStr}T{$startTime}\r\n";
        echo "DTEND:{$dateStr}T{$endTime}\r\n";
        echo "SUMMARY:{$summary}\r\n";
        echo "DESCRIPTION:{$description}\r\n";
        echo "STATUS:CONFIRMED\r\n";
        echo "END:VEVENT\r\n";
    }

    echo "END:VCALENDAR\r\n";
    exit;
}

// 3. Month View Aggregation Data (JSON for Calendar UI Grid)
if ($action === 'month') {
    $year = intval($_GET['year'] ?? date('Y'));
    $month = intval($_GET['month'] ?? date('n'));

    if ($year < 2000 || $year > 2100) $year = intval(date('Y'));
    if ($month < 1 || $month > 12) $month = intval(date('n'));

    $startDate = sprintf('%04d-%02d-01', $year, $month);
    $daysInMonth = intval(date('t', strtotime($startDate)));
    $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

    // Fetch activities for the month
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.activity_name,
            a.duration_minutes,
            a.utility_type,
            a.log_date,
            a.start_time,
            a.end_time,
            c.name AS category_name,
            c.color AS category_color
        FROM activities a
        JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = :user_id AND a.log_date BETWEEN :start_date AND :end_date
        ORDER BY a.log_date ASC, a.start_time ASC
    ");
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
    $monthActivities = $stmt->fetchAll();

    // Map activities by day number
    $dayMap = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $formattedDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dayMap[$formattedDate] = [
            'day' => $d,
            'date' => $formattedDate,
            'day_of_week' => intval(date('w', strtotime($formattedDate))), // 0 (Sun) to 6 (Sat)
            'productive_minutes' => 0,
            'productive_hours' => 0,
            'lost_minutes' => 0,
            'lost_hours' => 0,
            'total_minutes' => 0,
            'total_hours' => 0,
            'productivity_rate' => 0,
            'activities' => []
        ];
    }

    foreach ($monthActivities as $act) {
        $d = $act['log_date'];
        if (isset($dayMap[$d])) {
            $mins = intval($act['duration_minutes']);
            if ($act['utility_type'] === 'productive') {
                $dayMap[$d]['productive_minutes'] += $mins;
            } else {
                $dayMap[$d]['lost_minutes'] += $mins;
            }
            $dayMap[$d]['total_minutes'] += $mins;
            $dayMap[$d]['activities'][] = $act;
        }
    }

    // Calculate rates and hours
    foreach ($dayMap as $k => $day) {
        $dayMap[$k]['productive_hours'] = round($day['productive_minutes'] / 60, 1);
        $dayMap[$k]['lost_hours'] = round($day['lost_minutes'] / 60, 1);
        $dayMap[$k]['total_hours'] = round($day['total_minutes'] / 60, 1);
        $dayMap[$k]['productivity_rate'] = $day['total_minutes'] > 0 
            ? round(($day['productive_minutes'] / $day['total_minutes']) * 100) 
            : 0;
    }

    // Get user's calendar token for feed subscription
    $userTokenStmt = $pdo->prepare("SELECT calendar_token FROM users WHERE id = :id");
    $userTokenStmt->execute(['id' => $userId]);
    $userToken = $userTokenStmt->fetchColumn() ?: '';

    $baseUri = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/losttime') !== false) ? '/losttime' : '';
    $feedUrl = "{$protocol}{$host}{$baseUri}/api/calendar.php?action=feed&token={$userToken}";

    sendJsonResponse([
        'success' => true,
        'year' => $year,
        'month' => $month,
        'month_name' => date('F', strtotime($startDate)),
        'first_day_of_week' => intval(date('w', strtotime($startDate))),
        'days_in_month' => $daysInMonth,
        'calendar_feed_url' => $feedUrl,
        'days' => array_values($dayMap)
    ]);
}

sendJsonResponse(['success' => false, 'message' => 'Invalid calendar action.'], 400);

