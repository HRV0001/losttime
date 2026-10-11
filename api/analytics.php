<?php
/**
 * LostTime - Analytics API Endpoint
 * Computes daily 24h split, weekly productivity trends, and peak unproductive hours
 */

require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$date = $_GET['date'] ?? date('Y-m-d');

try {
    // 1. Daily Metrics for the selected date and user
    $dailyStmt = $pdo->prepare("
        SELECT 
            utility_type,
            SUM(duration_minutes) AS total_minutes,
            COUNT(*) AS activity_count
        FROM activities
        WHERE user_id = :user_id AND log_date = :log_date
        GROUP BY utility_type
    ");
    $dailyStmt->execute([
        'user_id' => $userId,
        'log_date' => $date
    ]);
    $dailyRows = $dailyStmt->fetchAll();

    $productiveMinutes = 0;
    $lostMinutes = 0;
    foreach ($dailyRows as $row) {
        if ($row['utility_type'] === 'productive') {
            $productiveMinutes = intval($row['total_minutes']);
        } elseif ($row['utility_type'] === 'unproductive') {
            $lostMinutes = intval($row['total_minutes']);
        }
    }

    $totalDayMinutes = 1440; // 24 hours * 60 minutes
    $totalLoggedMinutes = $productiveMinutes + $lostMinutes;
    $untrackedMinutes = max(0, $totalDayMinutes - $totalLoggedMinutes);

    $productivityRate = $totalLoggedMinutes > 0 
        ? round(($productiveMinutes / $totalLoggedMinutes) * 100, 1) 
        : 0;

    // Daily category distribution for user
    $catStmt = $pdo->prepare("
        SELECT 
            c.name AS category_name,
            c.color AS category_color,
            c.icon AS category_icon,
            a.utility_type,
            SUM(a.duration_minutes) AS minutes
        FROM activities a
        JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = :user_id AND a.log_date = :log_date
        GROUP BY c.id, a.utility_type
        ORDER BY minutes DESC
    ");
    $catStmt->execute([
        'user_id' => $userId,
        'log_date' => $date
    ]);
    $categoryBreakdown = $catStmt->fetchAll();

    // 2. Weekly Trend (Last 7 Days leading up to $date for user)
    $weeklyTrends = [];
    for ($i = 6; $i >= 0; $i--) {
        $targetDay = date('Y-m-d', strtotime("$date -$i days"));
        $dayLabel = date('D (M d)', strtotime($targetDay));

        $weekStmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN utility_type = 'productive' THEN duration_minutes ELSE 0 END) AS prod_mins,
                SUM(CASE WHEN utility_type = 'unproductive' THEN duration_minutes ELSE 0 END) AS lost_mins
            FROM activities
            WHERE user_id = :user_id AND log_date = :log_date
        ");
        $weekStmt->execute([
            'user_id' => $userId,
            'log_date' => $targetDay
        ]);
        $dayData = $weekStmt->fetch();

        $prodM = intval($dayData['prod_mins'] ?? 0);
        $lostM = intval($dayData['lost_mins'] ?? 0);

        $weeklyTrends[] = [
            'date' => $targetDay,
            'label' => $dayLabel,
            'productive_hours' => round($prodM / 60, 2),
            'lost_hours' => round($lostM / 60, 2),
            'total_hours' => round(($prodM + $lostM) / 60, 2)
        ];
    }

    // 3. Peak Unproductive Hours Analysis for user
    $peakStmt = $pdo->prepare("
        SELECT 
            HOUR(start_time) AS hour_block,
            SUM(duration_minutes) AS lost_minutes,
            COUNT(*) AS incidents
        FROM activities
        WHERE user_id = :user_id AND utility_type = 'unproductive' AND start_time IS NOT NULL
        GROUP BY HOUR(start_time)
        ORDER BY lost_minutes DESC
    ");
    $peakStmt->execute(['user_id' => $userId]);
    $peakRaw = $peakStmt->fetchAll();

    // Build 24-hour array (0:00 to 23:00)
    $peakHours = [];
    $peakMap = [];
    foreach ($peakRaw as $p) {
        $peakMap[intval($p['hour_block'])] = [
            'lost_minutes' => intval($p['lost_minutes']),
            'incidents' => intval($p['incidents'])
        ];
    }

    for ($h = 0; $h < 24; $h++) {
        $formattedHour = date('g A', strtotime("$h:00:00"));
        $data = $peakMap[$h] ?? ['lost_minutes' => 0, 'incidents' => 0];
        $peakHours[] = [
            'hour' => $h,
            'label' => $formattedHour,
            'lost_minutes' => $data['lost_minutes'],
            'lost_hours' => round($data['lost_minutes'] / 60, 2),
            'incidents' => $data['incidents']
        ];
    }

    // Identify the worst hour
    $worstHour = null;
    if (!empty($peakRaw)) {
        $worstHour = [
            'hour' => intval($peakRaw[0]['hour_block']),
            'label' => date('g:00 A', strtotime($peakRaw[0]['hour_block'] . ':00:00')),
            'lost_minutes' => intval($peakRaw[0]['lost_minutes']),
            'incidents' => intval($peakRaw[0]['incidents'])
        ];
    }

    sendJsonResponse([
        'success' => true,
        'date' => $date,
        'user_id' => $userId,
        'daily' => [
            'productive_minutes' => $productiveMinutes,
            'productive_hours' => round($productiveMinutes / 60, 2),
            'lost_minutes' => $lostMinutes,
            'lost_hours' => round($lostMinutes / 60, 2),
            'total_logged_minutes' => $totalLoggedMinutes,
            'total_logged_hours' => round($totalLoggedMinutes / 60, 2),
            'untracked_minutes' => $untrackedMinutes,
            'untracked_hours' => round($untrackedMinutes / 60, 2),
            'productivity_rate' => $productivityRate,
            'category_breakdown' => $categoryBreakdown
        ],
        'weekly' => $weeklyTrends,
        'peak_hours' => $peakHours,
        'worst_hour' => $worstHour
    ]);
} catch (PDOException $e) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Analytics calculation failed: ' . $e->getMessage()
    ], 500);
}
