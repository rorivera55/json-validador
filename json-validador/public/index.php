<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Router;
use App\Controllers\AuthController;
use App\Controllers\UploadController;

$router = new Router();
$router->get('/admin/users', [AuthController::class, 'adminUsers']);

/* ---------- Landing (marketing) ---------- */
$router->get('/', function () {
    $user = $_SESSION['user'] ?? null;
    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8">
    <title>Validador JSON SOPMEX SUPERIRORRRR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/app.css" rel="stylesheet">
    </head><body>
    <header class="app-header">
      <div class="bar">
        <div class="brand">
      <img class="brand-logo" src="/assets/img/sopmex-logo.png"
        width="320" alt="120" loading="eager">
       <div>SOPMEX <span>Validador SAT</span></div>    
      </div>
        <nav class="nav">
          <a class="active" href="/">Inicio</a>
          <a href="/uploads">Cargas</a>
          <a href="/upload">Subir</a>
          <?php if ($user): ?>
            <a href="/dashboard">Mi panel</a>
            <a href="/logout">Salir</a>
          <?php else: ?>
            <a href="/login">Entrar</a>
            <a href="/register">Crear cuenta</a>
          <?php endif; ?>
        </nav>
      </div>
    </header>
    <div class="container">
      <div class="card" style="max-width:980px;margin:0 auto">
        <h1 style="margin-bottom:6px">Valida tus archivos JSON/EXO</h1>
        <p class="muted">Sube ZIP/JSON, verificamos estructura, agrupamos por estación (RFC + Permiso),
           calculamos recepciones/entregas, inventarios iniciales y finales, y te damos un resumen claro.</p>

        <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:16px;align-items:center;margin-top:14px">
          <div>
            <ul style="margin:0;padding-left:18px">
              <li>Carga múltiple de archivos (ZIP y JSON).</li>
              <li>Multiusuario y multicliente.</li>
              <li>Inventario inicial inferido por mes anterior si el JSON no lo trae.</li>
              <li>Panel admin: usuarios registrados y actividad.</li>
            </ul>
            <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
              <a class="btn btn-primary" href="/register">Crear cuenta gratis</a>
              <a class="btn" href="/login">Ya tengo cuenta</a>
            </div>
          </div>
          <img alt="Vista de resumen" src="https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?q=80&w=1400&auto=format&fit=crop" style="width:100%;border-radius:16px;border:1px solid var(--line)">
        </div>
      </div>
      <div class="footer">© SOPMEX · Validador JSON</div>
    </div>
    </body></html>
    <?php return ob_get_clean();
});

/* ---------- Auth ---------- */
$router->get ('/register', [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'registerHandle']);
$router->get ('/login',    [AuthController::class, 'loginForm']);
$router->post('/login',    [AuthController::class, 'loginHandle']);
$router->get ('/logout',   [AuthController::class, 'logout']);
$router->get ('/dashboard',[AuthController::class, 'dashboard']); // simple panel

/* ---------- Subidas / Listas (requieren login) ---------- */
$router->get ('/upload',       [UploadController::class, 'form']);
$router->post('/upload',       [UploadController::class, 'handle']);
$router->get ('/uploads',      [UploadController::class, 'list']);
$router->get ('/upload-view',  [UploadController::class, 'view']);
$router->get('/export-upload', [UploadController::class, 'exportUpload']);
$router->post('/upload-replace', [UploadController::class, 'replace']);
$router->post('/upload-delete',  [UploadController::class, 'delete']);
$router->get ('/export-upload',  [UploadController::class, 'exportUpload']);
$router->get ('/admin/users/new',      [AuthController::class, 'adminUserNewForm']);
$router->post('/admin/users/new',      [AuthController::class, 'adminUserNewHandle']);

$router->get ('/admin/users/edit',     [AuthController::class, 'adminUserEditForm']);   // ?id=#
$router->post('/admin/users/edit',     [AuthController::class, 'adminUserEditHandle']); // POST

$router->post('/admin/users/reset-pass',[AuthController::class, 'adminUserResetPass']); // POST
$router->post('/admin/users/toggle-admin',[AuthController::class, 'adminUserToggleAdmin']); // POST
$router->post('/upload-replace', [UploadController::class, 'replace']);
$router->post('/upload-delete',  [UploadController::class, 'delete']);
$router->get ('/export-upload',  [UploadController::class, 'exportUpload']);
$router->get('/', [\App\Controllers\HomeController::class, 'home']);

// (Opcional)
$router->post('/admin/users/delete',   [AuthController::class, 'adminUserDelete']);

/* ---------- Diagnóstico UTF-8 ---------- */
$router->get('/diagnostico-utf8', function () {
    if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    $prueba = "Árbol, canción, ñandú, México, petróleo, José";
    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8"><title>Diagnóstico UTF-8</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/app.css" rel="stylesheet">
    </head><body>
    <div class="container">
      <div class="card" style="max-width:780px;margin:0 auto">
        <h2>Diagnóstico UTF-8</h2>
        <p>Deberías ver acentos y la ñ sin signos raros:</p>
        <p><code><?= htmlspecialchars($prueba, ENT_QUOTES, 'UTF-8') ?></code></p>
      </div>
    </div>
    </body></html>
    <?php return ob_get_clean();
});

$router->dispatch();
