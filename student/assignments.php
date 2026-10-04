<?php
// Student: assignments + submit
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $aid = (int)$_POST['assignment_id'];
    // verify assignment belongs to an enrolled course
    $st = $pdo->prepare('SELECT a.* FROM assignments a JOIN enrollments e ON e.course_id = a.course_id WHERE a.id = ? AND e.student_id = ?');
    $st->execute([$aid, $u['id']]);
    if (!$st->fetch()) { flash('Invalid assignment.', 'error'); redirect('assignments.php'); }
    try {
        $file = upload_file('sub_file', 'submissions', ['pdf', 'doc', 'docx', 'zip', 'jpg', 'jpeg', 'png']);
        $text = trim($_POST['answer_text'] ?? '');
        if (!$file && $text === '') throw new Exception('File ya answer text — kuch to bhejein.');
        // resubmit = update
        $st = $pdo->prepare('SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?');
        $st->execute([$aid, $u['id']]);
        if ($old = $st->fetch()) {
            $pdo->prepare('UPDATE submissions SET file_path = COALESCE(?, file_path), answer_text = ?, status = "submitted", marks = NULL, submitted_at = NOW() WHERE id = ?')
                ->execute([$file, $text, $old['id']]);
        } else {
            $pdo->prepare('INSERT INTO submissions (assignment_id, student_id, file_path, answer_text) VALUES (?,?,?,?)')
                ->execute([$aid, $u['id'], $file, $text]);
        }
        flash('Assignment submit ho gaya! ');
    } catch (Exception $ex) { flash($ex->getMessage(), 'error'); }
    redirect('assignments.php');
}

$rows = $pdo->prepare('SELECT a.*, c.title AS course_title,
    (SELECT s.status FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ? ORDER BY s.submitted_at DESC LIMIT 1) AS my_status,
    (SELECT s.marks FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ? ORDER BY s.submitted_at DESC LIMIT 1) AS my_marks,
    (SELECT s.feedback FROM submissions s WHERE s.assignment_id = a.id AND s.student_id = ? ORDER BY s.submitted_at DESC LIMIT 1) AS my_feedback
  FROM assignments a JOIN courses c ON c.id = a.course_id
  JOIN enrollments e ON e.course_id = a.course_id AND e.student_id = ?
  ORDER BY a.due_date ASC');
$rows->execute([$u['id'], $u['id'], $u['id'], $u['id']]);
$rows = $rows->fetchAll();

$page_title = 'Assignments';
require __DIR__ . '/../inc/header.php';
?>

<?php if (!$rows): ?>
  <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">Aapke courses me koi assignment nahi.</div>
<?php endif; ?>

<?php foreach ($rows as $a): ?>
<div class="bg-white rounded-lg shadow p-5 mb-4">
  <div class="flex justify-between flex-wrap gap-2">
    <div><b><?= e($a['title']) ?></b> <span class="text-sm text-gray-400">· <?= e($a['course_title']) ?></span>
      <div class="text-xs text-gray-400 mt-1">Due: <?= e($a['due_date'] ?: '—') ?> · Max marks: <?= (int)$a['max_marks'] ?></div></div>
    <div><?= $a['my_status'] ? status_badge($a['my_status']) : '<span class="px-2 py-1 rounded text-xs font-semibold bg-gray-200 text-gray-700">Not submitted</span>' ?></div>
  </div>
  <?php if ($a['description']): ?><p class="text-sm text-gray-600 mt-2"><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
  <?php if ($a['my_marks'] !== null): ?>
    <div class="mt-2 text-sm bg-green-50 border border-green-200 p-3 rounded">Marks: <b><?= (int)$a['my_marks'] ?>/<?= (int)$a['max_marks'] ?></b>
    <?php if ($a['my_feedback']): ?><br>Feedback: <?= e($a['my_feedback']) ?><?php endif; ?></div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="mt-3 grid md:grid-cols-3 gap-3 items-end bg-gray-50 p-3 rounded">
  <?= csrf_field() ?>
    <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
    <div><label class="text-xs font-semibold">Answer text (optional)</label>
      <input name="answer_text" class="w-full border rounded px-2 py-1.5 text-sm"></div>
    <div><label class="text-xs font-semibold">File (PDF/DOC/ZIP/Image)</label>
      <input type="file" name="sub_file" class="text-sm"></div>
    <button class="text-white px-4 py-2 rounded text-sm font-semibold" style="background:#c74b25">Submit</button>
  </form>
</div>
<?php endforeach; ?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
