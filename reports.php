<?php
/**
 * LostTime - Printable Reports & Analytics Summary
 * Built with HTML5, PHP, CSS3, JavaScript, AngularJS & MySQL
 */
require_once __DIR__ . '/config/auth.php';

// Authentication guard
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$userId = getCurrentUserId();
$currentUser = getCurrentUser();

// Fetch summary metrics for current user
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_activities,
        SUM(CASE WHEN utility_type = 'productive' THEN duration_minutes ELSE 0 END) AS prod_mins,
        SUM(CASE WHEN utility_type = 'unproductive' THEN duration_minutes ELSE 0 END) AS lost_mins
    FROM activities
    WHERE user_id = :user_id
");
$stmt->execute(['user_id' => $userId]);
$overall = $stmt->fetch();

$totalProdM = intval($overall['prod_mins'] ?? 0);
$totalLostM = intval($overall['lost_mins'] ?? 0);
$totalM = $totalProdM + $totalLostM;
$prodRatio = $totalM > 0 ? round(($totalProdM / $totalM) * 100, 1) : 0;

// Fetch all logs for current user
$logStmt = $pdo->prepare("
    SELECT a.*, c.name AS category_name, c.color AS category_color
    FROM activities a
    JOIN categories c ON a.category_id = c.id
    WHERE a.user_id = :user_id
    ORDER BY a.log_date DESC, a.start_time ASC
");
$logStmt->execute(['user_id' => $userId]);
$allLogs = $logStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LostTime — Summary Report</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    @media print {
      .navbar, .no-print { display: none !important; }
      body { background: #fff !important; color: #000 !important; }
      .card, .metric-card { border: 1px solid #ccc !important; box-shadow: none !important; background: #fff !important; color: #000 !important; }
      .metric-value { color: #000 !important; }
      .activity-table th, .activity-table td { color: #000 !important; border-bottom: 1px solid #eee !important; }
      .tag-productive { background: #e6fcf5 !important; color: #0ca678 !important; border: 1px solid #0ca678 !important; }
      .tag-unproductive { background: #fff5f5 !important; color: #fa5252 !important; border: 1px solid #fa5252 !important; }
    }
  </style>
</head>
<body>

  <!-- Top Navigation -->
  <header class="navbar">
    <div class="nav-container">
      <div class="brand">
        <div class="brand-icon">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <span>LostTime — Summary Report</span>
      </div>

      <nav class="nav-links">
        <a href="index.php" class="nav-btn">
          <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
        <button class="nav-btn" onclick="window.print()">
          <i class="fa-solid fa-print"></i> Print / Save PDF
        </button>
        <a href="api/export.php?period=all" class="nav-btn btn-primary">
          <i class="fa-solid fa-download"></i> Export CSV
        </a>
      </nav>

      <div class="user-menu-group no-print">
        <div class="user-profile-badge">
          <div class="user-avatar-circle">
            <?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
          </div>
          <span><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></span>
        </div>
        <a href="logout.php" class="nav-btn btn-logout" title="Sign Out">
          <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
      </div>
    </div>
  </header>

  <main class="main-wrapper">
    <div style="margin-bottom: 2rem;">
      <h1 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 0.35rem;">Time Audit & Productivity Summary</h1>
      <p style="color: var(--text-muted); font-size: 0.95rem;">
        User: <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong> (<?php echo htmlspecialchars($currentUser['email']); ?>) &bull; Generated on <?php echo date('F d, Y \a\t h:i A'); ?>
      </p>
    </div>

    <!-- Summary Metrics -->
    <section class="metrics-grid">
      <div class="metric-card lost-border">
        <div class="metric-title">Total Time Lost</div>
        <div class="metric-value" style="color: var(--unproductive);">
          <?php echo round($totalLostM / 60, 1); ?> <span style="font-size:1.1rem;">hrs</span>
        </div>
        <div class="metric-sub"><?php echo $totalLostM; ?> minutes diverted to unproductive tasks</div>
      </div>

      <div class="metric-card productive-border">
        <div class="metric-title">Total Productive Time</div>
        <div class="metric-value" style="color: var(--productive);">
          <?php echo round($totalProdM / 60, 1); ?> <span style="font-size:1.1rem;">hrs</span>
        </div>
        <div class="metric-sub"><?php echo $totalProdM; ?> minutes invested productively</div>
      </div>

      <div class="metric-card neutral-border">
        <div class="metric-title">Overall Productivity Score</div>
        <div class="metric-value" style="color: var(--accent-primary);">
          <?php echo $prodRatio; ?>%
        </div>
        <div class="metric-sub">Useful time / Total logged time</div>
      </div>

      <div class="metric-card warning-border">
        <div class="metric-title">Total Logged Activities</div>
        <div class="metric-value">
          <?php echo count($allLogs); ?> <span style="font-size:1.1rem;">logs</span>
        </div>
        <div class="metric-sub">Across all recorded dates</div>
      </div>
    </section>

    <!-- Detailed Historical Log Table -->
    <section class="card" style="margin-top: 1.5rem;">
      <div class="card-title-bar">
        <h3 class="card-title"><i class="fa-solid fa-list"></i> Full Activity History</h3>
      </div>

      <div class="activity-table-wrap">
        <table class="activity-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Time Slot</th>
              <th>Activity</th>
              <th>Category</th>
              <th>Duration</th>
              <th>Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($allLogs as $log): ?>
              <tr>
                <td style="font-weight: 500; font-size: 0.88rem;"><?php echo htmlspecialchars($log['log_date']); ?></td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">
                  <?php echo $log['start_time'] ? substr($log['start_time'], 0, 5) . ' - ' . substr($log['end_time'] ?? '', 0, 5) : '-'; ?>
                </td>
                <td style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($log['activity_name']); ?></td>
                <td>
                  <span class="cat-pill">
                    <span class="cat-dot" style="background-color: <?php echo htmlspecialchars($log['category_color']); ?>;"></span>
                    <?php echo htmlspecialchars($log['category_name']); ?>
                  </span>
                </td>
                <td style="font-weight: 700;">
                  <?php 
                    $h = floor($log['duration_minutes'] / 60);
                    $m = $log['duration_minutes'] % 60;
                    echo ($h > 0 ? "{$h}h " : "") . ($m > 0 || $h == 0 ? "{$m}m" : "");
                  ?>
                </td>
                <td>
                  <span class="utility-tag <?php echo $log['utility_type'] === 'productive' ? 'tag-productive' : 'tag-unproductive'; ?>">
                    <i class="fa-solid <?php echo $log['utility_type'] === 'productive' ? 'fa-check' : 'fa-xmark'; ?>"></i>
                    <?php echo ucfirst($log['utility_type'] === 'productive' ? 'Useful' : 'Lost Time'); ?>
                  </span>
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($log['notes'] ?: '-'); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

</body>
</html>
