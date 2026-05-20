<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_humanidades.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no permitido.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$orcidSesion = trim((string)($_SESSION['orcid_usuario'] ?? ''));

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

if ($orcidSesion !== '') {
    $jsonEntrada['orcid_candidato'] = $orcidSesion;
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

// Normalización de claves para asegurar que el INSERT tenga datos correctos
$b1Val = (float)($resultado['bloque_1']['B1'] ?? $resultado['bloque_1_total'] ?? $resultado['bloque_1_flat'] ?? $resultado['bloque_1'] ?? 0);
$b2Val = (float)($resultado['bloque_2']['B2'] ?? $resultado['bloque_2_total'] ?? $resultado['bloque_2_flat'] ?? $resultado['bloque_2'] ?? 0);
$b3Val = (float)($resultado['bloque_3']['B3'] ?? $resultado['bloque_3_total'] ?? $resultado['bloque_3_flat'] ?? $resultado['bloque_3'] ?? 0);
$b4Val = (float)($resultado['bloque_4']['B4'] ?? $resultado['bloque_4_total'] ?? $resultado['bloque_4_flat'] ?? $resultado['bloque_4'] ?? 0);

$jsonEntrada['resultado_calculo'] = [
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

$jsonNormalizado = json_encode($jsonEntrada, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($jsonNormalizado === false) {
    die('No se pudo codificar el JSON de entrada.');
}

/* =========================================================
 * Guardar en base de datos
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
    ':area' => 'Humanidades',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $jsonNormalizado,

    ':puntuacion_1a' => $resultado['puntuacion_1a'] ?? $resultado['puntuaciones']['1A'] ?? 0,
    ':puntuacion_1b' => $resultado['puntuacion_1b'] ?? $resultado['puntuaciones']['1B'] ?? 0,
    ':puntuacion_1c' => $resultado['puntuacion_1c'] ?? $resultado['puntuaciones']['1C'] ?? 0,
    ':puntuacion_1d' => $resultado['puntuacion_1d'] ?? $resultado['puntuaciones']['1D'] ?? 0,
    ':puntuacion_1e' => $resultado['puntuacion_1e'] ?? $resultado['puntuaciones']['1E'] ?? 0,
    ':puntuacion_1f' => $resultado['puntuacion_1f'] ?? $resultado['puntuaciones']['1F'] ?? 0,
    ':puntuacion_1g' => $resultado['puntuacion_1g'] ?? $resultado['puntuaciones']['1G'] ?? 0,
    ':bloque_1' => $b1Val,

    ':puntuacion_2a' => $resultado['puntuacion_2a'] ?? $resultado['puntuaciones']['2A'] ?? 0,
    ':puntuacion_2b' => $resultado['puntuacion_2b'] ?? $resultado['puntuaciones']['2B'] ?? 0,
    ':puntuacion_2c' => $resultado['puntuacion_2c'] ?? $resultado['puntuaciones']['2C'] ?? 0,
    ':puntuacion_2d' => $resultado['puntuacion_2d'] ?? $resultado['puntuaciones']['2D'] ?? 0,
    ':bloque_2' => $b2Val,

    ':puntuacion_3a' => $resultado['puntuacion_3a'] ?? $resultado['puntuaciones']['3A'] ?? 0,
    ':puntuacion_3b' => $resultado['puntuacion_3b'] ?? $resultado['puntuaciones']['3B'] ?? 0,
    ':bloque_3' => $b3Val,

    ':bloque_4' => $b4Val,

    ':total_b1_b2' => $resultado['total_b1_b2'] ?? ($b1Val + $b2Val),
    ':total_final' => $resultado['total_final'] ?? ($b1Val + $b2Val + $b3Val + $b4Val),
    ':resultado' => $resultado['resultado'] ?? $resultado['decision']['resultado'] ?? 'NEGATIVA',
    ':cumple_regla_1' => !empty($resultado['cumple_regla_1']) ? 1 : 0,
    ':cumple_regla_2' => !empty($resultado['cumple_regla_2']) ? 1 : 0,
]);

$id = (int)$pdo->lastInsertId();

header('Location: ver_evaluacion.php?id=' . $id);
exit;