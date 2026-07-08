<?php
/**
 * Configuración global — Carrera 7K Phaway Phu'spu
 *
 * IMPORTANTE: Este archivo vive FUERA de public_html.
 * En cPanel, la estructura debe quedar:
 *   /home/USUARIO/public_html/    ← contenido web accesible
 *   /home/USUARIO/includes/       ← este archivo (NO accesible por web)
 *   /home/USUARIO/storage/        ← Excel y logs (NO accesible por web)
 *   /home/USUARIO/vendor/         ← dependencias de Composer
 */

declare(strict_types=1);

// -----------------------------------------------------------------------------
// Zona horaria y errores
// -----------------------------------------------------------------------------
date_default_timezone_set('America/Lima');

// En producción: no mostrar errores al usuario. Loggearlos al archivo.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// -----------------------------------------------------------------------------
// Rutas absolutas
// -----------------------------------------------------------------------------
define('BASE_PATH',     dirname(__DIR__));                  // /home/USUARIO
define('STORAGE_PATH',  BASE_PATH . '/storage');
define('LOG_PATH',      STORAGE_PATH . '/logs');
define('EXCEL_FILE',    STORAGE_PATH . '/inscripciones.xlsx');
define('RATE_LIMIT_DB', STORAGE_PATH . '/rate_limit.json');
define('VENDOR_AUTOLOAD', BASE_PATH . '/vendor/autoload.php');

ini_set('error_log', LOG_PATH . '/php-errors.log');

// -----------------------------------------------------------------------------
// Configuración del panel administrativo
// -----------------------------------------------------------------------------
// Generar el hash con: php -r "echo password_hash('TU_PASSWORD_AQUI', PASSWORD_DEFAULT);"
// NUNCA guardar la contraseña en texto plano.
define('ADMIN_USER', 'admin');
define('ADMIN_PASS_HASH', '$2y$10$REEMPLAZA_ESTE_HASH_GENERADO_CON_PASSWORD_HASH_EN_PHP.');

// -----------------------------------------------------------------------------
// Configuración del evento
// -----------------------------------------------------------------------------
define('EVENT_NAME',       'Carrera 7K Phaway Phu\'spu');
define('REG_OPEN_DATE',    '2026-07-08 08:00:00');
define('REG_CLOSE_DATE',   '2026-08-05 17:00:00');
define('MAX_PARTICIPANTS', 2000);

// -----------------------------------------------------------------------------
// Configuración de seguridad
// -----------------------------------------------------------------------------
define('CSRF_TOKEN_LIFETIME',       3600);   // 1 hora
define('RATE_LIMIT_MAX_ATTEMPTS',   5);      // envíos permitidos
define('RATE_LIMIT_WINDOW_SECONDS', 3600);   // por hora
define('SESSION_LIFETIME',          1800);   // 30 min sesión admin

// Orígenes permitidos para CORS/Referer (poner el dominio real en producción)
define('ALLOWED_ORIGINS', [
    'https://tu-dominio.com',
    'https://www.tu-dominio.com',
    // Descomenta durante desarrollo local:
    // 'http://localhost',
    // 'http://127.0.0.1',
]);

// -----------------------------------------------------------------------------
// Categorías válidas (para validación en servidor)
// -----------------------------------------------------------------------------
define('VALID_CATEGORIES', [
    'juvenil-varon' => ['label' => 'Juvenil Varón', 'min' => 14, 'max' => 17],
    'juvenil-mujer' => ['label' => 'Juvenil Mujer', 'min' => 14, 'max' => 17],
    'libre-varon'   => ['label' => 'Libre Varón',   'min' => 18, 'max' => 39],
    'libre-mujer'   => ['label' => 'Libre Mujer',   'min' => 18, 'max' => 39],
    'master-varon'  => ['label' => 'Máster Varón',  'min' => 40, 'max' => 100],
    'master-mujer'  => ['label' => 'Máster Mujer',  'min' => 40, 'max' => 100],
]);

// -----------------------------------------------------------------------------
// Configuración de sesión segura
// -----------------------------------------------------------------------------
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('C7K_SESSION');
    session_start();
}
