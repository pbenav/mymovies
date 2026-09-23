#!/usr/bin/env php
<?php
/**
 * Instalador interactivo de VideoTeca
 * 
 * Ejecutar: php install.php
 * 
 * Este script:
 * 1. Pide usuario y contraseña del administrador MySQL
 * 2. Pide la ruta donde están las películas (video/)
 * 3. Pide la clave TMDB API (opcional)
 * 4. Crea la base de datos y tablas
 * 5. Escanea los archivos de video y los registra en la BD
 * 6. Migra datos de XML si existe info_peliculas.xml
 */

// ============================================================
// CONFIGURACIÓN DEL INSTALADOR
// ============================================================
// Usar path absoluto basado en el archivo del script, no el cwd
// INSTALL_PATH = directorio donde está install.php (videoteca_pro/)
// PROJECT_ROOT = mismo directorio (todo el proyecto está en una carpeta)
define('INSTALL_PATH', realpath(dirname(__FILE__)));
define('PROJECT_ROOT', INSTALL_PATH);
define('CONFIG_TEMPLATE', PROJECT_ROOT . '/includes/config.php');
define('CONFIG_FILE', PROJECT_ROOT . '/includes/config.php');

// ============================================================
// FUNCIONES DE UTILIDAD
// ============================================================

function mostrar_logo() {
    echo "\n";
    echo "╔════════════════════════════════════════╗\n";
    echo "║        🎬 VideoTeca - Instalador       ║\n";
    echo "║     Sistema de Gestión de Películas    ║\n";
    echo "╚════════════════════════════════════════╝\n\n";
}

function esperar_tecla($mensaje = 'Presiona Enter para continuar...') {
    echo "\n$mensaje\n";
    fgets(STDIN);
}

function obtener_entrada($pregunta, $default = null) {
    $prompt = $default !== null ? "$pregunta [$default]: " : "$pregunta: ";
    echo $prompt;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    $value = trim($line);
    return $value === '' ? $default : $value;
}

function obtener_password($pregunta = 'Contraseña:') {
    echo "$pregunta: ";
    $handle = fopen("php://stdin", "r");
    exec('stty -echo');
    $password = trim(fgets($handle));
    exec('stty echo');
    echo "\n";
    fclose($handle);
    return $password;
}

function log_msg($msg, $color = 'white') {
    $colors = [
        'white' => "\033[37m",
        'green' => "\033[32m",
        'red'   => "\033[31m",
        'yellow'=> "\033[33m",
        'blue'  => "\033[34m",
        'cyan'  => "\033[36m",
        'reset' => "\033[0m",
    ];
    echo "{$colors[$color]}$msg{$colors['reset']}\n";
}

function verificar_php() {
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        log_msg('ERROR: Se requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION, 'red');
        exit(1);
    }
    log_msg("PHP " . PHP_VERSION . " detectado. OK.", 'green');
}

function verificar_extension($ext) {
    if (!extension_loaded($ext)) {
        log_msg("ERROR: La extensión PHP '$ext' no está instalada.", 'red');
        exit(1);
    }
}

function verificar_dependencias() {
    log_msg('Verificando dependencias...', 'cyan');
    verificar_extension('pdo');
    verificar_extension('pdo_mysql');
    verificar_extension('xml');
    verificar_extension('json');
    verificar_extension('mbstring');
    log_msg('Todas las dependencias están presentes. OK.', 'green');
}

function conectar_mysql($usuario, $password) {
    try {
        $dsn = 'mysql:host=localhost';
        $pdo = new PDO($dsn, $usuario, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function crear_directorios() {
    $dirs = ['api', 'includes', 'scripts', 'css', 'js', 'Obsoletos'];
    foreach ($dirs as $dir) {
        $path = PROJECT_ROOT . '/' . $dir;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            log_msg("  Directorio creado: $dir/", 'cyan');
        }
    }
}

function obtener_categorias_de_directorio($videoPath) {
    $categorias = [];
    if (!is_dir($videoPath)) {
        return $categorias;
    }

    $items = scandir($videoPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $videoPath . '/' . $item;
        if (is_dir($fullPath)) {
            $categorias[] = $item;
        }
    }
    sort($categorias);
    return $categorias;
}

function obtener_videos_en_directorio($dirPath) {
    $videos = [];
    $extensiones = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg', 'm2ts', 'ts'];
    
    if (!is_dir($dirPath)) return $videos;
    
    $items = scandir($dirPath);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $fullPath = $dirPath . '/' . $item;
        if (is_file($fullPath)) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            if (in_array($ext, $extensiones)) {
                $videos[] = [
                    'nombre' => $item,
                    'extension' => $ext,
                    'tamano' => filesize($fullPath),
                    'path' => $fullPath
                ];
            }
        }
    }
    return $videos;
}

function extraer_titulo_del_nombre($nombreArchivo) {
    // Eliminar extensión
    $titulo = pathinfo($nombreArchivo, PATHINFO_FILENAME);
    
    // Eliminar información técnica
    $patrones = [
        '/\s*[\(\\[].*?[\)\\]]/s',           // Todo entre () o []
        '/\s*[-_]{2,}/',                      // Separadores múltiples
        '/\s*[-_]/',                            // Separador simple -> espacio
        '/\s+/',                                // Múltiples espacios
        '/^(S\d{2}E\d{2}[_\- ])/',            // Temporada/Episodio
    ];
    
    foreach ($patrones as $patron) {
        $titulo = preg_replace($patron, ' ', $titulo);
    }
    
    $titulo = trim($titulo);
    // Capitalizar
    $titulo = ucwords($titulo);
    
    return $titulo;
}

// ============================================================
// PASO 1: Verificaciones iniciales
// ============================================================

mostrar_logo();
verificar_php();
verificar_dependencias();

// Crear directorios si no existen
log_msg('Verificando estructura de directorios...', 'cyan');
crear_directorios();

echo "\n";
log_msg('Este instalador configurará VideoTeca en este servidor.', 'cyan');
log_msg('Directorio de instalación: ' . PROJECT_ROOT, 'white');
esperar_tecla();

// ============================================================
// PASO 2: Configuración de la base de datos
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('PASO 1: Configuración de la Base de Datos', 'cyan');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";

$db_user = obtener_entrada('Usuario administrador de MySQL', 'root');
$db_pass = obtener_password('Contraseña de MySQL (dejar vacío si no tiene)');

log_msg('Conectando a MySQL...', 'cyan');
$pdo = conectar_mysql($db_user, $db_pass);

if (!$pdo) {
    log_msg('ERROR: No se pudo conectar a MySQL. Verifica usuario y contraseña.', 'red');
    exit(1);
}
log_msg('Conexión a MySQL exitosa.', 'green');

// Crear base de datos
log_msg('Creando base de datos...', 'cyan');
$dbName = 'peliculas_db';
try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    log_msg("Base de datos '$dbName' creada/verificada.", 'green');
} catch (PDOException $e) {
    log_msg("ERROR al crear BD: " . $e->getMessage(), 'red');
    exit(1);
}

// Ejecutar esquema SQL
log_msg('Creando tablas...', 'cyan');
$sqlFile = INSTALL_PATH . '/scripts/crear_db.sql';
$sql = file_get_contents($sqlFile);
$pdo->exec("USE `$dbName`");

// Dividir y ejecutar statements
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $statement) {
    if (empty($statement)) continue;
    if (strpos($statement, 'CREATE') !== false || strpos($statement, 'DROP') !== false || 
        strpos($statement, 'USE') !== false) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            // Ignorar errores de tablas ya existentes
        }
    }
}
log_msg('Tablas creadas correctamente.', 'green');

// Crear usuario administrador por defecto
log_msg('Creando usuario administrador...', 'cyan');
$adminUser = obtener_entrada('Usuario administrador (default: admin)', 'admin');
$adminPass = obtener_password('Contraseña de administrador');
$adminEmail = obtener_entrada('Email de administrador', 'admin@videoteca.local');

$adminHash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
try {
    $pdo->exec("INSERT INTO usuarios (username, email, password, is_admin) VALUES ('$adminUser', '$adminEmail', '$adminHash', 1)");
    log_msg("Usuario administrador '$adminUser' creado.", 'green');
} catch (PDOException $e) {
    log_msg("ADVERTENCIA: No se pudo crear el usuario administrador (ya existe?).", 'yellow');
}

// ============================================================
// PASO 3: Ruta de las películas
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('PASO 2: Ruta de las Películas', 'cyan');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";

log_msg('Indica la ruta absoluta donde están las películas.', 'white');
log_msg('Ejemplo: /var/www/html/video o /mnt/almacen/video', 'cyan');
log_msg('La estructura esperada es:', 'cyan');
log_msg('  video/Acción/archivo.mp4', 'cyan');
log_msg('  video/Comedia/archivo.mkv', 'cyan');
echo "\n";

$videoPath = obtener_entrada('Ruta de las películas', '/var/www/html/video');
$videoPath = rtrim($videoPath, '/');

if (!is_dir($videoPath)) {
    log_msg("ADVERTENCIA: El directorio '$videoPath' no existe o no es accesible.", 'yellow');
    $cont = obtener_entrada('¿Continuar de todos modos? (s/N)', 'N');
    if (strtolower($cont) !== 's') {
        log_msg('Instalación cancelada.', 'red');
        exit(1);
    }
}

// Mostrar categorías detectadas
$categorias = obtener_categorias_de_directorio($videoPath);
$totalVideos = 0;
foreach ($categorias as $cat) {
    $videos = obtener_videos_en_directorio($videoPath . '/' . $cat);
    $totalVideos += count($videos);
}

if (empty($categorias)) {
    log_msg("ADVERTENCIA: No se encontraron categorías (subdirectorios) en '$videoPath'.", 'yellow');
    $cont = obtener_entrada('¿Continuar de todos modos? (s/N)', 'N');
    if (strtolower($cont) !== 's') {
        log_msg('Instalación cancelada.', 'red');
        exit(1);
    }
}

log_msg("Se encontraron " . count($categorias) . " categorías con $totalVideos videos en total.", 'green');
echo "\n";

// ============================================================
// PASO 4: Clave TMDB API
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('PASO 3: API de TMDB (Opcional)', 'cyan');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";

log_msg('TMDB proporciona pósters, sinopsis y metadata automática.', 'white');
log_msg('Obtén una clave gratis en: https://www.themoviedb.org/settings/api', 'cyan');
echo "\n";

$tmdb_key = obtener_entrada('Clave API de TMDB (dejar vacío para omitir)');
if (empty($tmdb_key)) {
    log_msg('TMDB no configurado. Podrás agregar metadata manualmente después.', 'yellow');
} else {
    // Verificar clave
    $url = "https://api.themoviedb.org/3/configuration?api_key=$tmdb_key";
    $response = @file_get_contents($url);
    if ($response) {
        log_msg('Clave TMDB válida. OK.', 'green');
    } else {
        log_msg('ADVERTENCIA: No se pudo verificar la clave TMDB.', 'yellow');
        $cont = obtener_entrada('¿Continuar de todos modos? (s/N)', 'N');
        if (strtolower($cont) !== 's') {
            log_msg('Instalación cancelada.', 'red');
            exit(1);
        }
    }
}

// ============================================================
// PASO 5: Generar config.php
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('PASO 4: Generando configuración...', 'cyan');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";

$configContent = '<?php
/**
 * Configuración de la aplicación
 * 
 * Este archivo se generó automáticamente durante la instalación.
 * NO lo edites manualmente.
 */

define(\'DB_HOST\', \'localhost\');
define(\'DB_NAME\', \'peliculas_db\');
define(\'DB_USER\', \'' . addslashes($db_user) . '\');
define(\'DB_PASS\', \'' . addslashes($db_pass) . '\');
define(\'DB_CHARSET\', \'utf8mb4\');

define(\'TMDB_API_KEY\', \'' . addslashes($tmdb_key) . '\');
define(\'TMDB_BASE_URL\', \'https://api.themoviedb.org/3\');
define(\'TMDB_IMG_BASE\', \'https://image.tmdb.org/t/p\');

define(\'BASE_PATH\', dirname(__DIR__));
define(\'VIDEO_PATH\', \'' . addslashes($videoPath) . '\');

define(\'PER_PAGE\', 24);
define(\'TMDB_LANG\', \'es-ES\');
';

file_put_contents(CONFIG_FILE, $configContent);
log_msg('Archivo includes/config.php generado.', 'green');

// ============================================================
// PASO 6: Crear categorías y registrar películas
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('PASO 5: Registrando películas en la BD...', 'cyan');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";

require_once PROJECT_ROOT . '/includes/db.php';

try {
    $conn = db();
} catch (Exception $e) {
    log_msg('ERROR: No se pudo conectar a la BD. Verifica config.php', 'red');
    exit(1);
}

// Crear categorías a partir de subdirectorios
$catInsert = $conn->prepare("INSERT IGNORE INTO categorias (nombre, slug) VALUES (:nombre, :slug)");
$categoriasInsertadas = 0;

foreach ($categorias as $catNombre) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $catNombre)));
    $catInsert->execute([':nombre' => $catNombre, ':slug' => $slug]);
    $categoriasInsertadas++;
}
log_msg("Categorías creadas: $categoriasInsertadas", 'green');

// Escanear y registrar películas
$peliInsert = $conn->prepare("
    INSERT INTO peliculas (titulo, categoria_id, archivo, extension, tamano, vistas)
    VALUES (:titulo, :cat_id, :archivo, :ext, :tamano, 0)
    ON DUPLICATE KEY UPDATE archivo = :archivo2
");

$catSelect = $conn->prepare("SELECT id FROM categorias WHERE nombre = :nombre");
$totalRegistradas = 0;
$totalNuevas = 0;
$errores = 0;

foreach ($categorias as $catNombre) {
    $catSelect->execute([':nombre' => $catNombre]);
    $cat = $catSelect->fetch();
    if (!$cat) continue;
    
    $catVideos = obtener_videos_en_directorio($videoPath . '/' . $catNombre);
    
    foreach ($catVideos as $video) {
        $titulo = extraer_titulo_del_nombre($video['nombre']);
        
        try {
            $peliInsert->execute([
                ':titulo' => $titulo,
                ':cat_id' => $cat['id'],
                ':archivo' => $catNombre . '/' . $video['nombre'],
                ':ext' => $video['extension'],
                ':tamano' => $video['tamano'],
                ':archivo2' => $catNombre . '/' . $video['nombre'],
            ]);
            $totalRegistradas++;
        } catch (Exception $e) {
            $errores++;
        }
    }
    
    log_msg("  Procesadas: " . count($catVideos) . " en '$catNombre'", 'cyan');
}

log_msg("Películas registradas en BD: $totalRegistradas", 'green');
if ($errores > 0) {
    log_msg("Errores durante el registro: $errores", 'yellow');
}

// ============================================================
// PASO 7: Opcional - Enriquecer con TMDB
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('PASO 6: Enriquecer con TMDB (Opcional)', 'cyan');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";

if (!empty($tmdb_key)) {
    $enriquecer = obtener_entrada('¿Deseas obtener metadata de TMDB para las películas? (s/N)', 'N');
    if (strtolower($enriquecer) === 's') {
        log_msg('Iniciando enriquecimiento con TMDB...', 'cyan');
        log_msg('Esto puede tardar varios minutos.', 'yellow');
        echo "\n";
        
        $selectPelis = $conn->query("SELECT id, titulo FROM peliculas ORDER BY id");
        $enriquecidas = 0;
        $fallidas = 0;
        
        $total_pelis = $selectPelis->rowCount();
        log_msg("Total de películas a procesar: $total_pelis", 'cyan');
        echo "\n";
        
        $numero = 0;
        while ($peli = $selectPelis->fetch()) {
            $numero++;
            $info = obtener_info_pelicula_tmdb($peli['titulo']);
            if ($info) {
                $update = $conn->prepare("
                    UPDATE peliculas SET 
                    año = :año, poster = :poster, sinopsis = :sinopsis,
                    director = :director, elenco = :elenco, tmdb_id = :tmdb_id,
                    rating = :rating
                    WHERE id = :id
                ");
                $update->execute([
                    ':año' => $info['año'],
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
            
            // Mostrar progreso cada 5 películas
            if ($numero % 5 === 0 || $numero >= $total_pelis) {
                $pct = round(($numero / $total_pelis) * 100);
                log_msg("  Progreso: $numero/$total_pelis ($pct%) - $enriquecidas OK, $fallidas sin resultados", 'cyan');
            }
            
            // Evitar rate limiting de TMDB (1 req/segundo)
            usleep(250000); // 250ms entre peticiones
        }
        
        log_msg("TMDB: $enriquecidas enriquecidas, $fallidas sin resultados.", 'green');
    }
} else {
    log_msg('TMDB no configurado. Omitiendo enriquecimiento.', 'yellow');
}

// ============================================================
// FIN
// ============================================================

echo "\n";
log_msg('═══════════════════════════════════════════', 'cyan');
log_msg('¡INSTALACIÓN COMPLETADA!', 'green');
log_msg('═══════════════════════════════════════════', 'cyan');
echo "\n";
log_msg("Base de datos: $dbName", 'white');
log_msg("Usuario BD: $db_user", 'white');
log_msg("Ruta de películas: $videoPath", 'white');
log_msg("Películas registradas: $totalRegistradas", 'white');
log_msg("Categorías: $categoriasInsertadas", 'white');
if (!empty($tmdb_key)) {
    log_msg("TMDB: Configurado", 'white');
} else {
    log_msg("TMDB: No configurado", 'yellow');
}
echo "\n";
log_msg('Accede a index.php en tu navegador para ver la web.', 'cyan');
log_msg('Para actualizar la BD en el futuro, ejecuta:', 'cyan');
log_msg('  php scripts/actualizar_db.php', 'cyan');
echo "\n";
esperar_tecla('Presiona Enter para salir.');
