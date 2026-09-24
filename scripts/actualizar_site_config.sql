-- Migración: agregar tabla site_config para configuración del sitio
-- Ejecutar en: mysql -u movies -p'hP9)ff2pa_' peliculas_db < actualizar_site_config.sql

USE `peliculas_db`;

CREATE TABLE IF NOT EXISTS `site_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_key` VARCHAR(100) NOT NULL UNIQUE,
    `config_value` TEXT,
    `config_type` VARCHAR(20) DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insertar valores por defecto si no existen
INSERT IGNORE INTO `site_config` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('tmdb_api_key', '', 'string', 'API Key de TMDB (The Movie Database)'),
('video_folder', '/var/www/videos', 'string', 'Carpeta que alberga los videos'),
('max_upload_size', '104857600', 'integer', 'Tamaño máximo de subida en bytes (100MB por defecto)'),
('allow_registration', '1', 'boolean', 'Permitir registro público de usuarios (1=sí, 0=no)'),
('site_name', 'VideoTeca', 'string', 'Nombre del sitio'),
('site_description', 'Videoteca personal', 'string', 'Descripción del sitio');
