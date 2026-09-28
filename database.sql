-- =============================================================================
-- Sistema de Gestión de Biblioteca - Base de Datos Inicial / Demostración
-- =============================================================================
-- Este archivo contiene la estructura completa de tablas y un conjunto de datos
-- de muestra (usuarios, libros, autores, categorías, editoriales, estantes,
-- préstamos y eventos) para probar el sistema sin requerir archivos privados.
--
-- Credenciales de acceso de prueba:
--   - Administrador : admin@biblioteca.com        / admin123
--   - Bibliotecario : bibliotecario@biblioteca.com / admin123
--   - Lector        : lector@biblioteca.com       / admin123
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS `library_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `library_system`;

-- -----------------------------------------------------------------------------
-- 1. Tabla: users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `rol` ENUM('Administrador', 'Bibliotecario', 'Lector') NOT NULL DEFAULT 'Lector',
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Tabla: categories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Tabla: authors
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `authors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Tabla: publishers
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `publishers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Tabla: racks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `racks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Tabla: books
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `books` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titulo` VARCHAR(255) NOT NULL,
    `author_id` INT DEFAULT NULL,
    `isbn` VARCHAR(50) DEFAULT NULL,
    `publisher_id` INT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `rack_id` INT DEFAULT NULL,
    `imagen` VARCHAR(255) DEFAULT NULL,
    `estado` ENUM('Disponible', 'Prestado', 'Extraviado') NOT NULL DEFAULT 'Disponible',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`author_id`) REFERENCES `authors`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`publisher_id`) REFERENCES `publishers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`rack_id`) REFERENCES `racks`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Tabla: transactions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `book_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `lector_nombre` VARCHAR(255) DEFAULT NULL,
    `lector_documento` VARCHAR(50) DEFAULT NULL,
    `librarian_id` INT NOT NULL,
    `accion` ENUM('Prestamo', 'Devolucion', 'Ingreso') NOT NULL,
    `fecha_hora` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `fecha_devolucion_esperada` DATE DEFAULT NULL,
    FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`librarian_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Tabla: events_schedule
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events_schedule` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` ENUM('Feria', 'Horario', 'Aviso') NOT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `descripcion` TEXT,
    `fecha_inicio` DATETIME NOT NULL,
    `fecha_fin` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- INSERCIÓN DE DATOS DE DEMOSTRACIÓN (SEED DATA)
-- =============================================================================

-- Contraseña para todos los usuarios de prueba: 'admin123'
-- Hash bcrypt verificado: $2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u
INSERT INTO `users` (`id`, `nombre`, `rol`, `email`, `password`) VALUES
(1, 'Administrador del Sistema', 'Administrador', 'admin@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
(2, 'Bibliotecario Principal', 'Bibliotecario', 'bibliotecario@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
(3, 'Lector de Prueba', 'Lector', 'lector@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
(4, 'María González', 'Lector', 'maria.gonzalez@correo.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
(5, 'Carlos Rodríguez', 'Lector', 'carlos.rodriguez@correo.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u'),
(6, 'Lucía Fernández', 'Lector', 'lucia.fernandez@correo.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `rol` = VALUES(`rol`);

-- Categorías
INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'Literatura y Ficción'),
(2, 'Ciencia Ficción y Fantasía'),
(3, 'Historia y Ciencias Sociales'),
(4, 'Ciencia y Divulgación'),
(5, 'Filosofía y Ensayo'),
(6, 'Informática y Tecnología'),
(7, 'Poesía y Dramaturgia'),
(8, 'Cuentos y Relatos'),
(9, 'Literatura Juvenil'),
(10, 'Arte y Cultura')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Autores
INSERT INTO `authors` (`id`, `name`) VALUES
(1, 'Gabriel García Márquez'),
(2, 'Jorge Luis Borges'),
(3, 'Julio Cortázar'),
(4, 'Miguel de Cervantes Saavedra'),
(5, 'Isabel Allende'),
(6, 'George Orwell'),
(7, 'Antoine de Saint-Exupéry'),
(8, 'Mario Vargas Llosa'),
(9, 'Isaac Asimov'),
(10, 'Jane Austen'),
(11, 'Arthur Conan Doyle'),
(12, 'Mary Shelley'),
(13, 'Federico García Lorca'),
(14, 'Mario Benedetti'),
(15, 'Carl Sagan'),
(16, 'Ernesto Sabato')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Editoriales
INSERT INTO `publishers` (`id`, `name`) VALUES
(1, 'Editorial Sudamericana'),
(2, 'Alfaguara'),
(3, 'Penguin Random House'),
(4, 'Editorial Planeta'),
(5, 'Alianza Editorial'),
(6, 'Siglo XXI Editores'),
(7, 'Fondo de Cultura Económica'),
(8, 'Cátedra'),
(9, 'Debolsillo')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Estantes / Ubicaciones
INSERT INTO `racks` (`id`, `name`) VALUES
(1, 'Estante A-1 (Literatura Latinoamericana)'),
(2, 'Estante B-2 (Clásicos Universales)'),
(3, 'Estante C-1 (Ciencia y Tecnología)'),
(4, 'Estante D-3 (Ciencia Ficción y Fantasía)'),
(5, 'Estante E-1 (Filosofía y Ensayo)'),
(6, 'Sector Referencia y Consulta')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Libros de muestra
INSERT INTO `books` (`id`, `titulo`, `author_id`, `isbn`, `publisher_id`, `category_id`, `rack_id`, `imagen`, `estado`) VALUES
(1, 'Cien años de soledad', 1, '978-0307474728', 1, 1, 1, NULL, 'Disponible'),
(2, 'El amor en los tiempos del cólera', 1, '978-0307389732', 2, 1, 1, NULL, 'Prestado'),
(3, 'Crónica de una muerte anunciada', 1, '978-1400034956', 9, 1, 1, NULL, 'Disponible'),
(4, 'Ficciones', 2, '978-8420633114', 5, 8, 1, NULL, 'Disponible'),
(5, 'El Aleph', 2, '978-8420658766', 5, 8, 1, NULL, 'Disponible'),
(6, 'Rayuela', 3, '978-8437604572', 8, 1, 1, NULL, 'Disponible'),
(7, 'Bestiario', 3, '978-8420471839', 2, 8, 1, NULL, 'Prestado'),
(8, 'Don Quijote de la Mancha', 4, '978-8467016963', 8, 1, 2, NULL, 'Disponible'),
(9, 'La casa de los espíritus', 5, '978-0525433484', 4, 1, 1, NULL, 'Disponible'),
(10, '1984', 6, '978-8499890944', 9, 2, 4, NULL, 'Disponible'),
(11, 'Rebelión en la granja', 6, '978-8466332156', 9, 2, 4, NULL, 'Disponible'),
(12, 'El Principito', 7, '978-8498381498', 5, 9, 2, NULL, 'Disponible'),
(13, 'La ciudad y los perros', 8, '978-8420471846', 2, 1, 1, NULL, 'Disponible'),
(14, 'Fundación', 9, '978-8497599245', 9, 2, 4, NULL, 'Prestado'),
(15, 'Yo, Robot', 9, '978-8435018364', 4, 2, 4, NULL, 'Disponible'),
(16, 'Orgullo y prejuicio', 10, '978-8491051305', 3, 1, 2, NULL, 'Disponible'),
(17, 'Estudio en escarlata', 11, '978-8420665566', 5, 1, 2, NULL, 'Disponible'),
(18, 'El sabueso de los Baskerville', 11, '978-8420674407', 5, 1, 2, NULL, 'Disponible'),
(19, 'Frankenstein o el moderno Prometeo', 12, '978-8420666013', 5, 2, 4, NULL, 'Disponible'),
(20, 'Romancero gitano', 13, '978-8437601144', 8, 7, 2, NULL, 'Disponible'),
(21, 'La tregua', 14, '978-8420653891', 5, 1, 1, NULL, 'Disponible'),
(22, 'Cosmos', 15, '978-8408053743', 4, 4, 3, NULL, 'Disponible'),
(23, 'El cerebro de Broca', 15, '978-8499894454', 9, 4, 3, NULL, 'Disponible'),
(24, 'El túnel', 16, '978-8437604381', 8, 1, 1, NULL, 'Extraviado'),
(25, 'Sobre héroes y tumbas', 16, '978-8432248313', 4, 1, 1, NULL, 'Disponible')
ON DUPLICATE KEY UPDATE `titulo` = VALUES(`titulo`), `estado` = VALUES(`estado`);

-- Transacciones (Préstamos vigentes e historial de devoluciones)
-- Para que coincida con books.estado = 'Prestado', los libros 2, 7 y 14 tienen su último movimiento como 'Prestamo'
INSERT INTO `transactions` (`id`, `book_id`, `user_id`, `lector_nombre`, `lector_documento`, `librarian_id`, `accion`, `fecha_hora`, `fecha_devolucion_esperada`) VALUES
-- Préstamo y devolución histórica (Libro 1: Cien años de soledad)
(1, 1, 3, 'Lector de Prueba', '35123456', 1, 'Prestamo', DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 15 DAY)),
(2, 1, 3, 'Lector de Prueba', '35123456', 1, 'Devolucion', DATE_SUB(NOW(), INTERVAL 16 DAY), NULL),

-- Préstamo y devolución histórica (Libro 10: 1984)
(3, 10, 4, 'María González', '41234567', 2, 'Prestamo', DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY)),
(4, 10, 4, 'María González', '41234567', 1, 'Devolucion', DATE_SUB(NOW(), INTERVAL 9 DAY), NULL),

-- Préstamos ACTIVOS actuales (coinciden con libros con estado 'Prestado')
(5, 2, 4, 'María González', '41234567', 1, 'Prestamo', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY)),
(6, 7, 5, 'Carlos Rodríguez', '39876543', 1, 'Prestamo', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY)),
(7, 14, 6, 'Lucía Fernández', '43567890', 2, 'Prestamo', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 6 DAY))
ON DUPLICATE KEY UPDATE `accion` = VALUES(`accion`);

-- Eventos y avisos (con fechas dinámicas para que siempre figuren como próximos en el Dashboard)
INSERT INTO `events_schedule` (`id`, `tipo`, `titulo`, `descripcion`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Feria', 'Feria Anual del Libro y la Lectura', 'Exposición de nuevos títulos, actividades de lectura y talleres abiertos.', DATE_ADD(NOW(), INTERVAL 3 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY)),
(2, 'Aviso', 'Taller de Lectura y Creación Literaria', 'Inscripción abierta para todos los estudiantes y lectores.', DATE_ADD(NOW(), INTERVAL 12 DAY), DATE_ADD(NOW(), INTERVAL 18 DAY)),
(3, 'Horario', 'Horario Especial de Atención', 'Atención en sala de lectura extendida los días viernes por la tarde.', DATE_ADD(NOW(), INTERVAL 20 DAY), DATE_ADD(NOW(), INTERVAL 40 DAY))
ON DUPLICATE KEY UPDATE `titulo` = VALUES(`titulo`);

SET FOREIGN_KEY_CHECKS = 1;
