#!/usr/bin/env php
<?php
/**
 * Procesador de trabajos colgados en archivos
 * Se ejecuta via cron cada 30 segundos
 * Uso: php scripts/work_processor.php
 */

$workDir = __DIR__ . '/../work/';
if (!is_dir($workDir)) {
    mkdir($workDir, 0777, true);
}

$scriptPath = __DIR__ . '/actualizar_db.php';

// Buscar archivos .queue en work/
$files = glob($workDir . '*.queue');

foreach ($files as $queueFile) {
    $workId = basename($queueFile, '.queue');
    $workFile = $workDir . $workId . '.json';
    $logFile = $workDir . $workId . '.log';
    
    // Leer configuración del trabajo
    $config = json_decode(file_get_contents($queueFile), true);
    if (!$config) continue;
    
    // Crear work file si no existe
    if (!file_exists($workFile)) {
        $work = [
            'id' => $workId,
            'type' => $config['type'] ?? 'update_db',
            'status' => 'running',
            'progress' => 5,
            'started_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($workFile, json_encode($work));
    } else {
        // Ya está en ejecución, saltar
        continue;
    }
    
    // Ejecutar el proceso
    $output = '';
    $returnCode = 1;
    
    if ($config['type'] === 'update_db') {
        $cmd = sprintf('php %s --enriquecer 2>%s',
            escapeshellarg($scriptPath),
            escapeshellarg($logFile)
        );
    } else {
        $cmd = sprintf('php %s --enriquecer 2>%s',
            escapeshellarg($scriptPath),
            escapeshellarg($logFile)
        );
    }
    
    exec($cmd, $dummy, $returnCode);
    
    // Leer output
    $output = file_exists($logFile) ? file_get_contents($logFile) : '';
    
    // Actualizar work file
    $work = json_decode(file_get_contents($workFile), true);
    $work['status'] = $returnCode === 0 ? 'completed' : 'failed';
    $work['finished_at'] = date('Y-m-d H:i:s');
    $work['progress'] = 100;
    $work['return_code'] = $returnCode;
    $work['output'] = $output;
    
    // Extraer resumen
    if (preg_match('/Nuevas:\s*(\d+)/', $output, $m)) $work['nuevas'] = $m[1];
    if (preg_match('/Existente:\s*(\d+)/', $output, $m)) $work['existente'] = $m[1];
    if (preg_match('/Enriquecidas:\s*(\d+)/', $output, $m)) $work['enriquecidas'] = $m[1];
    if (preg_match('/Sin resultados:\s*(\d+)/', $output, $m)) $work['fallidas'] = $m[1];
    
    file_put_contents($workFile, json_encode($work));
    
    // Eliminar archivo de cola
    unlink($queueFile);
}
