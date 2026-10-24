<?php
// ============================================================
//  chat.php  —  Chat with your AI health agent
//  Talks to agent_service/app.py (via api/agent_chat.php), which owns
//  conversation memory in the agent_conversations table.
// ============================================================
require_once 'includes/config.php';
require_login();

$user_id = (int) $_SESSION['user_id'];
$db      = get_db();

// Load existing conversation history (oldest first) so the page isn't blank
$stmt = $db->prepare(
    "SELECT role, message, created_at FROM agent_conversations
      WHERE user_id = ? ORDER BY created_at ASC LIMIT 200"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page = 'chat';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Agent — BMI Monitor</title>
    <link rel="stylesheet" href="/bmi_monitor/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">

        <button class="hamburger" id="hamburger" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="topbar">
            <div>
                <h1 class="page-title">AI Health Agent</h1>
                <p class="page-subtitle">Ask about your BMI trend, your plan, or "what if" scenarios — it looks up your real data.</p>
            </div>
        </div>

        <div class="table-panel" style="display:flex;flex-direction:column;height:calc(100vh - 180px);max-height:720px;">
            <div id="chat-log" style="flex:1;overflow-y:auto;padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
                <?php if (empty($history)): ?>
                    <div class="chat-bubble chat-bubble-agent">
                        Hi <?= clean($_SESSION['name'] ?? 'there') ?>! I'm your AI health agent — I can look up your
                        BMI history, explain your current recommendation, or run "what if" scenarios
                        (e.g. "what would my calories look like at 75kg?"). What would you like to know?
                    </div>
                <?php else: ?>
                    <?php foreach ($history as $m): ?>
                        <div class="chat-bubble <?= $m['role']==='user' ? 'chat-bubble-user' : 'chat-bubble-agent' ?>">
                            <?= nl2br(clean($m['message'])) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <form id="chat-form" style="display:flex;gap:10px;padding:16px 24px;border-top:1px solid rgba(148,163,184,0.15);">
                <input type="text" id="chat-input" class="form-control" style="flex:1;"
                       placeholder="Ask your AI agent something…" autocomplete="off" maxlength="2000" required>
                <button type="submit" class="btn btn-primary" id="chat-send-btn">Send</button>
            </form>
            <p id="chat-error" style="padding:0 24px 14px;color:#ef4444;font-size:0.85rem;display:none;"></p>
        </div>

    </main>
</div>

<style>
.chat-bubble {
    max-width: 78%;
    padding: 12px 16px;
    border-radius: 14px;
    font-size: 0.92rem;
    line-height: 1.5;
    white-space: pre-wrap;
}
.chat-bubble-user {
    align-self: flex-end;
    background: #06d6a0;
    color: #052e24;
    border-bottom-right-radius: 4px;
}
.chat-bubble-agent {
    align-self: flex-start;
    background: rgba(148,163,184,0.12);
    color: inherit;
    border-bottom-left-radius: 4px;
}
</style>

<script src="/bmi_monitor/assets/js/app.js"></script>
<script>
const chatLog   = document.getElementById('chat-log');
const chatForm  = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
const sendBtn   = document.getElementById('chat-send-btn');
const chatError = document.getElementById('chat-error');

function addBubble(text, who) {
    const div = document.createElement('div');
    div.className = 'chat-bubble ' + (who === 'user' ? 'chat-bubble-user' : 'chat-bubble-agent');
    div.textContent = text;
    chatLog.appendChild(div);
    chatLog.scrollTop = chatLog.scrollHeight;
}

chatLog.scrollTop = chatLog.scrollHeight;

chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const message = chatInput.value.trim();
    if (!message) return;

    chatError.style.display = 'none';
    addBubble(message, 'user');
    chatInput.value = '';
    chatInput.disabled = true;
    sendBtn.disabled = true;
    sendBtn.textContent = 'Thinking…';

    fetch('/bmi_monitor/api/agent_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            addBubble(data.reply, 'agent');
        } else {
            chatError.textContent = data.message || 'Something went wrong.';
            chatError.style.display = 'block';
        }
    })
    .catch(() => {
        chatError.textContent = 'Network error — check the agent service is running.';
        chatError.style.display = 'block';
    })
    .finally(() => {
        chatInput.disabled = false;
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send';
        chatInput.focus();
    });
});
</script>
</body>
</html>
