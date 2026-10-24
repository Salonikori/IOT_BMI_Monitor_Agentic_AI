<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMI Monitor — IoT Health Tracking</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ===================== HERO SECTION ===================== -->
<section class="hero">

    <!-- Top navigation bar -->
    <nav style="position:fixed;top:0;left:0;right:0;z-index:100;
                display:flex;align-items:center;justify-content:space-between;
                padding:16px 40px;background:rgba(10,15,30,0.8);
                backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,0.06);">

        <div class="brand-logo">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#06d6a0" stroke-width="2.5">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            BMI<span class="dot">.</span>Monitor
        </div>

        <div style="display:flex;gap:12px;align-items:center;">
            <a href="login.php"    class="btn btn-outline btn-sm">Log In</a>
            <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
        </div>
    </nav>

    <!-- Hero copy -->
    <div style="margin-top:60px;">
        <p class="hero-eyebrow">⚡ Powered by ESP32 IoT Technology</p>
        <h1 class="hero-title">
            Smart Body Mass<br>
            <em>Index Monitoring</em>
        </h1>
        <p class="hero-sub">
            Real-time height & weight data from your IoT sensor, delivered to
            a beautiful dashboard — track trends, get health insights, download reports.
        </p>
        <div class="hero-actions">
            <a href="register.php" class="btn btn-primary" style="padding:13px 30px;font-size:1rem;">
                Create Free Account
            </a>
            <a href="login.php" class="btn btn-outline" style="padding:13px 30px;font-size:1rem;">
                Sign In
            </a>
        </div>
    </div>

    <!-- Feature cards -->
    <div class="feature-grid">
        <div class="feature-card">
            <div class="icon">📡</div>
            <h3>ESP32 Integration</h3>
            <p>Your hardware device sends data automatically via Wi-Fi to the server in real time.</p>
        </div>
        <div class="feature-card">
            <div class="icon">📊</div>
            <h3>Trend Charts</h3>
            <p>Interactive Chart.js graphs show your BMI and weight history over time.</p>
        </div>
        <div class="feature-card">
            <div class="icon">🏥</div>
            <h3>Health Insights</h3>
            <p>Colour-coded BMI categories with personalised health suggestions.</p>
        </div>
        <div class="feature-card">
            <div class="icon">📄</div>
            <h3>PDF Reports</h3>
            <p>Download a printable health report with one click at any time.</p>
        </div>
    </div>

    <!-- Tech stack badge strip -->
    <div style="margin-top:60px;display:flex;gap:12px;flex-wrap:wrap;justify-content:center;position:relative;">
        <?php foreach(['ESP32','PHP','MySQL','Chart.js','REST API'] as $tech): ?>
        <span style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.08);
                     border-radius:999px;padding:5px 16px;font-size:0.78rem;color:#94a3b8;">
            <?= $tech ?>
        </span>
        <?php endforeach; ?>
    </div>

</section>

</body>
</html>
