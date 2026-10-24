
<?php
// ============================================================
// includes/config.php
// Central configuration — DB settings, sessions, and helpers
// Credentials are loaded from environment variables.
// ============================================================

// ---------- Environment variable helper ----------
function app_env(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}

// ---------- Database settings ----------
define('DB_HOST', app_env('BMI_DB_HOST', 'localhost'));
define('DB_USER', app_env('BMI_DB_USER', 'root'));
define('DB_PASS', app_env('BMI_DB_PASS', ''));
define('DB_NAME', app_env('BMI_DB_NAME', 'bmi_monitor'));

// ---------- Application settings ----------
define('APP_NAME', 'BMI Monitor');
define('APP_VERSION', '1.1');
define(
    'APP_URL',
    rtrim(app_env('BMI_APP_URL', 'http://localhost/bmi_monitor'), '/')
);

// ---------- Email / SMTP settings ----------
// Credentials must be configured outside this file.
// If SMTP credentials are empty, email delivery is not configured.
define('SMTP_HOST', app_env('BMI_SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) app_env('BMI_SMTP_PORT', '587'));
define('SMTP_SECURE', app_env('BMI_SMTP_SECURE', 'tls'));
define('SMTP_USER', app_env('BMI_SMTP_USER', ''));
define('SMTP_PASS', app_env('BMI_SMTP_PASS', ''));
define('SMTP_FROM', app_env('BMI_SMTP_FROM', SMTP_USER));
define('SMTP_FROM_NAME', APP_NAME);

// ---------- ML recommendation service ----------
define(
    'ML_SERVICE_URL',
    rtrim(app_env('BMI_ML_SERVICE_URL', 'http://127.0.0.1:5001'), '/')
);
define(
    'ML_SERVICE_TIMEOUT_SECONDS',
    (int) app_env('BMI_ML_TIMEOUT', '3')
);

// ---------- Agentic AI service ----------
define(
    'AGENT_SERVICE_URL',
    rtrim(app_env('BMI_AGENT_SERVICE_URL', 'http://127.0.0.1:5002'), '/')
);
define(
    'AGENT_SERVICE_TIMEOUT_SECONDS',
    (int) app_env('BMI_AGENT_TIMEOUT', '20')
);

// ---------- Start session if not already started ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// get_db() — returns a reusable MySQLi connection
// ============================================================
function get_db(): mysqli {
    static $conn = null;

    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF);

        $conn = new mysqli(
            DB_HOST,
            DB_USER,
            DB_PASS,
            DB_NAME
        );

        if ($conn->connect_error) {
            error_log('BMI Monitor: Database connection failed.');
            http_response_code(500);
            exit(json_encode([
                'error' => 'Database connection failed.'
            ]));
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}

// ============================================================
// Helper: redirect to a URL
// ============================================================
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// ============================================================
// Helper: is the current visitor logged in?
// ============================================================
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

// ============================================================
// Helper: is the current visitor an admin?
// ============================================================
function is_admin(): bool {
    return isset($_SESSION['role'])
        && $_SESSION['role'] === 'admin';
}

// ============================================================
// Helper: require login
// ============================================================
function require_login(): void {
    if (!is_logged_in()) {
        redirect('/bmi_monitor/login.php');
    }
}

// ============================================================
// Helper: escape user input before HTML output
// ============================================================
function clean(string $input): string {
    return htmlspecialchars(
        trim($input),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ============================================================
// get_ml_recommendation()
// Calls the Python ML service for a personalized recommendation.
// ============================================================
function get_ml_recommendation(
    array $profile,
    float $height,
    float $weight
): array {
    if (
        empty($profile['age']) ||
        empty($profile['gender']) ||
        empty($profile['activity_level']) ||
        empty($profile['fitness_goal'])
    ) {
        return [
            'success' => false,
            'message' => 'Profile incomplete. Age, gender, activity level, and goal are required.'
        ];
    }

    $payload = json_encode([
        'age' => (int) $profile['age'],
        'gender' => $profile['gender'],
        'height_cm' => $height,
        'weight_kg' => $weight,
        'activity_level' => $profile['activity_level'],
        'goal' => $profile['fitness_goal']
    ]);

    if ($payload === false) {
        return [
            'success' => false,
            'message' => 'Unable to encode ML request.'
        ];
    }

    $ch = curl_init(ML_SERVICE_URL . '/predict');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => ML_SERVICE_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => ML_SERVICE_TIMEOUT_SECONDS
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        curl_close($ch);

        return [
            'success' => false,
            'message' => 'ML service is currently unavailable.'
        ];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'ML service returned an invalid response.'
        ];
    }

    if ($httpCode >= 400) {
        return [
            'success' => false,
            'message' => $decoded['message'] ?? 'ML service error.'
        ];
    }

    return $decoded;
}

// ============================================================
// get_agent_reading_evaluation()
// Requests an evaluation from the agent service.
// ============================================================
function get_agent_reading_evaluation(
    int $user_id,
    int $record_id,
    float $height,
    float $weight,
    float $bmi
): array {
    $payload = json_encode([
        'user_id' => $user_id,
        'record_id' => $record_id,
        'height' => $height,
        'weight' => $weight,
        'bmi' => $bmi
    ]);

    if ($payload === false) {
        return [
            'success' => false,
            'message' => 'Unable to encode agent request.'
        ];
    }

    $ch = curl_init(AGENT_SERVICE_URL . '/on_reading');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => AGENT_SERVICE_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        curl_close($ch);

        return [
            'success' => false,
            'message' => 'Agent service is currently unavailable.'
        ];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Agent service returned an invalid response.'
        ];
    }

    if ($httpCode >= 400 || empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => $decoded['message'] ?? 'Agent service error.'
        ];
    }

    return $decoded;
}

// ============================================================
// get_agent_chat_reply()
// Sends a chat message to the agent service.
// ============================================================
function get_agent_chat_reply(
    int $user_id,
    string $message
): array {
    $payload = json_encode([
        'user_id' => $user_id,
        'message' => $message
    ]);

    if ($payload === false) {
        return [
            'success' => false,
            'message' => 'Unable to encode chat request.'
        ];
    }

    $ch = curl_init(AGENT_SERVICE_URL . '/chat');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => AGENT_SERVICE_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => 3
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        curl_close($ch);

        return [
            'success' => false,
            'message' => 'Agent service is currently unavailable.'
        ];
    }

    curl_close($ch);

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        return [
            'success' => false,
            'message' => 'Agent service returned an invalid response.'
        ];
    }

    if ($httpCode >= 400 || empty($decoded['success'])) {
        return [
            'success' => false,
            'message' => $decoded['message'] ?? 'Agent service error.'
        ];
    }

    return $decoded;
}

// ============================================================
// Helper: get BMI category and colour
// ============================================================
function bmi_category(float $bmi): array {
    if ($bmi < 18.5) {
        return [
            'label' => 'Underweight',
            'color' => '#3b82f6',
            'bg' => '#eff6ff'
        ];
    } elseif ($bmi < 25) {
        return [
            'label' => 'Normal',
            'color' => '#10b981',
            'bg' => '#ecfdf5'
        ];
    } elseif ($bmi < 30) {
        return [
            'label' => 'Overweight',
            'color' => '#f59e0b',
            'bg' => '#fffbeb'
        ];
    }

    return [
        'label' => 'Obese',
        'color' => '#ef4444',
        'bg' => '#fef2f2'
    ];
}

// ============================================================
// Helper: health suggestion based on BMI
// ============================================================
function health_suggestion(float $bmi): string {
    if ($bmi < 18.5) {
        return 'Your BMI is below the usual adult reference range. Consider discussing your nutritional needs with a qualified healthcare professional.';
    } elseif ($bmi < 25) {
        return 'Your BMI is within the usual adult reference range. Maintain balanced nutrition and regular physical activity.';
    } elseif ($bmi < 30) {
        return 'Your BMI is above the usual adult reference range. Consider sustainable lifestyle habits and consult a healthcare professional for personalized guidance.';
    }

    return 'Your BMI is above the usual adult reference range. Consider discussing your health goals with a qualified healthcare professional.';
}
