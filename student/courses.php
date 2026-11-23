<?php
// Student: course catalog — sab published courses dikhenge
// Enrolled = full access | baki = details + enroll (payment) ka option
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$courses = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM lectures WHERE course_id = c.id) AS total_lectures FROM courses c WHERE c.status = "published" ORDER BY c.created_at DESC')->fetchAll();
$st = $pdo->prepare('SELECT course_id FROM enrollments WHERE student_id = ?');
$st->execute([$u['id']]);
$enrolled = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

$page_title = 'Course Catalog';
require __DIR__ . '/../inc/header.php';
?>

<?php if (!$courses): ?>
  <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">Abhi koi course published nahi hai.</div>
<?php endif; ?>

<div class="grid md:grid-cols-3 gap-5">
<?php foreach ($courses as $c):
  $is_enrolled = in_array((int)$c['id'], $enrolled, true);
  $pct = $is_enrolled ? course_progress($u['id'], $c['id']) : 0;
?>
  <div class="course-card">
    <?php if ($c['thumbnail']): ?><div class="course-thumb"><img src="../<?= e($c['thumbnail']) ?>" alt=""></div><?php endif; ?>
    <div class="course-body">
      <div class="flex justify-between items-start gap-2">
        <span class="course-title"><?= e($c['title']) ?></span>
        <?= $is_enrolled ? status_badge('paid') : '' ?>
      </div>
      <?php if ($is_enrolled): $cb2 = student_batch($u['id'], $c['id']); ?>
        <?php if ($cb2): ?><div class="mt-2"><?= batch_badge($cb2) ?></div><?php endif; ?>
      <?php endif; ?>
      <p class="dm-muted-p mt-2 flex-1"><?= e(mb_substr($c['description'] ?? '', 0, 140)) ?><?= mb_strlen($c['description'] ?? '') > 140 ? '…' : '' ?></p>
      <?php $dur = course_duration($c['id']); ?>
      <div class="course-meta"><?= (int)$c['total_lectures'] ?> lectures<?= $dur ? ' · ' . e($dur) : '' ?><?= $is_enrolled ? '' : ' · <b style="color:var(--dm-ink)">' . money($c['price']) . '</b>' ?></div>
      <?php if ($is_enrolled): ?>
        <div class="dm-progress-row"><?= watch_ring($pct, 20) ?><span class="dm-progress"><div style="width:<?= $pct ?>%"></div></span><span class="num"><?= $pct ?>%</span></div>
        <a href="course.php?course_id=<?= $c['id'] ?>" class="dm-btn-ghost mt-4">Continue learning</a>
      <?php else: ?>
        <div class="mt-3 flex gap-2">
          <a href="course.php?course_id=<?= $c['id'] ?>" class="dm-btn-ghost flex-1">Details</a>
          <a href="payments.php?course_id=<?= $c['id'] ?>" class="dm-btn flex-1">Enroll</a>
        </div>
        <p class="course-meta">Payment verify hone ke baad admin aapko enroll kar dega.</p>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
