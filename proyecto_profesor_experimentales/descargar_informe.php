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

<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Informe imprimible</title><link rel="stylesheet" href="css/dashboard_profesor.css"><style>@media print {.barra-botones-horizontal { display:none; } body { background:#fff; } .tarjeta { box-shadow:none; }}</style></head><body>
<?php if ($error): ?><div class="contenedor"><div class="tarjeta" style="margin-top:24px; padding:20px;"><strong>Error:</strong> <?= e($error) ?></div></div>
<?php else: ?>
<header class="cabecera"><div class="contenedor cabecera-grid"><div><h1 class="titulo">Informe del profesor</h1><p class="subtitulo"><?= e((string)$evaluacion['nombre_candidato']) ?> · <?= e((string)$evaluacion['categoria']) ?></p></div><div class="estado-global"><span class="chip"><?= e((string)$resumen['resultado']) ?></span></div></div></header>
<main class="contenedor"><section class="seccion tarjeta panel-pestanas"><?php include __DIR__ . '/includes/botonera_dashboard.php'; ?><div class="detalle-grid">
<article class="mini-tarjeta"><h4>Resumen global</h4><ul><li>Cumplimiento global: <?= number_format((float)$resumen['cumplimiento_global'], 0) ?>%</li><li>Total final: <?= number_format((float)$resumen['total_final'], 2) ?></li><li>Estado: <?= e((string)$resumen['estado_global']) ?></li></ul></article>
<article class="mini-tarjeta"><h4>Bloques</h4><ul><?php foreach ($bloques as $bloque): ?><li><?= e($bloque['nombre']) ?>: <?= number_format((float)$bloque['porcentaje'], 0) ?>%</li><?php endforeach; ?></ul></article>
<article class="mini-tarjeta"><h4>Observaciones</h4><ul><?php foreach ($faltantes as $faltante): ?><li><?= e($faltante) ?></li><?php endforeach; ?></ul></article>
</div><div style="margin-top:20px;"><a href="#" onclick="window.print(); return false;" class="boton-horizontal activo">Imprimir / Guardar en PDF</a></div></section></main><?php endif; ?></body></html>
