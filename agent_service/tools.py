"""
tools.py
========
The actual functions behind each tool the agent can call. Each function
takes plain Python args and returns a JSON-serialisable dict — agent.py
wraps these as Anthropic tool_use handlers.

All DB access is scoped by a server-known user_id (never trusted from
model output) so the agent can't be talked into reading/writing another
user's data.
"""

import os
import smtplib
from email.mime.text import MIMEText

import requests

from db import execute, fetch_all, fetch_one

ML_SERVICE_URL = os.environ.get("ML_SERVICE_URL", "http://127.0.0.1:5001")

VALID_ACTIVITY = {"Sedentary", "Lightly Active", "Moderately Active", "Very Active"}
VALID_GOALS = {"Lose Weight", "Maintain Weight", "Gain Weight"}


def get_user_profile(user_id: int) -> dict:
    row = fetch_one(
        "SELECT name, email, age, gender, activity_level, fitness_goal FROM users WHERE id=%s",
        (user_id,),
    )
    if not row:
        return {"error": "User not found."}
    if not all([row["age"], row["gender"], row["activity_level"], row["fitness_goal"]]):
        return {**row, "warning": "Profile incomplete — some fields are missing."}
    return row


def get_bmi_history(user_id: int, limit: int = 10) -> dict:
    limit = max(1, min(int(limit), 50))
    rows = fetch_all(
        """SELECT id, height, weight, bmi, recorded_at
             FROM bmi_records WHERE user_id=%s
             ORDER BY recorded_at DESC LIMIT %s""",
        (user_id, limit),
    )
    for r in rows:
        r["recorded_at"] = str(r["recorded_at"])
        r["bmi"] = float(r["bmi"])
        r["height"] = float(r["height"])
        r["weight"] = float(r["weight"])
    return {"readings": rows, "count": len(rows)}


def predict_recommendation(user_id: int, height_cm: float = None, weight_kg: float = None) -> dict:
    """Calls the ml_service RandomForest model. height_cm/weight_kg can be
    overridden by the agent to answer hypothetical ("what if I lost 5kg?")
    questions without touching the stored record."""
    profile = get_user_profile(user_id)
    if profile.get("error") or profile.get("warning"):
        return {"error": "Cannot predict — profile is incomplete. Ask the user for "
                          "age, gender, activity level and goal first."}

    if height_cm is None or weight_kg is None:
        latest = fetch_one(
            "SELECT height, weight FROM bmi_records WHERE user_id=%s ORDER BY recorded_at DESC LIMIT 1",
            (user_id,),
        )
        if not latest:
            return {"error": "No BMI reading on file yet."}
        height_cm = height_cm or float(latest["height"])
        weight_kg = weight_kg or float(latest["weight"])

    try:
        resp = requests.post(
            f"{ML_SERVICE_URL}/predict",
            json={
                "age": profile["age"],
                "gender": profile["gender"],
                "height_cm": height_cm,
                "weight_kg": weight_kg,
                "activity_level": profile["activity_level"],
                "goal": profile["fitness_goal"],
            },
            timeout=5,
        )
        return resp.json()
    except requests.RequestException as e:
        return {"error": f"ML service unreachable: {e}"}


def log_agent_event(user_id: int, bmi_record_id, decision: str, summary: str) -> dict:
    if decision not in ("on_track", "needs_attention"):
        return {"error": "decision must be 'on_track' or 'needs_attention'."}
    record_id = bmi_record_id if bmi_record_id not in (None, "", "null") else None
    new_id = execute(
        """INSERT INTO agent_events (user_id, bmi_record_id, decision, summary)
           VALUES (%s, %s, %s, %s)""",
        (user_id, record_id, decision, summary[:2000]),
    )
    return {"success": True, "event_id": new_id}


def send_alert_email(user_id: int, subject: str, message: str) -> dict:
    profile = get_user_profile(user_id)
    to_email = profile.get("email")
    if not to_email:
        return {"error": "No email on file for this user."}

    host = os.environ.get("SMTP_HOST")
    port = int(os.environ.get("SMTP_PORT", 587))
    user = os.environ.get("SMTP_USER")
    pw = os.environ.get("SMTP_PASS")
    from_name = os.environ.get("SMTP_FROM_NAME", "BMI Monitor")

    if not all([host, user, pw]):
        return {"error": "SMTP not configured in agent_service/.env — skipping send.",
                "would_have_sent_to": to_email}

    try:
        msg = MIMEText(message)
        msg["Subject"] = subject
        msg["From"] = f"{from_name} <{user}>"
        msg["To"] = to_email
        with smtplib.SMTP(host, port, timeout=8) as server:
            server.starttls()
            server.login(user, pw)
            server.sendmail(user, [to_email], msg.as_string())
        return {"success": True, "sent_to": to_email}
    except Exception as e:
        return {"error": f"Failed to send: {e}"}


# ---------------------------------------------------------------
# Anthropic tool schemas — paired with the functions above by name
# ---------------------------------------------------------------
TOOL_DEFINITIONS = [
    {
        "name": "get_user_profile",
        "description": "Get the current user's profile: name, email, age, gender, activity level, fitness goal.",
        "input_schema": {"type": "object", "properties": {}},
    },
    {
        "name": "get_bmi_history",
        "description": "Get the user's past BMI readings, most recent first.",
        "input_schema": {
            "type": "object",
            "properties": {
                "limit": {"type": "integer", "description": "Max readings to return (default 10, max 50)."}
            },
        },
    },
    {
        "name": "predict_recommendation",
        "description": (
            "Get an ML-generated recommendation (calories, protein, water, exercise plan, diet, "
            "overall recommendation) for the user. By default uses their latest stored height/weight. "
            "Pass height_cm/weight_kg to answer a hypothetical question instead."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "height_cm": {"type": "number"},
                "weight_kg": {"type": "number"},
            },
        },
    },
    {
        "name": "log_agent_event",
        "description": (
            "Record your decision about this reading for the audit trail. Call this once you've "
            "evaluated a new reading against the user's history and goal."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "bmi_record_id": {"type": ["integer", "null"], "description": "The reading this decision is about, if any."},
                "decision": {"type": "string", "enum": ["on_track", "needs_attention"]},
                "summary": {"type": "string", "description": "1-2 sentence reason for the decision."},
            },
            "required": ["decision", "summary"],
        },
    },
    {
        "name": "send_alert_email",
        "description": (
            "Send an email alert to the user. Only use this when you've decided 'needs_attention' "
            "and the situation genuinely warrants proactively contacting them (e.g. BMI trending "
            "the wrong way for 3+ readings in a row)."
        ),
        "input_schema": {
            "type": "object",
            "properties": {
                "subject": {"type": "string"},
                "message": {"type": "string"},
            },
            "required": ["subject", "message"],
        },
    },
]


def dispatch_tool(name: str, tool_input: dict, user_id: int) -> dict:
    """Runs a tool by name with the server-trusted user_id injected, never
    taken from model output."""
    if name == "get_user_profile":
        return get_user_profile(user_id)
    if name == "get_bmi_history":
        return get_bmi_history(user_id, tool_input.get("limit", 10))
    if name == "predict_recommendation":
        return predict_recommendation(user_id, tool_input.get("height_cm"), tool_input.get("weight_kg"))
    if name == "log_agent_event":
        return log_agent_event(user_id, tool_input.get("bmi_record_id"), tool_input["decision"], tool_input["summary"])
    if name == "send_alert_email":
        return send_alert_email(user_id, tool_input["subject"], tool_input["message"])
    return {"error": f"Unknown tool: {name}"}
