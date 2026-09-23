<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Si ya está logueado, redirigir
if (is_logged_in()) {
    $redirect = $_GET['redirect'] ?? 'index.php';
    header("Location: $redirect");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Completa todos los campos.';
    } else {
        // Forzar session_start por si acaso
        if (!session_id()) {
            session_start();
        }
        
        $result = login($username, $password);
        
        if ($result) {
            // Verificar que la sesión se guardó
            if (!isset($_SESSION['user_id'])) {
                $error = 'Error: la sesión no se pudo guardar. Verifica permisos de sessions/';
            } else {
                $redirect = $_POST['redirect'] ?? 'index.php';
                header("Location: $redirect");
                exit;
            }
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

$titulo_pagina = 'Iniciar Sesión - VideoTeca';
require_once 'includes/header.php';
?>

<section class="section">
    <div class="auth-container">
        <h1>🎬 Iniciar Sesión</h1>
        
        <?php if ($error): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="auth-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <?php if (isset($_GET['redirect'])): ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username" placeholder="Tu nombre de usuario">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Tu contraseña">
            </div>
            
            <button type="submit" class="btn-auth btn-auth-primary">Entrar</button>
        </form>
        
        <p class="auth-link">
            ¿No tienes cuenta? <a href="register.php">Regístrate aquí</a>
        </p>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
