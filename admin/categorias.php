<?php
// ============================================================
// admin/categorias.php — Gestión de categorías (solo Admin)
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Categorías';

// Listar categorías
$categorias = $pdo->query('SELECT * FROM categorias ORDER BY activo DESC, nombre ASC')->fetchAll();
$csrfToken = csrfToken();

// Algunos iconos sugeridos
$iconosSugeridos = [
    'bi-box' => 'Caja',
    'bi-tv' => 'TV',
    'bi-house' => 'Casa',
    'bi-phone' => 'Celular',
    'bi-bag' => 'Bolsa',
    'bi-grid' => 'Grilla',
    'bi-lightbulb' => 'Idea',
    'bi-tools' => 'Herramientas',
    'bi-heart' => 'Salud',
    'bi-controller' => 'Juegos'
];
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3 pb-2">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="fs-5 fw-bold mb-0">Categorías</h1>
        <button class="btn btn-warning btn-sm fw-medium rounded-3 px-3"
                data-bs-toggle="modal" data-bs-target="#modal-nueva">
            <i class="bi bi-plus-circle me-1"></i>Nueva
        </button>
    </div>

    <!-- Lista de categorías -->
    <div class="d-flex flex-column gap-2 mb-4">
        <?php foreach ($categorias as $cat): ?>
        <div class="card border-0 shadow-sm rounded-3 <?= !$cat['activo'] ? 'opacity-50' : '' ?>">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-3">
                    <!-- Icono -->
                    <div class="rounded-circle d-flex align-items-center justify-content-center
                                flex-shrink-0 bg-light text-dark shadow-sm"
                         style="width:40px;height:40px;font-size:1.2rem;">
                        <i class="bi <?= htmlspecialchars($cat['icono']) ?>"></i>
                    </div>
                    <!-- Info -->
                    <div class="flex-grow-1">
                        <span class="fw-medium small d-block">
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </span>
                        <?php if (!$cat['activo']): ?>
                        <span class="badge bg-danger p-1" style="font-size:.6rem">Inactiva</span>
                        <?php endif; ?>
                    </div>
                    <!-- Acciones -->
                    <form method="POST" action="<?= APP_URL ?>/api/categorias_admin.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                        <input type="hidden" name="accion" value="toggle_activo">
                        <input type="hidden" name="activo" value="<?= $cat['activo'] ?>">
                        <button type="submit" class="btn btn-sm <?= $cat['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-3">
                            <i class="bi bi-<?= $cat['activo'] ? 'eye-slash' : 'eye' ?>"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- ── MODAL: Nueva categoría ───────────────────────────────── -->
<div class="modal fade" id="modal-nueva" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-5 fw-bold">Agregar Categoría</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <form method="POST" action="<?= APP_URL ?>/api/categorias_admin.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="accion" value="crear">

                    <div class="mb-3">
                        <label class="form-label small fw-medium mb-1">Nombre *</label>
                        <input type="text" name="nombre"
                               class="form-control form-control-touch"
                               placeholder="Ej: Accesorios" required autocomplete="off">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-medium mb-1">Icono sugerido</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($iconosSugeridos as $id => $label): ?>
                            <div class="flex-shrink-0">
                                <input type="radio" class="btn-check" name="icono"
                                       id="ico-<?= $id ?>" value="<?= $id ?>"
                                       <?= $id === 'bi-box' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-light text-dark btn-sm rounded-3 border-secondary-subtle px-2 py-2 shadow-sm"
                                       for="ico-<?= $id ?>" title="<?= $label ?>">
                                    <i class="bi <?= $id ?> fs-5"></i>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn-venta">
                        <i class="bi bi-plus-lg me-2"></i>Guardar Categoría
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
