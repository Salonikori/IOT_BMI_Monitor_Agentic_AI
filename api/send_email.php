<?php
// ============================================================
//  api/send_email.php
//  Emails a single BMI record (as a styled report) to any
//  email address the logged-in user/admin types in.
//
//  Method : POST (JSON)
//  Body   : { "record_id": 12, "to_email": "someone@example.com",
//             "to_name": "Optional Name", "note": "Optional note" }
//
//  Rules:
//   - Must be logged in.
//   - A normal user may only email their own records.
//   - An admin may email any user's record.
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

require_once '../includes/mailer.php';

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$record_id = isset($data['record_id']) ? (int) $data['record_id'] : 0;
$to_email  = isset($data['to_email'])  ? trim((string) $data['to_email'])  : '';
$to_name   = isset($data['to_name'])   ? trim((string) $data['to_name'])   : '';
$note      = isset($data['note'])      ? trim((string) $data['note'])      : '';

if (!$record_id || $to_email === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'record_id and to_email are required.']);
    exit;
}

if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

if (mb_strlen($note) > 500) {
    $note = mb_substr($note, 0, 500);
}

$db      = get_db();
$user_id = (int) $_SESSION['user_id'];

// ---- Fetch the record, joined with the owning user's name ----
$stmt = $db->prepare(
    "SELECT b.id, b.user_id, b.height, b.weight, b.bmi, b.recorded_at, u.name AS owner_name
       FROM bmi_records b
       JOIN users u ON u.id = b.user_id
      WHERE b.id = ?"
);
$stmt->bind_param('i', $record_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Record not found.']);
    exit;
}

// ---- Ownership check: normal users can only email their own records ----
if (!is_admin() && (int) $record['user_id'] !== $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You can only email your own BMI records.']);
    exit;
}

// ---- Send the email ----
$result = send_bmi_report_email($to_email, $to_name, $record, $record['owner_name'], $note);

// ---- Log the attempt (best-effort — do not fail the request if logging fails) ----
try {
    $status = $result['success'] ? 'sent' : 'failed';
    $errMsg = $result['success'] ? null : $result['message'];
    $log = $db->prepare(
        "INSERT INTO email_logs (record_id, sent_by, sent_to, recipient_name, note, status, error_msg)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $log->bind_param('iisssss', $record_id, $user_id, $to_email, $to_name, $note, $status, $errMsg);
    $log->execute();
    $log->close();
} catch (\Throwable $e) {
    // Logging is non-critical; ignore failures here.
}

if ($result['success']) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => $result['message']]);
} else {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $result['message']]);
}
