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

// -----------------------------------------------------------------------------
// Verificación suave de columnas signatura e isbn_editorial en books
// -----------------------------------------------------------------------------
try {
    $pdo->query("SELECT signatura, isbn_editorial FROM books LIMIT 1");
} catch (\Exception $e) {
    try {
        $pdo->exec("ALTER TABLE books ADD COLUMN signatura VARCHAR(100) NULL AFTER isbn");
        $pdo->exec("ALTER TABLE books ADD COLUMN isbn_editorial VARCHAR(50) NULL AFTER signatura");
        $pdo->exec("ALTER TABLE books ADD INDEX (signatura)");
        $pdo->exec("ALTER TABLE books ADD INDEX (isbn_editorial)");

        // Poblar desde observacion para registros existentes
        $stmt_m = $pdo->query("SELECT id, observacion FROM books WHERE observacion IS NOT NULL");
        $stmt_u = $pdo->prepare("UPDATE books SET signatura = ?, isbn_editorial = ? WHERE id = ?");
        while ($r_m = $stmt_m->fetch()) {
            $sig = null; $isbn_ed = null;
            if (preg_match('/Signatura:\s*([^\|]+)/i', $r_m['observacion'], $m)) $sig = trim($m[1]);
            if (preg_match('/ISBN:\s*([^\|]+)/i', $r_m['observacion'], $m)) $isbn_ed = trim($m[1]);
            if ($sig !== null || $isbn_ed !== null) {
                $stmt_u->execute([$sig, $isbn_ed, $r_m['id']]);
            }
        }
    } catch (\Exception $e2) {
        // En caso de que no tenga permisos DDL
    }
}

// -----------------------------------------------------------------------------
// Verificación suave de columnas turno, curso, responsable en transactions
// -----------------------------------------------------------------------------
try {
    $pdo->query("SELECT turno, curso, responsable, fecha_devolucion_real FROM transactions LIMIT 1");
} catch (\Exception $e) {
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN turno ENUM('Mañana','Tarde','Noche') DEFAULT NULL AFTER lector_documento");
    } catch (\Exception $e2) {}
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN curso VARCHAR(50) DEFAULT NULL AFTER turno");
    } catch (\Exception $e2) {}
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN responsable VARCHAR(255) DEFAULT NULL AFTER curso");
    } catch (\Exception $e2) {}
    try {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN fecha_devolucion_real DATETIME DEFAULT NULL AFTER fecha_devolucion_esperada");
    } catch (\Exception $e2) {}
    try {
        $pdo->exec("ALTER TABLE transactions ADD INDEX idx_trans_accion (accion)");
    } catch (\Exception $e2) {}
}

// -----------------------------------------------------------------------------
// Verificación suave de plain_password en users
// -----------------------------------------------------------------------------
try {
    $pdo->query("SELECT plain_password FROM users LIMIT 1");
} catch (\Exception $e) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN plain_password VARCHAR(255) NULL AFTER password");
        $pdo->exec("UPDATE users SET plain_password = 'admin123' WHERE plain_password IS NULL AND email IN ('admin@biblioteca.com', 'bibliotecario@biblioteca.com', 'lector@biblioteca.com')");
    } catch (\Exception $e2) {}
}
?>
