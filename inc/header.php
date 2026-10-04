<?php
// DigitalMax LMS - Premium shared layout (sidebar + topbar)
// Expects: $page_title (string). Uses current_user() for nav.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/legal.php';
require_login();
$user = current_user();
$role = $user['role'];

// v44: Payment verification se pehle LMS access nahi — bina enrollment wala student
// sirf Payments (aur Profile) khol sakta hai; baqi sab payments.php par redirect.
$has_enrollment = true;
if ($role === 'student') {
    $st = db()->prepare('SELECT 1 FROM enrollments WHERE student_id = ? LIMIT 1');
    $st->execute([$user['id']]);
    $has_enrollment = (bool)$st->fetch();
    if (!$has_enrollment) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['payments.php', 'profile.php', 'logout.php'], true)) {
            flash('Pehle payment verify karwayein — uske baad hi LMS ka access milega.', 'error');
            redirect('payments.php');
        }
    }
}

function nav_items($role, $ann_unread = 0) {
    $base = ($role === 'student') ? '../student/' : '../admin/';
    if ($role === 'student') {
        $ann_label = 'Notice Board' . ($ann_unread > 0 ? " ($ann_unread)" : '');
        return [
            ['My Courses',  $base . 'index.php', 'graduation-cap'],
            [$ann_label, $base . 'announcements.php', 'bell'],
            ['Assignments', $base . 'assignments.php', 'clipboard-list'],
            ['Vault', $base . 'vault.php', 'archive'],
            ['Payments',    $base . 'payments.php', 'credit-card'],
            ['Course Catalog', $base . 'courses.php', 'play'],
            ['Earn with DigitalMax', $base . 'refer.php', 'wallet'],
            ['Profile',     $base . 'profile.php', 'user'],
        ];
    }
    // v37: frequency order — roz istemal hone wale oopar, config/settings neeche
    $items = [['Dashboard', $base . 'index.php', 'dashboard']];
    if (can('p_payments'))    $items[] = ['Payments', $base . 'payments.php', 'credit-card'];
    if (can('p_students'))    $items[] = ['Students', $base . 'students.php', 'users'];
    if (can('p_students'))    $items[] = ['Student Stats', $base . 'reports.php', 'bar-chart'];
    if (can('p_courses'))     $items[] = ['Batches', $base . 'batches.php', 'calendar'];
    if (can('p_courses'))     $items[] = ['Courses', $base . 'courses.php', 'book-open'];
    if (can('p_courses'))     $items[] = ['Announcements', $base . 'announcements.php', 'megaphone'];
    if (can('p_assignments')) { $items[] = ['Submissions', $base . 'submissions.php', 'inbox']; $items[] = ['Assignments', $base . 'assignments.php', 'clipboard-list']; }
    if (can('p_students'))       $items[] = ['Leads & Broadcast', $base . 'leads.php', 'send'];
    if (can('p_courses'))     $items[] = ['Vault', $base . 'vault.php', 'archive'];
    if (can('p_payments'))    $items[] = ['Affiliates & Payouts', $base . 'affiliates.php', 'wallet'];
    if (can('p_payments') || can('p_students')) $items[] = ['Exports', $base . 'exports.php', 'bar-chart'];
    if (can('p_team'))        $items[] = ['Team', $base . 'team.php', 'users'];
    if (can('p_tasks'))       $items[] = ['Tasks', $base . 'tasks.php', 'check-square'];
    if (can('p_payments'))    $items[] = ['Payment Methods', $base . 'payment_methods.php', 'wallet'];
    if (can('p_courses'))     $items[] = ['Community Rules', $base . 'community_rules.php', 'alert'];
    if (is_admin())           $items[] = ['Settings', $base . 'settings.php', 'settings'];
    if (is_admin())           $items[] = ['System & Backup', $base . 'backup.php', 'database'];
    return $items;
}
$ann_unread = ($role === 'student') ? student_unread_ann_count($user['id']) : 0;
$nav = nav_items($role, $ann_unread);
// v44: bina enrollment wale student ko sidebar me sirf Payments + Profile dikhao
if ($role === 'student' && !$has_enrollment) {
    $nav = array_values(array_filter($nav, function($n){
        return in_array(basename($n[1]), ['payments.php', 'profile.php'], true);
    }));
}
$current = basename($_SERVER['SCRIPT_NAME']);
$initial = mb_strtoupper(mb_substr(trim($user['name'] ?: 'U'), 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title ?? 'Dashboard') ?> — <?= e(setting('site_name', SITE_NAME)) ?></title>
<link rel="icon" type="image/png" href="../assets/favicon.png">
<link rel="stylesheet" href="../assets/premium.css?v=<?= filemtime(__DIR__ . '/../assets/premium.css') ?>">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="dm-body">
<div class="dm-shell">
  <div class="dm-scrim" id="dmScrim" onclick="document.body.classList.remove('dm-open')"></div>

  <!-- Sidebar -->
  <aside class="dm-sidebar" id="dmSidebar">
    <div class="dm-brand">
      <div class="dm-brand-row">
        <img src="../assets/logo.png" alt="logo" class="dm-logo">
        <div>
          <div class="dm-brand-name"><?= e(setting('site_name', SITE_NAME)) ?></div>
          <span class="dm-role-pill"><?= e(ucfirst($role)) ?> Panel</span>
        </div>
      </div>
    </div>
    <nav class="dm-nav">
      <?php foreach ($nav as [$label, $url, $icn]):
        $active = basename($url) === $current ? 'active' : '';
      ?>
        <a href="<?= e($url) ?>" class="<?= $active ?>"><?= icon($icn, 18) ?><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="dm-side-foot">
      <div class="dm-user-chip">
        <div class="dm-avatar dm-avatar-premium"><?= e($initial) ?></div>
        <div class="min-w-0"><div class="nm truncate"><?= e($user['name']) ?></div><div class="rl"><?= e($user['email']) ?></div></div>
      </div>
      <a href="../logout.php" class="dm-logout"><?= icon('log-out', 16) ?><span>Logout</span></a>
    </div>
  </aside>

  <!-- Main -->
  <div class="dm-main">
    <header class="dm-topbar">
      <button class="dm-burger" onclick="document.body.classList.toggle('dm-open')" aria-label="Menu"><?= icon('menu', 20) ?></button>
      <div>
        <div class="dm-crumb"><?= e(setting('site_name', SITE_NAME)) ?> · <?= e(ucfirst($role)) ?></div>
        <h1 class="dm-title"><?= e($page_title ?? 'Dashboard') ?></h1>
      </div>
      <div class="dm-topbar-right">
        <div class="dm-profile" id="dmProfile">
          <button type="button" class="dm-profile-btn" onclick="document.getElementById('dmProfile').classList.toggle('open')" aria-label="Profile menu">
            <span class="dm-avatar-premium"><?= e($initial) ?></span>
            <span class="dm-profile-meta"><b><?= e($user['name']) ?></b><i><?= e(ucfirst($role)) ?></i></span>
            <?= icon('chevron-down', 16) ?>
          </button>
          <div class="dm-profile-menu">
            <div class="pm-head"><b><?= e($user['name']) ?></b><span><?= e($user['email']) ?></span></div>
            <?php if ($role === 'student'): ?>
            <a href="../student/profile.php"><?= icon('user', 16) ?> My Profile</a>
            <?php endif; ?>
            <a href="../logout.php" class="danger"><?= icon('log-out', 16) ?> Logout</a>
          </div>
        </div>
      </div>
      <script>
      document.addEventListener('click', function(e){
        var p = document.getElementById('dmProfile');
        if (p && !p.contains(e.target)) p.classList.remove('open');
      });
      </script>
    </header>

    <main class="dm-content">
      <?= flash() ?>
