<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_experimentales.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$orcidSesion = trim((string)($_SESSION['orcid_usuario'] ?? ''));

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
 * Normalización mínima para asegurar estructura estable.
 */
$datosExtraidos['nombre_candidato'] = $nombreCandidato;
if ($orcidSesion !== '') {
    $datosExtraidos['orcid_candidato'] = $orcidSesion;
}
$datosExtraidos['area'] = 'Experimentales';
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

$datosExtraidos['bloque_4'] = is_array($datosExtraidos['bloque_4']) ? $datosExtraidos['bloque_4'] : [];

/*
 * Recalcular con la lógica nueva.
 */
$resultado = evaluar_expediente($datosExtraidos);

// Normalización de claves para asegurar que el INSERT tenga datos correctos
$b1Val = (float)($resultado['bloque_1']['B1'] ?? $resultado['bloque_1_total'] ?? $resultado['bloque_1_flat'] ?? $resultado['bloque_1'] ?? 0);
$b2Val = (float)($resultado['bloque_2']['B2'] ?? $resultado['bloque_2_total'] ?? $resultado['bloque_2_flat'] ?? $resultado['bloque_2'] ?? 0);
$b3Val = (float)($resultado['bloque_3']['B3'] ?? $resultado['bloque_3_total'] ?? $resultado['bloque_3_flat'] ?? $resultado['bloque_3'] ?? 0);
$b4Val = (float)($resultado['bloque_4']['B4'] ?? $resultado['bloque_4_total'] ?? $resultado['bloque_4_flat'] ?? $resultado['bloque_4'] ?? 0);

/*
 * Guardamos resultado resumido dentro del JSON para
 * que ver_evaluacion.php pueda mostrar diagnóstico y asesor.
 */
$datosExtraidos['resultado_calculo'] = [
    'puntuaciones' => $resultado['puntuaciones'] ?? [],
    'bloque_1' => ['B1' => $b1Val],
    'bloque_2' => ['B2' => $b2Val],
    'bloque_3' => ['B3' => $b3Val],
    'bloque_4' => ['B4' => $b4Val],
    'totales' => $resultado['totales'] ?? [],
    'decision' => $resultado['decision'] ?? [],
    'diagnostico' => $resultado['diagnostico'] ?? [],
    'asesor' => $resultado['asesor'] ?? [],
];

$jsonFinal = json_encode($datosExtraidos, JSON_UNESCAPED_UNICODE);

if ($jsonFinal === false) {
    die('No se pudo serializar el JSON final.');
}

$sql = "INSERT INTO evaluaciones (
    nombre_candidato,
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

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre_candidato' => $nombreCandidato,
        ':area' => 'Experimentales',
        ':categoria' => 'PCD/PUP',
        ':json_entrada' => $jsonFinal,

        ':p1a' => $resultado['puntuaciones']['1A'] ?? 0,
        ':p1b' => $resultado['puntuaciones']['1B'] ?? 0,
        ':p1c' => $resultado['puntuaciones']['1C'] ?? 0,
        ':p1d' => $resultado['puntuaciones']['1D'] ?? 0,
        ':p1e' => $resultado['puntuaciones']['1E'] ?? 0,
        ':p1f' => $resultado['puntuaciones']['1F'] ?? 0,
        ':p1g' => $resultado['puntuaciones']['1G'] ?? 0,

        ':p2a' => $resultado['puntuaciones']['2A'] ?? 0,
        ':p2b' => $resultado['puntuaciones']['2B'] ?? 0,
        ':p2c' => $resultado['puntuaciones']['2C'] ?? 0,
        ':p2d' => $resultado['puntuaciones']['2D'] ?? 0,

        ':p3a' => $resultado['puntuaciones']['3A'] ?? 0,
        ':p3b' => $resultado['puntuaciones']['3B'] ?? 0,

        ':p4' => $resultado['puntuaciones']['4'] ?? 0,

        ':b1' => $b1Val,
        ':b2' => $b2Val,
        ':b3' => $b3Val,
        ':b4' => $b4Val,

        ':total_b1_b2' => $resultado['totales']['total_b1_b2'] ?? ($b1Val + $b2Val),
        ':total_final' => $resultado['totales']['total_final'] ?? ($b1Val + $b2Val + $b3Val + $b4Val),

        ':cumple_regla_1' => !empty($resultado['decision']['cumple_regla_1']) ? 1 : 0,
        ':cumple_regla_2' => !empty($resultado['decision']['cumple_regla_2']) ? 1 : 0,

        ':resultado' => $resultado['decision']['resultado'] ?? 'NEGATIVA',
    ]);
} catch (PDOException $e) {
    echo '<pre>';
    echo 'ERROR PDO: ' . $e->getMessage() . "\n";
    echo 'Tamaño JSON final: ' . strlen($jsonFinal) . " bytes\n";
    echo '</pre>';
    exit;
}

$idEvaluacion = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $idEvaluacion);
exit;