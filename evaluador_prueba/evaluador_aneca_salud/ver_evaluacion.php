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

$diagnostico = [];
$asesor = [];

if (isset($jsonEntrada['resultado_calculo']) && is_array($jsonEntrada['resultado_calculo'])) {
    $diagnostico = $jsonEntrada['resultado_calculo']['diagnostico'] ?? [];
    $asesor = $jsonEntrada['resultado_calculo']['asesor'] ?? [];
} else {
    $diagnostico = $jsonEntrada['diagnostico'] ?? [];
    $asesor = $jsonEntrada['asesor'] ?? [];
}

/*
 * Si Salud todavía no genera diagnóstico/asesor desde funciones_evaluador_salud.php,
 * se construye uno orientativo a partir de las puntuaciones guardadas.
 * Esto NO modifica la lógica de evaluación ni recalcula puntuaciones.
 */
if (empty($diagnostico)) {
    $b1 = (float)($evaluacion['bloque_1'] ?? 0);
    $b2 = (float)($evaluacion['bloque_2'] ?? 0);
    $b3 = (float)($evaluacion['bloque_3'] ?? 0);
    $b4 = (float)($evaluacion['bloque_4'] ?? 0);
    $totalB1B2 = (float)($evaluacion['total_b1_b2'] ?? 0);
    $totalFinal = (float)($evaluacion['total_final'] ?? 0);

    $p1a = (float)($evaluacion['puntuacion_1a'] ?? 0);
    $p1c = (float)($evaluacion['puntuacion_1c'] ?? 0);
    $p2a = (float)($evaluacion['puntuacion_2a'] ?? 0);
    $p3a = (float)($evaluacion['puntuacion_3a'] ?? 0);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ($b1 >= 45) {
        $fortalezas[] = 'Buen rendimiento en el Bloque 1 de investigación.';
    } else {
        $debilidades[] = 'El Bloque 1 de investigación tiene margen de mejora.';
    }

    if ($p1a >= 25) {
        $fortalezas[] = 'El apartado 1.A de publicaciones científicas presenta una base sólida.';
    } else {
        $debilidades[] = 'Conviene reforzar las publicaciones científicas, especialmente las de mayor impacto.';
    }

    if ($p1c >= 4) {
        $fortalezas[] = 'Existe participación relevante en proyectos o contratos de investigación.';
    } else {
        $debilidades[] = 'Sería recomendable reforzar proyectos, contratos o actividad investigadora acreditada.';
    }

    if ($b2 >= 22) {
        $fortalezas[] = 'La experiencia docente del Bloque 2 es adecuada.';
    } else {
        $debilidades[] = 'El Bloque 2 requiere más evidencias docentes o mejor acreditación de la actividad docente.';
    }

    if ($p2a >= 12) {
        $fortalezas[] = 'La docencia universitaria tiene peso suficiente dentro del expediente.';
    } else {
        $debilidades[] = 'La docencia universitaria debería reforzarse o documentarse con mayor detalle.';
    }

    if ($b3 >= 5) {
        $fortalezas[] = 'La formación académica y experiencia profesional aportan soporte al expediente.';
    } else {
        $debilidades[] = 'El Bloque 3 puede mejorar con formación, estancias, becas o experiencia profesional sanitaria/académica.';
    }

    if ($totalB1B2 < 50) {
        $alertas[] = 'No se alcanza el mínimo orientativo de 50 puntos en la suma de Bloque 1 + Bloque 2.';
    }

    if ($totalFinal < 55) {
        $alertas[] = 'No se alcanza el mínimo orientativo de 55 puntos en el total final.';
    }

    if (empty($fortalezas)) {
        $fortalezas[] = 'El expediente contiene información evaluable, aunque necesita refuerzo en varios apartados.';
    }

    if (empty($debilidades)) {
        $debilidades[] = 'No se detectan debilidades graves en los bloques principales.';
    }

    if (empty($alertas)) {
        $alertas[] = 'No se detectan alertas críticas según las reglas principales.';
    }

    $diagnostico = [
        'perfil_detectado' => ($totalB1B2 >= 50 && $totalFinal >= 55)
            ? 'Perfil favorable para evaluación positiva'
            : 'Perfil con margen de mejora antes de una evaluación positiva',
        'fortalezas' => $fortalezas,
        'debilidades' => $debilidades,
        'alertas' => $alertas,
        'reglas' => [
            [
                'nombre' => 'Bloque 1 + Bloque 2 ≥ 50',
                'valor_actual' => $totalB1B2,
                'objetivo' => 50,
                'deficit' => max(0, 50 - $totalB1B2),
            ],
            [
                'nombre' => 'Total final ≥ 55',
                'valor_actual' => $totalFinal,
                'objetivo' => 55,
                'deficit' => max(0, 55 - $totalFinal),
            ],
        ],
    ];
}

if (empty($asesor)) {
    $b1 = (float)($evaluacion['bloque_1'] ?? 0);
    $b2 = (float)($evaluacion['bloque_2'] ?? 0);
    $b3 = (float)($evaluacion['bloque_3'] ?? 0);
    $totalB1B2 = (float)($evaluacion['total_b1_b2'] ?? 0);
    $totalFinal = (float)($evaluacion['total_final'] ?? 0);

    $acciones = [];

    if ($b1 < 45) {
        $acciones[] = [
            'titulo' => 'Reforzar publicaciones e investigación',
            'detalle' => 'Priorizar publicaciones científicas de impacto, proyectos competitivos, transferencia y dirección de tesis si procede.',
            'impacto_estimado' => 'Alto en Bloque 1',
        ];
    }

    if ($b2 < 22) {
        $acciones[] = [
            'titulo' => 'Aumentar y documentar mejor la actividad docente',
            'detalle' => 'Aportar horas de docencia universitaria, evaluaciones docentes, formación docente y materiales/proyectos de innovación.',
            'impacto_estimado' => 'Alto en Bloque 2',
        ];
    }

    if ($b3 < 5) {
        $acciones[] = [
            'titulo' => 'Completar formación académica y experiencia profesional',
            'detalle' => 'Incluir estancias, becas, tesis, otros títulos y experiencia profesional sanitaria, clínica o universitaria acreditada.',
            'impacto_estimado' => 'Medio en Bloque 3',
        ];
    }

    if (empty($acciones)) {
        $acciones[] = [
            'titulo' => 'Mantener evidencias actualizadas',
            'detalle' => 'El expediente está bien orientado. Conviene mantener actualizados los méritos y justificar documentalmente cada apartado.',
            'impacto_estimado' => 'Mantenimiento',
        ];
    }

    $asesor = [
        'resumen' => ($totalB1B2 >= 50 && $totalFinal >= 55)
            ? 'El expediente presenta una situación favorable. Se recomienda revisar la documentación justificativa antes de la presentación final.'
            : 'El expediente necesita refuerzo en los bloques con menor puntuación antes de alcanzar una evaluación positiva.',
        'acciones' => $acciones,
        'simulaciones' => [
            [
                'escenario' => 'Mejora moderada en investigación',
                'efecto_estimado' => 'Añadir méritos relevantes en publicaciones, proyectos o transferencia puede mejorar la suma de Bloque 1 + Bloque 2.',
                'nuevo_b1_b2_aprox' => $totalB1B2 + 3,
                'nuevo_total_aprox' => $totalFinal + 3,
            ],
            [
                'escenario' => 'Mejora moderada en docencia',
                'efecto_estimado' => 'Acreditar más docencia, evaluaciones docentes o materiales puede acercar el expediente a los mínimos requeridos.',
                'nuevo_b1_b2_aprox' => $totalB1B2 + 2,
                'nuevo_total_aprox' => $totalFinal + 2,
            ],
        ],
    ];
}

function salud_v(mixed $value, string $default = '0'): string
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (string)$value;
}

function salud_f(mixed $value, int $decimals = 2): string
{
    return number_format((float)$value, $decimals, ',', '.');
}

function salud_json_pretty(array $data): string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return $json !== false ? $json : '{}';
}

salud_render_layout_start(
    'Resultado de evaluación',
    'Detalle completo de la evaluación guardada para la rama Salud.',
    [
        ['label' => 'Portal ANECA', 'url' => salud_portal_url()],
        ['label' => 'Salud', 'url' => salud_index_url()],
        ['label' => 'Evaluación #' . $id],
    ],
    [
        ['label' => 'Volver a Salud', 'url' => salud_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => salud_portal_url(), 'class' => 'light'],
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
            <h1 style="margin:0 0 6px;">Evaluación #<?= salud_h((string)$id) ?></h1>
            <p class="muted" style="margin:0;">
                Candidato: <strong><?= salud_h((string)($evaluacion['nombre_candidato'] ?? 'Sin nombre')) ?></strong>
                · Área: <strong><?= salud_h((string)($evaluacion['area'] ?? 'Salud')) ?></strong>
                · Categoría: <strong><?= salud_h((string)($evaluacion['categoria'] ?? 'PCD/PUP')) ?></strong>
            </p>
        </div>

        <div class="resultado-badge <?= $esPositiva ? 'positiva' : 'negativa' ?>">
            <?= $esPositiva ? '✅' : '❌' ?> <?= salud_h($resultadoTexto) ?>
        </div>
    </div>
</section>

<section class="card stack">
    <h2 class="seccion-titulo">Resumen global</h2>
    <div class="resumen-grid">
        <div class="resumen-box">
            <span class="k">Bloque 1</span>
            <span class="v"><?= salud_f($evaluacion['bloque_1'] ?? 0) ?></span>
            <span class="maximo">Máximo 60</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 2</span>
            <span class="v"><?= salud_f($evaluacion['bloque_2'] ?? 0) ?></span>
            <span class="maximo">Máximo 30</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 3</span>
            <span class="v"><?= salud_f($evaluacion['bloque_3'] ?? 0) ?></span>
            <span class="maximo">Máximo 8</span>
        </div>
        <div class="resumen-box">
            <span class="k">Bloque 4</span>
            <span class="v"><?= salud_f($evaluacion['bloque_4'] ?? 0) ?></span>
            <span class="maximo">Máximo 2</span>
        </div>
        <div class="resumen-box">
            <span class="k">1 + 2</span>
            <span class="v"><?= salud_f($evaluacion['total_b1_b2'] ?? 0) ?></span>
            <span class="maximo">Debe ser ≥ 50</span>
        </div>
        <div class="resumen-box">
            <span class="k">Total final</span>
            <span class="v"><?= salud_f($evaluacion['total_final'] ?? 0) ?></span>
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
                    <tr><td><strong>1.A</strong> Publicaciones científicas</td><td><?= salud_f($evaluacion['puntuacion_1a'] ?? 0) ?></td><td>35</td></tr>
                    <tr><td><strong>1.B</strong> Libros y capítulos de libro</td><td><?= salud_f($evaluacion['puntuacion_1b'] ?? 0) ?></td><td>7</td></tr>
                    <tr><td><strong>1.C</strong> Proyectos y contratos de investigación</td><td><?= salud_f($evaluacion['puntuacion_1c'] ?? 0) ?></td><td>7</td></tr>
                    <tr><td><strong>1.D</strong> Transferencia tecnológica</td><td><?= salud_f($evaluacion['puntuacion_1d'] ?? 0) ?></td><td>4</td></tr>
                    <tr><td><strong>1.E</strong> Dirección de tesis doctorales</td><td><?= salud_f($evaluacion['puntuacion_1e'] ?? 0) ?></td><td>4</td></tr>
                    <tr><td><strong>1.F</strong> Congresos, conferencias y seminarios</td><td><?= salud_f($evaluacion['puntuacion_1f'] ?? 0) ?></td><td>2</td></tr>
                    <tr><td><strong>1.G</strong> Otros méritos de investigación</td><td><?= salud_f($evaluacion['puntuacion_1g'] ?? 0) ?></td><td>1</td></tr>

                    <tr><td colspan="3" style="background:#f8fafc;"><strong>Bloque 2 · Experiencia docente</strong></td></tr>

                    <tr><td><strong>2.A</strong> Docencia universitaria</td><td><?= salud_f($evaluacion['puntuacion_2a'] ?? 0) ?></td><td>17</td></tr>
                    <tr><td><strong>2.B</strong> Evaluaciones sobre su calidad</td><td><?= salud_f($evaluacion['puntuacion_2b'] ?? 0) ?></td><td>3</td></tr>
                    <tr><td><strong>2.C</strong> Cursos y seminarios de formación docente universitaria</td><td><?= salud_f($evaluacion['puntuacion_2c'] ?? 0) ?></td><td>3</td></tr>
                    <tr><td><strong>2.D</strong> Material docente, proyectos y contribuciones al EEES</td><td><?= salud_f($evaluacion['puntuacion_2d'] ?? 0) ?></td><td>7</td></tr>

                    <tr><td colspan="3" style="background:#f8fafc;"><strong>Bloque 3 · Formación académica y experiencia profesional</strong></td></tr>

                    <tr><td><strong>3.A</strong> Tesis, becas, estancias, otros títulos</td><td><?= salud_f($evaluacion['puntuacion_3a'] ?? 0) ?></td><td>6</td></tr>
                    <tr><td><strong>3.B</strong> Trabajo en empresas / instituciones / hospitales</td><td><?= salud_f($evaluacion['puntuacion_3b'] ?? 0) ?></td><td>2</td></tr>

                    <tr><td colspan="3" style="background:#f8fafc;"><strong>Bloque 4</strong></td></tr>

                    <tr><td><strong>4</strong> Otros méritos</td><td><?= salud_f($evaluacion['puntuacion_4'] ?? ($evaluacion['bloque_4'] ?? 0)) ?></td><td>2</td></tr>
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
                    <p><strong>Perfil detectado:</strong> <?= salud_h((string)$diagnostico['perfil_detectado']) ?></p>
                <?php endif; ?>

                <?php if (!empty($diagnostico['fortalezas']) && is_array($diagnostico['fortalezas'])): ?>
                    <div>
                        <strong>Fortalezas</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['fortalezas'] as $item): ?>
                                <li><?= salud_h((string)$item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($diagnostico['debilidades']) && is_array($diagnostico['debilidades'])): ?>
                    <div>
                        <strong>Debilidades</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['debilidades'] as $item): ?>
                                <li><?= salud_h((string)$item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($diagnostico['alertas']) && is_array($diagnostico['alertas'])): ?>
                    <div>
                        <strong>Alertas</strong>
                        <ul class="lista-simple">
                            <?php foreach ($diagnostico['alertas'] as $item): ?>
                                <li><?= salud_h((string)$item) ?></li>
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
                                    <?= salud_h((string)($regla['nombre'] ?? 'Regla')) ?>:
                                    actual <?= salud_f($regla['valor_actual'] ?? 0) ?> /
                                    objetivo <?= salud_f($regla['objetivo'] ?? 0) ?>
                                    <?php if (isset($regla['deficit'])): ?>
                                        · déficit <?= salud_f($regla['deficit']) ?>
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
                    <p><?= salud_h((string)$asesor['resumen']) ?></p>
                <?php endif; ?>

                <?php if (!empty($asesor['acciones']) && is_array($asesor['acciones'])): ?>
                    <div class="acciones-asesor">
                        <?php foreach ($asesor['acciones'] as $accion): ?>
                            <div class="accion-item">
                                <div><strong><?= salud_h((string)($accion['titulo'] ?? 'Acción')) ?></strong></div>
                                <?php if (!empty($accion['detalle'])): ?>
                                    <div style="margin-top:6px;"><?= salud_h((string)$accion['detalle']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($accion['impacto_estimado'])): ?>
                                    <div class="maximo" style="margin-top:8px;">
                                        Impacto estimado: <?= salud_h((string)$accion['impacto_estimado']) ?>
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
                                    <div><strong><?= salud_h((string)($sim['escenario'] ?? 'Escenario')) ?></strong></div>
                                    <?php if (!empty($sim['efecto_estimado'])): ?>
                                        <div style="margin-top:6px;"><?= salud_h((string)$sim['efecto_estimado']) ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($sim['nuevo_b1_b2_aprox'])): ?>
                                        <div class="maximo" style="margin-top:8px;">
                                            Nuevo 1+2 aprox.: <?= salud_f($sim['nuevo_b1_b2_aprox']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($sim['nuevo_total_aprox'])): ?>
                                        <div class="maximo">
                                            Nuevo total aprox.: <?= salud_f($sim['nuevo_total_aprox']) ?>
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
                <pre><?= salud_h(salud_json_pretty($jsonEntrada)) ?></pre>
            </details>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen técnico</h2>
            <div class="kpis">
                <div class="kpi">
                    <span class="label">1A</span>
                    <strong><?= salud_f($evaluacion['puntuacion_1a'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">1C</span>
                    <strong><?= salud_f($evaluacion['puntuacion_1c'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">1D</span>
                    <strong><?= salud_f($evaluacion['puntuacion_1d'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">2A</span>
                    <strong><?= salud_f($evaluacion['puntuacion_2a'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">2B</span>
                    <strong><?= salud_f($evaluacion['puntuacion_2b'] ?? 0) ?></strong>
                </div>
                <div class="kpi">
                    <span class="label">3A</span>
                    <strong><?= salud_f($evaluacion['puntuacion_3a'] ?? 0) ?></strong>
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

<?php salud_render_layout_end(); ?>