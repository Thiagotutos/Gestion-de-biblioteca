-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS library_system;
USE library_system;

-- Tabla de Usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('Administrador', 'Bibliotecario', 'Lector') NOT NULL DEFAULT 'Lector',
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tablas relacionales para Libros
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS publishers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS racks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Tabla de Libros
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    author_id INT,
    isbn VARCHAR(50),
    publisher_id INT,
    category_id INT,
    rack_id INT,
    imagen VARCHAR(255) DEFAULT NULL,
    estado ENUM('Disponible', 'Prestado', 'Extraviado') NOT NULL DEFAULT 'Disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL,
    FOREIGN KEY (publisher_id) REFERENCES publishers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (rack_id) REFERENCES racks(id) ON DELETE SET NULL
);

-- Tabla de Transacciones (Préstamos, Devoluciones, etc.)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NULL, -- Puede ser null si se usa lector_nombre
    lector_nombre VARCHAR(255) NULL, -- Nombre de quien retiró
    lector_documento VARCHAR(50) NULL, -- Documento de quien retiró
    librarian_id INT NOT NULL, -- Quien gestionó la acción
    accion ENUM('Prestamo', 'Devolucion', 'Ingreso') NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_devolucion_esperada DATE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (librarian_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabla de Eventos y Horarios
CREATE TABLE IF NOT EXISTS events_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('Feria', 'Horario', 'Aviso') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NOT NULL
);

-- Datos de prueba para Administrador (La contraseña debería estar hasheada con password_hash en PHP)
-- pass temporal: 'admin123' (hash simplificado para ej: '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u' -> 'admin123')
INSERT IGNORE INTO users (nombre, rol, email, password) VALUES 
('Admin Biblioteca', 'Administrador', 'admin@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
('Lector Prueba', 'Lector', 'lector@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u');

-- Eventos de prueba
INSERT IGNORE INTO events_schedule (tipo, titulo, descripcion, fecha_inicio, fecha_fin) VALUES
('Feria', 'Feria del Libro Anual', 'Ven y descubre nuevos títulos.', '2026-05-10 09:00:00', '2026-05-15 18:00:00'),
('Horario', 'Horario de Verano', 'La biblioteca estará abierta en estos horarios especiales.', '2026-01-01 08:00:00', '2026-03-31 14:00:00');

-- NOTA: Los datos de libros se importan ejecutando migrate_aguapay.php
-- que lee el archivo ISO 2709 del sistema Aguapay (carpeta 20260512/basedato.iso)
