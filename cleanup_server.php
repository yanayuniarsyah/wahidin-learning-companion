<?php
$files = ['clean_and_seed_kids.php', 'seed_kids_content.php', 'migrate_kids.php', 'test_phase1.php', 'export.php', 'db_export.json', 'items_export.json', 'check.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}
echo "Cleanup done.\n";
unlink(__FILE__);
?>
