<?php
// Admin: Student Leads & WhatsApp Broadcast Exporter
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
if (!can('p_students')) { http_response_code(403); die('Access denied.'); }
$pdo = db();

/** 03XXXXXXXXX / 923XXXXXXXXX / +92... -> 923XXXXXXXXX (ya null) */
function normalize_pk_phone($raw) {
    $d = preg_replace('/\D+/', '', (string)$raw);
    if ($d === '') return null;
    if (strncmp($d, '92', 2) === 0) { /* ok */ }
    elseif ($d[0] === '0') $d = '92' . substr($d, 1);
    elseif ($d[0] === '3') $d = '92' . $d;
    else return null;
    return preg_match('/^923\d{9}$/', $d) ? $d : null;
}

function leads_query($filter) {
    $where = "u.role = 'student'";
    if ($filter === 'paid')        $where .= " AND EXISTS (SELECT 1 FROM payments p WHERE p.student_id = u.id AND p.status = 'approved')";
    elseif ($filter === 'pending') $where .= " AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.student_id = u.id AND p.status = 'approved')";
    return "SELECT u.id, u.name, u.email, u.phone,
                   EXISTS (SELECT 1 FROM payments p WHERE p.student_id = u.id AND p.status = 'approved') AS paid,
                   (SELECT GROUP_CONCAT(c.title SEPARATOR ' | ') FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.student_id = u.id) AS courses
            FROM users u WHERE $where ORDER BY u.created_at DESC";
}

$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'paid', 'pending'], true)) $filter = 'all';

// export CSV
if (isset($_GET['export'])) {
    $rows = $pdo->query(leads_query($filter))->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="whatsapp-broadcast-' . $filter . '-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // Excel ke liye
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'WhatsApp Number', 'Email', 'Status', 'Courses']);
    foreach ($rows as $r) {
        $ph = normalize_pk_phone($r['phone']);
        if (!$ph) continue; // invalid number skip
        fputcsv($out, [$r['name'], $ph, $r['email'], $r['paid'] ? 'Paid' : 'Pending/Unpaid', $r['courses'] ?: '-']);
    }
    fclose($out);
    exit;
}

// counts
$counts = [];
foreach (['all', 'paid', 'pending'] as $f) {
    $counts[$f] = (int)$pdo->query('SELECT COUNT(*) FROM (' . leads_query($f) . ') t')->fetchColumn();
}
$rows = $pdo->query(leads_query($filter))->fetchAll();

// broadcast template save
if (isset($_POST['save_template'])) {
    $t = trim($_POST['broadcast_template'] ?? '');
    $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = ?')->execute(['broadcast_template', $t, $t]);
    flash('Broadcast template save ho gaya.');
    redirect('leads.php?filter=' . $filter);
}
$template = setting('broadcast_template', icon('graduation-cap',16) . " *DigitalMax LMS — Special Offer!*\n\nAssalam-o-Alaikum!\n\n" . icon('megaphone',16) . " Hamara naya batch start ho raha hai — limited seats!\n\n" . icon('arrow-right',16) . " Login: https://lms.digitalmx.pk/login.php\n" . icon('phone',16) . " Help: " . setting('whatsapp_number', '') . "\n\n— DigitalMax Team");

$page_title = 'Leads & WhatsApp Broadcast';
require __DIR__ . '/../inc/header.php';
?>

<!-- Filter cards -->
<div class="grid md:grid-cols-3 gap-4 mb-6">
  <?php foreach ([['all',icon('users',16) . ' All Students','blue'],['paid',icon('check-circle',16) . ' Paid Students','green'],['pending',icon('clock',16) . ' Pending/Unpaid Leads','amber']] as [$f,$lbl,$c]): ?>
  <a href="leads.php?filter=<?= $f ?>" class="bg-white rounded-lg shadow p-4 <?= $filter===$f?'ring-2':'' ?>" style="<?= $filter===$f?'--tw-ring-color:#c74b25':'' ?>">
    <div class="text-sm text-gray-500"><?= $lbl ?></div>
    <div class="text-3xl font-extrabold" style="color:<?= $c==='blue'?'#2563eb':($c==='green'?'#16a34a':'#d97706') ?>"><?= $counts[$f] ?></div>
  </a>
  <?php endforeach; ?>
</div>

<div class="bg-white rounded-lg shadow p-6 mb-6">
  <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
    <h2 class="font-bold text-lg"><?= icon('clipboard-list',16) ?> <?= $filter==='all'?'All Students':($filter==='paid'?'Paid Students':'Pending/Unpaid Leads') ?> <span class="text-sm font-normal text-gray-400">(<?= count($rows) ?>)</span></h2>
    <a href="leads.php?filter=<?= $filter ?>&export=1" class="text-white px-5 py-2.5 rounded font-bold text-sm" style="background:#25D366"><?= icon('download',16) ?> Export for WhatsApp Broadcast</a>
  </div>
  <p class="text-xs text-gray-400 mb-4">Export me numbers <code class="bg-gray-100 px-1 rounded">923XXXXXXXXX</code> format me honge — invalid numbers auto-skip. CSV Excel/WhatsApp broadcast tools me seedha import hota hai.</p>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead><tr class="text-left text-gray-500 border-b">
        <th class="py-2">Name</th><th>Phone (raw)</th><th>WhatsApp 923…</th><th>Email</th><th>Status</th><th>Courses</th>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $r):
        $ph = normalize_pk_phone($r['phone']); ?>
        <tr class="border-b">
          <td class="py-2 font-semibold"><?= e($r['name']) ?></td>
          <td class="text-gray-500"><?= e($r['phone'] ?: '-') ?></td>
          <td><?= $ph ? '<code class="bg-green-50 text-green-700 px-1.5 py-0.5 rounded font-mono">' . e($ph) . '</code>' : '<span class="text-xs text-red-500">invalid</span>' ?></td>
          <td class="text-gray-500"><?= e($r['email']) ?></td>
          <td><?= $r['paid'] ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Paid</span>' : '<span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Pending</span>' ?></td>
          <td class="text-xs text-gray-500"><?= e($r['courses'] ?: '-') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="py-6 text-center text-gray-400">Koi record nahi.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Broadcast template -->
<div class="bg-white rounded-lg shadow p-6">
  <h2 class="font-bold text-lg mb-1"><?= icon('megaphone',16) ?> Direct Broadcast Template</h2>
  <p class="text-sm text-gray-500 mb-4">Naya course offer ya update likhein, <b>Copy</b> dabayein aur WhatsApp Community / Broadcast list me paste karke send kar dein.</p>
  <form method="post" class="space-y-3">
  <?= csrf_field() ?>
    <textarea name="broadcast_template" id="btpl" rows="8" class="w-full border rounded-lg px-4 py-3 text-sm font-mono focus:outline-none focus:ring-2" style="--tw-ring-color:#c74b25"><?= e($template) ?></textarea>
    <div class="flex gap-3 flex-wrap">
      <button type="button" onclick="copyTpl()" class="text-white px-6 py-2.5 rounded font-bold text-sm" style="background:#25D366"><?= icon('clipboard-list',16) ?> Copy Message</button>
      <button name="save_template" value="1" class="px-6 py-2.5 rounded font-bold text-sm border border-gray-300"><?= icon('save',16) ?> Save Template</button>
      <span id="copied" class="text-sm text-green-600 font-semibold hidden self-center"><?= icon('check-circle',16) ?> Copied!</span>
    </div>
  </form>
  <div class="mt-4 text-xs text-gray-400 bg-gray-50 border rounded p-3">
    <b>Tip:</b> WhatsApp me <code>*bold*</code> aur _italic_ formatting kaam karti hai. Broadcast list max 256 contacts — bari list ke liye CSV export karke WhatsApp Business API / broadcast tool use karein.
  </div>
</div>

<script>
function copyTpl(){
  var t = document.getElementById('btpl');
  t.select();
  var done = function(){ var c=document.getElementById('copied'); c.classList.remove('hidden'); setTimeout(function(){c.classList.add('hidden')},2000); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(t.value).then(done).catch(function(){ document.execCommand('copy'); done(); });
  } else { document.execCommand('copy'); done(); }
}
</script>

<?php require __DIR__ . '/../inc/footer.php'; ?>
