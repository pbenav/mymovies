<?php
// Crear carpeta work en múltiples ubicaciones posibles
$possibleDirs = [
    __DIR__ . '/../work',
    dirname(__DIR__) . '/work',
    '/tmp/mymovies_work',
];

foreach ($possibleDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    if (is_writable($dir)) {
        define('WORK_DIR', $dir);
        break;
    }
}

if (!defined('WORK_DIR')) {
    define('WORK_DIR', '/tmp');
}

header('Content-Type: application/json');

function logMsg($msg) {
    file_put_contents(WORK_DIR . '/api.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

function workPath($id) {
    return WORK_DIR . '/' . basename($id) . '.json';
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$id = $_GET['id'] ?? '';

logMsg("START action=$action id=$id type=" . ($_POST['type'] ?? 'N/A'));

function saveWork($id, $data) {
    file_put_contents(workPath($id), json_encode($data), LOCK_EX);
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
    $scriptPath = dirname(__DIR__) . '/scripts/run_work.php';
    $realScript = realpath($scriptPath);
    
    if (!$realScript) {
        logMsg("ERROR: run_work.php no encontrado en: $scriptPath");
        echo json_encode(['error' => 'Script no encontrado']);
        exit;
    }
    
    logMsg("Lanzando: php $realScript $id");
    
    $launched = false;
    
    // Método 1: proc_open
    if (!function_exists('proc_open')) {
        logMsg("proc_open no disponible");
    } else {
        $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        $cmd = 'nohup php ' . escapeshellarg($realScript) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 &';
        $proc = @proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
            proc_close($proc);
            $launched = true;
            logMsg("Lanzado con proc_open");
        } else {
            logMsg("proc_open falló");
        }
    }
    
    // Método 2: shell_exec
    if (!$launched) {
        $cmd = 'nohup php ' . escapeshellarg($realScript) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 &';
        $pid = shell_exec($cmd . ' echo $!');
        $launched = true;
        logMsg("Lanzado con shell_exec, PID: " . trim($pid ?? ''));
    }
    
    // Método 3: popen
    if (!$launched) {
        $cmd = 'nohup php ' . escapeshellarg($realScript) . ' ' . escapeshellarg($id) . ' >/dev/null 2>&1 &';
        $fp = @popen($cmd, 'r');
        if ($fp) {
            pclose($fp);
            $launched = true;
            logMsg("Lanzado con popen");
        } else {
            logMsg("popen falló");
        }
    }
    
    if (!$launched) {
        logMsg("ERROR: Todos los métodos fallaron");
        echo json_encode(['error' => 'No se pudo iniciar el proceso']);
        exit;
    }
    
    usleep(300000); // 300ms
    
    // Verificar que el trabajo existe
    $workFile = workPath($id);
    if (!file_exists($workFile)) {
        logMsg("ERROR: Work file no creado en 300ms");
        echo json_encode(['error' => 'Work file no creado']);
        exit;
    }
    
    $updatedWork = json_decode(file_get_contents($workFile), true);
    logMsg("Work status: " . ($updatedWork['status'] ?? 'unknown'));
    
    if ($updatedWork && ($updatedWork['status'] === 'queued' || $updatedWork['status'] === 'running')) {
        echo json_encode(['id' => $id, 'status' => 'queued']);
    } else {
        echo json_encode(['error' => 'Error desconocido al iniciar']);
    }
    
    echo json_encode(['id' => $id, 'status' => 'queued']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción inválida']);
