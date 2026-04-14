<?php
// ============================================================
// index.php — Dashboard principal
// ============================================================
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
requireLogin();

$pdo       = getPDO();
$pageTitle = 'Dashboard';
$user      = currentUser();

// KPIs del día
$hoy = date('Y-m-d');

$stmtVentasHoy = $pdo->prepare(
    'SELECT COUNT(*) as cant, COALESCE(SUM(total), 0) as total
       FROM ventas
      WHERE estado = "confirmada" AND DATE(created_at) = ?'
);
$stmtVentasHoy->execute([$hoy]);
$ventasHoy = $stmtVentasHoy->fetch();

$totalArticulos = (int)$pdo->query(
    'SELECT COUNT(*) FROM articulos WHERE activo = 1'
)->fetchColumn();

$stockBajo = (int)$pdo->query(
    'SELECT COUNT(*) FROM articulos
      WHERE activo = 1 AND stock_actual <= stock_minimo AND stock_actual > 0'
)->fetchColumn();

$sinStock = (int)$pdo->query(
    'SELECT COUNT(*) FROM articulos WHERE activo = 1 AND stock_actual = 0'
)->fetchColumn();

// Últimas 5 ventas
$ultimasVentas = $pdo->prepare(
    'SELECT v.id, v.total, v.tipo_pago, v.created_at,
            c.nombre, c.apellido,
            u.nombre as vendedor
       FROM ventas v
       JOIN clientes c ON c.id = v.cliente_id
       JOIN usuarios u ON u.id = v.vendedor_id
      WHERE v.estado = "confirmada"
      ORDER BY v.created_at DESC
      LIMIT 5'
);
$ultimasVentas->execute();
$ventas = $ultimasVentas->fetchAll();
?>
<?php require_once __DIR__ . '/includes/head.php'; ?>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<main class="container-fluid container-mobile px-3 pt-4">

    <!-- Saludo -->
      <div class="mb-4 text-center">
        <h1 class="fs-4 fw-bold mb-1">
            <?= htmlspecialchars(explode(' ', $user['nombre'])[0]) ?> 👋
        </h1>
        <p class="text-muted small mb-0">
            <?= date('l j \d\e F', strtotime($hoy)) ?>
        </p>
    </div>

    <!-- KPI Cards -->
    <p class="section-title text-center">Resumen de Actividad</p>
    <div class="row g-3 mb-4 justify-content-center">

        <div class="col-6">
            <div class="stat-card h-100">
                <div class="stat-card__icon bg-success bg-opacity-10">
                    <i class="bi bi-bag-check text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value text-success">
                        <?= $ventasHoy['cant'] ?>
                    </div>
                    <div class="stat-card__label">Ventas</div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="stat-card h-100">
                <div class="stat-card__icon bg-primary bg-opacity-10">
                    <i class="bi bi-boxes text-primary"></i>
                </div>
                <div>
                    <div class="stat-card__value"><?= $totalArticulos ?></div>
                    <div class="stat-card__label">Items</div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="stat-card h-100">
                <div class="stat-card__icon bg-danger bg-opacity-10">
                    <i class="bi bi-exclamation-triangle text-danger"></i>
                </div>
                <div>
                    <div class="stat-card__value text-danger">
                        <?= $stockBajo + $sinStock ?>
                    </div>
                    <div class="stat-card__label">S. Crítico</div>
                </div>
            </div>
        </div>

        <div class="col-6">
            <div class="stat-card h-100">
                <div class="stat-card__icon bg-secondary bg-opacity-10">
                    <i class="bi bi-cash-stack text-secondary"></i>
                </div>
                <div>
                    <div class="stat-card__value text-secondary" style="font-size:1rem">
                        <?= formatShortNumber((float)$ventasHoy['total']) ?>
                    </div>
                    <div class="stat-card__label">Recaudo</div>
                </div>
            </div>
        </div>

    </div>

    <!-- Acceso rápido -->
    <p class="section-title text-center">Acceso rápido</p>
    <div class="row g-3 mb-4 justify-content-center">
        <div class="col-6">
            <a href="<?= APP_URL ?>/ventas/nueva.php"
               class="d-block text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 text-center py-3"
                     style="background: #fff">
                    <i class="bi bi-plus-circle-fill text-accent mb-1"
                       style="font-size:1.8rem"></i>
                    <p class="fw-bold mb-0 small" style="color:var(--ic-text)">Vender</p>
                </div>
            </a>
        </div>
        <div class="col-6">
            <a href="<?= APP_URL ?>/stock/index.php"
               class="d-block text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 text-center py-3"
                     style="background: #fff">
                    <i class="bi bi-boxes text-primary mb-1"
                       style="font-size:1.8rem"></i>
                    <p class="fw-bold mb-0 small" style="color:var(--ic-text)">Stock</p>
                </div>
            </a>
        </div>
        <div class="col-6">
            <a href="<?= APP_URL ?>/ventas/index.php"
               class="d-block text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 text-center py-3"
                     style="background: #fff">
                    <i class="bi bi-receipt text-warning mb-1"
                       style="font-size:1.8rem"></i>
                    <p class="fw-bold mb-0 small" style="color:var(--ic-text)">Historial</p>
                </div>
            </a>
        </div>
<?php if ($user['rol'] === 'admin'): ?>
        <div class="col-4">
            <a href="<?= APP_URL ?>/reportes/index.php"
               class="d-block text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 text-center py-3"
                     style="background: #fff">
                    <i class="bi bi-bar-chart-line-fill text-success mb-1"
                       style="font-size:1.8rem"></i>
                    <p class="fw-bold mb-0 small" style="color:var(--ic-text)">Reportes</p>
                </div>
            </a>
        </div>
        <div class="col-4">
            <a href="<?= APP_URL ?>/stock/nuevo.php"
               class="d-block text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 text-center py-3"
                     style="background: #fff">
                    <i class="bi bi-plus-square-fill text-danger mb-1"
                       style="font-size:1.8rem"></i>
                    <p class="fw-bold mb-0 small" style="color:var(--ic-text)">Nuevo Ítem</p>
                </div>
            </a>
        </div>
        <div class="col-4">
            <a href="<?= APP_URL ?>/admin/usuarios.php"
               class="d-block text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 text-center py-3"
                     style="background: #fff">
                    <i class="bi bi-people-fill text-info mb-1"
                       style="font-size:1.8rem"></i>
                    <p class="fw-bold mb-0 small" style="color:var(--ic-text)">Usuarios</p>
                </div>
            </a>
        </div>
<?php endif; ?>
    </div>

    <!-- Últimas ventas -->
    <?php if ($ventas): ?>
    <div class="d-flex align-items-center justify-content-between mb-2">
        <p class="section-title mb-0">Últimas ventas</p>
        <a href="<?= APP_URL ?>/ventas/index.php?sin_fecha=1"
           class="btn btn-sm btn-outline-secondary rounded-3 px-3" style="font-size:.75rem">
            Ver todas <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="d-flex flex-column gap-2 mb-4">
        <?php foreach ($ventas as $v): ?>
        <a href="<?= APP_URL ?>/ventas/detalle.php?id=<?= $v['id'] ?>"
           class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-3 py-2 px-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle bg-warning bg-opacity-15 d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:38px;height:38px">
                            <i class="bi bi-receipt text-warning"></i>
                        </div>
                        <div>
                            <p class="mb-0 fw-medium small">
                                <?= htmlspecialchars($v['apellido'] . ', ' . $v['nombre']) ?>
                            </p>
                            <p class="mb-0 text-muted" style="font-size:.72rem">
                                <?= date('d/m H:i', strtotime($v['created_at'])) ?>
                                &middot; <?= ucfirst($v['tipo_pago']) ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-end">
                        <p class="mb-0 fw-bold text-success small">
                            <?= formatPesos((float)$v['total']) ?>
                        </p>
                        <p class="mb-0 text-muted" style="font-size:.7rem">
                            #<?= $v['id'] ?>
                        </p>
                    </div>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
