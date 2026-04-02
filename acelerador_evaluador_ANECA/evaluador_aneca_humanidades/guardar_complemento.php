<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_humanidades.php';

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

function bool_post(mixed $valor): bool
{
    return in_array((string)$valor, ['1', 'true', 'on'], true);
}

function int_post(mixed $valor, int $default = 0): int
{
    return is_numeric($valor) ? (int)$valor : $default;
}

function float_post(mixed $valor, float $default = 0.0): float
{
    return is_numeric($valor) ? (float)$valor : $default;
}

$nombre_candidato = trim($_POST['nombre_candidato'] ?? '');
$jsonEntradaBase = trim($_POST['json_entrada_base'] ?? '');

if ($nombre_candidato === '' || $jsonEntradaBase === '') {
    die('Faltan datos obligatorios.');
}

$extraido = json_decode($jsonEntradaBase, true);

if (!is_array($extraido)) {
    die('El JSON base extraído no es válido.');
}

/*
|--------------------------------------------------------------------------
| 1. BLOQUE 1
|--------------------------------------------------------------------------
*/
$publicaciones = [];
foreach (array_values($_POST['publicaciones'] ?? []) as $item) {
    $publicaciones[] = [
        'tipo' => 'articulo',
        'es_valida' => bool_post($item['es_valida'] ?? '1'),
        'tipo_indice' => (string)($item['tipo_indice'] ?? ''),
        'subtipo_indice' => (string)($item['subtipo_indice'] ?? ''),
        'cuartil' => (string)($item['cuartil'] ?? ''),
        'tipo_aportacion' => (string)($item['tipo_aportacion'] ?? 'articulo'),
        'afinidad' => (string)($item['afinidad'] ?? 'relacionada'),
        'posicion_autor' => (string)($item['posicion_autor'] ?? 'intermedio'),
        'numero_autores' => int_post($item['numero_autores'] ?? 1, 1),
        'citas' => int_post($item['citas'] ?? 0, 0),
    ];
}

$libros = [];
foreach (array_values($_POST['libros'] ?? []) as $item) {
    $libros[] = [
        'tipo' => (string)($item['tipo'] ?? 'capitulo'),
        'es_valido' => bool_post($item['es_valido'] ?? '1'),
        'es_libro_investigacion' => bool_post($item['es_libro_investigacion'] ?? '1'),
        'es_autoedicion' => bool_post($item['es_autoedicion'] ?? '0'),
        'es_acta_congreso' => bool_post($item['es_acta_congreso'] ?? '0'),
        'es_labor_edicion' => bool_post($item['es_labor_edicion'] ?? '0'),
        'nivel_editorial' => (string)($item['nivel_editorial'] ?? 'secundaria'),
        'afinidad' => (string)($item['afinidad'] ?? 'relacionada'),
        'posicion_autor' => (string)($item['posicion_autor'] ?? 'autor_unico'),
    ];
}

$proyectos = [];
foreach (array_values($_POST['proyectos'] ?? []) as $item) {
    $proyectos[] = [
        'es_valido' => bool_post($item['es_valido'] ?? '1'),
        'esta_certificado' => bool_post($item['esta_certificado'] ?? '1'),
        'tipo_proyecto' => (string)($item['tipo_proyecto'] ?? 'universidad'),
        'rol' => (string)($item['rol'] ?? 'investigador'),
        'anios_duracion' => float_post($item['anios_duracion'] ?? 0, 0),
    ];
}

/*
|--------------------------------------------------------------------------
| 2. BLOQUE 2
|--------------------------------------------------------------------------
*/
$docencia = [];
foreach (array_values($_POST['docencia'] ?? []) as $item) {
    $docencia[] = [
        'es_valido' => bool_post($item['es_valido'] ?? '1'),
        'horas' => int_post($item['horas'] ?? 0, 0),
        'nivel' => (string)($item['nivel'] ?? 'grado'),
        'responsabilidad' => (string)($item['responsabilidad'] ?? 'media'),
    ];
}

/*
|--------------------------------------------------------------------------
| 3. BLOQUE 3
|--------------------------------------------------------------------------
*/
$formacion = [];
foreach (array_values($_POST['formacion'] ?? []) as $item) {
    $formacion[] = [
        'es_valido' => bool_post($item['es_valido'] ?? '1'),
        'tipo' => (string)($item['tipo'] ?? 'master'),
    ];
}

$experiencia = [];
foreach (array_values($_POST['experiencia'] ?? []) as $item) {
    $experiencia[] = [
        'es_valido' => bool_post($item['es_valido'] ?? '1'),
        'anios' => float_post($item['anios'] ?? 0, 0),
        'relacion' => (string)($item['relacion'] ?? 'media'),
    ];
}

/*
|--------------------------------------------------------------------------
| 4. BLOQUE 4
|--------------------------------------------------------------------------
*/
$bloque4 = [];
foreach (array_values($_POST['bloque4'] ?? []) as $item) {
    $bloque4[] = [
        'es_valido' => bool_post($item['es_valido'] ?? '1'),
        'tipo' => (string)($item['tipo'] ?? 'otro'),
    ];
}

/*
|--------------------------------------------------------------------------
| 5. JSON MANUAL
|--------------------------------------------------------------------------
*/
$manual = [
    'bloque_1' => [
        'publicaciones' => $publicaciones,
        'libros' => $libros,
        'proyectos' => $proyectos,
        'transferencia' => [],
        'tesis_dirigidas' => [],
        'congresos' => [],
        'otros_meritos_investigacion' => [],
    ],
    'bloque_2' => [
        'docencia_universitaria' => $docencia,
        'evaluacion_docente' => [],
        'formacion_docente' => [],
        'material_docente' => [],
    ],
    'bloque_3' => [
        'formacion_academica' => $formacion,
        'experiencia_profesional' => $experiencia,
    ],
    'bloque_4' => $bloque4,
];

/*
|--------------------------------------------------------------------------
| 6. FUSIONAR JSON EXTRAÍDO + JSON MANUAL
|--------------------------------------------------------------------------
*/
$jsonFinal = fusionar_json_aneca($extraido, $manual);

/*
|--------------------------------------------------------------------------
| 7. EVALUAR
|--------------------------------------------------------------------------
*/
$resultado = evaluar_expediente($jsonFinal);

$bloque_1 = $resultado['bloque_1'];
$bloque_2 = $resultado['bloque_2'];
$bloque_3 = $resultado['bloque_3'];
$bloque_4_eval = $resultado['bloque_4'];
$totales = $resultado['totales'];
$decision = $resultado['decision'];

$json_entrada = json_encode($jsonFinal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($json_entrada === false) {
    die('No se pudo convertir el JSON final.');
}

/*
|--------------------------------------------------------------------------
| 8. GUARDAR EN BASE DE DATOS
|--------------------------------------------------------------------------
*/
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
    ':area' => 'Humanidades',
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

    ':b4' => $bloque_4_eval['B4'],
    ':total_b1_b2' => $totales['total_b1_b2'],
    ':total_final' => $totales['total_final'],
    ':resultado' => $decision['resultado'],
    ':cumple_regla_1' => $decision['cumple_regla_1'] ? 1 : 0,
    ':cumple_regla_2' => $decision['cumple_regla_2'] ? 1 : 0,
]);

$id = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $id);
exit;