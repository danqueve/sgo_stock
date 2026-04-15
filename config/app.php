<?php
// ============================================================
// config/app.php — Configuración global de la aplicación
// ============================================================

define('APP_NAME',    'Imperio Comercial');
define('APP_VERSION', '1.0.0');

// Detectar entorno según el host (compatible PHP 7.4+)
$_appHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($_appHost === 'localhost' || $_appHost === '127.0.0.1') {
    define('APP_URL', 'http://localhost/sgo');
    define('APP_ENV', 'development');
} else {
    $_appScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('APP_URL', $_appScheme . '://' . $_appHost);
    define('APP_ENV', 'production');
}
unset($_appHost, $_appScheme);

// Zona horaria Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (APP_ENV === 'production') {
    ini_set('session.cookie_secure', 1);
}
session_start();

// Helpers de autenticación
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_rol'] !== 'admin') {
        header('Location: ' . APP_URL . '/index.php?err=forbidden');
        exit;
    }
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']       ?? null,
        'usuario'  => $_SESSION['user_usuario']  ?? '',
        'nombre'   => $_SESSION['user_nombre']   ?? '',
        'apellido' => $_SESSION['user_apellido'] ?? '',
        'rol'      => $_SESSION['user_rol']      ?? '',
    ];
}

// CSRF helpers
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// Formato moneda Argentina
function formatPesos(float $amount): string {
    return '$' . number_format($amount, 2, ',', '.');
}

/**
 * Formatea un número grande en formato corto (K, M)
 */
function formatShortNumber(float $amount): string {
    if ($amount >= 1000000) {
        return '$' . round($amount / 1000000, 1) . 'M';
    }
    if ($amount >= 1000) {
        return '$' . round($amount / 1000, 1) . 'K';
    }
    return formatPesos($amount);
}

// Flash messages
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Subida de imágenes de artículos ─────────────────────────
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/articulos/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/articulos/');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB

/**
 * Valida y guarda la imagen de un artículo subida vía $_FILES.
 * Usa getimagesize() para verificar el contenido real del archivo,
 * más confiable que finfo en distintos entornos de hosting.
 * Retorna la URL pública del archivo o false si falla.
 */
function subirImagenArticulo(array $file): string|false {
    $errores = [
        1 => 'UPLOAD_ERR_INI_SIZE',
        2 => 'UPLOAD_ERR_FORM_SIZE',
        3 => 'UPLOAD_ERR_PARTIAL',
        4 => 'UPLOAD_ERR_NO_FILE',
        6 => 'UPLOAD_ERR_NO_TMP_DIR',
        7 => 'UPLOAD_ERR_CANT_WRITE',
    ];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $desc = $errores[$file['error']] ?? 'DESCONOCIDO';
        error_log("[IMG_UPLOAD] FALLO paso 1 — error_code={$file['error']} ({$desc})");
        $GLOBALS['_upload_debug'] = "error_php={$file['error']}({$desc})";
        return false;
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        error_log("[IMG_UPLOAD] FALLO paso 2 — size={$file['size']} max=" . UPLOAD_MAX_BYTES);
        $GLOBALS['_upload_debug'] = "size={$file['size']} supera max=" . UPLOAD_MAX_BYTES;
        return false;
    }

    $tmp = $file['tmp_name'];
    $readable = is_readable($tmp);
    $info = $readable ? @getimagesize($tmp) : false;
    if (!$info) {
        error_log("[IMG_UPLOAD] FALLO paso 3 — tmp={$tmp} readable=" . ($readable ? 'si' : 'no') . " getimagesize=" . ($info ? 'ok' : 'false'));
        $GLOBALS['_upload_debug'] = "tmp={$tmp} readable=" . ($readable ? 'si' : 'no') . " getimagesize=false";
        return false;
    }

    $extMap = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($extMap[$info[2]])) {
        error_log("[IMG_UPLOAD] FALLO paso 4 — tipo_imagen={$info[2]} no permitido");
        $GLOBALS['_upload_debug'] = "tipo_imagen={$info[2]} no permitido";
        return false;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $filename = uniqid('art_', true) . '.' . $extMap[$info[2]];
    $destino  = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($tmp, $destino)) {
        error_log("[IMG_UPLOAD] FALLO paso 5 — move_uploaded_file destino={$destino} dir_existe=" . (is_dir(UPLOAD_DIR) ? 'si' : 'no') . " dir_writable=" . (is_writable(UPLOAD_DIR) ? 'si' : 'no'));
        $GLOBALS['_upload_debug'] = "move_failed destino={$destino} dir_writable=" . (is_writable(UPLOAD_DIR) ? 'si' : 'no');
        return false;
    }

    return UPLOAD_URL . $filename;
}

/**
 * Elimina del disco una imagen local de artículo (ignora URLs externas).
 */
function eliminarImagenArticulo(?string $url): void {
    if (!$url) return;
    if (strpos($url, UPLOAD_URL) !== 0) return;
    $path = UPLOAD_DIR . basename($url);
    if (is_file($path)) @unlink($path);
}
