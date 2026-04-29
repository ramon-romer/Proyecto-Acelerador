<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_csyj.php';
require_once __DIR__ . '/../src/evaluaciones_traceability.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no permitido.');
}

function csyj_post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    return is_array($value) ? array_values($value) : [];
}

function csyj_normalizar_lista_post(mixed $lista): array
{
    if (!is_array($lista)) {
        return [];
    }

    $resultado = [];

    foreach ($lista as $item) {
        if (!is_array($item)) {
            continue;
        }

        $limpio = [];
        foreach ($item as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $limpio[(string)$key] = is_string($value) ? trim($value) : $value;
        }

        $vacio = true;
        foreach ($limpio as $value) {
            if ($value !== '' && $value !== null) {
                $vacio = false;
                break;
            }
        }

        if ($vacio) {
            continue;
        }

        if (!isset($limpio['es_valido']) && !isset($limpio['es_valida'])) {
            $limpio['es_valido'] = 1;
        }

        $resultado[] = $limpio;
    }

    return $resultado;
}

function csyj_append_items(array &$destino, array $items): void
{
    foreach ($items as $item) {
        if (is_array($item)) {
            $destino[] = $item;
        }
    }
}

$nombreCandidato = trim($_POST['nombre_candidato'] ?? '');
$jsonEntradaBase = trim($_POST['json_entrada_base'] ?? '');

if ($nombreCandidato === '' || $jsonEntradaBase === '') {
    die('Faltan datos obligatorios.');
}

$jsonBase = json_decode($jsonEntradaBase, true);
if (!is_array($jsonBase)) {
    die('El JSON base no es válido.');
}

$jsonBase['nombre_candidato'] = $nombreCandidato;
$jsonBase['area'] = 'Ciencias Sociales y Jurídicas';
$jsonBase['categoria'] = 'PCD/PUP';

if (!isset($jsonBase['bloque_1']) || !is_array($jsonBase['bloque_1'])) {
    $jsonBase['bloque_1'] = [];
}
if (!isset($jsonBase['bloque_2']) || !is_array($jsonBase['bloque_2'])) {
    $jsonBase['bloque_2'] = [];
}
if (!isset($jsonBase['bloque_3']) || !is_array($jsonBase['bloque_3'])) {
    $jsonBase['bloque_3'] = [];
}
if (!isset($jsonBase['bloque_4']) || !is_array($jsonBase['bloque_4'])) {
    $jsonBase['bloque_4'] = [];
}

$jsonBase['bloque_1']['publicaciones'] = is_array($jsonBase['bloque_1']['publicaciones'] ?? null) ? $jsonBase['bloque_1']['publicaciones'] : [];
$jsonBase['bloque_1']['libros'] = is_array($jsonBase['bloque_1']['libros'] ?? null) ? $jsonBase['bloque_1']['libros'] : [];
$jsonBase['bloque_1']['proyectos'] = is_array($jsonBase['bloque_1']['proyectos'] ?? null) ? $jsonBase['bloque_1']['proyectos'] : [];
$jsonBase['bloque_1']['transferencia'] = is_array($jsonBase['bloque_1']['transferencia'] ?? null) ? $jsonBase['bloque_1']['transferencia'] : [];
$jsonBase['bloque_1']['tesis_dirigidas'] = is_array($jsonBase['bloque_1']['tesis_dirigidas'] ?? null) ? $jsonBase['bloque_1']['tesis_dirigidas'] : [];
$jsonBase['bloque_1']['congresos'] = is_array($jsonBase['bloque_1']['congresos'] ?? null) ? $jsonBase['bloque_1']['congresos'] : [];
$jsonBase['bloque_1']['otros_meritos_investigacion'] = is_array($jsonBase['bloque_1']['otros_meritos_investigacion'] ?? null) ? $jsonBase['bloque_1']['otros_meritos_investigacion'] : [];

$jsonBase['bloque_2']['docencia_universitaria'] = is_array($jsonBase['bloque_2']['docencia_universitaria'] ?? null) ? $jsonBase['bloque_2']['docencia_universitaria'] : [];
$jsonBase['bloque_2']['evaluacion_docente'] = is_array($jsonBase['bloque_2']['evaluacion_docente'] ?? null) ? $jsonBase['bloque_2']['evaluacion_docente'] : [];
$jsonBase['bloque_2']['formacion_docente'] = is_array($jsonBase['bloque_2']['formacion_docente'] ?? null) ? $jsonBase['bloque_2']['formacion_docente'] : [];
$jsonBase['bloque_2']['material_docente'] = is_array($jsonBase['bloque_2']['material_docente'] ?? null) ? $jsonBase['bloque_2']['material_docente'] : [];

$jsonBase['bloque_3']['formacion_academica'] = is_array($jsonBase['bloque_3']['formacion_academica'] ?? null) ? $jsonBase['bloque_3']['formacion_academica'] : [];
$jsonBase['bloque_3']['experiencia_profesional'] = is_array($jsonBase['bloque_3']['experiencia_profesional'] ?? null) ? $jsonBase['bloque_3']['experiencia_profesional'] : [];

$jsonBase['bloque_4'] = is_array($jsonBase['bloque_4']) ? $jsonBase['bloque_4'] : [];

csyj_append_items($jsonBase['bloque_1']['publicaciones'], csyj_normalizar_lista_post(csyj_post_array('publicaciones')));
csyj_append_items($jsonBase['bloque_1']['libros'], csyj_normalizar_lista_post(csyj_post_array('libros')));
csyj_append_items($jsonBase['bloque_1']['proyectos'], csyj_normalizar_lista_post(csyj_post_array('proyectos')));
csyj_append_items($jsonBase['bloque_1']['transferencia'], csyj_normalizar_lista_post(csyj_post_array('transferencia')));
csyj_append_items($jsonBase['bloque_1']['tesis_dirigidas'], csyj_normalizar_lista_post(csyj_post_array('tesis_dirigidas')));
csyj_append_items($jsonBase['bloque_1']['congresos'], csyj_normalizar_lista_post(csyj_post_array('congresos')));
csyj_append_items($jsonBase['bloque_1']['otros_meritos_investigacion'], csyj_normalizar_lista_post(csyj_post_array('otros_meritos_investigacion')));

csyj_append_items($jsonBase['bloque_2']['docencia_universitaria'], csyj_normalizar_lista_post(csyj_post_array('docencia')));
csyj_append_items($jsonBase['bloque_2']['evaluacion_docente'], csyj_normalizar_lista_post(csyj_post_array('evaluacion_docente')));
csyj_append_items($jsonBase['bloque_2']['formacion_docente'], csyj_normalizar_lista_post(csyj_post_array('formacion_docente')));
csyj_append_items($jsonBase['bloque_2']['material_docente'], csyj_normalizar_lista_post(csyj_post_array('material_docente')));

csyj_append_items($jsonBase['bloque_3']['formacion_academica'], csyj_normalizar_lista_post(csyj_post_array('formacion')));
csyj_append_items($jsonBase['bloque_3']['experiencia_profesional'], csyj_normalizar_lista_post(csyj_post_array('experiencia')));

csyj_append_items($jsonBase['bloque_4'], csyj_normalizar_lista_post(csyj_post_array('bloque4')));

$orcidCandidato = aneca_attach_candidate_orcid($jsonBase);
$resultado = evaluar_expediente($jsonBase);
$jsonBase['resultado_calculo'] = [
    'puntuaciones' => $resultado['puntuaciones'] ?? [],
    'bloque_1' => $resultado['bloque_1'] ?? [],
    'bloque_2' => $resultado['bloque_2'] ?? [],
    'bloque_3' => $resultado['bloque_3'] ?? [],
    'bloque_4' => $resultado['bloque_4'] ?? [],
    'totales' => $resultado['totales'] ?? [],
    'decision' => $resultado['decision'] ?? [],
    'diagnostico' => $resultado['diagnostico'] ?? [],
    'asesor' => $resultado['asesor'] ?? [],
];

$jsonFinal = json_encode($jsonBase, JSON_UNESCAPED_UNICODE);
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
    cumple_regla_2,
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
    :b1,
    :p2a,
    :p2b,
    :p2c,
    :p2d,
    :b2,
    :p3a,
    :p3b,
    :b3,
    :b4,
    :total_b1_b2,
    :total_final,
    :resultado,
    :cumple_regla_1,
    :cumple_regla_2,
    NOW()
)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre_candidato' => $nombreCandidato,
    ':orcid_candidato' => $orcidCandidato,
    ':area' => 'Ciencias Sociales y Jurídicas',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $jsonFinal,

    ':p1a' => $resultado['puntuaciones']['1A'] ?? 0,
    ':p1b' => $resultado['puntuaciones']['1B'] ?? 0,
    ':p1c' => $resultado['puntuaciones']['1C'] ?? 0,
    ':p1d' => $resultado['puntuaciones']['1D'] ?? 0,
    ':p1e' => $resultado['puntuaciones']['1E'] ?? 0,
    ':p1f' => $resultado['puntuaciones']['1F'] ?? 0,
    ':p1g' => $resultado['puntuaciones']['1G'] ?? 0,
    ':b1' => $resultado['bloque_1']['B1'] ?? $resultado['bloque_1_total'] ?? 0,

    ':p2a' => $resultado['puntuaciones']['2A'] ?? 0,
    ':p2b' => $resultado['puntuaciones']['2B'] ?? 0,
    ':p2c' => $resultado['puntuaciones']['2C'] ?? 0,
    ':p2d' => $resultado['puntuaciones']['2D'] ?? 0,
    ':b2' => $resultado['bloque_2']['B2'] ?? $resultado['bloque_2_total'] ?? 0,

    ':p3a' => $resultado['puntuaciones']['3A'] ?? 0,
    ':p3b' => $resultado['puntuaciones']['3B'] ?? 0,
    ':b3' => $resultado['bloque_3']['B3'] ?? $resultado['bloque_3_total'] ?? 0,

    ':b4' => $resultado['bloque_4']['B4'] ?? $resultado['bloque_4_total'] ?? 0,

    ':total_b1_b2' => $resultado['totales']['total_b1_b2'] ?? $resultado['total_b1_b2'] ?? 0,
    ':total_final' => $resultado['totales']['total_final'] ?? $resultado['total_final'] ?? 0,
    ':resultado' => $resultado['decision']['resultado'] ?? $resultado['resultado'] ?? 'NEGATIVA',
    ':cumple_regla_1' => !empty($resultado['decision']['cumple_regla_1']) ? 1 : (int)($resultado['cumple_regla_1'] ?? 0),
    ':cumple_regla_2' => !empty($resultado['decision']['cumple_regla_2']) ? 1 : (int)($resultado['cumple_regla_2'] ?? 0),
]);

$idEvaluacion = (int)$pdo->lastInsertId();
header('Location: ver_evaluacion.php?id=' . $idEvaluacion);
exit;
