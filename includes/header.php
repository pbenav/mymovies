<?php
$titulo_pagina = $titulo_pagina ?? 'VideoTeca - Películas';
$categoria_actual = $categoria_actual ?? '';
require_once __DIR__ . '/auth.php';
$user = is_logged_in() ? get_logged_user() : null;

// Detectar si estamos en una subcarpeta para ajustar rutas relativas
$base = '';
if (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $base = '../';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="<?= $base ?>css/style.css">
    <?php if (isset($extra_css)): ?>
        <?= $extra_css ?>
    <?php endif; ?>
</head>
<body>
    <header>
        <nav>
            <div class="nav-container">
                <a href="index.php" class="logo">🎬 VideoTeca</a>
                <div class="nav-links">
                    <a href="index.php">Inicio</a>
                    <div class="dropdown">
                        <span class="dropdown-toggle">Categorías ▾</span>
                        <div class="dropdown-content">
                            <a href="index.php#todas">Todas</a>
                            <?php
                            try {
                                $stmt = db()->query("SELECT id, nombre FROM categorias ORDER BY nombre");
                                while ($cat = $stmt->fetch()) {
                                    echo '<a href="genre.php?categoria=' . urlencode($cat['id']) . '">' . htmlspecialchars($cat['nombre']) . '</a>';
                                }
                            } catch (Exception $e) {
                                // BD no creada aún
                            }
                            ?>
                        </div>
                    </div>
                    <?php if ($user): ?>
                    <div class="dropdown">
                        <span class="dropdown-toggle"><?php echo htmlspecialchars($user['username']); ?> ▾</span>
                        <div class="dropdown-content">
                            <?php if ($user['is_admin']): ?>
                            <a href="admin/users.php">👥 Usuarios</a>
                            <a href="admin.php">⚙️ Admin</a>
                            <?php endif; ?>
                            <a href="logout.php">🚪 Cerrar sesión</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="login.php">Iniciar sesión</a>
                    <a href="register.php" class="btn-register">Registrarse</a>
                    <?php endif; ?>
                </div>
                <form class="search-form" method="GET" action="index.php">
                    <input type="text" name="buscar" placeholder="Buscar películas..." value="<?= isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : '' ?>">
                    <button type="submit">🔍</button>
                </form>
            </div>
        </nav>
    </header>
    <main>
