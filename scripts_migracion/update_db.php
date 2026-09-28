<?php
require 'config.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS authors (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL UNIQUE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS publishers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS racks (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50) NOT NULL UNIQUE)");

    $pdo->exec("INSERT IGNORE INTO categories (name) VALUES ('Literatura'), ('Matemáticas'), ('Programación')");
    $pdo->exec("INSERT IGNORE INTO authors (name) VALUES ('Miguel de Cervantes'), ('James Stewart'), ('Laura Thomson')");
    $pdo->exec("INSERT IGNORE INTO publishers (name) VALUES ('Santillana'), ('Cengage'), ('Anaya')");
    $pdo->exec("INSERT IGNORE INTO racks (name) VALUES ('Estante R1'), ('Estante R2'), ('Estante R3')");

    $pdo->exec("DELETE FROM transactions");
    $pdo->exec("DELETE FROM books");

    // Intentar dropear las columnas viejas, si fallan lo ignoramos.
    try { $pdo->exec("ALTER TABLE books DROP COLUMN categoria"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books DROP COLUMN autor"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books DROP COLUMN editorial"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books DROP COLUMN ubicacion"); } catch (Exception $e) {}

    // Intentar agregar las nuevas, si fallan lo ignoramos.
    try { $pdo->exec("ALTER TABLE books ADD COLUMN category_id INT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books ADD COLUMN author_id INT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books ADD COLUMN publisher_id INT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books ADD COLUMN rack_id INT"); } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE books ADD FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books ADD FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books ADD FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON DELETE SET NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE books ADD FOREIGN KEY (rack_id) REFERENCES racks(id) ON DELETE SET NULL"); } catch (Exception $e) {}

    $pdo->exec("INSERT INTO books (titulo, author_id, isbn, publisher_id, category_id, rack_id, estado) VALUES 
        ('El Quijote', 1, '978-84-15-200', 1, 1, 1, 'Disponible'),
        ('Cálculo Integral', 2, '978-0-534-39321-2', 2, 2, 2, 'Prestado'),
        ('PHP y MySQL', 3, '978-84-415-3841-8', 3, 3, 3, 'Extraviado')");

    echo "Éxito";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
