<?php

// Script personalizado para ejecutar PHPUnit sin mostrar deprecaciones

// Capturar la salida de PHPUnit
ob_start();
$exitCode = 0;

// Ejecutar PHPUnit y capturar la salida
$command = 'php vendor/bin/phpunit ' . implode(' ', array_slice($argv, 1));
$output = [];
exec($command . ' 2>&1', $output, $exitCode);

// Filtrar las líneas que contienen información sobre deprecaciones
$filteredOutput = [];
$skipDeprecationCount = false;

foreach ($output as $line) {
    // Saltar líneas que mencionan deprecaciones en el resumen
    if (strpos($line, 'PHPUnit Deprecations:') !== false) {
        $skipDeprecationCount = true;
        // Modificar la línea para mostrar solo las pruebas exitosas
        $line = preg_replace('/,\s*PHPUnit Deprecations:\s*\d+/', '', $line);
    }
    
    // No mostrar líneas vacías después de filtrar deprecaciones
    if (trim($line) === '' && $skipDeprecationCount) {
        continue;
    }
    
    $filteredOutput[] = $line;
    $skipDeprecationCount = false;
}

// Mostrar la salida filtrada
foreach ($filteredOutput as $line) {
    echo $line . PHP_EOL;
}

// Salir con el mismo código que PHPUnit
exit($exitCode);