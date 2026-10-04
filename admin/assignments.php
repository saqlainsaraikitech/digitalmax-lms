<?php
// Assignments list
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_assignments');
$pdo = db();

$course_id = (int)($_GET['course_id'] ?? 0);
if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM assignments WHERE id = ?')->execute([(int)$_POST['delete']]);
    flash('Assignment delete ho gaya.');
    redirect('assignments.php' . ($course_id ? '?course_id=' . $course_id : ''));
}

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$sql = 'SELECT a.*, c.title AS course_title, (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) AS subs FROM assignments a JOIN courses c ON c.id = a.course_id';
$params = [];
if ($course_id) { $sql .= ' WHERE a.course_id = ?'; $params[] = $course_id; }
$sql .= ' ORDER BY a.created_at DESC';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$page_title = 'Assignments';
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-4 flex gap-3 items-center flex-wrap">
  <a href="assignment_form.php<?= $course_id ? '?course_id=' . $course_id : '' ?>" class="text-white px-4 py-2 rounded text-sm font-semibold" style="background:#c74b25">+ New assignment</a>
  <form method="get" class="text-sm">
    <select name="course_id" onchange="this.form.submit()" class="border rounded px-2 py-1.5">
      <option value="0">Sab courses</option>
      <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= $course_id === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
    </select>
  </form>
</div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Title</th><th class="p-3">Course</th><th class="p-3">Due</th><th class="p-3">Marks</th><th class="p-3">Submissions</th><th class="p-3">Action</th></tr>
  <?php foreach ($rows as $a): ?>
  <tr class="border-t">
    <td class="p-3 font-semibold"><?= e($a['title']) ?></td>
    <td class="p-3"><?= e($a['course_title']) ?></td>
    <td class="p-3"><?= e($a['due_date'] ?: '-') ?></td>
    <td class="p-3"><?= (int)$a['max_marks'] ?></td>
    <td class="p-3"><?= (int)$a['subs'] ?></td>
    <td class="p-3 space-x-2 whitespace-nowrap">
      <a href="submissions.php?assignment_id=<?= $a['id'] ?>" class="underline" style="color:#c74b25">Check</a>
      <a href="assignment_form.php?id=<?= $a['id'] ?>" class="underline text-blue-600">Edit</a>
      <form method="post" action="assignments.php<?= $course_id ? '?course_id=' . $course_id : '' ?>" class="inline" onsubmit="return confirm('Assignment delete?')">
        <?= csrf_field() ?>
        <input type="hidden" name="delete" value="<?= $a['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="p-6 text-center text-gray-400">Koi assignment nahi.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
