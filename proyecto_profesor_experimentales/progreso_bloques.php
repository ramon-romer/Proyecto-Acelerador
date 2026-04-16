<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/funciones_dashboard.php';
$error = null; $evaluacion = null; $jsonEntrada = []; $bloques = []; $resumen = []; $faltantes = []; $simulaciones = []; $detalles = [];
try {
    $conexion = obtenerConexion();
    $idProfesor = obtenerIdProfesor();
    $evaluacion = obtenerEvaluacionPorId($conexion, $idProfesor);
    if ($evaluacion === null) throw new RuntimeException('No se ha encontrado la evaluación solicitada.');
    $jsonEntrada = decodificarJsonEntrada($evaluacion['json_entrada'] ?? '');
    $bloques = construirBloques($evaluacion, $jsonEntrada);
    $resumen = construirResumenGlobal($evaluacion, $bloques);
    $faltantes = construirFaltantes($bloques, $jsonEntrada);
    $simulaciones = obtenerSimulaciones($jsonEntrada);
    $detalles = obtenerTodosLosDetalles($jsonEntrada);
} catch (Throwable $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Progreso por bloques</title><link rel="stylesheet" href="css/dashboard_profesor.css"></head><body>
<?php if ($error): ?><div class="contenedor"><div class="tarjeta" style="margin-top:24px; padding:20px;"><strong>Error:</strong> <?= e($error) ?></div></div>
<?php else: ?>
<header class="cabecera"><div class="contenedor cabecera-grid"><div><h1 class="titulo"><?= e((string)$evaluacion['nombre_candidato']) ?></h1><p class="subtitulo">Progreso por bloques · <?= e((string)$evaluacion['categoria']) ?></p></div><div class="estado-global"><span class="chip"><?= e((string)$resumen['resultado']) ?></span></div></div></header>
<main class="contenedor"><section class="seccion tarjeta panel-pestanas"><?php include __DIR__ . '/includes/botonera_dashboard.php'; ?><h2 class="panel-titulo">Progreso por bloques</h2>
<table class="tabla"><thead><tr><th>Bloque</th><th>Puntuación</th><th>Objetivo</th><th>Progreso</th><th>Estado</th></tr></thead><tbody>
<?php foreach ($bloques as $bloque): ?><tr><td><?= e($bloque['nombre']) ?></td><td><?= number_format((float)$bloque['valor'], 2) ?></td><td><?= number_format((float)$bloque['objetivo'], 2) ?></td><td style="min-width:240px;"><div class="barra-externa"><span class="<?= e($bloque['clase_estado'] === 'estado-verde' ? 'barra-verde' : ($bloque['clase_estado'] === 'estado-ambar' ? 'barra-ambar' : 'barra-roja')) ?>" style="width: <?= number_format((float)$bloque['porcentaje'], 2, '.', '') ?>%;"></span></div><div class="texto-menor"><?= number_format((float)$bloque['porcentaje'], 0) ?>%</div></td><td class="<?= e($bloque['clase_estado']) ?>"><?= e($bloque['estado']) ?></td></tr><?php endforeach; ?>
</tbody></table></section></main><?php endif; ?></body></html>
