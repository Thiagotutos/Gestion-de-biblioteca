<?php
// config.php

$host = 'localhost';
$db   = 'library_system';
$user = 'root'; // Cambiar si es necesario
$pass = '';     // Cambiar si es necesario
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
