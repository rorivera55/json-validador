<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DB;
use PDO;

class AuthController {

    private static function headerNav(string $active = ''): string {
        $user = $_SESSION['user'] ?? null;
        $is = fn($k) => $active === $k ? 'active' : '';
        ob_start(); ?>
        <header class="app-header">
          <div class="bar">
            <div class="brand"><div class="logo"></div><div>SOPMEX <span>Validador SAT</span></div></div>
            <nav class="nav">
              <a class="<?= $is('inicio') ?>" href="/">Inicio</a>
              <a class="<?= $is('cargas') ?>" href="/uploads">Cargas</a>
              <a class="<?= $is('subir') ?>" href="/upload">Subir</a>
              <?php if ($user): ?>
                <a class="<?= $is('panel') ?>" href="/dashboard">Mi panel</a>
                <a href="/logout">Salir</a>
              <?php else: ?>
                <a class="<?= $is('login') ?>" href="/login">Entrar</a>
                <a class="<?= $is('register') ?>" href="/register">Crear cuenta</a>
              <?php endif; ?>
            </nav>
          </div>
        </header>
        <?php return ob_get_clean();
    }

    public static function registerForm(): string {
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        $csrf = self::csrfToken();
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Crear cuenta</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('register') ?>
        <div class="container">
          <div class="card" style="max-width:720px;margin:0 auto">
            <h2>Crear cuenta</h2>
            <form method="post" action="/register" accept-charset="UTF-8">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <p><label>Nombre<br><input name="name" required style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p><label>Empresa (opcional)<br><input name="org" style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p><label>RFC (opcional)<br><input name="org_rfc" maxlength="13" style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p><label>Teléfono<br><input name="phone" required style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p><label>Email<br><input type="email" name="email" required style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p><label>Contraseña<br><input type="password" name="password" required minlength="8" style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p class="muted"><input type="checkbox" name="terms" required> Acepto términos y aviso de privacidad.</p>
              <button class="btn btn-primary" type="submit">Crear cuenta</button>
              <a href="/login" class="btn">Ya tengo cuenta</a>
            </form>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function registerHandle(): string {
        self::assertCsrf($_POST['csrf'] ?? '');
        $name  = trim((string)($_POST['name'] ?? ''));
        $org   = trim((string)($_POST['org'] ?? ''));      // opcional (hoy no se guarda)
        $orgR  = trim((string)($_POST['org_rfc'] ?? ''));  // opcional (hoy no se guarda)
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');

        if (!$name || !$phone || !$email || !$pass) return "Datos incompletos.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "Email inválido.";
        if (strlen($pass) < 8) return "La contraseña debe tener al menos 8 caracteres.";

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            // No usamos organizations por ahora (mantengo campos en el formulario por marketing)
            // Validación de email único
            $st = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $st->execute([$email]);
            if ($st->fetchColumn()) {
                $pdo->rollBack();
                return "Ese email ya está registrado.";
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $st = $pdo->prepare("
                INSERT INTO users(org_id, name, email, phone, password_hash, role)
                VALUES (NULL, ?, ?, ?, ?, 'client')
            ");
            // IMPORTANTE: aquí guardamos $hash, no $pass
            $st->execute([$name, $email, $phone, $hash]);

            $uid = (int)$pdo->lastInsertId();
            $pdo->commit();

            $_SESSION['user'] = ['id'=>$uid, 'name'=>$name, 'email'=>$email, 'role'=>'client'];
            header('Location: /dashboard'); return '';
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return "Error al registrar: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    public static function loginForm(): string {
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        $csrf = self::csrfToken();
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Entrar</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('login') ?>
        <div class="container">
          <div class="card" style="max-width:560px;margin:0 auto">
            <h2>Entrar</h2>
            <form method="post" action="/login" accept-charset="UTF-8">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <p><label>Email<br><input type="email" name="email" required style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <p><label>Contraseña<br><input type="password" name="password" required style="width:100%;padding:.7rem;border:1px solid var(--line);border-radius:12px"></label></p>
              <button class="btn btn-primary" type="submit">Entrar</button>
              <a class="btn" href="/register">Crear cuenta</a>
            </form>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function loginHandle(): string {
        self::assertCsrf($_POST['csrf'] ?? '');
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id, name, email, phone, password_hash, role FROM users WHERE email=?");
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u || !password_verify($pass, $u['password_hash'])) {
            return "Credenciales inválidas.";
        }
        $_SESSION['user'] = ['id'=>(int)$u['id'], 'name'=>$u['name'], 'email'=>$u['email'], 'role'=>$u['role']];
        header('Location: /dashboard'); return '';
    }

    public static function logout(): string {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        header('Location: /'); return '';
    }

    public static function dashboard(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user) { header('Location:/login'); return ''; }
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Mi panel</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('panel') ?>
        <div class="container">
          <div class="card" style="max-width:900px;margin:0 auto">
            <h2>Hola, <?= htmlspecialchars($user['name']) ?></h2>
            <p class="muted">Desde aquí puedes subir archivos y revisar tus cargas.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
              <a class="btn btn-primary" href="/upload">Subir ZIP/JSON</a>
              <a class="btn" href="/uploads">Ver cargas</a>
            </div>
          </div>
          <?php if (($user['role'] ?? 'client') === 'admin'): ?>
          <div class="card" style="max-width:900px;margin:12px auto 0">
            <h3>Administración</h3>
            <a class="btn" href="/admin/users">Usuarios registrados</a>
          </div>
          <?php endif; ?>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* --------- Admin: lista de usuarios --------- */
    public static function adminUsers(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user || ($user['role'] ?? 'client') !== 'admin') { http_response_code(403); return "No autorizado."; }
        $pdo = DB::pdo();
        $st = $pdo->query("
          SELECT
            u.id, u.name, u.email, u.phone, u.role, u.created_at,
            (SELECT COUNT(*) FROM uploads x WHERE x.user_id = u.id) AS uploads_count
          FROM users u
          ORDER BY u.created_at DESC
          LIMIT 200
        ");
        $rows = $st->fetchAll();
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Usuarios</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('panel') ?>
        <div class="container">
          <h2>Usuarios registrados</h2>
          <div class="table-wrap" style="margin-top:10px">
            <table>
              <thead><tr>
                <th>ID</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Rol</th><th>Alta</th><th># Cargas</th>
              </tr></thead>
              <tbody>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td class="num"><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= htmlspecialchars($r['email']) ?></td>
                  <td><?= htmlspecialchars($r['phone']) ?></td>
                  <td><?= htmlspecialchars($r['role']) ?></td>
                  <td class="mono"><?= htmlspecialchars($r['created_at']) ?></td>
                  <td class="num"><?= (int)$r['uploads_count'] ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* --------- CSRF helpers --------- */
    private static function csrfToken(): string {
        $t = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'] = $_SESSION['csrf_tokens'] ?? [];
        // Guardamos el token con timestamp
        $_SESSION['csrf_tokens'][$t] = time();

        // Limpiamos tokens viejos (2 horas)
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $k => $ts) {
            if ($ts < $now - 7200) unset($_SESSION['csrf_tokens'][$k]);
        }
        return $t;
    }

    private static function assertCsrf(string $t): void {
        $pool = $_SESSION['csrf_tokens'] ?? [];
        if (!$t || !isset($pool[$t])) {
            http_response_code(400);
            die('CSRF inválido');
        }
        // Consumimos el token
        unset($_SESSION['csrf_tokens'][$t]);
    }
}
