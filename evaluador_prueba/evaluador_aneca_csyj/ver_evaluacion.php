<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID de evaluación no válido.');
}

$stmt = $pdo->prepare('SELECT * FROM evaluaciones WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluacion) {
    die('No se ha encontrado la evaluación solicitada.');
}

$jsonEntrada = json_decode((string)($evaluacion['json_entrada'] ?? ''), true);
if (!is_array($jsonEntrada)) {
    $jsonEntrada = [];
}

$resultadoCalculo = [];
$diagnostico = [];
$asesor = [];

if (isset($jsonEntrada['resultado_calculo']) && is_array($jsonEntrada['resultado_calculo'])) {
    $resultadoCalculo = $jsonEntrada['resultado_calculo'];
    $diagnostico = $resultadoCalculo['diagnostico'] ?? [];
    $asesor = $resultadoCalculo['asesor'] ?? [];
}

function csyj_vf(mixed $value, int $decimals = 2): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function csyj_json_pretty(array $data): string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false ? $json : '{}';
}

$resultadoTexto = strtoupper((string)($evaluacion['resultado'] ?? 'NEGATIVA'));
$esPositiva = $resultadoTexto === 'POSITIVA';

$bloques = [
    [
        'titulo' => 'Bloque 1. Investigación',
        'items' => [
            ['label' => '1.A Publicaciones científicas', 'valor' => $evaluacion['puntuacion_1a'] ?? 0, 'max' => 30],
            ['label' => '1.B Libros y capítulos', 'valor' => $evaluacion['puntuacion_1b'] ?? 0, 'max' => 12],
            ['label' => '1.C Proyectos de investigación', 'valor' => $evaluacion['puntuacion_1c'] ?? 0, 'max' => 5],
            ['label' => '1.D Transferencia', 'valor' => $evaluacion['puntuacion_1d'] ?? 0, 'max' => 2],
            ['label' => '1.E Dirección de tesis doctorales', 'valor' => $evaluacion['puntuacion_1e'] ?? 0, 'max' => 4],
            ['label' => '1.F Congresos, seminarios y jornadas', 'valor' => $evaluacion['puntuacion_1f'] ?? 0, 'max' => 5],
            ['label' => '1.G Otros méritos de investigación', 'valor' => $evaluacion['puntuacion_1g'] ?? 0, 'max' => 2],
        ],
        'total' => $evaluacion['bloque_1'] ?? 0,
        'maximo' => 60,
    ],
    [
        'titulo' => 'Bloque 2. Experiencia docente',
        'items' => [
            ['label' => '2.A Docencia universitaria', 'valor' => $evaluacion['puntuacion_2a'] ?? 0, 'max' => 17],
            ['label' => '2.B Evaluación docente', 'valor' => $evaluacion['puntuacion_2b'] ?? 0, 'max' => 3],
            ['label' => '2.C Seminarios y cursos orientados a docencia', 'valor' => $evaluacion['puntuacion_2c'] ?? 0, 'max' => 3],
            ['label' => '2.D Material docente y proyectos de innovación', 'valor' => $evaluacion['puntuacion_2d'] ?? 0, 'max' => 7],
        ],
        'total' => $evaluacion['bloque_2'] ?? 0,
        'maximo' => 30,
    ],
    [
        'titulo' => 'Bloque 3. Formación académica y experiencia profesional',
        'items' => [
            ['label' => '3.A Formación académica', 'valor' => $evaluacion['puntuacion_3a'] ?? 0, 'max' => 6],
            ['label' => '3.B Experiencia profesional', 'valor' => $evaluacion['puntuacion_3b'] ?? 0, 'max' => 2],
        ],
        'total' => $evaluacion['bloque_3'] ?? 0,
        'maximo' => 8,
    ],
    [
        'titulo' => 'Bloque 4. Otros méritos',
        'items' => [
            ['label' => '4. Otros méritos', 'valor' => $evaluacion['bloque_4'] ?? 0, 'max' => 2],
        ],
        'total' => $evaluacion['bloque_4'] ?? 0,
        'maximo' => 2,
    ],
];

csyj_render_layout_start(
    'Resultado de evaluación',
    'Detalle completo de la evaluación guardada para la rama Ciencias Sociales y Jurídicas.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSyJ', 'url' => csyj_index_url()],
        ['label' => 'Evaluación #' . $id],
    ],
    [
        ['label' => 'Volver a CSyJ', 'url' => csyj_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => csyj_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .resultado-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 999px;
        font-weight: 800;
        font-size: 14px;
        letter-spacing: .02em;
    }
    .resultado-badge.positiva {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .resultado-badge.negativa {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .resumen-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 14px;
    }
    .resumen-box {
        border: 1px solid #dbe4ee;
        border-radius: 12px;
        background: #fff;
        padding: 14px;
    }
    .resumen-box .k {
        display: block;
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .resumen-box .v {
        display: block;
        font-size: 28px;
        font-weight: 800;
        color: #0f172a;
    }
    .tabla-puntuaciones {
        width: 100%;
        border-collapse: collapse;
        margin-top: 8px;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
    }
    .tabla-puntuaciones th,
    .tabla-puntuaciones td {
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
        vertical-align: top;
    }
    .tabla-puntuaciones th:last-child,
    .tabla-puntuaciones td:last-child {
        text-align: right;
        white-space: nowrap;
    }
    .tabla-puntuaciones th {
        background: #f8fafc;
        font-size: 13px;
        color: #334155;
    }
    .tabla-puntuaciones tr:last-child td {
        border-bottom: none;
    }
    .maximo {
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
    }
    .seccion-titulo {
        margin: 0 0 12px;
    }
    .lista-simple {
        margin: 8px 0 0;
        padding-left: 18px;
    }
    .lista-simple li {
        margin-bottom: 8px;
    }
    .regla-ok {
        color: #166534;
        font-weight: 700;
    }
    .regla-ko {
        color: #991b1b;
        font-weight: 700;
    }
    .acciones-asesor {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .accion-item {
        border: 1px solid #dbe4ee;
        border-radius: 12px;
        padding: 14px;
        background: #fff;
    }
    .sim-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .sim-item {
        border: 1px solid #dbe4ee;
        border-radius: 12px;
        padding: 14px;
        background: #f8fafc;
    }
    .meta-top {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }
    details summary {
        cursor: pointer;
        font-weight: 700;
    }
    pre {
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>

<section class="card stack">
    <div class="meta-top">
        <div>
            <h1 style="margin:0 0 6px;">Evaluación #<?= csyj_h((string)$id) ?></h1>
            <p class="muted" style="margin:0;">
                Candidato: <strong><?= csyj_h((string)($evaluacion['nombre_candidato'] ?? 'Sin nombre')) ?></strong>
                · Área: <strong><?= csyj_h((string)($evaluacion['area'] ?? 'Ciencias Sociales y Jurídicas')) ?></strong>
                · Categoría: <strong><?= csyj_h((string)($evaluacion['categoria'] ?? 'PCD/PUP')) ?></strong>
            </p>
        </div>

        <div class="resultado-badge <?= $esPositiva ? 'positiva' : 'negativa' ?>">
            <?= $esPositiva ? '✅' : '❌' ?> <?= csyj_h($resultadoTexto) ?>
        </div>
    </div>
</section>

<section class="card stack">
    <h2 class="seccion-titulo">Resumen global</h2>
    <div class="resumen-grid">
        <div class="resumen-box">
            <span class="k">Bloque 1</span>
            <span class="v"><?= csyj_vf($evaluacion['bloque_1'] ?? 0) ?></span>
            <span class="maximo">Máximo 60</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 2</span>
            <span class="v"><?= csyj_vf($evaluacion['bloque_2'] ?? 0) ?></span>
            <span class="maximo">Máximo 30</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 3</span>
            <span class="v"><?= csyj_vf($evaluacion['bloque_3'] ?? 0) ?></span>
            <span class="maximo">Máximo 8</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 4</span>
            <span class="v"><?= csyj_vf($evaluacion['bloque_4'] ?? 0) ?></span>
            <span class="maximo">Máximo 2</span>
        </div>
        <div class="resumen-box">
            <span class="k">1 + 2</span>
            <span class="v"><?= csyj_vf($evaluacion['total_b1_b2'] ?? 0) ?></span>
            <span class="maximo">Debe ser ≥ 50</span>
        </div>
        <div class="resumen-box">
            <span class="k">Total final</span>
            <span class="v"><?= csyj_vf($evaluacion['total_final'] ?? 0) ?></span>
            <span class="maximo">Debe ser ≥ 55</span>
        </div>
    </div>
</section>

<section class="split">
    <section class="card stack">
        <h2 class="seccion-titulo">Reglas de corte</h2>
        <div>
            <div class="<?= ((int)($evaluacion['cumple_regla_1'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                Regla 1: bloque 1 + bloque 2 ≥ 50 → <?= ((int)($evaluacion['cumple_regla_1'] ?? 0) === 1) ? 'Cumple' : 'No cumple' ?>
            </div>
        </div>
        <div>
            <div class="<?= ((int)($evaluacion['cumple_regla_2'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                Regla 2: total final ≥ 55 → <?= ((int)($evaluacion['cumple_regla_2'] ?? 0) === 1) ? 'Cumple' : 'No cumple' ?>
            </div>
        </div>
    </section>

    <section class="card stack">
        <h2 class="seccion-titulo">Diagnóstico técnico</h2>
        <?php if (!empty($diagnostico['version'])): ?>
            <div><strong>Versión:</strong> <?= csyj_h((string)$diagnostico['version']) ?></div>
        <?php endif; ?>

        <?php if (!empty($diagnostico['conteos']) && is_array($diagnostico['conteos'])): ?>
            <div class="sim-grid">
                <?php foreach ($diagnostico['conteos'] as $label => $valor): ?>
                    <div class="sim-item">
                        <strong><?= csyj_h((string)$label) ?></strong><br>
                        <span class="muted">Detectados: <?= csyj_h((string)$valor) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($diagnostico['criterios_clave']) && is_array($diagnostico['criterios_clave'])): ?>
            <ul class="lista-simple">
                <?php foreach ($diagnostico['criterios_clave'] as $criterio): ?>
                    <li><?= csyj_h((string)$criterio) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</section>

<?php foreach ($bloques as $bloque): ?>
    <section class="card stack">
        <h2 class="seccion-titulo"><?= csyj_h($bloque['titulo']) ?></h2>
        <table class="tabla-puntuaciones">
            <thead>
                <tr>
                    <th>Subapartado</th>
                    <th>Puntuación</th>
                    <th>Máximo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bloque['items'] as $item): ?>
                    <tr>
                        <td><?= csyj_h($item['label']) ?></td>
                        <td><?= csyj_vf($item['valor']) ?></td>
                        <td><?= csyj_vf($item['max']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Total del bloque</td>
                    <td><?= csyj_vf($bloque['total']) ?></td>
                    <td><?= csyj_vf($bloque['maximo']) ?></td>
                </tr>
            </tfoot>
        </table>
    </section>
<?php endforeach; ?>

<?php if (!empty($asesor)): ?>
    <section class="card stack">
        <h2 class="seccion-titulo">Asesor orientativo</h2>

        <?php if (!empty($asesor['resumen'])): ?>
            <p><?= csyj_h((string)$asesor['resumen']) ?></p>
        <?php endif; ?>

        <?php if (!empty($asesor['acciones']) && is_array($asesor['acciones'])): ?>
            <div class="acciones-asesor">
                <?php foreach ($asesor['acciones'] as $accion): ?>
                    <div class="accion-item">
                        <strong><?= csyj_h((string)($accion['titulo'] ?? 'Acción')) ?></strong>
                        <p style="margin:8px 0 0;"><?= csyj_h((string)($accion['detalle'] ?? '')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($asesor['simulaciones']) && is_array($asesor['simulaciones'])): ?>
            <div class="sim-grid">
                <?php foreach ($asesor['simulaciones'] as $sim): ?>
                    <div class="sim-item">
                        <strong><?= csyj_h((string)($sim['escenario'] ?? 'Escenario')) ?></strong><br>
                        <span class="muted"><?= csyj_h((string)($sim['mensaje'] ?? '')) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card">
    <details>
        <summary>Ver JSON del expediente evaluado</summary>
        <pre><?= csyj_h(csyj_json_pretty($jsonEntrada)) ?></pre>
    </details>
</section>

<?php csyj_render_layout_end(); ?>
