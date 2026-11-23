<?php
// Student: Vault item print view → "Save as PDF" (cheat-sheet)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') { http_response_code(403); die('Students only.'); }
$pdo = db();
ensure_batch_tables();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT v.*, c.title AS course_title FROM vault_resources v LEFT JOIN courses c ON c.id = v.course_id WHERE v.id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) die('Nahi mila.');

// access: global ya enrolled course ka
if ($r['course_id']) {
    $st = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?');
    $st->execute([$u['id'], $r['course_id']]);
    if (!$st->fetch()) { http_response_code(403); die('Access nahi hai.'); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($r['title']) ?> — Cheat Sheet</title>
<style>
  body { font-family:'Segoe UI',system-ui,sans-serif; max-width:720px; margin:40px auto; padding:0 20px; color:#222; }
  h1 { font-size:24px; border-bottom:3px solid #c74b25; padding-bottom:10px; }
  .meta { color:#888; font-size:13px; margin:8px 0 20px; }
  .content { line-height:1.7; font-size:14px; }
  pre { background:#f4f4f4; padding:12px; border-radius:8px; overflow-x:auto; font-size:12px; }
  code { background:#f4f4f4; padding:1px 5px; border-radius:4px; font-size:12px; }
  .no-print { text-align:center; margin:20px 0; }
  .no-print button { background:#c74b25; color:#fff; border:none; padding:12px 32px; border-radius:10px; font-size:15px; font-weight:bold; cursor:pointer; }
  @media print { .no-print { display:none; } body { margin:0; } }
</style>
</head>
<body onload="window.print()">
  <div class="no-print"><button onclick="window.print()"><?= icon('printer',16) ?> Print / Save as PDF</button></div>
  <h1><?= e($r['title']) ?></h1>
  <div class="meta"><?= e(vault_category_label($r['category'])) ?><?= $r['course_title'] ? ' · ' . icon('book-open',16) . ' ' . e($r['course_title']) : '' ?> · DigitalMax LMS</div>
  <div class="content"><?= vault_markdown($r['content']) ?></div>
  <div class="no-print" style="color:#aaa; font-size:12px; margin-top:30px">Tip: Print dialog me "Save as PDF" chunein</div>
</body>
</html>
