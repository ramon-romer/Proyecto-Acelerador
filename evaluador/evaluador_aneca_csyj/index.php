<?php
declare(strict_types=1);
require __DIR__ . '/ui.php';

csyj_render_layout_start(
    'CSyJ',
    'Módulo de evaluación ANECA para PCD/PUP conectado al portal principal. Desde aquí puedes cargar el PDF del candidato o revisar evaluaciones ya guardadas.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSyJ'],
    ],
    [
        ['label' => 'Volver al portal', 'url' => csyj_portal_url(), 'class' => 'light'],
        ['label' => 'Ver evaluaciones', 'url' => csyj_listado_url(), 'class' => 'light'],
    ]
);
?>
<div class="split">
    <section class="card stack">
        <div>
            <h2>Nueva evaluación</h2>
            <p class="muted">Carga el CV en PDF, deja que el extractor prepare el expediente y luego elige si quieres evaluar directamente o completar datos manualmente.</p>
        </div>

        <form action="procesar_pdf.php" method="post" enctype="multipart/form-data">
            <div class="form-grid">
                <div>
                    <label for="nombre_candidato">Nombre del candidato</label>
                    <input type="text" id="nombre_candidato" name="nombre_candidato" placeholder="Ej. María Pérez García" required>
                </div>
                <div>
                    <label for="pdf_cv">PDF del candidato</label>
                    <input type="file" id="pdf_cv" name="pdf_cv" accept=".pdf" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="formato_cv" value="aneca">Procesar PDF</button>
                <button type="submit" name="formato_cv" value="cvn_fecyt" class="secondary">Procesar CVN (FECYT)</button>
                <a class="btn outline" href="<?= csyj_h(csyj_listado_url()) ?>">Abrir listado</a>
            </div>
        </form>
    </section>

    <aside class="stack">
        <section class="card">
            <h3>Flujo del módulo</h3>
            <div class="kpis">
                <div class="kpi"><span class="label">Paso 1</span><strong>Subir PDF</strong></div>
                <div class="kpi"><span class="label">Paso 2</span><strong>Revisar extracción</strong></div>
                <div class="kpi"><span class="label">Paso 3</span><strong>Evaluar o completar</strong></div>
            </div>
        </section>

        <section class="card">
            <h3>Accesos rápidos</h3>
            <div class="toolbar">
                <a class="btn outline" href="<?= csyj_h(csyj_portal_url()) ?>">Portal principal</a>
                <a class="btn outline" href="<?= csyj_h(csyj_listado_url()) ?>">Histórico de evaluaciones</a>
            </div>
        </section>
    </aside>
</div>
<?php csyj_render_layout_end(); ?>
