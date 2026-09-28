<?php
/**
 * Endpoint AJAX para ejecutar actualizaciones
 * GET ?action=run&type=update_db
 * 
 * Ejecuta el proceso y devuelve el resultado directamente
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Prevenir timeout del navegador y PHP
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '512M');

// Crear work dir
$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) @mkdir($workDir, 0777, true);
if (!is_dir($workDir) || !is_writable($workDir)) {
    $workDir = '/tmp/mymovies_work/';
    @mkdir($workDir, 0777, true);
}

$type = $_GET['type'] ?? '';
if (!in_array($type, ['update_db', 'update_tmdb'])) {
    echo json_encode(['error' => 'Tipo inválido']);
    exit;
}

$id = 'work_' . bin2hex(random_bytes(8));
$workFile = $workDir . $id . '.json';
$logFile = $workDir . $id . '.log';

$work = [
    'id' => $id,
    'type' => $type,
    'status' => 'running',
    'progress' => 0,
    'started_at' => date('Y-m-d H:i:s'),
];
file_put_contents($workFile, json_encode($work));

$scriptPath = __DIR__ . '/actualizar_db.php';

if (!is_file($scriptPath)) {
    $work['status'] = 'failed';
    $work['error'] = 'Script no encontrado';
    file_put_contents($workFile, json_encode($work));
    echo json_encode(['error' => 'Script no encontrado']);
    exit;
}

// Ejecutar con proc_open para leer output en tiempo real
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open("php " . escapeshellarg($scriptPath) . " --enriquecer", $descriptors, $pipes, null, null, ['bypass_shell' => true]);

if (!is_resource($proc)) {
    $work['status'] = 'failed';
    $work['error'] = 'No se pudo iniciar el proceso';
    file_put_contents($workFile, json_encode($work));
    echo json_encode(['error' => 'No se pudo iniciar el proceso']);
    exit;
}

// Leer output
$output = '';
$lineCount = 0;
while ((!feof($pipes[1]) || !feof($pipes[2])) && is_resource($proc)) {
    $lines = [];
    stream_select($lines, $read = [], $write = [], 1);
    
    if (!feof($pipes[1]) && ($line = fgets($pipes[1]))) {
        $output .= $line;
        $lineCount++;
    }
    if (!feof($pipes[2]) && ($errLine = fgets($pipes[2]))) {
        $output .= $errLine;
    }
    
    // Actualizar work file cada 10 líneas
    if ($lineCount % 10 === 0) {
        $progress = min(90, 10 + ($lineCount * 2));
        $work['progress'] = $progress;
        file_put_contents($workFile, json_encode($work));
    }
}

$exitCode = proc_close($proc);
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);

// Parsear resultados
$status = $exitCode === 0 ? 'completed' : 'failed';
$nuevas = $existente = $enriquecidas = $fallidas = 0;

if (preg_match('/Nuevas:\s*(\d+)/', $output, $m)) $nuevas = $m[1];
if (preg_match('/Existente:\s*(\d+)/', $output, $m)) $existente = $m[1];
if (preg_match('/Enriquecidas:\s*(\d+)/', $output, $m)) $enriquecidas = $m[1];
if (preg_match('/Sin resultados:\s*(\d+)/', $output, $m)) $fallidas = $m[1];

$work = [
    'id' => $id,
    'type' => $type,
    'status' => $status,
    'progress' => 100,
    'started_at' => date('Y-m-d H:i:s'),
    'finished_at' => date('Y-m-d H:i:s'),
    'nuevas' => $nuevas,
    'existente' => $existente,
    'enriquecidas' => $enriquecidas,
    'fallidas' => $fallidas,
    'output' => $output,
];
file_put_contents($workFile, json_encode($work));

// Devolver resultado
if ($status === 'completed') {
    echo json_encode([
        'success' => true,
        'status' => $status,
        'nuevas' => $nuevas,
        'existente' => $existente,
        'enriquecidas' => $enriquecidas,
        'fallidas' => $fallidas,
        'message' => 'Actualización completada: ' . $nuevas . ' nuevas, ' . $existente . ' existentes, ' . $enriquecidas . ' enriquecidas TMDB',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error al ejecutar (código: ' . $exitCode . ')',
        'output' => substr($output, -2000), // Últimas 2000 chars
    ]);
}
