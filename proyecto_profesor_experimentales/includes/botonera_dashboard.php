<?php
$paginaActual = basename($_SERVER['PHP_SELF']);
$idProfesor = isset($_GET['id']) ? (int) $_GET['id'] : 1;
function activa(string $paginaObjetivo, string $paginaActual): string {
    return $paginaObjetivo === $paginaActual ? 'activo' : '';
}
?>
<nav class="barra-botones-horizontal" aria-label="Navegación del dashboard">
    <a href="resumen.php?id=<?= $idProfesor ?>" class="boton-horizontal <?= activa('resumen.php', $paginaActual) ?>">Resumen</a>
    <a href="progreso_bloques.php?id=<?= $idProfesor ?>" class="boton-horizontal <?= activa('progreso_bloques.php', $paginaActual) ?>">Progreso por bloques</a>
    <a href="detalles.php?id=<?= $idProfesor ?>" class="boton-horizontal <?= activa('detalles.php', $paginaActual) ?>">Detalles</a>
    <a href="recomendaciones.php?id=<?= $idProfesor ?>" class="boton-horizontal <?= activa('recomendaciones.php', $paginaActual) ?>">Recomendaciones</a>
    <a href="simulaciones.php?id=<?= $idProfesor ?>" class="boton-horizontal <?= activa('simulaciones.php', $paginaActual) ?>">Simulaciones</a>
    <a href="descargar_informe.php?id=<?= $idProfesor ?>" class="boton-horizontal <?= activa('descargar_informe.php', $paginaActual) ?>">Descargar informe</a>
</nav>
