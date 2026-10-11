/**
 * LostTime - Chart.js Visualizations Manager
 */

const LostTimeCharts = {
  donutChartInstance: null,
  weeklyChartInstance: null,
  peakChartInstance: null,

  /**
   * Render or update the 24-Hour Day Audit Donut Chart
   */
  renderDailyDonut: function(canvasId, prodHours, lostHours, untrackedHours) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return;

    if (this.donutChartInstance) {
      this.donutChartInstance.destroy();
    }

    const dataValues = [
      Math.max(0, parseFloat(prodHours) || 0),
      Math.max(0, parseFloat(lostHours) || 0),
      Math.max(0, parseFloat(untrackedHours) || 0)
    ];

    this.donutChartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Productive (Useful)', 'Lost Time', 'Untracked / Off-hours'],
        datasets: [{
          data: dataValues,
          backgroundColor: ['#10b981', '#f43f5e', '#334155'],
          borderColor: '#182238',
          borderWidth: 3,
          hoverOffset: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: '#0f172a',
            titleColor: '#f8fafc',
            bodyColor: '#cbd5e1',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 10,
            callbacks: {
              label: function(context) {
                const val = context.raw || 0;
                return ` ${context.label}: ${val} hrs`;
              }
            }
          }
        }
      }
    });
  },

  /**
   * Render or update Weekly Comparison Bar Chart
   */
  renderWeeklyTrends: function(canvasId, weeklyData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !weeklyData || weeklyData.length === 0) return;

    if (this.weeklyChartInstance) {
      this.weeklyChartInstance.destroy();
    }

    const labels = weeklyData.map(d => d.label);
    const prodHours = weeklyData.map(d => d.productive_hours);
    const lostHours = weeklyData.map(d => d.lost_hours);

    this.weeklyChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Productive (Useful)',
            data: prodHours,
            backgroundColor: '#10b981',
            borderRadius: 6,
            maxBarThickness: 28
          },
          {
            label: 'Lost Time',
            data: lostHours,
            backgroundColor: '#f43f5e',
            borderRadius: 6,
            maxBarThickness: 28
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: '#94a3b8', font: { size: 11 } }
          },
          y: {
            grid: { color: 'rgba(255, 255, 255, 0.06)' },
            ticks: {
              color: '#94a3b8',
              font: { size: 11 },
              callback: function(val) { return val + 'h'; }
            },
            beginAtZero: true
          }
        },
        plugins: {
          legend: {
            position: 'top',
            labels: { color: '#cbd5e1', boxWidth: 12, usePointStyle: true }
          },
          tooltip: {
            backgroundColor: '#0f172a',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 10,
            callbacks: {
              label: function(context) {
                return ` ${context.dataset.label}: ${context.raw} hrs`;
              }
            }
          }
        }
      }
    });
  },

  /**
   * Render Peak Unproductive Hours Bar Chart
   */
  renderPeakHours: function(canvasId, peakHoursData) {
    const ctx = document.getElementById(canvasId);
    if (!ctx || !peakHoursData || peakHoursData.length === 0) return;

    if (this.peakChartInstance) {
      this.peakChartInstance.destroy();
    }

    const labels = peakHoursData.map(p => p.label);
    const lostMins = peakHoursData.map(p => p.lost_minutes);

    // Color gradient / highlight peaks
    const maxVal = Math.max(...lostMins);
    const backgroundColors = lostMins.map(val => {
      if (val === 0) return 'rgba(244, 63, 94, 0.15)';
      if (val === maxVal && val > 0) return '#f43f5e'; // Peak highlight
      return 'rgba(244, 63, 94, 0.6)';
    });

    this.peakChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Minutes Wasted',
          data: lostMins,
          backgroundColor: backgroundColors,
          borderRadius: 4,
          maxBarThickness: 16
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: {
            grid: { display: false },
            ticks: {
              color: '#94a3b8',
              font: { size: 10 },
              maxRotation: 45,
              minRotation: 45
            }
          },
          y: {
            grid: { color: 'rgba(255, 255, 255, 0.06)' },
            ticks: {
              color: '#94a3b8',
              font: { size: 11 },
              callback: function(val) { return val + 'm'; }
            },
            beginAtZero: true
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#0f172a',
            borderColor: '#334155',
            borderWidth: 1,
            padding: 10,
            callbacks: {
              label: function(context) {
                const mins = context.raw;
                const hrs = (mins / 60).toFixed(1);
                return ` Wasted: ${mins} mins (${hrs} hrs)`;
              }
            }
          }
        }
      }
    });
  }
};

