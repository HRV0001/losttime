<?php
/**
 * LostTime - Smart Productive Suggestions Engine API
 * Analyzes the user's logged Lost Time and returns tailored, actionable alternatives
 */

require_once __DIR__ . '/../config/auth.php';

$pdo = getDBConnection();
$userId = getCurrentUserId();
$date = $_GET['date'] ?? date('Y-m-d');

try {
    // 1. Fetch today's unproductive activities for user
    $stmt = $pdo->prepare("
        SELECT 
            a.activity_name,
            a.duration_minutes,
            a.start_time,
            c.id AS category_id,
            c.name AS category_name,
            c.color AS category_color,
            c.icon AS category_icon
        FROM activities a
        JOIN categories c ON a.category_id = c.id
        WHERE a.user_id = :user_id AND a.log_date = :log_date AND a.utility_type = 'unproductive'
        ORDER BY a.duration_minutes DESC
    ");
    $stmt->execute([
        'user_id' => $userId,
        'log_date' => $date
    ]);
    $lostActivities = $stmt->fetchAll();

    $totalLostMinutes = 0;
    $categoryLostMap = [];

    foreach ($lostActivities as $act) {
        $mins = intval($act['duration_minutes']);
        $totalLostMinutes += $mins;
        $catId = $act['category_id'];

        if (!isset($categoryLostMap[$catId])) {
            $categoryLostMap[$catId] = [
                'category_id' => $catId,
                'category_name' => $act['category_name'],
                'color' => $act['category_color'],
                'icon' => $act['category_icon'],
                'total_minutes' => 0,
                'activities' => []
            ];
        }
        $categoryLostMap[$catId]['total_minutes'] += $mins;
        $categoryLostMap[$catId]['activities'][] = $act['activity_name'];
    }

    // Sort categories by time lost descending
    usort($categoryLostMap, function($a, $b) {
        return $b['total_minutes'] - $a['total_minutes'];
    });

    // 2. Fetch all suggestions from database catalog
    $sugStmt = $pdo->query("
        SELECT s.*, c.name AS category_name, c.color AS category_color
        FROM suggestions s
        LEFT JOIN categories c ON s.category_id = c.id
    ");
    $allCatalog = $sugStmt->fetchAll();

    $recommendations = [];

    if ($totalLostMinutes === 0) {
        $recommendations[] = [
            'type' => 'praise',
            'badge' => 'Peak Focus',
            'badge_color' => '#10b981',
            'title' => 'Zero Lost Time Recorded! 🎯',
            'description' => 'You have zero unproductive hours logged today. Maintain this high-clarity streak or recharge with healthy rest.',
            'estimated_minutes' => 0,
            'icon' => 'fa-trophy'
        ];
    } else {
        $lostHours = round($totalLostMinutes / 60, 1);
        $topCategory = !empty($categoryLostMap) ? $categoryLostMap[0] : null;

        if ($topCategory) {
            $catName = $topCategory['category_name'];
            $catMins = $topCategory['total_minutes'];
            $catHours = round($catMins / 60, 1);

            $dsaProblems = max(1, floor($catMins / 30));
            $bookPages = max(5, floor($catMins * 0.7));

            $contextualPill = "You lost {$catMins} mins ({$catHours} hrs) in '{$catName}'.";

            $recommendations[] = [
                'type' => 'primary_intervention',
                'badge' => 'High-Impact Swap',
                'badge_color' => '#f43f5e',
                'category_trigger' => $catName,
                'context' => $contextualPill,
                'title' => "Swap {$catName} for {$dsaProblems} DSA Problem" . ($dsaProblems > 1 ? 's' : ''),
                'description' => "Instead of {$catMins} mins in {$catName}, spending just 30 minutes solving LeetCode or revising algorithms compounds into real career growth.",
                'estimated_minutes' => 30,
                'icon' => 'fa-code'
            ];

            $recommendations[] = [
                'type' => 'reading_swap',
                'badge' => 'Knowledge Habit',
                'badge_color' => '#8b5cf6',
                'category_trigger' => $catName,
                'context' => "Equivalent to reading ~{$bookPages} pages",
                'title' => "20-Minute Focused Reading Sprint",
                'description' => "Pick up a core book or tech blog. In 20 minutes, you can easily read 10–15 pages and reset mental focus.",
                'estimated_minutes' => 20,
                'icon' => 'fa-book-open'
            ];

            $recommendations[] = [
                'type' => 'physical_reset',
                'badge' => 'Dopamine Detox',
                'badge_color' => '#06b6d4',
                'category_trigger' => $catName,
                'context' => 'Instant Energy Boost',
                'title' => "15-Minute Bodyweight & Stretch Reset",
                'description' => "Long unproductive sitting drains physical alertness. Do 15 jumping jacks, 20 squats, and drink 500ml water.",
                'estimated_minutes' => 15,
                'icon' => 'fa-person-walking'
            ];
        }

        foreach ($allCatalog as $catItem) {
            if ($catItem['category_id'] !== null) {
                foreach ($categoryLostMap as $lostCat) {
                    if ($lostCat['category_id'] == $catItem['category_id']) {
                        $recommendations[] = [
                            'type' => 'catalog_suggestion',
                            'badge' => ucfirst($catItem['action_type']),
                            'badge_color' => '#3b82f6',
                            'title' => $catItem['title'],
                            'description' => $catItem['suggestion_text'],
                            'estimated_minutes' => intval($catItem['estimated_minutes']),
                            'icon' => 'fa-lightbulb'
                        ];
                    }
                }
            }
        }
    }

    sendJsonResponse([
        'success' => true,
        'date' => $date,
        'user_id' => $userId,
        'total_lost_minutes' => $totalLostMinutes,
        'total_lost_hours' => round($totalLostMinutes / 60, 2),
        'top_loss_categories' => array_values($categoryLostMap),
        'suggestions' => $recommendations
    ]);

} catch (PDOException $e) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Failed to generate suggestions: ' . $e->getMessage()
    ], 500);
}
