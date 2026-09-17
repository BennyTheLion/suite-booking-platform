<?php
require_once __DIR__ . '/../includes/auth.php';
super_admin_logout();
header('Location: login.php');
