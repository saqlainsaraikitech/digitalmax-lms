<?php
// Team list
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_team');
$pdo = db();

if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM users WHERE id = ? AND role = "team"')->execute([(int)$_POST['delete']]);
    flash('Team member delete ho gaya.');
    redirect('team.php');
}

$rows = $pdo->query('SELECT u.*, tp.* FROM users u LEFT JOIN team_permissions tp ON tp.user_id = u.id WHERE u.role = "team" ORDER BY u.created_at DESC')->fetchAll();
$perms = team_perms_list();

$page_title = 'Team';
require __DIR__ . '/../inc/header.php';
?>
<div class="mb-4"><a href="team_form.php" class="text-white px-4 py-2 rounded text-sm font-semibold" style="background:#c74b25">+ Team member add</a></div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Permissions</th><th class="p-3">Status</th><th class="p-3">Action</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr class="border-t">
    <td class="p-3 font-semibold"><?= e($r['name']) ?></td>
    <td class="p-3"><?= e($r['email']) ?></td>
    <td class="p-3 text-xs text-gray-500">
      <?php $has = []; foreach ($perms as $k => $label) if (!empty($r[$k])) $has[] = $label; echo e($has ? implode(', ', $has) : '—'); ?>
    </td>
    <td class="p-3"><?= status_badge($r['status']) ?></td>
    <td class="p-3 space-x-2 whitespace-nowrap">
      <a href="team_form.php?id=<?= $r['id'] ?>" class="underline text-blue-600">Edit</a>
      <form method="post" class="inline" onsubmit="return confirm('Team member delete?')">
        <?= csrf_field() ?>
        <input type="hidden" name="delete" value="<?= $r['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="p-6 text-center text-gray-400">Koi team member nahi.</td></tr><?php endif; ?>
</table>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
