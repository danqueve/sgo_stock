<?php
// ============================================================
// clientes/index.php — Lista de clientes con búsqueda
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pdo       = getPDO();
$pageTitle = 'Clientes';
$user      = currentUser();

$buscar    = trim($_GET['q']      ?? '');
$provincia = (int)($_GET['prov'] ?? 0);
$pagina    = max(1, (int)($_GET['pag'] ?? 1));
$porPag    = 20;
$offset    = ($pagina - 1) * $porPag;

// Provincias para filtro
$provincias = $pdo->query(
    'SELECT id, nombre FROM provincias ORDER BY nombre'
)->fetchAll();

// ── WHERE dinámico ───────────────────────────────────────────
$cond   = [];
$params = [];

if ($buscar) {
    $cond[]   = '(c.nombre LIKE ? OR c.apellido LIKE ? OR c.celular LIKE ? OR c.localidad LIKE ?)';
    $like     = "%{$buscar}%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($provincia) {
    $cond[]   = 'c.provincia_id = ?';
    $params[] = $provincia;
}

$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

// Total
$stmtTot = $pdo->prepare("SELECT COUNT(*) FROM clientes c $where");
$stmtTot->execute($params);
$total = (int)$stmtTot->fetchColumn();
$totalPags = (int)ceil($total / $porPag);

// Clientes con total de compras
$stmtC = $pdo->prepare(
    "SELECT c.id, c.nombre, c.apellido, c.celular, c.localidad,
            p.nombre AS provincia,
            c.created_at,
            COUNT(v.id) AS total_compras,
            COALESCE(SUM(v.total), 0) AS monto_total
       FROM clientes c
       JOIN provincias p ON p.id = c.provincia_id
       LEFT JOIN ventas v ON v.cliente_id = c.id AND v.estado = 'confirmada'
     $where
     GROUP BY c.id
     ORDER BY c.apellido, c.nombre
     LIMIT $porPag OFFSET $offset"
);
$stmtC->execute($params);
$clientes = $stmtC->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="fs-5 fw-bold mb-0">Clientes</h1>
            <p class="text-muted small mb-0"><?= number_format($total) ?> registrados</p>
        </div>
    </div>

    <!-- ── FILTROS ─────────────────────────────────────────── -->
    <form method="GET" class="mb-3">
        <div class="input-group mb-2 shadow-sm">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" name="q"
                   class="form-control form-control-touch border-start-0 ps-0"
                   placeholder="Nombre, apellido, celular o localidad..."
                   value="<?= htmlspecialchars($buscar) ?>"
                   autocomplete="off">
        </div>

        <div class="row g-2">
            <div class="col-8">
                <select name="prov" class="form-select form-select-touch">
                    <option value="">— Todas las provincias —</option>
                    <?php foreach ($provincias as $pr): ?>
                    <option value="<?= $pr['id'] ?>"
                        <?= $provincia == $pr['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($pr['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4">
                <button type="submit"
                        class="btn btn-dark w-100 rounded-3 py-2">
                    <i class="bi bi-funnel"></i>
                </button>
            </div>
        </div>
    </form>

    <!-- ── LISTA ─────────────────────────────────────────── -->
    <?php if ($clientes): ?>
    <div class="d-flex flex-column gap-2 mb-3">
        <?php foreach ($clientes as $cli): ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <!-- Avatar + datos -->
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <div class="rounded-circle bg-warning bg-opacity-15
                                    d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:40px;height:40px;font-size:1rem;font-weight:700;color:#b8750a">
                            <?= mb_strtoupper(mb_substr($cli['apellido'], 0, 1)) ?>
                        </div>
                        <div class="min-w-0">
                            <p class="mb-0 fw-medium small text-truncate">
                                <?= htmlspecialchars($cli['apellido'] . ', ' . $cli['nombre']) ?>
                            </p>
                            <div class="text-muted" style="font-size:.7rem">
                                <i class="bi bi-telephone me-1"></i>
                                <a href="tel:<?= htmlspecialchars($cli['celular']) ?>"
                                   class="text-reset">
                                    <?= htmlspecialchars($cli['celular']) ?>
                                </a>
                                &nbsp;·&nbsp;
                                <?= htmlspecialchars($cli['localidad']) ?>,
                                <?= htmlspecialchars($cli['provincia']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Compras + acciones -->
                    <div class="text-end flex-shrink-0">
                        <p class="mb-0 fw-bold text-success small">
                            <?= formatPesos($cli['monto_total']) ?>
                        </p>
                        <p class="mb-1 text-muted" style="font-size:.7rem">
                            <?= $cli['total_compras'] ?>
                            <?= $cli['total_compras'] == 1 ? 'compra' : 'compras' ?>
                        </p>
                        <a href="https://wa.me/54<?= preg_replace('/\D/', '', $cli['celular']) ?>"
                           target="_blank"
                           class="btn btn-sm btn-outline-success rounded-circle p-1"
                           style="width:30px;height:30px;line-height:1"
                           aria-label="WhatsApp">
                            <i class="bi bi-whatsapp" style="font-size:.8rem"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Paginación -->
    <?php if ($totalPags > 1): ?>
    <nav class="mb-4">
        <div class="d-flex justify-content-center gap-2">
            <?php if ($pagina > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pag' => $pagina - 1])) ?>"
               class="btn btn-sm btn-outline-secondary rounded-3 px-3">
                <i class="bi bi-chevron-left"></i>
            </a>
            <?php endif; ?>
            <span class="btn btn-sm btn-warning disabled rounded-3 px-3">
                <?= $pagina ?> / <?= $totalPags ?>
            </span>
            <?php if ($pagina < $totalPags): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['pag' => $pagina + 1])) ?>"
               class="btn btn-sm btn-outline-secondary rounded-3 px-3">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </nav>
    <?php endif; ?>

    <?php else: ?>
    <div class="text-center py-5">
        <i class="bi bi-people text-muted d-block mb-2" style="font-size:3rem"></i>
        <p class="text-muted">No se encontraron clientes.</p>
    </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
