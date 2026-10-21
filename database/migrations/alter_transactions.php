<?php
require 'config.php';

try {
    $pdo->exec("ALTER TABLE transactions MODIFY COLUMN user_id INT NULL");
    $pdo->exec("ALTER TABLE transactions ADD COLUMN lector_nombre VARCHAR(255) NULL AFTER user_id");
    $pdo->exec("ALTER TABLE transactions ADD COLUMN lector_documento VARCHAR(50) NULL AFTER lector_nombre");
    echo "Tablas actualizadas con éxito.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
