<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Si la tabla de usuarios existe, requerir autenticación
$auth_required = true;
try {
    db()->query("SELECT 1 FROM usuarios LIMIT 1");
} catch (Exception $e) {
    $auth_required = false;
}

if ($auth_required) {
    require_auth();
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$pelicula = null;
$similares = [];

try {
    // Obtener película
    $stmt = db()->prepare("
        SELECT p.*, c.nombre as categoria_nombre, c.slug as categoria_slug
        FROM peliculas p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $pelicula = $stmt->fetch();

    if ($pelicula) {
        // Incrementar vistas
        $update = db()->prepare("UPDATE peliculas SET vistas = vistas + 1 WHERE id = :id");
        $update->execute([':id' => $id]);

        // Obtener similares
        $stmt_sim = db()->prepare("
            SELECT p.*, c.nombre as categoria_nombre
            FROM peliculas p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.categoria_id = :cat AND p.id != :id
            ORDER BY RAND()
            LIMIT 6
        ");
        $stmt_sim->execute([':cat' => $pelicula['categoria_id'], ':id' => $id]);
        $similares = $stmt_sim->fetchAll();
    }
} catch (Exception $e) {
    // BD no creada aún
}

if (!$pelicula) {
    header('Location: index.php');
    exit;
}

$titulo_pagina = $pelicula['titulo'] . ' - VideoTeca';
$categoria_actual = $pelicula['categoria_nombre'] ?? '';

require_once 'includes/header.php';
?>

<section class="detail-section">
    <div class="detail-container">
        <!-- Columna izquierda: reproductor + info -->
        <div class="detail-main">
            <!-- Reproductor de video embebido -->
            <div class="video-player-wrapper">
                <?php
                $videoPath = VIDEO_PATH;
                $archivo = $pelicula['archivo'];
                $archivoCompleto = $videoPath . '/' . $archivo;
                $urlVideo = 'video/' . rawurlencode($archivo);
                $extension = strtolower($pelicula['extension'] ?? '');
                
                // Determinar tipo MIME
                $mimeTypes = [
                    'mp4' => 'video/mp4',
                    'webm' => 'video/webm',
                    'ogg' => 'video/ogg',
                    'mov' => 'video/quicktime',
                    'mkv' => 'video/x-matroska',
                    'avi' => 'video/x-msvideo',
                    'wmv' => 'video/x-ms-wmv',
                    'm4v' => 'video/x-m4v',
                ];
                $mimeType = $mimeTypes[$extension] ?? 'video/mp4';
                ?>
                
                <video
                    id="video-player"
                    class="video-js vjs-big-play-centered vjs-theme-fantasy"
                    controls
                    preload="auto"
                    width="100%"
                    data-setup='{}'
                >
                    <source src="<?php echo htmlspecialchars($urlVideo); ?>" type="<?php echo $mimeType; ?>">
                    <?php if ($extension === 'mkv'): ?>
                    <p>Tu navegador no soporta video embebido. 
                       <a href="<?php echo htmlspecialchars($urlVideo); ?>" target="_blank">Descargar o reproducir en nuevo tab</a>
                    </p>
                    <?php else: ?>
                    <p>Tu navegador no soporta la etiqueta de video. 
                       <a href="<?php echo htmlspecialchars($urlVideo); ?>" target="_blank">Reproducir aquí</a>
                    </p>
                    <?php endif; ?>
                </video>
            </div>

            <!-- Información de la película -->
            <div class="detail-info">
                <h1><?php echo htmlspecialchars($pelicula['titulo']); ?></h1>
                
                <div class="detail-meta">
                    <?php if ($pelicula['año']): ?>
                        <span>📅 <?php echo htmlspecialchars($pelicula['año']); ?></span>
                    <?php endif; ?>
                    <?php if ($pelicula['categoria_nombre']): ?>
                        <span>📂 <a href="genre.php?categoria=<?php echo urlencode($pelicula['categoria_slug'] ?? $pelicula['categoria_id']); ?>"><?php echo htmlspecialchars($pelicula['categoria_nombre']); ?></a></span>
                    <?php endif; ?>
                    <span>👁️ <?php echo number_format($pelicula['vistas']); ?> vistas</span>
                    <?php if (!empty($pelicula['rating'])): ?>
                        <span>⭐ <?php echo number_format($pelicula['rating'], 1); ?>/10</span>
                    <?php endif; ?>
                </div>

                <?php if ($pelicula['director']): ?>
                    <p class="detail-director">
                        <strong>Dirección:</strong> <?php echo htmlspecialchars($pelicula['director']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($pelicula['elenco']): ?>
                    <p class="detail-cast">
                        <strong>Reparto:</strong> <?php echo htmlspecialchars($pelicula['elenco']); ?>
                    </p>
                <?php endif; ?>

                <?php if ($pelicula['sinopsis']): ?>
                    <div class="detail-sinopsis">
                        <h3>Sinopsis</h3>
                        <p><?php echo nl2br(htmlspecialchars($pelicula['sinopsis'])); ?></p>
                    </div>
                <?php endif; ?>

                <div class="detail-actions">
                    <a href="<?php echo htmlspecialchars($urlVideo); ?>" 
                       download
                       class="btn-play">
                        ⬇️ Descargar
                    </a>
                    <a href="<?php echo htmlspecialchars($urlVideo); ?>" 
                       target="_blank"
                       class="btn-watch">
                        ▶️ Reproducir
                    </a>
                </div>
            </div>
        </div>

        <!-- Columna derecha: póster -->
        <div class="detail-sidebar">
            <?php if (!empty($pelicula['poster'])): ?>
                <img src="<?php echo htmlspecialchars($pelicula['poster']); ?>" 
                     alt="<?php echo htmlspecialchars($pelicula['titulo']); ?>"
                     onerror="this.style.display='none'">
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if (!empty($similares)): ?>
<section class="section">
    <h2>Películas Similares</h2>
    <div class="grid">
        <?php foreach ($similares as $peli): ?>
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
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
