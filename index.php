<?php
/**
 * LostTime - Main Application Entry
 * Built with HTML5, PHP, CSS3, JavaScript, AngularJS & MySQL
 */
require_once __DIR__ . '/config/auth.php';

// Authentication guard
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en" ng-app="lostTimeApp">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LostTime — Daily Time & Productivity Intelligence</title>
  
  <!-- FontAwesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- Custom Modern UI Styles -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body ng-controller="DashboardCtrl">

  <!-- ================= TOP NAVIGATION ================= -->
  <header class="navbar">
    <div class="nav-container">
      <div class="brand">
        <div class="brand-icon">
          <i class="fa-solid fa-hourglass-half"></i>
        </div>
        <span>LostTime</span>
      </div>

      <nav class="nav-links">
        <button class="nav-btn" ng-class="{'active': activeTab === 'dashboard'}" ng-click="setTab('dashboard')">
          <i class="fa-solid fa-gauge-high"></i> Dashboard
        </button>
        <button class="nav-btn" ng-class="{'active': activeTab === 'calendar'}" ng-click="setTab('calendar')">
          <i class="fa-solid fa-calendar-days"></i> Calendar Sync
        </button>
        <button class="nav-btn" ng-class="{'active': activeTab === 'analytics'}" ng-click="setTab('analytics')">
          <i class="fa-solid fa-chart-line"></i> Analytics & Trends
        </button>
        <a href="reports.php" class="nav-btn" title="View printable summary report">
          <i class="fa-solid fa-file-invoice"></i> Summary Report
        </a>
        <button class="nav-btn btn-primary" ng-click="openModal()">
          <i class="fa-solid fa-plus"></i> Log Activity
        </button>
      </nav>

      <!-- User Profile & Logout -->
      <div class="user-menu-group">
        <div class="user-profile-badge" title="<?php echo htmlspecialchars($currentUser['email']); ?>">
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

  <!-- ================= MAIN APPLICATION WRAPPER ================= -->
  <main class="main-wrapper">

    <!-- Alert / Toast Banner -->
    <div class="alert-box" ng-class="'alert-' + alert.type" ng-if="alert.show">
      <span><i class="fa-solid" ng-class="alert.type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'"></i> {{ alert.message }}</span>
      <button style="background:none; border:none; color:inherit; cursor:pointer;" ng-click="alert.show = false">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <!-- Date Navigation Control Bar (Shown on Dashboard & Analytics tabs) -->
    <section class="date-control-bar" ng-if="activeTab !== 'calendar'">
      <div class="date-selector-group">
        <button class="btn-icon-subtle" ng-click="shiftDate(-1)" title="Previous Day">
          <i class="fa-solid fa-chevron-left"></i>
        </button>
        <input type="date" class="date-input" id="globalDateSelector" value="<?php echo date('Y-m-d'); ?>" title="Choose any date">
        <button class="btn-icon-subtle" ng-click="shiftDate(1)" title="Next Day">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
        <button class="nav-btn" ng-click="setToday()" title="Jump to today">
          <i class="fa-solid fa-calendar-day"></i> Today
        </button>
        
        <!-- Jump to Logged Date Selector -->
        <select class="form-select" style="width: auto; padding: 0.45rem 0.85rem; font-size: 0.85rem;" ng-model="selectedDate" ng-change="setDate(selectedDate)" title="Jump directly to date with activities" ng-if="loggedDates.length > 0">
          <option value="" disabled>-- Jump to Date ({{ loggedDates.length }}) --</option>
          <option ng-repeat="d in loggedDates" ng-value="d.log_date">
            {{ d.log_date }} ({{ d.count }} logged)
          </option>
        </select>

        <span style="font-size: 0.92rem; font-weight: 700; color: var(--accent-primary); margin-left: 0.5rem; display: inline-flex; align-items: center; gap: 0.4rem;">
          <i class="fa-regular fa-calendar-check"></i> {{ displayDateFormatted }}
        </span>
      </div>

      <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
        <button class="nav-btn" ng-click="exportReport('day')">
          <i class="fa-solid fa-file-csv"></i> Export Day CSV
        </button>
        <button class="nav-btn" ng-click="exportReport('all')">
          <i class="fa-solid fa-download"></i> Export All History
        </button>
      </div>
    </section>

    <!-- Top Key Metrics Cards (Shown on Dashboard & Analytics) -->
    <section class="metrics-grid" ng-if="activeTab !== 'calendar'">
      <!-- Hours Lost Metric -->
      <div class="metric-card lost-border">
        <div class="metric-header">
          <span class="metric-title">Hours Lost ({{ selectedDate === todayDate ? 'Today' : selectedDate }})</span>
          <div class="metric-icon-badge" style="background: var(--unproductive-bg); color: var(--unproductive);">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
        </div>
        <div class="metric-value" style="color: var(--unproductive);">
          {{ dailyMetrics.lost_hours }} <span style="font-size: 1.1rem; font-weight: 500;">hrs</span>
        </div>
        <div class="metric-sub">{{ dailyMetrics.lost_minutes }} minutes slipped into distractions</div>
      </div>

      <!-- Productive Hours Metric -->
      <div class="metric-card productive-border">
        <div class="metric-header">
          <span class="metric-title">Productive Time ({{ selectedDate === todayDate ? 'Today' : selectedDate }})</span>
          <div class="metric-icon-badge" style="background: var(--productive-bg); color: var(--productive);">
            <i class="fa-solid fa-bullseye"></i>
          </div>
        </div>
        <div class="metric-value" style="color: var(--productive);">
          {{ dailyMetrics.productive_hours }} <span style="font-size: 1.1rem; font-weight: 500;">hrs</span>
        </div>
        <div class="metric-sub">{{ dailyMetrics.productive_minutes }} minutes invested in growth</div>
      </div>

      <!-- Productivity Rate -->
      <div class="metric-card neutral-border">
        <div class="metric-header">
          <span class="metric-title">Productivity Score</span>
          <div class="metric-icon-badge" style="background: rgba(99, 102, 241, 0.15); color: var(--accent-primary);">
            <i class="fa-solid fa-chart-pie"></i>
          </div>
        </div>
        <div class="metric-value" style="color: var(--accent-primary);">
          {{ dailyMetrics.productivity_rate }}%
        </div>
        <div class="metric-sub">Ratio of Useful to Total Logged hours</div>
      </div>

      <!-- 24-Hour Day Audited -->
      <div class="metric-card warning-border">
        <div class="metric-header">
          <span class="metric-title">24h Audit ({{ selectedDate === todayDate ? 'Today' : selectedDate }})</span>
          <div class="metric-icon-badge" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);">
            <i class="fa-solid fa-clock"></i>
          </div>
        </div>
        <div class="metric-value">
          {{ dailyMetrics.total_logged_hours }} <span style="font-size: 1.1rem; font-weight: 500;">/ 24 hrs</span>
        </div>
        <div class="metric-sub">{{ dailyMetrics.untracked_hours }} hrs untracked or sleep/rest</div>
      </div>
    </section>

    <!-- ================= TAB 1: DASHBOARD ================= -->
    <div ng-show="activeTab === 'dashboard'">
      <div class="dashboard-grid">
        <!-- 24-Hour Day Visual Breakdown Card -->
        <div class="card">
          <div class="card-title-bar">
            <h3 class="card-title"><i class="fa-solid fa-chart-pie"></i> 24-Hour Day Breakdown</h3>
            <span class="badge badge-info">{{ dailyMetrics.total_logged_hours }} hrs Logged</span>
          </div>

          <div class="chart-container">
            <canvas id="dailyDonutChart"></canvas>
          </div>

          <!-- Stacked 24h Timeline Progress Bar -->
          <div class="day-progress-wrap">
            <div class="progress-stacked">
              <div class="progress-bar-prod" ng-style="{'width': (dailyMetrics.productive_minutes / 1440 * 100) + '%'}"></div>
              <div class="progress-bar-lost" ng-style="{'width': (dailyMetrics.lost_minutes / 1440 * 100) + '%'}"></div>
              <div class="progress-bar-untracked" ng-style="{'width': (dailyMetrics.untracked_minutes / 1440 * 100) + '%'}"></div>
            </div>

            <div class="progress-legend">
              <span class="legend-item"><span class="legend-dot" style="background: var(--productive);"></span> Productive ({{ dailyMetrics.productive_hours }}h)</span>
              <span class="legend-item"><span class="legend-dot" style="background: var(--unproductive);"></span> Lost Time ({{ dailyMetrics.lost_hours }}h)</span>
              <span class="legend-item"><span class="legend-dot" style="background: var(--text-dim);"></span> Untracked/Sleep ({{ dailyMetrics.untracked_hours }}h)</span>
            </div>
          </div>

          <!-- Category Breakdown List -->
          <div style="margin-top: 1.5rem;" ng-if="dailyMetrics.category_breakdown.length > 0">
            <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
              Category Breakdown
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
              <div ng-repeat="cat in dailyMetrics.category_breakdown" style="display: flex; align-items: center; justify-content: space-between; font-size: 0.88rem;">
                <span class="cat-pill">
                  <span class="cat-dot" ng-style="{'background-color': cat.category_color}"></span>
                  {{ cat.category_name }}
                </span>
                <span style="font-weight: 600;" ng-class="cat.utility_type === 'productive' ? 'style-prod' : 'style-lost'">
                  {{ formatMinutes(cat.minutes) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Smart Productive Suggestions Engine -->
        <div class="card">
          <div class="card-title-bar">
            <h3 class="card-title"><i class="fa-solid fa-wand-magic-sparkles" style="color: var(--accent-primary);"></i> Smart Alternatives Engine</h3>
            <span class="badge badge-danger" ng-if="dailyMetrics.lost_hours > 0">{{ dailyMetrics.lost_hours }}h to Reclaim</span>
            <span class="badge badge-success" ng-if="dailyMetrics.lost_hours == 0">On Track</span>
          </div>

          <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Context-aware micro-habit alternatives tailored to today's logged unproductive time.
          </p>

          <div class="suggestion-list">
            <div class="suggestion-card" ng-repeat="sug in suggestions">
              <div class="suggestion-badge-row">
                <span class="badge" ng-style="{'background': sug.badge_color + '22', 'color': sug.badge_color, 'border': '1px solid ' + sug.badge_color + '66'}">
                  {{ sug.badge }}
                </span>
                <span class="suggestion-time" ng-if="sug.estimated_minutes > 0">
                  <i class="fa-regular fa-clock"></i> ~{{ sug.estimated_minutes }} mins
                </span>
              </div>

              <div class="suggestion-card-title">
                <i class="fa-solid" ng-class="sug.icon" style="color: var(--accent-primary); margin-right: 0.35rem;"></i>
                {{ sug.title }}
              </div>
              <p class="suggestion-desc">{{ sug.description }}</p>

              <div style="margin-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; color: var(--text-dim);" ng-if="sug.context">{{ sug.context }}</span>
                <button class="nav-btn" style="padding: 0.3rem 0.75rem; font-size: 0.78rem; border-color: var(--border-color);" ng-click="adoptSuggestion(sug)" ng-if="sug.estimated_minutes > 0">
                  <i class="fa-solid fa-bolt"></i> Adopt Swap
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Today's Activity Log Stream -->
      <section class="card" style="margin-top: 1.5rem;">
        <div class="card-title-bar">
          <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Activities</h3>
            <button class="nav-btn" style="padding: 0.3rem 0.75rem; font-size: 0.8rem;" ng-class="{'btn-primary': activitiesViewMode === 'single'}" ng-click="toggleActivitiesView('single')">
              <i class="fa-solid fa-calendar-day"></i> {{ displayDateFormatted }} ({{ activities.length }})
            </button>
            <button class="nav-btn" style="padding: 0.3rem 0.75rem; font-size: 0.8rem;" ng-class="{'btn-primary': activitiesViewMode === 'all'}" ng-click="toggleActivitiesView('all')">
              <i class="fa-solid fa-clock-rotate-left"></i> View All Recent Logs
            </button>
          </div>
          <button class="nav-btn btn-primary" style="padding: 0.45rem 0.9rem;" ng-click="openModal()">
            <i class="fa-solid fa-plus"></i> Add Entry
          </button>
        </div>

        <div class="activity-table-wrap" ng-if="activities.length > 0">
          <table class="activity-table">
            <thead>
              <tr>
                <th>Log Date</th>
                <th>Time / Slot</th>
                <th>Activity Name</th>
                <th>Category</th>
                <th>Duration</th>
                <th>Utility Status</th>
                <th>Notes</th>
                <th style="text-align: right;">Sync & Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr ng-repeat="act in activities">
                <td style="white-space: nowrap; font-size: 0.82rem;">
                  <span class="badge" ng-style="{'background': act.log_date === todayDate ? 'var(--productive-bg)' : 'rgba(255,255,255,0.06)', 'color': act.log_date === todayDate ? 'var(--productive)' : 'var(--text-muted)', 'border': '1px solid ' + (act.log_date === todayDate ? 'var(--productive-border)' : 'var(--border-color)')}">
                    <i class="fa-regular fa-calendar"></i> {{ act.log_date }}
                  </span>
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">
                  <span ng-if="act.start_time"><i class="fa-regular fa-clock"></i> {{ act.start_time | limitTo:5 }} - {{ act.end_time | limitTo:5 }}</span>
                  <span ng-if="!act.start_time">-</span>
                </td>
                <td style="font-weight: 600; color: var(--text-main);">
                  {{ act.activity_name }}
                </td>
                <td>
                  <span class="cat-pill">
                    <span class="cat-dot" ng-style="{'background-color': act.category_color}"></span>
                    {{ act.category_name }}
                  </span>
                </td>
                <td style="font-weight: 700;">
                  {{ formatMinutes(act.duration_minutes) }}
                </td>
                <td>
                  <span class="utility-tag" ng-class="act.utility_type === 'productive' ? 'tag-productive' : 'tag-unproductive'">
                    <i class="fa-solid" ng-class="act.utility_type === 'productive' ? 'fa-check' : 'fa-xmark'"></i>
                    {{ act.utility_type === 'productive' ? 'Useful' : 'Lost Time' }}
                  </span>
                </td>
                <td style="color: var(--text-muted); font-size: 0.83rem;">
                  {{ act.notes || '-' }}
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <!-- Add to Google Calendar Button -->
                  <button class="btn-gcal-sync" ng-click="addToGoogleCalendar(act)" title="Add to Google Calendar">
                    <i class="fa-brands fa-google"></i>
                  </button>
                  <!-- Delete Activity Button -->
                  <button class="btn-delete-act" ng-click="deleteActivity(act)" title="Delete Activity">
                    <i class="fa-solid fa-trash-can"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div class="empty-state" ng-if="activities.length === 0">
          <i class="fa-regular fa-calendar-xmark"></i>
          <h4>No activities logged for this day yet</h4>
          <p>Click "Log Activity" to start auditing your 24 hours.</p>
          <button class="nav-btn btn-primary" style="margin-top: 1rem;" ng-click="openModal()">
            <i class="fa-solid fa-plus"></i> Log Activity Now
          </button>
        </div>
      </section>
    </div>

    <!-- ================= TAB 2: CALENDAR SYNCHRONIZATION ================= -->
    <div ng-show="activeTab === 'calendar'">
      <div class="calendar-card">
        
        <!-- Calendar Header Navigation & Synchronization Actions -->
        <div class="calendar-header-bar">
          <div class="calendar-month-title">
            <button class="btn-icon-subtle" ng-click="shiftCalendarMonth(-1)" title="Previous Month">
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span>{{ calendarData.month_name }} {{ calendarYear }}</span>
            <button class="btn-icon-subtle" ng-click="shiftCalendarMonth(1)" title="Next Month">
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>

          <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <button class="nav-btn" ng-click="exportIcs()">
              <i class="fa-solid fa-calendar-arrow-down"></i> Download .ICS Calendar
            </button>
            <button class="nav-btn btn-primary" ng-click="copyCalendarFeedUrl()">
              <i class="fa-solid fa-link"></i> Copy Live Calendar Feed URL
            </button>
          </div>
        </div>

        <!-- Calendar Sync Instructions Banner -->
        <div class="calendar-sync-banner">
          <div class="sync-banner-text">
            <strong><i class="fa-solid fa-arrows-rotate"></i> Synchronize with Google Calendar or Apple Calendar:</strong><br>
            Use the Live Feed URL to subscribe in Google Calendar (under <em>"Other calendars" &gt; "From URL"</em>) or Apple Calendar. Your productive & lost time activities will stay continuously synced!
          </div>
        </div>

        <!-- Calendar Days Grid -->
        <div class="calendar-grid">
          <!-- Day Name Headers -->
          <div class="calendar-day-header">Sun</div>
          <div class="calendar-day-header">Mon</div>
          <div class="calendar-day-header">Tue</div>
          <div class="calendar-day-header">Wed</div>
          <div class="calendar-day-header">Thu</div>
          <div class="calendar-day-header">Fri</div>
          <div class="calendar-day-header">Sat</div>

          <!-- Empty Leading Slots -->
          <div class="calendar-day-cell empty-pad" ng-repeat="pad in calendarData.empty_prefix_days"></div>

          <!-- Month Days -->
          <div class="calendar-day-cell" 
               ng-repeat="day in calendarData.days" 
               ng-class="{'is-today': day.date === todayDate, 'is-selected': day.date === selectedDate}"
               ng-click="selectCalendarDay(day)"
               title="Click to view/log activities for {{ day.date }}">
            
            <div class="cal-day-num">
              <span>{{ day.day }}</span>
              <span class="cal-dot-indicator" 
                    ng-if="day.total_hours > 0" 
                    ng-style="{'background-color': day.productivity_rate >= 60 ? 'var(--productive)' : 'var(--unproductive)'}"></span>
            </div>

            <div class="cal-metrics-summary" ng-if="day.total_hours > 0">
              <span class="cal-badge cal-badge-prod" ng-if="day.productive_hours > 0">
                <i class="fa-solid fa-check"></i> {{ day.productive_hours }}h
              </span>
              <span class="cal-badge cal-badge-lost" ng-if="day.lost_hours > 0">
                <i class="fa-solid fa-xmark"></i> {{ day.lost_hours }}h
              </span>
            </div>

            <div style="font-size: 0.72rem; color: var(--text-dim); margin-top: auto;" ng-if="day.total_hours == 0">
              Untracked
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ================= TAB 3: ANALYTICS & TRENDS ================= -->
    <div ng-show="activeTab === 'analytics'">
      <div class="dashboard-grid">
        <!-- 7-Day Trend Chart -->
        <div class="card">
          <div class="card-title-bar">
            <h3 class="card-title"><i class="fa-solid fa-chart-column"></i> Weekly Productivity Trends</h3>
            <span class="badge badge-info">Last 7 Days</span>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Comparison of productive hours vs. lost hours day by day.
          </p>
          <div class="chart-container" style="height: 320px; max-height: 320px;">
            <canvas id="weeklyTrendsChart"></canvas>
          </div>
        </div>

        <!-- Peak Unproductive Hours Bar Chart -->
        <div class="card">
          <div class="card-title-bar">
            <h3 class="card-title"><i class="fa-solid fa-fire" style="color: var(--unproductive);"></i> Peak Unproductive Hours</h3>
            <span class="badge badge-danger" ng-if="worstHour">Worst: {{ worstHour.label }}</span>
          </div>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
            Identifies the specific hours of the day when distractions and lost time peak.
          </p>
          <div class="chart-container" style="height: 320px; max-height: 320px;">
            <canvas id="peakHoursChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Peak Hours Insight & Export Card -->
      <section class="card" style="margin-top: 1.5rem;">
        <div class="card-title-bar">
          <h3 class="card-title"><i class="fa-solid fa-file-waveform"></i> Summary Analytics & Data Export</h3>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 0.5rem;">
          <div style="background: var(--bg-card); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <h4 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-main);"><i class="fa-solid fa-clock-rotate-left"></i> Peak Distraction Insight</h4>
            <p style="font-size: 0.88rem; color: var(--text-muted); line-height: 1.5;" ng-if="worstHour">
              Your highest concentration of lost time is around <strong style="color: var(--unproductive);">{{ worstHour.label }}</strong> with {{ worstHour.lost_minutes }} total minutes lost. Schedule your breaks or high-demand tasks away from this window.
            </p>
            <p style="font-size: 0.88rem; color: var(--text-muted);" ng-if="!worstHour">
              Not enough hourly start time data yet. Log activities with start & end times to discover your peak distraction hours.
            </p>
          </div>

          <div style="background: var(--bg-card); padding: 1.25rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <h4 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--text-main);"><i class="fa-solid fa-file-export"></i> Download CSV Reports</h4>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem;">
              Export full historical logs and time usage data for external analysis or submission.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
              <button class="nav-btn btn-primary" ng-click="exportReport('week')">
                <i class="fa-solid fa-download"></i> Last 7 Days CSV
              </button>
              <button class="nav-btn" style="border-color: var(--border-color);" ng-click="exportReport('all')">
                <i class="fa-solid fa-database"></i> All History CSV
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

  </main>

  <!-- ================= ACTIVITY LOGGING MODAL ================= -->
  <div class="modal-overlay" ng-if="isModalOpen">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title"><i class="fa-solid fa-clock-rotate-left" style="color: var(--accent-primary);"></i> Log Daily Activity</h3>
        <button class="modal-close" ng-click="closeModal()"><i class="fa-solid fa-xmark"></i></button>
      </div>

      <div class="modal-body">
        <!-- Quick Preset Chips -->
        <div class="form-group">
          <label class="form-label">Quick Presets</label>
          <div class="quick-tags">
            <span class="quick-tag-chip" ng-repeat="preset in quickPresets" ng-click="applyPreset(preset)">
              {{ preset.name }}
            </span>
          </div>
        </div>

        <!-- Activity Name -->
        <div class="form-group">
          <label class="form-label">Activity Name *</label>
          <input type="text" class="form-input" placeholder="e.g. Studying DSA, Scrolling Instagram, Gaming" ng-model="newActivity.activity_name" required>
        </div>

        <!-- Category Dropdown -->
        <div class="form-group">
          <label class="form-label">Category *</label>
          <select class="form-select" ng-model="newActivity.category_id" ng-change="onCategoryChange()" required>
            <option value="">-- Select Category --</option>
            <option ng-repeat="cat in categories" ng-value="cat.id">{{ cat.name }}</option>
          </select>
        </div>

        <!-- Utility Type (Useful vs Lost Time) Selector -->
        <div class="form-group">
          <label class="form-label">Utility Classification *</label>
          <div class="utility-picker">
            <button type="button" class="utility-choice-btn" ng-class="{'selected-prod': newActivity.utility_type === 'productive'}" ng-click="newActivity.utility_type = 'productive'">
              <i class="fa-solid fa-circle-check"></i> Useful (Productive)
            </button>
            <button type="button" class="utility-choice-btn" ng-class="{'selected-lost': newActivity.utility_type === 'unproductive'}" ng-click="newActivity.utility_type = 'unproductive'">
              <i class="fa-solid fa-circle-xmark"></i> Lost Time (Unproductive)
            </button>
          </div>
        </div>

        <!-- Duration (Hours & Minutes) -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Hours</label>
            <input type="number" min="0" max="24" class="form-input" ng-model="newActivity.duration_hours">
          </div>
          <div class="form-group">
            <label class="form-label">Minutes</label>
            <input type="number" min="0" max="59" step="5" class="form-input" ng-model="newActivity.duration_minutes">
          </div>
        </div>

        <!-- Optional Start & End Time -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
              <span>Start Time (Optional)</span>
              <button type="button" style="background: none; border: none; color: var(--accent-primary); font-size: 0.75rem; cursor: pointer;" ng-click="setModalTimeNow()">
                <i class="fa-solid fa-clock"></i> Set Now
              </button>
            </label>
            <input type="time" class="form-input" ng-model="newActivity.start_time" ng-change="onTimeRangeChange()">
          </div>
          <div class="form-group">
            <label class="form-label">End Time (Optional)</label>
            <input type="time" class="form-input" ng-model="newActivity.end_time" ng-change="onTimeRangeChange()">
          </div>
        </div>

        <!-- Date & Notes -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
              <span>Log Date *</span>
              <button type="button" style="background: none; border: none; color: var(--accent-primary); font-size: 0.75rem; cursor: pointer;" ng-click="setModalDateToday()">
                <i class="fa-solid fa-calendar-day"></i> Set Today ({{ todayDate }})
              </button>
            </label>
            <input type="date" class="form-input" id="modalDateInput" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Reflection / Notes (Optional)</label>
            <input type="text" class="form-input" placeholder="e.g. Felt distracted after lunch" ng-model="newActivity.notes">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button class="nav-btn" style="border-color: var(--border-color);" ng-click="closeModal()">Cancel</button>
        <button class="nav-btn btn-primary" ng-click="saveActivity()">
          <i class="fa-solid fa-check"></i> Save Activity Log
        </button>
      </div>
    </div>
  </div>

  <!-- ================= EXTERNAL SCRIPTS ================= -->
  <!-- AngularJS 1.8.2 CDN -->
  <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.8.2/angular.min.js"></script>
  <!-- Chart.js 4.4.1 CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Application JS Modules -->
  <script src="assets/js/charts.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
