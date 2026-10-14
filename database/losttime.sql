-- LostTime Database Schema & Seed Data
-- Database: losttime_db

-- CREATE DATABASE IF NOT EXISTS losttime_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE losttime_db;

DROP TABLE IF EXISTS activities;
DROP TABLE IF EXISTS suggestions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    calendar_token VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,
    default_utility ENUM('productive', 'unproductive') NOT NULL,
    color VARCHAR(20) DEFAULT '#6366f1',
    icon VARCHAR(40) DEFAULT 'fa-clock'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Activities Table
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 1,
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
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, log_date),
    INDEX idx_utility (utility_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Suggestions Catalog Table
CREATE TABLE IF NOT EXISTS suggestions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    title VARCHAR(120) NOT NULL,
    suggestion_text TEXT NOT NULL,
    action_type VARCHAR(50) DEFAULT 'micro_habit',
    estimated_minutes INT DEFAULT 20,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clear previous data
DELETE FROM activities;
DELETE FROM suggestions;
DELETE FROM categories;
DELETE FROM users;

-- Seed Default Demo User
-- Email: demo@losttime.com | Password: password123
INSERT INTO users (id, name, email, password, calendar_token) VALUES
(1, 'Demo User', 'demo@losttime.com', '$2y$10$A8s5JP.wvYeyalJjWvHZ3uV.fnjRiJuCCq3of2bQDCXVb/yCXHCbq', 'demo_calendar_token_8a92f03b4');

-- Seed Default Categories
INSERT INTO categories (id, name, default_utility, color, icon) VALUES
(1, 'Study & Coding', 'productive', '#10b981', 'fa-book-open'),
(2, 'Work & Projects', 'productive', '#3b82f6', 'fa-laptop-code'),
(3, 'Health & Exercise', 'productive', '#06b6d4', 'fa-dumbbell'),
(4, 'Chores & Personal Care', 'productive', '#8b5cf6', 'fa-house'),
(5, 'Social Media & Feeds', 'unproductive', '#f43f5e', 'fa-mobile-screen'),
(6, 'Gaming & Streaming', 'unproductive', '#f59e0b', 'fa-gamepad'),
(7, 'Idle Browsing & Reels', 'unproductive', '#ec4899', 'fa-film');

-- Seed Smart Suggestion Rules
INSERT INTO suggestions (category_id, title, suggestion_text, action_type, estimated_minutes) VALUES
(5, 'Replace Social Media with DSA', 'Instead of scrolling feeds, solve 1 Medium problem on LeetCode or review Array techniques.', 'learning', 25),
(5, 'Read 10 Pages of Non-Fiction', 'Swap idle screen time for a chapter in an insightful book (e.g. Atomic Habits or Deep Work).', 'reading', 20),
(5, 'Hydrate & Quick Posture Stretch', 'Take a screen break. Do 10 bodyweight squats and 5 minutes of mindful breathing.', 'wellness', 10),
(6, 'Mini Coding Sprint', 'Convert 30 minutes of gaming into building a mini feature or exploring a new GitHub repo.', 'learning', 30),
(6, 'Take a 15-Minute Brisk Walk', 'Get some fresh air without your phone to reset dopamine levels.', 'wellness', 15),
(7, 'Pomodoro Focus Session', 'Reset with a 25-minute Pomodoro timer dedicated purely to deep study or work.', 'productivity', 25),
(NULL, 'Declutter Your Workspace', 'A clean physical workspace directly reduces mental fatigue and impulse distractions.', 'environment', 15),
(NULL, 'Plan Tomorrow’s Top 3 Priorities', 'Take 10 minutes to write down your essential 3 goals for tomorrow to start with clarity.', 'planning', 10);

-- Seed Sample Demo Activities for User 1 (Today and recent days)
INSERT INTO activities (user_id, activity_name, category_id, duration_minutes, utility_type, log_date, start_time, end_time, notes) VALUES
(1, 'DSA Practice - Binary Search Trees', 1, 90, 'productive', CURDATE(), '09:00:00', '10:30:00', 'Solved 3 problems on LeetCode'),
(1, 'Instagram Reels & TikTok', 5, 75, 'unproductive', CURDATE(), '11:00:00', '12:15:00', 'Doomscrolled explore feed'),
(1, 'Web App Development (Frontend UI)', 2, 120, 'productive', CURDATE(), '13:00:00', '15:00:00', 'Designed dashboard components'),
(1, 'YouTube Gaming Streams', 6, 90, 'unproductive', CURDATE(), '15:30:00', '17:00:00', 'Watched game playthroughs'),
(1, 'Evening Jog & Stretching', 3, 45, 'productive', CURDATE(), '17:30:00', '18:15:00', 'Outdoor 4km run'),
(1, 'Random Twitter & Reddit Scrolling', 7, 60, 'unproductive', CURDATE(), '21:00:00', '22:00:00', 'Lost track of time before bed'),

-- Yesterday logs
(1, 'Object-Oriented Programming Revision', 1, 110, 'productive', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00:00', '11:50:00', 'Polymorphism and design patterns'),
(1, 'Mobile Gaming (BGMI)', 6, 120, 'unproductive', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:00:00', '16:00:00', 'Played matches with friends'),
(1, 'Project Documentation & Report', 2, 90, 'productive', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '16:30:00', '18:00:00', 'Wrote system architecture'),
(1, 'Reels Doomscrolling', 5, 80, 'unproductive', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '22:00:00', '23:20:00', 'Fell into recommendation rabbit hole'),

-- 2 Days ago logs
(1, 'Full Stack Database Setup', 2, 150, 'productive', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '09:30:00', '12:00:00', 'MySQL schema design'),
(1, 'Netflix Binge Watching', 6, 180, 'unproductive', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '14:00:00', '17:00:00', '3 episodes of sci-fi series'),
(1, 'Gym Workout', 3, 60, 'productive', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '18:00:00', '19:00:00', 'Chest and triceps day');
