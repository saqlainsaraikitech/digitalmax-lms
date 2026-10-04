<?php
// Task add/edit
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_tasks');
$pdo = db();
$me = current_user();

$id = (int)($_GET['id'] ?? 0);
$t = ['title' => '', 'description' => '', 'assigned_to' => '', 'due_date' => '', 'status' => 'pending'];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $st->execute([$id]);
    if ($row = $st->fetch()) $t = $row;
}
$members = $pdo->query('SELECT id, name, role FROM users WHERE role IN ("admin","team") AND status="active" ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [trim($_POST['title']), $_POST['description'] ?? '', $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null, $_POST['due_date'] ?: null, $_POST['status']];
    if ($id) {
        $pdo->prepare('UPDATE tasks SET title=?, description=?, assigned_to=?, due_date=?, status=? WHERE id=?')->execute([...$data, $id]);
        flash('Task update ho gaya.');
    } else {
        $pdo->prepare('INSERT INTO tasks (title, description, assigned_to, due_date, status, assigned_by) VALUES (?,?,?,?,?,?)')->execute([...$data, $me['id']]);
        flash('Task assign ho gaya.');
    }
    redirect('tasks.php');
}

$page_title = $id ? 'Edit task' : 'New task';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" class="bg-white rounded-lg shadow p-6 max-w-2xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Task title *</label>
    <input name="title" required value="<?= e($t['title']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Detail</label>
    <textarea name="description" rows="4" class="w-full border rounded px-3 py-2 mt-1"><?= e($t['description']) ?></textarea></div>
  <div class="grid grid-cols-3 gap-4">
    <div><label class="text-sm font-semibold">Assign to</label>
      <select name="assigned_to" class="w-full border rounded px-3 py-2 mt-1">
        <option value="">— Select —</option>
        <?php foreach ($members as $m): ?>
          <option value="<?= $m['id'] ?>" <?= (int)$t['assigned_to'] === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?> (<?= e($m['role']) ?>)</option>
        <?php endforeach; ?>
      </select></div>
    <div><label class="text-sm font-semibold">Due date</label>
      <input name="due_date" type="date" value="<?= e($t['due_date']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
    <div><label class="text-sm font-semibold">Status</label>
      <select name="status" class="w-full border rounded px-3 py-2 mt-1">
        <?php foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'done' => 'Done'] as $k => $label): ?>
          <option value="<?= $k ?>" <?= $t['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select></div>
  </div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save task</button>
  <a href="tasks.php" class="ml-3 text-sm underline text-gray-500">Cancel</a>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
