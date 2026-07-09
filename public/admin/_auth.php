<?php
/**
 * Guarda de autenticación. Include al inicio de cada página protegida del admin.
 * Redirige a login.php si no hay sesión válida.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
start_secure_session();

$authed  = !empty($_SESSION['admin_authenticated']);
$fresh   = ($_SESSION['admin_last_activity'] ?? 0) > (time() - SESSION_LIFETIME);

if (!$authed || !$fresh) {
    // Sesión expirada o no iniciada
    if ($authed && !$fresh) {
        security_log('admin_session_expired', ['user' => $_SESSION['admin_user'] ?? '']);
    }
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}

// Renueva actividad
$_SESSION['admin_last_activity'] = time();
