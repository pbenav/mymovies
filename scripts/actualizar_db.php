#!/usr/bin/env php
<?php
/**
 * Actualizar base de datos - Escanea video/ y sincroniza con la BD
 * 
 * Uso: php scripts/actualizar_db.php
 * 
 * Este script:
 * 1. Lee todas las categorías (subdirectorios) de video/
 * 2. Inserta películas que no existan en la BD
 * 3. Elimina de la BD las películas que ya no existen en disco
 * 4. Muestra un resumen al final
 */

require_once __DIR__ . '/../includes/db.php';

$videoPath = VIDEO_PATH;

if (!is_dir($videoPath)) {
    echo "ERROR: La ruta de videos no existe: $videoPath\n";
    echo "Ejecuta install.php primero para configurar la ruta.\n";
    exit(1);
}

echo "╔══════════════════════════════════════════╗\n";
echo "║   Sincronizando películas con la BD      ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

$conn = db();

// Obtener categorías del disco
$items = scandir($videoPath);
$categorias = [];
foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    if ($item === 'Convertidas') continue;
    if (is_dir($videoPath . '/' . $item)) {
        $categorias[] = $item;
    }
}
sort($categorias);

echo "Categorías encontradas: " . count($categorias) . "\n\n";

// Crear/verificar categorías en BD
$catInsert = $conn->prepare("INSERT IGNORE INTO categorias (nombre, slug) VALUES (:nombre, :slug)");
foreach ($categorias as $catNombre) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $catNombre)));
    $catInsert->execute([':nombre' => $catNombre, ':slug' => $slug]);
}

// Obtener categorías de la BD
$catSelect = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre");
$categoriasBD = [];
while ($cat = $catSelect->fetch()) {
    $categoriasBD[$cat['nombre']] = $cat['id'];
}

// Extensiones de video soportadas
$extensiones = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', 'm2ts', 'ts'];

// Insertar películas
$peliInsert = $conn->prepare("
    INSERT INTO peliculas (titulo, categoria_id, archivo, extension, tamano, vistas)
    VALUES (:titulo, :cat_id, :archivo, :ext, :tamano, 0)
    ON DUPLICATE KEY UPDATE titulo = :titulo2
");

$stats = ['nuevas' => 0, 'existente' => 0, 'errores' => 0];

foreach ($categorias as $catNombre) {
    if (!isset($categoriasBD[$catNombre])) continue;
    
    $catId = $categoriasBD[$catNombre];
    $catDir = $videoPath . '/' . $catNombre;
    
    $videos = scandir($catDir);
    $nuevasEnCat = 0;
    
    foreach ($videos as $video) {
        if ($video === '.' || $video === '..') continue;
        if ($video === 'Convertidas') continue;
        
        $filePath = $catDir . '/' . $video;
        
        // Si es un directorio (y no Convertidas), escanear recursivamente
        if (is_dir($filePath)) {
            $subVideos = scandir($filePath);
            foreach ($subVideos as $subVideo) {
                if ($subVideo === '.' || $subVideo === '..') continue;
                
                $subExt = strtolower(pathinfo($subVideo, PATHINFO_EXTENSION));
                if (!in_array($subExt, $extensiones)) continue;
                
                $subFilePath = $filePath . '/' . $subVideo;
                if (!is_file($subFilePath)) continue;
                
                $tamano = filesize($subFilePath);
                $titulo = pathinfo($subVideo, PATHINFO_FILENAME);
                $titulo = preg_replace('/\s*[\(\\[].*?[\)\\]]/s', '', $titulo);
                $titulo = preg_replace('/\s+/', ' ', trim($titulo));
                
                $archivo = "$catNombre/$video/$subVideo";
                
                $check = $conn->prepare("SELECT id FROM peliculas WHERE archivo = :archivo LIMIT 1");
                $check->execute([':archivo' => $archivo]);
                
                if ($check->fetch()) {
                    $stats['existente']++;
                } else {
                    try {
                        $peliInsert->execute([
                            ':titulo' => $titulo,
                            ':cat_id' => $catId,
                            ':archivo' => $archivo,
                            ':ext' => $subExt,
                            ':tamano' => $tamano,
                            ':titulo2' => $titulo,
                        ]);
                        $stats['nuevas']++;
                        $nuevasEnCat++;
                    } catch (Exception $e) {
                        $stats['errores']++;
                    }
                }
            }
            continue;
        }
        
        $ext = strtolower(pathinfo($video, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensiones)) continue;
        
        if (!is_file($filePath)) continue;
        
        $tamano = filesize($filePath);
        $titulo = pathinfo($video, PATHINFO_FILENAME);
        // Limpiar título
        $titulo = preg_replace('/\s*[\(\\[].*?[\)\\]]/s', '', $titulo);
        $titulo = preg_replace('/\s+/', ' ', trim($titulo));
        
        $archivo = "$catNombre/$video";
        
        // Verificar si ya existe
        $check = $conn->prepare("SELECT id FROM peliculas WHERE archivo = :archivo LIMIT 1");
        $check->execute([':archivo' => $archivo]);
        
        if ($check->fetch()) {
            $stats['existente']++;
        } else {
            try {
                $peliInsert->execute([
                    ':titulo' => $titulo,
                    ':cat_id' => $catId,
                    ':archivo' => $archivo,
                    ':ext' => $ext,
                    ':tamano' => $tamano,
                    ':titulo2' => $titulo,
                ]);
                $stats['nuevas']++;
                $nuevasEnCat++;
            } catch (Exception $e) {
                $stats['errores']++;
            }
        }
    }
    
    if ($nuevasEnCat > 0) {
        echo "  $catNombre: +$nuevasEnCat nuevas\n";
    }
}

// Opcional: eliminar películas que ya no existen en disco
if (isset($_SERVER['argv']) && in_array('--limpiar', $_SERVER['argv'])) {
    echo "\nEliminando películas que ya no existen en disco...\n";
    
    $allPeliculas = $conn->query("SELECT id, archivo FROM peliculas");
    $archivoBD = [];
    while ($p = $allPeliculas->fetch()) {
        $archivoBD[$p['archivo']] = $p['id'];
    }
    
    // Reconstruir lista de archivos en disco
    $archivosDisco = [];
    foreach ($categorias as $catNombre) {
        if (!is_dir($videoPath . '/' . $catNombre)) continue;
        $videos = scandir($videoPath . '/' . $catNombre);
        foreach ($videos as $video) {
            if ($video === '.' || $video === '..') continue;
            if ($video === 'Convertidas') continue;
            
            $filePath = $videoPath . '/' . $catNombre . '/' . $video;
            
            // Soporte para archivos dentro de subdirectorios
            if (is_dir($filePath)) {
                $subVideos = scandir($filePath);
                foreach ($subVideos as $subVideo) {
                    if ($subVideo === '.' || $subVideo === '..') continue;
                    $subExt = strtolower(pathinfo($subVideo, PATHINFO_EXTENSION));
                    if (in_array($subExt, $extensiones)) {
                        $archivosDisco[] = "$catNombre/$video/$subVideo";
                    }
                }
            } else {
                $ext = strtolower(pathinfo($video, PATHINFO_EXTENSION));
                if (in_array($ext, $extensiones)) {
                    $archivosDisco[] = "$catNombre/$video";
                }
            }
        }
    }
    
    $archivosEliminar = array_diff_key($archivoBD, array_flip($archivosDisco));
    if (!empty($archivosEliminar)) {
        $deleteStmt = $conn->prepare("DELETE FROM peliculas WHERE archivo = :archivo");
        foreach ($archivosEliminar as $archivo => $id) {
            $deleteStmt->execute([':archivo' => $archivo]);
        }
        echo "  Eliminadas: " . count($archivosEliminar) . " películas\n";
    } else {
        echo "  Ninguna película por eliminar.\n";
    }
}

echo "\n";
echo "╔══════════════════════════════════════════╗\n";
echo "║   Resumen de sincronización              ║\n";
echo "╚══════════════════════════════════════════╝\n";
echo "  Nuevas:      {$stats['nuevas']}\n";
echo "  Existente:   {$stats['existente']}\n";
echo "  Errores:     {$stats['errores']}\n";
echo "\n";
echo "Tip: Usa --limpiar para eliminar películas borradas del disco.\n";
echo "  php scripts/actualizar_db.php --limpiar\n";
echo "Tip: Usa --enriquecer para obtener metadata de TMDB.\n";
echo "  php scripts/actualizar_db.php --enriquecer\n";

// ============================================================
// Enriquecer con TMDB (opcional, flag --enriquecer)
// ============================================================
if (isset($_SERVER['argv']) && in_array('--enriquecer', $_SERVER['argv'])) {
    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
        echo "\nERROR: TMDB_API_KEY no está configurada en includes/config.php\n";
        exit(1);
    }
    
    echo "\n";
    echo "╔══════════════════════════════════════════╗\n";
    echo "║   Enriqueciendo con TMDB                 ║\n";
    echo "╚══════════════════════════════════════════╝\n\n";
    
    $allPeliculas = $conn->query("SELECT id, titulo FROM peliculas ORDER BY id");
    $total = $allPeliculas->rowCount();
    $enriquecidas = 0;
    $fallidas = 0;
    $numero = 0;
    
    echo "Total de películas a procesar: $total\n\n";
    
    while ($peli = $allPeliculas->fetch()) {
        $numero++;
        $info = obtener_info_pelicula_tmdb($peli['titulo']);
        if ($info) {
            $update = $conn->prepare("
                UPDATE peliculas SET 
                `año` = :ano, poster = :poster, sinopsis = :sinopsis,
                director = :director, elenco = :elenco, tmdb_id = :tmdb_id,
                rating = :rating
                WHERE id = :id
            ");
            $update->execute([
                ':ano' => $info['año'],
                ':poster' => $info['poster'],
                ':sinopsis' => $info['sinopsis'],
                ':director' => $info['director'],
                ':elenco' => $info['elenco'],
                ':tmdb_id' => $info['tmdb_id'],
                ':rating' => $info['rating'],
                ':id' => $peli['id'],
            ]);
            $enriquecidas++;
        } else {
            $fallidas++;
        }
        
        if ($numero % 10 === 0 || $numero >= $total) {
            $pct = round(($numero / $total) * 100);
            echo "  Progreso: $numero/$total ($pct%) - $enriquecidas OK, $fallidas sin resultados\n";
        }
        
        usleep(250000); // 250ms entre peticiones
    }
    
    echo "\n";
    echo "╔══════════════════════════════════════════╗\n";
    echo "║   Resumen TMDB                           ║\n";
    echo "╚══════════════════════════════════════════╝\n";
    echo "  Enriquecidas: $enriquecidas\n";
    echo "  Sin resultados: $fallidas\n";
    echo "\n";
}
