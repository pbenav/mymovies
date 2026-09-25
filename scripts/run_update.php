<?php
/**
 * Ejecutar actualización de forma síncrona con streaming
 * GET ?action=run&type=update_db
 * 
 * Usa chunked transfer encoding para evitar timeouts del navegador
 */

// Crear work dir
$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) {
    @mkdir($workDir, 0777, true);
}
if (!is_dir($workDir) || !is_writable($workDir)) {
    $workDir = '/tmp/mymovies_work/';
    @mkdir($workDir, 0777, true);
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');  // Nginx
header('Content-Encoding: none');

function sendEvent($data) {
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

function workPath($id) {
    global $workDir;
    return $workDir . basename($id) . '.json';
}

$type = $_GET['type'] ?? '';
if (!in_array($type, ['update_db', 'update_tmdb'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo inválido']);
    exit;
}

$id = 'work_' . bin2hex(random_bytes(8));
$workFile = workPath($id);

// Work inicial
$work = [
    'id' => $id,
    'type' => $type,
    'status' => 'running',
    'progress' => 0,
    'started_at' => date('Y-m-d H:i:s'),
];
file_put_contents($workFile, json_encode($work));

// Enviar inicio
sendEvent(['status' => 'starting', 'id' => $id]);

$scriptPath = __DIR__ . '/actualizar_db.php';

// Ejecutar con proc_open para leer output en tiempo real
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open("php " . escapeshellarg($scriptPath) . " --enriquecer", $descriptors, $pipes, null, null, ['bypass_shell' => true]);

if (!is_resource($proc)) {
    sendEvent(['status' => 'failed', 'error' => 'No se pudo iniciar el proceso']);
    $work['status'] = 'failed';
    file_put_contents($workFile, json_encode($work));
    exit;
}

sendEvent(['status' => 'processing', 'progress' => 10]);

$output = '';
$lineCount = 0;
$lastProgressUpdate = microtime(true);

while (!feof($pipes[1]) && !feof($pipes[2])) {
    $line = fgets($pipes[1]);
    if ($line) {
        $output .= $line;
        $lineCount++;
        
        // Enviar cada N líneas o cada segundo
        $now = microtime(true);
        if ($lineCount % 5 === 0 || ($now - $lastProgressUpdate) > 1) {
            $progress = min(90, 10 + ($lineCount * 2));
            sendEvent([
                'status' => 'processing',
                'progress' => $progress,
                'line' => trim($line),
            ]);
            $lastProgressUpdate = $now;
        }
    }
    
    // También leer stderr
    $errLine = fgets($pipes[2]);
    if ($errLine) {
        $output .= $errLine;
    }
}

$exitCode = proc_close($proc);
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);

// Parsear resultados
$status = $exitCode === 0 ? 'completed' : 'failed';
$progress = 100;

if (preg_match('/Nuevas:\s*(\d+)/', $output, $m)) $nuevas = $m[1];
if (preg_match('/Existente:\s*(\d+)/', $output, $m)) $existente = $m[1];
if (preg_match('/Enriquecidas:\s*(\d+)/', $output, $m)) $enriquecidas = $m[1];
if (preg_match('/Sin resultados:\s*(\d+)/', $output, $m)) $fallidas = $m[1];

// Enviar resultado final
sendEvent([
    'status' => $status,
    'progress' => $progress,
    'finished_at' => date('Y-m-d H:i:s'),
    'nuevas' => $nuevas ?? 0,
    'existente' => $existente ?? 0,
    'enriquecidas' => $enriquecidas ?? 0,
    'fallidas' => $fallidas ?? 0,
    'output' => $output,
]);

// Guardar en disco
$work = [
    'id' => $id,
    'type' => $type,
    'status' => $status,
    'progress' => 100,
    'started_at' => date('Y-m-d H:i:s'),
    'finished_at' => date('Y-m-d H:i:s'),
    'nuevas' => $nuevas ?? 0,
    'existente' => $existente ?? 0,
    'enriquecidas' => $enriquecidas ?? 0,
    'fallidas' => $fallidas ?? 0,
    'output' => $output,
];
file_put_contents($workFile, json_encode($work));

sendEvent(['status' => 'done']);
