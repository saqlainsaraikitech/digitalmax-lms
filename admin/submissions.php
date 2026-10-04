<?php
// Submissions check + grading — v55: tabs (Pending / Graded), compact list, inline grading
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_assignments');
$pdo = db();

$assignment_id = (int)($_GET['assignment_id'] ?? 0);
$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'graded'])) $tab = 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade_id'])) {
    $gid = (int)$_POST['grade_id'];
    $pdo->prepare('UPDATE submissions SET marks = ?, feedback = ?, status = "graded" WHERE id = ?')
        ->execute([$_POST['marks'] !== '' ? (int)$_POST['marks'] : null, $_POST['feedback'] ?? '', $gid]);
    flash('Grading save ho gayi — submission "Graded" tab me chali gayi.');
    redirect('submissions.php?tab=pending&assignment_id=' . $assignment_id);
}

$assignments = $pdo->query('SELECT a.id, a.title, c.title AS course_title FROM assignments a JOIN courses c ON c.id=a.course_id ORDER BY a.created_at DESC')->fetchAll();

// tab counts (assignment filter ke sath)
$count_sql = 'SELECT COUNT(*) FROM submissions s WHERE 1=1';
$count_params = [];
if ($assignment_id) { $count_sql .= ' AND s.assignment_id = ?'; $count_params[] = $assignment_id; }
$st = $pdo->prepare($count_sql . ' AND s.status = "submitted"'); $st->execute($count_params);
$cnt_pending = (int)$st->fetchColumn();
$st = $pdo->prepare($count_sql . ' AND s.status = "graded"'); $st->execute($count_params);
$cnt_graded = (int)$st->fetchColumn();

$sql = 'SELECT s.*, u.name AS student_name, a.title AS asg_title, a.max_marks, c.title AS course_title
        FROM submissions s JOIN users u ON u.id=s.student_id
        JOIN assignments a ON a.id=s.assignment_id JOIN courses c ON c.id=a.course_id
        WHERE s.status = ?';
$params = [$tab === 'pending' ? 'submitted' : 'graded'];
if ($assignment_id) { $sql .= ' AND s.assignment_id = ?'; $params[] = $assignment_id; }
$sql .= ' ORDER BY s.submitted_at DESC';
$st = $pdo->prepare($sql); $st->execute($params);
$rows = $st->fetchAll();

$page_title = 'Submissions';
require __DIR__ . '/../inc/header.php';
?>
<style>
.sub-tab { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; border-radius:999px; font-weight:700; font-size:14px; color:#5b6b7a; background:#fff; border:1.5px solid #e2e8f0; text-decoration:none; transition:all .15s; }
.sub-tab:hover { border-color:#c74b25; color:#c74b25; }
.sub-tab.active { background:#c74b25; border-color:#c74b25; color:#fff; box-shadow:0 4px 12px rgba(199,75,37,.3); }
.sub-tab .cnt { background:rgba(0,0,0,.08); border-radius:999px; padding:1px 9px; font-size:12px; font-weight:800; }
.sub-tab.active .cnt { background:rgba(255,255,255,.25); }
.sub-row { transition:box-shadow .15s; }
.sub-row:hover { box-shadow:0 4px 16px rgba(29,52,72,.1); }
.grade-panel { display:none; }
.grade-panel.open { display:block; animation:subfade .2s ease; }
@keyframes subfade { from { opacity:0; transform:translateY(-4px);} to { opacity:1; transform:none;} }
.marks-pill { display:inline-flex; align-items:center; gap:6px; font-weight:800; font-size:13px; padding:4px 12px; border-radius:999px; }
</style>

<div class="flex gap-3 flex-wrap items-center mb-4">
  <a href="?tab=pending&assignment_id=<?= $assignment_id ?>" class="sub-tab <?= $tab === 'pending' ? 'active' : '' ?>">
    <?= icon('clock', 15) ?> Pending Review <span class="cnt"><?= $cnt_pending ?></span>
  </a>
  <a href="?tab=graded&assignment_id=<?= $assignment_id ?>" class="sub-tab <?= $tab === 'graded' ? 'active' : '' ?>">
    <?= icon('check-circle', 15) ?> Graded <span class="cnt"><?= $cnt_graded ?></span>
  </a>
</div>

<div class="flex gap-3 flex-wrap items-center mb-4">
  <form method="get" class="text-sm">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <select name="assignment_id" onchange="this.form.submit()" class="border rounded-lg px-3 py-2">
      <option value="0">Sab assignments</option>
      <?php foreach ($assignments as $x): ?>
        <option value="<?= $x['id'] ?>" <?= $assignment_id === (int)$x['id'] ? 'selected' : '' ?>><?= e($x['course_title']) ?> — <?= e($x['title']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div class="relative flex-1 min-w-48 max-w-sm">
    <input id="subSearch" oninput="filterSubs()" placeholder="Student ka naam likhein…" class="w-full border rounded-lg pl-9 pr-3 py-2 text-sm">
    <span class="absolute left-3 top-2.5 text-gray-400"><?= icon('search', 15) ?></span>
  </div>
</div>

<div id="subList">
<?php foreach ($rows as $s): ?>
<div class="sub-row bg-white rounded-xl shadow p-4 mb-3" data-name="<?= e(mb_strtolower($s['student_name'])) ?>">
  <div class="flex justify-between items-center gap-3 flex-wrap">
    <div class="flex items-center gap-3 min-w-0">
      <span class="w-10 h-10 rounded-full flex items-center justify-center font-extrabold text-white flex-shrink-0" style="background:#294461"><?= e(mb_strtoupper(mb_substr($s['student_name'], 0, 1))) ?></span>
      <div class="min-w-0">
        <div class="font-bold truncate"><?= e($s['student_name']) ?></div>
        <div class="text-xs text-gray-400 truncate"><?= e($s['asg_title']) ?> · <?= e(date('d M, h:i A', strtotime($s['submitted_at']))) ?></div>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <?php if ($tab === 'graded'): ?>
        <?php $mpct = $s['max_marks'] > 0 ? round(($s['marks'] ?? 0) / $s['max_marks'] * 100) : 0;
        $mcol = $mpct >= 80 ? '#16a34a' : ($mpct >= 50 ? '#c74b25' : '#dc2626'); ?>
        <span class="marks-pill" style="background:<?= $mcol ?>18; color:<?= $mcol ?>; border:1px solid <?= $mcol ?>40"><?= (int)($s['marks'] ?? 0) ?>/<?= (int)$s['max_marks'] ?></span>
      <?php else: ?>
        <span class="marks-pill" style="background:#fef3c7; color:#b45309; border:1px solid #fcd34d"><?= icon('clock', 12) ?> Pending</span>
      <?php endif; ?>
      <button type="button" onclick="toggleGrade(<?= $s['id'] ?>)" class="text-sm font-bold text-white px-4 py-2 rounded-lg" style="background:#c74b25">
        <?= $tab === 'pending' ? 'Check & Grade' : 'Edit Grade' ?>
      </button>
    </div>
  </div>
  <div id="gp-<?= $s['id'] ?>" class="grade-panel mt-3 pt-3" style="border-top:1px dashed #e2e8f0">
    <?php if ($s['answer_text']): ?><div class="text-sm bg-gray-50 p-3 rounded-lg mb-2" style="max-height:180px; overflow-y:auto"><?= nl2br(e($s['answer_text'])) ?></div><?php endif; ?>
    <?php if ($s['file_path']): ?><div class="mb-2 text-sm"><a href="../<?= e($s['file_path']) ?>" class="underline font-semibold" style="color:#294461" target="_blank"><?= icon('paperclip', 14) ?> Submitted file dekhein</a></div><?php endif; ?>
    <form method="post" class="grid md:grid-cols-4 gap-3 items-end">
    <?= csrf_field() ?>
      <input type="hidden" name="grade_id" value="<?= $s['id'] ?>">
      <div><label class="text-xs font-bold text-gray-500">Marks (max <?= (int)$s['max_marks'] ?>)</label>
        <input name="marks" type="number" min="0" max="<?= (int)$s['max_marks'] ?>" value="<?= e($s['marks'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 mt-1" required></div>
      <div class="md:col-span-2"><label class="text-xs font-bold text-gray-500">Feedback</label>
        <input name="feedback" value="<?= e($s['feedback'] ?? '') ?>" placeholder="Shabash! …" class="w-full border rounded-lg px-3 py-2 mt-1"></div>
      <button class="text-white px-4 py-2 rounded-lg text-sm font-bold" style="background:#16a34a"><?= icon('check', 14) ?> Grade Save</button>
    </form>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="bg-white rounded-xl shadow p-8 text-center text-gray-400"><?= $tab === 'pending' ? 'Koi pending submission nahi — sab check ho gaya!' : 'Abhi koi graded submission nahi.' ?></div><?php endif; ?>

<script>
function toggleGrade(id){
  var el = document.getElementById('gp-' + id);
  el.classList.toggle('open');
  if (el.classList.contains('open')) { var inp = el.querySelector('input[name="marks"]'); if (inp) inp.focus(); }
}
function filterSubs(){
  var q = document.getElementById('subSearch').value.trim().toLowerCase();
  document.querySelectorAll('#subList .sub-row').forEach(function(r){
    r.style.display = (!q || r.dataset.name.indexOf(q) !== -1) ? '' : 'none';
  });
}
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
