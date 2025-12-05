<?php
declare(strict_types=1);

namespace App\Controllers;

class HomeController {

    private static function headerNav(string $active = ''): string {
        $is = fn($k) => $active === $k ? 'active' : '';
        $user = $_SESSION['user'] ?? null;
        ob_start(); ?>
        <header class="app-header">
          <div class="bar">
            <a class="brand" href="/" aria-label="SOPMEX Â· Validador SAT">
              <img class="brand-logo" src="/assets/img/sopmex-logo.png" alt="SOPMEX">
            </a>
            <nav class="nav" aria-label="Principal">
              <a class="<?= $is('inicio') ?>" href="/">Inicio</a>
              <a class="<?= $is('cargas') ?>" href="/uploads">Cargas</a>
              <a class="<?= $is('subir') ?>" href="/upload">Subir ZIP/JSON</a>
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

    public static function home(): string {
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        $user = $_SESSION['user'] ?? null;
        $ctaHref = $user ? '/dashboard' : '/register';
        $ctaText = $user ? 'Ir a mi panel' : 'Crear cuenta gratis';
        ob_start(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SOPMEX Â· Control VolumÃ©trico y Validador SAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Control volumÃ©trico confiable. Carga mÃºltiple ZIP/JSON, multiusuario y multicliente, inventario inicial inferido, panel admin con actividad. SOPMEX te ayuda a cumplir el Anexo 30.">
  <link rel="preload" as="image" href="/assets/img/sopmex-logo.png">
  <link href="/assets/css/app.css" rel="stylesheet">
  <style>
    /* ---- Landing styles ---- */
    .hero{
      padding:64px 0 40px;
      background: radial-gradient(1200px 600px at 0% -10%, rgba(0,174,255,.15), transparent 60%),
                  radial-gradient(1200px 600px at 100% -20%, rgba(0,86,179,.12), transparent 60%);
      border-bottom:1px solid var(--line);
    }
    .hero .wrap{max-width:1080px;margin:0 auto;display:grid;grid-template-columns:1.2fr .8fr;gap:28px;align-items:center;padding:0 16px}
    .hero h1{font-size:clamp(28px,4vw,42px);line-height:1.1;margin:.2rem 0 .6rem}
    .hero p.lead{font-size:clamp(16px,2.2vw,20px);color:var(--muted);margin:0 0 18px}
    .hero-figure{display:flex;align-items:center;justify-content:center}
    .hero-figure img{max-width:360px;width:100%;filter:drop-shadow(0 10px 30px rgba(0,0,0,.18))}
    .cta-row{display:flex;gap:10px;flex-wrap:wrap}
    .btn-ghost{background:transparent;border:1px solid var(--line)}
    .badges{display:flex;gap:14px;flex-wrap:wrap;margin-top:14px;color:var(--muted);font-size:13px}
    .grid{max-width:1080px;margin:28px auto;padding:0 16px;display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
    .card.kpi{grid-column:span 6}
    .card.feature{grid-column:span 6}
    @media (max-width: 860px){
      .hero .wrap{grid-template-columns:1fr}
      .card.kpi,.card.feature{grid-column:span 12}
    }
    .feature h3{margin:.3rem 0}
    .feature p{color:var(--muted);margin:0}
    .trust{max-width:1080px;margin:8px auto 0;padding:0 16px;color:var(--muted);font-size:14px}
    .footer-cta{margin:26px 0 10px;text-align:center}
  </style>
</head>
<body>
  <?= self::headerNav('inicio') ?>

  <section class="hero">
    <div class="wrap">
      <div>
        <img src="/assets/img/sopmex-logo.png" alt="SOPMEX" style="height:54px;width:auto">
        <h1>Control volumÃ©trico simple, preciso y listo para cumplir.</h1>
        <p class="lead">
          Centraliza tus reportes del SAT, valida JSON/ZIP en segundos y mantÃ©n el historial por estaciÃ³n.
          Hecho para multiusuario y multicliente, con trazabilidad completa y panel de actividad.
        </p>
        <div class="cta-row">
          <a class="btn btn-primary" href="<?= $ctaHref ?>"><?= htmlspecialchars($ctaText) ?></a>
          <a class="btn btn-ghost" href="/upload">Probar carga ahora</a>
          <a class="btn" href="https://sopmex.com.mx" target="_blank" rel="noopener">Conoce SOPMEX</a>
        </div>
        <div class="badges">
          <span>âœ“ Cumplimiento Anexo 30</span>
          <span>âœ“ Seguridad y privacidad</span>
          <span>âœ“ ExportaciÃ³n a Excel</span>
          <span>âœ“ Soporte en espaÃ±ol</span>
        </div>
      </div>
      <div class="hero-figure">
        <img src="/assets/img/sopmex-logo.png" alt="SOPMEX esfera">
      </div>
    </div>
  </section>
<section class="grid">
  <!-- Benefit 1 -->
  <div class="card feature">
    <h3>Revisión preventiva SAT</h3>
    <p>
      Detectamos errores <b>antes</b> del envío: estructura del JSON, periodos,
      duplicados por SHA, sumatorias y campos obligatorios. Sube con confianza.
    </p>
  </div>

  <!-- Benefit 2 -->
  <div class="card feature">
    <h3>Nos importan nuestros clientes</h3>
    <p>
      Acompañamiento 1 a 1, soporte en español y seguimiento. En SOPMEX nos
      preocupamos por tu cumplimiento y por la continuidad de tu operación.
    </p>
  </div>

  <!-- Benefit 3 -->
  <div class="card feature">
    <h3>Seguridad & cumplimiento</h3>
    <p>
      Multiusuario y multicliente con controles de acceso, bitácora de actividad,
      y exportación a Excel para tus auditorías internas.
    </p>
  </div>

  <!-- Benefit 4 -->
  <div class="card feature">
    <h3>Operación sin fricción</h3>
    <p>
      Carga múltiple ZIP/JSON, manejo <b>mensual y diario</b> en pestañas separadas,
      inventario inicial inferido sólo del mes inmediato anterior (mismo usuario y permiso).
    </p>
  </div>

  <!-- Mini KPIs o “por qué SOPMEX” -->
  <div class="card kpi">
    <h3>Listo para enviar al SAT</h3>
    <p>Valida, corrige y vuelve a intentar. Reduce rechazos y evita retrabajos.</p>
  </div>
  <div class="card kpi">
    <h3>Somos tu aliado</h3>
    <p>Consultoría y servicios integrales de control volumétrico. <a href="https://sopmex.com.mx" target="_blank" rel="noopener">Conoce más</a>.</p>
  </div>
</section>
  


  <p class="trust">
    SOPMEX es tu aliado en control volumÃ©trico. Â¿Quieres saber mÃ¡s de servicios integrales y hardware? Visita
    <a href="https://sopmex.com.mx" target="_blank" rel="noopener">sopmex.com.mx</a>.
  </p>

  <div class="footer-cta">
    <a class="btn btn-primary" href="<?= $ctaHref ?>"><?= htmlspecialchars($ctaText) ?></a>
    <a class="btn" href="https://sopmex.com.mx" target="_blank" rel="noopener">Ir a sopmex.com.mx</a>
  </div>

  <div class="footer">Â© SOPMEX Â· Validador Anexo 30</div>
</body>
</html>
<?php return ob_get_clean();
    }
}
