<?php
// Courses list
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_courses');
$pdo = db();

if (isset($_POST['delete'])) {
    $st = $pdo->prepare('DELETE FROM courses WHERE id = ?');
    $st->execute([(int)$_POST['delete']]);
    flash('Course delete ho gaya.');
    redirect('courses.php');
}

$courses = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM lectures WHERE course_id = c.id) AS lectures, (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) AS students FROM courses c ORDER BY c.created_at DESC')->fetchAll();

$page_title = 'Courses';
require __DIR__ . '/../inc/header.php';
?>

<div class="mb-4"><a href="course_form.php" class="text-white px-4 py-2 rounded text-sm font-semibold" style="background:#c74b25">+ New course</a></div>

<div class="bg-white rounded-lg shadow overflow-x-auto">
<table class="w-full text-sm">
  <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Course</th><th class="p-3">Price</th><th class="p-3">Lectures</th><th class="p-3">Students</th><th class="p-3">Status</th><th class="p-3">Action</th></tr>
  <?php foreach ($courses as $c): ?>
  <tr class="border-t">
    <td class="p-3 font-semibold"><?= e($c['title']) ?></td>
    <td class="p-3"><?= money($c['price']) ?></td>
    <td class="p-3"><?= (int)$c['lectures'] ?></td>
    <td class="p-3"><a href="students.php?course_id=<?= $c['id'] ?>" class="font-extrabold underline" style="color:#c74b25" title="Enrolled students dekhein"><?= (int)$c['students'] ?></a></td>
    <td class="p-3"><?= status_badge($c['status']) ?></td>
    <td class="p-3 space-x-2 whitespace-nowrap">
      <a href="curriculum.php?course_id=<?= $c['id'] ?>" class="underline" style="color:#c74b25">Curriculum</a>
      <a href="batches.php?course_id=<?= $c['id'] ?>" class="underline text-indigo-600">Batches</a>
      <a href="course_form.php?id=<?= $c['id'] ?>" class="underline text-blue-600">Edit</a>
      <form method="post" class="inline" onsubmit="return confirm('Course aur uske tamam lectures delete ho jayenge. Pakka?')">
        <?= csrf_field() ?>
        <input type="hidden" name="delete" value="<?= $c['id'] ?>">
        <button class="underline text-red-600">Delete</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$courses): ?><tr><td colspan="5" class="p-6 text-center text-gray-400">Koi course nahi — pehla course banayein.</td></tr><?php endif; ?>
</table>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
