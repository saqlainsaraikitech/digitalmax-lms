<?php
// Community Rules Manager — student ko dikhne wale rules yahan se manage
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_courses');
$pdo = db();
ensure_community_rules();

// Add / Edit
if (isset($_POST['save_rule'])) {
    $en = trim($_POST['rule_en'] ?? '');
    $ur = trim($_POST['rule_ur'] ?? '');
    $ord = (int)($_POST['rule_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;
    if ($en === '') { flash('English text lazmi hai.', 'error'); }
    else {
        $id = (int)$_POST['rule_id'];
        if ($id > 0) {
            $pdo->prepare('UPDATE community_rules SET rule_en=?, rule_ur=?, rule_order=?, is_active=? WHERE id=?')
                ->execute([$en, $ur, $ord, $active, $id]);
            flash('Rule update ho gaya. ');
        } else {
            $pdo->prepare('INSERT INTO community_rules (rule_en, rule_ur, rule_order, is_active) VALUES (?,?,?,?)')
                ->execute([$en, $ur, $ord, $active]);
            flash('Naya rule add ho gaya. ');
        }
    }
    redirect('community_rules.php');
}
if (isset($_POST['toggle_rule'])) {
    $pdo->prepare('UPDATE community_rules SET is_active = IF(is_active=1,0,1) WHERE id=?')
        ->execute([(int)$_POST['toggle_rule']]);
    redirect('community_rules.php');
}
if (isset($_POST['delete_rule'])) {
    $pdo->prepare('DELETE FROM community_rules WHERE id=?')->execute([(int)$_POST['delete_rule']]);
    flash('Rule delete ho gaya.');
    redirect('community_rules.php');
}

$rules = $pdo->query('SELECT * FROM community_rules ORDER BY rule_order ASC, id ASC')->fetchAll();
$edit = null;
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM community_rules WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$page_title = 'Community Rules';
require __DIR__ . '/../inc/header.php';
?>
<div class="bg-white rounded-xl shadow p-5 mb-6 max-w-2xl">
  <h2 class="font-extrabold text-lg mb-1"><?= $edit ? icon('edit',16) . ' Rule edit karein' : icon('plus',16) . ' Naya rule add karein' ?></h2>
  <p class="text-xs text-gray-500 mb-4">Ye rules student ko WhatsApp join karne se pehle guidelines modal me dikhte hain (English + اردو).</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="rule_id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
    <label class="text-sm font-semibold">Rule (English) *</label>
    <textarea name="rule_en" rows="2" required class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="e.g. No personal messages (DMs) to students..."><?= e($edit['rule_en'] ?? '') ?></textarea>
    <label class="text-sm font-semibold mt-3 block">Rule (اردو) — RTL preview</label>
    <textarea name="rule_ur" rows="2" dir="rtl" class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="اردو میں اصول لکھیں..." style="font-family:'Noto Nastaliq Urdu',serif; line-height:2.2"><?= e($edit['rule_ur'] ?? '') ?></textarea>
    <div class="flex gap-3 mt-3 items-end flex-wrap">
      <div><label class="text-sm font-semibold">Sort order</label>
        <input name="rule_order" type="number" value="<?= $edit ? (int)$edit['rule_order'] : 0 ?>" class="w-24 border rounded px-3 py-2 mt-1 text-sm"></div>
      <label class="text-sm flex items-center gap-2 mt-5">
        <input type="checkbox" name="is_active" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>> Active
      </label>
      <button name="save_rule" value="1" class="text-white px-5 py-2 rounded font-semibold text-sm" style="background:#c74b25"><?= $edit ? 'Update' : 'Add Rule' ?></button>
      <?php if ($edit): ?><a href="community_rules.php" class="text-sm underline text-gray-500">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<h2 class="font-extrabold text-lg mb-3"><?= icon('clipboard-list', 20) ?> Current rules <span class="text-sm font-normal text-gray-500">(student ko isi order me nazar ayenge)</span></h2>
<div class="space-y-3 max-w-3xl">
<?php foreach ($rules as $r): ?>
  <div class="bg-white rounded-xl shadow p-4 <?= $r['is_active'] ? '' : 'opacity-60' ?>">
    <div class="flex justify-between items-start gap-3">
      <div class="flex-1">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-xs font-bold text-white px-2 py-0.5 rounded" style="background:#c74b25">#<?= (int)$r['rule_order'] ?></span>
          <?= $r['is_active'] ? '<span class="text-xs font-bold text-green-700">● Active</span>' : '<span class="text-xs font-bold text-gray-400">○ Inactive</span>' ?>
        </div>
        <div class="text-sm font-semibold"> <?= e($r['rule_en']) ?></div>
        <?php if ($r['rule_ur']): ?><div class="text-sm text-gray-600 mt-1" dir="rtl" style="text-align:right; font-family:'Noto Nastaliq Urdu',serif; line-height:2.2"> <?= e($r['rule_ur']) ?></div><?php endif; ?>
      </div>
      <div class="flex gap-2 shrink-0 text-xs">
        <a href="community_rules.php?edit=<?= (int)$r['id'] ?>" class="underline text-blue-600">Edit</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="toggle_rule" value="<?= (int)$r['id'] ?>">
          <button class="underline text-gray-500"><?= $r['is_active'] ? 'Deactivate' : 'Activate' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Rule delete karein?')"><?= csrf_field() ?><input type="hidden" name="delete_rule" value="<?= (int)$r['id'] ?>">
          <button class="underline text-red-600">Delete</button></form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$rules): ?><div class="text-gray-400 text-sm">Koi rule nahi — upar se add karein.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/../inc/footer.php'; ?>
