<?php
// DigitalMax LMS - Web installer (sirf pehli dafa chalana hai, phir DELETE kar dein)
session_start();
require_once __DIR__ . '/inc/helpers.php'; // csrf ke liye (DB ke baghair safe functions)
$error = '';
$success = false;

$already = false;
if (file_exists(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $st = $pdo->query("SELECT v FROM settings WHERE k = 'installed'");
        $already = ($st && $st->fetchColumn() === '1');
    } catch (Exception $ex) { /* not installed */ }
}

if (!$already && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db_host  = trim($_POST['db_host'] ?? 'localhost');
    $db_name  = trim($_POST['db_name'] ?? '');
    $db_user  = trim($_POST['db_user'] ?? '');
    $db_pass  = $_POST['db_pass'] ?? '';
    $a_name   = trim($_POST['a_name'] ?? '');
    $a_email  = trim($_POST['a_email'] ?? '');
    $a_pass   = $_POST['a_pass'] ?? '';

    if ($db_name === '' || $db_user === '' || $a_name === '' || $a_email === '' || $a_pass === '') {
        $error = 'Tamam fields bharna zaroori hain.';
    } elseif (!filter_var($a_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Admin email theek nahi hai.';
    } elseif (strlen($a_pass) < 6) {
        $error = 'Admin password kam az kam 6 characters ka ho.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sql = file_get_contents(__DIR__ . '/install.sql');
            // statements ko alag kar ke run karna
            $stmts = array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql))));
            foreach ($stmts as $s) { if ($s !== '') $pdo->exec($s); }

            $hash = password_hash($a_pass, PASSWORD_DEFAULT);
            $st = $pdo->prepare('INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, "admin", "active")');
            $st->execute([$a_name, $a_email, $hash]);

            $cfg = "<?php\n"
                . "define('DB_HOST', " . var_export($db_host, true) . ");\n"
                . "define('DB_NAME', " . var_export($db_name, true) . ");\n"
                . "define('DB_USER', " . var_export($db_user, true) . ");\n"
                . "define('DB_PASS', " . var_export($db_pass, true) . ");\n"
                . "define('SITE_NAME', 'DigitalMax LMS');\n"
                . "define('SITE_URL', 'https://lms.digitalmx.pk');\n"
                . "define('CURRENCY', 'Rs');\n"
                . "define('MAX_UPLOAD_MB', 10);\n";
            file_put_contents(__DIR__ . '/config.php', $cfg);
            $success = true;
        } catch (Exception $ex) {
            $error = 'Database error: ' . $ex->getMessage()
                . ' — Hostinger ke hPanel me database + user bana kar dobara try karein.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DigitalMax LMS — Install</title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-lg">
  <h1 class="text-2xl font-bold mb-1 flex items-center gap-2" style="color:#c74b25"><img src="assets/logo.png" alt="logo" class="w-9 h-9 rounded-lg">DigitalMax LMS</h1>
  <p class="text-gray-500 mb-6">Installer — sirf pehli dafa</p>

  <?php if ($already): ?>
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded">
      LMS pehle se installed hai. Security ke liye <b>install.php</b> file ko server se <b>delete</b> kar dein.
    </div>
  <?php elseif ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
      <?= icon('check-circle',16) ?> Install ho gaya! Ab <a href="login.php" class="underline font-semibold">login page</a> par jayein.
    </div>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
      <?= icon('alert',16) ?> Zaroori: abhi <b>install.php</b> ko server se delete kar dein.
    </div>
  <?php else: ?>
    <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="space-y-4">
  <?= csrf_field() ?>
      <h2 class="font-semibold text-gray-700">Database (Hostinger hPanel → Databases)</h2>
      <input name="db_host" value="localhost" placeholder="DB Host" class="w-full border rounded px-3 py-2">
      <input name="db_name" placeholder="Database name" class="w-full border rounded px-3 py-2">
      <input name="db_user" placeholder="Database user" class="w-full border rounded px-3 py-2">
      <input name="db_pass" type="password" placeholder="Database password" class="w-full border rounded px-3 py-2">
      <h2 class="font-semibold text-gray-700 pt-2">Admin account</h2>
      <input name="a_name" placeholder="Apna naam" class="w-full border rounded px-3 py-2">
      <input name="a_email" type="email" placeholder="Admin email" class="w-full border rounded px-3 py-2">
      <input name="a_pass" type="password" placeholder="Admin password (min 6)" class="w-full border rounded px-3 py-2">
      <button class="w-full text-white font-semibold py-2 rounded" style="background:#c74b25">Install LMS</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
