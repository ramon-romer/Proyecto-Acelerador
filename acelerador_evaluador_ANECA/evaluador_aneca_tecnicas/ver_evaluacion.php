<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_tecnicas.php';
require __DIR__ . '/ui.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) { die('ID no válido.'); }

$sql = "SELECT * FROM evaluaciones WHERE id = :id LIMIT 1";
$sentencia = $pdo->prepare($sql);
$sentencia->execute([':id' => $id]);
$evaluacion = $sentencia->fetch();

if (!$evaluacion) { die('Evaluación no encontrada.'); }

$datos = json_decode($evaluacion['json_entrada'], true);
if (!is_array($datos)) { $datos = []; }
$resultado_recalculado = evaluar_expediente($datos);
$diagnostico = $resultado_recalculado['diagnostico'];
$asesor = $resultado_recalculado['asesor'];

tec_render_layout_start(
    'Evaluación #' . (string)$evaluacion['id'],
    'Detalle completo del expediente evaluado en Técnicas, con desglose, diagnóstico y asesor orientativo.',
    [
        ['label' => 'Portal ANECA', 'url' => tec_portal_url()],
        ['label' => 'Técnicas', 'url' => tec_index_url()],
        ['label' => 'Listado', 'url' => tec_listado_url()],
        ['label' => 'Evaluación #' . (string)$evaluacion['id']],
    ],
    [
        ['label' => 'Volver al listado', 'url' => tec_listado_url(), 'class' => 'light'],
        ['label' => 'Nueva evaluación', 'url' => tec_index_url(), 'class' => 'light'],
    ]
);
?>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= tec_h($evaluacion['nombre_candidato']) ?></span></div>
        <div class="metric"><span class="label">Resultado</span><span class="value" style="font-size:18px"><?= tec_render_result_badge((string)$evaluacion['resultado']) ?></span></div>
        <div class="metric"><span class="label">Fecha</span><span class="value" style="font-size:18px"><?= tec_h($evaluacion['fecha_creacion']) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Técnicas</span></div>
    </div>

    <div class="kpis">
        <div class="kpi"><span class="label">Bloque 1</span><strong><?= tec_h((string)$evaluacion['bloque_1']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 2</span><strong><?= tec_h((string)$evaluacion['bloque_2']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 3</span><strong><?= tec_h((string)$evaluacion['bloque_3']) ?></strong></div>
        <div class="kpi"><span class="label">Bloque 4</span><strong><?= tec_h((string)$evaluacion['bloque_4']) ?></strong></div>
        <div class="kpi"><span class="label">Total B1 + B2</span><strong><?= tec_h((string)$evaluacion['total_b1_b2']) ?></strong></div>
        <div class="kpi"><span class="label">Total final</span><strong><?= tec_h((string)$evaluacion['total_final']) ?></strong></div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card">
            <h2>Desglose de puntuaciones</h2>
            <table>
                <tr><th>Concepto</th><th>Puntuación</th></tr>
                <tr><td>1.A</td><td><?= tec_h((string)$evaluacion['puntuacion_1a']) ?></td></tr>
                <tr><td>1.B</td><td><?= tec_h((string)$evaluacion['puntuacion_1b']) ?></td></tr>
                <tr><td>1.C</td><td><?= tec_h((string)$evaluacion['puntuacion_1c']) ?></td></tr>
                <tr><td>1.D</td><td><?= tec_h((string)$evaluacion['puntuacion_1d']) ?></td></tr>
                <tr><td>1.E</td><td><?= tec_h((string)$evaluacion['puntuacion_1e']) ?></td></tr>
                <tr><td>1.F</td><td><?= tec_h((string)$evaluacion['puntuacion_1f']) ?></td></tr>
                <tr><td>1.G</td><td><?= tec_h((string)$evaluacion['puntuacion_1g']) ?></td></tr>
                <tr><td><strong>Bloque 1</strong></td><td><strong><?= tec_h((string)$evaluacion['bloque_1']) ?></strong></td></tr>
                <tr><td>2.A</td><td><?= tec_h((string)$evaluacion['puntuacion_2a']) ?></td></tr>
                <tr><td>2.B</td><td><?= tec_h((string)$evaluacion['puntuacion_2b']) ?></td></tr>
                <tr><td>2.C</td><td><?= tec_h((string)$evaluacion['puntuacion_2c']) ?></td></tr>
                <tr><td>2.D</td><td><?= tec_h((string)$evaluacion['puntuacion_2d']) ?></td></tr>
                <tr><td><strong>Bloque 2</strong></td><td><strong><?= tec_h((string)$evaluacion['bloque_2']) ?></strong></td></tr>
                <tr><td>3.A</td><td><?= tec_h((string)$evaluacion['puntuacion_3a']) ?></td></tr>
                <tr><td>3.B</td><td><?= tec_h((string)$evaluacion['puntuacion_3b']) ?></td></tr>
                <tr><td><strong>Bloque 3</strong></td><td><strong><?= tec_h((string)$evaluacion['bloque_3']) ?></strong></td></tr>
                <tr><td><strong>Bloque 4</strong></td><td><strong><?= tec_h((string)$evaluacion['bloque_4']) ?></strong></td></tr>
                <tr><td><strong>Total B1 + B2</strong></td><td><strong><?= tec_h((string)$evaluacion['total_b1_b2']) ?></strong></td></tr>
                <tr><td><strong>Total final</strong></td><td><strong><?= tec_h((string)$evaluacion['total_final']) ?></strong></td></tr>
            </table>
        </section>

        <section class="card stack">
            <div>
                <h2>Diagnóstico inteligente</h2>
                <p><strong>Perfil detectado:</strong> <?= tec_h($diagnostico['perfil_detectado']) ?></p>
            </div>

            <table>
                <tr><th>Regla</th><th>Actual</th><th>Objetivo</th><th>Déficit</th><th>Cumple</th></tr>
                <?php foreach ($diagnostico['reglas'] as $regla): ?>
                    <tr>
                        <td><?= tec_h($regla['nombre']) ?></td>
                        <td><?= tec_h((string)$regla['valor_actual']) ?></td>
                        <td><?= tec_h((string)$regla['objetivo']) ?></td>
                        <td><?= tec_h((string)$regla['deficit']) ?></td>
                        <td><?= $regla['cumple'] ? tec_render_result_badge('Sí') : tec_render_result_badge('No') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="split" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                <div>
                    <h3>Fortalezas</h3>
                    <?php if (!empty($diagnostico['fortalezas'])): ?>
                        <ul><?php foreach ($diagnostico['fortalezas'] as $item): ?><li><?= tec_h($item) ?></li><?php endforeach; ?></ul>
                    <?php else: ?><p class="muted">No se detectan fortalezas especialmente marcadas.</p><?php endif; ?>
                </div>
                <div>
                    <h3>Debilidades</h3>
                    <?php if (!empty($diagnostico['debilidades'])): ?>
                        <ul><?php foreach ($diagnostico['debilidades'] as $item): ?><li><?= tec_h($item) ?></li><?php endforeach; ?></ul>
                    <?php else: ?><p class="muted">No se detectan debilidades críticas.</p><?php endif; ?>
                </div>
            </div>

            <div>
                <h3>Alertas</h3>
                <?php if (!empty($diagnostico['alertas'])): ?>
                    <ul><?php foreach ($diagnostico['alertas'] as $item): ?><li><?= tec_h($item) ?></li><?php endforeach; ?></ul>
                <?php else: ?><p class="muted">Sin alertas relevantes.</p><?php endif; ?>
            </div>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Asesor inteligente</h2>
            <p><?= tec_h($asesor['resumen']) ?></p>
            <h3>Acciones recomendadas</h3>
            <ol>
                <?php foreach ($asesor['acciones'] as $accion): ?>
                    <li><strong><?= tec_h($accion['titulo']) ?></strong><br><?= tec_h($accion['detalle']) ?><br><em><?= tec_h($accion['impacto_estimado']) ?></em></li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="card">
            <h2>Simulaciones orientativas</h2>
            <table>
                <tr><th>Escenario</th><th>Efecto estimado</th><th>Nuevo B1+B2</th><th>Nuevo total</th></tr>
                <?php foreach ($asesor['simulaciones'] as $sim): ?>
                    <tr>
                        <td><?= tec_h($sim['escenario']) ?></td>
                        <td><?= tec_h($sim['efecto_estimado']) ?></td>
                        <td><?= tec_h((string)$sim['nuevo_b1_b2_aprox']) ?></td>
                        <td><?= tec_h((string)$sim['nuevo_total_aprox']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>

        <section class="card">
            <details><summary>Ver JSON de entrada</summary><pre><?= tec_h($evaluacion['json_entrada']) ?></pre></details>
        </section>
    </aside>
</section>
<?php tec_render_layout_end(); ?>
