<?php
// Admin dashboard
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$pdo = db();
$u = current_user();

$stats = [];
$stats['students'] = $pdo->query('SELECT COUNT(*) FROM users WHERE role="student"')->fetchColumn();
$stats['courses']  = $pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn();
$stats['enroll']    = $pdo->query('SELECT COUNT(*) FROM enrollments')->fetchColumn();
$stats['pay_pend']  = $pdo->query('SELECT COUNT(*) FROM payments WHERE status="pending"')->fetchColumn();
$stats['sub_pend']  = $pdo->query('SELECT COUNT(*) FROM submissions WHERE status="submitted"')->fetchColumn();

$my_tasks = $pdo->prepare('SELECT * FROM tasks WHERE assigned_to = ? AND status != "done" ORDER BY due_date ASC');
$my_tasks->execute([$u['id']]);
$my_tasks = $my_tasks->fetchAll();

$recent_pay = $pdo->query('SELECT p.*, u.name FROM payments p JOIN users u ON u.id = p.student_id ORDER BY p.created_at DESC LIMIT 5')->fetchAll();

// v31: course-wise enrollment stats
$course_stats = $pdo->query("SELECT c.id, c.title, c.status, COUNT(DISTINCT e.student_id) AS students,
    COALESCE((SELECT SUM(amount) FROM payments WHERE course_id = c.id AND status = 'approved'), 0) AS revenue
  FROM courses c LEFT JOIN enrollments e ON e.course_id = c.id
  GROUP BY c.id ORDER BY students DESC, c.title ASC")->fetchAll();
$max_students = 0;
foreach ($course_stats as $cs) $max_students = max($max_students, (int)$cs['students']);
?>
<?php $page_title = 'Dashboard'; require __DIR__ . '/../inc/header.php'; ?>

<div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
  <?php foreach ([
      ['Students', $stats['students'], false],
      ['Courses', $stats['courses'], false],
      ['Enrollments', $stats['enroll'], false],
      ['Pending payments', $stats['pay_pend'], true],
      ['Unchecked assignments', $stats['sub_pend'], false],
  ] as [$l, $v, $attn]): ?>
    <div class="stat-card<?= ($attn && (int)$v > 0) ? ' is-attention' : '' ?>">
      <div><div class="stat-lbl"><?= e($l) ?></div><div class="stat-num"><?= number_format((int)$v) ?></div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid md:grid-cols-2 gap-4">
  <div class="dm-card dm-card-pad">
    <div class="dm-card-title">Mere tasks <span class="text-gray-400 text-xs">pending</span></div>
    <?php if (!$my_tasks): ?><p class="text-gray-400 text-sm">Koi pending task nahi</p><?php endif; ?>
    <?php foreach ($my_tasks as $t): ?>
      <div class="dm-row">
        <span><?= e($t['title']) ?> <span class="meta">· due <?= e($t['due_date'] ?: '–') ?></span></span>
        <?= status_badge($t['status']) ?>
      </div>
    <?php endforeach; ?>
    <?php if (can('p_tasks')): ?><a href="tasks.php" class="dm-card-link mt-3 inline-block">Sab tasks dekhein</a><?php endif; ?>
  </div>

  <div class="dm-card dm-card-pad">
    <div class="dm-card-title">Recent payments</div>
    <?php if (!$recent_pay): ?><p class="text-gray-400 text-sm">Abhi koi payment nahi</p><?php endif; ?>
    <?php foreach ($recent_pay as $p): ?>
      <div class="dm-row">
        <span><?= e($p['name']) ?> · <b><?= money($p['amount']) ?></b></span>
        <?= status_badge($p['status']) ?>
      </div>
    <?php endforeach; ?>
    <?php if (can('p_payments')): ?><a href="payments.php" class="dm-card-link mt-3 inline-block">Sab payments</a><?php endif; ?>
  </div>
</div>

<div class="dm-card dm-card-pad mt-4">
  <div class="dm-card-title">Course-wise enrollments <span class="text-gray-400 text-xs">kis course me kitne students</span></div>
  <?php if (!$course_stats): ?>
    <p class="text-gray-400 text-sm">Abhi koi course nahi</p>
  <?php else: ?>
    <div class="dm-rank">
    <?php $rk = 0; foreach ($course_stats as $cs): $rk++;
      $pct = $max_students > 0 ? (int)round($cs['students'] / $max_students * 100) : 0; ?>
      <div>
        <div class="dm-rank-head">
          <span class="t"><?= e($cs['title']) ?><?php if (($cs['status'] ?? '') !== 'published'): ?> <i><?= e($cs['status']) ?></i><?php endif; ?></span>
          <span class="v"><b><?= number_format((int)$cs['students']) ?></b> students<?php if ($cs['revenue'] > 0): ?> · <?= money($cs['revenue']) ?> approved<?php endif; ?></span>
        </div>
        <div class="dm-rank-bar<?= ($rk === 1 && $pct > 0) ? ' lead' : '' ?>"><span style="width:<?= $pct ?>%"></span></div>
      </div>
    <?php endforeach; ?>
    </div>
    <?php if (can('p_courses')): ?><a href="courses.php" class="dm-card-link mt-4 inline-block">Sab courses manage karein</a><?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
