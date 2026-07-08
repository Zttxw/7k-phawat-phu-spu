<?php
/**
 * Manejador de Excel — Carrera 7K
 * Lee y escribe inscripciones en un único archivo .xlsx con file locking.
 *
 * Requiere PhpSpreadsheet (via composer).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!file_exists(VENDOR_AUTOLOAD)) {
    throw new RuntimeException('Composer no instalado. Ejecuta `composer install` en el servidor.');
}
require_once VENDOR_AUTOLOAD;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Columnas del Excel (orden importa)
const EXCEL_HEADERS = [
    'ID',
    'Fecha de Registro',
    'Apellidos y Nombres',
    'DNI',
    'Edad',
    'Celular',
    'Domicilio',
    'Distrito',
    'Provincia',
    'Departamento',
    'Categoría',
    'Nombre Apoderado',
    'DNI Apoderado',
    'Celular Apoderado',
    'IP',
];

/**
 * Ejecuta $callback con un lock exclusivo sobre el archivo de inscripciones,
 * evitando corrupción por concurrencia. Retorna lo que retorne $callback.
 */
function with_excel_lock(callable $callback) {
    $lockFile = EXCEL_FILE . '.lock';
    if (!is_dir(dirname($lockFile))) {
        @mkdir(dirname($lockFile), 0755, true);
    }
    $fp = fopen($lockFile, 'c');
    if (!$fp) throw new RuntimeException('No se pudo crear archivo de lock.');

    // Espera hasta 5 segundos por el lock exclusivo
    $start = microtime(true);
    while (!flock($fp, LOCK_EX | LOCK_NB)) {
        if (microtime(true) - $start > 5.0) {
            fclose($fp);
            throw new RuntimeException('Timeout esperando lock de Excel.');
        }
        usleep(50000);
    }

    try {
        return $callback();
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function excel_ensure_exists(): void {
    if (file_exists(EXCEL_FILE)) return;

    $dir = dirname(EXCEL_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $ss = new Spreadsheet();
    $sheet = $ss->getActiveSheet();
    $sheet->setTitle('Inscripciones');

    foreach (EXCEL_HEADERS as $i => $h) {
        $col = chr(65 + $i); // A, B, C…
        $sheet->setCellValue($col . '1', $h);
    }

    $lastCol = chr(65 + count(EXCEL_HEADERS) - 1);
    $headerRange = "A1:{$lastCol}1";

    $sheet->getStyle($headerRange)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);

    foreach (range('A', $lastCol) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    $sheet->freezePane('A2');

    (new XlsxWriter($ss))->save(EXCEL_FILE);
    @chmod(EXCEL_FILE, 0640);
}

/** Retorna true si el DNI ya está registrado. */
function excel_dni_exists(string $dni): bool {
    return with_excel_lock(function () use ($dni): bool {
        excel_ensure_exists();
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $ss = $reader->load(EXCEL_FILE);
        $sheet = $ss->getActiveSheet();
        $highest = $sheet->getHighestRow();
        for ($r = 2; $r <= $highest; $r++) {
            $val = (string)$sheet->getCell("D{$r}")->getValue();
            if ($val === $dni) return true;
        }
        return false;
    });
}

/**
 * Añade una inscripción. Retorna ['id' => int] al éxito.
 * $row debe contener todas las claves esperadas (validadas por el caller).
 */
function excel_append_row(array $row): array {
    return with_excel_lock(function () use ($row): array {
        excel_ensure_exists();

        $reader = new XlsxReader();
        $ss = $reader->load(EXCEL_FILE);
        $sheet = $ss->getActiveSheet();
        $nextRow = $sheet->getHighestRow() + 1;
        $id = $nextRow - 1;

        $ordered = [
            $id,
            $row['fecha_registro'],
            $row['nombres_completos'],
            $row['dni'],
            $row['edad'],
            $row['celular'],
            $row['domicilio'],
            $row['distrito'],
            $row['provincia'],
            $row['departamento'],
            $row['categoria'],
            $row['apoderado_nombres'],
            $row['apoderado_dni'],
            $row['apoderado_celular'],
            $row['ip'],
        ];

        foreach ($ordered as $i => $val) {
            $col = chr(65 + $i);
            $sheet->setCellValueExplicit(
                $col . $nextRow,
                (string)$val,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
            );
        }

        (new XlsxWriter($ss))->save(EXCEL_FILE);
        return ['id' => $id];
    });
}

/** Retorna todas las inscripciones como array de arrays asociativos. */
function excel_read_all(): array {
    return with_excel_lock(function (): array {
        excel_ensure_exists();
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $ss = $reader->load(EXCEL_FILE);
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        if (count($rows) < 2) return [];
        $header = array_shift($rows);
        $out = [];
        foreach ($rows as $r) {
            if (empty(array_filter($r, fn($v) => $v !== null && $v !== ''))) continue;
            $out[] = array_combine($header, $r);
        }
        return $out;
    });
}

/** Cuenta total de inscripciones. */
function excel_count(): int {
    return with_excel_lock(function (): int {
        if (!file_exists(EXCEL_FILE)) return 0;
        $reader = new XlsxReader();
        $reader->setReadDataOnly(true);
        $ss = $reader->load(EXCEL_FILE);
        return max(0, $ss->getActiveSheet()->getHighestRow() - 1);
    });
}
