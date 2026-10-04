<?php
// Curriculum manager: sections + lectures of one course
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_lectures');
$pdo = db();

$course_id = (int)($_GET['course_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
$st->execute([$course_id]);
$course = $st->fetch();
if (!$course) { flash('Course nahi mila.', 'error'); redirect('courses.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_section'])) {
    $st = $pdo->prepare('INSERT INTO sections (course_id, title, sort_order) VALUES (?, ?, (SELECT COALESCE(MAX(sort_order),0)+1 FROM (SELECT sort_order FROM sections WHERE course_id=?) s))');
    $st->execute([$course_id, trim($_POST['new_section']), $course_id]);
    flash('Section add ho gaya.');
    redirect('curriculum.php?course_id=' . $course_id);
}
if (isset($_POST['del_section'])) {
    $pdo->prepare('DELETE FROM sections WHERE id = ? AND course_id = ?')->execute([(int)$_POST['del_section'], $course_id]);
    flash('Section delete ho gaya.');
    redirect('curriculum.php?course_id=' . $course_id);
}
if (isset($_POST['del_lecture'])) {
    $pdo->prepare('DELETE FROM lectures WHERE id = ? AND course_id = ?')->execute([(int)$_POST['del_lecture'], $course_id]);
    flash('Lecture delete ho gaya.');
    redirect('curriculum.php?course_id=' . $course_id);
}

$sections = $pdo->prepare('SELECT * FROM sections WHERE course_id = ? ORDER BY sort_order, id');
$sections->execute([$course_id]);
$sections = $sections->fetchAll();

$lectures = $pdo->prepare('SELECT * FROM lectures WHERE course_id = ? ORDER BY sort_order, id');
$lectures->execute([$course_id]);
$by_section = [];
foreach ($lectures->fetchAll() as $l) { $by_section[$l['section_id'] ?? 0][] = $l; }

$page_title = 'Curriculum: ' . $course['title'];
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-4 flex gap-3 text-sm">
  <a href="courses.php" class="underline text-gray-500">← Courses</a>
  <a href="lecture_form.php?course_id=<?= $course_id ?>" class="text-white px-4 py-1.5 rounded font-semibold" style="background:#c74b25">+ Lecture</a>
</div>

<form method="post" class="bg-white rounded-lg shadow p-4 mb-6 flex gap-2 max-w-xl">
  <?= csrf_field() ?>
  <input name="new_section" required placeholder="Naya section — misal: Module 1: Introduction" class="flex-1 border rounded px-3 py-2">
  <button class="text-white px-4 py-2 rounded" style="background:#c74b25">Add section</button>
</form>

<?php foreach ($sections as $s): ?>
<div class="bg-white rounded-lg shadow mb-4">
  <div class="p-4 border-b flex justify-between items-center bg-gray-50 rounded-t-lg">
    <b><?= e($s['title']) ?></b>
    <span class="space-x-2 text-sm">
      <a href="lecture_form.php?course_id=<?= $course_id ?>&section_id=<?= $s['id'] ?>" class="underline" style="color:#c74b25">+ Lecture</a>
      <form method="post" action="curriculum.php?course_id=<?= $course_id ?>" class="inline" onsubmit="return confirm('Section delete? Iske lectures section-less ho jayenge.')">
        <?= csrf_field() ?>
        <input type="hidden" name="del_section" value="<?= $s['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
    </span>
  </div>
  <?php foreach ($by_section[$s['id']] ?? [] as $l): ?>
    <div class="p-3 border-b flex justify-between items-center text-sm">
      <span><?= e($l['title']) ?><?php if (is_new_lecture($l)) echo new_badge(); ?> <span class="text-gray-400">· <?= e($l['duration'] ?: '-') ?></span>
        <?php if ($l['is_preview']): ?><span class="ml-1 px-1.5 py-0.5 rounded text-xs bg-purple-100 text-purple-700">Preview</span><?php endif; ?></span>
      <span class="space-x-2">
        <a href="lecture_form.php?id=<?= $l['id'] ?>" class="underline text-blue-600">Edit</a>
        <form method="post" action="curriculum.php?course_id=<?= $course_id ?>" class="inline" onsubmit="return confirm('Lecture delete?')">
        <?= csrf_field() ?>
        <input type="hidden" name="del_lecture" value="<?= $l['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
      </span>
    </div>
  <?php endforeach; ?>
  <?php if (empty($by_section[$s['id']])): ?><div class="p-3 text-sm text-gray-400">Koi lecture nahi.</div><?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (!empty($by_section[0])): ?>
<div class="bg-white rounded-lg shadow mb-4">
  <div class="p-4 border-b bg-gray-50 rounded-t-lg"><b>Bina section ke lectures</b></div>
  <?php foreach ($by_section[0] as $l): ?>
    <div class="p-3 border-b flex justify-between items-center text-sm">
      <span><?= e($l['title']) ?></span>
      <span class="space-x-2">
        <a href="lecture_form.php?id=<?= $l['id'] ?>" class="underline text-blue-600">Edit</a>
        <form method="post" action="curriculum.php?course_id=<?= $course_id ?>" class="inline" onsubmit="return confirm('Lecture delete?')">
        <?= csrf_field() ?>
        <input type="hidden" name="del_lecture" value="<?= $l['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
      </span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
