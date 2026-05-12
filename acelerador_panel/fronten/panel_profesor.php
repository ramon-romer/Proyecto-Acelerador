<?php
session_start();
include('login.php');
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

  // Datos básicos
  $nombre     = htmlspecialchars($datos_perfil['nombre']      ?? '');
  $apellidos  = htmlspecialchars($datos_perfil['apellidos']   ?? '');
  $dni        = htmlspecialchars($datos_perfil['dni']         ?? '');
  $orcid      = htmlspecialchars($datos_perfil['orcid']       ?? '');

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

// ── Notificaciones persistentes pendientes ──────────────────────────────────────────
$notifsPendientes = [];
if ($idProf > 0) {
  // Verificar que la tabla existe antes de consultar
  $tablaExists = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_notificacion_pendiente'");
  if ($tablaExists && mysqli_num_rows($tablaExists) > 0) {
    $qNotifs = mysqli_query($conn, "SELECT mensaje FROM tbl_notificacion_pendiente WHERE id_profesor = $idProf ORDER BY fecha_creacion ASC");
    if ($qNotifs) {
      while ($rn = mysqli_fetch_assoc($qNotifs)) {
        $notifsPendientes[] = $rn['mensaje'];
      }
      // Limpiar notificaciones ya leídas
      if (!empty($notifsPendientes)) {
        mysqli_query($conn, "DELETE FROM tbl_notificacion_pendiente WHERE id_profesor = $idProf");
      }
    }
  }
}

// ── Tareas pendientes del profesor ────────────────────────────────────────────────
$tareasActivas = [];
$entregasTotales = []; // Array plano con todas las entregas de todas las tareas

try {
    $resTarea = mysqli_query($conn,
      "SELECT t.*, g.nombre AS grupo_nombre
       FROM tbl_tarea_entrega t
       INNER JOIN tbl_grupo g ON t.id_grupo = g.id_grupo
       WHERE t.id_profesor = $idProf
       ORDER BY t.fecha_creacion DESC"
    );
    if ($resTarea && mysqli_num_rows($resTarea) > 0) {
        while ($t = mysqli_fetch_assoc($resTarea)) {
            $jsonFechas = $t['fechas_entregas'] ?? '[]';
            $decoded    = json_decode($jsonFechas, true);
            $fechas     = is_array($decoded) ? $decoded : [];
            
            $proximaFecha = null;
            $proximaIdx = -1;
            
            // Decodificar el estado de las entregas ya realizadas
            $hechas = json_decode($t['fechas_reales_entregas'] ?? '[]', true) ?: [];
            
            foreach ($fechas as $idx => $fe) {
                // El hito activo es el primero que NO tiene fecha real registrada
                if (empty($hechas[$idx])) {
                    $proximaFecha = $fe;
                    $proximaIdx   = $idx;
                    break;
                }
            }
            
            $t['fechasEntregas'] = $fechas;
            $t['proximaFecha'] = $proximaFecha;
            $t['proximaIdx'] = $proximaIdx;
            $tareasActivas[] = $t;
        }
    }
} catch (Exception $e) {
    // Tabla puede no existir — silenciar
}

// ── Dashboard: Consultar la base de datos de evaluación de la rama ─────────
$ramaNorm = strtoupper(trim($rama));
$ramaNorm = iconv('UTF-8', 'ASCII//TRANSLIT', $ramaNorm);
$ramaNorm = preg_replace('/[^A-Z]/', '', $ramaNorm);

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

$dbHost = getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos');
$dbUser = getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'root');
$dbPass = getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'root_super_segura');
$dbPort = (int)(getenv('ACELERADOR_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
$eval = null;
$dbError = null;

try {
    $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // El profesor está ingresado como nombre, buscamos coincidencias con LIKE
    $stmt = $pdo->prepare("SELECT * FROM evaluaciones WHERE nombre_candidato LIKE :nombre ORDER BY fecha_creacion DESC LIMIT 1");
    // Extraemos solo el primer nombre o nombre completo as-is
    $stmt->execute(['nombre' => '%' . trim($nombre) . '%']);
    $eval = $stmt->fetch();
    
    // Fallback si no se encontró exacto por nombre
    if (!$eval) {
        $stmt = $pdo->query("SELECT * FROM evaluaciones ORDER BY fecha_creacion DESC LIMIT 1");
        $eval = $stmt->fetch();
    }
} catch (PDOException $e) {
    $dbError = "No se pudo conectar a la base de datos de evaluaciones ({$dbName}). Comprueba que el servicio está activo.";
}

// ── Extraer asesor orientativo del json_entrada ─────────────────────────
$asesorProf = [];
if (!empty($eval['json_entrada'])) {
    $jsonProf = json_decode((string)$eval['json_entrada'], true);
    if (is_array($jsonProf)) {
        $rc = $jsonProf['resultado_calculo'] ?? [];
        $asesorProf = is_array($rc['asesor'] ?? null) ? $rc['asesor'] : [];
    }
}
$esPositivaProf  = strtoupper(trim((string)($eval['resultado'] ?? ''))) === 'POSITIVA';
$_totalFinalProf = (float)($eval['total_final'] ?? 0);
$colorProf       = $_totalFinalProf >= 70 ? '#4ade80' : ($_totalFinalProf >= 50 ? '#fbbf24' : '#f87171');
$gradientProf    = $_totalFinalProf >= 70 ? '#4ade80' : ($_totalFinalProf >= 50 ? '#fbbf24' : '#f87171');
$iconProf        = $_totalFinalProf >= 70 ? '✅' : ($_totalFinalProf >= 50 ? '⚠️' : '❌');

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
  <style>
    .popover-body { white-space: pre-line; }
  </style>
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

        <?php if (!empty($tareasActivas)): ?>
        <hr class="w-100 border-light my-3 opacity-25">
        <h6 class="text-white fw-bold mb-3" style="font-size:.95rem;">
          <i class="bi bi-hourglass-split me-1"></i> Entregas Programadas
        </h6>

        <div class="w-100 custom-scrollbar pe-2" style="max-height: 450px; overflow-y: auto; overflow-x: hidden;">
        <?php foreach ($tareasActivas as $tareaPendiente): 
            $fechasEntregas = $tareaPendiente['fechasEntregas'];
            $proximaFecha   = $tareaPendiente['proximaFecha'];
            $proximaIdx     = $tareaPendiente['proximaIdx'];
            $numEntregas    = (int)($tareaPendiente['num_entregas'] ?? 0);
        ?>
          <!-- ═══ CUENTA ATRÁS — Entregas pendientes de la tarea ═══ -->
          <div class="w-100 mb-4 p-3 rounded-4" style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.10);">
            
            <!-- Info de la tarea -->
            <div class="w-100 mb-3 p-2 rounded-3" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);">
              <div class="text-white-50 small text-uppercase fw-bold" style="font-size:.7rem;">Tarea asignada</div>
              <div class="text-white fw-bold" style="font-size:.95rem;"><?= htmlspecialchars($tareaPendiente['titulo_tarea'] ?? '') ?></div>
              <?php if (!empty($tareaPendiente['descripcion_tarea'])): ?>
                <div class="text-white-50 small mt-1"><?= htmlspecialchars($tareaPendiente['descripcion_tarea']) ?></div>
              <?php endif; ?>
              <div class="text-white-50 small mt-1"><i class="bi bi-collection me-1"></i>Grupo: <?= htmlspecialchars($tareaPendiente['grupo_nombre'] ?? '') ?></div>
            </div>

            <?php if ($proximaFecha): ?>
              <!-- Temporizador dinámico -->
              <div class="w-100 text-center p-3 rounded-3 mb-3 countdown-item" style="background:rgba(0,0,0,0.20); border:1px solid rgba(255,255,255,0.10);" data-deadline="<?= htmlspecialchars($proximaFecha) ?>">
                <div class="text-white-50 small text-uppercase fw-bold mb-2" style="font-size:.7rem;">Entrega <?= $proximaIdx + 1 ?> de <?= $numEntregas ?> — Tiempo restante</div>
                <div class="d-flex justify-content-center gap-3 countdown-digits">
                  <div class="text-center">
                    <div class="text-white fw-bold cdDias" style="font-size:1.8rem; line-height:1;">--</div>
                    <div class="text-white-50" style="font-size:.65rem; text-transform:uppercase;">Días</div>
                  </div>
                  <div class="text-white fw-bold" style="font-size:1.8rem; line-height:1; opacity:0.4;">:</div>
                  <div class="text-center">
                    <div class="text-white fw-bold cdHoras" style="font-size:1.8rem; line-height:1;">--</div>
                    <div class="text-white-50" style="font-size:.65rem; text-transform:uppercase;">Horas</div>
                  </div>
                  <div class="text-white fw-bold" style="font-size:1.8rem; line-height:1; opacity:0.4;">:</div>
                  <div class="text-center">
                    <div class="text-white fw-bold cdMin" style="font-size:1.8rem; line-height:1;">--</div>
                    <div class="text-white-50" style="font-size:.65rem; text-transform:uppercase;">Min</div>
                  </div>
                  <div class="text-white fw-bold" style="font-size:1.8rem; line-height:1; opacity:0.4;">:</div>
                  <div class="text-center">
                    <div class="text-white fw-bold cdSeg" style="font-size:1.8rem; line-height:1;">--</div>
                    <div class="text-white-50" style="font-size:.65rem; text-transform:uppercase;">Seg</div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="w-100 text-center p-3 rounded-3 mb-3" style="background:rgba(0,0,0,0.20); border:1px solid rgba(255,255,255,0.10);">
                <div class="text-white-50 small"><i class="bi bi-check-circle me-1"></i> Todas las entregas de esta tarea han vencido o se han completado.</div>
              </div>
            <?php endif; ?>

            <!-- Lista visual de entregas programadas -->
            <?php if (count($fechasEntregas) > 0): ?>
              <div class="text-white-50 small text-uppercase fw-bold mb-2" style="font-size:.7rem;">Calendario de entregas (<?= $numEntregas ?>)</div>
              <div class="w-100 custom-scrollbar pe-1" style="max-height:160px; overflow-y:auto; overflow-x: hidden;">
                <?php foreach ($fechasEntregas as $idx => $fe):
                  $feTs   = strtotime($fe);
                  $esPasada  = ($feTs !== false && $feTs < time());
                  $esProxima = ($idx === $proximaIdx);
                  $fechaFmt  = $feTs ? date('d/m/Y H:i', $feTs) : $fe;

                  // CONDICIÓN VISUAL: Estado 'Hecha'
                  $hechasCur = json_decode($tareaPendiente['fechas_reales_entregas'] ?? '{}', true) ?: [];
                  $estaHecha = !empty($hechasCur[$idx]);
                ?>
                  <div class="d-flex align-items-center gap-2 py-1 px-2 rounded-2 mb-1"
                       style="background:<?= $esProxima ? 'rgba(74,222,128,0.12)' : 'rgba(255,255,255,0.04)' ?>; border:1px solid <?= $esProxima ? 'rgba(74,222,128,0.25)' : 'rgba(255,255,255,0.06)' ?>;">
                    <span style="font-size:.85rem;">
                      <?php if ($estaHecha): ?>
                        <i class="bi bi-check-circle-fill" style="color:#198754;"></i>
                      <?php elseif ($esPasada): ?>
                        <i class="bi bi-clock-history" style="color:#f87171;"></i>
                      <?php elseif ($esProxima): ?>
                        <i class="bi bi-arrow-right-circle-fill" style="color:#4ade80;"></i>
                      <?php else: ?>
                        <i class="bi bi-circle" style="color:rgba(255,255,255,0.3);"></i>
                      <?php endif; ?>
                    </span>
                    <span class="text-white small fw-medium <?= ($esPasada && !$esProxima && !$estaHecha) ? 'text-decoration-line-through opacity-50' : '' ?>"
                          style="<?= $estaHecha ? 'text-decoration: line-through; text-decoration-color: #198754; text-decoration-thickness: 4px;' : '' ?>">
                      Entrega <?= $idx + 1 ?>: <?= $fechaFmt ?>
                    </span>
                    <?php if ($estaHecha): ?>
                      <span class="badge rounded-pill ms-auto" style="background:rgba(25,135,84,0.2); color:#198754; font-size:.65rem;">HECHA</span>
                    <?php elseif ($esProxima): ?>
                      <span class="badge rounded-pill ms-auto" style="background:rgba(74,222,128,0.2); color:#4ade80; font-size:.65rem;">ACTIVA</span>
                    <?php elseif ($esPasada): ?>
                      <span class="badge rounded-pill ms-auto" style="background:rgba(248,113,113,0.2); color:#f87171; font-size:.65rem;">VENCIDA</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div><!-- /.formulario -->

      <!-- ══════════════════════════════════════════════════════════
           DASHBOARD PROFESOR — Grupos y tutor asignado
      ══════════════════════════════════════════════════════════ -->
      <div class="dashboard">

        <!-- Tarjetas de estadísticas -->
        <div class="row g-3 mb-4 w-100">
          <div class="col-md-4">
            <div class="dashboard-stat-card position-relative h-100">
              <button class="info-popover-btn" tabindex="0"
                data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                data-bs-title="Grupos asignados"
                data-bs-content="Número de grupos de investigación a los que perteneces actualmente.">ⓘ</button>
              <div class="stat-label"><i class="bi bi-collection me-1"></i> Grupos asignados</div>
              <div class="stat-value"><?= $totalGrupos ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card position-relative h-100">
              <button class="info-popover-btn" tabindex="0"
                data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                data-bs-title="Rama de conocimiento"
                data-bs-content="Área de conocimiento ANECA a la que pertenece tu expediente de evaluación.">ⓘ</button>
              <div class="stat-label"><i class="bi bi-diagram-2 me-1"></i> Rama</div>
              <div class="stat-value" style="font-size:1.1rem; word-break:break-word;"><?= htmlspecialchars($rama) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card position-relative h-100">
              <button class="info-popover-btn" tabindex="0"
                data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                data-bs-title="Correo electrónico"
                data-bs-content="Correo electrónico con el que estás registrado en el sistema.">ⓘ</button>
              <div class="stat-label"><i class="bi bi-envelope me-1"></i> Correo</div>
              <div class="stat-value stat-email"><?= htmlspecialchars($correo) ?></div>
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
              <div class="dashboard-stat-card h-100 position-relative">
                <button class="info-popover-btn" tabindex="0"
                  data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                  data-bs-title="Cumplimiento global"
                  data-bs-content="Porcentaje total del expediente respecto a los 100 puntos posibles. Una evaluación POSITIVA requiere al menos 55 puntos totales y que B1+B2 ≥ 50.">ⓘ</button>
                <div class="stat-label">Cumplimiento global</div>
                <div class="stat-value" style="color: <?= $colorProf ?>;"><?= number_format((float)$eval['total_final'], 2) ?>%</div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100 position-relative">
                <button class="info-popover-btn" tabindex="0"
                  data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                  data-bs-title="Estado estimado"
                  data-bs-content="Valoración orientativa del nivel actual: Muy sólida (≥ 70%), Sólida (≥ 50%) o Insuficiente (< 50%) del umbral de evaluación positiva.">ⓘ</button>
                <div class="stat-label">Estado estimado</div>
                <div class="stat-value" style="color: <?= $colorProf ?>;"><?= estadoEstimado($eval) ?></div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100 position-relative">
                <button class="info-popover-btn" tabindex="0"
                  data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                  data-bs-title="Bloques cumplidos"
                  data-bs-content="Número de los 4 bloques de méritos ANECA en los que tienes puntuación superior a 0. Los 4 bloques son: Investigación, Docencia, Formación y Otros méritos.">ⓘ</button>
                <div class="stat-label">Bloques cumplidos</div>
                <div class="stat-value"><?= bloquesCumplidos($eval) ?> <span style="font-size: 1rem;">/ 4</span></div>
              </div>
            </div>
            <div class="col-md-6 col-xl-3">
              <div class="dashboard-stat-card h-100 position-relative">
                <button class="info-popover-btn" tabindex="0"
                  data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
                  data-bs-title="Bloque más débil"
                  data-bs-content="El bloque de méritos en el que tienes la puntuación más baja. Es el área prioritaria de mejora para acercarte a la evaluación positiva.">ⓘ</button>
                <div class="stat-label">Bloque más débil</div>
                <div class="stat-value text-break" style="font-size:1.4rem;"><?= bloqueDebil($eval) ?></div>
              </div>
            </div>
          </div>
          
          <!-- ── DIAGRAMA DE FALTANTES (LO QUE LE FALTA AL CANDIDATO) ── -->
          <div class="row g-3 mb-4 w-100">
            <div class="col-12">
              <div class="dashboard-info-card">
                <h3 class="dashboard-section-title text-white mb-4"><i class="bi bi-pie-chart-fill me-2"></i>Análisis de Faltantes Académicos (Puntos pendientes para el máximo)</h3>
                <div class="row g-3 text-center">
                  <?php 
                    $maximos = [60, 30, 8, 2];
                    $nombres = ['Investigación', 'Docencia', 'Formación', 'Otros'];
                    for($i=1; $i<=4; $i++): 
                      $actual = (float)($eval['bloque_'.$i] ?? 0);
                      $max = $maximos[$i-1];
                      $falta = max(0, $max - $actual);
                      $pct_actual = ($max > 0) ? ($actual / $max) * 100 : 0;
                  ?>
                    <div class="col-6 col-lg-3">
                      <div class="mini-info-box" style="padding: 20px; border: 1px solid rgba(255,255,255,0.05);">
                        <div class="text-white-50 small mb-3"><?= strtoupper($nombres[$i-1]) ?></div>
                        
                        <div style="position: relative; width: 80px; height: 80px; margin: 0 auto 15px;">
                          <svg viewBox="0 0 36 36" style="transform: rotate(-90deg); width: 100%; height: 100%;">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3" />
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f87171" stroke-width="3" stroke-dasharray="<?= 100 - $pct_actual ?>, 100" />
                          </svg>
                          <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; font-weight: bold; font-size: 0.9rem;">
                            -<?= number_format($falta, 1) ?>
                          </div>
                        </div>
                        <div class="text-white small">Faltan <?= number_format($falta, 2) ?> pts</div>
                      </div>
                    </div>
                  <?php endfor; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4 w-100">
            <div class="col-lg-8">
              <div class="dashboard-info-card h-100" style="position:relative;">
                <h3 class="dashboard-section-title text-white mb-3">Resumen de progreso</h3>
                <div class="d-flex align-items-center gap-3 mb-2">
                  <div class="fs-1 fw-bold" style="line-height:1; color: <?= $colorProf ?>;">
                  <?= number_format((float)$eval['total_final'], 2) ?>%
                  <span class="fs-5" style="margin-left:8px;"><?= $iconProf ?></span>
                </div>
                  <div class="flex-grow-1">
                    <div class="progress rounded-pill bg-white bg-opacity-25" style="height: 12px;">
                      <div class="progress-bar" role="progressbar" style="background-color: <?= $gradientProf ?>; width: <?= min(100, max(0, (float)$eval['total_final'])) ?>%;"></div>
                    </div>
                  </div>
                </div>
                <button type="button" class="info-popover-btn" tabindex="0"
                  data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="left"
                  data-bs-title="Código de colores"
                  data-bs-content="● Verde (≥ 70 pts): expediente muy sólido.&#10;&#10;● Ámbar (50–70 pts): en zona intermedia.&#10;&#10;● Rojo (< 50 pts): por debajo del umbral.&#10;&#10;⚠️ La barra puede ser ámbar y la evaluación estar APROBADA si se cumplen las reglas: B1+B2 ≥ 50 y total ≥ 55.">ⓘ
                </button>
                <hr class="border-light opacity-25">
                <div class="row g-2">
                  <div class="col-sm-4">
                    <div class="mini-info-box">
                      <div class="mini-info-label">Resultado</div>
                      <div class="mini-info-value" style="color: <?= $esPositivaProf ? '#4ade80' : '#f87171' ?>; font-weight:800;"><?= htmlspecialchars($eval['resultado'] ?? '-') ?></div>
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

                  <div class="d-flex justify-content-between align-items-center border-bottom border-light border-opacity-25 pb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="text-white-50 small text-uppercase">Bloque 1</span>
                      <button class="info-popover-btn" style="position:static; font-size:.85rem;" tabindex="0"
                        data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="right"
                        data-bs-title="Bloque 1 — Investigación (máx. 60)"
                        data-bs-content="Actividad investigadora: publicaciones científicas, libros, patentes, proyectos de investigación, transferencia tecnológica, dirección de tesis, congresos y otros méritos de investigación.">ⓘ</button>
                    </div>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_1'] ?? 0), 2) ?></strong>
                  </div>

                  <div class="d-flex justify-content-between align-items-center border-bottom border-light border-opacity-25 pb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="text-white-50 small text-uppercase">Bloque 2</span>
                      <button class="info-popover-btn" style="position:static; font-size:.85rem;" tabindex="0"
                        data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="right"
                        data-bs-title="Bloque 2 — Docencia (máx. 30)"
                        data-bs-content="Actividad docente universitaria: horas impartidas, evaluaciones de calidad docente, cursos de formación pedagógica y material o innovación docente.">ⓘ</button>
                    </div>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_2'] ?? 0), 2) ?></strong>
                  </div>

                  <div class="d-flex justify-content-between align-items-center border-bottom border-light border-opacity-25 pb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="text-white-50 small text-uppercase">Bloque 3</span>
                      <button class="info-popover-btn" style="position:static; font-size:.85rem;" tabindex="0"
                        data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="right"
                        data-bs-title="Bloque 3 — Formación y experiencia (máx. 8)"
                        data-bs-content="Formación académica y experiencia profesional: tesis doctoral, becas postdoctorales, estancias de investigación, otros títulos y experiencia en empresas, hospitales o instituciones.">ⓘ</button>
                    </div>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_3'] ?? 0), 2) ?></strong>
                  </div>

                  <div class="d-flex justify-content-between align-items-center pb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="text-white-50 small text-uppercase">Bloque 4</span>
                      <button class="info-popover-btn" style="position:static; font-size:.85rem;" tabindex="0"
                        data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="right"
                        data-bs-title="Bloque 4 — Otros méritos (máx. 2)"
                        data-bs-content="Otros méritos relevantes no incluidos en los bloques anteriores que contribuyen a completar el expediente de evaluación ANECA.">ⓘ</button>
                    </div>
                    <strong class="text-white"><?= number_format((float)($eval['bloque_4'] ?? 0), 2) ?></strong>
                  </div>

                </div>
              </div>
            </div>
            
            <div class="col-12">
              <div class="dashboard-info-card">
                <h3 class="dashboard-section-title text-white mb-4"><i class="bi bi-robot me-2"></i>Asesor orientativo</h3>
                <?php if (!empty($asesorProf)): ?>
                  <?php if (!empty($asesorProf['resumen'])): ?>
                    <p class="text-white mb-4" style="font-size:1rem; line-height:1.6;"><?= htmlspecialchars((string)$asesorProf['resumen']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($asesorProf['acciones']) && is_array($asesorProf['acciones'])): ?>
                    <div class="mini-info-label mb-3" style="font-size:.8rem;">ACCIONES RECOMENDADAS</div>
                    <div class="row g-3 mb-4">
                      <?php foreach ($asesorProf['acciones'] as $accion): ?>
                        <div class="col-md-6">
                          <div class="mini-info-box" style="padding:18px 20px; height:100%;">
                            <div class="mini-info-label mb-2"><?= htmlspecialchars((string)($accion['titulo'] ?? 'Acción')) ?></div>
                            <?php if (!empty($accion['detalle'])): ?>
                              <div class="text-white" style="font-size:.93rem; line-height:1.55;"><?= htmlspecialchars((string)$accion['detalle']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($accion['impacto_estimado'])): ?>
                              <div class="text-white-50 small mt-2"><i class="bi bi-lightning me-1"></i>Impacto estimado: <?= htmlspecialchars((string)$accion['impacto_estimado']) ?></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($asesorProf['simulaciones']) && is_array($asesorProf['simulaciones'])): ?>
                    <div class="mini-info-label mb-3" style="font-size:.8rem;">SIMULACIONES RÁPIDAS</div>
                    <div class="row g-3">
                      <?php foreach ($asesorProf['simulaciones'] as $sim): ?>
                        <div class="col-md-6 col-xl-4">
                          <div class="mini-info-box" style="padding:18px 20px; height:100%;">
                            <div class="mini-info-label mb-2"><?= htmlspecialchars((string)($sim['escenario'] ?? 'Escenario')) ?></div>
                            <?php if (!empty($sim['efecto_estimado'])): ?>
                              <div class="text-white" style="font-size:.93rem; line-height:1.55;"><?= htmlspecialchars((string)$sim['efecto_estimado']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($sim['nuevo_total_aprox'])): ?>
                              <div class="text-white-50 small mt-2"><i class="bi bi-graph-up me-1"></i>Nuevo total ≈ <strong class="text-white"><?= number_format((float)$sim['nuevo_total_aprox'], 2) ?></strong></div>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="text-white-50 small mb-0">No hay consejos del asesor disponibles para esta evaluación.</p>
                <?php endif; ?>
                <div class="text-white-50 small mt-4">Última actualización: <?= htmlspecialchars($eval['fecha_creacion'] ?? '-') ?></div>
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
        <p>&copy; UF3. Todos los derechos reservados.</p>
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
          showNotification("Perfil/Rama no reconocida: " + perfilRaw, 'warning');
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

  <style>
    /* Scrollbar personalizada minimalista (Fina línea) */
    .custom-scrollbar::-webkit-scrollbar {
      width: 3px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.05); 
      border-radius: 10px;
      margin: 10px 0;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.5); 
      border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.8); 
    }
    .custom-scrollbar {
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.5) rgba(255, 255, 255, 0.05);
    }

    /* Botón de info flotante en tarjetas */
    .info-popover-btn {
      position: absolute;
      top: 10px;
      right: 12px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      color: rgba(255,255,255,0.45);
      padding: 0;
      line-height: 1;
      transition: color .2s ease;
    }
    .info-popover-btn:hover, .info-popover-btn:focus {
      color: rgba(255,255,255,0.9);
      outline: none;
    }

    /* Ajuste de posición de notificaciones solicitado: 60px + 10px a la izquierda */
    #toast-container {
      right: 95px !important; /* Original 25px + 70px shift */
    }
  </style>
  <link rel="stylesheet" href="css/notifications.css">
  <script src="js/notifications.js"></script>

  <script>
    // Inicializar todos los popovers
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
        new bootstrap.Popover(el, { html: false });
      });

      // Notificación de validación correcta
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('validated')) {
        showNotification('Validación correcta', 'success');
        // Limpiar URL
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });
  </script>

  <script>
    // ── Countdown dinámico hacia entregas múltiples ───────────────────────
    document.addEventListener('DOMContentLoaded', () => {
      const items = document.querySelectorAll('.countdown-item');
      if (items.length === 0) return;

      setInterval(() => {
        const ahora = new Date().getTime();

        items.forEach(item => {
          const deadlineStr = item.getAttribute('data-deadline');
          if (!deadlineStr) return;

          // Parsear la fecha
          const deadline = new Date(deadlineStr + (deadlineStr.includes('T') ? '' : 'T23:59:59')).getTime();
          let diff = deadline - ahora;

          const cdDias = item.querySelector('.cdDias');
          const cdHoras = item.querySelector('.cdHoras');
          const cdMin = item.querySelector('.cdMin');
          const cdSeg = item.querySelector('.cdSeg');

          if (diff <= 0) {
            if (cdDias) cdDias.textContent = '00';
            if (cdHoras) cdHoras.textContent = '00';
            if (cdMin) cdMin.textContent = '00';
            if (cdSeg) cdSeg.textContent = '00';
            item.style.opacity = '0.5';
            return;
          }

          const dias  = Math.floor(diff / (1000 * 60 * 60 * 24));
          diff -= dias * (1000 * 60 * 60 * 24);
          const horas = Math.floor(diff / (1000 * 60 * 60));
          diff -= horas * (1000 * 60 * 60);
          const min   = Math.floor(diff / (1000 * 60));
          diff -= min * (1000 * 60);
          const seg   = Math.floor(diff / 1000);

          if (cdDias) cdDias.textContent  = String(dias).padStart(2, '0');
          if (cdHoras) cdHoras.textContent = String(horas).padStart(2, '0');
          if (cdMin) cdMin.textContent   = String(min).padStart(2, '0');
          if (cdSeg) cdSeg.textContent   = String(seg).padStart(2, '0');
        });
      }, 1000);
    });
  </script>

  <?php include('chatbot.php'); ?>

  <?php if (!empty($notifsPendientes)): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      <?php foreach ($notifsPendientes as $notifMsg): ?>
      showNotificationPersistent(<?= json_encode(htmlspecialchars_decode($notifMsg)) ?>, 'warning');
      <?php endforeach; ?>
    });
  </script>
  <?php endif; ?>

</body>

</html>