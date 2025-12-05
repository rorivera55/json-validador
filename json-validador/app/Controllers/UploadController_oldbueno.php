<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DB;

class UploadController {

    /* -------------------- HEADER / NAV -------------------- */
    private static function headerNav(string $active = ''): string {
        // $active: 'inicio' | 'cargas' | 'subir'
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

    /* -------------------- VISTAS -------------------- */
    public static function form(): string {
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
            <h2 style="margin-bottom:6px">Subir archivo ZIP o JSON</h2>
            <p class="muted" style="margin:0 0 16px 0">Formato EXO del Anexo 30 (SAT). El ZIP puede contener múltiples JSON.</p>
            <form method="post" enctype="multipart/form-data" action="/upload">
              <p><input type="file" name="archivo" accept=".zip,.json" required
                        style="padding:.8rem;border:1px solid var(--line);border-radius:12px;width:100%"></p>
              <button type="submit" class="btn btn-primary">Procesar archivo</button>
              <a href="/uploads" class="btn">Ver cargas recientes</a>
            </form>
          </div>
          <div class="footer">© SOPMEX · Validador Anexo 30</div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

public static function list(): string {
    $pdo = DB::pdo();
    $q = $pdo->query("
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
        GROUP BY u.id
        ORDER BY u.id DESC
        LIMIT 200
    ");
    $rows = $q->fetchAll();

    ob_start(); ?>
    <!doctype html><html lang="es"><head>
    <meta charset="utf-8">
    <title>Cargas · Validador SAT</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/css/app.css" rel="stylesheet">
    </head><body>
    <?= self::headerNav('cargas') ?>
    <div class="container">
      <h2 style="margin-bottom:14px">Cargas recientes</h2>
      <div class="table-wrap" role="region" aria-label="Cargas recientes" tabindex="0">
        <table>
          <colgroup>
            <col style="width:70px">
            <col>
            <col style="width:140px">
            <col style="width:140px">
            <col style="width:140px">
            <col style="width:110px">
            <col style="width:140px">
            <col style="width:140px">
            <col style="width:130px">
            <col style="width:120px"> <!-- Estatus -->
            <col style="width:120px"> <!-- Acciones -->
          </colgroup>
          <thead>
            <tr>
              <th>ID</th><th>Archivo</th><th>RFC</th><th>Permiso</th><th>Instalación</th>
              <th>Periodo</th><th>Compras (L)</th><th>Ventas (L)</th><th># Recepciones</th><th>Estatus</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($rows as $r):
              $status = $r['status'];
              $class = $status === 'valid' ? 'ok' : ($status === 'warning' ? 'warn' : 'bad');
              $url = "/upload-view?id=".(int)$r['id'];
          ?>
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
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:12px">
        <a href="/upload" class="btn btn-primary">Subir nuevo ZIP/JSON</a>
        <a href="/" class="btn">Inicio</a>
      </div>

      <div class="footer">© SOPMEX · Validador Anexo 30</div>
    </div>
    </body></html>
    <?php return ob_get_clean();
}


    public static function view(): string {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) { http_response_code(400); return "ID inválido"; }

        $pdo = DB::pdo();

        $stmt = $pdo->prepare("
            SELECT u.*, s.rfc, s.permiso, s.clave_instalacion
            FROM uploads u
            JOIN stations s ON s.id = u.station_id
            WHERE u.id = ?
        ");
        $stmt->execute([$id]);
        $up = $stmt->fetch();
        if (!$up) { http_response_code(404); return "No encontrado"; }

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
              Periodo: <b class="mono"><?= (int)$up['period_year'] ?>-<?= str_pad((string)$up['period_month'],2,'0',STR_PAD_LEFT) ?></b> ·
              Versión SAT: <b><?= htmlspecialchars((string)$up['sat_version'], ENT_QUOTES, 'UTF-8') ?></b> ·
              Estado: <b class="mono"><?= htmlspecialchars($up['status'], ENT_QUOTES, 'UTF-8') ?></b><br>
              Hash (SHA-256): <span class="mono"><?= htmlspecialchars($up['sha256'], ENT_QUOTES, 'UTF-8') ?></span>
            </p>
            <div style="margin-top:8px">
              <a href="/uploads" class="btn">? Volver a cargas</a>
              <a href="/upload" class="btn">Subir otra</a>
              <a href="/" class="btn">Inicio</a>
            </div>
          </div>

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
                  <th>Entregas (L)</th><th>Entregas (MXN)</th>
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

          <div class="footer">© SOPMEX · Validador Anexo 30</div>
        </div>
        </body></html>
        <?php return ob_get_clean();
    }

    /* -------------------- HANDLER -------------------- */
    public static function handle(): string {
        try {
            // 1) Validar upload
            if (!isset($_FILES['archivo'])) {
                http_response_code(400);
                return "No se recibió ningún archivo.";
            }
            $err = (int)($_FILES['archivo']['error'] ?? 0);
            if ($err !== UPLOAD_ERR_OK) {
                return self::uploadErrorMessage($err);
            }

            $tmp  = $_FILES['archivo']['tmp_name'] ?? '';
            $name = $_FILES['archivo']['name'] ?? 'archivo';
            $size = (int)($_FILES['archivo']['size'] ?? 0);

            if (!is_uploaded_file($tmp)) {
                http_response_code(400);
                return "Subida inválida (archivo temporal no encontrado).";
            }

            if ($size > 60*1024*1024) { // 60 MB
                return "Archivo demasiado grande.";
            }

            $uploadsDir = __DIR__ . '/../../storage/uploads';
            $tmpDir     = __DIR__ . '/../../storage/tmp';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $files = [];

            if ($ext === 'json') {
                $safe = self::moveSafe($tmp, $uploadsDir, $name);
                $files[] = $safe['path'];
            } elseif ($ext === 'zip') {
                self::assertZipSupport();
                $safe = self::moveSafe($tmp, $tmpDir, $name);
                $jsonList = self::unzipJson($safe['path'], $tmpDir . '/unz_' . uniqid());
                if (!$jsonList) return "ZIP sin JSON válidos.";
                $files = $jsonList;
            } else {
                return "Extensión no permitida (usa .json o .zip).";
            }

            // 2) Procesar
            $pdo = DB::pdo();
            self::ensureSchemaUploadProducts($pdo); // defensivo

            $orgId = 1; // demo

            $rows = [];
            foreach ($files as $jsonPath) {
                $raw = file_get_contents($jsonPath);
                if ($raw === false) {
                    $rows[] = ["archivo"=>basename($jsonPath), "error"=>"No se pudo leer el archivo"];
                    continue;
                }

                // Intentamos decodificar asumiendo UTF-8; si falla, convertimos desde latin1/CP1252
                $data = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                if (!is_array($data)) {
                    $conv = @mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
                    $data = json_decode($conv, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
                }
                if (!is_array($data)) {
                    $rows[] = ["archivo"=>basename($jsonPath), "error"=>"JSON inválido"];
                    continue;
                }

                $rfc   = $data['RfcContribuyente'] ?? null;
                $perm  = $data['NumPermiso'] ?? null;
                $inst  = $data['ClaveInstalacion'] ?? null;
                $vers  = $data['Version'] ?? null;
                $fecha = $data['FechaYHoraReporteMes'] ?? null;
                $y = (int)substr((string)$fecha, 0, 4);
                $m = (int)substr((string)$fecha, 5, 2);

                if (!$rfc || !$perm || !$inst) {
                    $rows[] = ["archivo"=>basename($jsonPath), "error"=>"Faltan RFC/Permiso/Instalación"];
                    continue;
                }

                $stationId = self::ensureStation($pdo, $orgId, $rfc, $perm, $inst);

                $sha = hash_file('sha256', $jsonPath);
                $stmt = $pdo->prepare("INSERT INTO uploads(org_id, station_id, filename, sha256, period_year, period_month, sat_version, status, errors_json)
                                       VALUES(?,?,?,?,?,?,?,?,NULL)");
                $stmt->execute([$orgId, $stationId, basename($jsonPath), $sha, $y, $m, $vers, 'warning']);
                $uploadId = (int)$pdo->lastInsertId();

                $prod = $data['Producto'] ?? [];
                $totales = ['ventas_l'=>0.0,'compras_l'=>0.0,'rec_count'=>0];

                foreach ($prod as $p) {
                    $pc = $p['ClaveProducto'] ?? '';
                    $sp = $p['ClaveSubProducto'] ?? null;
                    $brand = $p['MarcaComercial'] ?? null;
                    $rep = $p['ReporteDeVolumenMensual'] ?? [];

                    // Lecturas
                    $vol_rec = self::num($rep,'Recepciones','SumaVolumenRecepcionMes','ValorNumerico');
                    $imp_rec = self::num($rep,'Recepciones','ImporteTotalRecepcionesMensual');
                    $rec_cnt  = (int)($rep['Recepciones']['TotalRecepcionesMes'] ?? 0);
                    $rec_docs = (int)($rep['Recepciones']['TotalDocumentosMes'] ?? 0);

                    $vol_ent = self::num($rep,'Entregas','SumaVolumenEntregadoMes','ValorNumerico');
                    $imp_ent = self::num($rep,'Entregas','ImporteTotalEntregasMes');

                    $exist_fin = self::num($rep,'ControlDeExistencias','VolumenExistenciasMes');

                    // Inicial: JSON -> si no viene, cierre del mes anterior por ORG+PERMISO+producto(+sub)
                    $exist_ini = null;
                    if (isset($rep['ControlDeExistencias']['VolumenExistenciasInicialesMes'])
                        && $rep['ControlDeExistencias']['VolumenExistenciasInicialesMes'] !== '') {
                        $exist_ini = (float)$rep['ControlDeExistencias']['VolumenExistenciasInicialesMes'];
                    } else {
                        $exist_ini = self::prevEndingInventoryByPermiso($pdo, $orgId, $perm, $pc, $sp, $y, $m);
                    }

                    $ins = $pdo->prepare("INSERT INTO upload_products(
                              upload_id, product_code, subproduct_code, brand,
                              existencia_ini_l,
                              vol_recepcion_l, imp_recepcion_mxn, recepciones_count, recepciones_docs,
                              vol_entrega_l,   imp_entrega_mxn,  existencia_fin_l
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
                    $ins->execute([
                        $uploadId, $pc, $sp, $brand,
                        $exist_ini,
                        $vol_rec, $imp_rec, $rec_cnt, $rec_docs,
                        $vol_ent, $imp_ent, $exist_fin
                    ]);

                    $totales['compras_l'] += $vol_rec;
                    $totales['ventas_l']  += $vol_ent;
                    $totales['rec_count'] += $rec_cnt;
                }

                $status = ($totales['ventas_l'] == 0.0) ? 'warning' : 'valid';
                $pdo->prepare("UPDATE uploads SET status=? WHERE id=?")->execute([$status, $uploadId]);

                $rows[] = [
                  "archivo"=>basename($jsonPath),
                  "rfc"=>$rfc, "permiso"=>$perm, "instalacion"=>$inst, "version"=>$vers,
                  "año"=>$y, "mes"=>$m,
                  "vol_compras_l"=>$totales['compras_l'],
                  "vol_ventas_l"=>$totales['ventas_l'],
                  "num_recepciones"=>$totales['rec_count'],
                  "status"=>$status
                ];
            }

            // 3) Render resultado
            ob_start(); ?>
            <!doctype html><html lang="es"><head>
            <meta charset="utf-8">
            <title>Resultado de carga · Validador SAT</title>
            <link href="/assets/css/app.css" rel="stylesheet">
            </head><body>
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
                    <td><?= htmlspecialchars($r['archivo']) ?></td>
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
                <a href="/upload" class="btn btn-primary">? Subir otro</a>
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

    /* -------------------- HELPERS -------------------- */

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
        if ($ret !== true) {
            throw new \RuntimeException("No se pudo abrir el ZIP (código $ret)");
        }
        @mkdir($outDir, 0770, true);
        if (!$za->extractTo($outDir)) {
            $za->close();
            throw new \RuntimeException("Falló la extracción del ZIP");
        }
        $za->close();

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($outDir));
        foreach ($rii as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
                $list[] = $file->getPathname();
            }
        }
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

    private static function ensureStation(\PDO $pdo, int $orgId, string $rfc, string $perm, string $inst): int {
        $sel = $pdo->prepare("SELECT id FROM stations WHERE org_id=? AND rfc=? AND permiso=? AND clave_instalacion=?");
        $sel->execute([$orgId,$rfc,$perm,$inst]);
        $id = $sel->fetchColumn();
        if ($id) return (int)$id;
        $ins = $pdo->prepare("INSERT INTO stations(org_id, rfc, permiso, clave_instalacion, nombre) VALUES(?,?,?,?,NULL)");
        $ins->execute([$orgId,$rfc,$perm,$inst]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Existencia final del último mes anterior disponible para misma ORG + PERMISO + producto/subproducto.
     */
    private static function prevEndingInventoryByPermiso(
        \PDO $pdo,
        int $orgId,
        string $permiso,
        string $product,
        ?string $subproduct,
        int $year,
        int $month
    ): ?float {
        $period = $year * 100 + $month;
        $sql = "
            SELECT up.existencia_fin_l
            FROM uploads u
            JOIN stations s         ON s.id = u.station_id
            JOIN upload_products up ON up.upload_id = u.id
            WHERE u.org_id = ?
              AND s.permiso = ?
              AND (u.period_year*100 + u.period_month) < ?
              AND up.product_code = ?
              AND (up.subproduct_code <=> ?)
            ORDER BY u.period_year DESC, u.period_month DESC
            LIMIT 1
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId, $permiso, $period, $product, $subproduct]);
        $val = $st->fetchColumn();
        return ($val === false) ? null : (float)$val;
    }

    /** Crea columnas nuevas si no existen (defensivo). */
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
        } catch (\Throwable $e) {
            error_log("[schema] " . $e->getMessage());
        }
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
