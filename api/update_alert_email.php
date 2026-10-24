<?php
// ============================================================
//  api/update_alert_email.php
//  Lets a logged-in user set/update the fixed email address
//  that automated (ESP32-triggered) reports get sent to.
//
//  Method : POST (JSON)
//  Body   : { "alert_email": "someone@example.com" }
//           Pass "" (empty string) to clear it.
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

if (!is_array($data) || !array_key_exists('alert_email', $data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'alert_email is required (use "" to clear it).']);
    exit;
}

$alert_email = trim((string) $data['alert_email']);

if ($alert_email !== '' && !filter_var($alert_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$db      = get_db();
$user_id = (int) $_SESSION['user_id'];

$stmt = $db->prepare("UPDATE users SET alert_email = ? WHERE id = ?");
$emailOrNull = $alert_email === '' ? null : $alert_email;
$stmt->bind_param('si', $emailOrNull, $user_id);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
        'success'     => true,
        'message'     => $alert_email === '' ? 'Auto-report email cleared.' : 'Auto-report email saved.',
        'alert_email' => $alert_email,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
