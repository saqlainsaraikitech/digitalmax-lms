<?php
// Admin: Affiliates & Payouts
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_payments');
$pdo = db();

// purani installs ke liye columns ensure
try { $pdo->query('SELECT referred_by FROM payments LIMIT 1'); }
catch (Exception $ex) {
    try { $pdo->exec('ALTER TABLE payments ADD COLUMN referred_by INT NULL DEFAULT NULL, ADD COLUMN commission_amount DECIMAL(10,2) NULL DEFAULT NULL, ADD COLUMN commission_paid TINYINT(1) NOT NULL DEFAULT 0'); }
    catch (Exception $ex2) {}
}
try { $pdo->query('SELECT 1 FROM referral_clicks LIMIT 1'); }
catch (Exception $ex) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS referral_clicks (
      id INT AUTO_INCREMENT PRIMARY KEY, referrer_id INT NOT NULL, ip VARCHAR(45) DEFAULT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

if (isset($_GET['clear_aff'])) {
    $aid = (int)$_GET['clear_aff'];
    $pdo->prepare("UPDATE payments SET commission_paid = 1 WHERE referred_by = ? AND status = 'approved' AND commission_paid = 0")->execute([$aid]);
    flash('Payout clear mark kar diya. ');
    redirect('affiliates.php');
}
if (isset($_GET['pay_one'])) {
    $pdo->prepare('UPDATE payments SET commission_paid = 1 WHERE id = ?')->execute([(int)$_GET['pay_one']]);
    flash('Commission paid mark ho gaya.');
    redirect('affiliates.php');
}

$affs = $pdo->query("SELECT u.id, u.name, u.phone,
    (SELECT COUNT(*) FROM referral_clicks rc WHERE rc.referrer_id = u.id) AS clicks,
    (SELECT COUNT(*) FROM payments p WHERE p.referred_by = u.id AND p.status = 'approved') AS referrals,
    (SELECT COALESCE(SUM(p.commission_amount),0) FROM payments p WHERE p.referred_by = u.id AND p.status = 'approved') AS earned,
    (SELECT COALESCE(SUM(p.commission_amount),0) FROM payments p WHERE p.referred_by = u.id AND p.status = 'approved' AND p.commission_paid = 0) AS pending
  FROM users u WHERE u.role = 'student'
  HAVING clicks > 0 OR referrals > 0 OR pending > 0
  ORDER BY pending DESC, earned DESC")->fetchAll();

$rows = $pdo->query("SELECT p.*, u.name AS student_name, c.title AS course_title, r.name AS referrer_name, r.phone AS referrer_phone
  FROM payments p JOIN users u ON u.id = p.student_id
  LEFT JOIN courses c ON c.id = p.course_id LEFT JOIN users r ON r.id = p.referred_by
  WHERE p.referred_by IS NOT NULL AND p.status = 'approved'
  ORDER BY p.commission_paid ASC, p.created_at DESC")->fetchAll();

$page_title = 'Affiliates & Payouts';
require __DIR__ . '/../inc/header.php';
?>

<p class="text-sm text-gray-500 mb-4">Commission rate: <b><?= e(affiliate_percent()) ?>%</b> (Settings me change hota hai) — commission approved payment par lock hota hai.</p>

<h2 class="font-bold mb-3">Affiliates summary</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Affiliate</th><th class="p-3">Easypaisa/JazzCash #</th><th class="p-3">Clicks</th><th class="p-3">Referrals</th><th class="p-3">Earned</th><th class="p-3">Pending payout</th><th class="p-3">Action</th></tr>
  <?php foreach ($affs as $a): ?>
  <tr class="border-t">
    <td class="p-3 font-semibold"><?= e($a['name']) ?></td>
    <td class="p-3"><?= e($a['phone'] ?: '—') ?></td>
    <td class="p-3"><?= (int)$a['clicks'] ?></td>
    <td class="p-3"><?= (int)$a['referrals'] ?></td>
    <td class="p-3"><?= money($a['earned']) ?></td>
    <td class="p-3 font-bold" style="color:#c74b25"><?= money($a['pending']) ?></td>
    <td class="p-3">
      <?php if ((float)$a['pending'] > 0): ?>
        <a href="affiliates.php?clear_aff=<?= $a['id'] ?>" data-confirm="<?= e($a['name']) ?> ka <?= money($a['pending']) ?> payout clear mark karein? Pehle Easypaisa/JazzCash par bhej dein." class="text-white px-3 py-1.5 rounded text-xs font-semibold" style="background:#16a34a">Clear payout</a>
      <?php else: ?><span class="text-xs text-gray-400">—</span><?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$affs): ?><tr><td colspan="7" class="p-6 text-center text-gray-400">Abhi koi affiliate activity nahi.</td></tr><?php endif; ?>
</table>
</div>

<h2 class="font-bold mb-3">Commission detail</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Date</th><th class="p-3">Referred student</th><th class="p-3">Course</th><th class="p-3">Payment</th><th class="p-3">Commission</th><th class="p-3">Affiliate</th><th class="p-3">Status</th><th class="p-3">Action</th></tr>
  <?php foreach ($rows as $p): ?>
  <tr class="border-t">
    <td class="p-3"><?= e(date('d M Y', strtotime($p['created_at']))) ?></td>
    <td class="p-3"><?= e($p['student_name']) ?></td>
    <td class="p-3"><?= e($p['course_title'] ?: '—') ?></td>
    <td class="p-3"><?= money($p['amount']) ?></td>
    <td class="p-3 font-semibold"><?= money($p['commission_amount'] ?: 0) ?></td>
    <td class="p-3"><?= e($p['referrer_name']) ?><br><span class="text-xs text-gray-400"><?= e($p['referrer_phone'] ?: '') ?></span></td>
    <td class="p-3"><?= $p['commission_paid'] ? status_badge('paid') : status_badge('pending') ?></td>
    <td class="p-3"><?php if (!$p['commission_paid']): ?><a href="affiliates.php?pay_one=<?= $p['id'] ?>" class="underline text-green-700">Mark paid</a><?php else: ?><span class="text-xs text-gray-400">—</span><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="p-6 text-center text-gray-400">Koi referred payment nahi.</td></tr><?php endif; ?>
</table>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
