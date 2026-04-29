<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_salud.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Acceso no permitido.');
}

$nombre = trim($_POST['nombre_candidato'] ?? '');
$jsonBaseTexto = trim($_POST['json_entrada_base'] ?? '');

if ($nombre === '' || $jsonBaseTexto === '') {
    die('Faltan datos obligatorios.');
}

$jsonBase = json_decode($jsonBaseTexto, true);
if (!is_array($jsonBase)) {
    die('El JSON base no es válido.');
}

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
    if (!isset($jsonBase['bloque_1'][$key]) || !is_array($jsonBase['bloque_1'][$key])) {
        $jsonBase['bloque_1'][$key] = [];
    }
}
foreach ($defaultsBloque2 as $key) {
    if (!isset($jsonBase['bloque_2'][$key]) || !is_array($jsonBase['bloque_2'][$key])) {
        $jsonBase['bloque_2'][$key] = [];
    }
}
foreach ($defaultsBloque3 as $key) {
    if (!isset($jsonBase['bloque_3'][$key]) || !is_array($jsonBase['bloque_3'][$key])) {
        $jsonBase['bloque_3'][$key] = [];
    }
}

function normalizar_lista_post_salud(mixed $lista): array
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
        foreach ($item as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $limpio[(string)$k] = is_string($v) ? trim($v) : $v;
        }

        $vacios = true;
        foreach ($limpio as $valor) {
            if ($valor !== '' && $valor !== null) {
                $vacios = false;
                break;
            }
        }

        if ($vacios) {
            continue;
        }

        if (!isset($limpio['es_valido']) && !isset($limpio['es_valida'])) {
            $limpio['es_valido'] = 1;
        }

        $resultado[] = $limpio;
    }

    return $resultado;
}

function fusionar_listas_salud(array $base, array $nuevos): array
{
    if ($nuevos === []) {
        return $base;
    }
    return array_values(array_merge($base, $nuevos));
}

$publicaciones = normalizar_lista_post_salud($_POST['publicaciones'] ?? []);
$libros = normalizar_lista_post_salud($_POST['libros'] ?? []);
$proyectos = normalizar_lista_post_salud($_POST['proyectos'] ?? []);
$transferencia = normalizar_lista_post_salud($_POST['transferencia'] ?? []);
$tesisDirigidas = normalizar_lista_post_salud($_POST['tesis_dirigidas'] ?? []);
$congresos = normalizar_lista_post_salud($_POST['congresos'] ?? []);
$otrosInv = normalizar_lista_post_salud($_POST['otros_meritos_investigacion'] ?? []);

$docencia = normalizar_lista_post_salud($_POST['docencia'] ?? []);
$evaluacionDocente = normalizar_lista_post_salud($_POST['evaluacion_docente'] ?? []);
$formacionDocente = normalizar_lista_post_salud($_POST['formacion_docente'] ?? []);
$materialDocente = normalizar_lista_post_salud($_POST['material_docente'] ?? []);

$formacion = normalizar_lista_post_salud($_POST['formacion'] ?? []);
$experiencia = normalizar_lista_post_salud($_POST['experiencia'] ?? []);

$bloque4 = normalizar_lista_post_salud($_POST['bloque4'] ?? []);

$jsonBase['bloque_1']['publicaciones'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['publicaciones'],
    $publicaciones
);

$jsonBase['bloque_1']['libros'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['libros'],
    $libros
);

$jsonBase['bloque_1']['proyectos'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['proyectos'],
    $proyectos
);

$jsonBase['bloque_1']['transferencia'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['transferencia'],
    $transferencia
);

$jsonBase['bloque_1']['tesis_dirigidas'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['tesis_dirigidas'],
    $tesisDirigidas
);

$jsonBase['bloque_1']['congresos'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['congresos'],
    $congresos
);

$jsonBase['bloque_1']['otros_meritos_investigacion'] = fusionar_listas_salud(
    $jsonBase['bloque_1']['otros_meritos_investigacion'],
    $otrosInv
);

$jsonBase['bloque_2']['docencia_universitaria'] = fusionar_listas_salud(
    $jsonBase['bloque_2']['docencia_universitaria'],
    $docencia
);

$jsonBase['bloque_2']['evaluacion_docente'] = fusionar_listas_salud(
    $jsonBase['bloque_2']['evaluacion_docente'],
    $evaluacionDocente
);

$jsonBase['bloque_2']['formacion_docente'] = fusionar_listas_salud(
    $jsonBase['bloque_2']['formacion_docente'],
    $formacionDocente
);

$jsonBase['bloque_2']['material_docente'] = fusionar_listas_salud(
    $jsonBase['bloque_2']['material_docente'],
    $materialDocente
);

$jsonBase['bloque_3']['formacion_academica'] = fusionar_listas_salud(
    $jsonBase['bloque_3']['formacion_academica'],
    $formacion
);

$jsonBase['bloque_3']['experiencia_profesional'] = fusionar_listas_salud(
    $jsonBase['bloque_3']['experiencia_profesional'],
    $experiencia
);

$jsonBase['bloque_4'] = fusionar_listas_salud(
    $jsonBase['bloque_4'],
    $bloque4
);

$resultado = evaluar_expediente($jsonBase);
$jsonFinal = json_encode($jsonBase, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonFinal === false) {
    die('No se pudo codificar el JSON final.');
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
    ':area' => 'Salud',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $jsonFinal,

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