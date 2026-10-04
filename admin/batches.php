<?php
// Admin: Batch management (course ke batches — start/end/duration)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
require_perm('p_courses');
$pdo = db();
ensure_batch_tables();

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$filter_course = (int)($_GET['course_id'] ?? 0);

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM batches WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}

if (isset($_POST['save_batch'])) {
    $course_id = (int)($_POST['course_id'] ?? 0);
    $name = trim($_POST['batch_name'] ?? '');
    $start = trim($_POST['start_date'] ?? '') ?: null;
    $end = trim($_POST['end_date'] ?? '') ?: null;
    $dur = max(1, (int)($_POST['duration_months'] ?? 3));
    if ($course_id <= 0 || $name === '') { flash('Course aur batch name zaroori hai.', 'error'); }
    elseif ($start && $end && $end < $start) { flash('End date start date se pehle nahi ho sakti.', 'error'); }
    else {
        ensure_community_columns();
        $wa_community = trim($_POST['whatsapp_community_link'] ?? '');
        if ($wa_community !== '' && !valid_wa_link($wa_community)) { flash('Official community link https:// se shuru hona chahiye.', 'error'); redirect('batches.php' . ($filter_course ? '?course_id=' . $filter_course : '')); }
        $wa_group = trim($_POST['whatsapp_group_link'] ?? '');
        if ($wa_group !== '' && !valid_wa_link($wa_group)) { flash('Discussion group link https:// se shuru hona chahiye.', 'error'); redirect('batches.php' . ($filter_course ? '?course_id=' . $filter_course : '')); }
        // auto duration: end-start se months nikal lo (agar di gayi ho)
        if ($start && $end) {
            $d1 = new DateTime($start); $d2 = new DateTime($end);
            $diff = $d1->diff($d2);
            $dur = max(1, $diff->y * 12 + $diff->m + ($diff->d >= 15 ? 1 : 0));
        }
        $status = 'upcoming';
        $today = date('Y-m-d');
        if ($end && $end < $today) $status = 'completed';
        elseif ((!$start || $start <= $today) && (!$end || $end >= $today)) $status = 'active';
        $bid = (int)($_POST['batch_id'] ?? 0);
        if ($bid > 0) {
            $pdo->prepare('UPDATE batches SET course_id=?, batch_name=?, start_date=?, end_date=?, duration_months=?, status=?, whatsapp_community_link=?, whatsapp_group_link=? WHERE id=?')
                ->execute([$course_id, $name, $start, $end, $dur, $status, $wa_community ?: null, $wa_group ?: null, $bid]);
            flash('Batch update ho gaya.');
        } else {
            $pdo->prepare('INSERT INTO batches (course_id, batch_name, start_date, end_date, duration_months, status, whatsapp_community_link, whatsapp_group_link) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([$course_id, $name, $start, $end, $dur, $status, $wa_community ?: null, $wa_group ?: null]);
            flash('Naya batch ban gaya. ');
        }
    }
    redirect('batches.php' . ($filter_course ? '?course_id=' . $filter_course : ''));
}

if (isset($_POST['delete'])) {
    $pdo->prepare('DELETE FROM batches WHERE id = ?')->execute([(int)$_POST['delete']]);
    flash('Batch delete ho gaya.');
    redirect('batches.php' . ($filter_course ? '?course_id=' . $filter_course : ''));
}

$sql = 'SELECT b.*, c.title AS course_title, (SELECT COUNT(*) FROM enrollments e WHERE e.batch_id = b.id) AS students
        FROM batches b JOIN courses c ON c.id = b.course_id';
$params = [];
if ($filter_course) { $sql .= ' WHERE b.course_id = ?'; $params[] = $filter_course; }
$sql .= ' ORDER BY b.start_date DESC, b.id DESC';
$st = $pdo->prepare($sql); $st->execute($params);
$batches = $st->fetchAll();

$page_title = 'Batches';
require __DIR__ . '/../inc/header.php';
?>

<?php
$filter_title = '';
if ($filter_course) {
    foreach ($courses as $cc) if ((int)$cc['id'] === $filter_course) { $filter_title = $cc['title']; break; }
}
?>
<div class="flex flex-wrap justify-between items-center gap-3 mb-5">
  <h2 class="text-xl font-bold"><?= icon('calendar',16) ?> Batches <?= $filter_title ? '<span class="text-sm font-normal text-gray-500">(' . e($filter_title) . ')</span>' : '' ?></h2>
  <form method="get" class="flex gap-2">
    <select name="course_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm">
      <option value="0">Sab courses</option>
      <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filter_course === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="grid lg:grid-cols-3 gap-6">
  <!-- Form -->
  <div class="bg-white rounded-lg shadow p-6 h-fit">
    <h3 class="font-bold mb-4"><?= $edit ? icon('edit',16) . ' Edit Batch' : icon('plus',16) . ' Naya Batch' ?></h3>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="batch_id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
      <div><label class="text-sm font-semibold">Course *</label>
        <select name="course_id" required class="w-full border rounded px-3 py-2 mt-1">
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($edit && (int)$edit['course_id'] === (int)$c['id']) || (!$edit && $filter_course === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['title']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label class="text-sm font-semibold">Batch name *</label>
        <input name="batch_name" required placeholder='Misal: Batch 1 - Oct 2026' value="<?= e($edit['batch_name'] ?? '') ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="text-sm font-semibold">Start date</label>
          <input name="start_date" type="date" value="<?= e($edit['start_date'] ?? '') ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
        <div><label class="text-sm font-semibold">End date</label>
          <input name="end_date" type="date" value="<?= e($edit['end_date'] ?? '') ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
      </div>
      <div><label class="text-sm font-semibold">Duration (months)</label>
        <input name="duration_months" type="number" min="1" value="<?= e($edit['duration_months'] ?? 3) ?>" class="w-full border rounded px-3 py-2 mt-1">
        <p class="text-xs text-gray-400 mt-1">Start+End dono diye to auto-calculate ho jayega.</p></div>
      <div><label class="text-sm font-semibold"><?= icon('megaphone',16) ?> Official WhatsApp Community Link <span class="text-gray-400 font-normal">(Announcements)</span></label>
        <input name="whatsapp_community_link" value="<?= e($edit['whatsapp_community_link'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..." class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
        <p class="text-xs text-gray-400 mt-1">Khali = course ka community link use hoga.</p></div>
      <div><label class="text-sm font-semibold"><?= icon('message',16) ?> Batch WhatsApp Discussion Group Link <span class="text-gray-400 font-normal">(Students Q&amp;A)</span></label>
        <input name="whatsapp_group_link" value="<?= e($edit['whatsapp_group_link'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..." class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
        <p class="text-xs text-gray-400 mt-1">Khali = course ka discussion link use hoga.</p></div>
      <div class="flex gap-2">
        <button name="save_batch" value="1" class="text-white px-5 py-2 rounded font-semibold text-sm" style="background:#c74b25"><?= $edit ? 'Update' : 'Create Batch' ?></button>
        <?php if ($edit): ?><a href="batches.php<?= $filter_course ? '?course_id=' . $filter_course : '' ?>" class="px-4 py-2 text-sm underline text-gray-500">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <!-- List -->
  <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
    <h3 class="font-bold mb-4">Tamam Batches (<?= count($batches) ?>)</h3>
    <?php if (!$batches): ?><p class="text-sm text-gray-400">Abhi koi batch nahi bana. Pehla batch upar form se banayein.</p><?php endif; ?>
    <?php foreach ($batches as $b):
      $st8 = batch_status($b); $days = batch_days_left($b); ?>
    <div class="border rounded-lg p-4 mb-3 flex flex-wrap justify-between items-center gap-3">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <b><?= e($b['batch_name']) ?></b>
          <?= batch_badge_short($b) ?>
          <span class="text-xs text-gray-400"><?= (int)$b['students'] ?> students</span>
        </div>
        <div class="text-xs text-gray-500 mt-1">
          <?= icon('book-open',16) ?> <?= e($b['course_title']) ?> ·
          <?= $b['start_date'] ? date('d M Y', strtotime($b['start_date'])) : '—' ?> → <?= $b['end_date'] ? date('d M Y', strtotime($b['end_date'])) : '—' ?> ·
          <?= icon('clock',16) ?> <?= (int)$b['duration_months'] ?> months
          <?= $st8 === 'active' && $days !== null ? " · <b class='text-green-700'>$days din baqi</b>" : '' ?>
          <?= !empty($b['whatsapp_community_link']) ? ' · ' . icon('megaphone',16) . ' <span class="text-green-700 font-semibold">Community linked</span>' : '' ?>
          <?= !empty($b['whatsapp_group_link']) ? ' · ' . icon('message',16) . ' <span class="text-green-700 font-semibold">Group linked</span>' : '' ?>
        </div>
      </div>
      <div class="flex gap-3 items-center">
        <a href="batches.php?<?= $filter_course ? 'course_id=' . $filter_course . '&' : '' ?>edit=<?= $b['id'] ?>" class="underline text-blue-600 text-sm">Edit</a>
        <form method="post" action="batches.php<?= $filter_course ? '?course_id=' . $filter_course : '' ?>" class="inline" onsubmit="return confirm('Batch delete? Students ki enrollment se batch hat jayega (student delete nahi hoga).')">
          <?= csrf_field() ?>
          <input type="hidden" name="delete" value="<?= $b['id'] ?>">
          <button class="underline text-red-600 text-sm">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
