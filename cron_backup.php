<?php
// DigitalMax LMS — Auto backup endpoint (Hostinger Cron Jobs ke liye)
// Cron command (roz raat 2 baje):
//   php /home/USERNAME/public_html/lms/cron_backup.php YOUR_SECRET_KEY
// Ya URL method:
//   wget -q -O /dev/null "https://lms.digitalmx.pk/cron_backup.php?key=YOUR_SECRET_KEY"
// Secret key: Admin panel → System & Backup me milti hai.

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/backup.php';

$key = '';
if (php_sapi_name() === 'cli') {
    global $argv;
    $key = $argv[1] ?? '';
} else {
    $key = $_GET['key'] ?? '';
}

$real = setting('backup_secret_key', '');
if ($real === '' || !hash_equals($real, (string)$key)) {
    http_response_code(403);
    die('Forbidden');
}

$dir = backup_dir();
$sql = db_backup_sql();
$file = $dir . '/lms-backup-' . date('Ymd-His') . '.sql';
file_put_contents($file, $sql);

// purane backups saf karo — sirf akhri 7 rakho (storage safe)
$files = glob($dir . '/lms-backup-*.sql') ?: [];
usort($files, fn($a, $b) => strcmp($b, $a));
foreach (array_slice($files, 7) as $old) @unlink($old);

echo 'OK: ' . basename($file) . ' (' . round(filesize($file) / 1024) . " KB)\n";
