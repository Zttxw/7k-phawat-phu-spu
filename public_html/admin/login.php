<?php
/**
 * Login del panel administrativo.
 * Compara con hash bcrypt guardado en config.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
start_secure_session();

// Si ya está autenticado, redirigir al panel
if (!empty($_SESSION['admin_authenticated']) && ($_SESSION['admin_last_activity'] ?? 0) > (time() - SESSION_LIFETIME)) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limit por IP para intentos de login
    if (!rate_limit_check('admin_login:' . client_ip())) {
        security_log('admin_login_ratelimit');
        $error = 'Demasiados intentos. Espere una hora.';
    } elseif (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Sesión expirada. Recargue la página.';
    } else {
        $user = clean_string($_POST['username'] ?? '', 50);
        $pass = (string)($_POST['password'] ?? '');

        // Comparación en tiempo constante del usuario + hash de contraseña
        $userOk = hash_equals(ADMIN_USER, $user);
        $passOk = password_verify($pass, ADMIN_PASS_HASH);

        // Delay artificial pequeño para dificultar timing attacks
        usleep(random_int(100000, 300000));

        if ($userOk && $passOk) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_user']          = ADMIN_USER;
            $_SESSION['admin_last_activity'] = time();
            csrf_rotate();
            security_log('admin_login_ok', ['user' => ADMIN_USER]);
            header('Location: index.php');
            exit;
        }
        security_log('admin_login_fail', ['user' => $user]);
        $error = 'Usuario o contraseña incorrectos.';
    }
}

$csrf = csrf_generate_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-box {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,215,0,0.2);
            border-radius: 1.5rem;
            padding: 40px 32px;
            width: 100%;
            max-width: 400px;
        }
        .login-box h1 { text-align: center; margin-bottom: 8px; font-size: 24px; }
        .login-box .sub { text-align: center; color: rgba(255,255,255,0.5); margin-bottom: 32px; font-size: 14px; }
        .login-box label { display: block; margin-bottom: 6px; font-size: 13px; color: rgba(255,255,255,0.7); }
        .login-box input { margin-bottom: 20px; }
        .err {
            background: rgba(220,38,38,0.1);
            border: 1px solid rgba(220,38,38,0.3);
            color: #FCA5A5;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1><span class="gradient-text">Panel Admin</span></h1>
        <p class="sub">Carrera 7K Phaway Phu'spu</p>

        <?php if ($error): ?>
            <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

            <label for="username">Usuario</label>
            <input id="username" name="username" type="text" class="form-input" required maxlength="50" autocomplete="username">

            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" class="form-input" required autocomplete="current-password">

            <button type="submit" class="btn-primary w-full justify-center" style="width:100%; display:flex; justify-content:center;">
                Ingresar
            </button>
        </form>
    </div>
</body>
</html>
