<?php
// ============================================================
// reportes/stock_pdf.php — Reporte de Stock optimizado para PDF
// Se imprime con Ctrl+P / "Guardar como PDF" del navegador
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Reporte de Stock';

// Filtros
$categoriaId = (int)($_GET['cat']        ?? 0);
$soloStock   = !empty($_GET['critico']);
$orden       = in_array($_GET['orden'] ?? '', ['nombre','stock_actual','categoria'])
               ? $_GET['orden'] : 'nombre';

// ── Construir query ──────────────────────────────────────────
$cond   = ['a.activo = 1'];
$params = [];

if ($categoriaId) { $cond[] = 'a.categoria_id = ?'; $params[] = $categoriaId; }
if ($soloStock)   { $cond[] = 'a.stock_actual <= a.stock_minimo'; }

$orderMap = [
    'nombre'       => 'a.nombre ASC',
    'stock_actual' => 'a.stock_actual ASC',
    'categoria'    => 'cat.nombre ASC, a.nombre ASC',
];
$orderSql = $orderMap[$orden];

$where = 'WHERE ' . implode(' AND ', $cond);

$stmt = $pdo->prepare(
    "SELECT a.id, a.codigo, a.nombre, a.descripcion,
            a.precio_contado, a.precio_financiado, a.cuotas, a.monto_cuota,
            a.stock_actual, a.stock_minimo, a.updated_at,
            cat.nombre AS categoria, cat.icono
       FROM articulos a
       JOIN categorias cat ON cat.id = a.categoria_id
     $where
     ORDER BY $orderSql"
);
$stmt->execute($params);
$articulos = $stmt->fetchAll();

// Estadísticas del reporte
$totalArt    = count($articulos);
$sinStock    = count(array_filter($articulos, fn($a) => $a['stock_actual'] == 0));
$stockBajo   = count(array_filter($articulos, fn($a) => $a['stock_actual'] > 0 && $a['stock_actual'] <= $a['stock_minimo']));
$valorTotal  = array_sum(array_map(fn($a) => $a['precio_contado'] * $a['stock_actual'], $articulos));

$categorias = $pdo->query(
    'SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Stock — <?= APP_NAME ?></title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <style>
        /* ── Estilos pantalla ─────────────────────────────── */
        body { background: #f0f2f5; }

        .report-header {
            background: linear-gradient(135deg, var(--ic-primary), #2d3561);
            color: #fff;
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
        }

        .report-table th {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            background: #f8f9fa;
            font-weight: 700;
            border-bottom: 2px solid #dee2e6;
        }

        .report-table td {
            font-size: .82rem;
            vertical-align: middle;
        }

        .badge-stock-ok    { background:#d4edda;color:#155724;padding:.25rem .5rem;border-radius:6px;font-size:.75rem }
        .badge-stock-low   { background:#fff3cd;color:#856404;padding:.25rem .5rem;border-radius:6px;font-size:.75rem }
        .badge-stock-empty { background:#f8d7da;color:#721c24;padding:.25rem .5rem;border-radius:6px;font-size:.75rem }

        /* ── Estilos de impresión ─────────────────────────── */
        @media print {
            @page {
                size: A4 landscape;
                margin: 1.5cm 1cm;
            }

            body {
                background: #fff !important;
                font-size: 10pt;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .no-print { display: none !important; }
            .report-header { border-radius: 0 !important; }

            .report-table th { font-size: 7pt; }
            .report-table td { font-size: 8pt; padding: .25rem .4rem !important; }

            .badge-stock-ok,
            .badge-stock-low,
            .badge-stock-empty { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            .card { box-shadow: none !important; border: 1px solid #ccc !important; }
            .stat-card { page-break-inside: avoid; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="container-fluid px-3 py-3">

    <!-- ── Controles (solo pantalla) ────────────────────── -->
    <div class="no-print mb-3 d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/reportes/index.php"
           class="btn btn-sm btn-light rounded-3">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
        <form method="GET" class="d-flex gap-2 flex-wrap">
            <select name="cat" class="form-select form-select-sm rounded-3"
                    style="max-width:180px" onchange="this.form.submit()">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $categoriaId == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="form-check form-switch d-flex align-items-center ms-1 mb-0">
                <input class="form-check-input" type="checkbox" name="critico"
                       id="chk-critico" value="1"
                       <?= $soloStock ? 'checked' : '' ?>
                       onchange="this.form.submit()">
                <label class="form-check-label small ms-2" for="chk-critico">
                    Solo crítico
                </label>
            </div>
            <select name="orden" class="form-select form-select-sm rounded-3"
                    style="max-width:140px" onchange="this.form.submit()">
                <option value="nombre"       <?= $orden==='nombre'       ? 'selected' : '' ?>>Por nombre</option>
                <option value="stock_actual" <?= $orden==='stock_actual' ? 'selected' : '' ?>>Por stock</option>
                <option value="categoria"    <?= $orden==='categoria'    ? 'selected' : '' ?>>Por categoría</option>
            </select>
        </form>
        <button onclick="window.print()"
                class="btn btn-warning fw-medium rounded-3 ms-auto px-4">
            <i class="bi bi-printer me-2"></i>Imprimir / Guardar PDF
        </button>
    </div>

    <!-- ── Encabezado del reporte ───────────────────────── -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="fs-4 fw-bold mb-1">
                    <i class="bi bi-boxes me-2 text-warning"></i><?= APP_NAME ?>
                </h1>
                <p class="mb-0 opacity-75">Reporte de Inventario de Stock</p>
                <p class="mb-0 opacity-60 small">
                    Generado: <?= date('d/m/Y H:i') ?>
                    <?= $soloStock ? ' · Solo artículos críticos' : '' ?>
                    <?= $categoriaId ? ' · Categoría: ' . htmlspecialchars($categorias[array_search($categoriaId, array_column($categorias, 'id'))]['nombre'] ?? '') : '' ?>
                </p>
            </div>
            <div class="col-auto text-end">
                <p class="fs-3 fw-bold text-warning mb-0"><?= $totalArt ?></p>
                <p class="mb-0 opacity-75 small">artículos</p>
            </div>
        </div>
    </div>

    <!-- ── Resumen KPIs ──────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-success bg-opacity-15">
                    <i class="bi bi-boxes text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value text-success"><?= $totalArt ?></div>
                    <div class="stat-card__label">Total artículos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-warning bg-opacity-15">
                    <i class="bi bi-exclamation-triangle text-warning"></i>
                </div>
                <div>
                    <div class="stat-card__value text-warning"><?= $stockBajo ?></div>
                    <div class="stat-card__label">Stock bajo</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-danger bg-opacity-15">
                    <i class="bi bi-x-circle text-danger"></i>
                </div>
                <div>
                    <div class="stat-card__value text-danger"><?= $sinStock ?></div>
                    <div class="stat-card__label">Sin stock</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-primary bg-opacity-15">
                    <i class="bi bi-currency-dollar text-primary"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.85rem">
                        <?= formatPesos($valorTotal) ?>
                    </div>
                    <div class="stat-card__label">Valor contado</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla de artículos ────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table report-table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Código</th>
                        <th>Artículo</th>
                        <th>Categoría</th>
                        <th class="text-end">$ Contado</th>
                        <th class="text-end">$ Financiado</th>
                        <th class="text-center">Cuotas</th>
                        <th class="text-center">$ Cuota</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center pe-3">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($articulos): ?>
                    <?php foreach ($articulos as $i => $a): ?>
                    <?php
                        $stockClass = $a['stock_actual'] == 0 ? 'badge-stock-empty'
                                    : ($a['stock_actual'] <= $a['stock_minimo'] ? 'badge-stock-low'
                                    : 'badge-stock-ok');
                        $stockLabel = $a['stock_actual'] == 0 ? 'Sin stock'
                                    : ($a['stock_actual'] <= $a['stock_minimo'] ? 'Bajo' : 'OK');
                    ?>
                    <tr class="<?= $a['stock_actual'] == 0 ? 'table-danger' :
                                  ($a['stock_actual'] <= $a['stock_minimo'] ? 'table-warning' : '') ?>">
                        <td class="ps-3 text-muted"><?= $i + 1 ?></td>
                        <td class="text-muted"><?= htmlspecialchars($a['codigo'] ?? '—') ?></td>
                        <td>
                            <strong><?= htmlspecialchars($a['nombre']) ?></strong>
                            <?php if ($a['descripcion']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars(mb_substr($a['descripcion'], 0, 50)) ?>...</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($a['categoria']) ?></td>
                        <td class="text-end fw-medium"><?= formatPesos($a['precio_contado']) ?></td>
                        <td class="text-end"><?= formatPesos($a['precio_financiado']) ?></td>
                        <td class="text-center"><?= $a['cuotas'] ?>x</td>
                        <td class="text-center"><?= formatPesos($a['monto_cuota']) ?></td>
                        <td class="text-center fw-bold"><?= $a['stock_actual'] ?></td>
                        <td class="text-center pe-3">
                            <span class="<?= $stockClass ?>"><?= $stockLabel ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            No hay artículos para mostrar con los filtros seleccionados.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr>
                        <td colspan="4" class="ps-3 fw-bold">TOTALES</td>
                        <td class="text-end fw-bold"><?= formatPesos(array_sum(array_column($articulos, 'precio_contado'))) ?></td>
                        <td colspan="4"></td>
                        <td class="text-center pe-3 fw-bold">
                            <?= array_sum(array_column($articulos, 'stock_actual')) ?> uds
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="text-muted small text-center mt-3 no-print">
        Para guardar como PDF: <strong>Ctrl+P</strong> → Destino: Guardar como PDF
        → Orientación: Horizontal (Paisaje)
    </p>
</div>
</body>
</html>
