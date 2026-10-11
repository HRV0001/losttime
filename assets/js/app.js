/**
 * LostTime - AngularJS Application
 * Version: AngularJS 1.8.2
 * Date Synchronization Engine & Controllers
 */

// Helper: Formats a Date object or string to YYYY-MM-DD in LOCAL system timezone
function getLocalDateString(d) {
  if (!d) d = new Date();
  if (typeof d === 'string') {
    if (/^\d{4}-\d{2}-\d{2}$/.test(d)) return d;
    d = new Date(d);
  }
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

// Helper: Formats a date string to human-readable form (Today, Yesterday, etc.)
function formatDisplayDate(dateStr) {
  if (!dateStr) return '';
  const parts = dateStr.split('-').map(Number);
  const dt = new Date(parts[0], parts[1] - 1, parts[2]);
  
  const todayStr = getLocalDateString(new Date());
  
  const yest = new Date();
  yest.setDate(yest.getDate() - 1);
  const yesterdayStr = getLocalDateString(yest);

  const tom = new Date();
  tom.setDate(tom.getDate() + 1);
  const tomorrowStr = getLocalDateString(tom);

  const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
  const formatted = dt.toLocaleDateString(undefined, options);

  if (dateStr === todayStr) {
    return `Today (${formatted})`;
  } else if (dateStr === yesterdayStr) {
    return `Yesterday (${formatted})`;
  } else if (dateStr === tomorrowStr) {
    return `Tomorrow (${formatted})`;
  }
  return formatted;
}

const app = angular.module('lostTimeApp', []);

app.controller('DashboardCtrl', ['$scope', '$http', '$timeout', function($scope, $http, $timeout) {
  // Navigation & State
  $scope.activeTab = 'dashboard'; // 'dashboard', 'calendar', or 'analytics'
  $scope.activitiesViewMode = 'single'; // 'single' (active date) or 'all' (all history)

  // System Local Date — Guarantees initial date is always today
  $scope.todayDate = getLocalDateString(new Date());
  $scope.selectedDate = $scope.todayDate;
  $scope.displayDateFormatted = formatDisplayDate($scope.selectedDate);

  $scope.isLoading = false;
  $scope.isModalOpen = false;

  // User Profile
  $scope.currentUser = null;

  // Data Stores
  $scope.categories = [];
  $scope.activities = [];
  $scope.loggedDates = []; // List of distinct dates with logged activity
  $scope.dailyMetrics = {
    productive_minutes: 0,
    productive_hours: 0,
    lost_minutes: 0,
    lost_hours: 0,
    total_logged_minutes: 0,
    total_logged_hours: 0,
    untracked_minutes: 1440,
    untracked_hours: 24,
    productivity_rate: 0,
    category_breakdown: []
  };
  $scope.weeklyTrends = [];
  $scope.peakHours = [];
  $scope.worstHour = null;
  $scope.suggestions = [];

  // Calendar Grid State
  const now = new Date();
  $scope.calendarYear = now.getFullYear();
  $scope.calendarMonth = now.getMonth() + 1; // 1-12
  $scope.calendarData = {
    month_name: '',
    first_day_of_week: 0,
    days: [],
    empty_prefix_days: [],
    calendar_feed_url: ''
  };

  // Quick preset chips
  $scope.quickPresets = [
    { name: 'DSA Practice', catId: 1, utility: 'productive' },
    { name: 'Instagram Doomscrolling', catId: 5, utility: 'unproductive' },
    { name: 'Web Dev Coding', catId: 2, utility: 'productive' },
    { name: 'YouTube Streams', catId: 6, utility: 'unproductive' },
    { name: 'Gym & Cardio', catId: 3, utility: 'productive' },
    { name: 'Twitter/Reddit Feeds', catId: 7, utility: 'unproductive' }
  ];

  // Activity Form Model
  $scope.resetForm = function() {
    $scope.newActivity = {
      activity_name: '',
      category_id: '',
      duration_hours: 1,
      duration_minutes: 0,
      start_time: '',
      end_time: '',
      utility_type: 'productive',
      log_date: $scope.selectedDate || $scope.todayDate,
      notes: ''
    };
  };
  $scope.resetForm();

  // Toast / Alert Notification
  $scope.alert = { show: false, message: '', type: 'success' };
  $scope.showAlert = function(msg, type = 'success') {
    $scope.alert = { show: true, message: msg, type: type };
    $timeout(function() {
      $scope.alert.show = false;
    }, 4500);
  };

  /**
   * CENTRAL DATE SYNCHRONIZER
   * Updates the active date across all components and reloads data
   */
  $scope.setDate = function(dateStr) {
    if (!dateStr) return;
    $scope.selectedDate = dateStr;
    $scope.displayDateFormatted = formatDisplayDate(dateStr);
    
    // Synchronize HTML5 Datepicker element in DOM
    const el = document.getElementById('globalDateSelector');
    if (el && el.value !== dateStr) {
      el.value = dateStr;
    }

    $scope.activitiesViewMode = 'single';
    $scope.loadData();
  };

  /**
   * Jump to Today
   */
  $scope.setToday = function() {
    $scope.setDate($scope.todayDate);
  };

  /**
   * Shift Date by N days (Previous / Next)
   */
  $scope.shiftDate = function(days) {
    const parts = $scope.selectedDate.split('-').map(Number);
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    d.setDate(d.getDate() + days);
    $scope.setDate(getLocalDateString(d));
  };

  /**
   * Modal quick date & time helpers
   */
  $scope.setModalDateToday = function() {
    $scope.newActivity.log_date = $scope.todayDate;
    const modalInput = document.getElementById('modalDateInput');
    if (modalInput) modalInput.value = $scope.todayDate;
  };

  $scope.setModalTimeNow = function() {
    const cur = new Date();
    const hh = String(cur.getHours()).padStart(2, '0');
    const mm = String(cur.getMinutes()).padStart(2, '0');
    $scope.newActivity.start_time = `${hh}:${mm}`;
  };

  /**
   * Toggle between active single date view and all history view
   */
  $scope.toggleActivitiesView = function(mode) {
    $scope.activitiesViewMode = mode;
    if (mode === 'all') {
      $scope.loadAllActivities();
    } else {
      $scope.loadData();
    }
  };

  $scope.loadAllActivities = function() {
    $scope.isLoading = true;
    $http.get('api/activities.php?all=true')
      .then(function(res) {
        if (res.data.success) {
          $scope.activities = res.data.activities;
          $scope.categories = res.data.categories;
          $scope.loggedDates = res.data.logged_dates || [];
        }
      })
      .finally(function() {
        $scope.isLoading = false;
      });
  };

  /**
   * Check Current Logged-in User
   */
  $scope.checkAuth = function() {
    $http.get('api/auth.php?action=me')
      .then(function(res) {
        if (res.data.success && res.data.authenticated) {
          $scope.currentUser = res.data.user;
        } else {
          window.location.href = 'login.php';
        }
      })
      .catch(function() {
        $scope.currentUser = { id: 1, name: 'Demo User', email: 'demo@losttime.com' };
      });
  };

  /**
   * Fetch All Data for the Active Selected Date
   */
  $scope.loadData = function() {
    $scope.isLoading = true;
    const date = $scope.selectedDate;
    $scope.displayDateFormatted = formatDisplayDate(date);

    // 1. Fetch Activities & Categories for Date
    $http.get(`api/activities.php?date=${date}`)
      .then(function(res) {
        if (res.data.success) {
          $scope.categories = res.data.categories;
          $scope.activities = res.data.activities;
          $scope.loggedDates = res.data.logged_dates || [];
        }
      })
      .catch(function(err) {
        if (err.status === 401) window.location.href = 'login.php';
        console.error('Error fetching activities:', err);
      });

    // 2. Fetch Analytics for Date
    $http.get(`api/analytics.php?date=${date}`)
      .then(function(res) {
        if (res.data.success) {
          $scope.dailyMetrics = res.data.daily;
          $scope.weeklyTrends = res.data.weekly;
          $scope.peakHours = res.data.peak_hours;
          $scope.worstHour = res.data.worst_hour;

          // Update Charts
          $timeout(function() {
            $scope.updateCharts();
          }, 50);
        }
      })
      .catch(function(err) {
        console.error('Error fetching analytics:', err);
      });

    // 3. Fetch Smart Suggestions for Date
    $http.get(`api/suggestions.php?date=${date}`)
      .then(function(res) {
        if (res.data.success) {
          $scope.suggestions = res.data.suggestions;
        }
      })
      .catch(function(err) {
        console.error('Error fetching suggestions:', err);
      })
      .finally(function() {
        $scope.isLoading = false;
      });
  };

  /**
   * Calendar Data Loader
   */
  $scope.loadCalendar = function(year, month) {
    $http.get(`api/calendar.php?action=month&year=${year}&month=${month}`)
      .then(function(res) {
        if (res.data.success) {
          const d = res.data;
          const emptyPrefix = [];
          for (let i = 0; i < d.first_day_of_week; i++) {
            emptyPrefix.push(i);
          }
          $scope.calendarData = {
            month_name: d.month_name,
            first_day_of_week: d.first_day_of_week,
            days: d.days,
            empty_prefix_days: emptyPrefix,
            calendar_feed_url: d.calendar_feed_url
          };
        }
      })
      .catch(function(err) {
        console.error('Error fetching calendar data:', err);
      });
  };

  $scope.shiftCalendarMonth = function(offset) {
    let m = $scope.calendarMonth + offset;
    let y = $scope.calendarYear;
    if (m > 12) {
      m = 1;
      y++;
    } else if (m < 1) {
      m = 12;
      y--;
    }
    $scope.calendarMonth = m;
    $scope.calendarYear = y;
    $scope.loadCalendar(y, m);
  };

  /**
   * Synchronize Calendar Cell Click with Dashboard & Date
   */
  $scope.selectCalendarDay = function(day) {
    if (!day || !day.date) return;
    $scope.setDate(day.date);
    $scope.activeTab = 'dashboard';
    $scope.showAlert(`Switched to ${$scope.displayDateFormatted}`);
  };

  $scope.exportIcs = function() {
    window.location.href = `api/calendar.php?action=export_ics&year=${$scope.calendarYear}&month=${$scope.calendarMonth}`;
  };

  $scope.copyCalendarFeedUrl = function() {
    if (!$scope.calendarData.calendar_feed_url) return;
    navigator.clipboard.writeText($scope.calendarData.calendar_feed_url).then(function() {
      $scope.showAlert('Live calendar sync feed URL copied to clipboard! Subscribe in Google Calendar or Apple Calendar.');
    }).catch(function() {
      prompt('Copy your Live Calendar Feed URL:', $scope.calendarData.calendar_feed_url);
    });
  };

  $scope.addToGoogleCalendar = function(act) {
    const title = encodeURIComponent((act.utility_type === 'productive' ? '[Productive] ' : '[Lost Time] ') + act.activity_name);
    const dateClean = act.log_date.replace(/-/g, '');
    let startClean = '090000';
    let endClean = '100000';

    if (act.start_time) {
      startClean = act.start_time.replace(/:/g, '').substring(0, 4) + '00';
    }
    if (act.end_time) {
      endClean = act.end_time.replace(/:/g, '').substring(0, 4) + '00';
    } else {
      const mins = parseInt(act.duration_minutes) || 60;
      const sh = parseInt(startClean.substring(0, 2));
      const sm = parseInt(startClean.substring(2, 4));
      const totalM = sh * 60 + sm + mins;
      const eh = Math.min(23, Math.floor(totalM / 60));
      const em = totalM % 60;
      endClean = String(eh).padStart(2, '0') + String(em).padStart(2, '0') + '00';
    }

    const dates = `${dateClean}T${startClean}/${dateClean}T${endClean}`;
    const details = encodeURIComponent(`Category: ${act.category_name || ''}\nUtility: ${act.utility_type}\nDuration: ${act.duration_minutes} mins\nNotes: ${act.notes || 'Tracked on LostTime'}`);
    const gcalUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${dates}&details=${details}`;
    window.open(gcalUrl, '_blank');
  };

  /**
   * Update visual Chart.js graphs
   */
  $scope.updateCharts = function() {
    if ($scope.activeTab === 'dashboard') {
      LostTimeCharts.renderDailyDonut(
        'dailyDonutChart',
        $scope.dailyMetrics.productive_hours,
        $scope.dailyMetrics.lost_hours,
        $scope.dailyMetrics.untracked_hours
      );
    } else if ($scope.activeTab === 'analytics') {
      LostTimeCharts.renderWeeklyTrends('weeklyTrendsChart', $scope.weeklyTrends);
      LostTimeCharts.renderPeakHours('peakHoursChart', $scope.peakHours);
    }
  };

  /**
   * Tab Navigation Switch
   */
  $scope.setTab = function(tab) {
    $scope.activeTab = tab;
    if (tab === 'calendar') {
      $scope.loadCalendar($scope.calendarYear, $scope.calendarMonth);
    } else {
      $timeout(function() {
        $scope.updateCharts();
      }, 50);
    }
  };

  /**
   * Modal Controls
   */
  $scope.openModal = function() {
    $scope.newActivity.log_date = $scope.selectedDate || $scope.todayDate;
    $scope.isModalOpen = true;

    // Direct DOM sync ensures date is NEVER blank
    $timeout(function() {
      const modalInput = document.getElementById('modalDateInput');
      if (modalInput) {
        modalInput.value = $scope.newActivity.log_date;
        modalInput.onchange = function() {
          $scope.$apply(function() {
            $scope.newActivity.log_date = modalInput.value;
          });
        };
      }
    }, 20);
  };

  $scope.closeModal = function() {
    $scope.isModalOpen = false;
  };

  /**
   * Auto-select default utility when category changes
   */
  $scope.onCategoryChange = function() {
    if (!$scope.newActivity.category_id) return;
    const selected = $scope.categories.find(c => c.id == $scope.newActivity.category_id);
    if (selected) {
      $scope.newActivity.utility_type = selected.default_utility;
    }
  };

  /**
   * Fast preset click
   */
  $scope.applyPreset = function(preset) {
    $scope.newActivity.activity_name = preset.name;
    $scope.newActivity.category_id = preset.catId;
    $scope.newActivity.utility_type = preset.utility;
  };

  /**
   * Calculate duration automatically if start and end times provided
   */
  $scope.onTimeRangeChange = function() {
    if ($scope.newActivity.start_time && $scope.newActivity.end_time) {
      const [sh, sm] = $scope.newActivity.start_time.split(':').map(Number);
      const [eh, em] = $scope.newActivity.end_time.split(':').map(Number);
      const diff = (eh * 60 + em) - (sh * 60 + sm);
      if (diff > 0) {
        $scope.newActivity.duration_hours = Math.floor(diff / 60);
        $scope.newActivity.duration_minutes = diff % 60;
      }
    }
  };

  /**
   * Save Activity Log
   */
  $scope.saveActivity = function() {
    const totalMins = (parseInt($scope.newActivity.duration_hours) || 0) * 60 + (parseInt($scope.newActivity.duration_minutes) || 0);
    
    // Always retrieve active value directly from DOM or model
    const modalInput = document.getElementById('modalDateInput');
    const chosenDate = (modalInput && modalInput.value) 
      ? modalInput.value 
      : ($scope.newActivity.log_date || $scope.selectedDate || $scope.todayDate);

    if (!$scope.newActivity.activity_name.trim()) {
      $scope.showAlert('Please enter an activity name.', 'danger');
      return;
    }
    if (!$scope.newActivity.category_id) {
      $scope.showAlert('Please choose an activity category.', 'danger');
      return;
    }
    if (totalMins <= 0) {
      $scope.showAlert('Duration must be at least 1 minute.', 'danger');
      return;
    }

    const payload = {
      activity_name: $scope.newActivity.activity_name,
      category_id: $scope.newActivity.category_id,
      duration_minutes: totalMins,
      utility_type: $scope.newActivity.utility_type,
      log_date: chosenDate,
      start_time: $scope.newActivity.start_time || null,
      end_time: $scope.newActivity.end_time || null,
      notes: $scope.newActivity.notes
    };

    $http.post('api/activities.php', payload)
      .then(function(res) {
        if (res.data.success) {
          $scope.showAlert(`Activity successfully logged for ${formatDisplayDate(chosenDate)}!`, 'success');
          $scope.closeModal();
          $scope.resetForm();

          // Immediately navigate to the exact logged date of the activity!
          $scope.setDate(chosenDate);

          if ($scope.activeTab === 'calendar') {
            $scope.loadCalendar($scope.calendarYear, $scope.calendarMonth);
          }
        } else {
          $scope.showAlert(res.data.message || 'Could not save activity.', 'danger');
        }
      })
      .catch(function(err) {
        $scope.showAlert('Server error while saving activity.', 'danger');
      });
  };

  /**
   * Delete Activity
   */
  $scope.deleteActivity = function(act) {
    if (!confirm(`Delete "${act.activity_name}"?`)) return;

    $http.delete(`api/activities.php?id=${act.id}`)
      .then(function(res) {
        if (res.data.success) {
          $scope.showAlert('Activity removed.', 'success');
          $scope.loadData();
          if ($scope.activeTab === 'calendar') {
            $scope.loadCalendar($scope.calendarYear, $scope.calendarMonth);
          }
        } else {
          $scope.showAlert(res.data.message || 'Could not delete.', 'danger');
        }
      })
      .catch(function(err) {
        $scope.showAlert('Error deleting activity.', 'danger');
      });
  };

  /**
   * Adopt a suggestion directly into form
   */
  $scope.adoptSuggestion = function(sug) {
    $scope.openModal();
    $scope.newActivity.activity_name = sug.title;
    $scope.newActivity.utility_type = 'productive';
    $scope.newActivity.duration_hours = 0;
    $scope.newActivity.duration_minutes = sug.estimated_minutes || 25;
    $scope.newActivity.notes = `Adopted suggestion: ${sug.description}`;
    const prodCat = $scope.categories.find(c => c.default_utility === 'productive');
    if (prodCat) {
      $scope.newActivity.category_id = prodCat.id;
    }
  };

  /**
   * CSV Export trigger
   */
  $scope.exportReport = function(period = 'day') {
    window.location.href = `api/export.php?period=${period}&date=${$scope.selectedDate}`;
  };

  // Helper formatters
  $scope.formatMinutes = function(mins) {
    mins = parseInt(mins) || 0;
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
  };

  // Connect Top Date Input Element Immediately
  $timeout(function() {
    const el = document.getElementById('globalDateSelector');
    if (el) {
      el.value = $scope.selectedDate;
      el.onchange = function() {
        if (el.value) {
          $scope.$apply(function() {
            $scope.setDate(el.value);
          });
        }
      };
      el.oninput = function() {
        if (el.value && el.value.length === 10) {
          $scope.$apply(function() {
            $scope.setDate(el.value);
          });
        }
      };
    }
  }, 10);

  // Initial Load
  $scope.checkAuth();
  $scope.loadData();
}]);
