-- ============================================================
--  BMI Monitor Database Setup
--  Run this file in phpMyAdmin or MySQL CLI to set up the DB
-- ============================================================

-- Create the database
CREATE DATABASE IF NOT EXISTS bmi_monitor;
USE bmi_monitor;

-- ============================================================
-- USERS TABLE
-- Stores registered user accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,       -- bcrypt hashed
    role        ENUM('user','admin') DEFAULT 'user',
    alert_email VARCHAR(150)        NULL,          -- fixed recipient for auto-triggered reports
    -- Profile fields used as inputs to the ML recommendation model
    age             INT                                                     NULL,
    gender          ENUM('Male','Female')                                   NULL,
    activity_level  ENUM('Sedentary','Lightly Active','Moderately Active','Very Active') NULL,
    fitness_goal    ENUM('Lose Weight','Maintain Weight','Gain Weight')     NULL,
    created_at  TIMESTAMP           DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- BMI RECORDS TABLE
-- Stores every BMI reading sent by the ESP32 (or manual entry)
-- ============================================================
CREATE TABLE IF NOT EXISTS bmi_records (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    height      DECIMAL(5,2)    NOT NULL,   -- in centimetres
    weight      DECIMAL(5,2)    NOT NULL,   -- in kilograms
    bmi         DECIMAL(5,2)    NOT NULL,
    -- Cached output of the ML recommendation model (api/bmi.php fills
    -- these in at insert time by calling the Python ml_service). NULL
    -- until the ML service has answered for this record.
    overall_recommendation  VARCHAR(100)  NULL,
    exercise_plan           VARCHAR(100)  NULL,
    diet_recommendation     VARCHAR(150)  NULL,
    recommended_calories    INT           NULL,
    protein_g_per_day       INT           NULL,
    water_l_per_day         DECIMAL(4,1)  NULL,
    sleep_hours             VARCHAR(20)   NULL,
    -- Agent's evaluation of this reading against the user's history/goal
    -- (see agent_service/) — cached here so the dashboard needs no join.
    agent_decision          VARCHAR(20)   NULL,
    agent_summary           TEXT          NULL,
    recorded_at TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- EMAIL LOGS TABLE
-- Records every BMI report email sent from the website
-- ============================================================
CREATE TABLE IF NOT EXISTS email_logs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    record_id    INT             NOT NULL,
    sent_by      INT             NOT NULL,   -- user id of whoever clicked "send"
    sent_to      VARCHAR(150)    NOT NULL,   -- recipient email address
    recipient_name VARCHAR(100)  DEFAULT NULL,
    note         VARCHAR(500)    DEFAULT NULL,
    status       ENUM('sent','failed') DEFAULT 'sent',
    error_msg    VARCHAR(255)    DEFAULT NULL,
    sent_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (record_id) REFERENCES bmi_records(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by)   REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- AGENT CONVERSATIONS TABLE
-- Chat memory for the agentic AI layer (see agent_service/)
-- ============================================================
CREATE TABLE IF NOT EXISTS agent_conversations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT                      NOT NULL,
    role        ENUM('user','assistant') NOT NULL,
    message     TEXT                     NOT NULL,
    created_at  TIMESTAMP                DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- AGENT EVENTS TABLE
-- Audit trail: every decision the agent made and why
-- ============================================================
CREATE TABLE IF NOT EXISTS agent_events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT                                    NOT NULL,
    bmi_record_id   INT                                    NULL,
    decision        ENUM('on_track','needs_attention')     NOT NULL,
    summary         TEXT                                   NOT NULL,
    created_at      TIMESTAMP                               DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bmi_record_id) REFERENCES bmi_records(id) ON DELETE SET NULL
);

-- ============================================================
-- DEFAULT ADMIN ACCOUNT
-- Password: admin123  (bcrypt hash below)
-- Change this immediately in production!
-- ============================================================
INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@bmimonitor.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'admin');

-- ============================================================
-- SAMPLE USER (for testing)
-- Password: password
-- Profile fields are pre-filled so the AI Recommendation card on the
-- dashboard works immediately, without needing to fill in the profile
-- form first.
-- ============================================================
INSERT INTO users (name, email, password, role, age, gender, activity_level, fitness_goal) VALUES
('Test User', 'user@bmimonitor.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'user', 28, 'Male', 'Lightly Active', 'Lose Weight');

-- Sample BMI records for the test user
INSERT INTO bmi_records (user_id, height, weight, bmi, recorded_at) VALUES
(2, 170.0, 65.0, 22.49, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 170.0, 67.5, 23.35, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(2, 170.0, 66.0, 22.84, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 170.0, 68.0, 23.53, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(2, 170.0, 65.5, 22.68, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 170.0, 64.0, 22.15, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 170.0, 63.5, 21.97, NOW());
