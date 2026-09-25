<?php
/**
 * API para crear trabajos en cola
 * GET  ?action=status&id=<id> - Ver estado
 * POST ?action=create         - Crear trabajo
 */

// Crear work dir en /tmp si es necesario
$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) {
    @mkdir($workDir, 0777, true);
}
if (!is_dir($workDir) || !is_writable($workDir)) {
    $workDir = '/tmp/mymovies_work/';
    @mkdir($workDir, 0777, true);
}

header('Content-Type: application/json');

function workPath($id) {
    global $workDir;
    return $workDir . basename($id) . '.json';
}

function getWork($id) {
    $p = workPath($id);
    return file_exists($p) ? json_decode(file_get_contents($p), true) : null;
}

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

if ($action === 'status' && $id) {
    $work = getWork($id);
    if (!$work) {
        http_response_code(404);
        echo json_encode(['error' => 'Trabajo no encontrado']);
    } else {
        echo json_encode($work);
    }
    exit;
}

if ($action === 'create') {
    $type = $_POST['type'] ?? '';
    if (!in_array($type, ['update_db', 'update_tmdb'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido']);
        exit;
    }
    
    $id = 'work_' . bin2hex(random_bytes(8));
    
    // Crear archivo de cola (trigger para el cron)
    $queueFile = $workDir . $id . '.queue';
    $queueData = json_encode(['type' => $type, 'created' => date('Y-m-d H:i:s')]);
    
    if (file_put_contents($queueFile, $queueData)) {
        // Crear work file inicial inmediatamente
        $work = [
            'id' => $id,
            'type' => $type,
            'status' => 'queued',
            'progress' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents(workPath($id), json_encode($work));
        
        echo json_encode(['id' => $id, 'status' => 'queued']);
    } else {
        echo json_encode(['error' => 'No se pudo crear el archivo de cola']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción inválida']);
