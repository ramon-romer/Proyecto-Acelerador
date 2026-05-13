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

require_once __DIR__ . '/acelerador_login/fronten/lib/session_security.php';
require_once __DIR__ . '/acelerador_panel/fronten/lib/db.php';

// Aplicar protecciones de sesión y validación de credenciales
acelerador_apply_protected_page_session_guards();

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
  <title>Dashboard Profesor - <?= htmlspecialchars($eval['nombre_candidato'] ?? 'Candidato') ?></title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
        --azul-glass: rgba(20, 88, 204, 0.4);
        --blanco: #ffffff;
        --blanco-alpha: rgba(255, 255, 255, 0.1);
        --verde: #4ade80;
        --verde-fondo: rgba(74, 222, 128, 0.15);
        --rojo: #f87171;
        --rojo-fondo: rgba(248, 113, 113, 0.15);
        --sombra: 0 8px 32px rgba(0, 0, 0, 0.37);
        --radio: 24px;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-image: url('acelerador_panel/fronten/img/Image (3).jpg');
        background-size: cover;
        background-repeat: no-repeat;
        background-position: center;
        background-attachment: fixed;
        color: #e2e8f0;
        min-height: 100vh;
    }
    .shell { max-width: 1280px; margin: 0 auto; padding: 40px 24px; }
    .hero {
        background: var(--azul-glass);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        color: var(--blanco);
        border-radius: var(--radio);
        padding: 30px 35px;
        box-shadow: var(--sombra);
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    .hero-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .hero h1 { margin: 0; font-size: 36px; font-weight: 800; letter-spacing: -0.02em; }
    .hero p { margin: 12px 0 0; max-width: 850px; color: rgba(255,255,255,.7); font-size: 18px; }
    .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }

    .card {
        background: var(--azul-glass);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: var(--radio);
        box-shadow: var(--sombra);
        padding: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        height: 100%;
    }
    .card h2, .card h3 { margin-top: 0; font-weight: 700; color: #fff; }

    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .metric {
        background: rgba(20, 88, 204, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 18px;
        padding: 20px;
        text-align: center;
        transition: transform 0.2s, background 0.2s;
        backdrop-filter: blur(8px);
    }
    .metric:hover { background: rgba(20, 88, 204, 0.5); transform: translateY(-3px); }
    .metric .label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: rgba(255, 255, 255, 0.5);
        margin-bottom: 8px;
        font-weight: 700;
    }
    .metric .value {
        font-size: 32px;
        font-weight: 800;
        color: #fff;
    }

    .btn {
        border-radius: 50px;
        background: #1458cc;
        color: var(--blanco);
        padding: 12px 24px;
        font-weight: 700;
        border: none;
        transition: all 0.2s ease;
        box-shadow: 0 4px 12px rgba(20, 88, 204, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn:hover {
        transform: translateY(-2px);
        background: #1e63e6;
        box-shadow: 0 6px 16px rgba(20, 88, 204, 0.4);
        color: #fff;
    }
    .btn.outline {
        background: transparent;
        color: #fff;
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: none;
    }
    .btn.outline:hover { background: rgba(255, 255, 255, 0.1); border-color: #fff; }

    .badge-custom {
        display: inline-flex;
        align-items: center;
        border-radius: 50px;
        padding: 6px 16px;
        font-weight: 700;
        font-size: 14px;
        text-transform: uppercase;
    }
    .badge-custom.success { color: #4ade80; background: rgba(74, 222, 128, 0.15); border: 1px solid rgba(74, 222, 128, 0.2); }
    .badge-custom.danger { color: #f87171; background: rgba(248, 113, 113, 0.15); border: 1px solid rgba(248, 113, 113, 0.2); }
    .badge-custom.warning { color: #fbbf24; background: rgba(251, 191, 36, 0.15); border: 1px solid rgba(251, 191, 36, 0.2); }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        border-radius: 20px;
        overflow: hidden;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.05);
        margin-top: 20px;
    }
    th, td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 15px 20px;
        text-align: left;
    }
    th { background: rgba(255, 255, 255, 0.05); color: rgba(255, 255, 255, 0.6); font-size: 12px; text-transform: uppercase; font-weight: 700; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255, 255, 255, 0.03); }

    .progress-glass {
        height: 12px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 999px;
        overflow: hidden;
        margin: 20px 0;
    }
    .progress-glass-bar {
        height: 100%;
        transition: width 1s ease-in-out;
    }

    .asesor-item {
        background: rgba(20, 88, 204, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 18px;
        padding: 20px;
        margin-bottom: 15px;
        backdrop-filter: blur(5px);
    }
    .asesor-item h4 { margin-top: 0; font-size: 18px; color: #fff; }

    .stack { display: flex; flex-direction: column; gap: 24px; }
    .split { display: grid; grid-template-columns: 1fr 350px; gap: 24px; }

    @media (max-width: 992px) {
        .split { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<div class="shell">
    <header class="hero">
        <div class="hero-top">
            <div>
                <?php if (!empty($eval)): ?>
                    <h1><?= htmlspecialchars($eval['nombre_candidato']) ?></h1>
                    <p>
                        <i class="bi bi-bookmark-fill me-1"></i> <?= htmlspecialchars($eval['area'] ?? '') ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-award me-1"></i> Categoría: <?= htmlspecialchars($eval['categoria'] ?? '') ?>
                        <span class="mx-2">|</span>
                        <i class="bi bi-diagram-3 me-1"></i> Rama: <?= htmlspecialchars(strtoupper($ramaNorm)) ?>
                    </p>
                <?php else: ?>
                    <h1>Evaluación no encontrada</h1>
                    <p>No se han encontrado registros para los criterios especificados.</p>
                <?php endif; ?>
            </div>
            <div class="hero-actions">
                <?php
                  $perfilUser = strtoupper($_SESSION['perfil_usuario'] ?? ($_SESSION['rol'] ?? 'PROFESOR'));
                  $btnText = ($perfilUser === 'TUTOR') ? 'Panel Tutor' : 'Panel Profesor';
                  $btnUrl = ($perfilUser === 'TUTOR') ? 'acelerador_panel/fronten/panel_tutor.php' : 'acelerador_panel/fronten/panel_profesor.php';
                ?>
                <a href="<?= $btnUrl ?>" class="btn">
                    <i class="bi bi-arrow-left-circle me-2"></i><?= $btnText ?>
                </a>
            </div>
        </div>
    </header>

    <main class="page-body">
        <?php if (isset($dbError)): ?>
            <div class="card">
                <div class="alert alert-danger mb-0"><?= htmlspecialchars($dbError) ?></div>
            </div>
        <?php elseif (!empty($eval)): ?>

            <div class="metrics-grid">
                <div class="metric">
                    <span class="label">Puntuación Total</span>
                    <div class="value" style="color: <?= $colorDash ?>;"><?= number_format($_totalFinalDash, 2) ?>%</div>
                </div>
                <div class="metric">
                    <span class="label">Estado Estimado</span>
                    <div class="value" style="color: <?= $colorDash ?>;"><?= estadoEstimado($eval) ?></div>
                </div>
                <div class="metric">
                    <span class="label">Bloques Cumplidos</span>
                    <div class="value"><?= bloquesCumplidos($eval) ?> / 4</div>
                </div>
                <div class="metric">
                    <span class="label">Bloque más débil</span>
                    <div class="value" style="font-size: 20px;"><?= bloqueDebil($eval) ?></div>
                </div>
            </div>

            <div class="split">
                <div class="stack">
                    <section class="card">
                        <h2>Resumen última evaluación</h2>
                        <div class="d-flex align-items-baseline gap-3">
                            <span style="font-size: 48px; font-weight: 800; color: <?= $colorDash ?>;"><?= number_format($_totalFinalDash, 2) ?>%</span>
                            <span class="badge-custom <?= $esPositivaDash ? 'success' : 'danger' ?>">
                                <?= $esPositivaDash ? '<i class="bi bi-check-circle-fill me-2"></i>' : '<i class="bi bi-x-circle-fill me-2"></i>' ?>
                                <?= htmlspecialchars($eval['resultado'] ?? '-') ?>
                            </span>
                        </div>

                        <div class="progress-glass">
                            <div class="progress-glass-bar" style="width: <?= min(100, max(0, $_totalFinalDash)) ?>%; background: <?= $gradientDash ?>;"></div>
                        </div>

                        <div class="row g-4 mt-2">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center p-3 rounded-4" style="background: rgba(20, 88, 204, 0.2); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
                                    <span class="fw-bold">Regla 1 (B1+B2 &ge; 50)</span>
                                    <span><?= (int)($eval['cumple_regla_1'] ?? 0) ? '<span class="text-success"><i class="bi bi-check-lg"></i></span>' : '<span class="text-danger"><i class="bi bi-x-lg"></i></span>' ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between align-items-center p-3 rounded-4" style="background: rgba(20, 88, 204, 0.2); border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(5px);">
                                    <span class="fw-bold">Regla 2 (Total &ge; 55)</span>
                                    <span><?= (int)($eval['cumple_regla_2'] ?? 0) ? '<span class="text-success"><i class="bi bi-check-lg"></i></span>' : '<span class="text-danger"><i class="bi bi-x-lg"></i></span>' ?></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card">
                        <h3>Detalle de puntuaciones</h3>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Apartado</th>
                                        <th>Puntuación</th>
                                        <th>Máximo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr style="background: rgba(20, 88, 204, 0.2);"><td colspan="3"><strong>Bloque 1 — Investigación</strong></td></tr>
                                    <tr><td>1.A Publicaciones científicas y patentes</td><td><?= number_format((float)($eval['puntuacion_1a'] ?? 0),2) ?></td><td>30</td></tr>
                                    <tr><td>1.B Libros y capítulos</td><td><?= number_format((float)($eval['puntuacion_1b'] ?? 0),2) ?></td><td>12</td></tr>
                                    <tr><td>1.C Proyectos y contratos</td><td><?= number_format((float)($eval['puntuacion_1c'] ?? 0),2) ?></td><td>5</td></tr>
                                    <tr><td>1.D Transferencia de tecnología</td><td><?= number_format((float)($eval['puntuacion_1d'] ?? 0),2) ?></td><td>2</td></tr>
                                    <tr><td>1.E Dirección de tesis doctorales</td><td><?= number_format((float)($eval['puntuacion_1e'] ?? 0),2) ?></td><td>4</td></tr>
                                    <tr><td>1.F Congresos, conferencias</td><td><?= number_format((float)($eval['puntuacion_1f'] ?? 0),2) ?></td><td>5</td></tr>
                                    <tr><td>1.G Otros méritos</td><td><?= number_format((float)($eval['puntuacion_1g'] ?? 0),2) ?></td><td>2</td></tr>
                                    <tr style="background: rgba(20, 88, 204, 0.2);"><td colspan="3"><strong>Bloque 2 — Docencia</strong></td></tr>
                                    <tr><td>2.A Docencia universitaria</td><td><?= number_format((float)($eval['puntuacion_2a'] ?? 0),2) ?></td><td>17</td></tr>
                                    <tr><td>2.B Evaluaciones de calidad</td><td><?= number_format((float)($eval['puntuacion_2b'] ?? 0),2) ?></td><td>3</td></tr>
                                    <tr><td>2.C Cursos de formación docente</td><td><?= number_format((float)($eval['puntuacion_2c'] ?? 0),2) ?></td><td>3</td></tr>
                                    <tr><td>2.D Material docente y EEES</td><td><?= number_format((float)($eval['puntuacion_2d'] ?? 0),2) ?></td><td>7</td></tr>
                                    <tr style="background: rgba(20, 88, 204, 0.2);"><td colspan="3"><strong>Bloque 3 — Formación y experiencia</strong></td></tr>
                                    <tr><td>3.A Tesis, becas, estancias</td><td><?= number_format((float)($eval['puntuacion_3a'] ?? 0),2) ?></td><td>6</td></tr>
                                    <tr><td>3.B Trabajo profesional</td><td><?= number_format((float)($eval['puntuacion_3b'] ?? 0),2) ?></td><td>2</td></tr>
                                    <tr style="background: rgba(20, 88, 204, 0.2);"><td colspan="3"><strong>Bloque 4 — Otros méritos</strong></td></tr>
                                    <tr><td>4. Otros méritos</td><td><?= number_format((float)($eval['bloque_4'] ?? 0),2) ?></td><td>2</td></tr>
                                    <tr style="background: var(--azul-glass);"><td class="fw-bold">Total Final</td><td class="fw-bold" style="color: <?= $colorDash ?>;"><?= number_format($_totalFinalDash, 2) ?></td><td class="fw-bold">&ge; 55</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside class="stack">
                    <section class="card">
                        <h3>Puntuación por Bloques</h3>
                        <div class="stack" style="gap: 15px;">
                            <div class="metric p-3">
                                <span class="label">Investigación</span>
                                <div class="value" style="font-size: 24px;"><?= number_format((float)($eval['bloque_1'] ?? 0), 2) ?> <small class="text-white-50" style="font-size: 14px;">/ 60</small></div>
                            </div>
                            <div class="metric p-3">
                                <span class="label">Docencia</span>
                                <div class="value" style="font-size: 24px;"><?= number_format((float)($eval['bloque_2'] ?? 0), 2) ?> <small class="text-white-50" style="font-size: 14px;">/ 30</small></div>
                            </div>
                            <div class="metric p-3">
                                <span class="label">Formación</span>
                                <div class="value" style="font-size: 24px;"><?= number_format((float)($eval['bloque_3'] ?? 0), 2) ?> <small class="text-white-50" style="font-size: 14px;">/ 8</small></div>
                            </div>
                            <div class="metric p-3">
                                <span class="label">Otros Méritos</span>
                                <div class="value" style="font-size: 24px;"><?= number_format((float)($eval['bloque_4'] ?? 0), 2) ?> <small class="text-white-50" style="font-size: 14px;">/ 2</small></div>
                            </div>
                        </div>
                    </section>

                    <section class="card">
                        <h3><i class="bi bi-robot me-2 text-info"></i>Asesor IA</h3>
                        <?php if (!empty($asesorDash)): ?>
                            <?php if (!empty($asesorDash['resumen'])): ?>
                                <p class="small text-white-50"><?= htmlspecialchars((string)$asesorDash['resumen']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($asesorDash['acciones']) && is_array($asesorDash['acciones'])): ?>
                                <div class="stack" style="gap: 10px;">
                                    <?php foreach (array_slice($asesorDash['acciones'], 0, 3) as $accion): ?>
                                        <div class="asesor-item p-3 mb-0">
                                            <div class="fw-bold small mb-1"><?= htmlspecialchars((string)($accion['titulo'] ?? 'Acción')) ?></div>
                                            <div class="text-white-50" style="font-size: 12px;"><?= htmlspecialchars((string)($accion['impacto_estimado'] ?? '')) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-info-circle text-white-50" style="font-size: 24px;"></i>
                                <p class="small text-white-50 mt-2">No hay consejos disponibles.</p>
                            </div>
                        <?php endif; ?>
                    </section>
                </aside>
            </div>

        <?php else: ?>
            <div class="card text-center py-5">
                <i class="bi bi-search text-white-50" style="font-size: 48px;"></i>
                <h2 class="mt-3">Sin resultados</h2>
                <p class="text-white-50">No se ha encontrado ninguna evaluación para "<strong><?= htmlspecialchars($nombre ?? 'sin nombre') ?></strong>".</p>
                <div class="mt-4">
                    <a href="<?= $btnUrl ?>" class="btn">Volver al panel</a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include('acelerador_panel/fronten/chatbot.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
