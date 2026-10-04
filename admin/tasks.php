<?php
// Tasks: team ko kaam assign
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_tasks');
$pdo = db();
$me = current_user();

$mine = isset($_GET['mine']);
if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([(int)$_POST['delete']]);
    flash('Task delete ho gaya.');
    redirect('tasks.php' . ($mine ? '?mine=1' : ''));
}
if (isset($_POST['status']) && isset($_POST['id'])) {
    $allowed = ['pending', 'in_progress', 'done'];
    if (in_array($_POST['status'], $allowed, true)) {
        $pdo->prepare('UPDATE tasks SET status = ? WHERE id = ?')->execute([$_POST['status'], (int)$_POST['id']]);
        flash('Task status update.');
    }
    redirect('tasks.php' . ($mine ? '?mine=1' : ''));
}

$sql = 'SELECT t.*, u1.name AS to_name, u2.name AS by_name FROM tasks t
        LEFT JOIN users u1 ON u1.id = t.assigned_to LEFT JOIN users u2 ON u2.id = t.assigned_by';
$params = [];
if ($mine) { $sql .= ' WHERE t.assigned_to = ?'; $params[] = $me['id']; }
$sql .= ' ORDER BY t.created_at DESC';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$page_title = 'Tasks';
require __DIR__ . '/../inc/header.php';
?>
<div class="mb-4 flex gap-3 text-sm items-center">
  <a href="task_form.php" class="text-white px-4 py-2 rounded font-semibold" style="background:#c74b25">+ New task</a>
  <a href="tasks.php" class="underline <?= !$mine ? 'font-bold' : 'text-gray-500' ?>">Sab tasks</a>
  <a href="tasks.php?mine=1" class="underline <?= $mine ? 'font-bold' : 'text-gray-500' ?>">Mere tasks</a>
</div>

<?php foreach ($rows as $t): ?>
<div class="bg-white rounded-lg shadow p-4 mb-3">
  <div class="flex justify-between items-start flex-wrap gap-2">
    <div>
      <b><?= e($t['title']) ?></b>
      <div class="text-xs text-gray-500 mt-1">To: <?= e($t['to_name'] ?: '—') ?> · By: <?= e($t['by_name'] ?: '—') ?> · Due: <?= e($t['due_date'] ?: '—') ?></div>
      <?php if ($t['description']): ?><p class="text-sm text-gray-600 mt-2"><?= nl2br(e($t['description'])) ?></p><?php endif; ?>
    </div>
    <div class="text-right text-sm space-y-2">
      <?= status_badge($t['status']) ?>
      <div class="space-x-1">
        <?php foreach (['pending' => 'Pending', 'in_progress' => 'Start', 'done' => 'Done'] as $k => $label): ?>
          <?php if ($t['status'] !== $k): ?>
            <form method="post" action="tasks.php<?= $mine ? '?mine=1' : '' ?>" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <input type="hidden" name="status" value="<?= $k ?>">
              <button class="underline text-blue-600"><?= $label ?></button>
            </form>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <div class="space-x-2">
        <a href="task_form.php?id=<?= $t['id'] ?>" class="underline text-blue-600">Edit</a>
        <form method="post" action="tasks.php<?= $mine ? '?mine=1' : '' ?>" class="inline" onsubmit="return confirm('Task delete?')">
          <?= csrf_field() ?>
          <input type="hidden" name="delete" value="<?= $t['id'] ?>">
          <button class="underline text-red-600">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><div class="bg-white rounded-lg shadow p-6 text-center text-gray-400">Koi task nahi.</div><?php endif; ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>
