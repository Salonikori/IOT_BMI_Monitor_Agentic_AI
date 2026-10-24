-- ============================================================
--  migration_add_agent_tables.sql
--  Adds the tables/columns the agentic AI layer needs. Run once:
--    mysql -u root -p bmi_monitor < migration_add_agent_tables.sql
-- ============================================================
USE bmi_monitor;

-- Chat memory: every message either side of the conversation sent,
-- so the agent has context across turns (and users can scroll back).
CREATE TABLE IF NOT EXISTS agent_conversations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT                      NOT NULL,
    role        ENUM('user','assistant') NOT NULL,
    message     TEXT                     NOT NULL,
    created_at  TIMESTAMP                DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Audit trail: every decision the agent made about a reading, and why.
-- bmi_record_id is nullable so chat-triggered reasoning can log too.
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

-- Cache the agent's latest decision directly on the reading, so the
-- dashboard can show it without a join.
ALTER TABLE bmi_records
    ADD COLUMN IF NOT EXISTS agent_decision VARCHAR(20) NULL AFTER sleep_hours,
    ADD COLUMN IF NOT EXISTS agent_summary  TEXT        NULL AFTER agent_decision;
