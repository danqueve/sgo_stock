<?php
// ============================================================
// config/app.php — Configuración global de la aplicación
// ============================================================

define('APP_NAME',    'Imperio Comercial');
define('APP_VERSION', '1.0.0');

// Detectar entorno automáticamente según el host
(function() {
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = in_array($host, ['localhost', '127.0.0.1'], true)
               || str_ends_with($host, '.local')
               || str_ends_with($host, '.test');

    if ($isLocal) {
        define('APP_URL', 'http://localhost/sgo');
        define('APP_ENV', 'development');
    } else {
        // VPS: construir URL base a partir del host real
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        define('APP_URL', $scheme . '://' . $host);
        define('APP_ENV', 'production');
    }
})();

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
 * Retorna la URL pública del archivo o false si falla.
 */
function subirImagenArticulo(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > UPLOAD_MAX_BYTES) return false;

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $exts  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($exts[$mime])) return false;

    $filename = uniqid('art_', true) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) return false;

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
