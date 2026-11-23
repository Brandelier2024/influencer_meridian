<?php
session_start();
$_SESSION['meridian_admin'] = true;
$_SESSION['admin_user']     = 'admin';
$_SESSION['csrf_token']     = 'test_token_123';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_stats';
require __DIR__ . '/admin/api.php';