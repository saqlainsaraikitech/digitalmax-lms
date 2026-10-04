<?php
// Course add/edit
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_courses');
$pdo = db();
$id = (int)($_GET['id'] ?? 0);
$course = ['title' => '', 'description' => '', 'thumbnail' => '', 'price' => 0, 'status' => 'draft', 'whatsapp_group_link' => '', 'whatsapp_community_link' => ''];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([$id]);
    $course = $st->fetch() ?: $course;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $thumb = upload_file('thumbnail', 'thumbs', ['jpg', 'jpeg', 'png', 'webp'], 500); // max 500KB
        if ($thumb) $course['thumbnail'] = $thumb;
        ensure_community_columns();
        $wa_community = trim($_POST['whatsapp_community_link'] ?? '');
        if ($wa_community !== '' && !valid_wa_link($wa_community)) throw new Exception('Official community link https:// se shuru hona chahiye.');
        $wa_group = trim($_POST['whatsapp_group_link'] ?? '');
        if ($wa_group !== '' && !valid_wa_link($wa_group)) throw new Exception('Discussion group link https:// se shuru hona chahiye.');
        $data = [
            trim($_POST['title']), $_POST['description'] ?? '', $course['thumbnail'],
            (float)($_POST['price'] ?? 0), $_POST['status'] ?? 'draft', $wa_community ?: null, $wa_group ?: null,
        ];
        if ($id) {
            $st = $pdo->prepare('UPDATE courses SET title=?, description=?, thumbnail=?, price=?, status=?, whatsapp_community_link=?, whatsapp_group_link=? WHERE id=?');
            $st->execute([...$data, $id]);
            flash('Course update ho gaya.');
        } else {
            $st = $pdo->prepare('INSERT INTO courses (title, description, thumbnail, price, status, whatsapp_community_link, whatsapp_group_link) VALUES (?,?,?,?,?,?,?)');
            $st->execute($data);
            $id = $pdo->lastInsertId();
            flash('Course ban gaya. Ab curriculum (lectures) add karein.');
        }
        redirect('curriculum.php?course_id=' . $id);
    } catch (Exception $ex) {
        flash($ex->getMessage(), 'error');
    }
}
$page_title = $id ? 'Edit course' : 'New course';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 max-w-2xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Title *</label>
    <input name="title" required value="<?= e($course['title']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Description</label>
    <textarea name="description" rows="4" class="w-full border rounded px-3 py-2 mt-1"><?= e($course['description']) ?></textarea></div>
  <div class="grid grid-cols-2 gap-4">
    <div><label class="text-sm font-semibold">Price (Rs)</label>
      <input name="price" type="number" min="0" step="1" value="<?= e($course['price']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
    <div><label class="text-sm font-semibold">Status</label>
      <select name="status" class="w-full border rounded px-3 py-2 mt-1">
        <option value="draft" <?= $course['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="published" <?= $course['status'] === 'published' ? 'selected' : '' ?>>Published</option>
      </select></div>
  </div>
  <div><label class="text-sm font-semibold"><?= icon('megaphone',16) ?> Official WhatsApp Community Link <span class="text-gray-400 font-normal">(Announcements only)</span></label>
    <input name="whatsapp_community_link" value="<?= e($course['whatsapp_community_link'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..." class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
    <p class="text-xs text-gray-400 mt-1">Announcements wali official community — dashboard card par <?= icon('megaphone',16) ?> button. Khali = button hide.</p></div>
  <div><label class="text-sm font-semibold"><?= icon('message',16) ?> Batch WhatsApp Discussion Group Link <span class="text-gray-400 font-normal">(Students Q&amp;A)</span></label>
    <input name="whatsapp_group_link" value="<?= e($course['whatsapp_group_link'] ?? '') ?>" placeholder="https://chat.whatsapp.com/..." class="w-full border rounded px-3 py-2 mt-1" dir="ltr">
    <p class="text-xs text-gray-400 mt-1">Discussion/Q&amp;A group — dashboard card par <?= icon('message',16) ?> button (fallback — agar batch par link na ho).</p></div>
  <div><label class="text-sm font-semibold">Thumbnail</label>
    <?php if ($course['thumbnail']): ?><div class="my-2"><img src="../<?= e($course['thumbnail']) ?>" class="h-20 rounded"></div><?php endif; ?>
    <input type="file" name="thumbnail" accept="image/*" class="mt-1 text-sm"></div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save</button>
  <a href="courses.php" class="ml-3 text-sm underline text-gray-500">Cancel</a>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
