<?php
declare(strict_types=1);

/**
 * ============================================================
 * VER UNA EVALUACIÓN CONCRETA
 * ============================================================
 *
 * Muestra el detalle de una evaluación guardada,
 * incluyendo el JSON original.
 */

require __DIR__ . '/config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('ID no válido.');
}

$sql = "SELECT * FROM evaluaciones WHERE id = :id LIMIT 1";
$sentencia = $pdo->prepare($sql);
$sentencia->execute([':id' => $id]);

$evaluacion = $sentencia->fetch();

if (!$evaluacion) {
    die('Evaluación no encontrada.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver evaluación</title>
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
        }
        th {
            background: #efefef;
        }
        pre {
            white-space: pre-wrap;
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
        }
        a {
            color: #1f6feb;
            text-decoration: none;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>Evaluación #<?= htmlspecialchars((string)$evaluacion['id']) ?></h1>

    <p><strong>Candidato:</strong> <?= htmlspecialchars($evaluacion['nombre_candidato']) ?></p>
    <p><strong>Resultado:</strong> <?= htmlspecialchars($evaluacion['resultado']) ?></p>
    <p><strong>Fecha:</strong> <?= htmlspecialchars($evaluacion['fecha_creacion']) ?></p>

    <table>
        <tr><th>Concepto</th><th>Puntuación</th></tr>
        <tr><td>1.A</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1a']) ?></td></tr>
        <tr><td>1.B</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1b']) ?></td></tr>
        <tr><td>1.C</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1c']) ?></td></tr>
        <tr><td>1.D</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1d']) ?></td></tr>
        <tr><td>1.E</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1e']) ?></td></tr>
        <tr><td>1.F</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1f']) ?></td></tr>
        <tr><td>1.G</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_1g']) ?></td></tr>
        <tr><td><strong>Bloque 1</strong></td><td><strong><?= htmlspecialchars((string)$evaluacion['bloque_1']) ?></strong></td></tr>

        <tr><td>2.A</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_2a']) ?></td></tr>
        <tr><td>2.B</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_2b']) ?></td></tr>
        <tr><td>2.C</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_2c']) ?></td></tr>
        <tr><td>2.D</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_2d']) ?></td></tr>
        <tr><td><strong>Bloque 2</strong></td><td><strong><?= htmlspecialchars((string)$evaluacion['bloque_2']) ?></strong></td></tr>

        <tr><td>3.A</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_3a']) ?></td></tr>
        <tr><td>3.B</td><td><?= htmlspecialchars((string)$evaluacion['puntuacion_3b']) ?></td></tr>
        <tr><td><strong>Bloque 3</strong></td><td><strong><?= htmlspecialchars((string)$evaluacion['bloque_3']) ?></strong></td></tr>

        <tr><td><strong>Bloque 4</strong></td><td><strong><?= htmlspecialchars((string)$evaluacion['bloque_4']) ?></strong></td></tr>
        <tr><td><strong>Total B1 + B2</strong></td><td><strong><?= htmlspecialchars((string)$evaluacion['total_b1_b2']) ?></strong></td></tr>
        <tr><td><strong>Total final</strong></td><td><strong><?= htmlspecialchars((string)$evaluacion['total_final']) ?></strong></td></tr>
    </table>

    <h2>JSON de entrada</h2>
    <pre><?= htmlspecialchars($evaluacion['json_entrada']) ?></pre>

    <p><a href="listado.php">Volver al listado</a></p>
    <p><a href="index.php">Nueva evaluación</a></p>
</div>
</body>
</html>
