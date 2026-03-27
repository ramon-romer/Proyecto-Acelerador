<?php
declare(strict_types=1);

namespace GenerarDocumentacion\SessionContextService;

use RuntimeException;

/**
 * @return array{
 *   technical_updates: array<string, array<int, string>>,
 *   daily_updates: array<string, array<int, string>>
 * }
 */
function buildAutomaticUpdates(string $repositoryRoot): array
{
    try {
        $statusLines = readGitStatusLines($repositoryRoot);
    } catch (RuntimeException $exception) {
        return [
            'technical_updates' => [
                '1' => ['No fue posible detectar cambios de sesión con git status en esta ejecución.'],
                '2' => ['Área de repositorio no disponible para inspección automática.'],
                '3' => ['No se registraron cambios de archivos por fallo en la lectura de contexto.'],
                '6' => ['Pendiente validar acceso al repositorio para recuperar contexto real.'],
                '7' => ['Reintentar la ejecución de la skill cuando git status esté disponible.'],
            ],
            'daily_updates' => [
                '1' => ['No se pudo recopilar contexto automático del repositorio en esta ejecución.'],
                '2' => ['No se detectó trabajo de archivos por error al ejecutar git status.'],
                '4' => ['Error detectado al leer estado del repositorio para documentación automática.'],
                '5' => ['Se conserva la ejecución sin inventar cambios no verificables.'],
                '6' => ['Pendiente recuperar acceso de lectura al estado del repositorio.'],
                '7' => ['Repetir generación de documentación tras restablecer contexto de sesión.'],
            ],
        ];
    }

    $entries = parseStatusEntries($statusLines);

    if (count($entries) === 0) {
        return [
            'technical_updates' => [
                '1' => ['No se detectaron cambios de archivos en git status durante esta ejecución.'],
                '2' => ['Sin áreas de código modificadas detectables en esta ejecución.'],
                '3' => ['Se ejecutó la generación automática de documentación sin cambios de archivos pendientes.'],
                '6' => ['No hay riesgos abiertos derivados de cambios no confirmados en el repositorio.'],
                '7' => ['Continuar con el flujo de trabajo habitual y registrar nuevos cambios cuando existan.'],
            ],
            'daily_updates' => [
                '1' => ['Ejecución de documentación diaria sin cambios de archivos detectados en el repositorio.'],
                '2' => ['Se confirmó estado limpio de cambios de sesión mediante git status.'],
                '3' => ['Se mantiene el uso de detección automática de contexto para evitar carga manual.'],
                '6' => ['Sin pendientes de archivos modificados o nuevos en esta ejecución.'],
                '7' => ['Registrar próximos cambios reales y volver a ejecutar la skill al cierre de jornada.'],
            ],
        ];
    }

    $moduleSet = [];
    $changeItems = [];
    $workItems = [];
    $pendingCount = count($entries);

    foreach ($entries as $entry) {
        $module = topLevelModule($entry['path']);
        if ($module !== '') {
            $moduleSet[$module] = true;
        }

        $changeItems[] = sprintf('[%s] %s', $entry['status'], $entry['path']);
        $workItems[] = sprintf('Cambio detectado (%s): %s', $entry['status'], $entry['path']);
    }

    $modules = array_keys($moduleSet);
    sort($modules);

    $risks = [];
    foreach ($entries as $entry) {
        if (str_contains($entry['status'], 'D')) {
            $risks[] = 'Se detectaron eliminaciones de archivos que requieren revisión de impacto.';
            break;
        }
    }
    foreach ($entries as $entry) {
        if ($entry['status'] === '??') {
            $risks[] = 'Existen archivos nuevos sin seguimiento que conviene clasificar o versionar.';
            break;
        }
    }
    if (count($risks) === 0) {
        $risks[] = 'No se detectaron señales de riesgo inmediato en los cambios pendientes registrados.';
    }

    return [
        'technical_updates' => [
            '1' => [sprintf('Se detectaron %d cambios de sesión en git status para esta ejecución de documentación.', $pendingCount)],
            '2' => [sprintf('Áreas afectadas detectadas: %s.', implode(', ', $modules))],
            '3' => array_slice($changeItems, 0, 12),
            '4' => [sprintf('La integración técnica actual involucra %d áreas con cambios pendientes en la sesión.', count($modules))],
            '6' => $risks,
            '7' => [sprintf('Revisar y consolidar %d cambios detectados antes del siguiente cierre técnico.', $pendingCount)],
        ],
        'daily_updates' => [
            '1' => [sprintf('Se registró actividad real de sesión con %d cambios detectados en el repositorio.', $pendingCount)],
            '2' => array_slice($workItems, 0, 12),
            '3' => ['Se mantiene el flujo automático de detección de contexto para evitar carga manual de secciones.'],
            '4' => ['No se reportaron incidencias de ejecución en la detección automática del estado de sesión.'],
            '5' => ['La documentación diaria se alimentó con evidencia real derivada de git status de la sesión.'],
            '6' => [sprintf('Quedan %d cambios detectados para revisión/confirmación según flujo del equipo.', $pendingCount)],
            '7' => ['Completar revisión final de cambios y mantener ejecución diaria de la skill al cierre.'],
        ],
    ];
}

/**
 * @return array<int, string>
 */
function readGitStatusLines(string $repositoryRoot): array
{
    $result = runCommand('git status --short', $repositoryRoot);
    if ($result['exit_code'] !== 0) {
        throw new RuntimeException('No fue posible leer git status para construir contexto automático.');
    }

    $lines = preg_split('/\R/u', rtrim($result['stdout'])) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $normalized = rtrim($line);
        if (trim($normalized) !== '') {
            $clean[] = $normalized;
        }
    }

    return $clean;
}

/**
 * @param array<int, string> $lines
 * @return array<int, array{status: string, path: string}>
 */
function parseStatusEntries(array $lines): array
{
    $entries = [];
    foreach ($lines as $line) {
        if (strlen($line) < 4) {
            continue;
        }

        $status = trim(substr($line, 0, 2));
        $path = trim(substr($line, 3));
        if ($path === '') {
            continue;
        }

        $arrowPos = strpos($path, '->');
        if ($arrowPos !== false) {
            $path = trim(substr($path, $arrowPos + 2));
        }

        $entries[] = [
            'status' => $status === '' ? '--' : $status,
            'path' => $path,
        ];
    }

    return $entries;
}

function topLevelModule(string $path): string
{
    $normalized = str_replace('\\', '/', trim($path));
    if ($normalized === '') {
        return '';
    }

    $parts = explode('/', $normalized);
    return $parts[0] ?? '';
}

/**
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runCommand(string $command, string $workingDirectory): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        throw new RuntimeException(sprintf('No se pudo iniciar el comando: %s', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exit_code' => (int) $exitCode,
        'stdout' => trim($stdout === false ? '' : $stdout),
        'stderr' => trim($stderr === false ? '' : $stderr),
    ];
}
