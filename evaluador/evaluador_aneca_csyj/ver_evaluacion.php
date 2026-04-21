<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';
require __DIR__ . '/funciones_evaluador_csyj.php';

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
if (isset($jsonEntrada['resultado_calculo']) && is_array($jsonEntrada['resultado_calculo'])) {
    $resultadoCalculo = $jsonEntrada['resultado_calculo'];
}

if ($resultadoCalculo === []) {
    $resultadoCalculado = evaluar_expediente($jsonEntrada);
    $resultadoCalculo = [
        'puntuaciones' => $resultadoCalculado['puntuaciones'] ?? [],
        'totales' => $resultadoCalculado['totales'] ?? [],
        'decision' => $resultadoCalculado['decision'] ?? [],
        'diagnostico' => $resultadoCalculado['diagnostico'] ?? [],
        'asesor' => $resultadoCalculado['asesor'] ?? [],
        'puntuacion_4' => $resultadoCalculado['puntuacion_4'] ?? ($resultadoCalculado['bloque_4'] ?? 0),
    ];
}

$diagnostico = is_array($resultadoCalculo['diagnostico'] ?? null) ? $resultadoCalculo['diagnostico'] : [];
$asesor = is_array($resultadoCalculo['asesor'] ?? null) ? $resultadoCalculo['asesor'] : [];

function csyj_v(mixed $value, string $default = '0'): string
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function csyj_f(mixed $value, int $decimals = 2): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function csyj_json_pretty(array $data): string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false ? $json : '{}';
}

$puntuacion4 = $resultadoCalculo['puntuacion_4'] ?? ($resultadoCalculo['puntuaciones']['4'] ?? ($evaluacion['bloque_4'] ?? 0));

csyj_render_layout_start(
    'Resultado de evaluación',
    'Detalle completo de la evaluación guardada para la rama de Ciencias Sociales y Jurídicas.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSYJ', 'url' => csyj_index_url()],
        ['label' => 'Evaluación #' . $id],
    ],
    [
        ['label' => 'Volver a CSYJ', 'url' => csyj_index_url(), 'class' => 'light'],
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

<?php
$resultadoTexto = strtoupper((string)($evaluacion['resultado'] ?? 'NEGATIVA'));
$esPositiva = $resultadoTexto === 'POSITIVA';
?>

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
            <span class="v"><?= csyj_f($evaluacion['bloque_1'] ?? 0) ?></span>
            <span class="maximo">Máximo 60</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 2</span>
            <span class="v"><?= csyj_f($evaluacion['bloque_2'] ?? 0) ?></span>
            <span class="maximo">Máximo 30</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 3</span>
            <span class="v"><?= csyj_f($evaluacion['bloque_3'] ?? 0) ?></span>
            <span class="maximo">Máximo 8</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 4</span>
            <span class="v"><?= csyj_f($evaluacion['bloque_4'] ?? 0) ?></span>
            <span class="maximo">Máximo 2</span>
        </div>
        <div class="resumen-box">
            <span class="k">1 + 2</span>
            <span class="v"><?= csyj_f($evaluacion['total_b1_b2'] ?? 0) ?></span>
            <span class="maximo">Debe ser ≥ 50</span>
        </div>
        <div class="resumen-box">
            <span class="k">Total final</span>
            <span class="v"><?= csyj_f($evaluacion['total_final'] ?? 0) ?></span>
            <span class="maximo">Debe ser ≥ 55</span>
        </div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card stack">
            <h2 class="seccion-titulo">Detalle de puntuaciones</h2>

            <table class="tabla-puntuaciones">
                <thead>
                    <tr>
                        <th>Apartado</th>
                        <th>Puntuación</th>
                        <th>Máximo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td><strong>1.A</strong> Publicaciones científicas y patentes</td><td><?= csyj_f($evaluacion['puntuacion_1a'] ?? 0) ?></td><td>30</td></tr>
                    <tr><td><strong>1.B</strong> Libros y capítulos de libro</td><td><?= csyj_f($evaluacion['puntuacion_1b'] ?? 0) ?></td><td>12</td></tr>
                    <tr><td><strong>1.C</strong> Proyectos y contratos de investigación</td><td><?= csyj_f($evaluacion['puntuacion_1c'] ?? 0) ?></td><td>5</td></tr>
                    <tr><td><strong>1.D</strong> Transferencia de tecnología</td><td><?= csyj_f($evaluacion['puntuacion_1d'] ?? 0) ?></td><td>2</td></tr>
                    <tr><td><strong>1.E</strong> Dirección de tesis doctorales</td><td><?= csyj_f($evaluacion['puntuacion_1e'] ?? 0) ?></td><td>4</td></tr>
                    <tr><td><strong>1.F</strong> Congresos, conferencias, seminarios</td><td><?= csyj_f($evaluacion['puntuacion_1f'] ?? 0) ?></td><td>5</td></tr>
                    <tr><td><strong>1.G</strong> Otros méritos</td><td><?= csyj_f($evaluacion['puntuacion_1g'] ?? 0) ?></td><td>2</td></tr>

                    <tr><td colspan="3" style="background:#f8fafc;"><strong>Bloque 2 · Experiencia docente</strong></td></tr>

                    <tr><td><strong>2.A</strong> Docencia universitaria</td><td><?= csyj_f($evaluacion['puntuacion_2a'] ?? 0) ?></td><td>17</td></tr>
                    <tr><td><strong>2.B</strong> Evaluaciones sobre su calidad</td><td><?= csyj_f($evaluacion['puntuacion_2b'] ?? 0) ?></td><td>3</td></tr>
                    <tr><td><strong>2.C</strong> Cursos y seminarios de formación docente universitaria</td><td><?= csyj_f($evaluacion['puntuacion_2c'] ?? 0) ?></td><td>3</td></tr>
                    <tr><td><strong>2.D</strong> Material docente, proyectos y contribuciones al EEES</td><td><?= csyj_f($evaluacion['puntuacion_2d'] ?? 0) ?></td><td>7</td></tr>

                    <tr><td colspan="3" style="background:#f8fafc;"><strong>Bloque 3 · Formación académica y experiencia profesional</strong></td></tr>

                    <tr><td><strong>3.A</strong> Tesis, becas, estancias, otros títulos</td><td><?= csyj_f($evaluacion['puntuacion_3a'] ?? 0) ?></td><td>6</td></tr>
                    <tr><td><strong>3.B</strong> Trabajo en empresas / instituciones / hospitales</td><td><?= csyj_f($evaluacion['puntuacion_3b'] ?? 0) ?></td><td>2</td></tr>

                    <tr><td colspan="3" style="background:#f8fafc;"><strong>Bloque 4</strong></td></tr>

                    <tr><td><strong>4</strong> Otros méritos</td><td><?= csyj_f($puntuacion4) ?></td><td>2</td></tr>
                </tbody>
            </table>
        </section>

        <section class="card stack">
            <h2 class="seccion-titulo">Reglas de decisión</h2>
            <div class="<?= ((int)($evaluacion['cumple_regla_1'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                Regla 1: Bloque 1 + Bloque 2 ≥ 50
                → <?= ((int)($evaluacion['cumple_regla_1'] ?? 0) === 1) ? 'CUMPLE' : 'NO CUMPLE' ?>
            </div>
            <div class="<?= ((int)($evaluacion['cumple_regla_2'] ?? 0) === 1) ? 'regla-ok' : 'regla-ko' ?>">
                Regla 2: Bloque 1 + Bloque 2 + Bloque 3 + Bloque 4 ≥ 55
                → <?= ((int)($evaluacion['cumple_regla_2'] ?? 0) === 1) ? 'CUMPLE' : 'NO CUMPLE' ?>
            </div>
        </section>

        <?php if (!empty($diagnostico)): ?>
            <section class="card stack">
                <h2 class="seccion-titulo">Diagnóstico</h2>

                <?php if (!empty($diagnostico['perfil_detectado'])): ?>
                    <p><strong>Perfil detectado:</strong> <?= csyj_h((string)$diagnostico['perfil_detectado']) ?></p>
                <?php endif; ?>

                <?php if (!empty($diagnostico['fortalezas']) && is_array($diagnostico['fortalezas'])): ?>
                    <div>
                        <strong>Fortalezas</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['fortalezas'] as $item): ?>
                                <li><?= csyj_h((string)$item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($diagnostico['debilidades']) && is_array($diagnostico['debilidades'])): ?>
                    <div>
                        <strong>Debilidades</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['debilidades'] as $item): ?>
                                <li><?= csyj_h((string)$item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($diagnostico['alertas']) && is_array($diagnostico['alertas'])): ?>
                    <div>
                        <strong>Alertas</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['alertas'] as $item): ?>
                                <li><?= csyj_h((string)$item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($diagnostico['reglas']) && is_array($diagnostico['reglas'])): ?>
                    <div>
                        <strong>Detalle de reglas</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['reglas'] as $regla): ?>
                                <li>
                                    <?= csyj_h((string)($regla['nombre'] ?? 'Regla')) ?>:
                                    actual <?= csyj_f($regla['valor_actual'] ?? 0) ?> /
                                    objetivo <?= csyj_f($regla['objetivo'] ?? 0) ?>
                                    <?php if (isset($regla['deficit'])): ?>
                                        · déficit <?= csyj_f($regla['deficit']) ?>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

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
                                <div><strong><?= csyj_h((string)($accion['titulo'] ?? 'Acción')) ?></strong></div>
                                <?php if (!empty($accion['detalle'])): ?>
                                    <div style="margin-top:6px;"><?= csyj_h((string)$accion['detalle']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($accion['impacto_estimado'])): ?>
                                    <div class="maximo" style="margin-top:8px;">
                                        Impacto estimado: <?= csyj_h((string)$accion['impacto_estimado']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($asesor['simulaciones']) && is_array($asesor['simulaciones'])): ?>
                    <div>
                        <strong>Simulaciones rápidas</strong>
                        <div class="sim-grid" style="margin-top:10px;">
                            <?php foreach ($asesor['simulaciones'] as $sim): ?>
                                <div class="sim-item">
                                    <div><strong><?= csyj_h((string)($sim['escenario'] ?? 'Escenario')) ?></strong></div>
                                    <?php if (!empty($sim['efecto_estimado'])): ?>
                                        <div style="margin-top:6px;"><?= csyj_h((string)$sim['efecto_estimado']) ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($sim['nuevo_b1_b2_aprox'])): ?>
                                        <div class="maximo" style="margin-top:8px;">
                                            Nuevo 1+2 aprox.: <?= csyj_f($sim['nuevo_b1_b2_aprox']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($sim['nuevo_total_aprox'])): ?>
                                        <div class="maximo">
                                            Nuevo total aprox.: <?= csyj_f($sim['nuevo_total_aprox']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card stack">
            <details>
                <summary>Ver JSON guardado</summary>
                <pre><?= csyj_h(csyj_json_pretty($jsonEntrada)) ?></pre>
            </details>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen técnico</h2>
            <div class="kpis">
                <div class="kpi">
                    <span class="label">1A</span>
                    <strong><?= csyj_f($evaluacion['puntuacion_1a'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">1C</span>
                    <strong><?= csyj_f($evaluacion['puntuacion_1c'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">1F</span>
                    <strong><?= csyj_f($evaluacion['puntuacion_1f'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">2A</span>
                    <strong><?= csyj_f($evaluacion['puntuacion_2a'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">2B</span>
                    <strong><?= csyj_f($evaluacion['puntuacion_2b'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">3A</span>
                    <strong><?= csyj_f($evaluacion['puntuacion_3a'] ?? 0) ?></strong>
                </div>
            </div>
        </section>

        <section class="card">
            <h2>Referencia de máximos</h2>
            <ul class="lista-simple">
                <li>Bloque 1 = 60</li>
                <li>Bloque 2 = 30</li>
                <li>Bloque 3 = 8</li>
                <li>Bloque 4 = 2</li>
                <li>Positiva si 1+2 ≥ 50 y total ≥ 55</li>
            </ul>
        </section>

        <section class="card">
            <h2>Acciones</h2>
            <div class="stack">
                <a class="btn" href="index.php">Nueva evaluación</a>
                <a class="btn secondary" href="listado.php">Ir al listado</a>
            </div>
        </section>
    </aside>
</section>

<?php csyj_render_layout_end(); ?>
