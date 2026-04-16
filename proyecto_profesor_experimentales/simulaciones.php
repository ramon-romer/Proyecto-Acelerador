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
    <title>Simulaciones</title>
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
                    <p class="subtitulo">Simulaciones de escenario</p>
                </div>
                <div class="estado-global"><span class="chip">Simulaciones</span></div>
            </div>
        </header>
        <main class="contenedor">
            <section class="seccion tarjeta panel-pestanas"><?php include __DIR__ . '/includes/botonera_dashboard.php'; ?>
                <h2 class="panel-titulo">Simulaciones</h2>
                <?php if (count($simulaciones) > 0): ?>
                    <pre
                        style="white-space: pre-wrap; background:#f8fbff; border:1px solid var(--borde); padding:16px; border-radius:12px; overflow:auto;"><?= e(json_encode($simulaciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                <?php else: ?>
                    <div class="tarjeta" style="padding:20px;">
                        <p>No se han encontrado simulaciones estructuradas dentro de <code>json_entrada</code>.</p>
                        <p class="texto-menor">Esta pantalla queda preparada para una futura simulación de escenarios.</p>
                    </div><?php endif; ?>
            </section>
        </main><?php endif; ?>
</body>

</html>