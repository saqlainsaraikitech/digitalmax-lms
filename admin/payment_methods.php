<?php
// Admin: Payment Methods Manager (dynamic — code edit kiye baghair)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_payments');
$pdo = db();
ensure_payment_settings();

if (isset($_POST['save_method'])) {
    $id = (int)$_POST['save_method'];
    $title = trim($_POST['account_title'] ?? '');
    $number = trim($_POST['account_number'] ?? '');
    $ins = trim($_POST['instructions'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;
    $ord = (int)($_POST['sort_order'] ?? 0);
    if ($id > 0) {
        $pdo->prepare('UPDATE payment_settings SET account_title=?, account_number=?, instructions=?, is_active=?, sort_order=? WHERE id=?')
            ->execute([$title, $number, $ins, $active, $ord, $id]);
        flash('Payment method update ho gaya.');
    } else {
        $name = trim($_POST['method_name'] ?? '');
        if ($name === '') { flash('Method ka naam zaroori hai.', 'error'); }
        else {
            $pdo->prepare('INSERT INTO payment_settings (method_name, account_title, account_number, instructions, is_active, sort_order) VALUES (?,?,?,?,?,?)')
                ->execute([$name, $title, $number, $ins, $active, $ord]);
            flash('Naya payment method add ho gaya.');
        }
    }
    redirect('payment_methods.php');
}
if (isset($_POST['toggle_method'])) {
    $pdo->prepare('UPDATE payment_settings SET is_active = IF(is_active=1,0,1) WHERE id=?')
        ->execute([(int)$_POST['toggle_method']]);
    redirect('payment_methods.php');
}
if (isset($_POST['delete_method'])) {
    $pdo->prepare('DELETE FROM payment_settings WHERE id=?')->execute([(int)$_POST['delete_method']]);
    flash('Method delete ho gaya.');
    redirect('payment_methods.php');
}

$methods = all_payment_methods();
$edit = null;
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM payment_settings WHERE id=?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$page_title = 'Payment Methods';
require __DIR__ . '/../inc/header.php';
?>
<div class="dm-card dm-card-pad mb-6" style="max-width:640px">
  <div class="dm-card-title"><?= icon('credit-card', 18) ?> <?= $edit ? 'Method edit karein' : 'Naya payment method' ?></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="save_method" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
    <?php if (!$edit): ?>
    <div class="mb-3"><label class="text-sm font-semibold">Method name * <span class="text-gray-400 font-normal">(e.g. EasyPaisa, JazzCash, Bank Transfer)</span></label>
      <input name="method_name" required class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="EasyPaisa"></div>
    <?php else: ?>
    <div class="mb-3 text-sm"><span class="text-gray-500">Method:</span> <b><?= e($edit['method_name']) ?></b></div>
    <?php endif; ?>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="text-sm font-semibold">Account Title *</label>
        <input name="account_title" value="<?= e($edit['account_title'] ?? '') ?>" class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="e.g. Muhammad Ahmed"></div>
      <div><label class="text-sm font-semibold">Account Number *</label>
        <input name="account_number" value="<?= e($edit['account_number'] ?? '') ?>" class="w-full border rounded px-3 py-2 mt-1 text-sm" dir="ltr" placeholder="03XX-XXXXXXX"></div>
    </div>
    <div class="mt-3"><label class="text-sm font-semibold">Instructions <span class="text-gray-400 font-normal">(student ko kya hidayat nazar aaye)</span></label>
      <input name="instructions" value="<?= e($edit['instructions'] ?? '') ?>" class="w-full border rounded px-3 py-2 mt-1 text-sm" placeholder="EasyPaisa App ya retailer se send karein"></div>
    <div class="flex gap-4 mt-3 items-center flex-wrap">
      <div><label class="text-sm font-semibold">Sort order</label>
        <input name="sort_order" type="number" value="<?= $edit ? (int)$edit['sort_order'] : 0 ?>" class="w-24 border rounded px-3 py-2 mt-1 text-sm"></div>
      <label class="text-sm flex items-center gap-2 mt-5"><input type="checkbox" name="is_active" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?> style="accent-color:#c74b25"> Active (checkout par show ho)</label>
      <button class="dm-btn mt-4"><?= $edit ? 'Update' : 'Add Method' ?></button>
      <?php if ($edit): ?><a href="payment_methods.php" class="text-sm underline text-gray-500 mt-4">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="dm-card-title"><?= icon('wallet', 18) ?> Active methods <span class="text-gray-400 font-semibold text-xs">(checkout preview jaisa student ko nazar aayega)</span></div>
<div class="pay-cards" style="max-width:900px">
  <?php foreach ($methods as $m): ?>
    <?= payment_method_card($m) ?>
  <?php endforeach; ?>
</div>

<h2 class="font-extrabold mt-6 mb-3">Manage</h2>
<table>
  <thead><tr><th>Method</th><th>Title / Number</th><th>Status</th><th>Actions</th></tr></thead>
  <tbody>
  <?php foreach ($methods as $m): ?>
    <tr>
      <td><b><?= e($m['method_name']) ?></b></td>
      <td><?= e($m['account_title'] ?: '—') ?><br><span class="text-gray-400" dir="ltr"><?= e($m['account_number'] ?: '—') ?></span></td>
      <td><?= $m['is_active'] ? status_badge('active') : status_badge('inactive') ?></td>
      <td class="whitespace-nowrap text-xs">
        <a href="payment_methods.php?edit=<?= (int)$m['id'] ?>" class="underline text-blue-600">Edit</a>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="toggle_method" value="<?= (int)$m['id'] ?>">
          <button class="underline text-gray-500 ml-2"><?= $m['is_active'] ? 'Disable' : 'Enable' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete karein?')"><?= csrf_field() ?><input type="hidden" name="delete_method" value="<?= (int)$m['id'] ?>">
          <button class="underline text-red-600 ml-2">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<div id="payToast"></div>
<script>
document.querySelectorAll('.pay-copy-btn').forEach(function(b){
  b.addEventListener('click', function(){
    var t = b.getAttribute('data-copy');
    function done(){
      var toast = document.getElementById('payToast');
      toast.textContent = 'Copied: ' + t;
      toast.classList.add('show');
      setTimeout(function(){ toast.classList.remove('show'); }, 1800);
      var sp = b.querySelector('span'); var old = sp.textContent;
      sp.textContent = 'Copied!';
      setTimeout(function(){ sp.textContent = old; }, 1500);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done).catch(done);
    else { var ta = document.createElement('textarea'); ta.value = t; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch(e){} document.body.removeChild(ta); done(); }
  });
});
</script>
<?php require __DIR__ . '/../inc/footer.php'; ?>
