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
  $nombre = htmlspecialchars($datos_perfil['nombre'] ?? '');
  $apellidos = htmlspecialchars($datos_perfil['apellidos'] ?? '');
  $dni = htmlspecialchars($datos_perfil['dni'] ?? '');
  $orcid = htmlspecialchars($datos_perfil['orcid'] ?? '');

  // Datos extra
  $correo = htmlspecialchars($datos_perfil['correo'] ?? '');
  $departamento = htmlspecialchars($datos_perfil['departamento'] ?? '');
  $telefono = htmlspecialchars($datos_perfil['telefono'] ?? '');
  $facultad = htmlspecialchars($datos_perfil['facultad'] ?? '');
  $rama = htmlspecialchars($datos_perfil['rama'] ?? '');

  // ── Propagar a sesión para que los evaluadores conozcan ORCID y rama ──
  $_SESSION['orcid_usuario'] = $datos_perfil['orcid'] ?? '';
  $_SESSION['rama_usuario']  = $datos_perfil['rama']  ?? '';

} else {
  $nombre = 'No registrado';
  $apellidos = 'No registrado';
  $dni = 'No registrado';
  $orcid = 'No registrado';
  $correo = 'No registrado';
  $departamento = 'No registrado';
  $telefono = 'No registrado';
  $facultad = 'No registrado';
  $rama = 'No registrado';
}

// ── Dashboard: id del tutor desde su correo de sesión ──────────────────
$correoRaw = $_SESSION['nombredelusuario'];

$resId = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE correo = '" . mysqli_real_escape_string($conn, $correoRaw) . "' AND perfil = 'TUTOR' LIMIT 1");
$rowId  = $resId ? mysqli_fetch_assoc($resId) : null;
$idTutor = $rowId ? (int)$rowId['id_profesor'] : 0;

// Grupos del tutor
$resGrupos = mysqli_query($conn, "SELECT id_grupo, nombre FROM tbl_grupo WHERE id_tutor = $idTutor ORDER BY nombre");
$grupos = [];
if ($resGrupos) { while ($g = mysqli_fetch_assoc($resGrupos)) $grupos[] = $g; }
$totalGrupos = count($grupos);

// Profesores asignados al tutor a través de sus grupos
$resProfesores = mysqli_query($conn,
  "SELECT DISTINCT p.id_profesor, p.nombre, p.apellidos, p.correo, p.departamento, p.facultad, p.rama, g.nombre AS grupo
   FROM tbl_grupo g
   INNER JOIN tbl_grupo_profesor gp ON gp.id_grupo = g.id_grupo
   INNER JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
   WHERE g.id_tutor = $idTutor AND p.perfil = 'PROFESOR'
   ORDER BY p.apellidos, p.nombre"
);
$profesores = [];
if ($resProfesores) { while ($p = mysqli_fetch_assoc($resProfesores)) $profesores[] = $p; }
$totalProfesores = count($profesores);
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
      <div class="formulario">
        <div class="text-center mb-4 w-100">
          <i class="bi bi-person-vcard text-white mb-2"
            style="font-size: 4rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>
          <h2 class="text-white fw-bold">Perfil de Tutor</h2>
          <hr class="w-100 border-light opacity-25 mt-3 mb-1">
        </div>

        <div class="lista-perfil w-100 px-lg-4">
          <ul class="list-unstyled d-flex flex-column gap-2 mb-0 text-start w-100 mx-auto">

            <!-- ✅ Datos visibles siempre -->
            <li
              class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-person me-1"></i> Nombre
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $nombre ?: '-'; ?>
              </span>
            </li>

            <li
              class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-people me-1"></i> Apellidos
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $apellidos ?: '-'; ?>
              </span>
            </li>

            <li
              class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-card-heading me-1"></i> DNI
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $dni ?: '-'; ?>
              </span>
            </li>

            <li
              class="d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-globe me-1"></i> ORCID
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $orcid ?: '-'; ?>
              </span>
            </li>

            <!-- ✅ Datos ocultos por defecto → se mostrarán al pulsar el botón -->
            <li
              class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-envelope me-1"></i> Correo
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $correo ?: '-'; ?>
              </span>
            </li>

            <li
              class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-building me-1"></i> Departamento
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $departamento ?: '-'; ?>
              </span>
            </li>

            <li
              class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-telephone me-1"></i> Teléfono
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $telefono ?: '-'; ?>
              </span>
            </li>

            <li
              class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 py-2 px-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-mortarboard me-1"></i> Facultad
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $facultad ?: '-'; ?>
              </span>
            </li>

            <li
              class="extraDato d-none d-flex flex-column bg-light bg-opacity-10 p-3 rounded-4 border border-light border-opacity-25 shadow-sm">
              <span class="text-white-50 small mb-1 fw-bold text-uppercase">
                <i class="bi bi-diagram-2 me-1"></i> Rama
              </span>
              <span class="fs-5 fw-medium text-white ms-1">
                <?php echo $rama ?: '-'; ?>
              </span>
            </li>

          </ul>
        </div>

        <hr class="w-100 border-light my-4 opacity-25">

        <div class="d-flex flex-wrap justify-content-center gap-3 w-100 mb-2">

          <button type="button" id="btnMostrarTodo"
            class="btn btn-primary px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 shadow-sm transition-all"
            style="background-color: rgba(20, 88, 204, 0.8); border: none;">
            <i class="bi bi-person-lines-fill"></i> Mostrar todos mis datos
          </button>

          <a href="grupos_profesor.php"
            class="btn btn-outline-light px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all text-decoration-none">
            <i class="bi bi-people-fill"></i> Mostrar mis grupos de profesores
          </a>
          <a href="../../acelerador_login/fronten/logout.php"
            class="btn btn-outline-danger px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all text-decoration-none">
            <i class="bi bi-box-arrow-right"></i> Cerrar sesión
          </a>

          <a href="../../acelerador_primerapantallas/fronten/index.php"
            class="btn btn-outline-light px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 transition-all"><i
              class="bi bi-arrow-clockwise"></i> Actualizar mis datos</a>

          <button type="button" id="subirdatos" data-rama="<?php echo htmlspecialchars($rama, ENT_QUOTES, 'UTF-8'); ?>"
            class="btn btn-outline-info px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2 text-white border-info">
            <i class="bi bi-file-earmark-plus"></i> Añadir trabajos/artículos
          </button>


        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════
           DASHBOARD TUTOR — Grupos y profesores asignados
      ════════════════════════════════════════════════════════ -->
      <div class="dashboard">

        <!-- Tarjetas de estadísticas -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="dashboard-stat-card">
              <div class="stat-label"><i class="bi bi-collection me-1"></i> Grupos asignados</div>
              <div class="stat-value"><?= $totalGrupos ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card">
              <div class="stat-label"><i class="bi bi-person-workspace me-1"></i> Profesores tutorizados</div>
              <div class="stat-value"><?= $totalProfesores ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card">
              <div class="stat-label"><i class="bi bi-envelope me-1"></i> Correo del tutor</div>
              <div class="stat-value stat-email"><?= htmlspecialchars($correo) ?></div>
            </div>
          </div>
        </div>

        <!-- Resumen del tutor -->
        <div class="row g-3 mb-4 w-100">
          <div class="col-12">
            <div class="dashboard-info-card">
              <h2 class="dashboard-section-title"><i class="bi bi-info-circle me-2"></i>Resumen del tutor</h2>
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
                    <div class="mini-info-label">Rama</div>
                    <div class="mini-info-value"><?= htmlspecialchars($rama) ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tarjetas de profesores -->
        <?php if ($totalProfesores > 0): ?>
        <h2 class="dashboard-section-title mb-3"><i class="bi bi-people-fill me-2"></i>Mis profesores</h2>
        <div class="row g-3 w-100">
          <?php foreach ($profesores as $prof): ?>
            <div class="col-12">
              <div class="prof-panel-card p-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                  <div class="flex-grow-1">
                    <div class="prof-panel-name mb-1">
                      <?= htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) ?>
                    </div>
                    <div class="prof-panel-grupo text-white-50 small mb-0">
                      <i class="bi bi-collection me-1"></i>Grupo: <strong><?= htmlspecialchars($prof['grupo']) ?></strong> | 
                      Rama: <?= htmlspecialchars($prof['rama']) ?> |
                      <a href="mailto:<?= htmlspecialchars($prof['correo']) ?>" class="text-white-50 text-decoration-none ms-1"><i class="bi bi-envelope"></i> <?= htmlspecialchars($prof['correo']) ?></a>
                    </div>
                  </div>
                  
                  <div class="ms-lg-3 text-lg-end">
                    <a href="../../dashboard_profesor.php?nombre=<?= urlencode($prof['nombre']) ?>&rama=<?= urlencode($prof['rama']) ?>" class="btn btn-sm btn-primary w-100 rounded-pill">
                      <i class="bi bi-diagram-3 me-1"></i> Ver dashboard
                    </a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info rounded-4">
          <i class="bi bi-info-circle me-2"></i>No hay profesores asignados a tus grupos todavía.
        </div>
        <?php endif; ?>

      </div>

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


  <script>
    document.addEventListener("DOMContentLoaded", () => {
      const boton = document.getElementById("btnMostrarTodo");
      const extraDatos = document.querySelectorAll(".extraDato");

      boton.addEventListener("click", () => {

        // ✅ Si los datos extra están ocultos → mostrarlos
        if (extraDatos[0].classList.contains("d-none")) {

          extraDatos.forEach(el => el.classList.remove("d-none"));

          boton.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Mostrar resumen datos';

        }
        // ✅ Si están visibles → ocultarlos
        else {

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
          .replace(/[\u0300-\u036f]/g, ""); // quita tildes (TÉCNICAS -> TECNICAS)

        // 2) Mapa de rutas (SIN prefijo del proyecto todavía)
        const rutas = {
          "CSYJ": "/evaluador/evaluador_aneca_csyj/index.php",
          "EXPERIMENTALES": "/evaluador/evaluador_aneca_experimentales/index.php",
          "HUMANIDADES": "/evaluador/evaluador_aneca_humanidades/index.php",
          "SALUD": "/evaluador/evaluador_aneca_salud/index.php",
          "TECNICA": "/evaluador/evaluador_aneca_tecnicas/index.php"
        };

        const rutaRelativa = rutas[perfilRaw];
        if (!rutaRelativa) {
          alert("Perfil/Rama no reconocida: " + perfilRaw);
          console.warn("[VALIDAR] Rama desconocida:", btnValidar.dataset.rama, "->", perfilRaw);
          return;
        }

        // 3) Detectar prefijo del proyecto automáticamente
        // Si estás en /Proyecto-Acelerador/acelerador_panel/fronten/....
        // esto devuelve "/Proyecto-Acelerador"
        const path = window.location.pathname;
        const base = path.split("/acelerador_panel/")[0] || "";
        // Si algún día esta página no está en acelerador_panel, me lo dices y lo ajustamos.

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