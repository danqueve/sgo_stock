<?php
$user    = currentUser();
$flash   = getFlash();
$pageTitle = $pageTitle ?? APP_NAME;
?>

<!-- ======================================================
     TOP BAR — Mobile header + Desktop sidebar toggle
     ====================================================== -->
<header class="app-topbar sticky-top">
    <div class="container-fluid px-3">
        <div class="d-flex align-items-center justify-content-between" style="min-height:56px">

            <!-- Logo / App name -->
            <a href="<?= APP_URL ?>/index.php" class="app-brand text-decoration-none">
                <i class="bi bi-shop-window me-2"></i>
                <span class="fw-bold"><?= APP_NAME ?></span>
            </a>

            <!-- Page title (mobile) -->
            <span class="app-topbar__title d-md-none small">
                <?= htmlspecialchars($pageTitle) ?>
            </span>

            <!-- Desktop user menu -->
            <div class="d-none d-md-flex align-items-center gap-3">
                <span class="text-muted small">
                    <i class="bi bi-person-fill me-1"></i>
                    <?= htmlspecialchars($user['nombre']) ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-1">
                        <?= ucfirst($user['rol']) ?>
                    </span>
                </span>
                <a href="<?= APP_URL ?>/auth/logout.php"
                   class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Flash messages -->
<?php if ($flash): ?>
<div class="container-fluid px-3 pt-2">
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show
                py-2 small shadow-sm" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($flash['msg']) ?>
        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
