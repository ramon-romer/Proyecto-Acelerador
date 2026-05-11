<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';

$sql = "SELECT 
            id,
            nombre_candidato,
            area,
            categoria,
            bloque_1,
            bloque_2,
            bloque_3,
            bloque_4,
            total_b1_b2,
            total_final,
            resultado,
            cumple_regla_1,
            cumple_regla_2,
            fecha_creacion
        FROM evaluaciones
        ORDER BY id DESC";

$evaluaciones = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

function csyj_nf(mixed $value): string
{
    return number_format((float)$value, 2, ',', '.');
}

csyj_render_layout_start(
    'Listado de evaluaciones',
    'Histórico del módulo de CSyJ con acceso rápido al detalle de cada expediente.',
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
    .tabla-listado {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
    }
    .tabla-listado th,
    .tabla-listado td {
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }
    .tabla-listado th {
        background: #f8fafc;
        color: #334155;
        font-size: 13px;
    }
    .tabla-listado tr:hover td {
        background: #fafafa;
    }
    .tabla-listado .nombre {
        min-width: 220px;
        white-space: normal;
    }
    .tabla-listado .num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .resumen-top {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }
    .box-mini {
        border: 1px solid #dbe4ee;
        border-radius: 12px;
        padding: 14px;
        background: #fff;
    }
    .box-mini .label {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .box-mini .value {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
    }
    .muted-small {
        color: #64748b;
        font-size: 13px;
    }
    .regla-ok {
        color: #166534;
        font-weight: 700;
    }
    .regla-ko {
        color: #991b1b;
        font-weight: 700;
    }
    .reglas-cell {
        white-space: normal;
        min-width: 180px;
        line-height: 1.45;
    }
</style>

<section class="card stack">
    <div class="resumen-top">
        <div class="box-mini">
            <span class="label">Área</span>
            <span class="value" style="font-size:20px;">CSyJ</span>
        </div>
        <div class="box-mini">
            <span class="label">Registros</span>
            <span class="value"><?= csyj_h((string)count($evaluaciones)) ?></span>
        </div>
        <div class="box-mini">
            <span class="label">Criterio principal</span>
            <span class="value" style="font-size:20px;">1+2 ≥ 50</span>
        </div>
        <div class="box-mini">
            <span class="label">Criterio final</span>
            <span class="value" style="font-size:20px;">Total ≥ 55</span>
        </div>
    </div>
</section>

<section class="card stack">
    <h2 style="margin:0;">Histórico de expedientes</h2>
    <p class="muted-small" style="margin:0;">
        Vista rápida de bloques, reglas y decisión final. El diagnóstico y el asesor orientativo se ven dentro del detalle de cada evaluación.
    </p>

    <div style="overflow:auto;">
        <table class="tabla-listado">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Candidato</th>
                    <th>Área</th>
                    <th>Categoría</th>
                    <th class="num">B1</th>
                    <th class="num">B2</th>
                    <th class="num">B3</th>
                    <th class="num">B4</th>
                    <th class="num">1+2</th>
                    <th class="num">Total</th>
                    <th>Reglas</th>
                    <th>Resultado</th>
                    <th>Fecha</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$evaluaciones): ?>
                    <tr>
                        <td colspan="14" style="text-align:center; color:#64748b;">
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
                                <a class="btn outline" href="ver_evaluacion.php?id=<?= urlencode((string)$fila['id']) ?>">
                                    Abrir
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php csyj_render_layout_end(); ?>