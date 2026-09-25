<?php
/**
 * API para gestionar trabajos en segundo plano
 * GET ?action=status&id=<id> - Ver estado
 * POST  ?action=create        - Crear nuevo trabajo
 */

header('Content-Type: application/json');

$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) {
    mkdir($workDir, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = $_GET['id'] ?? '';

function workPath($id) {
    return __DIR__ . '/../work/' . basename($id) . '.json';
}

function saveWork($id, $data) {
    file_put_contents(workPath($id), json_encode($data));
}

function getWork($id) {
    $p = workPath($id);
    return file_exists($p) ? json_decode(file_get_contents($p), true) : null;
}

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
        echo json_encode(['error' => 'Tipo de trabajo inválido']);
        exit;
    }
    
    $id = 'work_' . bin2hex(random_bytes(8));
    $work = [
        'id' => $id,
        'type' => $type,
        'status' => 'queued',
        'progress' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    saveWork($id, $work);
    
    // Lanzar proceso en background (desconectado del request)
    $script = __DIR__ . '/run_work.php';
    $cmd = sprintf(
        'nohup php -c %s %s %s > /dev/null 2>&1 &',
        escapeshellarg(ini_get('disable_functions') ? ini_get('disable_functions') : ''),
        escapeshellarg($script),
        escapeshellarg($id)
    );
    // Usar proc_open para verdadero background
    $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
    $proc = proc_open('nohup php ' . escapeshellarg($script) . ' ' . escapeshellarg($id) . ' > /dev/null 2>&1', $desc, $pipes);
    if (is_resource($proc)) {
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }
    
    echo json_encode(['id' => $id, 'status' => 'queued']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción inválida']);
