<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';

function csyj_nf(mixed $value): string
{
    return number_format((float)$value, 2, ',', '.');
}

$sql = 'SELECT * FROM evaluaciones ORDER BY id DESC';
$evaluaciones = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

csyj_render_layout_start(
    'Listado de evaluaciones',
    'Histórico completo del módulo CSyJ con vista rápida de bloques, reglas y decisión final.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSyJ', 'url' => csyj_index_url()],
        ['label' => 'Listado'],
    ],
    [
        ['label' => 'Nueva evaluación', 'url' => csyj_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => csyj_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .tabla-listado th:nth-child(n+5),
    .tabla-listado td.num {
        text-align: center;
        white-space: nowrap;
    }
    .tabla-listado td.nombre {
        min-width: 220px;
    }
    .reglas-cell {
        display: grid;
        gap: 6px;
        min-width: 170px;
    }
    .regla-ok {
        color: #166534;
        font-weight: 700;
    }
    .regla-ko {
        color: #991b1b;
        font-weight: 700;
    }
</style>

<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Ciencias Sociales y Jurídicas</span></div>
        <div class="metric"><span class="label">Categoría por defecto</span><span class="value" style="font-size:20px">PCD/PUP</span></div>
        <div class="metric"><span class="label">Evaluaciones guardadas</span><span class="value"><?= csyj_h((string)count($evaluaciones)) ?></span></div>
    </div>

    <div class="muted">
        Umbrales de corte: bloque 1 + bloque 2 ≥ 50 y total final ≥ 55.
    </div>

    <div style="overflow:auto;">
        <table class="tabla-listado">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Candidato</th>
                    <th>Área</th>
                    <th>Categoría</th>
                    <th>B1</th>
                    <th>B2</th>
                    <th>B3</th>
                    <th>B4</th>
                    <th>1+2</th>
                    <th>Total</th>
                    <th>Reglas</th>
                    <th>Resultado</th>
                    <th>Fecha</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($evaluaciones === []): ?>
                    <tr>
                        <td colspan="14" class="muted" style="text-align:center; padding:24px;">
                            No hay evaluaciones guardadas todavía.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($evaluaciones as $fila): ?>
                        <tr>
                            <td><?= csyj_h((string)$fila['id']) ?></td>
                            <td class="nombre"><?= csyj_h((string)$fila['nombre_candidato']) ?></td>
                            <td><?= csyj_h((string)$fila['area']) ?></td>
                            <td><?= csyj_h((string)$fila['categoria']) ?></td>

                            <td class="num"><?= csyj_h(csyj_nf($fila['bloque_1'] ?? 0)) ?></td>
                            <td class="num"><?= csyj_h(csyj_nf($fila['bloque_2'] ?? 0)) ?></td>
                            <td class="num"><?= csyj_h(csyj_nf($fila['bloque_3'] ?? 0)) ?></td>
                            <td class="num"><?= csyj_h(csyj_nf($fila['bloque_4'] ?? 0)) ?></td>
                            <td class="num"><?= csyj_h(csyj_nf($fila['total_b1_b2'] ?? 0)) ?></td>
                            <td class="num"><strong><?= csyj_h(csyj_nf($fila['total_final'] ?? 0)) ?></strong></td>

                            <td class="reglas-cell">
                                <div class="<?= ((int)($fila['cumple_regla_1'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                                    1+2 ≥ 50: <?= ((int)($fila['cumple_regla_1'] ?? 0) === 1) ? 'Sí' : 'No' ?>
                                </div>
                                <div class="<?= ((int)($fila['cumple_regla_2'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                                    Total ≥ 55: <?= ((int)($fila['cumple_regla_2'] ?? 0) === 1) ? 'Sí' : 'No' ?>
                                </div>
                            </td>

                            <td><?= csyj_render_result_badge((string)$fila['resultado']) ?></td>
                            <td><?= csyj_h((string)$fila['fecha_creacion']) ?></td>
                            <td>
                                <a class="btn outline" href="ver_evaluacion.php?id=<?= urlencode((string)$fila['id']) ?>">Abrir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php csyj_render_layout_end(); ?>
