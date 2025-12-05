<?php
declare(strict_types=1);
ini_set('log_errors', '1');
ini_set('error_log', '/var/log/php_errors.log');
ini_set('display_errors', '0'); // en producciÃ³n 0
/**
 * Encabezados y UTF-8
 */
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Mexico_City');

/**
 * Detección HTTPS (incluye proxy)
 */
$https = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
);

/**
 * Dominio de cookie: si es json.sopmex.net lo fijamos; si es IP u otro host, NO lo fijes
 */
$host = $_SERVER['HTTP_HOST'] ?? '';
$cookieDomain = null;
if (stripos($host, 'json.sopmex.net') !== false) {
    $cookieDomain = 'json.sopmex.net';
}

/**
 * SESIÓN ultra compatible (vía ini_set) + SameSite
 * Evitamos session_set_cookie_params(array) para esquivar rarezas de versión.
 */
ini_set('session.name', 'sopmex_sid');
ini_set('session.cookie_lifetime', (string)(60 * 60 * 24 * 7)); // 7 días
ini_set('session.cookie_path', '/');
if ($cookieDomain) {
    ini_set('session.cookie_domain', $cookieDomain);
}
ini_set('session.cookie_secure', $https ? '1' : '0');
ini_set('session.cookie_httponly', '1');

/* SameSite compatible con PHP 7.3-8.x */
ini_set('session.cookie_samesite', 'Lax');

/* Directorio de sesiones (asegúrate que exista y sea de www-data) */
// ini_set('session.save_path', '/var/lib/php/sessions');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * (Opcional) logging de errores a archivo
 * Descomenta si quieres ver errores en /var/log/php_errors.log
 */
// ini_set('log_errors', '1');
// ini_set('error_log', '/var/log/php_errors.log');
// ini_set('display_errors', '0'); // en prod debe estar en 0


/**
 * Autoloader PSR-4 simple para namespace App\
 * App\Router -> /var/www/json-validador/app/Router.php
 * App\Controllers\UploadController -> /var/www/json-validador/app/Controllers/UploadController.php
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'App\\') !== 0) {
        return;
    }
    $relative = substr($class, 4); // quita "App\"
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
