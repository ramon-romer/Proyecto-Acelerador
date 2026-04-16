/*Qué parte del dashboard original sí queda ya cubierta

Con estas dos páginas ya tienes:

En tutor
cabecera real del tutor
total de grupos
total de profesores asignados
listado de profesores
acceso al detalle del profesor.
En profesor
nombre del candidato
área
categoría
total final
resultado
reglas
bloques 1 a 4
fecha de evaluación.
4. Qué te falta para tener el dashboard tutor “perfecto”

Ahora mismo, para que el tutor vea en su lista de profesores algo como:

porcentaje global
estado
bloque más débil
casos prioritarios

necesitas poder enlazar cada profesor de tbl_profesor con una fila de evaluaciones. Eso hoy no está resuelto porque tbl_profesor tiene id_profesor y ORCID, mientras que evaluaciones solo guarda nombre_candidato.

La mejora mínima sería:

ALTER TABLE evaluaciones
ADD COLUMN id_profesor INT NULL;

o, mejor aún si quieres coherencia académica:

ALTER TABLE evaluaciones
ADD COLUMN ORCID VARCHAR(19) NULL;

Con eso ya se puede regenerar el dashboard del tutor con métricas reales de cada profesor.

5. Recomendación práctica

Ahora mismo yo haría esto:

usar dashboard_tutor.php con acelerador
usar dashboard_profesor.php con evaluador_aneca_salud_v2
después añadir una clave común y fusionar ambos

Eso te permite avanzar ya sin romper el modelo actual.*/





<?php
$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

$pdo = new PDO(
    "mysql:host=localhost;dbname=evaluador_aneca_salud_v2;charset=utf8mb4",
    "root",
    "",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE id = :id");
    $stmt->execute(['id' => $id]);
} elseif ($nombre) {
    $stmt = $pdo->prepare("
        SELECT * FROM evaluaciones
        WHERE nombre_candidato = :nombre
        ORDER BY fecha_creacion DESC
        LIMIT 1
    ");
    $stmt->execute(['nombre' => $nombre]);
} else {
    $stmt = $pdo->query("
        SELECT * FROM evaluaciones
        ORDER BY fecha_creacion DESC
        LIMIT 1
    ");
}

$eval = $stmt->fetch();

function estadoEstimado(array $e): string {
    if ((float)$e['total_final'] >= 70) return 'Avanzado';
    if ((float)$e['total_final'] >= 50) return 'Cerca';
    return 'Lejos';
}

function bloquesCumplidos(array $e): int {
    $bloques = [
        (float)$e['bloque_1'],
        (float)$e['bloque_2'],
        (float)$e['bloque_3'],
        (float)$e['bloque_4'],
    ];

    $cumplidos = 0;
    foreach ($bloques as $b) {
        if ($b > 0) $cumplidos++;
    }
    return $cumplidos;
}

function bloqueDebil(array $e): string {
    $bloques = [
        'Bloque 1' => (float)$e['bloque_1'],
        'Bloque 2' => (float)$e['bloque_2'],
        'Bloque 3' => (float)$e['bloque_3'],
        'Bloque 4' => (float)$e['bloque_4'],
    ];

    asort($bloques);
    return array_key_first($bloques);
}

function recomendaciones(array $e): array {
    $out = [];

    if ((float)$e['bloque_1'] < (float)$e['bloque_2']) {
        $out[] = "Reforzar investigación y transferencia.";
    }
    if ((float)$e['bloque_2'] < 5) {
        $out[] = "Mejorar formación y experiencia profesional.";
    }
    if ((float)$e['bloque_3'] < 3) {
        $out[] = "Aumentar docencia o evidencias docentes.";
    }
    if ((float)$e['bloque_4'] < 3) {
        $out[] = "Incrementar otros méritos relevantes.";
    }
    if ((int)$e['cumple_regla_1'] === 0) {
        $out[] = "No cumple la regla 1.";
    }
    if ((int)$e['cumple_regla_2'] === 0) {
        $out[] = "No cumple la regla 2.";
    }

    if (!$out) {
        $out[] = "Expediente equilibrado en términos generales.";
    }

    return $out;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Profesor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #123f82;
      --primary-dark: #0f356d;
      --text-main: #123b72;
      --bg-page: #eef2f7;
      --card-border: #d9e2ee;
      --soft-orange: #d8921d;
    }
    body {
      background: var(--bg-page);
      font-family: "Segoe UI", sans-serif;
    }
    .top-header {
      background: linear-gradient(180deg, var(--primary) 0%, #154a97 100%);
      color: #fff;
      padding: 20px 0;
    }
    .dashboard-card {
      background: #fff;
      border: 1px solid var(--card-border);
      border-radius: 18px;
      box-shadow: 0 4px 16px rgba(18, 63, 130, 0.05);
      height: 100%;
    }
    .stat-card { padding: 18px; }
    .stat-label {
      font-size: .9rem;
      text-transform: uppercase;
      color: #708198;
      margin-bottom: .4rem;
    }
    .stat-value {
      font-size: 2rem;
      font-weight: 800;
      color: var(--text-main);
    }
    .summary-card { padding: 20px; }
    .big-percent {
      font-size: 3rem;
      font-weight: 800;
      color: var(--soft-orange);
      line-height: 1;
    }
    .custom-progress {
      height: 12px;
      background: #dfe7f2;
      border-radius: 999px;
      overflow: hidden;
      margin: 14px 0;
    }
    .custom-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #d8921d 0%, #d69522 100%);
    }
  </style>
</head>
<body>

<header class="top-header">
  <div class="container">
    <?php if ($eval): ?>
      <h1 class="h2 mb-1"><?= htmlspecialchars($eval['nombre_candidato']) ?></h1>
      <div><?= htmlspecialchars($eval['area']) ?> · Categoría actual: <?= htmlspecialchars($eval['categoria']) ?></div>
    <?php else: ?>
      <h1 class="h2 mb-1">Evaluación no encontrada</h1>
    <?php endif; ?>
  </div>
</header>

<main class="py-4">
  <div class="container">
    <?php if ($eval): ?>
      <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card">
            <div class="stat-label">Cumplimiento global</div>
            <div class="stat-value"><?= number_format((float)$eval['total_final'], 2) ?>%</div>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card">
            <div class="stat-label">Estado estimado</div>
            <div class="stat-value"><?= estadoEstimado($eval) ?></div>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card">
            <div class="stat-label">Bloques cumplidos</div>
            <div class="stat-value"><?= bloquesCumplidos($eval) ?> / 4</div>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card">
            <div class="stat-label">Bloque más débil</div>
            <div class="stat-value" style="font-size:1.2rem;"><?= bloqueDebil($eval) ?></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-lg-8">
          <div class="dashboard-card summary-card">
            <h2 class="h3 mb-3" style="color:#123b72;">Resumen de progreso</h2>
            <div class="big-percent"><?= number_format((float)$eval['total_final'], 2) ?>%</div>
            <div class="custom-progress">
              <div class="custom-progress-bar" style="width:<?= min(100, max(0, (float)$eval['total_final'])) ?>%;"></div>
            </div>
            <p class="mb-1"><strong>Resultado:</strong> <?= htmlspecialchars($eval['resultado']) ?></p>
            <p class="mb-1"><strong>Regla 1:</strong> <?= (int)$eval['cumple_regla_1'] ? 'Sí' : 'No' ?></p>
            <p class="mb-0"><strong>Regla 2:</strong> <?= (int)$eval['cumple_regla_2'] ? 'Sí' : 'No' ?></p>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="dashboard-card summary-card">
            <h3 class="h5 mb-3" style="color:#123b72;">Bloques</h3>
            <p class="mb-2"><strong>Bloque 1:</strong> <?= number_format((float)$eval['bloque_1'], 2) ?></p>
            <p class="mb-2"><strong>Bloque 2:</strong> <?= number_format((float)$eval['bloque_2'], 2) ?></p>
            <p class="mb-2"><strong>Bloque 3:</strong> <?= number_format((float)$eval['bloque_3'], 2) ?></p>
            <p class="mb-0"><strong>Bloque 4:</strong> <?= number_format((float)$eval['bloque_4'], 2) ?></p>
          </div>
        </div>
      </div>

      <div class="dashboard-card p-4">
        <h3 class="h5 mb-3" style="color:#123b72;">Recomendaciones automáticas</h3>
        <ul class="mb-0">
          <?php foreach (recomendaciones($eval) as $rec): ?>
            <li><?= htmlspecialchars($rec) ?></li>
          <?php endforeach; ?>
        </ul>
        <hr>
        <small class="text-muted">
          Última actualización: <?= htmlspecialchars($eval['fecha_creacion']) ?>
        </small>
      </div>
    <?php else: ?>
      <div class="alert alert-warning">No se ha encontrado ninguna evaluación.</div>
    <?php endif; ?>
  </div>
</main>

</body>
</html>