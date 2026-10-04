<?php
// Team add/edit + permissions
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_team');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$m = ['name' => '', 'email' => '', 'phone' => '', 'status' => 'active'];
$cur = [];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "team"');
    $st->execute([$id]);
    if ($row = $st->fetch()) $m = $row;
    $st = $pdo->prepare('SELECT * FROM team_permissions WHERE user_id = ?');
    $st->execute([$id]);
    $cur = $st->fetch() ?: [];
}
$perms = team_perms_list();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']); $email = trim($_POST['email']); $phone = trim($_POST['phone'] ?? '');
    $err = '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Naam aur valid email zaroori hai.';
    if (!$err) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $st->execute([$email, $id]);
        if ($st->fetch()) $err = 'Ye email pehle se registered hai.';
    }
    if (!$err && !$id && empty($_POST['password'])) $err = 'Password zaroori hai.';
    if ($err) flash($err, 'error');
    else {
        if ($id) {
            $q = 'UPDATE users SET name=?, email=?, phone=?, status=?';
            $p = [$name, $email, $phone, $_POST['status'] ?? 'active'];
            if (!empty($_POST['password'])) { $q .= ', password=?'; $p[] = password_hash($_POST['password'], PASSWORD_DEFAULT); }
            $p[] = $id;
            $pdo->prepare($q . ' WHERE id=?')->execute($p);
        } else {
            $pdo->prepare('INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?,"team",?)')
                ->execute([$name, $email, $phone, password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['status'] ?? 'active']);
            $id = $pdo->lastInsertId();
        }
        $cols = array_keys($perms);
        $permVals = [];
        foreach ($cols as $c) $permVals[] = isset($_POST['perms'][$c]) ? 1 : 0;
        $st = $pdo->prepare('SELECT 1 FROM team_permissions WHERE user_id = ?');
        $st->execute([$id]);
        if ($st->fetch()) {
            $sets = implode(',', array_map(fn($c) => "$c = ?", $cols));
            $pdo->prepare("UPDATE team_permissions SET $sets WHERE user_id = ?")->execute([...$permVals, $id]);
        } else {
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare('INSERT INTO team_permissions (user_id, ' . implode(',', $cols) . ") VALUES (?,$ph)")->execute([$id, ...$permVals]);
        }
        flash('Team member save ho gaya.');
        redirect('team.php');
    }
}

$page_title = $id ? 'Edit team member' : 'New team member';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" class="bg-white rounded-lg shadow p-6 max-w-2xl space-y-4">
  <?= csrf_field() ?>
  <div class="grid grid-cols-2 gap-4">
    <div><label class="text-sm font-semibold">Name *</label>
      <input name="name" required value="<?= e($m['name']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
    <div><label class="text-sm font-semibold">Phone</label>
      <input name="phone" value="<?= e($m['phone']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  </div>
  <div><label class="text-sm font-semibold">Email * (login ID)</label>
    <input name="email" type="email" required value="<?= e($m['email']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Password <?= $id ? '(khali = purana)' : '*' ?></label>
    <input name="password" type="text" <?= $id ? '' : 'required' ?> class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Status</label>
    <select name="status" class="w-full border rounded px-3 py-2 mt-1">
      <option value="active" <?= $m['status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $m['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select></div>
  <div>
    <label class="text-sm font-semibold">Permissions — ye banda kya kar sakta hai?</label>
    <div class="grid grid-cols-2 gap-2 mt-2">
      <?php foreach ($perms as $k => $label): ?>
        <label class="flex items-center gap-2 text-sm bg-gray-50 p-2 rounded">
          <input type="checkbox" name="perms[<?= $k ?>]" <?= !empty($cur[$k]) ? 'checked' : '' ?>> <?= e($label) ?>
        </label>
      <?php endforeach; ?>
    </div>
  </div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save</button>
  <a href="team.php" class="ml-3 text-sm underline text-gray-500">Cancel</a>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
