
<?php
$idTutor = isset($_GET['id_tutor']) ? (int)$_GET['id_tutor'] : 48;

// Conexión usando el host del contenedor Docker (o variable de entorno)
$dbHost = getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos');
$dbUser = getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'usuario_web');
$dbPass = getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'password_segura');
$dbPort = (int)(getenv('ACELERADOR_DB_PORT') ?: getenv('DB_PORT') ?: 3306);

$pdo = new PDO(
    "mysql:host={$dbHost};port={$dbPort};dbname=acelerador;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);


/**
 * Tutor
 */
$sqlTutor = "
    SELECT
        id_profesor,
        nombre,
        apellidos,
        facultad,
        departamento,
        correo,
        rama,
        perfil
    FROM tbl_profesor
    WHERE id_profesor = :id_tutor
      AND perfil = 'TUTOR'
";
$stmtTutor = $pdo->prepare($sqlTutor);
$stmtTutor->execute(['id_tutor' => $idTutor]);
$tutor = $stmtTutor->fetch();

/**
 * Grupos del tutor
 */
$sqlGrupos = "
    SELECT
        g.id_grupo,
        g.nombre
    FROM tbl_grupo g
    WHERE g.id_tutor = :id_tutor
    ORDER BY g.nombre
";
$stmtGrupos = $pdo->prepare($sqlGrupos);
$stmtGrupos->execute(['id_tutor' => $idTutor]);
$grupos = $stmtGrupos->fetchAll();

/**
 * Profesores asignados al tutor a través de sus grupos
 */
$sqlProfesores = "
    SELECT DISTINCT
        p.id_profesor,
        p.nombre,
        p.apellidos,
        p.correo,
        p.departamento,
        p.facultad,
        p.rama,
        g.nombre AS grupo
    FROM tbl_grupo g
    INNER JOIN tbl_grupo_profesor gp ON gp.id_grupo = g.id_grupo
    INNER JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
    WHERE g.id_tutor = :id_tutor
      AND p.perfil = 'PROFESOR'
    ORDER BY p.apellidos, p.nombre
";
$stmtProfesores = $pdo->prepare($sqlProfesores);
$stmtProfesores->execute(['id_tutor' => $idTutor]);
$profesores = $stmtProfesores->fetchAll();

$totalProfesores = count($profesores);
$totalGrupos = count($grupos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Tutor</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --primary: #123f82;
      --primary-dark: #0f356d;
      --text-main: #123b72;
      --bg-page: #eef2f7;
      --card-border: #d9e2ee;
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
    .prof-card { padding: 18px; }
    .prof-name {
      color: var(--text-main);
      font-size: 1.15rem;
      font-weight: 700;
      margin-bottom: .2rem;
    }
    .mini-box {
      background: #f5f8fc;
      border: 1px solid #e1e9f3;
      border-radius: 12px;
      padding: 10px 12px;
    }
    .mini-label {
      font-size: .78rem;
      text-transform: uppercase;
      color: #7b8ba3;
      margin-bottom: .2rem;
    }
    .mini-value {
      font-size: 1rem;
      font-weight: 700;
      color: #193f7a;
      line-height: 1.15;
    }
  </style>
</head>
<body>

<header class="top-header">
  <div class="container">
    <?php if ($tutor): ?>
      <h1 class="h2 mb-1"><?= htmlspecialchars($tutor['nombre'] . ' ' . $tutor['apellidos']) ?></h1>
      <div>
        Tutor académico · <?= htmlspecialchars($tutor['departamento']) ?> · <?= htmlspecialchars($tutor['rama']) ?>
      </div>
    <?php else: ?>
      <h1 class="h2 mb-1">Tutor no encontrado</h1>
    <?php endif; ?>
  </div>
</header>

<main class="py-4">
  <div class="container">
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="dashboard-card stat-card">
          <div class="stat-label">Grupos asignados</div>
          <div class="stat-value"><?= $totalGrupos ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="dashboard-card stat-card">
          <div class="stat-label">Profesores tutorizados</div>
          <div class="stat-value"><?= $totalProfesores ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="dashboard-card stat-card">
          <div class="stat-label">Correo del tutor</div>
          <div class="stat-value" style="font-size:1.1rem;">
            <?= $tutor ? htmlspecialchars($tutor['correo']) : '-' ?>
          </div>
        </div>
      </div>
    </div>

    <div class="dashboard-card p-4 mb-4">
      <h2 class="h4 mb-3" style="color:#123b72;">Resumen del tutor</h2>
      <?php if ($tutor): ?>
        <p class="mb-1"><strong>Facultad:</strong> <?= htmlspecialchars($tutor['facultad']) ?></p>
        <p class="mb-1"><strong>Departamento:</strong> <?= htmlspecialchars($tutor['departamento']) ?></p>
        <p class="mb-0"><strong>Rama:</strong> <?= htmlspecialchars($tutor['rama']) ?></p>
      <?php else: ?>
        <p class="mb-0">No hay datos para este tutor.</p>
      <?php endif; ?>
    </div>

    <div class="row g-3">
      <?php foreach ($profesores as $prof): ?>
        <div class="col-md-6 col-xl-4">
          <div class="dashboard-card prof-card">
            <div class="prof-name">
              <?= htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']) ?>
            </div>
            <div class="text-muted mb-3">
              Grupo: <?= htmlspecialchars($prof['grupo']) ?>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="mini-box">
                  <div class="mini-label">Departamento</div>
                  <div class="mini-value"><?= htmlspecialchars($prof['departamento']) ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="mini-box">
                  <div class="mini-label">Rama</div>
                  <div class="mini-value"><?= htmlspecialchars($prof['rama']) ?></div>
                </div>
              </div>
              <div class="col-12">
                <div class="mini-box">
                  <div class="mini-label">Correo</div>
                  <div class="mini-value"><?= htmlspecialchars($prof['correo']) ?></div>
                </div>
              </div>
            </div>

            <a href="dashboard_profesor.php?nombre=<?= urlencode($prof['nombre']) ?>&rama=<?= urlencode($prof['rama']) ?>"
               class="btn btn-primary">
              Ver dashboard profesor
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</main>

</body>
</html>