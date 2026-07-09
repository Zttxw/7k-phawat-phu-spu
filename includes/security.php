<?php
/**
 * Módulo de seguridad — Carrera 7K
 * CSRF, rate-limit por IP, sanitización, validación estricta.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $sess_dir = STORAGE_PATH . '/sessions';
        if (!is_dir($sess_dir)) @mkdir($sess_dir, 0755, true);
        session_save_path($sess_dir);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

// =============================================================================
// CSRF
// =============================================================================

function csrf_generate_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || ($_SESSION['csrf_time'] ?? 0) < time() - CSRF_TOKEN_LIFETIME) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_time']  = time();
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(?string $token): bool {
    start_secure_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    if (($_SESSION['csrf_time'] ?? 0) < time() - CSRF_TOKEN_LIFETIME) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_rotate(): void {
    start_secure_session();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_time']  = time();
}

// =============================================================================
// IP y verificación de origen
// =============================================================================

function client_ip(): string {
    // Confiar solo en REMOTE_ADDR (los headers X-Forwarded-* son falsificables
    // salvo detrás de un reverse proxy que sepamos que sanea).
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function verify_origin(): bool {
    $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $source  = $origin ?: $referer;
    if ($source === '') return true; // Algunos navegadores no envían Referer en same-origin
    foreach (ALLOWED_ORIGINS as $allowed) {
        if (stripos($source, $allowed) === 0) return true;
    }
    return false;
}

// =============================================================================
// Rate limiting basado en archivo (con flock)
// =============================================================================

function rate_limit_check(string $key): bool {
    $file = RATE_LIMIT_DB;
    $dir  = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $fp = @fopen($file, 'c+');
    if (!$fp) return true; // no bloquear si no podemos escribir; loggear en su lugar

    if (!flock($fp, LOCK_EX)) { fclose($fp); return true; }

    $raw  = stream_get_contents($fp);
    $data = $raw ? (json_decode($raw, true) ?: []) : [];

    $now    = time();
    $window = RATE_LIMIT_WINDOW_SECONDS;

    // Purga entradas viejas para no crecer indefinidamente
    foreach ($data as $k => $entry) {
        if (!isset($entry['ts']) || ($now - $entry['ts']) > $window) {
            unset($data[$k]);
        }
    }

    $entry = $data[$key] ?? ['count' => 0, 'ts' => $now];
    if (($now - $entry['ts']) > $window) {
        $entry = ['count' => 0, 'ts' => $now];
    }
    $entry['count']++;
    $data[$key] = $entry;

    $exceeded = $entry['count'] > RATE_LIMIT_MAX_ATTEMPTS;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return !$exceeded;
}

// =============================================================================
// Sanitización y validación
// =============================================================================

/** Limpia string: recorta, colapsa espacios, remueve caracteres de control. */
function clean_string(?string $v, int $maxLen = 200): string {
    if ($v === null) return '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
    if (mb_strlen($v, 'UTF-8') > $maxLen) {
        $v = mb_substr($v, 0, $maxLen, 'UTF-8');
    }
    return $v;
}

function valid_dni(string $v): bool  { return (bool)preg_match('/^\d{8}$/', $v); }
function valid_cel(string $v): bool  { return (bool)preg_match('/^9\d{8}$/', $v); }
function valid_name(string $v): bool {
    // Letras (incluye tildes/ñ), espacios, comas, puntos, guiones, apóstrofos
    return mb_strlen($v, 'UTF-8') >= 5
        && mb_strlen($v, 'UTF-8') <= 120
        && preg_match('/^[\p{L}\p{M}\s\'\-.,]+$/u', $v) === 1;
}
function valid_place(string $v): bool {
    return mb_strlen($v, 'UTF-8') >= 2
        && mb_strlen($v, 'UTF-8') <= 60
        && preg_match('/^[\p{L}\p{M}\p{N}\s\'\-.,#°]+$/u', $v) === 1;
}
function valid_age(int $age): bool { return $age >= 14 && $age <= 100; }

function valid_category_for_age(string $cat, int $age): bool {
    if (!isset(VALID_CATEGORIES[$cat])) return false;
    $r = VALID_CATEGORIES[$cat];
    return $age >= $r['min'] && $age <= $r['max'];
}

// =============================================================================
// Respuesta JSON
// =============================================================================

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// =============================================================================
// Logging estructurado
// =============================================================================

function security_log(string $event, array $context = []): void {
    if (!is_dir(LOG_PATH)) @mkdir(LOG_PATH, 0755, true);
    $line = sprintf(
        "[%s] %s ip=%s ctx=%s\n",
        date('Y-m-d H:i:s'),
        $event,
        client_ip(),
        json_encode($context, JSON_UNESCAPED_UNICODE)
    );
    @file_put_contents(LOG_PATH . '/security.log', $line, FILE_APPEND | LOCK_EX);
}
