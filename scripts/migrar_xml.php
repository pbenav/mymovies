#!/usr/bin/env php
<?php
/**
 * Migrar datos desde info_peliculas.xml a la base de datos
 * 
 * Uso: php scripts/migrar_xml.php
 * 
 * Este script lee info_peliculas.xml y actualiza:
 * - Título de las películas
 * - Año
 * - Director
 * - Elenco
 * - Sinopsis
 * - TMDB ID (si está disponible)
 */

require_once __DIR__ . '/../includes/db.php';

$xmlFile = __DIR__ . '/../info_peliculas.xml';

if (!file_exists($xmlFile)) {
    echo "ERROR: No se encontró info_peliculas.xml en el directorio raíz.\n";
    exit(1);
}

echo "╔══════════════════════════════════════════╗\n";
echo "║   Migrando datos desde XML a la BD       ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

$conn = db();
$xml = simplexml_load_file($xmlFile);

if (!$xml) {
    echo "ERROR: No se pudo leer el archivo XML.\n";
    exit(1);
}

$actualizadas = 0;
$no_encontradas = 0;
$errores = 0;

// Determinar la estructura del XML
foreach ($xml->children() as $pelicula) {
    $archivo = (string)$pelicula->archivo;
    $titulo = (string)$pelicula->titulo;
    $año = (string)$pelicula->año;
    $director = (string)$pelicula->director;
    $elenco = (string)$pelicula->elenco;
    $sinopsis = (string)$pelicula->sinopsis;
    $tmdb_id = (string)$pelicula->tmdb_id;

    // Buscar película por archivo
    $update = $conn->prepare("
        UPDATE peliculas SET
            titulo = COALESCE(NULLIF(:titulo, ''), titulo),
            año = COALESCE(NULLIF(:año, ''), año),
            director = COALESCE(NULLIF(:director, ''), director),
            elenco = COALESCE(NULLIF(:elenco, ''), elenco),
            sinopsis = COALESCE(NULLIF(:sinopsis, ''), sinopsis),
            tmdb_id = COALESCE(NULLIF(:tmdb_id, ''), tmdb_id)
        WHERE archivo = :archivo
    ");

    try {
        $update->execute([
            ':titulo' => $titulo,
            ':año' => $año,
            ':director' => $director,
            ':elenco' => $elenco,
            ':sinopsis' => $sinopsis,
            ':tmdb_id' => $tmdb_id,
            ':archivo' => $archivo,
        ]);
        
        if ($update->rowCount() > 0) {
            $actualizadas++;
        } else {
            $no_encontradas++;
        }
    } catch (Exception $e) {
        $errores++;
    }
}

echo "╔══════════════════════════════════════════╗\n";
echo "║   Resumen de migración                   ║\n";
echo "╚══════════════════════════════════════════╝\n";
echo "  Actualizadas:    $actualizadas\n";
echo "  No encontradas:  $no_encontradas\n";
echo "  Errores:         $errores\n";
echo "\n";
