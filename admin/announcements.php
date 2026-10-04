<?php
// Admin: Announcements & Notice Board (create / manage)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
require_perm('p_courses');
$pdo = db();
ensure_ann_tables();
ensure_batch_tables();

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$batches = $pdo->query('SELECT b.*, c.title AS course_title FROM batches b JOIN courses c ON c.id = b.course_id ORDER BY c.title, b.start_date')->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM announcements WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

if (isset($_POST['save_ann'])) {
    $title = trim($_POST['title'] ?? '');
    $message = clean_announcement_html($_POST['message'] ?? '');
    $target = $_POST['target_type'] ?? 'all_students';
    $course_id = (int)($_POST['course_id'] ?? 0);
    $batch_id = (int)($_POST['batch_id'] ?? 0);
    $priority = $_POST['priority'] ?? 'normal';
    if (!in_array($target, ['all_students', 'specific_course', 'specific_batch'], true)) $target = 'all_students';
    if (!in_array($priority, ['normal', 'urgent', 'pinned'], true)) $priority = 'normal';
    if ($target === 'specific_course' && $course_id <= 0) $target = 'all_students';
    if ($target === 'specific_batch' && $batch_id <= 0) $target = 'all_students';
    if ($title === '' || $message === '') { flash('Title aur message zaroori hai.', 'error'); }
    else {
        $aid = (int)($_POST['ann_id'] ?? 0);
        $cid = $target === 'specific_course' ? $course_id : null;
        $bid = $target === 'specific_batch' ? $batch_id : null;
        if ($aid > 0) {
            $pdo->prepare('UPDATE announcements SET title=?, message=?, target_type=?, course_id=?, batch_id=?, priority=? WHERE id=?')
                ->execute([$title, $message, $target, $cid, $bid, $priority, $aid]);
            flash('Announcement update ho gayi.');
        } else {
            $pdo->prepare('INSERT INTO announcements (title, message, target_type, course_id, batch_id, priority) VALUES (?,?,?,?,?,?)')
                ->execute([$title, $message, $target, $cid, $bid, $priority]);
            flash('Announcement publish ho gayi. ');
        }
    }
    redirect('announcements.php');
}

if (isset($_POST['toggle'])) {
    $pdo->prepare('UPDATE announcements SET is_active = IF(is_active=1,0,1) WHERE id = ?')->execute([(int)$_POST['toggle']]);
    flash('Status change ho gaya.');
    redirect('announcements.php');
}
if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([(int)$_POST['delete']]);
    flash('Announcement delete ho gayi.');
    redirect('announcements.php');
}

$rows = $pdo->query('SELECT a.*, c.title AS course_title, b.batch_name FROM announcements a
    LEFT JOIN courses c ON c.id = a.course_id LEFT JOIN batches b ON b.id = a.batch_id
    ORDER BY a.created_at DESC')->fetchAll();

$page_title = 'Announcements';
require __DIR__ . '/../inc/header.php';
?>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Create/Edit -->
  <div class="bg-white rounded-lg shadow p-6 h-fit">
    <h3 class="font-bold mb-4"><?= $edit ? icon('edit',16) . ' Edit Announcement' : icon('megaphone',16) . ' Nayi Announcement' ?></h3>
    <form method="post" id="annForm" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="ann_id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div><label class="text-sm font-semibold">Title *</label>
        <input name="title" required value="<?= e($edit['title'] ?? '') ?>" placeholder="Misal: Kal raat 9 baje Zoom class!" class="w-full border rounded px-3 py-2 mt-1"></div>

      <div><label class="text-sm font-semibold">Message * (rich text)</label>
        <div class="border rounded mt-1 overflow-hidden">
          <div class="flex gap-1 p-2 bg-gray-50 border-b flex-wrap">
            <button type="button" onclick="ed('bold')" class="px-2.5 py-1 border rounded text-sm font-bold bg-white" title="Bold">B</button>
            <button type="button" onclick="ed('italic')" class="px-2.5 py-1 border rounded text-sm italic bg-white" title="Italic">I</button>
            <button type="button" onclick="ed('underline')" class="px-2.5 py-1 border rounded text-sm underline bg-white" title="Underline">U</button>
            <button type="button" onclick="ed('insertUnorderedList')" class="px-2.5 py-1 border rounded text-sm bg-white" title="Bullet list">• List</button>
            <button type="button" onclick="edLink()" class="px-2.5 py-1 border rounded text-sm bg-white" title="Link lagayein"><?= icon('link',16) ?> Link</button>
          </div>
          <div id="msgEd" contenteditable="true" class="p-3 min-h-32 text-sm focus:outline-none"><?= $edit ? $edit['message'] : '' ?></div>
        </div>
        <input type="hidden" name="message" id="msgHidden">
        <p class="text-xs text-gray-400 mt-1">Zoom/WhatsApp links yahan lagayein — student ko clickable milenge.</p></div>

      <div><label class="text-sm font-semibold">Target Audience *</label>
        <select name="target_type" id="targetType" onchange="targetUI()" class="w-full border rounded px-3 py-2 mt-1">
          <option value="all_students" <?= ($edit['target_type'] ?? '') === 'all_students' ? 'selected' : '' ?>><?= icon('users',16) ?> All Registered Students</option>
          <option value="specific_course" <?= ($edit['target_type'] ?? '') === 'specific_course' ? 'selected' : '' ?>><?= icon('book-open',16) ?> Specific Course</option>
          <option value="specific_batch" <?= ($edit['target_type'] ?? '') === 'specific_batch' ? 'selected' : '' ?>><?= icon('calendar',16) ?> Specific Batch</option>
        </select></div>
      <div id="targetCourseBox" style="display:none"><label class="text-sm font-semibold">Course select karein</label>
        <select name="course_id" class="w-full border rounded px-3 py-2 mt-1">
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($edit['course_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div id="targetBatchBox" style="display:none"><label class="text-sm font-semibold">Batch select karein</label>
        <select name="batch_id" class="w-full border rounded px-3 py-2 mt-1">
          <?php foreach ($batches as $b): ?>
          <option value="<?= $b['id'] ?>" <?= ($edit['batch_id'] ?? 0) == $b['id'] ? 'selected' : '' ?>><?= e($b['course_title']) ?> — <?= e($b['batch_name']) ?></option>
          <?php endforeach; ?>
        </select></div>

      <div><label class="text-sm font-semibold">Priority</label>
        <div class="flex gap-4 mt-2 text-sm">
          <label class="flex items-center gap-1.5"><input type="radio" name="priority" value="normal" <?= ($edit['priority'] ?? 'normal') === 'normal' ? 'checked' : '' ?>> Normal</label>
          <label class="flex items-center gap-1.5"><input type="radio" name="priority" value="urgent" <?= ($edit['priority'] ?? '') === 'urgent' ? 'checked' : '' ?>> <?= icon('dot',16) ?> Urgent</label>
          <label class="flex items-center gap-1.5"><input type="radio" name="priority" value="pinned" <?= ($edit['priority'] ?? '') === 'pinned' ? 'checked' : '' ?>> <?= icon('pin',16) ?> Pinned</label>
        </div>
        <p class="text-xs text-gray-400 mt-1">Urgent = dashboard par red alert bar · Pinned = list me sab se upar.</p></div>

      <div class="flex gap-2">
        <button name="save_ann" value="1" class="text-white px-5 py-2 rounded font-semibold text-sm" style="background:#c74b25"><?= $edit ? 'Update' : 'Publish ' . icon('megaphone',16) ?></button>
        <?php if ($edit): ?><a href="announcements.php" class="px-4 py-2 text-sm underline text-gray-500">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Manage -->
  <div class="lg:col-span-2">
    <h3 class="font-bold mb-4">Manage Announcements (<?= count($rows) ?>)</h3>
    <?php if (!$rows): ?><div class="bg-white rounded-lg shadow p-8 text-center text-gray-400 text-sm">Abhi koi announcement nahi.</div><?php endif; ?>
    <?php foreach ($rows as $r): ?>
    <div class="bg-white rounded-lg shadow p-4 mb-3 <?= !$r['is_active'] ? 'opacity-60' : '' ?>">
      <div class="flex justify-between items-start gap-2 flex-wrap">
        <div>
          <b><?= e($r['title']) ?></b>
          <div class="mt-1.5 space-x-1">
            <?= ann_priority_badge($r['priority']) ?>
            <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full"><?= ann_target_label($r) ?></span>
            <?= !$r['is_active'] ? '<span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full">Hidden</span>' : '' ?>
          </div>
          <div class="text-xs text-gray-400 mt-1"><?= date('d M Y · h:i A', strtotime($r['created_at'])) ?></div>
        </div>
        <div class="flex gap-2 items-center">
          <a href="announcements.php?edit=<?= $r['id'] ?>" class="underline text-blue-600 text-sm">Edit</a>
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="toggle" value="<?= $r['id'] ?>">
            <button class="underline text-amber-600 text-sm"><?= $r['is_active'] ? 'Hide' : 'Show' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('Delete?')">
            <?= csrf_field() ?>
            <input type="hidden" name="delete" value="<?= $r['id'] ?>">
            <button class="underline text-red-600 text-sm">Delete</button>
          </form>
        </div>
      </div>
      <div class="text-sm text-gray-600 mt-2 border-t pt-2"><?= $r['message'] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function ed(cmd){ document.execCommand(cmd, false, null); document.getElementById('msgEd').focus(); }
function edLink(){
  var u = prompt('Link URL likhein (https:// se shuru):');
  if (u) { if (!/^https?:\/\//i.test(u)) u = 'https://' + u; document.execCommand('createLink', false, u); }
  document.getElementById('msgEd').focus();
}
function targetUI(){
  var t = document.getElementById('targetType').value;
  document.getElementById('targetCourseBox').style.display = t === 'specific_course' ? 'block' : 'none';
  document.getElementById('targetBatchBox').style.display = t === 'specific_batch' ? 'block' : 'none';
}
targetUI();
document.getElementById('annForm').addEventListener('submit', function(){
  document.getElementById('msgHidden').value = document.getElementById('msgEd').innerHTML;
});
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
