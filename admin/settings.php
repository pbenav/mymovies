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
    
    if ($_POST['action'] === 'update_db' || $_POST['action'] === 'update_tmdb') {
        // Ejecutar directamente (el proceso se maneja en el frontend con SweetAlert2)
        $type = $_POST['action'] === 'update_db' ? 'update_db' : 'update_tmdb';
        
        // Guardar el tipo para mostrar en el modal
        $_SESSION['pending_update'] = $type;
        
        // Mostrar mensaje de confirmación con SweetAlert2
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'info',
                title: 'Actualización iniciada',
                text: 'El proceso se está ejecutando en segundo plano. No cierres esta página.',
                confirmButtonText: 'Entendido',
                background: '#1a1a2e',
                color: '#fff',
                confirmButtonColor: '#00d4ff'
            });
        });
        </script>
        <?php
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
            <button type="button" class="btn-action btn-update-db" onclick="runUpdate('update_db')">
                🗄️ Actualizar Base de Datos
            </button>
            <small class="config-desc">Ejecutar migraciones pendientes</small>
            
            <button type="button" class="btn-action btn-update-tmdb" onclick="runUpdate('update_tmdb')">
                🎬 Actualizar Metadatos TMDB
            </button>
            <small class="config-desc">Sincronizar películas con TMDB</small>
        </div>
    </div>
    
    <!-- Panel de estado del trabajo en segundo plano -->
    <div id="work-status-panel" style="display:none; background:rgba(0,0,0,0.85); border:2px solid #00d4ff; border-radius:12px; padding:20px; margin:20px 0; animation:slideDown 0.3s ease;">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center;">
                <div class="work-spinner"></div>
                <strong id="work-status-text" style="font-size:1.1rem; color:#00d4ff;">Procesando...</strong>
            </div>
            <button id="btn-cancel-work" class="btn-cancel-work" type="button">Cancelar</button>
        </div>
        <div class="work-progress-bar">
            <div id="work-progress-fill" class="work-progress-fill" style="width:0%"></div>
        </div>
        <div style="text-align:right; font-size:14px; color:rgba(255,255,255,0.7);">
            <span id="work-progress-percent">0%</span>
        </div>
        <div id="work-details" class="work-details"></div>
        <div id="work-results" style="margin-top:15px;"></div>
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

/* Panel de estado del trabajo */
#work-status-panel {
    background: rgba(0,0,0,0.85);
    border: 2px solid #00d4ff;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.work-spinner {
    display: inline-block;
    width: 24px;
    height: 24px;
    border: 3px solid rgba(0,212,255,0.3);
    border-top-color: #00d4ff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 10px;
    vertical-align: middle;
}

.work-progress-bar {
    width: 100%;
    height: 8px;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    overflow: hidden;
    margin: 10px 0;
}

.work-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #00d4ff, #00ff88);
    border-radius: 4px;
    transition: width 0.5s ease;
}

.work-details {
    font-size: 14px;
    color: rgba(255,255,255,0.7);
    margin-top: 10px;
}

.work-details span {
    color: #00d4ff;
    font-weight: bold;
}

.btn-cancel-work {
    background: rgba(255,107,107,0.2);
    border: 1px solid #ff6b6b;
    color: #ff6b6b;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    margin-top: 10px;
    transition: all 0.2s;
}

.btn-cancel-work:hover {
    background: rgba(255,107,107,0.4);
}

.work-complete {
    border-color: #00ff88;
}

.work-complete .work-spinner {
    border-color: rgba(0,255,136,0.3);
    border-top-color: #00ff88;
}

.work-failed {
    border-color: #ff6b6b;
}

.work-failed .work-spinner {
    border-color: rgba(255,107,107,0.3);
    border-top-color: #ff6b6b;
}

.work-output {
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    padding: 12px;
    margin-top: 10px;
    max-height: 200px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: rgba(255,255,255,0.8);
    white-space: pre-wrap;
}
</style>

<script>
function runUpdate(type) {
    const typeName = type === 'update_db' ? 'Base de Datos' : 'Metadatos TMDB';
    const icon = type === 'update_db' ? '🗄️' : '🎬';
    
    // Mostrar modal de carga con SweetAlert2
    Swal.fire({
        title: icon + ' Actualizando ' + typeName + '...',
        html: '<div style="text-align:center;">' +
              '<div style="width:100%;height:8px;background:rgba(255,255,255,0.1);border-radius:4px;overflow:hidden;margin:20px 0;">' +
              '<div id="swal-progress" style="height:100%;width:0%;background:linear-gradient(90deg,#00d4ff,#00ff88);border-radius:4px;transition:width 0.5s;"></div>' +
              '</div>' +
              '<div id="swal-status" style="color:rgba(255,255,255,0.7);font-size:14px;">Iniciando...</div>' +
              '</div>',
        showConfirmButton: false,
        showCloseButton: true,
        allowOutsideClick: false,
        background: '#1a1a2e',
        color: '#fff',
        customClass: {
            popup: 'swal-dark'
        }
    });
    
    // Actualizar progreso cada segundo
    let progressInterval = setInterval(function() {
        const bar = document.getElementById('swal-progress');
        const status = document.getElementById('swal-status');
        if (bar) {
            const current = parseFloat(bar.style.width) || 0;
            if (current < 90) {
                bar.style.width = (current + 5) + '%';
            }
        }
        if (status) status.textContent = 'Procesando...';
    }, 1000);
    
    // Llamar al endpoint AJAX
    fetch('/scripts/api_run.php?action=run&type=' + encodeURIComponent(type))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            clearInterval(progressInterval);
            
            const bar = document.getElementById('swal-progress');
            const status = document.getElementById('swal-status');
            if (bar) bar.style.width = '100%';
            if (status) status.textContent = 'Completado!';
            
            setTimeout(function() {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '✅ Actualización completada',
                        html: '<div style="text-align:left;">' +
                              '<p>Completado: <strong>' + (data.finished_at || 'ahora') + '</strong></p>' +
                              (data.nuevas ? '<p>📁 Nuevas: <strong>' + data.nuevas + '</strong></p>' : '') +
                              (data.existente ? '<p>📁 Existentes: <strong>' + data.existente + '</strong></p>' : '') +
                              (data.enriquecidas ? '<p>🎬 TMDB enriquecidas: <strong>' + data.enriquecidas + '</strong></p>' : '') +
                              '</div>',
                        background: '#1a1a2e',
                        color: '#fff',
                        confirmButtonColor: '#00d4ff',
                        customClass: {
                            popup: 'swal-dark'
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en la actualización',
                        html: '<p>' + (data.error || 'Error desconocido') + '</p>' +
                              (data.output ? '<pre style="background:rgba(0,0,0,0.5);padding:10px;border-radius:6px;margin-top:10px;max-height:200px;overflow:auto;font-size:12px;">' + escapeHtml(data.output) + '</pre>' : ''),
                        background: '#1a1a2e',
                        color: '#fff',
                        confirmButtonColor: '#ff6b6b',
                        customClass: {
                            popup: 'swal-dark'
                        }
                    });
                }
            }, 500);
        })
        .catch(function(err) {
            clearInterval(progressInterval);
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor',
                background: '#1a1a2e',
                color: '#fff',
                confirmButtonColor: '#ff6b6b',
                customClass: {
                    popup: 'swal-dark'
                }
            });
        });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
