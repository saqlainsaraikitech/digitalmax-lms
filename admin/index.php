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

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
  <?php foreach ([
      ['Students', $stats['students'], 'users', 'linear-gradient(135deg,#e8823f,#c74b25)'],
      ['Courses', $stats['courses'], 'book-open', 'linear-gradient(135deg,#d97706,#b45309)'],
      ['Enrollments', $stats['enroll'], 'graduation-cap', 'linear-gradient(135deg,#a16207,#7c4a03)'],
      ['Pending payments', $stats['pay_pend'], 'credit-card', 'linear-gradient(135deg,#b45309,#1d3448)'],
      ['Unchecked assignments', $stats['sub_pend'], 'clipboard-list', 'linear-gradient(135deg,#e8a13f,#c74b25)'],
  ] as [$l, $v, $icn, $bg]): ?>
    <div class="stat-card">
      <div class="stat-ico" style="background:<?= $bg ?>"><?= icon($icn, 24) ?></div>
      <div><div class="stat-num"><?= (int)$v ?></div><div class="stat-lbl"><?= e($l) ?></div></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid md:grid-cols-2 gap-5">
  <div class="dm-card dm-card-pad">
    <div class="dm-card-title"><?= icon('pin',16) ?> Mere tasks <span class="text-gray-400 font-semibold text-xs">(pending)</span></div>
    <?php if (!$my_tasks): ?><p class="text-gray-400 text-sm">Koi pending task nahi. <?= icon('thumbs-up',16) ?></p><?php endif; ?>
    <?php foreach ($my_tasks as $t): ?>
      <div class="dm-row">
        <span><?= e($t['title']) ?> <span class="text-gray-400 text-xs">· due <?= e($t['due_date'] ?: '-') ?></span></span>
        <?= status_badge($t['status']) ?>
      </div>
    <?php endforeach; ?>
    <?php if (can('p_tasks')): ?><a href="tasks.php" class="dm-card-link mt-3 inline-block">Sab tasks dekhein →</a><?php endif; ?>
  </div>

  <div class="dm-card dm-card-pad">
    <div class="dm-card-title"><?= icon('wallet',16) ?> Recent payments</div>
    <?php if (!$recent_pay): ?><p class="text-gray-400 text-sm">Abhi koi payment nahi.</p><?php endif; ?>
    <?php foreach ($recent_pay as $p): ?>
      <div class="dm-row">
        <span><?= e($p['name']) ?> · <b><?= money($p['amount']) ?></b></span>
        <?= status_badge($p['status']) ?>
      </div>
    <?php endforeach; ?>
    <?php if (can('p_payments')): ?><a href="payments.php" class="dm-card-link mt-3 inline-block">Sab payments →</a><?php endif; ?>
  </div>
</div>

<div class="dm-card dm-card-pad mt-5">
  <div class="dm-card-title"><?= icon('bar-chart', 18) ?> Course-wise enrollments <span class="text-gray-400 font-semibold text-xs">(kis course me kitne students)</span></div>
  <?php if (!$course_stats): ?>
    <p class="text-gray-400 text-sm">Abhi koi course nahi.</p>
  <?php else: ?>
    <div class="space-y-3 mt-2">
    <?php foreach ($course_stats as $cs):
      $pct = $max_students > 0 ? (int)round($cs['students'] / $max_students * 100) : 0; ?>
      <div>
        <div class="flex items-center justify-between gap-3 text-sm mb-1">
          <span class="font-bold truncate"><?= e($cs['title']) ?>
            <?php if (($cs['status'] ?? '') !== 'published'): ?><span class="text-xs font-semibold text-gray-400">(<?= e($cs['status']) ?>)</span><?php endif; ?>
          </span>
          <span class="whitespace-nowrap text-xs font-bold" style="color:#294461"><?= (int)$cs['students'] ?> students<?php if ($cs['revenue'] > 0): ?> · <span style="color:#15803d"><?= money($cs['revenue']) ?></span><?php endif; ?></span>
        </div>
        <div class="h-2.5 rounded-full" style="background:#e2e8f0"><div class="h-2.5 rounded-full" style="width:<?= $pct ?>%; background:linear-gradient(90deg,#c74b25,#e8823f)"></div></div>
      </div>
    <?php endforeach; ?>
    </div>
    <?php if (can('p_courses')): ?><a href="courses.php" class="dm-card-link mt-4 inline-block">Sab courses manage karein →</a><?php endif; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
