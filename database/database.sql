-- =============================================================================
-- Sistema de Gestión de Biblioteca Escolar / Institucional
-- Esquema DDL de Base de Datos (Estructura Limpia sin Datos de Producción)
-- Motor: MySQL 8.0+ / MariaDB 10.4+ (InnoDB, UTF-8 mb4)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `library_system` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `library_system`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

-- -------------------------------------------------------------
-- 1. Tabla: `users` (Usuarios y Roles RBAC)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('Administrador','Bibliotecario','Lector') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Lector',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plain_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuentas de demostración iniciales (Contraseña: admin123)
INSERT INTO `users` (`id`, `nombre`, `rol`, `email`, `password`, `plain_password`) VALUES
(1, 'Administrador del Sistema', 'Administrador', 'admin@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u', 'admin123'),
(2, 'Bibliotecario Principal', 'Bibliotecario', 'bibliotecario@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u', 'admin123'),
(3, 'Lector de Prueba', 'Lector', 'lector@biblioteca.com', '$2y$10$RToxd6U4jpqhnOajhzErdugUAM8WHXAJtWhAEGJaV6qv.VtYzcX/u', 'admin123');

-- -------------------------------------------------------------
-- 2. Tabla: `racks` (Estantes y Ubicaciones Físicas)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `racks`;
CREATE TABLE `racks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `racks` (`id`, `name`) VALUES
(1, 'Estante A-1 (Literatura General)'),
(2, 'Estante B-2 (Clásicos y Ficción)'),
(3, 'Estante C-1 (Ciencias y Tecnología)'),
(4, 'Estante D-3 (Historia y Geografía)'),
(5, 'Estante E-1 (Pedagogía y Educación)'),
(6, 'Sector Referencia y Diccionarios');

-- -------------------------------------------------------------
-- 3. Tabla: `authors` (Autores)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `authors`;
CREATE TABLE `authors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. Tabla: `publishers` (Editoriales)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `publishers`;
CREATE TABLE `publishers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. Tabla: `categories` (Categorías y Géneros)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. Tabla: `books` (Catálogo de Libros y Ejemplares)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `books`;
CREATE TABLE `books` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_id` int DEFAULT NULL,
  `isbn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signatura` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn_editorial` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher_id` int DEFAULT NULL,
  `lugar_editorial` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_publicacion` date DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `rack_id` int DEFAULT NULL,
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `observacion` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('Disponible','Prestado','Extraviado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Disponible',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  KEY `publisher_id` (`publisher_id`),
  KEY `category_id` (`category_id`),
  KEY `rack_id` (`rack_id`),
  KEY `signatura` (`signatura`),
  KEY `isbn_editorial` (`isbn_editorial`),
  CONSTRAINT `books_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `books_ibfk_2` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `books_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `books_ibfk_4` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. Tabla: `transactions` (Préstamos y Devoluciones)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `book_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `lector_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lector_documento` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turno` enum('Mañana','Tarde','Noche') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `curso` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `librarian_id` int NOT NULL,
  `accion` enum('Prestamo','Devolucion','Ingreso') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_hora` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_devolucion_esperada` date DEFAULT NULL,
  `fecha_devolucion_real` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `user_id` (`user_id`),
  KEY `librarian_id` (`librarian_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`librarian_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. Tabla: `events_schedule` (Horarios y Avisos)
-- -------------------------------------------------------------
DROP TABLE IF EXISTS `events_schedule`;
CREATE TABLE `events_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('Feria','Horario','Aviso') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `events_schedule` (`id`, `tipo`, `titulo`, `descripcion`, `fecha_inicio`, `fecha_fin`) VALUES
(1, 'Aviso', 'Bienvenida al Sistema de Biblioteca', 'Sistema instalado y operativo.', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY));

SET FOREIGN_KEY_CHECKS = 1;
