<?php
// ============================================================
//  api/update_profile.php
//  Lets a logged-in user set/update the profile fields that feed
//  the ML recommendation model (age, gender, activity level, goal).
//  Without these, api/bmi.php can't call the ML service for them.
//
//  Method : POST (JSON)
//  Body   : { "age":22, "gender":"Male",
//             "activity_level":"Sedentary", "fitness_goal":"Lose Weight" }
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

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

$age            = isset($data['age']) ? (int) $data['age'] : null;
$gender         = trim((string) ($data['gender'] ?? ''));
$activity_level = trim((string) ($data['activity_level'] ?? ''));
$fitness_goal   = trim((string) ($data['fitness_goal'] ?? ''));

$valid_genders  = ['Male', 'Female'];
$valid_activity = ['Sedentary', 'Lightly Active', 'Moderately Active', 'Very Active'];
$valid_goals    = ['Lose Weight', 'Maintain Weight', 'Gain Weight'];

if ($age === null || $age < 1 || $age > 120) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid age (1-120).']);
    exit;
}
if (!in_array($gender, $valid_genders, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'gender must be Male or Female.']);
    exit;
}
if (!in_array($activity_level, $valid_activity, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid activity_level.']);
    exit;
}
if (!in_array($fitness_goal, $valid_goals, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid fitness_goal.']);
    exit;
}

$db      = get_db();
$user_id = (int) $_SESSION['user_id'];

$stmt = $db->prepare(
    "UPDATE users SET age = ?, gender = ?, activity_level = ?, fitness_goal = ? WHERE id = ?"
);
$stmt->bind_param('isssi', $age, $gender, $activity_level, $fitness_goal, $user_id);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile saved. New BMI readings will now include an AI recommendation.',
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
