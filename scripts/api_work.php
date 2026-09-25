<?php
/**
 * API para gestionar trabajos en segundo plano
 * GET ?action=status&id=<id> - Ver estado
 * POST  ?action=create        - Crear nuevo trabajo
 */

header('Content-Type: application/json');

// Logging propio para debugging
$logFile = __DIR__ . '/../work/api.log';
function logMsg($msg) {
    file_put_contents(__DIR__ . '/../work/api.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
}

$logDir = __DIR__ . '/../work/';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) {
    mkdir($workDir, 0755, true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = $_GET['id'] ?? '';

logMsg("ACTION=$action ID=$id TYPE=" . ($_POST['type'] ?? 'N/A'));

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
    
    // Lanzar proceso en background
    $script = __DIR__ . '/run_work.php';
    $fullScript = realpath($script);
    
    if (!$fullScript) {
        logMsg("ERROR: Script no encontrado: $script");
        echo json_encode(['error' => 'Script no encontrado: ' . $script]);
        exit;
    }
    
    logMsg("Lanzando: php $fullScript $id");
    
    // Intentar con proc_open primero
    $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
    $proc = @proc_open('nohup php ' . escapeshellarg($fullScript) . ' ' . escapeshellarg($id) . ' > /dev/null 2>&1 &', $desc, $pipes);
    
    if (!is_resource($proc)) {
        logMsg("proc_open falló, intentando shell_exec");
        // Fallback: usar shell_exec con &
        $cmd = 'nohup php ' . escapeshellarg($fullScript) . ' ' . escapeshellarg($id) . ' > /dev/null 2>&1 &';
        $result = shell_exec($cmd);
        logMsg("shell_exec result: " . var_export($result, true));
    } else {
        logMsg("proc_open exitoso");
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }
    
    // Pequeña pausa para asegurar que el proceso arrancó
    usleep(200000); // 200ms
    
    // Verificar que el work file tiene status 'queued'
    $workFile = workPath($id);
    if (!file_exists($workFile)) {
        logMsg("ERROR: Work file no creado: $workFile");
        echo json_encode(['error' => 'Work file no creado']);
        exit;
    }
    
    $updatedWork = json_decode(file_get_contents($workFile), true);
    logMsg("Work status after launch: " . ($updatedWork['status'] ?? 'unknown'));
    
    if (!isset($updatedWork['status']) || $updatedWork['status'] === 'queued') {
        echo json_encode(['id' => $id, 'status' => 'queued']);
    } else {
        echo json_encode(['error' => 'Error al iniciar el proceso. Status: ' . ($updatedWork['status'] ?? 'unknown')]);
    }
    
    echo json_encode(['id' => $id, 'status' => 'queued']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción inválida']);
