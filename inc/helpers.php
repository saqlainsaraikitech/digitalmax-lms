<?php
// DigitalMax LMS - Helper functions

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function flash($msg = null, $type = 'success') {
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $cls = $f['type'] === 'error' ? 'error' : 'success';
        $icon = $f['type'] === 'error' ? icon('alert',16) : icon('check-circle',16);
        return '<div class="dm-flash ' . $cls . '"><span>' . $icon . '</span><span>' . e($f['msg']) . '</span></div>';
    }
    return '';
}

function setting($key, $default = '') {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $st = db()->prepare('SELECT v FROM settings WHERE k = ?');
        $st->execute([$key]);
        $row = $st->fetch();
        $cache[$key] = $row ? $row['v'] : $default;
    } catch (Exception $ex) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/** YouTube URL (watch / youtu.be / shorts / embed) se video ID nikalna */
function youtube_id($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) return $url; // already an ID
    $patterns = [
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $url, $m)) return $m[1];
    }
    return '';
}

function youtube_embed($url) {
    $id = youtube_id($url);
    if (!$id) return '';
    // Anti-leak params: modest branding, no related videos, no annotations, keyboard shortcuts off
    // v40: controls=1 (native play/seek/speed = 100% reliable). Share/title/Watch-on-YouTube
    // ko page par strategic transparent shields block karte hain (lecture.php).
    // v45: loop=1 + playlist=ID — video khatam hone par endscreen (clickable "Hire Me / More videos"
    // elements) kabhi nahi aata; video dobara shuru ho jata hai. API ke baghair bhi kaam karta hai.
    $params = 'controls=1&modestbranding=1&rel=0&iv_load_policy=3&disablekb=1&loop=1&playlist=' . $id;
    return 'https://www.youtube.com/embed/' . $id . '?' . $params;
}

/** File upload helper. Returns saved relative path or throws.
 *  $image_max_kb > 0 ho to images auto-compress (GD) kar ke us limit tak layi jati hain. */
function upload_file($field, $subdir, array $allowed_exts, $image_max_kb = 0) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed (code ' . $f['error'] . ').');
    }
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        throw new Exception('File ' . MAX_UPLOAD_MB . 'MB se bari hai.');
    }
    // double-extension trick block (shell.php.jpg)
    if (preg_match('/\.(php[0-9]*|phtml|phar|sh|bash|pl|py|cgi|asp|aspx|jsp|js|html?)(\.|$)/i', $f['name'])) {
        throw new Exception('Is file ki ijazat nahi hai.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts, true)) {
        throw new Exception('Sirf in formats ki ijazat hai: ' . implode(', ', $allowed_exts));
    }
    $dir = __DIR__ . '/../uploads/' . trim($subdir, '/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new Exception('File save nahi ho saki.');
    }
    $rel = 'uploads/' . trim($subdir, '/') . '/' . $name;
    if ($image_max_kb > 0) {
        $rel = optimize_image($rel, $image_max_kb);
    }
    return $rel;
}

/**
 * Image ko resize + compress karo (PHP GD).
 * Max 1600px, WebP (ya JPEG) me max $max_kb tak. Original heavy file delete.
 * Returns nayi relative path (ya purani, agar GD na ho).
 */
function optimize_image($rel_path, $max_kb = 300) {
    $abs = __DIR__ . '/../' . ltrim($rel_path, '/');
    if (!file_exists($abs) || !function_exists('imagecreatefromjpeg') || !function_exists('getimagesize')) {
        return $rel_path;
    }
    $info = @getimagesize($abs);
    if (!$info) return $rel_path;
    [$w, $h, $type] = $info;
    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($abs); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($abs); break;
        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($abs); break;
        case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : false; break;
        default: return $rel_path;
    }
    if (!$src) return $rel_path;

    // resize: sab se lambi side max 1600px (transparency white par flatten)
    $flat = ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF);
    $max_dim = 1600;
    if (max($w, $h) > $max_dim || $flat) {
        $r = max($w, $h) > $max_dim ? $max_dim / max($w, $h) : 1;
        $nw = max(1, (int)($w * $r)); $nh = max(1, (int)($h * $r));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }

    $dir = dirname($abs);
    $base = pathinfo($abs, PATHINFO_FILENAME);
    $target = $max_kb * 1024;
    if (function_exists('imagewebp')) {
        $out = $dir . '/' . $base . '.webp';
        $q = 85;
        imagewebp($src, $out, $q);
        while (@filesize($out) > $target && $q > 35) { $q -= 10; imagewebp($src, $out, $q); }
        $new_rel = preg_replace('/\.[a-z0-9]+$/i', '.webp', $rel_path);
    } else {
        $out = $dir . '/' . $base . '.jpg';
        $q = 85;
        imagejpeg($src, $out, $q);
        while (@filesize($out) > $target && $q > 45) { $q -= 10; imagejpeg($src, $out, $q); }
        $new_rel = preg_replace('/\.[a-z0-9]+$/i', '.jpg', $rel_path);
    }
    imagedestroy($src);
    if (file_exists($out) && realpath($out) !== realpath($abs)) {
        @unlink($abs); // original heavy file delete — storage safe
    }
    return $new_rel;
}

/** Kya student ne course 100% complete kiya? */
function course_completed($student_id, $course_id) {
    return course_progress($student_id, $course_id) === 100;
}

/** Course complete hone ki tareekh (akhri lecture kab complete hua) */
function completion_date($student_id, $course_id) {
    $st = db()->prepare('SELECT MAX(lp.completed_at) FROM lecture_progress lp JOIN lectures l ON l.id = lp.lecture_id WHERE lp.student_id = ? AND l.course_id = ?');
    $st->execute([$student_id, $course_id]);
    return $st->fetchColumn();
}

/** v53: Lecture "naya" hai? (pichle 7 din me add hua) */
function is_new_lecture($l) {
    if (empty($l['created_at'])) return false;
    $t = strtotime($l['created_at']);
    return $t && (time() - $t) < (7 * 86400);
}
/** v53: Stylish red NEW badge */
function new_badge() {
    return '<span class="lec-new">New</span>';
}

/** v54: Fee status badge (paid = green, pending = amber) */
function fee_badge($status) {
    if (($status ?? 'pending') === 'paid')
        return '<span class="fee-badge paid">' . icon('check-circle', 12) . ' Paid</span>';
    return '<span class="fee-badge pending">' . icon('clock', 12) . ' Pending</span>';
}

/** v54b: "12:34" / "1:02:15" → seconds */
function duration_to_seconds($str) {
    $str = trim((string)$str);
    if ($str === '' || !preg_match('/^(\d+:)*\d+$/', $str)) return 0;
    $parts = array_map('intval', explode(':', $str));
    $sec = 0;
    foreach ($parts as $p) $sec = $sec * 60 + $p;
    return $sec;
}
/** v54b: Lecture complete ke liye kitne seconds dekhna lazmi (3 min, ya chhoti video par 90%) */
function required_watch_sec($duration_str) {
    $d = duration_to_seconds($duration_str);
    if ($d <= 0) return 180;
    return (int)min(180, round($d * 0.9));
}

/** Certificate row lao, na ho to banao (unique verification code ke sath) */
function get_certificate($student_id, $course_id) {
    $pdo = db();
    $fetch = function() use ($pdo, $student_id, $course_id) {
        $st = $pdo->prepare('SELECT * FROM certificates WHERE student_id = ? AND course_id = ?');
        $st->execute([$student_id, $course_id]);
        return $st->fetch();
    };
    try {
        if ($row = $fetch()) return $row;
    } catch (Exception $ex) {
        // purani install: table banao (self-healing)
        $pdo->exec('CREATE TABLE IF NOT EXISTS certificates (
          id INT AUTO_INCREMENT PRIMARY KEY,
          student_id INT NOT NULL,
          course_id INT NOT NULL,
          cert_code VARCHAR(32) NOT NULL UNIQUE,
          issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_cert (student_id, course_id),
          FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    if ($row = $fetch()) return $row;
    $code = 'DML-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    try {
        $pdo->prepare('INSERT INTO certificates (student_id, course_id, cert_code) VALUES (?,?,?)')
            ->execute([$student_id, $course_id, $code]);
    } catch (PDOException $ex) { /* race — dobara read karein */ }
    return $fetch();
}

/** Student ki course progress % */
function course_progress($student_id, $course_id) {
    // v21: student ke batch ko sirf visible lectures gine jayenge
    // (purani enrollment jisme batch NULL hai → sab visible, purana behavior)
    $batch_id = student_batch_id($student_id, $course_id);
    return course_progress_batch($student_id, $course_id, $batch_id);
}

function money($amount) {
    return CURRENCY . ' ' . number_format((float)$amount, 0);
}

/** Pakistani number ko wa.me format me (923001234567) */
function wa_number($num) {
    $d = preg_replace('/\D/', '', (string)$num);
    if ($d === '') return '';
    if (strpos($d, '92') === 0) return $d;
    if (strpos($d, '0') === 0) return '92' . substr($d, 1);
    if (strlen($d) === 10) return '92' . $d;
    return $d;
}

function wa_link($number, $text) {
    $num = wa_number($number);
    if ($num === '') return '';
    return 'https://wa.me/' . $num . '?text=' . rawurlencode($text);
}

/** Floating WhatsApp help button (student pages + login). Number: Admin → Settings */
function whatsapp_float() {
    $num = trim(setting('whatsapp_number', ''));
    if ($num === '') return '';
    $link = wa_link($num, 'Assalam-o-Alaikum, mujhe lms.digitalmx.pk par help chahiye.');
    if (!$link) return '';
    return '<a href="' . e($link) . '" target="_blank" rel="noopener" title="WhatsApp par help lein"'
        . ' style="position:fixed;right:20px;bottom:20px;z-index:60;width:58px;height:58px;border-radius:50%;'
        . 'background:#25D366;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.35);">'
        . '<svg width="32" height="32" viewBox="0 0 24 24" fill="#ffffff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg></a>';
}

/** Referral code: DM + 5 unambiguous chars (misal: DM7X2K9) */
function generate_referral_code() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return 'DM' . substr(str_shuffle(str_repeat($chars, 5)), 0, 5);
}

/** Student ka referral code — na ho to bana kar save karo (self-healing) */
function my_referral_code($user_id) {
    $pdo = db();
    try {
        $st = $pdo->prepare('SELECT referral_code FROM users WHERE id = ?');
        $st->execute([$user_id]);
        $code = $st->fetchColumn();
        if ($code) return $code;
        for ($i = 0; $i < 5; $i++) {
            $code = generate_referral_code();
            $up = $pdo->prepare('UPDATE users SET referral_code = ? WHERE id = ? AND referral_code IS NULL');
            $up->execute([$code, $user_id]);
            if ($up->rowCount() > 0) return $code;
        }
    } catch (PDOException $ex) {
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN referral_code VARCHAR(16) NULL DEFAULT NULL, ADD UNIQUE KEY uniq_refcode (referral_code)');
            return my_referral_code($user_id);
        } catch (Exception $ex2) {}
    }
    return '';
}

/** Affiliate commission % (admin settings se) */
function affiliate_percent() {
    return (float)setting('affiliate_commission_percent', 10);
}

function status_badge($status) {
    $map = [
        'published'   => 'bg-green-100 text-green-800',
        'draft'       => 'bg-gray-200 text-gray-700',
        'active'      => 'bg-green-100 text-green-800',
        'inactive'    => 'bg-gray-200 text-gray-700',
        'pending'     => 'bg-yellow-100 text-yellow-800',
        'approved'    => 'bg-green-100 text-green-800',
        'rejected'    => 'bg-red-100 text-red-700',
        'banned'      => 'bg-red-600 text-white',
        'submitted'   => 'bg-blue-100 text-blue-800',
        'graded'      => 'bg-green-100 text-green-800',
        'in_progress' => 'bg-blue-100 text-blue-800',
        'done'        => 'bg-green-100 text-green-800',
        'paid'        => 'bg-green-100 text-green-800',
        'unpaid'      => 'bg-red-100 text-red-700',
        'partial'     => 'bg-yellow-100 text-yellow-800',
    ];
    $cls = $map[$status] ?? 'bg-gray-200 text-gray-700';
    $label = ucfirst(str_replace('_', ' ', $status));
    return '<span class="px-2 py-0.5 rounded text-xs font-medium ' . $cls . '">' . e($label) . '</span>';
}

/* ============ CSRF protection ============ */
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
function verify_csrf() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sent = $_POST['_csrf'] ?? '';
    $real = $_SESSION['_csrf'] ?? '';
    if ($real === '' || !hash_equals($real, (string)$sent)) {
        http_response_code(403);
        die('Security check fail — form dobara load karke try karein.');
    }
}

/* ============ Course duration ============ */
/** "12:30", "1:05:00", "15", "15 min" → minutes (float) */
function duration_to_minutes($s) {
    $s = trim((string)$s);
    if ($s === '') return 0;
    if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $s, $m)) {
        $min = (int)$m[1] * 60 + (int)$m[2];
        if (isset($m[3]) && $m[3] !== '') return $min + (int)$m[3] / 60;
        return $min;
    }
    if (preg_match('/(\d+(?:\.\d+)?)/', $s, $m)) return (float)$m[1];
    return 0;
}
function format_minutes($min) {
    $min = (int)round($min);
    if ($min <= 0) return '';
    if ($min < 60) return $min . ' min';
    $h = intdiv($min, 60); $m = $min % 60;
    return $h . 'h' . ($m ? ' ' . $m . 'm' : '');
}
/** Course ki tamam lectures ka total duration (misal: "2h 15m") */
function course_duration($course_id) {
    $st = db()->prepare('SELECT duration FROM lectures WHERE course_id = ?');
    $st->execute([$course_id]);
    $total = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $d) $total += duration_to_minutes($d);
    return format_minutes($total);
}

/* ============ Batches, lecture visibility, vault (v21) ============ */

/** Nayi tables self-healing (purani install par auto-create) */
function ensure_batch_tables() {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = db();
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS batches (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            batch_name VARCHAR(120) NOT NULL,
            start_date DATE NULL, end_date DATE NULL,
            duration_months INT NOT NULL DEFAULT 3,
            status ENUM('upcoming','active','completed') NOT NULL DEFAULT 'upcoming',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS lecture_batches (
            lecture_id INT NOT NULL, batch_id INT NOT NULL,
            PRIMARY KEY (lecture_id, batch_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS vault_resources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(200) NOT NULL,
            category ENUM('niche','master_prompt','ad_copy','supplier_list','ai','original','copyright') NOT NULL DEFAULT 'master_prompt',
            content LONGTEXT NOT NULL, course_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // v39: vault me 3 nayi categories (AI / Original / Copyright) — purani DB auto-upgrade
        $vcat = $pdo->query("SHOW COLUMNS FROM vault_resources LIKE 'category'")->fetch();
        if ($vcat && strpos($vcat['Type'], "'ai'") === false) {
            $pdo->exec("ALTER TABLE vault_resources MODIFY COLUMN category ENUM('niche','master_prompt','ad_copy','supplier_list','ai','original','copyright') NOT NULL DEFAULT 'master_prompt'");
        }
        $cols = $pdo->query("SHOW COLUMNS FROM enrollments LIKE 'batch_id'")->fetch();
        if (!$cols) $pdo->exec("ALTER TABLE enrollments ADD COLUMN batch_id INT NULL DEFAULT NULL");
        $cols = $pdo->query("SHOW COLUMNS FROM lectures LIKE 'share_all_batches'")->fetch();
        if (!$cols) $pdo->exec("ALTER TABLE lectures ADD COLUMN share_all_batches TINYINT(1) NOT NULL DEFAULT 1");
        // v53: lectures par "NEW" badge — created_at column (purane lectures ko purani date taake NEW na lagein)
        $cols = $pdo->query("SHOW COLUMNS FROM lectures LIKE 'created_at'")->fetch();
        if (!$cols) {
            $pdo->exec("ALTER TABLE lectures ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            $pdo->exec("UPDATE lectures SET created_at = '2020-01-01 00:00:00'");
        }
        // v54: fee tracking — enrollment par fee_status + fee_amount
        $cols = $pdo->query("SHOW COLUMNS FROM enrollments LIKE 'fee_status'")->fetch();
        if (!$cols) $pdo->exec("ALTER TABLE enrollments ADD COLUMN fee_status ENUM('paid','pending') NOT NULL DEFAULT 'pending'");
        $cols = $pdo->query("SHOW COLUMNS FROM enrollments LIKE 'fee_amount'")->fetch();
        if (!$cols) $pdo->exec("ALTER TABLE enrollments ADD COLUMN fee_amount DECIMAL(10,2) NULL DEFAULT NULL");
        // v54b: 3-min watch rule — video_progress par watched_sec (actual dekha hua time)
        $cols = $pdo->query("SHOW COLUMNS FROM video_progress LIKE 'watched_sec'")->fetch();
        if (!$cols) $pdo->exec("ALTER TABLE video_progress ADD COLUMN watched_sec INT NOT NULL DEFAULT 0");
    } catch (Exception $ex) { /* table pehle se hai ya DB purani — upgrade-v21.sql chalana */ }
}

/** Batch ka auto-status: dates se compute (upcoming / active / completed) */
function batch_status($b) {
    $today = date('Y-m-d');
    if (!empty($b['end_date']) && $b['end_date'] < $today) return 'completed';
    if (!empty($b['start_date']) && $b['start_date'] > $today) return 'upcoming';
    return 'active';
}
function batch_status_label($s) {
    return ['upcoming' => 'Upcoming', 'active' => 'Active', 'completed' => 'Ended'][$s] ?? $s;
}
/** Batch khatam hone me kitne din (0 = khatam) */
function batch_days_left($b) {
    if (empty($b['end_date'])) return null;
    $d = (int)ceil((strtotime($b['end_date']) - strtotime(date('Y-m-d'))) / 86400);
    return max(0, $d);
}
/** Student dashboard / player header badge */
function batch_badge($b) {
    if (!$b) return '';
    $s = batch_status($b);
    $name = e($b['batch_name']);
    if ($s === 'active') {
        $days = batch_days_left($b);
        $extra = $days !== null ? ", ends in $days day" . ($days === 1 ? '' : 's') : '';
        return '<span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded bg-green-50 text-green-800">' . icon('dot',8) . $name . ' · Active' . e($extra) . '</span>';
    }
    if ($s === 'completed') {
        return '<span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded bg-stone-100 text-stone-700">' . icon('dot',8) . $name . ' · Completed, archive access</span>';
    }
    $sd = !empty($b['start_date']) ? date('d M Y', strtotime($b['start_date'])) : '';
    return '<span class="inline-flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded bg-amber-50 text-amber-800">' . icon('dot', 8) . $name . ' · Upcoming' . ($sd ? ', starts ' . e($sd) : '') . '</span>';
}
/** Admin list ke liye chhota badge: [Batch 1 - Active] */
function batch_badge_short($b) {
    if (!$b) return '<span class="text-xs text-gray-400">—</span>';
    $s = batch_status($b);
    $cls = $s === 'active' ? 'bg-green-100 text-green-700' : ($s === 'completed' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
    return '<span class="text-xs font-medium px-2 py-0.5 rounded ' . $cls . '">' . e($b['batch_name']) . ' · ' . batch_status_label($s) . '</span>';
}
/** Course ke batches */
function course_batches($course_id) {
    ensure_batch_tables();
    $st = db()->prepare('SELECT * FROM batches WHERE course_id = ? ORDER BY start_date, id');
    $st->execute([$course_id]);
    return $st->fetchAll();
}
/** Student ka is course me batch (row ya null) */
function student_batch($student_id, $course_id) {
    ensure_batch_tables();
    $st = db()->prepare('SELECT b.* FROM enrollments e LEFT JOIN batches b ON b.id = e.batch_id WHERE e.student_id = ? AND e.course_id = ? LIMIT 1');
    $st->execute([$student_id, $course_id]);
    $b = $st->fetch();
    return ($b && $b['id']) ? $b : null;
}
function student_batch_id($student_id, $course_id) {
    $b = student_batch($student_id, $course_id);
    return $b ? (int)$b['id'] : null;
}
/** Lecture is batch ko visible? (batch_id null = purani enrollment → sab visible) */
function lecture_visible($lec, $batch_id) {
    if (!empty($lec['share_all_batches'])) return true;
    if ($batch_id === null) return true;
    $st = db()->prepare('SELECT 1 FROM lecture_batches WHERE lecture_id = ? AND batch_id = ? LIMIT 1');
    $st->execute([$lec['id'], $batch_id]);
    return (bool)$st->fetch();
}
/** Course ke un lectures ki IDs jo is batch ko visible hain (bulk, 2 queries) */
function batch_visible_lecture_ids($course_id, $batch_id) {
    $pdo = db();
    $lecs = $pdo->prepare('SELECT id, share_all_batches FROM lectures WHERE course_id = ?');
    $lecs->execute([$course_id]);
    $rows = $lecs->fetchAll();
    $ids = array_map(fn($l) => (int)$l['id'], $rows);
    if ($batch_id === null) return $ids;
    $allowed = [];
    if ($ids) {
        $in = implode(',', $ids);
        foreach ($pdo->query("SELECT lecture_id FROM lecture_batches WHERE lecture_id IN ($in) AND batch_id = " . (int)$batch_id)->fetchAll(PDO::FETCH_COLUMN) as $lid)
            $allowed[(int)$lid] = true;
    }
    $out = [];
    foreach ($rows as $l) {
        if (!empty($l['share_all_batches']) || isset($allowed[(int)$l['id']])) $out[] = (int)$l['id'];
    }
    return $out;
}
/** Batch-scoped progress (sirf visible lectures gine jayenge) */
function course_progress_batch($student_id, $course_id, $batch_id = null) {
    $visible = batch_visible_lecture_ids($course_id, $batch_id);
    $total = count($visible);
    if ($total === 0) return 0;
    $in = implode(',', $visible);
    $done = db()->prepare("SELECT COUNT(*) FROM lecture_progress WHERE student_id = ? AND lecture_id IN ($in)");
    $done->execute([$student_id]);
    return (int)round((int)$done->fetchColumn() / $total * 100);
}

/** Vault: safe mini-markdown render (pehle escape, phir formatting) */
function vault_markdown($text) {
    $t = e($text);
    // code blocks ```...```
    $t = preg_replace_callback('/```(.*?)```/s', fn($m) => '<pre class="bg-gray-900 text-green-300 text-xs p-3 rounded-lg overflow-x-auto my-2">' . $m[1] . '</pre>', $t);
    // headings
    $t = preg_replace('/^### (.+)$/m', '<div class="font-bold mt-3 mb-1">$1</div>', $t);
    $t = preg_replace('/^## (.+)$/m', '<div class="font-extrabold text-lg mt-3 mb-1">$1</div>', $t);
    // bold / italic / inline code
    $t = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $t);
    $t = preg_replace('/`([^`]+)`/', '<code class="bg-gray-100 text-red-700 px-1 rounded text-xs">$1</code>', $t);
    // bullet lists
    $t = preg_replace('/^(?:-|\*) (.+)$/m', '<li class="ml-5 list-disc">$1</li>', $t);
    // numbered lists
    $t = preg_replace('/^\d+\. (.+)$/m', '<li class="ml-5 list-decimal">$1</li>', $t);
    return nl2br($t);
}
function vault_category_label($c) {
    return ['niche' => icon('target',16) . ' Niche', 'master_prompt' => icon('bot',16) . ' Master Prompt', 'ad_copy' => icon('edit',16) . ' Ad Copy', 'supplier_list' => icon('package',16) . ' Supplier List', 'ai' => icon('sparkles',16) . ' AI', 'original' => icon('star',16) . ' Original', 'copyright' => icon('shield',16) . ' Copyright'][$c] ?? $c;
}

/* ============ Announcements & Notice Board (v22) ============ */

function ensure_ann_tables() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            target_type ENUM('all_students','specific_course','specific_batch') NOT NULL DEFAULT 'all_students',
            course_id INT NULL, batch_id INT NULL,
            priority ENUM('normal','urgent','pinned') NOT NULL DEFAULT 'normal',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->exec("CREATE TABLE IF NOT EXISTS announcement_reads (
            announcement_id INT NOT NULL, student_id INT NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (announcement_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $ex) {}
}

/** Rich-text HTML sanitize: sirf safe tags, javascript: links hatao */
function clean_announcement_html($html) {
    $html = strip_tags($html, '<b><strong><i><em><u><a><ul><ol><li><p><br>');
    $html = preg_replace_callback('/<a\b[^>]*>/i', function ($m) {
        $tag = $m[0];
        if (preg_match('/href\s*=\s*("|\')(.*?)\1/i', $tag, $mm)) {
            $url = trim($mm[2]);
            if (preg_match('/^\s*javascript:/i', $url) || !preg_match('/^(https?:\/\/|mailto:)/i', $url)) {
                $tag = str_replace($mm[0], '', $tag); // khatarnak ya ajeeb link hatao
            } elseif (stripos($tag, 'target=') === false) {
                $tag = rtrim($tag, '>') . ' target="_blank" rel="noopener">';
            }
        }
        return $tag;
    }, $html);
    return trim($html);
}

/** Student ko nazar aane wali announcements (targeting ke sath) */
function student_visible_announcements($student_id) {
    ensure_ann_tables();
    $pdo = db();
    $st = $pdo->prepare('SELECT course_id, batch_id FROM enrollments WHERE student_id = ?');
    $st->execute([$student_id]);
    $enr = $st->fetchAll();
    $course_ids = array_unique(array_map(fn($r) => (int)$r['course_id'], $enr));
    $batch_ids = array_unique(array_filter(array_map(fn($r) => $r['batch_id'] ? (int)$r['batch_id'] : null, $enr)));
    $where = "(a.target_type = 'all_students'";
    if ($course_ids) $where .= " OR (a.target_type = 'specific_course' AND a.course_id IN (" . implode(',', $course_ids) . "))";
    if ($batch_ids)  $where .= " OR (a.target_type = 'specific_batch' AND a.batch_id IN (" . implode(',', $batch_ids) . "))";
    $where .= ")";
    return $pdo->query("SELECT a.*, c.title AS course_title, b.batch_name
        FROM announcements a
        LEFT JOIN courses c ON c.id = a.course_id
        LEFT JOIN batches b ON b.id = a.batch_id
        WHERE a.is_active = 1 AND $where
        ORDER BY (a.priority = 'pinned') DESC, (a.priority = 'urgent') DESC, a.created_at DESC")->fetchAll();
}

function student_unread_ann_count($student_id) {
    $all = student_visible_announcements($student_id);
    if (!$all) return 0;
    $ids = array_map(fn($r) => (int)$r['id'], $all);
    $read = db()->query('SELECT announcement_id FROM announcement_reads WHERE student_id = ' . (int)$student_id .
        ' AND announcement_id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_COLUMN);
    return count($ids) - count($read);
}

function ann_target_label($a) {
    if ($a['target_type'] === 'specific_batch' && !empty($a['batch_name'])) return icon('calendar',16) . ' ' . $a['batch_name'];
    if ($a['target_type'] === 'specific_course' && !empty($a['course_title'])) return icon('book-open',16) . ' ' . $a['course_title'];
    return icon('users',16) . ' All Students';
}
function ann_priority_badge($p) {
    if ($p === 'urgent') return '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 border border-red-300">' . icon('dot',16) . ' Urgent</span>';
    if ($p === 'pinned') return '<span class="text-xs font-bold px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 border border-orange-300">' . icon('pin',16) . ' Pinned</span>';
    return '<span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Normal</span>';
}

/* ============ VIP WhatsApp Community (v23) ============ */

function ensure_community_columns() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();
        if (!$pdo->query("SHOW COLUMNS FROM batches LIKE 'whatsapp_group_link'")->fetch())
            $pdo->exec("ALTER TABLE batches ADD COLUMN whatsapp_group_link VARCHAR(255) NULL DEFAULT NULL");
        if (!$pdo->query("SHOW COLUMNS FROM batches LIKE 'whatsapp_community_link'")->fetch())
            $pdo->exec("ALTER TABLE batches ADD COLUMN whatsapp_community_link VARCHAR(255) NULL DEFAULT NULL");
        if (!$pdo->query("SHOW COLUMNS FROM courses LIKE 'whatsapp_group_link'")->fetch())
            $pdo->exec("ALTER TABLE courses ADD COLUMN whatsapp_group_link VARCHAR(255) NULL DEFAULT NULL");
        if (!$pdo->query("SHOW COLUMNS FROM courses LIKE 'whatsapp_community_link'")->fetch())
            $pdo->exec("ALTER TABLE courses ADD COLUMN whatsapp_community_link VARCHAR(255) NULL DEFAULT NULL");
    } catch (Exception $ex) {}
}

/** Ban system columns (users table) — self-healing */
function ensure_ban_columns() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();
        $pdo->exec("ALTER TABLE users MODIFY COLUMN status ENUM('active','inactive','pending','banned') NOT NULL DEFAULT 'active'");
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'ban_reason'")->fetch())
            $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason TEXT NULL DEFAULT NULL, ADD COLUMN banned_at DATETIME NULL DEFAULT NULL, ADD COLUMN banned_by INT NULL DEFAULT NULL");
    } catch (Exception $ex) {}
}

/** Batch ka link pehle, warna course ka link. null = koi link set nahi (discussion group) */
function community_link_for($course_id, $batch_id = null) {
    $links = community_links_for($course_id, $batch_id);
    return $links['group'];
}

/** Dono links: ['community' => ? , 'group' => ?] — batch ko priority, warna course */
function community_links_for($course_id, $batch_id = null) {
    ensure_community_columns();
    $pdo = db();
    $out = ['community' => null, 'group' => null];
    $cols = ['community' => 'whatsapp_community_link', 'group' => 'whatsapp_group_link'];
    foreach ($cols as $k => $col) {
        if ($batch_id) {
            $st = $pdo->prepare("SELECT $col FROM batches WHERE id = ?");
            $st->execute([$batch_id]);
            $l = trim($st->fetchColumn() ?: '');
            if ($l !== '') { $out[$k] = $l; continue; }
        }
        $st = $pdo->prepare("SELECT $col FROM courses WHERE id = ?");
        $st->execute([$course_id]);
        $l = trim($st->fetchColumn() ?: '');
        if ($l !== '') $out[$k] = $l;
    }
    return $out;
}

function valid_wa_link($url) {
    $url = trim((string)$url);
    return $url !== '' && preg_match('/^https:\/\//i', $url);
}

function wa_icon_svg($size = 30) {
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="#fff"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
}

/** Dashboard VIP card — DUAL WhatsApp access (Community + Discussion Group).
 *  $links = ['community' => url|null, 'group' => url|null]. Dono null → '' (auto-hide). */
function vip_community_card($links, $course_title, $batch_name = null, $locked = false) {
    $has_c = !empty($links['community']);
    $has_g = !empty($links['group']);
    if (!$has_c && !$has_g) return '';
    $tag = $batch_name ? '<b>' . e($batch_name) . '</b> · ' . e($course_title) : e($course_title);
    $btns = '';
    if ($has_c) $btns .= '<button type="button" class="wa-join-btn font-extrabold px-5 py-3 rounded-xl text-green-900 bg-white hover:brightness-95 shadow-lg whitespace-nowrap text-sm" data-wa="' . e($links['community']) . '">' . icon('megaphone', 16) . ' Join Official Community</button>';
    if ($has_g) $btns .= '<button type="button" class="wa-join-btn font-extrabold px-5 py-3 rounded-xl text-white hover:brightness-110 shadow-lg whitespace-nowrap text-sm" style="background:#0b5c4d" data-wa="' . e($links['group']) . '">' . wa_icon_svg(16) . ' Join Discussion Group</button>';
    $inner = '<div class="flex items-center gap-4 flex-wrap">'
        . '<div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0" style="background:rgba(255,255,255,.22)">' . wa_icon_svg(32) . '</div>'
        . '<div class="flex-1 min-w-52"><div class="font-extrabold text-lg">Official Batch WhatsApp Access</div>'
        . '<div class="text-sm text-white/85">Announcements ke liye official community, Q&amp;A ke liye discussion group.<br><span class="text-white/70 text-xs">' . $tag . '</span></div></div>'
        . '<div class="flex gap-2 flex-wrap">' . $btns . '</div></div>';
    if ($locked) {
        return '<div class="rounded-2xl mb-4 relative overflow-hidden" style="background:linear-gradient(135deg,#128C7E,#25D366)">'
            . '<div class="p-5 opacity-50 select-none" style="filter:blur(1px)">' . $inner . '</div>'
            . '<div class="absolute inset-0 flex items-center justify-center"><span class="text-white font-bold text-sm px-5 py-2.5 rounded-full shadow" style="background:rgba(0,0,0,.55)">' . icon('lock',16) . ' Batch completed — group access ended</span></div></div>';
    }
    return '<div class="rounded-2xl p-5 mb-4 text-white relative overflow-hidden shadow-lg" style="background:linear-gradient(135deg,#128C7E,#25D366)">' . $inner . '</div>';
}

/** Player screen chhota button (rules modal kholta hai) */
function vip_community_btn($link, $batch_name = null) {
    return '<button type="button" class="wa-join-btn inline-flex items-center gap-2 text-white text-sm font-bold px-4 py-2 rounded-xl shadow" style="background:#25D366" data-wa="' . e($link) . '">'
        . wa_icon_svg(18) . ' Batch Community' . ($batch_name ? ' · ' . e($batch_name) : '') . '</button>';
}

/* ============ WhatsApp Community Rules (v24, dynamic) ============ */

function ensure_community_rules() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS community_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rule_en TEXT NOT NULL, rule_ur TEXT NOT NULL,
            rule_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $n = (int)db()->query('SELECT COUNT(*) FROM community_rules')->fetchColumn();
        if ($n === 0) {
            // EXACT approved Community Guidelines text (admin baad me edit kar sakta hai)
            $defaults = [
                ['No Personal Messages (DMs): Strictly do not message any student or team member directly without permission.', 'پرسنل میسج (ان باکس) کرنا سخت منع ہے: کسی بھی طالب علم یا ٹیم ممبر کو بغیر اجازت پرسنل میسج کرنا سخت منع ہے۔'],
                ['No Buying, Selling, or Self-Promotion: Promoting your own services, accounts, stores, or third-party links is 100% prohibited.', 'خرید و فروخت یا پروموشن کی قطعی ممانعت: گروپ میں اپنی سروسز، ٹک ٹاک اکاؤنٹس، شاپائف اسٹورز یا کوئی بھی پروموشنل لنک شیئر کرنا 100 فیصد منع ہے۔'],
                ['Course-Related Discussions Only: All discussions must strictly pertain to TikTok Monetization, Shopify, and the course curriculum.', 'صرف کورس سے متعلق گفتگو: گروپ میں صرف اور صرف ٹک ٹاک مونیٹائزیشن، شاپائف اور لیکچرز سے متعلق سوال و جواب کی اجازت ہوگی۔'],
                ['Mutual Respect & Privacy: Respect everyone\'s privacy. Harassment, abusive language, or spamming will result in immediate action.', 'باہمی احترام اور پرائیویسی: تمام ممبران کے احترام اور پرائیویسی کا خیال رکھیں۔ بدتمیزی، بیہودہ گفتگو یا اسپامنگ پر فوری ایکشن لیا جائے گا۔'],
                ['ZERO TOLERANCE POLICY: Violating any rule will lead to an PERMANENT BAN from both the WhatsApp group and the LMS platform, with NO REFUND under any circumstances.', 'زیرو ٹالرنس پالیسی (Zero Tolerance Policy): کسی بھی اصول کی خلاف ورزی کی صورت میں بغیر کسی وارننگ کے واٹس ایپ گروپ اور LMS اکاؤنٹ دونوں سے مستقل طور پر بلاک (Ban) کر دیا جائے گا، اور کسی بھی صورت میں فیس واپس (Refund) نہیں کی جائے گی۔'],
            ];
            $ins = db()->prepare('INSERT INTO community_rules (rule_en, rule_ur, rule_order) VALUES (?,?,?)');
            $i = 1;
            foreach ($defaults as [$en, $ur]) $ins->execute([$en, $ur, $i++]);
        }
    } catch (Exception $ex) {}
}

/** STRICT Community Guidelines modal — agreement ke baghair link nahi khulta.
 *  Student pages par ek dafa <?= wa_rules_modal() ?> include karein. */
function wa_rules_modal() {
    ensure_community_rules();
    $rules = db()->query('SELECT rule_en, rule_ur FROM community_rules WHERE is_active = 1 ORDER BY rule_order ASC, id ASC')->fetchAll();
    $json = json_encode(array_map(fn($r) => ['en' => $r['rule_en'], 'ur' => $r['rule_ur']], $rules), JSON_UNESCAPED_UNICODE);
    return '<link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">'
      . '<div id="waRulesModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,.65)">'
      . '<div class="bg-white rounded-2xl max-w-xl w-full max-h-[88vh] overflow-y-auto shadow-2xl">'
      . '<div class="p-4 sticky top-0 z-10" style="background:linear-gradient(135deg,#b91c1c,#dc2626)">'
      . '<div class="flex justify-between items-start gap-2">'
      . '<div><b class="text-white text-base">' . icon('alert',16) . ' IMPORTANT COMMUNITY RULES &amp; CODE OF CONDUCT</b>'
      . '<div class="text-white/90 text-sm mt-1" dir="rtl" style="font-family:\'Noto Nastaliq Urdu\',serif; line-height:2.2">ضروری اصول و ضوابط (واٹس ایپ کمیونٹی اور ڈسکشن گروپ)</div></div>'
      . '<button type="button" onclick="closeWaRules()" class="text-white text-2xl leading-none px-2 shrink-0">' . icon('x',16) . '</button></div></div>'
      . '<div class="p-5 text-gray-800">'
      . '<div class="font-extrabold mb-2 text-sm"> ENGLISH:</div><ol id="waRulesEn" class="text-sm space-y-2 mb-6 ml-5" style="list-style:decimal"></ol>'
      . '<div class="font-extrabold mb-2 text-sm" dir="rtl" style="text-align:right"> اردو:</div><ol id="waRulesUr" class="text-sm space-y-2 mb-6" dir="rtl" style="text-align:right; list-style:decimal; padding-right:22px; font-family:\'Noto Nastaliq Urdu\',serif; line-height:2.3"></ol>'
      . '<label class="flex items-start gap-2 text-sm bg-amber-50 border border-amber-300 rounded-xl p-3 cursor-pointer font-semibold">'
      . '<input type="checkbox" id="waAgree" class="mt-1 shrink-0" onchange="setWaAgree(this.checked)">'
      . '<span><span dir="rtl" style="font-family:\'Noto Nastaliq Urdu\',serif; line-height:2.2">میں نے تمام اصول پڑھ لیے ہیں اور میں ان کی پابندی کرنے کا پابند ہوں</span><br><span class="text-gray-600 font-normal">I have read and agree to all rules</span></span></label>'
      . '<a id="waJoinBtn" href="#" target="_blank" rel="noopener" class="block text-center mt-4 font-extrabold px-6 py-3.5 rounded-xl text-white text-lg" style="background:#25D366; opacity:.45; pointer-events:none">' . icon('arrow-right',16) . ' Join WhatsApp Now</a>'
      . '<p class="text-xs text-gray-400 text-center mt-2">Checkbox tick kiye baghair link nahi khulega.</p>'
      . '</div></div></div>'
      . '<script>var WA_RULES = ' . $json . ';'
      . 'function renderWaRules(){'
      . 'var en=document.getElementById("waRulesEn"),ur=document.getElementById("waRulesUr");en.innerHTML="";ur.innerHTML="";'
      . 'WA_RULES.forEach(function(r){var li=document.createElement("li");li.textContent=r.en;en.appendChild(li);'
      . 'var li2=document.createElement("li");li2.textContent=r.ur;ur.appendChild(li2);});'
      . 'if(!WA_RULES.length){var li=document.createElement("li");li.textContent="No rules defined.";en.appendChild(li);}}'
      . 'function openWaRules(link){'
      . 'document.getElementById("waJoinBtn").href=link;'
      . 'var c=document.getElementById("waAgree");c.checked=false;setWaAgree(false);'
      . 'renderWaRules();var m=document.getElementById("waRulesModal");m.classList.remove("hidden");m.classList.add("flex");'
      . 'document.body.style.overflow="hidden";}'
      . 'function closeWaRules(){var m=document.getElementById("waRulesModal");m.classList.add("hidden");m.classList.remove("flex");document.body.style.overflow="";}'
      . 'function setWaAgree(ok){var j=document.getElementById("waJoinBtn");j.style.opacity=ok?"1":".45";j.style.pointerEvents=ok?"auto":"none";}'
      . 'document.addEventListener("click",function(ev){var b=ev.target.closest(".wa-join-btn");if(b){ev.preventDefault();openWaRules(b.getAttribute("data-wa"));}});'
      . 'document.getElementById("waRulesModal").addEventListener("click",function(ev){if(ev.target===this)closeWaRules();});'
      . '</script>';
}

/* ============ Ban / Block modal (admin) ============ */

/** Student list/profile par reuse hone wala Ban confirmation modal */
function ban_student_modal() {
    return '<div id="banModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4" style="background:rgba(0,0,0,.6)">'
      . '<form method="post" class="bg-white rounded-2xl max-w-md w-full p-6 text-gray-800 shadow-2xl">'
      . csrf_field()
      . '<input type="hidden" name="ban_student" id="banStudentId" value="">'
      . '<h3 class="font-extrabold text-lg text-red-700">' . icon('ban',16) . ' Ban Student (Rules Violation)</h3>'
      . '<p class="text-sm mt-1">Student: <b id="banStudentName"></b></p>'
      . '<div class="text-sm rounded-xl p-3 mt-3" style="background:#fef2f2; border:1px solid #fca5a5; color:#991b1b">'
      . icon('alert',16) . ' Yeh action student ka <b>foran LMS access band</b> kar dega aur uski <b>fees refund nahi hogi</b>.</div>'
      . '<label class="text-sm font-semibold mt-4 block">Violation reason *</label>'
      . '<select name="ban_reason_select" id="banReasonSel" class="w-full border rounded-lg px-3 py-2 mt-1 text-sm" onchange="document.getElementById(\'banReasonCustom\').style.display = this.value===\'other\' ? \'block\' : \'none\'">'
      . '<option>Personal messaging (DMs) to students</option>'
      . '<option>Self-promotion / buying-selling in group</option>'
      . '<option>Abusive behavior</option>'
      . '<option>Sharing course content outside</option>'
      . '<option value="other">Other (neeche likhein)</option>'
      . '</select>'
      . '<textarea name="ban_reason" id="banReasonCustom" rows="2" style="display:none" placeholder="Reason likhein..." class="w-full border rounded-lg px-3 py-2 mt-2 text-sm"></textarea>'
      . '<div class="flex gap-2 mt-5">'
      . '<button type="button" onclick="closeBanModal()" class="flex-1 border rounded-xl py-2.5 text-sm font-semibold">Cancel</button>'
      . '<button class="flex-1 text-white rounded-xl py-2.5 text-sm font-extrabold" style="background:#dc2626">Confirm Permanent Ban</button>'
      . '</div></form></div>'
      . '<script>'
      . 'function openBanModal(id, name){'
      . 'document.getElementById("banStudentId").value=id;'
      . 'document.getElementById("banStudentName").textContent=name;'
      . 'var m=document.getElementById("banModal");m.classList.remove("hidden");m.classList.add("flex");}'
      . 'function closeBanModal(){var m=document.getElementById("banModal");m.classList.add("hidden");m.classList.remove("flex");}'
      . 'document.getElementById("banModal").addEventListener("click",function(ev){if(ev.target===this)closeBanModal();});'
      . '</script>';
}

/* ============ SVG icon system (emojis ki jagah professional icons) ============ */

/** Lucide-style inline SVG icon. size = px. */
/**
 * Watch ring — the LMS signature. A lecture counts once 3 minutes are watched;
 * the ring fills with progress (0–100). Used on course progress, the player and lecture lists.
 */
function watch_ring($pct, $size = 20, $label = '') {
    $pct = max(0, min(100, (int)$pct));
    $sw = $size >= 32 ? 3 : 2;
    $r = ($size - $sw) / 2;
    $c = 2 * M_PI * $r;
    $off = $c * (1 - $pct / 100);
    $mid = $size / 2;
    $aria = $label !== '' ? $label : $pct . '% watched';
    $svg = sprintf('<svg class="wring" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d" role="img" aria-label="%2$s">', $size, e($aria));
    $svg .= sprintf('<circle class="wr-track" cx="%1$s" cy="%1$s" r="%2$s" stroke-width="%3$s"/>', $mid, round($r, 2), $sw);
    if ($pct > 0) {
        $svg .= sprintf('<circle class="wr-fill" cx="%1$s" cy="%1$s" r="%2$s" stroke-width="%3$s" stroke-dasharray="%4$s" stroke-dashoffset="%5$s" transform="rotate(-90 %1$s %1$s)"/>',
            $mid, round($r, 2), $sw, round($c, 2), round($off, 2));
    }
    if ($pct === 100) $svg .= sprintf('<circle class="wr-done" cx="%1$s" cy="%1$s" r="%2$s"/>', $mid, round($size / 7, 2));
    return $svg . '</svg>';
}

function icon($name, $size = 16) {
    static $P = null;
    if ($P === null) $P = [
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
        'calendar' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'archive' => '<rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'clipboard-list' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'clipboard' => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6"/><path d="M9 16h6"/>',
        'inbox' => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'credit-card' => '<rect width="22" height="16" x="1" y="4" rx="2"/><line x1="1" x2="23" y1="10" y2="10"/>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'bar-chart' => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'check-square' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'graduation-cap' => '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12.5V16a6 3 0 0 0 12 0v-3.5"/><path d="M22 10v6"/>',
        'play' => '<polygon points="6 3 20 12 6 21 6 3"/>',
        'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'ban' => '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
        'message' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'rewind' => '<polygon points="11 19 2 12 11 5 11 19"/><polygon points="22 19 13 12 22 5 22 19"/>',
        'fast-forward' => '<polygon points="13 19 22 12 13 5 13 19"/><polygon points="2 19 11 12 2 5 2 19"/>',
        'pause' => '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
        'volume' => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/>',
        'volume-x' => '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/>',
        'maximize' => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'edit' => '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/>',
        'trash' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'lightbulb' => '<path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'paperclip' => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'file-text' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        'pin' => '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1z"/>',
        'bot' => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'sparkles' => '<path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/>',
        'trophy' => '<path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
        'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'arrow-down' => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'video' => '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'copy' => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'thumbs-up' => '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
        'package' => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/>',
        'award' => '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>',
        'trending-up' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'clapperboard' => '<path d="M20.2 6 3 11l-.9-2.4c-.3-1.1.3-2.2 1.3-2.5l13.5-4c1.1-.3 2.2.3 2.5 1.3Z"/><path d="m6.2 5.3 3.1 3.9"/><path d="m12.4 3.4 3.1 4"/><path d="M3 11h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/>',
        'palette' => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h2.996c3.03 0 5.5-2.47 5.5-5.5 0-5.5-4.5-10-10-10z"/>',
        'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'menu' => '<line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'dot' => 'DOT',
    ];
    $svg_open = '<svg class=\'ico\' width=\'%d\' height=\'%d\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\' aria-hidden=\'true\'>';
    if ($name === 'dot') return sprintf('<svg class=\'ico\' width=\'%d\' height=\'%d\' viewBox=\'0 0 24 24\' aria-hidden=\'true\'><circle cx=\'12\' cy=\'12\' r=\'6\' fill=\'currentColor\'/></svg>', (int)$size, (int)$size);
    $p = $P[$name] ?? '';
    if ($p === '' || $p === 'DOT') return '';
    return sprintf($svg_open, (int)$size, (int)$size) . $p . '</svg>';
}

/* ============ Dynamic payment methods ============ */

/** payment_settings table self-healing + default rows */
function ensure_payment_settings() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            method_name VARCHAR(100) NOT NULL,
            account_title VARCHAR(255) NOT NULL DEFAULT '',
            account_number VARCHAR(255) NOT NULL DEFAULT '',
            instructions TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $n = (int)$pdo->query('SELECT COUNT(*) FROM payment_settings')->fetchColumn();
        if ($n === 0) {
            $pdo->exec("INSERT INTO payment_settings (method_name, account_title, account_number, instructions, sort_order) VALUES
                ('EasyPaisa', '', '', 'EasyPaisa App ya qareebi retailer se send karein', 1),
                ('JazzCash', '', '', 'JazzCash App ya qareebi retailer se send karein', 2),
                ('Bank Transfer / Raast', '', '', 'Neeche diye gaye IBAN par transfer karein', 3)");
        }
    } catch (Exception $ex) {}
}

/** Sirf active methods (checkout par display ke liye) */
function active_payment_methods() {
    ensure_payment_settings();
    try {
        return db()->query("SELECT * FROM payment_settings WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    } catch (Exception $ex) { return []; }
}

/** Saari methods (admin manager ke liye) */
function all_payment_methods() {
    ensure_payment_settings();
    try {
        return db()->query("SELECT * FROM payment_settings ORDER BY sort_order ASC, id ASC")->fetchAll();
    } catch (Exception $ex) { return []; }
}

/** Premium payment method card (checkout) — copy button ke sath */
function payment_method_card($m) {
    $num = trim($m['account_number'] ?? '');
    $title = trim($m['account_title'] ?? '');
    $name = $m['method_name'] ?? '';
    $icon = (stripos($name, 'bank') !== false || stripos($name, 'raast') !== false) ? 'credit-card' : 'wallet';
    $copy = $num !== ''
        ? '<button type="button" class="pay-copy-btn" data-copy="' . e($num) . '">' . icon('copy', 14) . ' <span>Copy Number</span></button>'
        : '';
    return '<div class="pay-card">'
        . '<div class="pay-card-head">' . icon($icon, 22) . '<div><b>' . e($name) . '</b>'
        . ($title !== '' ? '<div class="pay-title">' . e($title) . '</div>' : '') . '</div></div>'
        . ($num !== ''
            ? '<div class="pay-num-row"><span class="pay-num">' . e($num) . '</span>' . $copy . '</div>'
            : '<div class="pay-empty">Number jald update ho raha hai — support se rabta karein.</div>')
        . (!empty($m['instructions']) ? '<div class="pay-ins">' . e($m['instructions']) . '</div>' : '')
        . '</div>';
}
