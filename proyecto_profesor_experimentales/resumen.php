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
    <title>Resumen del profesor</title>
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
                    <p class="subtitulo"><?= e((string) $evaluacion['area']) ?> · Categoría actual:
                        <?= e((string) $evaluacion['categoria']) ?></p>
                </div>
                <div class="estado-global"><span class="chip">Resultado: <?= e((string) $resumen['resultado']) ?></span><span
                        class="fecha">Última actualización:
                        <?= e(formatearFecha((string) $evaluacion['fecha_creacion'])) ?></span></div>
            </div>
        </header>
        <main class="contenedor">
            <section class="seccion rejilla-kpis">
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">Cumplimiento global</p>
                    <p class="valor"><?= number_format((float) $resumen['cumplimiento_global'], 0) ?>%</p>
                    <div class="texto-menor">Total final: <?= number_format((float) $resumen['total_final'], 2) ?> / 100
                    </div>
                </article>
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">Estado estimado</p>
                    <p class="valor"><?= e((string) $resumen['estado_global']) ?></p>
                    <div class="texto-menor">Basado en bloques y puntuación total</div>
                </article>
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">Bloques cumplidos</p>
                    <p class="valor"><?= (int) $resumen['bloques_cumplidos'] ?> / <?= (int) $resumen['bloques_totales'] ?></p>
                    <div class="texto-menor">Según objetivos configurados</div>
                </article>
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">Bloque más débil</p>
                    <p class="valor" style="font-size:1.5rem;"><?= e((string) $resumen['bloque_mas_debil']['nombre']) ?></p>
                    <div class="texto-menor"><?= number_format((float) $resumen['bloque_mas_debil']['porcentaje'], 0) ?>% de
                        progreso</div>
                </article>
            </section>
            <section class="seccion rejilla-principal">
                <article class="tarjeta panel-grande">
                    <h2 class="panel-titulo">Resumen de progreso</h2>
                    <div class="bloque-progreso">
                        <div class="porcentaje-principal"><?= number_format((float) $resumen['cumplimiento_global'], 0) ?>%
                        </div>
                        <div class="estado-texto"><?= e((string) $resumen['estado_global']) ?></div>
                    </div>
                    <div class="barra">
                        <div class="barra-interna"
                            style="width: <?= number_format((float) $resumen['cumplimiento_global'], 2, '.', '') ?>%;"></div>
                    </div>
                    <p class="frase-resumen"><?= e((string) $resumen['resultado']) ?> ·
                        <?= $resumen['cumple_regla_1'] ? 'Cumple regla 1' : 'No cumple regla 1' ?> ·
                        <?= $resumen['cumple_regla_2'] ? 'Cumple regla 2' : 'No cumple regla 2' ?></p>
                </article>
                <aside class="tarjeta panel-lateral">
                    <h3 class="subseccion-titulo">Lo que te falta</h3>
                    <ul class="lista"><?php foreach ($faltantes as $faltante): ?>
                            <li><?= e($faltante) ?></li><?php endforeach; ?>
                    </ul>
                </aside>
            </section>
            <section class="seccion tarjeta panel-pestanas"><?php include __DIR__ . '/includes/botonera_dashboard.php'; ?>
                <div class="detalle-grid">
                    <article class="mini-tarjeta">
                        <h4>Ya cumples</h4>
                        <ul><?php foreach ($bloques as $bloque): ?><?php if ($bloque['porcentaje'] >= 100): ?>
                                    <li><?= e($bloque['nombre']) ?></li><?php endif; ?><?php endforeach; ?>
                        </ul>
                    </article>
                    <article class="mini-tarjeta">
                        <h4>Todavía te falta</h4>
                        <ul><?php foreach ($bloques as $bloque): ?><?php if ($bloque['porcentaje'] < 100): ?>
                                    <li><?= e($bloque['nombre']) ?> — faltan <?= number_format((float) $bloque['faltante'], 2) ?>
                                        puntos</li><?php endif; ?><?php endforeach; ?>
                        </ul>
                    </article>
                    <article class="mini-tarjeta">
                        <h4>Prioridades</h4>
                        <ul><?php $ordenados = $bloques;
                        usort($ordenados, fn($a, $b) => $a['porcentaje'] <=> $b['porcentaje']);
                        foreach (array_slice($ordenados, 0, 3) as $bloque): ?>
                                <li><?= e($bloque['nombre']) ?></li><?php endforeach; ?>
                        </ul>
                    </article>
                </div>
            </section>
        </main><?php endif; ?>
</body>

</html>