<?php
// ============================================================
// api/stock_ajuste.php — Ajuste rápido de stock (AJAX/Admin)
// ============================================================
ini_set('display_errors', 0);
ob_start();
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn() || currentUser()['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de seguridad inválido']);
    exit;
}

$id       = (int)($_POST['id']          ?? 0);
$tipo     = $_POST['tipo_ajuste']       ?? '';
$cantidad = max(1, (int)($_POST['cant_ajuste'] ?? 1));
$motivo   = trim($_POST['motivo']       ?? '') ?: 'Ajuste manual';

if (!$id || !in_array($tipo, ['entrada', 'salida', 'ajuste'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

try {
    $pdo = getPDO();

    $stmt = $pdo->prepare(
        'SELECT stock_actual, stock_minimo FROM articulos WHERE id = ? AND activo = 1'
    );
    $stmt->execute([$id]);
    $art = $stmt->fetch();

    if (!$art) {
        http_response_code(404);
        echo json_encode(['error' => 'Artículo no encontrado']);
        exit;
    }

    $stockAntes = (int)$art['stock_actual'];
    $stockMin   = (int)$art['stock_minimo'];
    $stockNuevo = $tipo === 'entrada'
        ? $stockAntes + $cantidad
        : max(0, $stockAntes - $cantidad);

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE articulos SET stock_actual = ? WHERE id = ?')
        ->execute([$stockNuevo, $id]);
    $pdo->prepare(
        'INSERT INTO stock_movimientos
            (articulo_id, usuario_id, tipo, cantidad, stock_antes, stock_despues, referencia)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([$id, currentUser()['id'], $tipo, $cantidad,
                $stockAntes, $stockNuevo, $motivo]);
    $pdo->commit();

    echo json_encode([
        'ok'          => true,
        'stock_nuevo' => $stockNuevo,
        'stock_min'   => $stockMin,
    ]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
