<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Router;
use App\Services\DB;
use App\Controllers\UploadController;

$router = new Router();
$router->get('/uploads', [UploadController::class, 'list']);
$router->get('/upload-view', [UploadController::class, 'view']);
/* ===================== RUTAS ===================== */

/* Home */
$router->get('/', function() {
    $cfg = require __DIR__ . '/../config/app.php';
    ob_start(); ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title><?= htmlspecialchars($cfg['app_name'], ENT_QUOTES, 'UTF-8') ?></title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 2rem; }
        .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.25rem; max-width: 720px; }
        .ok { color: #065f46; background: #ecfdf5; padding: .5rem .75rem; border-radius: 8px; display:inline-block;}
        a { text-decoration: none; color: #0ea5e9; }
        a:hover { text-decoration: underline; }
      </style>
    </head>
    <body>
      <div class="card">
        <h1>â <?= htmlspecialchars($cfg['app_name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="ok">Servidor listo. PrÃ³ximo paso: subir ZIP/JSON y conectar MySQL.</p>
        <p><a href="/diagnostico-utf8">DiagnÃ³stico de acentos/Ã± (UTF-8)</a></p>
        <p><a href="/upload">Subir ZIP/JSON</a></p>
      </div>
    </body>
    </html>
    <?php return ob_get_clean();
});

/* DiagnÃ³stico UTF-8 */
$router->get('/diagnostico-utf8', function() {
    $prueba = "Ãrbol, canciÃ³n, Ã±andÃº, MÃ©xico, petrÃ³leo, VolumÃ©trico, JosÃ©";
    ob_start(); ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>DiagnÃ³stico UTF-8</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 2rem; }
        code { background: #f3f4f6; padding: .25rem .5rem; border-radius: 6px; }
      </style>
    </head>
    <body>
      <h2>DiagnÃ³stico de acentos/Ã±</h2>
      <p>DeberÃ­as ver acentos y la Ã± sin signos raros:</p>
      <p><code><?= htmlspecialchars($prueba, ENT_QUOTES, 'UTF-8') ?></code></p>
      <p>Si ves âÂ¿â o caracteres extraÃ±os:<br>
      1) Apache sin forzar charset (AddDefaultCharset Off en el vhost).<br>
      2) PHP manda <b>Content-Type: ... UTF-8</b> (bootstrap.php).<br>
      3) Este HTML trae <b>&lt;meta charset="utf-8"&gt;</b>.<br>
      4) BD en <b>utf8mb4</b> con colaciÃ³n <b>utf8mb4_unicode_ci</b>.
      </p>
    </body>
    </html>
    <?php return ob_get_clean();
});

/* DB check (utf8mb4) */
$router->get('/db-check', function() {
    $pdo = DB::pdo();
    $pdo->exec("CREATE TABLE IF NOT EXISTS utf8_test (
        id INT AUTO_INCREMENT PRIMARY KEY,
        texto VARCHAR(190) NOT NULL
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("DELETE FROM utf8_test");
    $stmt = $pdo->prepare("INSERT INTO utf8_test(texto) VALUES (?)");
    $stmt->execute(["Ãrbol, canciÃ³n, Ã±andÃº, MÃ©xico, petrÃ³leo, JosÃ©"]);
    $fila = $pdo->query("SELECT texto FROM utf8_test LIMIT 1")->fetch();
    $ok = $fila ? $fila['texto'] : '(sin datos)';

    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8"><title>DB UTF-8</title>
    <style>body{font-family:system-ui;margin:2rem}</style></head><body>
    <h2>Prueba de BD (utf8mb4)</h2>
    <p><b>Texto leÃ­do desde MySQL:</b> <code><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>Si los acentos/Ã± se ven correctos, la cadena completa UTF-8 estÃ¡ OK.</p>
    </body></html>
    <?php return ob_get_clean();
});

/* Subida de archivos (GET/POST) */
$router->get('/upload', [UploadController::class, 'form']);
$router->post('/upload', [UploadController::class, 'handle']);

/* ================================================= */

$router->dispatch();
