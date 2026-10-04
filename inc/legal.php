<?php
// DigitalMax LMS — Shared legal page renderer (public pages, no login needed)
// Usage: $sections = [['en_t'=>..,'en_b'=>..HTML..,'ur_t'=>..,'ur_b'=>..HTML..], ...];
//        render_legal_page('Title EN', 'اردو عنوان', $sections);

function legal_footer_links($sep = ' · ', $prefix = '') {
    $links = [
        ['Terms of Service', $prefix . 'terms.php'],
        ['Privacy Policy', $prefix . 'privacy.php'],
        ['Refund Policy', $prefix . 'refund-policy.php'],
        ['Earnings Disclaimer', $prefix . 'disclaimer.php'],
        ['Anti-Piracy', $prefix . 'copyright.php'],
    ];
    $out = [];
    foreach ($links as [$label, $url]) {
        $out[] = '<a href="' . $url . '" style="color:inherit">' . $label . '</a>';
    }
    return implode('<span style="opacity:.4">' . $sep . '</span>', $out);
}

function render_legal_page($title_en, $title_ur, $sections) {
    $site = setting('site_name', 'DigitalMax LMS');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title_en) ?> — <?= e($site) ?></title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">
<style>
  * { font-family:'Plus Jakarta Sans',system-ui,sans-serif; box-sizing:border-box; }
  body { margin:0; background:#ffffff; color:#294461; }
  .urdu { font-family:'Noto Nastaliq Urdu','Jameel Noori Nastaleeq',serif; line-height:2.5; }
  .topbar { background:rgba(255,255,255,.92); backdrop-filter:blur(12px); border-bottom:1px solid #e2e8f0; position:sticky; top:0; z-index:10; }
  .wrap { max-width:820px; margin:0 auto; padding:0 20px; }
  .doc { background:#fff; border:1px solid #e2e8f0; border-radius:24px; padding:40px 38px; margin:32px 0 24px; box-shadow:0 20px 50px -24px rgba(29,52,72,.18); }
  .doc h1 { font-size:clamp(25px,4vw,34px); font-weight:800; letter-spacing:-.02em; margin:0; }
  .doc h1 .o { color:#c74b25; }
  .tabs { display:flex; gap:8px; margin:24px 0 6px; background:#f4f7fb; padding:6px; border-radius:14px; }
  .tabs button { flex:1; border:none; background:none; padding:11px; border-radius:10px; font-weight:800; font-size:14px; cursor:pointer; color:#5b6b7a; }
  .tabs button.on { background:#fff; color:#c74b25; box-shadow:0 4px 12px rgba(29,52,72,.1); }
  .pane { display:none; }
  .pane.on { display:block; animation:fade .3s ease; }
  @keyframes fade { from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:none} }
  .sec { margin-top:26px; }
  .sec > h2 { display:flex; align-items:center; gap:12px; font-size:16.5px; font-weight:800; margin:0 0 12px; }
  .num { min-width:34px; height:34px; border-radius:12px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#e8823f,#c74b25); color:#fff; font-weight:800; font-size:14px; padding:0 8px; }
  .sec p, .sec li { font-size:14.5px; line-height:1.78; color:#294461; }
  .sec ul { margin:10px 0 0; padding-left:20px; }
  .sec li { margin-bottom:9px; }
  .sec li b, .sec p b { color:#294461; }
  .warn { background:#fef2f2; border:1px solid #f3b4b4; border-radius:14px; padding:14px 18px; font-size:13.5px; color:#7f1d1d; font-weight:600; margin-top:14px; }
  .contact { background:#fdf6ec; border:1px solid #e2e8f0; border-radius:16px; padding:18px 22px; margin-top:14px; font-size:14px; }
  .contact a { color:#c74b25; font-weight:700; }
  .rel { display:flex; flex-wrap:wrap; gap:8px; margin:26px 0 8px; }
  .rel a { font-size:12.5px; font-weight:700; color:#5b6b7a; background:#f4f7fb; border:1px solid #e2e8f0; padding:8px 14px; border-radius:999px; text-decoration:none; }
  .rel a:hover { color:#c74b25; border-color:#c74b25; }
  .foot { text-align:center; padding:0 20px 44px; font-size:12.5px; color:#7c8ba0; }
  @media (max-width:640px){ .doc { padding:26px 20px; } }
</style>
</head>
<body>

<div class="topbar">
  <div class="wrap" style="display:flex; justify-content:space-between; align-items:center; padding-top:12px; padding-bottom:12px">
    <a href="index.php" style="display:flex; align-items:center; gap:10px; text-decoration:none; color:#294461">
      <img src="assets/logo.png" alt="logo" style="width:36px; height:36px; border-radius:11px">
      <b><?= e($site) ?></b>
    </a>
    <a href="login.php" style="font-size:13.5px; font-weight:700; color:#c74b25; text-decoration:none">Student Login →</a>
  </div>
</div>

<div class="wrap">
  <div class="doc">
    <h1><?= e($title_en) ?></h1>
    <div class="urdu" dir="rtl" style="text-align:right; font-size:19px; font-weight:600; color:#5b6b7a; margin-top:6px"><?= e($title_ur) ?></div>
    <p style="font-size:12.5px; color:#7c8ba0; margin:10px 0 0">Last updated: October 2026 · <?= e($site) ?></p>

    <div class="tabs">
      <button class="on" onclick="legalTab('en', this)">English</button>
      <button onclick="legalTab('ur', this)"><span class="urdu">اردو</span></button>
    </div>

    <div class="pane on" id="pane-en">
      <?php $i=0; foreach ($sections as $s): $i++; ?>
      <div class="sec">
        <h2><span class="num"><?= $i ?></span> <?= e($s['en_t']) ?></h2>
        <div><?= $s['en_b'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="pane" id="pane-ur">
      <?php $i=0; foreach ($sections as $s): $i++; ?>
      <div class="sec">
        <h2><span class="num urdu" dir="rtl"><?= e($s['ur_n'] ?? $i) ?></span> <span class="urdu" dir="rtl"><?= e($s['ur_t']) ?></span></h2>
        <div class="urdu" dir="rtl" style="text-align:right"><?= $s['ur_b'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="rel">
      <a href="terms.php">Terms of Service</a>
      <a href="privacy.php">Privacy Policy</a>
      <a href="refund-policy.php">Refund Policy</a>
      <a href="disclaimer.php">Earnings Disclaimer</a>
      <a href="copyright.php">Anti-Piracy</a>
    </div>
  </div>
</div>

<div class="foot">
  © <?= date('Y') ?> <?= e($site) ?><br>
  <span style="display:inline-block; margin-top:8px"><?= legal_footer_links(' | ') ?></span>
</div>

<script>
function legalTab(which, btn){
  document.querySelectorAll('.tabs button').forEach(function(b){ b.classList.remove('on'); });
  btn.classList.add('on');
  document.getElementById('pane-en').classList.toggle('on', which === 'en');
  document.getElementById('pane-ur').classList.toggle('on', which === 'ur');
  window.scrollTo({top:0, behavior:'smooth'});
}
</script>
</body>
</html>
    <?php
}
