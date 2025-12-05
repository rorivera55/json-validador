<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DB;

class UploadController {

    /* -------------------- HEADER / NAV -------------------- */
    private static function headerNav(string $active = ''): string {
        $is = fn($k) => $active === $k ? 'active' : '';
        ob_start(); ?>
        <header class="app-header">
          <div class="bar">
            <div class="brand">
              <div class="logo" aria-hidden="true"></div>
              <div>SOPMEX <span>Validador SAT</span></div>
            </div>
            <nav class="nav" aria-label="Principal">
              <a class="<?= $is('inicio') ?>" href="/">Inicio</a>
              <a class="<?= $is('cargas') ?>" href="/uploads">Cargas</a>
              <a class="<?= $is('subir') ?>" href="/upload">Subir ZIP/JSON</a>
            </nav>
          </div>
        </header>
        <?php return ob_get_clean();
    }

    /* -------------------- CSRF + TOKENS -------------------- */
    private static function csrfToken(): string {
        $t = bin2hex(random_bytes(32));
        $_SESSION['csrf_tokens'] = $_SESSION['csrf_tokens'] ?? [];
        $_SESSION['csrf_tokens'][$t] = time();
        $now = time();
        foreach ($_SESSION['csrf_tokens'] as $k => $ts) {
            if ($ts < $now - 7200) unset($_SESSION['csrf_tokens'][$k]);
        }
        return $t;
    }
    private static function assertCsrf(string $t): void {
        $pool = $_SESSION['csrf_tokens'] ?? [];
        if (!$t || !isset($pool[$t])) { http_response_code(400); die('CSRF inválido'); }
        unset($_SESSION['csrf_tokens'][$t]);
    }
    /** token temporal para “reemplazar” (apunta al JSON subido y su sha) */
    private static function makeReplaceToken(array $payload): string {
        $tok = bin2hex(random_bytes(16));
        $_SESSION['replace_tokens'] = $_SESSION['replace_tokens'] ?? [];
        $_SESSION['replace_tokens'][$tok] = $payload + ['ts'=>time()];
        foreach ($_SESSION['replace_tokens'] as $k=>$v) {
            if (($v['ts'] ?? 0) < time()-900) unset($_SESSION['replace_tokens'][$k]);
        }
        return $tok;
    }
    private static function takeReplaceToken(string $tok): ?array {
        $bag = $_SESSION['replace_tokens'] ?? [];
        if (!isset($bag[$tok])) return null;
        $payload = $bag[$tok];
        unset($_SESSION['replace_tokens'][$tok]);
        return $payload;
    }

    /* -------------------- VISTAS -------------------- */
    public static function form(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user) { header('Location:/login'); return ''; }
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8">
        <title>Subir ZIP/JSON · Validador SAT</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet">
        </head><body>
        <?= self::headerNav('subir') ?>
        <div class="container">
          <div class="card" style="max-width:760px;margin:0 auto">
            <h2 style="margin-bottom:6px">Subir archivos</h2>
            <p class="muted" style="margin:0 0 16px 0">Puedes seleccionar varios ZIP/JSON a la vez. Agruparemos por estación (RFC + Permiso).</p>
            <form method="post" enctype="multipart/form-data" action="/upload" accept-charset="UTF-8">
              <p>
                <input type="file" name="archivos[]" accept=".zip,.json" required multiple
                       style="padding:.8rem;border:1px solid var(--line);border-radius:12px;width:100%">
              </p>
              <button type="submit" class="btn btn-primary">Procesar archivos</button>
              <a href="/uploads" class="btn">Ver cargas recientes</a>
            </form>
          </div>
          <div class="footer">© SOPMEX · Validador JSON</div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function list(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user || empty($user['id'])) { header('Location:/login'); return ''; }
        $userId = (int)$user['id'];
        $pdo = DB::pdo();

        // --- Mensuales
        $sqlM = "
          SELECT u.id, u.filename, u.period_year, u.period_month, u.status, u.created_at,
                 s.rfc, s.permiso, s.clave_instalacion,
                 COALESCE(SUM(up.vol_recepcion_l),0)   AS compras_l,
                 COALESCE(SUM(up.imp_recepcion_mxn),0) AS compras_mxn,
                 COALESCE(SUM(up.vol_entrega_l),0)     AS ventas_l,
                 COALESCE(SUM(up.imp_entrega_mxn),0)   AS ventas_mxn,
                 COALESCE(SUM(up.recepciones_count),0) AS num_recepciones
          FROM uploads u
          JOIN stations s ON s.id = u.station_id
          LEFT JOIN upload_products up ON up.upload_id = u.id
          WHERE u.user_id = :uid
            AND (u.report_type IS NULL OR u.report_type = 'monthly')
          GROUP BY u.id
          ORDER BY u.period_year DESC, u.period_month DESC, u.id DESC
          LIMIT 300
        ";
        $stm = $pdo->prepare($sqlM);
        $stm->execute([':uid' => $userId]);
        $monthly = $stm->fetchAll();

        // --- Diarios
        $sqlD = "
          SELECT u.id, u.filename, u.period_year, u.period_month, u.period_day, u.status, u.created_at,
                 s.rfc, s.permiso, s.clave_instalacion,
                 COALESCE(SUM(upd.vol_recepcion_l),0)   AS compras_l,
                 COALESCE(SUM(upd.imp_recepcion_mxn),0) AS compras_mxn,
                 COALESCE(SUM(upd.vol_entrega_l),0)     AS ventas_l,
                 COALESCE(SUM(upd.imp_entrega_mxn),0)   AS ventas_mxn
          FROM uploads u
          JOIN stations s ON s.id = u.station_id
          LEFT JOIN upload_products_daily upd ON upd.upload_id = u.id
          WHERE u.user_id = :uid
            AND u.report_type = 'daily'
          GROUP BY u.id
          ORDER BY u.period_year DESC, u.period_month DESC, u.period_day DESC, u.id DESC
          LIMIT 300
        ";
        $std = $pdo->prepare($sqlD);
        $std->execute([':uid' => $userId]);
        $daily = $std->fetchAll();

        $csrf = self::csrfToken();
        $tab  = ($_GET['tab'] ?? 'monthly') === 'daily' ? 'daily' : 'monthly';

        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8">
        <title>Cargas · Validador SAT</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet">
        <style>
          .tabs { display:flex; gap:8px; margin-bottom:10px; }
          .tabs a { padding:8px 12px; border:1px solid var(--line); border-radius:10px; text-decoration:none; }
          .tabs a.active { background: var(--brand-wash); border-color: var(--brand); }
          .hidden { display:none; }
        </style>
        </head><body>
        <?= self::headerNav('cargas') ?>
        <div class="container">
          <h2 style="margin-bottom:10px">Cargas</h2>

          <div class="tabs" role="tablist" aria-label="Tipo de cargas">
            <a href="/uploads?tab=monthly" class="<?= $tab==='monthly'?'active':'' ?>" role="tab" aria-selected="<?= $tab==='monthly'?'true':'false'?>">Mensuales</a>
            <a href="/uploads?tab=daily"   class="<?= $tab==='daily'?'active':''   ?>" role="tab" aria-selected="<?= $tab==='daily'?'true':'false'?>">Diarias</a>
          </div>

          <!-- Mensuales -->
          <div class="<?= $tab==='monthly'?'':'hidden' ?>">
            <div class="table-wrap" role="region" aria-label="Cargas mensuales" tabindex="0">
              <table>
                <colgroup>
                  <col style="width:70px"><col><col style="width:140px"><col style="width:140px"><col style="width:140px">
                  <col style="width:110px"><col style="width:140px"><col style="width:140px"><col style="width:130px">
                  <col style="width:120px"><col style="width:160px">
                </colgroup>
                <thead>
                  <tr>
                    <th>ID</th><th>Archivo</th><th>RFC</th><th>Permiso</th><th>Instalación</th>
                    <th>Periodo</th><th>Compras (L)</th><th>Ventas (L)</th><th># Recepciones</th>
                    <th>Estatus</th><th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach($monthly as $r):
                    $status = $r['status'];
                    $class = $status === 'valid' ? 'ok' : ($status === 'warning' ? 'warn' : 'bad');
                    $url = "/upload-view?id=".(int)$r['id']; ?>
                  <tr>
                    <td class="num"><a href="<?= $url ?>" class="mono" style="text-decoration:none;color:inherit"><?= (int)$r['id'] ?></a></td>
                    <td><a href="<?= $url ?>" style="text-decoration:none;color:var(--brand-ink)"><?= htmlspecialchars($r['filename'], ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td class="mono"><?= htmlspecialchars($r['rfc'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono"><?= htmlspecialchars($r['permiso'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['clave_instalacion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono"><?= (int)$r['period_year'] ?>-<?= str_pad((string)$r['period_month'],2,'0',STR_PAD_LEFT) ?></td>
                    <td class="num"><?= number_format((float)$r['compras_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$r['ventas_l'], 3) ?></td>
                    <td class="num"><?= (int)$r['num_recepciones'] ?></td>
                    <td><span class="pill <?= $class ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="actions">
                      <a class="btn btn-sm" href="<?= $url ?>">Ver</a>
                      <form method="post" action="/upload-delete" style="display:inline" onsubmit="return confirm('¿Eliminar esta carga?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Diarias -->
          <div class="<?= $tab==='daily'?'':'hidden' ?>">
            <div class="table-wrap" role="region" aria-label="Cargas diarias" tabindex="0">
              <table>
                <colgroup>
                  <col style="width:70px"><col><col style="width:140px"><col style="width:140px"><col style="width:140px">
                  <col style="width:160px"><col style="width:140px"><col style="width:140px"><col style="width:120px">
                </colgroup>
                <thead>
                  <tr>
                    <th>ID</th><th>Archivo</th><th>RFC</th><th>Permiso</th><th>Instalación</th>
                    <th>Fecha</th><th>Compras (L)</th><th>Ventas (L)</th><th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach($daily as $r):
                    $url = "/upload-view?id=".(int)$r['id']; ?>
                  <tr>
                    <td class="num"><a href="<?= $url ?>" class="mono" style="text-decoration:none;color:inherit"><?= (int)$r['id'] ?></a></td>
                    <td><a href="<?= $url ?>" style="text-decoration:none;color:var(--brand-ink)"><?= htmlspecialchars($r['filename'], ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td class="mono"><?= htmlspecialchars($r['rfc'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono"><?= htmlspecialchars($r['permiso'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['clave_instalacion'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono"><?= (int)$r['period_year'] ?>-<?= str_pad((string)$r['period_month'],2,'0',STR_PAD_LEFT) ?>-<?= str_pad((string)$r['period_day'],2,'0',STR_PAD_LEFT) ?></td>
                    <td class="num"><?= number_format((float)$r['compras_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$r['ventas_l'], 3) ?></td>
                    <td class="actions">
                      <a class="btn btn-sm" href="<?= $url ?>">Ver</a>
                      <form method="post" action="/upload-delete" style="display:inline" onsubmit="return confirm('¿Eliminar esta carga?');">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div style="margin-top:12px">
            <a href="/upload" class="btn btn-primary">Subir nuevo ZIP/JSON</a>
            <a href="/" class="btn">Inicio</a>
          </div>

          <div class="footer">© SOPMEX · Validador JSON</div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    public static function view(): string {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) { http_response_code(400); return "ID inválido"; }

        $pdo = DB::pdo();
        $user = $_SESSION['user'] ?? null;
        if (!$user || empty($user['id'])) { header('Location:/login'); return ''; }
        $userId = (int)$user['id'];

        $stmt = $pdo->prepare("
            SELECT u.*, s.rfc, s.permiso, s.clave_instalacion
            FROM uploads u
            JOIN stations s ON s.id = u.station_id
            WHERE u.id = :id AND u.user_id = :uid
        ");
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $up = $stmt->fetch();
        if (!$up) { http_response_code(404); return "No encontrado"; }

        $csrf = self::csrfToken();

        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        ob_start(); ?>
        <!doctype html><html lang="es"><head>
        <meta charset="utf-8">
        <title>Detalle carga #<?= (int)$id ?> · Validador SAT</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="/assets/css/app.css" rel="stylesheet">
        </head><body>
        <?= self::headerNav('cargas') ?>
        <div class="container">

          <div class="card" style="margin-bottom:16px">
            <h2 style="margin-bottom:6px">Upload #<?= (int)$up['id'] ?> — <?= htmlspecialchars($up['filename'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="muted">
              RFC: <b><?= htmlspecialchars($up['rfc'], ENT_QUOTES, 'UTF-8') ?></b> ·
              Permiso: <b class="mono"><?= htmlspecialchars($up['permiso'], ENT_QUOTES, 'UTF-8') ?></b> ·
              Instalación: <b><?= htmlspecialchars($up['clave_instalacion'], ENT_QUOTES, 'UTF-8') ?></b><br>
              Periodo: <b class="mono"><?= (int)$up['period_year'] ?>-<?= str_pad((string)$up['period_month'],2,'0',STR_PAD_LEFT) ?><?= $up['period_day'] ? '-'.str_pad((string)$up['period_day'],2,'0',STR_PAD_LEFT) : '' ?></b> ·
              Versión SAT: <b><?= htmlspecialchars((string)$up['sat_version'], ENT_QUOTES, 'UTF-8') ?></b> ·
              Estado: <b class="mono"><?= htmlspecialchars($up['status'], ENT_QUOTES, 'UTF-8') ?></b><br>
              Hash (SHA-256): <span class="mono"><?= htmlspecialchars($up['sha256'], ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <div style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap">
              <a href="/uploads" class="btn">? Volver a cargas</a>
              <a href="/upload" class="btn">Subir otra</a>
              <a href="/" class="btn">Inicio</a>
              <a class="btn btn-primary" href="/export-upload?id=<?= (int)$up['id'] ?>">Exportar XLS</a>
              <form method="post" action="/upload-delete" onsubmit="return confirm('¿Eliminar esta carga?');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$up['id'] ?>">
                <button type="submit" class="btn btn-danger">Eliminar</button>
              </form>
            </div>
          </div>

          <?php if (($up['report_type'] ?? 'monthly') === 'daily'): ?>
            <?php
              $stmt = $pdo->prepare("
                SELECT fecha, product_code, subproduct_code, brand,
                       existencia_ini_l, vol_recepcion_l, imp_recepcion_mxn, recepciones_count,
                       vol_entrega_l, imp_entrega_mxn, existencia_fin_l
                FROM upload_products_daily
                WHERE upload_id = ?
                ORDER BY fecha, product_code, subproduct_code
              ");
              $stmt->execute([$id]);
              $rows = $stmt->fetchAll();
            ?>
            <h3 style="margin:10px 0">Productos (diario)</h3>
            <div class="table-wrap" role="region" aria-label="Detalle por producto (diario)" tabindex="0">
              <table>
                <colgroup>
                  <col style="width:110px"><col style="width:120px"><col style="width:120px"><col>
                  <col style="width:140px"><col style="width:140px"><col style="width:120px">
                  <col style="width:140px"><col style="width:140px"><col style="width:160px">
                </colgroup>
                <thead>
                  <tr>
                    <th>Fecha</th><th>Producto</th><th>Subproducto</th><th>Marca</th>
                    <th>Existencia inicial (L)</th>
                    <th>Recepciones (L)</th><th>Recepciones (MXN)</th>
                    <th>Entregas (L)</th><th>Ventas (MXN)</th>
                    <th>Existencia fin (L)</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($rows as $p): ?>
                  <tr>
                    <td class="mono"><?= htmlspecialchars($p['fecha']) ?></td>
                    <td class="mono"><?= htmlspecialchars($p['product_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono"><?= htmlspecialchars($p['subproduct_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['brand'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num"><?= $p['existencia_ini_l'] === null ? '—' : number_format((float)$p['existencia_ini_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$p['vol_recepcion_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$p['imp_recepcion_mxn'], 2) ?></td>
                    <td class="num"><?= number_format((float)$p['vol_entrega_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$p['imp_entrega_mxn'], 2) ?></td>
                    <td class="num"><?= number_format((float)$p['existencia_fin_l'], 3) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <?php
              $stmt = $pdo->prepare("
                  SELECT product_code, subproduct_code, brand,
                         existencia_ini_l,
                         vol_recepcion_l, imp_recepcion_mxn, recepciones_count, recepciones_docs,
                         vol_entrega_l, imp_entrega_mxn,
                         existencia_fin_l
                  FROM upload_products
                  WHERE upload_id = ?
                  ORDER BY product_code, subproduct_code
              ");
              $stmt->execute([$id]);
              $prod = $stmt->fetchAll();
            ?>
            <h3 style="margin:10px 0">Productos</h3>
            <div class="table-wrap" role="region" aria-label="Detalle por producto" tabindex="0">
              <table>
                <colgroup>
                  <col style="width:120px"><col style="width:120px"><col>
                  <col style="width:160px">
                  <col style="width:140px"><col style="width:140px"><col style="width:130px"><col style="width:130px">
                  <col style="width:140px"><col style="width:140px">
                  <col style="width:160px">
                </colgroup>
                <thead>
                  <tr>
                    <th>Producto</th><th>Subproducto</th><th>Marca</th>
                    <th>Existencia inicial (L)</th>
                    <th>Recepciones (L)</th><th>Recepciones (MXN)</th>
                    <th># Recepciones</th><th># Documentos</th>
                    <th>Entregas (L)</th><th>Ventas (MXN)</th>
                    <th>Existencia fin (L)</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach($prod as $p): ?>
                  <tr>
                    <td class="mono"><?= htmlspecialchars($p['product_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="mono"><?= htmlspecialchars($p['subproduct_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($p['brand'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="num"><?= $p['existencia_ini_l'] === null ? '—' : number_format((float)$p['existencia_ini_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$p['vol_recepcion_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$p['imp_recepcion_mxn'], 2) ?></td>
                    <td class="num"><?= (int)$p['recepciones_count'] ?></td>
                    <td class="num"><?= (int)$p['recepciones_docs'] ?></td>
                    <td class="num"><?= number_format((float)$p['vol_entrega_l'], 3) ?></td>
                    <td class="num"><?= number_format((float)$p['imp_entrega_mxn'], 2) ?></td>
                    <td class="num"><?= number_format((float)$p['existencia_fin_l'], 3) ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <div class="footer">© SOPMEX · Validador Anexo 30</div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* -------------------- HANDLER SUBIR -------------------- */
    public static function handle(): string {
        try {
            $user = $_SESSION['user'] ?? null;
            if (!$user) { header('Location:/login'); return ''; }
            $userId = (int)$user['id'];

            // 1) Recolectar archivos
            $fileSpecs = [];
            if (!empty($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
                foreach ($_FILES['archivos']['name'] as $i => $name) {
                    $fileSpecs[] = [
                        'name' => $_FILES['archivos']['name'][$i],
                        'tmp'  => $_FILES['archivos']['tmp_name'][$i],
                        'err'  => $_FILES['archivos']['error'][$i],
                        'size' => (int)$_FILES['archivos']['size'][$i],
                    ];
                }
            } elseif (!empty($_FILES['archivo'])) {
                $fileSpecs[] = [
                    'name' => $_FILES['archivo']['name'] ?? 'archivo',
                    'tmp'  => $_FILES['archivo']['tmp_name'] ?? '',
                    'err'  => $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE,
                    'size' => (int)($_FILES['archivo']['size'] ?? 0),
                ];
            } else {
                http_response_code(400);
                return "No se recibió ningún archivo.";
            }

            $uploadsDir = __DIR__ . '/../../storage/uploads';
            $tmpDir     = __DIR__ . '/../../storage/tmp';
            $allJsonPaths = [];

            foreach ($fileSpecs as $f) {
                if ($f['err'] !== UPLOAD_ERR_OK) return "Error de subida (código {$f['err']}).";
                if (!is_uploaded_file($f['tmp'])) return "Subida inválida.";
                if ($f['size'] > 60*1024*1024) return "Archivo demasiado grande (>60MB).";

                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                if ($ext === 'json') {
                    $safe = self::moveSafe($f['tmp'], $uploadsDir, $f['name']);
                    $allJsonPaths[] = $safe['path'];
                } elseif ($ext === 'zip') {
                    self::assertZipSupport();
                    $safe = self::moveSafe($f['tmp'], $tmpDir, $f['name']);
                    $jsonList = self::unzipJson($safe['path'], $tmpDir . '/unz_' . uniqid());
                    if ($jsonList) $allJsonPaths = array_merge($allJsonPaths, $jsonList);
                } else {
                    return "Extensión no permitida (usa .json o .zip).";
                }
            }

            if (!$allJsonPaths) return "No se encontraron JSON válidos.";

            $pdo = DB::pdo();
            self::ensureSchemaUploads($pdo);
            self::ensureSchemaUploadProducts($pdo);
            self::ensureSchemaUploadProductsDaily($pdo);

            // 2) Duplicado por SHA -> confirmar reemplazo
            foreach ($allJsonPaths as $jsonPath) {
                $shaNew = hash_file('sha256', $jsonPath);
                $du = $pdo->prepare("SELECT id, filename, period_year, period_month FROM uploads WHERE user_id=? AND sha256=? LIMIT 1");
                $du->execute([$userId, $shaNew]);
                $existing = $du->fetch();
                if ($existing) {
                    $csrf = self::csrfToken();
                    $token = self::makeReplaceToken([
                        'user_id' => $userId,
                        'json_path' => $jsonPath,
                        'sha' => $shaNew,
                        'orig_name' => basename($jsonPath),
                        'replace_upload_id' => (int)$existing['id'],
                    ]);
                    ob_start(); ?>
                    <!doctype html><html lang="es"><head>
                    <meta charset="utf-8"><title>Archivo duplicado · Confirmar reemplazo</title>
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <link href="/assets/css/app.css" rel="stylesheet">
                    </head><body>
                    <?= self::headerNav('cargas') ?>
                    <div class="container">
                      <div class="card" style="max-width:780px;margin:0 auto">
                        <h2>Archivo ya existente</h2>
                        <p class="muted">Detectamos que este archivo (mismo SHA-256) ya fue subido por ti.</p>
                        <ul>
                          <li><b>SHA-256:</b> <span class="mono"><?= htmlspecialchars($shaNew) ?></span></li>
                          <li><b>Archivo nuevo:</b> <?= htmlspecialchars(basename($jsonPath)) ?></li>
                          <li><b>Carga existente #<?= (int)$existing['id'] ?></b> — periodo <?= (int)$existing['period_year'] ?>-<?= str_pad((string)$existing['period_month'],2,'0',STR_PAD_LEFT) ?></li>
                        </ul>
                        <form method="post" action="/upload-replace" style="display:inline">
                          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                          <button class="btn btn-primary" type="submit">Sí, reemplazar</button>
                        </form>
                        <a href="/uploads" class="btn">Cancelar</a>
                      </div>
                    </div>
                    </body></html>
                    <?php return ob_get_clean();
                }
            }

            // 3) Procesar todos
            $rows = [];
            foreach ($allJsonPaths as $jsonPath) {
                $rows[] = self::processSingleJson($pdo, $userId, $jsonPath, /*skipDup*/true);
            }

            // 4) Resumen
            if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
            ob_start(); ?>
            <!doctype html><html lang="es"><head>
            <meta charset="utf-8"><title>Resultado de carga · Validador SAT</title>
            <link href="/assets/css/app.css" rel="stylesheet"></head><body>
            <?= self::headerNav('cargas') ?>
            <div class="container">
              <h2 style="margin-bottom:10px">Resultado</h2>
              <div class="table-wrap">
                <table>
                  <thead><tr>
                    <th>Archivo</th><th>RFC</th><th>Permiso</th><th>Instalación</th><th>Versión</th>
                    <th>Año</th><th>Mes</th><th>Compras (L)</th><th>Ventas (L)</th><th># Recepciones</th><th>Status</th>
                  </tr></thead><tbody>
                <?php foreach($rows as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['archivo'] ?? ($r['error_archivo'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($r['rfc'] ?? ($r['error'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($r['permiso'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['instalacion'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['version'] ?? '') ?></td>
                    <td class="mono"><?= htmlspecialchars((string)($r['año'] ?? '')) ?></td>
                    <td class="mono"><?= htmlspecialchars((string)($r['mes'] ?? '')) ?></td>
                    <td class="num"><?= number_format((float)($r['vol_compras_l'] ?? 0), 3) ?></td>
                    <td class="num"><?= number_format((float)($r['vol_ventas_l'] ?? 0), 3) ?></td>
                    <td class="num"><?= (int)($r['num_recepciones'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($r['status'] ?? ($r['error'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div style="margin-top:12px">
                <a href="/uploads" class="btn">Ver cargas</a>
                <a href="/upload" class="btn btn-primary">? Subir más</a>
                <a href="/" class="btn">Inicio</a>
              </div>
              <div class="footer">© SOPMEX · Validador Anexo 30</div>
            </div>
            </body></html>
            <?php return ob_get_clean();

        } catch (\Throwable $e) {
            error_log("[upload] " . $e->getMessage() . "\n" . $e->getTraceAsString());
            http_response_code(500);
            return "Error al procesar la carga: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    /* -------------------- REEMPLAZAR (POST) -------------------- */
    public static function replace(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user) { header('Location:/login'); return ''; }
        $userId = (int)$user['id'];

        self::assertCsrf($_POST['csrf'] ?? '');
        $token = (string)($_POST['token'] ?? '');
        $payload = self::takeReplaceToken($token);
        if (!$payload || (int)$payload['user_id'] !== $userId) {
            http_response_code(400);
            return "Token inválido o expirado.";
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $sel = $pdo->prepare("SELECT id FROM uploads WHERE id=? AND user_id=?");
            $sel->execute([(int)$payload['replace_upload_id'], $userId]);
            if (!$sel->fetchColumn()) {
                $pdo->rollBack();
                return "No autorizado para reemplazar esa carga.";
            }

            $pdo->prepare("DELETE FROM upload_products WHERE upload_id=?")->execute([(int)$payload['replace_upload_id']]);
            $pdo->prepare("DELETE FROM upload_products_daily WHERE upload_id=?")->execute([(int)$payload['replace_upload_id']]);
            $pdo->prepare("DELETE FROM uploads WHERE id=?")->execute([(int)$payload['replace_upload_id']]);

            $row = self::processSingleJson($pdo, $userId, (string)$payload['json_path'], /*skipDup*/true);

            $pdo->commit();

            if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
            ob_start(); ?>
            <!doctype html><html lang="es"><head>
            <meta charset="utf-8"><title>Reemplazo completado</title>
            <link href="/assets/css/app.css" rel="stylesheet"></head><body>
            <?= self::headerNav('cargas') ?>
            <div class="container">
              <div class="card" style="max-width:760px;margin:0 auto">
                <h2>Reemplazo completado</h2>
                <p>Se reemplazó la carga anterior por el archivo <b><?= htmlspecialchars($row['archivo'] ?? '') ?></b>.</p>
                <a class="btn btn-primary" href="/uploads">Ver cargas</a>
                <a class="btn" href="/upload">Subir otro</a>
              </div>
            </div>
            </body></html>
            <?php return ob_get_clean();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            return "Error al reemplazar: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    /* -------------------- ELIMINAR (POST) -------------------- */
    public static function delete(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user) { header('Location:/login'); return ''; }
        $userId = (int)$user['id'];
        self::assertCsrf($_POST['csrf'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { http_response_code(400); return "ID inválido"; }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $chk = $pdo->prepare("SELECT id FROM uploads WHERE id=? AND user_id=?");
            $chk->execute([$id, $userId]);
            if (!$chk->fetchColumn()) { $pdo->rollBack(); return "No autorizado o no existe."; }
            $pdo->prepare("DELETE FROM upload_products WHERE upload_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM upload_products_daily WHERE upload_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM uploads WHERE id=?")->execute([$id]);
            $pdo->commit();

            if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
            ob_start(); ?>
            <!doctype html><html lang="es"><head>
            <meta charset="utf-8"><title>Carga eliminada</title>
            <link href="/assets/css/app.css" rel="stylesheet"></head><body>
            <?= self::headerNav('cargas') ?>
            <div class="container">
              <div class="card" style="max-width:760px;margin:0 auto">
                <h2>Registro eliminado</h2>
                <p>La carga #<?= $id ?> fue eliminada.</p>
                <a class="btn btn-primary" href="/uploads">Volver a cargas</a>
              </div>
            </div>
            </body></html>
            <?php return ob_get_clean();

        } catch (\Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            return "Error al eliminar: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }

    /* -------------------- EXPORT XLS -------------------- */
    public static function exportUpload(): string {
        $user = $_SESSION['user'] ?? null;
        if (!$user || empty($user['id'])) { header('Location:/login'); return ''; }
        $userId = (int)$user['id'];

        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) { http_response_code(400); return "ID inválido"; }

        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT u.*, s.rfc, s.permiso, s.clave_instalacion
            FROM uploads u
            JOIN stations s ON s.id = u.station_id
            WHERE u.id = :id AND u.user_id = :uid
        ");
        $stmt->execute([':id'=>$id, ':uid'=>$userId]);
        $up = $stmt->fetch();
        if (!$up) { http_response_code(404); return "No encontrado"; }

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        $fname = sprintf(
            "upload_%d_%s_%s_%s_%02d-%02d%s.xls",
            (int)$up['id'],
            preg_replace('/\W+/','',$up['rfc']),
            preg_replace('/\W+/','',$up['permiso']),
            preg_replace('/\W+/','',$up['clave_instalacion']),
            (int)$up['period_year'],
            (int)$up['period_month'],
            $up['period_day'] ? ('-'.str_pad((string)$up['period_day'],2,'0',STR_PAD_LEFT)) : ''
        );
        header('Content-Disposition: attachment; filename="'.$fname.'"');

        if (($up['report_type'] ?? 'monthly') === 'daily') {
            $stmt = $pdo->prepare("
                SELECT fecha, product_code, subproduct_code, brand,
                       vol_recepcion_l, imp_recepcion_mxn,
                       recepciones_count,
                       vol_entrega_l, imp_entrega_mxn,
                       existencia_ini_l, existencia_fin_l
                FROM upload_products_daily
                WHERE upload_id = ?
                ORDER BY fecha, product_code, subproduct_code
            ");
            $stmt->execute([$id]);
            $rows = $stmt->fetchAll();

            ob_start(); ?>
            <html>
            <head>
              <meta charset="utf-8">
              <style>
                table { border-collapse: collapse; }
                th, td { border: 1px solid #999; padding: 4px 6px; font-family: Arial, sans-serif; font-size: 12px; }
                th { background: #e6f2ff; }
                .num { mso-number-format:"0.000"; text-align:right; }
                .money { mso-number-format:"\\#\\,\\#\\#0.00"; text-align:right; }
              </style>
            </head>
            <body>
              <table>
                <tr><th colspan="11" style="font-size:14px;text-align:left">Resumen de carga (diaria)</th></tr>
                <tr><td>Upload</td><td><?= (int)$up['id'] ?></td><td>RFC</td><td><?= htmlspecialchars($up['rfc']) ?></td><td>Permiso</td><td><?= htmlspecialchars($up['permiso']) ?></td><td>Instalación</td><td><?= htmlspecialchars($up['clave_instalacion']) ?></td><td>Periodo</td><td><?= (int)$up['period_year'] ?>-<?= str_pad((string)$up['period_month'],2,'0',STR_PAD_LEFT) ?></td></tr>
              </table>
              <br>
              <table>
                <tr>
                  <th>Fecha</th><th>Producto</th><th>Subproducto</th><th>Marca</th>
                  <th>Recep. (L)</th><th>Recep. (MXN)</th><th># Recep.</th>
                  <th>Entregas (L)</th><th>Entregas (MXN)</th>
                  <th>Existencia inicial (L)</th><th>Existencia final (L)</th>
                </tr>
                <?php foreach($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['fecha']) ?></td>
                  <td><?= htmlspecialchars($r['product_code'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['subproduct_code'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['brand'] ?? '') ?></td>
                  <td class="num"><?= number_format((float)($r['vol_recepcion_l'] ?? 0), 3, '.', '') ?></td>
                  <td class="money"><?= number_format((float)($r['imp_recepcion_mxn'] ?? 0), 2, '.', '') ?></td>
                  <td class="num"><?= number_format((float)($r['recepciones_count'] ?? 0), 0, '.', '') ?></td>
                  <td class="num"><?= number_format((float)($r['vol_entrega_l'] ?? 0), 3, '.', '') ?></td>
                  <td class="money"><?= number_format((float)($r['imp_entrega_mxn'] ?? 0), 2, '.', '') ?></td>
                  <td class="num"><?= number_format((float)($r['existencia_ini_l'] ?? 0), 3, '.', '') ?></td>
                  <td class="num"><?= number_format((float)($r['existencia_fin_l'] ?? 0), 3, '.', '') ?></td>
                </tr>
                <?php endforeach; ?>
              </table>
            </body>
            </html>
            <?php
            return ob_get_clean();
        }

        // Mensual
        $stmt = $pdo->prepare("
            SELECT product_code, subproduct_code, brand,
                   vol_recepcion_l, imp_recepcion_mxn,
                   recepciones_count,
                   vol_entrega_l, imp_entrega_mxn,
                   existencia_ini_l, existencia_fin_l
            FROM upload_products
            WHERE upload_id = ?
            ORDER BY product_code, subproduct_code
        ");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();

        ob_start(); ?>
        <html>
        <head>
          <meta charset="utf-8">
          <style>
            table { border-collapse: collapse; }
            th, td { border: 1px solid #999; padding: 4px 6px; font-family: Arial, sans-serif; font-size: 12px; }
            th { background: #e6f2ff; }
            .num { mso-number-format:"0.000"; text-align:right; }
            .money { mso-number-format:"\\#\\,\\#\\#0.00"; text-align:right; }
          </style>
        </head>
        <body>
          <table>
            <tr><th colspan="10" style="font-size:14px;text-align:left">Resumen de carga</th></tr>
            <tr><td>Upload</td><td><?= (int)$up['id'] ?></td><td>RFC</td><td><?= htmlspecialchars($up['rfc']) ?></td><td>Permiso</td><td><?= htmlspecialchars($up['permiso']) ?></td><td>Instalación</td><td><?= htmlspecialchars($up['clave_instalacion']) ?></td><td>Periodo</td><td><?= (int)$up['period_year'] ?>-<?= str_pad((string)$up['period_month'],2,'0',STR_PAD_LEFT) ?></td></tr>
          </table>
          <br>
          <table>
            <tr>
              <th>Producto</th><th>Subproducto</th><th>Marca</th>
              <th>Recep. (L)</th><th>Recep. (MXN)</th><th># Recep.</th>
              <th>Entregas (L)</th><th>Entregas (MXN)</th>
              <th>Existencia inicial (L)</th><th>Existencia final (L)</th>
            </tr>
            <?php foreach($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['product_code'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['subproduct_code'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['brand'] ?? '') ?></td>
              <td class="num"><?= number_format((float)($r['vol_recepcion_l'] ?? 0), 3, '.', '') ?></td>
              <td class="money"><?= number_format((float)($r['imp_recepcion_mxn'] ?? 0), 2, '.', '') ?></td>
              <td class="num"><?= number_format((float)($r['recepciones_count'] ?? 0), 0, '.', '') ?></td>
              <td class="num"><?= number_format((float)($r['vol_entrega_l'] ?? 0), 3, '.', '') ?></td>
              <td class="money"><?= number_format((float)($r['imp_entrega_mxn'] ?? 0), 2, '.', '') ?></td>
              <td class="num"><?= number_format((float)($r['existencia_ini_l'] ?? 0), 3, '.', '') ?></td>
              <td class="num"><?= number_format((float)($r['existencia_fin_l'] ?? 0), 3, '.', '') ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /* -------------------- HELPERS -------------------- */

    private static function processSingleJson(\PDO $pdo, int $userId, string $jsonPath, bool $skipDup = false): array {
        $raw = file_get_contents($jsonPath);
        if ($raw === false) return ["error_archivo"=>basename($jsonPath), "error"=>"No se pudo leer el archivo"];

        $data = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_array($data)) {
            $conv = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $data = json_decode($conv, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if (!is_array($data)) return ["error_archivo"=>basename($jsonPath), "error"=>"JSON inválido"];

        $rfc   = $data['RfcContribuyente'] ?? null;
        $perm  = $data['NumPermiso'] ?? null;
        $inst  = $data['ClaveInstalacion'] ?? null;
        $vers  = $data['Version'] ?? null;

        // Detectar tipo (diario si trae FechaYHoraCorte o estructura de Tanque)
        $isDaily = !empty($data['FechaYHoraCorte']) || (!empty($data['Producto'][0]['Tanque']));
        $isMonthly = !$isDaily; // fallback

        // Periodo
        $fecha = $data['FechaYHoraReporteMes'] ?? ($data['FechaYHoraCorte'] ?? ($data['Fecha'] ?? null));
        $y = (int)substr((string)$fecha, 0, 4);
        $m = (int)substr((string)$fecha, 5, 2);
        $d = $isDaily ? (int)substr((string)$fecha, 8, 2) : null;

        if (!$rfc || !$perm || !$inst) return ["error_archivo"=>basename($jsonPath), "error"=>"Faltan RFC/Permiso/Instalación"];

        $stationId = self::ensureStation($pdo, $userId, $rfc, $perm, $inst);
        $sha = hash_file('sha256', $jsonPath);

        if (!$skipDup) {
            $du = $pdo->prepare("SELECT id FROM uploads WHERE user_id=? AND sha256=? LIMIT 1");
            $du->execute([$userId, $sha]);
            if ($du->fetchColumn()) return ["error_archivo"=>basename($jsonPath), "error"=>"Archivo duplicado (SHA-256)."];
        }

        $pdo->prepare("INSERT INTO uploads(user_id, station_id, filename, sha256,
                                           period_year, period_month, period_day,
                                           sat_version, report_type, status, errors_json)
                       VALUES(?,?,?,?,?,?,?,?,?,? ,NULL)")
            ->execute([$userId, $stationId, basename($jsonPath), $sha,
                       $y, $m, $d, $vers, $isDaily ? 'daily' : 'monthly', 'warning']);
        $uploadId = (int)$pdo->lastInsertId();

        $prod = $data['Producto'] ?? [];

        if ($isDaily) {
            // Diario (por fecha del corte)
            foreach ($prod as $p) {
                $pc    = $p['ClaveProducto']    ?? '';
                $sp    = $p['ClaveSubProducto'] ?? null;
                $brand = $p['MarcaComercial']   ?? null;

                $sumRecL  = 0.0; $sumRecMx = 0.0; $sumEntL = 0.0; $sumEntMx = 0.0;
                $existIni = null; $existFin = null;

                foreach (($p['Tanque'] ?? []) as $t) {
                    if (isset($t['Existencias']['VolumenExistenciasInicialesDia']))
                        $existIni = (float)$t['Existencias']['VolumenExistenciasInicialesDia'];
                    if (isset($t['Existencias']['VolumenExistenciasAnterior']))
                        $existIni = (float)$t['Existencias']['VolumenExistenciasAnterior'];
                    if (isset($t['Existencias']['VolumenExistencias']))
                        $existFin = (float)$t['Existencias']['VolumenExistencias'];

                    if (isset($t['Recepciones']['SumaVolumenRecepcion']['ValorNumerico']))
                        $sumRecL += (float)$t['Recepciones']['SumaVolumenRecepcion']['ValorNumerico'];
                    if (isset($t['Recepciones']['SumaCompras']))
                        $sumRecMx += (float)$t['Recepciones']['SumaCompras'];

                    if (isset($t['Entregas']['SumaVolumenEntregado']['ValorNumerico']))
                        $sumEntL += (float)$t['Entregas']['SumaVolumenEntregado']['ValorNumerico'];
                    if (isset($t['Entregas']['SumaVentas']))
                        $sumEntMx += (float)$t['Entregas']['SumaVentas'];
                }

                $pdo->prepare("INSERT INTO upload_products_daily(
                    upload_id, fecha, product_code, subproduct_code, brand,
                    existencia_ini_l, vol_recepcion_l, imp_recepcion_mxn, recepciones_count,
                    vol_entrega_l, imp_entrega_mxn, existencia_fin_l
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                    $uploadId, sprintf('%04d-%02d-%02d',$y,$m,(int)($d ?? 1)), $pc, $sp, $brand,
                    $existIni, $sumRecL, $sumRecMx, 0, $sumEntL, $sumEntMx, $existFin
                ]);
            }

            $pdo->prepare("UPDATE uploads SET status='valid' WHERE id=?")->execute([$uploadId]);

            return [
              "archivo"=>basename($jsonPath),
              "rfc"=>$rfc, "permiso"=>$perm, "instalacion"=>$inst, "version"=>$vers,
              "año"=>$y, "mes"=>$m, "status"=>"valid", "tipo"=>"daily"
            ];
        }

        // -------- Mensual ----------
        $totales = ['ventas_l'=>0.0,'compras_l'=>0.0,'rec_count'=>0];
        foreach ($prod as $p) {
            $pc = $p['ClaveProducto'] ?? '';
            $sp = $p['ClaveSubProducto'] ?? null;
            $brand = $p['MarcaComercial'] ?? null;
            $rep = $p['ReporteDeVolumenMensual'] ?? [];

            $vol_rec = self::num($rep,'Recepciones','SumaVolumenRecepcionMes','ValorNumerico');
            $imp_rec = self::num($rep,'Recepciones','ImporteTotalRecepcionesMensual');
            $rec_cnt = (int)($rep['Recepciones']['TotalRecepcionesMes'] ?? 0);
            $rec_docs= (int)($rep['Recepciones']['TotalDocumentosMes'] ?? 0);

            $vol_ent = self::num($rep,'Entregas','SumaVolumenEntregadoMes','ValorNumerico');
            $imp_ent = self::num($rep,'Entregas','ImporteTotalEntregasMes');

            $exist_fin = self::num($rep,'ControlDeExistencias','VolumenExistenciasMes');

            $exist_ini = null;
            if (isset($rep['ControlDeExistencias']['VolumenExistenciasInicialesMes'])
                && $rep['ControlDeExistencias']['VolumenExistenciasInicialesMes'] !== '') {
                $exist_ini = (float)$rep['ControlDeExistencias']['VolumenExistenciasInicialesMes'];
            } else {
                // misma estación (usuario+station_id), sólo mes anterior exacto
                $exist_ini = self::prevEndingInventorySameStationMonthly($pdo, $userId, $stationId, $pc, $sp, $y, $m);
            }

            $pdo->prepare("INSERT INTO upload_products(
                upload_id, product_code, subproduct_code, brand,
                existencia_ini_l, vol_recepcion_l, imp_recepcion_mxn, recepciones_count, recepciones_docs,
                vol_entrega_l, imp_entrega_mxn, existencia_fin_l
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $uploadId, $pc, $sp, $brand,
                $exist_ini, $vol_rec, $imp_rec, $rec_cnt, $rec_docs,
                $vol_ent,  $imp_ent,  $exist_fin
            ]);

            $totales['compras_l'] += $vol_rec;
            $totales['ventas_l']  += $vol_ent;
            $totales['rec_count'] += $rec_cnt;
        }

        $status = ($totales['ventas_l'] == 0.0) ? 'warning' : 'valid';
        $pdo->prepare("UPDATE uploads SET status=? WHERE id=?")->execute([$status, $uploadId]);

        return [
          "archivo"=>basename($jsonPath),
          "rfc"=>$rfc, "permiso"=>$perm, "instalacion"=>$inst, "version"=>$vers,
          "año"=>$y, "mes"=>$m,
          "vol_compras_l"=>$totales['compras_l'],
          "vol_ventas_l"=>$totales['ventas_l'],
          "num_recepciones"=>$totales['rec_count'],
          "status"=>$status
        ];
    }

    private static function moveSafe(string $tmp, string $dir, string $origName): array {
        if (!is_dir($dir)) mkdir($dir, 0770, true);
        $base = preg_replace('/[^A-Za-z0-9._-]/','_', $origName);
        $final = $dir . '/' . uniqid('up_') . '_' . $base;
        if (!move_uploaded_file($tmp, $final)) {
            throw new \RuntimeException("No se pudo mover el archivo subido");
        }
        return ['path'=>$final, 'name'=>basename($final)];
    }

    private static function unzipJson(string $zipPath, string $outDir): array {
        $list = [];
        $za = new \ZipArchive();
        $ret = $za->open($zipPath);
        if ($ret !== true) throw new \RuntimeException("No se pudo abrir el ZIP (código $ret)");
        @mkdir($outDir, 0770, true);
        if (!$za->extractTo($outDir)) { $za->close(); throw new \RuntimeException("Falló la extracción del ZIP"); }
        $za->close();

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($outDir));
        foreach ($rii as $file) if ($file->isFile() && strtolower($file->getExtension()) === 'json') $list[] = $file->getPathname();
        return $list;
    }

    private static function num(array $rep, ...$keys): float {
        $cur = $rep;
        foreach ($keys as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) return 0.0;
            $cur = $cur[$k];
        }
        if ($cur === null || $cur === '') return 0.0;
        return (float)$cur;
    }

    private static function is_assoc($arr): bool {
        if (!is_array($arr)) return false;
        return array_keys($arr) !== range(0, count($arr)-1);
    }

    private static function ensureStation(\PDO $pdo, int $userId, string $rfc, string $perm, string $inst): int {
        $sel = $pdo->prepare("
            SELECT id FROM stations
            WHERE user_id = ? AND rfc = ? AND permiso = ? AND clave_instalacion = ?
            LIMIT 1
        ");
        $sel->execute([$userId, $rfc, $perm, $inst]);
        $id = $sel->fetchColumn();
        if ($id) return (int)$id;

        $ins = $pdo->prepare("
            INSERT INTO stations(user_id, rfc, permiso, clave_instalacion, nombre)
            VALUES(?,?,?,?,NULL)
        ");
        $ins->execute([$userId, $rfc, $perm, $inst]);
        return (int)$pdo->lastInsertId();
    }

    /** Mes anterior exacto, misma estación (user+station_id), sólo 'monthly'. */
    private static function prevEndingInventorySameStationMonthly(
        \PDO $pdo,
        int $userId,
        int $stationId,
        string $product,
        ?string $subproduct,
        int $year,
        int $month
    ): ?float {
        $py = $year; $pm = $month - 1;
        if ($pm <= 0) { $pm = 12; $py = $year - 1; }
        $sql = "
            SELECT up.existencia_fin_l
            FROM uploads u
            JOIN upload_products up ON up.upload_id = u.id
            WHERE u.user_id = ?
              AND u.station_id = ?
              AND u.period_year  = ?
              AND u.period_month = ?
              AND up.product_code = ?
              AND (up.subproduct_code <=> ?)
              AND (u.report_type = 'monthly' OR u.report_type IS NULL)
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$userId, $stationId, $py, $pm, $product, $subproduct]);
        $val = $st->fetchColumn();
        return ($val === false) ? null : (float)$val;
    }

    /** Defensivo: asegura columnas en uploads. */
    private static function ensureSchemaUploads(\PDO $pdo): void {
        try {
            if (self::columnMissing($pdo, 'uploads', 'report_type')) {
                $pdo->exec("ALTER TABLE uploads ADD COLUMN report_type ENUM('monthly','daily') NOT NULL DEFAULT 'monthly' AFTER sat_version");
            }
            if (self::columnMissing($pdo, 'uploads', 'period_day')) {
                $pdo->exec("ALTER TABLE uploads ADD COLUMN period_day TINYINT NULL AFTER period_month");
            }
            if (self::columnMissing($pdo, 'uploads', 'created_at')) {
                $pdo->exec("ALTER TABLE uploads ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            }
        } catch (\Throwable $e) { error_log("[schema uploads] ".$e->getMessage()); }
    }

    /** Defensivo: asegura columnas en detalle mensual. */
    private static function ensureSchemaUploadProducts(\PDO $pdo): void {
        try {
            $needIni = self::columnMissing($pdo, 'upload_products', 'existencia_ini_l');
            $needCnt = self::columnMissing($pdo, 'upload_products', 'recepciones_count');
            $needDoc = self::columnMissing($pdo, 'upload_products', 'recepciones_docs');
            if ($needIni || $needCnt || $needDoc) {
                $sql = "ALTER TABLE upload_products ";
                $parts = [];
                if ($needIni) $parts[] = "ADD COLUMN existencia_ini_l DECIMAL(14,3) NULL AFTER brand";
                if ($needCnt) $parts[] = "ADD COLUMN recepciones_count INT NOT NULL DEFAULT 0 AFTER imp_recepcion_mxn";
                if ($needDoc) $parts[] = "ADD COLUMN recepciones_docs  INT NOT NULL DEFAULT 0 AFTER recepciones_count";
                $sql .= implode(', ', $parts);
                $pdo->exec($sql);
            }
        } catch (\Throwable $e) { error_log("[schema upload_products] ".$e->getMessage()); }
    }

    /** Defensivo: crea tabla diaria si no existe. */
    private static function ensureSchemaUploadProductsDaily(\PDO $pdo): void {
        try {
            $pdo->exec("
            CREATE TABLE IF NOT EXISTS upload_products_daily (
              id                INT AUTO_INCREMENT PRIMARY KEY,
              upload_id         INT NOT NULL,
              fecha             DATE NOT NULL,
              product_code      VARCHAR(32)  NULL,
              subproduct_code   VARCHAR(32)  NULL,
              brand             VARCHAR(120) NULL,
              existencia_ini_l  DECIMAL(14,3) NULL,
              vol_recepcion_l   DECIMAL(14,3) NOT NULL DEFAULT 0,
              imp_recepcion_mxn DECIMAL(14,2) NOT NULL DEFAULT 0,
              recepciones_count INT NOT NULL DEFAULT 0,
              vol_entrega_l     DECIMAL(14,3) NOT NULL DEFAULT 0,
              imp_entrega_mxn   DECIMAL(14,2) NOT NULL DEFAULT 0,
              existencia_fin_l  DECIMAL(14,3) NULL,
              KEY (upload_id),
              KEY (fecha),
              KEY (product_code, subproduct_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (\Throwable $e) { error_log("[schema upload_products_daily] ".$e->getMessage()); }
    }

    private static function columnMissing(\PDO $pdo, string $table, string $column): bool {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() == 0;
    }

    private static function assertZipSupport(): void {
        if (!class_exists('\ZipArchive')) {
            throw new \RuntimeException("Falta la extensión php-zip (instala php-zip y recarga Apache).");
        }
    }

    private static function uploadErrorMessage(int $err): string {
        return match ($err) {
            UPLOAD_ERR_INI_SIZE   => "El archivo excede upload_max_filesize.",
            UPLOAD_ERR_FORM_SIZE  => "El archivo excede MAX_FILE_SIZE del formulario.",
            UPLOAD_ERR_PARTIAL    => "El archivo se subió parcialmente.",
            UPLOAD_ERR_NO_FILE    => "No se seleccionó archivo.",
            UPLOAD_ERR_NO_TMP_DIR => "No existe el directorio temporal.",
            UPLOAD_ERR_CANT_WRITE => "No se pudo escribir en disco.",
            UPLOAD_ERR_EXTENSION  => "Una extensión de PHP detuvo la subida.",
            default               => "Error de subida (código $err).",
        };
    }
}
