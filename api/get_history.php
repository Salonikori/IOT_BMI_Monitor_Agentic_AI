<?php
// ============================================================
//  api/get_history.php
//  Returns all BMI records for the logged-in user as JSON
//  Used by Chart.js on the history/dashboard page
// ============================================================

header('Content-Type: application/json');
require_once '../includes/config.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$limit   = isset($_GET['limit']) ? (int)$_GET['limit'] : 30; // last 30 records
$db      = get_db();

$stmt = $db->prepare(
    "SELECT height, weight, bmi, recorded_at
       FROM bmi_records
      WHERE user_id = ?
      ORDER BY recorded_at ASC
      LIMIT ?"
);
$stmt->bind_param('ii', $user_id, $limit);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'records' => $rows]);
