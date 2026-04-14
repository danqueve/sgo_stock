<?php
// ============================================================
// reportes/ventas_pdf.php — Reporte de Ventas optimizado PDF
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Reporte de Ventas';

$desde    = $_GET['desde']   ?? date('Y-m-01');
$hasta    = $_GET['hasta']   ?? date('Y-m-d');
$tipoPago = $_GET['tipo']    ?? '';
$vendId   = (int)($_GET['vend'] ?? 0);

// Construir WHERE
$cond   = ["v.estado = 'confirmada'", "DATE(v.created_at) BETWEEN ? AND ?"];
$params = [$desde, $hasta];

if ($tipoPago) { $cond[] = 'v.tipo_pago = ?';    $params[] = $tipoPago; }
if ($vendId)   { $cond[] = 'v.vendedor_id = ?';  $params[] = $vendId;   }

$where = 'WHERE ' . implode(' AND ', $cond);

// ── Ventas ────────────────────────────────────────────────────
$stmtV = $pdo->prepare(
    "SELECT v.id, v.tipo_pago, v.cuotas, v.total, v.created_at,
            c.nombre AS cli_nombre, c.apellido AS cli_apellido,
            c.celular,
            (SELECT GROUP_CONCAT(a.nombre ORDER BY a.nombre SEPARATOR ', ')
               FROM venta_detalles vd
               JOIN articulos a ON a.id = vd.articulo_id
              WHERE vd.venta_id = v.id) AS articulos_vendidos
       FROM ventas v
       JOIN clientes c ON c.id = v.cliente_id
     $where
     ORDER BY v.created_at DESC"
);
$stmtV->execute($params);
$ventas = $stmtV->fetchAll();

// ── KPIs ──────────────────────────────────────────────────────
$stmtKpi = $pdo->prepare(
    "SELECT COUNT(*) AS cant,
            COALESCE(SUM(v.total), 0) AS total,
            COALESCE(SUM(CASE WHEN v.tipo_pago='contado'    THEN v.total ELSE 0 END), 0) AS contado,
            COALESCE(SUM(CASE WHEN v.tipo_pago='financiado' THEN v.total ELSE 0 END), 0) AS financiado
       FROM ventas v
       JOIN clientes c ON c.id = v.cliente_id
     $where"
);
$stmtKpi->execute($params);
$kpi = $stmtKpi->fetch();

// Vendedores para filtro
$vendedores = $pdo->query(
    'SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Ventas — <?= APP_NAME ?></title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <style>
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

        .report-table td { font-size: .82rem; vertical-align: middle; }

        .tipo-contado    { color: #155724; font-weight:600; }
        .tipo-financiado { color: #084298; font-weight:600; }

        /* ── Print B&W ────────────────────────────────────── */
        @media print {
            @page {
                size: A4 portrait;
                margin: 1.5cm 1cm;
            }

            /* Forzar blanco y negro */
            * {
                color: #000 !important;
                background: transparent !important;
                box-shadow: none !important;
                text-shadow: none !important;
                opacity: 1 !important;
            }

            body {
                background: #fff !important;
                font-size: 9pt;
            }

            .no-print { display: none !important; }

            /* Header: sin gradiente, solo borde inferior */
            .report-header {
                border-radius: 0 !important;
                border-bottom: 2px solid #000 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            /* KPI cards: compactas en una sola fila */
            .row.g-3 {
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: .3rem !important;
                margin-bottom: .5rem !important;
            }
            .row.g-3 > [class*="col-"] {
                flex: 1 !important;
                width: auto !important;
                padding: 0 !important;
            }
            .stat-card {
                padding: .2rem .35rem !important;
                gap: .3rem !important;
                border: 1px solid #888 !important;
                border-radius: 3px !important;
                page-break-inside: avoid;
                flex-direction: row !important;
                align-items: center !important;
            }
            .stat-card__icon { display: none !important; }
            .stat-card__value {
                font-size: 8pt !important;
                font-weight: 700 !important;
                margin-bottom: 0 !important;
                line-height: 1.2 !important;
            }
            .stat-card__label {
                font-size: 6.5pt !important;
                letter-spacing: 0 !important;
            }

            /* Tabla */
            .report-table th {
                font-size: 7pt;
                padding: .3rem .4rem;
                background: #e0e0e0 !important;
                border-bottom: 2px solid #000 !important;
            }
            .report-table td { font-size: 8pt; padding: .25rem .4rem !important; }
            .card { border: 1px solid #888 !important; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr { page-break-inside: avoid; }

            /* Sin fondo en filas de contado */
            .table-success td { background: transparent !important; }
        }
    </style>
</head>
<body>
<div class="container-fluid px-3 py-3">

    <!-- ── Controles (pantalla) ──────────────────────────── -->
    <div class="no-print mb-3 d-flex align-items-center gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/reportes/index.php"
           class="btn btn-sm btn-light rounded-3">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
        <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde"
                       class="form-control form-control-sm rounded-3"
                       value="<?= $desde ?>">
            </div>
            <div>
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta"
                       class="form-control form-control-sm rounded-3"
                       value="<?= $hasta ?>">
            </div>
            <div>
                <select name="tipo" class="form-select form-select-sm rounded-3">
                    <option value="">Todos los pagos</option>
                    <option value="contado"    <?= $tipoPago === 'contado'    ? 'selected' : '' ?>>Contado</option>
                    <option value="financiado" <?= $tipoPago === 'financiado' ? 'selected' : '' ?>>Financiado</option>
                </select>
            </div>
            <div>
                <select name="vend" class="form-select form-select-sm rounded-3">
                    <option value="">Todos los vendedores</option>
                    <?php foreach ($vendedores as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= $vendId == $v['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-dark btn-sm rounded-3">
                <i class="bi bi-funnel"></i>
            </button>
        </form>
        <button onclick="window.print()"
                class="btn btn-warning fw-medium rounded-3 ms-auto px-4">
            <i class="bi bi-printer me-2"></i>Imprimir / Guardar PDF
        </button>
    </div>

    <!-- ── Header reporte ────────────────────────────────── -->
    <div class="report-header">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="fs-4 fw-bold mb-1">
                    <i class="bi bi-receipt me-2 text-warning"></i><?= APP_NAME ?>
                </h1>
                <p class="mb-1 opacity-75">Reporte de Ventas</p>
                <p class="mb-0 opacity-60 small">
                    Período: <?= date('d/m/Y', strtotime($desde)) ?>
                    al <?= date('d/m/Y', strtotime($hasta)) ?>
                    <?= $tipoPago ? " · " . ucfirst($tipoPago) : '' ?>
                    · Generado: <?= date('d/m/Y H:i') ?>
                </p>
            </div>
            <div class="col-auto text-end">
                <p class="fs-2 fw-bold text-warning mb-0"><?= formatPesos($kpi['total']) ?></p>
                <p class="mb-0 opacity-75 small"><?= $kpi['cant'] ?> ventas</p>
            </div>
        </div>
    </div>

    <!-- ── KPIs ──────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-success bg-opacity-15">
                    <i class="bi bi-bag-check text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value text-success"><?= $kpi['cant'] ?></div>
                    <div class="stat-card__label">Ventas</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-warning bg-opacity-15">
                    <i class="bi bi-cash-stack text-warning"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.85rem">
                        <?= formatPesos($kpi['total']) ?>
                    </div>
                    <div class="stat-card__label">Total general</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-success bg-opacity-15">
                    <i class="bi bi-cash-coin text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.85rem">
                        <?= formatPesos($kpi['contado']) ?>
                    </div>
                    <div class="stat-card__label">Contado</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card__icon bg-primary bg-opacity-15">
                    <i class="bi bi-credit-card text-primary"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.85rem">
                        <?= formatPesos($kpi['financiado']) ?>
                    </div>
                    <div class="stat-card__label">Financiado</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Tabla de ventas ───────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table report-table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Fecha</th>
                        <th>Cliente</th>
                        <th>Celular</th>
                        <th>Artículo Vendido</th>
                        <th class="text-center">Tipo pago</th>
                        <th class="text-center">Cuotas</th>
                        <th class="text-end pe-3">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($ventas): ?>
                    <?php foreach ($ventas as $v): ?>
                    <tr class="<?= $v['tipo_pago'] === 'contado' ? 'table-success' : '' ?>">
                        <td class="ps-3"><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($v['cli_apellido'] . ', ' . $v['cli_nombre']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($v['celular']) ?></td>
                        <td><?= htmlspecialchars($v['articulos_vendidos'] ?? '—') ?></td>
                        <td class="text-center">
                            <span class="tipo-<?= $v['tipo_pago'] ?>">
                                <?= ucfirst($v['tipo_pago']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?= $v['cuotas'] > 1 ? $v['cuotas'] . 'x' : '—' ?>
                        </td>
                        <td class="text-end fw-bold pe-3"><?= formatPesos($v['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay ventas para el período y filtros seleccionados.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr>
                        <td colspan="4" class="ps-3 fw-bold">
                            TOTAL PERÍODO
                        </td>
                        <td class="text-center fw-bold"><?= $kpi['cant'] ?> ventas</td>
                        <td></td>
                        <td class="text-end fw-bold pe-3 text-success">
                            <?= formatPesos($kpi['total']) ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" class="ps-3 text-muted small">
                            Contado: <?= formatPesos($kpi['contado']) ?>
                            &nbsp;·&nbsp;
                            Financiado: <?= formatPesos($kpi['financiado']) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <p class="text-muted small text-center mt-3 no-print">
        <strong>Ctrl+P</strong> → Destino: Impresora / Guardar como PDF → Orientación: Vertical (Retrato)
    </p>

</div>
</body>
</html>
