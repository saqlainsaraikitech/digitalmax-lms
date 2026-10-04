<?php
// Student profile
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?')
        ->execute([trim($_POST['name']), trim($_POST['phone'] ?? ''), $u['id']]);
    if (!empty($_POST['new_pass'])) {
        if (!password_verify($_POST['old_pass'] ?? '', $u['password'])) {
            flash('Purana password ghalat hai.', 'error');
            redirect('profile.php');
        }
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($_POST['new_pass'], PASSWORD_DEFAULT), $u['id']]);
        flash('Profile + password update ho gaya.');
    } else {
        flash('Profile update ho gaya.');
    }
    redirect('profile.php');
}

$page_title = 'Profile';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" class="bg-white rounded-lg shadow p-6 max-w-xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Name</label>
    <input name="name" value="<?= e($u['name']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Email (change nahi ho sakta)</label>
    <input value="<?= e($u['email']) ?>" disabled class="w-full border rounded px-3 py-2 mt-1 bg-gray-100"></div>
  <div><label class="text-sm font-semibold">Phone</label>
    <input name="phone" value="<?= e($u['phone']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <hr>
  <p class="text-sm font-semibold">Password change (optional)</p>
  <div><label class="text-sm">Purana password</label>
    <input name="old_pass" type="password" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm">Naya password</label>
    <input name="new_pass" type="password" class="w-full border rounded px-3 py-2 mt-1"></div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save</button>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
