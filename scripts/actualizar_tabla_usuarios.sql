-- Migración: agregar campos 'activo' y 'perfil' a la tabla usuarios
-- Ejecutar en: mysql -u movies -p'hP9)ff2pa_' peliculas_db < actualizar_tabla_usuarios.sql

USE `peliculas_db`;

-- Agregar columna 'activo' si no existe
ALTER TABLE `usuarios` ADD COLUMN `activo` TINYINT(1) DEFAULT 0 AFTER `is_admin`;

-- Agregar columna 'perfil' si no existe
ALTER TABLE `usuarios` ADD COLUMN `perfil` ENUM('admin', 'usuario') DEFAULT 'usuario' AFTER `activo`;

-- Activar al usuario admin existente
UPDATE `usuarios` SET `activo` = 1, `perfil` = 'admin' WHERE `username` = 'admin';

-- Activar todos los usuarios existentes (para no bloquearlos)
UPDATE `usuarios` SET `activo` = 1 WHERE `activo` = 0;
