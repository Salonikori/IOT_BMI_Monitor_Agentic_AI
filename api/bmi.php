<?php
// ============================================================
//  api/bmi.php
//  REST API endpoint — receives BMI data from the ESP32
//
//  Method : POST
//  URL    : http://localhost/bmi_monitor/api/bmi.php
//  Body   : JSON { "height": 170, "weight": 65, "bmi": 22.49,
//                  "user_id": 2 }
//
//  The ESP32 sends a POST request with JSON; this script
//  validates, stores the data, and returns a JSON response.
// ============================================================

// Allow cross-origin requests (ESP32 / mobile apps)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';

// ---- Only accept POST requests ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed. Use POST.']);
    exit;
}

// ---- Read raw JSON body sent by ESP32 ----
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// ---- Validate that JSON was parsed correctly ----
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
    exit;
}

// ---- Extract and validate required fields ----
$height     = isset($data['height'])     ? (float) $data['height']     : null;
$weight     = isset($data['weight'])     ? (float) $data['weight']     : null;
$bmi        = isset($data['bmi'])        ? (float) $data['bmi']        : null;
$user_id    = isset($data['user_id'])    ? (int)   $data['user_id']    : 1; // default user 1
$send_email = isset($data['send_email']) ? (bool)  $data['send_email'] : false; // one-shot flag from ESP32 Serial command

if ($height === null || $weight === null || $bmi === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields: height, weight, bmi.']);
    exit;
}

// ---- Sanity-check values ----
if ($height < 50 || $height > 300) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Height must be between 50 and 300 cm.']);
    exit;
}
if ($weight < 1 || $weight > 500) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Weight must be between 1 and 500 kg.']);
    exit;
}

// Recalculate BMI server-side for accuracy (height in metres)
$calculated_bmi = round($weight / (($height / 100) ** 2), 2);

// ---- Save the raw reading first (so it's never lost even if the
// ---- agent/ML services are slow or down) ----
$db = get_db();

$stmt = $db->prepare(
    "INSERT INTO bmi_records (user_id, height, weight, bmi) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('iddd', $user_id, $height, $weight, $calculated_bmi);

if ($stmt->execute()) {
    $record_id = $db->insert_id;
    $category  = bmi_category($calculated_bmi);
    $stmt->close();

    // ---- Let the agentic AI evaluate this reading ----
    // The agent pulls the user's own profile/history via its own tools,
    // calls the ML model, decides on_track vs needs_attention, and may
    // send an alert email — all server-side, never trusting client input
    // for who the reading belongs to. Falls back to a direct (non-agentic)
    // ML call if the agent service is down/slow, so a reading is never
    // left without at least the basic recommendation.
    $agent = get_agent_reading_evaluation($user_id, $record_id, $height, $weight, $calculated_bmi);

    if ($agent['success']) {
        $prediction   = $agent['prediction'] ?? [];
        $ml_overall   = $prediction['overall_recommendation'] ?? null;
        $ml_exercise  = $prediction['exercise_plan'] ?? null;
        $ml_diet      = $prediction['diet_recommendation'] ?? null;
        $ml_calories  = $prediction['recommended_calories'] ?? null;
        $ml_protein   = $prediction['protein_g_per_day'] ?? null;
        $ml_water     = $prediction['water_l_per_day'] ?? null;
        $ml_sleep     = $prediction['sleep_hours'] ?? null;
        $agent_decision = $agent['decision'] ?? null;
        $agent_summary  = $agent['summary'] ?? null;
    } else {
        // Fallback: skip the agent, call the ML model directly (same as
        // before the agentic layer existed).
        $p_stmt = $db->prepare("SELECT age, gender, activity_level, fitness_goal FROM users WHERE id = ?");
        $p_stmt->bind_param('i', $user_id);
        $p_stmt->execute();
        $profile = $p_stmt->get_result()->fetch_assoc() ?: [];
        $p_stmt->close();

        $ml = get_ml_recommendation($profile, $height, $weight);
        $ml_overall   = $ml['success'] ? ($ml['overall_recommendation'] ?? null) : null;
        $ml_exercise  = $ml['success'] ? ($ml['exercise_plan'] ?? null)          : null;
        $ml_diet      = $ml['success'] ? ($ml['diet_recommendation'] ?? null)    : null;
        $ml_calories  = $ml['success'] ? ($ml['recommended_calories'] ?? null)   : null;
        $ml_protein   = $ml['success'] ? ($ml['protein_g_per_day'] ?? null)      : null;
        $ml_water     = $ml['success'] ? ($ml['water_l_per_day'] ?? null)        : null;
        $ml_sleep     = $ml['success'] ? ($ml['sleep_hours'] ?? null)            : null;
        $agent_decision = null;
        $agent_summary  = null; // no genuine agent decision was made — don't show a fake "agent note"
        $fallback_reason = $agent['message'] ?? 'Agent service unavailable — showed the direct ML recommendation instead.';
    }

    // ---- Cache the recommendation + agent decision onto the record ----
    $u_stmt = $db->prepare(
        "UPDATE bmi_records SET
            overall_recommendation = ?, exercise_plan = ?, diet_recommendation = ?,
            recommended_calories = ?, protein_g_per_day = ?, water_l_per_day = ?, sleep_hours = ?,
            agent_decision = ?, agent_summary = ?
         WHERE id = ?"
    );
    $u_stmt->bind_param(
        'sssiidsssi',
        $ml_overall, $ml_exercise, $ml_diet,
        $ml_calories, $ml_protein, $ml_water, $ml_sleep,
        $agent_decision, $agent_summary, $record_id
    );
    $u_stmt->execute();
    $u_stmt->close();

    // ---- Automated email trigger ----
    // Only fires if the ESP32 sent send_email:true (typed "email" in the
    // Serial Monitor for this specific reading). Everyday readings every
    // 10s never hit this block, so there's no spam.
    $email_result = null;
    if ($send_email) {
        require_once '../includes/mailer.php';

        $u_stmt = $db->prepare("SELECT name, alert_email FROM users WHERE id = ?");
        $u_stmt->bind_param('i', $user_id);
        $u_stmt->execute();
        $owner = $u_stmt->get_result()->fetch_assoc();
        $u_stmt->close();

        if ($owner && !empty($owner['alert_email'])) {
            $record_for_email = [
                'height'      => $height,
                'weight'      => $weight,
                'bmi'         => $calculated_bmi,
                'recorded_at' => date('Y-m-d H:i:s'),
            ];

            $email_result = send_bmi_report_email(
                $owner['alert_email'],
                $owner['name'],
                $record_for_email,
                $owner['name'],
                'Automatically sent from your BMI Monitor device.'
            );

            // Best-effort logging — never fail the ESP32 request over this.
            try {
                $status = $email_result['success'] ? 'sent' : 'failed';
                $errMsg = $email_result['success'] ? null : $email_result['message'];
                $log = $db->prepare(
                    "INSERT INTO email_logs (record_id, sent_by, sent_to, recipient_name, note, status, error_msg)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $note = 'Auto-triggered from ESP32';
                $log->bind_param('iisssss', $record_id, $user_id, $owner['alert_email'], $owner['name'], $note, $status, $errMsg);
                $log->execute();
                $log->close();
            } catch (\Throwable $e) {
                // Logging is non-critical; ignore failures here.
            }
        } else {
            $email_result = [
                'success' => false,
                'message' => 'send_email was set but this user has no alert_email configured.',
            ];
        }
    }

    http_response_code(201);  // 201 Created
    echo json_encode([
        'success'    => true,
        'message'    => 'BMI record saved successfully.',
        'record_id'  => $record_id,
        'data'       => [
            'height'   => $height,
            'weight'   => $weight,
            'bmi'      => $calculated_bmi,
            'category' => $category['label'],
        ],
        // Overall recommendation payload (works whether it came from the
        // agent or the direct-ML fallback)
        'recommendation' => [
            'success'                => $agent['success'] || $ml_overall !== null,
            'message'                => $agent['success'] ? null : $fallback_reason,
            'source'                 => $agent['success'] ? 'agent' : 'ml_fallback',
            'overall_recommendation' => $ml_overall,
            'exercise_plan'          => $ml_exercise,
            'diet_recommendation'    => $ml_diet,
            'recommended_calories'   => $ml_calories,
            'protein_g_per_day'      => $ml_protein,
            'water_l_per_day'        => $ml_water,
            'sleep_hours'            => $ml_sleep,
        ],
        // Agentic evaluation of this reading, if the agent ran successfully
        'agent' => [
            'decision' => $agent_decision,
            'summary'  => $agent_summary,
        ],
        'email'      => $email_result, // null if send_email was false/not requested
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
