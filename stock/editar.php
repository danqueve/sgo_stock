<?php
// ============================================================
// stock/editar.php — Editar artículo + ajuste de stock manual
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Editar Artículo';
$id        = (int)($_GET['id'] ?? 0);
$errors    = [];

if (!$id) {
    header('Location: ' . APP_URL . '/stock/index.php');
    exit;
}

// Cargar artículo
$stmtA = $pdo->prepare(
    'SELECT * FROM articulos WHERE id = ? AND activo = 1'
);
$stmtA->execute([$id]);
$art = $stmtA->fetch();

if (!$art) {
    setFlash('danger', 'Artículo no encontrado.');
    header('Location: ' . APP_URL . '/stock/index.php');
    exit;
}

$categorias = $pdo->query(
    'SELECT id, nombre FROM categorias WHERE activo = 1 ORDER BY nombre'
)->fetchAll();

// Últimos 5 movimientos de stock de este artículo
$movimientos = $pdo->prepare(
    'SELECT m.tipo, m.cantidad, m.stock_antes, m.stock_despues,
            m.referencia, m.created_at, u.nombre AS usuario
       FROM stock_movimientos m
       JOIN usuarios u ON u.id = m.usuario_id
      WHERE m.articulo_id = ?
      ORDER BY m.created_at DESC
      LIMIT 5'
);
$movimientos->execute([$id]);
$movs = $movimientos->fetchAll();

// ── POST: Guardar cambios ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token inválido.';
    } elseif (isset($_POST['ajuste_stock'])) {
        // ── Ajuste de stock manual ───────────────────────────
        $tipoAjuste = $_POST['tipo_ajuste'] ?? 'entrada';
        $cantAjuste = max(1, (int)($_POST['cant_ajuste'] ?? 1));
        $motivo     = trim($_POST['motivo'] ?? 'Ajuste manual');

        $stockAntes = $art['stock_actual'];
        $stockNuevo = $tipoAjuste === 'entrada'
            ? $stockAntes + $cantAjuste
            : max(0, $stockAntes - $cantAjuste);

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE articulos SET stock_actual = ? WHERE id = ?')
            ->execute([$stockNuevo, $id]);
        $pdo->prepare(
            'INSERT INTO stock_movimientos
                (articulo_id, usuario_id, tipo, cantidad, stock_antes, stock_despues, referencia)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, currentUser()['id'], $tipoAjuste, $cantAjuste,
                    $stockAntes, $stockNuevo, $motivo]);
        $pdo->commit();

        setFlash('success', "Stock actualizado: {$stockAntes} → {$stockNuevo} unidades.");
        header('Location: ' . APP_URL . '/stock/editar.php?id=' . $id);
        exit;

    } else {
        // ── Editar datos del artículo ─────────────────────────
        $nombre   = trim($_POST['nombre']     ?? '');
        $desc     = trim($_POST['descripcion'] ?? '');
        $catId    = (int)($_POST['categoria_id'] ?? 0);
        $codigo   = trim($_POST['codigo']     ?? '') ?: null;
        $pC       = str_replace(['.', ','], ['', '.'], $_POST['precio_contado']   ?? '0');
        $pF       = str_replace(['.', ','], ['', '.'], $_POST['precio_financiado'] ?? '0');
        $cuotas   = max(1, (int)($_POST['cuotas']      ?? 1));
        $stockMin = max(1, (int)($_POST['stock_minimo'] ?? 1));
        $imgUrl   = trim($_POST['imagen_url'] ?? '') ?: null;

        if (!$nombre)   $errors[] = 'El nombre es obligatorio.';
        if (!$catId)    $errors[] = 'Seleccioná una categoría.';
        if ($pC <= 0)   $errors[] = 'Precio contado inválido.';
        if ($pF <= 0)   $errors[] = 'Precio financiado inválido.';

        if (empty($errors)) {
            try {
                $pdo->prepare(
                    'UPDATE articulos
                        SET categoria_id = ?, codigo = ?, nombre = ?, descripcion = ?,
                            precio_contado = ?, precio_financiado = ?, cuotas = ?,
                            stock_minimo = ?, imagen_url = ?
                      WHERE id = ?'
                )->execute([$catId, $codigo, $nombre, $desc,
                            $pC, $pF, $cuotas, $stockMin, $imgUrl, $id]);

                setFlash('success', "Artículo actualizado correctamente.");
                header('Location: ' . APP_URL . '/stock/editar.php?id=' . $id);
                exit;
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'El código ya existe en otro artículo.'
                    : 'Error al guardar.';
            }
        }
        // Recargar art con datos del POST para repoblar el form
        $art = array_merge($art, [
            'nombre'           => $nombre,
            'descripcion'      => $desc,
            'categoria_id'     => $catId,
            'codigo'           => $codigo,
            'precio_contado'   => $pC,
            'precio_financiado'=> $pF,
            'cuotas'           => $cuotas,
            'stock_minimo'     => $stockMin,
            'imagen_url'       => $imgUrl,
        ]);
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
            <h1 class="fs-5 fw-bold mb-0">Editar artículo</h1>
            <p class="text-muted small mb-0"><?= htmlspecialchars($art['nombre']) ?></p>
        </div>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger py-2 small mb-3">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- ── STOCK ACTUAL + AJUSTE RÁPIDO ─────────────────────── -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <p class="section-title mb-0">
                    <i class="bi bi-boxes me-1 text-warning"></i>Stock actual
                </p>
                <span class="badge fs-5 fw-bold px-3 py-2
                      <?= $art['stock_actual'] == 0 ? 'stock-empty' :
                         ($art['stock_actual'] <= $art['stock_minimo'] ? 'stock-low' : 'stock-ok') ?>">
                    <?= $art['stock_actual'] ?> uds
                </span>
            </div>

            <!-- Ajuste de stock -->
            <form method="POST" class="d-flex flex-column gap-2">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="ajuste_stock" value="1">

                <div class="row g-2">
                    <div class="col-4">
                        <select name="tipo_ajuste" class="form-select form-select-touch">
                            <option value="entrada">+ Entrada</option>
                            <option value="salida">− Salida</option>
                            <option value="ajuste">≈ Ajuste</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <input type="number" name="cant_ajuste"
                               class="form-control form-control-touch text-center fw-bold"
                               value="1" min="1" inputmode="numeric">
                    </div>
                    <div class="col-5">
                        <input type="text" name="motivo"
                               class="form-control form-control-touch"
                               placeholder="Motivo" value="Ajuste manual">
                    </div>
                </div>

                <button type="submit"
                        class="btn btn-outline-primary rounded-3 py-2 fw-medium">
                    <i class="bi bi-arrow-left-right me-2"></i>Aplicar ajuste
                </button>
            </form>

            <!-- Historial movimientos -->
            <?php if ($movs): ?>
            <hr class="my-3">
            <p class="section-title mb-2">Últimos movimientos</p>
            <?php foreach ($movs as $mov): ?>
            <?php
                $tipo = $mov['tipo'];
                $color = $tipo === 'entrada' ? 'success' : ($tipo === 'salida' ? 'danger' : 'warning');
                $icon  = $tipo === 'entrada' ? 'arrow-down-circle' : ($tipo === 'salida' ? 'arrow-up-circle' : 'arrow-left-right');
            ?>
            <div class="d-flex align-items-center gap-2 py-1 border-bottom border-light-subtle small">
                <i class="bi bi-<?= $icon ?> text-<?= $color ?>"></i>
                <div class="flex-grow-1">
                    <span class="fw-medium"><?= ucfirst($tipo) ?> ×<?= $mov['cantidad'] ?></span>
                    <span class="text-muted ms-1"><?= htmlspecialchars($mov['referencia']) ?></span>
                </div>
                <div class="text-end text-muted" style="font-size:.7rem">
                    <?= $mov['stock_antes'] ?> → <strong><?= $mov['stock_despues'] ?></strong><br>
                    <?= date('d/m H:i', strtotime($mov['created_at'])) ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── EDITAR DATOS ──────────────────────────────────────── -->
    <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

        <div class="venta-form rounded-4 p-3 shadow-sm mb-3">
            <p class="section-title mb-3">
                <i class="bi bi-pencil-fill me-1 text-warning"></i>Datos del artículo
            </p>

            <div class="mb-3">
                <label class="form-label small fw-medium mb-1">Nombre *</label>
                <input type="text" name="nombre"
                       class="form-control form-control-touch"
                       value="<?= htmlspecialchars($art['nombre']) ?>" required>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-7">
                    <label class="form-label small fw-medium mb-1">Categoría *</label>
                    <select name="categoria_id" class="form-select form-select-touch" required>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= $art['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-5">
                    <label class="form-label small fw-medium mb-1">Código</label>
                    <input type="text" name="codigo"
                           class="form-control form-control-touch"
                           value="<?= htmlspecialchars($art['codigo'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-medium mb-1">Descripción</label>
                <textarea name="descripcion" class="form-control form-control-touch"
                          rows="2" style="resize:none"
                ><?= htmlspecialchars($art['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-medium mb-1">$ Contado *</label>
                    <input type="number" name="precio_contado"
                           class="form-control form-control-touch"
                           value="<?= $art['precio_contado'] ?>"
                           min="0" step="0.01" inputmode="decimal" required>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-medium mb-1">$ Financiado *</label>
                    <input type="number" name="precio_financiado"
                           class="form-control form-control-touch"
                           value="<?= $art['precio_financiado'] ?>"
                           min="0" step="0.01" inputmode="decimal" required>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6">
                    <label class="form-label small fw-medium mb-1">Cuotas</label>
                    <select name="cuotas" class="form-select form-select-touch">
                        <?php foreach ([1,3,6,9,12,18,24] as $c): ?>
                        <option value="<?= $c ?>" <?= $art['cuotas'] == $c ? 'selected' : '' ?>>
                            <?= $c === 1 ? '1 pago' : "{$c} cuotas" ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-medium mb-1">Alerta stock ≤</label>
                    <input type="number" name="stock_minimo"
                           class="form-control form-control-touch text-center"
                           value="<?= $art['stock_minimo'] ?>"
                           min="1" inputmode="numeric">
                </div>
            </div>

            <div class="mb-1">
                <label class="form-label small fw-medium mb-1">URL imagen</label>
                <input type="url" name="imagen_url"
                       class="form-control form-control-touch"
                       value="<?= htmlspecialchars($art['imagen_url'] ?? '') ?>"
                       inputmode="url">
            </div>
        </div>

        <button type="submit" class="btn-venta mb-3">
            <i class="bi bi-check2-circle me-2 fs-5"></i>Guardar cambios
        </button>

        <!-- Desactivar artículo -->
        <button type="button"
                class="btn btn-outline-danger w-100 rounded-3 py-2 mb-4"
                data-bs-toggle="modal" data-bs-target="#modal-desactivar">
            <i class="bi bi-archive me-2"></i>Dar de baja artículo
        </button>
    </form>

    <!-- Modal baja -->
    <div class="modal fade" id="modal-desactivar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-body text-center pt-4 pb-3 px-4">
                    <i class="bi bi-archive-fill text-warning d-block mb-3"
                       style="font-size:2.5rem"></i>
                    <h2 class="fs-5 fw-bold mb-2">¿Dar de baja «<?= htmlspecialchars($art['nombre']) ?>»?</h2>
                    <p class="text-muted small mb-4">
                        El artículo no aparecerá en ventas ni en stock.
                        No se eliminan los datos históricos.
                    </p>
                    <form method="POST" action="<?= APP_URL ?>/api/articulos_admin.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="accion" value="desactivar">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary flex-fill rounded-3 py-2"
                                    data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-warning flex-fill rounded-3 py-2 fw-bold">
                                Dar de baja
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
