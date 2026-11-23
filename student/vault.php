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

$cats = ['niche' => 'Niches', 'master_prompt' => 'AI prompts', 'ad_copy' => 'Ad copy', 'supplier_list' => 'Suppliers', 'ai' => 'AI', 'original' => 'Original', 'copyright' => 'Copyright'];
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
  <h2 class="dm-page-h">Niches & master prompts</h2>
  <p class="text-sm text-gray-500 mt-1">Tested niches aur copy-paste ready prompts — 1 click me copy karein, apne kaam me lagayein.</p>
</div>

<div class="dm-tabs mb-6">
  <a href="vault.php?cat=all" class="dm-tab <?= $cat === 'all' ? 'on' : '' ?>">All</a>
  <?php foreach ($cats as $k => $lbl): ?>
  <a href="vault.php?cat=<?= $k ?>" class="dm-tab <?= $cat === $k ? 'on' : '' ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <div class="dm-card std-empty"><p style="margin:0">Is category me abhi kuch nahi hai. Jald add hoga</p></div>
<?php endif; ?>

<div class="grid md:grid-cols-2 gap-5">
<?php foreach ($rows as $i => $r): ?>
  <div class="bg-white rounded-xl shadow overflow-hidden flex flex-col">
    <div class="p-4 border-b bg-gray-50 flex justify-between items-start gap-2 flex-wrap">
      <div>
        <b><?= e($r['title']) ?></b>
        <div class="mt-1.5 space-x-1">
          <span class="dm-tag"><?= e($cats[$r['category']] ?? $r['category']) ?></span>
          <?php if ($r['course_title']): ?><span class="meta"><?= e($r['course_title']) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="p-4 text-sm text-gray-700 flex-1 max-h-72 overflow-y-auto">
      <?= vault_markdown($r['content']) ?>
    </div>
    <div class="p-3 border-t bg-gray-50 flex gap-2">
      <button onclick="copyVault(<?= $i ?>, this)" class="dm-btn flex-1"><?= icon('clipboard-list',16) ?> Copy prompt</button>
      <a href="vault_print.php?id=<?= $r['id'] ?>" target="_blank" class="text-sm px-4 py-2 rounded-lg border font-semibold" title="Print / Save as PDF"><?= icon('printer',16) ?> PDF</a>
    </div>
    <textarea id="vault-raw-<?= $i ?>" class="hidden"><?= e($r['content']) ?></textarea>
  </div>
<?php endforeach; ?>
</div>

<script>
function copyVault(i, srcBtn){
  var t = document.getElementById('vault-raw-' + i).value;
  var done = function(btn){ var b = btn || srcBtn; if (!b) return; var old = b.innerHTML; b.innerHTML = <?= json_encode(icon('check-circle',16) . ' Copied') ?>; setTimeout(function(){ b.innerHTML = old; }, 1800); };
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
