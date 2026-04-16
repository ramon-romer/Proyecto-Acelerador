<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/funciones_dashboard.php';
$error = null;
$evaluacion = null;
$jsonEntrada = [];
$bloques = [];
$resumen = [];
$faltantes = [];
$simulaciones = [];
$detalles = [];
try {
    $conexion = obtenerConexion();
    $idProfesor = obtenerIdProfesor();
    $evaluacion = obtenerEvaluacionPorId($conexion, $idProfesor);
    if ($evaluacion === null)
        throw new RuntimeException('No se ha encontrado la evaluación solicitada.');
    $jsonEntrada = decodificarJsonEntrada($evaluacion['json_entrada'] ?? '');
    $bloques = construirBloques($evaluacion, $jsonEntrada);
    $resumen = construirResumenGlobal($evaluacion, $bloques);
    $faltantes = construirFaltantes($bloques, $jsonEntrada);
    $simulaciones = obtenerSimulaciones($jsonEntrada);
    $detalles = obtenerTodosLosDetalles($jsonEntrada);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recomendaciones</title>
    <link rel="stylesheet" href="css/dashboard_profesor.css">
</head>

<body>
    <?php if ($error): ?>
        <div class="contenedor">
            <div class="tarjeta" style="margin-top:24px; padding:20px;"><strong>Error:</strong> <?= e($error) ?></div>
        </div>
    <?php else: ?>
        <header class="cabecera">
            <div class="contenedor cabecera-grid">
                <div>
                    <h1 class="titulo"><?= e((string) $evaluacion['nombre_candidato']) ?></h1>
                    <p class="subtitulo">Recomendaciones y líneas de mejora</p>
                </div>
                <div class="estado-global"><span class="chip"><?= e((string) $resumen['estado_global']) ?></span></div>
            </div>
        </header>
        <main class="contenedor">
            <section class="seccion tarjeta panel-pestanas"><?php include __DIR__ . '/includes/botonera_dashboard.php'; ?>
                <div class="detalle-grid">
                    <article class="mini-tarjeta">
                        <h4>Recomendaciones detectadas</h4>
                        <ul><?php foreach (obtenerRecomendaciones($jsonEntrada) as $recomendacion): ?>
                                <li><?= e($recomendacion) ?></li>
                            <?php endforeach; ?>     <?php if (count(obtenerRecomendaciones($jsonEntrada)) === 0): ?>
                                <li>No hay recomendaciones explícitas en el JSON.</li><?php endif; ?>
                        </ul>
                    </article>
                    <article class="mini-tarjeta">
                        <h4>Qué te falta</h4>
                        <ul><?php foreach ($faltantes as $faltante): ?>
                                <li><?= e($faltante) ?></li><?php endforeach; ?>
                        </ul>
                    </article>
                    <article class="mini-tarjeta">
                        <h4>Prioridades automáticas</h4>
                        <ul><?php $ordenados = $bloques;
                        usort($ordenados, fn($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);
                        foreach (array_slice($ordenados, 0, 3) as $bloque): ?>
                                <li>Prioridad: <?= e($bloque['nombre']) ?></li><?php endforeach; ?>
                        </ul>
                    </article>
                </div>
            </section>
        </main><?php endif; ?>
</body>

</html>