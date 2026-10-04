<?php
// DigitalMax LMS - Database backup & restore (pure PHP, mysqldump ki zaroorat nahi)

/** Poori database ka .sql dump string me banao */
function db_backup_sql() {
    $pdo = db();
    $out = "-- DigitalMax LMS database backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $tq = '`' . str_replace('`', '``', $t) . '`';
        $row = $pdo->query("SHOW CREATE TABLE $tq")->fetch();
        $out .= "DROP TABLE IF EXISTS $tq;\n" . $row['Create Table'] . ";\n\n";
        $stmt = $pdo->query("SELECT * FROM $tq");
        $batch = [];
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
            $vals = [];
            foreach ($r as $v) $vals[] = $v === null ? 'NULL' : $pdo->quote($v);
            $batch[] = '(' . implode(',', $vals) . ')';
            if (count($batch) >= 200) {
                $out .= "INSERT INTO $tq VALUES\n" . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }
        if ($batch) $out .= "INSERT INTO $tq VALUES\n" . implode(",\n", $batch) . ";\n";
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

/** .sql dump ko database me restore karo. Returns executed statements count. */
function db_restore_sql($sql) {
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $stmts = array_filter(array_map('trim', explode(";\n", str_replace("\r\n", "\n", $sql))));
    $n = 0;
    foreach ($stmts as $s) {
        if ($s === '' || strncmp($s, '--', 2) === 0) continue;
        $pdo->exec($s);
        $n++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return $n;
}

/** backups/ folder ready karo (protected) */
function backup_dir() {
    $dir = __DIR__ . '/../backups';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (!file_exists($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
        file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}
