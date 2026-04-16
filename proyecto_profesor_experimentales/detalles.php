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

<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Detalles del profesor</title><link rel="stylesheet" href="css/dashboard_profesor.css"></head><body>
<?php if ($error): ?><div class="contenedor"><div class="tarjeta" style="margin-top:24px; padding:20px;"><strong>Error:</strong> <?= e($error) ?></div></div>
<?php else: ?>
<header class="cabecera"><div class="contenedor cabecera-grid"><div><h1 class="titulo"><?= e((string)$evaluacion['nombre_candidato']) ?></h1><p class="subtitulo">Detalle de méritos detectados</p></div><div class="estado-global"><span class="chip">ID evaluación: <?= (int)$evaluacion['id'] ?></span></div></div></header>
<main class="contenedor"><section class="seccion tarjeta panel-pestanas"><?php include __DIR__ . '/includes/botonera_dashboard.php'; ?><h2 class="panel-titulo">Detalle de elementos detectados en el JSON</h2>
<table class="tabla"><thead><tr><th>Bloque</th><th>Apartado</th><th>Cantidad</th></tr></thead><tbody><?php foreach ($detalles as $detalle): ?><tr><td><?= e($detalle['bloque']) ?></td><td><?= e($detalle['apartado']) ?></td><td><?= (int)$detalle['cantidad'] ?></td></tr><?php endforeach; ?></tbody></table>
<section class="seccion detalle-grid">
<article class="mini-tarjeta"><h4>Investigación</h4><ul><li>Publicaciones: <?= (int)($bloques[0]['detalle']['publicaciones'] ?? 0) ?></li><li>Libros: <?= (int)($bloques[0]['detalle']['libros'] ?? 0) ?></li><li>Proyectos: <?= (int)($bloques[0]['detalle']['proyectos'] ?? 0) ?></li></ul></article>
<article class="mini-tarjeta"><h4>Docencia</h4><ul><li>Apartados docentes detectados: <?= isset($bloques[1]['detalle']['apartados']) ? count($bloques[1]['detalle']['apartados']) : 0 ?></li></ul></article>
<article class="mini-tarjeta"><h4>Otros datos</h4><ul><li>Área: <?= e((string)$evaluacion['area']) ?></li><li>Categoría: <?= e((string)$evaluacion['categoria']) ?></li><li>Resultado: <?= e((string)$evaluacion['resultado']) ?></li></ul></article>
</section></section></main><?php endif; ?></body></html>
