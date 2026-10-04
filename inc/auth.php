<?php
// DigitalMax LMS - Auth & permissions

// Secure session cookies (hijacking se bachao)
if (session_status() === PHP_SESSION_NONE) {
    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // JS cookie nahi parh sakti (XSS se session chori mushkil)
        'samesite' => 'Lax',  // cross-site request par cookie nahi jayegi (CSRF mushkil)
        'secure'   => $is_https, // HTTPS par hi cookie
    ]);
    session_start();
}

// CSRF: har POST request ka token verify (install.php alag se handle karta hai)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !defined('SKIP_CSRF_CHECK')) {
    verify_csrf();
}

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    ensure_ban_columns(); // purani install par ban columns auto-add
    static $user = null;
    if ($user === null) {
        // NOTE: status filter nahi — banned/inactive ka faisla require_login karta hai
        $st = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $st->execute([$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
        if (!$user) { unset($_SESSION['user_id']); }
    }
    return $user;
}

function require_login() {
    $u = current_user();
    if (!$u) redirect('../login.php');
    // 🚫 BANNED: foran session khatam + banned notice page
    if (($u['status'] ?? '') === 'banned') {
        $uid = (int)$u['id'];
        session_unset();
        session_destroy();
        redirect('../banned.php?uid=' . $uid);
    }
    // inactive/pending: purana behavior (logout → login page)
    if (($u['status'] ?? '') !== 'active') {
        unset($_SESSION['user_id']);
        redirect('../login.php');
    }
    check_single_device();
}

/** Login par current session id DB me save (single-device enforcement) */
function set_current_session($user_id) {
    $pdo = db();
    $sid = session_id();
    try {
        $pdo->prepare('UPDATE users SET current_session_id = ? WHERE id = ?')->execute([$sid, $user_id]);
    } catch (PDOException $ex) {
        // purani install me column nahi hoga → auto-add karke retry
        try {
            $pdo->exec('ALTER TABLE users ADD COLUMN current_session_id VARCHAR(255) NULL DEFAULT NULL');
            $pdo->prepare('UPDATE users SET current_session_id = ? WHERE id = ?')->execute([$sid, $user_id]);
        } catch (PDOException $ex2) { /* ignore */ }
    }
}

/**
 * Har student page request par check:
 * agar DB wali session id current se match na kare (doosri device login ho gayi),
 * to purana session destroy + login page par message ke sath redirect.
 */
function check_single_device() {
    $u = current_user();
    if (!$u || $u['role'] !== 'student') return;
    try {
        $st = db()->prepare('SELECT current_session_id FROM users WHERE id = ?');
        $st->execute([$u['id']]);
        $db_sid = $st->fetchColumn();
    } catch (PDOException $ex) {
        return; // column mojood nahi (purani install) → check skip
    }
    if (!empty($db_sid) && $db_sid !== session_id()) {
        session_destroy();
        session_start();
        $_SESSION['login_error'] = 'Aapka account kisi doosri device par login ho chuka hai. Ek waqt mein sirf ek device allowed hai.';
        redirect('../login.php');
    }
}

function is_admin() {
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

function is_staff() { // admin ya team
    $u = current_user();
    return $u && in_array($u['role'], ['admin', 'team'], true);
}

/** Team member ke permissions check. Admin ko sab allowed. */
function can($perm) {
    $u = current_user();
    if (!$u) return false;
    if ($u['role'] === 'admin') return true;
    if ($u['role'] !== 'team') return false;
    static $perms = null;
    if ($perms === null) {
        $st = db()->prepare('SELECT * FROM team_permissions WHERE user_id = ?');
        $st->execute([$u['id']]);
        $perms = $st->fetch() ?: [];
    }
    return !empty($perms[$perm]);
}

function require_perm($perm) {
    require_login();
    if (!can($perm)) {
        http_response_code(403);
        die('Access denied — is section ki permission nahi hai.');
    }
}

function team_perms_list() {
    return [
        'p_courses'     => 'Courses manage',
        'p_lectures'    => 'Lectures / curriculum',
        'p_assignments' => 'Assignments + grading',
        'p_students'    => 'Students + enrollments',
        'p_payments'    => 'Payments verify',
        'p_team'        => 'Team manage',
        'p_tasks'       => 'Tasks manage',
    ];
}

/* ============ Remember Me (30-day passwordless re-login) ============ */

function ensure_remember_table() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_ip VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_hash (token_hash),
            KEY idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $ex) {}
}

/** Login par 30-din ka token issue karein */
function issue_remember_token($user_id) {
    ensure_remember_table();
    try {
        // purane tokens saaf (ek device = ek token)
        db()->prepare('DELETE FROM remember_tokens WHERE user_id = ? OR expires_at < NOW()')->execute([$user_id]);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $exp = date('Y-m-d H:i:s', time() + 30 * 86400);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        db()->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at, created_ip) VALUES (?,?,?,?)')
           ->execute([$user_id, $hash, $exp, $ip]);
        $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        setcookie('dm_remember', $user_id . ':' . $token, [
            'expires'  => time() + 30 * 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $is_https,
        ]);
    } catch (Exception $ex) {}
}

/** Cookie se auto-login (rotate-on-use) */
function try_remember_login() {
    if (!empty($_SESSION['user_id'])) return;
    if (empty($_COOKIE['dm_remember']) || strpos($_COOKIE['dm_remember'], ':') === false) return;
    ensure_remember_table();
    try {
        list($uid, $token) = explode(':', $_COOKIE['dm_remember'], 2);
        $uid = (int)$uid;
        if ($uid <= 0 || $token === '') return;
        $hash = hash('sha256', $token);
        $st = db()->prepare('SELECT rt.*, u.status FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
                             WHERE rt.user_id = ? AND rt.token_hash = ? AND rt.expires_at > NOW() LIMIT 1');
        $st->execute([$uid, $hash]);
        $row = $st->fetch();
        if (!$row || ($row['status'] ?? '') !== 'active') {
            // ghalat/expire/banned → token khatam
            if ($row) db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$uid]);
            forget_remember_cookie();
            return;
        }
        // rotate: purana delete, naya issue
        db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$uid]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;
        set_current_session($uid);
        issue_remember_token($uid);
    } catch (Exception $ex) {}
}

function forget_remember_cookie() {
    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('dm_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $is_https]);
}

/** Saare tokens revoke (logout / password change) */
function revoke_remember_tokens($user_id) {
    ensure_remember_table();
    try { db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$user_id]); } catch (Exception $ex) {}
    forget_remember_cookie();
}

// har page load par: session nahi to cookie try karein
try_remember_login();
