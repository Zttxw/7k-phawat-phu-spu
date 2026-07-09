<?php
/**
 * Endpoint: GET api/csrf-token.php
 * Devuelve un token CSRF para incrustar en el formulario.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(['success' => false, 'message' => 'Método no permitido.'], 405);
}

if (!verify_origin()) {
    json_response(['success' => false, 'message' => 'Origen no autorizado.'], 400);
}

json_response([
    'success' => true,
    'token'   => csrf_generate_token(),
    'ttl'     => CSRF_TOKEN_LIFETIME,
]);
