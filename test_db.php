<?php
require 'config.php';
$stmt = $pdo->query('DESCRIBE books');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
