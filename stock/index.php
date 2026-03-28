<?php
// ============================================================
// stock/index.php — Inventario con Lazy Loading
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pdo       = getPDO();
$pageTitle = 'Stock';
$user      = currentUser();

// Cargar categorías para filtros
$categorias = $pdo->query(
    'SELECT id, nombre, icono FROM categorias WHERE activo = 1 ORDER BY nombre'
)->fetchAll();
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3">

    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="fs-5 fw-bold mb-0">Inventario</h1>
            <p class="text-muted small mb-0" id="lbl-total-art">Cargando...</p>
        </div>
        <?php if ($user['rol'] === 'admin'): ?>
        <a href="<?= APP_URL ?>/stock/nuevo.php"
           class="btn btn-warning btn-sm fw-medium rounded-3 px-3">
            <i class="bi bi-plus-lg me-1"></i>Agregar
        </a>
        <?php endif; ?>
    </div>

    <!-- Buscador global -->
    <div class="input-group mb-3 shadow-sm">
        <span class="input-group-text bg-white border-end-0">
            <i class="bi bi-search text-muted"></i>
        </span>
        <input type="text"
               id="buscar-stock"
               class="form-control form-control-touch border-start-0 ps-0"
               placeholder="Buscar artículo..."
               autocomplete="off"
               inputmode="search">
        <button class="input-group-text bg-white" id="btn-clear-busca" style="display:none">
            <i class="bi bi-x-lg text-muted"></i>
        </button>
    </div>

    <!-- Filtros por categoría (scroll horizontal táctil) -->
    <div class="scroll-x-touch mb-3 pb-1">
        <div class="d-flex gap-2" style="width:max-content">
            <button class="btn btn-sm btn-warning rounded-pill px-3 fw-medium filtro-cat active"
                    data-cat="">
                Todos
            </button>
            <?php foreach ($categorias as $cat): ?>
            <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 filtro-cat"
                    data-cat="<?= $cat['id'] ?>">
                <i class="bi <?= htmlspecialchars($cat['icono']) ?> me-1"></i>
                <?= htmlspecialchars($cat['nombre']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Filtro stock bajo (toggle) -->
    <div class="d-flex align-items-center gap-2 mb-3 small">
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="toggle-stock-bajo">
            <label class="form-check-label text-muted" for="toggle-stock-bajo">
                <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                Solo stock bajo / sin stock
            </label>
        </div>
    </div>

    <!-- Grid de artículos (se llena con Lazy Load) -->
    <div class="row g-3" id="stock-grid">
        <!-- Los artículos se inyectan via JS (StockLazyLoader) -->
    </div>

    <!-- Sentinel — cuando entra en viewport, carga más artículos -->
    <div id="stock-sentinel" class="py-2 text-center">
        <div class="spinner-border spinner-border-sm text-warning" role="status"
             style="display:none" id="spinner-stock">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const loader = window.stockLoader = new StockLazyLoader({
        endpoint:    '<?= APP_URL ?>/api/articulos.php',
        containerId: 'stock-grid',
        sentinelId:  'stock-sentinel',
        perPage:     12,
    });

    // Obtener total de artículos
    fetch('<?= APP_URL ?>/api/articulos.php?count=1')
        .then(r => r.json())
        .then(d => {
            document.getElementById('lbl-total-art').textContent =
                `${d.total} artículos en stock`;
        });

    /* ── Filtros por categoría ─────────────────────── */
    document.querySelectorAll('.filtro-cat').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filtro-cat').forEach(b => {
                b.classList.remove('btn-warning', 'active');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.add('btn-warning', 'active');
            btn.classList.remove('btn-outline-secondary');
            loader.setFilter('categoria_id', btn.dataset.cat);
        });
    });

    /* ── Buscador con debounce ─────────────────────── */
    let timer;
    const buscar = document.getElementById('buscar-stock');
    const btnClear = document.getElementById('btn-clear-busca');

    buscar.addEventListener('input', () => {
        clearTimeout(timer);
        btnClear.style.display = buscar.value ? 'flex' : 'none';
        timer = setTimeout(() => loader.setFilter('q', buscar.value), 400);
    });

    btnClear.addEventListener('click', () => {
        buscar.value = '';
        btnClear.style.display = 'none';
        loader.setFilter('q', '');
    });

    /* ── Toggle stock bajo ─────────────────────────── */
    document.getElementById('toggle-stock-bajo').addEventListener('change', e => {
        loader.setFilter('stock_bajo', e.target.checked ? '1' : '');
    });

    /* ── Tap en card → ir a detalle (admin) o seleccionar (vendedor) ── */
    document.getElementById('stock-grid').addEventListener('click', e => {
        const card = e.target.closest('.article-card');
        if (!card) return;
        const id = card.dataset.id;
        <?php if ($user['rol'] === 'admin'): ?>
        window.location.href = `/sgo/stock/editar.php?id=${id}`;
        <?php else: ?>
        window.location.href = `/sgo/ventas/nueva.php?articulo_id=${id}`;
        <?php endif; ?>
    });
});
</script>
</body>
</html>
