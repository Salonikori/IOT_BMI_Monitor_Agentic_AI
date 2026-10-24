# Agentic AI Service

The "agentic" layer on top of the plain ML model: an LLM (Claude) with
tool-calling that decides what to look up and what to do, rather than
PHP just calling one fixed prediction endpoint.

```
                     ┌─────────────────────────────────────────┐
                     │           agent_service (:5002)          │
PHP  ──HTTP──▶       │  Claude (tool-use loop)                  │
(bmi.php,            │    ├─ get_user_profile      ──▶ MySQL    │
 chat.php)           │    ├─ get_bmi_history        ──▶ MySQL    │
                     │    ├─ predict_recommendation ──▶ ml_service│
                     │    ├─ log_agent_event        ──▶ MySQL    │
                     │    └─ send_alert_email       ──▶ SMTP     │
                     └─────────────────────────────────────────┘
```

## Why an agent instead of just calling the ML model?

`ml_service` (the RandomForest model) only ever sees one point in time —
today's height/weight. It can't tell if you're improving or backsliding.
The agent can:

- Pull your **history** and compare the trend to your **stated goal**
  ("BMI has been climbing for 3 readings despite a Lose Weight goal")
- **Decide** whether that trend is worth flagging (`on_track` vs
  `needs_attention`), instead of a fixed rule
- **Escalate** — send an email — only when it judges the pattern is
  real, not just one noisy reading
- **Answer free-form questions** in `chat.php` using your actual data
  ("what would my plan look like at 75kg?" runs a real prediction with
  overridden weight, it doesn't guess)
- **Log every decision** to `agent_events` so there's an audit trail of
  what it did and why

## Setup

```bash
cd agent_service
pip install -r requirements.txt --break-system-packages   # or use a venv
cp .env.example .env
```

Edit `.env`:
- `ANTHROPIC_API_KEY` — required. Get one at https://console.anthropic.com/
- `DB_*` — same MySQL DB as the PHP app (see `includes/config.php`)
- `ML_SERVICE_URL` — where `ml_service/app.py` is running (default `:5001`)
- `SMTP_*` — optional; if left blank, `send_alert_email` just reports
  what it *would* have sent instead of failing

Run the DB migration once (adds `agent_conversations`, `agent_events`,
and two columns on `bmi_records`):
```bash
mysql -u root -p bmi_monitor < ../migration_add_agent_tables.sql
```

Start the service:
```bash
python app.py     # serves http://127.0.0.1:5002
```

Keep this running alongside `ml_service/app.py` and XAMPP.

## What happens if the agent is down?

`api/bmi.php` tries the agent first. If it's unreachable or times out,
it falls back to calling `ml_service` directly (the same non-agentic
path from before this layer existed) — the reading is never lost and
the recommendation is never blank, it just won't have a trend
evaluation or audit-log entry for that reading.

## API

### GET /health
`{"status": "ok"}`

### POST /on_reading
Called by `api/bmi.php` right after a reading is saved.
```json
{ "user_id": 3, "record_id": 42, "height": 178, "weight": 92, "bmi": 29.0 }
```
```json
{
  "success": true,
  "summary": "Your BMI has held steady around 29 for the last month, and your calorie deficit is on track...",
  "decision": "on_track",
  "prediction": { "overall_recommendation": "...", "recommended_calories": 1900, ... },
  "tool_calls": ["get_user_profile", "get_bmi_history", "predict_recommendation", "log_agent_event"]
}
```

### POST /chat
Called by `api/agent_chat.php`. Conversation memory (`agent_conversations`)
is handled entirely by this service — callers just send the latest message.
```json
{ "user_id": 3, "message": "What would my plan look like at 80kg?" }
```
```json
{ "success": true, "reply": "At 80kg you'd be looking at roughly..." }
```

## Retraining / model changes

This service doesn't train anything itself — it calls `ml_service` for
predictions. Retrain there (`ml_service/train_model.py`) and this
service automatically uses the new numbers.
