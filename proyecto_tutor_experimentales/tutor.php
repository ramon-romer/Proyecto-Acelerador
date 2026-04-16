<?php
declare(strict_types=1);
echo "<h1 style='color: red; font-size: 50px;'>¡EL ARCHIVO TUTOR SÍ CARGA!</h1>";
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/funciones_tutor.php';

$error = null;
$profesores = [];
$resumen = [];
try {
    $conexion = obtenerConexion();
    $profesores = obtenerProfesoresEnriquecidos($conexion);
    $resumen = construirResumenTutor($profesores);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Cuadro de mando del tutor</title>
    <link rel="stylesheet" href="css/dashboard_tutor.css">
</head>

<body>
    <?php if ($error): ?>
        <div class="contenedor">
            <div class="tarjeta panel-contenido" style="margin-top:24px;"><strong>Error:</strong> <?= e($error) ?></div>
        </div>
    <?php else: ?>
        <header class="cabecera">
            <div class="contenedor cabecera-grid">
                <div>
                    <h1 class="titulo">Cuadro de mando del tutor</h1>
                    <p class="subtitulo">Seguimiento visual del progreso de los profesores · Rama de Experimentales</p>
                </div>
                <div class="estado-global"><span class="chip">Tutor: Dra. Laura Gómez Ruiz</span><span
                        class="fecha">Profesores analizados: <?= (int) $resumen['total'] ?></span></div>
            </div>
        </header>
        <main class="contenedor">
            <section class="seccion rejilla-kpis">
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">Profesores asignados</p>
                    <p class="valor"><?= (int) $resumen['total'] ?></p>
                    <div class="texto-menor">Total de profesores bajo seguimiento</div>
                </article>
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">En buen estado</p>
                    <p class="valor"><?= (int) $resumen['cerca'] ?></p>
                    <div class="texto-menor">Con progreso alto o cercano al objetivo</div>
                </article>
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">En seguimiento</p>
                    <p class="valor"><?= (int) $resumen['seguimiento'] ?></p>
                    <div class="texto-menor">Con avances, pero con brechas relevantes</div>
                </article>
                <article class="tarjeta tarjeta-kpi">
                    <p class="etiqueta">Prioridad alta</p>
                    <p class="valor"><?= (int) $resumen['prioridad_alta'] ?></p>
                    <div class="texto-menor">Profesores con déficits más acusados</div>
                </article>
            </section>

            <section class="seccion tarjeta panel-contenido">
                <h2 class="panel-titulo">Vista general del profesorado</h2>
                <div class="barra-filtros">
                    <div class="filtro">Todos</div>
                    <div class="filtro">Cerca del objetivo</div>
                    <div class="filtro">En progreso</div>
                    <div class="filtro">Prioridad alta</div>
                </div>
                <table class="tabla-profesores">
                    <thead>
                        <tr>
                            <th>Profesor</th>
                            <th>Progreso global</th>
                            <th>Bloque más débil</th>
                            <th>Estado</th>
                            <th>Qué le falta</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profesores as $profesor): ?>
                            <tr>
                                <td>
                                    <div class="nombre-profesor"><?= e((string) $profesor['nombre_candidato']) ?></div>
                                    <div class="meta-profesor"><?= e((string) $profesor['categoria']) ?> ·
                                        <?= e((string) $profesor['area']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="barra-progreso"><span
                                            class="<?= $profesor['clase_estado'] === 'estado-verde' ? 'progreso-verde' : ($profesor['clase_estado'] === 'estado-ambar' ? 'progreso-ambar' : 'progreso-rojo') ?>"
                                            style="width: <?= obtenerAnchoBarra((float) $profesor['cumplimiento_global']) ?>;"></span>
                                    </div>
                                    <div class="texto-progreso">
                                        <?= number_format((float) $profesor['cumplimiento_global'], 0) ?>% de cumplimiento
                                        global
                                    </div>
                                </td>
                                <td><?= e((string) $profesor['bloque_mas_debil']) ?></td>
                                <td><span
                                        class="estado-chip <?= e((string) $profesor['clase_estado']) ?>"><?= e((string) $profesor['texto_estado']) ?></span>
                                </td>
                                <td>
                                    <ul class="lista-breve"><?php foreach ($profesor['faltas_breves'] as $falta): ?>
                                            <li><?= e((string) $falta) ?></li><?php endforeach; ?>
                                    </ul>
                                </td>
                                <td><a href="<?= e((string) $profesor['url_detalle']) ?>" class="boton-detalles">Más
                                        detalles</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="seccion resumen-inferior">
                <article class="mini-tarjeta">
                    <h3>Profesores más avanzados</h3>
                    <ul><?php foreach ($resumen['mas_avanzados'] as $profesor): ?>
                            <li><?= e((string) $profesor['nombre_candidato']) ?>
                                (<?= number_format((float) $profesor['cumplimiento_global'], 0) ?>%)</li><?php endforeach; ?>
                    </ul>
                </article>
                <article class="mini-tarjeta">
                    <h3>Profesores que requieren atención</h3>
                    <ul><?php foreach ($resumen['mas_necesitados'] as $profesor): ?>
                            <li><?= e((string) $profesor['nombre_candidato']) ?>
                                (<?= number_format((float) $profesor['cumplimiento_global'], 0) ?>%)</li><?php endforeach; ?>
                    </ul>
                </article>
                <article class="mini-tarjeta">
                    <h3>Alertas principales del grupo</h3>
                    <ul>
                        <li>Déficit recurrente en otros méritos.</li>
                        <li>Varias carencias en formación y experiencia profesional.</li>
                        <li>Conviene revisar los expedientes con estado de prioridad alta.</li>
                    </ul>
                </article>
            </section>
        </main>
    <?php endif; ?>
</body>

</html>