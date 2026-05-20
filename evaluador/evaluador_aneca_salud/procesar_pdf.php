<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../src/Pipeline.php';
require_once __DIR__ . '/../src/FecytCvnExtractor.php';
require __DIR__ . '/ui.php';

$nombreCandidato = trim($_POST['nombre_candidato'] ?? '');
$formatoCv = trim((string)($_POST['formato_cv'] ?? 'aneca'));

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
$ramaSesion  = trim((string)($_SESSION['rama_usuario'] ?? ''));

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

$extractor = null;
$etiquetaFormato = 'PDF estándar';
if ($formatoCv === 'cvn_fecyt') {
    $extractor = new FecytCvnExtractor();
    $etiquetaFormato = 'CVN (FECYT)';
}

$pipeline = new Pipeline($extractor);
$jsonExtraido = $pipeline->procesar($rutaPdf);

if (!is_array($jsonExtraido)) {
    die('El pipeline no devolvió un array válido.');
}

$jsonExtraido['nombre_candidato'] = $nombreCandidato;
$jsonExtraido['orcid_candidato'] = $orcidSesion;
$jsonExtraido['area'] = 'Salud';
$jsonExtraido['categoria'] = 'PCD/PUP';
$jsonExtraido['formato_cv'] = $formatoCv;
$jsonExtraido['formato_cv_label'] = $etiquetaFormato;

$jsonPlano = json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

$resumen = [
    '1A Publicaciones' => count($jsonExtraido['bloque_1']['publicaciones'] ?? []),
    '1B Libros' => count($jsonExtraido['bloque_1']['libros'] ?? []),
    '1C Proyectos' => count($jsonExtraido['bloque_1']['proyectos'] ?? []),
    '1D Transferencia' => count($jsonExtraido['bloque_1']['transferencia'] ?? []),
    '2A Docencia' => count($jsonExtraido['bloque_2']['docencia_universitaria'] ?? []),
    '3A Formación' => count($jsonExtraido['bloque_3']['formacion_academica'] ?? []),
];

salud_render_layout_start(
    'Expediente extraído',
    'Revisa el resumen de extracción y elige si quieres guardar la evaluación directamente o completar datos manuales antes de recalcular.',
    [
        ['label' => 'Portal ANECA', 'url' => salud_portal_url()],
        ['label' => 'Salud', 'url' => salud_index_url()],
        ['label' => 'Expediente extraído'],
    ],
    [
        ['label' => 'Volver a Salud', 'url' => salud_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => salud_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .resumen-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .resumen-item {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 18px;
        padding: 18px;
        background: rgba(255, 255, 255, 0.03);
        transition: background 0.2s, transform 0.2s;
    }
    .resumen-item:hover {
        background: rgba(255, 255, 255, 0.06);
        transform: translateY(-2px);
    }
    .resumen-item .k {
        display: block;
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .resumen-item .v {
        display: block;
        font-size: 28px;
        font-weight: 800;
        color: #fff;
    }
    .subinfo {
        color: rgba(255, 255, 255, 0.4);
        font-size: 13px;
        background: rgba(255, 255, 255, 0.02);
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
</style>

<section class="card stack">
    <div class="meta-grid">
        <div class="metric">
            <span class="label">Candidato</span>
            <span class="value" style="font-size:20px"><?= salud_h($nombreCandidato) ?></span>
        </div>
        <div class="metric">
            <span class="label">Archivo</span>
            <span class="value" style="font-size:18px"><?= salud_h(basename($rutaPdf)) ?></span>
        </div>
        <div class="metric">
            <span class="label">Área</span>
            <span class="value" style="font-size:20px">Salud</span>
        </div>
        <div class="metric">
            <span class="label">Categoría</span>
            <span class="value" style="font-size:20px">PCD/PUP</span>
        </div>
        <div class="metric">
            <span class="label">Formato</span>
            <span class="value" style="font-size:20px"><?= salud_h($etiquetaFormato) ?></span>
        </div>
    </div>
</section>

<section class="card stack">
    <div>
        <h2 style="margin:0 0 6px;">Resumen de extracción</h2>
        <p class="muted" style="margin:0;">
            Conteo rápido de elementos detectados por el pipeline antes de evaluar o completar manualmente.
        </p>
    </div>

    <div class="resumen-grid">
        <?php foreach ($resumen as $label => $valor): ?>
            <div class="resumen-item">
                <span class="k"><?= salud_h($label) ?></span>
                <span class="v"><?= salud_h((string)$valor) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="subinfo">
        Referencia de máximos en Salud: B1=60, B2=30, B3=8, B4=2.  
        Regla positiva: 1+2 ≥ 50 y total ≥ 55.
    </div>
</section>

<section class="card stack">
    <div>
        <h2 style="margin:0 0 6px;">Siguiente paso</h2>
        <p class="muted" style="margin:0;">
            Puedes guardar ya la evaluación con lo extraído del PDF o pasar antes por el formulario ampliado para completar apartados y corregir datos.
        </p>
    </div>

    <div class="toolbar">
        <form action="guardar_evaluacion.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= salud_h($nombreCandidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= salud_h($jsonPlano) ?></textarea>
            <button type="submit">Evaluar directamente</button>
        </form>

        <form action="completar_datos.php" method="post">
            <input type="hidden" name="nombre_candidato" value="<?= salud_h($nombreCandidato) ?>">
            <textarea name="json_entrada" style="display:none;"><?= salud_h($jsonPlano) ?></textarea>
            <button type="submit" class="secondary">Completar datos manualmente</button>
        </form>
    </div>
</section>

<section class="card">
    <details>
        <summary>Ver JSON extraído</summary>
        <pre><?= salud_h($jsonPlano) ?></pre>
    </details>
</section>

<?php salud_render_layout_end(); ?>
