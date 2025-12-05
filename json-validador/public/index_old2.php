<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Router;
use App\Controllers\UploadController;

$router = new Router();

// HOME
$router->get('/', function () {
    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8">
    <title>Inicio · Validador SAT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/app.css" rel="stylesheet">
    </head><body>
    <header class="app-header">
      <div class="bar">
        <div class="brand"><div class="logo"></div><div>SOPMEX <span>Validador SAT</span></div></div>
        <nav class="nav">
          <a class="active" href="/">Inicio</a>
          <a href="/uploads">Cargas</a>
          <a href="/upload">Subir ZIP/JSON</a>
        </nav>
      </div>
    </header>
    <div class="container">
      <div class="card" style="max-width:780px;margin:0 auto">
        <h1 style="margin-bottom:8px">Validador de JSON EXO (Anexo 30)</h1>
        <p class="muted">Sube tu archivo ZIP/JSON, validamos estructura y te resumimos <i>compras/ventas</i> por producto.</p>
        <div style="display:flex;gap:10px;margin-top:10px">
          <a class="btn btn-primary" href="/upload">Subir ZIP/JSON</a>
          <a class="btn" href="/uploads">Ver cargas</a>
          <a class="btn" href="/diagnostico-utf8">Diagnóstico UTF-8</a>
        </div>
      </div>
      <div class="footer">© SOPMEX · Validador Anexo 30</div>
    </div>
    </body></html>
    <?php return ob_get_clean();
});

// Diagnóstico rápido de UTF-8 (texto plano)
$router->get('/diagnostico-utf8', function () {
    $prueba = "Árbol, canción, ñandú, México, petróleo, José";
    header('Content-Type: text/html; charset=UTF-8');
    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8"><title>Diagnóstico UTF-8</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/app.css" rel="stylesheet">
    </head><body>
    <div class="container">
      <div class="card" style="max-width:780px;margin:0 auto">
        <h2>Diagnóstico UTF-8</h2>
        <p>Deberías ver acentos/ñ bien:</p>
        <p><code><?= htmlspecialchars($prueba, ENT_QUOTES, 'UTF-8') ?></code></p>
        <p class="muted">Si se ven raros, revisa los 4 niveles del checklist más abajo (Apache, PHP, archivos, MySQL).</p>
      </div>
    </div>
    </body></html>
    <?php return ob_get_clean();
});

// Subir y listar
$router->get ('/upload',       [UploadController::class, 'form']);
$router->post('/upload',       [UploadController::class, 'handle']);
$router->get ('/uploads',      [UploadController::class, 'list']);
$router->get ('/upload-view',  [UploadController::class, 'view']);

$router->dispatch();
