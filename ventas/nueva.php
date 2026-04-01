<?php
// ============================================================
// ventas/nueva.php — Venta Rápida  |  Bootstrap 5 Mobile-First
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireLogin();

$pdo       = getPDO();
$pageTitle = 'Nueva Venta';
$user      = currentUser();

// Cargar provincias para el selector
$provincias = $pdo->query('SELECT id, nombre FROM provincias ORDER BY nombre')->fetchAll();

// Manejo POST — Confirmar venta
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar CSRF
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido. Recargá la página.';
    } else {
        // Sanitizar y validar
        $nombre      = trim($_POST['nombre']      ?? '');
        $apellido    = trim($_POST['apellido']    ?? '');
        $direccion   = trim($_POST['direccion']   ?? '');
        $provincia   = (int)($_POST['provincia_id'] ?? 0);
        $localidad   = trim($_POST['localidad']   ?? '');
        $celular     = trim($_POST['celular']      ?? '');
        $obs         = trim($_POST['observaciones'] ?? '');
        $tipo_pago   = $_POST['tipo_pago'] ?? 'financiado';
        $cuotas      = (int)($_POST['cuotas'] ?? 1);
        $articulo_id        = (int)($_POST['articulo_id'] ?? 0);
        $cantidad           = (int)($_POST['cantidad'] ?? 1);
        $es_mensual         = ($tipo_pago === 'financiado' && isset($_POST['es_mensual'])) ? 1 : 0;
        $primer_vencimiento = trim($_POST['primer_vencimiento'] ?? '');

        if (!$nombre)    $errors[] = 'El nombre es obligatorio.';
        if (!$apellido)  $errors[] = 'El apellido es obligatorio.';
        if (!$direccion) $errors[] = 'La dirección es obligatoria.';
        if (!$provincia) $errors[] = 'Seleccioná una provincia.';
        if (!$localidad) $errors[] = 'La localidad es obligatoria.';
        if (!preg_match('/^[\d\s\-\+]{8,15}$/', $celular)) $errors[] = 'Celular inválido.';
        if (!$articulo_id) $errors[] = 'Seleccioná un artículo.';
        if ($cantidad < 1) $errors[] = 'La cantidad debe ser al menos 1.';
        if ($es_mensual) {
            if (!$primer_vencimiento) {
                $errors[] = 'Indicá la fecha del primer vencimiento.';
            } elseif ($primer_vencimiento < date('Y-m-d')) {
                $errors[] = 'El primer vencimiento no puede ser una fecha pasada.';
            }
        }

        if (empty($errors)) {
            // Verificar stock disponible
            $art = $pdo->prepare(
                'SELECT id, nombre, precio_contado, precio_financiado, cuotas, stock_actual
                   FROM articulos WHERE id = ? AND activo = 1'
            );
            $art->execute([$articulo_id]);
            $articulo = $art->fetch();

            if (!$articulo) {
                $errors[] = 'Artículo no encontrado.';
            } elseif ($articulo['stock_actual'] < $cantidad) {
                $errors[] = "Stock insuficiente. Disponible: {$articulo['stock_actual']} unidad(es).";
            }

            if (empty($errors)) {
                $precio = ($tipo_pago === 'financiado')
                    ? $articulo['precio_financiado']
                    : $articulo['precio_contado'];
                $total  = $precio * $cantidad;

                try {
                    $pdo->beginTransaction();

                    // 1. Insertar cliente
                    $stmtCli = $pdo->prepare(
                        'INSERT INTO clientes
                            (nombre, apellido, celular, direccion, localidad, provincia_id, observaciones)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $stmtCli->execute([
                        $nombre, $apellido, $celular,
                        $direccion, $localidad, $provincia, $obs
                    ]);
                    $clienteId = (int)$pdo->lastInsertId();

                    // 2. Insertar venta (cabecera)
                    $stmtVta = $pdo->prepare(
                        'INSERT INTO ventas
                            (cliente_id, vendedor_id, tipo_pago, cuotas,
                             es_mensual, primer_vencimiento, total, estado, observaciones)
                         VALUES (?, ?, ?, ?, ?, ?, ?, "confirmada", ?)'
                    );
                    $stmtVta->execute([
                        $clienteId, $user['id'],
                        $tipo_pago, ($tipo_pago === 'financiado' ? $cuotas : 1),
                        $es_mensual,
                        ($es_mensual && $primer_vencimiento) ? $primer_vencimiento : null,
                        $total, $obs
                    ]);
                    $ventaId = (int)$pdo->lastInsertId();

                    // 3. Insertar detalle (el TRIGGER descuenta stock)
                    $pdo->prepare(
                        'INSERT INTO venta_detalles
                            (venta_id, articulo_id, cantidad, precio_unitario, subtotal)
                         VALUES (?, ?, ?, ?, ?)'
                    )->execute([$ventaId, $articulo_id, $cantidad, $precio, $total]);

                    $pdo->commit();

                    // Limpiar borrador JS (via flash)
                    setFlash('success', "¡Venta #{$ventaId} confirmada! 🎉");
                    header('Location: ' . APP_URL . '/ventas/detalle.php?id=' . $ventaId);
                    exit;

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $errors[] = 'Error al guardar la venta. Intentá nuevamente.';
                }
            }
        }
    }
}

$csrfToken = csrfToken();
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>

<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3 pb-2">

    <!-- Encabezado de sección -->
    <div class="d-flex align-items-center mb-3 gap-2">
        <a href="<?= APP_URL ?>/index.php"
           class="btn btn-sm btn-light rounded-circle p-2"
           style="min-width:38px;min-height:38px;"
           aria-label="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="fs-5 fw-bold mb-0">Nueva Venta</h1>
            <p class="text-muted small mb-0">Completá los datos del cliente</p>
        </div>
    </div>

    <!-- Errores globales -->
    <?php if ($errors): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         FORMULARIO DE VENTA RÁPIDA
         Bootstrap 5 Mobile Utility Classes
         ====================================================== -->
    <form id="form-venta" method="POST" action="" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <!-- ── PASO 1: Artículo seleccionado ──────────────── -->
        <div class="mb-3">
            <p class="section-title">
                <i class="bi bi-1-circle-fill me-1 text-warning"></i> Artículo
            </p>

            <!-- Botón para abrir modal de selección -->
            <button type="button" id="btn-abrir-modal-art"
                    class="btn btn-outline-secondary w-100 rounded-3 py-3 mb-2"
                    data-bs-toggle="modal" data-bs-target="#modal-articulos">
                <i class="bi bi-search me-2 text-warning"></i>
                Buscar y seleccionar artículo...
            </button>

            <!-- Artículo seleccionado (se muestra después de elegir) -->
            <div id="articulo-seleccionado" class="selected-article-card d-none">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-check-circle-fill text-success fs-4 mt-1 flex-shrink-0"></i>
                    <div class="flex-grow-1 min-w-0">
                        <p class="fw-semibold mb-1" id="art-nombre">—</p>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span id="art-precio-contado" class="text-success fw-bold"></span>
                            <span id="art-precio-financiado" class="text-muted small"></span>
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            Stock disponible: <strong id="art-stock">—</strong>
                        </p>
                    </div>
                    <button type="button" id="btn-cambiar-art"
                            class="btn btn-sm btn-outline-secondary flex-shrink-0"
                            data-bs-toggle="modal" data-bs-target="#modal-articulos">
                        <i class="bi bi-pencil"></i>
                    </button>
                </div>
            </div>

            <input type="hidden" name="articulo_id" id="articulo_id" required>
        </div>

        <!-- ── PASO 2: Tipo de pago ───────────────────────── -->
        <div class="mb-3" id="sec-tipo-pago">
            <p class="section-title">
                <i class="bi bi-2-circle-fill me-1 text-warning"></i> Tipo de pago
            </p>

            <div class="pago-toggle">
                <!-- Financiado (primero y por defecto) -->
                <input type="radio" class="btn-check" name="tipo_pago"
                       id="pago-financiado" value="financiado" checked>
                <label class="w-100" for="pago-financiado">
                    <i class="bi bi-credit-card text-primary"></i>
                    <span>Financiado</span>
                </label>

                <!-- Contado -->
                <input type="radio" class="btn-check" name="tipo_pago"
                       id="pago-contado" value="contado">
                <label class="w-100" for="pago-contado">
                    <i class="bi bi-cash-coin text-success"></i>
                    <span>Contado</span>
                </label>
            </div>

            <!-- Resumen precio financiado (visible por defecto) -->
            <div id="sec-financiado" class="mt-3">
                <input type="hidden" name="cuotas" id="sel-cuotas" value="1">

                <div class="p-3 bg-primary bg-opacity-10 border border-primary
                            border-opacity-25 rounded-3 small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Cuotas</span>
                        <strong class="text-primary" id="lbl-cuotas-cant">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Monto por cuota</span>
                        <strong class="text-primary fs-5" id="lbl-monto-cuota">—</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Total financiado</span>
                        <span class="fw-medium" id="lbl-total">—</span>
                    </div>
                </div>
            </div>

            <!-- Venta mensual (solo financiado) -->
            <div class="mt-3 p-3 border rounded-3 bg-white" id="sec-mensual">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox"
                           role="switch"
                           name="es_mensual" id="chk-mensual" value="1"
                           <?= !isset($_POST['es_mensual']) || $_POST['es_mensual'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="chk-mensual">
                        <i class="bi bi-calendar-month me-1 text-primary"></i>
                        ¿Es venta mensual?
                    </label>
                </div>
                <div id="sec-vencimiento" class="mt-3 <?= (isset($_POST['es_mensual']) && !$_POST['es_mensual']) ? 'd-none' : '' ?>">
                    <label for="primer_vencimiento"
                           class="form-label small fw-medium mb-1">
                        Primer vencimiento *
                    </label>
                    <input type="date"
                           id="primer_vencimiento" name="primer_vencimiento"
                           class="form-control form-control-touch"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= htmlspecialchars($_POST['primer_vencimiento'] ?? '') ?>">
                    <div class="form-text text-muted small mt-1">
                        <i class="bi bi-info-circle me-1"></i>Fecha del primer cobro mensual
                    </div>
                </div>
            </div>

            <!-- Resumen precio contado (oculto por defecto) -->
            <div id="sec-contado" class="mt-3 d-none p-3 bg-success bg-opacity-10
                 border border-success border-opacity-25 rounded-3 small">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Precio contado</span>
                    <strong class="text-success fs-5" id="lbl-precio-contado">—</strong>
                </div>
            </div>
        </div>

        <!-- ── PASO 3: Cantidad ───────────────────────────── -->
        <div class="mb-3">
            <p class="section-title">
                <i class="bi bi-3-circle-fill me-1 text-warning"></i> Cantidad
            </p>
            <!-- Selector numérico con botones táctiles -->
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-outline-secondary rounded-circle
                        d-flex align-items-center justify-content-center"
                        style="width:44px;height:44px;font-size:1.2rem"
                        onclick="cambiarCantidad(-1)">
                    <i class="bi bi-dash"></i>
                </button>
                <input type="number" name="cantidad" id="cantidad"
                       class="form-control form-control-touch text-center fw-bold fs-4"
                       value="1" min="1" max="99"
                       style="max-width:90px"
                       readonly>
                <button type="button" class="btn btn-outline-secondary rounded-circle
                        d-flex align-items-center justify-content-center"
                        style="width:44px;height:44px;font-size:1.2rem"
                        onclick="cambiarCantidad(1)">
                    <i class="bi bi-plus"></i>
                </button>
            </div>
        </div>

        <!-- ── PASO 4: Datos del cliente ─────────────────── -->
        <div class="mb-3">
            <p class="section-title">
                <i class="bi bi-4-circle-fill me-1 text-warning"></i> Datos del cliente
            </p>

            <div class="venta-form rounded-4 p-3 shadow-sm">

                <!-- Nombre + Apellido en grid de 2 -->
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label for="nombre" class="form-label small fw-medium mb-1">Nombre *</label>
                        <input type="text"
                               id="nombre" name="nombre"
                               class="form-control form-control-touch"
                               placeholder="Juan"
                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                               autocomplete="given-name"
                               required>
                        <div class="invalid-feedback">Nombre requerido.</div>
                    </div>
                    <div class="col-6">
                        <label for="apellido" class="form-label small fw-medium mb-1">Apellido *</label>
                        <input type="text"
                               id="apellido" name="apellido"
                               class="form-control form-control-touch"
                               placeholder="García"
                               value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>"
                               autocomplete="family-name"
                               required>
                        <div class="invalid-feedback">Apellido requerido.</div>
                    </div>
                </div>

                <!-- Celular -->
                <div class="mb-3">
                    <label for="celular" class="form-label small fw-medium mb-1">
                        Celular *
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-whatsapp text-success"></i>
                        </span>
                        <input type="tel"
                               id="celular" name="celular"
                               class="form-control form-control-touch"
                               placeholder="11 2345-6789"
                               value="<?= htmlspecialchars($_POST['celular'] ?? '') ?>"
                               autocomplete="tel"
                               inputmode="tel"
                               required>
                    </div>
                    <div class="invalid-feedback">Celular inválido.</div>
                </div>

                <!-- Dirección -->
                <div class="mb-3">
                    <label for="direccion" class="form-label small fw-medium mb-1">
                        Dirección *
                    </label>
                    <input type="text"
                           id="direccion" name="direccion"
                           class="form-control form-control-touch"
                           placeholder="Av. San Martín 1234"
                           value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>"
                           autocomplete="street-address"
                           required>
                    <div class="invalid-feedback">Dirección requerida.</div>
                </div>

                <!-- Provincia + Localidad en grid de 2 -->
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label for="provincia_id" class="form-label small fw-medium mb-1">
                            Provincia *
                        </label>
                        <select id="provincia_id" name="provincia_id"
                                class="form-select form-select-touch"
                                required>
                            <option value="">— Elegir —</option>
                            <?php foreach ($provincias as $prov): ?>
                            <option value="<?= $prov['id'] ?>"
                                <?= (($_POST['provincia_id'] ?? '') == $prov['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label for="localidad" class="form-label small fw-medium mb-1">
                            Localidad *
                        </label>
                        <input type="text"
                               id="localidad" name="localidad"
                               class="form-control form-control-touch"
                               placeholder="Ej: Quilmes"
                               value="<?= htmlspecialchars($_POST['localidad'] ?? '') ?>"
                               autocomplete="address-level2"
                               required>
                        <div class="invalid-feedback">Localidad requerida.</div>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-1">
                    <label for="observaciones" class="form-label small fw-medium mb-1">
                        Observaciones <span class="text-muted">(opcional)</span>
                    </label>
                    <textarea id="observaciones" name="observaciones"
                              class="form-control form-control-touch"
                              rows="2"
                              placeholder="Notas adicionales..."
                              style="resize:none"
                    ><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                </div>

            </div><!-- /.venta-form -->
        </div>

        <!-- ── RESUMEN TOTAL ───────────────────────────────── -->
        <div class="card border-0 shadow-sm rounded-4 mb-4" id="resumen-total">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">TOTAL A PAGAR</span>
                    <span class="fs-3 fw-bold text-dark" id="lbl-total-final">$0,00</span>
                </div>
            </div>
        </div>

        <!-- ── BOTÓN CONFIRMAR VENTA ───────────────────────── -->
        <button type="submit" class="btn-venta mb-2" id="btn-confirmar" disabled>
            <i class="bi bi-check2-circle me-2 fs-5"></i>
            Confirmar Venta
        </button>

        <p class="text-center text-muted small">
            <i class="bi bi-shield-lock me-1"></i>
            Datos seguros. Se descuenta el stock automáticamente.
        </p>

    </form>
</main>

<!-- ── Modal: selección de artículo ──────────────────────────── -->
<div class="modal fade" id="modal-articulos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header py-2 border-0 gap-2">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="modal-buscar"
                           class="form-control border-start-0 ps-0 form-control-touch"
                           placeholder="Buscar artículo..."
                           autocomplete="off" inputmode="search">
                </div>
                <button type="button" class="btn-close flex-shrink-0"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body p-2">
                <div id="modal-loading" class="text-center py-5">
                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                    <p class="text-muted mt-2 small">Cargando artículos...</p>
                </div>
                <div id="modal-empty" class="text-center py-5 d-none">
                    <i class="bi bi-box-seam text-muted" style="font-size:2.5rem"></i>
                    <p class="text-muted mt-2 small">Sin resultados</p>
                </div>
                <div class="row g-2" id="modal-grid"></div>
            </div>

        </div>
    </div>
</div>

<!-- Modal de confirmación de venta -->
<div class="modal fade" id="modal-confirmar-venta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-body p-4 text-center">
                <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 rounded-circle mb-3"
                     style="width:60px; height:60px">
                    <i class="bi bi-bag-check-fill text-warning fs-3"></i>
                </div>
                <h5 class="fw-bold mb-1">¿Confirmar venta?</h5>
                <p class="text-muted small mb-3">Revisá los datos antes de confirmar</p>

                <div class="bg-light rounded-3 p-3 text-start small mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Artículo</span>
                        <strong id="confirm-art" class="text-end" style="max-width:55%">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Pago</span>
                        <strong id="confirm-pago">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1 d-none" id="confirm-mensual-row">
                        <span class="text-muted">Mensual</span>
                        <strong id="confirm-mensual" class="text-primary">—</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Cantidad</span>
                        <strong id="confirm-cant">—</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Total</span>
                        <span class="fw-bold text-success fs-5" id="confirm-total">—</span>
                    </div>
                </div>
            </div>
            <div class="d-flex border-top">
                <button type="button" class="btn btn-light flex-fill py-3 rounded-0 border-0 fw-medium"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" class="btn btn-warning flex-fill py-3 rounded-0 border-0 fw-bold" id="btn-confirm-final">
                    <i class="bi bi-check2-circle me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>

<!-- Bootstrap JS + App JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
/* ── Estado ──────────────────────────────────────────────── */
const artSelected   = document.getElementById('articulo-seleccionado');
const artInput      = document.getElementById('articulo_id');
const btnConfirm    = document.getElementById('btn-confirmar');
const cantInput     = document.getElementById('cantidad');
const btnAbrirModal = document.getElementById('btn-abrir-modal-art');
const modalEl       = document.getElementById('modal-articulos');
const modalBuscar   = document.getElementById('modal-buscar');

let articuloActual    = null;
let catalogoArticulos = [];
const chkMensual      = document.getElementById('chk-mensual');
const secVencimiento  = document.getElementById('sec-vencimiento');
const inputVenc       = document.getElementById('primer_vencimiento');
let catalogoCargado   = false;

/* ── Carga del catálogo ──────────────────────────────────── */
(async function cargarCatalogo() {
    try {
        const res  = await fetch(`<?= APP_URL ?>/api/articulos.php?per_page=500`, {
            credentials: 'same-origin'
        });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (data.error) throw new Error(data.error);
        catalogoArticulos = data.items || [];
    } catch(err) {
        catalogoArticulos = [];
        // Mostrar error dentro del modal si ya está visible
        const loading = document.getElementById('modal-loading');
        loading.innerHTML = `<p class="text-danger small text-center py-3">
            <i class="bi bi-exclamation-triangle me-1"></i>${err.message}</p>`;
    } finally {
        catalogoCargado = true;
        // Si el modal se abrió antes de que termine la carga, renderizar ahora
        if (modalEl.classList.contains('show')) renderModalCards(catalogoArticulos);
    }
})();

/* ── Eventos del modal ───────────────────────────────────── */
modalEl.addEventListener('show.bs.modal', () => {
    modalBuscar.value = '';
    if (catalogoCargado) renderModalCards(catalogoArticulos);
});

modalEl.addEventListener('shown.bs.modal', () => modalBuscar.focus());

modalBuscar.addEventListener('input', () => {
    const q = modalBuscar.value.trim().toLowerCase();
    const filtrados = q
        ? catalogoArticulos.filter(a => a.nombre.toLowerCase().includes(q))
        : catalogoArticulos;
    renderModalCards(filtrados);
});

function renderModalCards(items) {
    const grid    = document.getElementById('modal-grid');
    const empty   = document.getElementById('modal-empty');
    const loading = document.getElementById('modal-loading');

    loading.classList.add('d-none');
    grid.innerHTML = '';

    if (!items.length) {
        empty.classList.remove('d-none');
        return;
    }
    empty.classList.add('d-none');

    items.forEach(item => {
        const col = document.createElement('div');
        col.className = 'col-6';

        const stockClass = item.stock_actual === 0               ? 'stock-empty'
                         : item.stock_actual <= item.stock_minimo ? 'stock-low'
                         : 'stock-ok';
        const stockText  = item.stock_actual === 0               ? 'Sin stock'
                         : item.stock_actual <= item.stock_minimo ? `¡Últimas ${item.stock_actual}!`
                         : `Stock: ${item.stock_actual}`;

        col.innerHTML = `
            <div class="article-card mb-2" style="cursor:pointer">
                ${item.imagen_url
                    ? `<img src="${item.imagen_url}" class="article-card__img" alt="" loading="lazy">`
                    : `<div class="article-card__img-placeholder">
                           <i class="bi ${item.icono || 'bi-box'}"></i>
                       </div>`}
                <div class="article-card__body">
                    <p class="article-card__name mb-1">${item.nombre}</p>
                    <p class="article-card__price-contado mb-0">${item.precio_contado_fmt}</p>
                    ${item.cuotas > 1
                        ? `<p class="article-card__price-cuota mb-1">${item.cuotas}x ${item.monto_cuota_fmt}</p>`
                        : `<p class="mb-1">&nbsp;</p>`}
                    <span class="badge article-card__stock-badge ${stockClass}">${stockText}</span>
                </div>
            </div>`;

        col.querySelector('.article-card').addEventListener('click', () => {
            seleccionarArticulo(item);
            bootstrap.Modal.getInstance(modalEl).hide();
        });
        grid.appendChild(col);
    });
}

/* ── Selección de artículo ───────────────────────────────── */
function seleccionarArticulo(item) {
    articuloActual = item;
    artInput.value = item.id;

    document.getElementById('art-nombre').textContent            = item.nombre;
    document.getElementById('art-precio-contado').textContent    = item.precio_contado_fmt;
    document.getElementById('art-precio-financiado').textContent =
        item.cuotas > 1 ? `${item.cuotas}x ${item.monto_cuota_fmt}` : '';
    document.getElementById('art-stock').textContent             = item.stock_actual;
    document.getElementById('lbl-precio-contado').textContent    = item.precio_contado_fmt;

    document.getElementById('sel-cuotas').value = item.cuotas;
    const lblCuotasCant = document.getElementById('lbl-cuotas-cant');
    if (lblCuotasCant) lblCuotasCant.textContent =
        item.cuotas === 1 ? '1 pago' : `${item.cuotas} cuotas`;

    actualizarTotal();
    artSelected.classList.remove('d-none');
    btnAbrirModal.classList.add('d-none');
    btnConfirm.disabled = false;
}

/* ── Calcular total dinámico ─────────────────────────────── */
function actualizarTotal() {
    if (!articuloActual) return;
    const tipo   = document.querySelector('input[name="tipo_pago"]:checked')?.value;
    const cant   = parseInt(cantInput.value) || 1;
    const precio = tipo === 'financiado'
        ? parseFloat(articuloActual.precio_financiado)
        : parseFloat(articuloActual.precio_contado);
    const total  = precio * cant;

    document.getElementById('lbl-total-final').textContent = formatPesos(total);

    if (tipo === 'financiado') {
        const cuotaMonto = articuloActual.monto_cuota * cant;
        const lblMonto = document.getElementById('lbl-monto-cuota');
        if (lblMonto) lblMonto.textContent = formatPesos(cuotaMonto);
        const lblTotal = document.getElementById('lbl-total');
        if (lblTotal) lblTotal.textContent = formatPesos(total);
    }
}

function formatPesos(n) {
    return '$' + n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function toggleSeccionesPago(tipo) {
    document.getElementById('sec-financiado').classList.toggle('d-none', tipo !== 'financiado');
    document.getElementById('sec-contado').classList.toggle('d-none',    tipo === 'financiado');
    // Al cambiar a contado, limpiar toggle mensual
    if (tipo === 'contado') {
        chkMensual.checked = false;
        secVencimiento.classList.add('d-none');
        inputVenc.value = '';
    }
}

/* ── Toggle venta mensual ────────────────────────────────── */
chkMensual.addEventListener('change', function () {
    secVencimiento.classList.toggle('d-none', !this.checked);
    if (!this.checked) inputVenc.value = '';
});

document.querySelectorAll('input[name="tipo_pago"]').forEach(r =>
    r.addEventListener('change', e => {
        toggleSeccionesPago(e.target.value);
        actualizarTotal();
    }));
cantInput.addEventListener('change', actualizarTotal);

/* ── Botones cantidad ────────────────────────────────────── */
window.cambiarCantidad = function(delta) {
    const max = articuloActual ? articuloActual.stock_actual : 99;
    let v = Math.min(Math.max(1, parseInt(cantInput.value) + delta), max);
    cantInput.value = v;
    actualizarTotal();
};

/* ── Modal de confirmación antes de enviar venta ──────────── */
(function() {
    const form = document.getElementById('form-venta');
    const btn  = document.getElementById('btn-confirmar');
    if (!form || !btn) return;

    const confirmModal = new bootstrap.Modal(document.getElementById('modal-confirmar-venta'));

    btn.addEventListener('click', function(e) {
        e.preventDefault();

        // Validar formulario primero
        if (window.ventaValidator && !window.ventaValidator.validateAll()) return;
        if (!artInput.value || !articuloActual) return;

        // Llenar resumen en el modal
        const tipo = document.querySelector('input[name="tipo_pago"]:checked')?.value;
        const cant = parseInt(cantInput.value) || 1;

        document.getElementById('confirm-art').textContent  = articuloActual.nombre;
        document.getElementById('confirm-pago').textContent = tipo === 'financiado' ? 'Financiado' : 'Contado';
        document.getElementById('confirm-cant').textContent = cant;
        document.getElementById('confirm-total').textContent = document.getElementById('lbl-total-final').textContent;

        // Mensual en el modal
        const mensualRow = document.getElementById('confirm-mensual-row');
        if (tipo === 'financiado' && chkMensual.checked && inputVenc.value) {
            const [y, m, d] = inputVenc.value.split('-');
            document.getElementById('confirm-mensual').textContent = `Sí — vence el ${d}/${m}/${y}`;
            mensualRow.classList.remove('d-none');
        } else {
            mensualRow.classList.add('d-none');
        }

        confirmModal.show();
    });

    // Botón final de confirmar dentro del modal
    document.getElementById('btn-confirm-final').addEventListener('click', function() {
        confirmModal.hide();
        form.submit();
    });
})();
</script>
</body>
</html>
