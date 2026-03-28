<?php
// Bottom Navigation — visible en todas las resoluciones
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentPath = $_SERVER['PHP_SELF'];
?>

<!-- ======================================================
     BOTTOM NAVIGATION BAR
     Fijada al fondo, respeta safe-area-inset-bottom (notch)
     ====================================================== -->
<nav class="bottom-nav" role="navigation" aria-label="Navegación principal">
    <a href="<?= APP_URL ?>/index.php"
       class="bottom-nav__item <?= in_array($currentPage, ['index','dashboard']) && strpos($currentPath, 'stock') === false ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i>
        <span>Inicio</span>
    </a>

    <a href="<?= APP_URL ?>/ventas/nueva.php"
       class="bottom-nav__item bottom-nav__item--cta"
       aria-label="Nueva venta">
        <div class="bottom-nav__cta-btn">
            <i class="bi bi-plus-lg"></i>
        </div>
        <span>Vender</span>
    </a>

    <a href="<?= APP_URL ?>/stock/index.php"
       class="bottom-nav__item <?= strpos($currentPath, 'stock') !== false ? 'active' : '' ?>">
        <i class="bi bi-boxes"></i>
        <span>Stock</span>
    </a>

    <?php if ($user['rol'] === 'admin'): ?>
    <a href="<?= APP_URL ?>/admin/usuarios.php"
       class="bottom-nav__item <?= strpos($currentPath, 'admin/usuarios') !== false ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i>
        <span>Usuarios</span>
    </a>

    <a href="<?= APP_URL ?>/admin/categorias.php"
       class="bottom-nav__item <?= strpos($currentPath, 'admin/categorias') !== false ? 'active' : '' ?>">
        <i class="bi bi-tags-fill"></i>
        <span>Categorías</span>
    </a>

    <a href="<?= APP_URL ?>/reportes/index.php"
       class="bottom-nav__item <?= strpos($currentPath, 'reportes') !== false ? 'active' : '' ?>">
        <i class="bi bi-bar-chart-line"></i>
        <span>Reportes</span>
    </a>
    <?php else: ?>
    <a href="<?= APP_URL ?>/clientes/index.php"
       class="bottom-nav__item <?= strpos($currentPath, 'clientes') !== false ? 'active' : '' ?>">
        <i class="bi bi-people"></i>
        <span>Clientes</span>
    </a>
    <?php endif; ?>
</nav>

<!-- Spacer para que el contenido no quede detrás del bottom nav -->
<div class="bottom-nav-spacer"></div>
