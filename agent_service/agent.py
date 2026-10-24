"""
agent.py
========
The actual "agentic" part: a tool-calling loop against the Anthropic
API. Two entry points:

  run_on_reading(user_id, record_id, height, weight, bmi)
      Called right after a new BMI reading is saved. The agent pulls
      the user's profile + history itself (as tools), decides whether
      they're on_track or need attention, calls the ML model, logs its
      decision, and — only if warranted — sends an alert email. Returns
      a dict ready to store back onto the bmi_records row.

  run_chat(user_id, user_message, history)
      Called from the chat UI. Same tools available, but the agent is
      just answering the user's question using their real data instead
      of guessing.

Both share _run_loop(), which is the actual tool-use loop.
"""

import json
import os

import anthropic

from tools import TOOL_DEFINITIONS, dispatch_tool

MODEL = os.environ.get("AGENT_MODEL", "claude-sonnet-5")
MAX_TOOL_ROUNDS = 6  # hard cap so a confused agent can't loop forever

_client = None


def get_client():
    global _client
    if _client is None:
        api_key = os.environ.get("ANTHROPIC_API_KEY")
        if not api_key:
            raise RuntimeError(
                "ANTHROPIC_API_KEY is not set. Copy .env.example to .env and fill it in."
            )
        _client = anthropic.Anthropic(api_key=api_key)
    return _client


def _run_loop(system_prompt: str, messages: list, user_id: int) -> tuple[str, list]:
    """Runs the tool-use loop until the model returns a plain text answer.
    Returns (final_text, tool_events) where tool_events is a log of every
    tool call made, useful for the audit trail / debugging."""
    client = get_client()
    tool_events = []

    for _ in range(MAX_TOOL_ROUNDS):
        response = client.messages.create(
            model=MODEL,
            max_tokens=1024,
            system=system_prompt,
            messages=messages,
            tools=TOOL_DEFINITIONS,
        )

        if response.stop_reason != "tool_use":
            final_text = "".join(
                block.text for block in response.content if block.type == "text"
            ).strip()
            return final_text, tool_events

        # Model wants to call one or more tools — execute each, feed results back
        messages.append({"role": "assistant", "content": response.content})
        tool_results = []
        for block in response.content:
            if block.type != "tool_use":
                continue
            result = dispatch_tool(block.name, block.input, user_id)
            tool_events.append({"tool": block.name, "input": block.input, "result": result})
            tool_results.append({
                "type": "tool_result",
                "tool_use_id": block.id,
                "content": json.dumps(result),
            })
        messages.append({"role": "user", "content": tool_results})

    return ("I wasn't able to finish reasoning about this in time — please try again.",
            tool_events)


ON_READING_SYSTEM_PROMPT = """You are a supportive health-monitoring agent for a BMI \
tracking app. A user just logged a new BMI reading. Your job:

1. Call get_user_profile and get_bmi_history to understand this person and their trend.
2. Call predict_recommendation to get the ML model's numbers (calories, protein, \
water, exercise plan, diet, overall recommendation) for their latest reading.
3. Decide: is their BMI trend consistent with their stated goal, or drifting the \
wrong way over the last several readings? Don't overreact to one reading — look \
for a real pattern (3+ readings moving the wrong direction, or a sudden large jump).
4. Call log_agent_event with your decision ('on_track' or 'needs_attention') and a \
short reason.
5. Only if you decided 'needs_attention' AND the pattern is genuinely concerning \
(not just one noisy data point), call send_alert_email with a warm, specific, \
non-alarmist message.
6. Finish with a short (2-3 sentence) plain-text summary for the user, warm and \
factual, mentioning their recommended calories/exercise focus and whether you \
flagged anything. Do not repeat raw JSON. Do not give medical diagnoses — you are \
not a doctor. If you notice anything that could reflect disordered eating patterns \
(extreme rapid weight loss, very low calorie intake, etc.), gently suggest they \
consider speaking with a doctor rather than pushing a stricter calorie target.
"""

CHAT_SYSTEM_PROMPT = """You are the health-monitoring agent for a BMI tracking app, \
chatting with a logged-in user about their own data. You have tools to look up their \
profile, their BMI history, and get ML-based recommendations (including hypothetical \
"what if" scenarios by passing different height/weight). Use them whenever the answer \
depends on their actual data — never guess or make up numbers. Keep replies concise, \
warm, and specific. You are not a doctor; for medical questions, suggest they consult \
one. If a request suggests they want to lose weight in an unsafe way, gently push back."""


def run_on_reading(user_id: int, record_id: int, height: float, weight: float, bmi: float) -> dict:
    messages = [{
        "role": "user",
        "content": (
            f"New reading just logged for this user: height={height}cm, "
            f"weight={weight}kg, bmi={bmi}. bmi_record_id={record_id}. "
            "Evaluate it per your instructions."
        ),
    }]
    summary_text, tool_events = _run_loop(ON_READING_SYSTEM_PROMPT, messages, user_id)

    # Pull back whatever predict_recommendation / log_agent_event produced
    # so the caller (Flask app) can store it without re-parsing free text.
    prediction = {}
    decision = None
    for ev in tool_events:
        if ev["tool"] == "predict_recommendation" and "error" not in ev["result"]:
            prediction = ev["result"]
        if ev["tool"] == "log_agent_event":
            decision = ev["input"].get("decision")

    return {
        "success": True,
        "summary": summary_text,
        "decision": decision,
        "prediction": prediction,
        "tool_calls": [e["tool"] for e in tool_events],
    }


def run_chat(user_id: int, user_message: str, history: list) -> str:
    """history is a list of {"role": "user"|"assistant", "content": str}
    from agent_conversations, oldest first."""
    messages = [{"role": h["role"], "content": h["content"]} for h in history]
    messages.append({"role": "user", "content": user_message})
    reply, _ = _run_loop(CHAT_SYSTEM_PROMPT, messages, user_id)
    return reply
