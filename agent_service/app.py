"""
app.py
======
Flask microservice exposing the agent over HTTP, mirroring the pattern
used by ml_service/app.py. PHP calls this instead of (or in addition
to) the raw ML service — the agent decides internally when to call the
ML model, check history, log its reasoning, or send an alert.

Run:
    pip install -r requirements.txt
    cp .env.example .env    # fill in ANTHROPIC_API_KEY, DB + SMTP creds
    python app.py            # serves http://127.0.0.1:5002

Endpoints:
    GET  /health
    POST /on_reading   { user_id, record_id, height, weight, bmi }
    POST /chat         { user_id, message }
"""

import os

from dotenv import load_dotenv

load_dotenv()  # must run before importing modules that read env vars at import time

from flask import Flask, jsonify, request
from flask_cors import CORS

from agent import run_chat, run_on_reading
from db import execute, fetch_all

app = Flask(__name__)
CORS(app)

MAX_HISTORY_MESSAGES = 20  # cap conversation context sent to the model


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/on_reading", methods=["POST"])
def on_reading():
    data = request.get_json(silent=True) or {}
    try:
        user_id = int(data["user_id"])
        record_id = int(data["record_id"])
        height = float(data["height"])
        weight = float(data["weight"])
        bmi = float(data["bmi"])
    except (KeyError, ValueError, TypeError):
        return jsonify({"success": False, "message": "Missing/invalid fields."}), 400

    try:
        result = run_on_reading(user_id, record_id, height, weight, bmi)
        return jsonify(result)
    except Exception as e:
        return jsonify({"success": False, "message": f"Agent error: {e}"}), 500


@app.route("/chat", methods=["POST"])
def chat():
    data = request.get_json(silent=True) or {}
    user_id = data.get("user_id")
    message = (data.get("message") or "").strip()

    if not user_id or not message:
        return jsonify({"success": False, "message": "user_id and message are required."}), 400

    try:
        user_id = int(user_id)
    except ValueError:
        return jsonify({"success": False, "message": "user_id must be an integer."}), 400

    if len(message) > 2000:
        return jsonify({"success": False, "message": "Message too long."}), 400

    try:
        history_rows = fetch_all(
            """SELECT role, message FROM agent_conversations
               WHERE user_id=%s ORDER BY created_at DESC LIMIT %s""",
            (user_id, MAX_HISTORY_MESSAGES),
        )
        history = [{"role": r["role"], "content": r["message"]} for r in reversed(history_rows)]

        reply = run_chat(user_id, message, history)

        execute(
            "INSERT INTO agent_conversations (user_id, role, message) VALUES (%s, 'user', %s)",
            (user_id, message),
        )
        execute(
            "INSERT INTO agent_conversations (user_id, role, message) VALUES (%s, 'assistant', %s)",
            (user_id, reply),
        )

        return jsonify({"success": True, "reply": reply})
    except Exception as e:
        return jsonify({"success": False, "message": f"Agent error: {e}"}), 500


if __name__ == "__main__":
    if not os.environ.get("ANTHROPIC_API_KEY"):
        print("WARNING: ANTHROPIC_API_KEY not set — /on_reading and /chat will fail. "
              "Copy .env.example to .env and fill it in.")
    app.run(host="127.0.0.1", port=5002, debug=False)
