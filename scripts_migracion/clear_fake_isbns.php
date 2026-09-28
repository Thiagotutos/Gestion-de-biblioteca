<?php
require 'config.php';
$stmt = $pdo->query("UPDATE books SET isbn = '' WHERE isbn LIKE '978-%' AND id > 100");
echo "Fake ISBNs eliminados: " . $stmt->rowCount() . "\n";
