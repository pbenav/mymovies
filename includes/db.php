<?php
/**
 * Conexión a la base de datos y funciones SQL comunes
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            // No die() aquí - permitir que la página muestre un mensaje amigable
            $this->conn = null;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        if ($this->conn === null) {
            throw new Exception("No se pudo conectar a la base de datos. Verifica includes/config.php");
        }
        return $this->conn;
    }

    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}

function db() {
    return Database::getInstance()->getConnection();
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function getPagination($total, $perPage, $currentPage) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'current'     => $currentPage,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $currentPage > 1,
        'has_next'    => $currentPage < $totalPages,
    ];
}

function obtener_info_pelicula_tmdb($titulo) {
    if (empty($titulo)) return null;

    $titulo_limpio = $titulo;
    
    // Eliminar URLs completas (ej: www.lokotorrents.com, http://...)
    $titulo_limpio = preg_replace('#https?://\S+#i', '', $titulo_limpio);
    $titulo_limpio = preg_replace('#www\.\S+#i', '', $titulo_limpio);
    
    // Eliminar dominios y nombres de sitios
    $titulo_limpio = preg_replace('#\.(com|org|net|es|tv|co)\b#i', '', $titulo_limpio);
    
    // Eliminar palabras de formato y calidad
    $basura = ['spanish', 'castellano', 'latino', 'english', 'ingles', 'subs', 
               '1080p', '720p', '4k', 'uhd', 'bluray', 'bdrip', 'brrip',
               'xvid', 'h264', 'h265', 'x264', 'hevc', 'ac3', 'dts',
               'microhd', 'web-dl', 'webdl', 'dvdrip', 'dvdscreener',
               'vidmono', 'vidomon', 'torrent', 'download', 'free'];

    foreach ($basura as $palabra) {
        $titulo_limpio = preg_replace('/\b' . preg_quote($palabra, '/') . '\b/i', '', $titulo_limpio);
    }
    
    // Eliminar todo entre paréntesis y corchetes (incluyendo años dentro de ellos)
    $titulo_limpio = preg_replace('/\s*[\(\[].*?[\)\]]/', '', $titulo_limpio);
    
    // Eliminar años sueltos al final (ej: "Pelicula 1991" -> "Pelicula")
    $titulo_limpio = preg_replace('/\s+\d{4}\s*$/', '', $titulo_limpio);
    
    // Eliminar puntos y comas sobrantes
    $titulo_limpio = str_replace(['.', ','], '', $titulo_limpio);
    
    // Limpiar espacios múltiples
    $titulo_limpio = trim(preg_replace('/\s+/', ' ', $titulo_limpio));

    if (empty($titulo_limpio)) return null;

    $query = urlencode($titulo_limpio);
    $url = TMDB_BASE_URL . "/search/movie?api_key=" . TMDB_API_KEY . "&query={$query}&language=" . TMDB_LANG;

    $response = hacer_request_tmdb($url);
    if (!$response) return null;
    
    $data = json_decode($response, true);
    if (!isset($data['results'][0])) return null;

    $peli = $data['results'][0];
    $movie_id = $peli['id'];

    $url_details = TMDB_BASE_URL . "/movie/{$movie_id}?api_key=" . TMDB_API_KEY . "&append_to_response=credits&language=" . TMDB_LANG;
    $response_details = hacer_request_tmdb($url_details);
    if (!$response_details) {
        // Sin detalles, devolver al menos lo básico
        return [
            'año'      => substr($peli['release_date'] ?? '----', 0, 4),
            'poster'   => !empty($peli['poster_path']) ? TMDB_IMG_BASE . '/w500' . $peli['poster_path'] : '',
            'sinopsis' => $peli['overview'] ?? '',
            'director' => 'Desconocido',
            'elenco'   => '',
            'tmdb_id'  => $movie_id,
            'rating'   => round($peli['vote_average'] ?? 0, 1),
        ];
    }
    
    $detalles = json_decode($response_details, true);

    $director = 'Desconocido';
    if (isset($detalles['credits']['crew'])) {
        foreach ($detalles['credits']['crew'] as $member) {
            if ($member['job'] === 'Director') {
                $director = $member['name'];
                break;
            }
        }
    }

    $elenco_arr = [];
    if (isset($detalles['credits']['cast'])) {
        $cast = array_slice($detalles['credits']['cast'], 0, 5);
        foreach ($cast as $actor) {
            $elenco_arr[] = $actor['name'];
        }
    }

    return [
        'año'      => substr($peli['release_date'] ?? '----', 0, 4),
        'poster'   => !empty($peli['poster_path']) ? TMDB_IMG_BASE . '/w500' . $peli['poster_path'] : '',
        'sinopsis' => $peli['overview'] ?? '',
        'director' => $director,
        'elenco'   => implode(', ', $elenco_arr),
        'tmdb_id'  => $movie_id,
        'rating'   => round($peli['vote_average'] ?? 0, 1),
    ];
}

function hacer_request_tmdb($url) {
    // Intentar con cURL primero, fallback a file_get_contents
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'VideoTeca/1.0',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            return $response;
        }
    }
    
    // Fallback a file_get_contents
    $opts = [
        'http' => [
            'method'  => 'GET',
            'header'  => 'User-Agent: VideoTeca/1.0',
            'timeout' => 15,
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    return ($response && strlen($response) > 0) ? $response : null;
}
