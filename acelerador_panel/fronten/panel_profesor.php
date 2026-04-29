<?php
session_start();
include('login.php');
require_once __DIR__ . '/../../evaluador/src/evaluaciones_traceability.php';
error_reporting(0);

// Si no hay sesión activa, redirigir al login
if (!isset($_SESSION['nombredelusuario']) || $_SESSION['nombredelusuario'] == '') {
  header("Location: ../../acelerador_login/fronten/index.php");
  exit();
}

$correo = $_SESSION['nombredelusuario'];

// Consultar los datos correspondientes en tbl_profesor
$query_perfil = mysqli_query(
  $conn,
  "SELECT
        nombre,
        apellidos,
        DNI AS dni,
        ORCID AS orcid,
        correo,
        departamento,
        telefono,
        facultad,
        rama
    FROM tbl_profesor
    WHERE correo = '$correo'"
);

if ($query_perfil && mysqli_num_rows($query_perfil) > 0) {
  $datos_perfil = mysqli_fetch_array($query_perfil);

  $nombreRaw    = trim((string)($datos_perfil['nombre']    ?? ''));
  $apellidosRaw = trim((string)($datos_perfil['apellidos'] ?? ''));
  $orcidRaw     = trim((string)($datos_perfil['orcid']     ?? ''));

  // Datos básicos
  $nombre     = htmlspecialchars($nombreRaw);
  $apellidos  = htmlspecialchars($apellidosRaw);
  $dni        = htmlspecialchars($datos_perfil['dni']         ?? '');
  $orcid      = htmlspecialchars($orcidRaw);

  // Datos extra
  $correo      = htmlspecialchars($datos_perfil['correo']      ?? '');
  $departamento= htmlspecialchars($datos_perfil['departamento']?? '');
  $telefono    = htmlspecialchars($datos_perfil['telefono']    ?? '');
  $facultad    = htmlspecialchars($datos_perfil['facultad']    ?? '');
  $rama        = htmlspecialchars($datos_perfil['rama']        ?? '');

  // ── Propagar a sesión para que los evaluadores conozcan ORCID y rama ──
  $_SESSION['orcid_usuario'] = $datos_perfil['orcid'] ?? '';
  $_SESSION['rama_usuario']  = $datos_perfil['rama']  ?? '';

} else {
  $nombreRaw = $apellidosRaw = $orcidRaw = '';
  $nombre = $apellidos = $dni = $orcid = 'No registrado';
  $correo = $departamento = $telefono = $facultad = $rama = 'No registrado';
}

// ── Dashboard: id del profesor desde su correo de sesión ─────────────────
$correoRaw = $_SESSION['nombredelusuario'];

$resId   = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE correo = '" . mysqli_real_escape_string($conn, $correoRaw) . "' LIMIT 1");
$rowId   = $resId ? mysqli_fetch_assoc($resId) : null;
$idProf  = $rowId ? (int)$rowId['id_profesor'] : 0;

// Grupos a los que pertenece el profesor, con su tutor
$resGrupos = mysqli_query($conn,
  "SELECT g.id_grupo, g.nombre AS grupo_nombre,
          t.nombre AS tutor_nombre, t.apellidos AS tutor_apellidos, t.correo AS tutor_correo
   FROM tbl_grupo_profesor gp
   INNER JOIN tbl_grupo g    ON gp.id_grupo   = g.id_grupo
   INNER JOIN tbl_profesor t ON g.id_tutor    = t.id_profesor
   WHERE gp.id_profesor = $idProf
   ORDER BY g.nombre ASC"
);
$misGrupos   = [];
if ($resGrupos) { while ($g = mysqli_fetch_assoc($resGrupos)) $misGrupos[] = $g; }
$totalGrupos = count($misGrupos);

// ── Dashboard: Consultar la base de datos de evaluación de la rama ─────────
$ramaNorm = strtoupper(trim($rama));
$ramaNorm = iconv('UTF-8', 'ASCII//TRANSLIT', $ramaNorm);
$ramaNorm = preg_replace('/[^A-Z]/', '', $ramaNorm);

$mapaDB = [
    'CSYJ'          => 'evaluador_aneca_csyj',
    'EXPERIMENTALES'=> 'evaluador_aneca_experimentales',
    'HUMANIDADES'   => 'evaluador_aneca_humanidades',
    'SALUD'         => 'evaluador_aneca_salud',
    'TECNICA'       => 'evaluador_aneca_tecnicas',
    'TECNICAS'      => 'evaluador_aneca_tecnicas',
];
$dbName = $mapaDB[$ramaNorm] ?? 'evaluador_aneca_salud';

$dbHost = getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos');
$dbUser = getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'usuario_web');
$dbPass = getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'password_segura');
$dbPort = (int)(getenv('ACELERADOR_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$eval = null;
$dbError = null;

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Preferimos ORCID y dejamos el nombre solo para fallback legacy.
    $hasOrcidColumn = aneca_pdo_table_has_column($pdo, 'evaluaciones', 'orcid_candidato');
    $orcidBusqueda = aneca_normalize_orcid($orcidRaw);

    if ($hasOrcidColumn && $orcidBusqueda !== null) {
        $stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE orcid_candidato = :orcid ORDER BY fecha_creacion DESC LIMIT 1");
        $stmt->execute(['orcid' => $orcidBusqueda]);
        $eval = $stmt->fetch();
    }

    if (!$eval) {
        // Fallback legacy: evaluaciones antiguas sin ORCID. El LIKE solo se acepta si hay una unica coincidencia.
        $legacyWhere = $hasOrcidColumn ? "(orcid_candidato IS NULL OR orcid_candidato = '') AND " : "";
        $nombreCompletoRaw = trim($nombreRaw . ' ' . $apellidosRaw);
        $nombresLegacy = array_values(array_unique(array_filter([$nombreCompletoRaw, $nombreRaw])));

        foreach ($nombresLegacy as $nombreLegacy) {
            $stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE {$legacyWhere}nombre_candidato = :nombre ORDER BY fecha_creacion DESC LIMIT 1");
            $stmt->execute(['nombre' => $nombreLegacy]);
            $eval = $stmt->fetch();
            if ($eval) {
                break;
            }
        }

        if (!$eval && $nombreRaw !== '') {
            $stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE {$legacyWhere}nombre_candidato LIKE :nombre ORDER BY fecha_creacion DESC LIMIT 2");
            $stmt->execute(['nombre' => '%' . $nombreRaw . '%']);
            $legacyMatches = $stmt->fetchAll();
            if (count($legacyMatches) === 1) {
                $eval = $legacyMatches[0];
            }
        }
    }
} catch (PDOException $e) {
    $dbError = "No se pudo conectar a la base de datos de evaluaciones ({$dbName}). Comprueba que el servicio está activo.";
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

<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acelerador</title>
  <link rel="icon" type="image/x-icon" href="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="css/styles.css?v=<?= time() ?>">
</head>

<body>
  <header>
    <div class="contenedorimg">
      <div class="imagen">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          style="height:50px; width:auto;" id="#acele" />
      </div>
      <div class="imagen">
        <img src="img/AcademyAccelerator_def.png" id="academy" alt="academy" />
      </div>
    </div>
  </header>

  <main>
    <div class="panel-wrapper">

      <!-- ══ COLUMNA IZQUIERDA: Perfil del profesor ══ -->
      <div class="formulario">
        <div class="text-center mb-4 w-100">
          <i class="bi bi-person-vcard text-white mb-2"
            style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
          <h2 class="text-white fw-bold">Perfil de Profesor</h2>
          <hr class="w-100 border-light opacity-25 mt-3 mb-1">
        </div>

        <div class="lista-perfil w-100 px-lg-4">
          <ul class="list-unstyled d-flex flex-column gap-2 mb-0 text-start w-100 mx-auto">

            <!-- ✅ Datos visibles siempre -->
            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-person me-1"></i> Nombre
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $nombre ?: '-'; ?></span>
            </li>

            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-people me-1"></i> Apellidos
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $apellidos ?: '-'; ?></span>
            </li>

            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-card-heading me-1"></i> DNI
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $dni ?: '-'; ?></span>
            </li>

            <li class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-globe me-1"></i> ORCID
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $orcid ?: '-'; ?></span>
            </li>

            <!-- ✅ Datos ocultos por defecto -->
            <li class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-envelope me-1"></i> Correo
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $correo ?: '-'; ?></span>
            </li>

            <li class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-building me-1"></i> Departamento
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $departamento ?: '-'; ?></span>
            </li>

            <li class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-telephone me-1"></i> Teléfono
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $telefono ?: '-'; ?></span>
            </li>

            <li class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-mortarboard me-1"></i> Facultad
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $facultad ?: '-'; ?></span>
            </li>

            <li class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-diagram-2 me-1"></i> Rama
              </span>
              <span class="fs-5 fw-medium text-white ms-1"><?php echo $rama ?: '-'; ?></span>
            </li>

          </ul>
        </div>

        <hr class="w-100 border-light my-4 opacity-25">

        <!-- Botones de acción -->
        <div class="d-flex flex-wrap justify-content-center gap-3 w-100 mb-2">

          <button type="button" id="btnMostrarTodo"
            class="btn btn-primary px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all"
            style="background-color: rgba(20, 88, 204, 0.8); border: none;">
            <i class="bi bi-person-lines-fill"></i> Mostrar todos mis datos
          </button>

          <a href="../../acelerador_primerapantallas/fronten/index.php"
            class="btn btn-outline-light px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all">
            <i class="bi bi-arrow-clockwise"></i> Actualizar mis datos
          </a>

          <button type="button" id="subirdatos" data-rama="<?php echo htmlspecialchars($rama, ENT_QUOTES, 'UTF-8'); ?>"
            class="btn btn-outline-info px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 text-white border-info">
            <i class="bi bi-file-earmark-plus"></i> Añadir trabajos/artículos
          </button>

          <a href="../../acelerador_login/fronten/logout.php"
            class="btn btn-outline-danger px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2">
            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
          </a>

        </div>
      </div><!-- /.formulario -->

      <!-- ══════════════════════════════════════════════════════════
           DASHBOARD PROFESOR — Grupos y tutor asignado
      ══════════════════════════════════════════════════════════ -->
      <div class="dashboard">

        <!-- Tarjetas de estadísticas -->
        <div class="row g-3 mb-4 w-100">
          <div class="col-md-4">
            <div class="dashboard-stat-card">
              <div class="stat-label"><i class="bi bi-collection me-1"></i> Grupos asignados</div>
              <div class="stat-value"><?= $totalGrupos ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card">
              <div class="stat-label"><i class="bi bi-diagram-2 me-1"></i> Rama</div>
              <div class="stat-value" style="font-size:1.1rem; word-break:break-word;"><?= htmlspecialchars($rama) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card">
              <div class="stat-label"><i class="bi bi-envelope me-1"></i> Correo</div>
              <div class="stat-value stat-email"><?= htmlspecialchars($correo) ?></div>
            </div>
          </div>
        </div>

        <!-- Resumen del profesor -->
        <div class="row g-3 mb-4 w-100">
          <div class="col-12">
            <div class="dashboard-info-card">
              <h2 class="dashboard-section-title"><i class="bi bi-info-circle me-2"></i>Resumen del profesor</h2>
              <div class="row g-3">
                <div class="col-sm-4">
                  <div class="mini-info-box">
                    <div class="mini-info-label">Facultad</div>
                    <div class="mini-info-value"><?= htmlspecialchars($facultad) ?></div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="mini-info-box">
                    <div class="mini-info-label">Departamento</div>
                    <div class="mini-info-value"><?= htmlspecialchars($departamento) ?></div>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="mini-info-box">
                    <div class="mini-info-label">ORCID</div>
                    <div class="mini-info-value"><?= htmlspecialchars($orcid) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tarjetas de grupos -->
        <?php if ($totalGrupos > 0): ?>
        <h2 class="dashboard-section-title mb-3 w-100"><i class="bi bi-diagram-3-fill me-2"></i>Mis grupos</h2>
        <div class="row g-3 w-100 mb-4">
          <?php foreach ($misGrupos as $grupo): ?>
            <div class="col-12">
              <div class="prof-panel-card p-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                  <div class="flex-grow-1">
                    <div class="prof-panel-name mb-1">
                      <i class="bi bi-collection me-1"></i><?= htmlspecialchars($grupo['grupo_nombre']) ?>
                    </div>
                    <div class="prof-panel-grupo mb-0 text-white-50">
                      <i class="bi bi-person-badge me-1"></i>Tutor: <strong><?= htmlspecialchars($grupo['tutor_nombre'] . ' ' . $grupo['tutor_apellidos']) ?></strong>
                    </div>
                  </div>
                  
                  <div class="text-lg-end">
                    <a href="mailto:<?= htmlspecialchars($grupo['tutor_correo']) ?>" class="btn btn-sm btn-outline-light rounded-pill">
                      <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($grupo['tutor_correo']) ?>
                    </a>
                  </div>

                  <div class="ms-lg-2">
                    <a href="mis_grupos.php" class="btn btn-sm btn-primary w-100 rounded-pill">
                      <i class="bi bi-diagram-3 me-1"></i> Ver mis grupos
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info rounded-4 w-100 mb-4">
          <i class="bi bi-info-circle me-2"></i>Aún no estás asignado a ningún grupo.
          Cuando un tutor te añada a su grupo de investigación, aparecerá aquí.
        </div>
        <?php endif; ?>


        <!-- ══════════════════════════════════════════════════════════
             MI EXPEDIENTE DE EVALUACIÓN
        ══════════════════════════════════════════════════════════ -->
        <?php if ($dbError): ?>
          <div class="alert alert-danger w-100 rounded-4">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($dbError) ?>
          </div>
        <?php elseif (!empty($eval)): ?>
          <h2 class="dashboard-section-title mb-3 w-100"><i class="bi bi-bar-chart-fill me-2"></i>Mi Expediente de Evaluación</h2>
          
          <div class="row g-3 mb-4 w-100">
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100">
                <div class="stat-label">Cumplimiento global</div>
                <div class="stat-value text-warning"><?= number_format((float)$eval['total_final'], 2) ?>%</div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100">
                <div class="stat-label">Estado estimado</div>
                <div class="stat-value"><?= estadoEstimado($eval) ?></div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100">
                <div class="stat-label">Bloques cumplidos</div>
                <div class="stat-value"><?= bloquesCumplidos($eval) ?> <span style="font-size: 1rem;">/ 4</span></div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100">
                <div class="stat-label">Bloque más débil</div>
                <div class="stat-value text-break" style="font-size:1.4rem;"><?= bloqueDebil($eval) ?></div>
              </div>
            </div>
          </div>
          
          <div class="row g-3 mb-4 w-100">
            <div class="col-lg-8">
              <div class="dashboard-info-card h-100">
                <h3 class="dashboard-section-title text-white mb-3">Resumen de progreso</h3>
                <div class="d-flex align-items-center gap-3 mb-2">
                  <div class="fs-1 fw-bold text-warning" style="line-height:1;"><?= number_format((float)$eval['total_final'], 2) ?>%</div>
                  <div class="flex-grow-1">
                    <div class="progress rounded-pill bg-white bg-opacity-25" style="height: 12px;">
                      <div class="progress-bar bg-warning" role="progressbar" style="width: <?= min(100, max(0, (float)$eval['total_final'])) ?>%;"></div>
                    </div>
                  </div>
                </div>
                <hr class="border-light opacity-25">
                <div class="row g-2">
                  <div class="col-sm-4">
                    <div class="mini-info-box">
                      <div class="mini-info-label">Resultado</div>
                      <div class="mini-info-value"><?= htmlspecialchars($eval['resultado'] ?? '-') ?></div>
                    </div>
                  </div>
                  <div class="col-sm-4">
                    <div class="mini-info-box">
                      <div class="mini-info-label">Regla 1</div>
                      <div class="mini-info-value"><?= (int)($eval['cumple_regla_1'] ?? 0) ? 'Sí ✅' : 'No ❌' ?></div>
                    </div>
                  </div>
                  <div class="col-sm-4">
                    <div class="mini-info-box">
                      <div class="mini-info-label">Regla 2</div>
                      <div class="mini-info-value"><?= (int)($eval['cumple_regla_2'] ?? 0) ? 'Sí ✅' : 'No ❌' ?></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-lg-4">
              <div class="dashboard-info-card h-100">
                <h3 class="dashboard-section-title text-white mb-3">Bloques</h3>
                <div class="d-flex flex-column gap-2">
                  <div class="d-flex justify-content-between align-items-center border-bottom border-light border-opacity-25 pb-1">
                    <span class="text-white-50 small text-uppercase">Bloque 1</span>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_1'] ?? 0), 2) ?></strong>
                  </div>
                  <div class="d-flex justify-content-between align-items-center border-bottom border-light border-opacity-25 pb-1">
                    <span class="text-white-50 small text-uppercase">Bloque 2</span>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_2'] ?? 0), 2) ?></strong>
                  </div>
                  <div class="d-flex justify-content-between align-items-center border-bottom border-light border-opacity-25 pb-1">
                    <span class="text-white-50 small text-uppercase">Bloque 3</span>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_3'] ?? 0), 2) ?></strong>
                  </div>
                  <div class="d-flex justify-content-between align-items-center pb-1">
                    <span class="text-white-50 small text-uppercase">Bloque 4</span>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_4'] ?? 0), 2) ?></strong>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="col-12">
              <div class="dashboard-info-card">
                <h3 class="dashboard-section-title text-white mb-3">Recomendaciones automáticas</h3>
                <ul class="text-white mb-2 ps-3">
                  <?php foreach (recomendaciones($eval) as $rec): ?>
                    <li><?= htmlspecialchars($rec) ?></li>
                  <?php endforeach; ?>
                </ul>
                <div class="text-white-50 small mt-2">Última actualización: <?= htmlspecialchars($eval['fecha_creacion'] ?? '-') ?></div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-warning rounded-4 w-100 bg-light bg-opacity-10 text-white border-light border-opacity-25 shadow-sm">
            <i class="bi bi-info-circle me-2"></i>Aún no tienes evaluaciones registradas en la base de datos <strong><?= htmlspecialchars($dbName) ?></strong>. Sube tus méritos para comenzar.
          </div>
        <?php endif; ?>



      </div><!-- /.dashboard -->

    </div><!-- /.panel-wrapper -->
  </main>

  <footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          style="height:50px; width:auto;" id="#acele" />
        <p>
          Glorieta Ángel Herrera Oria, s/n,<br />
          41930 Bormujos,<br />
          Sevilla
        </p>
      </div>
      <div class="requerimientolegal">
        <div class="columna">
          <h4>La Empresa</h4>
          <ul>
            <li>Contacto</li>
            <li>Preguntas Frecuentes (FAQ)</li>
            <li>Centro de Ayuda</li>
            <li>Soporte</li>
          </ul>
        </div>
        <div class="columna">
          <h4>Ayuda</h4>
          <ul>
            <li>Términos y Condiciones</li>
            <li>Política de Cookies</li>
          </ul>
        </div>
        <div class="columna">
          <h4>Legal</h4>
          <ul>
            <li>Sobre nosotros</li>
            <li>Política de Cookies</li>
            <li>Blog</li>
          </ul>
        </div>
      </div>
      <div class="piepag">
        <p>&copy; CEU Lab. Todos los derechos reservados.</p>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="js/script.js"></script>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const boton     = document.getElementById("btnMostrarTodo");
      const extraDatos = document.querySelectorAll(".extraDato");

      boton.addEventListener("click", () => {
        if (extraDatos[0].classList.contains("d-none")) {
          extraDatos.forEach(el => el.classList.remove("d-none"));
          boton.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Mostrar resumen datos';
        } else {
          extraDatos.forEach(el => el.classList.add("d-none"));
          boton.innerHTML = '<i class="bi bi-person-lines-fill"></i> Mostrar todos mis datos';
        }
      });
    });

    document.addEventListener("DOMContentLoaded", () => {
      const btnValidar = document.getElementById("subirdatos");
      if (!btnValidar) {
        console.error("[VALIDAR] No encuentro el botón #subirdatos");
        return;
      }

      btnValidar.addEventListener("click", (e) => {
        e.preventDefault();

        // 1) Rama desde data-rama
        let perfilRaw = (btnValidar.dataset.rama || "")
          .toUpperCase()
          .trim()
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, ""); // quita tildes

        // 2) Mapa de rutas
        const rutas = {
          "CSYJ":          "/evaluador/evaluador_aneca_csyj/index.php",
          "EXPERIMENTALES":"/evaluador/evaluador_aneca_experimentales/index.php",
          "HUMANIDADES":   "/evaluador/evaluador_aneca_humanidades/index.php",
          "SALUD":         "/evaluador/evaluador_aneca_salud/index.php",
          "TECNICA":       "/evaluador/evaluador_aneca_tecnicas/index.php"
        };

        const rutaRelativa = rutas[perfilRaw];
        if (!rutaRelativa) {
          alert("Perfil/Rama no reconocida: " + perfilRaw);
          console.warn("[VALIDAR] Rama desconocida:", btnValidar.dataset.rama, "->", perfilRaw);
          return;
        }

        // 3) Detectar prefijo del proyecto automáticamente
        const path = window.location.pathname;
        const base = path.split("/acelerador_panel/")[0] || "";

        const destino = base + rutaRelativa;
        console.log("[VALIDAR] Rama:", perfilRaw);
        console.log("[VALIDAR] Base:", base);
        console.log("[VALIDAR] Destino:", destino);

        window.location.href = destino;
      });
    });
  </script>
</body>

</html>
