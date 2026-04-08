<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/funciones_evaluador_salud.php';

$nombre_candidato = trim($_POST['nombre_candidato'] ?? '');
$json_entrada = trim($_POST['json_entrada'] ?? '');

if ($nombre_candidato === '' || $json_entrada === '') {
    die('Faltan datos obligatorios.');
}

$datos = json_decode($json_entrada, true);

if (!is_array($datos)) {
    die('El JSON no es válido.');
}

$resultado = evaluar_expediente($datos);

$bloque_1 = $resultado['bloque_1'];
$bloque_2 = $resultado['bloque_2'];
$bloque_3 = $resultado['bloque_3'];
$bloque_4 = $resultado['bloque_4'];
$totales = $resultado['totales'];
$decision = $resultado['decision'];
$diagnostico = $resultado['diagnostico'];
$asesor = $resultado['asesor'];

/*
|--------------------------------------------------------------------------
| Máximos por apartado
|--------------------------------------------------------------------------
| Ajusta estos valores a la rúbrica real de Salud si fuera necesario.
*/
$maximos = [
    '1A' => 35,
    '1B' => 7,
    '1C' => 7,
    '1D' => 4,
    '1E' => 4,
    '1F' => 2,
    '1G' => 1,
    'B1' => 60,

    '2A' => 17,
    '2B' => 3,
    '2C' => 3,
    '2D' => 7,
    'B2' => 30,

    '3A' => 6,
    '3B' => 2,
    'B3' => 8,

    'B4' => 2,

    'TOTAL_B1_B2' => 90,
    'TOTAL_FINAL' => 100
];

$sql = "INSERT INTO evaluaciones (
    nombre_candidato, area, categoria, json_entrada,
    puntuacion_1a, puntuacion_1b, puntuacion_1c, puntuacion_1d, puntuacion_1e, puntuacion_1f, puntuacion_1g, bloque_1,
    puntuacion_2a, puntuacion_2b, puntuacion_2c, puntuacion_2d, bloque_2,
    puntuacion_3a, puntuacion_3b, bloque_3,
    bloque_4, total_b1_b2, total_final, resultado, cumple_regla_1, cumple_regla_2
) VALUES (
    :nombre_candidato, :area, :categoria, :json_entrada,
    :p1a, :p1b, :p1c, :p1d, :p1e, :p1f, :p1g, :b1,
    :p2a, :p2b, :p2c, :p2d, :b2,
    :p3a, :p3b, :b3,
    :b4, :total_b1_b2, :total_final, :resultado, :cumple_regla_1, :cumple_regla_2
)";

$sentencia = $pdo->prepare($sql);

$sentencia->execute([
    ':nombre_candidato' => $nombre_candidato,
    ':area' => 'Salud',
    ':categoria' => 'PCD/PUP',
    ':json_entrada' => $json_entrada,

    ':p1a' => $bloque_1['1A'],
    ':p1b' => $bloque_1['1B'],
    ':p1c' => $bloque_1['1C'],
    ':p1d' => $bloque_1['1D'],
    ':p1e' => $bloque_1['1E'],
    ':p1f' => $bloque_1['1F'],
    ':p1g' => $bloque_1['1G'],
    ':b1' => $bloque_1['B1'],

    ':p2a' => $bloque_2['2A'],
    ':p2b' => $bloque_2['2B'],
    ':p2c' => $bloque_2['2C'],
    ':p2d' => $bloque_2['2D'],
    ':b2' => $bloque_2['B2'],

    ':p3a' => $bloque_3['3A'],
    ':p3b' => $bloque_3['3B'],
    ':b3' => $bloque_3['B3'],

    ':b4' => $bloque_4['B4'],
    ':total_b1_b2' => $totales['total_b1_b2'],
    ':total_final' => $totales['total_final'],
    ':resultado' => $decision['resultado'],
    ':cumple_regla_1' => $decision['cumple_regla_1'] ? 1 : 0,
    ':cumple_regla_2' => $decision['cumple_regla_2'] ? 1 : 0,
]);

$id_evaluacion = (int)$pdo->lastInsertId();

function formatear_puntuacion(mixed $valor): string
{
    return number_format((float)$valor, 2, '.', '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resultado evaluación</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f7f7f7;
        }

        .contenedor {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #efefef;
        }

        td.num {
            text-align: right;
            white-space: nowrap;
            width: 140px;
        }

        .positivo {
            color: green;
            font-weight: bold;
        }

        .negativo {
            color: red;
            font-weight: bold;
        }

        a {
            color: #1f6feb;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>Resultado de la evaluación</h1>

    <p><strong>ID:</strong> <?= htmlspecialchars((string)$id_evaluacion) ?></p>
    <p><strong>Candidato:</strong> <?= htmlspecialchars($nombre_candidato) ?></p>
    <p>
        <strong>Resultado final:</strong>
        <span class="<?= $decision['positiva'] ? 'positivo' : 'negativo' ?>">
            <?= htmlspecialchars($decision['resultado']) ?>
        </span>
    </p>

    <table>
        <tr>
            <th>Concepto</th>
            <th>Puntuación</th>
            <th>Máximo</th>
        </tr>

        <tr>
            <td>1.A Publicaciones y patentes</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1A']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1A']) ?></td>
        </tr>
        <tr>
            <td>1.B Libros y capítulos</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1B']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1B']) ?></td>
        </tr>
        <tr>
            <td>1.C Proyectos y contratos</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1C']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1C']) ?></td>
        </tr>
        <tr>
            <td>1.D Transferencia</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1D']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1D']) ?></td>
        </tr>
        <tr>
            <td>1.E Tesis dirigidas</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1E']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1E']) ?></td>
        </tr>
        <tr>
            <td>1.F Congresos</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1F']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1F']) ?></td>
        </tr>
        <tr>
            <td>1.G Otros méritos investigación</td>
            <td class="num"><?= formatear_puntuacion($bloque_1['1G']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['1G']) ?></td>
        </tr>
        <tr>
            <td><strong>Bloque 1</strong></td>
            <td class="num"><strong><?= formatear_puntuacion($bloque_1['B1']) ?></strong></td>
            <td class="num"><strong><?= formatear_puntuacion($maximos['B1']) ?></strong></td>
        </tr>

        <tr>
            <td>2.A Docencia universitaria</td>
            <td class="num"><?= formatear_puntuacion($bloque_2['2A']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['2A']) ?></td>
        </tr>
        <tr>
            <td>2.B Evaluación docente</td>
            <td class="num"><?= formatear_puntuacion($bloque_2['2B']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['2B']) ?></td>
        </tr>
        <tr>
            <td>2.C Formación docente</td>
            <td class="num"><?= formatear_puntuacion($bloque_2['2C']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['2C']) ?></td>
        </tr>
        <tr>
            <td>2.D Material docente</td>
            <td class="num"><?= formatear_puntuacion($bloque_2['2D']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['2D']) ?></td>
        </tr>
        <tr>
            <td><strong>Bloque 2</strong></td>
            <td class="num"><strong><?= formatear_puntuacion($bloque_2['B2']) ?></strong></td>
            <td class="num"><strong><?= formatear_puntuacion($maximos['B2']) ?></strong></td>
        </tr>

        <tr>
            <td>3.A Formación académica</td>
            <td class="num"><?= formatear_puntuacion($bloque_3['3A']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['3A']) ?></td>
        </tr>
        <tr>
            <td>3.B Experiencia profesional</td>
            <td class="num"><?= formatear_puntuacion($bloque_3['3B']) ?></td>
            <td class="num"><?= formatear_puntuacion($maximos['3B']) ?></td>
        </tr>
        <tr>
            <td><strong>Bloque 3</strong></td>
            <td class="num"><strong><?= formatear_puntuacion($bloque_3['B3']) ?></strong></td>
            <td class="num"><strong><?= formatear_puntuacion($maximos['B3']) ?></strong></td>
        </tr>

        <tr>
            <td><strong>Bloque 4</strong></td>
            <td class="num"><strong><?= formatear_puntuacion($bloque_4['B4']) ?></strong></td>
            <td class="num"><strong><?= formatear_puntuacion($maximos['B4']) ?></strong></td>
        </tr>

        <tr>
            <td><strong>Total B1 + B2</strong></td>
            <td class="num"><strong><?= formatear_puntuacion($totales['total_b1_b2']) ?></strong></td>
            <td class="num"><strong><?= formatear_puntuacion($maximos['TOTAL_B1_B2']) ?></strong></td>
        </tr>
        <tr>
            <td><strong>Total final</strong></td>
            <td class="num"><strong><?= formatear_puntuacion($totales['total_final']) ?></strong></td>
            <td class="num"><strong><?= formatear_puntuacion($maximos['TOTAL_FINAL']) ?></strong></td>
        </tr>
    </table>

    <p>Regla 1 (B1 + B2 ≥ 50): <strong><?= $decision['cumple_regla_1'] ? 'Sí' : 'No' ?></strong></p>
    <p>Regla 2 (Total final ≥ 55): <strong><?= $decision['cumple_regla_2'] ? 'Sí' : 'No' ?></strong></p>

    <h2>Diagnóstico inteligente</h2>
    <p><strong>Perfil detectado:</strong> <?= htmlspecialchars($diagnostico['perfil_detectado']) ?></p>

    <table>
        <tr>
            <th>Regla</th>
            <th>Actual</th>
            <th>Objetivo</th>
            <th>Déficit</th>
            <th>Cumple</th>
        </tr>
        <?php foreach ($diagnostico['reglas'] as $regla): ?>
            <tr>
                <td><?= htmlspecialchars($regla['nombre']) ?></td>
                <td><?= htmlspecialchars((string)$regla['valor_actual']) ?></td>
                <td><?= htmlspecialchars((string)$regla['objetivo']) ?></td>
                <td><?= htmlspecialchars((string)$regla['deficit']) ?></td>
                <td><?= $regla['cumple'] ? 'Sí' : 'No' ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h3>Fortalezas detectadas</h3>
    <?php if (!empty($diagnostico['fortalezas'])): ?>
        <ul>
            <?php foreach ($diagnostico['fortalezas'] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No se detectan fortalezas especialmente marcadas.</p>
    <?php endif; ?>

    <h3>Debilidades detectadas</h3>
    <?php if (!empty($diagnostico['debilidades'])): ?>
        <ul>
            <?php foreach ($diagnostico['debilidades'] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No se detectan debilidades críticas.</p>
    <?php endif; ?>

    <h3>Alertas</h3>
    <?php if (!empty($diagnostico['alertas'])): ?>
        <ul>
            <?php foreach ($diagnostico['alertas'] as $item): ?>
                <li><?= htmlspecialchars($item) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Sin alertas relevantes.</p>
    <?php endif; ?>

    <h2>Asesor inteligente de carrera académica</h2>
    <p><?= htmlspecialchars($asesor['resumen']) ?></p>

    <h3>Plan de acción recomendado</h3>
    <ol>
        <?php foreach ($asesor['acciones'] as $accion): ?>
            <li>
                <strong><?= htmlspecialchars($accion['titulo']) ?></strong><br>
                <?= htmlspecialchars($accion['detalle']) ?><br>
                <em><?= htmlspecialchars($accion['impacto_estimado']) ?></em>
            </li>
        <?php endforeach; ?>
    </ol>

    <h3>Simulaciones orientativas</h3>
    <table>
        <tr>
            <th>Escenario</th>
            <th>Efecto estimado</th>
            <th>Nuevo B1+B2 aprox.</th>
            <th>Nuevo total aprox.</th>
        </tr>
        <?php foreach ($asesor['simulaciones'] as $sim): ?>
            <tr>
                <td><?= htmlspecialchars($sim['escenario']) ?></td>
                <td><?= htmlspecialchars($sim['efecto_estimado']) ?></td>
                <td><?= htmlspecialchars((string)$sim['nuevo_b1_b2_aprox']) ?></td>
                <td><?= htmlspecialchars((string)$sim['nuevo_total_aprox']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="index.php">Nueva evaluación</a></p>
    <p><a href="listado.php">Ver listado</a></p>
    <p><a href="ver_evaluacion.php?id=<?= urlencode((string)$id_evaluacion) ?>">Abrir esta evaluación</a></p>
</div>
</body>
</html>