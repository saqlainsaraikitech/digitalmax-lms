<?php
// Public payment page — login NOT required
// ?course=youtube-automation → us course ki image/title/amount ke sath
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/legal.php';

$pay_methods = active_payment_methods();
$site = 'DigitalMax LMS';
try { $site = setting('site_name', $site); } catch (Exception $ex) {}
$wa = '';
try { $wa = setting('whatsapp_number', ''); } catch (Exception $ex) {}

// course info (outlines se)
$outlines = include __DIR__ . '/inc/course_outlines.php';
$course_slug = trim($_GET['course'] ?? '');
$course = $outlines[$course_slug] ?? null;
$course_images = [
  'youtube-automation' => 'assets/courses/course-youtube.png',
  'facebook-automation' => 'assets/courses/course-facebook.png',
  'tiktok-automation' => 'assets/courses/course-tiktok.png',
];
$course_img = $course ? ($course_images[$course_slug] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $course ? e($course['name']) . ' — ' : '' ?>Payment Details — <?= e($site) ?></title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="stylesheet" href="assets/premium.css?v=<?= filemtime(__DIR__ . '/assets/premium.css') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Plus Jakarta Sans',system-ui,sans-serif; background:#0f1e2e; color:#294461; min-height:100vh; }
  /* hero band */
  .hero { position:relative; overflow:hidden; background:radial-gradient(1200px 500px at 50% -10%, #2a4a68 0%, #1d3448 55%, #142536 100%); padding:36px 20px 0; text-align:center; }
  .hero::after { content:''; position:absolute; left:0; right:0; bottom:0; height:4px; background:linear-gradient(90deg,#c74b25,#e8823f,#c74b25); }
  .hero .logo { width:54px; height:54px; border-radius:15px; box-shadow:0 8px 24px rgba(0,0,0,.35); }
  .hero .word { color:#fff; font-weight:800; font-size:20px; letter-spacing:.4px; margin-top:10px; }
  .hero .word span { color:#e8823f; }
  .hero .kicker { color:#e8823f; font-size:11px; font-weight:800; letter-spacing:3px; text-transform:uppercase; margin-top:26px; }
  .hero h1 { font-family:'Fraunces',Georgia,serif; font-weight:700; font-size:38px; color:#fff; margin:10px 0 8px; line-height:1.15; }
  .hero .sub { color:#aebdd0; font-size:14.5px; line-height:1.7; max-width:600px; margin:0 auto; }
  .hero .sub b { color:#fff; }
  /* course card — hero ke andar floating */
  .course-hero { max-width:720px; margin:30px auto -70px; position:relative; z-index:2; background:#fff; border-radius:22px; overflow:hidden; box-shadow:0 30px 70px rgba(0,0,0,.35); display:flex; text-align:left; }
  .course-hero img { width:42%; object-fit:cover; min-height:190px; }
  .course-hero .ci { padding:26px 28px; flex:1; display:flex; flex-direction:column; justify-content:center; }
  .course-hero .ct { font-size:11px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:#c74b25; }
  .course-hero h2 { font-family:'Fraunces',Georgia,serif; font-size:24px; color:#1d3448; margin:6px 0 4px; line-height:1.25; }
  .course-hero .cd { font-size:13px; color:#5b6b7a; }
  .course-hero .cp { margin-top:12px; display:flex; align-items:baseline; gap:10px; }
  .course-hero .cp .amt { font-size:30px; font-weight:800; color:#c74b25; }
  .course-hero .cp .per { font-size:12px; color:#7c8ba0; }
  .course-hero .cb { display:inline-block; margin-top:4px; font-size:11px; font-weight:700; background:#fef3c7; color:#b45309; border:1px solid #fcd34d; padding:3px 10px; border-radius:999px; }
  /* body */
  .body { background:#f4f7fb; padding:100px 16px 10px; }
  .wrap { max-width:860px; margin:0 auto; }
  .sec { background:#fff; border-radius:22px; box-shadow:0 18px 50px rgba(29,52,72,.09); padding:32px 30px; margin-bottom:22px; }
  .sec h2 { font-family:'Fraunces',Georgia,serif; font-size:22px; color:#1d3448; margin-bottom:4px; }
  .sec .hint { color:#5b6b7a; font-size:13.5px; margin-bottom:20px; line-height:1.6; }
  .warn { background:#fffbeb; border:1.5px solid #fcd34d; color:#92400e; border-radius:14px; padding:14px 16px; font-size:13.5px; margin-bottom:20px; line-height:1.65; display:flex; gap:10px; }
  .warn svg { flex-shrink:0; margin-top:2px; }
  .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
  .step { background:#f8fafc; border:1px solid #e2e8f0; border-radius:16px; padding:18px 16px; position:relative; overflow:hidden; }
  .step::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,#c74b25,#e8823f); }
  .step .n { width:30px; height:30px; border-radius:50%; background:#1d3448; color:#fff; font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; }
  .step b { display:block; font-size:13.5px; color:#1d3448; margin-bottom:4px; }
  .step p { font-size:12.5px; color:#5b6b7a; line-height:1.6; }
  .cta-row { display:flex; gap:12px; flex-wrap:wrap; margin-top:22px; }
  .btn-p { display:inline-flex; align-items:center; gap:8px; background:#c74b25; color:#fff; font-weight:800; font-size:14px; padding:14px 30px; border-radius:14px; text-decoration:none; transition:all .15s; box-shadow:0 10px 26px -8px rgba(199,75,37,.55); }
  .btn-p:hover { background:#a93e1f; transform:translateY(-1px); }
  .btn-wa { display:inline-flex; align-items:center; gap:8px; background:#25d366; color:#fff; font-weight:800; font-size:14px; padding:14px 30px; border-radius:14px; text-decoration:none; box-shadow:0 10px 26px -8px rgba(37,211,102,.55); transition:all .15s; }
  .btn-wa:hover { transform:translateY(-1px); }
  .btn-o { display:inline-flex; align-items:center; gap:8px; border:2px solid #cbd5e1; color:#475569; font-weight:700; font-size:14px; padding:12px 28px; border-radius:14px; text-decoration:none; }
  .foot { text-align:center; padding:26px 16px 38px; color:#8fa0b3; font-size:12.5px; background:#f4f7fb; }
  .foot a { color:#5b6b7a; }
  #payToast { position:fixed; bottom:24px; left:50%; transform:translateX(-50%) translateY(20px); background:#1d3448; color:#fff; font-size:13px; font-weight:600; padding:10px 20px; border-radius:999px; opacity:0; pointer-events:none; transition:all .25s; z-index:99; }
  #payToast.show { opacity:1; transform:translateX(-50%) translateY(0); }
  svg.icn { display:inline-block; vertical-align:middle; }
  @media (max-width:640px){
    .hero h1{font-size:28px;}
    .course-hero{flex-direction:column; margin:24px 16px -60px;}
    .course-hero img{width:100%; height:170px; min-height:0;}
    .sec{padding:24px 20px;}
  }
</style>
</head>
<body>

<div class="hero">
  <a href="index.php"><img src="assets/logo.png" alt="DigitalMax" class="logo" onerror="this.style.display='none'"></a>
  <div class="word">Digital<span>Max</span></div>
  <div class="kicker">Official Payment Information</div>
  <h1><?= $course ? 'Enroll in<br>' . e($course['name']) : 'Payment Details' ?></h1>
  <p class="sub">Fees sirf neeche diye gaye <b>official accounts</b> par bhejein — kisi aur number par bheji gayi payment ki zimmedari nahi hogi.</p>

  <?php if ($course): ?>
  <div class="course-hero">
    <?php if ($course_img): ?><img src="<?= e($course_img) ?>" alt="<?= e($course['name']) ?>"><?php endif; ?>
    <div class="ci">
      <div class="ct">Selected Course</div>
      <h2><?= e($course['name']) ?></h2>
      <div class="cd"><?= e($course['duration'] ?? '') ?> · Certificate · Lifetime access</div>
      <div class="cp"><span class="amt">Rs <?= number_format((float)($course['fee'] ?? 0)) ?></span><span class="per">one-time fee</span></div>
      <div><span class="cb"><?= icon('shield', 11) ?> Verify ke baad hi access</span></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<div class="body">
<div class="wrap">

  <div class="sec">
    <h2>Official Accounts</h2>
    <p class="hint">Number par tap karke copy karein. Payment ke baad screenshot lazmi rakhein.</p>
    <?php if (!$pay_methods): ?>
      <div class="warn"><?= icon('alert', 16) ?><span>Payment details jald update ho rahe hain — support se rabta karein.</span></div>
    <?php else: ?>
      <div class="warn"><?= icon('alert', 16) ?><span><b>Zaroori:</b> Sirf neeche diye gaye official accounts par payment bhejein.</span></div>
      <div class="pay-cards">
        <?php foreach ($pay_methods as $m): ?><?= payment_method_card($m) ?><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="sec">
    <h2>Payment Process</h2>
    <p class="hint">Enroll karne ka tareeqa — 4 asaan steps:</p>
    <div class="steps">
      <div class="step"><span class="n">1</span><b>Fees bhejein</b><p>Oopar official account par fees transfer karein — <b>screenshot</b> lein, <b>11–12 digit TID</b> note karein.</p></div>
      <div class="step"><span class="n">2</span><b>WhatsApp par proof</b><p>Screenshot + TID aur apna <b>naam + mobile number</b> WhatsApp par bhejein.</p></div>
      <div class="step"><span class="n">3</span><b>Verification</b><p>Hamari team <b>15–60 minute</b> me payment verify karegi.</p></div>
      <div class="step"><span class="n">4</span><b>Login details</b><p>Verify hote hi admin aapko <b>LMS login details</b> dega.</p></div>
    </div>
    <div class="cta-row">
      <?php if ($wa): ?><a href="https://wa.me/<?= e(preg_replace('/\D/', '', $wa)) ?>?text=<?= urlencode('Assalam-o-Alaikum! Maine ' . ($course['name'] ?? 'DigitalMax course') . ' ki fees bhej di hai. Screenshot aur TID ye raha:') ?>" class="btn-wa" target="_blank"><?= icon('phone', 15) ?> WhatsApp par Proof Bhejein</a><?php endif; ?>
      <a href="login.php" class="btn-o">Pehle se account hai? Login</a>
    </div>
  </div>

</div>
</div>

<div class="foot">
  <span><?= legal_footer_links(' | ') ?></span><br>
  <span style="display:inline-block; margin-top:8px"><?= e($site) ?> — har payment verify hone ke baad hi access milta hai.</span>
</div>
<div id="payToast"></div>

<script>
document.querySelectorAll('.pay-copy-btn').forEach(function(b){
  b.addEventListener('click', function(){
    var t = b.getAttribute('data-copy');
    function done(){
      var toast = document.getElementById('payToast');
      if (toast) { toast.textContent = 'Copied: ' + t; toast.classList.add('show'); setTimeout(function(){ toast.classList.remove('show'); }, 1800); }
      var sp = b.querySelector('span'); if (sp) { var old = sp.textContent; sp.textContent = 'Copied!'; setTimeout(function(){ sp.textContent = old; }, 1500); }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done).catch(done);
    else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch(e){} document.body.removeChild(ta); done(); }
  });
});
</script>
</body>
</html>
