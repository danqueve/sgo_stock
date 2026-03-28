<?php
// ============================================================
// api/articulos_admin.php — Acciones administrativas de artículos
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

// Solo administradores pueden usar este endpoint
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/stock/index.php');
    exit;
}

// Verificar CSRF
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Error de seguridad (CSRF inválido).');
    header('Location: ' . APP_URL . '/stock/index.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$pdo    = getPDO();

if ($id <= 0) {
    setFlash('danger', 'ID de artículo inválido.');
    header('Location: ' . APP_URL . '/stock/index.php');
    exit;
}

switch ($accion) {
    case 'desactivar':
        try {
            // Soft delete: marcar como inactivo
            $stmt = $pdo->prepare('UPDATE articulos SET activo = 0 WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                setFlash('success', 'El artículo ha sido dado de baja correctamente.');
            } else {
                setFlash('info', 'El artículo no existía o ya estaba dado de baja.');
            }
        } catch (PDOException $e) {
            setFlash('danger', 'Error al dar de baja el artículo: ' . $e->getMessage());
        }
        break;

    default:
        setFlash('warning', 'Acción no reconocida.');
        break;
}

header('Location: ' . APP_URL . '/stock/index.php');
exit;
