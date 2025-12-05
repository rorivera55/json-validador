<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DB;
use PDO;

class AuthController {

    /* ------------------ NAV ------------------ */
    private static function headerNav(string $active = '', ?bool $showDataNav = null): string {
    $user = $_SESSION['user'] ?? null;
    // Por defecto: solo mostrar links de datos si HAY sesión
    if ($showDataNav === null) $showDataNav = (bool)$user;
    $is = fn($k) => $active === $k ? 'active' : '';
    ob_start(); ?>
    <header class="app-header">
      <div class="bar">
        <a class="brand" href="/">
          <img src="/assets/img/sopmex-logo.png" alt="SOPMEX" class="logo">
          <div>SOPMEX <span>Validador SAT</span></div>
        </a>
        <nav class="nav" aria-label="Principal">
          <a class="<?= $is('inicio') ?>" href="/">Inicio</a>
          <?php if ($showDataNav): ?>
            <a class="<?= $is('cargas') ?>" href="/uploads">Cargas</a>
            <a class="<?= $is('subir') ?>" href="/upload">Subir</a>
          <?php endif; ?>
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
    /* ------------------ AUTH BÃƒÂSICO ------------------ */
   public static function registerForm(): string {
    if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
    $csrf = self::csrfToken();
    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8">
    <title>Crear cuenta · SOPMEX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/app.css?v=3" rel="stylesheet">
    </head>
    <body class="auth-bg">
    <?= self::headerNav('register', false) ?>

    <div class="auth-wrapper">
      <div class="auth-card">
        <div class="auth-brand">
          <img src="/assets/img/sopmex-logo.png" alt="SOPMEX" class="auth-logo">
          <h1 class="auth-title">Crea tu cuenta</h1>
          <p class="auth-subtitle">Invita a tu equipo y administra varias razones sociales desde una sola cuenta.</p>
        </div>

        <form method="post" action="/register" accept-charset="UTF-8" class="auth-form" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

          <div class="auth-field">
            <label class="auth-label">Nombre</label>
            <input name="name" required placeholder="Tu nombre completo">
          </div>

          <div class="auth-field">
            <label class="auth-label">Empresa (opcional)</label>
            <input name="org" placeholder="Razón social">
          </div>

          <div class="auth-field">
            <label class="auth-label">RFC de la empresa (opcional)</label>
            <input name="org_rfc" maxlength="13" placeholder="RFC">
          </div>

          <div class="auth-field">
            <label class="auth-label">Teléfono</label>
            <input type="tel" name="phone" inputmode="tel" required placeholder="55 1234 5678">
          </div>

          <div class="auth-field">
            <label class="auth-label">Email</label>
            <input type="email" name="email" required placeholder="tucorreo@empresa.com">
          </div>

          <div class="auth-field auth-password">
            <label class="auth-label">Contraseña</label>
            <input type="password" id="regpass" name="password" minlength="8" required placeholder="Mínimo 8 caracteres">
            <button type="button" class="auth-eye" aria-label="Mostrar/ocultar contraseña"
              onclick="const p=document.getElementById('regpass'); p.type=p.type==='password'?'text':'password'; this.setAttribute('aria-pressed', p.type!=='password');">👁</button>
          </div>

          <label class="auth-label" style="display:flex;gap:10px;align-items:center;">
            <input type="checkbox" name="terms" required style="width:18px;height:18px;">
            <span>Acepto los <a class="link" href="/tyc" target="_blank" rel="noopener">Términos</a> y el
              <a class="link" href="/privacidad" target="_blank" rel="noopener">Aviso de privacidad</a>.</span>
          </label>

          <button class="btn btn-primary btn-full" type="submit">Crear cuenta</button>

          <div class="auth-links">
            <a class="link" href="/login">¿Ya tienes cuenta? Entrar</a>
          </div>
        </form>
      </div>

      <div class="auth-footer">© SOPMEX · Validador JSON</div>
    </div>

    <script>
      // Validación mínima de longitud de contraseña
      (function(){
        const f=document.querySelector('.auth-form');
        f.addEventListener('submit', function(e){
          const p=document.getElementById('regpass');
          if(p.value.length<8){ e.preventDefault(); alert('La contraseña debe tener al menos 8 caracteres.'); p.focus(); }
        });
      })();
    </script>
    </body></html>
    <?php return ob_get_clean();
}
    public static function registerHandle(): string {
        self::assertCsrf($_POST['csrf'] ?? '');
        $name  = trim((string)($_POST['name'] ?? ''));
        $org   = trim((string)($_POST['org'] ?? ''));
        $orgR  = trim((string)($_POST['org_rfc'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');

        if (!$name || !$phone || !$email || !$pass) return "Datos incompletos.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "Email invÃƒÂ¡lido.";
        if (strlen($pass) < 8) return "La contraseÃƒÂ±a debe tener al menos 8 caracteres.";

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            // OrganizaciÃƒÂ³n opcional
            $orgId = null;
            if ($org) {
                $st = $pdo->prepare("INSERT INTO organizations(name, rfc) VALUES (?,?)");
                $st->execute([$org, $orgR ?: null]);
                $orgId = (int)$pdo->lastInsertId();
            }

            // Email ÃƒÂºnico
            $st = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $st->execute([$email]);
            if ($st->fetchColumn()) { $pdo->rollBack(); return "Ese email ya estÃƒÂ¡ registrado."; }

            $hash = password_hash($pass, PASSWORD_DEFAULT); // <-- FIX: antes se guardaba en claro
            $st = $pdo->prepare("INSERT INTO users(name, email, phone, password_hash, role) VALUES (?,?,?,?, 'client')");
            $st->execute([$name, $email, $phone, $hash]);
            $uid = (int)$pdo->lastInsertId();
            $pdo->commit();

            $_SESSION['user'] = ['id'=>$uid, 'org_id'=>$orgId, 'name'=>$name, 'email'=>$email, 'role'=>'client'];
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
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Entrar Â· SOPMEX Validador</title>
      <link href="/assets/css/app.css?v=3" rel="stylesheet">
    </head><body class="auth-bg">
<?= self::headerNav('login', false) ?>
    <main class="auth-wrapper">
      <section class="auth-card">
        <div class="auth-brand">
          <img src="/assets/img/sopmex-logo.png" alt="SOPMEX" class="auth-logo">
          <h1 class="auth-title">Bienvenido</h1>
          <p class="auth-subtitle">Accede para validar y revisar tus reportes antes del SAT.</p>
        </div>

        <form method="post" action="/login" accept-charset="UTF-8" class="auth-form" autocomplete="on">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

          <label class="auth-field">
            <span class="auth-label">Email</span>
            <input type="email" name="email" required placeholder="tucorreo@empresa.com" inputmode="email" autocomplete="email">
          </label>

          <label class="auth-field">
            <span class="auth-label">ContraseÃ±a</span>
            <div class="auth-password">
              <input id="pwd" type="password" name="password" required minlength="8" placeholder="********" autocomplete="current-password">
              <button type="button" class="auth-eye" aria-label="Mostrar u ocultar contraseÃ±a" onclick="(function(btn){const i=document.getElementById('pwd'); i.type = i.type==='password' ? 'text':'password'; btn.setAttribute('aria-pressed', i.type==='text');})(this)">ðŸ‘</button>
            </div>
          </label>

          <button class="btn btn-primary btn-full" type="submit">Entrar</button>

          <div class="auth-links">
            <a class="link" href="/register">Crear cuenta</a>
            <span class="sep">Â·</span>
            <a class="link" href="/forgot">Â¿Olvidaste tu contraseÃ±a?</a>
          </div>

          <p class="auth-footnote">Protegemos tus datos y sesiones con buenas prÃ¡cticas de seguridad.</p>
        </form>
      </section>
    </main>

    <footer class="auth-footer">Â© SOPMEX Â· Validador JSON</footer>

    </body></html>
    <?php return ob_get_clean();
}

    public static function loginHandle(): string {
        self::assertCsrf($_POST['csrf'] ?? '');
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id, org_id, name, email, phone, password_hash, role FROM users WHERE email=?");
        $st->execute([$email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u || !password_verify($pass, $u['password_hash'])) {
            return "Credenciales invÃƒÂ¡lidas.";
        }
        $_SESSION['user'] = ['id'=>(int)$u['id'], 'org_id'=>$u['org_id'] ? (int)$u['org_id'] : null, 'name'=>$u['name'], 'email'=>$u['email'], 'role'=>$u['role']];
        header('Location: /dashboard'); return '';
    }

    public static function logout(): string {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"] ?? '', $p["secure"], $p["httponly"]);
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
            <p class="muted">Desde aquÃƒÂ­ puedes subir archivos y revisar tus cargas.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
              <a class="btn btn-primary" href="/upload">Subir ZIP/JSON</a>
              <a class="btn" href="/uploads">Ver cargas</a>
              <?php if (($user['role'] ?? 'client') === 'admin'): ?>
                <a class="btn" href="/admin/users">Admin usuarios</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* --------------- ADMIN: LISTA --------------- */
    public static function adminUsers(): string {
        self::requireAdmin();
        $pdo = DB::pdo();

        // defensivo: asegurar columnas/ÃƒÂ­ndices
        self::ensureUsersSchema($pdo);

        $st = $pdo->query("
            SELECT
              u.id, u.name, u.email, u.phone, u.role, u.created_at
            , (SELECT COUNT(*) FROM uploads x WHERE x.user_id = u.id) AS uploads_count
            FROM users u
            ORDER BY u.created_at DESC
            LIMIT 500
        ");
        $rows = $st->fetchAll();

        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        $csrf = self::csrfToken();

        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Admin Ã‚Â· Usuarios</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('admin') ?>
        <div class="container">
          <div class="card" style="display:flex;justify-content:space-between;align-items:center">
            <h2 style="margin:0">Usuarios</h2>
            <a class="btn btn-primary" href="/admin/users/new">+ Nuevo usuario</a>
          </div>

          <div class="table-wrap" style="margin-top:10px">
            <table>
              <thead><tr>
                <th>ID</th><th>Nombre</th><th>Email</th><th>TelÃƒÂ©fono</th><th>Rol</th><th>Subidas</th><th>Alta</th><th>Acciones</th>
              </tr></thead>
              <tbody>
              <?php foreach($rows as $r): ?>
                <tr>
                  <td class="num"><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['name']) ?></td>
                  <td><?= htmlspecialchars($r['email']) ?></td>
                  <td><?= htmlspecialchars($r['phone']) ?></td>
                  <td><span class="pill <?= $r['role']==='admin'?'ok':'warn' ?>"><?= htmlspecialchars($r['role']) ?></span></td>
                  <td class="num"><?= (int)$r['uploads_count'] ?></td>
                  <td class="mono"><?= htmlspecialchars($r['created_at']) ?></td>
                  <td class="actions">
                    <a class="btn btn-sm" href="/admin/users/edit?id=<?= (int)$r['id'] ?>">Editar</a>
                    <form style="display:inline" method="post" action="/admin/users/reset-pass">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm" type="submit">Reset pass</button>
                    </form>
                    <form style="display:inline" method="post" action="/admin/users/toggle-admin">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <input type="hidden" name="to" value="<?= $r['role']==='admin'?'client':'admin' ?>">
                      <button class="btn btn-sm" type="submit"><?= $r['role']==='admin'?'Quitar admin':'Hacer admin' ?></button>
                    </form>
                    <!-- Opcional borrar:
                    <form style="display:inline" method="post" action="/admin/users/delete" onsubmit="return confirm('Ã‚Â¿Eliminar definitivamente?')">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                      <button class="btn btn-sm" type="submit">Eliminar</button>
                    </form>
                    -->
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

    /* --------------- ADMIN: NEW --------------- */
    public static function adminUserNewForm(): string {
        self::requireAdmin();
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        $csrf = self::csrfToken();
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Nuevo usuario</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('admin') ?>
        <div class="container">
          <div class="card" style="max-width:720px;margin:0 auto">
            <h2>Nuevo usuario</h2>
            <form method="post" action="/admin/users/new" accept-charset="UTF-8">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <p><label>Nombre<br><input name="name" required></label></p>
              <p><label>TelÃƒÂ©fono<br><input name="phone" required></label></p>
              <p><label>Email<br><input type="email" name="email" required></label></p>
              <p><label>ContraseÃƒÂ±a temporal<br><input type="text" name="password" value="<?= htmlspecialchars(self::randomPass()) ?>" required></label></p>
              <p><label>Rol<br>
                <select name="role">
                  <option value="client">Cliente</option>
                  <option value="admin">Admin</option>
                </select></label>
              </p>
              <button class="btn btn-primary" type="submit">Crear</button>
              <a class="btn" href="/admin/users">Cancelar</a>
            </form>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function adminUserNewHandle(): string {
        self::requireAdmin();
        self::assertCsrf($_POST['csrf'] ?? '');

        $name  = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pass  = (string)($_POST['password'] ?? '');
        $role  = ($_POST['role'] ?? 'client') === 'admin' ? 'admin' : 'client';

        if (!$name || !$phone || !$email || !$pass) return "Datos incompletos.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "Email invÃƒÂ¡lido.";
        if (strlen($pass) < 8) return "La contraseÃƒÂ±a debe tener al menos 8 caracteres.";

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $st->execute([$email]);
        if ($st->fetchColumn()) return "Ese email ya existe.";

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $st = $pdo->prepare("INSERT INTO users(name, email, phone, password_hash, role) VALUES (?,?,?,?,?)");
        $st->execute([$name, $email, $phone, $hash, $role]);

        header('Location: /admin/users'); return '';
    }

    /* --------------- ADMIN: EDIT --------------- */
    public static function adminUserEditForm(): string {
        self::requireAdmin();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); return "ID invÃƒÂ¡lido"; }

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id, name, email, phone, role FROM users WHERE id=?");
        $st->execute([$id]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) { http_response_code(404); return "No encontrado"; }

        $csrf = self::csrfToken();
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');

        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>Editar usuario</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('admin') ?>
        <div class="container">
          <div class="card" style="max-width:720px;margin:0 auto">
            <h2>Editar usuario #<?= (int)$u['id'] ?></h2>
            <form method="post" action="/admin/users/edit">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <p><label>Nombre<br><input name="name" value="<?= htmlspecialchars($u['name']) ?>" required></label></p>
              <p><label>TelÃƒÂ©fono<br><input name="phone" value="<?= htmlspecialchars($u['phone']) ?>" required></label></p>
              <p><label>Email<br><input type="email" name="email" value="<?= htmlspecialchars($u['email']) ?>" required></label></p>
              <p><label>Rol<br>
                <select name="role">
                  <option value="client" <?= $u['role']==='client'?'selected':'' ?>>Cliente</option>
                  <option value="admin"  <?= $u['role']==='admin'?'selected':''  ?>>Admin</option>
                </select></label>
              </p>
              <button class="btn btn-primary" type="submit">Guardar cambios</button>
              <a class="btn" href="/admin/users">Volver</a>
            </form>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function adminUserEditHandle(): string {
        self::requireAdmin();
        self::assertCsrf($_POST['csrf'] ?? '');

        $id    = (int)($_POST['id'] ?? 0);
        $name  = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $role  = ($_POST['role'] ?? 'client') === 'admin' ? 'admin' : 'client';

        if ($id<=0 || !$name || !$phone || !$email) return "Datos incompletos.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "Email invÃƒÂ¡lido.";

        $pdo = DB::pdo();
        $st = $pdo->prepare("SELECT id FROM users WHERE email=? AND id<>?");
        $st->execute([$email,$id]);
        if ($st->fetchColumn()) return "Ese email ya existe en otro usuario.";

        $st = $pdo->prepare("UPDATE users SET name=?, phone=?, email=?, role=? WHERE id=?");
        $st->execute([$name,$phone,$email,$role,$id]);

        header('Location: /admin/users'); return '';
    }

    /* --------------- ADMIN: RESET PASS --------------- */
    public static function adminUserResetPass(): string {
        self::requireAdmin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { http_response_code(400); return "ID invÃƒÂ¡lido"; }

        $new = self::randomPass();
        $hash = password_hash($new, PASSWORD_DEFAULT);

        $pdo = DB::pdo();
        $st = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $st->execute([$hash, $id]);

        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8"><title>ContraseÃƒÂ±a restablecida</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet"></head><body>
        <?= self::headerNav('admin') ?>
        <div class="container">
          <div class="card" style="max-width:580px;margin:0 auto">
            <h2>ContraseÃƒÂ±a restablecida</h2>
            <p>Entrega esta contraseÃƒÂ±a temporal al usuario y pÃƒÂ­dele cambiarla al entrar:</p>
            <p><code style="font-size:1.2rem"><?= htmlspecialchars($new) ?></code></p>
            <a class="btn" href="/admin/users">Volver</a>
          </div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* --------------- ADMIN: TOGGLE ADMIN --------------- */
    public static function adminUserToggleAdmin(): string {
        self::requireAdmin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        $to = ($_POST['to'] ?? 'client') === 'admin' ? 'admin' : 'client';
        if ($id<=0) { http_response_code(400); return "ID invÃƒÂ¡lido"; }

        $pdo = DB::pdo();
        $st = $pdo->prepare("UPDATE users SET role=? WHERE id=?");
        $st->execute([$to, $id]);

        header('Location: /admin/users'); return '';
    }

    /* --------------- ADMIN: DELETE (opcional) --------------- */
    public static function adminUserDelete(): string {
        self::requireAdmin();
        self::assertCsrf($_POST['csrf'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { http_response_code(400); return "ID invÃƒÂ¡lido"; }

        $pdo = DB::pdo();
        // ojo: borra definitvamente; puedes cambiar por soft-delete si gustas
        $st = $pdo->prepare("DELETE FROM users WHERE id=?");
        $st->execute([$id]);

        header('Location: /admin/users'); return '';
    }

    /* ------------------ HELPERS ------------------ */
    private static function requireAdmin(): void {
        $u = $_SESSION['user'] ?? null;
        if (!$u || ($u['role'] ?? 'client') !== 'admin') {
            http_response_code(403); die('No autorizado.');
        }
    }

    private static function csrfToken(): string {
        $t = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'] = $_SESSION['csrf_tokens'] ?? [];
        $_SESSION['csrf_tokens'][$t] = time();
        // limpiar > 2 horas
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $k => $ts) {
            if ($ts < $now - 7200) unset($_SESSION['csrf_tokens'][$k]);
        }
        return $t;
    }

    private static function assertCsrf(string $t): void {
        $pool = $_SESSION['csrf_tokens'] ?? [];
        if (!$t || !isset($pool[$t])) { http_response_code(400); die('CSRF invÃƒÂ¡lido'); }
        unset($_SESSION['csrf_tokens'][$t]);
    }

    private static function ensureUsersSchema(PDO $pdo): void {
        // role
        $hasRole = self::columnExists($pdo, 'users', 'role');
        if (!$hasRole) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('client','admin') NOT NULL DEFAULT 'client' AFTER password_hash");
        }
        // unique email
        try { $pdo->exec("CREATE UNIQUE INDEX ux_users_email ON users(email)"); } catch (\Throwable $e) {}
    }

    private static function columnExists(PDO $pdo, string $table, string $col): bool {
        $q = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
        $q->execute([$table,$col]);
        return (int)$q->fetchColumn() > 0;
    }

    private static function randomPass(int $len=12): string {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%*+-_';
        $n = strlen($alphabet);
        $out = '';
        for ($i=0; $i<$len; $i++) $out .= $alphabet[random_int(0,$n-1)];
        return $out;
    }
}
