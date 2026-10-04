<?php
// Student: payment screenshot bhejo + history
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_POST['agree_refund'])) {
            throw new Exception('Refund Policy se agree karna zaroori hai — checkbox tick karein.');
        }
        $shot = upload_file('screenshot', 'payments', ['jpg', 'jpeg', 'png', 'webp'], 300); // auto-compress: max 300KB
        if (!$shot) throw new Exception('Payment ka screenshot lagana zaroori hai.');
        $method = $_POST['method'] ?? 'easypaisa';
        $trx = trim($_POST['trx_id'] ?? '');
        // Easypaisa/JazzCash: TID lazmi + sirf 11-12 digits numeric
        if (in_array($method, ['easypaisa', 'jazzcash'], true)) {
            if (!preg_match('/^\d{11,12}$/', $trx)) {
                throw new Exception('Transaction ID ghalat hai — Easypaisa/JazzCash ki TID sirf 11-12 digits ki numeric hoti hai.');
            }
            $st = $pdo->prepare('SELECT id FROM payments WHERE trx_id = ? LIMIT 1');
            $st->execute([$trx]);
            if ($st->fetch()) throw new Exception('Yeh Transaction ID pehle se use ho chuki hai.');
        }
        // referral attribution (cookie: join?ref= se aaya ho)
        try { $pdo->query('SELECT referred_by FROM payments LIMIT 1'); }
        catch (Exception $e2) {
            try { $pdo->exec('ALTER TABLE payments ADD COLUMN referred_by INT NULL DEFAULT NULL, ADD COLUMN commission_amount DECIMAL(10,2) NULL DEFAULT NULL, ADD COLUMN commission_paid TINYINT(1) NOT NULL DEFAULT 0'); }
            catch (Exception $e3) {}
        }
        $referred_by = null;
        $ref_code = $_COOKIE['dm_ref'] ?? '';
        if ($ref_code !== '') {
            $st = $pdo->prepare('SELECT id FROM users WHERE referral_code = ? AND status = "active" AND id != ? LIMIT 1');
            $st->execute([$ref_code, $u['id']]);
            $referred_by = $st->fetchColumn() ?: null;
        }
        try {
            $pdo->prepare('INSERT INTO payments (student_id, course_id, amount, method, sender_number, trx_id, screenshot, status, referred_by) VALUES (?,?,?,?,?,?,?,"pending",?)')
                ->execute([$u['id'], $_POST['course_id'] !== '' ? (int)$_POST['course_id'] : null,
                    (float)$_POST['amount'], $method, trim($_POST['sender_number'] ?? ''), $trx !== '' ? $trx : null, $shot, $referred_by]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') throw new Exception('Yeh Transaction ID pehle se use ho chuki hai.');
            throw $ex;
        }
        flash('Payment bhej di! Admin verify karke aapko enroll kar dega. ');
    } catch (Exception $ex) { flash($ex->getMessage(), 'error'); }
    redirect('payments.php');
}

$courses = $pdo->query('SELECT id, title, price FROM courses WHERE status = "published" ORDER BY title')->fetchAll();
$history = $pdo->prepare('SELECT p.*, c.title AS course_title FROM payments p LEFT JOIN courses c ON c.id = p.course_id WHERE p.student_id = ? ORDER BY p.created_at DESC');
$history->execute([$u['id']]); $history = $history->fetchAll();
$pre_course = (int)($_GET['course_id'] ?? 0);

$page_title = 'Payments';
require __DIR__ . '/../inc/header.php';

// Dynamic payment methods (admin se manage)
$pay_methods = active_payment_methods();
$method_map = ['easypaisa' => 'EasyPaisa', 'jazzcash' => 'JazzCash', 'bank' => 'Bank Transfer / Raast'];
function pay_method_info($method, $pay_methods, $method_map) {
    $want = strtolower($method_map[strtolower($method)] ?? '');
    foreach ($pay_methods as $m) {
        if (strtolower($m['method_name']) === $want) return $m;
    }
    return $pay_methods[0] ?? null;
}
$pending = array_values(array_filter($history, fn($p) => ($p['status'] ?? '') === 'pending'));
$sup_wa = setting('whatsapp_number', '');
?>

<?php if (!$has_enrollment): ?>
<div class="pay-pending-banner" style="background:linear-gradient(90deg,#fdf6ec,#fff); border:1.5px solid #e8823f">
  <b><?= icon('lock', 18) ?> Access payment ke baad milega</b>
  <p>Aapka LMS access <b>payment verification ke baad</b> activate hoga. Neeche official accounts par payment bhejein aur screenshot + TID submit karein — verify hote hi courses unlock ho jayenge.</p>
</div>
<?php endif; ?>

<?php if ($pending):
  $pp = $pending[0];
  $pmi = pay_method_info($pp['method'] ?? '', $pay_methods, $method_map); ?>
<div class="pay-pending-banner">
  <b><?= icon('clock', 18) ?> Payment Pending Verification</b>
  <p>Aapne <b><?= money($pp['amount']) ?></b><?php if ($pmi): ?> is account par bheje hain: <b><?= e($pmi['method_name']) ?><?= $pmi['account_number'] ? ' (' . e($pmi['account_number']) . ')' : '' ?></b><?php endif; ?>. Hamari team <b>15–60 minute</b> mein verify kar rahi hai — verify hote hi course unlock ho jayega.</p>
  <?php if ($sup_wa): ?>
  <a href="https://wa.me/<?= e(preg_replace('/\D+/', '', $sup_wa)) ?>?text=Assalam-o-Alaikum,%20meri%20payment%20verify%20nahi%20hui.%20TID:%20<?= e(urlencode($pp['trx_id'] ?? '')) ?>" target="_blank" rel="noopener" class="dm-btn" style="margin-top:12px; background:#25D366; box-shadow:0 10px 22px -10px rgba(37,211,102,.7)"><?= icon('phone', 16) ?> WhatsApp Support</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($pay_methods): ?>
<h2 class="font-extrabold mb-1">Payment Process — 4 Steps</h2>
<p class="text-sm text-gray-500 mb-3">Enroll karne ka tareeqa:</p>
<div class="grid md:grid-cols-3 gap-3 mb-6">
  <?php
  $steps = [
    ['wallet', 'Step 1', 'Neeche official account par fees bhejein — screenshot + TID rakhein.'],
    ['clipboard-list', 'Step 2', 'Neeche form me screenshot + TID submit karein.'],
    ['check-circle', 'Step 3', '15–60 min me verify hote hi courses unlock ho jayenge.'],
  ];
  foreach ($steps as [$ic, $t, $d]): ?>
  <div class="bg-white rounded-xl border p-4" style="border-color:#e2e8f0">
    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full text-white font-extrabold mb-2" style="background:#c74b25"><?= icon($ic, 16) ?></span>
    <div class="font-bold text-sm"><?= $t ?></div>
    <div class="text-xs text-gray-500 mt-1"><?= $d ?></div>
  </div>
  <?php endforeach; ?>
</div>
<h2 class="font-extrabold mb-1">Official Payment Accounts</h2>
<p class="text-sm text-gray-500 mb-3">Neeche diye gaye <b>official</b> accounts par hi fees bhejein — kisi aur number par bheji gayi payment ki zimmedari nahi hogi.</p>
<div class="pay-cards">
  <?php foreach ($pay_methods as $m): ?><?= payment_method_card($m) ?><?php endforeach; ?>
</div>
<div class="pay-warn">
  <?= icon('alert', 20) ?>
  <span>Paisa bhejne ke <b>foran baad</b> 11–12 digit ki <b>Transaction ID (TID)</b> aur screenshot neeche diye gaye form mein <b>lazmi submit</b> karein — TID ke baghair payment verify nahi hogi.</span>
</div>
<div id="payToast"></div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow p-6 mb-6 max-w-2xl">
  <h2 class="font-bold mb-1">Payment bhejein</h2>
  <p class="text-sm text-gray-500 mb-4">Easypaisa / JazzCash par payment karke neeche screenshot upload karein. Admin verify karega.</p>
  <form method="post" enctype="multipart/form-data" class="space-y-4">
  <?= csrf_field() ?>
    <div><label class="text-sm font-semibold">Course *</label>
      <select name="course_id" required class="w-full border rounded px-3 py-2 mt-1">
        <option value="">— Select course —</option>
        <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= $pre_course === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?> (<?= money($c['price']) ?>)</option><?php endforeach; ?>
      </select></div>
    <div class="grid grid-cols-2 gap-4">
      <div><label class="text-sm font-semibold">Amount (Rs) *</label>
        <input name="amount" type="number" min="1" required class="w-full border rounded px-3 py-2 mt-1"></div>
      <div><label class="text-sm font-semibold">Method *</label>
        <select name="method" class="w-full border rounded px-3 py-2 mt-1">
          <option value="easypaisa">Easypaisa</option>
          <option value="jazzcash">JazzCash</option>
          <option value="bank">Bank transfer</option>
          <option value="other">Other</option>
        </select></div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div><label class="text-sm font-semibold">Sender number</label>
        <input name="sender_number" placeholder="03xx-xxxxxxx" class="w-full border rounded px-3 py-2 mt-1"></div>
      <div><label class="text-sm font-semibold">Transaction ID <span id="trxReq" class="text-red-500">*</span></label>
        <input id="trxId" name="trx_id" placeholder="11-12 digits (misal: 12345678901)" class="w-full border rounded px-3 py-2 mt-1">
        <p class="text-xs text-gray-400 mt-1">Easypaisa/JazzCash SMS ya app se TID copy karke yahan paste karein.</p></div>
    </div>
    <div><label class="text-sm font-semibold">Screenshot *</label>
      <input type="file" name="screenshot" accept="image/*" required class="mt-1 text-sm"></div>
    <label class="agree-box">
      <input type="checkbox" name="agree_refund" id="agreeRefund" value="1">
      <span>I agree to the <a href="../refund-policy.php" target="_blank" rel="noopener">Terms &amp; Refund Policy</a> (Fees is strictly non-refundable once access is granted)<br><span class="urdu" dir="rtl">میں ریفنڈ پالیسی سے متفق ہوں</span></span>
    </label>
    <p class="text-xs text-gray-500 leading-relaxed" style="margin-top:10px">By enrolling, you acknowledge that results vary and this training does not guarantee specific earnings. <a href="../disclaimer.php" target="_blank" rel="noopener" style="color:#c74b25; font-weight:600">Read Earnings Disclaimer →</a></p>
    <button id="paySubmitBtn" class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Payment submit</button>
  </form>
</div>
<div class="dm-legalbar" style="max-width:640px; margin-left:0; text-align:left"><?= legal_footer_links(' | ', '../') ?></div>
<script>
(function(){
  var m = document.querySelector('select[name=method]');
  var t = document.getElementById('trxId');
  var req = document.getElementById('trxReq');
  var form = t.closest('form');
  var agree = document.getElementById('agreeRefund');
  var submitBtn = document.getElementById('paySubmitBtn');
  function syncAgree(){
    submitBtn.disabled = !agree.checked;
    submitBtn.style.opacity = agree.checked ? '1' : '.45';
    submitBtn.style.cursor = agree.checked ? 'pointer' : 'not-allowed';
  }
  agree.addEventListener('change', syncAgree); syncAgree();
  function syncTrx(){
    var mobile = (m.value === 'easypaisa' || m.value === 'jazzcash');
    t.required = mobile;
    req.style.display = mobile ? '' : 'none';
    t.placeholder = mobile ? '11-12 digits (misal: 12345678901)' : 'Optional';
  }
  m.addEventListener('change', syncTrx); syncTrx();
  form.addEventListener('submit', function(ev){
    var mobile = (m.value === 'easypaisa' || m.value === 'jazzcash');
    if (!agree.checked) {
      ev.preventDefault();
      alert('Pehle Terms & Refund Policy se agree karein (checkbox tick karein).');
      agree.focus();
      return;
    }
    if (mobile && !/^[0-9]{11,12}$/.test(t.value.trim())) {
      ev.preventDefault();
      alert('Transaction ID sirf 11-12 digits ki numeric honi chahiye.');
      t.focus();
    }
  });
})();
// Copy number buttons (payment cards)
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

<h2 class="font-bold mb-3">Payment history</h2>
<?php foreach ($history as $p): ?>
  <div class="bg-white rounded-lg shadow p-4 mb-3 text-sm flex justify-between flex-wrap gap-2">
    <div><b><?= money($p['amount']) ?></b> · <?= e(ucfirst($p['method'])) ?> · <?= e($p['course_title'] ?: '—') ?>
      <div class="text-xs text-gray-400"><?= e($p['created_at']) ?><?php if ($p['note']): ?> · Note: <?= e($p['note']) ?><?php endif; ?></div></div>
    <?= status_badge($p['status']) ?>
  </div>
<?php endforeach; ?>
<?php if (!$history): ?><p class="text-sm text-gray-400">Koi payment nahi bheji.</p><?php endif; ?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
