#!/usr/bin/env php
<?php
/**
 * Ejecutar trabajos en segundo plano
 * Uso: php scripts/run_work.php <work_id>
 */

$workDir = __DIR__ . '/../work/';
$workId = $argv[1] ?? '';
if (!$workId) {
    echo "Uso: php scripts/run_work.php <work_id>\n";
    exit(1);
}

$workFile = $workDir . basename($workId) . '.json';
if (!file_exists($workFile)) exit(1);

$work = json_decode(file_get_contents($workFile), true);
$work['status'] = 'running';
$work['started_at'] = date('Y-m-d H:i:s');
$work['progress'] = 5;
file_put_contents($workFile, json_encode($work));

try {
    $scriptPath = __DIR__ . '/../scripts/actualizar_db.php';
    $outputFile = $workDir . basename($workId) . '.log';
    
    $cmd = sprintf('php %s --enriquecer 2>%s',
        escapeshellarg($scriptPath),
        escapeshellarg($outputFile)
    );
    
    $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
    $proc = proc_open($cmd, $desc, $pipes);
    
    if (is_resource($proc)) {
        fclose($pipes[0]);
        
        // Leer output en chunks para actualizar progreso
        $output = '';
        $lastProgress = 5;
        while (!feof($pipes[2])) {
            $line = fgets($pipes[2]);
            if ($line) $output .= $line;
            
            // Actualizar progreso cada cierto tiempo
            $now = microtime(true);
            if (isset($lastUpdate) && ($now - $lastUpdate) > 2) {
                $work['progress'] = min(95, $lastProgress + 5);
                file_put_contents($workFile, json_encode($work));
                $lastProgress = $work['progress'];
                $lastUpdate = $now;
            }
        }
        fclose($pipes[2]);
        $returnCode = proc_close($proc);
        
        $work['status'] = $returnCode === 0 ? 'completed' : 'failed';
        $work['finished_at'] = date('Y-m-d H:i:s');
        $work['progress'] = 100;
        $work['output'] = $output;
        $work['return_code'] = $returnCode;
        
        // Extraer resumen
        if (preg_match('/Nuevas:\s*(\d+)/', $output, $m)) $work['nuevas'] = $m[1];
        if (preg_match('/Existente:\s*(\d+)/', $output, $m)) $work['existente'] = $m[1];
        if (preg_match('/Enriquecidas:\s*(\d+)/', $output, $m)) $work['enriquecidas'] = $m[1];
        if (preg_match('/Sin resultados:\s*(\d+)/', $output, $m)) $work['fallidas'] = $m[1];
        
    } else {
        throw new Exception('No se pudo iniciar el proceso');
    }
    
} catch (Exception $e) {
    $work['status'] = 'failed';
    $work['error'] = $e->getMessage();
    $work['finished_at'] = date('Y-m-d H:i:s');
}

file_put_contents($workFile, json_encode($work));
