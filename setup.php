<?php
// ============================================================
// setup.php — Diagnóstico y reset de usuario admin
// ¡ELIMINAR ESTE ARCHIVO DESPUÉS DE USAR!
// ============================================================
require_once __DIR__ . '/config/db.php';

$msg    = '';
$error  = '';
$pasos  = [];

// ── 1. Test conexión ─────────────────────────────────────────
try {
    $pdo = getPDO();
    $pasos[] = ['ok', 'Conexión a la base de datos: OK'];
} catch (Exception $e) {
    die('<h2 style="color:red">❌ Sin conexión: ' . htmlspecialchars($e->getMessage()) . '</h2>');
}

// ── 2. Verificar si existe la tabla usuarios ──────────────────
try {
    $cols = $pdo->query('SHOW COLUMNS FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
    $pasos[] = ['ok', 'Tabla `usuarios` encontrada. Columnas: ' . implode(', ', $cols)];

    $tieneUsuario = in_array('usuario', $cols);
    $tieneClave   = in_array('clave',   $cols);
    $tieneEmail   = in_array('email',   $cols);

    if (!$tieneUsuario) $pasos[] = ['warn', 'Falta columna `usuario` — el schema no fue reimportado'];
    if (!$tieneClave)   $pasos[] = ['warn', 'Falta columna `clave`   — el schema no fue reimportado'];
    if ($tieneEmail)    $pasos[] = ['warn', 'Columna `email` todavía existe — schema viejo en uso'];

} catch (Exception $e) {
    $pasos[] = ['err', 'Tabla `usuarios` NO existe: ' . $e->getMessage()];
    $tieneUsuario = false;
    $tieneClave   = false;
}

// ── 3. Mostrar usuarios actuales ─────────────────────────────
try {
    $rows = $pdo->query('SELECT * FROM usuarios')->fetchAll();
    $pasos[] = ['ok', count($rows) . ' usuario(s) en la tabla'];
} catch (Exception $e) {
    $rows = [];
}

// ── 4. Acción: crear/actualizar admin ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    $nuevaClave = trim($_POST['nueva_clave'] ?? '');
    $nuevoUser  = trim($_POST['nuevo_usuario'] ?? 'admin');

    if (strlen($nuevaClave) < 4) {
        $error = 'La clave debe tener al menos 4 caracteres.';
    } else {
        $hash = password_hash($nuevaClave, PASSWORD_BCRYPT, ['cost' => 10]);

        if ($tieneUsuario && $tieneClave) {
            // Schema nuevo
            $existe = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ?');
            $existe->execute([$nuevoUser]);

            if ($existe->fetch()) {
                $pdo->prepare('UPDATE usuarios SET clave = ?, activo = 1 WHERE usuario = ?')
                    ->execute([$hash, $nuevoUser]);
                $msg = "✅ Clave actualizada para usuario «{$nuevoUser}».";
            } else {
                $pdo->prepare(
                    "INSERT INTO usuarios (usuario, nombre, apellido, clave, rol, activo)
                     VALUES (?, 'Admin', 'Imperio', ?, 'admin', 1)"
                )->execute([$nuevoUser, $hash]);
                $msg = "✅ Usuario «{$nuevoUser}» creado como admin.";
            }
        } elseif ($tieneEmail) {
            // Schema viejo (aún tiene email/password_hash)
            $email = $nuevoUser . '@imperio.com';
            $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
            $existe->execute([$email]);

            if ($existe->fetch()) {
                $pdo->prepare('UPDATE usuarios SET password_hash = ?, activo = 1 WHERE email = ?')
                    ->execute([$hash, $email]);
                $msg = "✅ (schema viejo) Clave actualizada para email «{$email}».";
            } else {
                $pdo->prepare(
                    "INSERT INTO usuarios (nombre, email, password_hash, rol, activo)
                     VALUES ('Admin', ?, ?, 'admin', 1)"
                )->execute([$email, $hash]);
                $msg = "✅ (schema viejo) Admin creado con email «{$email}».";
            }
        } else {
            $error = 'No se puede determinar el esquema de la tabla.';
        }

        // Recargar usuarios
        $rows = $pdo->query('SELECT * FROM usuarios')->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Setup — Imperio Comercial</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background:#f0f2f5; }
        pre  { font-size:.8rem; background:#1a1f36; color:#f8f9fa;
               padding:1rem; border-radius:8px; overflow-x:auto; }
    </style>
</head>
<body class="p-3">
<div class="container" style="max-width:720px">

    <div class="alert alert-danger border-0 mb-3">
        <strong>⚠️ Archivo de diagnóstico temporal.</strong>
        Eliminalo del servidor una vez resuelto el problema.
    </div>

    <h1 class="fs-4 fw-bold mb-3">Diagnóstico — Imperio Comercial</h1>

    <!-- Diagnóstico -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <h2 class="fs-6 fw-bold mb-3">Resultados</h2>
            <?php foreach ($pasos as [$tipo, $txt]): ?>
            <div class="d-flex align-items-start gap-2 mb-1 small">
                <span><?= $tipo === 'ok' ? '✅' : ($tipo === 'warn' ? '⚠️' : '❌') ?></span>
                <span><?= htmlspecialchars($txt) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Usuarios actuales -->
    <?php if ($rows): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <h2 class="fs-6 fw-bold mb-2">Usuarios en la tabla</h2>
            <div class="table-responsive">
                <table class="table table-sm small mb-0">
                    <thead><tr>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                        <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $k => $v): ?>
                            <td><?= $k === 'clave' || $k === 'password_hash'
                                    ? '<em class="text-muted">[hash]</em>'
                                    : htmlspecialchars((string)$v) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Reset de clave -->
    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <h2 class="fs-6 fw-bold mb-3">Crear / resetear usuario admin</h2>

            <?php if ($msg): ?>
            <div class="alert alert-success py-2 small mb-3"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small mb-3"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="reset" value="1">
                <div class="mb-3">
                    <label class="form-label small fw-medium mb-1">Usuario</label>
                    <input type="text" name="nuevo_usuario"
                           class="form-control" value="admin" autocapitalize="none">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-medium mb-1">Nueva clave</label>
                    <input type="text" name="nueva_clave"
                           class="form-control" placeholder="Escribí la clave deseada" required>
                    <div class="form-text">Se guardará como hash bcrypt.</div>
                </div>
                <button type="submit" class="btn btn-warning fw-bold px-4">
                    Crear / Actualizar admin
                </button>
            </form>
        </div>
    </div>

    <p class="text-muted small">
        Una vez que puedas ingresar, eliminá este archivo:
        <code>c:/wamp64/www/sgo/setup.php</code>
    </p>
</div>
</body>
</html>
