<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Pipeline.php';
require __DIR__ . '/ui.php';

$nombre_candidato = trim($_POST['nombre_candidato'] ?? '');

if ($nombre_candidato === '') {
    die('Falta el nombre del candidato.');
}

if (!isset($_FILES['pdf_cv']) || $_FILES['pdf_cv']['error'] !== UPLOAD_ERR_OK) {
    die('No se ha subido el PDF correctamente.');
}

$dirPdfs = __DIR__ . '/../storage/pdfs/';

if (!is_dir($dirPdfs) && !mkdir($dirPdfs, 0777, true) && !is_dir($dirPdfs)) {
    die('No se pudo crear la carpeta storage/pdfs.');
}

$token = uniqid('exp_', false);
$rutaPdf = $dirPdfs . $token . '.pdf';

if (!move_uploaded_file($_FILES['pdf_cv']['tmp_name'], $rutaPdf)) {
    die('No se pudo guardar el PDF.');
}

$pipeline = new Pipeline();
$jsonExtraido = $pipeline->procesar($rutaPdf);

if (!is_array($jsonExtraido)) {
    die('El pipeline no devolvió un array válido.');
}

$jsonPlano = json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if ($jsonPlano === false) {
    die('No se pudo convertir el JSON extraído.');
}

$contadores = [
    'publicaciones' => count($jsonExtraido['bloque_1']['publicaciones'] ?? []),
    'libros' => count($jsonExtraido['bloque_1']['libros'] ?? []),
    'proyectos' => count($jsonExtraido['bloque_1']['proyectos'] ?? []),
    'docencia' => count($jsonExtraido['bloque_2']['docencia_universitaria'] ?? []),
    'formacion' => count($jsonExtraido['bloque_3']['formacion_academica'] ?? []),
    'experiencia' => count($jsonExtraido['bloque_3']['experiencia_profesional'] ?? []),
    'bloque4' => count($jsonExtraido['bloque_4'] ?? []),
];

hum_render_layout_start(
    'Expediente extraído',
    'Revisa el resumen de extracción y elige si quieres guardar la evaluación directamente o completar datos manuales antes de recalcular.',
    [
        ['label' => 'Portal ANECA', 'url' => hum_portal_url()],
        ['label' => 'Humanidades', 'url' => hum_index_url()],
        ['label' => 'Expediente extraído'],
    ],
    [
        ['label' => 'Volver a Humanidades', 'url' => hum_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => hum_portal_url(), 'class' => 'light'],
    ]
);
?>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= h($nombre_candidato) ?></span></div>
        <div class="metric"><span class="label">Archivo</span><span class="value" style="font-size:18px"><?= h(basename($rutaPdf)) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Humanidades</span></div>
    </div>

    <div class="stats-grid">
        <div class="metric"><span class="label">Publicaciones</span><span class="value"><?= h((string)$contadores['publicaciones']) ?></span></div>
        <div class="metric"><span class="label">Libros</span><span class="value"><?= h((string)$contadores['libros']) ?></span></div>
        <div class="metric"><span class="label">Proyectos</span><span class="value"><?= h((string)$contadores['proyectos']) ?></span></div>
        <div class="metric"><span class="label">Docencia</span><span class="value"><?= h((string)$contadores['docencia']) ?></span></div>
        <div class="metric"><span class="label">Formación</span><span class="value"><?= h((string)$contadores['formacion']) ?></span></div>
        <div class="metric"><span class="label">Experiencia</span><span class="value"><?= h((string)$contadores['experiencia']) ?></span></div>
    </div>
</section>

<section class="card stack">
    <div>
        <h2>Siguiente paso</h2>
        <p class="muted">Para pruebas, he dejado visible el JSON técnico, pero ya queda apartado en un bloque plegable para que no rompa la pantalla.</p>
    </div>

    <div class="toolbar">
        <form action="guardar_evaluacion.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= h($nombre_candidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= h($jsonPlano) ?></textarea>
            <button type="submit">Evaluar directamente</button>
        </form>

        <form action="completar_datos.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= h($nombre_candidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= h($jsonPlano) ?></textarea>
            <button type="submit" class="secondary">Completar datos manualmente</button>
        </form>
    </div>
</section>

<section class="card">
    <details>
        <summary>Ver JSON extraído</summary>
        <pre><?= h($jsonPlano) ?></pre>
    </details>
</section>
<?php hum_render_layout_end(); ?>
