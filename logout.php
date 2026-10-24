<?php
// logout.php — Destroys the session and redirects to login
require_once 'includes/config.php';
session_destroy();
redirect('/bmi_monitor/login.php');
