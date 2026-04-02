<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_experimentales.php';
require __DIR__ . '/ui.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { exit('ID no válido.'); }

$sql = "SELECT * FROM evaluaciones WHERE id = :id LIMIT 1";
$sentencia = $pdo->prepare($sql);
$sentencia->execute([':id' => $id]);
$evaluacion = $sentencia->fetch(PDO::FETCH_ASSOC);
if (!$evaluacion) { exit('Evaluación no encontrada.'); }

$datos = json_decode((string)$evaluacion['json_entrada'], true);
if (!is_array($datos)) { $datos = []; }

$bloque_1 = calcular_bloque_1_experimentales($datos['bloque_1'] ?? []);
$bloque_2 = calcular_bloque_2_experimentales($datos['bloque_2'] ?? []);
$bloque_3 = calcular_bloque_3_experimentales($datos['bloque_3'] ?? []);
$bloque_4 = calcular_bloque_4_experimentales($datos['bloque_4'] ?? []);
$totales = calcular_totales_experimentales($bloque_1, $bloque_2, $bloque_3, $bloque_4);
$decision = evaluar_experimentales($totales);
$area = $evaluacion['area'] ?? 'Experimentales';
$categoria = $evaluacion['categoria'] ?? 'PCD/PUP';
$fecha = $evaluacion['fecha_creacion'] ?? '';

exp_render_layout_start(
    'Evaluación #' . (string)$evaluacion['id'],
    'Detalle completo del expediente evaluado en Experimentales, con comprobación de reglas y acceso al JSON original.',
    [
        ['label' => 'Portal ANECA', 'url' => exp_portal_url()],
        ['label' => 'Experimentales', 'url' => exp_index_url()],
        ['label' => 'Listado', 'url' => exp_listado_url()],
        ['label' => 'Evaluación #' . (string)$evaluacion['id']],
    ],
    [
        ['label' => 'Volver al listado', 'url' => exp_listado_url(), 'class' => 'light'],
        ['label' => 'Nueva evaluación', 'url' => exp_index_url(), 'class' => 'light'],
    ]
);
?>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= exp_h((string)$evaluacion['nombre_candidato']) ?></span></div>
        <div class="metric"><span class="label">Resultado</span><span class="value" style="font-size:18px"><?= exp_render_result_badge((string)$decision['resultado']) ?></span></div>
        <div class="metric"><span class="label">Categoría</span><span class="value" style="font-size:18px"><?= exp_h((string)$categoria) ?></span></div>
        <div class="metric"><span class="label">Fecha</span><span class="value" style="font-size:18px"><?= exp_h((string)$fecha) ?></span></div>
    </div>
    <div class="kpis">
        <div class="kpi"><span class="label">Bloque 1</span><strong><?= exp_h((string)$bloque_1['B1']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 2</span><strong><?= exp_h((string)$bloque_2['B2']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 3</span><strong><?= exp_h((string)$bloque_3['B3']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 4</span><strong><?= exp_h((string)$bloque_4['B4']) ?></strong></div>
        <div class="kpi"><span class="label">Total B1 + B2</span><strong><?= exp_h((string)$totales['total_b1_b2']) ?></strong></div>
        <div class="kpi"><span class="label">Total final</span><strong><?= exp_h((string)$totales['total_final']) ?></strong></div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card">
            <h2>Desglose de puntuaciones</h2>
            <table>
                <tr><th>Concepto</th><th>Puntuación</th><th>Máximo</th></tr>
                <tr><td>1.A Publicaciones científicas y patentes</td><td><?= exp_h((string)$bloque_1['1A']) ?></td><td>35</td></tr>
                <tr><td>1.B Libros y capítulos de libros</td><td><?= exp_h((string)$bloque_1['1B']) ?></td><td>7</td></tr>
                <tr><td>1.C Proyectos y contratos de investigación</td><td><?= exp_h((string)$bloque_1['1C']) ?></td><td>7</td></tr>
                <tr><td>1.D Transferencia de tecnología</td><td><?= exp_h((string)$bloque_1['1D']) ?></td><td>4</td></tr>
                <tr><td>1.E Dirección de tesis doctorales</td><td><?= exp_h((string)$bloque_1['1E']) ?></td><td>4</td></tr>
                <tr><td>1.F Congresos, conferencias y seminarios</td><td><?= exp_h((string)$bloque_1['1F']) ?></td><td>2</td></tr>
                <tr><td>1.G Otros méritos de investigación</td><td><?= exp_h((string)$bloque_1['1G']) ?></td><td>1</td></tr>
                <tr><td><strong>Bloque 1</strong></td><td><strong><?= exp_h((string)$bloque_1['B1']) ?></strong></td><td>60</td></tr>
                <tr><td>2.A Docencia universitaria</td><td><?= exp_h((string)$bloque_2['2A']) ?></td><td>17</td></tr>
                <tr><td>2.B Evaluaciones sobre su calidad</td><td><?= exp_h((string)$bloque_2['2B']) ?></td><td>3</td></tr>
                <tr><td>2.C Cursos y seminarios de formación docente universitaria</td><td><?= exp_h((string)$bloque_2['2C']) ?></td><td>3</td></tr>
                <tr><td>2.D Material docente, proyectos y contribuciones al EEES</td><td><?= exp_h((string)$bloque_2['2D']) ?></td><td>7</td></tr>
                <tr><td><strong>Bloque 2</strong></td><td><strong><?= exp_h((string)$bloque_2['B2']) ?></strong></td><td>30</td></tr>
                <tr><td>3.A Tesis, becas, estancias, otros títulos</td><td><?= exp_h((string)$bloque_3['3A']) ?></td><td>6</td></tr>
                <tr><td>3.B Trabajo en empresas e instituciones</td><td><?= exp_h((string)$bloque_3['3B']) ?></td><td>2</td></tr>
                <tr><td><strong>Bloque 3</strong></td><td><strong><?= exp_h((string)$bloque_3['B3']) ?></strong></td><td>8</td></tr>
                <tr><td><strong>Bloque 4</strong></td><td><strong><?= exp_h((string)$bloque_4['B4']) ?></strong></td><td>2</td></tr>
                <tr><td><strong>Total 1 + 2</strong></td><td><strong><?= exp_h((string)$totales['total_b1_b2']) ?></strong></td><td>50 mínimo exigido</td></tr>
                <tr><td><strong>Total final</strong></td><td><strong><?= exp_h((string)$totales['total_final']) ?></strong></td><td>55 mínimo exigido</td></tr>
            </table>
        </section>
    </div>
    <aside class="stack">
        <section class="card">
            <h2>Comprobación de reglas</h2>
            <table>
                <tr><th>Regla</th><th>Estado</th></tr>
                <tr><td>1 + 2 ≥ 50</td><td><?= $decision['cumple_regla_1'] ? exp_render_result_badge('Cumple') : exp_render_result_badge('No cumple') ?></td></tr>
                <tr><td>1 + 2 + 3 + 4 ≥ 55</td><td><?= $decision['cumple_regla_2'] ? exp_render_result_badge('Cumple') : exp_render_result_badge('No cumple') ?></td></tr>
            </table>
        </section>
        <section class="card">
            <details><summary>Ver JSON de entrada</summary><pre><?= exp_h((string)$evaluacion['json_entrada']) ?></pre></details>
        </section>
    </aside>
</section>
<?php exp_render_layout_end(); ?>
