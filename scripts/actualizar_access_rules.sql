-- Migración: agregar tabla access_rules para control de acceso por horario
-- Ejecutar en: mysql -u movies -p'hP9)ff2pa_' peliculas_db < actualizar_access_rules.sql

USE `peliculas_db`;

CREATE TABLE IF NOT EXISTS `access_rules` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `enabled` TINYINT(1) DEFAULT 1,
    `start_time` TIME DEFAULT '00:00:00',
    `end_time` TIME DEFAULT '23:59:59',
    `days` VARCHAR(7) DEFAULT '1111111',
    `date_start` DATE DEFAULT NULL,
    `date_end` DATE DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `usuarios`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Crear reglas por defecto para todos los usuarios existentes
INSERT INTO `access_rules` (`user_id`, `enabled`, `start_time`, `end_time`, `days`)
SELECT id, 1, '00:00:00', '23:59:59', '1111111'
FROM `usuarios`
WHERE id NOT IN (SELECT user_id FROM `access_rules`);
