<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';

$sql = "SELECT id, nombre_candidato, area, categoria, total_final, resultado, fecha_creacion
        FROM evaluaciones
        ORDER BY id DESC";

$evaluaciones = $pdo->query($sql)->fetchAll();

exp_render_layout_start(
    'Listado de evaluaciones',
    'Histórico del módulo de Experimentales con acceso rápido al detalle de cada expediente.',
    [
        ['label' => 'Portal ANECA', 'url' => exp_portal_url()],
        ['label' => 'Experimentales', 'url' => exp_index_url()],
        ['label' => 'Listado'],
    ],
    [
        ['label' => 'Nueva evaluación', 'url' => exp_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => exp_portal_url(), 'class' => 'light'],
    ]
);
?>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Experimentales</span></div>
        <div class="metric"><span class="label">Registros</span><span class="value"><?= exp_h((string)count($evaluaciones)) ?></span></div>
    </div>

    <div style="overflow:auto;">
        <table>
            <tr>
                <th>ID</th>
                <th>Candidato</th>
                <th>Área</th>
                <th>Categoría</th>
                <th>Total final</th>
                <th>Resultado</th>
                <th>Fecha</th>
                <th>Ver</th>
            </tr>

            <?php foreach ($evaluaciones as $fila): ?>
                <tr>
                    <td><?= exp_h((string)$fila['id']) ?></td>
                    <td><?= exp_h($fila['nombre_candidato']) ?></td>
                    <td><?= exp_h($fila['area']) ?></td>
                    <td><?= exp_h($fila['categoria']) ?></td>
                    <td><?= exp_h((string)$fila['total_final']) ?></td>
                    <td><?= exp_render_result_badge((string)$fila['resultado']) ?></td>
                    <td><?= exp_h($fila['fecha_creacion']) ?></td>
                    <td><a class="btn outline" href="ver_evaluacion.php?id=<?= urlencode((string)$fila['id']) ?>">Abrir</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</section>
<?php exp_render_layout_end(); ?>
