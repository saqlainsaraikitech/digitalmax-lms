<?php
// Student: Notice Board / Announcements
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$rows = student_visible_announcements($u['id']);

// sab ko read mark karo (badge clear)
if ($rows) {
    $ins = $pdo->prepare('INSERT IGNORE INTO announcement_reads (announcement_id, student_id) VALUES (?, ?)');
    foreach ($rows as $r) $ins->execute([(int)$r['id'], $u['id']]);
}

$page_title = 'Notice Board';
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-6">
  <h2 class="dm-page-h">Notice board</h2>
  <p class="text-sm text-gray-500 mt-1">Tamam zaroori elanat — classes, updates aur links.</p>
</div>

<?php if (!$rows): ?>
  <div class="bg-white rounded-lg shadow p-10 text-center text-gray-400 text-sm"><?= icon('inbox', 28) ?><br>Abhi koi announcement nahi hai.</div>
<?php endif; ?>

<div class="max-w-3xl space-y-4">
<?php foreach ($rows as $r):
  $is_urgent = $r['priority'] === 'urgent';
  $is_pinned = $r['priority'] === 'pinned';
?>
  <div class="bg-white rounded-xl shadow overflow-hidden <?= $is_urgent ? 'border-l-4 border-red-500' : ($is_pinned ? 'border-l-4 border-orange-400' : '') ?>">
    <div class="p-5">
      <div class="flex justify-between items-start gap-3 flex-wrap">
        <b class="text-lg"><?= e($r['title']) ?></b>
        <?= ann_priority_badge($r['priority']) ?>
      </div>
      <div class="flex gap-2 items-center mt-2 text-xs text-gray-400 flex-wrap">
        <span><?= icon('calendar',16) ?> <?= date('d M Y · h:i A', strtotime($r['created_at'])) ?></span>
        <span class="dm-tag dm-tag-n"><?= ann_target_label($r) ?></span>
      </div>
      <div class="text-sm text-gray-700 mt-3 leading-relaxed"><?= $r['message'] ?></div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
