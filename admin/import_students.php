<?php
// Admin: bulk student import (CSV) — accounts + course assign ek click me
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_perm('p_students');
$pdo = db();

// CSV template download
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="students-template.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['name', 'email', 'phone', 'course_id']);
    fputcsv($out, ['Ahmed Raza', 'ahmed@example.com', '03001234567', '1']);
    fputcsv($out, ['Fatima Noor', 'fatima@example.com', '03007654321', '1']);
    fclose($out);
    exit;
}

function random_pass($len = 8) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    return substr(str_shuffle(str_repeat($chars, $len)), 0, $len);
}

$results = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv']['tmp_name'])) {
    $ext = strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        flash('Sirf .csv file upload karein (Excel me "Save As → CSV" kar lein).', 'error');
    } else {
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        if ($headers) {
            // BOM strip + normalize
            $headers = array_map(fn($h) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', $h))), $headers);
            $idx = array_flip($headers);
            if (!isset($idx['name']) || !isset($idx['email'])) {
                flash('CSV me "name" aur "email" columns lazmi hain.', 'error');
                $results = [];
            } else {
                $results = [];
                $n = 0;
                while (($row = fgetcsv($handle)) !== false && $n < 500) {
                    $n++;
                    $name  = trim($row[$idx['name']] ?? '');
                    $email = trim($row[$idx['email']] ?? '');
                    $phone = trim($row[$idx['phone']] ?? '');
                    $cid   = (int)trim($row[$idx['course_id']] ?? '');
                    $status = ''; $pass = '';
                    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $status = icon('x-circle',16) . ' Invalid name/email';
                    } else {
                        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                        $st->execute([$email]);
                        if ($st->fetch()) {
                            $status = icon('alert',16) . ' Email pehle se mojood — skip';
                        } else {
                            $pass = random_pass();
                            $pdo->prepare('INSERT INTO users (name, email, phone, password, role, status) VALUES (?,?,?,?, "student", "active")')
                                ->execute([$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
                            $sid = (int)$pdo->lastInsertId();
                            $enrolled = '';
                            if ($cid > 0) {
                                $st = $pdo->prepare('SELECT id FROM courses WHERE id = ?');
                                $st->execute([$cid]);
                                if ($st->fetch()) {
                                    $pdo->prepare('INSERT IGNORE INTO enrollments (student_id, course_id) VALUES (?, ?)')->execute([$sid, $cid]);
                                    $enrolled = " (course #$cid)";
                                } else $enrolled = ' (course nahi mila)';
                            }
                            $status = icon('check-circle',16) . ' Created' . $enrolled;
                        }
                    }
                    $results[] = ['name' => $name, 'email' => $email, 'phone' => $phone, 'course_id' => $cid, 'password' => $pass, 'status' => $status];
                }
                fclose($handle);
                $ok = count(array_filter($results, fn($r) => str_starts_with($r['status'], icon('check-circle',16))));
                flash("$ok accounts ban gaye, " . (count($results) - $ok) . " skip/invalid.");
            }
        } else {
            flash('Khali ya ghalat file.', 'error');
        }
    }
}

$courses = $pdo->query('SELECT id, title FROM courses ORDER BY id')->fetchAll();
$page_title = 'Bulk Import Students';
require __DIR__ . '/../inc/header.php';
?>

<div class="bg-white rounded-lg shadow p-6 max-w-2xl mb-6">
  <h2 class="font-bold mb-2">CSV upload karein</h2>
  <p class="text-sm text-gray-500 mb-1">Columns: <code class="bg-gray-100 px-1 rounded">name</code>, <code class="bg-gray-100 px-1 rounded">email</code>, <code class="bg-gray-100 px-1 rounded">phone</code>, <code class="bg-gray-100 px-1 rounded">course_id</code></p>
  <p class="text-xs text-gray-400 mb-4">Course IDs: <?php foreach ($courses as $c): ?><span class="mr-2">#<?= $c['id'] ?> = <?= e($c['title']) ?></span><?php endforeach; ?></p>
  <div class="mb-4"><a href="import_students.php?template=1" class="text-sm underline" style="color:#c74b25"><?= icon('download',16) ?> Sample template download karein</a>
    <span class="text-xs text-gray-400">(Excel me bharein → Save As → CSV)</span></div>
  <form method="post" enctype="multipart/form-data" class="flex gap-3 items-center flex-wrap">
  <?= csrf_field() ?>
    <input type="file" name="csv" accept=".csv" required class="text-sm">
    <button class="text-white px-6 py-2 rounded font-semibold text-sm" style="background:#c74b25">Upload & Import</button>
  </form>
</div>

<?php if (is_array($results)): ?>
<div class="bg-white rounded-lg shadow overflow-x-auto">
  <div class="p-4 border-b font-bold">Result — passwords abhi note kar lein (dobara nahi dikhenge!) <?= icon('alert',16) ?></div>
  <table class="w-full text-sm">
    <tr class="bg-gray-50 text-left text-gray-500"><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Password</th><th class="p-3">Status</th></tr>
    <?php foreach ($results as $r): ?>
    <tr class="border-t">
      <td class="p-3"><?= e($r['name']) ?></td>
      <td class="p-3"><?= e($r['email']) ?></td>
      <td class="p-3 font-mono font-bold"><?= e($r['password'] ?: '—') ?></td>
      <td class="p-3"><?= e($r['status']) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../inc/footer.php'; ?>
