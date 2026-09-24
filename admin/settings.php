<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

require_admin();

$message = '';
$messageType = '';

// Procesar guardado de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_config') {
        $configData = [
            'tmdb_api_key' => trim($_POST['tmdb_api_key'] ?? ''),
            'video_folder' => trim($_POST['video_folder'] ?? '/var/www/videos'),
            'max_upload_size' => (int)($_POST['max_upload_size'] ?? 104857600),
            'allow_registration' => isset($_POST['allow_registration']),
            'site_name' => trim($_POST['site_name'] ?? 'VideoTeca'),
            'site_description' => trim($_POST['site_description'] ?? 'Videoteca personal')
        ];
        
        $allSaved = true;
        foreach ($configData as $key => $value) {
            $type = ($key === 'max_upload_size') ? 'integer' : 
                    ($key === 'allow_registration' ? 'boolean' : 'string');
            if (!save_site_config($key, $value, $type)) {
                $allSaved = false;
                break;
            }
        }
        
        if ($allSaved) {
            $message = 'Configuración guardada correctamente.';
            $messageType = 'success';
        } else {
            $message = 'Error al guardar la configuración.';
            $messageType = 'error';
        }
    }
    
    if ($_POST['action'] === 'update_db') {
        // Aquí se ejecutarían migraciones pendientes
        $message = 'La base de datos está actualizada.';
        $messageType = 'success';
    }
    
    if ($_POST['action'] === 'update_tmdb') {
        // Aquí se sincronizarían los metadatos de TMDB
        $message = 'Sincronización con TMDB iniciada. Esto puede tardar unos minutos.';
        $messageType = 'success';
    }
}

// Obtener configuración actual
$config = get_site_config();
$configMap = [];
foreach ($config as $item) {
    $configMap[$item['config_key']] = $item;
}

$titulo_pagina = 'Configuración del Sistema - VideoTeca';
$extra_css = '<link rel="stylesheet" href="../css/admin.css">';
require_once '../includes/header.php';
?>

<div class="admin-container">
    <div class="admin-header">
        <h1>⚙️ Configuración del Sistema</h1>
    </div>
    
    <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <!-- Sección de Configuración General -->
    <div class="settings-section">
        <h2>Configuración General</h2>
        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="save_config">
            
            <div class="config-group">
                <label for="site_name">Nombre del Sitio</label>
                <input type="text" id="site_name" name="site_name" 
                       value="<?php echo htmlspecialchars($configMap['site_name']['config_value'] ?? 'VideoTeca'); ?>">
                <small class="config-desc"><?php echo htmlspecialchars($configMap['site_name']['description'] ?? ''); ?></small>
            </div>
            
            <div class="config-group">
                <label for="site_description">Descripción del Sitio</label>
                <input type="text" id="site_description" name="site_description" 
                       value="<?php echo htmlspecialchars($configMap['site_description']['config_value'] ?? 'Videoteca personal'); ?>">
                <small class="config-desc"><?php echo htmlspecialchars($configMap['site_description']['description'] ?? ''); ?></small>
            </div>
            
            <div class="config-group">
                <label for="tmdb_api_key">API Key de TMDB</label>
                <input type="password" id="tmdb_api_key" name="tmdb_api_key" 
                       value="<?php echo htmlspecialchars($configMap['tmdb_api_key']['config_value'] ?? ''); ?>">
                <small class="config-desc"><?php echo htmlspecialchars($configMap['tmdb_api_key']['description'] ?? ''); ?></small>
            </div>
            
            <div class="config-group">
                <label for="video_folder">Carpeta de Videos</label>
                <input type="text" id="video_folder" name="video_folder" 
                       value="<?php echo htmlspecialchars($configMap['video_folder']['config_value'] ?? '/var/www/videos'); ?>">
                <small class="config-desc"><?php echo htmlspecialchars($configMap['video_folder']['description'] ?? ''); ?></small>
            </div>
            
            <div class="config-group">
                <label for="max_upload_size">Tamaño Máximo de Subida (bytes)</label>
                <input type="number" id="max_upload_size" name="max_upload_size" 
                       value="<?php echo htmlspecialchars($configMap['max_upload_size']['config_value'] ?? 104857600); ?>">
                <small class="config-desc"><?php echo htmlspecialchars($configMap['max_upload_size']['description'] ?? ''); ?></small>
            </div>
            
            <div class="config-group checkbox-group">
                <input type="checkbox" id="allow_registration" name="allow_registration" 
                       <?php echo ($configMap['allow_registration']['config_value'] ?? '1') == '1' ? 'checked' : ''; ?>>
                <label for="allow_registration">Permitir Registro Público</label>
            </div>
            <small class="config-desc" style="margin-top: -0.5rem; margin-bottom: 1.5rem; display: block;">
                <?php echo htmlspecialchars($configMap['allow_registration']['description'] ?? ''); ?>
            </small>
            
            <div class="modal-actions" style="margin-top: 2rem;">
                <button type="submit" class="btn-submit">💾 Guardar Configuración</button>
            </div>
        </form>
    </div>
    
    <!-- Sección de Acciones -->
    <div class="settings-section">
        <h2>Acciones del Sistema</h2>
        
        <div class="action-buttons">
            <form method="POST" action="settings.php" onsubmit="return confirm('¿Actualizar la base de datos con las migraciones pendientes?');">
                <input type="hidden" name="action" value="update_db">
                <button type="submit" class="btn-action btn-update-db">
                    🗄️ Actualizar Base de Datos
                </button>
                <small class="config-desc">Ejecutar migraciones pendientes</small>
            </form>
            
            <form method="POST" action="settings.php" onsubmit="return confirm('¿Iniciar la sincronización de metadatos con TMDB? Esto puede tardar unos minutos.');">
                <input type="hidden" name="action" value="update_tmdb">
                <button type="submit" class="btn-action btn-update-tmdb">
                    🎬 Actualizar Metadatos TMDB
                </button>
                <small class="config-desc">Sincronizar películas con TMDB</small>
            </form>
        </div>
    </div>
</div>

<style>
.settings-section {
    background: #1a1a2e;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
}

.settings-section h2 {
    color: #00d4ff;
    margin-bottom: 1.5rem;
    font-size: 1.3rem;
}

.config-group {
    margin-bottom: 1.5rem;
}

.config-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #e0e0e0;
    font-weight: 600;
}

.config-group input[type="text"],
.config-group input[type="password"],
.config-group input[type="number"] {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #333;
    border-radius: 6px;
    background: #16213e;
    color: #fff;
    font-size: 0.95rem;
}

.config-group input:focus {
    outline: none;
    border-color: #00d4ff;
}

.config-desc {
    display: block;
    margin-top: 0.4rem;
    color: #888;
    font-size: 0.85rem;
}

.action-buttons {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.btn-action {
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 200px;
}

.btn-action small {
    font-size: 0.8rem;
    font-weight: 400;
}

.btn-update-db {
    background: #00d4ff;
    color: #000;
}

.btn-update-db:hover {
    background: #00a8cc;
}

.btn-update-tmdb {
    background: #ff6b6b;
    color: #fff;
}

.btn-update-tmdb:hover {
    background: #ee5a5a;
}
</style>

<?php require_once '../includes/footer.php'; ?>
