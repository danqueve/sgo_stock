<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';

// Redirigir si ya está logueado
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario']  ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario && $password) {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, apellido, rol, clave, activo FROM usuarios WHERE usuario = ? LIMIT 1'
        );
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && $user['activo'] && password_verify($password, $user['clave'])) {
            // Regenerar session ID (previene session fixation)
            session_regenerate_id(true);

            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_usuario']  = $usuario;
            $_SESSION['user_nombre']   = $user['nombre'];
            $_SESSION['user_apellido'] = $user['apellido'];
            $_SESSION['user_rol']      = $user['rol'];

            // Actualizar último login
            $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?')
                ->execute([$user['id']]);

            header('Location: ' . APP_URL . '/index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Completá todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f5f5f7">
    <title>Ingresar — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
</head>
<body>

<div class="login-page">

    <!-- Logo Apple Style -->
    <div class="text-center mb-5 element-animate">
        <div class="mb-3">
            <div class="d-inline-flex align-items-center justify-content-center bg-white rounded-circle shadow-sm"
                 style="width:80px; height:80px; border:1px solid rgba(0,0,0,0.05)">
                <i class="bi bi-shield-lock-fill text-accent" style="font-size:2.4rem"></i>
            </div>
        </div>
        <h1 class="fw-bold fs-4 mb-0 text-dark" style="letter-spacing:-0.02em"><?= APP_NAME ?></h1>
        <p class="text-muted small">Acceso Seguro</p>
    </div>

    <!-- Card de login -->
    <div class="login-card element-animate">
        <h2 class="fs-5 fw-bold mb-4 text-center" style="letter-spacing:-0.01em">Ingresar</h2>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible py-2 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <!-- Grupo de Entradas iOS -->
            <div class="ios-input-group">
                <div class="ios-input-item">
                    <input
                        type="text"
                        id="usuario"
                        name="usuario"
                        class="form-control"
                        placeholder="Usuario"
                        value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >
                </div>
                <div class="ios-input-item">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Contraseña"
                        autocomplete="current-password"
                        required
                    >
                </div>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-iphone mb-3">
                Continuar
            </button>
        </form>

        <!-- Accesos de demo -->
        <hr class="my-4 opacity-10">
        <p class="text-muted small text-center mb-3">Accesos de prueba</p>
        <div class="row g-2">
            <div class="col-6">
                <button class="btn btn-light w-100 py-2 border small"
                        onclick="fillDemo('admin')">
                    <i class="bi bi-shield-check me-1"></i>Admin
                </button>
            </div>
            <div class="col-6">
                <button class="btn btn-light w-100 py-2 border small"
                        onclick="fillDemo('vendedor')">
                    <i class="bi bi-person-badge me-1"></i>Vendedor
                </button>
            </div>
        </div>
    </div>

    <!-- PWA install button -->
    <button id="btn-instalar-pwa" class="btn btn-outline-dark btn-sm mt-4 d-none">
        <i class="bi bi-download me-2"></i>Instalar App
    </button>

    <p class="text-muted small mt-4">v<?= APP_VERSION ?></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function togglePassword() {
    const inp  = document.getElementById('password');
    const icon = document.getElementById('icon-eye');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function fillDemo(usuario) {
    document.getElementById('usuario').value  = usuario;
    document.getElementById('password').value = 'password';
}

// Animación de entrada estilo iOS
document.addEventListener('DOMContentLoaded', () => {
    anime({
        targets: '.element-animate',
        translateY: [30, 0],
        opacity: [0, 1],
        delay: anime.stagger(150),
        duration: 800,
        easing: 'easeOutElastic(1, .8)'
    });
});
</script>
</body>
</html>
