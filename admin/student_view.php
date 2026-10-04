<?php
// Student detail: enrollments + progress + payments
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_students');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "student"');
$st->execute([$id]);
$student = $st->fetch();
if (!$student) { flash('Student nahi mila.', 'error'); redirect('students.php'); }

if (isset($_POST['enroll_course'])) {
    $cid = (int)$_POST['enroll_course'];
    $bid = (int)($_POST['enroll_batch'] ?? 0);
    $cb = course_batches($cid);
    if ($cb && $bid <= 0) { flash('Is course ke liye batch select karna lazmi hai.', 'error'); }
    else {
        $fstat = ($_POST['fee_status'] ?? 'pending') === 'paid' ? 'paid' : 'pending';
        $famt = trim($_POST['fee_amount'] ?? '');
        $famt = ($famt !== '' && is_numeric($famt)) ? (float)$famt : null;
        $pdo->prepare('INSERT INTO enrollments (student_id, course_id, batch_id, fee_status, fee_amount) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE fee_status=VALUES(fee_status), fee_amount=VALUES(fee_amount), batch_id=VALUES(batch_id)')->execute([$id, $cid, $bid > 0 ? $bid : null, $fstat, $famt]);
        flash('Course me enroll kar diya.');
    }
    redirect('student_view.php?id=' . $id);
}
// v54: fee status inline update
if (isset($_POST['fee_update_course'])) {
    $fcid = (int)$_POST['fee_update_course'];
    $fstat = ($_POST['fee_status'] ?? 'pending') === 'paid' ? 'paid' : 'pending';
    $famt = trim($_POST['fee_amount'] ?? '');
    $famt = ($famt !== '' && is_numeric($famt)) ? (float)$famt : null;
    $pdo->prepare('UPDATE enrollments SET fee_status=?, fee_amount=? WHERE student_id=? AND course_id=?')->execute([$fstat, $famt, $id, $fcid]);
    flash('Fee status update ho gaya.');
    redirect('student_view.php?id=' . $id);
}
if (isset($_POST['unenroll'])) {
    $pdo->prepare('DELETE FROM enrollments WHERE student_id = ? AND course_id = ?')->execute([$id, (int)$_POST['unenroll']]);
    flash('Enrollment khatam.');
    redirect('student_view.php?id=' . $id);
}
// 🚫 Permanent ban — session foran terminate
if (isset($_POST['ban_student'])) {
    ensure_ban_columns();
    $sel = $_POST['ban_reason_select'] ?? '';
    $custom = trim($_POST['ban_reason'] ?? '');
    $reason = $sel === 'other' ? ($custom !== '' ? $custom : 'Other violation') : ($sel !== '' ? $sel : ($custom !== '' ? $custom : 'Community guidelines violation'));
    $pdo->prepare('UPDATE users SET status = "banned", ban_reason = ?, banned_at = NOW(), banned_by = ?, current_session_id = NULL WHERE id = ? AND role = "student"')
        ->execute([$reason, current_user()['id'], $id]);
    flash('Student permanently banned kar diya gaya. ');
    redirect('student_view.php?id=' . $id);
}
if (isset($_POST['unban_student'])) {
    ensure_ban_columns();
    $pdo->prepare('UPDATE users SET status = "active", ban_reason = NULL, banned_at = NULL, banned_by = NULL WHERE id = ? AND role = "student"')
        ->execute([$id]);
    flash('Student unban ho gaya — dobara login kar sakta hai. ');
    redirect('student_view.php?id=' . $id);
}

$enrolled = $pdo->prepare('SELECT e.*, c.title, b.batch_name, b.start_date, b.end_date FROM enrollments e JOIN courses c ON c.id=e.course_id LEFT JOIN batches b ON b.id=e.batch_id WHERE e.student_id=? ORDER BY e.enrolled_at DESC');
$enrolled->execute([$id]); $enrolled = $enrolled->fetchAll();
$enrolled_ids = array_column($enrolled, 'course_id');

$all_courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$available = array_filter($all_courses, fn($c) => !in_array($c['id'], $enrolled_ids));

$pays = $pdo->prepare('SELECT p.*, c.title AS course_title FROM payments p LEFT JOIN courses c ON c.id=p.course_id WHERE p.student_id=? ORDER BY p.created_at DESC');
$pays->execute([$id]); $pays = $pays->fetchAll();

$subs = $pdo->prepare('SELECT s.marks, s.status, a.title, a.max_marks, c.title AS course_title FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id WHERE s.student_id=? ORDER BY s.submitted_at DESC');
$subs->execute([$id]); $subs = $subs->fetchAll();

$page_title = 'Student: ' . $student['name'];
require __DIR__ . '/../inc/header.php';
?>

<div class="bg-white rounded-lg shadow p-5 mb-6">
  <div class="flex justify-between flex-wrap gap-2">
    <div><b class="text-lg"><?= e($student['name']) ?></b>
      <div class="text-sm text-gray-500"><?= e($student['email']) ?> · <?= e($student['phone'] ?: '-') ?> · <?= status_badge($student['status']) ?></div></div>
    <div class="flex items-center gap-3">
      <a href="student_form.php?id=<?= $id ?>" class="text-sm underline text-blue-600">Edit / password reset</a>
      <?php if (($student['status'] ?? '') === 'banned'): ?>
      <form method="post" onsubmit="return confirm('Is student ko unban karein?')"><?= csrf_field() ?>
        <input type="hidden" name="unban_student" value="1">
        <button class="text-sm font-bold text-white px-4 py-2 rounded-lg" style="background:#16a34a"><?= icon('check-circle',16) ?> Unban Student</button>
      </form>
      <?php else: ?>
      <button type="button" class="text-sm font-extrabold text-white px-4 py-2 rounded-lg" style="background:#dc2626" data-nm="<?= e($student['name']) ?>" onclick="openBanModal(<?= (int)$id ?>, this.dataset.nm)"><?= icon('ban',16) ?> Ban Student (Rules Violation)</button>
      <?php endif; ?>
    </div>
  </div>
  <?php if (($student['status'] ?? '') === 'banned'): ?>
  <div class="mt-3 rounded-xl p-3 text-sm" style="background:#fef2f2; border:1px solid #fca5a5; color:#7f1d1d">
    <b><?= icon('ban',16) ?> Banned</b> — Reason: <b><?= e($student['ban_reason'] ?? '-') ?></b>
    <?php if (!empty($student['banned_at'])): ?> · <?= e(date('d M Y · h:i A', strtotime($student['banned_at']))) ?><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?= ban_student_modal() ?>

<div class="grid md:grid-cols-2 gap-6">
<div>
  <h2 class="font-bold mb-3">Enrolled courses</h2>
  <?php if ($available): ?>
  <?php
  $qe_map = [];
  foreach ($available as $c) {
      $qe_map[$c['id']] = array_map(fn($b) => ['id' => (int)$b['id'], 'n' => $b['batch_name'] . ' (' . batch_status_label(batch_status($b)) . ')'], course_batches($c['id']));
  }
  ?>
  <form method="post" class="bg-white rounded-lg shadow p-4 mb-4">
  <?= csrf_field() ?>
    <div class="flex gap-2 flex-wrap">
      <select name="enroll_course" id="qeCourse" onchange="qeBatches()" class="flex-1 border rounded px-2 py-1.5 text-sm min-w-40">
        <?php foreach ($available as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['title']) ?></option><?php endforeach; ?>
      </select>
      <select name="enroll_batch" id="qeBatch" class="border rounded px-2 py-1.5 text-sm min-w-40"></select>
      <select name="fee_status" class="border rounded px-2 py-1.5 text-sm" title="Fee status">
        <option value="pending">Fee: Pending</option>
        <option value="paid">Fee: Paid</option>
      </select>
      <input type="number" name="fee_amount" placeholder="Fee Rs" min="0" step="0.01" class="border rounded px-2 py-1.5 text-sm w-28">
      <button class="text-white px-4 py-1.5 rounded text-sm" style="background:#c74b25">Enroll</button>
    </div>
  </form>
  <script>
  var QE_MAP = <?= json_encode($qe_map) ?>;
  function qeBatches(){
    var cid = document.getElementById('qeCourse').value;
    var sel = document.getElementById('qeBatch');
    var list = QE_MAP[cid] || [];
    sel.innerHTML = '';
    if (!list.length) {
      var o = document.createElement('option'); o.value = '0'; o.textContent = 'koi batch nahi (sab access)'; sel.appendChild(o);
    } else {
      var d = document.createElement('option'); d.value = '0'; d.textContent = '— Select Batch * —'; sel.appendChild(d);
      list.forEach(function(b){ var o = document.createElement('option'); o.value = b.id; o.textContent = b.n; sel.appendChild(o); });
    }
  }
  qeBatches();
  </script>
  <?php endif; ?>
  <?php foreach ($enrolled as $en):
    $pct = course_progress($id, $en['course_id']); ?>
    <div class="bg-white rounded-lg shadow p-4 mb-3">
      <div class="flex justify-between text-sm gap-2"><b><?= e($en['title']) ?></b>
        <form method="post" class="inline" onsubmit="return confirm('Enrollment khatam karein?')">
          <?= csrf_field() ?>
          <input type="hidden" name="unenroll" value="<?= $en['course_id'] ?>">
          <button class="underline text-red-600">Remove</button>
        </form></div>
      <?php if (!empty($en['batch_name'])): ?><div class="mt-1.5"><?= batch_badge_short(['batch_name' => $en['batch_name'], 'start_date' => $en['start_date'], 'end_date' => $en['end_date']]) ?></div><?php endif; ?>
      <div class="mt-2 bg-gray-200 rounded-full h-2.5"><div class="h-2.5 rounded-full" style="width:<?= $pct ?>%;background:#c74b25"></div></div>
      <div class="text-xs text-gray-500 mt-1"><?= $pct ?>% complete · enrolled <?= e($en['enrolled_at']) ?></div>
      <form method="post" class="mt-2.5 flex items-center gap-2 flex-wrap bg-gray-50 rounded-lg px-3 py-2">
        <?= csrf_field() ?>
        <input type="hidden" name="fee_update_course" value="<?= $en['course_id'] ?>">
        <?= fee_badge($en['fee_status'] ?? 'pending') ?>
        <?php if (!empty($en['fee_amount'])): ?><span class="text-xs font-bold text-gray-700">Rs <?= number_format((float)$en['fee_amount']) ?></span><?php endif; ?>
        <select name="fee_status" class="border rounded px-1.5 py-1 text-xs">
          <option value="pending" <?= ($en['fee_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="paid" <?= ($en['fee_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
        </select>
        <input type="number" name="fee_amount" value="<?= e($en['fee_amount'] ?? '') ?>" placeholder="Rs" min="0" step="0.01" class="border rounded px-1.5 py-1 text-xs w-24">
        <button class="text-xs font-bold text-white px-3 py-1 rounded" style="background:#294461">Update fee</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (!$enrolled): ?><p class="text-sm text-gray-400">Kisi course me enroll nahi.</p><?php endif; ?>

  <h2 class="font-bold mb-3 mt-6">Assignment grades</h2>
  <?php foreach ($subs as $s): ?>
    <div class="bg-white rounded-lg shadow p-3 mb-2 text-sm flex justify-between">
      <span><?= e($s['title']) ?> <span class="text-gray-400">· <?= e($s['course_title']) ?></span></span>
      <span><?= $s['marks'] !== null ? '<b>' . (int)$s['marks'] . '/' . (int)$s['max_marks'] . '</b>' : status_badge($s['status']) ?></span>
    </div>
  <?php endforeach; ?>
  <?php if (!$subs): ?><p class="text-sm text-gray-400">Koi submission nahi.</p><?php endif; ?>
</div>

<div>
  <h2 class="font-bold mb-3">Payments</h2>
  <?php foreach ($pays as $p): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-3 text-sm">
      <div class="flex justify-between"><b><?= money($p['amount']) ?></b> <?= status_badge($p['status']) ?></div>
      <div class="text-gray-500 mt-1"><?= e(ucfirst($p['method'])) ?> · <?= e($p['course_title'] ?: 'General') ?> · <?= e($p['created_at']) ?></div>
      <?php if ($p['screenshot']): ?><a href="../<?= e($p['screenshot']) ?>" target="_blank" class="underline text-blue-600"><?= icon('paperclip',16) ?> Screenshot</a><?php endif; ?>
      <?php if ($p['status'] === 'pending' && can('p_payments')): ?>
        <div class="mt-2"><a href="payments.php" class="underline" style="color:#c74b25">Payments page par verify karein →</a></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (!$pays): ?><p class="text-sm text-gray-400">Koi payment record nahi.</p><?php endif; ?>
</div>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
