<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, titulo, isbn FROM books WHERE id > 5000 LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
