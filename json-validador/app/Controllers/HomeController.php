<?php
declare(strict_types=1);

namespace App\Controllers;

final class HomeController
{
    /* ---------- Header / Nav ---------- */
 private static function headerNav(string $active = '', ?bool $showDataNav = null): string {
    $user = $_SESSION['user'] ?? null;
    // Por defecto: solo mostrar links de datos si HAY sesi�n
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

    /* ---------- Home ---------- */
    public static function home(): string
    {
        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        $user = $_SESSION['user'] ?? null;
        $cta = $user ? '/upload' : '/register';

        ob_start(); ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SOPMEX · Validador SAT</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/assets/css/app.css" rel="stylesheet">
  <style>
    /* ====== LOOK & FEEL del Home moderno ====== */
    :root{
      --bg: #0b1220; --ink:#0f172a; --muted:#94a3b8; --line:#e5ecf5;
      --brand:#0ea5e9; --brand-ink:#0b5fa6; --glow: 0 10px 40px rgba(14,165,233,.35);
    }
    body{ background: radial-gradient(1000px 600px at 70% -10%, #0ea5e93a, transparent 60%),
                        radial-gradient(900px 500px at -10% 10%, #22d3ee2b, transparent 55%); }
    .glass{ backdrop-filter: blur(8px); background: rgba(255,255,255,.6); }
    .bar{ max-width:1200px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; }
    .brand{ display:flex; align-items:center; gap:10px; }
    .brand-logo{ height:38px; width:auto; border-radius:10px; }
    .brand-name{ font-weight:800; letter-spacing:.2px; }
    .brand-name span{ display:block; font-weight:500; color:#55697e; font-size:12px; margin-top:-2px; }

    .nav a{ margin-left:12px; }
    .nav-user{ color:#55697e; margin:0 6px 0 8px; }

    /* Hero */
    .hero-wrap{ position:relative; overflow:hidden; }
    .aurora::before, .aurora::after{
      content:""; position:absolute; inset:-20%;
      background: conic-gradient(from 90deg, #0ea5e9 0%, #22d3ee 18%, #38bdf8 33%, transparent 40%);
      filter: blur(60px); opacity:.35; animation: spin 26s linear infinite;
    }
    .aurora::after{ animation-direction: reverse; opacity:.28; }
    @keyframes spin{ to{ transform: rotate(360deg) } }

    .hero{ max-width:1200px; margin:0 auto; padding:72px 16px 18px; position:relative; z-index:1;}
    .headline{ font-size: clamp(26px, 4vw, 42px); line-height:1.07; margin:0 0 10px; font-weight:900; }
    .lead{ color:#334155; max-width:850px; font-size: clamp(15px, 2.2vw, 18px); }
    .cta-row{ display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
    .btn.btn-hero{ font-size:16px; padding:.9rem 1.2rem; box-shadow: var(--glow); transform: translateZ(0); }
    .btn.btn-ghost{ border:1px dashed #9ecae6; }

    /* Secciones */
    .container{ max-width:1200px; margin:0 auto; padding: 0 16px; }
    .grid{ display:grid; gap:16px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
    .card{ background:#fff; border:1px solid #e6eef7; border-radius:16px; padding:16px; transition: transform .25s ease, box-shadow .25s ease; }
    .card:hover{ transform: translateY(-4px); box-shadow: 0 10px 30px rgba(2,6,23,.08); }
    .card[data-tilt]{ transform-style: preserve-3d; }
    .card h3{ margin:0 0 6px; font-weight:800; }
    .muted{ color:#64748b; }

    /* KPI / Promo */
    .promo{ display:grid; gap:16px; grid-template-columns: 1.2fr .8fr; margin:20px 0; }
    @media (max-width:900px){ .promo{ grid-template-columns: 1fr; } }

    /* Reveal on scroll */
    .reveal{ opacity:0; transform: translateY(14px); transition: all .7s cubic-bezier(.2,.8,.2,1); }
    .reveal.in{ opacity:1; transform:none; }

    /* Footer */
    .home-footer{ text-align:center; color:#667085; font-size:13px; padding:22px 0 14px; }
  </style>
</head>
<body>
<div class="hero-wrap aurora">
  <?= self::headerNav('inicio', /*landing*/true) ?>

  <section class="hero">
    <h1 class="headline">Validamos tus JSON antes del SAT — moderno, rápido y confiable.</h1>
    <p class="lead">
      Evita rechazos y retrabajos: validación de estructura, periodos, duplicados por <span class="mono">SHA-256</span> y resúmenes por estación.
      Reportes <b>mensuales y diarios</b> separados, SOPMEX se preocupa por Ti .
    </p>
    <div class="cta-row">
      <a class="btn btn-primary btn-hero" href="<?= htmlspecialchars($cta) ?>">Comenzar ahora</a>
      <a class="btn btn-ghost" href="https://sopmex.com.mx" target="_blank" rel="noopener">Conoce SOPMEX</a>
    </div>
  </section>
</div>

<main>
  <section class="container promo">
    <div class="card reveal" data-tilt>
      <h3>Nos importan nuestros clientes</h3>
      <p class="muted">Acompañamiento 1 a 1, soporte en español y seguimiento proactivo. Revisamos tus archivos <b>antes</b> del envío al SAT.</p>
      <ul class="muted" style="margin:.6rem 0 0 1rem">
        <li>Mensuales y diarios en pestañas distintas</li>
        <li>Bitácora y auditoría por usuario</li>
        <li>Exportación a Excel en un clic</li>
      </ul>
    </div>
    <div class="card reveal" data-tilt>
      <h3>Operación sin fricción</h3>
      <p class="muted">Carga múltiple ZIP/JSON, deduplicación por hash y cálculo del inventario inicial del mes anterior (mismo permiso, mismo usuario).</p>
    </div>
  </section>

  <section class="container" style="margin-top:10px">
    <div class="grid">
      <div class="card reveal" data-tilt>
        <h3>Revisa antes de enviar</h3>
        <p class="muted">Conoce exactamente qué contiene tu archivo JSON antes de presentarlo al SAT. Te entregamos información clara.</p>
      </div>
      <div class="card reveal" data-tilt>
        <h3>Validación preventiva SAT</h3>
        <p class="muted">Estructura, periodos, sumatorias y campos obligatorios. Menos rechazos, más tranquilidad.</p>
      </div>
      <div class="card reveal" data-tilt>
        <h3>Seguridad & cumplimiento</h3>
        <p class="muted">Accesos con roles, historiales y exportables listos para auditoría.</p>
      </div>
      <div class="card reveal" data-tilt>
        <h3>Soporte cercano</h3>
        <p class="muted">Estamos contigo cuando lo necesitas. <a href="https://sopmex.com.mx" target="_blank" rel="noopener">Saber más</a>.</p>
      </div>
    </div>
  </section>
</main>

<footer class="home-footer">© SOPMEX · Validador SAT</footer>

<script>
/* ====== Microinteracciones (sin librerías) ====== */
// Reveal on scroll
const io = new IntersectionObserver((els)=>els.forEach(e=>e.isIntersecting && e.target.classList.add('in')), {threshold: .12});
document.querySelectorAll('.reveal').forEach(el=>io.observe(el));

// Tilt ligero en tarjetas
document.querySelectorAll('[data-tilt]').forEach(card=>{
  let rAF = null;
  const damp = 20;
  card.addEventListener('mousemove', e=>{
    const r = card.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width;
    const y = (e.clientY - r.top)  / r.height;
    const rx = (y - .5) * 6;   // rotX
    const ry = (x - .5) * -6;  // rotY
    if (rAF) cancelAnimationFrame(rAF);
    rAF = requestAnimationFrame(()=>{
      card.style.transform = `rotateX(${rx}deg) rotateY(${ry}deg) translateY(-4px)`;
      card.style.boxShadow = '0 18px 40px rgba(2,6,23,.12)';
    });
  });
  card.addEventListener('mouseleave', ()=>{
    if (rAF) cancelAnimationFrame(rAF);
    card.style.transform = '';
    card.style.boxShadow = '';
  });
});
</script>
</body>
</html>
<?php
        return ob_get_clean();
    }
}
