<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
start_secure_session();

if (!empty($_SESSION['admin_user'])) {
    security_log('admin_logout', ['user' => $_SESSION['admin_user']]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: login.php');
exit;
