<?php
declare(strict_types=1);

session_start();

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_experimentales.php';


if (!isset($_POST['token'], $_SESSION['token_experimentales'])) {
    http_response_code(400);
    exit('Falta el token.');
}

if (!hash_equals((string)$_SESSION['token_experimentales'], (string)$_POST['token'])) {
    http_response_code(400);
    exit('Token no válido.');
}

function es_array_lista(array $array): bool
{
    return array_keys($array) === range(0, count($array) - 1);
}

function fusionar_json_aneca(array $extraido, array $manual): array
{
    foreach ($manual as $clave => $valorManual) {
        if (!array_key_exists($clave, $extraido)) {
            $extraido[$clave] = $valorManual;
            continue;
        }

        if (is_array($valorManual) && is_array($extraido[$clave])) {
            if (es_array_lista($valorManual) && es_array_lista($extraido[$clave])) {
                $extraido[$clave] = array_merge($extraido[$clave], $valorManual);
            } else {
                $extraido[$clave] = fusionar_json_aneca($extraido[$clave], $valorManual);
            }
        } else {
            $extraido[$clave] = $valorManual;
        }
    }

    return $extraido;
}

function limpiar_lista(?array $lista): array
{
    if (!is_array($lista)) {
        return [];
    }

    $salida = [];

    foreach ($lista as $fila) {
        if (!is_array($fila)) {
            continue;
        }

        $filaLimpia = [];

        foreach ($fila as $clave => $valor) {
            if (is_string($valor)) {
                $valor = trim($valor);
            }

            if ($valor !== '' && $valor !== null) {
                $filaLimpia[$clave] = $valor;
            }
        }

        if ($filaLimpia !== []) {
            $salida[] = $filaLimpia;
        }
    }

    return $salida;
}

function normalizar_numero($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }

    if (is_string($valor)) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

$nombre_candidato = trim($_POST['nombre_candidato'] ?? '');
$json_entrada = trim($_POST['json_entrada'] ?? '');

if ($nombre_candidato === '' || $json_entrada === '') {
    http_response_code(400);
    exit('Faltan datos obligatorios.');
}

$extraido = json_decode($json_entrada, true);

if (!is_array($extraido)) {
    http_response_code(400);
    exit('El JSON base no es válido.');
}

$manual = [
    'bloque_1' => [
        'publicaciones' => limpiar_lista($_POST['publicaciones'] ?? []),
        'libros' => limpiar_lista($_POST['libros'] ?? []),
        'proyectos' => limpiar_lista($_POST['proyectos'] ?? []),
        'transferencia' => limpiar_lista($_POST['transferencia'] ?? []),
        'tesis_dirigidas' => limpiar_lista($_POST['tesis_dirigidas'] ?? []),
        'congresos' => limpiar_lista($_POST['congresos'] ?? []),
        'otros_meritos_investigacion' => limpiar_lista($_POST['otros_meritos_investigacion'] ?? []),
    ],
    'bloque_2' => [
        'docencia_universitaria' => limpiar_lista($_POST['docencia_universitaria'] ?? []),
        'evaluacion_docente' => limpiar_lista($_POST['evaluacion_docente'] ?? []),
        'formacion_docente' => limpiar_lista($_POST['formacion_docente'] ?? []),
        'material_docente' => limpiar_lista($_POST['material_docente'] ?? []),
    ],
    'bloque_3' => [
        'formacion_academica' => limpiar_lista($_POST['formacion_academica'] ?? []),
        'experiencia_profesional' => limpiar_lista($_POST['experiencia_profesional'] ?? []),
    ],
    'bloque_4' => limpiar_lista($_POST['bloque_4'] ?? []),
    'metadatos_extraccion' => [
        'area' => 'Experimentales',
        'comite' => 'Experimentales',
        'categoria' => 'PCD/PUP',
        'version_esquema' => '1.1',
        'completado_manual' => true,
    ],
];

$jsonFusionado = fusionar_json_aneca($extraido, $manual);
$jsonFusionadoPlano = json_encode($jsonFusionado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonFusionadoPlano === false) {
    http_response_code(500);
    exit('No se pudo reconstruir el JSON fusionado.');
}

$bloque_1 = [
    '1A' => min(35.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['publicaciones'] ?? []))),
    '1B' => min(7.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['libros'] ?? []))),
    '1C' => min(7.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['proyectos'] ?? []))),
    '1D' => min(4.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['transferencia'] ?? []))),
    '1E' => min(4.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['tesis_dirigidas'] ?? []))),
    '1F' => min(2.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['congresos'] ?? []))),
    '1G' => min(1.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_1']['otros_meritos_investigacion'] ?? []))),
];

$bloque_1['B1'] = min(60.0, array_sum($bloque_1));

$bloque_2 = [
    '2A' => min(17.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_2']['docencia_universitaria'] ?? []))),
    '2B' => min(3.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_2']['evaluacion_docente'] ?? []))),
    '2C' => min(3.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_2']['formacion_docente'] ?? []))),
    '2D' => min(7.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_2']['material_docente'] ?? []))),
];

$bloque_2['B2'] = min(30.0, array_sum($bloque_2));

$bloque_3 = [
    '3A' => min(6.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_3']['formacion_academica'] ?? []))),
    '3B' => min(2.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_3']['experiencia_profesional'] ?? []))),
];

$bloque_3['B3'] = min(8.0, array_sum($bloque_3));

$bloque_4 = [
    'B4' => min(2.0, array_sum(array_map(fn($x) => normalizar_numero($x['puntuacion'] ?? 0), $jsonFusionado['bloque_4'] ?? []))),
];

$totales = [
    'total_b1_b2' => $bloque_1['B1'] + $bloque_2['B2'],
    'total_final' => $bloque_1['B1'] + $bloque_2['B2'] + $bloque_3['B3'] + $bloque_4['B4'],
];

$decision = [
    'cumple_regla_1' => $totales['total_b1_b2'] >= 50.0,
    'cumple_regla_2' => $totales['total_final'] >= 55.0,
];

$decision['resultado'] = ($decision['cumple_regla_1'] && $decision['cumple_regla_2']) ? 'POSITIVA' : 'NEGATIVA';

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
    ':area' => 'Experimentales',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $jsonFusionadoPlano,

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

unset($_SESSION['token_experimentales']);

$id_evaluacion = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $id_evaluacion);
exit;