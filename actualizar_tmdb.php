<?php
/**
 * Script para actualizar películas sin info de TMDB
 * 
 * Uso: php actualizar_tmdb.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Obtener películas sin info de TMDB
$stmt = $conn->query("SELECT id, titulo, archivo FROM peliculas WHERE sinopsis = '' OR tmdb_id IS NULL OR tmdb_id = 0");
$peliculas = $stmt->fetchAll();

if (empty($peliculas)) {
    echo "Todas las películas ya tienen información de TMDB.\n";
    exit;
}

echo "Se encontraron " . count($peliculas) . " películas sin información de TMDB.\n\n";

$actualizadas = 0;
$errores = 0;

foreach ($peliculas as $pelicula) {
    echo "Procesando: " . $pelicula['titulo'] . " (ID: " . $pelicula['id'] . ")\n";
    
    $info = obtener_info_pelicula_tmdb($pelicula['titulo']);
    
    if ($info) {
        $update = $conn->prepare("
            UPDATE peliculas SET 
                sinopsis = :sinopsis,
                director = :director,
                elenco = :elenco,
                año = :año,
                poster = :poster,
                rating = :rating,
                tmdb_id = :tmdb_id
            WHERE id = :id
        ");
        
        $update->execute([
            ':sinopsis' => $info['sinopsis'] ?? '',
            ':director' => $info['director'] ?? 'Desconocido',
            ':elenco' => $info['elenco'] ?? '',
            ':año' => $info['año'] ?? '',
            ':poster' => $info['poster'] ?? '',
            ':rating' => $info['rating'] ?? 0,
            ':tmdb_id' => $info['tmdb_id'],
            ':id' => $pelicula['id']
        ]);
        
        echo "  ✓ Actualizada: " . $info['año'] . " - Rating: " . ($info['rating'] ?? 'N/A') . "\n";
        $actualizadas++;
    } else {
        echo "  ✗ No se encontró información en TMDB\n";
        $errores++;
    }
    
    // Pausa para no sobrecargar la API de TMDB
    sleep(1);
}

echo "\n=== Resumen ===\n";
echo "Actualizadas: $actualizadas\n";
echo "Errores: $errores\n";
echo "Total: " . count($peliculas) . "\n";
