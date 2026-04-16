<?php
declare(strict_types=1);
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/funciones_tutor.php';
$error = null; $profesores = []; $resumen = [];
try {
    $conexion = obtenerConexion();
    $profesores = obtenerProfesoresEnriquecidos($conexion);
    $resumen = construirResumenTutor($profesores);
} catch (Throwable $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>Listado de profesores</title><link rel="stylesheet" href="css/dashboard_tutor.css"></head><body>
<?php if ($error): ?><div class="contenedor"><div class="tarjeta panel-contenido" style="margin-top:24px;"><strong>Error:</strong> <?= e($error) ?></div></div>
<?php else: ?>
<header class="cabecera"><div class="contenedor cabecera-grid"><div><h1 class="titulo">Listado completo de profesores</h1><p class="subtitulo">Vista auxiliar del tutor</p></div><div class="estado-global"><span class="chip">Total: <?= count($profesores) ?></span><span class="fecha"><a href="tutor.php" style="color:#fff;">Volver al cuadro de mando</a></span></div></div></header>
<main class="contenedor"><section class="seccion tarjeta panel-contenido">
<table class="tabla-profesores"><thead><tr><th>ID</th><th>Profesor</th><th>Categoría</th><th>Total final</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
<?php foreach ($profesores as $profesor): ?>
<tr><td><?= (int)$profesor['id'] ?></td><td><?= e((string)$profesor['nombre_candidato']) ?></td><td><?= e((string)$profesor['categoria']) ?></td><td><?= number_format((float)$profesor['total_final'], 2) ?></td><td><span class="estado-chip <?= e((string)$profesor['clase_estado']) ?>"><?= e((string)$profesor['texto_estado']) ?></span></td><td><a href="<?= e((string)$profesor['url_detalle']) ?>" class="boton-detalles">Más detalles</a></td></tr>
<?php endforeach; ?>
</tbody></table></section></main>
<?php endif; ?></body></html>
