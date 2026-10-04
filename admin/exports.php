<?php
// Admin: 1-click CSV exports — sales, enrolled students, pending payments
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
if (!(can('p_payments') || can('p_students'))) { http_response_code(403); die('Access denied.'); }
$pdo = db();

$type = $_GET['type'] ?? '';
if ($type !== '') {
    $headers = []; $rows = []; $fname = 'lms-' . $type . '-' . date('Ymd') . '.csv';

    if ($type === 'sales' && can('p_payments')) {
        $headers = ['Date', 'Student', 'Email', 'Course', 'Amount (Rs)', 'Method', 'TRX ID', 'Status', 'Referred By'];
        $q = $pdo->query("SELECT p.created_at, u.name AS sname, u.email, c.title AS course, p.amount, p.method, p.trx_id, p.status, r.name AS ref
                          FROM payments p JOIN users u ON u.id = p.student_id
                          LEFT JOIN courses c ON c.id = p.course_id LEFT JOIN users r ON r.id = p.referred_by
                          WHERE p.status = 'approved' ORDER BY p.created_at DESC");
        foreach ($q->fetchAll() as $x) $rows[] = [$x['created_at'], $x['sname'], $x['email'], $x['course'] ?: '-', $x['amount'], $x['method'], $x['trx_id'] ?: '-', $x['status'], $x['ref'] ?: '-'];
    } elseif ($type === 'enrolled' && can('p_students')) {
        $headers = ['Student', 'Email', 'Phone', 'Course', 'Enrolled On', 'Progress %'];
        $q = $pdo->query("SELECT u.name AS sname, u.email, u.phone, c.title AS course, e.enrolled_at, e.student_id, e.course_id
                          FROM enrollments e JOIN users u ON u.id = e.student_id JOIN courses c ON c.id = e.course_id
                          ORDER BY e.enrolled_at DESC");
        foreach ($q->fetchAll() as $x) $rows[] = [$x['sname'], $x['email'], $x['phone'] ?: '-', $x['course'], $x['enrolled_at'], course_progress($x['student_id'], $x['course_id']) . '%'];
    } elseif ($type === 'pending' && can('p_payments')) {
        $headers = ['Date', 'Student', 'Email', 'Phone', 'Course', 'Amount (Rs)', 'Method', 'Sender #', 'TRX ID'];
        $q = $pdo->query("SELECT p.created_at, u.name AS sname, u.email, u.phone, c.title AS course, p.amount, p.method, p.sender_number, p.trx_id
                          FROM payments p JOIN users u ON u.id = p.student_id LEFT JOIN courses c ON c.id = p.course_id
                          WHERE p.status = 'pending' ORDER BY p.created_at DESC");
        foreach ($q->fetchAll() as $x) $rows[] = [$x['created_at'], $x['sname'], $x['email'], $x['phone'] ?: '-', $x['course'] ?: '-', $x['amount'], $x['method'], $x['sender_number'] ?: '-', $x['trx_id'] ?: '-'];
    }

    if ($headers) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel Urdu/UTF-8 fix
        fputcsv($out, $headers);
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
        exit;
    }
    flash('Export ki permission nahi.', 'error');
    redirect('exports.php');
}

$page_title = 'Exports';
require __DIR__ . '/../inc/header.php';
?>

<div class="grid md:grid-cols-3 gap-5">
  <?php if (can('p_payments')): ?>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="text-3xl mb-2"><?= icon('wallet',16) ?></div>
    <h3 class="font-bold">Total Sales</h3>
    <p class="text-sm text-gray-500 mt-1 mb-4">Approved payments — student, course, amount, method, TRX ID.</p>
    <a href="exports.php?type=sales" class="inline-block text-white text-sm px-5 py-2 rounded font-semibold" style="background:#c74b25"><?= icon('arrow-down',16) ?> Download CSV</a>
  </div>
  <?php endif; ?>
  <?php if (can('p_students')): ?>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="text-3xl mb-2"><?= icon('graduation-cap',16) ?></div>
    <h3 class="font-bold">Enrolled Students</h3>
    <p class="text-sm text-gray-500 mt-1 mb-4">Kaun kis course me — enroll date + progress % ke sath.</p>
    <a href="exports.php?type=enrolled" class="inline-block text-white text-sm px-5 py-2 rounded font-semibold" style="background:#c74b25"><?= icon('arrow-down',16) ?> Download CSV</a>
  </div>
  <?php endif; ?>
  <?php if (can('p_payments')): ?>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="text-3xl mb-2"><?= icon('clock',16) ?></div>
    <h3 class="font-bold">Pending Payments</h3>
    <p class="text-sm text-gray-500 mt-1 mb-4">Verify ke muntazir — screenshot check list.</p>
    <a href="exports.php?type=pending" class="inline-block text-white text-sm px-5 py-2 rounded font-semibold" style="background:#c74b25"><?= icon('arrow-down',16) ?> Download CSV</a>
  </div>
  <?php endif; ?>
</div>
<p class="text-xs text-gray-400 mt-6">CSV Excel me khulta hai — "Save As → Excel Workbook" karke .xlsx bana sakte hain.</p>

<?php require __DIR__ . '/../inc/footer.php'; ?>
