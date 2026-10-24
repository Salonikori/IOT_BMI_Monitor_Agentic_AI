<?php
// ============================================================
//  history.php  —  Full BMI History for Logged-in User
// ============================================================
require_once 'includes/config.php';
require_login();

$user_id = (int) $_SESSION['user_id'];
$db      = get_db();

// Handle delete single record
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $rid  = (int) $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM bmi_records WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $rid, $user_id);
    $stmt->execute();
    $stmt->close();
    redirect('/bmi_monitor/history.php?deleted=1');
}

// Fetch all records for this user (newest first)
$stmt = $db->prepare(
    "SELECT id, height, weight, bmi, recorded_at
       FROM bmi_records
      WHERE user_id = ?
      ORDER BY recorded_at DESC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$active_page = 'history';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History — BMI Monitor</title>
    <link rel="stylesheet" href="/bmi_monitor/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<div class="page-wrapper">
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">

        <button class="hamburger" id="hamburger" aria-label="Menu">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <div class="topbar">
            <div>
                <h1 class="page-title">BMI <span>History</span></h1>
                <p class="text-muted text-sm"><?= count($records) ?> total records</p>
            </div>
            <button class="btn btn-outline btn-sm" onclick="downloadPDF()">
                📄 Export PDF
            </button>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">✅ Record deleted successfully.</div>
        <?php endif; ?>

        <div id="pdf-area">

        <!-- Charts -->
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

        <!-- Records Table -->
        <div class="table-panel">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;">All Measurements</p>
            </div>

            <?php if (empty($records)): ?>
            <div style="padding:48px;text-align:center;color:#94a3b8;">
                <div style="font-size:3rem;margin-bottom:12px;">📭</div>
                <p>No BMI records yet. Use the Dashboard to add your first measurement!</p>
                <a href="dashboard.php" class="btn btn-primary" style="margin-top:16px;">Go to Dashboard</a>
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
                                     border:1px solid <?= $cat['color'].'33' ?>;">
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
                                data-person="<?= clean($_SESSION['name']) ?>">
                            ✉️ Email
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete('/bmi_monitor/history.php?delete=<?= $r['id'] ?>',
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
        </div><!-- /table-panel -->

        </div><!-- /pdf-area -->

    </main>
</div>

<script src="/bmi_monitor/assets/js/app.js"></script>
</body>
</html>
