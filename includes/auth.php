<?php
/**
 * Sistema de autenticación de usuarios
 */

// Configurar directorio de sesiones antes de session_start()
$sessionDir = __DIR__ . '/../sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}
if (is_dir($sessionDir)) {
    ini_set('session.save_path', $sessionDir);
}

// Deshabilitar warnings de session_start para evitar problemas con Redis
error_reporting(error_reporting() & ~E_WARNING);
$sessionStarted = session_start();
error_reporting(error_reporting() | E_WARNING);

if (!$sessionStarted) {
    // Fallback: usar cookies si las sesiones no funcionan
    if (!isset($_COOKIE['SESSION_ID'])) {
        session_create_id();
        setcookie(session_name(), session_id(), [
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header("Location: login.php?redirect=$redirect");
        exit;
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_logged_user() {
    if (!isset($_SESSION['user_id'])) return null;
    
    try {
        $stmt = db()->prepare("SELECT id, username, email, is_admin, created_at, last_login FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function login($username, $password) {
    try {
        $stmt = db()->prepare("SELECT id, username, password, is_admin FROM usuarios WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            
            // Actualizar last_login
            $update = db()->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = :id");
            $update->execute([':id' => $user['id']]);
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function register($username, $email, $password) {
    // Validaciones
    $username = trim($username);
    $email = trim($email);
    
    if (strlen($username) < 3 || strlen($username) > 50) {
        return ['error' => 'El nombre de usuario debe tener entre 3 y 50 caracteres.'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['error' => 'El email no es válido.'];
    }
    
    if (strlen($password) < 6) {
        return ['error' => 'La contraseña debe tener al menos 6 caracteres.'];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['error' => 'El nombre de usuario solo puede contener letras, números y guion bajo.'];
    }
    
    try {
        // Verificar si ya existe
        $check = db()->prepare("SELECT id FROM usuarios WHERE username = :username OR email = :email LIMIT 1");
        $check->execute([':username' => $username, ':email' => $email]);
        
        if ($check->fetch()) {
            return ['error' => 'El nombre de usuario o email ya está registrado.'];
        }
        
        // Insertar usuario
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = db()->prepare("INSERT INTO usuarios (username, email, password) VALUES (:username, :email, :password)");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hash
        ]);
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        return ['error' => 'Error al registrar. Inténtalo de nuevo.'];
    }
}

function logout() {
    session_destroy();
    session_start();
}

function require_admin() {
    require_auth();
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        header('Location: index.php');
        exit;
    }
}
