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
if ($action === 'edit' && $id > 0) {
    foreach ($users as $user) {
        if ($user['id'] == $id) {
            $edit_user = $user;
            break;
        }
    }
}

$titulo_pagina = 'Administración de Usuarios - VideoTeca';
require_once '../includes/header.php';
?>

<style>
    .admin-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }
    
    .admin-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }
    
    .admin-header h1 {
        margin: 0;
        color: #fff;
    }
    
    .btn-new-user {
        background: #00d4ff;
        color: #000;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    
    .btn-new-user:hover {
        background: #00a8cc;
    }
    
    .message {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }
    
    .message.success {
        background: #00ff8833;
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    
    .message.error {
        background: #ff475733;
        border: 1px solid #ff4757;
        color: #ff4757;
    }
    
    .users-table {
        width: 100%;
        border-collapse: collapse;
        background: #1a1a2e;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .users-table th {
        background: #16213e;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #00d4ff;
    }
    
    .users-table td {
        padding: 1rem;
        border-bottom: 1px solid #1a1a3e;
        color: #e0e0e0;
    }
    
    .users-table tr:hover {
        background: #16213e55;
    }
    
    .badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .badge-admin {
        background: #ff6b6b;
        color: #fff;
    }
    
    .badge-usuario {
        background: #4ecdc4;
        color: #000;
    }
    
    .badge-active {
        background: #00ff88;
        color: #000;
    }
    
    .badge-inactive {
        background: #ff4757;
        color: #fff;
    }
    
    .actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-action {
        padding: 0.4rem 0.8rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-block;
        color: #fff;
    }
    
    .btn-edit {
        background: #00d4ff;
        color: #000;
    }
    
    .btn-delete {
        background: #ff4757;
    }
    
    .btn-activate {
        background: #00ff88;
        color: #000;
    }
    
    .btn-deactivate {
        background: #ffa502;
        color: #000;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.8);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: #1a1a2e;
        padding: 2rem;
        border-radius: 12px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-content h2 {
        color: #00d4ff;
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        color: #e0e0e0;
    }
    
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #333;
        border-radius: 6px;
        background: #16213e;
        color: #fff;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: #00d4ff;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: auto;
    }
    
    .checkbox-group label {
        margin: 0;
    }
    
    .modal-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
        margin-top: 1.5rem;
    }
    
    .btn-submit {
        background: #00d4ff;
        color: #000;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .btn-cancel {
        background: #666;
        color: #fff;
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }
</style>

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

function editUser(id, username, email, isAdmin, activo) {
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
        <?php echo $edit_user['activo']; ?>
    );
<?php endif; ?>
</script>

<?php require_once '../includes/footer.php'; ?>
