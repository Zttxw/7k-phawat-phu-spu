<?php
/**
 * Fuerza la descarga del archivo Excel de inscripciones.
 * Solo accesible con sesión admin activa + CSRF válido.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/excel-handler.php';

if (!csrf_verify($_GET['csrf'] ?? '')) {
    http_response_code(400);
    exit('Token CSRF inválido.');
}

try {
    excel_ensure_exists();
} catch (Throwable $e) {
    error_log('[admin/descargar.php] ' . $e->getMessage());
    http_response_code(500);
    exit('Error al preparar el archivo.');
}

if (!is_readable(EXCEL_FILE)) {
    http_response_code(404);
    exit('Archivo no encontrado.');
}

security_log('excel_download', ['user' => $_SESSION['admin_user'] ?? '', 'size' => filesize(EXCEL_FILE)]);

$filename = 'inscripciones_c7k_' . date('Y-m-d_His') . '.xlsx';

// Limpiar cualquier output previo
while (ob_get_level()) ob_end_clean();

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Length: ' . filesize(EXCEL_FILE));
header('X-Content-Type-Options: nosniff');

readfile(EXCEL_FILE);
exit;
