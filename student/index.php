<?php
// Student dashboard — my courses
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$courses = $pdo->prepare('SELECT c.* FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_id = ? AND c.status = "published" ORDER BY e.enrolled_at DESC');
$courses->execute([$u['id']]);
$courses = $courses->fetchAll();

$pending_tasks = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE student_id = ? AND status = "pending"');
$pending_tasks->execute([$u['id']]);

$page_title = 'My Courses';

// v22: urgent announcement banner (dismissible)
if (isset($_GET['dismiss_ann'])) {
    $_SESSION['dismissed_ann'][(int)$_GET['dismiss_ann']] = 1;
    redirect('index.php');
}
$urgent_ann = null;
$dismissed = $_SESSION['dismissed_ann'] ?? [];
foreach (student_visible_announcements($u['id']) as $a) {
    if ($a['priority'] === 'urgent' && empty($dismissed[$a['id']])) { $urgent_ann = $a; break; }
}

require __DIR__ . '/../inc/header.php'; ?>

<?php
// Hero stats
$hero_avg = 0;
if ($courses) {
    $sum = 0;
    foreach ($courses as $hc) $sum += course_progress($u['id'], $hc['id']);
    $hero_avg = (int)round($sum / count($courses));
}
$pend_pay = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE student_id = ? AND status = "pending"');
$pend_pay->execute([$u['id']]);
$pend_pay = (int)$pend_pay->fetchColumn();
$first = trim(explode(' ', $u['name'])[0]);
?>
<?php
// v29: Resume Learning — last watched lecture (database se, har device par)
$resume = null;
try {
    $st = $pdo->prepare("SELECT l.id AS lid, l.title AS lt, l.course_id, c.title AS ct, vp.seconds
                         FROM video_progress vp
                         JOIN lectures l ON l.id = vp.lecture_id
                         JOIN courses c ON c.id = l.course_id
                         JOIN enrollments e ON e.course_id = l.course_id AND e.student_id = vp.student_id
                         WHERE vp.student_id = ? AND c.status = 'published'
                         ORDER BY vp.updated_at DESC LIMIT 1");
    $st->execute([$u['id']]);
    $resume = $st->fetch() ?: null;
    if (!$resume) {
        // koi progress nahi → pehla incomplete lecture
        $st = $pdo->prepare("SELECT l.id AS lid, l.title AS lt, l.course_id, c.title AS ct, 0 AS seconds
                             FROM enrollments e JOIN courses c ON c.id = e.course_id
                             JOIN lectures l ON l.course_id = c.id
                             LEFT JOIN lecture_progress lp ON lp.lecture_id = l.id AND lp.student_id = e.student_id
                             WHERE e.student_id = ? AND c.status = 'published' AND lp.id IS NULL
                             ORDER BY e.enrolled_at DESC, l.sort_order, l.id LIMIT 1");
        $st->execute([$u['id']]);
        $resume = $st->fetch() ?: null;
    }
} catch (Exception $ex) {}
?>

<header class="std-masthead">
  <div class="std-date"><?= date('l, j F Y') ?></div>
  <h1 class="std-greet">Assalam-o-Alaikum, <?= e($first) ?>.</h1>
  <div class="std-rule"></div>
  <p class="std-sub"><b><?= count($courses) ?></b> enrolled<span class="dot">·</span><b><?= $hero_avg ?>%</b> average progress<?php if ($pend_pay): ?><span class="dot">·</span><b><?= $pend_pay ?></b> payment pending<?php endif; ?></p>
</header>

<?php if ($resume):
  $rs = (int)($resume['seconds'] ?? 0);
  $rlabel = $rs > 0 ? ' · ' . gmdate($rs >= 3600 ? 'H:i:s' : 'i:s', $rs) . ' se' : ''; ?>
<a class="resume-cinema" href="lecture.php?course_id=<?= (int)$resume['course_id'] ?>&lecture_id=<?= (int)$resume['lid'] ?>">
  <div class="rc-kicker">Continue learning</div>
  <div class="rc-title"><?= e($resume['lt']) ?></div>
  <div class="rc-meta"><?= e($resume['ct']) ?><?= e($rlabel) ?></div>
  <span class="rc-cta"><span class="rc-playbtn"><?= icon('play', 20) ?></span> Resume lecture</span>
</a>
<?php endif; ?>

<?php if ($urgent_ann):
  $plain = trim(strip_tags($urgent_ann['message']));
  $preview = mb_substr($plain, 0, 90); ?>
  <div class="rounded-xl shadow mb-5 p-4 flex items-center gap-3 flex-wrap" style="background:linear-gradient(90deg,#fef2f2,#fdf6ec); border:1px solid #fca5a5">
    <span class="text-2xl"><?= icon('bell',16) ?></span>
    <div class="flex-1 min-w-52">
      <b class="text-red-800">Important: <?= e($urgent_ann['title']) ?></b>
      <span class="text-sm text-gray-600"> — <?= e($preview) ?><?= mb_strlen($plain) > 90 ? '…' : '' ?></span>
    </div>
    <a href="announcements.php" class="text-sm font-bold underline" style="color:#c74b25">Read More →</a>
    <a href="index.php?dismiss_ann=<?= (int)$urgent_ann['id'] ?>" class="text-gray-400 hover:text-gray-600 text-lg leading-none" title="Dismiss"><?= icon('x',16) ?></a>
  </div>
<?php endif; ?>

<?php
// v24: Official WhatsApp access — sirf enrolled (approved) students, kam az kam ek link ho
foreach ($courses as $vc) {
    $vb = student_batch($u['id'], $vc['id']);
    $vbid = $vb ? (int)$vb['id'] : null;
    $vlinks = community_links_for($vc['id'], $vbid);
    if (!$vlinks['community'] && !$vlinks['group']) continue; // koi link nahi → block hide
    $vended = $vb && batch_status($vb) === 'completed';
    echo vip_community_card($vlinks, $vc['title'], $vb['batch_name'] ?? null, $vended);
}
?>
<?= wa_rules_modal() ?>

<?php if (!$courses): ?>
  <div class="dm-card"><div class="std-empty">
    <h3>Abhi koi course nahi.</h3>
    <p>Course Catalog me sab courses dekhein — pasand aaye to payment bhej kar enroll ho jayein.</p>
    <a href="courses.php" class="dm-btn">Course Catalog dekhein <?= icon('arrow-right', 16) ?></a>
  </div></div>
<?php endif; ?>

<?php if ($courses): ?>
<div class="std-sec">
  <h2>Mere courses</h2>
  <span class="std-count"><?= count($courses) ?> enrolled</span>
</div>
<div class="course-rows">
<?php $ri = 0; foreach ($courses as $c):
  $ri++;
  $pct = course_progress($u['id'], $c['id']);
  $total = $pdo->prepare('SELECT COUNT(*) FROM lectures WHERE course_id = ?');
  $total->execute([$c['id']]); $total = (int)$total->fetchColumn();
  $card_batch = student_batch($u['id'], $c['id']);
?>
  <div class="course-row">
    <span class="cr-idx"><?= sprintf('%02d', $ri) ?></span>
    <?php if ($c['thumbnail']): ?>
      <img class="cr-thumb" src="../<?= e($c['thumbnail']) ?>" alt="" loading="lazy">
    <?php endif; ?>
    <div class="cr-main">
      <div class="cr-title"><a href="course.php?course_id=<?= $c['id'] ?>"><?= e($c['title']) ?></a></div>
      <div class="cr-meta">
        <?php if ($card_batch): ?><?= batch_badge($card_batch) ?><?php endif; ?>
        <span><?= $total ?> lectures</span>
        <?php if ($pct === 100): ?>
          <a class="cr-cert" href="certificate.php?course_id=<?= $c['id'] ?>"><?= icon('graduation-cap', 14) ?> Certificate</a>
        <?php endif; ?>
      </div>
      <div class="cr-bar"><span style="width:<?= $pct ?>%"></span></div>
    </div>
    <div class="cr-side">
      <div class="cr-pct"><?= $pct ?>%</div>
      <a class="cr-go" href="course.php?course_id=<?= $c['id'] ?>"><?= $pct > 0 ? 'Continue' : 'Start' ?> <?= icon('arrow-right', 14) ?></a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
