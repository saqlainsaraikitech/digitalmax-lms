<?php
// Logout — session khatam + DB se device lock clear + remember token revoke
session_start();
if (!empty($_SESSION['user_id'])) {
    try {
        require __DIR__ . '/inc/db.php';
        require_once __DIR__ . '/inc/auth.php';
        db()->prepare('UPDATE users SET current_session_id = NULL WHERE id = ?')->execute([$_SESSION['user_id']]);
        revoke_remember_tokens((int)$_SESSION['user_id']);
    } catch (Exception $ex) { /* ignore */ }
}
session_destroy();
header('Location: login.php');
exit;
