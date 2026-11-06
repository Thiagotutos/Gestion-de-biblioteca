-- Migración 003: Nuevos campos para Signatura Topográfica e ISBN Editorial
-- Permite búsqueda y visualización independiente de la Signatura y del ISBN comercial

ALTER TABLE `books`
    ADD COLUMN `signatura` VARCHAR(100) DEFAULT NULL AFTER `isbn`,
    ADD COLUMN `isbn_editorial` VARCHAR(50) DEFAULT NULL AFTER `signatura`,
    ADD INDEX `idx_books_signatura` (`signatura`),
    ADD INDEX `idx_books_isbn_editorial` (`isbn_editorial`);

