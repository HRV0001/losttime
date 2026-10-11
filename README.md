# ⏳ LostTime — Daily Time Audit & Productivity Intelligence

**LostTime** is a lightweight, responsive web application designed to track daily time usage, audit the 24-hour day, categorize activities as **Productive (Useful)** or **Unproductive ("Lost Time")**, identify peak unproductive hours, provide smart habit suggestions, and synchronize seamlessly with external calendars.

Built with **HTML5, PHP, CSS3, JavaScript, AngularJS (1.8.2), and MySQL**.

---

## 📁 Project Folder Structure

```text
losttime/
│
├── api/                        # Backend RESTful API endpoints (JSON & iCal)
│   ├── activities.php          # CRUD operations for logging activities (user-scoped)
│   ├── analytics.php           # Calculates daily 24h split, weekly trends & peak hours
│   ├── auth.php                # User login, registration, and session verification
│   ├── calendar.php            # Monthly calendar aggregation, .ics export & live feed
│   ├── export.php              # Export activity logs as CSV report
│   └── suggestions.php         # Smart productive suggestion engine
│
├── assets/                     # Frontend static assets
│   ├── css/
│   │   └── style.css           # Clean modern UI styling, calendar grid & theme
│   └── js/
│       ├── app.js              # AngularJS controllers, calendar sync & state management
│       └── charts.js           # Chart.js (24h donut chart & weekly trends)
│
├── config/
│   ├── auth.php                # Authentication helper & session guard
│   └── db.php                  # PDO database connection with auto-port detection
│
├── database/
│   └── losttime.sql            # MySQL schema & starter seed data (users, categories, logs)
│
├── index.php                   # Main Dashboard, Calendar & Time Tracker interface
├── login.php                   # User Authentication portal (Sign In & Sign Up)
├── logout.php                  # Session termination & redirect
├── reports.php                 # Summary analytics & print-to-PDF report page
├── .gitignore                  # Git ignore rules
└── README.md                   # Project documentation & setup instructions
```

---

## 🎯 Key Features & Modules

### 1. 🔐 Authentication & Multi-User System (`login.php`, `api/auth.php`)
- **User Registration & Login**: Secure account creation with bcrypt password hashing (`password_hash`).
- **PHP Session Management**: Scoped user sessions (`$_SESSION['user_id']`). Every user only sees their own logged activities, reports, and calendar.
- **1-Click Demo Login**: Preloaded button on the login screen for testing:
  - **Email**: `demo@losttime.com`
  - **Password**: `password123`
- **Navigation Profile & Logout**: Top bar shows the user avatar, name, and logout action.

### 2. 📅 Calendar Synchronization System (`index.php`, `api/calendar.php`)
- **Interactive Monthly Calendar View**:
  - Month/Year navigator (`< Month Year >`).
  - Color-coded badges on each day showing **Productive Hours** (`+4.2h` in green) vs. **Lost Hours** (`-3.8h` in red).
  - Productivity dot indicators based on efficiency percentage.
  - Clicking any date instantly loads that specific day's breakdown and activity stream!
- **iCalendar (.ICS) Export**: One-click download of RFC 5545 `.ics` file for importing all tracked activities into **Google Calendar, Microsoft Outlook, or Apple Calendar**.
- **Live Calendar Subscription Feed**: Unique per-user WebCal / iCal feed URL (`api/calendar.php?action=feed&token=...`) that you can subscribe to in Google Calendar (*"Other calendars" > "From URL"*) for automatic live synchronization.
- **1-Click "Add to Google Calendar"**: Dedicated Google Calendar button next to each activity in the activity stream to schedule or save the session directly.

### 3. ⏱️ Time & Activity Logging (`index.php`, `api/activities.php`)
- Fast activity entry with presets (*DSA Practice*, *Instagram Doomscrolling*, *Web Dev Coding*, *Gaming*).
- Duration inputs (hours & minutes) or start & end times with auto-duration calculation.
- Utility Tagging: **Useful (Productive)** vs. **Not Useful (Lost Time)** with automatic default selection based on category.

### 4. 📊 Daily Dashboard & 24h Breakdown (`index.php`, `assets/js/charts.js`)
- **24-Hour Day Audit**: Total logged time vs. Untracked time in a 24-hour day.
- **Visual Breakdown**: Interactive donut chart & stacked progress bar showing the split between **Productive Time** and **Lost Time**.
- **Hero Metric**: **"Hours Lost Today"** counter with status badges.

### 5. 🧠 Smart Productive Suggestions Engine (`api/suggestions.php`, `assets/js/app.js`)
- Contextually analyzes logged unproductive time.
- Suggests actionable habit swaps (e.g., *"Swap 90 mins of Gaming for 3 DSA Problems"*, *"20-Minute Focused Reading Sprint"*).
- Clickable **"Adopt Swap"** button to immediately pre-fill and log the productive habit.

### 6. 📈 Reports, Trends & Analytics (`reports.php`, `api/analytics.php`)
- **Weekly Trends**: 7-day comparative bar chart of productive vs. lost hours.
- **Peak Unproductive Hours**: 24-hour distribution identifying when distractions happen most frequently (e.g., 2:00 PM).
- **Export & Print**: CSV export and printable summary report formatted with `@media print`.

---

## 🗄️ Database Schema (`database/losttime.sql`)

```sql
-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    calendar_token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Categories Table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    default_utility ENUM('productive', 'unproductive') NOT NULL,
    color VARCHAR(20) DEFAULT '#6366f1',
    icon VARCHAR(40) DEFAULT 'fa-clock'
);

-- 3. Activities Table
CREATE TABLE activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_name VARCHAR(150) NOT NULL,
    category_id INT NOT NULL,
    duration_minutes INT NOT NULL,
    utility_type ENUM('productive', 'unproductive') NOT NULL,
    log_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- 4. Suggestions Catalog Table
CREATE TABLE suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    title VARCHAR(120) NOT NULL,
    suggestion_text TEXT NOT NULL,
    action_type VARCHAR(50) DEFAULT 'micro_habit',
    estimated_minutes INT DEFAULT 20,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);
```

---

## 🚀 Running the Application in XAMPP

1. Open **XAMPP Control Panel** and ensure **Apache** and **MySQL** are running.
2. Open your browser and navigate to:
   👉 **`http://localhost/losttime/`**
3. If not logged in, you will be directed to `login.php`. Click **"1-Click Demo Login"** or register a new account!
