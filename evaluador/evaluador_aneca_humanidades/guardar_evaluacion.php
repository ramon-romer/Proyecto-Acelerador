<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_humanidades.php';
require_once __DIR__ . '/../src/evaluaciones_traceability.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no permitido.');
}

$nombre = trim($_POST['nombre_candidato'] ?? '');
$jsonEntradaTexto = trim($_POST['json_entrada'] ?? '');

if ($nombre === '') {
    die('Falta el nombre del candidato.');
}

if ($jsonEntradaTexto === '') {
    die('Falta el JSON de entrada.');
}

$jsonEntrada = json_decode($jsonEntradaTexto, true);
if (!is_array($jsonEntrada)) {
    die('El JSON de entrada no es válido.');
}

/* =========================================================
 * Garantizar estructura mínima para evitar warnings/errores
 * ========================================================= */
if (!isset($jsonEntrada['bloque_1']) || !is_array($jsonEntrada['bloque_1'])) {
    $jsonEntrada['bloque_1'] = [];
}
if (!isset($jsonEntrada['bloque_2']) || !is_array($jsonEntrada['bloque_2'])) {
    $jsonEntrada['bloque_2'] = [];
}
if (!isset($jsonEntrada['bloque_3']) || !is_array($jsonEntrada['bloque_3'])) {
    $jsonEntrada['bloque_3'] = [];
}
if (!isset($jsonEntrada['bloque_4']) || !is_array($jsonEntrada['bloque_4'])) {
    $jsonEntrada['bloque_4'] = [];
}

$orcidCandidato = aneca_attach_candidate_orcid($jsonEntrada);

$defaultsBloque1 = [
    'publicaciones',
    'libros',
    'proyectos',
    'transferencia',
    'tesis_dirigidas',
    'congresos',
    'otros_meritos_investigacion',
];

$defaultsBloque2 = [
    'docencia_universitaria',
    'evaluacion_docente',
    'formacion_docente',
    'material_docente',
];

$defaultsBloque3 = [
    'formacion_academica',
    'experiencia_profesional',
];

foreach ($defaultsBloque1 as $key) {
    if (!isset($jsonEntrada['bloque_1'][$key]) || !is_array($jsonEntrada['bloque_1'][$key])) {
        $jsonEntrada['bloque_1'][$key] = [];
    }
}
foreach ($defaultsBloque2 as $key) {
    if (!isset($jsonEntrada['bloque_2'][$key]) || !is_array($jsonEntrada['bloque_2'][$key])) {
        $jsonEntrada['bloque_2'][$key] = [];
    }
}
foreach ($defaultsBloque3 as $key) {
    if (!isset($jsonEntrada['bloque_3'][$key]) || !is_array($jsonEntrada['bloque_3'][$key])) {
        $jsonEntrada['bloque_3'][$key] = [];
    }
}

/* =========================================================
 * Evaluar expediente con lógica Humanidades PCD/PUP
 * ========================================================= */
$resultado = evaluar_expediente($jsonEntrada);

$jsonNormalizado = json_encode($jsonEntrada, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($jsonNormalizado === false) {
    die('No se pudo codificar el JSON de entrada.');
}

/* =========================================================
 * Guardar en base de datos
 * ========================================================= */
$sql = "INSERT INTO evaluaciones (
    nombre_candidato,
    orcid_candidato,
    area,
    categoria,
    json_entrada,
    puntuacion_1a,
    puntuacion_1b,
    puntuacion_1c,
    puntuacion_1d,
    puntuacion_1e,
    puntuacion_1f,
    puntuacion_1g,
    bloque_1,
    puntuacion_2a,
    puntuacion_2b,
    puntuacion_2c,
    puntuacion_2d,
    bloque_2,
    puntuacion_3a,
    puntuacion_3b,
    bloque_3,
    bloque_4,
    total_b1_b2,
    total_final,
    resultado,
    cumple_regla_1,
    cumple_regla_2
) VALUES (
    :nombre_candidato,
    :orcid_candidato,
    :area,
    :categoria,
    :json_entrada,
    :puntuacion_1a,
    :puntuacion_1b,
    :puntuacion_1c,
    :puntuacion_1d,
    :puntuacion_1e,
    :puntuacion_1f,
    :puntuacion_1g,
    :bloque_1,
    :puntuacion_2a,
    :puntuacion_2b,
    :puntuacion_2c,
    :puntuacion_2d,
    :bloque_2,
    :puntuacion_3a,
    :puntuacion_3b,
    :bloque_3,
    :bloque_4,
    :total_b1_b2,
    :total_final,
    :resultado,
    :cumple_regla_1,
    :cumple_regla_2
)";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':nombre_candidato' => $nombre,
    ':orcid_candidato' => $orcidCandidato,
    ':area' => 'Humanidades',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $jsonNormalizado,

    ':puntuacion_1a' => $resultado['puntuacion_1a'],
    ':puntuacion_1b' => $resultado['puntuacion_1b'],
    ':puntuacion_1c' => $resultado['puntuacion_1c'],
    ':puntuacion_1d' => $resultado['puntuacion_1d'],
    ':puntuacion_1e' => $resultado['puntuacion_1e'],
    ':puntuacion_1f' => $resultado['puntuacion_1f'],
    ':puntuacion_1g' => $resultado['puntuacion_1g'],
    ':bloque_1' => $resultado['bloque_1'],

    ':puntuacion_2a' => $resultado['puntuacion_2a'],
    ':puntuacion_2b' => $resultado['puntuacion_2b'],
    ':puntuacion_2c' => $resultado['puntuacion_2c'],
    ':puntuacion_2d' => $resultado['puntuacion_2d'],
    ':bloque_2' => $resultado['bloque_2'],

    ':puntuacion_3a' => $resultado['puntuacion_3a'],
    ':puntuacion_3b' => $resultado['puntuacion_3b'],
    ':bloque_3' => $resultado['bloque_3'],

    ':bloque_4' => $resultado['bloque_4'],

    ':total_b1_b2' => $resultado['total_b1_b2'],
    ':total_final' => $resultado['total_final'],
    ':resultado' => $resultado['resultado'],
    ':cumple_regla_1' => $resultado['cumple_regla_1'],
    ':cumple_regla_2' => $resultado['cumple_regla_2'],
]);

$id = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $id);
exit;
