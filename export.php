<?php
// export.php - Download the entire SQLite database as backup

$file = __DIR__ . '/wlc.db';

if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_wlc_db_' . date('Y-m-d_H-i-s') . '.db"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
} else {
    echo "Database tidak ditemukan.";
}
?>
