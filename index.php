<?php
// DigitalMax LMS — Sales landing page (v66, unslopped)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/legal.php';

if ($u = current_user()) {
    redirect($u['role'] === 'student' ? 'student/index.php' : 'admin/index.php');
}
$site = setting('site_name', 'DigitalMax LMS');

$courses = [];
$outlines = include __DIR__ . '/inc/course_outlines.php';
$stats = ['students' => 0, 'lectures' => 0];
try {
    $pdo = db();
    $courses = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM lectures WHERE course_id = c.id) AS lc FROM courses c WHERE c.status = 'published' ORDER BY c.created_at DESC")->fetchAll();
    $stats['students'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $stats['lectures'] = (int)$pdo->query('SELECT COUNT(*) FROM lectures')->fetchColumn();
} catch (Exception $ex) {}

// Batch info — sirf tab dikhao jab admin ne Settings me khud set kiya ho.
// (Pehle ye har mahine khud reset hota tha aur seats default 7 thi — wo fake scarcity thi.)
$deadline = trim((string)setting('batch_deadline', ''));
$ts = $deadline !== '' ? strtotime($deadline) : false;
$has_deadline = $ts && $ts > time();
$seats_raw = trim((string)setting('seats_remaining', ''));
$seats_total_raw = trim((string)setting('seats_total', ''));
$has_seats = $seats_raw !== '' && $seats_total_raw !== '' && (int)$seats_total_raw > 0;
$seats_left = $has_seats ? max(0, (int)$seats_raw) : 0;
$seats_total = $has_seats ? (int)$seats_total_raw : 0;
$seats_pct = $has_seats ? min(100, (int)round((1 - $seats_left / $seats_total) * 100)) : 0;

// earnings proof screenshots (assets/proof/ me khud upload karein)
$proofs = glob(__DIR__ . '/assets/proof/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
$proofs = array_values(array_filter(array_map('basename', $proofs), fn($f) => $f !== 'README.txt'));

// Hero preview — asli YouTube course outline ke pehle lectures
$yt = $outlines['youtube-automation'] ?? reset($outlines);
$preview_lessons = array_slice($yt['parts'][0]['modules'] ?? [], 0, 5);

// DB courses ko outline se match karo (naam se)
function match_db_course($db_courses, $outline_name) {
    $on = mb_strtolower($outline_name);
    foreach ($db_courses as $dc) {
        $dn = mb_strtolower($dc['title']);
        foreach (['youtube','facebook','tiktok'] as $kw) {
            if (mb_strpos($on, $kw) !== false && mb_strpos($dn, $kw) !== false) return $dc;
        }
        if ($dn === $on) return $dc;
    }
    return null;
}
$min_fee = null;
foreach ($outlines as $oc) {
    $dbc = match_db_course($courses, $oc['name']);
    $f = (int)($dbc ? $dbc['price'] : $oc['fee']);
    if ($f > 0) $min_fee = $min_fee === null ? $f : min($min_fee, $f);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($site) ?> — Faceless YouTube, TikTok & Facebook courses in simple Urdu</title>
<meta name="description" content="AI se faceless videos bana kar YouTube, TikTok aur Facebook channel monetize karna seekhein — simple Urdu me, step by step. Payment EasyPaisa/JazzCash se.">
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&family=Fraunces:opsz,wght@9..144,400;9..144,500&display=swap" rel="stylesheet">
<style>
  :root {
    --bg:#f7f4ee; --card:#fdfbf7; --white:#ffffff; --raised:#efe9df;
    --ink:#1d3448; --text-2:#4b5866; --muted:#6c7580;
    --line:rgba(29,52,72,.09); --line-2:rgba(29,52,72,.16);
    --accent:#c74b25; --accent-ink:#a93e1e; --accent-soft:#f6e3d8;
    --night:#16283a; --night-2:#1f3549; --night-text:#e8ecf0; --night-muted:#9aa8b6;
    --pos:#15803d;
    --ease:cubic-bezier(0.22,1,0.36,1);
    --serif:'Fraunces', Georgia, serif;
    --mono:ui-monospace,'SF Mono',Menlo,Consolas,monospace;
  }
  *, *::before, *::after { box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body { margin:0; background:var(--bg); color:var(--ink); font-family:'Figtree',system-ui,sans-serif; font-size:15px; line-height:1.55; -webkit-font-smoothing:antialiased; }
  a { color:inherit; }
  b, strong { font-weight:600; }
  .ico { display:inline-block; vertical-align:-3px; flex-shrink:0; }
  .wrap { max-width:1200px; margin:0 auto; padding:0 24px; }
  .num { font-variant-numeric:tabular-nums; }
  :focus-visible { outline:2px solid var(--accent); outline-offset:2px; border-radius:4px; }

  /* buttons */
  .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; height:44px; padding:0 20px; border-radius:8px; font-size:15px; font-weight:500; text-decoration:none; border:1px solid transparent; cursor:pointer; white-space:nowrap; transition:background-color .15s var(--ease), border-color .15s var(--ease), transform .12s var(--ease); }
  .btn:active { transform:scale(.98); }
  .btn-p { background:var(--accent); color:#fff; }
  .btn-p:hover { background:var(--accent-ink); }
  .btn-s { background:var(--white); color:var(--ink); border-color:var(--line-2); }
  .btn-s:hover { background:var(--raised); }
  .btn-sm { height:36px; padding:0 16px; font-size:14px; }

  /* nav */
  .nav { position:sticky; top:0; z-index:50; background:rgba(247,244,238,.92); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); border-bottom:1px solid var(--line); }
  .nav .wrap { display:flex; align-items:center; gap:32px; height:64px; }
  .brand { display:flex; align-items:center; gap:10px; text-decoration:none; font-weight:600; font-size:17px; }
  .brand img { width:32px; height:32px; border-radius:8px; }
  .nav-links { display:flex; gap:24px; }
  .nav-links a { font-size:14px; color:var(--text-2); text-decoration:none; transition:color .15s var(--ease); }
  .nav-links a:hover { color:var(--ink); }
  .nav-right { margin-left:auto; display:flex; align-items:center; gap:16px; }
  .nav-right .login { font-size:14px; color:var(--text-2); text-decoration:none; }
  .nav-right .login:hover { color:var(--ink); }

  /* hero */
  .hero { min-height:calc(100vh - 64px); display:flex; align-items:center; padding:48px 0 64px; }
  .hero-grid { display:grid; grid-template-columns:minmax(0,5fr) minmax(0,6fr); gap:64px; align-items:center; }
  .hero-note { font-size:14px; color:var(--text-2); margin:0 0 20px; display:flex; align-items:center; gap:8px; }
  .hero-note .dot { width:6px; height:6px; border-radius:50%; background:var(--accent); }
  .hero h1 { font-family:var(--serif); font-weight:400; font-size:clamp(40px,4.6vw,60px); line-height:1.06; letter-spacing:-.025em; margin:0; font-variation-settings:'opsz' 144; }
  .hero h1 em { font-style:normal; color:var(--accent-ink); }
  .hero .sub { font-size:19px; line-height:1.55; color:var(--text-2); max-width:32rem; margin:24px 0 0; }
  .hero-actions { display:flex; gap:12px; margin-top:32px; flex-wrap:wrap; }
  .hero-meta { display:flex; flex-wrap:wrap; gap:8px 20px; margin-top:40px; padding-top:20px; border-top:1px solid var(--line); font-size:14px; color:var(--text-2); }
  .hero-meta span { display:inline-flex; align-items:center; gap:8px; }
  .hero-meta b { color:var(--ink); font-weight:600; }
  .hero-meta .ico { color:var(--muted); }

  /* product preview — the lecture player (moment 1) */
  .player { background:var(--white); border:1px solid var(--line); border-radius:16px; padding:12px; box-shadow:0 1px 2px rgba(29,52,72,.04), 0 24px 48px -24px rgba(29,52,72,.22); }
  .pl-top { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:4px 4px 12px; }
  .pl-course { font-size:13px; color:var(--muted); }
  .pl-title { font-size:15px; font-weight:600; margin-top:2px; }
  .pl-protected { font-size:12px; color:var(--muted); display:inline-flex; align-items:center; gap:4px; white-space:nowrap; }
  .screen { position:relative; aspect-ratio:16/9; border-radius:8px; overflow:hidden; background:var(--night); color:var(--night-text); }
  .screen-doc { position:absolute; inset:24px 24px 64px 24px; display:grid; grid-template-columns:1.15fr 1fr; gap:16px; }
  .doc { background:#f9f6f0; color:#2a2f36; border-radius:6px; padding:16px; font-size:12px; line-height:1.55; overflow:hidden; }
  .doc h5 { margin:0 0 8px; font-size:12px; font-weight:600; color:#1d3448; }
  .doc p { margin:0 0 6px; color:#4b5866; }
  .doc mark { background:#f6e3d8; color:#7a2c14; padding:0 2px; border-radius:2px; }
  .doc .line { height:6px; border-radius:3px; background:#e6e0d4; margin:8px 0; }
  .vo { background:var(--night-2); border-radius:6px; padding:12px; display:flex; flex-direction:column; gap:8px; font-size:12px; }
  .vo-head { display:flex; justify-content:space-between; color:var(--night-muted); }
  .wave { display:flex; align-items:center; gap:2px; height:44px; }
  .wave i { display:block; width:3px; border-radius:2px; background:#4d6780; }
  .wave i.on { background:#f0a080; }
  .vo-row { display:flex; justify-content:space-between; color:var(--night-muted); }
  .vo-row b { color:var(--night-text); font-weight:500; }
  .scrub { position:absolute; left:16px; right:16px; bottom:16px; display:flex; align-items:center; gap:12px; font-size:12px; color:var(--night-muted); }
  .scrub .bar { flex:1; height:4px; border-radius:2px; background:rgba(255,255,255,.16); position:relative; }
  .scrub .bar i { position:absolute; left:0; top:0; bottom:0; width:15%; background:#fff; border-radius:2px; }
  .scrub .bar s { position:absolute; left:15%; top:50%; width:12px; height:12px; margin:-6px 0 0 -6px; border-radius:50%; background:#fff; }
  .scrub .play { width:28px; height:28px; display:inline-flex; align-items:center; justify-content:center; color:#fff; }
  .lessons { list-style:none; margin:12px 0 0; padding:0; }
  .lessons li { display:flex; align-items:center; gap:12px; height:44px; padding:0 8px; border-radius:8px; font-size:14px; }
  .lessons li + li { margin-top:2px; }
  .lessons li .n { font-family:var(--mono); font-size:12px; color:var(--muted); width:20px; }
  .lessons li .t { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .lessons li .m { font-size:12px; color:var(--muted); font-variant-numeric:tabular-nums; white-space:nowrap; }
  .lessons li.done .t { color:var(--text-2); }
  .lessons li.now { background:var(--accent-soft); }
  .lessons li.now .t { font-weight:500; }
  .lessons li.now .m { color:var(--accent-ink); }
  .lessons li.todo .t { color:var(--muted); }

  /* watch ring — signature */
  .wring { display:inline-block; flex:none; vertical-align:middle; --wr-track:var(--raised); color:var(--accent); }
  .wring circle { fill:none; }
  .wring .wr-track { stroke:var(--wr-track); }
  .wring .wr-fill { stroke:currentColor; stroke-linecap:round; }
  .wring .wr-done { fill:currentColor; }
  .lessons li.now .wring { --wr-track:rgba(199,75,37,.18); }

  /* batch strip (only when admin set it) */
  .batch { border-top:1px solid var(--line); border-bottom:1px solid var(--line); background:var(--card); }
  .batch .wrap { display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; padding-top:20px; padding-bottom:20px; font-size:15px; }
  .batch .t span { color:var(--text-2); }
  .seats { display:flex; align-items:center; gap:12px; min-width:280px; font-size:14px; color:var(--text-2); }
  .seats .bar { flex:1; height:6px; border-radius:3px; background:var(--raised); overflow:hidden; }
  .seats .bar i { display:block; height:100%; background:var(--accent); border-radius:3px; }

  /* sections */
  section.sec { padding:96px 0; }
  .sec-head { max-width:40rem; margin-bottom:40px; }
  .sec-head h2 { font-family:var(--serif); font-weight:400; font-size:clamp(30px,3.4vw,40px); line-height:1.15; letter-spacing:-.02em; margin:0; font-variation-settings:'opsz' 144; }
  .sec-head p { color:var(--text-2); font-size:17px; margin:12px 0 0; }
  .sec-head p a { color:var(--accent-ink); text-decoration:none; font-weight:500; }
  .sec-head p a:hover { text-decoration:underline; text-underline-offset:3px; }
  .alt { background:var(--card); border-top:1px solid var(--line); border-bottom:1px solid var(--line); }

  /* course cards */
  .courses { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; }
  .ccard { background:var(--white); border:1px solid var(--line); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; transition:border-color .15s var(--ease); }
  .ccard:hover { border-color:var(--line-2); }
  .ccard img { display:block; width:100%; aspect-ratio:16/9; object-fit:cover; background:var(--raised); }
  .ccard .ph { aspect-ratio:16/9; background:var(--night); color:#fff; display:flex; align-items:flex-end; padding:20px; font-family:var(--serif); font-size:22px; line-height:1.2; }
  .ccard .body { padding:20px; display:flex; flex-direction:column; flex:1; }
  .ccard h3 { font-size:17px; font-weight:600; margin:0; line-height:1.35; }
  .ccard .desc { font-size:14px; color:var(--text-2); margin:8px 0 0; flex:1; }
  .ccard .meta { font-size:13px; color:var(--muted); margin-top:16px; }
  .ccard .foot { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:16px; padding-top:16px; border-top:1px solid var(--line); }
  .price { font-size:22px; font-weight:500; font-variant-numeric:tabular-nums; }
  .price small { display:block; font-size:12px; color:var(--muted); font-weight:400; }

  /* how to pay — steps + receipt (moment 2) */
  .pay-grid { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:64px; align-items:center; }
  .steps { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:28px; }
  .steps li { display:grid; grid-template-columns:32px 1fr; gap:16px; }
  .steps .k { width:32px; height:32px; border-radius:50%; border:1px solid var(--line-2); display:flex; align-items:center; justify-content:center; font-family:var(--mono); font-size:13px; color:var(--text-2); background:var(--white); }
  .steps h3 { font-size:16px; font-weight:600; margin:4px 0 4px; }
  .steps p { margin:0; color:var(--text-2); font-size:15px; }
  .receipt-wrap { position:relative; display:flex; justify-content:center; padding:24px 0 48px; }
  .receipt { width:100%; max-width:360px; background:var(--white); border-radius:12px 12px 0 0; padding:24px 24px 20px; box-shadow:0 1px 2px rgba(29,52,72,.05), 0 24px 48px -24px rgba(29,52,72,.28); transform:rotate(-1.5deg); position:relative; }
  .receipt::after { content:''; position:absolute; left:0; right:0; bottom:-8px; height:8px; background:radial-gradient(circle at 6px 8px, transparent 6px, var(--white) 6.5px) 0 0/12px 8px repeat-x; }
  .r-head { display:flex; align-items:center; justify-content:space-between; font-size:13px; color:var(--muted); }
  .r-ok { display:inline-flex; align-items:center; gap:6px; color:var(--pos); font-weight:500; }
  .r-amt { font-size:36px; font-weight:500; letter-spacing:-.01em; margin:16px 0 4px; font-variant-numeric:tabular-nums; }
  .r-to { font-size:14px; color:var(--text-2); }
  .r-rows { margin-top:20px; border-top:1px dashed var(--line-2); padding-top:12px; }
  .r-row { display:flex; justify-content:space-between; gap:12px; font-size:14px; padding:6px 0; }
  .r-row span:first-child { color:var(--muted); }
  .r-row .tid { font-family:var(--mono); font-size:14px; letter-spacing:.04em; background:var(--accent-soft); color:#7a2c14; padding:1px 6px; border-radius:4px; }
  .r-note { font-size:12px; color:var(--muted); text-align:center; margin-top:16px; }
  .r-status { position:absolute; right:0; bottom:0; background:var(--white); border:1px solid var(--line); border-radius:12px; padding:12px 16px; font-size:13px; box-shadow:0 8px 24px rgba(29,52,72,.08); display:flex; align-items:center; gap:12px; }
  .r-status b { display:block; font-size:14px; }
  .r-status span { color:var(--muted); }

  /* curriculum */
  .curr { max-width:800px; }
  .tabs { display:inline-flex; gap:4px; padding:4px; background:var(--raised); border-radius:10px; margin-bottom:24px; flex-wrap:wrap; }
  .tab { height:36px; padding:0 16px; border:0; border-radius:6px; background:transparent; font:inherit; font-size:14px; font-weight:500; color:var(--text-2); cursor:pointer; transition:background-color .15s var(--ease), color .15s var(--ease); }
  .tab:hover { color:var(--ink); }
  .tab.on { background:var(--white); color:var(--ink); box-shadow:0 1px 2px rgba(29,52,72,.08); }
  .curr-meta { font-size:14px; color:var(--muted); margin:0 0 16px; }
  .acc { background:var(--white); border:1px solid var(--line); border-radius:12px; margin-bottom:8px; overflow:hidden; }
  .acc-btn { width:100%; display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px; background:none; border:0; font:inherit; color:inherit; text-align:left; cursor:pointer; }
  .acc-btn .h { font-size:15px; font-weight:600; }
  .acc-btn .s { display:block; font-size:13px; color:var(--muted); font-weight:400; margin-top:2px; }
  .acc-icon { color:var(--muted); display:inline-flex; transition:transform .2s var(--ease); }
  .acc-body { display:grid; grid-template-rows:0fr; transition:grid-template-rows .3s var(--ease); }
  .acc-body > div { overflow:hidden; }
  .acc.open .acc-body { grid-template-rows:1fr; }
  .acc.open .acc-icon { transform:rotate(45deg); }
  .topics { list-style:none; margin:0; padding:0 20px 12px; }
  .topics li { display:grid; grid-template-columns:28px 1fr; gap:8px; padding:12px 0; border-top:1px solid var(--line); font-size:15px; }
  .topics li .k { font-family:var(--mono); font-size:12px; color:var(--muted); padding-top:3px; }
  .topics li span.d { display:block; font-size:14px; color:var(--text-2); margin-top:2px; }
  .faq .acc-body p { margin:0; padding:0 20px 20px; font-size:15px; color:var(--text-2); }

  /* results */
  .proof-box { overflow:hidden; border-radius:16px; border:1px solid var(--line); background:var(--white); }
  .proof-track { display:flex; transition:transform .5s var(--ease); }
  .proof-track > div { min-width:100%; }
  .proof-track img { display:block; width:100%; height:400px; object-fit:contain; background:var(--white); }
  .proof-ctl { display:flex; align-items:center; justify-content:space-between; margin-top:16px; }
  .proof-dots { display:flex; gap:8px; }
  .proof-dot { width:8px; height:8px; border-radius:50%; background:var(--line-2); border:0; padding:0; cursor:pointer; }
  .proof-dot.on { background:var(--ink); }
  .proof-arrows { display:flex; gap:8px; }
  .icon-btn { width:40px; height:40px; border-radius:8px; border:1px solid var(--line-2); background:var(--white); color:var(--ink); display:inline-flex; align-items:center; justify-content:center; cursor:pointer; transition:background-color .15s var(--ease); }
  .icon-btn:hover { background:var(--raised); }

  /* final cta */
  .cta { background:var(--night); color:var(--night-text); border-radius:20px; padding:64px 48px; display:grid; grid-template-columns:minmax(0,1fr) auto; gap:32px; align-items:center; }
  .cta h2 { font-family:var(--serif); font-weight:400; font-size:clamp(28px,3vw,36px); line-height:1.15; letter-spacing:-.02em; margin:0; color:#fff; }
  .cta p { color:var(--night-muted); margin:12px 0 0; font-size:16px; }

  footer { border-top:1px solid var(--line); color:var(--muted); font-size:13px; padding:32px 0; }
  footer .wrap { display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap; }
  footer a { color:var(--text-2); text-decoration:none; }
  footer a:hover { color:var(--ink); }

  /* one entrance, hero only */
  @keyframes rise { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
  .rise { animation:rise .45s var(--ease) both; }
  .rise-2 { animation-delay:.05s; } .rise-3 { animation-delay:.1s; } .rise-4 { animation-delay:.15s; }
  @media (prefers-reduced-motion: reduce) { .rise { animation:none; } html { scroll-behavior:auto; } }

  @media (max-width: 960px) {
    .hero { min-height:0; padding:48px 0 64px; }
    .hero-grid, .pay-grid { grid-template-columns:1fr; gap:48px; }
    .courses { grid-template-columns:1fr; max-width:480px; }
    .cta { grid-template-columns:1fr; padding:40px 24px; }
  }
  @media (max-width: 720px) {
    .wrap { padding:0 16px; }
    .nav .wrap { gap:16px; }
    .nav-links { display:none; }
    .hero h1 { font-size:40px; }
    .hero .sub { font-size:17px; }
    section.sec { padding:64px 0; }
    .screen-doc { inset:12px 12px 52px 12px; grid-template-columns:1fr; }
    .screen-doc .vo { display:none; }
    .lessons li:nth-child(n+5) { display:none; }
    .proof-track img { height:280px; }
    .r-status { position:static; margin-top:24px; }
    .receipt-wrap { flex-direction:column; align-items:center; padding-bottom:24px; }
  }
</style>
</head>
<body>

<nav class="nav">
  <div class="wrap">
    <a href="#" class="brand"><img src="assets/logo.png" alt=""><span><?= e($site) ?></span></a>
    <div class="nav-links">
      <a href="#courses">Courses</a>
      <a href="#curriculum">Curriculum</a>
      <?php if ($proofs): ?><a href="#results">Results</a><?php endif; ?>
      <a href="#faq">FAQ</a>
    </div>
    <div class="nav-right">
      <a href="login.php" class="login">Login</a>
      <a href="#courses" class="btn btn-p btn-sm">Enroll</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="hero">
  <div class="wrap hero-grid">
    <div>
      <?php if ($has_deadline): ?>
      <p class="hero-note rise"><span class="dot"></span><?= e(date('F', $ts)) ?> batch · admissions close <?= e(date('j F', $ts)) ?></p>
      <?php endif; ?>
      <h1 class="rise rise-2">Faceless YouTube, TikTok and Facebook channels, <em>taught in simple Urdu</em></h1>
      <p class="sub rise rise-3">AI se videos bana kar channel monetize karna, step by step. Koi experience nahi chahiye, mobile par bhi dekh sakte hain.</p>
      <div class="hero-actions rise rise-4">
        <a href="#courses" class="btn btn-p">Choose a course</a>
        <a href="#curriculum" class="btn btn-s">View curriculum</a>
      </div>
      <div class="hero-meta rise rise-4">
        <?php if ($stats['students'] > 0): ?><span><b class="num"><?= number_format($stats['students']) ?></b> students</span><?php endif; ?>
        <?php if ($stats['lectures'] > 0): ?><span><b class="num"><?= number_format($stats['lectures']) ?></b> video lectures</span><?php endif; ?>
        <span><?= icon('graduation-cap', 16) ?>Certificate with verification ID</span>
      </div>
    </div>

    <!-- Product preview: the lecture player students use -->
    <div class="player rise rise-3" aria-label="Preview of a lecture in the student portal">
      <div class="pl-top">
        <div>
          <div class="pl-course"><?= e($yt['name']) ?> · Lecture 03</div>
          <div class="pl-title"><?= e($preview_lessons[2]['t'] ?? 'Script Writing') ?></div>
        </div>
        <span class="pl-protected"><?= icon('lock', 14) ?>Protected video</span>
      </div>
      <div class="screen" aria-hidden="true">
        <div class="screen-doc">
          <div class="doc">
            <h5>Script · Episode 12</h5>
            <p><mark>Hook (0–5s):</mark> 1912 me ek jahaz ne aakhri signal bheja…</p>
            <div class="line" style="width:92%"></div>
            <div class="line" style="width:78%"></div>
            <p><mark>Story:</mark> Captain ki log book ka aakhri page</p>
            <div class="line" style="width:88%"></div>
            <div class="line" style="width:64%"></div>
            <div class="line" style="width:81%"></div>
          </div>
          <div class="vo">
            <div class="vo-head"><span>Voice over</span><span class="num">00:48</span></div>
            <div class="wave">
              <?php foreach ([10,18,26,14,32,40,22,30,44,28,16,34,24,38,20,12,26,36,18,30,14,22,10,16] as $wi => $wh): ?><i class="<?= $wi < 14 ? 'on' : '' ?>" style="height:<?= $wh ?>px"></i><?php endforeach; ?>
            </div>
            <div class="vo-row"><span>Speed</span><b class="num">1.05×</b></div>
            <div class="vo-row"><span>Pause after hook</span><b class="num">0.4s</b></div>
          </div>
        </div>
        <div class="scrub">
          <span class="play"><?= icon('pause', 16) ?></span>
          <span class="num">2:10</span>
          <span class="bar"><i></i><s></s></span>
          <span class="num">14:32</span>
        </div>
      </div>
      <ul class="lessons">
        <?php foreach ($preview_lessons as $li => $l):
          $state = $li < 2 ? 'done' : ($li === 2 ? 'now' : 'todo');
          $ring  = $li < 2 ? 100 : ($li === 2 ? 72 : 0); ?>
        <li class="<?= $state ?>">
          <span class="n"><?= sprintf('%02d', $li + 1) ?></span>
          <?= watch_ring($ring, 20, $state === 'done' ? 'Watched' : ($state === 'now' ? '72% of the 3-minute minimum' : 'Not started')) ?>
          <span class="t"><?= e($l['t']) ?></span>
          <span class="m"><?= $state === 'done' ? 'Watched' : ($state === 'now' ? '2:10 of 3:00 to count' : '') ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</header>

<?php if ($has_deadline || $has_seats): ?>
<!-- BATCH — sirf admin ki set ki hui values -->
<section class="batch">
  <div class="wrap">
    <div class="t">
      <?php if ($has_deadline): ?><b><?= e(date('F Y', $ts)) ?> batch</b> <span>· admissions close <?= e(date('l, j F', $ts)) ?></span>
      <?php else: ?><b>Current batch</b> <span>· admissions open</span><?php endif; ?>
    </div>
    <?php if ($has_seats): ?>
    <div class="seats">
      <span class="num"><?= $seats_left ?> of <?= $seats_total ?> seats left</span>
      <span class="bar"><i style="width:<?= $seats_pct ?>%"></i></span>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- COURSES -->
<section id="courses" class="sec">
  <div class="wrap">
    <div class="sec-head">
      <h2>Choose your course</h2>
      <p>Ek dafa fee, lifetime access. Payment EasyPaisa ya JazzCash se. <a href="pay.php">Payment details</a></p>
    </div>
    <div class="courses">
      <?php foreach ($outlines as $slug => $oc):
        $dbc = match_db_course($courses, $oc['name']);
        $thumb = $dbc['thumbnail'] ?? '';
        if (!$thumb) {
          $slug_img = ['youtube-automation' => 'course-youtube.png', 'facebook-automation' => 'course-facebook.png', 'tiktok-automation' => 'course-tiktok.png'];
          if (!empty($slug_img[$slug])) $thumb = 'assets/courses/' . $slug_img[$slug];
        }
        $price = $dbc ? $dbc['price'] : $oc['fee'];
        $lec_info = $dbc ? number_format((int)$dbc['lc']) . ' video lectures' : e($oc['duration']);
        $desc = $dbc && !empty($dbc['description']) ? e(mb_substr($dbc['description'], 0, 120)) : e($oc['tagline']);
      ?>
      <article class="ccard">
        <?php if ($thumb): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy">
        <?php else: ?><div class="ph"><?= e($oc['name']) ?></div><?php endif; ?>
        <div class="body">
          <h3><?= e($oc['name']) ?></h3>
          <p class="desc"><?= $desc ?></p>
          <div class="meta"><?= $lec_info ?> · Certificate · Lifetime access</div>
          <div class="foot">
            <span class="price"><?= $price ? money($price) : 'Fee soon' ?><small>one-time</small></span>
            <a href="pay.php?course=<?= e($slug) ?>" class="btn btn-p btn-sm">Enroll</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- HOW TO PAY -->
<section class="sec alt">
  <div class="wrap pay-grid">
    <div>
      <div class="sec-head">
        <h2>How to pay</h2>
        <p>Manual verification, koi online gateway nahi. Seedha aur mehfooz.</p>
      </div>
      <ol class="steps">
        <li><span class="k">1</span><div><h3>Official account par fee bhejein</h3><p>Course select karein aur diye gaye EasyPaisa, JazzCash ya bank account par fee bhejein. Number copy button se copy karein.</p></div></li>
        <li><span class="k">2</span><div><h3>TID aur screenshot lein</h3><p>Payment ke baad SMS ya app se 11–12 digit Transaction ID (TID) copy karein aur receipt ka screenshot lein.</p></div></li>
        <li><span class="k">3</span><div><h3>Portal par submit karein</h3><p>TID aur screenshot upload karein. Hamari team 15–60 minute me verify karke dashboard activate kar degi.</p></div></li>
      </ol>
    </div>
    <div class="receipt-wrap" aria-hidden="true">
      <div class="receipt">
        <div class="r-head"><span>Payment receipt</span><span class="r-ok"><?= icon('check-circle', 14) ?>Sent</span></div>
        <div class="r-amt"><?= money($min_fee ?: 6000) ?></div>
        <div class="r-to">to <?= e($site) ?></div>
        <div class="r-rows">
          <div class="r-row"><span>Method</span><span>EasyPaisa</span></div>
          <div class="r-row"><span>TID</span><span class="tid">41827365019</span></div>
          <div class="r-row"><span>Date</span><span class="num"><?= date('j M Y') ?>, 8:42 PM</span></div>
        </div>
        <div class="r-note">Example · ye TID aap portal par submit karte hain</div>
      </div>
      <div class="r-status">
        <?= watch_ring(100, 24, 'Verified') ?>
        <div><b>Dashboard active</b><span>Verified in 15–60 min</span></div>
      </div>
    </div>
  </div>
</section>

<!-- CURRICULUM -->
<section id="curriculum" class="sec">
  <div class="wrap curr">
    <div class="sec-head">
      <h2>What you'll learn</h2>
      <p>Har course ka mukammal roadmap. Module kholein aur topics dekhein.</p>
    </div>
    <div class="tabs" role="tablist">
      <?php $i=0; foreach ($outlines as $slug => $oc): $i++; ?>
        <button type="button" role="tab" aria-selected="<?= $i===1?'true':'false' ?>" onclick="showCurr('<?= e($slug) ?>', this)" class="tab <?= $i===1?'on':'' ?>"><?= e(trim(preg_replace('/\b(Crash Course|Program|Course)\b/i', '', $oc['name']))) ?></button>
      <?php endforeach; ?>
    </div>
    <?php $ci=0; foreach ($outlines as $slug => $oc): $ci++; ?>
    <div class="curr-block" id="curr-<?= e($slug) ?>"<?= $ci>1?' hidden':'' ?>>
      <?php if (!empty($oc['duration'])): ?><p class="curr-meta"><?= e($oc['name']) ?> · <?= e($oc['duration']) ?></p><?php endif; ?>
      <?php foreach ($oc['parts'] as $part): $mods = $part['modules'] ?? []; ?>
      <div class="acc">
        <button type="button" class="acc-btn" onclick="toggleAcc(this)" aria-expanded="false">
          <span><span class="h"><?= e($part['t']) ?></span><span class="s"><?= count($mods) ?> topics</span></span>
          <span class="acc-icon"><?= icon('plus', 18) ?></span>
        </button>
        <div class="acc-body"><div>
          <ul class="topics">
            <?php $ti=0; foreach ($mods as $m): $ti++; ?>
            <li><span class="k"><?= sprintf('%02d', $ti) ?></span><span><?= e($m['t']) ?><?php if (!empty($m['d'])): ?><span class="d"><?= e($m['d']) ?></span><?php endif; ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($proofs): ?>
<!-- RESULTS -->
<section id="results" class="sec alt">
  <div class="wrap" style="max-width:960px">
    <div class="sec-head">
      <h2>Student results</h2>
      <p>Hamare students ke channel dashboards ke screenshots.</p>
    </div>
    <div class="proof">
      <div class="proof-box"><div class="proof-track" id="proofTrack">
        <?php foreach ($proofs as $p): ?><div><img src="assets/proof/<?= e($p) ?>" alt="Student channel dashboard" loading="lazy"></div><?php endforeach; ?>
      </div></div>
      <div class="proof-ctl">
        <div class="proof-dots" id="proofDots"></div>
        <div class="proof-arrows">
          <button type="button" class="icon-btn" onclick="proofMove(-1)" aria-label="Previous"><?= icon('chevron-left', 18) ?></button>
          <button type="button" class="icon-btn" onclick="proofMove(1)" aria-label="Next"><?= icon('chevron-right', 18) ?></button>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
(function(){
  var track = document.getElementById('proofTrack'), dots = document.getElementById('proofDots');
  var n = track.children.length, i = 0;
  for (var k = 0; k < n; k++) { var d = document.createElement('button'); d.type = 'button'; d.className = 'proof-dot'; d.setAttribute('aria-label', 'Result ' + (k + 1)); d.dataset.k = k; d.onclick = function(){ proofGo(+this.dataset.k); }; dots.appendChild(d); }
  function paint(){ track.style.transform = 'translateX(-' + (i * 100) + '%)'; Array.prototype.forEach.call(dots.children, function(dt, idx){ dt.classList.toggle('on', idx === i); }); }
  window.proofGo = function(k){ i = (k + n) % n; paint(); };
  window.proofMove = function(d){ proofGo(i + d); };
  paint();
})();
</script>
<?php endif; ?>

<!-- FAQ -->
<section id="faq" class="sec faq">
  <div class="wrap" style="max-width:800px">
    <div class="sec-head"><h2>Common questions</h2></div>
    <?php
    $faqs = [
      ['Payment kaise karun?', 'EasyPaisa ya JazzCash par payment karke screenshot upload karein. Admin verify karke aapka course unlock kar dega.'],
      ['Course kis language me hai?', 'Simple Urdu me. Har step screen par practically karke dikhaya gaya hai.'],
      ['Kya certificate milega?', 'Jee. Course 100% complete karne par unique verification ID wala certificate milega.'],
      ['Access kitne arse tak rahega?', 'Lifetime access. Ek dafa enroll, hamesha ke liye.'],
      ['Kya mobile par dekh sakta hun?', 'Bilkul. Mobile, tablet aur laptop sab par chalega.'],
    ];
    foreach ($faqs as [$q,$a]): ?>
    <div class="acc">
      <button type="button" class="acc-btn" onclick="toggleAcc(this)" aria-expanded="false"><span class="h"><?= e($q) ?></span><span class="acc-icon"><?= icon('plus', 18) ?></span></button>
      <div class="acc-body"><div><p><?= e($a) ?></p></div></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FINAL CTA -->
<section class="wrap" style="padding-bottom:96px">
  <div class="cta">
    <div>
      <h2>Course chunein aur aaj shuru karein</h2>
      <p>Payment verify hote hi, 15–60 minute me, aapka dashboard active ho jata hai.</p>
    </div>
    <a href="#courses" class="btn btn-p">Choose a course</a>
  </div>
</section>

<footer>
  <div class="wrap">
    <span>© <?= date('Y') ?> <?= e($site) ?> · <a href="login.php">Student login</a></span>
    <span><?= legal_footer_links(' · ') ?></span>
  </div>
</footer>

<script>
function toggleAcc(btn){
  var acc = btn.closest('.acc'), wasOpen = acc.classList.contains('open');
  acc.parentElement.querySelectorAll('.acc.open').forEach(function(a){ a.classList.remove('open'); a.querySelector('.acc-btn').setAttribute('aria-expanded', 'false'); });
  if (!wasOpen) { acc.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
}
function showCurr(cid, btn){
  document.querySelectorAll('.curr-block').forEach(function(b){ b.hidden = true; });
  document.getElementById('curr-' + cid).hidden = false;
  document.querySelectorAll('.tab').forEach(function(p){ p.classList.remove('on'); p.setAttribute('aria-selected', 'false'); });
  btn.classList.add('on'); btn.setAttribute('aria-selected', 'true');
}
</script>
<?= whatsapp_float() ?>
</body>
</html>
