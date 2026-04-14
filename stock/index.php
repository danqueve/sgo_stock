<?php
// ============================================================
// stock/index.php — Inventario con Lazy Loading
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pdo        = getPDO();
$pageTitle  = 'Stock';
$user       = currentUser();
$csrfToken  = csrfToken();

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

<?php if ($user['rol'] === 'admin'): ?>
<!-- Modal: Ajuste rápido de stock -->
<div class="modal fade" id="modal-ajuste-rapido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold fs-6">
                    <i class="bi bi-boxes me-2 text-warning"></i>Ajuste de stock
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="text-muted small mb-3" id="ajuste-art-nombre">—</p>

                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="small fw-medium">Stock actual</span>
                    <span class="badge bg-secondary fs-6 px-3 py-2" id="ajuste-stock-badge">—</span>
                </div>

                <form id="form-ajuste-rapido">
                    <input type="hidden" id="ajuste-art-id" name="id">

                    <div class="row g-2 mb-3">
                        <div class="col-5">
                            <label class="form-label small fw-medium mb-1">Tipo</label>
                            <select id="ajuste-tipo" name="tipo_ajuste"
                                    class="form-select form-select-touch">
                                <option value="entrada">+ Entrada</option>
                                <option value="salida">− Salida</option>
                                <option value="ajuste">≈ Ajuste</option>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label small fw-medium mb-1">Cant.</label>
                            <input type="number" id="ajuste-cant" name="cant_ajuste"
                                   class="form-control form-control-touch text-center fw-bold"
                                   value="1" min="1" inputmode="numeric">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-medium mb-1">Motivo</label>
                            <input type="text" id="ajuste-motivo" name="motivo"
                                   class="form-control form-control-touch"
                                   placeholder="Ajuste manual">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 rounded-3 py-2 fw-bold">
                        <i class="bi bi-check2-circle me-2"></i>Aplicar ajuste
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const BASE_URL   = '<?= APP_URL ?>';
    const IS_ADMIN   = <?= $user['rol'] === 'admin' ? 'true' : 'false' ?>;
    const CSRF_TOKEN = '<?= $csrfToken ?>';

    /* ── Para admin: override renderCard con botón ± ─── */
    if (IS_ADMIN) {
        StockLazyLoader.prototype.renderCard = function(item) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 col-lg-3';
            const stockClass = item.stock_actual === 0 ? 'stock-empty'
                             : item.stock_actual <= item.stock_minimo ? 'stock-low'
                             : 'stock-ok';
            const stockText  = item.stock_actual === 0 ? 'Sin stock'
                             : item.stock_actual <= item.stock_minimo ? `¡Últimas ${item.stock_actual}!`
                             : `Stock: ${item.stock_actual}`;
            col.innerHTML = `
                <div class="article-card mb-3 position-relative" data-id="${item.id}">
                    ${item.imagen_url
                        ? `<img src="${item.imagen_url}" class="article-card__img" alt="" loading="lazy">`
                        : `<div class="article-card__img-placeholder"><i class="bi ${item.icono || 'bi-box'}"></i></div>`}
                    <button type="button"
                            class="btn-ajuste-stock position-absolute"
                            data-id="${item.id}"
                            data-nombre="${item.nombre.replace(/"/g,'&quot;')}"
                            data-stock="${item.stock_actual}"
                            data-stock-min="${item.stock_minimo}"
                            title="Ajuste rápido de stock"
                            style="top:6px;right:6px;width:30px;height:30px;border:none;
                                   border-radius:50%;background:rgba(255,193,7,.92);
                                   color:#000;font-size:.9rem;font-weight:700;cursor:pointer;
                                   z-index:5;display:flex;align-items:center;
                                   justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.25);
                                   line-height:1">±</button>
                    <div class="article-card__body">
                        <p class="article-card__name mb-1">${item.nombre}</p>
                        <p class="article-card__price-contado mb-0">${item.precio_contado_fmt}</p>
                        ${item.cuotas > 1
                            ? `<p class="article-card__price-cuota mb-1">${item.cuotas}x ${item.monto_cuota_fmt}</p>`
                            : `<p class="mb-1">&nbsp;</p>`}
                        <span class="badge article-card__stock-badge ${stockClass}"
                              id="stock-badge-${item.id}">${stockText}</span>
                    </div>
                </div>`;
            return col;
        };
    }

    const loader = window.stockLoader = new StockLazyLoader({
        endpoint:    `${BASE_URL}/api/articulos.php`,
        containerId: 'stock-grid',
        sentinelId:  'stock-sentinel',
        perPage:     12,
    });

    // Total de artículos
    fetch(`${BASE_URL}/api/articulos.php?count=1`)
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
    const buscar   = document.getElementById('buscar-stock');
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

    /* ── Tap en grid: botón ± o navegar a artículo ── */
    const grid    = document.getElementById('stock-grid');
    <?php if ($user['rol'] === 'admin'): ?>
    const modalEl = document.getElementById('modal-ajuste-rapido');
    const bsModal = new bootstrap.Modal(modalEl);

    grid.addEventListener('click', e => {
        // Botón ± → abrir modal
        const btnAjuste = e.target.closest('.btn-ajuste-stock');
        if (btnAjuste) {
            e.stopPropagation();
            document.getElementById('ajuste-art-id').value        = btnAjuste.dataset.id;
            document.getElementById('ajuste-art-nombre').textContent = btnAjuste.dataset.nombre;
            document.getElementById('ajuste-stock-badge').textContent = `${btnAjuste.dataset.stock} uds`;
            document.getElementById('ajuste-stock-badge').dataset.stockMin = btnAjuste.dataset.stockMin;
            document.getElementById('ajuste-cant').value = 1;
            document.getElementById('ajuste-motivo').value = '';
            document.getElementById('ajuste-tipo').value = 'entrada';
            bsModal.show();
            return;
        }
        // Click en card → ir a editar
        const card = e.target.closest('.article-card');
        if (card) window.location.href = `${BASE_URL}/stock/editar.php?id=${card.dataset.id}`;
    });

    /* ── AJAX: enviar ajuste de stock ─────────────── */
    document.getElementById('form-ajuste-rapido').addEventListener('submit', async e => {
        e.preventDefault();
        const artId    = document.getElementById('ajuste-art-id').value;
        const tipo     = document.getElementById('ajuste-tipo').value;
        const cant     = document.getElementById('ajuste-cant').value;
        const motivo   = document.getElementById('ajuste-motivo').value.trim() || 'Ajuste manual';
        const submitBtn = e.target.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        try {
            const res = await fetch(`${BASE_URL}/api/stock_ajuste.php`, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:        new URLSearchParams({
                    csrf_token:  CSRF_TOKEN,
                    id:          artId,
                    tipo_ajuste: tipo,
                    cant_ajuste: cant,
                    motivo,
                }),
            });
            const data = await res.json();
            if (data.ok) {
                const nuevoStock = data.stock_nuevo;
                const stockMin   = parseInt(data.stock_min ?? 1);
                const badge      = document.getElementById(`stock-badge-${artId}`);
                const btnEl      = document.querySelector(`.btn-ajuste-stock[data-id="${artId}"]`);

                if (badge) {
                    const cls  = nuevoStock === 0 ? 'stock-empty'
                               : nuevoStock <= stockMin ? 'stock-low' : 'stock-ok';
                    const txt  = nuevoStock === 0 ? 'Sin stock'
                               : nuevoStock <= stockMin ? `¡Últimas ${nuevoStock}!`
                               : `Stock: ${nuevoStock}`;
                    badge.className   = `badge article-card__stock-badge ${cls}`;
                    badge.textContent = txt;
                }
                if (btnEl) {
                    btnEl.dataset.stock = nuevoStock;
                }
                // Actualizar badge del modal por si lo vuelven a abrir
                document.getElementById('ajuste-stock-badge').textContent = `${nuevoStock} uds`;
                bsModal.hide();
            } else {
                alert(data.error || 'Error al ajustar stock');
            }
        } catch {
            alert('Error de conexión. Intentá nuevamente.');
        } finally {
            submitBtn.disabled = false;
        }
    });

    <?php else: ?>
    grid.addEventListener('click', e => {
        const card = e.target.closest('.article-card');
        if (card) window.location.href = `${BASE_URL}/ventas/nueva.php?articulo_id=${card.dataset.id}`;
    });
    <?php endif; ?>
});
</script>
</body>
</html>
