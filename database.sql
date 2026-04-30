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

-- Tabla de Libros
CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    autor VARCHAR(255) NOT NULL,
    isbn VARCHAR(50),
    editorial VARCHAR(100),
    categoria VARCHAR(100),
    ubicacion VARCHAR(50), -- Ej: Estante R1, R2
    estado ENUM('Disponible', 'Prestado', 'Extraviado') NOT NULL DEFAULT 'Disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de Transacciones (Préstamos, Devoluciones, etc.)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL, -- Quien retiró el libro
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
-- pass temporal: 'admin123' (hash simplificado para ej: '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' -> 'password')
INSERT IGNORE INTO users (nombre, rol, email, password) VALUES 
('Admin Biblioteca', 'Administrador', 'admin@biblioteca.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Lector Prueba', 'Lector', 'lector@biblioteca.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Libros de prueba
INSERT IGNORE INTO books (titulo, autor, isbn, editorial, categoria, ubicacion, estado) VALUES
('El Quijote', 'Miguel de Cervantes', '978-84-15-200', 'Santillana', 'Literatura', 'Estante R1', 'Disponible'),
('Cálculo Integral', 'James Stewart', '978-0-534-39321-2', 'Cengage', 'Matemáticas', 'Estante R2', 'Prestado'),
('PHP y MySQL', 'Laura Thomson', '978-84-415-3841-8', 'Anaya', 'Programación', 'Estante R3', 'Extraviado');

-- Eventos de prueba
INSERT IGNORE INTO events_schedule (tipo, titulo, descripcion, fecha_inicio, fecha_fin) VALUES
('Feria', 'Feria del Libro Anual', 'Ven y descubre nuevos títulos.', '2026-05-10 09:00:00', '2026-05-15 18:00:00'),
('Horario', 'Horario de Verano', 'La biblioteca estará abierta en estos horarios especiales.', '2026-01-01 08:00:00', '2026-03-31 14:00:00');
