-- Esquema de la base de datos VideoTeca
-- Versión: 1.0

CREATE DATABASE IF NOT EXISTS `peliculas_db` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `peliculas_db`;

-- Tabla de categorías
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL UNIQUE,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `orden` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Tabla de películas
DROP TABLE IF EXISTS `peliculas`;
CREATE TABLE `peliculas` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `titulo` VARCHAR(500) NOT NULL,
    `categoria_id` INT UNSIGNED NOT NULL,
    `archivo` VARCHAR(500) NOT NULL,
    `extension` VARCHAR(10) DEFAULT NULL,
    `tamano` BIGINT UNSIGNED DEFAULT 0,
    `año` VARCHAR(4) DEFAULT NULL,
    `poster` TEXT DEFAULT NULL,
    `sinopsis` TEXT DEFAULT NULL,
    `director` VARCHAR(200) DEFAULT NULL,
    `elenco` TEXT DEFAULT NULL,
    `tmdb_id` INT DEFAULT NULL,
    `rating` DECIMAL(3,1) DEFAULT NULL,
    `vistas` INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_categoria` (`categoria_id`),
    INDEX `idx_titulo` (`titulo`(255)),
    INDEX `idx_vistas` (`vistas`),
    INDEX `idx_archivo` (`archivo`(255)),
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de usuarios
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `is_admin` TINYINT(1) DEFAULT 0,
    `activo` TINYINT(1) DEFAULT 0,
    `perfil` ENUM('admin', 'usuario') DEFAULT 'usuario',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB;

-- Tabla de reglas de acceso (horarios y restricciones por usuario)
DROP TABLE IF EXISTS `access_rules`;
CREATE TABLE `access_rules` (
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

-- Tabla de configuración del sitio
DROP TABLE IF EXISTS `site_config`;
CREATE TABLE `site_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `config_key` VARCHAR(100) NOT NULL UNIQUE,
    `config_value` TEXT,
    `config_type` VARCHAR(20) DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Valores por defecto
INSERT INTO `site_config` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('tmdb_api_key', '', 'string', 'API Key de TMDB (The Movie Database)'),
('video_folder', '/var/www/videos', 'string', 'Carpeta que alberga los videos'),
('max_upload_size', '104857600', 'integer', 'Tamaño máximo de subida en bytes (100MB por defecto)'),
('allow_registration', '1', 'boolean', 'Permitir registro público de usuarios (1=sí, 0=no)'),
('site_name', 'VideoTeca', 'string', 'Nombre del sitio'),
('site_description', 'Videoteca personal', 'string', 'Descripción del sitio');

-- Tabla de vistas (historial detallado)
DROP TABLE IF EXISTS `vistas`;
CREATE TABLE `vistas` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `pelicula_id` INT UNSIGNED NOT NULL,
    `ip` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(500) DEFAULT NULL,
    `fecha` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pelicula` (`pelicula_id`),
    INDEX `idx_fecha` (`fecha`),
    FOREIGN KEY (`pelicula_id`) REFERENCES `peliculas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
