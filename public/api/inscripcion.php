<?php
/**
 * Endpoint: POST api/inscripcion.php
 * Recibe el formulario del participante, valida, y agrega una fila al Excel.
 *
 * Respuestas:
 *   200 { success: true,  message, id }
 *   422 { success: false, message, errors: { campo: mensaje } }
 *   409 { success: false, message } (DNI duplicado o cupo lleno)
 *   429 { success: false, message } (rate limit)
 *   400 { success: false, message } (CSRF / origen inválido)
 *   500 { success: false, message } (error interno)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/excel-handler.php';

// -----------------------------------------------------------------------------
// Método
// -----------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['success' => false, 'message' => 'Método no permitido.'], 405);
}

// -----------------------------------------------------------------------------
// Verificación de origen
// -----------------------------------------------------------------------------
if (!verify_origin()) {
    security_log('origin_rejected', ['origin' => $_SERVER['HTTP_ORIGIN'] ?? '', 'referer' => $_SERVER['HTTP_REFERER'] ?? '']);
    json_response(['success' => false, 'message' => 'Origen no autorizado.'], 400);
}

// -----------------------------------------------------------------------------
// Rate limit por IP
// -----------------------------------------------------------------------------
if (!rate_limit_check('inscripcion:' . client_ip())) {
    security_log('rate_limit_exceeded', ['endpoint' => 'inscripcion']);
    json_response([
        'success' => false,
        'message' => 'Ha realizado demasiados intentos. Intente de nuevo en una hora.'
    ], 429);
}

// -----------------------------------------------------------------------------
// CSRF
// -----------------------------------------------------------------------------
$csrf = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($csrf)) {
    security_log('csrf_invalid');
    json_response(['success' => false, 'message' => 'Sesión expirada. Recargue la página.'], 400);
}

// -----------------------------------------------------------------------------
// Honeypot
// -----------------------------------------------------------------------------
if (!empty($_POST['website'])) {
    security_log('honeypot_triggered', ['value' => substr((string)$_POST['website'], 0, 50)]);
    // Simular éxito para no dar pistas al bot
    json_response(['success' => true, 'message' => 'Inscripción registrada.', 'id' => 0], 200);
}

// -----------------------------------------------------------------------------
// Ventana de inscripción
// -----------------------------------------------------------------------------
$now = time();
if ($now < strtotime(REG_OPEN_DATE)) {
    json_response(['success' => false, 'message' => 'Las inscripciones aún no han iniciado.'], 400);
}
if ($now > strtotime(REG_CLOSE_DATE)) {
    json_response(['success' => false, 'message' => 'El período de inscripciones ha cerrado.'], 400);
}

// -----------------------------------------------------------------------------
// Extracción y sanitización
// -----------------------------------------------------------------------------
$in = [
    'nombres_completos' => clean_string($_POST['nombres_completos'] ?? '', 120),
    'dni'               => clean_string($_POST['dni'] ?? '', 8),
    'edad'              => clean_string($_POST['edad'] ?? '', 3),
    'celular'           => clean_string($_POST['celular'] ?? '', 9),
    'domicilio'         => clean_string($_POST['domicilio'] ?? '', 150),
    'distrito'          => clean_string($_POST['distrito'] ?? '', 60),
    'provincia'         => clean_string($_POST['provincia'] ?? '', 60),
    'departamento'      => clean_string($_POST['departamento'] ?? '', 60),
    'categoria'         => clean_string($_POST['categoria'] ?? '', 20),
    'apoderado_nombres' => clean_string($_POST['apoderado_nombres'] ?? '', 120),
    'apoderado_dni'     => clean_string($_POST['apoderado_dni'] ?? '', 8),
    'apoderado_celular' => clean_string($_POST['apoderado_celular'] ?? '', 9),
    'salud'             => clean_string($_POST['salud'] ?? '', 10),
    'talla'             => clean_string($_POST['talla'] ?? '', 10),
    'accept_terms'      => ($_POST['accept_terms'] ?? '') === 'on',
];

// -----------------------------------------------------------------------------
// Validación
// -----------------------------------------------------------------------------
$errors = [];

if (!valid_name($in['nombres_completos'])) {
    $errors['nombres_completos'] = 'Ingrese apellidos y nombres válidos (5-120 letras).';
}
if (!valid_dni($in['dni'])) {
    $errors['dni'] = 'DNI debe tener 8 dígitos numéricos.';
}
$edadInt = (int)$in['edad'];
if (!valid_age($edadInt)) {
    $errors['edad'] = 'Edad inválida (rango 14-100).';
}
if (!valid_cel($in['celular'])) {
    $errors['celular'] = 'Celular debe iniciar con 9 y tener 9 dígitos.';
}
if (!valid_place($in['distrito'])) {
    $errors['distrito'] = 'Distrito inválido.';
}
if (!valid_place($in['provincia'])) {
    $errors['provincia'] = 'Provincia inválida.';
}
if (!valid_place($in['departamento'])) {
    $errors['departamento'] = 'Departamento inválido.';
}
if ($in['domicilio'] !== '' && mb_strlen($in['domicilio'], 'UTF-8') > 150) {
    $errors['domicilio'] = 'Domicilio demasiado largo.';
}

if (!isset(VALID_CATEGORIES[$in['categoria']])) {
    $errors['categoria'] = 'Categoría inválida.';
} elseif (!isset($errors['edad']) && !valid_category_for_age($in['categoria'], $edadInt)) {
    $errors['categoria'] = 'La categoría no corresponde con la edad indicada.';
}

if (!$in['accept_terms']) {
    $errors['accept_terms'] = 'Debe aceptar la declaración jurada.';
}

if ($in['salud'] !== 'ok' && $in['salud'] !== 'anexos') {
    $errors['salud'] = 'Debe seleccionar una opción de salud válida.';
}

$valid_tallas = ['14', '16', 'S', 'M', 'L', 'XL'];
if (!in_array($in['talla'], $valid_tallas, true)) {
    $errors['talla'] = 'Debe seleccionar una talla válida.';
}

// Menores: apoderado obligatorio
if (!isset($errors['edad']) && $edadInt < 18) {
    if (!valid_name($in['apoderado_nombres'])) {
        $errors['apoderado_nombres'] = 'Datos del apoderado obligatorios para menores.';
    }
    if (!valid_dni($in['apoderado_dni'])) {
        $errors['apoderado_dni'] = 'DNI del apoderado inválido.';
    }
    if (!valid_cel($in['apoderado_celular'])) {
        $errors['apoderado_celular'] = 'Celular del apoderado inválido.';
    }
}

if ($errors) {
    json_response([
        'success' => false,
        'message' => 'Revise los campos marcados.',
        'errors'  => $errors,
    ], 422);
}

// -----------------------------------------------------------------------------
// Anti-duplicados y cupo máximo
// -----------------------------------------------------------------------------
try {
    if (excel_dni_exists($in['dni'])) {
        json_response([
            'success' => false,
            'message' => 'Ya existe una inscripción con ese DNI.',
        ], 409);
    }

    if (excel_count() >= MAX_PARTICIPANTS) {
        json_response([
            'success' => false,
            'message' => 'Se ha alcanzado el número máximo de participantes.',
        ], 409);
    }

    // -------------------------------------------------------------------------
    // Persistir
    // -------------------------------------------------------------------------
    $row = [
        'fecha_registro'    => date('Y-m-d H:i:s'),
        'nombres_completos' => mb_strtoupper($in['nombres_completos'], 'UTF-8'),
        'dni'               => $in['dni'],
        'edad'              => (string)$edadInt,
        'celular'           => $in['celular'],
        'domicilio'         => $in['domicilio'],
        'distrito'          => $in['distrito'],
        'provincia'         => $in['provincia'],
        'departamento'      => $in['departamento'],
        'categoria'         => VALID_CATEGORIES[$in['categoria']]['label'],
        'apoderado_nombres' => $in['apoderado_nombres'] !== '' ? mb_strtoupper($in['apoderado_nombres'], 'UTF-8') : '',
        'apoderado_dni'     => $in['apoderado_dni'],
        'apoderado_celular' => $in['apoderado_celular'],
        'acepta_dj'         => $in['accept_terms'] ? 'SI (' . (($in['salud'] === 'ok') ? 'Buena Salud, No Anexos' : 'Presentará Anexos') . ')' : 'NO',
        'estado'            => 'Pre-inscrito',
        'ip'                => client_ip(),
        'talla'             => $in['talla'],
    ];

    $result = excel_append_row($row);

    csrf_rotate();

    security_log('inscripcion_ok', ['id' => $result['id'], 'dni' => $in['dni']]);

    $msg = ($in['salud'] === 'ok') 
        ? '¡Inscripción registrada exitosamente! Solo debes traer tu DNI para recoger tu kit.'
        : '¡Inscripción registrada exitosamente! Recuerda traer tus Anexos firmados y tu DNI para recoger tu kit.';

    json_response([
        'success' => true,
        'message' => $msg,
        'id'      => $result['id'],
    ], 200);

} catch (Throwable $e) {
    security_log('inscripcion_error', ['error' => $e->getMessage()]);
    error_log('[inscripcion.php] ' . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Ocurrió un error al guardar. Intente nuevamente.'
    ], 500);
}
