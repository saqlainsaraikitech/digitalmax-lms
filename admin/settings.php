<?php
// Site settings — sirf admin
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
if (!is_admin()) { http_response_code(403); die('Sirf admin.'); }
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site = trim($_POST['site_name'] ?? '');
    $wa = trim($_POST['whatsapp_number'] ?? '');
    if ($site !== '') {
        $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['site_name', $site, $site]);
    }
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['whatsapp_number', $wa, $wa]);
    $pct = max(0, min(100, (float)($_POST['affiliate_commission_percent'] ?? 10)));
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['affiliate_commission_percent', (string)$pct, (string)$pct]);
    $bd = trim($_POST['batch_deadline'] ?? '');
    $bd = $bd !== '' ? str_replace('T', ' ', $bd) . ':00' : '';
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['batch_deadline', $bd, $bd]);
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['seats_remaining', (string)max(0, (int)($_POST['seats_remaining'] ?? 7)), (string)max(0, (int)($_POST['seats_remaining'] ?? 7))]);
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['seats_total', (string)max(1, (int)($_POST['seats_total'] ?? 30)), (string)max(1, (int)($_POST['seats_total'] ?? 30))]);
    flash('Settings save ho gayi.');
    redirect('settings.php');
}

$page_title = 'Settings';
require __DIR__ . '/../inc/header.php';
?>
<form method="post" class="bg-white rounded-lg shadow p-6 max-w-xl space-y-4">
  <?= csrf_field() ?>
  <div><label class="text-sm font-semibold">Site name</label>
    <input name="site_name" value="<?= e(setting('site_name', 'DigitalMax LMS')) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  <div><label class="text-sm font-semibold">WhatsApp support number</label>
    <input name="whatsapp_number" value="<?= e(setting('whatsapp_number', '')) ?>" placeholder="0300-1234567" class="w-full border rounded px-3 py-2 mt-1">
    <p class="text-xs text-gray-400 mt-1">Student wala floating WhatsApp button isi number par message bhejega. Pakistani format me likhein (0300...). Khali chhorein to button nazar nahi aayega.</p></div>
  <div><label class="text-sm font-semibold">Affiliate commission %</label>
    <input name="affiliate_commission_percent" type="number" min="0" max="100" step="0.5" value="<?= e(setting('affiliate_commission_percent', 10)) ?>" class="w-full border rounded px-3 py-2 mt-1">
    <p class="text-xs text-gray-400 mt-1">Referral link se aane wali har <b>approved</b> payment par affiliate ko itne % commission milega.</p></div>
  <hr>
  <p class="text-sm font-semibold"><?= icon('target',16) ?> Landing page FOMO (batch countdown)</p>
  <div><label class="text-sm font-semibold">Batch deadline</label>
    <input name="batch_deadline" type="datetime-local" value="<?= e(str_replace(' ', 'T', substr(setting('batch_deadline', ''), 0, 16))) ?>" class="w-full border rounded px-3 py-2 mt-1">
    <p class="text-xs text-gray-400 mt-1">Khali chhorein to auto: is mahine ki akhri tareekh.</p></div>
  <div class="grid grid-cols-2 gap-4">
    <div><label class="text-sm font-semibold">Seats remaining</label>
      <input name="seats_remaining" type="number" min="0" value="<?= e(setting('seats_remaining', 7)) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
    <div><label class="text-sm font-semibold">Total seats</label>
      <input name="seats_total" type="number" min="1" value="<?= e(setting('seats_total', 30)) ?>" class="w-full border rounded px-3 py-2 mt-1"></div>
  </div>
  <button class="text-white px-6 py-2 rounded font-semibold" style="background:#c74b25">Save settings</button>
</form>
<?php require __DIR__ . '/../inc/footer.php'; ?>
