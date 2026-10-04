<?php
// Lecture add/edit
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_lectures');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$course_id = (int)($_GET['course_id'] ?? 0);
$lec = ['course_id' => $course_id, 'section_id' => (int)($_GET['section_id'] ?? 0) ?: null,
        'title' => '', 'youtube_url' => '', 'duration' => '', 'notes' => '',
        'resource_file' => '', 'sort_order' => 0, 'is_preview' => 0];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM lectures WHERE id = ?');
    $st->execute([$id]);
    if ($row = $st->fetch()) { $lec = $row; $course_id = $row['course_id']; }
}
$st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
$st->execute([$course_id]);
$course = $st->fetch();
if (!$course) { flash('Course nahi mila.', 'error'); redirect('courses.php'); }

$sections = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order, id');
$sections->execute([$course_id]);
$sections = $sections->fetchAll();

// v21: course ke batches (assign-to-batches section ke liye)
ensure_batch_tables();
$all_batches = course_batches($course_id);
$lec_batches = [];
$share_all = 1;
if ($id) {
    $share_all = (int)($lec['share_all_batches'] ?? 1);
    $bst = $pdo->prepare('SELECT batch_id FROM lecture_batches WHERE lecture_id = ?');
    $bst->execute([$id]);
    $lec_batches = array_map('intval', $bst->fetchAll(PDO::FETCH_COLUMN));
}

// resources table (purani installs ke liye self-healing)
try { $pdo->query('SELECT 1 FROM lecture_resources LIMIT 1'); }
catch (Exception $ex) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS lecture_resources (
      id INT AUTO_INCREMENT PRIMARY KEY,
      lecture_id INT NOT NULL,
      label VARCHAR(150) NOT NULL DEFAULT "",
      file_path VARCHAR(255) DEFAULT NULL,
      url VARCHAR(255) DEFAULT NULL,
      sort_order INT NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

if ($id && isset($_POST['del_resource'])) {
    $pdo->prepare('DELETE FROM lecture_resources WHERE id = ? AND lecture_id = ?')->execute([(int)$_POST['del_resource'], $id]);
    flash('Resource delete ho gaya.');
    redirect('lecture_form.php?id=' . $id);
}
if ($id && isset($_POST['del_legacy'])) {
    $pdo->prepare('UPDATE lectures SET resource_file = NULL WHERE id = ?')->execute([$id]);
    $lec['resource_file'] = null;
    flash('Purani file delete ho gayi.');
    redirect('lecture_form.php?id=' . $id);
}

if ($id && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_resource'])) {
    try {
        $file = upload_file('res_file', 'resources', ['pdf','zip','jpg','jpeg','png','gif','webp','doc','docx','xls','xlsx','ppt','pptx','csv','txt','mp4']);
        $url = trim($_POST['res_url'] ?? '');
        $label = trim($_POST['res_label'] ?? '');
        if (!$file && $url === '') throw new Exception('File upload karein ya URL paste karein.');
        if ($url !== '' && !preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        if ($label === '') $label = $file ? 'Download file' : 'Open resource link';
        $pdo->prepare('INSERT INTO lecture_resources (lecture_id, label, file_path, url) VALUES (?,?,?,?)')
            ->execute([$id, $label, $file, $url !== '' ? $url : null]);
        flash('Resource add ho gaya. ');
    } catch (Exception $ex) { flash($ex->getMessage(), 'error'); }
    redirect('lecture_form.php?id=' . $id);
}

$res_list = [];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM lecture_resources WHERE lecture_id = ? ORDER BY sort_order, id');
    $st->execute([$id]);
    $res_list = $st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['add_resource'])) {
    try {
        $data = [
            trim($_POST['title']), trim($_POST['youtube_url']), trim($_POST['duration']),
            $_POST['notes'] ?? '', $lec['resource_file'], (int)($_POST['sort_order'] ?? 0),
            isset($_POST['is_preview']) ? 1 : 0,
            $_POST['section_id'] !== '' ? (int)$_POST['section_id'] : null,
        ];
        if ($id) {
            $st = $pdo->prepare('UPDATE lectures SET title=?, youtube_url=?, duration=?, notes=?, resource_file=?, sort_order=?, is_preview=?, section_id=?, share_all_batches=? WHERE id=?');
            $st->execute([...$data, isset($_POST['share_all']) ? 1 : 0, $id]);
            $saved_id = $id;
            flash('Lecture update ho gaya.');
        } else {
            $st = $pdo->prepare('INSERT INTO lectures (title, youtube_url, duration, notes, resource_file, sort_order, is_preview, section_id, course_id, share_all_batches) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $st->execute([...$data, $course_id, isset($_POST['share_all']) ? 1 : 0]);
            $saved_id = (int)$pdo->lastInsertId();
            flash('Lecture add ho gaya.');
        }
        // v21: batch assignment save
        $share_all_new = isset($_POST['share_all']) ? 1 : 0;
        $pdo->prepare('DELETE FROM lecture_batches WHERE lecture_id = ?')->execute([$saved_id]);
        if (!$share_all_new) {
            $bins = $pdo->prepare('INSERT IGNORE INTO lecture_batches (lecture_id, batch_id) VALUES (?, ?)');
            foreach (array_map('intval', $_POST['batches'] ?? []) as $bid) {
                // security: batch isi course ka hona chahiye
                foreach ($all_batches as $ab) {
                    if ((int)$ab['id'] === $bid) { $bins->execute([$saved_id, $bid]); break; }
                }
            }
        }
        redirect('curriculum.php?course_id=' . $course_id);
    } catch (Exception $ex) { flash($ex->getMessage(), 'error'); }
}

$page_title = ($id ? 'Edit lecture' : 'New lecture') . ' — ' . $course['title'];
require __DIR__ . '/../inc/header.php';
?>
<form method="post" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 max-w-2xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Lecture title *</label>
    <input name="title" required value="<?= e($lec['title']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div class="grid grid-cols-2 gap-4">
    <div><label class="text-sm font-semibold">Section</label>
      <select name="section_id" class="w-full border rounded px-3 py-2 mt-1">
        <option value="">— Bina section —</option>
        <?php foreach ($sections as $s): ?>
          <option value="<?= $s['id'] ?>" <?= (int)$lec['section_id'] === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div><label class="text-sm font-semibold">Order</label>
      <input name="sort_order" type="number" value="<?= (int)$lec['sort_order'] ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  </div>
  <div><label class="text-sm font-semibold">YouTube URL (Unlisted video ka link) *</label>
    <input name="youtube_url" required value="<?= e($lec['youtube_url']) ?>" placeholder="https://www.youtube.com/watch?v=..." class="w-full border rounded px-3 py-2 mt-1">
    <p class="text-xs text-gray-400 mt-1">Video YouTube par <b>Unlisted</b> rakhein — sirf LMS me embed hogi.</p></div>
  <div><label class="text-sm font-semibold">Duration (misal: 12:30)</label>
    <input name="duration" value="<?= e($lec['duration']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Notes (lecture ke neeche show honge)</label>
    <textarea name="notes" rows="4" class="w-full border rounded px-3 py-2 mt-1"><?= e($lec['notes']) ?></textarea></div>
  <div><label class="text-sm font-semibold">Resource file (PDF/ZIP)</label>
    <?php if ($lec['resource_file']): ?><div class="text-xs my-1"><a href="../<?= e($lec['resource_file']) ?>" class="underline text-blue-600">Current file</a></div><?php endif; ?>
    <input type="file" name="resource" class="mt-1 text-sm"></div>
  <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_preview" <?= $lec['is_preview'] ? 'checked' : '' ?>> Free preview (bina enroll dekha ja sakta hai)</label>

  <!-- v21: Assign to Batches -->
  <div class="border-2 border-dashed rounded-lg p-4" style="border-color:#e8823f; background:#fdf6ec">
    <div class="font-bold text-sm mb-1"><?= icon('calendar',16) ?> Assign to Batches</div>
    <?php if (!$all_batches): ?>
      <p class="text-xs text-gray-500">Is course ka abhi koi batch nahi bana — lecture sab ko visible rahega. <a href="batches.php?course_id=<?= $course_id ?>" class="underline" style="color:#c74b25">Batch banayein →</a></p>
      <input type="hidden" name="share_all" value="1">
    <?php else: ?>
      <label class="flex items-center gap-2 text-sm font-semibold bg-white p-2 rounded border mb-2">
        <input type="checkbox" name="share_all" value="1" id="shareAllChk" <?= $share_all ? 'checked' : '' ?> onchange="document.getElementById('batchPick').style.display = this.checked ? 'none' : 'block'">
        <?= icon('globe',16) ?> Share with ALL batches (current & previous)
      </label>
      <div id="batchPick" style="display:<?= $share_all ? 'none' : 'block' ?>">
        <p class="text-xs text-gray-500 mb-2">Ya manually select karein — konse batch walon ko ye lecture milega:</p>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach ($all_batches as $b): ?>
          <label class="flex items-center gap-2 text-xs bg-white p-2 rounded border">
            <input type="checkbox" name="batches[]" value="<?= $b['id'] ?>" <?= in_array((int)$b['id'], $lec_batches, true) ? 'checked' : '' ?>>
            <?= e($b['batch_name']) ?> <span class="text-gray-400">(<?= batch_status_label(batch_status($b)) ?>)</span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save lecture</button>
  <a href="curriculum.php?course_id=<?= $course_id ?>" class="ml-3 text-sm underline text-gray-500">Cancel</a>
</form>

<?php if ($id): ?>
<div class="bg-white rounded-lg shadow p-6 max-w-2xl mt-6">
  <h3 class="font-bold"><?= icon('paperclip',16) ?> Resources / Attachments</h3>
  <p class="text-xs text-gray-400 mb-4 mt-1">PDF, ZIP, theme files ya Google Drive / Sheet links — student ko "Resources & Downloads" tab me milenge.</p>

  <?php if ($lec['resource_file']): ?>
    <div class="flex justify-between items-center text-sm bg-yellow-50 border border-yellow-200 p-2.5 rounded mb-2">
      <span><?= icon('file-text',16) ?> <a href="../<?= e($lec['resource_file']) ?>" target="_blank" class="underline font-semibold">Purani attached file</a></span>
      <form method="post" action="lecture_form.php?id=<?= $id ?>" class="inline" onsubmit="return confirm('Purani file delete?')">
        <?= csrf_field() ?>
        <input type="hidden" name="del_legacy" value="1">
        <button class="underline text-red-600">Delete</button>
      </form>
    </div>
  <?php endif; ?>

  <?php foreach ($res_list as $r): ?>
    <div class="flex justify-between items-center text-sm bg-gray-50 p-2.5 rounded mb-2">
      <span><?= $r['file_path'] ? icon('file-text',16) : icon('link',16) ?> <b><?= e($r['label']) ?></b>
        <span class="text-gray-400 text-xs">(<?= $r['file_path'] ? 'File' : 'Link' ?>)</span></span>
      <span class="space-x-2">
        <?php if ($r['file_path']): ?><a href="../<?= e($r['file_path']) ?>" target="_blank" class="underline text-blue-600">View</a>
        <?php else: ?><a href="<?= e($r['url']) ?>" target="_blank" class="underline text-blue-600">Open</a><?php endif; ?>
        <form method="post" action="lecture_form.php?id=<?= $id ?>" class="inline" onsubmit="return confirm('Resource delete?')">
          <?= csrf_field() ?>
          <input type="hidden" name="del_resource" value="<?= $r['id'] ?>">
          <button class="underline text-red-600">Delete</button>
        </form>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (!$res_list && !$lec['resource_file']): ?><p class="text-sm text-gray-400 mb-2">Koi resource nahi.</p><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="mt-4 bg-gray-50 p-4 rounded space-y-3">
  <?= csrf_field() ?>
    <input type="hidden" name="add_resource" value="1">
    <div><label class="text-sm font-semibold">Label * <span class="font-normal text-gray-400">(misal: Product Research Sheet)</span></label>
      <input name="res_label" required placeholder="Resource ka naam" class="w-full border rounded px-3 py-2 mt-1"></div>
    <div class="grid md:grid-cols-2 gap-3">
      <div><label class="text-sm font-semibold">File upload</label>
        <input type="file" name="res_file" class="mt-1 text-sm w-full">
        <p class="text-xs text-gray-400">PDF, ZIP, DOC, XLS, PPT, images</p></div>
      <div><label class="text-sm font-semibold">Ya external URL</label>
        <input name="res_url" placeholder="https://drive.google.com/..." class="w-full border rounded px-3 py-2 mt-1">
        <p class="text-xs text-gray-400">Google Drive / Sheet ka link</p></div>
    </div>
    <p class="text-xs text-gray-400">File ya URL — dono me se ek kafi hai.</p>
    <button class="text-white px-5 py-2 rounded text-sm font-semibold" style="background:#c74b25">+ Add resource</button>
  </form>
</div>
<?php else: ?>
<p class="text-xs text-gray-400 mt-3 max-w-2xl"><?= icon('lightbulb',16) ?> Resources lecture save karne ke baad add hon ge.</p>
<?php endif; ?>
<?php require __DIR__ . '/../inc/footer.php'; ?>
