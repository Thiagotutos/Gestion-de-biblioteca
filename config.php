<?php
// config.php

// Cargar variables de entorno si existen, o usar valores por defecto
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'library_system';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si la DB no existe, intentamos conectar sin DB para mostrar un mensaje amigable
    try {
        $pdo_test = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
        die("Error: La base de datos '$db' no existe. Por favor, ejecuta el archivo database.sql en tu gestor de base de datos.");
    } catch (\PDOException $e2) {
         die("Error de conexión a la base de datos: " . $e2->getMessage());
    }
}
?>
