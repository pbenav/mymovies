<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

require_admin();

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$message = '';
$messageType = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if (empty($username) || empty($email) || empty($password)) {
                $message = 'Todos los campos son obligatorios.';
                $messageType = 'error';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = db()->prepare("INSERT INTO usuarios (username, email, password, is_admin, activo, perfil) VALUES (:username, :email, :password, :is_admin, :activo, 'usuario')");
                    $stmt->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $hash,
                        ':is_admin' => $is_admin,
                        ':activo' => $activo
                    ]);
                    $message = 'Usuario creado correctamente.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error al crear el usuario: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
            
        case 'update':
            $update_id = (int)$_POST['id'];
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $is_admin = isset($_POST['is_admin']) ? 1 : 0;
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            if (empty($username) || empty($email)) {
                $message = 'El nombre de usuario y email son obligatorios.';
                $messageType = 'error';
            } else {
                try {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                        $stmt = db()->prepare("UPDATE usuarios SET username = :username, email = :email, password = :password, is_admin = :is_admin, activo = :activo WHERE id = :id");
                        $stmt->execute([
                            ':username' => $username,
                            ':email' => $email,
                            ':password' => $hash,
                            ':is_admin' => $is_admin,
                            ':activo' => $activo,
                            ':id' => $update_id
                        ]);
                    } else {
                        $stmt = db()->prepare("UPDATE usuarios SET username = :username, email = :email, is_admin = :is_admin, activo = :activo WHERE id = :id");
                        $stmt->execute([
                            ':username' => $username,
                            ':email' => $email,
                            ':is_admin' => $is_admin,
                            ':activo' => $activo,
                            ':id' => $update_id
                        ]);
                    }
                    
                    // Guardar reglas de acceso
                    $accessData = [
                        'enabled' => isset($_POST['access_enabled']),
                        'start_time' => $_POST['start_time'] ?? '00:00:00',
                        'end_time' => $_POST['end_time'] ?? '23:59:59',
                        'days' => $_POST['days'] ?? [],
                        'date_start' => $_POST['date_start'] ?? null,
                        'date_end' => $_POST['date_end'] ?? null
                    ];
                    save_access_rules($update_id, $accessData);
                    
                    $message = 'Usuario actualizado correctamente.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error al actualizar el usuario: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
            
        case 'delete':
            $delete_id = (int)$_POST['id'];
            if ($delete_id != $_SESSION['user_id']) {
                try {
                    $stmt = db()->prepare("DELETE FROM usuarios WHERE id = :id");
                    $stmt->execute([':id' => $delete_id]);
                    $message = 'Usuario eliminado correctamente.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error al eliminar el usuario: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'No puedes eliminar tu propia cuenta.';
                $messageType = 'error';
            }
            break;
            
        case 'activate':
            $activate_id = (int)$_POST['id'];
            try {
                $stmt = db()->prepare("UPDATE usuarios SET activo = 1 WHERE id = :id");
                $stmt->execute([':id' => $activate_id]);
                $message = 'Usuario activado correctamente.';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error al activar el usuario: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'deactivate':
            $deactivate_id = (int)$_POST['id'];
            if ($deactivate_id != $_SESSION['user_id']) {
                try {
                    $stmt = db()->prepare("UPDATE usuarios SET activo = 0 WHERE id = :id");
                    $stmt->execute([':id' => $deactivate_id]);
                    $message = 'Usuario desactivado correctamente.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error al desactivar el usuario: ' . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = 'No puedes desactivar tu propia cuenta.';
                $messageType = 'error';
            }
            break;
    }
}

// Obtener todos los usuarios
$users = get_all_users();

// Si hay acción de editar, obtener el usuario
$edit_user = null;
$access_rules = null;
if ($action === 'edit' && $id > 0) {
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            $edit_user = $user;
            break;
        }
    }
    if ($edit_user) {
        $access_rules = get_access_rules($edit_user['id']);
    }
}

$titulo_pagina = 'Administración de Usuarios - VideoTeca';
$extra_css = '<link rel="stylesheet" href="../css/admin.css">';
require_once '../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>Administración de Usuarios</h1>
        <button class="btn-new-user" onclick="openModal()">+ Nuevo Usuario</button>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <table class="users-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Email</th>
                <th>Perfil</th>
                <th>Estado</th>
                <th>Registro</th>
                <th>Último Acceso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <span class="badge <?php echo $user['is_admin'] ? 'badge-admin' : 'badge-usuario'; ?>">
                        <?php echo $user['is_admin'] ? 'Admin' : 'Usuario'; ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?php echo $user['activo'] ? 'badge-active' : 'badge-inactive'; ?>">
                        <?php echo $user['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </td>
                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                <td>
                    <div class="actions">
                        <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn-action btn-edit">Editar</a>
                        <?php if ($user['activo']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn-action btn-deactivate" onclick="return confirm('¿Desactivar este usuario?')">Desactivar</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn-action btn-activate" onclick="return confirm('¿Activar este usuario?')">Activar</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('¿Eliminar este usuario?')">Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal para crear/editar usuario -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <h2 id="modalTitle">Nuevo Usuario</h2>
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id" id="userId" value="">
            
            <div class="form-group">
                <label for="username">Usuario *</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña <?php echo $edit_user ? '(dejar vacío para mantener)' : '*'; ?></label>
                <input type="password" id="password" name="password" <?php echo !$edit_user ? 'required' : ''; ?>>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="is_admin" name="is_admin" <?php echo $edit_user && $edit_user['is_admin'] ? 'checked' : ''; ?>>
                <label for="is_admin">Es Administrador</label>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="activo" name="activo" <?php echo $edit_user && $edit_user['activo'] ? 'checked' : 'checked'; ?>>
                <label for="activo">Cuenta Activada</label>
            </div>
            
            <div class="access-rules-section">
                <h3>Reglas de Acceso</h3>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="access_enabled" name="access_enabled" <?php echo $edit_user && !empty($access_rules) && $access_rules['enabled'] ? 'checked' : ($edit_user && empty($access_rules) ? 'checked' : ''); ?>>
                    <label for="access_enabled">Aplicar restricciones de acceso</label>
                </div>
                
                <div class="access-fields">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_time">Hora Inicio</label>
                            <input type="time" id="start_time" name="start_time" value="<?php echo $edit_user && !empty($access_rules) ? $access_rules['start_time'] : '00:00'; ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_time">Hora Fin</label>
                            <input type="time" id="end_time" name="end_time" value="<?php echo $edit_user && !empty($access_rules) ? $access_rules['end_time'] : '23:59'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Días Permitidos</label>
                        <div class="days-checkboxes">
                            <?php
                            $dias = ['Lun' => 1, 'Mar' => 2, 'Mié' => 3, 'Jue' => 4, 'Vie' => 5, 'Sáb' => 6, 'Dom' => 7];
                            foreach ($dias as $nombre => $valor):
                                $checked = '';
                                if (!empty($access_rules) && $access_rules['days']):
                                    $pos = $valor - 1;
                                    $checked = ($access_rules['days'][$pos] === '1') ? 'checked' : '';
                                else:
                                    $checked = 'checked'; // Todos activados por defecto
                                endif;
                            ?>
                                <label class="day-checkbox">
                                    <input type="checkbox" name="days[]" value="<?php echo $valor; ?>" <?php echo $checked; ?>>
                                    <?php echo $nombre; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_start">Fecha Inicio</label>
                            <input type="date" id="date_start" name="date_start" value="<?php echo $edit_user && !empty($access_rules) && $access_rules['date_start'] ? $access_rules['date_start'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_end">Fecha Fin</label>
                            <input type="date" id="date_end" name="date_end" value="<?php echo $edit_user && !empty($access_rules) && $access_rules['date_end'] ? $access_rules['date_end'] : ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn-submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('userModal').classList.add('active');
    document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
    document.getElementById('formAction').value = 'create';
    document.getElementById('userId').value = '';
    document.getElementById('userForm').reset();
    document.getElementById('password').required = true;
    document.getElementById('activo').checked = true;
}

function editUser(id, username, email, isAdmin, activo, accessRules) {
    document.getElementById('userModal').classList.add('active');
    document.getElementById('modalTitle').textContent = 'Editar Usuario';
    document.getElementById('formAction').value = 'update';
    document.getElementById('userId').value = id;
    document.getElementById('username').value = username;
    document.getElementById('email').value = email;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('is_admin').checked = isAdmin == 1;
    document.getElementById('activo').checked = activo == 1;
    
    // Cargar reglas de acceso
    if (accessRules) {
        document.getElementById('access_enabled').checked = accessRules.enabled == 1;
        document.getElementById('start_time').value = accessRules.start_time ? accessRules.start_time.substring(0, 5) : '00:00';
        document.getElementById('end_time').value = accessRules.end_time ? accessRules.end_time.substring(0, 5) : '23:59';
        document.getElementById('date_start').value = accessRules.date_start || '';
        document.getElementById('date_end').value = accessRules.date_end || '';
        
        // Cargar días
        var days = accessRules.days || '1111111';
        var checkboxes = document.querySelectorAll('input[name="days[]"]');
        checkboxes.forEach(function(cb) {
            var index = parseInt(cb.value) - 1;
            cb.checked = days[index] === '1';
        });
    } else {
        // Por defecto: todos los días, horario completo
        document.getElementById('access_enabled').checked = true;
        document.getElementById('start_time').value = '00:00';
        document.getElementById('end_time').value = '23:59';
        var checkboxes = document.querySelectorAll('input[name="days[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = true;
        });
    }
}

function closeModal() {
    document.getElementById('userModal').classList.remove('active');
}

// Cerrar modal al hacer clic fuera
document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Abrir modal si estamos en modo edición
<?php if ($action === 'edit' && $edit_user): ?>
    editUser(
        <?php echo $edit_user['id']; ?>,
        '<?php echo addslashes($edit_user['username']); ?>',
        '<?php echo addslashes($edit_user['email']); ?>',
        <?php echo $edit_user['is_admin']; ?>,
        <?php echo $edit_user['activo']; ?>,
        <?php echo $access_rules ? json_encode($access_rules) : 'null'; ?>
    );
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
