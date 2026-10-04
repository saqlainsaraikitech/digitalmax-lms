<?php
// Admin: System & Backup — download, restore, auto-backup (cron)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/backup.php';
require_login();
if (!is_admin()) { http_response_code(403); die('Sirf admin.'); }
$pdo = db();

// secret key (pehli dafa auto-generate)
$secret = setting('backup_secret_key', '');
if ($secret === '') {
    $secret = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['backup_secret_key', $secret, $secret]);
}
if (isset($_POST['regen_key'])) {
    $secret = bin2hex(random_bytes(16));
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['backup_secret_key', $secret, $secret]);
    flash('Nayi secret key generate ho gayi — cron job me update kar dein.');
    redirect('backup.php');
}

// 1-click download
if (isset($_POST['download'])) {
    $sql = db_backup_sql();
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="lms-backup-' . date('Ymd-His') . '.sql"');
    echo $sql;
    exit;
}

// emergency restore
if (isset($_POST['restore_confirm']) && !empty($_FILES['backup']['tmp_name'])) {
    try {
        if ($_FILES['backup']['size'] > 100 * 1024 * 1024) throw new Exception('File 100MB se bari hai.');
        $ext = strtolower(pathinfo($_FILES['backup']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') throw new Exception('Sirf .sql backup file upload karein.');
        $sql = file_get_contents($_FILES['backup']['tmp_name']);
        if (stripos($sql, 'CREATE TABLE') === false) throw new Exception('Ye valid database backup nahi lag rahi.');
        $n = db_restore_sql($sql);
        flash("Restore complete — $n statements execute huay. ");
    } catch (Exception $ex) { flash('Restore failed: ' . $ex->getMessage(), 'error'); }
    redirect('backup.php');
}

// server-side backup files: download / delete
// server backup download (read-only — GET safe) / delete (POST+CSRF)
if (isset($_GET['dl'])) {
    $f = basename($_GET['dl']);
    $path = backup_dir() . '/' . $f;
    if (!preg_match('/^lms-backup-[0-9\-]+\.sql$/', $f) || !file_exists($path)) {
        flash('File nahi mili.', 'error'); redirect('backup.php');
    }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $f . '"');
    readfile($path);
    exit;
}
if (isset($_POST['del_file'])) {
    $f = basename($_POST['del_file']);
    $path = backup_dir() . '/' . $f;
    if (!preg_match('/^lms-backup-[0-9\-]+\.sql$/', $f) || !file_exists($path)) {
        flash('File nahi mili.', 'error'); redirect('backup.php');
    }
    @unlink($path);
    flash('Backup file delete ho gayi.');
    redirect('backup.php');
}

$dir = backup_dir();
$files = glob($dir . '/lms-backup-*.sql') ?: [];
usort($files, fn($a, $b) => strcmp($b, $a));

$cron_url = 'https://lms.digitalmx.pk/cron_backup.php?key=' . $secret;
$page_title = 'System & Backup';
require __DIR__ . '/../inc/header.php';
?>

<div class="grid md:grid-cols-2 gap-6">

  <!-- Download -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-bold text-lg mb-1"><?= icon('save',16) ?> Database Backup</h2>
    <p class="text-sm text-gray-500 mb-4">Poori database ka instant <code class="bg-gray-100 px-1 rounded">.sql</code> dump download karein.</p>
    <form method="post">
  <?= csrf_field() ?>
      <button name="download" value="1" class="text-white px-6 py-2.5 rounded font-semibold" style="background:#c74b25"><?= icon('arrow-down',16) ?> Download Database Backup (.sql)</button>
    </form>
  </div>

  <!-- Restore -->
  <div class="bg-white rounded-lg shadow p-6">
    <h2 class="font-bold text-lg mb-1"><?= icon('alert', 20) ?> Emergency Restore</h2>
    <p class="text-sm text-gray-500 mb-4">Backup <code class="bg-gray-100 px-1 rounded">.sql</code> file upload karke database restore karein — phpMyAdmin ki zaroorat nahi.</p>
    <form method="post" enctype="multipart/form-data" class="space-y-3">
  <?= csrf_field() ?>
      <input type="file" name="backup" accept=".sql" required class="text-sm">
      <label class="flex items-start gap-2 text-sm text-red-700 bg-red-50 border border-red-200 p-3 rounded">
        <input type="checkbox" name="restore_confirm" value="1" required class="mt-1">
        <span>Samajh gaya — restore <b>purana data overwrite</b> kar dega. Pehle fresh backup download kar liya hai.</span>
      </label>
      <button class="bg-red-600 text-white px-6 py-2 rounded font-semibold text-sm">Restore Database</button>
    </form>
  </div>
</div>

<!-- Cron -->
<div class="bg-white rounded-lg shadow p-6 mt-6">
  <h2 class="font-bold text-lg mb-1">⏰ Auto-Backup (Cron Job)</h2>
  <p class="text-sm text-gray-500 mb-4">Hostinger → <b>Cron Jobs</b> me ye command roz raat 2 baje set kar dein — backup khud ban kar server par save hoga (akhri 7 rakhe jayenge):</p>
  <div class="bg-gray-900 text-green-300 font-mono text-xs p-4 rounded mb-3 overflow-x-auto">php /home/USERNAME/public_html/lms/cron_backup.php <?= e($secret) ?></div>
  <p class="text-xs text-gray-400 mb-4">Ya URL method: <code class="bg-gray-100 px-1 rounded break-all"><?= e($cron_url) ?></code></p>
  <form method="post" class="inline">
  <?= csrf_field() ?>
    <button name="regen_key" value="1" data-confirm="Nayi key banegi — purani cron command kaam nahi karegi. Pakka?" class="text-xs underline text-gray-500"><?= icon('key',16) ?> Nayi secret key generate karein</button>
  </form>
  <p class="text-xs text-gray-400 mt-2">Note: <code class="bg-gray-100 px-1 rounded">USERNAME</code> ki jagah apna Hostinger username likhein.</p>
</div>

<!-- Server backups list -->
<div class="bg-white rounded-lg shadow p-6 mt-6">
  <h2 class="font-bold text-lg mb-4"><?= icon('folder',16) ?> Server par saved backups (<?= count($files) ?>/7)</h2>
  <?php if (!$files): ?><p class="text-sm text-gray-400">Abhi koi auto-backup nahi bana — cron set karne ke baad yahan nazar ayenge.</p><?php endif; ?>
  <?php foreach ($files as $fp):
    $fn = basename($fp); $kb = round(filesize($fp) / 1024); ?>
    <div class="flex justify-between items-center text-sm border-b py-2.5">
      <span class="font-mono"><?= e($fn) ?> <span class="text-gray-400">(<?= number_format($kb) ?> KB)</span></span>
      <span class="space-x-3">
        <a href="backup.php?dl=<?= e($fn) ?>" class="underline" style="color:#c74b25">Download</a>
        <form method="post" class="inline" onsubmit="return confirm('Backup file delete?')">
          <?= csrf_field() ?>
          <input type="hidden" name="del_file" value="<?= e($fn) ?>">
          <button class="underline text-red-600">Delete</button>
        </form>
      </span>
    </div>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../inc/footer.php'; ?>
