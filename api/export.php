<?php
/**
 * LostTime - Export Report API Endpoint
 * Exports activity logs and summary to CSV format for current user
 */

require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$date = $_GET['date'] ?? '';
$period = $_GET['period'] ?? 'all'; // 'day', 'week', 'all'

$filename = "LostTime_Report_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Write BOM for Excel UTF-8 compatibility
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// CSV Column Headers
fputcsv($output, [
    'Log ID',
    'Date',
    'Activity Name',
    'Category',
    'Utility Status',
    'Duration (Minutes)',
    'Duration (Hours)',
    'Start Time',
    'End Time',
    'Notes'
]);

try {
    $sql = "
        SELECT 
            a.id,
            a.log_date,
            a.activity_name,
            c.name AS category_name,
            a.utility_type,
            a.duration_minutes,
            ROUND(a.duration_minutes / 60, 2) AS duration_hours,
            IFNULL(a.start_time, '-') AS start_time,
            IFNULL(a.end_time, '-') AS end_time,
            IFNULL(a.notes, '') AS notes
        FROM activities a
        JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = :user_id
    ";

    $params = ['user_id' => $userId];
    if (!empty($date) && $period === 'day') {
        $sql .= " AND a.log_date = :log_date";
        $params['log_date'] = $date;
    } elseif ($period === 'week') {
        $sql .= " AND a.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    }

    $sql .= " ORDER BY a.log_date DESC, a.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['log_date'],
            $row['activity_name'],
            $row['category_name'],
            ucfirst($row['utility_type']),
            $row['duration_minutes'],
            $row['duration_hours'],
            $row['start_time'],
            $row['end_time'],
            $row['notes']
        ]);
    }
} catch (Exception $e) {
    fputcsv($output, ['Error generating export: ' . $e->getMessage()]);
}

fclose($output);
exit;
