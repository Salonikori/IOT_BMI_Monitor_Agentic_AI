-- ============================================================
--  migration_add_alert_email.sql
--  Run this ONCE if your `users` table was created BEFORE the
--  automated-email feature was added (i.e. it doesn't already
--  have an `alert_email` column).
--
--  Safe to run in phpMyAdmin > SQL tab, or via MySQL CLI:
--    mysql -u root -p bmi_monitor < migration_add_alert_email.sql
-- ============================================================
USE bmi_monitor;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS alert_email VARCHAR(150) NULL
    AFTER role;
