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
    'CSYJ'                       => 'evaluador_aneca_csyj',
    'CIENCIASSOCIALESYJURIDICAS' => 'evaluador_aneca_csyj',
    'CIENCIASSOCIALESYJURIDICA'  => 'evaluador_aneca_csyj',
    'SOCIALES'                   => 'evaluador_aneca_csyj',
    'EXPERIMENTALES'             => 'evaluador_aneca_experimentales',
    'CIENCIASEXPERIMENTALES'     => 'evaluador_aneca_experimentales',
    'CIENCIAS'                   => 'evaluador_aneca_experimentales',
    'HUMANIDADES'                => 'evaluador_aneca_humanidades',
    'ARTESYHUMANIDADES'          => 'evaluador_aneca_humanidades',
    'ARTEYHUMANIDADES'           => 'evaluador_aneca_humanidades',
    'SALUD'                      => 'evaluador_aneca_salud',
    'CIENCIASDELASALUD'          => 'evaluador_aneca_salud',
    'TECNICA'                    => 'evaluador_aneca_tecnicas',
    'TECNICAS'                   => 'evaluador_aneca_tecnicas',
    'INGENIERIA'                 => 'evaluador_aneca_tecnicas',
    'INGENIERIAYARQUITECTURA'    => 'evaluador_aneca_tecnicas',
];

$dbName = $mapaDB[$ramaNorm] ?? 'evaluador_aneca_salud';

// ── Conexión PDO usando el host del contenedor Docker ─────────────────────
$dbHost = getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos');
$dbUser = getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'root');
$dbPass = getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'root_super_segura');
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
        $eval = $stmt->fetch();
        
        // Fallback si no encuentra por nombre (común si hay diferencias entre perfil y PDF extraído)
        if (!$eval) {
            $stmt = $pdo->query("
                SELECT * FROM evaluaciones
                ORDER BY fecha_creacion DESC
                LIMIT 1
            ");
            $eval = $stmt->fetch();
        }
    } else {
        $stmt = $pdo->query("
            SELECT * FROM evaluaciones
            ORDER BY fecha_creacion DESC
            LIMIT 1
        ");
        $eval = $stmt->fetch();
    }
}

// ── Extraer asesor orientativo del json_entrada ─────────────────────────
$asesorDash = [];
if (!empty($eval['json_entrada'])) {
    $jsonDash = json_decode((string)$eval['json_entrada'], true);
    if (is_array($jsonDash)) {
        $rcDash = $jsonDash['resultado_calculo'] ?? [];
        $asesorDash = is_array($rcDash['asesor'] ?? null) ? $rcDash['asesor'] : [];
    }
}
$esPositivaDash = strtoupper(trim((string)($eval['resultado'] ?? ''))) === 'POSITIVA';
$_totalFinalDash = (float)($eval['total_final'] ?? 0);
$colorDash       = $_totalFinalDash >= 70 ? '#16a34a' : ($_totalFinalDash >= 50 ? '#d97706' : '#dc2626');
$gradientDash    = $_totalFinalDash >= 70 ? 'linear-gradient(90deg,#16a34a,#22c55e)' : ($_totalFinalDash >= 50 ? 'linear-gradient(90deg,#d97706,#f59e0b)' : 'linear-gradient(90deg,#dc2626,#ef4444)');
$iconDash        = $_totalFinalDash >= 70 ? '&#9989;' : ($_totalFinalDash >= 50 ? '&#9888;' : '&#10060;');

// ── Funciones de análisis ────────────────────────────────────────────────
function estadoEstimado(array $e): string {
    if ((float)$e['total_final'] >= 70) return 'Muy sólida';
    if ((float)$e['total_final'] >= 50) return 'Sólida';
    return 'Insuficiente';
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
      position: relative;
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
    .popover-body {
      white-space: pre-line;
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
      <div class="row g-3 mb-4 w-100">
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card h-100">
            <div class="stat-label">Cumplimiento global</div>
            <div class="stat-value" style="color: <?= $colorDash ?>;">
              <?= number_format((float)$eval['total_final'], 2) ?>%
            </div>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card h-100">
            <div class="stat-label">Estado estimado</div>
            <div class="stat-value" style="color: <?= $colorDash ?>;"><?= estadoEstimado($eval) ?></div>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card h-100">
            <div class="stat-label">Bloques cumplidos</div>
            <div class="stat-value"><?= bloquesCumplidos($eval) ?> / 4</div>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="dashboard-card stat-card h-100">
            <div class="stat-label">Bloque más débil</div>
            <div class="stat-value" style="font-size:1.2rem;"><?= bloqueDebil($eval) ?></div>
          </div>
        </div>
      </div>

      <!-- Resumen + Bloques -->
      <div class="row g-3 mb-4">
        <div class="col-lg-8">
          <div class="dashboard-card summary-card">
            <h2 class="h3 mb-3" style="color: <?= $colorDash ?>;">Resumen de progreso</h2>
            <div class="big-percent" style="color: <?= $colorDash ?>;">
              <?= number_format((float)$eval['total_final'], 2) ?>%
              <span style="font-size:1.5rem; margin-left:10px;"><?= $iconDash ?></span>
            </div>
            <div class="custom-progress">
              <div class="custom-progress-bar" style="width:<?= min(100, max(0, (float)$eval['total_final'])) ?>%; background: <?= $gradientDash ?>;"></div>
            </div>
            <button type="button"
              style="position:absolute; top:12px; right:14px; background:none; border:none; cursor:pointer; font-size:1.1rem; color:#a0aec0; padding:0; line-height:1;"
              data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="left"
              data-bs-title="Código de colores"
              data-bs-content="● Verde (≥ 70 pts): expediente muy sólido.&#10;&#10;● Ámbar (50–70 pts): en zona intermedia.&#10;&#10;● Rojo (< 50 pts): por debajo del umbral.&#10;&#10;⚠️ La barra puede ser ámbar y la evaluación estar APROBADA si se cumplen las reglas: B1+B2 ≥ 50 y total ≥ 55."
              tabindex="0">ⓘ
            </button>
            <p class="mb-1"><strong>Resultado:</strong>
              <span style="color: <?= $esPositivaDash ? '#16a34a' : '#dc2626' ?>; font-weight:700;">
                <?= htmlspecialchars($eval['resultado'] ?? '-') ?>
              </span>
            </p>
            <p class="mb-1"><strong>Regla 1 (B1+B2 &ge; 50):</strong> <?= (int)($eval['cumple_regla_1'] ?? 0) ? '<span style="color:#16a34a">Sí &#9989;</span>' : '<span style="color:#dc2626">No &#10060;</span>' ?></p>
            <p class="mb-0"><strong>Regla 2 (total &ge; 55):</strong> <?= (int)($eval['cumple_regla_2'] ?? 0) ? '<span style="color:#16a34a">Sí &#9989;</span>' : '<span style="color:#dc2626">No &#10060;</span>' ?></p>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="dashboard-card summary-card">
            <h3 class="h5 mb-3" style="color: <?= $colorDash ?>;">Bloques</h3>
            <p class="mb-2"><strong>Bloque 1:</strong> <?= number_format((float)($eval['bloque_1'] ?? 0), 2) ?> <span class="text-muted">/ 60</span></p>
            <p class="mb-2"><strong>Bloque 2:</strong> <?= number_format((float)($eval['bloque_2'] ?? 0), 2) ?> <span class="text-muted">/ 30</span></p>
            <p class="mb-2"><strong>Bloque 3:</strong> <?= number_format((float)($eval['bloque_3'] ?? 0), 2) ?> <span class="text-muted">/ 8</span></p>
            <p class="mb-0"><strong>Bloque 4:</strong> <?= number_format((float)($eval['bloque_4'] ?? 0), 2) ?> <span class="text-muted">/ 2</span></p>
          </div>
        </div>
      </div>

      <!-- Detalle completo de puntuaciones -->
      <div class="dashboard-card p-4 mb-4">
        <h3 class="h5 mb-3" style="color:#123b72;">Detalle de puntuaciones</h3>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-light">
              <tr><th>Apartado</th><th>Puntuación</th><th>Máximo</th></tr>
            </thead>
            <tbody>
              <tr class="table-secondary"><td colspan="3"><strong>Bloque 1 &mdash; Investigación</strong></td></tr>
              <tr><td>1.A Publicaciones científicas y patentes</td><td><?= number_format((float)($eval['puntuacion_1a'] ?? 0),2) ?></td><td>30</td></tr>
              <tr><td>1.B Libros y capítulos</td><td><?= number_format((float)($eval['puntuacion_1b'] ?? 0),2) ?></td><td>12</td></tr>
              <tr><td>1.C Proyectos y contratos</td><td><?= number_format((float)($eval['puntuacion_1c'] ?? 0),2) ?></td><td>5</td></tr>
              <tr><td>1.D Transferencia de tecnología</td><td><?= number_format((float)($eval['puntuacion_1d'] ?? 0),2) ?></td><td>2</td></tr>
              <tr><td>1.E Dirección de tesis doctorales</td><td><?= number_format((float)($eval['puntuacion_1e'] ?? 0),2) ?></td><td>4</td></tr>
              <tr><td>1.F Congresos, conferencias</td><td><?= number_format((float)($eval['puntuacion_1f'] ?? 0),2) ?></td><td>5</td></tr>
              <tr><td>1.G Otros méritos</td><td><?= number_format((float)($eval['puntuacion_1g'] ?? 0),2) ?></td><td>2</td></tr>
              <tr class="table-secondary"><td colspan="3"><strong>Bloque 2 &mdash; Docencia</strong></td></tr>
              <tr><td>2.A Docencia universitaria</td><td><?= number_format((float)($eval['puntuacion_2a'] ?? 0),2) ?></td><td>17</td></tr>
              <tr><td>2.B Evaluaciones de calidad</td><td><?= number_format((float)($eval['puntuacion_2b'] ?? 0),2) ?></td><td>3</td></tr>
              <tr><td>2.C Cursos de formación docente</td><td><?= number_format((float)($eval['puntuacion_2c'] ?? 0),2) ?></td><td>3</td></tr>
              <tr><td>2.D Material docente y EEES</td><td><?= number_format((float)($eval['puntuacion_2d'] ?? 0),2) ?></td><td>7</td></tr>
              <tr class="table-secondary"><td colspan="3"><strong>Bloque 3 &mdash; Formación y experiencia</strong></td></tr>
              <tr><td>3.A Tesis, becas, estancias</td><td><?= number_format((float)($eval['puntuacion_3a'] ?? 0),2) ?></td><td>6</td></tr>
              <tr><td>3.B Trabajo profesional</td><td><?= number_format((float)($eval['puntuacion_3b'] ?? 0),2) ?></td><td>2</td></tr>
              <tr class="table-secondary"><td colspan="3"><strong>Bloque 4 &mdash; Otros méritos</strong></td></tr>
              <tr><td>4. Otros méritos</td><td><?= number_format((float)($eval['bloque_4'] ?? 0),2) ?></td><td>2</td></tr>
              <tr class="table-primary fw-bold"><td><strong>B1 + B2</strong></td><td><strong><?= number_format((float)($eval['total_b1_b2'] ?? ((float)($eval['bloque_1']??0)+(float)($eval['bloque_2']??0))), 2) ?></strong></td><td>&ge; 50</td></tr>
              <tr class="table-primary fw-bold"><td><strong>Total final</strong></td><td><strong><?= number_format((float)($eval['total_final'] ?? 0), 2) ?></strong></td><td>&ge; 55</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Asesor orientativo -->
      <div class="dashboard-card p-4">
        <h3 class="h5 mb-3" style="color:#123b72;"><i class="bi bi-robot me-1"></i> Asesor orientativo</h3>
        <?php if (!empty($asesorDash)): ?>
          <?php if (!empty($asesorDash['resumen'])): ?>
            <p><?= htmlspecialchars((string)$asesorDash['resumen']) ?></p>
          <?php endif; ?>
          <?php if (!empty($asesorDash['acciones']) && is_array($asesorDash['acciones'])): ?>
            <div class="d-flex flex-column gap-2 mb-3">
              <?php foreach ($asesorDash['acciones'] as $accion): ?>
                <div class="border rounded p-3">
                  <strong><?= htmlspecialchars((string)($accion['titulo'] ?? 'Acción')) ?></strong>
                  <?php if (!empty($accion['detalle'])): ?>
                    <div class="mt-1"><?= htmlspecialchars((string)$accion['detalle']) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($accion['impacto_estimado'])): ?>
                    <div class="text-muted small mt-1">Impacto estimado: <?= htmlspecialchars((string)$accion['impacto_estimado']) ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($asesorDash['simulaciones']) && is_array($asesorDash['simulaciones'])): ?>
            <div class="fw-bold small text-muted text-uppercase mb-2">Simulaciones rápidas</div>
            <div class="row g-2">
              <?php foreach ($asesorDash['simulaciones'] as $sim): ?>
                <div class="col-sm-6">
                  <div class="border rounded p-3">
                    <strong><?= htmlspecialchars((string)($sim['escenario'] ?? 'Escenario')) ?></strong>
                    <?php if (!empty($sim['efecto_estimado'])): ?>
                      <div class="mt-1 small"><?= htmlspecialchars((string)$sim['efecto_estimado']) ?></div>
                    <?php endif; ?>
                    <?php if (isset($sim['nuevo_total_aprox'])): ?>
                      <div class="text-muted small">Nuevo total ≈ <?= number_format((float)$sim['nuevo_total_aprox'], 2) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted">No hay consejos del asesor disponibles para esta evaluación.</p>
        <?php endif; ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
      new bootstrap.Popover(el, { html: false });
    });
  });
  </script>

  <?php include('acelerador_panel/fronten/chatbot.php'); ?>

  </html>