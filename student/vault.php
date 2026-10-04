<?php
// Student: Niches & Master Prompts Vault
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();
ensure_batch_tables();

$cats = ['niche' => icon('target',16) . ' Niches', 'master_prompt' => icon('bot',16) . ' AI Prompts', 'ad_copy' => icon('edit',16) . ' Ad Copy', 'supplier_list' => icon('package',16) . ' Suppliers', 'ai' => icon('sparkles',16) . ' AI', 'original' => icon('star',16) . ' Original', 'copyright' => icon('shield',16) . ' Copyright'];
$cat = $_GET['cat'] ?? 'all';
if ($cat !== 'all' && !isset($cats[$cat])) $cat = 'all';

// enrolled courses
$st = $pdo->prepare('SELECT course_id FROM enrollments WHERE student_id = ?');
$st->execute([$u['id']]);
$my_courses = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

$sql = 'SELECT v.*, c.title AS course_title FROM vault_resources v LEFT JOIN courses c ON c.id = v.course_id WHERE (v.course_id IS NULL';
$params = [];
if ($my_courses) {
    $sql .= ' OR v.course_id IN (' . implode(',', $my_courses) . ')';
}
$sql .= ')';
if ($cat !== 'all') { $sql .= ' AND v.category = ?'; $params[] = $cat; }
$sql .= ' ORDER BY v.created_at DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$page_title = 'Niches & Prompts Vault';
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-6">
  <h2 class="text-xl font-bold"><?= icon('archive',16) ?> Niches & Master Prompts Vault</h2>
  <p class="text-sm text-gray-500 mt-1">Tested niches aur copy-paste ready prompts — 1 click me copy karein, apne kaam me lagayein.</p>
</div>

<div class="flex gap-2 flex-wrap mb-6">
  <a href="vault.php?cat=all" class="px-4 py-2 rounded-full text-sm font-bold <?= $cat === 'all' ? 'text-white' : 'bg-white' ?>" <?= $cat === 'all' ? 'style="background:#c74b25"' : '' ?>>All</a>
  <?php foreach ($cats as $k => $lbl): ?>
  <a href="vault.php?cat=<?= $k ?>" class="px-4 py-2 rounded-full text-sm font-bold <?= $cat === $k ? 'text-white' : 'bg-white' ?>" <?= $cat === $k ? 'style="background:#c74b25"' : '' ?>><?= $lbl ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="bg-white rounded-lg shadow p-10 text-center text-gray-400 text-sm">Is category me abhi kuch nahi hai — jald add hoga! <?= icon('rocket',16) ?></div>
<?php endif; ?>

<div class="grid md:grid-cols-2 gap-5">
<?php foreach ($rows as $i => $r): ?>
  <div class="bg-white rounded-xl shadow overflow-hidden flex flex-col">
    <div class="p-4 border-b bg-gray-50 flex justify-between items-start gap-2 flex-wrap">
      <div>
        <b><?= e($r['title']) ?></b>
        <div class="mt-1.5 space-x-1">
          <span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-semibold"><?= $cats[$r['category']] ?? $r['category'] ?></span>
          <?php if ($r['course_title']): ?><span class="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full"><?= icon('book-open',16) ?> <?= e($r['course_title']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="p-4 text-sm text-gray-700 flex-1 max-h-72 overflow-y-auto">
      <?= vault_markdown($r['content']) ?>
    </div>
    <div class="p-3 border-t bg-gray-50 flex gap-2">
      <button onclick="copyVault(<?= $i ?>)" class="flex-1 text-white text-sm px-4 py-2 rounded-lg font-bold" style="background:#c74b25"><?= icon('clipboard-list',16) ?> Copy Prompt</button>
      <a href="vault_print.php?id=<?= $r['id'] ?>" target="_blank" class="text-sm px-4 py-2 rounded-lg border font-semibold" title="Print / Save as PDF"><?= icon('printer',16) ?> PDF</a>
    </div>
    <textarea id="vault-raw-<?= $i ?>" class="hidden"><?= e($r['content']) ?></textarea>
  </div>
<?php endforeach; ?>
</div>

<script>
function copyVault(i){
  var t = document.getElementById('vault-raw-' + i).value;
  var done = function(btn){ var b = btn || event.target; var old = b.innerHTML; b.innerHTML = '' + <?= icon('check-circle',14) ?> + ' Copied!'; setTimeout(function(){ b.innerHTML = old; }, 1800); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(t).then(function(){ done(); }).catch(function(){ fallback(); });
  } else fallback();
  function fallback(){
    var ta = document.getElementById('vault-raw-' + i);
    ta.classList.remove('hidden'); ta.select();
    try { document.execCommand('copy'); } catch(e){}
    ta.classList.add('hidden'); done();
  }
}
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
