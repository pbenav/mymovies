<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Si ya está logueado, redirigir
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (empty($username) || empty($email) || empty($password) || empty($password2)) {
        $error = 'Completa todos los campos.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $result = register($username, $email, $password);
        
        if (isset($result['success'])) {
            $success = '¡Registro exitoso! Ya puedes iniciar sesión.';
            $_SERVER['REQUEST_METHOD'] = 'GET';
        } else {
            $error = $result['error'];
        }
    }
}

$titulo_pagina = 'Registrarse - VideoTeca';
require_once 'includes/header.php';
?>

<section class="section">
    <div class="auth-container">
        <h1>🎬 Crear Cuenta</h1>
        
        <?php if ($error): ?>
            <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="auth-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username" placeholder="Mínimo 3 caracteres, solo letras, números y guion bajo" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <small>Solo letras, números y guion bajo</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" placeholder="tu@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" placeholder="Mínimo 6 caracteres">
            </div>
            
            <div class="form-group">
                <label for="password2">Confirmar Contraseña</label>
                <input type="password" id="password2" name="password2" required autocomplete="new-password" placeholder="Repite la contraseña">
            </div>
            
            <button type="submit" class="btn-auth btn-auth-primary">Registrarse</button>
        </form>
        
        <p class="auth-link">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </p>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
