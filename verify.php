<?php
// Public certificate verification — login NOT required
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';

$code = trim($_GET['code'] ?? '');
$result = null; // null = not searched, 'notfound', or row
if ($code !== '') {
    try {
        $st = db()->prepare('SELECT c.cert_code, c.issued_at, u.name AS student_name, co.title AS course_title
                             FROM certificates c JOIN users u ON u.id = c.student_id
                             JOIN courses co ON co.id = c.course_id WHERE c.cert_code = ?');
        $st->execute([$code]);
        $result = $st->fetch() ?: 'notfound';
    } catch (Exception $ex) { $result = 'notfound'; }
}
$site = 'DigitalMax LMS';
try { $site = setting('site_name', $site); } catch (Exception $ex) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Certificate — <?= e($site) ?></title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family:'Plus Jakarta Sans',system-ui,sans-serif;
    background:#f4f7fb; color:#294461;
    min-height:100vh; display:flex; flex-direction:column;
  }
  /* top brand band */
  .band { background:#1d3448; padding:26px 20px 90px; text-align:center; position:relative; overflow:hidden; }
  .band::after {
    content:''; position:absolute; left:0; right:0; bottom:0; height:4px;
    background:linear-gradient(90deg,#c74b25 0%,#e07b4f 50%,#c74b25 100%);
  }
  .band .logo { width:52px; height:52px; border-radius:14px; object-fit:cover; box-shadow:0 6px 18px rgba(0,0,0,.25); }
  .band .word { color:#fff; font-weight:800; font-size:19px; letter-spacing:.4px; margin-top:10px; }
  .band .word span { color:#e07b4f; }
  /* card */
  .wrap { flex:1; display:flex; justify-content:center; padding:0 16px; }
  .card {
    background:#fff; width:100%; max-width:560px; border-radius:22px;
    box-shadow:0 24px 60px rgba(29,52,72,.14), 0 2px 6px rgba(29,52,72,.08);
    margin-top:-64px; padding:36px 34px 30px; position:relative;
  }
  .kicker {
    color:#c74b25; font-size:11.5px; font-weight:800; letter-spacing:2.5px;
    text-transform:uppercase; margin-bottom:8px;
  }
  h1 { font-family:'Fraunces',Georgia,serif; font-weight:700; font-size:30px; color:#1d3448; line-height:1.2; }
  .sub { color:#5b6b7a; font-size:14px; margin-top:8px; line-height:1.6; }
  /* form */
  .vform { display:flex; gap:10px; margin-top:22px; }
  .vform input {
    flex:1; border:1.5px solid #e2e8f0; border-radius:14px; padding:13px 16px;
    font-size:14px; font-family:inherit; color:#1d3448; outline:none; transition:border-color .15s, box-shadow .15s;
    text-transform:uppercase; letter-spacing:.5px; font-weight:600;
  }
  .vform input:focus { border-color:#c74b25; box-shadow:0 0 0 3px rgba(199,75,37,.12); }
  .vform input::placeholder { text-transform:none; letter-spacing:0; font-weight:400; color:#94a3b8; }
  .vform button {
    background:#c74b25; color:#fff; border:none; border-radius:14px; padding:13px 26px;
    font-weight:800; font-size:14px; cursor:pointer; font-family:inherit; white-space:nowrap;
    display:inline-flex; align-items:center; gap:8px; transition:background .15s, transform .1s;
  }
  .vform button:hover { background:#a93e1f; }
  .vform button:active { transform:scale(.97); }
  /* result: valid */
  .valid {
    margin-top:24px; border:1.5px solid #bbf7d0; background:#f0fdf4;
    border-radius:18px; padding:26px 24px; text-align:center;
  }
  .seal {
    width:74px; height:74px; border-radius:50%; margin:0 auto 14px;
    background:#dcfce7; border:2px solid #86efac;
    display:flex; align-items:center; justify-content:center; color:#15803d;
  }
  .valid h2 { font-family:'Fraunces',Georgia,serif; font-size:22px; color:#14532d; font-weight:700; }
  .valid .vsub { color:#4d7c5f; font-size:13px; margin-top:6px; }
  .drows { margin-top:20px; text-align:left; border-top:1px dashed #bbf7d0; padding-top:16px; }
  .drow { display:flex; align-items:center; gap:12px; padding:9px 2px; }
  .drow .ic {
    width:36px; height:36px; border-radius:10px; background:#dcfce7; color:#15803d;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
  }
  .drow .lb { font-size:11px; text-transform:uppercase; letter-spacing:1.2px; color:#6b8f76; font-weight:700; }
  .drow .vl { font-size:15px; font-weight:700; color:#1d3448; }
  .drow .vl.mono { font-family:ui-monospace,Menlo,monospace; letter-spacing:.5px; }
  /* result: invalid */
  .invalid {
    margin-top:24px; border:1.5px solid #fecaca; background:#fef2f2;
    border-radius:18px; padding:20px 22px; display:flex; gap:12px; align-items:flex-start;
    color:#991b1b; font-size:14px; line-height:1.55;
  }
  .invalid .ic { color:#dc2626; flex-shrink:0; margin-top:1px; }
  /* footer */
  .foot { text-align:center; padding:26px 16px 34px; color:#8fa0b3; font-size:13px; }
  .foot a { color:#c74b25; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
  .foot a:hover { text-decoration:underline; }
  .foot .note { margin-top:10px; font-size:12px; }
  svg.icn { display:inline-block; vertical-align:middle; }
  @media (max-width:480px) {
    .card { padding:28px 22px 24px; }
    h1 { font-size:25px; }
    .vform { flex-direction:column; }
    .vform button { justify-content:center; }
  }
</style>
</head>
<body>

  <div class="band">
    <img src="assets/logo.png" alt="DigitalMax" class="logo" onerror="this.style.display='none'">
    <div class="word">Digital<span>Max</span></div>
  </div>

  <div class="wrap">
    <div class="card">
      <div class="kicker">Official Verification</div>
      <h1>Certificate Verification</h1>
      <p class="sub">Apna Certificate ID neeche darj karein taake hum us ki authenticity confirm kar sakein.</p>

      <form method="get" class="vform">
        <input name="code" value="<?= e($code) ?>" placeholder="Certificate ID — misal: DML-AB12CD34EF" autocomplete="off" spellcheck="false">
        <button type="submit"><?= icon('search', 16) ?> Verify</button>
      </form>

      <?php if ($result === 'notfound'): ?>
        <div class="invalid">
          <span class="ic"><?= icon('x-circle', 20) ?></span>
          <span><b>Record nahi mila.</b><br>Ye Certificate ID hamare record me maujood nahi. ID dobara check karein ya support se rabta karein.</span>
        </div>
      <?php elseif (is_array($result)): ?>
        <div class="valid">
          <div class="seal"><?= icon('award', 34) ?></div>
          <h2>Authentic Certificate</h2>
          <p class="vsub">Ye certificate DigitalMax ke official record me verified hai.</p>
          <div class="drows">
            <div class="drow">
              <span class="ic"><?= icon('user', 17) ?></span>
              <span><span class="lb">Student</span><br><span class="vl"><?= e($result['student_name']) ?></span></span>
            </div>
            <div class="drow">
              <span class="ic"><?= icon('book-open', 17) ?></span>
              <span><span class="lb">Course</span><br><span class="vl"><?= e($result['course_title']) ?></span></span>
            </div>
            <div class="drow">
              <span class="ic"><?= icon('shield', 17) ?></span>
              <span><span class="lb">Certificate ID</span><br><span class="vl mono"><?= e($result['cert_code']) ?></span></span>
            </div>
            <div class="drow">
              <span class="ic"><?= icon('calendar', 17) ?></span>
              <span><span class="lb">Issued On</span><br><span class="vl"><?= e(date('d F Y', strtotime($result['issued_at']))) ?></span></span>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="foot">
    <a href="login.php"><?= icon('chevron-left', 14) ?> Back to LMS</a>
    <div class="note"><?= e($site) ?> — har certificate ki ID yahan verify ho sakti hai.</div>
  </div>

</body>
</html>
