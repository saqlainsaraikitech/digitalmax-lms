<?php
// Referral landing: click track + cookie set, phir homepage par
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/helpers.php';

$code = trim($_GET['ref'] ?? '');
if ($code !== '') {
    try {
        $st = db()->prepare('SELECT id FROM users WHERE referral_code = ? AND status = "active" LIMIT 1');
        $st->execute([$code]);
        if ($rid = $st->fetchColumn()) {
            db()->prepare('INSERT INTO referral_clicks (referrer_id, ip) VALUES (?, ?)')
                ->execute([$rid, $_SERVER['REMOTE_ADDR'] ?? null]);
            setcookie('dm_ref', $code, time() + 30 * 86400, '/'); // 30 din
        }
    } catch (Exception $ex) { /* ignore */ }
}
header('Location: index.php');
exit;
