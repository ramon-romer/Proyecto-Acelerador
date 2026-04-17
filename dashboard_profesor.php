<?php
/**
 * dashboard_profesor.php
 * Muestra el expediente de evaluación ANECA de un profesor.
 *
 * Parámetros GET:
 *   nombre  – nombre del candidato (búsqueda parcial)
 *   rama    – rama del profesor (SALUD, TECNICA, HUMANIDADES, ...)
 *             Si no se indica, intenta obtenerse desde la sesión ($_SESSION['rama_usuario'])
 */

session_start();
error_reporting(0);

$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : null;

// ── Determinar la rama para elegir la BD correcta ─────────────────────────
$ramaRaw = $_GET['rama'] ?? $_SESSION['rama_usuario'] ?? 'SALUD';
$ramaNorm = strtoupper(trim($ramaRaw));

// Normalizar tildes: TÉCNICA → TECNICA
$ramaNorm = iconv('UTF-8', 'ASCII//TRANSLIT', $ramaNorm);
$ramaNorm = preg_replace('/[^A-Z]/', '', $ramaNorm);

// Mapa rama → nombre de base de datos
$mapaDB = [
    'CSYJ'          => 'evaluador_aneca_csyj',
    'EXPERIMENTALES'=> 'evaluador_aneca_experimentales',
    'HUMANIDADES'   => 'evaluador_aneca_humanidades',
    'SALUD'         => 'evaluador_aneca_salud',
    'TECNICA'       => 'evaluador_aneca_tecnicas',
    'TECNICAS'      => 'evaluador_aneca_tecnicas',
];

$dbName = $mapaDB[$ramaNorm] ?? 'evaluador_aneca_salud';

// ── Conexión PDO usando el host del contenedor Docker ─────────────────────
$dbHost = getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos');
$dbUser = getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'usuario_web');
$dbPass = getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'password_segura');
$dbPort = (int)(getenv('ACELERADOR_DB_PORT') ?: getenv('DB_PORT') ?: 3306);

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    // Mostramos error amigable sin exponer datos sensibles
    $eval = null;
    $dbError = "No se pudo conectar a la base de datos de evaluaciones ({$dbName}). Comprueba que el servicio está activo.";
}

if (isset($pdo)) {
    if ($nombre) {
        $stmt = $pdo->prepare("
            SELECT * FROM evaluaciones
            WHERE nombre_candidato LIKE :nombre
            ORDER BY fecha_creacion DESC
            LIMIT 1
        ");
        $stmt->execute(['nombre' => '%' . $nombre . '%']);
    } else {
        $stmt = $pdo->query("
            SELECT * FROM evaluaciones
            ORDER BY fecha_creacion DESC
            LIMIT 1
        ");
    }
    $eval = $stmt->fetch();
}

// ── Funciones de análisis ────────────────────────────────────────────────
function estadoEstimado(array $e): string {
    if ((float)$e['total_final'] >= 70) return 'Avanzado';
    if ((float)$e['total_final'] >= 50) return 'Cerca';
    return 'Lejos';
}

function bloquesCumplidos(array $e): int {
    $cumplidos = 0;
    foreach (['bloque_1','bloque_2','bloque_3','bloque_4'] as $b) {
        if ((float)($e[$b] ?? 0) > 0) $cumplidos++;
    }
    return $cumplidos;
}

function bloqueDebil(array $e): string {
    $bloques = [
        'Bloque 1' => (float)($e['bloque_1'] ?? 0),
        'Bloque 2' => (float)($e['bloque_2'] ?? 0),
        'Bloque 3' => (float)($e['bloque_3'] ?? 0),
        'Bloque 4' => (float)($e['bloque_4'] ?? 0),
    ];
    asort($bloques);
    return array_key_first($bloques);
}

function recomendaciones(array $e): array {
    $out = [];
    if ((float)($e['bloque_1'] ?? 0) < (float)($e['bloque_2'] ?? 0))
        $out[] = "Reforzar investigación y transferencia.";
    if ((float)($e['bloque_2'] ?? 0) < 5)
        $out[] = "Mejorar formación y experiencia profesional.";
    if ((float)($e['bloque_3'] ?? 0) < 3)
        $out[] = "Aumentar docencia o evidencias docentes.";
    if ((float)($e['bloque_4'] ?? 0) < 3)
        $out[] = "Incrementar otros méritos relevantes.";
    if (isset($e['cumple_regla_1']) && (int)$e['cumple_regla_1'] === 0)
        $out[] = "No cumple la regla 1.";
    if (isset($e['cumple_regla_2']) && (int)$e['cumple_regla_2'] === 0)
        $out[] = "No cumple la regla 2.";
    return $out ?: ["Expediente equilibrado en términos generales."];
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
      --text-main: #123b72;
      --bg-page: #eef2f7;
      --card-border: #d9e2ee;
      --soft-orange: #d8921d;
    }
    body { background: var(--bg-page); font-family: "Segoe UI", sans-serif; }
    .top-header {
      background: linear-gradient(180deg, var(--primary) 0%, #154a97 100%);
      color: #fff; padding: 20px 0;
    }
    .dashboard-card {
      background: #fff;
      border: 1px solid var(--card-border);
      border-radius: 18px;
      box-shadow: 0 4px 16px rgba(18,63,130,.05);
      height: 100%;
    }
    .stat-card { padding: 18px; }
    .stat-label { font-size:.9rem; text-transform:uppercase; color:#708198; margin-bottom:.4rem; }
    .stat-value { font-size:2rem; font-weight:800; color:var(--text-main); }
    .summary-card { padding: 20px; }
    .big-percent { font-size:3rem; font-weight:800; color:var(--soft-orange); line-height:1; }
    .custom-progress {
      height:12px; background:#dfe7f2; border-radius:999px; overflow:hidden; margin:14px 0;
    }
    .custom-progress-bar {
      height:100%;
      background: linear-gradient(90deg, #d8921d 0%, #d69522 100%);
    }
    .badge-rama {
      background: rgba(18,63,130,.12);
      color: var(--primary);
      font-size:.8rem;
      border-radius: 20px;
      padding: 4px 12px;
    }
  </style>
</head>
<body>

<header class="top-header">
  <div class="container">
    <?php if (!empty($eval)): ?>
      <h1 class="h2 mb-1"><?= htmlspecialchars($eval['nombre_candidato']) ?></h1>
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span><?= htmlspecialchars($eval['area'] ?? '') ?> · Categoría: <?= htmlspecialchars($eval['categoria'] ?? '') ?></span>
        <span class="badge-rama">📚 Rama: <?= htmlspecialchars(strtoupper($ramaNorm)) ?> (BD: <?= htmlspecialchars($dbName) ?>)</span>
      </div>
    <?php elseif (isset($dbError)): ?>
      <h1 class="h2 mb-1">Error de conexión</h1>
    <?php else: ?>
      <h1 class="h2 mb-1">Evaluación no encontrada</h1>
    <?php endif; ?>
  </div>
</header>

<main class="py-4">
  <div class="container">
    <?php if (isset($dbError)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($dbError) ?></div>
    <?php elseif (!empty($eval)): ?>

      <!-- Stat cards -->
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

      <!-- Resumen + Bloques -->
      <div class="row g-3 mb-4">
        <div class="col-lg-8">
          <div class="dashboard-card summary-card">
            <h2 class="h3 mb-3" style="color:#123b72;">Resumen de progreso</h2>
            <div class="big-percent"><?= number_format((float)$eval['total_final'], 2) ?>%</div>
            <div class="custom-progress">
              <div class="custom-progress-bar" style="width:<?= min(100, max(0, (float)$eval['total_final'])) ?>%;"></div>
            </div>
            <p class="mb-1"><strong>Resultado:</strong> <?= htmlspecialchars($eval['resultado'] ?? '-') ?></p>
            <p class="mb-1"><strong>Regla 1:</strong> <?= (int)($eval['cumple_regla_1'] ?? 0) ? 'Sí ✅' : 'No ❌' ?></p>
            <p class="mb-0"><strong>Regla 2:</strong> <?= (int)($eval['cumple_regla_2'] ?? 0) ? 'Sí ✅' : 'No ❌' ?></p>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="dashboard-card summary-card">
            <h3 class="h5 mb-3" style="color:#123b72;">Bloques</h3>
            <p class="mb-2"><strong>Bloque 1:</strong> <?= number_format((float)($eval['bloque_1'] ?? 0), 2) ?></p>
            <p class="mb-2"><strong>Bloque 2:</strong> <?= number_format((float)($eval['bloque_2'] ?? 0), 2) ?></p>
            <p class="mb-2"><strong>Bloque 3:</strong> <?= number_format((float)($eval['bloque_3'] ?? 0), 2) ?></p>
            <p class="mb-0"><strong>Bloque 4:</strong> <?= number_format((float)($eval['bloque_4'] ?? 0), 2) ?></p>
          </div>
        </div>
      </div>

      <!-- Recomendaciones -->
      <div class="dashboard-card p-4">
        <h3 class="h5 mb-3" style="color:#123b72;">Recomendaciones automáticas</h3>
        <ul class="mb-0">
          <?php foreach (recomendaciones($eval) as $rec): ?>
            <li><?= htmlspecialchars($rec) ?></li>
          <?php endforeach; ?>
        </ul>
        <hr>
        <small class="text-muted">Última actualización: <?= htmlspecialchars($eval['fecha_creacion'] ?? '-') ?></small>
      </div>

    <?php else: ?>
      <div class="alert alert-warning">
        No se ha encontrado ninguna evaluación para "<strong><?= htmlspecialchars($nombre ?? 'sin nombre') ?></strong>" en la base de datos <strong><?= htmlspecialchars($dbName) ?></strong>.
      </div>
    <?php endif; ?>
  </div>
</main>

</body>
</html>