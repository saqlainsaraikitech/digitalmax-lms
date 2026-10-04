<?php
// Payments verify (manual Easypaisa/JazzCash screenshot flow)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_payments');
$pdo = db();
$me = current_user();

$filter = $_GET['status'] ?? 'pending';
if (isset($_POST['approve'])) {
    $pid = (int)$_POST['approve'];
    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$pid]);
    $pay = $st->fetch();
    $commission = null;
    try {
        if ($pay && !empty($pay['referred_by'] ?? null)) {
            $commission = round((float)$pay['amount'] * affiliate_percent() / 100, 2);
        }
        $pdo->prepare('UPDATE payments SET status="approved", verified_by=?, commission_amount=? WHERE id=?')->execute([$me['id'], $commission, $pid]);
    } catch (PDOException $ex) {
        // purani install (columns nahi) → simple approve
        $pdo->prepare('UPDATE payments SET status="approved", verified_by=? WHERE id=?')->execute([$me['id'], $pid]);
    }
    flash('Payment approved. Ab student ko course me enroll kar dein (Students page).');
    redirect('payments.php?status=' . $filter);
}
if (isset($_POST['reject'])) {
    $pdo->prepare('UPDATE payments SET status="rejected", verified_by=?, note=? WHERE id=?')
        ->execute([$me['id'], trim($_POST['note'] ?? ''), (int)$_POST['reject']]);
    flash('Payment rejected.');
    redirect('payments.php?status=' . $filter);
}
if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM payments WHERE id=?')->execute([(int)$_POST['delete']]);
    flash('Payment record delete.');
    redirect('payments.php?status=' . $filter);
}

$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed, true)) $filter = 'pending';
$sql = 'SELECT p.*, u.name AS student_name, u.phone AS student_phone, c.title AS course_title FROM payments p JOIN users u ON u.id=p.student_id LEFT JOIN courses c ON c.id=p.course_id';
$params = [];
if ($filter !== 'all') { $sql .= ' WHERE p.status=?'; $params[] = $filter; }
$sql .= ' ORDER BY p.created_at DESC';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$page_title = 'Payments';
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-4 text-sm space-x-2">
  <?php foreach ($allowed as $s): ?>
    <a href="payments.php?status=<?= $s ?>" class="px-3 py-1.5 rounded <?= $filter === $s ? 'text-white' : 'bg-white' ?>" <?= $filter === $s ? 'style="background:#c74b25"' : '' ?>><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
</div>

<?php foreach ($rows as $p): ?>
<div class="bg-white rounded-lg shadow p-5 mb-4">
  <div class="flex justify-between items-start flex-wrap gap-3">
    <div>
      <b><?= e($p['student_name']) ?></b> — <b><?= money($p['amount']) ?></b>
      <div class="text-sm text-gray-500 mt-1">
        <?= e(ucfirst($p['method'])) ?>
        <?php if ($p['sender_number']): ?> · Sender: <?= e($p['sender_number']) ?><?php endif; ?>
        <?php if ($p['trx_id']): ?> · TRX: <?= e($p['trx_id']) ?><?php endif; ?>
        <br>Course: <?= e($p['course_title'] ?: '—') ?> · <?= e($p['created_at']) ?>
      </div>
      <?php if ($p['screenshot']): ?>
        <a href="../<?= e($p['screenshot']) ?>" target="_blank"><img src="../<?= e($p['screenshot']) ?>" class="h-24 mt-2 rounded border" alt="screenshot"></a>
      <?php else: ?><div class="text-xs text-gray-400 mt-2">Screenshot nahi bheja.</div><?php endif; ?>
      <?php if ($p['note']): ?><div class="text-xs text-gray-500 mt-1">Note: <?= e($p['note']) ?></div><?php endif; ?>
      <?php if ($p['trx_id']): ?>
        <div class="mt-2 flex items-center gap-2 flex-wrap">
          <code class="bg-gray-100 px-2 py-1 rounded text-xs font-mono"><?= e($p['trx_id']) ?></code>
          <button data-tid="<?= e($p['trx_id']) ?>" onclick="copyTid(this)" class="text-xs bg-gray-800 text-white px-3 py-1.5 rounded hover:bg-gray-700"><?= icon('clipboard-list',16) ?> Copy TID</button>
        </div>
        <div class="text-xs text-gray-400 mt-1">TID copy karke apni Easypaisa/JazzCash app me verify karein.</div>
      <?php endif; ?>
    </div>
    <div class="text-right">
      <?= status_badge($p['status']) ?>
      <?php if ($p['status'] === 'pending'): ?>
      <div class="mt-2 space-x-2 text-sm">
        <form method="post" action="payments.php?status=<?= $filter ?>" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="approve" value="<?= $p['id'] ?>">
          <button class="text-white px-3 py-1.5 rounded" style="background:#16a34a">Approve</button>
        </form>
        <form method="post" action="payments.php?status=<?= $filter ?>" class="inline" onsubmit="return confirm('Payment reject karein?')">
          <?= csrf_field() ?>
          <input type="hidden" name="reject" value="<?= $p['id'] ?>">
          <button class="text-white px-3 py-1.5 rounded bg-red-600">Reject</button>
        </form>
      </div>
      <?php endif; ?>
      <?php if ($p['status'] === 'approved'): ?>
        <?php
          $notify_msg = 'Assalam-o-Alaikum ' . $p['student_name'] . ', aapki payment verify ho gayi hai aur aapka course ' . ($p['course_title'] ?: 'LMS') . ' unlock kar diya gaya hai. Login karein: https://lms.digitalmx.pk/login.php';
          $notify_link = wa_link($p['student_phone'] ?? '', $notify_msg);
        ?>
        <?php if ($notify_link): ?>
          <a href="<?= e($notify_link) ?>" target="_blank" rel="noopener" class="mt-2 inline-block text-white px-3 py-1.5 rounded text-sm font-semibold" style="background:#25D366"><?= icon('message',16) ?> Notify on WhatsApp</a>
        <?php else: ?>
          <div class="mt-2 text-xs text-gray-400">Student ka WhatsApp number nahi hai.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="bg-white rounded-lg shadow p-6 text-center text-gray-400">Koi payment nahi.</div><?php endif; ?>

<script>
function copyTid(btn){
  var tid = btn.getAttribute('data-tid');
  function done(){ var o = btn.innerHTML; btn.innerHTML = '' + <?= icon('check-circle',14) ?> + ' Copied'; setTimeout(function(){ btn.innerHTML = o; }, 1500); }
  function fallback(){
    var ta = document.createElement('textarea');
    ta.value = tid; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); done(); } catch(e){ alert('Copy nahi ho saka: ' + tid); }
    document.body.removeChild(ta);
  }
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(tid).then(done).catch(fallback);
  } else { fallback(); }
}
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
