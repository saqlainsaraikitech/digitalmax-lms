<?php
// Student: course curriculum view
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$course_id = (int)($_GET['course_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND status = "published"');
$st->execute([$course_id]);
$course = $st->fetch();
if (!$course) { flash('Course nahi mila.', 'error'); redirect('index.php'); }

$st = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?');
$st->execute([$u['id'], $course_id]);
$enrolled = (bool)$st->fetch();

// v21: student ka batch (null = purani enrollment → sab visible)
$my_batch = $enrolled ? student_batch($u['id'], $course_id) : null;
$my_batch_id = $my_batch ? (int)$my_batch['id'] : null;
$visible_ids = batch_visible_lecture_ids($course_id, $my_batch_id);
$visible_map = array_fill_keys($visible_ids, true);

$sections = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order, id');
$sections->execute([$course_id]); $sections = $sections->fetchAll();
$lecs = $pdo->prepare('SELECT l.*, (SELECT 1 FROM lecture_progress lp WHERE lp.lecture_id = l.id AND lp.student_id = ?) AS done FROM lectures l WHERE l.course_id = ? ORDER BY l.sort_order, l.id');
$lecs->execute([$u['id'], $course_id]);
$by_section = [];
foreach ($lecs->fetchAll() as $l) { $by_section[$l['section_id'] ?? 0][] = $l; }

// first unfinished lecture = continue point (sirf visible lectures me)
$next_id = null;
foreach ($by_section as $list) foreach ($list as $l) {
    if ($enrolled && !$l['done'] && $next_id === null && isset($visible_map[$l['id']])) $next_id = $l['id'];
}

$page_title = $course['title'];
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-4"><a href="index.php" class="text-sm underline text-gray-500">← My courses</a></div>

<div class="dm-card dm-card-pad mb-6">
  <div class="flex gap-5 flex-wrap">
    <?php if ($course['thumbnail']): ?><img src="../<?= e($course['thumbnail']) ?>" class="dm-course-thumb" alt=""><?php endif; ?>
    <div class="flex-1 min-w-60">
      <h2 class="dm-page-h"><?= e($course['title']) ?></h2>
      <?php if ($enrolled && $my_batch): ?><div class="mt-2"><?= batch_badge($my_batch) ?></div><?php endif; ?>
      <p class="dm-muted-p mt-2"><?= nl2br(e(mb_substr($course['description'] ?? '', 0, 300))) ?></p>
      <?php if ($enrolled):
        $pct = course_progress($u['id'], $course_id); ?>
        <div class="dm-progress-row"><?= watch_ring($pct, 20) ?><span class="dm-progress max-w-md"><div style="width:<?= $pct ?>%"></div></span><span class="num"><?= $pct ?>% complete</span></div>
        <?php if ($pct === 100): ?>
          <a href="certificate.php?course_id=<?= $course_id ?>" class="dm-btn mt-4"><?= icon('graduation-cap',16) ?> Download certificate</a>
        <?php elseif ($next_id): ?>
          <a href="lecture.php?course_id=<?= $course_id ?>&lecture_id=<?= $next_id ?>" class="dm-btn mt-4"><?= icon('play',16) ?> Continue</a>
        <?php endif; ?>
      <?php else: ?>
        <div class="pay-warn mt-4" style="margin-bottom:0"><?= icon('lock',18) ?><span>Aap is course me enroll nahi hain. Sirf <b>free preview</b> lectures dekh sakte hain.</span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
// v29: section header me "X of Y lectures completed" + status icons
$next_marked = false; // sirf pehle incomplete lecture par "next" icon
$show_section = function($title, $list) use ($course_id, $enrolled, $visible_map, $next_id, &$next_marked) {
    $tot = count($list);
    $dn = 0; foreach ($list as $l) if ($l['done']) $dn++;
    $spct = $tot ? (int)round($dn / $tot * 100) : 0;
    echo '<div class="dm-card mb-4 overflow-hidden"><div class="dm-sec-head">'
       . '<span class="t">' . e($title) . '</span>'
       . '<div class="sec-progress"><div class="bar"><i style="width:' . $spct . '%"></i></div><span>' . $dn . ' of ' . $tot . ' lectures completed</span></div>'
       . '</div>';
    foreach ($list as $l) {
        $locked = !$enrolled && !$l['is_preview'];
        $batch_locked = $enrolled && !isset($visible_map[$l['id']]);
        // status icon
        if ($l['done']) $sticon = '<span class="lec-status done">' . icon('check', 16) . '</span>';
        elseif ($enrolled && !$batch_locked && !$next_marked && (int)$l['id'] === (int)$next_id) { $sticon = '<span class="lec-status next">' . icon('play', 15) . '</span>'; $next_marked = true; }
        elseif ($enrolled && !$batch_locked) $sticon = '<span class="lec-status todo">' . icon('play', 13) . '</span>';
        else $sticon = '<span class="lec-status todo">' . icon('lock', 13) . '</span>';
        echo '<div class="dm-lec-row">';
        echo '<span class="flex items-center gap-3">' . $sticon . '<span>' . e($l['title']);
        if (is_new_lecture($l)) echo new_badge();
        if (!empty($l['duration'])) echo ' <span class="meta">· ' . e($l['duration']) . '</span>';
        if ($l['is_preview']) echo ' <span class="dm-tag">Free preview</span>';
        echo '</span></span>';
        if ($locked) echo '<span class="meta">Locked</span>';
        elseif ($batch_locked) echo '<span class="meta">Naye batch ke liye · re-enroll to access</span>';
        else echo '<a href="lecture.php?course_id=' . $course_id . '&lecture_id=' . $l['id'] . '" class="cr-go">' . ($l['done'] ? 'Rewatch' : 'Watch') . '</a>';
        echo '</div>';
    }
    echo '</div>';
};
foreach ($sections as $s) $show_section($s['title'], $by_section[$s['id']] ?? []);
if (!empty($by_section[0])) $show_section('Other lectures', $by_section[0]);
?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
