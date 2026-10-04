<?php
// Student: Earn with DigitalMax — referral dashboard
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$code = my_referral_code($u['id']);
$link = 'https://lms.digitalmx.pk/join?ref=' . $code;
$pct = affiliate_percent();

$stats = ['clicks' => 0, 'referrals' => 0, 'earned' => 0, 'pending' => 0, 'paid' => 0];
$recent = [];
try {
    $st = $pdo->prepare('SELECT COUNT(*) FROM referral_clicks WHERE referrer_id = ?');
    $st->execute([$u['id']]); $stats['clicks'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE referred_by = ? AND status = 'approved'");
    $st->execute([$u['id']]); $stats['referrals'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM payments WHERE referred_by = ? AND status = 'approved'");
    $st->execute([$u['id']]); $stats['earned'] = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM payments WHERE referred_by = ? AND status = 'approved' AND commission_paid = 0");
    $st->execute([$u['id']]); $stats['pending'] = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM payments WHERE referred_by = ? AND status = 'approved' AND commission_paid = 1");
    $st->execute([$u['id']]); $stats['paid'] = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT p.created_at, p.amount, p.commission_amount, p.commission_paid, p.status, us.name AS student_name, c.title AS course_title
      FROM payments p JOIN users us ON us.id = p.student_id LEFT JOIN courses c ON c.id = p.course_id
      WHERE p.referred_by = ? ORDER BY p.created_at DESC LIMIT 10");
    $st->execute([$u['id']]); $recent = $st->fetchAll();
} catch (Exception $ex) { /* tables nahi hain to zeros */ }

$page_title = 'Earn with DigitalMax';
require __DIR__ . '/../inc/header.php';
?>

<div class="rounded-2xl p-6 md:p-8 text-white mb-6" style="background:linear-gradient(135deg,#1d3448,#5c2a0c)">
  <h2 class="text-2xl font-extrabold"><?= icon('wallet',16) ?> Earn with DigitalMax</h2>
  <p class="text-white/70 text-sm mt-2 max-w-xl">Apna referral link doston se share karein — jo bhi us link se aakar course ki payment karega, uski <b>approved</b> payment par aapko <b><?= e($pct) ?>% commission</b> milega!</p>
  <div class="mt-4 flex gap-2 flex-wrap">
    <input id="refLink" readonly value="<?= e($link) ?>" class="flex-1 min-w-60 bg-white/15 border border-white/25 rounded-xl px-4 py-3 text-sm text-white font-mono">
    <button onclick="copyRef()" id="copyBtn" class="bg-white font-bold px-6 py-3 rounded-xl text-sm" style="color:#c74b25"><?= icon('clipboard-list',16) ?> Copy link</button>
  </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
  <?php foreach ([['Total Clicks', $stats['clicks'], false], ['Total Referrals', $stats['referrals'], false], ['Commission Earned', money($stats['earned']), true], ['Pending Payout', money($stats['pending']), true]] as [$l, $v, $hl]): ?>
    <div class="bg-white rounded-lg shadow p-5">
      <div class="text-2xl font-bold" <?= $hl ? 'style="color:#c74b25"' : '' ?>><?= $v ?></div>
      <div class="text-sm text-gray-500 mt-1"><?= e($l) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow p-5 mb-6">
  <h3 class="font-bold mb-3">Kaise kaam karta hai?</h3>
  <ol class="text-sm text-gray-600 space-y-2 list-decimal ml-5">
    <li>Apna referral link copy karke WhatsApp / Facebook / TikTok par share karein.</li>
    <li>Jo link se aayega, uska visit count hoga (cookie 30 din tak yaad rehta hai).</li>
    <li>Jab woh student payment bhejega aur admin <b>approve</b> karega → aapko <b><?= e($pct) ?>%</b> commission.</li>
    <li>Admin aapke Easypaisa/JazzCash number (aapke profile wale phone) par payout bhejega.</li>
  </ol>
  <p class="text-xs text-gray-400 mt-3"><?= icon('lightbulb',16) ?> Apna phone number <a href="profile.php" class="underline" style="color:#c74b25">Profile</a> me update rakhein taake payout aap tak pahunche.</p>
</div>

<h3 class="font-bold mb-3">Recent referrals</h3>
<div class="bg-white rounded-lg shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Date</th><th class="p-3">Student</th><th class="p-3">Course</th><th class="p-3">Payment</th><th class="p-3">Commission</th><th class="p-3">Status</th></tr>
  <?php foreach ($recent as $r): ?>
  <tr class="border-t">
    <td class="p-3"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
    <td class="p-3"><?= e($r['student_name']) ?></td>
    <td class="p-3"><?= e($r['course_title'] ?: '—') ?></td>
    <td class="p-3"><?= money($r['amount']) ?></td>
    <td class="p-3 font-semibold"><?= $r['status'] === 'approved' ? money($r['commission_amount'] ?: 0) : '<span class="text-gray-400">—</span>' ?></td>
    <td class="p-3"><?= $r['status'] === 'approved' ? ($r['commission_paid'] ? status_badge('paid') : status_badge('pending')) : status_badge($r['status']) ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$recent): ?><tr><td colspan="6" class="p-6 text-center text-gray-400">Abhi koi referral nahi — link share karna shuru karein! <?= icon('rocket',16) ?></td></tr><?php endif; ?>
</table>
</div>

<script>
function copyRef(){
  var i = document.getElementById('refLink');
  var b = document.getElementById('copyBtn');
  function done(){ b.innerHTML = '' + <?= icon('check-circle',14) ?> + ' Copied!'; setTimeout(function(){ b.innerHTML = '' + <?= icon('clipboard-list',14) ?> + ' Copy link'; }, 1500); }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(i.value).then(done).catch(function(){ i.select(); document.execCommand('copy'); done(); });
  } else { i.select(); document.execCommand('copy'); done(); }
}
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
