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

function hum_nf(mixed $value): string
{
    return number_format((float)$value, 2, ',', '.');
}

hum_render_layout_start(
    'Listado de evaluaciones',
    'Histórico del módulo de Humanidades con acceso rápido al detalle de cada expediente.',
    [
        ['label' => 'Portal ANECA', 'url' => hum_portal_url()],
        ['label' => 'Humanidades', 'url' => hum_index_url()],
        ['label' => 'Listado'],
    ],
    [
        ['label' => 'Nueva evaluación', 'url' => hum_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => hum_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .shell {
        max-width: 1600px !important;
    }
    .tabla-listado {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }
    .tabla-listado th,
    .tabla-listado td {
        padding: 14px 18px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        border-left: none !important;
        border-right: none !important;
        text-align: left;
        vertical-align: middle;
        color: #e2e8f0;
    }
    .tabla-listado th {
        background: rgba(255, 255, 255, 0.05);
        color: rgba(255, 255, 255, 0.6);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    }
    .tabla-listado tr:hover td {
        background: rgba(255, 255, 255, 0.03);
    }
    .tabla-listado tr:last-child td {
        border-bottom: none;
    }
    .tabla-listado .nombre {
        min-width: 220px;
        white-space: nowrap;
        font-weight: 600;
        color: #fff;
    }
    .tabla-listado .num {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .resumen-top {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 18px;
    }
    .box-mini {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.03);
        text-align: center;
        transition: background 0.2s;
    }
    .box-mini:hover { background: rgba(255, 255, 255, 0.06); }
    .box-mini .label {
        display: block;
        color: rgba(255, 255, 255, 0.5);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 8px;
        letter-spacing: 0.05em;
    }
    .box-mini .value {
        font-size: 24px;
        font-weight: 800;
        color: #fff;
    }
    .muted-small {
        color: rgba(255, 255, 255, 0.5);
        font-size: 13px;
    }
    .regla-ok {
        color: #4ade80;
        font-weight: 700;
    }
    .regla-ko {
        color: #f87171;
        font-weight: 700;
    }
    .reglas-cell {
        white-space: nowrap;
        min-width: 180px;
        display: flex;
        gap: 15px;
        align-items: center;
        font-size: 13px;
    }

    /* 📱 RESPONSIVE: Transformar tabla en tarjetas */
    @media (max-width: 992px) {
        .tabla-listado, .tabla-listado thead, .tabla-listado tbody, .tabla-listado th, .tabla-listado td, .tabla-listado tr {
            display: block;
            width: 100%;
        }
        .tabla-listado thead {
            display: none; /* Ocultar cabecera original */
        }
        .tabla-listado tr {
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 15px;
            position: relative;
        }
        .tabla-listado td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            text-align: right !important;
            font-size: 14px;
        }
        .tabla-listado td:last-child {
            border-bottom: none;
            justify-content: center;
            padding-top: 15px;
        }
        .tabla-listado td::before {
            content: attr(data-label);
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.4);
            text-align: left;
            flex: 1;
        }
        .tabla-listado .nombre {
            font-size: 1.1rem;
            margin-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 10px;
            justify-content: center;
            text-align: center !important;
        }
        .tabla-listado .nombre::before {
            display: none;
        }
        .reglas-cell {
            justify-content: flex-end;
            min-width: 0;
        }
    }
</style>

<section class="card stack">
    <div class="resumen-top">
        <div class="box-mini">
            <span class="label">Área</span>
            <span class="value" style="font-size:20px;">Humanidades</span>
        </div>
        <div class="box-mini">
            <span class="label">Registros</span>
            <span class="value"><?= hum_h((string)count($evaluaciones)) ?></span>
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
                            <td data-label="ID"><?= hum_h((string)$fila['id']) ?></td>
                            <td data-label="Candidato" class="nombre"><?= hum_h((string)$fila['nombre_candidato']) ?></td>
                            <td data-label="Área"><?= hum_h((string)$fila['area']) ?></td>
                            <td data-label="Categoría"><?= hum_h((string)$fila['categoria']) ?></td>

                            <td data-label="B1" class="num"><?= hum_h(hum_nf($fila['bloque_1'] ?? 0)) ?></td>
                            <td data-label="B2" class="num"><?= hum_h(hum_nf($fila['bloque_2'] ?? 0)) ?></td>
                            <td data-label="B3" class="num"><?= hum_h(hum_nf($fila['bloque_3'] ?? 0)) ?></td>
                            <td data-label="B4" class="num"><?= hum_h(hum_nf($fila['bloque_4'] ?? 0)) ?></td>
                            <td data-label="1+2" class="num"><?= hum_h(hum_nf($fila['total_b1_b2'] ?? 0)) ?></td>
                            <td data-label="Total" class="num"><strong><?= hum_h(hum_nf($fila['total_final'] ?? 0)) ?></strong></td>

                            <td data-label="Reglas" class="reglas-cell">
                                <div class="<?= ((int)($fila['cumple_regla_1'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                                    1+2 ≥ 50: <?= ((int)($fila['cumple_regla_1'] ?? 0) === 1) ? 'Sí' : 'No' ?>
                                </div>
                                <div class="<?= ((int)($fila['cumple_regla_2'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                                    Total ≥ 55: <?= ((int)($fila['cumple_regla_2'] ?? 0) === 1) ? 'Sí' : 'No' ?>
                                </div>
                            </td>

                            <td data-label="Resultado"><?= hum_render_result_badge((string)$fila['resultado']) ?></td>
                            <td data-label="Fecha"><?= hum_h((string)$fila['fecha_creacion']) ?></td>
                            <td data-label="">
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

<?php hum_render_layout_end(); ?>