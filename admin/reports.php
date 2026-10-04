<?php
// Admin: Student Stats Report — kis ne kitna course dekha, kis ne assignment submit nahi ki
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_students');
$pdo = db();

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$filter_course = (int)($_GET['course_id'] ?? 0);
$only_attention = isset($_GET['attention']);

$where_course = $filter_course > 0 ? ' AND e.course_id = ' . $filter_course : '';

$rows = $pdo->query(
    'SELECT u.id, u.name, u.email, e.course_id, c.title AS course_title,
       e.fee_status, e.fee_amount,
       (SELECT COUNT(*) FROM lectures l WHERE l.course_id = e.course_id) AS total_lec,
       (SELECT COUNT(*) FROM lecture_progress lp JOIN lectures l ON l.id = lp.lecture_id
         WHERE lp.student_id = u.id AND l.course_id = e.course_id) AS watched_lec,
       (SELECT COUNT(*) FROM assignments a WHERE a.course_id = e.course_id) AS total_asg,
       (SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id = s.assignment_id
         WHERE s.student_id = u.id AND a.course_id = e.course_id) AS submitted_asg
     FROM users u
     JOIN enrollments e ON e.student_id = u.id
     JOIN courses c ON c.id = e.course_id
     WHERE u.role = "student" AND u.status = "active"' . $where_course . '
     ORDER BY c.title, u.name'
)->fetchAll();

// attention filter: 0% progress ya missing assignments
if ($only_attention) {
    $rows = array_filter($rows, function($r) {
        $pct = $r['total_lec'] > 0 ? ($r['watched_lec'] / $r['total_lec']) : 0;
        $missing = $r['total_asg'] - $r['submitted_asg'];
        return $pct < 0.1 || $missing > 0;
    });
}

// summary
$total_students = count(array_unique(array_column($rows, 'id')));
$avg_pct = 0; $fee_pending = 0; $missing_total = 0;
foreach ($rows as $r) {
    if ($r['total_lec'] > 0) $avg_pct += ($r['watched_lec'] / $r['total_lec']);
    if (($r['fee_status'] ?? 'pending') === 'pending') $fee_pending++;
    $missing_total += max(0, $r['total_asg'] - $r['submitted_asg']);
}
$avg_pct = $rows ? round($avg_pct / count($rows) * 100) : 0;

$page_title = 'Student Stats';
require __DIR__ . '/../inc/header.php';
?>
<div class="mb-5 flex gap-3 flex-wrap items-center">
  <form method="get" class="flex gap-2 flex-wrap items-center">
    <select name="course_id" class="border rounded-lg px-3 py-2 text-sm" onchange="this.form.submit()">
      <option value="0">— All courses —</option>
      <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $filter_course === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="text-sm flex items-center gap-1.5 bg-white border rounded-lg px-3 py-2 cursor-pointer">
      <input type="checkbox" name="attention" value="1" <?= $only_attention ? 'checked' : '' ?> onchange="this.form.submit()">
      Sirf attention wale
    </label>
  </form>
</div>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
  <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Students</div><div class="text-2xl font-extrabold" style="color:#294461"><?= $total_students ?></div></div>
  <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Avg progress</div><div class="text-2xl font-extrabold" style="color:#c74b25"><?= $avg_pct ?>%</div></div>
  <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Fee pending</div><div class="text-2xl font-extrabold" style="color:#b45309"><?= $fee_pending ?></div></div>
  <div class="bg-white rounded-xl shadow p-4"><div class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Missing assignments</div><div class="text-2xl font-extrabold" style="color:#dc2626"><?= $missing_total ?></div></div>
</div>

<div class="bg-white rounded-xl shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500">
    <th class="p-3">Student</th><th class="p-3">Course</th><th class="p-3" style="min-width:170px">Watch progress</th>
    <th class="p-3">Lectures</th><th class="p-3">Assignments</th><th class="p-3">Fee</th>
  </tr>
  <?php foreach ($rows as $r):
    $pct = $r['total_lec'] > 0 ? (int)round($r['watched_lec'] / $r['total_lec'] * 100) : 0;
    $missing = max(0, $r['total_asg'] - $r['submitted_asg']);
    $bar_color = $pct >= 80 ? '#16a34a' : ($pct >= 40 ? '#c74b25' : '#dc2626');
  ?>
  <tr class="border-t hover:bg-gray-50">
    <td class="p-3"><a href="student_view.php?id=<?= $r['id'] ?>" class="font-semibold underline" style="color:#c74b25"><?= e($r['name']) ?></a><br><span class="text-xs text-gray-400"><?= e($r['email']) ?></span></td>
    <td class="p-3"><?= e($r['course_title']) ?></td>
    <td class="p-3">
      <div class="flex items-center gap-2"><div class="flex-1 bg-gray-200 rounded-full h-2.5"><div class="h-2.5 rounded-full" style="width:<?= $pct ?>%;background:<?= $bar_color ?>"></div></div><b><?= $pct ?>%</b></div>
    </td>
    <td class="p-3"><?= (int)$r['watched_lec'] ?> / <?= (int)$r['total_lec'] ?></td>
    <td class="p-3">
      <?php if ($r['total_asg'] > 0): ?>
        <?= (int)$r['submitted_asg'] ?> / <?= (int)$r['total_asg'] ?>
        <?php if ($missing > 0): ?><br><span class="text-xs font-bold text-red-600"><?= $missing ?> missing</span><?php endif; ?>
      <?php else: ?><span class="text-gray-300">-</span><?php endif; ?>
    </td>
    <td class="p-3"><?= fee_badge($r['fee_status'] ?? 'pending') ?><?php if (!empty($r['fee_amount'])): ?><br><span class="text-xs text-gray-500">Rs <?= number_format((float)$r['fee_amount']) ?></span><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="p-6 text-center text-gray-400">Koi record nahi mila.</td></tr><?php endif; ?>
</table>
</div>
<p class="text-xs text-gray-400 mt-3">"Sirf attention wale" — wo students jin ki progress 10% se kam hai ya jin ki assignments missing hain.</p>
