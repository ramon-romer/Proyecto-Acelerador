<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_tecnicas.php';
require_once __DIR__ . '/../src/evaluaciones_traceability.php';

$nombreCandidato = trim($_POST['nombre_candidato'] ?? '');
$jsonEntrada = trim($_POST['json_entrada'] ?? '');

if ($nombreCandidato === '' || $jsonEntrada === '') {
    die('Faltan datos obligatorios.');
}

$datosExtraidos = json_decode($jsonEntrada, true);

if (!is_array($datosExtraidos)) {
    die('El JSON extraído no es válido.');
}

/*
 * Normalizamos mínimos por seguridad, para que el evaluador
 * no falle si el extractor devuelve bloques incompletos.
 */
$orcidCandidato = aneca_attach_candidate_orcid($datosExtraidos);

$datosExtraidos['nombre_candidato'] = $nombreCandidato;
$datosExtraidos['area'] = 'Técnicas';
$datosExtraidos['categoria'] = 'PCD/PUP';

if (!isset($datosExtraidos['bloque_1']) || !is_array($datosExtraidos['bloque_1'])) {
    $datosExtraidos['bloque_1'] = [];
}
if (!isset($datosExtraidos['bloque_2']) || !is_array($datosExtraidos['bloque_2'])) {
    $datosExtraidos['bloque_2'] = [];
}
if (!isset($datosExtraidos['bloque_3']) || !is_array($datosExtraidos['bloque_3'])) {
    $datosExtraidos['bloque_3'] = [];
}
if (!isset($datosExtraidos['bloque_4']) || !is_array($datosExtraidos['bloque_4'])) {
    $datosExtraidos['bloque_4'] = [];
}

$datosExtraidos['bloque_1']['publicaciones'] = is_array($datosExtraidos['bloque_1']['publicaciones'] ?? null) ? $datosExtraidos['bloque_1']['publicaciones'] : [];
$datosExtraidos['bloque_1']['libros'] = is_array($datosExtraidos['bloque_1']['libros'] ?? null) ? $datosExtraidos['bloque_1']['libros'] : [];
$datosExtraidos['bloque_1']['proyectos'] = is_array($datosExtraidos['bloque_1']['proyectos'] ?? null) ? $datosExtraidos['bloque_1']['proyectos'] : [];
$datosExtraidos['bloque_1']['transferencia'] = is_array($datosExtraidos['bloque_1']['transferencia'] ?? null) ? $datosExtraidos['bloque_1']['transferencia'] : [];
$datosExtraidos['bloque_1']['tesis_dirigidas'] = is_array($datosExtraidos['bloque_1']['tesis_dirigidas'] ?? null) ? $datosExtraidos['bloque_1']['tesis_dirigidas'] : [];
$datosExtraidos['bloque_1']['congresos'] = is_array($datosExtraidos['bloque_1']['congresos'] ?? null) ? $datosExtraidos['bloque_1']['congresos'] : [];
$datosExtraidos['bloque_1']['otros_meritos_investigacion'] = is_array($datosExtraidos['bloque_1']['otros_meritos_investigacion'] ?? null) ? $datosExtraidos['bloque_1']['otros_meritos_investigacion'] : [];

$datosExtraidos['bloque_2']['docencia_universitaria'] = is_array($datosExtraidos['bloque_2']['docencia_universitaria'] ?? null) ? $datosExtraidos['bloque_2']['docencia_universitaria'] : [];
$datosExtraidos['bloque_2']['evaluacion_docente'] = is_array($datosExtraidos['bloque_2']['evaluacion_docente'] ?? null) ? $datosExtraidos['bloque_2']['evaluacion_docente'] : [];
$datosExtraidos['bloque_2']['formacion_docente'] = is_array($datosExtraidos['bloque_2']['formacion_docente'] ?? null) ? $datosExtraidos['bloque_2']['formacion_docente'] : [];
$datosExtraidos['bloque_2']['material_docente'] = is_array($datosExtraidos['bloque_2']['material_docente'] ?? null) ? $datosExtraidos['bloque_2']['material_docente'] : [];

$datosExtraidos['bloque_3']['formacion_academica'] = is_array($datosExtraidos['bloque_3']['formacion_academica'] ?? null) ? $datosExtraidos['bloque_3']['formacion_academica'] : [];
$datosExtraidos['bloque_3']['experiencia_profesional'] = is_array($datosExtraidos['bloque_3']['experiencia_profesional'] ?? null) ? $datosExtraidos['bloque_3']['experiencia_profesional'] : [];

$resultado = evaluar_expediente($datosExtraidos);

/*
 * Guardamos el resultado completo dentro del JSON para que
 * ver_evaluacion.php pueda mostrar diagnóstico y asesor.
 */
$datosExtraidos['resultado_calculo'] = $resultado;

$jsonFinal = json_encode($datosExtraidos, JSON_UNESCAPED_UNICODE);

if ($jsonFinal === false) {
    die('No se pudo serializar el JSON final.');
}

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

    puntuacion_2a,
    puntuacion_2b,
    puntuacion_2c,
    puntuacion_2d,

    puntuacion_3a,
    puntuacion_3b,

    puntuacion_4,

    bloque_1,
    bloque_2,
    bloque_3,
    bloque_4,

    total_b1_b2,
    total_final,

    cumple_regla_1,
    cumple_regla_2,

    resultado,
    fecha_creacion
) VALUES (
    :nombre_candidato,
    :orcid_candidato,
    :area,
    :categoria,
    :json_entrada,

    :p1a,
    :p1b,
    :p1c,
    :p1d,
    :p1e,
    :p1f,
    :p1g,

    :p2a,
    :p2b,
    :p2c,
    :p2d,

    :p3a,
    :p3b,

    :p4,

    :b1,
    :b2,
    :b3,
    :b4,

    :total_b1_b2,
    :total_final,

    :cumple_regla_1,
    :cumple_regla_2,

    :resultado,
    NOW()
)";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':nombre_candidato' => $nombreCandidato,
    ':orcid_candidato' => $orcidCandidato,
    ':area' => 'Técnicas',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $jsonFinal,

    ':p1a' => $resultado['puntuaciones']['1A'],
    ':p1b' => $resultado['puntuaciones']['1B'],
    ':p1c' => $resultado['puntuaciones']['1C'],
    ':p1d' => $resultado['puntuaciones']['1D'],
    ':p1e' => $resultado['puntuaciones']['1E'],
    ':p1f' => $resultado['puntuaciones']['1F'],
    ':p1g' => $resultado['puntuaciones']['1G'],

    ':p2a' => $resultado['puntuaciones']['2A'],
    ':p2b' => $resultado['puntuaciones']['2B'],
    ':p2c' => $resultado['puntuaciones']['2C'],
    ':p2d' => $resultado['puntuaciones']['2D'],

    ':p3a' => $resultado['puntuaciones']['3A'],
    ':p3b' => $resultado['puntuaciones']['3B'],

    ':p4' => $resultado['puntuaciones']['4'],

    ':b1' => $resultado['bloque_1']['B1'],
    ':b2' => $resultado['bloque_2']['B2'],
    ':b3' => $resultado['bloque_3']['B3'],
    ':b4' => $resultado['bloque_4']['B4'],

    ':total_b1_b2' => $resultado['totales']['total_b1_b2'],
    ':total_final' => $resultado['totales']['total_final'],

    ':cumple_regla_1' => $resultado['decision']['cumple_regla_1'] ? 1 : 0,
    ':cumple_regla_2' => $resultado['decision']['cumple_regla_2'] ? 1 : 0,

    ':resultado' => $resultado['decision']['resultado'],
]);

$idEvaluacion = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $idEvaluacion);
exit;
