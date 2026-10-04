<?php
// Student AJAX: video resume position save + auto-complete
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/helpers.php';
require_once __DIR__ . '/../inc/auth.php';
header('Content-Type: application/json');
require_login();
$u = current_user();
if ($u['role'] !== 'student') { http_response_code(403); echo json_encode(['ok' => false]); exit; }
$pdo = db();

$lecture_id = (int)($_POST['lecture_id'] ?? 0);
$course_id  = (int)($_POST['course_id'] ?? 0);

// access verify: enrolled ya free preview
$st = $pdo->prepare('SELECT * FROM lectures WHERE id = ? AND course_id = ?');
$st->execute([$lecture_id, $course_id]);
$lec = $st->fetch();
if (!$lec) { echo json_encode(['ok' => false]); exit; }
$st = $pdo->prepare('SELECT 1 FROM enrollments WHERE student_id = ? AND course_id = ?');
$st->execute([$u['id'], $course_id]);
if (!$st->fetch() && !$lec['is_preview']) { echo json_encode(['ok' => false]); exit; }

// table self-healing (purani installs)
try { $pdo->query('SELECT 1 FROM video_progress LIMIT 1'); }
catch (Exception $ex) {
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS video_progress (
          student_id INT NOT NULL, lecture_id INT NOT NULL, seconds INT NOT NULL DEFAULT 0,
          watched_sec INT NOT NULL DEFAULT 0,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (student_id, lecture_id),
          FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (lecture_id) REFERENCES lectures(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } catch (Exception $ex2) {}
}

$action = $_POST['action'] ?? 'save';

if ($action === 'save') {
    $sec = max(0, (int)($_POST['seconds'] ?? 0));
    $watched = max(0, (int)($_POST['watched'] ?? 0));
    // v54b: watched_sec sirf barhta hai (MAX) — kabhi kam nahi hota
    $pdo->prepare('INSERT INTO video_progress (student_id, lecture_id, seconds, watched_sec) VALUES (?,?,?,?)
                    ON DUPLICATE KEY UPDATE seconds = VALUES(seconds), watched_sec = GREATEST(watched_sec, VALUES(watched_sec))')
        ->execute([$u['id'], $lecture_id, $sec, $watched]);
    echo json_encode(['ok' => true]);
} elseif ($action === 'complete') {
    // v54b: 3-min rule server-side — bina dekhe complete nahi
    $need = required_watch_sec($lec['duration'] ?? '');
    $wsec = 0;
    try {
        $st = $pdo->prepare('SELECT watched_sec FROM video_progress WHERE student_id = ? AND lecture_id = ?');
        $st->execute([$u['id'], $lecture_id]);
        $wsec = (int)($st->fetchColumn() ?: 0);
    } catch (Exception $ex) {}
    $wsec = max($wsec, (int)($_POST['watched'] ?? 0));
    if ($wsec < $need) { echo json_encode(['ok' => false, 'need' => $need, 'watched' => $wsec]); exit; }
    $pdo->prepare('INSERT IGNORE INTO lecture_progress (student_id, lecture_id) VALUES (?, ?)')
        ->execute([$u['id'], $lecture_id]);
    try { $pdo->prepare('DELETE FROM video_progress WHERE student_id = ? AND lecture_id = ?')->execute([$u['id'], $lecture_id]); }
    catch (Exception $ex) {}
    echo json_encode(['ok' => true, 'progress' => course_progress($u['id'], $course_id)]);
} else {
    echo json_encode(['ok' => false]);
}
