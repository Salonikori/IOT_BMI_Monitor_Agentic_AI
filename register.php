<?php
// ============================================================
//  register.php  —  New User Registration
// ============================================================
require_once 'includes/config.php';

if (is_logged_in()) redirect('/bmi_monitor/dashboard.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');

    // --- Profile fields (used as inputs to the ML recommendation model) ---
    $age            = isset($_POST['age']) && $_POST['age'] !== '' ? (int) $_POST['age'] : null;
    $gender         = $_POST['gender']         ?? '';
    $activity_level = $_POST['activity_level'] ?? '';
    $fitness_goal   = $_POST['fitness_goal']   ?? '';

    $valid_genders  = ['Male', 'Female'];
    $valid_activity = ['Sedentary', 'Lightly Active', 'Moderately Active', 'Very Active'];
    $valid_goals    = ['Lose Weight', 'Maintain Weight', 'Gain Weight'];

    // --- Validation ---
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($age === null || $age < 1 || $age > 120) {
        $error = 'Please enter a valid age.';
    } elseif (!in_array($gender, $valid_genders, true)) {
        $error = 'Please select a gender.';
    } elseif (!in_array($activity_level, $valid_activity, true)) {
        $error = 'Please select an activity level.';
    } elseif (!in_array($fitness_goal, $valid_goals, true)) {
        $error = 'Please select a fitness goal.';
    } else {
        $db = get_db();

        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'An account with this email already exists.';
            $stmt->close();
        } else {
            $stmt->close();
            // Hash the password before storing (bcrypt)
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $db->prepare(
                "INSERT INTO users (name, email, password, age, gender, activity_level, fitness_goal)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param('sssisss', $name, $email, $hashed, $age, $gender, $activity_level, $fitness_goal);

            if ($stmt->execute()) {
                // Auto-login the new user
                $_SESSION['user_id'] = $db->insert_id;
                $_SESSION['name']    = $name;
                $_SESSION['role']    = 'user';
                $stmt->close();
                redirect('/bmi_monitor/dashboard.php');
            } else {
                $error = 'Registration failed. Please try again.';
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — BMI Monitor</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">
<div class="auth-card">

    <div class="auth-brand">
        <h1>BMI<span>.</span>Monitor</h1>
        <p>Create your free account</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control"
                   placeholder="Rahul Sharma"
                   value="<?= clean($_POST['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control"
                   placeholder="you@example.com"
                   value="<?= clean($_POST['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Min. 6 characters" required>
        </div>

        <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm" class="form-control"
                   placeholder="Repeat password" required>
        </div>

        <p class="form-footer" style="text-align:left;margin:4px 0 12px;">
            A few more details so we can personalise your health recommendations:
        </p>

        <div class="form-group">
            <label class="form-label">Age</label>
            <input type="number" name="age" class="form-control" min="1" max="120"
                   placeholder="22" value="<?= clean($_POST['age'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-control" required>
                <option value="">Select…</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Activity Level</label>
            <select name="activity_level" class="form-control" required>
                <option value="">Select…</option>
                <option value="Sedentary">Sedentary (little/no exercise)</option>
                <option value="Lightly Active">Lightly Active (1–3 days/week)</option>
                <option value="Moderately Active">Moderately Active (3–5 days/week)</option>
                <option value="Very Active">Very Active (6–7 days/week)</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Goal</label>
            <select name="fitness_goal" class="form-control" required>
                <option value="">Select…</option>
                <option value="Lose Weight">Lose Weight</option>
                <option value="Maintain Weight">Maintain Weight</option>
                <option value="Gain Weight">Gain Weight</option>
            </select>
        </div>

        <button type="submit" class="form-submit">Create Account</button>
    </form>

    <div class="form-footer">
        Already have an account? <a href="login.php">Sign in</a>
    </div>
    <div class="form-footer" style="margin-top:8px;">
        <a href="index.php">← Back to Home</a>
    </div>

</div>
</div>
</body>
</html>
