<?php
/**
 * Panel administrativo: listado de inscritos + descarga del Excel.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../includes/excel-handler.php';

$rows  = [];
$error = '';
try {
    $rows = excel_read_all();
} catch (Throwable $e) {
    $error = 'No se pudo leer el archivo de inscripciones.';
    error_log('[admin/index.php] ' . $e->getMessage());
}

// Búsqueda simple (server-side, sobre los datos leídos)
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $qLow = mb_strtolower($q, 'UTF-8');
    $rows = array_values(array_filter($rows, function ($r) use ($qLow) {
        foreach ($r as $v) {
            if (mb_stripos((string)$v, $qLow, 0, 'UTF-8') !== false) return true;
        }
        return false;
    }));
}

$total = count($rows);
$csrf  = csrf_generate_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin — Inscripciones</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { padding: 24px; }
        .container { max-width: 1280px; margin: 0 auto; }
        header.admin-hd {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .stats { display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-card {
            flex: 1; min-width: 180px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 16px 20px;
        }
        .stat-card .label { color: rgba(255,255,255,0.5); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .stat-card .val { font-size: 28px; font-weight: 700; margin-top: 4px; }
        .actions { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .search { flex: 1; min-width: 240px; }
        table {
            width: 100%; border-collapse: collapse;
            background: rgba(255,255,255,0.03);
            border-radius: 12px; overflow: hidden;
            font-size: 13px;
        }
        thead {
            background: rgba(30,64,175,0.4);
            position: sticky; top: 0;
        }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { font-weight: 600; color: #FFD700; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        td { color: rgba(255,255,255,0.75); }
        tr:hover td { background: rgba(255,255,255,0.03); }
        .empty { padding: 60px; text-align: center; color: rgba(255,255,255,0.4); }
        .table-wrap { overflow-x: auto; border-radius: 12px; max-height: 70vh; overflow-y: auto; }
        .logout-btn { color: rgba(255,255,255,0.6); text-decoration: none; padding: 8px 16px; border-radius: 8px; }
        .logout-btn:hover { background: rgba(220,38,38,0.15); color: #FCA5A5; }
    </style>
</head>
<body>
<div class="container">
    <header class="admin-hd">
        <div>
            <h1 style="font-size: 22px;"><span class="gradient-text">Panel Admin</span> — Carrera 7K</h1>
            <p style="color: rgba(255,255,255,0.4); font-size: 13px; margin-top: 4px;">
                Sesión: <?= htmlspecialchars($_SESSION['admin_user'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
        <a class="logout-btn" href="logout.php">Cerrar sesión</a>
    </header>

    <div class="stats">
        <div class="stat-card">
            <div class="label">Total inscritos</div>
            <div class="val gradient-text"><?= number_format($total) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Cupo máximo</div>
            <div class="val"><?= number_format(MAX_PARTICIPANTS) ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Cierre</div>
            <div class="val" style="font-size: 16px;"><?= htmlspecialchars(REG_CLOSE_DATE, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>

    <div class="actions">
        <form method="GET" class="search" style="display: flex; gap: 8px;">
            <input type="text" name="q" class="form-input" placeholder="Buscar por nombre, DNI, distrito…"
                   value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" maxlength="60">
            <button type="submit" class="btn-secondary" style="padding: 10px 20px;">Buscar</button>
        </form>
        <a href="descargar.php?csrf=<?= urlencode($csrf) ?>" class="btn-primary" style="padding: 12px 24px;">
            ⬇ Descargar Excel
        </a>
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); color: #FCA5A5; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($total === 0): ?>
        <div class="empty">
            <?= $q !== '' ? 'No se encontraron resultados para su búsqueda.' : 'Aún no hay inscripciones registradas.' ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= htmlspecialchars((string)$col, ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php foreach ($r as $val): ?>
                                <td><?= htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
