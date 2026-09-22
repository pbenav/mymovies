<?php
require_once 'includes/db.php';

$categoria = $_GET['categoria'] ?? '';

if (empty($categoria)) {
    header('Location: index.php');
    exit;
}

// Determinar si es ID o slug
$isNumeric = is_numeric($categoria);
$pagina = max(1, (int)($_GET['p'] ?? 1));
$limit = PER_PAGE;
$offset = ($pagina - 1) * $limit;

$categoria_info = null;
$peliculas = [];

try {
    if (!defined('DB_HOST')) {
        require_once 'includes/db.php';
    }
    
    if ($isNumeric) {
        // Buscar por ID
        $stmt = db()->prepare("SELECT id, nombre, slug FROM categorias WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $categoria]);
        $categoria_info = $stmt->fetch();
        
        $stmt = db()->prepare("
            SELECT p.*, c.nombre as categoria_nombre 
            FROM peliculas p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.categoria_id = :cat 
            ORDER BY p.vistas DESC 
            LIMIT $limit OFFSET $offset
        ");
        $stmt->execute([':cat' => $categoria]);
        $peliculas = $stmt->fetchAll();
        
        $stmt = db()->prepare("SELECT COUNT(*) FROM peliculas WHERE categoria_id = :cat");
        $stmt->execute([':cat' => $categoria]);
        $total = $stmt->fetchColumn();
    } else {
        // Buscar por slug
        $stmt = db()->prepare("SELECT id, nombre, slug FROM categorias WHERE slug = :slug LIMIT 1");
        $stmt->execute([':slug' => $categoria]);
        $categoria_info = $stmt->fetch();
        
        if ($categoria_info) {
            $catId = $categoria_info['id'];
            
            $stmt = db()->prepare("
                SELECT p.*, c.nombre as categoria_nombre 
                FROM peliculas p 
                LEFT JOIN categorias c ON p.categoria_id = c.id 
                WHERE p.categoria_id = :cat 
                ORDER BY p.vistas DESC 
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute([':cat' => $catId]);
            $peliculas = $stmt->fetchAll();
            
            $stmt = db()->prepare("SELECT COUNT(*) FROM peliculas WHERE categoria_id = :cat");
            $stmt->execute([':cat' => $catId]);
            $total = $stmt->fetchColumn();
        }
    }
} catch (Exception $e) {
    // BD no creada aún
}

$pagination = $categoria_info ? getPagination($total ?? 0, $limit, $pagina) : null;
$titulo_pagina = ($categoria_info ? $categoria_info['nombre'] : 'Categoría') . ' - VideoTeca';
$categoria_actual = $categoria_info['nombre'] ?? '';

require_once 'includes/header.php';
?>

<section class="section">
    <h1><?php echo htmlspecialchars($categoria_info['nombre'] ?? $categoria); ?></h1>
    
    <?php if (!empty($peliculas)): ?>
        <div class="grid">
            <?php foreach ($peliculas as $peli): ?>
                <div class="card">
                    <a href="pel.php?id=<?php echo $peli['id']; ?>">
                        <div class="card-poster">
                            <?php if (!empty($peli['poster'])): ?>
                                <img src="<?php echo htmlspecialchars($peli['poster']); ?>" 
                                     alt="<?php echo htmlspecialchars($peli['titulo']); ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                                <div class="card-poster-placeholder" style="display:none">🎬</div>
                            <?php else: ?>
                                <div class="card-poster-placeholder">🎬</div>
                            <?php endif; ?>
                            <span class="card-rating"><?php echo $peli['rating'] ?? 'N/A'; ?></span>
                        </div>
                    </a>
                    <div class="card-info">
                        <a href="pel.php?id=<?php echo $peli['id']; ?>">
                            <h3><?php echo htmlspecialchars($peli['titulo']); ?></h3>
                        </a>
                        <span class="card-meta">
                            <?php echo $peli['año'] ?? ''; ?>
                            <?php if ($peli['vistas'] > 0): ?>
                                · <?php echo $peli['vistas']; ?> vistas
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginación -->
        <?php if ($pagination && $pagination['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($pagination['has_prev']): ?>
                <a href="?categoria=<?php echo urlencode($categoria); ?>&p=<?php echo $pagina - 1; ?>" class="page-btn">← Anterior</a>
            <?php endif; ?>
            
            <span class="page-info">Página <?php echo $pagination['current']; ?> de <?php echo $pagination['total_pages']; ?></span>
            
            <?php if ($pagination['has_next']): ?>
                <a href="?categoria=<?php echo urlencode($categoria); ?>&p=<?php echo $pagina + 1; ?>" class="page-btn">Siguiente →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <p class="no-results">No hay películas en esta categoría.</p>
    <?php endif; ?>
</section>

<?php require_once 'includes/footer.php'; ?>
