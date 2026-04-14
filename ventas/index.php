<?php
// ============================================================
// ventas/index.php — Historial de ventas con datos de cliente
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pdo       = getPDO();
$pageTitle = 'Ventas';
$user      = currentUser();

// ── Filtros ──────────────────────────────────────────────────
$sinFecha  = isset($_GET['sin_fecha']) && $_GET['sin_fecha'] === '1';
$desde     = $sinFecha ? '' : ($_GET['desde'] ?? date('Y-m-01'));
$hasta     = $sinFecha ? '' : ($_GET['hasta'] ?? date('Y-m-d'));
$tipoPago  = $_GET['tipo']    ?? '';
$estado    = $_GET['estado']  ?? 'confirmada';
$buscar    = trim($_GET['q']  ?? '');

// Vendedor: Admin ve todo, Vendedor solo las suyas
$soloMio   = ($user['rol'] !== 'admin');

// ── Construir WHERE ──────────────────────────────────────────
$cond   = [];
$params = [];

if (!$sinFecha && $desde && $hasta) {
    $cond[]   = 'DATE(v.created_at) BETWEEN ? AND ?';
    $params[] = $desde;
    $params[] = $hasta;
}
if ($estado)   { $cond[] = 'v.estado = ?';       $params[] = $estado; }
if ($tipoPago) { $cond[] = 'v.tipo_pago = ?';     $params[] = $tipoPago; }
if ($soloMio)  { $cond[] = 'v.vendedor_id = ?';   $params[] = $user['id']; }
if ($buscar) {
    $like     = "%{$buscar}%";
    $cond[]   = '(c.nombre LIKE ? OR c.apellido LIKE ? OR c.celular LIKE ? OR c.dni LIKE ?)';
    $params   = array_merge($params, [$like, $like, $like, $like]);
}

$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

// ── Totales del período ──────────────────────────────────────
$stmtTot = $pdo->prepare(
    "SELECT COUNT(*) AS cant, COALESCE(SUM(v.total), 0) AS monto
       FROM ventas v JOIN clientes c ON c.id = v.cliente_id $where"
);
$stmtTot->execute($params);
$totales = $stmtTot->fetch();

// ── Ventas paginadas ─────────────────────────────────────────
$pagina  = max(1, (int)($_GET['pag'] ?? 1));
$porPag  = 20;
$offset  = ($pagina - 1) * $porPag;

$stmtV = $pdo->prepare(
    "SELECT v.id, v.tipo_pago, v.cuotas, v.total, v.estado, v.created_at,
            c.nombre, c.apellido, c.dni, c.celular, c.localidad,
            p.nombre AS provincia,
            u.nombre AS vendedor,
            (SELECT GROUP_CONCAT(a.nombre ORDER BY a.nombre SEPARATOR ' · ')
               FROM venta_detalles vd
               JOIN articulos a ON a.id = vd.articulo_id
              WHERE vd.venta_id = v.id) AS articulos_vendidos
       FROM ventas v
       JOIN clientes c   ON c.id = v.cliente_id
       JOIN provincias p ON p.id = c.provincia_id
       JOIN usuarios u   ON u.id = v.vendedor_id
     $where
     ORDER BY v.created_at DESC
     LIMIT $porPag OFFSET $offset"
);
$stmtV->execute($params);
$ventas = $stmtV->fetchAll();

$totalPags = (int)ceil($totales['cant'] / $porPag);

// Parámetros actuales sin paginación (para links)
$qActual = array_filter(
    array_diff_key($_GET, ['pag' => '']),
    fn($v) => $v !== ''
);
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3">

    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="fs-5 fw-bold mb-0">Historial de ventas</h1>
        <a href="<?= APP_URL ?>/ventas/nueva.php"
           class="btn btn-warning btn-sm fw-medium rounded-3 px-3">
            <i class="bi bi-plus-lg me-1"></i>Nueva
        </a>
    </div>

    <!-- ── FILTROS ─────────────────────────────────────────── -->
    <form method="GET" id="formFiltros" class="mb-3">

        <!-- Buscador -->
        <div class="input-group mb-2 shadow-sm">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q"
                   class="form-control form-control-touch border-start-0 ps-0"
                   placeholder="Nombre, apellido, celular o DNI..."
                   value="<?= htmlspecialchars($buscar) ?>"
                   autocomplete="off">
            <?php if ($buscar): ?>
            <button type="button" class="btn btn-outline-secondary"
                    onclick="document.querySelector('[name=q]').value='';document.getElementById('formFiltros').submit()">
                <i class="bi bi-x-lg"></i>
            </button>
            <?php endif; ?>
        </div>

        <!-- Fechas -->
        <?php if (!$sinFecha): ?>
        <div class="row g-2 mb-2">
            <div class="col-6">
                <label class="form-label small fw-medium mb-1">Desde</label>
                <input type="date" name="desde"
                       class="form-control form-control-touch"
                       value="<?= $desde ?>">
            </div>
            <div class="col-6">
                <label class="form-label small fw-medium mb-1">Hasta</label>
                <input type="date" name="hasta"
                       class="form-control form-control-touch"
                       value="<?= $hasta ?>">
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="sin_fecha" value="1">
        <?php endif; ?>

        <!-- Chips -->
        <div class="scroll-x-touch mb-2 pb-1">
            <div class="d-flex gap-2" style="width:max-content">

                <!-- Rango de fechas -->
                <a href="?<?= http_build_query(array_merge(array_diff_key($qActual, ['sin_fecha'=>'']), ['desde'=>date('Y-m-01'),'hasta'=>date('Y-m-d')])) ?>"
                   class="btn btn-sm rounded-pill <?= !$sinFecha ? 'btn-dark' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-calendar3 me-1"></i>Este mes
                </a>
                <a href="?<?= http_build_query(array_merge(array_diff_key($qActual, ['desde'=>'','hasta'=>'']), ['sin_fecha'=>'1'])) ?>"
                   class="btn btn-sm rounded-pill <?= $sinFecha ? 'btn-dark' : 'btn-outline-secondary' ?>">
                    <i class="bi bi-infinity me-1"></i>Todas
                </a>

                <span class="border-start mx-1"></span>

                <!-- Tipo de pago -->
                <?php foreach (['' => 'Todos', 'contado' => 'Contado', 'financiado' => 'Financiado'] as $v => $lbl): ?>
                <input type="radio" class="btn-check" name="tipo"
                       id="tipo-<?= $v ?: 'todos' ?>" value="<?= $v ?>"
                       <?= $tipoPago === $v ? 'checked' : '' ?>>
                <label class="btn btn-sm rounded-pill
                              <?= $tipoPago === $v ? 'btn-warning' : 'btn-outline-secondary' ?>"
                       for="tipo-<?= $v ?: 'todos' ?>"><?= $lbl ?></label>
                <?php endforeach; ?>

                <span class="border-start mx-1"></span>

                <!-- Estado -->
                <?php foreach (['' => 'Todos estados', 'confirmada' => 'Confirmadas', 'anulada' => 'Anuladas'] as $v => $lbl): ?>
                <input type="radio" class="btn-check" name="estado"
                       id="est-<?= $v ?: 'todos' ?>" value="<?= $v ?>"
                       <?= $estado === $v ? 'checked' : '' ?>>
                <label class="btn btn-sm rounded-pill
                              <?= $estado === $v ? 'btn-primary' : 'btn-outline-secondary' ?>"
                       for="est-<?= $v ?: 'todos' ?>"><?= $lbl ?></label>
                <?php endforeach; ?>

            </div>
        </div>

        <button type="submit" class="btn btn-dark btn-sm w-100 rounded-3 py-2">
            <i class="bi bi-funnel me-1"></i>Filtrar
        </button>
    </form>

    <!-- ── RESUMEN DEL PERÍODO ────────────────────────────── -->
    <div class="row g-2 mb-3">
        <div class="col-6">
            <div class="stat-card py-3 px-3">
                <div class="stat-card__icon bg-success bg-opacity-15">
                    <i class="bi bi-bag-check text-success"></i>
                </div>
                <div>
                    <div class="stat-card__value text-success"><?= $totales['cant'] ?></div>
                    <div class="stat-card__label">ventas</div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="stat-card py-3 px-3">
                <div class="stat-card__icon bg-warning bg-opacity-15">
                    <i class="bi bi-cash-stack text-warning"></i>
                </div>
                <div>
                    <div class="stat-card__value" style="font-size:.95rem">
                        <?= formatPesos((float)$totales['monto']) ?>
                    </div>
                    <div class="stat-card__label">total</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── LISTA DE VENTAS ────────────────────────────────── -->
    <?php if ($ventas): ?>
    <div class="d-flex flex-column gap-2 mb-3">
        <?php foreach ($ventas as $v): ?>
        <?php $badge = $v['estado'] === 'confirmada' ? 'success' : 'danger'; ?>
        <a href="<?= APP_URL ?>/ventas/detalle.php?id=<?= $v['id'] ?>"
           class="text-decoration-none">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body py-2 px-3">

                    <!-- Fila 1: nombre + total -->
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <div class="rounded-circle d-flex align-items-center justify-content-center
                                        bg-<?= $badge ?> bg-opacity-15 flex-shrink-0"
                                 style="width:38px;height:38px">
                                <i class="bi bi-receipt text-<?= $badge ?>" style="font-size:.9rem"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="mb-0 fw-semibold small text-truncate">
                                    <?= htmlspecialchars($v['apellido'] . ', ' . $v['nombre']) ?>
                                </p>
                                <!-- Fila 2: DNI · Celular · Localidad -->
                                <p class="mb-0 text-muted" style="font-size:.72rem">
                                    <?php if ($v['dni']): ?>
                                    <i class="bi bi-person-vcard me-1"></i><?= htmlspecialchars($v['dni']) ?>
                                    &nbsp;&middot;&nbsp;
                                    <?php endif; ?>
                                    <i class="bi bi-phone me-1"></i><?= htmlspecialchars($v['celular']) ?>
                                    &nbsp;&middot;&nbsp;
                                    <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($v['localidad']) ?>, <?= htmlspecialchars($v['provincia']) ?>
                                </p>
                                <!-- Fila 3: Artículos -->
                                <?php if ($v['articulos_vendidos']): ?>
                                <p class="mb-0 text-muted text-truncate" style="font-size:.72rem; max-width:200px">
                                    <i class="bi bi-box me-1"></i><?= htmlspecialchars($v['articulos_vendidos']) ?>
                                </p>
                                <?php endif; ?>
                                <!-- Fila 4: meta de venta -->
                                <p class="mb-0 text-muted" style="font-size:.68rem">
                                    #<?= $v['id'] ?>
                                    &middot; <?= date('d/m/Y H:i', strtotime($v['created_at'])) ?>
                                    &middot; <?= ucfirst($v['tipo_pago']) ?>
                                    <?= $v['cuotas'] > 1 ? "({$v['cuotas']}x)" : '' ?>
                                    <?php if ($user['rol'] === 'admin'): ?>
                                    &middot; <i class="bi bi-person"></i> <?= htmlspecialchars($v['vendedor']) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <p class="mb-1 fw-bold small text-success">
                                <?= formatPesos($v['total']) ?>
                            </p>
                            <span class="badge bg-<?= $badge ?> bg-opacity-15 text-<?= $badge ?>"
                                  style="font-size:.62rem">
                                <?= ucfirst($v['estado']) ?>
                            </span>
                        </div>
                    </div>

                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── PAGINACIÓN ─────────────────────────────────────── -->
    <?php if ($totalPags > 1): ?>
    <nav class="mb-4" aria-label="Paginación">
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <?php if ($pagina > 1): ?>
            <a href="?<?= http_build_query(array_merge($qActual, ['pag' => $pagina - 1])) ?>"
               class="btn btn-sm btn-outline-secondary rounded-3 px-3">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php endif; ?>

            <span class="btn btn-sm btn-warning disabled rounded-3 px-3">
                <?= $pagina ?> / <?= $totalPags ?>
                &nbsp;<small class="opacity-75">(<?= $totales['cant'] ?> ventas)</small>
            </span>

            <?php if ($pagina < $totalPags): ?>
            <a href="?<?= http_build_query(array_merge($qActual, ['pag' => $pagina + 1])) ?>"
               class="btn btn-sm btn-outline-secondary rounded-3 px-3">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-receipt text-muted d-block mb-2" style="font-size:3rem"></i>
        <p class="text-muted">No hay ventas<?= $buscar ? " para «" . htmlspecialchars($buscar) . "»" : ' en el período seleccionado' ?>.</p>
        <?php if (!$buscar): ?>
        <a href="<?= APP_URL ?>/ventas/nueva.php"
           class="btn btn-warning rounded-3 px-4">
            <i class="bi bi-plus-lg me-2"></i>Registrar venta
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
