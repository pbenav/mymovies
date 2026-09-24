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
        $cookieParams = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 86400,
            'path' => $cookieParams['path'] ?? '/',
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
    
    // Verificar que la cuenta esté activada
    if (!is_account_activated($_SESSION['user_id'])) {
        session_destroy();
        header("Location: login.php?error=inactive");
        exit;
    }
    
    // Verificar reglas de acceso (horario, días, fechas)
    $accessResult = check_access_rules($_SESSION['user_id']);
    if ($accessResult !== true) {
        session_destroy();
        header("Location: login.php?error=" . urlencode($accessResult));
        exit;
    }
}

function is_account_activated($userId) {
    try {
        $stmt = db()->prepare("SELECT activo FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        return $user && $user['activo'] == 1;
    } catch (Exception $e) {
        return true; // Si no se puede verificar, permitir acceso
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_logged_user() {
    if (!isset($_SESSION['user_id'])) return null;
    
    try {
        $stmt = db()->prepare("SELECT id, username, email, is_admin, activo, perfil, created_at, last_login FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function login($username, $password) {
    try {
        $stmt = db()->prepare("SELECT id, username, password, is_admin, activo FROM usuarios WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        if ($user['activo'] != 1) {
            return 'inactive';
        }
        
        // Verificar reglas de acceso
        $accessResult = check_access_rules($user['id']);
        if ($accessResult !== true) {
            return 'access_denied';
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (bool)$user['is_admin'];
        
        // Actualizar last_login
        $update = db()->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);
        
        return true;
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
        
        // Insertar usuario como inactivo (pendiente de aprobación)
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = db()->prepare("INSERT INTO usuarios (username, email, password, activo, perfil) VALUES (:username, :email, :password, 0, 'usuario')");
        $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hash
        ]);
        
        return ['success' => true, 'pending' => true];
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        return ['error' => 'Error al registrar. Inténtalo de nuevo.'];
    }
}

function activate_user($userId) {
    try {
        $stmt = db()->prepare("UPDATE usuarios SET activo = 1 WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Activate user error: " . $e->getMessage());
        return false;
    }
}

function deactivate_user($userId) {
    try {
        $stmt = db()->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Deactivate user error: " . $e->getMessage());
        return false;
    }
}

function update_user($userId, $username, $email, $password, $is_admin, $activo) {
    try {
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = db()->prepare("UPDATE usuarios SET username = :username, email = :email, password = :password, is_admin = :is_admin, activo = :activo WHERE id = :id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hash,
                ':is_admin' => $is_admin ? 1 : 0,
                ':activo' => $activo ? 1 : 0,
                ':id' => $userId
            ]);
        } else {
            $stmt = db()->prepare("UPDATE usuarios SET username = :username, email = :email, is_admin = :is_admin, activo = :activo WHERE id = :id");
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':is_admin' => $is_admin ? 1 : 0,
                ':activo' => $activo ? 1 : 0,
                ':id' => $userId
            ]);
        }
        return true;
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        return false;
    }
}

function delete_user($userId) {
    try {
        $stmt = db()->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        return false;
    }
}

function get_all_users() {
    try {
        $stmt = db()->query("SELECT id, username, email, is_admin, activo, perfil, created_at, last_login FROM usuarios ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get users error: " . $e->getMessage());
        return [];
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

// ==================== ACCESS RULES ====================

function get_access_rules($userId) {
    try {
        $stmt = db()->prepare("SELECT * FROM access_rules WHERE user_id = :user_id ORDER BY created_at DESC");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get access rules error: " . $e->getMessage());
        return [];
    }
}

function get_access_rule($userId, $ruleId) {
    try {
        $stmt = db()->prepare("SELECT * FROM access_rules WHERE user_id = :user_id AND id = :id LIMIT 1");
        $stmt->execute([':user_id' => $userId, ':id' => $ruleId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get access rule error: " . $e->getMessage());
        return null;
    }
}

function create_access_rule($userId, $data) {
    try {
        $enabled = isset($data['enabled']) ? 1 : 0;
        $startTime = $data['start_time'] ?? '00:00:00';
        $endTime = $data['end_time'] ?? '23:59:59';
        $days = isset($data['days']) ? implode('', $data['days']) : '1111111';
        $dateStart = $data['date_start'] ?? null;
        $dateEnd = $data['date_end'] ?? null;
        
        $stmt = db()->prepare("
            INSERT INTO access_rules (user_id, enabled, start_time, end_time, days, date_start, date_end)
            VALUES (:user_id, :enabled, :start_time, :end_time, :days, :date_start, :date_end)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':enabled' => $enabled,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':days' => $days,
            ':date_start' => $dateStart,
            ':date_end' => $dateEnd
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Create access rule error: " . $e->getMessage());
        return false;
    }
}

function update_access_rule($userId, $ruleId, $data) {
    try {
        $enabled = isset($data['enabled']) ? 1 : 0;
        $startTime = $data['start_time'] ?? '00:00:00';
        $endTime = $data['end_time'] ?? '23:59:59';
        $days = isset($data['days']) ? implode('', $data['days']) : '1111111';
        $dateStart = $data['date_start'] ?? null;
        $dateEnd = $data['date_end'] ?? null;
        
        $stmt = db()->prepare("
            UPDATE access_rules 
            SET enabled = :enabled, start_time = :start_time, end_time = :end_time, 
                days = :days, date_start = :date_start, date_end = :date_end
            WHERE user_id = :user_id AND id = :id
        ");
        $stmt->execute([
            ':enabled' => $enabled,
            ':start_time' => $startTime,
            ':end_time' => $endTime,
            ':days' => $days,
            ':date_start' => $dateStart,
            ':date_end' => $dateEnd,
            ':user_id' => $userId,
            ':id' => $ruleId
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Update access rule error: " . $e->getMessage());
        return false;
    }
}

function delete_access_rule($userId, $ruleId) {
    try {
        $stmt = db()->prepare("DELETE FROM access_rules WHERE user_id = :user_id AND id = :id");
        $stmt->execute([':user_id' => $userId, ':id' => $ruleId]);
        return true;
    } catch (Exception $e) {
        error_log("Delete access rule error: " . $e->getMessage());
        return false;
    }
}

function check_access_rules($userId) {
    try {
        $rules = get_access_rules($userId);
        
        // Si no hay reglas, permitir acceso
        if (empty($rules)) {
            return true;
        }
        
        $now = new DateTime();
        $currentHour = $now->format('H:i:s');
        $currentDay = (int)$now->format('N'); // 1=Lunes, 7=Domingo
        $currentDate = $now->format('Y-m-d');
        
        // Verificar cada regla activa
        $anyActiveRule = false;
        
        foreach ($rules as $rule) {
            // Si la regla está desactivada, saltarla
            if (!$rule['enabled']) {
                continue;
            }
            
            $anyActiveRule = true;
            
            // Verificar días de la semana
            $dayIndex = $currentDay - 1; // Convertir a índice 0-6
            $dayString = $rule['days'];
            if (strlen($dayString) === 7 && $dayString[$dayIndex] === '0') {
                continue; // Este día no está permitido para esta regla
            }
            
            // Verificar fechas de inicio/fin
            if ($rule['date_start'] && $currentDate < $rule['date_start']) {
                continue; // Aún no comienza
            }
            
            if ($rule['date_end'] && $currentDate > $rule['date_end']) {
                continue; // Ya expiró
            }
            
            // Verificar horario
            if ($rule['start_time'] === '00:00:00' && $rule['end_time'] === '23:59:59') {
                // Horario completo - permitido
                return true;
            }
            
            // Verificar si el horario cruza medianoche (ej: 22:00 - 03:00)
            $crossesMidnight = ($rule['start_time'] > $rule['end_time']);
            
            if ($crossesMidnight) {
                // Horario que cruza medianoche: permitido si está en [start, 23:59] O [00:00, end]
                if ($currentHour >= $rule['start_time'] || $currentHour <= $rule['end_time']) {
                    return true;
                }
            } else {
                // Horario normal: permitido si está en [start, end]
                if ($currentHour >= $rule['start_time'] && $currentHour <= $rule['end_time']) {
                    return true;
                }
            }
        }
        
        // Si hay reglas activas pero ninguna permite el acceso ahora
        if ($anyActiveRule) {
            return 'outside_hours';
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Check access rules error: " . $e->getMessage());
        return true; // Si hay error, permitir acceso
    }
}

function get_access_error_message($errorCode) {
    $messages = [
        'inactive' => 'Tu cuenta está pendiente de aprobación por un administrador. Serás notificado cuando sea activada.',
        'outside_hours' => 'No tienes permiso para acceder en este horario. Consulta con el administrador.',
        'day_not_allowed' => 'No tienes permiso para acceder en este día. Consulta con el administrador.',
        'not_started' => 'Tu cuenta aún no está activa. Serás notificado cuando puedas acceder.',
        'expired' => 'Tu período de acceso ha finalizado. Consulta con el administrador.',
    ];
    
    return $messages[$errorCode] ?? 'Acceso denegado. Contacta con el administrador.';
}

// ==================== SITE CONFIG ====================

function get_site_config($key = null) {
    try {
        if ($key) {
            $stmt = db()->prepare("SELECT config_value, config_type FROM site_config WHERE config_key = :key LIMIT 1");
            $stmt->execute([':key' => $key]);
            $row = $stmt->fetch();
            if ($row) {
                return [
                    'value' => convert_config_type($row['config_value'], $row['config_type']),
                    'type' => $row['config_type']
                ];
            }
            return null;
        } else {
            $stmt = db()->query("SELECT config_key, config_value, config_type, description FROM site_config ORDER BY config_key");
            return $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Get site config error: " . $e->getMessage());
        return $key ? null : [];
    }
}

function save_site_config($key, $value, $type = 'string') {
    try {
        // Convertir a string para guardar
        $stringValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;
        
        $existing = db()->prepare("SELECT id FROM site_config WHERE config_key = :key LIMIT 1");
        $existing->execute([':key' => $key]);
        
        if (!$existing->fetch()) {
            $stmt = db()->prepare("
                INSERT INTO site_config (config_key, config_value, config_type)
                VALUES (:key, :value, :type)
            ");
            $stmt->execute([
                ':key' => $key,
                ':value' => $stringValue,
                ':type' => $type
            ]);
        } else {
            $stmt = db()->prepare("
                UPDATE site_config 
                SET config_value = :value, config_type = :type
                WHERE config_key = :key
            ");
            $stmt->execute([
                ':value' => $stringValue,
                ':type' => $type,
                ':key' => $key
            ]);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Save site config error: " . $e->getMessage());
        return false;
    }
}

function convert_config_type($value, $type) {
    if ($value === null || $value === '') {
        return $type === 'boolean' ? false : '';
    }
    
    switch ($type) {
        case 'integer':
            return (int)$value;
        case 'boolean':
            return (bool)$value && $value !== '0' && $value !== '';
        case 'float':
            return (float)$value;
        default:
            return $value;
    }
}
