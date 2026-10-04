<?php
// Student: course completion certificate (printable / save as PDF)
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
require_login();
$u = current_user();
if ($u['role'] !== 'student') redirect('../admin/index.php');
$pdo = db();

$course_id = (int)($_GET['course_id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM courses WHERE id = ? AND status = "published"');
$st->execute([$course_id]);
$course = $st->fetch();
if (!$course) die('Course nahi mila.');

$st = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?');
$st->execute([$u['id'], $course_id]);
if (!$st->fetch()) die('Aap is course me enroll nahi hain.');

if (!course_completed($u['id'], $course_id)) {
    flash('Certificate sirf course 100% complete karne par milta hai.', 'error');
    redirect('course.php?course_id=' . $course_id);
}

$cert = get_certificate($u['id'], $course_id);
$completed_on = completion_date($u['id'], $course_id);
$date_str = $completed_on ? date('d F Y', strtotime($completed_on)) : date('d F Y');
$verify_url = 'https://lms.digitalmx.pk/verify.php?code=' . urlencode($cert['cert_code']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Certificate — <?= e($course['title']) ?></title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Georgia, 'Times New Roman', serif; background:#e2e8f0; min-height:100vh;
         display:flex; flex-direction:column; align-items:center; padding:28px 14px; }
  .toolbar { display:flex; gap:10px; margin-bottom:22px; font-family:'Segoe UI',system-ui,sans-serif; }
  .btn { display:inline-block; padding:10px 22px; border-radius:10px; font-weight:700; font-size:14px;
         text-decoration:none; cursor:pointer; border:none; }
  .btn-print { background:linear-gradient(90deg,#c74b25,#e8823f); color:#fff; }
  .btn-back { background:#fff; color:#444; border:1px solid #ccc; }
  #cert { width:100%; max-width:1020px; aspect-ratio:1.414/1; background:#fdf6ec; padding:14px;
          box-shadow:0 12px 44px rgba(0,0,0,.28); }
  .cert-border { border:4px solid #c9a227; height:100%; padding:10px; }
  .cert-inner { border:1px solid #c9a227; height:100%; display:flex; flex-direction:column;
                align-items:center; justify-content:center; text-align:center; padding:34px 40px;
                background:radial-gradient(ellipse at center, #fdf6ec 55%, #f4e8cf 100%); }
  .brand { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
  .brand img { width:52px; height:52px; border-radius:12px; }
  .brand span { font-family:'Segoe UI',sans-serif; font-weight:800; font-size:18px; color:#c74b25; letter-spacing:1px; }
  .title { font-size:44px; letter-spacing:6px; color:#2b2b2b; font-weight:700; }
  .divider { display:flex; align-items:center; gap:12px; margin:14px 0; width:60%; }
  .divider::before, .divider::after { content:''; flex:1; height:2px; background:#c9a227; }
  .diamond { width:10px; height:10px; background:#c9a227; transform:rotate(45deg); }
  .presented { font-size:15px; color:#777; font-style:italic; }
  .student-name { font-size:52px; color:#1d3448; margin:8px 0; font-weight:700; }
  .course-line { font-size:15px; color:#777; font-style:italic; }
  .course-title { font-size:30px; color:#c74b25; font-weight:700; margin:6px 0 4px; }
  .date-line { font-size:14px; color:#555; margin-top:10px; }
  .foot { display:flex; justify-content:space-between; width:100%; margin-top:34px; align-items:flex-end; }
  .foot .left { text-align:left; font-family:'Segoe UI',sans-serif; }
  .foot .cert-id { font-size:12px; color:#555; letter-spacing:1px; }
  .foot .verify { font-size:11px; color:#999; margin-top:4px; }
  .foot .sign { text-align:center; }
  .foot .sign .line { width:200px; border-top:2px solid #333; margin:0 auto 6px; }
  .foot .sign .who { font-size:13px; color:#333; font-family:'Segoe UI',sans-serif; }
  @media print {
    body { background:#fff; padding:0; display:block; }
    .toolbar { display:none !important; }
    #cert { max-width:none; box-shadow:none; }
    @page { size:A4 landscape; margin:0; }
  }
</style>
</head>
<body>

<div class="toolbar">
  <button class="btn btn-print" onclick="window.print()"><?= icon('printer',16) ?> Print / Save as PDF</button>
  <a class="btn btn-back" href="course.php?course_id=<?= $course_id ?>">← Back to course</a>
</div>

<div id="cert">
  <div class="cert-border">
    <div class="cert-inner">
      <div class="brand">
        <img src="../assets/logo.png" alt="DigitalMax LMS">
        <span>DIGITALMAX LMS</span>
      </div>
      <div class="title">CERTIFICATE</div>
      <div class="title" style="font-size:22px; letter-spacing:10px; margin-top:2px;">OF COMPLETION</div>
      <div class="divider"><span class="diamond"></span></div>
      <div class="presented">This certificate is proudly presented to</div>
      <div class="student-name"><?= e($u['name']) ?></div>
      <div class="course-line">for successfully completing the course</div>
      <div class="course-title"><?= e($course['title']) ?></div>
      <div class="date-line">Completed on <b><?= e($date_str) ?></b></div>
      <div class="foot">
        <div class="left">
          <div class="cert-id">Certificate ID: <b><?= e($cert['cert_code']) ?></b></div>
          <div class="verify">Verify at: <?= e($verify_url) ?></div>
        </div>
        <div class="sign">
          <div class="line"></div>
          <div class="who">Founder, DigitalMax</div>
        </div>
      </div>
    </div>
  </div>
</div>

<p style="font-family:'Segoe UI',sans-serif;font-size:12px;color:#8a8272;margin-top:16px;" class="toolbar">
  Tip: "Print / Save as PDF" dabayein → Destination me "Save as PDF" select karein.
</p>

</body>
</html>
