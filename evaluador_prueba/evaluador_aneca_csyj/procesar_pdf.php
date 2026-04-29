<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

@ini_set('max_execution_time', '600');
@set_time_limit(600);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/../src/AnecaExtractor.php';
require_once __DIR__ . '/../src/FecytCvnExtractor.php';
require_once __DIR__ . '/../src/Pipeline.php';
require __DIR__ . '/ui.php';

$nombreCandidato = trim($_POST['nombre_candidato'] ?? '');
$formatoCv = trim((string)($_POST['formato_cv'] ?? 'aneca'));
$formatoCvNormalizado = strtolower(str_replace(['-', ' '], '_', $formatoCv));
$esCvnFecyt = in_array($formatoCvNormalizado, ['cvn_fecyt', 'fecyt_cvn', 'fecyt', 'cvn'], true)
    || isset($_POST['procesar_cvn_fecyt'])
    || isset($_POST['btn_cvn_fecyt']);

if ($nombreCandidato === '') {
    die('Falta el nombre del candidato.');
}

if (!isset($_FILES['pdf_cv']) || $_FILES['pdf_cv']['error'] !== UPLOAD_ERR_OK) {
    die('No se ha subido el PDF correctamente.');
}

$dirPdfs = __DIR__ . '/../storage/pdfs/';
if (!is_dir($dirPdfs) && !mkdir($dirPdfs, 0777, true) && !is_dir($dirPdfs)) {
    die('No se pudo crear la carpeta storage/pdfs.');
}

$orcidSesion = trim((string)($_SESSION['orcid_usuario'] ?? ''));
$ramaSesion = trim((string)($_SESSION['rama_usuario'] ?? ''));

if ($orcidSesion === '' || $ramaSesion === '') {
    die('Faltan ORCID o rama en la sesión del usuario.');
}

$orcidLimpio = preg_replace('/[^0-9Xx-]/', '', $orcidSesion);
$ramaLimpia = preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper($ramaSesion));
$fecha = date('Y-m-d_His');
$nombrePdf = $orcidLimpio . '_' . $ramaLimpia . '_' . $fecha . '.pdf';
$rutaPdf = $dirPdfs . $nombrePdf;

if (!move_uploaded_file($_FILES['pdf_cv']['tmp_name'], $rutaPdf)) {
    die('No se pudo guardar el PDF.');
}

$extractor = $esCvnFecyt ? new FecytCvnExtractor() : new AnecaExtractor();
$etiquetaFormato = $esCvnFecyt ? 'CVN (FECYT)' : 'PDF estándar';
$formatoCv = $esCvnFecyt ? 'cvn_fecyt' : 'aneca';

$pipeline = new Pipeline($extractor);
$jsonExtraido = $pipeline->procesar($rutaPdf);

if (!is_array($jsonExtraido)) {
    die('El pipeline no devolvió un array válido.');
}

$jsonExtraido['nombre_candidato'] = $nombreCandidato;
$jsonExtraido['area'] = 'Ciencias Sociales y Jurídicas';
$jsonExtraido['categoria'] = 'PCD/PUP';
$jsonExtraido['formato_cv'] = $formatoCv;
$jsonExtraido['formato_cv_label'] = $etiquetaFormato;

foreach (['bloque_1', 'bloque_2', 'bloque_3'] as $bloqueKey) {
    if (!isset($jsonExtraido[$bloqueKey]) || !is_array($jsonExtraido[$bloqueKey])) {
        $jsonExtraido[$bloqueKey] = [];
    }
}
if (!isset($jsonExtraido['bloque_4']) || !is_array($jsonExtraido['bloque_4'])) {
    $jsonExtraido['bloque_4'] = [];
}

$jsonExtraido['bloque_1']['publicaciones'] = is_array($jsonExtraido['bloque_1']['publicaciones'] ?? null) ? $jsonExtraido['bloque_1']['publicaciones'] : [];
$jsonExtraido['bloque_1']['libros'] = is_array($jsonExtraido['bloque_1']['libros'] ?? null) ? $jsonExtraido['bloque_1']['libros'] : [];
$jsonExtraido['bloque_1']['proyectos'] = is_array($jsonExtraido['bloque_1']['proyectos'] ?? null) ? $jsonExtraido['bloque_1']['proyectos'] : [];
$jsonExtraido['bloque_1']['transferencia'] = is_array($jsonExtraido['bloque_1']['transferencia'] ?? null) ? $jsonExtraido['bloque_1']['transferencia'] : [];
$jsonExtraido['bloque_1']['tesis_dirigidas'] = is_array($jsonExtraido['bloque_1']['tesis_dirigidas'] ?? null) ? $jsonExtraido['bloque_1']['tesis_dirigidas'] : [];
$jsonExtraido['bloque_1']['congresos'] = is_array($jsonExtraido['bloque_1']['congresos'] ?? null) ? $jsonExtraido['bloque_1']['congresos'] : [];
$jsonExtraido['bloque_1']['otros_meritos_investigacion'] = is_array($jsonExtraido['bloque_1']['otros_meritos_investigacion'] ?? null) ? $jsonExtraido['bloque_1']['otros_meritos_investigacion'] : [];

$jsonExtraido['bloque_2']['docencia_universitaria'] = is_array($jsonExtraido['bloque_2']['docencia_universitaria'] ?? null) ? $jsonExtraido['bloque_2']['docencia_universitaria'] : [];
$jsonExtraido['bloque_2']['evaluacion_docente'] = is_array($jsonExtraido['bloque_2']['evaluacion_docente'] ?? null) ? $jsonExtraido['bloque_2']['evaluacion_docente'] : [];
$jsonExtraido['bloque_2']['formacion_docente'] = is_array($jsonExtraido['bloque_2']['formacion_docente'] ?? null) ? $jsonExtraido['bloque_2']['formacion_docente'] : [];
$jsonExtraido['bloque_2']['material_docente'] = is_array($jsonExtraido['bloque_2']['material_docente'] ?? null) ? $jsonExtraido['bloque_2']['material_docente'] : [];

$jsonExtraido['bloque_3']['formacion_academica'] = is_array($jsonExtraido['bloque_3']['formacion_academica'] ?? null) ? $jsonExtraido['bloque_3']['formacion_academica'] : [];
$jsonExtraido['bloque_3']['experiencia_profesional'] = is_array($jsonExtraido['bloque_3']['experiencia_profesional'] ?? null) ? $jsonExtraido['bloque_3']['experiencia_profesional'] : [];

$jsonPlano = json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($jsonPlano === false) {
    die('No se pudo convertir el JSON extraído.');
}

$resumen = [
    '1A Publicaciones científicas' => count($jsonExtraido['bloque_1']['publicaciones']),
    '1B Libros y capítulos' => count($jsonExtraido['bloque_1']['libros']),
    '1C Proyectos de investigación' => count($jsonExtraido['bloque_1']['proyectos']),
    '1D Transferencia' => count($jsonExtraido['bloque_1']['transferencia']),
    '1E Dirección de tesis' => count($jsonExtraido['bloque_1']['tesis_dirigidas']),
    '1F Congresos' => count($jsonExtraido['bloque_1']['congresos']),
    '1G Otros méritos de investigación' => count($jsonExtraido['bloque_1']['otros_meritos_investigacion']),
    '2A Docencia universitaria' => count($jsonExtraido['bloque_2']['docencia_universitaria']),
    '2B Evaluación docente' => count($jsonExtraido['bloque_2']['evaluacion_docente']),
    '2C Formación docente' => count($jsonExtraido['bloque_2']['formacion_docente']),
    '2D Material docente' => count($jsonExtraido['bloque_2']['material_docente']),
    '3A Formación académica' => count($jsonExtraido['bloque_3']['formacion_academica']),
    '3B Experiencia profesional' => count($jsonExtraido['bloque_3']['experiencia_profesional']),
    '4 Otros méritos' => count($jsonExtraido['bloque_4']),
];

csyj_render_layout_start(
    'Expediente extraído',
    'Revisa el resumen de extracción y decide si quieres evaluar directamente o completar manualmente los apartados antes de recalcular.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSyJ', 'url' => csyj_index_url()],
        ['label' => 'Expediente extraído'],
    ],
    [
        ['label' => 'Volver a CSyJ', 'url' => csyj_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => csyj_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .resumen-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
    .resumen-item { border: 1px solid #dbe4ee; border-radius: 12px; padding: 14px; background: #fff; }
    .resumen-item .k { display: block; color: #64748b; font-size: 12px; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; }
    .resumen-item .v { display: block; font-size: 24px; font-weight: 800; color: #0f172a; }
    .subinfo { color: #64748b; font-size: 13px; }
</style>

<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= csyj_h($nombreCandidato) ?></span></div>
        <div class="metric"><span class="label">Archivo</span><span class="value" style="font-size:18px"><?= csyj_h(basename($rutaPdf)) ?></span></div>
        <div class="metric"><span class="label">Formato</span><span class="value" style="font-size:20px"><?= csyj_h($etiquetaFormato) ?></span></div>
        <div class="metric"><span class="label">Categoría</span><span class="value" style="font-size:20px">PCD/PUP</span></div>
    </div>
</section>

<section class="card stack">
    <div>
        <h2 style="margin:0 0 6px;">Resumen de extracción</h2>
        <p class="muted" style="margin:0;">Conteo rápido de elementos detectados por el pipeline antes de evaluar o completar manualmente.</p>
    </div>

    <div class="resumen-grid">
        <?php foreach ($resumen as $label => $valor): ?>
            <div class="resumen-item">
                <span class="k"><?= csyj_h($label) ?></span>
                <span class="v"><?= csyj_h((string)$valor) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="subinfo">
        Referencia de máximos en CSyJ PCD/PUP: B1=60, B2=30, B3=8, B4=2.<br>
        Evaluación positiva si 1+2 ≥ 50 y total ≥ 55.
    </div>
</section>

<section class="card stack">
    <div>
        <h2 style="margin:0 0 6px;">Siguiente paso</h2>
        <p class="muted" style="margin:0;">Puedes guardar ya la evaluación con lo extraído del PDF o pasar antes por el formulario ampliado para completar apartados y corregir datos.</p>
    </div>

    <div class="toolbar">
        <form action="guardar_evaluacion.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= csyj_h($nombreCandidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= csyj_h($jsonPlano) ?></textarea>
            <button type="submit">Evaluar directamente</button>
        </form>

        <form action="completar_datos.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= csyj_h($nombreCandidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= csyj_h($jsonPlano) ?></textarea>
            <button type="submit" class="secondary">Completar datos manualmente</button>
        </form>
    </div>
</section>

<section class="card">
    <details>
        <summary>Ver JSON extraído</summary>
        <pre><?= csyj_h($jsonPlano) ?></pre>
    </details>
</section>

<?php csyj_render_layout_end(); ?>
