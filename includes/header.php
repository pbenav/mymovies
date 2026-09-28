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

// URL raíz del sitio (ruta absoluta desde dominio)
$root = '/';
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal-dark {
            background: #1a1a2e !important;
            color: #fff !important;
        }
        .swal-dark .swal2-popup {
            background: #1a1a2e !important;
            border: 1px solid #333 !important;
        }
        .swal-dark .swal2-title {
            color: #fff !important;
        }
        .swal-dark .swal2-html-container {
            color: #e0e0e0 !important;
        }
        .swal-dark .swal2-footer {
            border-color: #333 !important;
            color: #e0e0e0 !important;
        }
        .swal-dark .swal2-close {
            color: #fff !important;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="nav-container">
                <a href="<?= $root ?>index.php" class="logo">🎬 VideoTeca</a>
                <div class="nav-links">
                    <a href="<?= $root ?>index.php">Inicio</a>
                    <div class="dropdown">
                        <span class="dropdown-toggle">Categorías ▾</span>
                        <div class="dropdown-content">
                            <a href="<?= $root ?>index.php#todas">Todas</a>
                            <?php
                            try {
                                $stmt = db()->query("SELECT id, nombre FROM categorias ORDER BY nombre");
                                while ($cat = $stmt->fetch()) {
                                    echo '<a href="' . $root . 'genre.php?categoria=' . urlencode($cat['id']) . '">' . htmlspecialchars($cat['nombre']) . '</a>';
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
                            <a href="<?= $root ?>admin/users.php">👥 Usuarios</a>
                            <a href="<?= $root ?>admin/settings.php">⚙️ Configuración</a>
                            <?php endif; ?>
                            <a href="<?= $root ?>logout.php">🚪 Cerrar sesión</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="<?= $root ?>login.php">Iniciar sesión</a>
                    <a href="<?= $root ?>register.php" class="btn-register">Registrarse</a>
                    <?php endif; ?>
                </div>
                <form class="search-form" method="GET" action="<?= $root ?>index.php">
                    <input type="text" name="buscar" placeholder="Buscar películas..." value="<?= isset($_GET['buscar']) ? htmlspecialchars($_GET['buscar']) : '' ?>">
                    <button type="submit">🔍</button>
                </form>
            </div>
        </nav>
    </header>
    <main>
<?php
// Mostrar mensajes flash
if (isset($_SESSION['flash_message'])):
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $_SESSION['flash_type'] === 'error' ? 'error' : ($_SESSION['flash_type'] === 'success' ? 'success' : 'info'); ?>',
        title: '<?php echo addslashes($_SESSION['flash_title'] ?? ''); ?>',
        text: '<?php echo addslashes($_SESSION['flash_message']); ?>',
        confirmButtonText: 'Aceptar',
        background: '#1a1a2e',
        color: '#fff',
        confirmButtonColor: '#00d4ff',
        customClass: {
            popup: 'swal-dark'
        }
    });
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type'], $_SESSION['flash_title']); ?>
});
</script>
<?php endif; ?>
</main>
