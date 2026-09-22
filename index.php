<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Si la tabla de usuarios existe, requerir autenticación
// Si no existe (instalación limpia), permitir acceso sin auth
$auth_required = true;
try {
    db()->query("SELECT 1 FROM usuarios LIMIT 1");
} catch (Exception $e) {
    $auth_required = false;
}

if ($auth_required) {
    require_auth();
}

// Intentar cargar db.php, pero no morir si falla
$db_loaded = false;
try {
    require_once 'includes/db.php';
    $db_loaded = true;
} catch (Exception $e) {
    $db_loaded = false;
}

// Obtener categorías
$categorias = [];
if ($db_loaded) {
    try {
        $stmt = db()->query("SELECT id, nombre FROM categorias ORDER BY nombre");
        while ($row = $stmt->fetch()) {
            $categorias[] = $row;
        }
    } catch (Exception $e) {
        // BD no creada aún o error
    }
}

// Buscar si se hizo búsqueda
$buscar = $_GET['buscar'] ?? '';
$peliculas = [];
$categoria_actual = '';

if (!empty($buscar)) {
    try {
        $stmt = db()->prepare("
            SELECT p.*, c.nombre as categoria_nombre 
            FROM peliculas p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.titulo LIKE :termino 
            ORDER BY p.vistas DESC 
            LIMIT 50
        ");
        $stmt->execute([':termino' => "%$buscar%"]);
        $peliculas = $stmt->fetchAll();
        $titulo_pagina = "Resultados para: $buscar";
    } catch (Exception $e) {
        $titulo_pagina = "Buscar: $buscar";
    }
} else {
    $titulo_pagina = 'VideoTeca - Películas';
}

require_once 'includes/header.php';
?>

<?php if (!empty($buscar)): ?>
    <section class="section">
        <h1>Resultados para "<?php echo htmlspecialchars($buscar); ?>"</h1>
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
                                         onerror="this.src='css/placeholder.png'">
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
        <?php else: ?>
            <p class="no-results">No se encontraron resultados para "<?php echo htmlspecialchars($buscar); ?>"</p>
        <?php endif; ?>
    </section>

<?php else: ?>

    <!-- Top vistas - Swiper carousel -->
    <?php if (!empty($categorias)): ?>
    <section class="hero-section">
        <div class="swiper top-vistas-swiper">
            <div class="swiper-wrapper" id="top-vistas-container">
                <!-- Cargado dinámicamente -->
            </div>
            <div class="swiper-pagination"></div>
        </div>
    </section>

    <!-- Categorías -->
    <section class="sections-container">
        <?php foreach ($categorias as $cat): ?>
        <section class="section" id="<?php echo $cat['nombre']; ?>">
            <h2><?php echo htmlspecialchars($cat['nombre']); ?></h2>
            <div class="grid" data-categoria="<?php echo $cat['id']; ?>" data-pagina="1">
                <!-- Cargado dinámicamente -->
            </div>
            <div class="load-more" data-categoria="<?php echo $cat['id']; ?>">
                <button class="btn-load">Cargar más</button>
            </div>
        </section>
        <?php endforeach; ?>
    </section>
    <?php else: ?>
    <section class="section">
        <div class="no-data">
            <h1>🎬 VideoTeca</h1>
            <p>No se encontraron películas en la base de datos.</p>
            <p>Ejecuta <code>php scripts/actualizar_db.php</code> para escanear tus archivos de video.</p>
        </div>
    </section>
    <?php endif; ?>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
