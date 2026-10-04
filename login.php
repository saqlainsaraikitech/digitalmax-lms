<?php
// DigitalMax LMS - Login (crafted split edition)
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/auth.php';

if (current_user()) redirect('index.php');
$error = '';
$banned_uid = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Brute-force protection: 1 IP se 5 ghalat koshishen → 15 min block
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $blocked = false;
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            ip VARCHAR(45) NOT NULL PRIMARY KEY,
            attempts INT NOT NULL DEFAULT 0,
            last_attempt DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $att = $pdo->prepare('SELECT attempts, last_attempt FROM login_attempts WHERE ip = ?');
        $att->execute([$ip]);
        if ($r = $att->fetch()) {
            $blocked = (int)$r['attempts'] >= 5 && strtotime($r['last_attempt']) > time() - 900;
        }
    } catch (Exception $ex) { $blocked = false; }

    if ($blocked) {
        $error = 'Bohat zyada ghalat koshishen — 15 minute baad dobara try karein.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        ensure_ban_columns();
        $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        $ok = $u && password_verify($pass, $u['password']);
        if ($ok && ($u['status'] ?? '') === 'banned') {
            $banned_uid = (int)$u['id']; // 🚫 red banned card (attempt record nahi)
        } elseif ($ok && ($u['status'] ?? '') === 'active') {
            try { db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]); } catch (Exception $ex) {}
            session_regenerate_id(true);
            $_SESSION['user_id'] = $u['id'];
            set_current_session($u['id']); // single-device: ye session DB me save
            if (!isset($_POST['remember_me'])) revoke_remember_tokens($u['id']);
            else issue_remember_token($u['id']); // 30 din ka auto-login
            redirect($u['role'] === 'student' ? 'student/index.php' : 'admin/index.php');
        } elseif ($ok) {
            $error = 'Aapka account inactive hai — admin se rabta karein.';
        } else {
            try {
                db()->prepare('INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, NOW())
                                ON DUPLICATE KEY UPDATE attempts = IF(last_attempt < NOW() - INTERVAL 15 MINUTE, 1, attempts + 1), last_attempt = NOW()')
                   ->execute([$ip]);
            } catch (Exception $ex) {}
            $error = 'Email ya password ghalat hai.';
        }
    }
}
$kick = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$site = setting('site_name', 'DigitalMax LMS');

// real stats (koi fake numbers nahi)
$st_students = $st_courses = $st_lectures = 0;
try {
    $pdo = db();
    $st_students = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $st_courses  = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();
    $st_lectures = (int)$pdo->query('SELECT COUNT(*) FROM lectures')->fetchColumn();
} catch (Exception $ex) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= e($site) ?></title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  * { font-family:'Plus Jakarta Sans',system-ui,sans-serif; box-sizing:border-box; }
  body { margin:0; background:#ffffff; color:#294461; }
  .login-wrap { min-height:100vh; display:flex; }

  /* ---- LEFT: brand panel ---- */
  .login-side {
    width:46%; max-width:620px; flex-shrink:0; color:#f4f7fb;
    background:
      radial-gradient(600px 300px at 100% 0%, rgba(199,75,37,.16), transparent 60%),
      radial-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
      #1d3448;
    background-size:auto, 26px 26px, auto;
    display:flex; flex-direction:column; justify-content:space-between;
    padding:44px 48px; position:relative;
  }
  .login-side::after {
    content:''; position:absolute; top:0; right:0; width:1px; height:100%;
    background:linear-gradient(180deg, transparent, rgba(232,130,63,.6), transparent);
  }
  .brand-row { display:flex; align-items:center; gap:14px; }
  .brand-row img { width:52px; height:52px; border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.4); }
  .brand-name { font-weight:800; font-size:19px; letter-spacing:-.01em; }
  .brand-sub { font-size:11px; letter-spacing:.22em; color:#7c8ba0; font-weight:600; margin-top:3px; }
  .hero-kicker {
    display:inline-block; font-size:11px; font-weight:700; letter-spacing:.18em;
    color:#e8823f; border:1px solid rgba(232,130,63,.4); border-radius:999px;
    padding:7px 16px; margin-bottom:22px; background:rgba(199,75,37,.08);
  }
  .hero-title { font-size:clamp(34px, 3.6vw, 48px); font-weight:800; line-height:1.08; letter-spacing:-.025em; margin:0; }
  .hero-title .u { position:relative; white-space:nowrap; }
  .hero-title .u::after {
    content:''; position:absolute; left:0; right:0; bottom:4px; height:10px;
    background:rgba(199,75,37,.45); z-index:-1; border-radius:3px;
  }
  .hero-title .u { z-index:0; }
  .hero-desc { color:#a39e8f; font-size:14.5px; line-height:1.7; margin:18px 0 0; max-width:44ch; }
  .feat { display:flex; gap:16px; padding:15px 0; border-top:1px solid rgba(255,255,255,.07); align-items:flex-start; }
  .feat:last-of-type { border-bottom:1px solid rgba(255,255,255,.07); }
  .feat-n {
    width:38px; height:38px; border-radius:12px; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    font-weight:800; font-size:13px; color:#f0a35e;
    background:rgba(199,75,37,.12); border:1px solid rgba(232,130,63,.3);
  }
  .feat b { display:block; font-size:14px; }
  .feat span { font-size:12.5px; color:#a39e8f; line-height:1.55; display:block; margin-top:3px; }
  .side-stats { display:flex; gap:26px; margin-top:26px; }
  .side-stats div b { font-size:24px; font-weight:800; letter-spacing:-.02em; display:block; }
  .side-stats div span { font-size:11px; color:#a39e8f; font-weight:600; letter-spacing:.06em; text-transform:uppercase; }
  .side-foot { font-size:11.5px; color:#a39e8f; display:flex; align-items:center; gap:8px; }

  /* ---- RIGHT: form ---- */
  .login-main { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 24px; }
  .login-card { width:100%; max-width:400px; animation:rise .5s ease both; }
  @keyframes rise { from{opacity:0; transform:translateY(14px)} to{opacity:1; transform:none} }
  .m-brand { display:none; align-items:center; gap:12px; margin-bottom:28px; }
  .m-brand img { width:44px; height:44px; border-radius:13px; }
  .login-card h1 { font-size:27px; font-weight:800; letter-spacing:-.02em; margin:0; }
  .login-card .sub { color:#5b6b7a; font-size:13.5px; margin:8px 0 26px; }
  .lbl { display:block; font-size:11px; font-weight:700; letter-spacing:.1em; color:#5b6b7a; margin-bottom:8px; }
  .fld { position:relative; margin-bottom:18px; }
  .fld input {
    width:100%; padding:14px 16px; font-size:14.5px; color:#294461;
    background:#fff; border:1.5px solid #e2e8f0; border-radius:14px;
    transition:border-color .18s, box-shadow .18s;
  }
  .fld input::placeholder { color:#9fb0c3; }
  .fld input:focus { outline:none; border-color:#c74b25; box-shadow:0 0 0 4px rgba(199,75,37,.12); }
  .pw-toggle {
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    background:none; border:none; cursor:pointer; font-size:12px; font-weight:700;
    color:#7c8ba0; padding:8px 10px; border-radius:8px;
  }
  .pw-toggle:hover { color:#c74b25; background:#fdf6ec; }
  .caps { display:none; font-size:12px; color:#b45309; margin-top:7px; font-weight:600; }
  .login-btn {
    width:100%; padding:15px; border:none; border-radius:14px; cursor:pointer;
    background:#c74b25; color:#fff; font-size:15px; font-weight:700;
    transition:transform .15s, background .18s, box-shadow .18s;
    box-shadow:0 12px 26px -12px rgba(199,75,37,.6);
  }
  .login-btn:hover { background:#a93e1e; transform:translateY(-1px); box-shadow:0 16px 30px -12px rgba(199,75,37,.7); }
  .login-btn:active { transform:translateY(0); }
  .login-btn:disabled { opacity:.7; cursor:wait; transform:none; }
  .alert {
    border-radius:14px; padding:13px 16px; font-size:13px; font-weight:600;
    margin-bottom:18px; line-height:1.55;
  }
  .alert.err { background:#fef2f2; border:1px solid #f3b4b4; color:#991b1b; }
  .alert.warn { background:#fffbeb; border:1px solid #f0d48a; color:#92400e; }
  .alert.ban { background:#fef2f2; border:1.5px solid #dc2626; color:#7f1d1d; }
  .alert.ban b.ttl { display:block; font-size:14px; margin-bottom:4px; }
  .alert a { color:inherit; font-weight:800; }
  .device-note {
    margin-top:20px; font-size:12px; color:#7c8ba0; line-height:1.65;
    background:#f4f7fb; border-radius:12px; padding:12px 15px;
  }
  .login-foot { margin-top:26px; padding-top:20px; border-top:1px solid #e2e8f0; font-size:12.5px; color:#7c8ba0; text-align:center; line-height:1.7; }
  .login-foot b { color:#5b6b7a; }

  @media (max-width: 960px) {
    .login-side { display:none; }
    .m-brand { display:flex; }
    body { background:#fff; }
  }
</style>
</head>
<body>
<div class="login-wrap">

  <!-- LEFT -->
  <aside class="login-side">
    <div class="brand-row">
      <img src="assets/logo.png" alt="DigitalMax">
      <div>
        <div class="brand-name"><?= e($site) ?></div>
        <div class="brand-sub">LEARNING PLATFORM</div>
      </div>
    </div>

    <div>
      <span class="hero-kicker">STUDENT PORTAL</span>
      <h2 class="hero-title">Skill seekho.<br><span class="u">Earning</span> shuru karo.</h2>
      <p class="hero-desc">TikTok monetization aur Shopify dropshipping ke practical video courses — assignments, grading aur verified certificates ke sath, sab ek hi dashboard me.</p>

      <div style="margin-top:26px">
        <div class="feat">
          <div class="feat-n">01</div>
          <div><b>Step-by-step video lectures</b><span>Har course mukammal curriculum ke sath — progress auto-save hoti hai, jahan chhorein wahan se continue karein.</span></div>
        </div>
        <div class="feat">
          <div class="feat-n">02</div>
          <div><b>Assignments aur real feedback</b><span>Practical tasks submit karein, mentor se marks aur feedback hasil karein.</span></div>
        </div>
        <div class="feat">
          <div class="feat-n">03</div>
          <div><b>Batch community access</b><span>Apne batch ke WhatsApp groups — announcements aur discussion, rules ke sath.</span></div>
        </div>
        <div class="feat">
          <div class="feat-n">04</div>
          <div><b>Verified certificate</b><span>Course complete karne par naam wala certificate — portfolio me lagayein.</span></div>
        </div>
      </div>

      <div class="side-stats">
        <div><b><?= $st_students ?></b><span>Students</span></div>
        <div><b><?= $st_courses ?></b><span>Courses</span></div>
        <div><b><?= $st_lectures ?></b><span>Lectures</span></div>
      </div>
    </div>

    <div class="side-foot">
      <span>Single-device secure login — aapka data mehfooz hai.</span>
    </div>
  </aside>

  <!-- RIGHT -->
  <main class="login-main">
    <div class="login-card">
      <div class="m-brand">
        <img src="assets/logo.png" alt="DigitalMax">
        <div>
          <div style="font-weight:800; font-size:17px"><?= e($site) ?></div>
          <div style="font-size:10px; letter-spacing:.2em; color:#7c8ba0; font-weight:600">LEARNING PLATFORM</div>
        </div>
      </div>

      <h1>Welcome back</h1>
      <p class="sub">Apne account me login karke learning continue karein.</p>

      <?php if ($error): ?>
        <div class="alert err"><?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($banned_uid): ?>
        <div class="alert ban">
          <b class="ttl">Account suspended / اکاؤنٹ معطل</b>
          Your account has been permanently banned for violating community guidelines.
          <a href="banned.php?uid=<?= $banned_uid ?>">View details →</a>
        </div>
      <?php endif; ?>
      <?php if ($kick): ?>
        <div class="alert warn"><?= e($kick) ?></div>
      <?php endif; ?>

      <form method="post" onsubmit="var b=document.getElementById('loginBtn'); b.disabled=true; b.textContent='Logging in…';">
        <?= csrf_field() ?>
        <label class="lbl" for="em">EMAIL ADDRESS</label>
        <div class="fld">
          <input id="em" type="email" name="email" required placeholder="aap@example.com" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">
        </div>
        <label class="lbl" for="pw">PASSWORD</label>
        <div class="fld">
          <input id="pw" type="password" name="password" required placeholder="Apna password likhein" autocomplete="current-password" style="padding-right:70px">
          <button type="button" class="pw-toggle" onclick="var i=document.getElementById('pw'); var show=i.type==='password'; i.type=show?'text':'password'; this.textContent=show?'Hide':'Show';">Show</button>
          <div class="caps" id="capsWarn">Caps Lock on hai — check kar lein.</div>
        </div>
        <label style="display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:600; color:#294461; margin:-6px 0 18px; cursor:pointer">
          <input type="checkbox" name="remember_me" checked style="width:17px; height:17px; accent-color:#c74b25; cursor:pointer">
          Remember Me — <span style="font-weight:400; color:#5b6b7a">30 din tak login rahein</span>
        </label>
        <button id="loginBtn" class="login-btn">Login to Dashboard →</button>
      </form>

      <div class="device-note">
        Ek waqt me sirf <b>ek device</b> par login allowed hai — doosri device se login par ye session auto-logout ho jayega.
      </div>

      <div class="login-foot">
        Account nahi hai? <b>Admin se rabta karein</b> — woh aapka account bana dega.<br>
        Password bhool gaye? Admin reset kar dega.<br>
        <span style="display:inline-block; margin-top:8px; font-size:12px"><?= legal_footer_links(' | ') ?></span>
      </div>
    </div>
  </main>

</div>

<script>
document.getElementById('pw').addEventListener('keyup', function(e){
  var on = e.getModifierState && e.getModifierState('CapsLock');
  document.getElementById('capsWarn').style.display = on ? 'block' : 'none';
});
</script>
<?= whatsapp_float() ?>
</body>
</html>
