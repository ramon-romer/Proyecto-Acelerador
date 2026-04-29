<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_experimentales.php';

function exp_post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    return is_array($value) ? array_values($value) : [];
}

function exp_normalizar_bool(mixed $value): bool
{
    return in_array((string)$value, ['1', 'true', 'on', 'si', 'sí'], true);
}

function exp_clean_string(mixed $value): string
{
    return trim((string)$value);
}

function exp_clean_int(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

function exp_clean_float(mixed $value, float $default = 0.0): float
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (float) str_replace(',', '.', (string)$value);
}

function exp_append_items(array &$destino, array $items): void
{
    foreach ($items as $item) {
        if (is_array($item)) {
            $destino[] = $item;
        }
    }
}

$nombreCandidato = trim($_POST['nombre_candidato'] ?? '');
$jsonEntradaBase = trim($_POST['json_entrada_base'] ?? '');

if ($nombreCandidato === '') {
    die('Falta el nombre del candidato.');
}

if ($jsonEntradaBase === '') {
    die('Falta el JSON base.');
}

$jsonBase = json_decode($jsonEntradaBase, true);

if (!is_array($jsonBase)) {
    die('El JSON base no es válido.');
}

/* =========================================================
 * ESTRUCTURA BASE
 * ========================================================= */
$jsonBase['nombre_candidato'] = $nombreCandidato;
$jsonBase['area'] = 'Experimentales';
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

/* =========================================================
 * 1A PUBLICACIONES
 * ========================================================= */
$publicacionesManual = [];
foreach (exp_post_array('publicaciones') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $publicacionesManual[] = [
        'tipo_indice' => exp_clean_string($item['tipo_indice'] ?? 'JCR'),
        'tercil' => exp_clean_string($item['tercil'] ?? ''),
        'especialidad' => exp_clean_string($item['especialidad'] ?? ''),
        'es_area_matematicas' => exp_normalizar_bool($item['es_area_matematicas'] ?? '0'),
        'posicion_autor' => exp_clean_string($item['posicion_autor'] ?? 'intermedio'),
        'numero_autores' => exp_clean_int($item['numero_autores'] ?? 1, 1),
        'orden_alfabetico' => exp_normalizar_bool($item['orden_alfabetico'] ?? '0'),
        'citas' => exp_clean_int($item['citas'] ?? 0, 0),
        'anios_desde_publicacion' => exp_clean_int($item['anios_desde_publicacion'] ?? 3, 3),
        'es_valida' => exp_normalizar_bool($item['es_valida'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['publicaciones'], $publicacionesManual);

/* =========================================================
 * 1B LIBROS Y CAPÍTULOS
 * ========================================================= */
$librosManual = [];
foreach (exp_post_array('libros') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $librosManual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'libro'),
        'nivel_editorial' => exp_clean_string($item['nivel_editorial'] ?? 'nacional'),
        'especialidad' => exp_clean_string($item['especialidad'] ?? ''),
        'complejidad_alta' => exp_normalizar_bool($item['complejidad_alta'] ?? '0'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['libros'], $librosManual);

/* =========================================================
 * 1C PROYECTOS Y CONTRATOS
 * ========================================================= */
$proyectosManual = [];
foreach (exp_post_array('proyectos') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $proyectosManual[] = [
        'tipo_proyecto' => exp_clean_string($item['tipo_proyecto'] ?? 'nacional'),
        'rol' => exp_clean_string($item['rol'] ?? 'investigador'),
        'anios_duracion' => exp_clean_float($item['anios_duracion'] ?? 0, 0),
        'esta_certificado' => exp_normalizar_bool($item['esta_certificado'] ?? '1'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['proyectos'], $proyectosManual);

/* =========================================================
 * 1D TRANSFERENCIA
 * ========================================================= */
$transferenciaManual = [];
foreach (exp_post_array('transferencia') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $transferenciaManual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'propiedad_intelectual'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['transferencia'], $transferenciaManual);

/* =========================================================
 * 1E TESIS
 * ========================================================= */
$tesisManual = [];
foreach (exp_post_array('tesis') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $tesisManual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'dirigida'),
        'doctorado_europeo' => exp_normalizar_bool($item['doctorado_europeo'] ?? '0'),
        'mencion_calidad' => exp_normalizar_bool($item['mencion_calidad'] ?? '0'),
        'numero_codirectores' => exp_clean_int($item['numero_codirectores'] ?? 0, 0),
        'proyecto_aprobado' => exp_normalizar_bool($item['proyecto_aprobado'] ?? '1'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['tesis_dirigidas'], $tesisManual);

/* =========================================================
 * 1F CONGRESOS
 * ========================================================= */
$congresosManual = [];
foreach (exp_post_array('congresos') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $congresosManual[] = [
        'ambito' => exp_clean_string($item['ambito'] ?? 'nacional'),
        'tipo' => exp_clean_string($item['tipo'] ?? 'comunicacion_oral'),
        'proceso_selectivo' => exp_normalizar_bool($item['proceso_selectivo'] ?? '1'),
        'id_evento' => exp_clean_string($item['id_evento'] ?? ''),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['congresos'], $congresosManual);

/* =========================================================
 * 1G OTROS MÉRITOS INVESTIGACIÓN
 * ========================================================= */
$otrosInvestigacionManual = [];
foreach (exp_post_array('otros_investigacion') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $otrosInvestigacionManual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'otro'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_1']['otros_meritos_investigacion'], $otrosInvestigacionManual);

/* =========================================================
 * 2A DOCENCIA
 * ========================================================= */
$docenciaManual = [];
foreach (exp_post_array('docencia') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $docenciaManual[] = [
        'horas' => exp_clean_int($item['horas'] ?? 0, 0),
        'tipo' => exp_clean_string($item['tipo'] ?? 'grado'),
        'etapa' => exp_clean_string($item['etapa'] ?? 'estable'),
        'tfg' => exp_clean_int($item['tfg'] ?? 0, 0),
        'tfm' => exp_clean_int($item['tfm'] ?? 0, 0),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_2']['docencia_universitaria'], $docenciaManual);

/* =========================================================
 * 2B EVALUACIÓN DOCENTE
 * ========================================================= */
$evalDocenteManual = [];
foreach (exp_post_array('eval_docente') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $evalDocenteManual[] = [
        'resultado' => exp_clean_string($item['resultado'] ?? 'favorable'),
        'cobertura_amplia' => exp_normalizar_bool($item['cobertura_amplia'] ?? '1'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_2']['evaluacion_docente'], $evalDocenteManual);

/* =========================================================
 * 2C FORMACIÓN DOCENTE
 * ========================================================= */
$formDocenteManual = [];
foreach (exp_post_array('form_docente') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $formDocenteManual[] = [
        'rol' => exp_clean_string($item['rol'] ?? 'ponente'),
        'horas' => exp_clean_int($item['horas'] ?? 0, 0),
        'relacion_docente' => exp_normalizar_bool($item['relacion_docente'] ?? '1'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_2']['formacion_docente'], $formDocenteManual);

/* =========================================================
 * 2D MATERIAL DOCENTE
 * ========================================================= */
$materialDocenteManual = [];
foreach (exp_post_array('material_docente') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $materialDocenteManual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'material_original'),
        'nivel_editorial' => exp_clean_string($item['nivel_editorial'] ?? 'nacional'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_2']['material_docente'], $materialDocenteManual);

/* =========================================================
 * 3A FORMACIÓN ACADÉMICA
 * ========================================================= */
$formacionManual = [];
foreach (exp_post_array('formacion') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $formacionManual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'curso_especializacion'),
        'alta_competitividad' => exp_normalizar_bool($item['alta_competitividad'] ?? '0'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_3']['formacion_academica'], $formacionManual);

/* =========================================================
 * 3B EXPERIENCIA PROFESIONAL
 * ========================================================= */
$experienciaManual = [];
foreach (exp_post_array('experiencia') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $experienciaManual[] = [
        'anios' => exp_clean_float($item['anios'] ?? 0, 0),
        'relacion' => exp_clean_string($item['relacion'] ?? 'media'),
        'justificada' => exp_normalizar_bool($item['justificada'] ?? '1'),
        'no_valorable' => exp_normalizar_bool($item['no_valorable'] ?? '0'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_3']['experiencia_profesional'], $experienciaManual);

/* =========================================================
 * 4 OTROS MÉRITOS
 * ========================================================= */
$bloque4Manual = [];
foreach (exp_post_array('bloque4') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $bloque4Manual[] = [
        'tipo' => exp_clean_string($item['tipo'] ?? 'otro'),
        'es_valido' => exp_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
exp_append_items($jsonBase['bloque_4'], $bloque4Manual);

/* =========================================================
 * EVALUAR
 * ========================================================= */
$resultado = evaluar_expediente($jsonBase);

/*
 * Guardamos el resultado completo dentro del JSON,
 * para que ver_evaluacion.php pueda mostrar diagnóstico,
 * asesor y simulaciones sin recalcular.
 */
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

/* =========================================================
 * SERIALIZAR JSON FINAL
 * ========================================================= */
$jsonFinal = json_encode($jsonBase, JSON_UNESCAPED_UNICODE);

if ($jsonFinal === false) {
    die('No se pudo serializar el JSON final.');
}

/* =========================================================
 * GUARDAR
 * ========================================================= */
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
} catch (PDOException $e) {
    echo '<pre>';
    echo 'ERROR PDO: ' . $e->getMessage() . "\n";
    echo 'Tamaño JSON final: ' . strlen($jsonFinal) . " bytes\n";
    echo '</pre>';
    exit;
}

$id = (int)$pdo->lastInsertId();
header('Location: ver_evaluacion.php?id=' . $id);
exit;