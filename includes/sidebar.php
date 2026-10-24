<?php
// ============================================================
//  includes/sidebar.php
//  Reusable sidebar nav — include on every authenticated page
//  Usage: require_once 'includes/sidebar.php';
//  Requires: $active_page variable set to 'dashboard'|'history'|'admin'
// ============================================================
$active = $active_page ?? '';
$initials = strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1));
?>
<!-- Mobile overlay -->
<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="#06d6a0" stroke-width="2.5">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06
                         a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78
                         1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            BMI<span class="dot">.</span>Monitor
        </div>
    </div>

    <!-- Navigation links -->
    <nav class="sidebar-nav">
        <p class="nav-label">Main</p>

        <a href="/bmi_monitor/dashboard.php"
           class="nav-link <?= $active==='dashboard' ? 'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3"
                width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
            </svg>
            Dashboard
        </a>

        <a href="/bmi_monitor/history.php"
           class="nav-link <?= $active==='history' ? 'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            History
        </a>

        <a href="/bmi_monitor/chat.php"
           class="nav-link <?= $active==='chat' ? 'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
            </svg>
            AI Agent
        </a>

        <?php if (is_admin()): ?>
        <p class="nav-label" style="margin-top:12px;">Admin</p>
        <a href="/bmi_monitor/admin/index.php"
           class="nav-link <?= $active==='admin' ? 'active':'' ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Admin Panel
        </a>
        <?php endif; ?>
    </nav>

    <!-- User footer -->
    <div class="sidebar-footer">
        <div class="user-pill">
            <div class="user-avatar"><?= $initials ?></div>
            <div class="user-info">
                <div class="user-name"><?= clean($_SESSION['name'] ?? 'User') ?></div>
                <div class="user-role"><?= ucfirst($_SESSION['role'] ?? 'user') ?></div>
            </div>
        </div>
        <a href="/bmi_monitor/logout.php"
           style="display:flex;align-items:center;gap:8px;margin-top:14px;
                  font-size:0.82rem;color:#94a3b8;padding:7px 0;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Sign Out
        </a>
    </div>
</aside>
