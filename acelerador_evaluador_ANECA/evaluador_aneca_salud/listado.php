<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$sql = "SELECT id, nombre_candidato, area, categoria, total_final, resultado, fecha_creacion
        FROM evaluaciones
        ORDER BY id DESC";

$evaluaciones = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de evaluaciones</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f7f7f7; }
        .contenedor { max-width: 1100px; margin: auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        th { background: #efefef; }
        a { color: #1f6feb; text-decoration: none; }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>Listado de evaluaciones - Salud</h1>
    <p><a href="index.php">Nueva evaluación</a></p>

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
                <td><?= htmlspecialchars((string)$fila['id']) ?></td>
                <td><?= htmlspecialchars($fila['nombre_candidato']) ?></td>
                <td><?= htmlspecialchars($fila['area']) ?></td>
                <td><?= htmlspecialchars($fila['categoria']) ?></td>
                <td><?= htmlspecialchars((string)$fila['total_final']) ?></td>
                <td><?= htmlspecialchars($fila['resultado']) ?></td>
                <td><?= htmlspecialchars($fila['fecha_creacion']) ?></td>
                <td><a href="ver_evaluacion.php?id=<?= urlencode((string)$fila['id']) ?>">Abrir</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>