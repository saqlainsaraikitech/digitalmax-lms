<?php
// DigitalMax LMS — Sales landing page (DigitalMax light scheme)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/auth.php';

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

// FOMO: deadline (default = is mahine ki akhri tareekh)
$deadline = setting('batch_deadline', '');
$ts = $deadline !== '' ? strtotime($deadline) : false;
if (!$ts || $ts < time()) {
    $ts = strtotime('last day of this month 23:59:59');
    if ($ts < time()) $ts = strtotime('last day of next month 23:59:59');
}
$seats_left  = max(0, (int)setting('seats_remaining', 7));
$seats_total = max(1, (int)setting('seats_total', 30));
$seats_pct   = min(100, round((1 - $seats_left / $seats_total) * 100));

// earnings proof screenshots (assets/proof/ me khud upload karein)
$proofs = glob(__DIR__ . '/assets/proof/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
$proofs = array_values(array_filter(array_map('basename', $proofs), fn($f) => $f !== 'README.txt'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($site) ?> — AI Automation & Online Earning Courses in Pakistan</title>
<meta name="description" content="Pakistan ka practical online earning program — AI automation se faceless content aur monetization, simple Urdu me. Limited seats!">
<link rel="icon" type="image/png" href="assets/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  * { font-family:'Plus Jakarta Sans',system-ui,sans-serif; box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body { margin:0; background:#ffffff; color:#294461; }
  .ico { display:inline-block; vertical-align:-2px; }
  .wrap { max-width:1120px; margin:0 auto; padding:0 20px; }
  /* nav */
  .lp-nav { position:sticky; top:0; z-index:50; background:rgba(250,248,244,.88); backdrop-filter:blur(14px); border-bottom:1px solid #e2e8f0; }
  .lp-nav a.nl { color:#5b6b7a; font-weight:600; font-size:14px; text-decoration:none; }
  .lp-nav a.nl:hover { color:#c74b25; }
  .btn-o { display:inline-block; background:#c74b25; color:#fff !important; font-weight:700; border-radius:14px; text-decoration:none; transition:transform .15s, background .18s, box-shadow .18s; box-shadow:0 12px 26px -12px rgba(199,75,37,.6); }
  .btn-o:hover { background:#a93e1e; transform:translateY(-2px); }
  .btn-g { display:inline-block; background:#fff; color:#294461 !important; font-weight:700; border-radius:14px; text-decoration:none; border:1.5px solid #e2e8f0; transition:all .18s; }
  .btn-g:hover { border-color:#c74b25; color:#c74b25 !important; transform:translateY(-2px); }
  /* hero */
  .hero {
    background:
      radial-gradient(700px 340px at 85% 10%, rgba(199,75,37,.1), transparent 60%),
      radial-gradient(rgba(199,75,37,.1) 1px, transparent 1px),
      linear-gradient(180deg, #fdf6ec 0%, #ffffff 70%);
    background-size:auto, 26px 26px, auto;
  }
  .kicker { display:inline-flex; align-items:center; gap:8px; font-size:11px; font-weight:800; letter-spacing:.16em; color:#c74b25; border:1px solid rgba(199,75,37,.35); background:rgba(199,75,37,.07); padding:8px 18px; border-radius:999px; }
  .pulse-dot { width:8px; height:8px; border-radius:50%; background:#c74b25; animation:pd 1.6s infinite; }
  @keyframes pd { 0%,100%{opacity:1; box-shadow:0 0 0 0 rgba(199,75,37,.4)} 50%{opacity:.6; box-shadow:0 0 0 7px rgba(199,75,37,0)} }
  .hero h1 { font-size:clamp(36px,5.4vw,62px); font-weight:800; letter-spacing:-.03em; line-height:1.06; margin:22px 0 0; }
  .hero h1 .u { position:relative; white-space:nowrap; z-index:0; }
  .hero h1 .u::after { content:''; position:absolute; left:0; right:0; bottom:6px; height:12px; background:rgba(199,75,37,.28); z-index:-1; border-radius:4px; }
  .hero .sub { color:#5b6b7a; font-size:17px; line-height:1.7; max-width:620px; margin:20px auto 0; }
  .hero .sub b { color:#294461; }
  .hstat b { font-size:26px; font-weight:800; letter-spacing:-.02em; display:block; }
  .hstat span { font-size:12px; color:#5b6b7a; font-weight:600; }
  /* fomo band */
  .fomo { background:linear-gradient(120deg,#1d3448,#294461); color:#f4f7fb; }
  .cd-box { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:16px; padding:10px 0; min-width:74px; text-align:center; }
  .cd-box b { font-size:26px; font-weight:800; color:#f0a35e; display:block; letter-spacing:-.02em; }
  .cd-box span { font-size:10px; color:#7c8ba0; font-weight:600; letter-spacing:.08em; text-transform:uppercase; }
  .seats-bar { height:12px; border-radius:999px; background:rgba(255,255,255,.12); overflow:hidden; }
  .seats-bar > div { height:100%; border-radius:999px; background:linear-gradient(90deg,#c74b25,#f0a35e); }
  /* cards */
  .course-card { background:#fff; border:1px solid #e2e8f0; border-radius:22px; overflow:hidden; display:flex; flex-direction:column; transition:transform .2s, box-shadow .2s; }
  .course-card:hover { transform:translateY(-5px); box-shadow:0 24px 50px -20px rgba(29,52,72,.25); }
  .sec-title { font-size:clamp(26px,3.4vw,36px); font-weight:800; letter-spacing:-.025em; text-align:center; margin:0; }
  .sec-title .o { color:#c74b25; }
  .sec-sub { text-align:center; color:#5b6b7a; margin:10px 0 0; font-size:15px; }
  /* accordion */
  .acc { background:#fff; border:1px solid #e2e8f0; border-radius:16px; margin-bottom:12px; overflow:hidden; }
  .acc-body { max-height:0; overflow:hidden; transition:max-height .35s ease; }
  .acc.open .acc-body { max-height:2000px; }
  .acc.open .acc-icon { transform:rotate(45deg); color:#c74b25; }
  .acc-icon { transition:transform .25s; color:#7c8ba0; font-size:22px; font-weight:300; line-height:1; }
  .curr-pill { border:1px solid #e2e8f0; background:#fff; }
  .curr-pill.on { background:#c74b25 !important; color:#fff !important; border-color:#c74b25; }
  /* proof slider */
  .proof-dot { width:10px; height:10px; border-radius:50%; background:#e2e8f0; border:none; cursor:pointer; padding:0; }
  /* final cta */
  .cta-band { background:linear-gradient(120deg,#1d3448,#294461 55%,#3a5a76); color:#f4f7fb; border-radius:28px; }
  footer.lp-foot { border-top:1px solid #e2e8f0; color:#7c8ba0; font-size:12.5px; }
  footer.lp-foot a { color:#5b6b7a; }
  @keyframes rise { from{opacity:0; transform:translateY(16px)} to{opacity:1; transform:none} }
  .rise { animation:rise .6s ease both; }
</style>
</head>
<body>

<!-- NAV -->
<nav class="lp-nav">
  <div class="wrap flex justify-between items-center" style="padding-top:12px; padding-bottom:12px">
    <a href="#" class="flex items-center gap-2.5" style="text-decoration:none; color:#294461">
      <img src="assets/logo.png" alt="logo" style="width:38px; height:38px; border-radius:12px; box-shadow:0 6px 16px -6px rgba(199,75,37,.5)">
      <span style="font-weight:800; font-size:17px"><?= e($site) ?></span>
    </a>
    <div class="hidden md:flex gap-7">
      <a class="nl" href="#courses">Courses</a>
      <a class="nl" href="#curriculum">Curriculum</a>
      <a class="nl" href="#results">Results</a>
      <a class="nl" href="#faq">FAQ</a>
    </div>
    <div class="flex gap-2">
      <a href="login.php" class="btn-g" style="padding:10px 20px; font-size:13.5px">Login</a>
      <a href="#courses" class="btn-o" style="padding:10px 20px; font-size:13.5px">Join Batch Now</a>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="hero">
  <div class="wrap text-center" style="padding-top:72px; padding-bottom:64px">
    <span class="kicker rise"><span class="pulse-dot"></span> ADMISSIONS OPEN — <?= e(date('F Y', $ts)) ?> BATCH</span>
    <h1 class="rise" style="animation-delay:.08s">Master <span class="u">Online Earning</span><br>with AI Automation</h1>
    <p class="sub rise" style="animation-delay:.16s">Zero se hero tak — AI se faceless videos bana kar dollars kamana, <b>simple Urdu me, step-by-step</b>. Koi experience nahi chahiye.</p>
    <div class="rise flex gap-3 justify-center flex-wrap" style="margin-top:32px; animation-delay:.24s">
      <a href="#courses" class="btn-o" style="padding:16px 40px; font-size:16px">Join Batch Now →</a>
      <a href="#curriculum" class="btn-g" style="padding:16px 32px; font-size:16px">View Curriculum</a>
    </div>
    <div class="rise flex gap-10 justify-center" style="margin-top:40px; animation-delay:.32s">
      <div class="hstat"><b><?= $stats['students'] ?>+</b><span>Students</span></div>
      <div class="hstat"><b><?= $stats['lectures'] ?>+</b><span>Video Lectures</span></div>
      <div class="hstat"><b><?= icon('graduation-cap', 26) ?></b><span>Verified Certificate</span></div>
    </div>
  </div>
</header>

<!-- FOMO -->
<section class="fomo">
  <div class="wrap flex flex-col md:flex-row items-center justify-between gap-8" style="padding-top:36px; padding-bottom:36px">
    <div class="text-center md:text-left">
      <div style="font-size:12px; font-weight:800; letter-spacing:.14em; color:#f0a35e"><?= icon('clock', 14) ?> CURRENT BATCH ADMISSIONS CLOSING IN</div>
      <div class="flex gap-2.5 mt-4 justify-center md:justify-start" id="countdown">
        <?php foreach ([['cd-d','Days'],['cd-h','Hours'],['cd-m','Mins'],['cd-s','Secs']] as [$id,$lbl]): ?>
        <div class="cd-box"><b id="<?= $id ?>">--</b><span><?= $lbl ?></span></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="w-full md:w-80">
      <div class="flex justify-between text-sm mb-2.5">
        <span style="font-weight:700"><?= icon('flame', 15) ?> Only <span style="color:#f0a35e"><?= $seats_left ?> seats</span> remaining</span>
      </div>
      <div class="seats-bar"><div style="width:<?= $seats_pct ?>%"></div></div>
      <div class="text-xs mt-2" style="color:#7c8ba0"><?= $seats_pct ?>% seats filled</div>
    </div>
  </div>
</section>
<script>
(function(){
  var target = <?= (int)$ts ?> * 1000;
  function pad(n){ return (n<10?'0':'')+n; }
  function tick(){
    var d = Math.max(0, target - Date.now());
    var days = Math.floor(d/86400000), hrs = Math.floor(d%86400000/3600000),
        min = Math.floor(d%3600000/60000), sec = Math.floor(d%60000/1000);
    document.getElementById('cd-d').textContent = pad(days);
    document.getElementById('cd-h').textContent = pad(hrs);
    document.getElementById('cd-m').textContent = pad(min);
    document.getElementById('cd-s').textContent = pad(sec);
  }
  setInterval(tick, 1000); tick();
})();
</script>

<!-- COURSES — tamam courses (outlines se, DB match ke sath) -->
<?php
// DB courses ko outline se match karo (naam se)
function match_db_course($db_courses, $outline_name) {
    $on = mb_strtolower($outline_name);
    foreach ($db_courses as $dc) {
        $dn = mb_strtolower($dc['title']);
        // "youtube" jaisa keyword match
        foreach (['youtube','facebook','tiktok'] as $kw) {
            if (mb_strpos($on, $kw) !== false && mb_strpos($dn, $kw) !== false) return $dc;
        }
        if ($dn === $on) return $dc;
    }
    return null;
}
?>
<section id="courses" class="wrap" style="padding-top:72px; padding-bottom:72px">
  <h2 class="sec-title">Choose Your <span class="o">Batch</span></h2>
  <p class="sec-sub">Payment Easypaisa/JazzCash se — screenshot bhejo, admin verify karke course unlock kar dega.
    <a href="pay.php" style="color:#c74b25; font-weight:700">Payment Details dekhein →</a></p>
  <div class="grid md:grid-cols-3 gap-6" style="margin-top:40px">
    <?php foreach ($outlines as $slug => $oc):
      $dbc = match_db_course($courses, $oc['name']);
      $thumb = $dbc['thumbnail'] ?? '';
      if (!$thumb) {
        // DigitalMax wali course image fallback
        $slug_img = ['youtube-automation' => 'course-youtube.png', 'facebook-automation' => 'course-facebook.png', 'tiktok-automation' => 'course-tiktok.png'];
        if (!empty($slug_img[$slug])) $thumb = 'assets/courses/' . $slug_img[$slug];
      }
      $price = $dbc ? $dbc['price'] : $oc['fee'];
      $lec_info = $dbc ? (int)$dbc['lc'] . ' video lectures' : e($oc['duration']);
      $desc = $dbc && !empty($dbc['description']) ? mb_substr($dbc['description'], 0, 120) : e($oc['tagline']);
    ?>
    <div class="course-card">
      <?php if ($thumb): ?><img src="<?= e($thumb) ?>" style="width:100%; height:170px; object-fit:cover">
      <?php else: ?><div style="width:100%; height:170px; background:linear-gradient(135deg,#1d3448,#294461); display:flex; align-items:center; justify-content:center; color:#fff; font-family:Fraunces,serif; font-size:22px; font-weight:700; padding:20px; text-align:center"><?= e($oc['name']) ?></div><?php endif; ?>
      <div style="padding:22px; flex:1; display:flex; flex-direction:column">
        <b style="font-size:17px"><?= e($oc['name']) ?></b>
        <p class="text-sm mt-2 flex-1" style="color:#5b6b7a"><?= $desc ?></p>
        <div class="text-xs mt-3" style="color:#7c8ba0"><?= $lec_info ?> · Certificate · Lifetime access</div>
        <div class="flex justify-between items-center mt-5">
          <span style="font-size:24px; font-weight:800; color:#c74b25"><?= $price ? money($price) : 'Price jald' ?></span>
          <a href="pay.php?course=<?= e($slug) ?>" class="btn-o" style="padding:11px 22px; font-size:13.5px">Enroll Now →</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- HOW TO PAY -->
<section style="background:#fff; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0">
  <div class="wrap" style="padding-top:64px; padding-bottom:64px">
    <h2 class="sec-title">How to <span class="o">Pay</span> — 3 Easy Steps</h2>
    <p class="sec-sub">Manual verification — koi online gateway nahi, seedha aur mehfooz.</p>
    <div class="grid md:grid-cols-3 gap-5" style="margin-top:36px">
      <?php foreach ([
        ['wallet', 'Step 1 — Official details lein', 'Course select karein — neeche diye gaye official EasyPaisa / JazzCash / Bank account par fees bhejein. Number copy button se copy karein.'],
        ['upload', 'Step 2 — Screenshot lein', 'Payment ke baad SMS/App se 11–12 digit Transaction ID (TID) copy karein aur receipt ka screenshot lein.'],
        ['check-circle', 'Step 3 — Portal par submit karein', 'TID + screenshot upload karein — hamari team 15–60 minute me verify karke dashboard activate kar degi.'],
      ] as $i => [$icn, $t, $d]): ?>
      <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:20px; padding:26px; position:relative">
        <div style="position:absolute; top:18px; right:20px; font-size:40px; font-weight:800; color:#e2e8f0"><?= $i+1 ?></div>
        <div style="width:48px; height:48px; border-radius:15px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#e8823f,#c74b25); color:#fff; margin-bottom:16px"><?= icon($icn, 22) ?></div>
        <b style="font-size:15.5px"><?= e($t) ?></b>
        <p style="font-size:13.5px; color:#5b6b7a; line-height:1.7; margin:8px 0 0"><?= e($d) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CURRICULUM — DigitalMax website outlines (inc/course_outlines.php) -->
<section id="curriculum" style="background:#fff; border-top:1px solid #e2e8f0; border-bottom:1px solid #e2e8f0">
  <div class="wrap" style="max-width:820px; padding-top:72px; padding-bottom:72px">
    <h2 class="sec-title">What You'll <span class="o">Learn</span></h2>
    <p class="sec-sub">Har course ka mukammal roadmap — module kholo aur topics dekho</p>
    <div class="flex gap-2 justify-center flex-wrap" style="margin:28px 0" id="currPills">
      <?php $i=0; foreach ($outlines as $slug => $oc): $i++; ?>
        <button onclick="showCurr('<?= e($slug) ?>', this)" class="curr-pill <?= $i===1?'on':'' ?>" style="padding:9px 20px; border-radius:999px; font-size:13px; font-weight:700; cursor:pointer; color:#5b6b7a"><?= e($oc['name']) ?></button>
      <?php endforeach; ?>
    </div>
    <?php $ci=0; foreach ($outlines as $slug => $oc): $ci++; ?>
    <div class="curr-block <?= $ci>1?'hidden':'' ?>" id="curr-<?= e($slug) ?>">
      <?php if (!empty($oc['tagline'])): ?><p class="text-center text-sm" style="color:#7c8ba0; margin-bottom:18px"><?= e($oc['tagline']) ?><?php if (!empty($oc['duration'])): ?> · <?= e($oc['duration']) ?><?php endif; ?></p><?php endif; ?>
      <?php $mi=0; foreach ($oc['parts'] as $part): $mi++; $mods = $part['modules'] ?? []; ?>
      <div class="acc">
        <button class="w-full flex justify-between items-center text-left" style="padding:18px 20px; background:none; border:none; cursor:pointer; width:100%" onclick="toggleAcc(this)">
          <span><b><?= $mi ?>. <?= e($part['t']) ?></b><br><span class="text-xs" style="color:#7c8ba0"><?= count($mods) ?> topics</span></span>
          <span class="acc-icon">+</span>
        </button>
        <div class="acc-body">
          <ul style="padding:0 20px 18px; margin:0; list-style:none">
            <?php foreach ($mods as $m): ?>
            <li class="text-sm" style="padding:10px 0; border-top:1px solid #f4f7fb">
              <span style="display:flex; gap:8px"><span style="color:#c74b25; flex-shrink:0; margin-top:2px"><?= icon('check', 12) ?></span><span><b><?= e($m['t']) ?></b><?php if (!empty($m['d'])): ?><br><span class="text-xs" style="color:#7c8ba0"><?= e($m['d']) ?></span><?php endif; ?></span></span>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<script>
function toggleAcc(btn){
  var acc = btn.closest('.acc');
  var wasOpen = acc.classList.contains('open');
  acc.parentElement.querySelectorAll('.acc.open').forEach(function(a){ a.classList.remove('open'); });
  if (!wasOpen) acc.classList.add('open');
}
function showCurr(cid, btn){
  document.querySelectorAll('.curr-block').forEach(function(b){ b.classList.add('hidden'); });
  document.getElementById('curr-' + cid).classList.remove('hidden');
  document.querySelectorAll('.curr-pill').forEach(function(p){ p.classList.remove('on'); });
  btn.classList.add('on');
}
</script>

<!-- RESULTS -->
<section id="results" class="wrap" style="max-width:900px; padding-top:72px; padding-bottom:72px">
  <h2 class="sec-title">Student <span class="o">Results</span></h2>
  <p class="sec-sub">Real dashboards, real earnings — hamare students ke results.</p>
  <?php if ($proofs): ?>
  <div class="relative" style="margin-top:36px">
    <div class="overflow-hidden" id="proofBox" style="border-radius:22px; border:1px solid #e2e8f0">
      <div class="flex transition-transform duration-500" id="proofTrack">
        <?php foreach ($proofs as $p): ?>
        <div class="min-w-full"><img src="assets/proof/<?= e($p) ?>" alt="Student result" class="w-full object-cover" style="height:340px"></div>
        <?php endforeach; ?>
      </div>
    </div>
    <button onclick="proofMove(-1)" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:42px; height:42px; border-radius:50%; border:none; background:#fff; box-shadow:0 8px 20px rgba(0,0,0,.15); cursor:pointer; font-size:18px; color:#294461">‹</button>
    <button onclick="proofMove(1)" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); width:42px; height:42px; border-radius:50%; border:none; background:#fff; box-shadow:0 8px 20px rgba(0,0,0,.15); cursor:pointer; font-size:18px; color:#294461">›</button>
    <div class="flex gap-2 justify-center mt-5" id="proofDots"></div>
  </div>
  <script>
  (function(){
    var track = document.getElementById('proofTrack');
    var n = track.children.length, i = 0, timer;
    var dots = document.getElementById('proofDots');
    for (var k=0;k<n;k++){ var d=document.createElement('button'); d.className='proof-dot'; d.setAttribute('data-k',k); d.onclick=function(){ proofGo(+this.getAttribute('data-k')); }; dots.appendChild(d); }
    function paint(){ track.style.transform='translateX(-'+(i*100)+'%)'; [...dots.children].forEach(function(dt,idx){ dt.style.background = idx===i ? '#c74b25' : '#e2e8f0'; }); }
    window.proofGo = function(k){ i=(k+n)%n; paint(); restart(); };
    window.proofMove = function(d){ proofGo(i+d); };
    function restart(){ clearInterval(timer); timer=setInterval(function(){ proofGo(i+1); }, 5000); }
    document.getElementById('proofBox').addEventListener('mouseenter', function(){ clearInterval(timer); });
    document.getElementById('proofBox').addEventListener('mouseleave', restart);
    paint(); restart();
  })();
  </script>
  <?php else: ?>
  <div style="margin-top:36px; border-radius:22px; padding:48px 24px; text-align:center; background:#fff; border:1.5px dashed #e2e8f0">
    <div style="color:#c74b25; margin-bottom:12px"><?= icon('image', 40) ?></div>
    <p style="color:#5b6b7a; margin:0">Student results jald yahan display hon ge.<br><span class="text-xs" style="color:#9fb0c3">Screenshots <code>assets/proof/</code> folder me upload karein — slider khud ban jayega.</span></p>
  </div>
  <?php endif; ?>
</section>

<!-- FAQ -->
<section id="faq" class="wrap" style="max-width:720px; padding-bottom:72px">
  <h2 class="sec-title" style="margin-bottom:32px">Common <span class="o">Questions</span></h2>
  <?php
  $faqs = [
    ['Payment kaise karun?', 'Easypaisa ya JazzCash par payment karke screenshot upload karein — admin verify karke aapka course unlock kar dega.'],
    ['Course kis language me hai?', 'Simple Urdu me — har step screen par practically karke dikhaya gaya hai.'],
    ['Kya certificate milega?', 'Jee! Course 100% complete karne par unique verification ID wala professional certificate milega.'],
    ['Access kitne arse tak rahega?', 'Lifetime access — ek dafa enroll, hamesha ke liye.'],
    ['Kya mobile par dekh sakta hun?', 'Bilkul — mobile, tablet aur laptop sab par chalega.'],
  ];
  foreach ($faqs as [$q,$a]): ?>
  <div class="acc">
    <button class="w-full flex justify-between items-center text-left" style="padding:18px 20px; background:none; border:none; cursor:pointer; width:100%; font-weight:700" onclick="toggleAcc(this)">
      <span><?= e($q) ?></span><span class="acc-icon">+</span>
    </button>
    <div class="acc-body"><p style="padding:0 20px 18px; margin:0; font-size:14px; color:#5b6b7a"><?= e($a) ?></p></div>
  </div>
  <?php endforeach; ?>
</section>

<!-- FINAL CTA -->
<section class="wrap" style="padding-bottom:72px">
  <div class="cta-band text-center" style="padding:60px 24px">
    <div style="color:#f0a35e; margin-bottom:14px"><?= icon('rocket', 34) ?></div>
    <h2 style="font-size:clamp(26px,3.6vw,38px); font-weight:800; letter-spacing:-.02em; margin:0">Ab waqt hai <span style="color:#f0a35e">action</span> lene ka</h2>
    <p style="color:#5b6b7a; margin:12px 0 0">Seats limited hain — batch fill hone ke baad admissions band.</p>
    <a href="#courses" class="btn-o" style="margin-top:28px; padding:16px 48px; font-size:16px">Join Batch Now →</a>
  </div>
</section>

<footer class="lp-foot">
  <div class="wrap text-center" style="padding-top:28px; padding-bottom:28px">
    © <?= date('Y') ?> <?= e($site) ?><br>
    <span style="display:inline-block; margin:10px 0"><?= legal_footer_links(' | ') ?></span><br>
    <a href="login.php" style="text-decoration:underline">Student Login</a>
  </div>
</footer>

<?= whatsapp_float() ?>
</body>
</html>
