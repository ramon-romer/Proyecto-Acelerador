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

exp_render_layout_start(
    'Expediente extraído',
    'Revisa el resumen de extracción y elige si quieres guardar la evaluación directamente o completar datos manuales antes de recalcular.',
    [
        ['label' => 'Portal ANECA', 'url' => exp_portal_url()],
        ['label' => 'Experimentales', 'url' => exp_index_url()],
        ['label' => 'Expediente extraído'],
    ],
    [
        ['label' => 'Volver a Experimentales', 'url' => exp_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => exp_portal_url(), 'class' => 'light'],
    ]
);
?>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= exp_h($nombre_candidato) ?></span></div>
        <div class="metric"><span class="label">Archivo</span><span class="value" style="font-size:18px"><?= exp_h(basename($rutaPdf)) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Experimentales</span></div>
    </div>

    <div class="stats-grid">
        <div class="metric"><span class="label">1 Investigación</span><span class="value"><?= exp_h((string)(count($jsonExtraido['bloque_1'] ?? []))) ?></span></div>
        <div class="metric"><span class="label">2 Docencia</span><span class="value"><?= exp_h((string)(count($jsonExtraido['bloque_2'] ?? []))) ?></span></div>
        <div class="metric"><span class="label">3 Formación/experiencia</span><span class="value"><?= exp_h((string)(count($jsonExtraido['bloque_3'] ?? []))) ?></span></div>
        <div class="metric"><span class="label">4 Otros méritos</span><span class="value"><?= exp_h((string)(count($jsonExtraido['bloque_4'] ?? []))) ?></span></div>
    </div>
</section>

<section class="card stack">
    <div>
        <h2>Siguiente paso</h2>
        <p class="muted">Para pruebas, el JSON técnico sigue disponible, pero ya queda apartado en un bloque plegable para no romper la pantalla.</p>
    </div>

    <div class="toolbar">
        <form action="guardar_evaluacion.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= exp_h($nombre_candidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= exp_h($jsonPlano) ?></textarea>
            <button type="submit">Evaluar directamente</button>
        </form>

        <form action="completar_datos.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= exp_h($nombre_candidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= exp_h($jsonPlano) ?></textarea>
            <button type="submit" class="secondary">Completar datos manualmente</button>
        </form>
    </div>
</section>

<section class="card">
    <details>
        <summary>Ver JSON extraído</summary>
        <pre><?= exp_h($jsonPlano) ?></pre>
    </details>
</section>
<?php exp_render_layout_end(); ?>
