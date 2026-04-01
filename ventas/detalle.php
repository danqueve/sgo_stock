<?php
// ============================================================
// ventas/detalle.php — Detalle completo de una venta
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pdo  = getPDO();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: ' . APP_URL . '/ventas/index.php');
    exit;
}

// Cabecera de la venta
$stmtV = $pdo->prepare(
    'SELECT v.id, v.tipo_pago, v.cuotas, v.es_mensual, v.primer_vencimiento,
            v.total, v.estado, v.observaciones, v.created_at,
            c.nombre, c.apellido, c.celular, c.direccion, c.localidad,
            p.nombre AS provincia,
            u.nombre AS vendedor
       FROM ventas v
       JOIN clientes c  ON c.id = v.cliente_id
       JOIN provincias p ON p.id = c.provincia_id
       JOIN usuarios u  ON u.id = v.vendedor_id
      WHERE v.id = ?'
);
$stmtV->execute([$id]);
$venta = $stmtV->fetch();

if (!$venta) {
    setFlash('danger', 'Venta no encontrada.');
    header('Location: ' . APP_URL . '/ventas/index.php');
    exit;
}

// Detalle de ítems
$stmtD = $pdo->prepare(
    'SELECT d.cantidad, d.precio_unitario, d.subtotal,
            a.nombre AS articulo, c.nombre AS categoria
       FROM venta_detalles d
       JOIN articulos a  ON a.id = d.articulo_id
       JOIN categorias c ON c.id = a.categoria_id
      WHERE d.venta_id = ?'
);
$stmtD->execute([$id]);
$items = $stmtD->fetchAll();

// Anular venta (solo Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anular'])) {
    requireAdmin();
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        if ($venta['estado'] === 'confirmada') {
            $pdo->beginTransaction();
            // Devolver stock por cada ítem
            foreach ($items as $item) {
                $pdo->prepare(
                    'UPDATE articulos SET stock_actual = stock_actual + ? WHERE nombre = ?'
                )->execute([$item['cantidad'], $item['articulo']]);
            }
            $pdo->prepare('UPDATE ventas SET estado = "anulada" WHERE id = ?')->execute([$id]);
            $pdo->commit();
            setFlash('warning', "Venta #{$id} anulada. Stock repuesto.");
            header('Location: ' . APP_URL . '/ventas/detalle.php?id=' . $id);
            exit;
        }
    }
}

$pageTitle   = "Venta #{$id}";
$csrfToken   = csrfToken();
$esFinanciado      = $venta['tipo_pago'] === 'financiado';
$esMensual         = (bool)$venta['es_mensual'];
$primerVencimiento = $venta['primer_vencimiento'];
$montoCuota        = $esFinanciado && $venta['cuotas'] > 0
    ? $venta['total'] / $venta['cuotas']
    : 0;

$estadoBadge = match($venta['estado']) {
    'confirmada' => 'success',
    'anulada'    => 'danger',
    default      => 'secondary',
};
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3">

    <!-- Encabezado -->
    <div class="d-flex align-items-center mb-3 gap-2">
        <a href="<?= APP_URL ?>/ventas/index.php"
           class="btn btn-sm btn-light rounded-circle p-2"
           style="min-width:38px;min-height:38px;">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2">
                <h1 class="fs-5 fw-bold mb-0">Venta #<?= $id ?></h1>
                <span class="badge bg-<?= $estadoBadge ?>">
                    <?= ucfirst($venta['estado']) ?>
                </span>
            </div>
            <p class="text-muted small mb-0">
                <?= date('d/m/Y H:i', strtotime($venta['created_at'])) ?>
                &middot; <?= htmlspecialchars($venta['vendedor']) ?>
            </p>
        </div>
        <!-- Botón imprimir / compartir -->
        <button onclick="window.print()"
                class="btn btn-sm btn-outline-secondary rounded-3"
                aria-label="Imprimir comprobante">
            <i class="bi bi-printer"></i>
        </button>
    </div>

    <!-- ── CLIENTE ──────────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <p class="section-title mb-2">
                <i class="bi bi-person-fill me-1 text-warning"></i>Cliente
            </p>
            <h2 class="fs-6 fw-bold mb-1">
                <?= htmlspecialchars($venta['apellido'] . ', ' . $venta['nombre']) ?>
            </h2>
            <div class="d-flex flex-column gap-1 small text-muted">
                <span>
                    <i class="bi bi-telephone me-2"></i>
                    <a href="tel:<?= htmlspecialchars($venta['celular']) ?>"
                       class="text-reset">
                        <?= htmlspecialchars($venta['celular']) ?>
                    </a>
                    &nbsp;
                    <a href="https://wa.me/54<?= preg_replace('/\D/', '', $venta['celular']) ?>"
                       target="_blank" class="text-success ms-1">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </a>
                </span>
                <span>
                    <i class="bi bi-geo-alt me-2"></i>
                    <?= htmlspecialchars($venta['direccion']) ?>,
                    <?= htmlspecialchars($venta['localidad']) ?>,
                    <?= htmlspecialchars($venta['provincia']) ?>
                </span>
                <?php if ($venta['observaciones']): ?>
                <span>
                    <i class="bi bi-chat-left-text me-2"></i>
                    <?= htmlspecialchars($venta['observaciones']) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ARTÍCULOS ─────────────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <p class="section-title mb-2">
                <i class="bi bi-box me-1 text-warning"></i>Artículos
            </p>
            <?php foreach ($items as $item): ?>
            <div class="d-flex justify-content-between align-items-start
                        py-2 border-bottom border-light-subtle">
                <div>
                    <p class="mb-0 fw-medium small"><?= htmlspecialchars($item['articulo']) ?></p>
                    <p class="mb-0 text-muted" style="font-size:.72rem">
                        <?= htmlspecialchars($item['categoria']) ?>
                        &middot; x<?= $item['cantidad'] ?>
                        &middot; <?= formatPesos($item['precio_unitario']) ?> c/u
                    </p>
                </div>
                <span class="fw-bold small text-dark">
                    <?= formatPesos($item['subtotal']) ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── RESUMEN DE PAGO ───────────────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <p class="section-title mb-2">
                <i class="bi bi-credit-card me-1 text-warning"></i>Resumen de pago
            </p>

            <div class="d-flex justify-content-between py-2 border-bottom border-light-subtle small">
                <span class="text-muted">Modalidad</span>
                <span class="fw-medium">
                    <?php if ($esFinanciado): ?>
                    <i class="bi bi-credit-card text-primary me-1"></i>Financiado
                    <?php else: ?>
                    <i class="bi bi-cash-coin text-success me-1"></i>Contado
                    <?php endif; ?>
                </span>
            </div>

            <?php if ($esFinanciado): ?>
            <div class="d-flex justify-content-between py-2 border-bottom border-light-subtle small">
                <span class="text-muted">Cuotas</span>
                <span class="fw-medium"><?= $venta['cuotas'] ?>x de <?= formatPesos($montoCuota) ?></span>
            </div>
            <?php if ($esMensual && $primerVencimiento): ?>
            <div class="d-flex justify-content-between py-2 border-bottom border-light-subtle small">
                <span class="text-muted">Pago mensual</span>
                <span class="fw-medium">
                    <i class="bi bi-calendar-check text-primary me-1"></i>
                    Vence el <?= date('d/m/Y', strtotime($primerVencimiento)) ?>
                </span>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between pt-3">
                <span class="fw-bold">TOTAL</span>
                <span class="fw-bold fs-5 text-success"><?= formatPesos($venta['total']) ?></span>
            </div>
        </div>
    </div>

    <!-- ── ANULAR VENTA (solo Admin, solo confirmadas) ───── -->
    <?php if ($user['rol'] === 'admin' && $venta['estado'] === 'confirmada'): ?>
    <div class="mb-4">
        <button class="btn btn-outline-danger w-100 rounded-3 py-2"
                data-bs-toggle="modal" data-bs-target="#modal-anular">
            <i class="bi bi-x-octagon me-2"></i>Anular venta
        </button>
    </div>

    <!-- Modal confirmación de anulación -->
    <div class="modal fade" id="modal-anular" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-body text-center pt-4 pb-3 px-4">
                    <i class="bi bi-exclamation-triangle-fill text-danger mb-3 d-block"
                       style="font-size:2.5rem"></i>
                    <h2 class="fs-5 fw-bold mb-2">¿Anular venta #<?= $id ?>?</h2>
                    <p class="text-muted small mb-4">
                        Se repondrá el stock automáticamente.
                        Esta acción no se puede deshacer.
                    </p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="anular" value="1">
                        <div class="d-flex gap-2">
                            <button type="button"
                                    class="btn btn-outline-secondary flex-fill rounded-3 py-2"
                                    data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit"
                                    class="btn btn-danger flex-fill rounded-3 py-2 fw-bold">
                                Sí, anular
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<!-- ── ESTILOS DE IMPRESIÓN ────────────────────────────────── -->
<style>
@media print {
    .app-topbar, .bottom-nav, .bottom-nav-spacer,
    [data-bs-toggle="modal"], .btn-outline-secondary { display: none !important; }
    body { background: #fff !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    main { padding-top: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
