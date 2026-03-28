<?php
// ============================================================
// api/articulos.php — JSON API para artículos (lazy loading)
// ============================================================
ini_set('display_errors', 0);   // Errors go to log, not response body
ob_start();                      // Buffer any stray output
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

// Para llamadas AJAX devolver JSON en vez de redirect HTML
if (!isLoggedIn()) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['error' => 'Sesión expirada. Recargá la página.']);
    exit;
}

ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$pdo     = getPDO();
$page    = max(1, (int)($_GET['page']    ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 12)));
$offset  = ($page - 1) * $perPage;

$q           = trim($_GET['q']            ?? '');
$categoriaId = (int)($_GET['categoria_id'] ?? 0);
$stockBajo   = !empty($_GET['stock_bajo']);
$soloId      = (int)($_GET['id']          ?? 0);
$count       = !empty($_GET['count']);

// ── Consulta de un artículo específico ──────────────────────
if ($soloId) {
    $stmt = $pdo->prepare(
        'SELECT a.id, a.nombre, a.precio_contado, a.precio_financiado,
                a.cuotas, a.monto_cuota, a.stock_actual, a.imagen_url,
                c.icono
           FROM articulos a
           JOIN categorias c ON c.id = a.categoria_id
          WHERE a.id = ? AND a.activo = 1'
    );
    $stmt->execute([$soloId]);
    $art = $stmt->fetch();
    if (!$art) {
        http_response_code(404);
        echo json_encode(['error' => 'Artículo no encontrado']);
        exit;
    }
    echo json_encode(formatArticulo($art));
    exit;
}

// ── Construir WHERE dinámico ────────────────────────────────
$conditions = ['a.activo = 1'];
$params     = [];

if ($q) {
    $conditions[] = '(a.nombre LIKE ? OR a.descripcion LIKE ? OR a.codigo LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($categoriaId) {
    $conditions[] = 'a.categoria_id = ?';
    $params[]     = $categoriaId;
}
if ($stockBajo) {
    $conditions[] = 'a.stock_actual <= a.stock_minimo';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

// ── Solo conteo ─────────────────────────────────────────────
if ($count) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM articulos a $where");
    $stmt->execute($params);
    echo json_encode(['total' => (int)$stmt->fetchColumn()]);
    exit;
}

try {
    // ── Total para paginación ─────────────────────────────────
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM articulos a $where");
    $stmtTotal->execute($params);
    $total = (int)$stmtTotal->fetchColumn();

    // ── Artículos paginados ───────────────────────────────────
    $sql = "SELECT a.id, a.nombre, a.precio_contado, a.precio_financiado,
                   a.cuotas, a.monto_cuota, a.stock_actual, a.stock_minimo,
                   a.imagen_url, c.icono
              FROM articulos a
              JOIN categorias c ON c.id = a.categoria_id
             $where
             ORDER BY a.nombre ASC
             LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($sql);
    // Bind filter params positionally (1-based)
    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    // Bind LIMIT/OFFSET explicitly as integers (EMULATE_PREPARES=false requires this)
    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset,  PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    echo json_encode([
        'items'    => array_map('formatArticulo', $items),
        'total'    => $total,
        'page'     => $page,
        'per_page' => $perPage,
        'has_more' => ($offset + count($items)) < $total,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}

// ── Helper de formato ───────────────────────────────────────
function formatArticulo(array $a): array {
    return [
        'id'                    => (int)$a['id'],
        'nombre'                => $a['nombre'],
        'precio_contado'        => (float)$a['precio_contado'],
        'precio_contado_fmt'    => formatPesos((float)$a['precio_contado']),
        'precio_financiado'     => (float)$a['precio_financiado'],
        'precio_financiado_fmt' => formatPesos((float)$a['precio_financiado']),
        'cuotas'                => (int)$a['cuotas'],
        'monto_cuota'           => (float)($a['monto_cuota'] ?? 0),
        'monto_cuota_fmt'       => formatPesos((float)($a['monto_cuota'] ?? 0)),
        'stock_actual'          => (int)$a['stock_actual'],
        'stock_minimo'          => (int)($a['stock_minimo'] ?? 1),
        'imagen_url'            => $a['imagen_url'],
        'icono'                 => $a['icono'] ?? 'bi-box',
    ];
}
