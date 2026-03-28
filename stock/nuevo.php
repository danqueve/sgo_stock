<?php
// ============================================================
// stock/nuevo.php — Alta de artículo (Admin)
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Nuevo Artículo';
$errors    = [];

$categorias = $pdo->query(
    'SELECT id, nombre, icono FROM categorias WHERE activo = 1 ORDER BY nombre'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $nombre     = trim($_POST['nombre']     ?? '');
        $desc       = trim($_POST['descripcion'] ?? '');
        $catId      = (int)($_POST['categoria_id'] ?? 0);
        $codigo     = trim($_POST['codigo']     ?? '') ?: null;
        $pContado   = str_replace(['.', ','], ['', '.'], $_POST['precio_contado'] ?? '0');
        $cuotas     = max(1, (int)($_POST['cuotas'] ?? 1));
        $montoCuota = str_replace(['.', ','], ['', '.'], $_POST['monto_cuota'] ?? '0');
        $pFinanc    = round($cuotas * $montoCuota, 2);
        $stock      = (int)($_POST['stock_actual']  ?? 0);
        $stockMin   = max(1, (int)($_POST['stock_minimo'] ?? 1));
        $imagenUrl  = trim($_POST['imagen_url']  ?? '') ?: null;

        if (!$nombre)        $errors[] = 'El nombre es obligatorio.';
        if (!$catId)         $errors[] = 'Seleccioná una categoría.';
        if ($pContado   <= 0) $errors[] = 'El precio de contado debe ser mayor a 0.';
        if ($montoCuota <= 0) $errors[] = 'El valor de cuota debe ser mayor a 0.';

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO articulos
                        (categoria_id, codigo, nombre, descripcion,
                         precio_contado, precio_financiado, cuotas,
                         stock_actual, stock_minimo, imagen_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $catId, $codigo, $nombre, $desc,
                    $pContado, $pFinanc, $cuotas,
                    $stock, $stockMin, $imagenUrl
                ]);

                // Registrar entrada de stock inicial
                if ($stock > 0) {
                    $artId = (int)$pdo->lastInsertId();
                    $pdo->prepare(
                        'INSERT INTO stock_movimientos
                            (articulo_id, usuario_id, tipo, cantidad,
                             stock_antes, stock_despues, referencia)
                         VALUES (?, ?, "entrada", ?, 0, ?, "Alta de artículo")'
                    )->execute([$artId, currentUser()['id'], $stock, $stock]);
                }

                setFlash('success', "Artículo «{$nombre}» creado con éxito.");
                header('Location: ' . APP_URL . '/stock/index.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'El código de artículo ya existe.';
                } else {
                    $errors[] = 'Error al guardar. Intentá nuevamente.';
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

    <div class="d-flex align-items-center mb-3 gap-2">
        <a href="<?= APP_URL ?>/stock/index.php"
           class="btn btn-sm btn-light rounded-circle p-2"
           style="min-width:38px;min-height:38px;">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="fs-5 fw-bold mb-0">Nuevo artículo</h1>
            <p class="text-muted small mb-0">Completá los datos del producto</p>
        </div>
    </div>

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

    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <!-- Información básica -->
        <div class="venta-form rounded-4 p-3 shadow-sm mb-3">
            <p class="section-title mb-3">
                <i class="bi bi-info-circle-fill me-1 text-warning"></i>Información básica
            </p>

            <div class="mb-3">
                <label for="nombre" class="form-label small fw-medium mb-1">Nombre *</label>
                <input type="text" id="nombre" name="nombre"
                       class="form-control form-control-touch"
                       placeholder="Ej: Televisor 55'' Smart"
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                       required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-7">
                    <label for="categoria_id" class="form-label small fw-medium mb-1">
                        Categoría *
                    </label>
                    <select id="categoria_id" name="categoria_id"
                            class="form-select form-select-touch" required>
                        <option value="">— Elegir —</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= (($_POST['categoria_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-5">
                    <label for="codigo" class="form-label small fw-medium mb-1">
                        Código <span class="text-muted">(opc.)</span>
                    </label>
                    <input type="text" id="codigo" name="codigo"
                           class="form-control form-control-touch"
                           placeholder="SKU-001"
                           value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-1">
                <label for="descripcion" class="form-label small fw-medium mb-1">
                    Descripción <span class="text-muted">(opcional)</span>
                </label>
                <textarea id="descripcion" name="descripcion"
                          class="form-control form-control-touch"
                          rows="2" style="resize:none"
                          placeholder="Características del producto..."
                ><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Precios -->
        <div class="venta-form rounded-4 p-3 shadow-sm mb-3">
            <p class="section-title mb-3">
                <i class="bi bi-tag-fill me-1 text-warning"></i>Precios
            </p>

            <div class="mb-3">
                <label for="precio_contado" class="form-label small fw-medium mb-1">
                    Precio contado *
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light fw-bold text-success">$</span>
                    <input type="number" id="precio_contado" name="precio_contado"
                           class="form-control form-control-touch"
                           placeholder="0.00" min="0" step="0.01"
                           value="<?= htmlspecialchars($_POST['precio_contado'] ?? '') ?>"
                           inputmode="decimal" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label for="cuotas" class="form-label small fw-medium mb-1">
                        Cantidad de cuotas *
                    </label>
                    <input type="number" id="cuotas" name="cuotas"
                           class="form-control form-control-touch text-center fw-bold"
                           value="<?= (int)($_POST['cuotas'] ?? 1) ?>"
                           min="1" max="12" inputmode="numeric">
                </div>
                <div class="col-6">
                    <label for="monto_cuota" class="form-label small fw-medium mb-1">
                        Valor por cuota *
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold text-primary">$</span>
                        <input type="number" id="monto_cuota" name="monto_cuota"
                               class="form-control form-control-touch"
                               placeholder="0.00" min="0" step="0.01"
                               value="<?= htmlspecialchars($_POST['monto_cuota'] ?? '') ?>"
                               inputmode="decimal" required>
                    </div>
                </div>
            </div>

            <!-- Preview total financiado (dinámico) -->
            <div id="preview-cuota"
                 class="p-2 bg-primary bg-opacity-10 rounded-3 small text-center d-none">
                Total financiado:
                <strong id="val-total-financiado" class="text-primary fs-6">—</strong>
            </div>
        </div>

        <!-- Stock -->
        <div class="venta-form rounded-4 p-3 shadow-sm mb-3">
            <p class="section-title mb-3">
                <i class="bi bi-boxes me-1 text-warning"></i>Stock inicial
            </p>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label for="stock_actual" class="form-label small fw-medium mb-1">
                        Unidades disponibles
                    </label>
                    <input type="number" id="stock_actual" name="stock_actual"
                           class="form-control form-control-touch text-center fw-bold"
                           value="<?= (int)($_POST['stock_actual'] ?? 0) ?>"
                           min="0" inputmode="numeric">
                </div>
                <div class="col-6">
                    <label for="stock_minimo" class="form-label small fw-medium mb-1">
                        Alerta bajo stock
                    </label>
                    <input type="number" id="stock_minimo" name="stock_minimo"
                           class="form-control form-control-touch text-center"
                           value="<?= (int)($_POST['stock_minimo'] ?? 1) ?>"
                           min="1" inputmode="numeric">
                </div>
            </div>
        </div>

        <!-- Imagen (URL) -->
        <div class="venta-form rounded-4 p-3 shadow-sm mb-4">
            <p class="section-title mb-2">
                <i class="bi bi-image me-1 text-warning"></i>Imagen <span class="text-muted">(opcional)</span>
            </p>
            <input type="url" id="imagen_url" name="imagen_url"
                   class="form-control form-control-touch mb-2"
                   placeholder="https://..."
                   value="<?= htmlspecialchars($_POST['imagen_url'] ?? '') ?>"
                   inputmode="url">
            <div id="preview-img" class="text-center d-none">
                <img id="img-prev" src="" alt="Preview"
                     class="rounded-3 shadow-sm"
                     style="max-height:160px;max-width:100%;object-fit:contain">
            </div>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn-venta mb-3">
            <i class="bi bi-check2-circle me-2 fs-5"></i>Crear artículo
        </button>

    </form>
</main>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Preview total financiado
const inpMontoCuota = document.getElementById('monto_cuota');
const selCuotas     = document.getElementById('cuotas');
const previewCuota  = document.getElementById('preview-cuota');
const valTotalFin   = document.getElementById('val-total-financiado');

function actualizarCuotaPreview() {
    const m = parseFloat(inpMontoCuota.value) || 0;
    const c = parseInt(selCuotas.value) || 1;
    if (m > 0) {
        const total = m * c;
        valTotalFin.textContent = '$' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        previewCuota.classList.remove('d-none');
    } else {
        previewCuota.classList.add('d-none');
    }
}
inpMontoCuota.addEventListener('input', actualizarCuotaPreview);
selCuotas.addEventListener('input', actualizarCuotaPreview);

// Preview de imagen
document.getElementById('imagen_url').addEventListener('input', function() {
    const url = this.value.trim();
    const wrap = document.getElementById('preview-img');
    const img  = document.getElementById('img-prev');
    if (url.startsWith('http')) {
        img.src = url;
        img.onload  = () => wrap.classList.remove('d-none');
        img.onerror = () => wrap.classList.add('d-none');
    } else {
        wrap.classList.add('d-none');
    }
});
</script>
</body>
</html>
