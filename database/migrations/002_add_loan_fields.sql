-- Migración 002: Nuevos campos para préstamos
-- Turno, curso, y fecha de devolución real

ALTER TABLE `transactions`
    ADD COLUMN `turno` ENUM('Mañana','Tarde','Noche') DEFAULT NULL AFTER `lector_documento`,
    ADD COLUMN `curso` VARCHAR(50) DEFAULT NULL AFTER `turno`,
    ADD COLUMN `fecha_devolucion_real` DATETIME DEFAULT NULL AFTER `fecha_devolucion_esperada`;
