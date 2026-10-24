<?php
// ============================================================
//  dashboard.php  —  Main User Dashboard
//  Shows latest BMI, charts, and health suggestion
//  Auto-refreshes every 5 seconds via JavaScript polling
// ============================================================
require_once 'includes/config.php';
require_login();

$user_id = (int) $_SESSION['user_id'];
$db      = get_db();

// --- Get latest BMI record (incl. cached ML + agent recommendation) ---
$stmt = $db->prepare(
    "SELECT id, height, weight, bmi, recorded_at,
            overall_recommendation, exercise_plan, diet_recommendation,
            recommended_calories, protein_g_per_day, water_l_per_day, sleep_hours,
            agent_decision, agent_summary
       FROM bmi_records
      WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();
$stmt->close();

// --- Get this user's auto-report email + ML profile fields ---
$stmt = $db->prepare("SELECT alert_email, age, gender, activity_level, fitness_goal FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$profile     = $stmt->get_result()->fetch_assoc() ?: [];
$alert_email = $profile['alert_email'] ?? '';
$profile_complete = !empty($profile['age']) && !empty($profile['gender'])
    && !empty($profile['activity_level']) && !empty($profile['fitness_goal']);
$stmt->close();

// --- Get total record count ---
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bmi_records WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// --- BMI category info ---
$bmi      = $latest ? (float)$latest['bmi'] : 0;
$cat      = bmi_category($bmi);
$suggest  = health_suggestion($bmi);

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — BMI Monitor</title>
    <link rel="stylesheet" href="/bmi_monitor/assets/css/style.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- html2pdf for report download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

<div class="page-wrapper">

    <!-- ===== SIDEBAR ===== -->
    <?php require_once 'includes/sidebar.php'; ?>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <!-- Mobile hamburger -->
        <button class="hamburger" id="hamburger" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1 class="page-title">Hello, <span><?= clean($_SESSION['name']) ?></span> 👋</h1>
                <p class="text-muted text-sm" id="last-updated">
                    <?= $latest ? 'Last updated: '.date('d M Y, h:i A', strtotime($latest['recorded_at'])) : 'No data yet' ?>
                </p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <div class="live-badge">
                    <span class="live-dot"></span> Live
                </div>
                <button class="btn btn-mail btn-sm" id="email-latest-btn"
                        onclick="openEmailModalForLatest(this)" <?= $latest ? '' : 'disabled' ?>>
                    ✉️ Email Report
                </button>
                <button class="btn btn-outline btn-sm" onclick="downloadPDF()">
                    📄 Download Report
                </button>
            </div>
        </div>

        <!-- ===== PDF AREA — everything inside gets exported ===== -->
        <div id="pdf-area">

        <!-- ===== STAT CARDS ===== -->
        <div class="cards-grid">

            <!-- BMI Card -->
            <div class="stat-card" style="--accent-color:#06d6a0;">
                <div class="stat-label">BMI Index</div>
                <div class="stat-value" id="bmi-value">
                    <?= $latest ? number_format($bmi, 1) : '—' ?>
                </div>
                <div id="bmi-badge" class="bmi-badge"
                     style="background:<?= $cat['bg'] ?? 'transparent' ?>;
                            color:<?= $cat['color'] ?? '#94a3b8' ?>;
                            border:1px solid <?= $cat['color'].'33' ?>;">
                    <?= $latest ? $cat['label'] : 'No data' ?>
                </div>
                <div class="stat-icon">❤️</div>
            </div>

            <!-- Height Card -->
            <div class="stat-card" style="--accent-color:#3b82f6;">
                <div class="stat-label">Height</div>
                <div class="stat-value">
                    <span id="height-value"><?= $latest ? number_format($latest['height'],1) : '—' ?></span>
                    <span class="stat-unit">cm</span>
                </div>
                <div class="stat-sub">Measured by ultrasonic sensor</div>
                <div class="stat-icon">📏</div>
            </div>

            <!-- Weight Card -->
            <div class="stat-card" style="--accent-color:#f59e0b;">
                <div class="stat-label">Weight</div>
                <div class="stat-value">
                    <span id="weight-value"><?= $latest ? number_format($latest['weight'],1) : '—' ?></span>
                    <span class="stat-unit">kg</span>
                </div>
                <div class="stat-sub">Measured by load cell sensor</div>
                <div class="stat-icon">⚖️</div>
            </div>

            <!-- Total Records Card -->
            <div class="stat-card" style="--accent-color:#8b5cf6;">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?= $count ?></div>
                <div class="stat-sub">Measurements stored</div>
                <div class="stat-icon">📊</div>
            </div>

        </div><!-- /cards-grid -->

        <!-- ===== HEALTH SUGGESTION ===== -->
        <?php if ($latest): ?>
        <div class="suggestion-panel">
            <div class="suggestion-icon">💡</div>
            <div class="suggestion-body">
                <h3>Health Insight for <?= $cat['label'] ?> BMI</h3>
                <p id="health-suggestion"><?= $suggest ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== AI RECOMMENDATION (from ml_service, the trained RandomForest model) ===== -->
        <div class="table-panel" id="ai-reco-panel" style="margin-bottom:20px;<?= (!$profile_complete) ? '' : '' ?>">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;">🤖 AI Health Recommendation</p>
            </div>

            <?php if (!$profile_complete): ?>
                <div style="padding:20px 24px;">
                    <p class="text-muted text-sm" style="margin-bottom:14px;">
                        Complete your profile to get a personalised, ML-generated recommendation
                        (calories, protein, water intake, exercise plan and diet) with every new reading.
                    </p>
                    <form id="profile-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;align-items:end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Age</label>
                            <input type="number" id="p-age" class="form-control" min="1" max="120" placeholder="22">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Gender</label>
                            <select id="p-gender" class="form-control">
                                <option value="">Select…</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Activity Level</label>
                            <select id="p-activity" class="form-control">
                                <option value="">Select…</option>
                                <option value="Sedentary">Sedentary</option>
                                <option value="Lightly Active">Lightly Active</option>
                                <option value="Moderately Active">Moderately Active</option>
                                <option value="Very Active">Very Active</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Goal</label>
                            <select id="p-goal" class="form-control">
                                <option value="">Select…</option>
                                <option value="Lose Weight">Lose Weight</option>
                                <option value="Maintain Weight">Maintain Weight</option>
                                <option value="Gain Weight">Gain Weight</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="saveProfile()">💾 Save Profile</button>
                    </form>
                    <p id="profile-msg" style="margin-top:10px;font-size:0.85rem;"></p>
                </div>
            <?php elseif ($latest && $latest['overall_recommendation']): ?>
                <div style="padding:20px 24px;" id="ai-reco-body">
                    <p style="font-weight:700;color:#06d6a0;font-size:1rem;margin-bottom:16px;" id="reco-overall">
                        <?= clean($latest['overall_recommendation']) ?>
                    </p>
                    <div class="cards-grid" style="margin-bottom:16px;">
                        <div class="stat-card" style="--accent-color:#f59e0b;">
                            <p class="text-muted text-sm">Calories</p>
                            <div class="stat-value"><span id="reco-calories"><?= (int)$latest['recommended_calories'] ?></span> <span style="font-size:0.9rem;">kcal/day</span></div>
                        </div>
                        <div class="stat-card" style="--accent-color:#3b82f6;">
                            <p class="text-muted text-sm">Protein</p>
                            <div class="stat-value"><span id="reco-protein"><?= (int)$latest['protein_g_per_day'] ?></span> <span style="font-size:0.9rem;">g/day</span></div>
                        </div>
                        <div class="stat-card" style="--accent-color:#06d6a0;">
                            <p class="text-muted text-sm">Water</p>
                            <div class="stat-value"><span id="reco-water"><?= number_format((float)$latest['water_l_per_day'],1) ?></span> <span style="font-size:0.9rem;">L/day</span></div>
                        </div>
                        <div class="stat-card" style="--accent-color:#8b5cf6;">
                            <p class="text-muted text-sm">Sleep</p>
                            <div class="stat-value"><span id="reco-sleep"><?= clean($latest['sleep_hours']) ?></span> <span style="font-size:0.9rem;">hrs</span></div>
                        </div>
                    </div>
                    <p class="text-sm" style="margin-bottom:6px;"><strong>Exercise plan:</strong> <span id="reco-exercise"><?= clean($latest['exercise_plan']) ?></span></p>
                    <p class="text-sm" style="margin-bottom:16px;"><strong>Diet:</strong> <span id="reco-diet"><?= clean($latest['diet_recommendation']) ?></span></p>

                    <?php if (!empty($latest['agent_summary'])): ?>
                    <div id="agent-note" style="border-top:1px solid rgba(148,163,184,0.15);padding-top:14px;display:flex;gap:10px;align-items:flex-start;">
                        <span style="font-size:1.1rem;line-height:1;">🤖</span>
                        <div>
                            <p class="text-sm" style="margin-bottom:4px;">
                                <strong>Agent note</strong>
                                <?php if ($latest['agent_decision'] === 'needs_attention'): ?>
                                    <span style="color:#f59e0b;font-weight:600;">· Needs attention</span>
                                <?php elseif ($latest['agent_decision'] === 'on_track'): ?>
                                    <span style="color:#06d6a0;font-weight:600;">· On track</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-sm text-muted" id="agent-note-text"><?= nl2br(clean($latest['agent_summary'])) ?></p>
                            <a href="/bmi_monitor/chat.php" class="text-sm" style="color:#06d6a0;">Ask the agent a question →</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding:20px 24px;">
                    <p class="text-muted text-sm">
                        No AI recommendation yet — take a new reading (Manual Entry below, or from the ESP32)
                        and the model will score it automatically. If this persists, make sure the ML
                        service is running: <code>python ml_service/app.py</code>.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== BMI CATEGORY SCALE ===== -->
        <div class="chart-panel" style="margin-bottom:20px;">
            <p class="panel-title">BMI Category Scale</p>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;text-align:center;">
                <?php
                $categories = [
                    ['Underweight', '< 18.5', '#3b82f6'],
                    ['Normal',      '18.5–24.9','#10b981'],
                    ['Overweight',  '25–29.9',  '#f59e0b'],
                    ['Obese',       '≥ 30',     '#ef4444'],
                ];
                foreach ($categories as [$label, $range, $color]):
                    $isActive = ($label === ($cat['label'] ?? ''));
                ?>
                <div style="padding:16px 8px;border-radius:10px;
                            background:<?= $isActive ? "rgba(".implode(',',sscanf($color,'#%02x%02x%02x')).",0.18)" : 'rgba(255,255,255,0.03)' ?>;
                            border:1px solid <?= $isActive ? $color.'55' : 'rgba(255,255,255,0.06)' ?>;
                            transition:all 0.3s;">
                    <div style="width:10px;height:10px;border-radius:50%;
                                background:<?= $color ?>;margin:0 auto 10px;
                                <?= $isActive ? "box-shadow:0 0 8px $color" : '' ?>"></div>
                    <div style="font-weight:700;font-size:0.85rem;color:<?= $isActive ? $color : '#e2e8f0' ?>">
                        <?= $label ?>
                    </div>
                    <div style="font-size:0.75rem;color:#94a3b8;margin-top:4px;"><?= $range ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== CHARTS ===== -->
        <div class="chart-grid">
            <div class="chart-panel">
                <p class="panel-title">BMI Over Time</p>
                <canvas id="bmiChart" height="200"></canvas>
            </div>
            <div class="chart-panel">
                <p class="panel-title">Weight Over Time</p>
                <canvas id="weightChart" height="200"></canvas>
            </div>
        </div>

        </div><!-- /pdf-area -->

        <!-- Automated Email Settings -->
        <div class="table-panel" style="margin-bottom:20px;">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;">Automated Email Alerts
                    <span style="font-size:0.75rem;color:#94a3b8;font-weight:400;">
                        (sent automatically when you type "email" in the ESP32 Serial Monitor)
                    </span>
                </p>
            </div>
            <div style="padding:20px 24px;">
                <form id="alert-email-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;align-items:end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Recipient email for auto-reports</label>
                        <input type="email" id="alert-email-input" class="form-control"
                               placeholder="you@example.com" value="<?= clean($alert_email) ?>">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="saveAlertEmail()">
                        💾 Save
                    </button>
                </form>
                <p id="alert-email-msg" style="margin-top:10px;font-size:0.85rem;"></p>
            </div>
        </div>

        <!-- Manual Entry Form (for testing without ESP32) -->
        <div class="table-panel">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;">Manual Entry <span style="font-size:0.75rem;color:#94a3b8;font-weight:400;">(for testing without hardware)</span></p>
            </div>
            <div style="padding:20px 24px;">
                <form id="manual-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;align-items:end;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" id="m-height" class="form-control"
                               placeholder="170" min="50" max="300" step="0.1">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" id="m-weight" class="form-control"
                               placeholder="65" min="1" max="500" step="0.1">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="submitManual()">
                        + Add Record
                    </button>
                </form>
                <p id="manual-msg" style="margin-top:10px;font-size:0.85rem;"></p>
            </div>
        </div>

    </main>
</div><!-- /page-wrapper -->

<script src="/bmi_monitor/assets/js/app.js"></script>
<script>
// Save the recipient email used for automated (ESP32 Serial-triggered) reports
function saveAlertEmail() {
    const input = document.getElementById('alert-email-input');
    const msg   = document.getElementById('alert-email-msg');
    const email = input.value.trim();

    fetch('/bmi_monitor/api/update_alert_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alert_email: email })
    })
    .then(r => r.json())
    .then(data => {
        msg.textContent = (data.success ? '✅ ' : '❌ ') + (data.message || '');
        msg.style.color = data.success ? '#06d6a0' : '#ef4444';
    })
    .catch(() => {
        msg.textContent = '❌ Network error. Check if the server is running.';
        msg.style.color = '#ef4444';
    });
}

// Save profile fields (age/gender/activity/goal) used by the ML model
function saveProfile() {
    const age      = parseInt(document.getElementById('p-age').value, 10);
    const gender   = document.getElementById('p-gender').value;
    const activity = document.getElementById('p-activity').value;
    const goal     = document.getElementById('p-goal').value;
    const msg      = document.getElementById('profile-msg');

    if (!age || !gender || !activity || !goal) {
        msg.textContent = '⚠️ Please fill in every field.';
        msg.style.color = '#ef4444';
        return;
    }

    fetch('/bmi_monitor/api/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ age, gender, activity_level: activity, fitness_goal: goal })
    })
    .then(r => r.json())
    .then(data => {
        msg.textContent = (data.success ? '✅ ' : '❌ ') + (data.message || '');
        msg.style.color = data.success ? '#06d6a0' : '#ef4444';
        if (data.success) setTimeout(() => window.location.reload(), 900);
    })
    .catch(() => {
        msg.textContent = '❌ Network error. Check if the server is running.';
        msg.style.color = '#ef4444';
    });
}

// Manual entry: sends data to API endpoint (simulates ESP32 POST)
function submitManual() {
    const height  = parseFloat(document.getElementById('m-height').value);
    const weight  = parseFloat(document.getElementById('m-weight').value);
    const msg     = document.getElementById('manual-msg');

    if (!height || !weight) {
        msg.textContent = '⚠️ Enter both height and weight.';
        msg.style.color = '#ef4444';
        return;
    }

    const bmi = +(weight / ((height / 100) ** 2)).toFixed(2);

    fetch('/bmi_monitor/api/bmi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            height, weight, bmi,
            user_id: <?= $user_id ?>
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msg.textContent = '✅ Record saved! Dashboard will refresh automatically.';
            msg.style.color = '#06d6a0';
            document.getElementById('m-height').value = '';
            document.getElementById('m-weight').value = '';
        } else {
            msg.textContent = '❌ ' + (data.message || 'Error saving record.');
            msg.style.color = '#ef4444';
        }
    })
    .catch(() => {
        msg.textContent = '❌ Network error. Check if the server is running.';
        msg.style.color = '#ef4444';
    });
}
</script>

</body>
</html>
