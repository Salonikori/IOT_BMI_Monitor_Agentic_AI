<?php
// ============================================================
//  api/get_latest.php
//  Returns the latest BMI record for the logged-in user (JSON)
//  Called every few seconds by the dashboard via fetch()
// ============================================================

header('Content-Type: application/json');
require_once '../includes/config.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$db      = get_db();

// Get the most recent record for this user
$stmt = $db->prepare(
    "SELECT id, height, weight, bmi, recorded_at,
            overall_recommendation, exercise_plan, diet_recommendation,
            recommended_calories, protein_g_per_day, water_l_per_day, sleep_hours
       FROM bmi_records
      WHERE user_id = ?
      ORDER BY recorded_at DESC
      LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();
$stmt->close();

if ($record) {
    $cat = bmi_category((float)$record['bmi']);
    echo json_encode([
        'success'    => true,
        'record_id'  => (int) $record['id'],
        'height'     => $record['height'],
        'weight'     => $record['weight'],
        'bmi'        => $record['bmi'],
        'category'   => $cat['label'],
        'color'      => $cat['color'],
        'recorded_at'=> $record['recorded_at'],
        'suggestion' => health_suggestion((float)$record['bmi']),
        'person'     => $_SESSION['name'] ?? 'You',
        'recommendation' => [
            'overall_recommendation' => $record['overall_recommendation'],
            'exercise_plan'          => $record['exercise_plan'],
            'diet_recommendation'    => $record['diet_recommendation'],
            'recommended_calories'   => $record['recommended_calories'],
            'protein_g_per_day'      => $record['protein_g_per_day'],
            'water_l_per_day'        => $record['water_l_per_day'],
            'sleep_hours'            => $record['sleep_hours'],
        ],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No records found']);
}
