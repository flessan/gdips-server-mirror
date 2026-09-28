<?php
session_start();

require_once __DIR__ . '/dashboard/incl/auth.php';
$au = new au();
$au->auth('');

if (!empty($_SESSION['accountID'])) {
    header('Location: /dashboard/', true, 302);
} else {
    header('Location: /dashboard/login/login.php', true, 302);
}
exit;
?>