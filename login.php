<?php
// ============================================================
//  login.php  —  User & Admin Login
// ============================================================
require_once 'includes/config.php';

// Already logged in? Send to dashboard
if (is_logged_in()) {
    redirect('/bmi_monitor/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $db   = get_db();
        $stmt = $db->prepare("SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // Redirect admin to admin panel, users to dashboard
            if ($user['role'] === 'admin') {
                redirect('/bmi_monitor/admin/index.php');
            } else {
                redirect('/bmi_monitor/dashboard.php');
            }
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — BMI Monitor</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
<div class="auth-card">

    <div class="auth-brand">
        <h1>BMI<span>.</span>Monitor</h1>
        <p>Sign in to your health dashboard</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control"
                   placeholder="you@example.com"
                   value="<?= clean($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="••••••••" required>
        </div>

        <button type="submit" class="form-submit">Sign In</button>
    </form>

    <div class="form-footer">
        Don't have an account? <a href="register.php">Register here</a>
    </div>

    <!-- Demo credentials hint -->
    <div style="margin-top:24px;padding:14px;background:rgba(6,214,160,0.05);
                border:1px solid rgba(6,214,160,0.15);border-radius:8px;font-size:0.8rem;color:#94a3b8;">
        <strong style="color:#06d6a0;">Demo Credentials</strong><br>
        User: user@bmimonitor.com / password<br>
        Admin: admin@bmimonitor.com / password
    </div>

    <div class="form-footer" style="margin-top:16px;">
        <a href="index.php">← Back to Home</a>
    </div>
</div>
</div>
</body>
</html>
