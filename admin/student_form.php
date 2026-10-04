<?php
// Student add/edit (account admin banata hai — public signup nahi)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_students');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$s = ['name' => '', 'email' => '', 'phone' => '', 'status' => 'active'];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? AND role = "student"');
    $st->execute([$id]);
    if ($row = $st->fetch()) $s = $row;
}
$all_courses = $pdo->query('SELECT id, title FROM courses ORDER BY title')->fetchAll();
$enrolled_ids = [];
if ($id) {
    $st = $pdo->prepare('SELECT course_id FROM enrollments WHERE student_id = ?');
    $st->execute([$id]);
    $enrolled_ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']); $email = trim($_POST['email']); $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $err = '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Naam aur valid email zaroori hai.';
    if (!$err) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $st->execute([$email, $id]);
        if ($st->fetch()) $err = 'Ye email pehle se registered hai.';
    }
    if (!$err && !$id && empty($_POST['password'])) $err = 'Naye student ke liye password zaroori hai.';
    if ($err) { flash($err, 'error'); }
    else {
        $chosen = array_map('intval', $_POST['courses'] ?? []);
        $batch_sel = $_POST['batch'] ?? [];
        // batch validation: jis course ke batches hain, wahan batch lazmi
        $course_titles = [];
        foreach ($all_courses as $cc) $course_titles[(int)$cc['id']] = $cc['title'];
        if (!$err) {
            foreach ($chosen as $cid) {
                $cb = course_batches($cid);
                $bid = (int)($batch_sel[$cid] ?? 0);
                if ($cb && $bid <= 0) { $err = 'Course "' . ($course_titles[$cid] ?? $cid) . '" ke liye batch select karna lazmi hai.'; break; }
                if ($bid > 0) {
                    $ok = false;
                    foreach ($cb as $b) if ((int)$b['id'] === $bid) { $ok = true; break; }
                    if (!$ok) { $err = 'Ghalat batch select hua hai.'; break; }
                }
            }
        }
        if ($err) { flash($err, 'error'); }
        else {
        if ($id) {
            if (!empty($_POST['password'])) {
                $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, status=?, password=? WHERE id=?')
                    ->execute([$name, $email, $phone, $status, password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            } else {
                $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, status=? WHERE id=?')
                    ->execute([$name, $email, $phone, $status, $id]);
            }
            $saved = $id;
        } else {
            $pdo->prepare('INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?, "student", ?)')
                ->execute([$name, $email, $phone, password_hash($_POST['password'], PASSWORD_DEFAULT), $status]);
            $saved = (int)$pdo->lastInsertId();
        }
        // course assign sync (batch + fee ke sath)
        $pdo->prepare('DELETE FROM enrollments WHERE student_id = ?')->execute([$saved]);
        $ins = $pdo->prepare('INSERT INTO enrollments (student_id, course_id, batch_id, fee_status, fee_amount) VALUES (?, ?, ?, ?, ?)');
        $fee_st = $_POST['fee_status'] ?? [];
        $fee_am = $_POST['fee_amount'] ?? [];
        foreach ($chosen as $cid) {
            $bid = (int)($batch_sel[$cid] ?? 0);
            $fstat = ($fee_st[$cid] ?? 'pending') === 'paid' ? 'paid' : 'pending';
            $famt = trim($fee_am[$cid] ?? '');
            $famt = ($famt !== '' && is_numeric($famt)) ? (float)$famt : null;
            $ins->execute([$saved, $cid, $bid > 0 ? $bid : null, $fstat, $famt]);
        }
        flash('Student save ho gaya' . ($chosen ? ' — ' . count($chosen) . ' course(s) assign.' : '.'));
        redirect('student_view.php?id=' . $saved);
        }
    }
}

$page_title = $id ? 'Edit student' : 'New student';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" class="bg-white rounded-lg shadow p-6 max-w-xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Name *</label>
    <input name="name" required value="<?= e($s['name']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Email * (login ID)</label>
    <input name="email" type="email" required value="<?= e($s['email']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Phone / WhatsApp</label>
    <input name="phone" value="<?= e($s['phone']) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Password <?= $id ? '(khali chhorein = purana rahega)' : '*' ?></label>
    <input name="password" type="text" <?= $id ? '' : 'required' ?> placeholder="<?= $id ? 'Naya password (reset)' : 'Login password' ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">Status</label>
    <select name="status" class="w-full border rounded px-3 py-2 mt-1">
      <option value="active" <?= $s['status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= $s['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select></div>
  <div>
    <label class="text-sm font-semibold">Courses assign karein (student in courses ko access kar sakega)</label>
    <?php if (!$all_courses): ?><p class="text-xs text-gray-400 mt-1">Pehle koi course banayein.</p><?php endif; ?>
    <?php
    // har course ke batches + is student ka current batch (edit par)
    $batches_by_course = [];
    foreach ($all_courses as $cc) $batches_by_course[$cc['id']] = course_batches($cc['id']);
    $enrolled_batch = [];
    $enrolled_fee = []; // v54: course_id => ['fee_status'=>..,'fee_amount'=>..]
    if ($id) {
        $bst = $pdo->prepare('SELECT course_id, batch_id, fee_status, fee_amount FROM enrollments WHERE student_id = ?');
        $bst->execute([$id]);
        foreach ($bst->fetchAll() as $r) {
            $enrolled_batch[(int)$r['course_id']] = $r['batch_id'] ? (int)$r['batch_id'] : 0;
            $enrolled_fee[(int)$r['course_id']] = ['fee_status' => $r['fee_status'] ?? 'pending', 'fee_amount' => $r['fee_amount']];
        }
    }
    ?>
    <div class="grid grid-cols-1 gap-2 mt-2 max-h-56 overflow-y-auto border rounded p-2">
      <?php foreach ($all_courses as $c):
        $cb = $batches_by_course[$c['id']] ?? [];
        $ef = $enrolled_fee[(int)$c['id']] ?? ['fee_status' => 'pending', 'fee_amount' => '']; ?>
        <div class="flex items-center gap-2 text-sm bg-gray-50 p-2 rounded flex-wrap">
          <label class="flex items-center gap-2 font-semibold">
            <input type="checkbox" name="courses[]" value="<?= $c['id'] ?>" <?= in_array((int)$c['id'], $enrolled_ids, true) ? 'checked' : '' ?>> <?= e($c['title']) ?>
          </label>
          <?php if ($cb): ?>
            <select name="batch[<?= $c['id'] ?>]" class="border rounded px-2 py-1 text-xs ml-auto" title="Batch select karna lazmi hai">
              <option value="0">— Select Batch * —</option>
              <?php foreach ($cb as $b): ?>
                <option value="<?= $b['id'] ?>" <?= ($enrolled_batch[(int)$c['id']] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>>
                  <?= e($b['batch_name']) ?> (<?= batch_status_label(batch_status($b)) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <span class="text-xs text-gray-400 ml-auto">koi batch nahi (sab access)</span>
          <?php endif; ?>
          <span class="flex items-center gap-1.5 text-xs ml-auto">
            <span class="text-gray-500 font-semibold">Fee:</span>
            <select name="fee_status[<?= $c['id'] ?>]" class="border rounded px-1.5 py-1 text-xs" title="Fee status">
              <option value="pending" <?= ($ef['fee_status'] ?? 'pending') === 'pending' ? 'selected' : '' ?>>Pending</option>
              <option value="paid" <?= ($ef['fee_status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
            </select>
            <input type="number" name="fee_amount[<?= $c['id'] ?>]" value="<?= e($ef['fee_amount'] ?? '') ?>" placeholder="Rs" min="0" step="0.01"
                   class="border rounded px-1.5 py-1 text-xs w-24" title="Fee amount">
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save</button>
  <a href="students.php" class="ml-3 text-sm underline text-gray-500">Cancel</a>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
