<?php
// ============================================================
//  admin/index.php  —  Admin Panel
//  Shows all users and their BMI records
//  Only accessible to accounts with role='admin'
// ============================================================
require_once '../includes/config.php';
require_login();

// Block non-admins
if (!is_admin()) {
    redirect('/bmi_monitor/dashboard.php');
}

$db  = get_db();
$msg = '';

// --- Handle user deletion ---
if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    $uid  = (int) $_GET['delete_user'];
    // Prevent deleting yourself
    if ($uid === (int)$_SESSION['user_id']) {
        $msg = 'error:You cannot delete your own account.';
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
        $msg = 'success:User and all their records deleted.';
    }
}

// --- Handle single record deletion ---
if (isset($_GET['delete_record']) && is_numeric($_GET['delete_record'])) {
    $rid  = (int) $_GET['delete_record'];
    $stmt = $db->prepare("DELETE FROM bmi_records WHERE id = ?");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $stmt->close();
    $msg = 'success:Record deleted.';
}

// --- Fetch all users with their latest BMI and record count ---
$users = $db->query(
    "SELECT u.id, u.name, u.email, u.role, u.created_at,
            COUNT(b.id)   AS record_count,
            MAX(b.bmi)    AS latest_bmi,
            MAX(b.recorded_at) AS last_reading
       FROM users u
       LEFT JOIN bmi_records b ON b.user_id = u.id
      GROUP BY u.id
      ORDER BY u.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

// --- Summary stats ---
$total_users   = count($users);
$total_records = $db->query("SELECT COUNT(*) FROM bmi_records")->fetch_row()[0];

// Fetch latest 20 records across all users for recent activity
$recent = $db->query(
    "SELECT b.id, b.height, b.weight, b.bmi, b.recorded_at, u.name, u.email
       FROM bmi_records b
       JOIN users u ON u.id = b.user_id
      ORDER BY b.recorded_at DESC
      LIMIT 20"
)->fetch_all(MYSQLI_ASSOC);

$active_page = 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel — BMI Monitor</title>
    <link rel="stylesheet" href="/bmi_monitor/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">
    <?php require_once '../includes/sidebar.php'; ?>

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
                <h1 class="page-title">Admin <span>Panel</span></h1>
                <p class="text-muted text-sm">Manage all users and BMI records</p>
            </div>
            <div class="live-badge">
                <span class="live-dot"></span> Admin View
            </div>
        </div>

        <?php if ($msg):
            [$type, $text] = explode(':', $msg, 2);
        ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>">
            <?= $type === 'error' ? '⚠️' : '✅' ?> <?= clean($text) ?>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="cards-grid" style="margin-bottom:28px;">
            <div class="stat-card" style="--accent-color:#06d6a0;">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= $total_users ?></div>
                <div class="stat-icon">👥</div>
            </div>
            <div class="stat-card" style="--accent-color:#3b82f6;">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?= $total_records ?></div>
                <div class="stat-icon">📊</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="table-panel" style="margin-bottom:28px;">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;">All Users</p>
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Records</th>
                        <th>Latest BMI</th>
                        <th>Last Reading</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $isMe = ((int)$u['id'] === (int)$_SESSION['user_id']);
                ?>
                <tr>
                    <td class="text-muted"><?= $u['id'] ?></td>
                    <td>
                        <strong><?= clean($u['name']) ?></strong>
                        <?php if ($isMe): ?>
                        <span style="font-size:0.7rem;color:#06d6a0;margin-left:4px;">(you)</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= clean($u['email']) ?></td>
                    <td>
                        <span style="font-size:0.75rem;font-weight:700;
                                     padding:3px 10px;border-radius:999px;
                                     background:<?= $u['role']==='admin' ? 'rgba(139,92,246,0.15)' : 'rgba(6,214,160,0.12)' ?>;
                                     color:<?= $u['role']==='admin' ? '#8b5cf6' : '#06d6a0' ?>;">
                            <?= ucfirst($u['role']) ?>
                        </span>
                    </td>
                    <td><?= (int)$u['record_count'] ?></td>
                    <td>
                        <?php if ($u['latest_bmi']): ?>
                        <?php $cat = bmi_category((float)$u['latest_bmi']); ?>
                        <span style="color:<?= $cat['color'] ?>;font-weight:700;">
                            <?= number_format($u['latest_bmi'], 1) ?>
                        </span>
                        <span style="font-size:0.75rem;color:#94a3b8;">(<?= $cat['label'] ?>)</span>
                        <?php else: echo '<span class="text-muted">—</span>'; endif; ?>
                    </td>
                    <td class="text-muted" style="font-size:0.82rem;">
                        <?= $u['last_reading'] ? date('d M Y', strtotime($u['last_reading'])) : '—' ?>
                    </td>
                    <td class="text-muted" style="font-size:0.82rem;">
                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                    </td>
                    <td style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="user_records.php?uid=<?= $u['id'] ?>"
                           class="btn btn-outline btn-sm">View</a>
                        <?php if (!$isMe): ?>
                        <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete('/bmi_monitor/admin/index.php?delete_user=<?= $u['id'] ?>',
                                         'Delete user <?= addslashes($u['name']) ?> and ALL their records?')">
                            🗑 Delete
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="table-panel">
            <div class="table-header">
                <p class="panel-title" style="margin-bottom:0;">Recent Activity <span style="font-size:0.75rem;color:#94a3b8;font-weight:400;">(last 20 readings)</span></p>
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Record ID</th>
                        <th>User</th>
                        <th>Height (cm)</th>
                        <th>Weight (kg)</th>
                        <th>BMI</th>
                        <th>Category</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $r):
                    $cat = bmi_category((float)$r['bmi']);
                ?>
                <tr>
                    <td class="text-muted">#<?= $r['id'] ?></td>
                    <td>
                        <strong><?= clean($r['name']) ?></strong><br>
                        <span style="font-size:0.75rem;color:#94a3b8;"><?= clean($r['email']) ?></span>
                    </td>
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
                    <td class="text-muted" style="font-size:0.82rem;">
                        <?= date('d M Y, h:i A', strtotime($r['recorded_at'])) ?>
                    </td>
                    <td style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button class="btn btn-icon-mail js-email-btn" title="Email this record"
                                data-record-id="<?= $r['id'] ?>"
                                data-bmi="<?= number_format($r['bmi'], 1) ?>"
                                data-color="<?= $cat['color'] ?>"
                                data-category="<?= $cat['label'] ?>"
                                data-height="<?= number_format($r['height'], 1) ?>"
                                data-weight="<?= number_format($r['weight'], 1) ?>"
                                data-date="<?= date('d M Y, h:i A', strtotime($r['recorded_at'])) ?>"
                                data-person="<?= clean($r['name']) ?>"
                                data-email="<?= clean($r['email']) ?>">
                            ✉️
                        </button>
                        <button class="btn btn-danger btn-sm"
                                onclick="confirmDelete('/bmi_monitor/admin/index.php?delete_record=<?= $r['id'] ?>',
                                         'Delete this BMI record?')">
                            🗑
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

    </main>
</div>
<script src="/bmi_monitor/assets/js/app.js"></script>
</body>
</html>
