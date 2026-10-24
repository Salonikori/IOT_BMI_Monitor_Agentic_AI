<?php
// ============================================================
//  admin/user_records.php  —  View one user's BMI records
// ============================================================
require_once '../includes/config.php';
require_login();
if (!is_admin()) redirect('/bmi_monitor/dashboard.php');

$uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
if (!$uid) redirect('/bmi_monitor/admin/index.php');

$db = get_db();

// Get user info
$stmt = $db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) redirect('/bmi_monitor/admin/index.php');

// Get their records
$stmt = $db->prepare(
    "SELECT id, height, weight, bmi, recorded_at FROM bmi_records
     WHERE user_id = ? ORDER BY recorded_at DESC"
);
$stmt->bind_param('i', $uid);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($user['name']) ?>'s Records — Admin</title>
    <link rel="stylesheet" href="/bmi_monitor/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="page-wrapper">
    <?php require_once '../includes/sidebar.php'; ?>
    <main class="main-content">

        <button class="hamburger" id="hamburger">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="topbar">
            <div>
                <h1 class="page-title"><span><?= clean($user['name']) ?></span>'s Records</h1>
                <p class="text-muted text-sm"><?= clean($user['email']) ?> · Joined <?= date('d M Y', strtotime($user['created_at'])) ?></p>
            </div>
            <a href="/bmi_monitor/admin/index.php" class="btn btn-outline btn-sm">← Back to Admin</a>
        </div>

        <?php if (count($records) >= 2): ?>
        <div class="chart-grid" style="margin-bottom:24px;">
            <div class="chart-panel">
                <p class="panel-title">BMI Trend</p>
                <canvas id="bmiChart" height="200"></canvas>
            </div>
            <div class="chart-panel">
                <p class="panel-title">Weight Trend</p>
                <canvas id="weightChart" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <div class="table-panel">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;"><?= count($records) ?> Total Measurements</p>
            </div>
            <?php if (empty($records)): ?>
            <div style="padding:48px;text-align:center;color:#94a3b8;">
                <div style="font-size:2.5rem;">📭</div>
                <p style="margin-top:10px;">No records for this user yet.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date & Time</th>
                        <th>Height (cm)</th>
                        <th>Weight (kg)</th>
                        <th>BMI</th>
                        <th>Category</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $i => $r):
                    $cat = bmi_category((float)$r['bmi']);
                ?>
                <tr>
                    <td class="text-muted"><?= count($records) - $i ?></td>
                    <td><?= date('d M Y, h:i A', strtotime($r['recorded_at'])) ?></td>
                    <td><?= number_format($r['height'], 1) ?></td>
                    <td><?= number_format($r['weight'], 1) ?></td>
                    <td><strong style="color:<?= $cat['color'] ?>"><?= number_format($r['bmi'], 2) ?></strong></td>
                    <td>
                        <span class="bmi-badge"
                              style="background:<?= $cat['bg'] ?>;color:<?= $cat['color'] ?>;
                                     border:1px solid <?= $cat['color'].'33' ?>">
                            <?= $cat['label'] ?>
                        </span>
                    </td>
                    <td style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="btn btn-mail btn-sm js-email-btn"
                                data-record-id="<?= $r['id'] ?>"
                                data-bmi="<?= number_format($r['bmi'], 1) ?>"
                                data-color="<?= $cat['color'] ?>"
                                data-category="<?= $cat['label'] ?>"
                                data-height="<?= number_format($r['height'], 1) ?>"
                                data-weight="<?= number_format($r['weight'], 1) ?>"
                                data-date="<?= date('d M Y, h:i A', strtotime($r['recorded_at'])) ?>"
                                data-person="<?= clean($user['name']) ?>"
                                data-email="<?= clean($user['email']) ?>">
                            ✉️ Email
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete('/bmi_monitor/admin/index.php?delete_record=<?= $r['id'] ?>',
                                         'Delete this record?')">
                            🗑 Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<script src="/bmi_monitor/assets/js/app.js"></script>
<script>
// Build charts from inline PHP data for this specific user
document.addEventListener('DOMContentLoaded', () => {
    const records = <?= json_encode(array_reverse($records)) ?>;
    if (records.length < 2) return;

    const labels  = records.map(r => new Date(r.recorded_at).toLocaleDateString('en-IN',{day:'2-digit',month:'short'}));
    const bmis    = records.map(r => parseFloat(r.bmi));
    const weights = records.map(r => parseFloat(r.weight));

    const opts = {
        responsive:true,
        plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#1f2937',titleColor:'#e2e8f0',bodyColor:'#94a3b8'} },
        scales:{
            x:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'#94a3b8',font:{size:11}} },
            y:{ grid:{color:'rgba(255,255,255,0.05)'}, ticks:{color:'#94a3b8',font:{size:11}} }
        }
    };

    const bmiCtx = document.getElementById('bmiChart');
    if (bmiCtx) {
        const g = bmiCtx.getContext('2d').createLinearGradient(0,0,0,200);
        g.addColorStop(0,'rgba(6,214,160,0.3)'); g.addColorStop(1,'rgba(6,214,160,0)');
        new Chart(bmiCtx,{type:'line',data:{labels,datasets:[{label:'BMI',data:bmis,borderColor:'#06d6a0',backgroundColor:g,borderWidth:2.5,tension:0.4,fill:true,pointBackgroundColor:'#06d6a0',pointRadius:4}]},options:opts});
    }

    const wCtx = document.getElementById('weightChart');
    if (wCtx) {
        new Chart(wCtx,{type:'bar',data:{labels,datasets:[{label:'Weight',data:weights,backgroundColor:'rgba(59,130,246,0.5)',borderColor:'#3b82f6',borderWidth:1.5,borderRadius:5}]},options:opts});
    }
});
</script>
</body>
</html>
