<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { die('ID no válido.'); }

$sql = "SELECT * FROM evaluaciones WHERE id = :id LIMIT 1";
$sentencia = $pdo->prepare($sql);
$sentencia->execute([':id' => $id]);
$evaluacion = $sentencia->fetch();

if (!$evaluacion) { die('Evaluación no encontrada.'); }

csyj_render_layout_start(
    'Evaluación #' . (string)$evaluacion['id'],
    'Detalle completo del expediente evaluado en CSyJ, con desglose y acceso al JSON original.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSyJ', 'url' => csyj_index_url()],
        ['label' => 'Listado', 'url' => csyj_listado_url()],
        ['label' => 'Evaluación #' . (string)$evaluacion['id']],
    ],
    [
        ['label' => 'Volver al listado', 'url' => csyj_listado_url(), 'class' => 'light'],
        ['label' => 'Nueva evaluación', 'url' => csyj_index_url(), 'class' => 'light'],
    ]
);
?>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= csyj_h($evaluacion['nombre_candidato']) ?></span></div>
        <div class="metric"><span class="label">Resultado</span><span class="value" style="font-size:18px"><?= csyj_render_result_badge((string)$evaluacion['resultado']) ?></span></div>
        <div class="metric"><span class="label">Fecha</span><span class="value" style="font-size:18px"><?= csyj_h($evaluacion['fecha_creacion']) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Ciencias Sociales y Jurídicas</span></div>
    </div>
    <div class="kpis">
        <div class="kpi"><span class="label">Bloque 1</span><strong><?= csyj_h((string)$evaluacion['bloque_1']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 2</span><strong><?= csyj_h((string)$evaluacion['bloque_2']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 3</span><strong><?= csyj_h((string)$evaluacion['bloque_3']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 4</span><strong><?= csyj_h((string)$evaluacion['bloque_4']) ?></strong></div>
        <div class="kpi"><span class="label">Total B1 + B2</span><strong><?= csyj_h((string)$evaluacion['total_b1_b2']) ?></strong></div>
        <div class="kpi"><span class="label">Total final</span><strong><?= csyj_h((string)$evaluacion['total_final']) ?></strong></div>
    </div>
</section>

<section class="card">
    <h2>Desglose de puntuaciones</h2>
    <table>
        <tr><th>Concepto</th><th>Puntuación</th></tr>
        <tr><td>1.A</td><td><?= csyj_h((string)$evaluacion['puntuacion_1a']) ?></td></tr>
        <tr><td>1.B</td><td><?= csyj_h((string)$evaluacion['puntuacion_1b']) ?></td></tr>
        <tr><td>1.C</td><td><?= csyj_h((string)$evaluacion['puntuacion_1c']) ?></td></tr>
        <tr><td>1.D</td><td><?= csyj_h((string)$evaluacion['puntuacion_1d']) ?></td></tr>
        <tr><td>1.E</td><td><?= csyj_h((string)$evaluacion['puntuacion_1e']) ?></td></tr>
        <tr><td>1.F</td><td><?= csyj_h((string)$evaluacion['puntuacion_1f']) ?></td></tr>
        <tr><td>1.G</td><td><?= csyj_h((string)$evaluacion['puntuacion_1g']) ?></td></tr>
        <tr><td><strong>Bloque 1</strong></td><td><strong><?= csyj_h((string)$evaluacion['bloque_1']) ?></strong></td></tr>
        <tr><td>2.A</td><td><?= csyj_h((string)$evaluacion['puntuacion_2a']) ?></td></tr>
        <tr><td>2.B</td><td><?= csyj_h((string)$evaluacion['puntuacion_2b']) ?></td></tr>
        <tr><td>2.C</td><td><?= csyj_h((string)$evaluacion['puntuacion_2c']) ?></td></tr>
        <tr><td>2.D</td><td><?= csyj_h((string)$evaluacion['puntuacion_2d']) ?></td></tr>
        <tr><td><strong>Bloque 2</strong></td><td><strong><?= csyj_h((string)$evaluacion['bloque_2']) ?></strong></td></tr>
        <tr><td>3.A</td><td><?= csyj_h((string)$evaluacion['puntuacion_3a']) ?></td></tr>
        <tr><td>3.B</td><td><?= csyj_h((string)$evaluacion['puntuacion_3b']) ?></td></tr>
        <tr><td><strong>Bloque 3</strong></td><td><strong><?= csyj_h((string)$evaluacion['bloque_3']) ?></strong></td></tr>
        <tr><td><strong>Bloque 4</strong></td><td><strong><?= csyj_h((string)$evaluacion['bloque_4']) ?></strong></td></tr>
        <tr><td><strong>Total B1 + B2</strong></td><td><strong><?= csyj_h((string)$evaluacion['total_b1_b2']) ?></strong></td></tr>
        <tr><td><strong>Total final</strong></td><td><strong><?= csyj_h((string)$evaluacion['total_final']) ?></strong></td></tr>
    </table>
</section>

<section class="card">
    <details>
        <summary>Ver JSON de entrada</summary>
        <pre><?= csyj_h($evaluacion['json_entrada']) ?></pre>
    </details>
</section>
<?php csyj_render_layout_end(); ?>
