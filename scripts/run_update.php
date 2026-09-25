<?php
/**
 * Ejecutar actualización con progreso en tiempo real
 * Muestra una página con barra de progreso que se actualiza
 */

$type = $_GET['type'] ?? '';
if (!in_array($type, ['update_db', 'update_tmdb'])) {
    http_response_code(400);
    die('Tipo inválido');
}

$id = 'work_' . bin2hex(random_bytes(8));
$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) @mkdir($workDir, 0777, true);
if (!is_dir($workDir) || !is_writable($workDir)) {
    $workDir = '/tmp/mymovies_work/';
    @mkdir($workDir, 0777, true);
}

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

// Abrir pipe para leer output en tiempo real
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$proc = proc_open("php " . escapeshellarg($scriptPath) . " --enriquecer", $descriptors, $pipes, null, null, ['bypass_shell' => true]);

if (!is_resource($proc)) {
    $work['status'] = 'failed';
    $work['error'] = 'No se pudo iniciar';
    file_put_contents($workFile, json_encode($work));
    die('Error: no se pudo iniciar el proceso');
}

// Leer output y guardar en archivo de log
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

// Mostrar página de resultado
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización - VideoTeca</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #0a0a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: #1a1a2e;
            border-radius: 16px;
            padding: 3rem;
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        h1 { color: #00d4ff; margin-bottom: 1.5rem; font-size: 1.5rem; }
        .spinner {
            width: 60px; height: 60px;
            border: 4px solid rgba(0,212,255,0.2);
            border-top-color: #00d4ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 2rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .progress-bar {
            width: 100%; height: 8px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00d4ff, #00ff88);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .progress-text {
            font-size: 2rem;
            color: #00d4ff;
            margin: 0.5rem 0;
        }
        .status-text {
            font-size: 1.1rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 1rem;
        }
        .results {
            background: rgba(0,0,0,0.3);
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            text-align: left;
        }
        .result-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 1rem;
        }
        .result-item:last-child { border: none; }
        .result-item span { color: #00d4ff; font-weight: bold; }
        .btn-back {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.75rem 2rem;
            background: #00d4ff;
            color: #000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-back:hover { background: #00a8cc; }
        .output-log {
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            max-height: 200px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: rgba(255,255,255,0.7);
            text-align: left;
            white-space: pre-wrap;
        }
        .success { color: #00ff88; }
        .error { color: #ff6b6b; }
    </style>
</head>
<body>
    <div class="container">
        <div id="loading-view">
            <div class="spinner"></div>
            <h1>Actualizando base de datos...</h1>
            <div class="progress-text" id="percent">0%</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width:0%"></div>
            </div>
            <div class="status-text" id="status-text">Iniciando...</div>
        </div>
        
        <div id="result-view" style="display:none">
            <h1 id="result-title"></h1>
            <div class="results" id="results"></div>
            <div id="error-output" style="display:none">
                <div class="output-log" id="output-log"></div>
            </div>
            <a href="../admin/settings.php" class="btn-back">Volver a Configuración</a>
        </div>
    </div>

    <script>
        const workId = '<?php echo $id; ?>';
        const status = '<?php echo $status; ?>';
        
        // Si ya terminó, mostrar resultados
        if (status === 'completed' || status === 'failed') {
            showResults(<?php echo json_encode($work); ?>);
        } else {
            // Polling mientras se ejecuta
            let pollInterval = setInterval(poll, 1000);
            function poll() {
                fetch('../scripts/api_work.php?action=status&id=' + workId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.status === 'completed' || data.status === 'failed') {
                            clearInterval(pollInterval);
                            showResults(data);
                        } else {
                            updateProgress(data);
                        }
                    });
            }
        }
        
        function updateProgress(data) {
            const percent = document.getElementById('percent');
            const fill = document.getElementById('progress-fill');
            const statusText = document.getElementById('status-text');
            
            if (percent) percent.textContent = Math.round(data.progress || 0) + '%';
            if (fill) fill.style.width = (data.progress || 0) + '%';
            if (statusText) statusText.textContent = 'Procesando...';
        }
        
        function showResults(data) {
            document.getElementById('loading-view').style.display = 'none';
            document.getElementById('result-view').style.display = 'block';
            
            const title = document.getElementById('result-title');
            const results = document.getElementById('results');
            const errorOutput = document.getElementById('error-output');
            const outputLog = document.getElementById('output-log');
            
            if (data.status === 'completed') {
                title.innerHTML = '<span class="success">✅ Actualización completada</span>';
                let html = '';
                html += '<div class="result-item">Completado: <span>' + (data.finished_at || '-') + '</span></div>';
                if (data.nuevas) html += '<div class="result-item">📁 Nuevas: <span>' + data.nuevas + '</span></div>';
                if (data.existente) html += '<div class="result-item">📁 Existentes: <span>' + data.existente + '</span></div>';
                if (data.enriquecidas) html += '<div class="result-item">🎬 TMDB enriquecidas: <span>' + data.enriquecidas + '</span></div>';
                results.innerHTML = html;
            } else {
                title.innerHTML = '<span class="error">❌ Error en la actualización</span>';
                results.innerHTML = '<div class="result-item">Error: <span>' + (data.error || 'Error desconocido') + '</span></div>';
            }
            
            if (data.output) {
                errorOutput.style.display = 'block';
                outputLog.textContent = data.output;
            }
        }
    </script>
</body>
</html>
