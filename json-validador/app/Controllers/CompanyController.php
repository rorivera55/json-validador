<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DB;
use PDO;

class CompanyController {

    private static function headerNav(string $active = ''): string {
        $user = $_SESSION['user'] ?? null;
        $is = fn($k) => $active === $k ? 'active' : '';
        ob_start(); ?>
        <header class="app-header">
          <div class="bar">
            <div class="brand">
              <div class="logo" aria-hidden="true"></div>
              <div>SOPMEX <span>Validador SAT</span></div>
            </div>
            <nav class="nav" aria-label="Principal">
              <a class="<?= $is('inicio')    ?>" href="/">Inicio</a>
              <?php if ($user): ?>
                <a class="<?= $is('panel')   ?>" href="/dashboard">Mi panel</a>
                <a class="<?= $is('companies')?>" href="/companies">Empresas</a>
                <a class="<?= $is('cargas')  ?>" href="/uploads">Cargas</a>
                <a class="<?= $is('subir')   ?>" href="/upload">Subir</a>
                <?php if (($user['role'] ?? 'client') === 'admin'): ?>
                  <a class="<?= $is('admin') ?>" href="/admin/users">Admin</a>
                <?php endif; ?>
                <a href="/logout">Salir</a>
              <?php else: ?>
                <a class="<?= $is('login')   ?>" href="/login">Entrar</a>
                <a class="<?= $is('register')?>" href="/register">Crear cuenta</a>
              <?php endif; ?>
            </nav>
          </div>
        </header>
        <?php return ob_get_clean();
    }

    private static function csrfToken(): string {
        $t = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'] = $_SESSION['csrf_tokens'] ?? [];
        $_SESSION['csrf_tokens'][$t] = time();
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $k => $ts) if ($ts < $now - 7200) unset($_SESSION['csrf_tokens'][$k]);
        return $t;
    }
    private static function assertCsrf(string $t): void {
        $pool = $_SESSION['csrf_tokens'] ?? [];
        if (!$t || !isset($pool[$t])) { http_response_code(400); die('CSRF inválido'); }
        unset($_SESSION['csrf_tokens'][$t]);
    }

    private static function requireLogin(): array {
        $user = $_SESSION['user'] ?? null;
        if (!$user || empty($user['id'])) { header('Location:/login'); exit; }
        return $user;
    }

    /* --------- Listado --------- */
    public static function index(): string {
        $user = self::requireLogin();
        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id, name, rfc, created_at FROM companies WHERE owner_user_id=? ORDER BY created_at DESC");
        $st->execute([(int)$user['id']]);
        $rows = $st->fetchAll();

        $csrf = self::csrfToken();
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Empresas</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('companies') ?>
        <div class="container">
          <div class="card" style="display:flex;justify-content:space-between;align-items:center">
            <h2 style="margin:0">Empresas</h2>
            <a class="btn btn-primary" href="/companies/new">+ Nueva empresa</a>
          </div>

          <div class="table-wrap" style="margin-top:10px">
            <table>
              <thead><tr>
                <th>ID</th><th>Nombre</th><th>RFC</th><th>Alta</th><th>Acciones</th>
              </tr></thead>
              <tbody>
                <?php foreach($rows as $r): ?>
                <tr>
                  <td class="num"><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td class="mono"><?= htmlspecialchars($r['rfc'] ?? '—') ?></td>
                  <td class="mono"><?= htmlspecialchars($r['created_at']) ?></td>
                  <td class="actions">
                    <a class="btn btn-sm" href="/companies/edit?id=<?= (int)$r['id'] ?>">Editar</a>
                    <form method="post" action="/companies/delete" style="display:inline" onsubmit="return confirm('¿Eliminar esta empresa? Esta acción no borra estaciones, solo desvincula.');">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm btn-danger" type="submit">Eliminar</button>
                    </form>
                    <?php
                      $isDefault = ((int)($user['company_id'] ?? 0) === (int)$r['id']);
                    ?>
                    <form method="post" action="/companies/set-default" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm" type="submit" <?= $isDefault?'disabled':'' ?>><?= $isDefault?'Por defecto':'Hacer por defecto' ?></button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* --------- New --------- */
    public static function newForm(): string {
        $user = self::requireLogin();
        $csrf = self::csrfToken();
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Nueva empresa</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('companies') ?>
        <div class="container">
          <div class="card" style="max-width:720px;margin:0 auto">
            <h2>Nueva empresa</h2>
            <form method="post" action="/companies/new">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <p><label>Nombre<br><input name="name" required style="width:100%"></label></p>
              <p><label>RFC (opcional)<br><input name="rfc" maxlength="13" style="width:100%"></label></p>
              <button class="btn btn-primary" type="submit">Crear</button>
              <a class="btn" href="/companies">Cancelar</a>
            </form>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function newHandle(): string {
        $user = self::requireLogin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $name = trim((string)($_POST['name'] ?? ''));
        $rfc  = trim((string)($_POST['rfc'] ?? ''));
        if ($name === '') return "Nombre requerido.";

        $pdo = DB::pdo();
        $st = $pdo->prepare("INSERT INTO companies(owner_user_id, name, rfc) VALUES (?,?,?)");
        $st->execute([(int)$user['id'], $name, $rfc !== '' ? $rfc : null]);

        header('Location: /companies'); return '';
    }

    /* --------- Edit --------- */
    public static function editForm(): string {
        $user = self::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        if ($id<=0) { http_response_code(400); return "ID inválido"; }

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id, name, rfc FROM companies WHERE id=? AND owner_user_id=?");
        $st->execute([$id, (int)$user['id']]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) { http_response_code(404); return "No encontrado"; }

        $csrf = self::csrfToken();
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Editar empresa</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('companies') ?>
        <div class="container">
          <div class="card" style="max-width:720px;margin:0 auto">
            <h2>Editar empresa #<?= (int)$c['id'] ?></h2>
            <form method="post" action="/companies/edit">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <p><label>Nombre<br><input name="name" value="<?= htmlspecialchars($c['name']) ?>" required style="width:100%"></label></p>
              <p><label>RFC (opcional)<br><input name="rfc" value="<?= htmlspecialchars($c['rfc'] ?? '') ?>" maxlength="13" style="width:100%"></label></p>
              <button class="btn btn-primary" type="submit">Guardar</button>
              <a class="btn" href="/companies">Volver</a>
            </form>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function editHandle(): string {
        $user = self::requireLogin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $rfc  = trim((string)($_POST['rfc'] ?? ''));
        if ($id<=0 || $name==='') return "Datos inválidos.";

        $pdo = DB::pdo();
        $st = $pdo->prepare("UPDATE companies SET name=?, rfc=? WHERE id=? AND owner_user_id=?");
        $st->execute([$name, ($rfc!==''?$rfc:null), $id, (int)$user['id']]);

        header('Location: /companies'); return '';
    }

    /* --------- Delete --------- */
    public static function delete(): string {
        $user = self::requireLogin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { http_response_code(400); return "ID inválido"; }

        // Si es la empresa por defecto en sesión, la limpiamos
        if ((int)($user['company_id'] ?? 0) === $id) {
            $_SESSION['user']['company_id'] = null;
        }

        $pdo = DB::pdo();
        $st = $pdo->prepare("DELETE FROM companies WHERE id=? AND owner_user_id=?");
        $st->execute([$id, (int)$user['id']]);

        header('Location: /companies'); return '';
    }

    /* --------- Set default in session --------- */
    public static function setDefault(): string {
        $user = self::requireLogin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { http_response_code(400); return "ID inválido"; }

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id FROM companies WHERE id=? AND owner_user_id=?");
        $st->execute([$id, (int)$user['id']]);
        if (!$st->fetchColumn()) { http_response_code(403); return "No autorizado"; }

        $_SESSION['user']['company_id'] = $id;
        header('Location: /companies'); return '';
    }
}
