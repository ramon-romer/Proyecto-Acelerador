<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_tecnicas.php';

function tec_post_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    return is_array($value) ? array_values($value) : [];
}

function tec_normalizar_bool(mixed $value): bool
{
    return in_array((string)$value, ['1', 'true', 'on', 'si', 'sí'], true);
}

function tec_clean_string(mixed $value): string
{
    return trim((string)$value);
}

function tec_clean_int(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

function tec_clean_float(mixed $value, float $default = 0.0): float
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (float) str_replace(',', '.', (string)$value);
}

function tec_append_items(array &$destino, array $items): void
{
    foreach ($items as $item) {
        if (is_array($item)) {
            $destino[] = $item;
        }
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$orcidSesion = trim((string)($_SESSION['orcid_usuario'] ?? ''));

$nombreCandidato = trim($_POST['nombre_candidato'] ?? '');
if ($nombreCandidato === '') {
    $nombreCandidato = trim($_POST['nombre_candidato_nuevo'] ?? '');
}

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
if ($orcidSesion !== '') {
    $jsonBase['orcid_candidato'] = $orcidSesion;
}
$jsonBase['area'] = 'Técnicas';
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
 * 1A PUBLICACIONES Y PATENTES
 * ========================================================= */
$publicacionesManual = [];
foreach (tec_post_array('publicaciones') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $publicacionesManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'articulo'),
        'tipo_indice' => tec_clean_string($item['tipo_indice'] ?? 'OTRO'),
        'subtipo_indice' => tec_clean_string($item['subtipo_indice'] ?? ''),
        'tercil' => tec_clean_string($item['tercil'] ?? ''),
        'cuartil' => tec_clean_string($item['cuartil'] ?? ''),
        'tipo_aportacion' => tec_clean_string($item['tipo_aportacion'] ?? 'articulo'),
        'afinidad' => tec_clean_string($item['afinidad'] ?? 'relacionada'),
        'posicion_autor' => tec_clean_string($item['posicion_autor'] ?? 'intermedio'),
        'numero_autores' => tec_clean_int($item['numero_autores'] ?? 1, 1),
        'citas' => tec_clean_int($item['citas'] ?? 0, 0),
        'anios_desde_publicacion' => tec_clean_int($item['anios_desde_publicacion'] ?? 3, 3),
        'liderazgo' => tec_normalizar_bool($item['liderazgo'] ?? '0'),
        'es_valida' => tec_normalizar_bool($item['es_valida'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['publicaciones'], $publicacionesManual);

/* =========================================================
 * 1B LIBROS Y CAPÍTULOS
 * ========================================================= */
$librosManual = [];
foreach (tec_post_array('libros') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $librosManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'libro'),
        'nivel_editorial' => tec_clean_string($item['nivel_editorial'] ?? 'secundaria'),
        'afinidad' => tec_clean_string($item['afinidad'] ?? 'relacionada'),
        'posicion_autor' => tec_clean_string($item['posicion_autor'] ?? 'intermedio'),
        'es_libro_investigacion' => tec_normalizar_bool($item['es_libro_investigacion'] ?? '1'),
        'es_autoedicion' => tec_normalizar_bool($item['es_autoedicion'] ?? '0'),
        'es_acta_congreso' => tec_normalizar_bool($item['es_acta_congreso'] ?? '0'),
        'es_labor_edicion' => tec_normalizar_bool($item['es_labor_edicion'] ?? '0'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['libros'], $librosManual);

/* =========================================================
 * 1C PROYECTOS Y CONTRATOS
 * ========================================================= */
$proyectosManual = [];
foreach (tec_post_array('proyectos') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $proyectosManual[] = [
        'tipo_proyecto' => tec_clean_string($item['tipo_proyecto'] ?? 'nacional'),
        'rol' => tec_clean_string($item['rol'] ?? 'investigador'),
        'dedicacion' => tec_clean_string($item['dedicacion'] ?? 'parcial'),
        'anios_duracion' => tec_clean_float($item['anios_duracion'] ?? 0, 0),
        'esta_certificado' => tec_normalizar_bool($item['esta_certificado'] ?? '1'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['proyectos'], $proyectosManual);

/* =========================================================
 * 1D TRANSFERENCIA
 * ========================================================= */
$transferenciaManual = [];
foreach (tec_post_array('transferencia') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $transferenciaManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'otro'),
        'impacto_externo' => tec_normalizar_bool($item['impacto_externo'] ?? '0'),
        'liderazgo' => tec_normalizar_bool($item['liderazgo'] ?? '0'),
        'participacion_menor' => tec_normalizar_bool($item['participacion_menor'] ?? '0'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['transferencia'], $transferenciaManual);

/* =========================================================
 * 1E TESIS
 * ========================================================= */
$tesisManual = [];
foreach (tec_post_array('tesis') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $tesisManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'direccion_principal'),
        'calidad_especial' => tec_normalizar_bool($item['calidad_especial'] ?? '0'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['tesis_dirigidas'], $tesisManual);

/* =========================================================
 * 1F CONGRESOS
 * ========================================================= */
$congresosManual = [];
foreach (tec_post_array('congresos') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $congresosManual[] = [
        'ambito' => tec_clean_string($item['ambito'] ?? 'nacional'),
        'tipo' => tec_clean_string($item['tipo'] ?? 'comunicacion_oral'),
        'id_evento' => tec_clean_string($item['id_evento'] ?? ''),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['congresos'], $congresosManual);

/* =========================================================
 * 1G OTROS MÉRITOS INVESTIGACIÓN
 * ========================================================= */
$otrosInvestigacionManual = [];
foreach (tec_post_array('otros_investigacion') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $otrosInvestigacionManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'otro'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_1']['otros_meritos_investigacion'], $otrosInvestigacionManual);

/* =========================================================
 * 2A DOCENCIA
 * ========================================================= */
$docenciaManual = [];
foreach (tec_post_array('docencia') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $docenciaManual[] = [
        'horas' => tec_clean_int($item['horas'] ?? 0, 0),
        'nivel' => tec_clean_string($item['nivel'] ?? 'grado'),
        'responsabilidad' => tec_clean_string($item['responsabilidad'] ?? 'media'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_2']['docencia_universitaria'], $docenciaManual);

/* =========================================================
 * 2B EVALUACIÓN DOCENTE
 * ========================================================= */
$evalDocenteManual = [];
foreach (tec_post_array('eval_docente') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $evalDocenteManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'otro'),
        'resultado' => tec_clean_string($item['resultado'] ?? 'favorable'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_2']['evaluacion_docente'], $evalDocenteManual);

/* =========================================================
 * 2C FORMACIÓN DOCENTE
 * ========================================================= */
$formDocenteManual = [];
foreach (tec_post_array('form_docente') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $formDocenteManual[] = [
        'horas' => tec_clean_int($item['horas'] ?? 0, 0),
        'rol' => tec_clean_string($item['rol'] ?? 'asistente'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_2']['formacion_docente'], $formDocenteManual);

/* =========================================================
 * 2D MATERIAL DOCENTE / EEES
 * ========================================================= */
$materialDocenteManual = [];
foreach (tec_post_array('material_docente') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $materialDocenteManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'material_publicado'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_2']['material_docente'], $materialDocenteManual);

/* =========================================================
 * 3A FORMACIÓN ACADÉMICA
 * ========================================================= */
$formacionManual = [];
foreach (tec_post_array('formacion') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $formacionManual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'otro'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_3']['formacion_academica'], $formacionManual);

/* =========================================================
 * 3B EXPERIENCIA PROFESIONAL
 * ========================================================= */
$experienciaManual = [];
foreach (tec_post_array('experiencia') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $experienciaManual[] = [
        'anios' => tec_clean_float($item['anios'] ?? 0, 0),
        'relacion' => tec_clean_string($item['relacion'] ?? 'media'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_3']['experiencia_profesional'], $experienciaManual);

/* =========================================================
 * 4 OTROS MÉRITOS
 * ========================================================= */
$bloque4Manual = [];
foreach (tec_post_array('bloque4') as $item) {
    if (!is_array($item)) {
        continue;
    }

    $bloque4Manual[] = [
        'tipo' => tec_clean_string($item['tipo'] ?? 'otro'),
        'es_valido' => tec_normalizar_bool($item['es_valido'] ?? '1'),
    ];
}
tec_append_items($jsonBase['bloque_4'], $bloque4Manual);

/* =========================================================
 * EVALUAR
 * ========================================================= */
$resultado = evaluar_expediente($jsonBase);

// Normalización de claves para asegurar que el INSERT tenga datos correctos
$b1Val = (float)($resultado['bloque_1']['B1'] ?? $resultado['bloque_1_total'] ?? $resultado['bloque_1_flat'] ?? 0);
$b2Val = (float)($resultado['bloque_2']['B2'] ?? $resultado['bloque_2_total'] ?? $resultado['bloque_2_flat'] ?? 0);
$b3Val = (float)($resultado['bloque_3']['B3'] ?? $resultado['bloque_3_total'] ?? $resultado['bloque_3_flat'] ?? 0);
$b4Val = (float)($resultado['bloque_4']['B4'] ?? $resultado['bloque_4_total'] ?? $resultado['bloque_4_flat'] ?? 0);

$jsonBase['resultado_calculo'] = [
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

$jsonFinal = json_encode($jsonBase, JSON_UNESCAPED_UNICODE);

if ($jsonFinal === false) {
    die('No se pudo serializar el JSON final.');
}

/* =========================================================
 * GUARDAR SEGÚN SCHEMA REAL
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



$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':nombre_candidato' => $nombreCandidato,
    ':area' => 'Técnicas',
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

$id = (int)$pdo->lastInsertId();
header('Location: ver_evaluacion.php?id=' . $id);
exit;