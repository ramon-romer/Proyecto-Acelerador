<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/ui.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('ID de evaluación no válido.');
}

$stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE id = :id");
$stmt->execute([':id' => $id]);
$evaluacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$evaluacion) {
    die('No se encontró la evaluación.');
}

function v(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function vf(mixed $value): string
{
    return number_format((float)$value, 2, ',', '.');
}

$resultado = strtoupper((string)($evaluacion['resultado'] ?? 'NEGATIVA'));
$claseResultado = ($resultado === 'POSITIVA') ? 'ok' : 'bad';

$bloques = [
    [
        'titulo' => 'Bloque 1. Investigación',
        'items' => [
            '1.A Publicaciones científicas' => $evaluacion['puntuacion_1a'] ?? 0,
            '1.B Libros y capítulos' => $evaluacion['puntuacion_1b'] ?? 0,
            '1.C Proyectos y contratos de investigación' => $evaluacion['puntuacion_1c'] ?? 0,
            '1.D Transferencia' => $evaluacion['puntuacion_1d'] ?? 0,
            '1.E Dirección de tesis doctorales' => $evaluacion['puntuacion_1e'] ?? 0,
            '1.F Congresos, conferencias y seminarios' => $evaluacion['puntuacion_1f'] ?? 0,
            '1.G Otros méritos de investigación' => $evaluacion['puntuacion_1g'] ?? 0,
        ],
        'total' => $evaluacion['bloque_1'] ?? 0,
        'maximo' => 60,
    ],
    [
        'titulo' => 'Bloque 2. Experiencia docente',
        'items' => [
            '2.A Docencia universitaria' => $evaluacion['puntuacion_2a'] ?? 0,
            '2.B Evaluaciones sobre la docencia' => $evaluacion['puntuacion_2b'] ?? 0,
            '2.C Formación docente' => $evaluacion['puntuacion_2c'] ?? 0,
            '2.D Material docente / innovación / EEES' => $evaluacion['puntuacion_2d'] ?? 0,
        ],
        'total' => $evaluacion['bloque_2'] ?? 0,
        'maximo' => 30,
    ],
    [
        'titulo' => 'Bloque 3. Formación académica y experiencia profesional',
        'items' => [
            '3.A Formación académica' => $evaluacion['puntuacion_3a'] ?? 0,
            '3.B Experiencia profesional' => $evaluacion['puntuacion_3b'] ?? 0,
        ],
        'total' => $evaluacion['bloque_3'] ?? 0,
        'maximo' => 8,
    ],
    [
        'titulo' => 'Bloque 4. Otros méritos',
        'items' => [
            '4. Otros méritos' => $evaluacion['bloque_4'] ?? 0,
        ],
        'total' => $evaluacion['bloque_4'] ?? 0,
        'maximo' => 2,
    ],
];

$jsonEntrada = $evaluacion['json_entrada'] ?? '';
$jsonBonito = $jsonEntrada;

$decodificado = json_decode((string)$jsonEntrada, true);
if (is_array($decodificado)) {
    $jsonBonito = json_encode($decodificado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($jsonBonito === false) {
        $jsonBonito = $jsonEntrada;
    }
}

hum_render_layout_start(
    'Resultado de evaluación',
    'Detalle completo del expediente evaluado en Humanidades.',
    [
        ['label' => 'Portal ANECA', 'url' => hum_portal_url()],
        ['label' => 'Humanidades', 'url' => hum_index_url()],
        ['label' => 'Resultado evaluación'],
    ],
    [
        ['label' => 'Volver a Humanidades', 'url' => hum_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => hum_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .resultado-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        border-radius: 999px;
        font-weight: 700;
        letter-spacing: .3px;
        font-size: 14px;
    }
    .resultado-pill.ok {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .resultado-pill.bad {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
    }
    .resumen-superior {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
    }
    .resumen-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 16px;
    }
    .resumen-card .label {
        display: block;
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    .resumen-card .value {
        font-size: 28px;
        font-weight: 700;
        color: #111827;
    }
    .tabla-bloque {
        width: 100%;
        border-collapse: collapse;
    }
    .tabla-bloque th,
    .tabla-bloque td {
        border-bottom: 1px solid #e5e7eb;
        padding: 10px 8px;
        text-align: left;
    }
    .tabla-bloque th:last-child,
    .tabla-bloque td:last-child {
        text-align: right;
        white-space: nowrap;
    }
    .tabla-bloque tfoot td {
        font-weight: 700;
        background: #f9fafb;
    }
    .reglas {
        display: grid;
        gap: 10px;
    }
    .regla {
        border-radius: 12px;
        padding: 12px 14px;
        border: 1px solid #e5e7eb;
        background: #fff;
    }
    .regla.ok {
        border-color: #86efac;
        background: #f0fdf4;
        color: #166534;
    }
    .regla.bad {
        border-color: #fca5a5;
        background: #fef2f2;
        color: #991b1b;
    }
    pre.json-box {
        background: #0f172a;
        color: #e5e7eb;
        padding: 16px;
        border-radius: 14px;
        overflow: auto;
        font-size: 13px;
        line-height: 1.5;
    }
</style>

<section class="stack">
    <section class="card">
        <div class="split" style="align-items:center;">
            <div class="stack" style="gap:10px;">
                <h2 style="margin:0;">Evaluación de <?= v($evaluacion['nombre_candidato'] ?? '') ?></h2>
                <p class="muted" style="margin:0;">
                    Área: <strong>Humanidades</strong> · Categoría: <strong><?= v($evaluacion['categoria'] ?? 'PCD/PUP') ?></strong>
                </p>
            </div>
            <div class="resultado-pill <?= $claseResultado ?>">
                Resultado: <?= v($resultado) ?>
            </div>
        </div>
    </section>

    <section class="resumen-superior">
        <div class="resumen-card">
            <span class="label">Bloque 1 + 2</span>
            <div class="value"><?= vf($evaluacion['total_b1_b2'] ?? 0) ?></div>
        </div>
        <div class="resumen-card">
            <span class="label">Total final</span>
            <div class="value"><?= vf($evaluacion['total_final'] ?? 0) ?></div>
        </div>
        <div class="resumen-card">
            <span class="label">Bloque 1</span>
            <div class="value"><?= vf($evaluacion['bloque_1'] ?? 0) ?></div>
        </div>
        <div class="resumen-card">
            <span class="label">Bloque 2</span>
            <div class="value"><?= vf($evaluacion['bloque_2'] ?? 0) ?></div>
        </div>
        <div class="resumen-card">
            <span class="label">Bloque 3</span>
            <div class="value"><?= vf($evaluacion['bloque_3'] ?? 0) ?></div>
        </div>
        <div class="resumen-card">
            <span class="label">Bloque 4</span>
            <div class="value"><?= vf($evaluacion['bloque_4'] ?? 0) ?></div>
        </div>
    </section>

    <section class="card">
        <h2>Comprobación de reglas</h2>
        <div class="reglas">
            <div class="regla <?= ((int)($evaluacion['cumple_regla_1'] ?? 0) === 1) ? 'ok' : 'bad' ?>">
                Regla 1: Bloque 1 + Bloque 2 ≥ 50 →
                <strong><?= ((int)($evaluacion['cumple_regla_1'] ?? 0) === 1) ? 'Cumple' : 'No cumple' ?></strong>
            </div>
            <div class="regla <?= ((int)($evaluacion['cumple_regla_2'] ?? 0) === 1) ? 'ok' : 'bad' ?>">
                Regla 2: Total final ≥ 55 →
                <strong><?= ((int)($evaluacion['cumple_regla_2'] ?? 0) === 1) ? 'Cumple' : 'No cumple' ?></strong>
            </div>
        </div>
    </section>

    <?php foreach ($bloques as $bloque): ?>
        <section class="card">
            <h2><?= v($bloque['titulo']) ?></h2>
            <table class="tabla-bloque">
                <thead>
                    <tr>
                        <th>Subapartado</th>
                        <th>Puntuación</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bloque['items'] as $nombre => $valor): ?>
                        <tr>
                            <td><?= v($nombre) ?></td>
                            <td><?= vf($valor) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total del bloque</td>
                        <td><?= vf($bloque['total']) ?> / <?= vf($bloque['maximo']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </section>
    <?php endforeach; ?>

    <section class="card">
        <details>
            <summary><strong>Ver JSON del expediente evaluado</strong></summary>
            <pre class="json-box"><?= v((string)$jsonBonito) ?></pre>
        </details>
    </section>
</section>

<?php hum_render_layout_end(); ?>