<?php
// Assignment add/edit
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_assignments');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$a = ['course_id' => (int)($_GET['course_id'] ?? 0), 'title' => '', 'description' => '', 'due_date' => '', 'max_marks' => 100];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM assignments WHERE id = ?');
    $st->execute([$id]);
    if ($row = $st->fetch()) $a = $row;
}
$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [trim($_POST['title']), $_POST['description'] ?? '', $_POST['due_date'] ?: null, (int)($_POST['max_marks'] ?? 100), (int)$_POST['course_id']];
    if ($id) {
        $pdo->prepare('UPDATE assignments SET title=?, description=?, due_date=?, max_marks=?, course_id=? WHERE id=?')->execute([...$data, $id]);
        flash('Assignment update ho gaya.');
    } else {
        $pdo->prepare('INSERT INTO assignments (title, description, due_date, max_marks, course_id) VALUES (?,?,?,?,?)')->execute($data);
        flash('Assignment ban gaya.');
    }
    redirect('assignments.php?course_id=' . (int)$_POST['course_id']);
}

$page_title = $id ? 'Edit assignment' : 'New assignment';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" class="bg-white rounded-lg shadow p-6 max-w-2xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Course *</label>
    <select name="course_id" required class="w-full border rounded px-3 py-2 mt-1">
      <?php foreach ($courses as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$a['course_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
    </select></div>
  <div><label class="text-sm font-semibold">Title *</label>
    <input name="title" required value="<?= e($a['title']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Description / instructions</label>
    <textarea name="description" rows="4" class="w-full border rounded px-3 py-2 mt-1"><?= e($a['description']) ?></textarea></div>
  <div class="grid grid-cols-2 gap-4">
    <div><label class="text-sm font-semibold">Due date</label>
      <input name="due_date" type="date" value="<?= e($a['due_date']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
    <div><label class="text-sm font-semibold">Max marks</label>
      <input name="max_marks" type="number" min="1" value="<?= (int)$a['max_marks'] ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  </div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save</button>
  <a href="assignments.php" class="ml-3 text-sm underline text-gray-500">Cancel</a>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
