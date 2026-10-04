<?php
// Students list
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_students');
$pdo = db();

if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "student"')->execute([(int)$_POST['delete']]);
    flash('Student delete ho gaya.');
    redirect('students.php');
}
// 🚫 Permanent ban (rules violation) — session foran terminate
if (isset($_POST['ban_student'])) {
    ensure_ban_columns();
    $sid = (int)$_POST['ban_student'];
    $sel = $_POST['ban_reason_select'] ?? '';
    $custom = trim($_POST['ban_reason'] ?? '');
    $reason = $sel === 'other' ? ($custom !== '' ? $custom : 'Other violation') : ($sel !== '' ? $sel : ($custom !== '' ? $custom : 'Community guidelines violation'));
    $pdo->prepare('UPDATE users SET status = "banned", ban_reason = ?, banned_at = NOW(), banned_by = ?, current_session_id = NULL WHERE id = ? AND role = "student"')
        ->execute([$reason, current_user()['id'], $sid]);
    flash('Student permanently banned kar diya gaya. ');
    redirect('students.php');
}
if (isset($_POST['unban_student'])) {
    ensure_ban_columns();
    $pdo->prepare('UPDATE users SET status = "active", ban_reason = NULL, banned_at = NULL, banned_by = NULL WHERE id = ? AND role = "student"')
        ->execute([(int)$_POST['unban_student']]);
    flash('Student unban ho gaya — dobara login kar sakta hai. ');
    redirect('students.php');
}
if (isset($_POST['toggle'])) {
    $pdo->prepare('UPDATE users SET status = IF(status="active","inactive","active") WHERE id = ? AND role="student" AND status != "banned"')->execute([(int)$_POST['toggle']]);
    redirect('students.php');
}

$filter_course = (int)($_GET['course_id'] ?? 0);
$filter_course_name = '';
if ($filter_course > 0) {
    $st = $pdo->prepare('SELECT title FROM courses WHERE id = ?');
    $st->execute([$filter_course]);
    $filter_course_name = $st->fetchColumn() ?: '';
}
$rows = $pdo->prepare('SELECT u.*, (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id) AS courses,
  (SELECT COUNT(*) FROM payments p WHERE p.student_id = u.id AND p.status = "pending") AS pay_pending,
  (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id AND e.fee_status = "pending") AS fee_pending,
  (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id AND e.fee_status = "paid") AS fee_paid,
  (SELECT GROUP_CONCAT(CONCAT(b.batch_name, "|", IFNULL(b.start_date, ""), "|", IFNULL(b.end_date, "")) SEPARATOR ";;")
   FROM enrollments e LEFT JOIN batches b ON b.id = e.batch_id WHERE e.student_id = u.id AND b.id IS NOT NULL) AS batch_info
  FROM users u WHERE u.role = "student"' . ($filter_course > 0 ? ' AND EXISTS (SELECT 1 FROM enrollments e2 WHERE e2.student_id = u.id AND e2.course_id = ?)' : '') . ' ORDER BY u.created_at DESC');
$rows->execute($filter_course > 0 ? [$filter_course] : []);
$rows = $rows->fetchAll();

$page_title = 'Students';
require __DIR__ . '/../inc/header.php';
?>
<?php if ($filter_course > 0 && $filter_course_name): ?>
<div class="mb-4 flex items-center gap-3 flex-wrap rounded-xl px-4 py-3" style="background:#fdf6ec; border:1px solid #e2e8f0">
  <span class="text-sm font-bold" style="color:#294461"><?= icon('book',16) ?> Filter: <?= e($filter_course_name) ?> — <?= count($rows) ?> students</span>
  <a href="students.php" class="text-xs font-bold underline" style="color:#c74b25">Clear filter ✕</a>
</div>
<?php endif; ?>
<div class="mb-4 flex gap-3 flex-wrap">
  <a href="student_form.php" class="text-white px-4 py-2 rounded text-sm font-semibold" style="background:#c74b25">+ New student (account banao)</a>
  <a href="import_students.php" class="px-4 py-2 rounded text-sm font-semibold border-2" style="border-color:#c74b25;color:#c74b25"><?= icon('download',16) ?> Bulk Import (CSV)</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Name</th><th class="p-3">Email / Phone</th><th class="p-3">Courses</th><th class="p-3">Fee</th><th class="p-3">Pending payments</th><th class="p-3">Status</th><th class="p-3">Action</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr class="border-t">
    <td class="p-3 font-semibold"><?= e($r['name']) ?></td>
    <td class="p-3"><?= e($r['email']) ?><br><span class="text-gray-400"><?= e($r['phone'] ?: '-') ?></span></td>
    <td class="p-3"><?= (int)$r['courses'] ?>
      <?php if ($r['batch_info']): ?><div class="mt-1 space-y-1"><?php foreach (explode(';;', $r['batch_info']) as $bi):
        $parts = explode('|', $bi);
        echo batch_badge_short(['batch_name' => $parts[0] ?? '', 'start_date' => ($parts[1] ?? '') ?: null, 'end_date' => ($parts[2] ?? '') ?: null]) . ' ';
      endforeach; ?></div><?php endif; ?>
    </td>
    <td class="p-3"><div class="flex gap-1 flex-wrap"><?php
      if ((int)$r['fee_paid']) echo '<span class="fee-badge paid">' . (int)$r['fee_paid'] . ' paid</span>';
      if ((int)$r['fee_pending']) echo '<span class="fee-badge pending">' . (int)$r['fee_pending'] . ' pending</span>';
      if (!(int)$r['fee_paid'] && !(int)$r['fee_pending']) echo '<span class="text-gray-300">-</span>';
    ?></div></td>
    <td class="p-3"><?= (int)$r['pay_pending'] ? '<span class="text-yellow-700 font-bold">' . (int)$r['pay_pending'] . ' pending</span>' : '-' ?></td>
    <td class="p-3"><?= status_badge($r['status']) ?></td>
    <td class="p-3 space-x-2 whitespace-nowrap">
      <a href="student_view.php?id=<?= $r['id'] ?>" class="underline" style="color:#c74b25">View</a>
      <a href="student_form.php?id=<?= $r['id'] ?>" class="underline text-blue-600">Edit</a>
      <form method="post" class="inline" onsubmit="return confirm('Status change?')">
        <?= csrf_field() ?>
        <input type="hidden" name="toggle" value="<?= $r['id'] ?>">
        <button class="underline text-gray-500"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
      </form>
      <?php if (($r['status'] ?? '') === 'banned'): ?>
      <form method="post" class="inline" onsubmit="return confirm('Is student ko unban karein? Wo dobara login kar sakega.')">
        <?= csrf_field() ?>
        <input type="hidden" name="unban_student" value="<?= $r['id'] ?>">
        <button class="underline text-green-700 font-bold">Unban</button>
      </form>
      <?php else: ?>
      <button type="button" class="underline font-bold text-red-700" data-nm="<?= e($r['name']) ?>" onclick="openBanModal(<?= (int)$r['id'] ?>, this.dataset.nm)"><?= icon('ban',16) ?> Ban</button>
      <?php endif; ?>
      <form method="post" class="inline" onsubmit="return confirm('Student delete? Uska tamam record (progress, payments) bhi delete ho jayega.')">
        <?= csrf_field() ?>
        <input type="hidden" name="delete" value="<?= $r['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="p-6 text-center text-gray-400">Koi student nahi.</td></tr><?php endif; ?>
</table>
</div>
<?= ban_student_modal() ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>
