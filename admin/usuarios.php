<?php
// ============================================================
// admin/usuarios.php — Gestión de usuarios (solo Admin)
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

$pdo       = getPDO();
$pageTitle = 'Usuarios';
$errors    = [];
$success   = '';

// ── Acciones POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token inválido.';
    } else {
        $accion = $_POST['accion'] ?? '';

        // ── Crear usuario ────────────────────────────────────
        if ($accion === 'crear') {
            $usuario  = trim($_POST['usuario']  ?? '');
            $nombre   = trim($_POST['nombre']   ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $rol      = $_POST['rol']    ?? 'vendedor';
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['confirm']  ?? '';

            if (!$usuario)                         $errors[] = 'El usuario es obligatorio.';
            if (!preg_match('/^\w{3,60}$/', $usuario)) $errors[] = 'Usuario: solo letras, números y _ (mín. 3 caracteres).';
            if (!$nombre)                          $errors[] = 'El nombre es obligatorio.';
            if (!$apellido)                        $errors[] = 'El apellido es obligatorio.';
            if (strlen($password) < 6)             $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
            if ($password !== $confirm)            $errors[] = 'Las contraseñas no coinciden.';
            if (!in_array($rol, ['admin','vendedor'])) $errors[] = 'Rol inválido.';

            if (empty($errors)) {
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare(
                        'INSERT INTO usuarios (usuario, nombre, apellido, clave, rol) VALUES (?, ?, ?, ?, ?)'
                    )->execute([$usuario, $nombre, $apellido, $hash, $rol]);
                    $success = "Usuario «{$usuario}» creado correctamente.";
                } catch (PDOException $e) {
                    $errors[] = 'El nombre de usuario ya existe.';
                }
            }

        // ── Cambiar contraseña ───────────────────────────────
        } elseif ($accion === 'cambiar_pass') {
            $uid      = (int)($_POST['uid'] ?? 0);
            $password = $_POST['new_password'] ?? '';
            $confirm  = $_POST['new_confirm']  ?? '';

            if (strlen($password) < 6) $errors[] = 'Mínimo 6 caracteres.';
            if ($password !== $confirm) $errors[] = 'Las contraseñas no coinciden.';

            if (empty($errors) && $uid) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE usuarios SET clave = ? WHERE id = ?')
                    ->execute([$hash, $uid]);
                $success = 'Contraseña actualizada.';
            }

        // ── Cambiar rol ──────────────────────────────────────
        } elseif ($accion === 'cambiar_rol') {
            $uid     = (int)($_POST['uid'] ?? 0);
            $nuevoRol = $_POST['nuevo_rol'] ?? '';

            // Proteger: no degradar al único admin
            if ($nuevoRol === 'vendedor') {
                $admins = (int)$pdo->query(
                    'SELECT COUNT(*) FROM usuarios WHERE rol = "admin" AND activo = 1'
                )->fetchColumn();
                if ($admins <= 1) {
                    $errors[] = 'Debe existir al menos un administrador.';
                }
            }

            if (empty($errors) && $uid && in_array($nuevoRol, ['admin','vendedor'])) {
                $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')
                    ->execute([$nuevoRol, $uid]);
                $success = 'Rol actualizado.';
            }

        // ── Activar / Desactivar ─────────────────────────────
        } elseif ($accion === 'toggle_activo') {
            $uid     = (int)($_POST['uid'] ?? 0);
            $activo  = (int)($_POST['activo'] ?? 0);
            $nuevoEstado = $activo ? 0 : 1;

            // No desactivar al propio usuario
            if ($uid === currentUser()['id']) {
                $errors[] = 'No podés desactivar tu propio usuario.';
            } else {
                $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')
                    ->execute([$nuevoEstado, $uid]);
                $success = $nuevoEstado ? 'Usuario activado.' : 'Usuario desactivado.';
            }
        }
    }

    if ($success) setFlash('success', $success);
    if ($errors)  setFlash('danger', implode(' | ', $errors));
    header('Location: ' . APP_URL . '/admin/usuarios.php');
    exit;
}

// ── Listar usuarios ─────────────────────────────────────────
$usuarios = $pdo->query(
    'SELECT id, usuario, nombre, apellido, rol, activo, ultimo_login, created_at
       FROM usuarios
      ORDER BY activo DESC, rol, apellido, nombre'
)->fetchAll();

$csrfToken = csrfToken();
?>
<?php require_once __DIR__ . '/../includes/head.php'; ?>
<?php require_once __DIR__ . '/../includes/topbar.php'; ?>

<main class="container-fluid px-3 pt-3 pb-2">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="fs-5 fw-bold mb-0">Usuarios</h1>
        <button class="btn btn-warning btn-sm fw-medium rounded-3 px-3"
                data-bs-toggle="modal" data-bs-target="#modal-nuevo">
            <i class="bi bi-person-plus me-1"></i>Nuevo
        </button>
    </div>

    <!-- Lista de usuarios -->
    <div class="d-flex flex-column gap-2 mb-4">
        <?php foreach ($usuarios as $u): ?>
        <?php $esMismo = $u['id'] === currentUser()['id']; ?>
        <div class="card border-0 shadow-sm rounded-3
                    <?= !$u['activo'] ? 'opacity-50' : '' ?>">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2">
                    <!-- Avatar -->
                    <div class="rounded-circle d-flex align-items-center justify-content-center
                                flex-shrink-0 fw-bold text-white"
                         style="width:42px;height:42px;font-size:1rem;
                                background:<?= $u['rol'] === 'admin' ? '#1a1f36' : '#6c757d' ?>">
                        <?= mb_strtoupper(mb_substr($u['nombre'], 0, 1)) ?>
                    </div>
                    <!-- Info -->
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-center gap-1 flex-wrap">
                            <span class="fw-medium small">
                                <?= htmlspecialchars($u['apellido'] . ', ' . $u['nombre']) ?>
                                <?= $esMismo ? '<span class="text-warning small">(vos)</span>' : '' ?>
                            </span>
                            <span class="badge <?= $u['rol'] === 'admin' ? 'bg-dark' : 'bg-secondary' ?>
                                             ms-1" style="font-size:.65rem">
                                <?= ucfirst($u['rol']) ?>
                            </span>
                            <?php if (!$u['activo']): ?>
                            <span class="badge bg-danger" style="font-size:.65rem">Inactivo</span>
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 text-muted" style="font-size:.72rem">
                            <i class="bi bi-at me-1"></i><?= htmlspecialchars($u['usuario']) ?>
                        </p>
                        <p class="mb-0 text-muted" style="font-size:.68rem">
                            Último ingreso:
                            <?= $u['ultimo_login']
                                ? date('d/m/Y H:i', strtotime($u['ultimo_login']))
                                : 'Nunca' ?>
                        </p>
                    </div>
                    <!-- Menú acciones -->
                    <div class="dropdown flex-shrink-0">
                        <button class="btn btn-sm btn-light rounded-circle p-2"
                                data-bs-toggle="dropdown"
                                style="width:34px;height:34px">
                            <i class="bi bi-three-dots-vertical" style="font-size:.8rem"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <!-- Cambiar contraseña -->
                            <li>
                                <button class="dropdown-item small"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modal-pass"
                                        data-uid="<?= $u['id'] ?>"
                                        data-nombre="<?= htmlspecialchars($u['nombre']) ?>">
                                    <i class="bi bi-key me-2"></i>Cambiar contraseña
                                </button>
                            </li>
                            <!-- Cambiar rol -->
                            <?php if (!$esMismo): ?>
                            <li>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="accion" value="cambiar_rol">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="nuevo_rol"
                                           value="<?= $u['rol'] === 'admin' ? 'vendedor' : 'admin' ?>">
                                    <button type="submit" class="dropdown-item small">
                                        <i class="bi bi-arrow-left-right me-2"></i>
                                        Hacer <?= $u['rol'] === 'admin' ? 'Vendedor' : 'Admin' ?>
                                    </button>
                                </form>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <!-- Activar / Desactivar -->
                            <li>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="accion" value="toggle_activo">
                                    <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= $u['activo'] ?>">
                                    <button type="submit"
                                            class="dropdown-item small text-<?= $u['activo'] ? 'danger' : 'success' ?>">
                                        <i class="bi bi-<?= $u['activo'] ? 'person-x' : 'person-check' ?> me-2"></i>
                                        <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- ── MODAL: Nuevo usuario ─────────────────────────────────── -->
<div class="modal fade" id="modal-nuevo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-5 fw-bold">Nuevo usuario</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="accion" value="crear">

                    <div class="mb-3">
                        <label class="form-label small fw-medium mb-1">Usuario *</label>
                        <input type="text" name="usuario"
                               class="form-control form-control-touch"
                               placeholder="ej: jgarcia" required autocomplete="off"
                               autocapitalize="none" pattern="\w{3,60}"
                               title="Solo letras, números y guión bajo. Mínimo 3 caracteres.">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium mb-1">Nombre *</label>
                            <input type="text" name="nombre"
                                   class="form-control form-control-touch"
                                   placeholder="Juan" required autocomplete="off">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium mb-1">Apellido *</label>
                            <input type="text" name="apellido"
                                   class="form-control form-control-touch"
                                   placeholder="García" required autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium mb-1">Rol *</label>
                        <select name="rol" class="form-select form-select-touch">
                            <option value="vendedor">Vendedor</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium mb-1">Contraseña *</label>
                        <input type="password" name="password"
                               class="form-control form-control-touch"
                               placeholder="Mínimo 6 caracteres"
                               autocomplete="new-password" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-medium mb-1">Confirmar contraseña *</label>
                        <input type="password" name="confirm"
                               class="form-control form-control-touch"
                               placeholder="Repetí la contraseña"
                               autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn-venta">
                        <i class="bi bi-person-plus me-2"></i>Crear usuario
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ── MODAL: Cambiar contraseña ────────────────────────────── -->
<div class="modal fade" id="modal-pass" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-5 fw-bold">Cambiar contraseña</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p id="modal-pass-nombre" class="text-muted small mb-3"></p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="accion" value="cambiar_pass">
                    <input type="hidden" name="uid" id="pass-uid">
                    <div class="mb-3">
                        <label class="form-label small fw-medium mb-1">Nueva contraseña *</label>
                        <input type="password" name="new_password"
                               class="form-control form-control-touch"
                               placeholder="Mínimo 6 caracteres"
                               autocomplete="new-password" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-medium mb-1">Confirmar *</label>
                        <input type="password" name="new_confirm"
                               class="form-control form-control-touch"
                               autocomplete="new-password" required>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 rounded-3 py-2 fw-bold">
                        <i class="bi bi-key me-2"></i>Actualizar contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/bottom_nav.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Poblar modal de cambio de contraseña
document.getElementById('modal-pass').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('pass-uid').value = btn.dataset.uid;
    document.getElementById('modal-pass-nombre').textContent = 'Usuario: ' + btn.dataset.nombre;
});
</script>
</body>
</html>
