<?php
/**
 * API REST - Endpoint único para todas las peticiones AJAX
 * 
 * Uso: api/endpoint.php?action=listado&categoria=accion&pagina=2
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/db.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'listado':
            apiListado();
            break;
        case 'buscar':
            apiBuscar();
            break;
        case 'detalle':
            apiDetalle();
            break;
        case 'top_vistas':
            apiTopVistas();
            break;
        case 'categorias':
            apiCategorias();
            break;
        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function apiListado() {
    $categoria = $_GET['categoria'] ?? '';
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $limit = PER_PAGE;
    $offset = ($pagina - 1) * $limit;

    $where = '';
    $params = [];
    
    if (!empty($categoria)) {
        if (is_numeric($categoria)) {
            $where = 'WHERE p.categoria_id = :cat';
            $params[':cat'] = $categoria;
        } else {
            $where = 'WHERE c.nombre = :cat';
            $params[':cat'] = $categoria;
        }
    }

    $countSql = "SELECT COUNT(*) FROM peliculas p LEFT JOIN categorias c ON p.categoria_id = c.id $where";
    $countStmt = db()->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM peliculas p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            $where 
            ORDER BY p.vistas DESC 
            LIMIT $limit OFFSET $offset";
    
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $peliculas = $stmt->fetchAll();

    echo json_encode([
        'peliculas' => $peliculas,
        'pagination' => getPagination($total, $limit, $pagina),
        'categoria' => $categoria
    ]);
}

function apiBuscar() {
    $termino = $_GET['termino'] ?? '';
    if (strlen($termino) < 2) {
        echo json_encode(['peliculas' => []]);
        return;
    }

    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM peliculas p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.titulo LIKE :termino 
            ORDER BY p.vistas DESC 
            LIMIT 20";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([':termino' => "%$termino%"]);
    $peliculas = $stmt->fetchAll();

    echo json_encode(['peliculas' => $peliculas]);
}

function apiDetalle() {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'ID inválido']);
        return;
    }

    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug
            FROM peliculas p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.id = :id";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $pelicula = $stmt->fetch();

    if (!$pelicula) {
        echo json_encode(['error' => 'Película no encontrada']);
        return;
    }

    // Incrementar vistas
    $update = db()->prepare("UPDATE peliculas SET vistas = vistas + 1 WHERE id = :id");
    $update->execute([':id' => $id]);

    // Obtener similares
    $similarSql = "SELECT p.*, c.nombre as categoria_nombre 
                   FROM peliculas p 
                   LEFT JOIN categorias c ON p.categoria_id = c.id 
                   WHERE p.categoria_id = :cat AND p.id != :id 
                   ORDER BY RAND() 
                   LIMIT 6";
    $similarStmt = db()->prepare($similarSql);
    $similarStmt->execute([':cat' => $pelicula['categoria_id'], ':id' => $id]);
    $similares = $similarStmt->fetchAll();

    $pelicula['similares'] = $similares;
    echo json_encode($pelicula);
}

function apiTopVistas() {
    $limit = (int)($_GET['limit'] ?? 10);
    
    $sql = "SELECT p.*, c.nombre as categoria_nombre 
            FROM peliculas p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            ORDER BY p.vistas DESC 
            LIMIT $limit";
    
    $stmt = db()->query($sql);
    $peliculas = $stmt->fetchAll();

    echo json_encode(['peliculas' => $peliculas]);
}

function apiCategorias() {
    $sql = "SELECT c.id, c.nombre, COUNT(p.id) as total 
            FROM categorias c 
            LEFT JOIN peliculas p ON c.id = p.categoria_id 
            GROUP BY c.id 
            ORDER BY c.nombre";
    
    $stmt = db()->query($sql);
    $categorias = $stmt->fetchAll();

    echo json_encode(['categorias' => $categorias]);
}
