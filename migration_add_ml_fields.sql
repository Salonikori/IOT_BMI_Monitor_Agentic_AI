-- ============================================================
--  migration_add_ml_fields.sql
--  Run this ONCE if your database was created BEFORE the ML
--  recommendation integration was added (i.e. `users` doesn't
--  already have an `age` column).
--
--  Safe to run in phpMyAdmin > SQL tab, or via MySQL CLI:
--    mysql -u root -p bmi_monitor < migration_add_ml_fields.sql
-- ============================================================
USE bmi_monitor;

-- Profile fields used as inputs to the ML model (ml_service/app.py)
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS age            INT NULL AFTER alert_email,
    ADD COLUMN IF NOT EXISTS gender         ENUM('Male','Female') NULL AFTER age,
    ADD COLUMN IF NOT EXISTS activity_level ENUM('Sedentary','Lightly Active','Moderately Active','Very Active') NULL AFTER gender,
    ADD COLUMN IF NOT EXISTS fitness_goal   ENUM('Lose Weight','Maintain Weight','Gain Weight') NULL AFTER activity_level;

-- Cached ML output, stored alongside each BMI reading
ALTER TABLE bmi_records
    ADD COLUMN IF NOT EXISTS overall_recommendation VARCHAR(100) NULL AFTER bmi,
    ADD COLUMN IF NOT EXISTS exercise_plan           VARCHAR(100) NULL AFTER overall_recommendation,
    ADD COLUMN IF NOT EXISTS diet_recommendation     VARCHAR(150) NULL AFTER exercise_plan,
    ADD COLUMN IF NOT EXISTS recommended_calories    INT          NULL AFTER diet_recommendation,
    ADD COLUMN IF NOT EXISTS protein_g_per_day       INT          NULL AFTER recommended_calories,
    ADD COLUMN IF NOT EXISTS water_l_per_day         DECIMAL(4,1) NULL AFTER protein_g_per_day,
    ADD COLUMN IF NOT EXISTS sleep_hours             VARCHAR(20)  NULL AFTER water_l_per_day;
