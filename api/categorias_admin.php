<?php
// ============================================================
// api/categorias_admin.php — Acciones de categorías (solo Admin)
// ============================================================
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/categorias.php');
    exit;
}

// Verificar CSRF
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    setFlash('danger', 'Error de seguridad (CSRF inválido).');
    header('Location: ' . APP_URL . '/admin/categorias.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$pdo    = getPDO();

switch ($accion) {
    case 'crear':
        $nombre = trim($_POST['nombre'] ?? '');
        $icono  = trim($_POST['icono'] ?? 'bi-box');

        if (!$nombre) {
            setFlash('danger', 'El nombre de la categoría es obligatorio.');
            header('Location: ' . APP_URL . '/admin/categorias.php');
            exit;
        }

        try {
            $pdo->prepare('INSERT INTO categorias (nombre, icono) VALUES (?, ?)')
                ->execute([$nombre, $icono]);
            setFlash('success', "Categoría «{$nombre}» creada correctamente.");
        } catch (PDOException $e) {
            setFlash('danger', 'Error al crear la categoría.');
        }
        break;

    case 'toggle_activo':
        $id = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        $nuevoEstado = $activo ? 0 : 1;

        if ($id > 0) {
            $pdo->prepare('UPDATE categorias SET activo = ? WHERE id = ?')
                ->execute([$nuevoEstado, $id]);
            setFlash('success', "Estado de la categoría actualizado.");
        }
        break;

    default:
        setFlash('warning', 'Acción no reconocida.');
        break;
}

header('Location: ' . APP_URL . '/admin/categorias.php');
exit;
