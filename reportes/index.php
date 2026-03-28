<?php
// ============================================================
// reportes/index.php — Panel de Reportes (Admin)
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Reportes';

// Período por defecto: mes actual
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

// ── KPIs del período ─────────────────────────────────────────
$stmtKpi = $pdo->prepare(
    'SELECT
         COUNT(*)                                          AS total_ventas,
         COALESCE(SUM(total), 0)                          AS monto_total,
         COALESCE(SUM(CASE WHEN tipo_pago="contado" THEN total ELSE 0 END), 0)    AS contado,
         COALESCE(SUM(CASE WHEN tipo_pago="financiado" THEN total ELSE 0 END), 0) AS financiado,
         COUNT(CASE WHEN tipo_pago="contado" THEN 1 END)    AS cant_contado,
         COUNT(CASE WHEN tipo_pago="financiado" THEN 1 END) AS cant_financiado
       FROM ventas
      WHERE estado = "confirmada"
        AND DATE(created_at) BETWEEN ? AND ?'
);
$stmtKpi->execute([$desde, $hasta]);
$kpi = $stmtKpi->fetch();

// ── Ranking vendedores ────────────────────────────────────────
$rankVend = $pdo->prepare(
    'SELECT u.nombre, COUNT(v.id) AS cant,
            COALESCE(SUM(v.total), 0) AS total
       FROM ventas v
       JOIN usuarios u ON u.id = v.vendedor_id
      WHERE v.estado = "confirmada"
        AND DATE(v.created_at) BETWEEN ? AND ?
      GROUP BY u.id
      ORDER BY total DESC
      LIMIT 5'
);
$rankVend->execute([$desde, $hasta]);
$vendedores = $rankVend->fetchAll();

// ── Top artículos más vendidos ────────────────────────────────
$topArt = $pdo->prepare(
    'SELECT a.nombre, SUM(d.cantidad) AS unidades,
            SUM(d.subtotal) AS monto
       FROM venta_detalles d
       JOIN articulos a ON a.id = d.articulo_id
       JOIN ventas v    ON v.id = d.venta_id
      WHERE v.estado = "confirmada"
        AND DATE(v.created_at) BETWEEN ? AND ?
      GROUP BY a.id
      ORDER BY unidades DESC
      LIMIT 5'
);
$topArt->execute([$desde, $hasta]);
$topArticulos = $topArt->fetchAll();

// ── Ventas por día (para mini-gráfico) ───────────────────────
$ventasDia = $pdo->prepare(
    'SELECT DATE(created_at) AS dia, COUNT(*) AS cant,
            SUM(total) AS monto
       FROM ventas
      WHERE estado = "confirmada"
        AND DATE(created_at) BETWEEN ? AND ?
      GROUP BY dia
      ORDER BY dia'
);
$ventasDia->execute([$desde, $hasta]);
$diasData = $ventasDia->fetchAll();

// ── Stock crítico ─────────────────────────────────────────────
$stockCritico = $pdo->query(
    'SELECT a.nombre, a.stock_actual, a.stock_minimo, c.nombre AS categoria
       FROM articulos a
       JOIN categorias c ON c.id = a.categoria_id
      WHERE a.activo = 1 AND a.stock_actual <= a.stock_minimo
      ORDER BY a.stock_actual ASC
      LIMIT 10'
)->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="fs-5 fw-bold mb-0">Reportes</h1>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/reportes/stock_pdf.php"
               class="btn btn-sm btn-outline-secondary rounded-3">
                <i class="bi bi-file-earmark-pdf me-1"></i>Stock PDF
            </a>
            <a href="<?= APP_URL ?>/reportes/ventas_pdf.php?desde=<?= $desde ?>&hasta=<?= $hasta ?>"
               class="btn btn-sm btn-outline-secondary rounded-3">
                <i class="bi bi-file-earmark-pdf me-1"></i>Ventas PDF
            </a>
        </div>
    </div>

    <!-- ── Filtro de período ──────────────────────────────── -->
    <form method="GET" class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-5">
                <label class="form-label small fw-medium mb-1">Desde</label>
                <input type="date" name="desde"
                       class="form-control form-control-touch"
                       value="<?= $desde ?>">
            </div>
            <div class="col-5">
                <label class="form-label small fw-medium mb-1">Hasta</label>
                <input type="date" name="hasta"
                       class="form-control form-control-touch"
                       value="<?= $hasta ?>">
            </div>
            <div class="col-2">
                <button type="submit" class="btn btn-dark w-100 rounded-3 py-2">
                    <i class="bi bi-search"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- ── KPIs ──────────────────────────────────────────── -->
    <p class="section-title">Resumen del período</p>
    <div class="row g-3 mb-4">
        <div class="col-6">
            <div class="stat-card">
                <div class="stat-card__icon bg-success bg-opacity-15">
                    <i class="bi bi-bag-check text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value text-success"><?= $kpi['total_ventas'] ?></div>
                    <div class="stat-card__label">Ventas totales</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card">
                <div class="stat-card__icon bg-warning bg-opacity-15">
                    <i class="bi bi-cash-stack text-warning"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.9rem">
                        <?= formatPesos($kpi['monto_total']) ?>
                    </div>
                    <div class="stat-card__label">Facturado</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card">
                <div class="stat-card__icon bg-success bg-opacity-15">
                    <i class="bi bi-cash-coin text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.85rem">
                        <?= formatPesos($kpi['contado']) ?>
                    </div>
                    <div class="stat-card__label">Contado (<?= $kpi['cant_contado'] ?>)</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card">
                <div class="stat-card__icon bg-primary bg-opacity-15">
                    <i class="bi bi-credit-card text-primary"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.85rem">
                        <?= formatPesos($kpi['financiado']) ?>
                    </div>
                    <div class="stat-card__label">Financiado (<?= $kpi['cant_financiado'] ?>)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Mini-gráfico de ventas por día ────────────────── -->
    <?php if ($diasData): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <p class="section-title mb-3">Ventas por día</p>
            <canvas id="chart-ventas" height="120"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Top vendedores ────────────────────────────────── -->
    <?php if ($vendedores): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <p class="section-title mb-3">
                <i class="bi bi-trophy me-1 text-warning"></i>Top vendedores
            </p>
            <?php foreach ($vendedores as $i => $vend): ?>
            <div class="d-flex align-items-center gap-2 py-2
                        <?= $i < count($vendedores) - 1 ? 'border-bottom border-light-subtle' : '' ?>">
                <span class="fw-bold text-muted" style="width:20px;font-size:.85rem">
                    <?= $i + 1 ?>
                </span>
                <div class="flex-grow-1">
                    <p class="mb-0 fw-medium small"><?= htmlspecialchars($vend['nombre']) ?></p>
                    <div class="progress mt-1" style="height:4px">
                        <?php
                            $maxMonto = $vendedores[0]['total'] ?: 1;
                            $pct = round($vend['total'] / $maxMonto * 100);
                        ?>
                        <div class="progress-bar bg-warning" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <div class="text-end">
                    <p class="mb-0 fw-bold small text-success"><?= formatPesos($vend['total']) ?></p>
                    <p class="mb-0 text-muted" style="font-size:.7rem"><?= $vend['cant'] ?> ventas</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Top artículos ─────────────────────────────────── -->
    <?php if ($topArticulos): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <p class="section-title mb-3">
                <i class="bi bi-star me-1 text-warning"></i>Artículos más vendidos
            </p>
            <?php foreach ($topArticulos as $i => $art): ?>
            <div class="d-flex align-items-center gap-2 py-2
                        <?= $i < count($topArticulos) - 1 ? 'border-bottom border-light-subtle' : '' ?>">
                <span class="badge bg-warning text-dark rounded-circle
                             d-flex align-items-center justify-content-center"
                      style="width:24px;height:24px;font-size:.75rem;flex-shrink:0">
                    <?= $i + 1 ?>
                </span>
                <div class="flex-grow-1 min-w-0">
                    <p class="mb-0 fw-medium small text-truncate">
                        <?= htmlspecialchars($art['nombre']) ?>
                    </p>
                </div>
                <div class="text-end flex-shrink-0">
                    <p class="mb-0 fw-bold small"><?= $art['unidades'] ?> uds</p>
                    <p class="mb-0 text-muted" style="font-size:.7rem">
                        <?= formatPesos($art['monto']) ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Stock crítico ─────────────────────────────────── -->
    <?php if ($stockCritico): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body py-3">
            <p class="section-title mb-3">
                <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>Stock crítico
            </p>
            <?php foreach ($stockCritico as $item): ?>
            <div class="d-flex align-items-center justify-content-between py-2
                        border-bottom border-light-subtle">
                <div>
                    <p class="mb-0 fw-medium small"><?= htmlspecialchars($item['nombre']) ?></p>
                    <p class="mb-0 text-muted" style="font-size:.7rem">
                        <?= htmlspecialchars($item['categoria']) ?>
                        · Mínimo: <?= $item['stock_minimo'] ?> uds
                    </p>
                </div>
                <span class="badge <?= $item['stock_actual'] == 0 ? 'stock-empty' : 'stock-low' ?>
                             fs-6 px-2">
                    <?= $item['stock_actual'] ?>
                </span>
            </div>
            <?php endforeach; ?>
            <div class="mt-3">
                <a href="<?= APP_URL ?>/stock/index.php?stock_bajo=1"
                   class="btn btn-outline-warning w-100 rounded-3 py-2 small">
                    <i class="bi bi-boxes me-2"></i>Ver todo el stock crítico
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>

<!-- Chart.js CDN (ligero) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
<?php if ($diasData): ?>
const dias   = <?= json_encode(array_column($diasData, 'dia')) ?>;
const montos = <?= json_encode(array_map(fn($d) => (float)$d['monto'], $diasData)) ?>;

new Chart(document.getElementById('chart-ventas'), {
    type: 'bar',
    data: {
        labels: dias.map(d => {
            const [y,m,day] = d.split('-');
            return `${day}/${m}`;
        }),
        datasets: [{
            label: 'Ventas ($)',
            data: montos,
            backgroundColor: 'rgba(245,166,35,.75)',
            borderColor: '#f5a623',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => '$' + ctx.parsed.y.toLocaleString('es-AR', {minimumFractionDigits:2})
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => '$' + (v/1000).toFixed(0) + 'k',
                    font: { size: 10 }
                },
                grid: { color: '#f0f2f5' }
            },
            x: { ticks: { font: { size: 10 } } }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
