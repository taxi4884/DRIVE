<?php
require_once __DIR__ . '/includes/db.php';

$dir = __DIR__ . '/migrations';
$files = glob($dir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $sql = file_get_contents($file);
    echo "Applying migration: " . basename($file) . PHP_EOL;
    try {
        $pdo->exec($sql);
        echo "Done" . PHP_EOL;
    } catch (PDOException $e) {
        echo "Failed: " . $e->getMessage() . PHP_EOL;
    }
}
?>
