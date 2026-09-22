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
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB;

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
