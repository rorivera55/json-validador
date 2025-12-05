<?php

header('Content-Type: text/html; charset=UTF-8');
// Forzar UTF-8 en todo
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Zona horaria
$config = require __DIR__ . '/../config/app.php';
date_default_timezone_set($config['timezone'] ?? 'America/Mexico_City');

// Locale del sistema (acentos y formatos regionales)
setlocale(LC_ALL, $config['locale'] ?? 'es_MX.UTF-8');

// Encabezados HTTP seguros y UTF-8
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// Autoload simple (PSR-4 bÃ¡sico)
spl_autoload_register(function($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});
