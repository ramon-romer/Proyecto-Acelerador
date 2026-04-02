<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_salud.php';

$nombre_candidato = trim($_POST['nombre_candidato'] ?? '');
$json_entrada = trim($_POST['json_entrada'] ?? '');

if ($nombre_candidato === '' || $json_entrada === '') {
    die('Faltan datos obligatorios.');
}

$datosExtraidos = json_decode($json_entrada, true);

if (!is_array($datosExtraidos)) {
    die('El JSON extraído no es válido.');
}

$resultado = evaluar_expediente($datosExtraidos);

$bloque_1 = $resultado['bloque_1'];
$bloque_2 = $resultado['bloque_2'];
$bloque_3 = $resultado['bloque_3'];
$bloque_4 = $resultado['bloque_4'];
$totales = $resultado['totales'];
$decision = $resultado['decision'];

$sql = "INSERT INTO evaluaciones (
    nombre_candidato, area, categoria, json_entrada,
    puntuacion_1a, puntuacion_1b, puntuacion_1c, puntuacion_1d, puntuacion_1e, puntuacion_1f, puntuacion_1g, bloque_1,
    puntuacion_2a, puntuacion_2b, puntuacion_2c, puntuacion_2d, bloque_2,
    puntuacion_3a, puntuacion_3b, bloque_3,
    bloque_4, total_b1_b2, total_final, resultado, cumple_regla_1, cumple_regla_2
) VALUES (
    :nombre_candidato, :area, :categoria, :json_entrada,
    :p1a, :p1b, :p1c, :p1d, :p1e, :p1f, :p1g, :b1,
    :p2a, :p2b, :p2c, :p2d, :b2,
    :p3a, :p3b, :b3,
    :b4, :total_b1_b2, :total_final, :resultado, :cumple_regla_1, :cumple_regla_2
)";

$sentencia = $pdo->prepare($sql);

$sentencia->execute([
    ':nombre_candidato' => $nombre_candidato,
    ':area' => 'Salud',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $json_entrada,

    ':p1a' => $bloque_1['1A'],
    ':p1b' => $bloque_1['1B'],
    ':p1c' => $bloque_1['1C'],
    ':p1d' => $bloque_1['1D'],
    ':p1e' => $bloque_1['1E'],
    ':p1f' => $bloque_1['1F'],
    ':p1g' => $bloque_1['1G'],
    ':b1' => $bloque_1['B1'],

    ':p2a' => $bloque_2['2A'],
    ':p2b' => $bloque_2['2B'],
    ':p2c' => $bloque_2['2C'],
    ':p2d' => $bloque_2['2D'],
    ':b2' => $bloque_2['B2'],

    ':p3a' => $bloque_3['3A'],
    ':p3b' => $bloque_3['3B'],
    ':b3' => $bloque_3['B3'],

    ':b4' => $bloque_4['B4'],
    ':total_b1_b2' => $totales['total_b1_b2'],
    ':total_final' => $totales['total_final'],
    ':resultado' => $decision['resultado'],
    ':cumple_regla_1' => $decision['cumple_regla_1'] ? 1 : 0,
    ':cumple_regla_2' => $decision['cumple_regla_2'] ? 1 : 0,
]);

$id_evaluacion = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $id_evaluacion);
exit;