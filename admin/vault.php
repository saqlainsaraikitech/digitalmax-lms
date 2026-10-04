<?php
// Admin: Niches & Master Prompts Vault (CRUD)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
require_perm('p_courses');
$pdo = db();
ensure_batch_tables();

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$cats = ['niche' => icon('target',16) . ' Niche', 'master_prompt' => icon('bot',16) . ' Master Prompt', 'ad_copy' => icon('edit',16) . ' Ad Copy', 'supplier_list' => icon('package',16) . ' Supplier List', 'ai' => icon('sparkles',16) . ' AI', 'original' => icon('star',16) . ' Original', 'copyright' => icon('shield',16) . ' Copyright'];

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM vault_resources WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

if (isset($_POST['save_vault'])) {
    $title = trim($_POST['title'] ?? '');
    $cat = $_POST['category'] ?? 'master_prompt';
    $content = trim($_POST['content'] ?? '');
    $course_id = (int)($_POST['course_id'] ?? 0);
    if (!isset($cats[$cat])) $cat = 'master_prompt';
    if ($title === '' || $content === '') { flash('Title aur content zaroori hai.', 'error'); }
    else {
        $vid = (int)($_POST['vault_id'] ?? 0);
        if ($vid > 0) {
            $pdo->prepare('UPDATE vault_resources SET title=?, category=?, content=?, course_id=? WHERE id=?')
                ->execute([$title, $cat, $content, $course_id > 0 ? $course_id : null, $vid]);
            flash('Vault item update ho gaya.');
        } else {
            $pdo->prepare('INSERT INTO vault_resources (title, category, content, course_id) VALUES (?,?,?,?)')
                ->execute([$title, $cat, $content, $course_id > 0 ? $course_id : null]);
            flash('Vault me add ho gaya. ');
        }
    }
    redirect('vault.php');
}

if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM vault_resources WHERE id = ?')->execute([(int)$_POST['delete']]);
    flash('Delete ho gaya.');
    redirect('vault.php');
}

$rows = $pdo->query('SELECT v.*, c.title AS course_title FROM vault_resources v LEFT JOIN courses c ON c.id = v.course_id ORDER BY v.created_at DESC')->fetchAll();

$page_title = 'Niches & Prompts Vault';
require __DIR__ . '/../inc/header.php';
?>

<div class="grid lg:grid-cols-3 gap-6">
  <div class="bg-white rounded-lg shadow p-6 h-fit">
    <h3 class="font-bold mb-4"><?= $edit ? icon('edit',16) . ' Edit Item' : icon('plus',16) . ' Naya Vault Item' ?></h3>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="vault_id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div><label class="text-sm font-semibold">Title *</label>
        <input name="title" required value="<?= e($edit['title'] ?? '') ?>" placeholder="Misal: 10 Winning TikTok Niches 2026" class="w-full border rounded px-3 py-2 mt-1"></div>
      <div><label class="text-sm font-semibold">Category *</label>
        <select name="category" class="w-full border rounded px-3 py-2 mt-1">
          <?php foreach ($cats as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= ($edit['category'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="text-sm font-semibold">Course (optional)</label>
        <select name="course_id" class="w-full border rounded px-3 py-2 mt-1">
          <option value="0"><?= icon('globe',16) ?> Sab courses (global)</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($edit['course_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-400 mt-1">Kisi course se link karein to sirf uske students ko dikhega.</p></div>
      <div><label class="text-sm font-semibold">Content * (Markdown supported)</label>
        <textarea name="content" rows="10" required placeholder="## Heading&#10;**bold** text&#10;- bullet point&#10;```code block```" class="w-full border rounded px-3 py-2 mt-1 font-mono text-sm"><?= e($edit['content'] ?? '') ?></textarea></div>
      <div class="flex gap-2">
        <button name="save_vault" value="1" class="text-white px-5 py-2 rounded font-semibold text-sm" style="background:#c74b25"><?= $edit ? 'Update' : 'Add to Vault' ?></button>
        <?php if ($edit): ?><a href="vault.php" class="px-4 py-2 text-sm underline text-gray-500">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="lg:col-span-2">
    <h3 class="font-bold mb-4">Vault Items (<?= count($rows) ?>)</h3>
    <?php if (!$rows): ?><div class="bg-white rounded-lg shadow p-8 text-center text-gray-400 text-sm">Abhi khali hai — pehla niche/prompt upar form se add karein.</div><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-3">
      <div class="flex justify-between items-start gap-2 flex-wrap">
        <div>
          <b><?= e($r['title']) ?></b>
          <div class="mt-1 space-x-1">
            <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-semibold"><?= $cats[$r['category']] ?? $r['category'] ?></span>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= $r['course_title'] ? icon('book-open',16) . ' ' . e($r['course_title']) : icon('globe',16) . ' All courses' ?></span>
          </div>
        </div>
        <div class="flex gap-3 items-center">
          <a href="vault.php?edit=<?= $r['id'] ?>" class="underline text-blue-600 text-sm">Edit</a>
          <form method="post" class="inline" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?>
            <input type="hidden" name="delete" value="<?= $r['id'] ?>">
            <button class="underline text-red-600 text-sm">Delete</button>
          </form>
        </div>
      </div>
      <div class="text-sm text-gray-600 mt-3 max-h-32 overflow-hidden relative">
        <?= vault_markdown(mb_substr($r['content'], 0, 400)) ?><?= mb_strlen($r['content']) > 400 ? '…' : '' ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
