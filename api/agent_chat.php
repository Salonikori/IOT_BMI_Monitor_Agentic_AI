<?php
// ============================================================
//  api/agent_chat.php
//  Proxies one chat message to the agent service (agent_service/app.py)
//  and returns its reply. The agent service owns conversation memory
//  (agent_conversations table) — this endpoint just authenticates the
//  session and forwards the message.
//
//  Method : POST (JSON)
//  Body   : { "message": "..." }
// ============================================================

header('Content-Type: application/json');

require_once '../includes/config.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$message = trim((string) ($data['message'] ?? ''));

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
    exit;
}
if (strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is too long (max 2000 characters).']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$result  = get_agent_chat_reply($user_id, $message);

if ($result['success']) {
    http_response_code(200);
    echo json_encode(['success' => true, 'reply' => $result['reply']]);
} else {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => $result['message'] ?? 'The AI agent is unavailable right now. Make sure agent_service/app.py is running.',
    ]);
}
