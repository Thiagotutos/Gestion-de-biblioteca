-- Migración 001: Nuevos campos para libros
-- Autor, título, editorial, lugar de la editorial, fecha publicación, notas y observación

ALTER TABLE `books`
    ADD COLUMN `lugar_editorial` VARCHAR(255) DEFAULT NULL AFTER `publisher_id`,
    ADD COLUMN `fecha_publicacion` DATE DEFAULT NULL AFTER `lugar_editorial`,
    ADD COLUMN `notas` TEXT DEFAULT NULL AFTER `imagen`,
    ADD COLUMN `observacion` TEXT DEFAULT NULL AFTER `notas`;
