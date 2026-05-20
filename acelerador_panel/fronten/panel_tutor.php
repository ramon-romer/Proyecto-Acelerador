<?php
ob_start();
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once('../../acelerador_frontend_security.php');
include('login.php');

// Protección global contra CSRF en todas las acciones de modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    acelerador_require_csrf();
}

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

  // -- Propagar a sesión para que los evaluadores conozcan ORCID y rama --
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

// -- Dashboard: id del tutor desde su correo de sesión -----------------
$correoRaw = $_SESSION['nombredelusuario'];

$resId = mysqli_query($conn, "SELECT id_profesor FROM tbl_profesor WHERE correo = '" . mysqli_real_escape_string($conn, $correoRaw) . "' AND perfil = 'TUTOR' LIMIT 1");
$rowId  = $resId ? mysqli_fetch_assoc($resId) : null;
$idTutor = $rowId ? (int)$rowId['id_profesor'] : 0;

// -- ACCIÓN: eliminar_cuenta_propia (TUTOR) ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_cuenta_propia') {
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_info_usuario_eliminado (
      id INT AUTO_INCREMENT PRIMARY KEY,
      id_profesor_original INT NOT NULL,
      correo VARCHAR(255) NOT NULL,
      datos_completos LONGTEXT NOT NULL,
      fecha_eliminacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $info_json = [];
    $q_prof = mysqli_query($conn, "SELECT * FROM tbl_profesor WHERE id_profesor = $idTutor");
    if($q_prof) $info_json['tbl_profesor'] = mysqli_fetch_assoc($q_prof);
    
    $q_usu = mysqli_query($conn, "SELECT * FROM tbl_usuario WHERE correo = '$correoRaw'");
    if($q_usu) $info_json['tbl_usuario'] = mysqli_fetch_assoc($q_usu);

    $info_json['tbl_grupo_profesor'] = [];
    $q_gp = mysqli_query($conn, "SELECT * FROM tbl_grupo_profesor WHERE id_profesor = $idTutor");
    if($q_gp) { while($r = mysqli_fetch_assoc($q_gp)) $info_json['tbl_grupo_profesor'][] = $r; }

    $info_json['tbl_grupo_creados'] = [];
    $q_gc = mysqli_query($conn, "SELECT * FROM tbl_grupo WHERE id_tutor = $idTutor");
    if($q_gc) { while($r = mysqli_fetch_assoc($q_gc)) $info_json['tbl_grupo_creados'][] = $r; }


    $json_str = mysqli_real_escape_string($conn, json_encode($info_json, JSON_UNESCAPED_UNICODE));
    mysqli_query($conn, "INSERT INTO tbl_info_usuario_eliminado (id_profesor_original, correo, datos_completos) VALUES ($idTutor, '$correoRaw', '$json_str')");

    // LÓGICA DE CASCADA TUTOR
    mysqli_query($conn, "DELETE FROM tbl_tarea_entrega WHERE id_tutor = $idTutor");
    mysqli_query($conn, "DELETE FROM tbl_grupo_profesor WHERE id_grupo IN (SELECT id_grupo FROM tbl_grupo WHERE id_tutor = $idTutor)");
    mysqli_query($conn, "DELETE FROM tbl_grupo WHERE id_tutor = $idTutor");

    mysqli_query($conn, "DELETE FROM tbl_profesor WHERE id_profesor = $idTutor");
    mysqli_query($conn, "DELETE FROM tbl_usuario WHERE correo = '$correoRaw'");

    session_destroy();
    header("Location: ../../acelerador_login/fronten/index.php?msg=cuenta_eliminada");
    exit();
}

// ------------------------------------------------------------------------- ACCIÓN: decision_readmision (TUTOR) --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'decision_readmision') {
    $id_solicitud = (int)($_POST['id_solicitud'] ?? 0);
    $decision = $_POST['decision'] ?? ''; // 'ACEPTAR' o 'RECHAZAR'

    if ($id_solicitud > 0) {
        $q_sol = mysqli_query($conn, "SELECT * FROM tbl_solicitud_readmision WHERE id = $id_solicitud AND id_tutor = $idTutor");
        if ($q_sol && $sol = mysqli_fetch_assoc($q_sol)) {
            $id_prof_r = $sol['id_profesor'];
            $id_grupo_r = $sol['id_grupo'];

            if ($decision === 'ACEPTAR') {
                mysqli_query($conn, "INSERT IGNORE INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES ($id_grupo_r, $id_prof_r)");
                mysqli_query($conn, "UPDATE tbl_solicitud_readmision SET estado = 'ACEPTADA' WHERE id = $id_solicitud");
                mysqli_query($conn, "DELETE FROM tbl_notificacion_tutor WHERE id_tutor = $idTutor AND tipo = 'readmision' AND id_referencia = $id_prof_r");
                header("Location: panel_tutor.php?msg=readmitido");
                exit();
            } else {
                mysqli_query($conn, "UPDATE tbl_solicitud_readmision SET estado = 'RECHAZADA' WHERE id = $id_solicitud");
                // Notificar al profesor el rechazo
                mysqli_query($conn, "CREATE TABLE IF NOT EXISTS tbl_notificacion_pendiente (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  id_profesor INT NOT NULL,
                  mensaje TEXT NOT NULL,
                  fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
                $msg_notif = mysqli_real_escape_string($conn, "Se le asignara un grupo a la mayor brevedad posible");
                mysqli_query($conn, "INSERT INTO tbl_notificacion_pendiente (id_profesor, mensaje) VALUES ($id_prof_r, '$msg_notif')");
                mysqli_query($conn, "DELETE FROM tbl_notificacion_tutor WHERE id_tutor = $idTutor AND tipo = 'readmision' AND id_referencia = $id_prof_r");
                header("Location: panel_tutor.php?msg=rechazado");
                exit();
            }
        }
    }
}

// ------------------------------------------------------------------------- Notificaciones del tutor (incluyendo readmisiones) -----------------------------------
$notifsTutor = [];
if ($idTutor > 0) {
    $tablaExistsT = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_notificacion_tutor'");
    if ($tablaExistsT && mysqli_num_rows($tablaExistsT) > 0) {
        $qNotT = mysqli_query($conn, "SELECT n.*, s.id as id_solicitud FROM tbl_notificacion_tutor n 
                                     LEFT JOIN tbl_solicitud_readmision s ON n.id_referencia = s.id_profesor AND s.estado = 'PENDIENTE'
                                     WHERE n.id_tutor = $idTutor ORDER BY n.fecha_creacion ASC");
        if ($qNotT) {
            while ($rt = mysqli_fetch_assoc($qNotT)) {
                $notifsTutor[] = $rt;
            }
        }
    }
}
$mapaDB = [
    'CSYJ' => 'evaluador_aneca_csyj', 'EXPERIMENTALES' => 'evaluador_aneca_experimentales',
    'HUMANIDADES' => 'evaluador_aneca_humanidades', 'SALUD' => 'evaluador_aneca_salud',
    'TECNICA' => 'evaluador_aneca_tecnicas', 'TECNICAS' => 'evaluador_aneca_tecnicas',
];
$dbHost = getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos');
$dbUser = getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'root');
$dbPass = getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'root_super_segura');
$dbPort = (int)(getenv('ACELERADOR_DB_PORT') ?: getenv('DB_PORT') ?: 3306);

// ------------------------------------------------------------------------- ACCIÓN: get_estado_candidato (ADITIVO - DISEÑADOR SENIOR / TARJETAS) -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'get_estado_candidato') {
    $orcid_req = mysqli_real_escape_string($conn, $_POST['orcid'] ?? '');
    
    // Obtenemos datos del profesor para rama
    $res_p = mysqli_query($conn, "SELECT rama FROM `tbl_profesor` WHERE `ORCID` = '$orcid_req' LIMIT 1");
    $p_info = mysqli_fetch_assoc($res_p);
    
    if ($p_info) {
        $r_norm = strtoupper(trim($p_info['rama'] ?? ''));
        $db_n = $mapaDB[$r_norm] ?? null;
        if ($db_n) {
            try {
                $pdo_e = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$db_n};charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                $orcid_limpio = str_replace('-', '', $orcid_req);
                $dni_prof = $p_info['dni'] ?? ($p_info['DNI'] ?? '');

                // 1. Conteo total de evaluaciones históricas
                $stCount = $pdo_e->prepare("SELECT COUNT(*) as total FROM evaluaciones WHERE json_entrada LIKE :o1 OR json_entrada LIKE :o2 OR json_entrada LIKE :d1");
                $stCount->execute([':o1' => '%'.$orcid_req.'%', ':o2' => '%'.$orcid_limpio.'%', ':d1' => '%'.$dni_prof.'%']);
                $totalEvals = (int)$stCount->fetch()['total'];

                // 2. Última evaluación para extraer JSON bruto integral
                $stLast = $pdo_e->prepare("SELECT json_entrada FROM evaluaciones WHERE json_entrada LIKE :o1 OR json_entrada LIKE :o2 OR json_entrada LIKE :d1 ORDER BY fecha_creacion DESC LIMIT 1");
                $stLast->execute([':o1' => '%'.$orcid_req.'%', ':o2' => '%'.$orcid_limpio.'%', ':d1' => '%'.$dni_prof.'%']);
                $lastEv = $stLast->fetch();

                $meritData = [
                    'meta' => ['total_evals' => $totalEvals],
                    'bloque1' => [
                        'publicaciones' => 0, 'libros' => 0, 'proyectos' => 0, 'transferencia' => 0, 
                        'tesis' => 0, 'congresos' => 0, 'otros' => 0
                    ],
                    'bloque2' => [
                        'docencia' => 0, 'evaluacion' => 0, 'formacion' => 0, 'material' => 0, 'total_horas' => 0
                    ],
                    'bloque3' => [
                        'formacion' => 0, 'anios_exp' => 0
                    ]
                ];

                if ($lastEv && !empty($lastEv['json_entrada'])) {
                    $json = json_decode($lastEv['json_entrada'], true);
                    if ($json) {
                        $b1 = $json['bloque_1'] ?? [];
                        $b2 = $json['bloque_2'] ?? [];
                        $b3 = $json['bloque_3'] ?? [];

                        // Bloque 1: Investigación
                        $meritData['bloque1']['publicaciones'] = count($b1['publicaciones'] ?? []);
                        $meritData['bloque1']['libros']        = count($b1['libros'] ?? []);
                        $meritData['bloque1']['proyectos']     = count($b1['proyectos'] ?? []);
                        $meritData['bloque1']['transferencia'] = count($b1['transferencia_resultados'] ?? []);
                        $meritData['bloque1']['tesis']         = count($b1['tesis_dirigidas'] ?? []);
                        $meritData['bloque1']['congresos']     = count($b1['congresos'] ?? []);
                        $meritData['bloque1']['otros']         = count($b1['otros_meritos_investigacion'] ?? []);
                        
                        // Bloque 2: Docencia
                        $meritData['bloque2']['docencia']   = count($b2['docencia_universitaria'] ?? []);
                        $meritData['bloque2']['evaluacion'] = count($b2['evaluacion_docente'] ?? []);
                        $meritData['bloque2']['formacion']  = count($b2['formacion_docente'] ?? []);
                        $meritData['bloque2']['material']   = count($b2['material_docente'] ?? []);
                        
                        $sumH = 0;
                        foreach (($b2['docencia_universitaria'] ?? []) as $item) {
                            if (isset($item['horas'])) $sumH += (float)$item['horas'];
                        }
                        $meritData['bloque2']['total_horas'] = $sumH;

                        // Bloque 3: Formación y Exp
                        $meritData['bloque3']['formacion'] = count($b3['formacion_academica'] ?? []);
                        $expProf = $b3['experiencia_profesional'] ?? [];
                        $meritData['bloque3']['anios_exp'] = (float)($expProf[0]['anios'] ?? 0);
                    }
                }

                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'success', 'data' => $meritData]);
                exit;

            } catch (Exception $e) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar el inventario.']);
    exit;
}

// ------------------------------------------------------------------------- ACCIÓN: marcar_entrega_hecha (ARQUITECTURA DE SISTEMAS CRÍTICOS) ---------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && ($_POST['accion'] === 'marcar_entrega_hecha' || $_POST['accion'] === 'marcar_tarea_hecha')) {
    $id_tarea = (int)($_POST['id_tarea'] ?? 0);
    $indice = (int)($_POST['indice'] ?? 0);
    $ahora = date('Y-m-d H:i:s');

    if ($id_tarea > 0) {
        try {
            $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname=acelerador;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Asegurar existencia de columna
            $pdo->exec("ALTER TABLE tbl_tarea_entrega ADD COLUMN IF NOT EXISTS fechas_reales_entregas JSON DEFAULT NULL");

            // 1. Obtener estado actual
            $stmt = $pdo->prepare("SELECT fechas_reales_entregas FROM tbl_tarea_entrega WHERE id = ?");
            $stmt->execute([$id_tarea]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $reales = json_decode($row['fechas_reales_entregas'] ?? '[]', true) ?: [];
            
            // 2. Registrar fecha real de finalización para el índice actual
            $reales[$indice] = $ahora; 
            
            // 3. Persistencia (La entrega N+1 se activará visualmente al usar esta fecha como inicio)
            $stmtUpdate = $pdo->prepare("UPDATE tbl_tarea_entrega SET fechas_reales_entregas = ? WHERE id = ?");
            $stmtUpdate->execute([json_encode($reales), $id_tarea]);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'timestamp' => $ahora]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// ------------------------------------------------------------------------- ACCIÓN: get_datos_gantt (DIFUSIÓN DE DATOS CRÍTICOS - ALTO RENDIMIENTO) --------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'get_datos_gantt') {
    $pid_req = (int)($_POST['id_profesor'] ?? 0);
    if ($pid_req > 0) {
        try {
            // Asegurar que la columna de fechas reales existe (Compatibilidad total)
            $checkCol = mysqli_query($conn, "SHOW COLUMNS FROM tbl_tarea_entrega LIKE 'fechas_reales_entregas'");
            if ($checkCol && mysqli_num_rows($checkCol) == 0) {
                mysqli_query($conn, "ALTER TABLE tbl_tarea_entrega ADD COLUMN fechas_reales_entregas JSON DEFAULT NULL");
            }

            // SELECT quirúrgico: capturamos solo lo indispensable para el gráfico
            // Flexibilizamos eliminando AND id_tutor = $idTutor para evitar fallos por inconsistencias de transferencia
            $resTareas = mysqli_query($conn, 
                "SELECT id, titulo_tarea, fecha_creacion, num_entregas, fechas_entregas, fechas_reales_entregas 
                 FROM tbl_tarea_entrega 
                 WHERE id_profesor = $pid_req 
                 ORDER BY fecha_creacion DESC"
            );
            
            if (!$resTareas) {
                throw new Exception(mysqli_error($conn));
            }

            $gData = [];
            while ($t = mysqli_fetch_assoc($resTareas)) {
                $numEntregas = (int)($t['num_entregas'] ?? 1);
                $hechas = json_decode($t['fechas_reales_entregas'] ?? '[]', true) ?: [];
                $todasHechas = true;
                for ($idx = 0; $idx < $numEntregas; $idx++) {
                    if (empty($hechas[$idx])) {
                        $todasHechas = false;
                        break;
                    }
                }
                if ($todasHechas) {
                    continue; // Quitar del gráfico de Gantt al estar todos los hitos cumplidos
                }

                $gData[] = [
                    'id' => $t['id'], 
                    'titulo' => $t['titulo_tarea'], 
                    'creacion' => $t['fecha_creacion'],
                    'n' => $numEntregas, 
                    'teoricas' => json_decode($t['fechas_entregas'] ?? '[]', true),
                    'reales' => $hechas
                ];
            }
            
            // Blindaje del flujo JSON
            if (ob_get_length()) ob_clean(); 
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'data' => $gData]);
            exit;
        } catch (Exception $e) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
    exit;
}

// --------------------€â”€ Procesar actualización de tarea (POST) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_tarea') {
    
    // DETECCIÓN DE MODO EDICIÓN (ADITIVO - CONTRATO PDO)
    if (isset($_POST['id_tarea']) && (int)$_POST['id_tarea'] > 0) {
        $id_tarea_edit = (int)$_POST['id_tarea'];
        $titulo_tarea = trim($_POST['titulo_tarea'] ?? '');
        $desc_tarea   = trim($_POST['descripcion_tarea'] ?? '');
        $num_entregas = max(1, intval($_POST['num_entregas'] ?? 1));
        $fechas_arr   = isset($_POST['fecha_entrega']) && is_array($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : [];
        $fechas_arr   = array_values(array_filter($fechas_arr, function($f) { return !empty(trim($f)); }));
        $fechas_json  = json_encode($fechas_arr, JSON_UNESCAPED_UNICODE);

        if (!empty($titulo_tarea) && count($fechas_arr) > 0) {
            // Validar orden cronológico de las entregas
            $fechasValidas = true;
            for ($i = 1; $i < count($fechas_arr); $i++) {
                if (strtotime($fechas_arr[$i]) < strtotime($fechas_arr[$i - 1])) {
                    $fechasValidas = false;
                    break;
                }
            }
            if (!$fechasValidas) {
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['status' => 'warning', 'message' => 'Error: Cada entrega debe ser programada para una fecha posterior o igual a la anterior.']);
                    exit;
                }
                $mensaje = 'Error: Cada entrega debe ser programada para una fecha posterior o igual a la anterior.';
                $tipo_mensaje = 'warning';
            } else {
                try {
                    // Configuración para PDO
                    $dbCfg = [
                        'host' => getenv('ACELERADOR_DB_HOST') ?: (getenv('DB_HOST') ?: 'base-de-datos'),
                        'user' => getenv('ACELERADOR_DB_USER') ?: (getenv('DB_USER') ?: 'root'),
                        'pass' => getenv('ACELERADOR_DB_PASS') ?: (getenv('DB_PASS') ?: 'root_super_segura'),
                        'port' => (int)(getenv('ACELERADOR_DB_PORT') ?: getenv('DB_PORT') ?: 3306),
                        'name' => 'acelerador'
                    ];
                    $pdoU = new PDO("mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['name']};charset=utf8mb4", $dbCfg['user'], $dbCfg['pass'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    $sqlU = "UPDATE tbl_tarea_entrega SET titulo_tarea = ?, descripcion_tarea = ?, num_entregas = ?, fechas_entregas = ? WHERE id = ? AND id_tutor = ?";
                    $stmtU = $pdoU->prepare($sqlU);
                    $stmtU->execute([$titulo_tarea, $desc_tarea, $num_entregas, $fechas_json, $id_tarea_edit, $idTutor]);

                    if (isset($_POST['ajax'])) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['status' => 'success', 'message' => 'Tarea actualizada con éxito.']);
                        exit;
                    }
                    $mensaje = 'Tarea actualizada correctamente.';
                    $tipo_mensaje = 'success';
                } catch (Exception $e) {
                    if (isset($_POST['ajax'])) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
                        exit;
                    }
                    $mensaje = 'Error al actualizar la tarea.';
                    $tipo_mensaje = 'danger';
                }
            }
        } else {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'warning', 'message' => 'Faltan datos obligatorios.']);
                exit;
            }
            $mensaje = 'Faltan datos obligatorios para actualizar la tarea.';
            $tipo_mensaje = 'warning';
        }
    } else {
        // LÓGICA EXISTENTE DE INSERCIÓN (CONSERVADA)
        $idProfAsignado = (int)($_POST['id_profesor'] ?? 0);
        $idGrupoAsignado = (int)($_POST['id_grupo'] ?? 0);
        $titulo_tarea = trim($_POST['titulo_tarea'] ?? '');
        $desc_tarea   = trim($_POST['descripcion_tarea'] ?? '');
        $num_entregas = max(1, intval($_POST['num_entregas'] ?? 1));
        $fechas_arr   = isset($_POST['fecha_entrega']) && is_array($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : [];
        
        $fechas_arr = array_values(array_filter($fechas_arr, function($f) { return !empty(trim($f)); }));
        
        if ($idProfAsignado > 0 && !empty($titulo_tarea) && count($fechas_arr) > 0) {
            // Validar orden cronológico de las entregas
            $fechasValidas = true;
            for ($i = 1; $i < count($fechas_arr); $i++) {
                if (strtotime($fechas_arr[$i]) < strtotime($fechas_arr[$i - 1])) {
                    $fechasValidas = false;
                    break;
                }
            }
            if (!$fechasValidas) {
                header("Location: panel_tutor.php?msg=task_warning");
                exit();
            } else {
                $titulo_esc = mysqli_real_escape_string($conn, $titulo_tarea);
                $desc_esc   = mysqli_real_escape_string($conn, $desc_tarea);
                $fechas_json = mysqli_real_escape_string($conn, json_encode($fechas_arr, JSON_UNESCAPED_UNICODE));
                
                try {
                    mysqli_query($conn,
                        "INSERT INTO tbl_tarea_entrega (id_grupo, id_profesor, id_tutor, titulo_tarea, descripcion_tarea, num_entregas, fechas_entregas)
                         VALUES ($idGrupoAsignado, $idProfAsignado, $idTutor, '$titulo_esc', '$desc_esc', $num_entregas, '$fechas_json')"
                    );
                    header("Location: panel_tutor.php?msg=task_created");
                    exit();
                } catch (Exception $e) {
                    header("Location: panel_tutor.php?msg=task_error");
                    exit();
                }
            }
        } else {
            header("Location: panel_tutor.php?msg=task_missing");
            exit();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'borrar_tarea') {
    $id_tarea = (int)($_POST['id_tarea'] ?? 0);
    if ($id_tarea > 0) {
        try {
            mysqli_query($conn, "DELETE FROM tbl_tarea_entrega WHERE id = $id_tarea AND id_tutor = $idTutor");
            header("Location: panel_tutor.php?msg=deleted");
            exit();
        } catch (Exception $e) {
            header("Location: panel_tutor.php?msg=error_delete");
            exit();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'editar_tarea') {
    // Se mantiene esta funcionalidad por contrato de conservación, aunque se prioriza 'actualizar_tarea' vía AJAX.
    $id_tarea = (int)($_POST['id_tarea'] ?? 0);
    $titulo_tarea = trim($_POST['titulo_tarea'] ?? '');
    $desc_tarea   = trim($_POST['descripcion_tarea'] ?? '');
    $num_entregas = max(1, intval($_POST['num_entregas'] ?? 1));
    $fechas_arr   = isset($_POST['fecha_entrega']) && is_array($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : [];
    
    // Filtrar fechas vacías
    $fechas_arr = array_values(array_filter($fechas_arr, function($f) { return !empty(trim($f)); }));
    
    if ($id_tarea > 0 && !empty($titulo_tarea) && count($fechas_arr) > 0) {
        // Validar orden cronológico de las entregas
        $fechasValidas = true;
        for ($i = 1; $i < count($fechas_arr); $i++) {
            if (strtotime($fechas_arr[$i]) < strtotime($fechas_arr[$i - 1])) {
                $fechasValidas = false;
                break;
            }
        }
        if (!$fechasValidas) {
            header("Location: panel_tutor.php?msg=task_warning");
            exit();
        } else {
            $titulo_esc = mysqli_real_escape_string($conn, $titulo_tarea);
            $desc_esc   = mysqli_real_escape_string($conn, $desc_tarea);
            $fechas_json = mysqli_real_escape_string($conn, json_encode($fechas_arr, JSON_UNESCAPED_UNICODE));
            
            try {
                mysqli_query($conn,
                    "UPDATE tbl_tarea_entrega 
                     SET titulo_tarea = '$titulo_esc', 
                         descripcion_tarea = '$desc_esc', 
                         num_entregas = $num_entregas, 
                         fechas_entregas = '$fechas_json'
                     WHERE id = $id_tarea AND id_tutor = $idTutor"
                );
                header("Location: panel_tutor.php?msg=task_updated");
                exit();
            } catch (Exception $e) {
                header("Location: panel_tutor.php?msg=task_update_error");
                exit();
            }
        }
    } else {
        header("Location: panel_tutor.php?msg=task_missing");
        exit();
    }
}

// Grupos del tutor
$resGrupos = mysqli_query($conn, "SELECT id_grupo, nombre FROM tbl_grupo WHERE id_tutor = $idTutor ORDER BY nombre");
$grupos = [];
if ($resGrupos) { while ($g = mysqli_fetch_assoc($resGrupos)) $grupos[] = $g; }
$totalGrupos = count($grupos);

// Profesores asignados al tutor a través de sus grupos (Flexibilizado)
$resProfesores = mysqli_query($conn,
  "SELECT DISTINCT p.id_profesor, p.nombre, p.apellidos, p.DNI AS dni, p.ORCID AS orcid, p.correo, p.departamento, p.facultad, p.rama, g.nombre AS grupo
   FROM tbl_grupo g
   INNER JOIN tbl_grupo_profesor gp ON gp.id_grupo = g.id_grupo
   INNER JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
   WHERE g.id_tutor = $idTutor
   ORDER BY p.apellidos, p.nombre"
);
$profesores = [];
if ($resProfesores) { while ($p = mysqli_fetch_assoc($resProfesores)) $profesores[] = $p; }
$totalProfesores = count($profesores);

// Datos por profesor: tareas, publicaciones y EVALUACIONES (Restaurado)
$profTareas  = [];  // id_profesor => [tareas]
$profPubs    = [];  // id_profesor => count publicaciones
$profEvals   = [];  // id_profesor => [evaluaciones]

foreach ($profesores as $prof) {
    $pid = (int)$prof['id_profesor'];
    $profEvals[$pid] = [];

    // -- Tareas de entrega --
    $profTareas[$pid] = [];
    try {
        $resTareas = mysqli_query($conn,
            "SELECT * FROM tbl_tarea_entrega WHERE id_profesor = $pid AND id_tutor = $idTutor ORDER BY fecha_creacion DESC"
        );
        if ($resTareas) { while ($t = mysqli_fetch_assoc($resTareas)) $profTareas[$pid][] = $t; }
    } catch (Exception $e) { }

    // -- Publicaciones --
    $profPubs[$pid] = 0;
    $resPub = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_publicacion WHERE ORCID_autor = '" . mysqli_real_escape_string($conn, $prof['correo']) . "'");
    if (!$resPub) {
        $resPub2 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_publicacion_profesor WHERE orcid_profesor = '" . mysqli_real_escape_string($conn, $prof['orcid']) . "'");
        if ($resPub2) $profPubs[$pid] = (int)mysqli_fetch_assoc($resPub2)['total'];
    } else {
        $profPubs[$pid] = (int)mysqli_fetch_assoc($resPub)['total'];
    }

    // -- Evaluaciones ANECA (BLOQUE RESTAURADO) --
    $r_norm = strtoupper(trim($prof['rama'] ?? ''));
    $db_eval = $mapaDB[$r_norm] ?? null;
    if ($db_eval) {
        try {
            $pdo_loop = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$db_eval};charset=utf8mb4", $dbUser, $dbPass);
            $orcid_p = $prof['orcid'];
            $orcid_l = str_replace('-', '', $orcid_p);
            $dni_p   = $prof['dni'] ?? ($prof['DNI'] ?? '');
            $st_loop = $pdo_loop->prepare("SELECT * FROM evaluaciones WHERE json_entrada LIKE ? OR json_entrada LIKE ? OR json_entrada LIKE ? ORDER BY fecha_creacion DESC");
            $st_loop->execute(['%'.$orcid_p.'%', '%'.$orcid_l.'%', '%'.$dni_p.'%']);
            $profEvals[$pid] = $st_loop->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { }
    }
}

// --- SISTEMA DE RESUMEN DE GRUPOS Y RETRASOS (ADITIVO) ---
$grupoStats = [];
foreach ($grupos as $g) {
    $gid = (int)$g['id_grupo'];
    $grupoStats[$gid] = [
        'id_grupo' => $gid,
        'nombre' => $g['nombre'],
        'total_profesores' => 0,
        'total_tareas' => 0,
        'tareas_completadas' => 0,
        'hitos_retrasados' => 0,
        'dias_retraso_max' => 0,
        'dias_retraso_total' => 0
    ];
}

// Mapa rápido del nombre del grupo a su ID
$groupNameToId = [];
foreach ($grupos as $g) {
    $groupNameToId[$g['nombre']] = (int)$g['id_grupo'];
}

$hoyTimestamp = time();

foreach ($profesores as $prof) {
    $gName = $prof['grupo'];
    if (!isset($groupNameToId[$gName])) continue;
    $gid = $groupNameToId[$gName];
    
    $grupoStats[$gid]['total_profesores']++;
    
    $pid = (int)$prof['id_profesor'];
    $tareas = $profTareas[$pid] ?? [];
    
    foreach ($tareas as $t) {
        $numEntregas = (int)$t['num_entregas'];
        $fechasLimite = json_decode($t['fechas_entregas'] ?? '[]', true) ?: [];
        $fechasReales = json_decode($t['fechas_reales_entregas'] ?? '[]', true) ?: [];
        
        $todasHechas = true;
        for ($i = 0; $i < $numEntregas; $i++) {
            if (empty($fechasReales[$i])) {
                $todasHechas = false;
                break;
            }
        }
        if ($todasHechas) {
            continue; // Si la tarea está completamente completada, se archiva y ya no cuenta en el Semáforo de Retraso Activo
        }
        
        $grupoStats[$gid]['total_tareas']++;
        
        $numEntregas = (int)$t['num_entregas'];
        
        for ($i = 0; $i < $numEntregas; $i++) {
            if (!isset($fechasLimite[$i])) continue;
            
            $limiteStr = $fechasLimite[$i];
            $limiteTime = strtotime($limiteStr);
            if (!$limiteTime) continue;
            
            // Si la fecha límite no incluye hora (ej: YYYY-MM-DD), se extiende hasta las 23:59:59 de ese día
            if (strlen(trim($limiteStr)) <= 10) {
                $limiteTime = strtotime(date('Y-m-d', $limiteTime) . ' 23:59:59');
            }
            
            $realTime = isset($fechasReales[$i]) ? strtotime($fechasReales[$i]) : null;
            
            if ($realTime !== null) {
                $grupoStats[$gid]['tareas_completadas']++;
                if ($realTime > $limiteTime) {
                    $grupoStats[$gid]['hitos_retrasados']++;
                    $diffDias = ($realTime - $limiteTime) / 86400;
                    if ($diffDias > 0) {
                        $grupoStats[$gid]['dias_retraso_total'] += $diffDias;
                        if ($diffDias > $grupoStats[$gid]['dias_retraso_max']) {
                            $grupoStats[$gid]['dias_retraso_max'] = $diffDias;
                        }
                    }
                }
            } else {
                // Hito pendiente. Si hoy ya pasó el límite, cuenta como retraso activo
                if ($hoyTimestamp > $limiteTime) {
                    $grupoStats[$gid]['hitos_retrasados']++;
                    $diffDias = ($hoyTimestamp - $limiteTime) / 86400;
                    if ($diffDias > 0) {
                        $grupoStats[$gid]['dias_retraso_total'] += $diffDias;
                        if ($diffDias > $grupoStats[$gid]['dias_retraso_max']) {
                            $grupoStats[$gid]['dias_retraso_max'] = $diffDias;
                        }
                    }
                }
            }
        }
    }
}

// Ordenar los grupos para mostrar primero los más retrasados
uasort($grupoStats, function($a, $b) {
    if ($a['dias_retraso_total'] != $b['dias_retraso_total']) {
        return $b['dias_retraso_total'] <=> $a['dias_retraso_total'];
    }
    return $b['hitos_retrasados'] <=> $a['hitos_retrasados'];
});

// ------------------------------------------------------------------------- AJAX: Obtener Histórico ANECA ---------------------------------------------------------
if (isset($_GET['accion']) && $_GET['accion'] === 'get_historico_aneca' && isset($_GET['orcid'])) {
    $orcid_req = mysqli_real_escape_string($conn, $_GET['orcid']);
    
    // Obtenemos los datos completos del profesor para criterios de búsqueda robustos
    $res_p = mysqli_query($conn, "SELECT * FROM `tbl_profesor` WHERE `ORCID` = '$orcid_req' LIMIT 1");
    $p_info = mysqli_fetch_assoc($res_p);
    
    if ($p_info) {
        $r_norm = strtoupper(trim($p_info['rama'] ?? ''));
        $db_n = $mapaDB[$r_norm] ?? null;
        if ($db_n) {
            try {
                $pdo_h = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$db_n};charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);

                $orcid_original = $p_info['ORCID'] ?? '';
                $orcid_limpio = str_replace('-', '', $orcid_original);
                $dni_prof = $p_info['DNI'] ?? '';
                $nombre_prof = trim($p_info['nombre'] ?? '');
                $nombre_completo = trim(($p_info['nombre'] ?? '') . ' ' . ($p_info['apellidos'] ?? ''));

                // Consulta blindada por identificador único (DNI/ORCID) o Nombre
                $sql = "SELECT * FROM `evaluaciones` 
                        WHERE (`json_entrada` LIKE :orcid_original 
                           OR `json_entrada` LIKE :orcid_limpio
                           OR `json_entrada` LIKE :dni_prof)
                        ORDER BY `fecha_creacion` DESC";
                
                $st_h = $pdo_h->prepare($sql);
                $st_h->execute([
                    ':orcid_original' => '%' . $orcid_original . '%',
                    ':orcid_limpio'   => '%' . $orcid_limpio . '%',
                    ':dni_prof'       => '%' . $dni_prof . '%'
                ]);
                $evals_h = $st_h->fetchAll();
                
                if (empty($evals_h)) {
                    echo '<div class="text-center text-white-50 py-4"><i class="bi bi-journal-x" style="font-size:2.5rem;"></i><p class="mt-2">No hay evaluaciones registradas para el ORCID: '.htmlspecialchars($orcid_req).'.</p></div>';
                } else {
                    foreach ($evals_h as $ei => $ev) {
                        $evTotal = (float)($ev['total_final'] ?? 0);
                        $evColor = $evTotal >= 70 ? '#4ade80' : ($evTotal >= 50 ? '#fbbf24' : '#f87171');
                        $resText = htmlspecialchars($ev['resultado'] ?? '-');
                        $resBadge = strtoupper($resText) === 'POSITIVA' ? 'rgba(74,222,128,0.2)' : 'rgba(248,113,113,0.2)';
                        $resColor = strtoupper($resText) === 'POSITIVA' ? '#4ade80' : '#f87171';
                        
                        $collapseId = 'detalleEvals_' . ($ev['id'] ?? $ei);
                        $jsonId = 'jsonRaw_' . ($ev['id'] ?? $ei);
                        $jsonDecoded = json_decode($ev['json_entrada'] ?? '{}', true);

                        echo '<div class="mb-2">';
                        // Tarjeta Principal (Clickable)
                        echo '<div class="p-3 rounded-3" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10); cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#'.$collapseId.'" aria-expanded="false" aria-controls="'.$collapseId.'">';
                        echo '<div class="d-flex justify-content-between align-items-center"><div><span class="text-white fw-bold">#'.($ei+1).'</span><span class="text-white-50 small ms-2">'.htmlspecialchars($ev['fecha_creacion'] ?? '-').'</span></div>';
                        echo '<div class="d-flex align-items-center gap-2"><span class="fw-bold" style="color:'.$evColor.';">'.number_format($evTotal, 2).'%</span>';
                        echo '<span class="badge rounded-pill" style="background:'.$resBadge.'; color:'.$resColor.'; font-size:.7rem;">'.$resText.'</span><i class="bi bi-chevron-down text-white-50 ms-1"></i></div></div>';
                        echo '<div class="progress rounded-pill mt-2" style="height:6px; background:rgba(255,255,255,0.1);"><div class="progress-bar" style="width:'.min(100, $evTotal).'%; background:'.$evColor.';"></div></div>';
                        echo '<div class="d-flex gap-3 mt-2 text-white-50 small"><span>B1: '.number_format((float)($ev['bloque_1'] ?? 0), 2).'</span><span>B2: '.number_format((float)($ev['bloque_2'] ?? 0), 2).'</span><span>B3: '.number_format((float)($ev['bloque_3'] ?? 0), 2).'</span><span>B4: '.number_format((float)($ev['bloque_4'] ?? 0), 2).'</span></div>';
                        echo '</div>'; // Fin Tarjeta Principal
                        
                        // Contenido Colapsable
                        echo '<div class="collapse mt-2" id="'.$collapseId.'">';
                        echo '<div class="p-3 rounded-3" style="background:rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.05);">';
                        
                        // Metadatos
                        echo '<h6 class="text-white border-bottom border-light border-opacity-10 pb-2 mb-3"><i class="bi bi-info-circle me-2"></i>Metadatos de la Evaluación</h6>';
                        echo '<div class="row g-2 mb-4">';
                        echo '<div class="col-sm-6"><div class="text-white-50 small">Área</div><div class="text-white">'.htmlspecialchars($ev['area'] ?? '-').'</div></div>';
                        echo '<div class="col-sm-6"><div class="text-white-50 small">Categoría</div><div class="text-white">'.htmlspecialchars($ev['categoria'] ?? '-').'</div></div>';
                        echo '<div class="col-sm-6"><div class="text-white-50 small">Fecha Creación</div><div class="text-white">'.htmlspecialchars($ev['fecha_creacion'] ?? '-').'</div></div>';
                        echo '<div class="col-sm-6"><div class="text-white-50 small">Resultado</div><div class="text-white fw-bold" style="color:'.$resColor.';">'.htmlspecialchars($ev['resultado'] ?? '-').'</div></div>';
                        echo '</div>';
                        
                        // Desglose Puntuaciones
                        echo '<h6 class="text-white border-bottom border-light border-opacity-10 pb-2 mb-3"><i class="bi bi-bar-chart-steps me-2"></i>Desglose de Puntuaciones</h6>';
                        echo '<div class="table-responsive"><table class="table table-sm table-dark table-borderless text-white-50" style="background:transparent;">';
                        echo '<thead><tr style="border-bottom:1px solid rgba(255,255,255,0.1);"><th class="text-white">Bloque 1</th><th class="text-white">Bloque 2</th><th class="text-white">Bloque 3</th><th class="text-white">Bloque 4</th></tr></thead>';
                        echo '<tbody><tr>';
                        
                        echo '<td><ul class="list-unstyled mb-0 small">';
                        echo '<li>1a: '.number_format((float)($ev['puntuacion_1a'] ?? 0), 2).'</li>';
                        echo '<li>1b: '.number_format((float)($ev['puntuacion_1b'] ?? 0), 2).'</li>';
                        echo '<li>1c: '.number_format((float)($ev['puntuacion_1c'] ?? 0), 2).'</li>';
                        echo '<li>1d: '.number_format((float)($ev['puntuacion_1d'] ?? 0), 2).'</li>';
                        echo '<li>1e: '.number_format((float)($ev['puntuacion_1e'] ?? 0), 2).'</li>';
                        echo '<li>1f: '.number_format((float)($ev['puntuacion_1f'] ?? 0), 2).'</li>';
                        echo '<li>1g: '.number_format((float)($ev['puntuacion_1g'] ?? 0), 2).'</li>';
                        echo '</ul></td>';
                        
                        echo '<td><ul class="list-unstyled mb-0 small">';
                        echo '<li>2a: '.number_format((float)($ev['puntuacion_2a'] ?? 0), 2).'</li>';
                        echo '<li>2b: '.number_format((float)($ev['puntuacion_2b'] ?? 0), 2).'</li>';
                        echo '<li>2c: '.number_format((float)($ev['puntuacion_2c'] ?? 0), 2).'</li>';
                        echo '<li>2d: '.number_format((float)($ev['puntuacion_2d'] ?? 0), 2).'</li>';
                        echo '</ul></td>';
                        
                        echo '<td><ul class="list-unstyled mb-0 small">';
                        echo '<li>3a: '.number_format((float)($ev['puntuacion_3a'] ?? 0), 2).'</li>';
                        echo '<li>3b: '.number_format((float)($ev['puntuacion_3b'] ?? 0), 2).'</li>';
                        echo '</ul></td>';
                        
                        echo '<td><ul class="list-unstyled mb-0 small">';
                        echo '<li>4: '.number_format((float)($ev['puntuacion_4'] ?? 0), 2).'</li>';
                        echo '</ul></td>';
                        
                        echo '</tr></tbody></table></div>';
                        
                        // JSON Original Colapsable
                        echo '<div class="mt-3">';
                        echo '<button class="btn btn-sm btn-outline-secondary w-100 mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#'.$jsonId.'" aria-expanded="false" aria-controls="'.$jsonId.'"><i class="bi bi-code-square me-2"></i>Ver JSON Original de Extracción IA</button>';
                        echo '<div class="collapse" id="'.$jsonId.'">';
                        echo '<div class="card card-body text-bg-dark border-secondary p-2" style="background:#1a1d20 !important; font-size:0.75rem;">';
                        echo '<pre class="mb-0 text-success" style="max-height:250px; overflow-y:auto; scrollbar-width:thin;"><code>';
                        if ($jsonDecoded) {
                            echo htmlspecialchars(json_encode($jsonDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        } else {
                            echo htmlspecialchars($ev['json_entrada'] ?? 'JSON no disponible o inválido.');
                        }
                        echo '</code></pre></div></div></div>'; // Fin JSON Original
                        
                        echo '</div></div></div>'; // Fin Body y Contenedor Principal
                    }
                }
            } catch (Exception $e) { 
                echo '<div class="alert alert-danger">Error de SQL: ' . htmlspecialchars($e->getMessage()) . '</div>'; 
            }
        } else { echo '<div class="alert alert-warning">Rama no reconocida.</div>'; }
    } else { echo '<div class="alert alert-danger">Profesor no encontrado.</div>'; }
    exit;
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
    
    /* FIX RESPONSIVE: Evitar scroll horizontal y optimizar cards */
    @media (max-width: 768px) {
      .panel-wrapper {
        padding: 10px !important;
        width: 100% !important;
        margin: 0 !important;
      }
      .dashboard {
        padding: 0 !important;
        overflow-x: hidden;
      }
      .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
      }
      .col-12, .col-md-4 {
        padding-left: 5px !important;
        padding-right: 5px !important;
      }
      .prof-panel-card {
        padding: 15px !important;
      }
      .prof-panel-card .d-flex {
        align-items: center !important;
        text-align: center;
      }
      .prof-panel-card .justify-content-end {
        justify-content: center !important;
        width: 100%;
        max-width: 1440px;
        gap: 10px !important;
      }
      .prof-panel-card .btn-sm {
        flex: 1 1 auto;
        min-width: 65px !important;
        height: 52px !important;
        padding: 5px !important;
        justify-content: center !important;
      }
      .prof-panel-card .btn-sm i {
        font-size: 0.95rem !important;
      }
      .prof-panel-card .btn-sm span {
        font-size: 0.55rem !important;
      }
      .formulario {
        width: clamp(280px, 30%, 460px);
        max-width: 90vw;
      }
      /* Header en una sola línea con hamburguesa a la izquierda */
      .contenedorimg {
        flex-direction: row !important;
        justify-content: flex-start !important;
        align-items: center !important;
        gap: 10px !important;
        padding: 10px 15px !important;
        flex-wrap: nowrap !important;
      }
      .hamburger-btn {
        margin-right: 5px !important;
        order: -1; /* Asegura que esté a la izquierda */
      }
      .imagen img {
        max-height: 35px !important; /* Ajuste para que quepan en una línea */
      }
      #academy {
        max-height: 45px !important;
        margin-left: auto; /* Empuja el segundo logo a la derecha si hay espacio */
      }
    }
  </style>
</head>

<body>
  <header>
    <div class="contenedorimg">
      <!-- Menú Hamburguesa en la misma línea que los logos -->
      <button class="hamburger-btn" id="hamburgerBtn" aria-label="Mostrar menú">
        <i class="bi bi-list"></i>
      </button>

      <div class="imagen">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          id="#acele" />
      </div>
      <div class="imagen">
        <img src="../../acelerador_login/fronten/img/AcademyAccelerator_def.png" id="academy" alt="academy" />
      </div>
    </div>
  <!-- Overlay para el menú lateral -->
  <div class="overlay-menu" id="overlayMenu"></div>
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

            <!-- âœ… Datos visibles siempre -->
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

            <!-- âœ… Datos ocultos por defecto â†’ se mostrarán al pulsar el botón -->
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

          <button type="button" class="btn btn-danger px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2" onclick="customConfirm('¿Estás totalmente seguro de que deseas eliminar tu cuenta permanentemente? Toda tu información y grupos creados serán archivados y perderás el acceso.', () => document.getElementById('formEliminarCuenta').submit());">
              <i class="bi bi-person-x-fill"></i> Eliminar cuenta
          </button>

          <form id="formEliminarCuenta" method="POST" class="d-none">
              <?php echo acelerador_csrf_field(); ?>
              <input type="hidden" name="accion" value="eliminar_cuenta_propia">
          </form>

        </div>

      </div>

      <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
           DASHBOARD TUTOR - Grupos y profesores asignados
      â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
      <div class="dashboard">

        <?php if (!empty($mensaje)): ?>
          <script>
            document.addEventListener('DOMContentLoaded', () => {
              showNotification(<?= json_encode($mensaje) ?>, <?= json_encode($tipo_mensaje) ?>);
            });
          </script>
        <?php endif; ?>

        <!-- Notificaciones de Decisiones Pendientes (Readmisiones) -->
        <?php if (!empty($notifsTutor)): ?>
        <div class="row g-3 mb-4">
          <div class="col-12">
            <div class="dashboard-info-card border-warning border-opacity-50">
              <h5 class="text-warning mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i>Decisiones Pendientes</h5>
              <div class="d-flex flex-column gap-3">
                <?php foreach ($notifsTutor as $nt): ?>
                  <div class="p-3 rounded-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);">
                    <div class="text-white">
                        <i class="bi bi-person-badge me-2 text-info"></i> <?= htmlspecialchars($nt['mensaje']) ?>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" class="m-0 d-flex gap-3">
                            <?php echo acelerador_csrf_field(); ?>
                            <input type="hidden" name="accion" value="decision_readmision">
                            <input type="hidden" name="id_solicitud" value="<?= $nt['id_solicitud'] ?>">
                            <button type="submit" name="decision" value="ACEPTAR" class="btn btn-sm btn-success rounded-pill px-4 fw-bold">ACEPTAR</button>
                            <button type="submit" name="decision" value="RECHAZAR" class="btn btn-sm btn-outline-danger rounded-pill px-4 fw-bold">RECHAZAR</button>
                        </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Tarjetas de estadísticas -->
        <div class="row g-3 mb-4 w-100">
          <div class="col-6 col-md-4">
            <div class="dashboard-stat-card h-100">
              <div class="stat-label"><i class="bi bi-collection me-1"></i> Grupos asignados</div>
              <div class="stat-value"><?= $totalGrupos ?></div>
            </div>
          </div>
          <div class="col-6 col-md-4">
            <div class="dashboard-stat-card h-100">
              <div class="stat-label"><i class="bi bi-person-workspace me-1"></i> Profesores tutorizados</div>
              <div class="stat-value"><?= $totalProfesores ?></div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="dashboard-stat-card h-100">
              <div class="stat-label"><i class="bi bi-envelope me-1"></i> Correo del tutor</div>
              <div class="stat-value stat-email"><?= htmlspecialchars($correo) ?></div>
            </div>
          </div>
        </div>

        <!-- Resumen de Grupos (Semáforo de retrasos) (ADITIVO) -->
        <?php if ($totalGrupos > 0): ?>
        <h2 class="dashboard-section-title mb-3"><i class="bi bi-diagram-3-fill me-2"></i>Semáforo de Retraso de los Grupos</h2>
        <div class="row g-3 mb-5 w-100">
          <?php 
          $esPrimerGrupo = true;
          foreach ($grupoStats as $gs):
            $diasRetraso = round($gs['dias_retraso_total']);
            $hitosRetrasados = $gs['hitos_retrasados'];
            
            // Determinar color de semáforo y estado
            if ($diasRetraso > 10) {
                $statusColor = '#f87171'; // Rojo
                $statusBg = 'rgba(248, 113, 113, 0.1)';
                $statusBorder = 'rgba(248, 113, 113, 0.4)';
                $statusText = 'RETRASO CRÍTICO';
                $statusIcon = 'bi-exclamation-triangle-fill';
            } elseif ($diasRetraso > 0) {
                $statusColor = '#fbbf24'; // Amarillo
                $statusBg = 'rgba(251, 191, 36, 0.1)';
                $statusBorder = 'rgba(251, 191, 36, 0.4)';
                $statusText = 'RETRASO MODERADO';
                $statusIcon = 'bi-exclamation-circle-fill';
            } else {
                $statusColor = '#4ade80'; // Verde
                $statusBg = 'rgba(74, 222, 128, 0.1)';
                $statusBorder = 'rgba(74, 222, 128, 0.4)';
                $statusText = 'AL DÍA';
                $statusIcon = 'bi-check-circle-fill';
            }
            
            // Si es el primer grupo de la lista ordenada y tiene retraso, es el más retrasado de todos
            $esMasRetrasado = $esPrimerGrupo && $diasRetraso > 0;
            $esPrimerGrupo = false;
          ?>
            <div class="col-12 col-md-6 col-lg-4">
              <div class="dashboard-stat-card h-100 position-relative p-4 rounded-4 shadow-lg transition-all" 
                   style="background: rgba(20, 88, 204, 0.4) !important; border: 1px solid <?= $statusBorder ?> !important; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
                
                <?php if ($esMasRetrasado): ?>
                  <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-danger px-3 py-2 fw-bold text-uppercase shadow-sm animate-pulse" style="font-size: 0.65rem; border: 1px solid rgba(255,255,255,0.2); z-index: 10;">
                    <i class="bi bi-fire me-1"></i> El Más Retrasado
                  </span>
                <?php endif; ?>
                
                <div class="d-flex align-items-center justify-content-between mb-3">
                  <h4 class="text-white fw-bold mb-0" style="font-size: 1.1rem; max-width: 70%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <i class="bi bi-collection me-2 text-info"></i><?= htmlspecialchars($gs['nombre']) ?>
                  </h4>
                  <span class="badge rounded-pill fw-bold text-uppercase" 
                        style="background: <?= $statusBg ?>; color: <?= $statusColor ?>; border: 1px solid <?= $statusColor ?>; font-size: 0.65rem; padding: 6px 10px;">
                    <i class="bi <?= $statusIcon ?> me-1"></i><?= $statusText ?>
                  </span>
                </div>
                
                <div class="d-flex flex-column gap-2 text-white-50 small mb-2">
                  <div class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-people me-2"></i>Profesores en grupo:</span>
                    <span class="text-white fw-semibold"><?= $gs['total_profesores'] ?></span>
                  </div>
                  <div class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-journal-check me-2"></i>Tareas asignadas:</span>
                    <span class="text-white fw-semibold"><?= $gs['total_tareas'] ?></span>
                  </div>
                  <div class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-calendar-x me-2 text-danger"></i>Hitos con retraso:</span>
                    <span class="text-danger fw-bold"><?= $hitosRetrasados ?></span>
                  </div>
                  <div class="d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-clock-history me-2 text-warning"></i>Retraso acumulado:</span>
                    <span class="fw-bold" style="color: <?= $diasRetraso > 0 ? '#fbbf24' : '#4ade80' ?>;"><?= $diasRetraso ?> días</span>
                  </div>
                </div>
                
                <!-- Barra de progreso visual de hitos -->
                <?php 
                  $pctHitosOk = $gs['total_tareas'] > 0 ? round((($gs['total_tareas'] - $hitosRetrasados) / $gs['total_tareas']) * 100) : 100;
                  if ($pctHitosOk < 0) $pctHitosOk = 0;
                ?>
                <div class="mt-3">
                  <div class="d-flex justify-content-between mb-1 small text-white-50">
                    <span>Ritmo de entregas</span>
                    <span class="fw-bold text-white"><?= $pctHitosOk ?>% al día</span>
                  </div>
                  <div class="progress rounded-pill" style="height: 6px; background: rgba(255, 255, 255, 0.05); overflow: hidden;">
                    <div class="progress-bar" role="progressbar" 
                         style="width: <?= $pctHitosOk ?>%; background-color: <?= $statusColor ?>; border-radius: 10px; transition: width 0.6s ease;" 
                         aria-valuenow="<?= $pctHitosOk ?>" aria-valuemin="0" aria-valuemax="100"></div>
                  </div>
                </div>

                <!-- Botón de Desglose (ADITIVO) -->
                <button type="button" class="btn btn-sm w-100 mt-3 rounded-pill fw-semibold transition-all" 
                        style="border: 1px solid rgba(255,255,255,0.15) !important; color: #fff !important; background: rgba(20, 88, 204, 0.2) !important; padding: 8px 16px;"
                        data-bs-toggle="modal" data-bs-target="#modalDesglose<?= $gs['id_grupo'] ?>"
                        onmouseover="this.style.background='rgba(20, 88, 204, 0.6)'; this.style.borderColor='rgba(255,255,255,0.3)';"
                        onmouseout="this.style.background='rgba(20, 88, 204, 0.2)'; this.style.borderColor='rgba(255,255,255,0.15)';">
                  <i class="bi bi-clock-history me-1"></i> Desglose
                </button>
                
              </div>
            </div>

            <!-- Modal de Desglose para el Grupo (ADITIVO) -->
            <div class="modal fade" id="modalDesglose<?= $gs['id_grupo'] ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content text-white rounded-4 border border-light border-opacity-10" style="background: rgba(15, 23, 42, 0.95) !important; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); box-shadow: 0 20px 50px rgba(0,0,0,0.6);">
                  
                  <div class="modal-header border-bottom border-light border-opacity-10 p-3">
                    <h5 class="modal-title fw-bold text-info"><i class="bi bi-diagram-3-fill me-2"></i>Desglose de Temporalidad: <?= htmlspecialchars($gs['nombre']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                  </div>
                  
                  <div class="modal-body p-4 custom-scrollbar" style="max-height: 70vh; overflow-y: auto;">
                    
                    <?php
                    // Obtener profesores del grupo actual
                    $profsGrupo = [];
                    foreach ($profesores as $prof) {
                        if ($prof['grupo'] === $gs['nombre']) {
                            $profsGrupo[] = $prof;
                        }
                    }
                    
                    if (empty($profsGrupo)):
                    ?>
                      <p class="text-white-50 text-center py-4">No hay profesores asignados en este grupo.</p>
                    <?php else: ?>
                      <div class="d-flex flex-column gap-4">
                        <?php foreach ($profsGrupo as $pGrupo): 
                          $pId = (int)$pGrupo['id_profesor'];
                          $tList = $profTareas[$pId] ?? [];
                        ?>
                          <div class="p-3 rounded-4" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08);">
                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
                              <h6 class="text-white fw-bold mb-0">
                                <i class="bi bi-person-circle text-info me-2"></i><?= htmlspecialchars($pGrupo['nombre'] . ' ' . $pGrupo['apellidos']) ?>
                              </h6>
                              <span class="text-white-50 small"><i class="bi bi-envelope"></i> <?= htmlspecialchars($pGrupo['correo']) ?></span>
                            </div>
                            
                            <?php
                              $tareasActivasDesglose = [];
                              foreach ($tList as $tl) {
                                  $fReal = json_decode($tl['fechas_reales_entregas'] ?? '[]', true) ?: [];
                                  $nEntr = (int)$tl['num_entregas'];
                                  $todasHechas = true;
                                  for ($idx = 0; $idx < $nEntr; $idx++) {
                                      if (empty($fReal[$idx])) {
                                          $todasHechas = false;
                                          break;
                                      }
                                  }
                                  if (!$todasHechas) {
                                      $tareasActivasDesglose[] = $tl;
                                  }
                              }
                            ?>
                            <?php if (empty($tareasActivasDesglose)): ?>
                              <div class="text-white-50 small p-3 text-center" style="background: rgba(0,0,0,0.15); border-radius: 8px;">
                                <i class="bi bi-info-circle me-1 text-info"></i> El profesor no tiene tareas o hitos activos actualmente.
                              </div>
                            <?php else: ?>
                              <div class="d-flex flex-column gap-3">
                                <?php foreach ($tareasActivasDesglose as $tl): 
                                  $fLimit = json_decode($tl['fechas_entregas'] ?? '[]', true) ?: [];
                                  $fReal = json_decode($tl['fechas_reales_entregas'] ?? '[]', true) ?: [];
                                  $nEntr = (int)$tl['num_entregas'];
                                ?>
                                  <div class="p-3 rounded-3" style="background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(255, 255, 255, 0.04);">
                                    <div class="fw-semibold text-white small mb-2"><i class="bi bi-clipboard-check text-info me-1"></i> Tarea: <?= htmlspecialchars($tl['titulo_tarea']) ?></div>
                                    
                                    <div class="row g-2">
                                      <?php for ($idx = 0; $idx < $nEntr; $idx++): 
                                        if (!isset($fLimit[$idx])) continue;
                                        $limiteStr = $fLimit[$idx];
                                        $limiteT = strtotime($limiteStr);
                                        if ($limiteT) {
                                            // Si la fecha límite no incluye hora (ej: YYYY-MM-DD), se extiende hasta las 23:59:59 de ese día
                                            if (strlen(trim($limiteStr)) <= 10) {
                                                $limiteT = strtotime(date('Y-m-d', $limiteT) . ' 23:59:59');
                                            }
                                        }
                                        $realStr = $fReal[$idx] ?? null;
                                        $realT = $realStr ? strtotime($realStr) : null;
                                        
                                        $formattedLimit = date('d/m/Y H:i', $limiteT);
                                        
                                        if ($realT !== null) {
                                            $formattedReal = date('d/m/Y H:i', $realT);
                                            if ($realT > $limiteT) {
                                                $diff = round(($realT - $limiteT) / 86400);
                                                if ($diff == 0) $diff = 1;
                                                $badgeBg = 'rgba(248, 113, 113, 0.15)';
                                                $badgeColor = '#f87171';
                                                $badgeText = "⚠️ Entregado con retraso ({$diff}d) el {$formattedReal}";
                                            } else {
                                                $badgeBg = 'rgba(74, 222, 128, 0.15)';
                                                $badgeColor = '#4ade80';
                                                $badgeText = "✅ Entregado a tiempo el {$formattedReal}";
                                            }
                                        } else {
                                            if (time() > $limiteT) {
                                                $diff = round((time() - $limiteT) / 86400);
                                                if ($diff == 0) $diff = 1;
                                                $badgeBg = 'rgba(248, 113, 113, 0.15)';
                                                $badgeColor = '#f87171';
                                                $badgeText = "❌ RETRASADO (Hace {$diff}d)";
                                            } else {
                                                $diff = round(($limiteT - time()) / 86400);
                                                if ($diff == 0) $diff = 1;
                                                $badgeBg = 'rgba(59, 130, 246, 0.15)';
                                                $badgeColor = '#60a5fa';
                                                $badgeText = "⏳ Pendiente (Faltan {$diff}d)";
                                            }
                                        }
                                      ?>
                                        <div class="col-12 col-md-6">
                                          <div class="p-2 rounded-3 d-flex flex-column gap-1" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); font-size: 0.75rem;">
                                            <div class="d-flex justify-content-between">
                                              <span class="text-white-50">Plazo <?= $idx + 1 ?>:</span>
                                              <span class="text-white fw-bold"><?= $formattedLimit ?></span>
                                            </div>
                                            <span class="badge w-100 py-2 text-start text-wrap mt-1" style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; border: 1px solid <?= $badgeColor ?>; font-size: 0.7rem; font-weight: 600;">
                                              <?= $badgeText ?>
                                            </span>
                                          </div>
                                        </div>
                                      <?php endfor; ?>
                                    </div>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    
                  </div>
                  
                  <div class="modal-footer border-top border-light border-opacity-10 p-3">
                    <button type="button" class="btn btn-outline-light rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                  </div>
                  
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Tarjetas de profesores -->
        <?php if ($totalProfesores > 0): ?>
        <h2 class="dashboard-section-title mb-3"><i class="bi bi-people-fill me-2"></i>Mis profesores</h2>
        <div class="row g-3">
          <?php foreach ($profesores as $prof):
            $pid = (int)$prof['id_profesor'];
            $evalsList = $profEvals[$pid] ?? [];
            $tareasList = $profTareas[$pid] ?? [];
            $lastEval = !empty($evalsList) ? $evalsList[0] : null;
            $numEvals = count($evalsList);
            $numPubs = $profPubs[$pid] ?? 0;
            $totalFinal = $lastEval ? (float)$lastEval['total_final'] : 0;
          ?>
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
                  
                  <div class="d-flex flex-wrap gap-2 ms-lg-3 justify-content-end align-items-start">
                    <a href="../../dashboard_profesor.php?orcid=<?= urlencode($prof['orcid']) ?>&dni=<?= urlencode($prof['dni']) ?>&rama=<?= urlencode($prof['rama']) ?>&nombre=<?= urlencode($prof['nombre']) ?>" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center" style="min-width:65px; padding:6px 4px; gap:2px;">
                      <i class="bi bi-file-earmark-bar-graph" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem; line-height:1;">Expediente</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center" style="min-width:60px; padding:6px 4px; gap:2px;" data-bs-toggle="modal" data-bs-target="#modalTarea<?= $pid ?>">
                      <i class="bi bi-clipboard-check" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem; line-height:1;">Tareas</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center" style="min-width:60px; padding:6px 4px; gap:2px;" data-bs-toggle="modal" data-bs-target="#modalHistoricoTareas<?= $pid ?>">
                      <i class="bi bi-clock-history" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem; line-height:1;">Histórico</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center btn-estado-prof" style="min-width:60px; padding:6px 4px; gap:2px;" data-bs-toggle="modal" data-bs-target="#modalEstado<?= $pid ?>" data-orcid="<?= htmlspecialchars($prof['orcid']) ?>">
                      <i class="bi bi-speedometer2" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem; line-height:1;">Estado</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center btn-gantt-prof" style="min-width:60px; padding:6px 4px; gap:2px;" data-bs-toggle="modal" data-bs-target="#modalGrafico<?= $pid ?>" data-id="<?= $pid ?>">
                      <i class="bi bi-bar-chart-line" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem; line-height:1;">Gráfico</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center" style="min-width:60px; padding:6px 4px; gap:2px;" data-bs-toggle="modal" data-bs-target="#modalOpciones<?= $pid ?>">
                      <i class="bi bi-three-dots" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem; line-height:1;">Opciones</span>
                    </button>
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

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         MODALES POR PROFESOR (fuera de panel-wrapper, dentro de main)
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
     <?php foreach ($profesores as $prof):
      $pid = (int)$prof['id_profesor'];
      $evalsList = $profEvals[$pid] ?? [];
      $tareasList = $profTareas[$pid] ?? [];
      $lastEval = !empty($evalsList) ? $evalsList[0] : null;
      $numEvals = count($evalsList);
      $numPubs = $profPubs[$pid] ?? 0;
      $totalFinal = $lastEval ? (float)$lastEval['total_final'] : 0;
      $profNombreCompleto = htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']);
      
      // Separar tareas activas e históricas (completamente realizadas)
      $tareasActivas = [];
      $tareasHistoricas = [];
      foreach ($tareasList as $tareaActual) {
          $fechasT = json_decode($tareaActual['fechas_entregas'] ?? '[]', true) ?: [];
          $hechas = json_decode($tareaActual['fechas_reales_entregas'] ?? '[]', true) ?: [];
          $numEntregas = (int)$tareaActual['num_entregas'];
          
          $todasHechas = true;
          for ($idx = 0; $idx < $numEntregas; $idx++) {
              if (empty($hechas[$idx])) {
                  $todasHechas = false;
                  break;
              }
          }
          if ($todasHechas) {
              $tareasHistoricas[] = $tareaActual;
          } else {
              $tareasActivas[] = $tareaActual;
          }
      }
    ?>

    <!-- MODAL 1: Ver tarea -->
    <div class="modal fade" id="modalTarea<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Tareas - <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body custom-scrollbar">
            
            <!-- Mostrar todas las tareas activas (múltiples simultáneas) -->
            <?php if (!empty($tareasActivas)): ?>
              <h6 class="text-white fw-bold mb-3 text-center w-100"><i class="bi bi-list-task me-2"></i>Tareas Activas</h6>
              <div class="d-flex flex-column gap-3 mb-4">
              <?php foreach ($tareasActivas as $tareaActual):
                $fechasT = json_decode($tareaActual['fechas_entregas'] ?? '[]', true) ?: [];
                $hechas = json_decode($tareaActual['fechas_reales_entregas'] ?? '[]', true) ?: [];
                $tiempoPrincipal = !empty($fechasT) ? $fechasT[0] : '';
              ?>                <div class="p-3 rounded-3 w-100" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);">
                  <!-- Cabecera centrada: título + acciones -->
                  <div class="text-center mb-2">
                    <h6 class="text-white fw-bold mb-1"><?= htmlspecialchars($tareaActual['titulo_tarea']) ?></h6>
                    <span class="badge rounded-pill" style="background:rgba(74,222,128,0.2); color:#4ade80; font-size:.7rem;"><?= $tareaActual['num_entregas'] ?> entregas</span>
                  </div>
                  <!-- Botones editar/borrar centrados -->
                  <form method="POST" class="m-0 d-flex justify-content-center gap-3 mb-2" onsubmit="event.preventDefault(); customConfirm('¿Seguro que deseas eliminar esta tarea?', () => this.submit());">
                    <?php echo acelerador_csrf_field(); ?>
                    <input type="hidden" name="accion" value="borrar_tarea">
                    <input type="hidden" name="id_tarea" value="<?= $tareaActual['id'] ?>">
                    <button type="button" class="btn btn-sm btn-outline-info rounded-3 d-flex flex-column align-items-center btn-editar-tarea" style="min-width:56px; padding:6px 10px; gap:3px;" title="Editar tarea"
                      data-id="<?= $tareaActual['id'] ?>"
                      data-titulo="<?= htmlspecialchars($tareaActual['titulo_tarea'], ENT_QUOTES) ?>"
                      data-desc="<?= htmlspecialchars($tareaActual['descripcion_tarea'], ENT_QUOTES) ?>"
                      data-entregas="<?= $tareaActual['num_entregas'] ?>"
                      data-fechas="<?= htmlspecialchars($tareaActual['fechas_entregas'] ?? '[]', ENT_QUOTES) ?>"
                      data-tiempo="<?= $tiempoPrincipal ?>"
                      data-bs-dismiss="modal">
                      <i class="bi bi-pencil-square" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem;">Editar</span>
                    </button>
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-3 d-flex flex-column align-items-center" style="min-width:56px; padding:6px 10px; gap:3px; background:none;" title="Borrar tarea">
                      <i class="bi bi-trash3-fill" style="font-size:1.1rem;"></i>
                      <span style="font-size:.6rem;">Borrar</span>
                    </button>
                  </form>
                  <?php if (!empty($tareaActual['descripcion_tarea'])): ?>
                    <p class="text-white-50 small mb-2 text-center"><?= htmlspecialchars($tareaActual['descripcion_tarea']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($fechasT)): ?>
                    <div class="text-white-50 small fw-bold text-uppercase mb-1 text-center" style="font-size:.7rem;">Entregas programadas:</div>
                    <?php foreach ($fechasT as $fi => $fe): 
                      $estaHecha = !empty($hechas[$fi]);
                      $esPasada  = (strtotime($fe) < time());
                    ?>
                      <div class="d-flex flex-column mb-2 p-2 rounded-3" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06);">
                        <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
                          <div class="d-flex align-items-center gap-1">
                            <i class="bi bi-clock me-1" style="color:rgba(255,255,255,0.4); font-size:.8rem;"></i>
                            <span class="text-white small">Entrega <?= $fi+1 ?>: <?= date('d/m/Y H:i', strtotime($fe)) ?></span>
                            <?php if ($estaHecha): ?>
                              <span class="badge bg-success bg-opacity-10 text-success ms-1" style="font-size: .6rem;">HECHA</span>
                            <?php endif; ?>
                          </div>
                          <?php if (!$estaHecha): ?>
                            <button class="btn btn-xs btn-success py-0 px-2 rounded-pill btn-marcar-hecha" style="font-size: .6rem;"
                                    data-id-tarea="<?= $tareaActual['id'] ?>" data-indice="<?= $fi ?>">Marcar como hecha</button>
                          <?php endif; ?>
                        </div>
                        
                        <?php if (!$estaHecha && !$esPasada): ?>
                          <div class="countdown-item mt-1 text-info small fw-bold" style="font-size: .7rem;" data-deadline="<?= htmlspecialchars($fe) ?>">
                            Restan: <span class="cdDias">--</span>d <span class="cdHoras">--</span>h <span class="cdMin">--</span>m <span class="cdSeg">--</span>s
                          </div>
                        <?php elseif (!$estaHecha && $esPasada): ?>
                          <div class="small text-danger fw-bold" style="font-size: .7rem;">VENCIDA</div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-center text-white-50 mb-4 w-100">
                <i class="bi bi-inbox" style="font-size:2.5rem;"></i>
                <p class="mt-2 mb-0">No hay tareas asignadas a este profesor.</p>
              </div>
            <?php endif; ?>

            <hr style="border-color:rgba(255,255,255,0.15); margin: 10px 0 20px 0; width:100%;">
            
            <h6 class="text-white fw-bold mb-3 text-center w-100"><i class="bi bi-plus-circle me-2"></i>Añadir nueva tarea</h6>
            
            <!-- Formulario para UPDATE/INSERT -->
            <form method="POST" class="d-flex flex-column gap-3 w-100">
              <?php echo acelerador_csrf_field(); ?>
              <input type="hidden" name="accion" value="actualizar_tarea">
              <input type="hidden" name="id_profesor" value="<?= $pid ?>">
              <!-- Obtener un grupo del profesor (el primero) -->
              <?php
                $resProfGrp = mysqli_query($conn, "SELECT id_grupo FROM tbl_grupo_profesor WHERE id_profesor = $pid LIMIT 1");
                $gId = ($resProfGrp && $rG = mysqli_fetch_assoc($resProfGrp)) ? $rG['id_grupo'] : 0;
              ?>
              <input type="hidden" name="id_grupo" value="<?= $gId ?>">
              
              <div>
                <label class="form-label text-white-50 small mb-1">Título de la tarea *</label>
                <input type="text" name="titulo_tarea" class="form-control form-control-sm text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" required placeholder="Ej: Preparación expediente">
              </div>
              
              <div>
                <label class="form-label text-white-50 small mb-1">Descripción</label>
                <textarea name="descripcion_tarea" class="form-control form-control-sm text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" rows="2" placeholder="Opcional..."></textarea>
              </div>

              <div>
                <label class="form-label text-white-50 small mb-1">Cantidad de entregas *</label>
                <input type="number" name="num_entregas" id="inputNumEntregas<?= $pid ?>" class="form-control form-control-sm text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" required min="1" max="10" value="1" oninput="generarFechasModal(<?= $pid ?>)">
              </div>
              
              <div id="fechasContainer<?= $pid ?>" class="d-flex flex-column gap-2">
                <!-- Se inyectan con JS -->
              </div>
              
              <button type="submit" class="btn btn-primary btn-sm rounded-pill mt-2 w-100"><i class="bi bi-save me-1"></i> Guardar tarea</button>
            </form>

          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 2: Estado -->
    <div class="modal fade" id="modalEstado<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:rgba(15,23,42,0.98); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>Estado Ejecutivo - <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body custom-scrollbar">
            
            <!-- Contenedor dinámico para tarjetas de bloques -->
            <div id="rawMeritContainer<?= $pid ?>">
              <div class="text-center py-5 text-white-50">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <span class="small">Compilando inventario curricular ejecutivo...</span>
              </div>
            </div>

            <?php if ($lastEval): ?>
            <hr class="my-4" style="border-color:rgba(255,255,255,0.15);">
            <div class="px-2">
                <div class="text-white-50 small fw-bold text-uppercase mb-3" style="font-size:.7rem;">Última evaluación registrada</div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="text-white small">Puntuación total</span>
                  <strong class="text-white"><?= number_format($totalFinal, 2) ?>%</strong>
                </div>
                <div class="progress rounded-pill" style="height:10px; background:rgba(255,255,255,0.1);">
                  <div class="progress-bar" style="width:<?= min(100, $totalFinal) ?>%; background:<?= $totalFinal >= 70 ? '#4ade80' : ($totalFinal >= 50 ? '#fbbf24' : '#f87171') ?>;"></div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                  <span class="text-white-50 small">Resultado: <strong style="color:<?= strtoupper($lastEval['resultado'] ?? '') === 'POSITIVA' ? '#4ade80' : '#f87171' ?>"><?= htmlspecialchars($lastEval['resultado'] ?? '-') ?></strong></span>
                  <span class="text-white-50 small"><?= htmlspecialchars($lastEval['fecha_creacion'] ?? '') ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center text-white-50 py-3 mt-3 no-eval-msg">
              <i class="bi bi-exclamation-circle" style="font-size:1.5rem;"></i>
              <p class="small mt-1 mb-0">Sin evaluaciones registradas.</p>
            </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 3: Gráfico (Gantt Secuencial & Semáforo) -->
    <div class="modal fade" id="modalGrafico<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title"><i class="bi bi-bar-chart-line me-2"></i>Logística de Entregas - <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body custom-scrollbar" style="max-height: 70vh; overflow-y: auto;">
            <div id="ganttContainer<?= $pid ?>">
              <div class="text-center py-5 text-white-50">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                <span class="small">Analizando líneas de tiempo y plazos...</span>
              </div>
            </div>
          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 4: Más opciones -->
    <div class="modal fade" id="modalOpciones<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title"><i class="bi bi-three-dots me-2"></i>Opciones - <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="d-grid gap-3">
              <!-- Enviar correo -->
              <a href="mailto:<?= htmlspecialchars($prof['correo']) ?>" class="btn btn-outline-light rounded-pill py-3 d-flex align-items-center justify-content-center gap-2" style="font-size:1rem;">
                <i class="bi bi-envelope-fill" style="font-size:1.2rem;"></i> Enviar correo a <?= htmlspecialchars($prof['nombre']) ?>
              </a>
              <!-- Histórico ANECA -->
              <button type="button" class="btn btn-outline-light rounded-pill py-3 d-flex align-items-center justify-content-center gap-2 btn-historico-aneca" style="font-size:1rem;"
                      data-bs-toggle="modal" data-bs-target="#modalHistorico<?= $pid ?>" data-bs-dismiss="modal" data-orcid="<?= htmlspecialchars($prof['orcid']) ?>">
                <i class="bi bi-clock-history" style="font-size:1.2rem;"></i> Histórico ANECA
              </button>
            </div>
          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 4b: Histórico ANECA -->
    <div class="modal fade" id="modalHistorico<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Histórico ANECA - <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php if (empty($evalsList)): ?>
              <div class="text-center text-white-50 py-4">
                <i class="bi bi-journal-x" style="font-size:2.5rem;"></i>
                <p class="mt-2">No hay evaluaciones ANECA registradas.</p>
              </div>
            <?php else: ?>
              <?php foreach ($evalsList as $ei => $ev):
                $evTotal = (float)($ev['total_final'] ?? 0);
                $evColor = $evTotal >= 70 ? '#4ade80' : ($evTotal >= 50 ? '#fbbf24' : '#f87171');
              ?>
              <div class="p-3 rounded-3 mb-2" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);">
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <span class="text-white fw-bold">#<?= $ei + 1 ?></span>
                    <span class="text-white-50 small ms-2"><?= htmlspecialchars($ev['fecha_creacion'] ?? '-') ?></span>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold" style="color:<?= $evColor ?>;"><?= number_format($evTotal, 2) ?>%</span>
                    <span class="badge rounded-pill" style="background:<?= strtoupper($ev['resultado'] ?? '') === 'POSITIVA' ? 'rgba(74,222,128,0.2)' : 'rgba(248,113,113,0.2)' ?>; color:<?= strtoupper($ev['resultado'] ?? '') === 'POSITIVA' ? '#4ade80' : '#f87171' ?>; font-size:.7rem;">
                      <?= htmlspecialchars($ev['resultado'] ?? '-') ?>
                    </span>
                  </div>
                </div>
                <div class="progress rounded-pill mt-2" style="height:6px; background:rgba(255,255,255,0.1);">
                  <div class="progress-bar" style="width:<?= min(100, $evTotal) ?>%; background:<?= $evColor ?>;"></div>
                </div>
                <div class="d-flex gap-3 mt-2 text-white-50 small">
                  <span>B1: <?= number_format((float)($ev['bloque_1'] ?? 0), 2) ?></span>
                  <span>B2: <?= number_format((float)($ev['bloque_2'] ?? 0), 2) ?></span>
                  <span>B3: <?= number_format((float)($ev['bloque_3'] ?? 0), 2) ?></span>
                  <span>B4: <?= number_format((float)($ev['bloque_4'] ?? 0), 2) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-outline-light rounded-pill" data-bs-toggle="modal" data-bs-target="#modalOpciones<?= $pid ?>" data-bs-dismiss="modal"><i class="bi bi-arrow-left me-1"></i> Volver</button>
            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL HISTÓRICO DE TAREAS -->
    <div class="modal fade" id="modalHistoricoTareas<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title text-success fw-bold"><i class="bi bi-clock-history me-2"></i>Histórico de Tareas - <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body custom-scrollbar" style="max-height: 70vh; overflow-y: auto;">
            <?php if (!empty($tareasHistoricas)): ?>
              <div class="d-flex flex-column gap-3">
                <?php foreach ($tareasHistoricas as $tareaActual):
                  $fechasT = json_decode($tareaActual['fechas_entregas'] ?? '[]', true) ?: [];
                  $hechas = json_decode($tareaActual['fechas_reales_entregas'] ?? '[]', true) ?: [];
                ?>
                  <div class="p-3 rounded-4 w-100" style="background:rgba(74,222,128,0.03); border:1px solid rgba(74,222,128,0.15);">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2 mb-3">
                      <h6 class="text-success fw-bold mb-0">
                        <i class="bi bi-check2-all text-success me-2"></i><?= htmlspecialchars($tareaActual['titulo_tarea']) ?>
                      </h6>
                      <span class="badge rounded-pill bg-success bg-opacity-10 text-success" style="border: 1px solid rgba(74,222,128,0.25); font-size: 0.7rem;">
                        <?= $tareaActual['num_entregas'] ?> entregas completadas
                      </span>
                    </div>
                    <?php if (!empty($tareaActual['descripcion_tarea'])): ?>
                      <p class="text-white-50 small mb-3"><?= htmlspecialchars($tareaActual['descripcion_tarea']) ?></p>
                    <?php endif; ?>
                    
                    <div class="row g-2">
                      <?php foreach ($fechasT as $fi => $fe): 
                        $fechaReal = $hechas[$fi] ?? '';
                      ?>
                        <div class="col-12 col-md-6">
                          <div class="p-2 rounded-3" style="background:rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.04); font-size:0.75rem;">
                            <div class="d-flex justify-content-between mb-1">
                              <span class="text-white-50">Plazo <?= $fi+1 ?>:</span>
                              <span class="text-white fw-semibold"><?= date('d/m/Y H:i', strtotime($fe)) ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                              <span class="text-success">Entregado el:</span>
                              <span class="text-success fw-bold"><?= $fechaReal ? date('d/m/Y H:i', strtotime($fechaReal)) : '-' ?></span>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>

                    <!-- Gráfico Gantt Histórico -->
                    <?php
                    $inicioTarea = strtotime($tareaActual['fecha_creacion']) * 1000;
                    $fechasLimMilli = array_map(function($f) { return strtotime($f) * 1000; }, $fechasT);
                    $fechasRealMilli = array_map(function($f) { return strtotime($f) * 1000; }, array_filter($hechas));
                    
                    if (!empty($fechasLimMilli)) {
                        $maxReal = !empty($fechasRealMilli) ? max($fechasRealMilli) : 0;
                        $maxPlazo = max($fechasLimMilli);
                        $finGrafico = max($maxReal, $maxPlazo);
                        $totalDuracion = $finGrafico - $inicioTarea;
                        if ($totalDuracion <= 0) $totalDuracion = 1;
                        
                        ?>
                        <div class="mt-4 p-3 rounded-3" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.08);">
                          <div class="text-white-50 small fw-bold text-uppercase mb-3" style="font-size:0.7rem;"><i class="bi bi-bar-chart-steps me-1 text-success"></i>Línea de Tiempo del Histórico</div>
                          <div class="d-flex flex-column gap-3">
                            <?php
                            foreach ($fechasT as $idx => $fe) {
                                $limite = $fechasLimMilli[$idx];
                                $real = $fechasRealMilli[$idx] ?? null;
                                
                                $inicioSegmento = ($idx === 0) ? $inicioTarea : 
                                                 (!empty($fechasRealMilli[$idx-1]) ? $fechasRealMilli[$idx-1] : $fechasLimMilli[$idx-1]);
                                
                                $finSegmento = $real ? $real : $limite;
                                if ($finSegmento < $inicioSegmento) $finSegmento = $inicioSegmento;
                                
                                $esRetraso = $finSegmento > $limite;
                                $widthRetraso = 0;
                                $widthNormal = 0;
                                $infoRetrasoTxt = '';
                                
                                if ($esRetraso) {
                                    $diff = $finSegmento - $limite;
                                    $dias = floor($diff / (1000 * 60 * 60 * 24));
                                    $horas = floor(($diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                    $infoRetrasoTxt = " (Retraso: {$dias}d {$horas}h)";
                                    
                                    $widthNormal = ((max($inicioSegmento, min($finSegmento, $limite)) - $inicioSegmento) / $totalDuracion) * 100;
                                    $widthRetraso = (($finSegmento - max($inicioSegmento, $limite)) / $totalDuracion) * 100;
                                } else {
                                    $widthNormal = (($finSegmento - $inicioSegmento) / $totalDuracion) * 100;
                                }
                                
                                $marginLeft = (($inicioSegmento - $inicioTarea) / $totalDuracion) * 100;
                                $colorBarra = '#198754'; // Verde para completado
                                $fechaLimiteLegible = date('d/m/Y H:i', strtotime($fe));
                                
                                ?>
                                <div class="gantt-row">
                                    <div class="d-flex justify-content-between mb-1" style="font-size:0.75rem;">
                                        <span class="text-white-50">Entrega <?= $idx + 1 ?> (Hito Completado)</span>
                                        <?php if ($esRetraso): ?>
                                            <span class="text-danger fw-bold" style="font-size:0.7rem;">ENTREGA TARDÍA<?= $infoRetrasoTxt ?></span>
                                        <?php else: ?>
                                            <span class="text-success fw-bold" style="font-size:0.7rem;"><i class="bi bi-check-circle-fill me-1"></i>A TIEMPO</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress position-relative" style="height:10px; background:rgba(255,255,255,0.05); overflow:visible; border-radius:10px;">
                                        <!-- Rombo de Plazo Teórico -->
                                        <div class="position-absolute" 
                                             style="left:<?= (($limite - $inicioTarea) / $totalDuracion) * 100 ?>%; top:50%; width:14px; height:14px; border:2px solid <?= $esRetraso ? '#f87171' : '#fff' ?>; background:#0f172a; transform: translate(-50%, -50%) rotate(45deg); z-index:10; box-shadow: <?= $esRetraso ? '0 0 8px #f87171' : 'none' ?>;" 
                                             title="Plazo Teórico: <?= $fechaLimiteLegible ?>"></div>
                                        
                                        <!-- Barra de Progreso Normal -->
                                        <div class="progress-bar" 
                                             style="width:<?= $widthNormal ?>%; margin-left:<?= $marginLeft ?>%; background-color:<?= $colorBarra ?>; border-radius:10px 0 0 10px;"></div>
                                        
                                        <!-- Barra de Retraso si existió -->
                                        <?php if ($widthRetraso > 0): ?>
                                        <div class="progress-bar progress-bar-striped" 
                                             style="width:<?= $widthRetraso ?>%; background-color:#f87171; border-radius:0 10px 10px 0;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                          </div>
                        </div>
                        <?php
                    }
                    ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-center text-white-50 py-5 w-100">
                <i class="bi bi-clock-history text-white-50 mb-3" style="font-size:3rem; opacity: 0.5;"></i>
                <p class="mt-2 mb-1 fw-bold text-white">No hay tareas en el histórico</p>
                <p class="small text-white-50 px-4">Las tareas se añadirán aquí automáticamente una vez que el profesor haya completado todos los hitos y entregas programadas.</p>
              </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <?php endforeach; ?>

  </main>

  <?php include('chatbot.php'); ?>

<footer>
    <div class="mipie" id="mipie">
      <div class="direccion">
        <img src="https://uf3ceu.es/wp-content/uploads/logo-uf3-2k25.svg" alt="CEU Universidad Fernando III"
          id="#acele" />
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

  <!-- MODAL GLOBAL: Editar Tarea -->
  <div class="modal fade" id="modalEditarTarea" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
        <div class="modal-header border-bottom border-light border-opacity-25">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Tarea</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="formEditarTarea">
          <?php echo acelerador_csrf_field(); ?>
          <div class="modal-body">
            <input type="hidden" name="accion" value="actualizar_tarea">
            <input type="hidden" name="id_tarea" id="editTareaId" value="">
            
            <div class="mb-3">
              <label class="form-label text-white-50 small">Título de la tarea</label>
              <input type="text" name="titulo_tarea" id="editTareaTitulo" class="form-control text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" required>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 small">Descripción (opcional)</label>
              <textarea name="descripcion_tarea" id="editTareaDesc" class="form-control text-white" rows="2" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label text-white-50 small">Número de entregas</label>
              <input type="number" name="num_entregas" id="editTareaEntregas" class="form-control text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" min="1" max="10" required>
            </div>
            
            <div id="editFechasContainer" class="d-flex flex-column gap-2">
              <!-- Los inputs de fecha se generarán dinámicamente aquí -->
            </div>
          </div>
          <div class="modal-footer border-top border-light border-opacity-25">
            <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-sm btn-info rounded-pill text-dark fw-bold px-4">Guardar Cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <script>
    // Inyección de CSRF robusta para AJAX (Merge de datos)
    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        if (options.type.toUpperCase() === 'POST') {
            const token = '<?php echo acelerador_get_csrf_token(); ?>';
            if (typeof options.data === 'string') {
                options.data += (options.data.length > 0 ? '&' : '') + 'csrf_token=' + token;
            } else if (typeof options.data === 'object' && !(options.data instanceof FormData)) {
                options.data = $.extend(options.data, { csrf_token: token });
            } else if (options.data instanceof FormData) {
                options.data.append('csrf_token', token);
            }
        }
    });

    document.addEventListener("DOMContentLoaded", () => {
      // Validación cronológica global de entregas al enviar formularios
      document.addEventListener("submit", function(e) {
        const form = e.target;
        const inputsFechas = form.querySelectorAll('input[name="fecha_entrega[]"]');
        if (inputsFechas.length > 1) {
          let lastTime = 0;
          for (let i = 0; i < inputsFechas.length; i++) {
            const val = inputsFechas[i].value;
            if (val) {
              const time = new Date(val).getTime();
              if (lastTime > 0 && time < lastTime) {
                e.preventDefault();
                e.stopPropagation();
                showNotification(`Error: La fecha de la entrega ${i + 1} no puede ser anterior a la entrega ${i}.`, 'danger');
                return false;
              }
              lastTime = time;
            }
          }
        }
      });
    });

    document.addEventListener("DOMContentLoaded", () => {
      const boton = document.getElementById("btnMostrarTodo");
      const extraDatos = document.querySelectorAll(".extraDato");

      boton.addEventListener("click", () => {

        // âœ… Si los datos extra están ocultos â†’ mostrarlos
        if (extraDatos[0].classList.contains("d-none")) {

          extraDatos.forEach(el => el.classList.remove("d-none"));

          boton.innerHTML = '<i class="bi bi-eye-slash-fill"></i> Mostrar resumen datos';

        }
        // âœ… Si están visibles â†’ ocultarlos
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
          showNotification("Perfil/Rama no reconocida: " + perfilRaw, 'warning');
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

    // Función auxiliar para dar formato compatible con input type="datetime-local" (YYYY-MM-DDTHH:MM)
    function formatForDatetimeLocal(dateStr) {
      if (!dateStr) return '';
      let formatted = dateStr.trim().replace(' ', 'T');
      if (formatted.length <= 10) {
        formatted += 'T23:59';
      } else if (formatted.length > 16) {
        formatted = formatted.substring(0, 16);
      }
      return formatted;
    }

    // Función para generar fechas dinámicas en el modal "Ver tarea" (Añadir tarea)
    function generarFechasModal(pid) {
      const inputVal = parseInt(document.getElementById('inputNumEntregas' + pid).value, 10);
      const numFechas = isNaN(inputVal) || inputVal < 1 ? 1 : inputVal;
      const container = document.getElementById('fechasContainer' + pid);
      
      // Conservar las fechas ya escritas para que no se borren mientras se añade una nueva entrega
      const inputsFechas = container.querySelectorAll('input[type="datetime-local"]');
      let valoresActuales = [];
      inputsFechas.forEach(inp => valoresActuales.push(inp.value));
      
      let html = '';
      for (let i = 1; i <= numFechas; i++) {
        const rawValor = valoresActuales[i - 1] || '';
        const valor = formatForDatetimeLocal(rawValor);
        html += `
          <div>
            <label class="form-label text-white-50 small mb-1">Fecha y hora de entrega ${i}</label>
            <input type="datetime-local" name="fecha_entrega[]" class="form-control form-control-sm text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" value="${valor}" required>
          </div>
        `;
      }
      container.innerHTML = html;
    }

    // Inicializar los contenedores de fechas al abrir los modales por primera vez
    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll('[id^="modalTarea"]').forEach(modal => {
        modal.addEventListener('show.bs.modal', function() {
          const pid = this.id.replace('modalTarea', '');
          const inputNumEntregas = document.getElementById('inputNumEntregas' + pid);
          if (inputNumEntregas) {
            // Asegurar que el valor mínimo sea 1
            if (!inputNumEntregas.value || parseInt(inputNumEntregas.value, 10) < 1) {
              inputNumEntregas.value = '1';
            }
            // Generar siempre los campos de fecha (incluye el caso de entrega única)
            generarFechasModal(pid);
          }
        });
      });
    });

    // Histórico ANECA: Captura de clic y carga dinámica
    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll('.btn-historico-aneca').forEach(btn => {
        btn.addEventListener('click', function() {
          const orcid = this.getAttribute('data-orcid');
          const targetModalId = this.getAttribute('data-bs-target');
          const modalBody = document.querySelector(targetModalId + ' .modal-body');
          
          if (orcid && modalBody) {
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-light" role="status"></div><p class="mt-2 text-white-50">Cargando historial para ORCID: ' + orcid + '...</p></div>';
            
            $.ajax({
              url: 'panel_tutor.php',
              type: 'GET',
              data: { accion: 'get_historico_aneca', orcid: orcid },
              success: function(response) {
                modalBody.innerHTML = response;
              },
              error: function() {
                modalBody.innerHTML = '<div class="alert alert-danger">Error al cargar el historial.</div>';
              }
            });
          }
        });
      });
    });

    // Función para regenerar los inputs de fechas en el modal de edición
    function generarFechasEditModal(numFechas, fechasValores = []) {
      const container = document.getElementById('editFechasContainer');
      let html = '';
      for (let i = 1; i <= numFechas; i++) {
        const rawValor = fechasValores[i - 1] || '';
        const valor = formatForDatetimeLocal(rawValor);
        html += `
          <div>
            <label class="form-label text-white-50 small mb-1">Fecha y hora de entrega ${i}</label>
            <input type="datetime-local" name="fecha_entrega[]" class="form-control form-control-sm text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" value="${valor}" required>
          </div>
        `;
      }
      container.innerHTML = html;
    }

    // Edición de tareas: Captura de datos y apertura de modal
    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll('.btn-editar-tarea').forEach(btn => {
        btn.addEventListener('click', function() {
          const id = this.getAttribute('data-id');
          const titulo = this.getAttribute('data-titulo');
          const desc = this.getAttribute('data-desc');
          const entregas = parseInt(this.getAttribute('data-entregas'), 10) || 1;
          const fechasStr = this.getAttribute('data-fechas');
          
          let fechasArr = [];
          try {
            fechasArr = JSON.parse(fechasStr);
          } catch(e) {}

          // Poblar formulario
          document.getElementById('editTareaId').value = id;
          document.getElementById('editTareaTitulo').value = titulo;
          document.getElementById('editTareaDesc').value = desc;
          document.getElementById('editTareaEntregas').value = entregas;

          // Generar campos de fecha
          generarFechasEditModal(entregas, fechasArr);

          // Mostrar modal usando Bootstrap 5 JS API (con timeout corto para permitir el cierre del modal anterior)
          setTimeout(() => {
            const modalEditar = new bootstrap.Modal(document.getElementById('modalEditarTarea'));
            modalEditar.show();
          }, 300);
        });
      });

      // Escuchar cambios en el input de 'Número de entregas' del modal de edición para regenerar inputs
      const inputEditEntregas = document.getElementById('editTareaEntregas');
      if (inputEditEntregas) {
        inputEditEntregas.addEventListener('input', function() {
          const nuevasEntregas = parseInt(this.value, 10) || 1;
          // Guardar temporalmente los valores actuales
          const inputsFechas = document.querySelectorAll('#editFechasContainer input[type="datetime-local"]');
          let valoresActuales = [];
          inputsFechas.forEach(inp => valoresActuales.push(inp.value));
          
          generarFechasEditModal(nuevasEntregas, valoresActuales);
        });
      }

      // AJAX submit para el formulario de edición (ADITIVO)
      const formEditar = document.getElementById('formEditarTarea');
      if (formEditar) {
        formEditar.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          formData.append('ajax', '1');

          $.ajax({
            url: 'panel_tutor.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
              if (response.status === 'success') {
                showNotification(response.message, 'success');
                setTimeout(() => location.reload(), 1000);
              } else if (response.status === 'warning') {
                showNotification(response.message, 'warning');
              } else {
                showNotification(response.message || 'Error desconocido', 'danger');
              }
            },
            error: function() {
              showNotification('Error de comunicación al actualizar la tarea', 'danger');
            }
          });
        });
      }
    });

    // ------------------------------------------------------------------------- AJAX: ANALISTA DE DATOS (DISEÑO EJECUTIVO REESTRUCTURADO) -------------------------
    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll('.btn-estado-prof').forEach(btn => {
        btn.addEventListener('click', function() {
          const orcid = this.getAttribute('data-orcid');
          const targetId = this.getAttribute('data-bs-target').replace('#', '');
          const pid = targetId.replace('modalEstado', '');
          const container = document.getElementById('rawMeritContainer' + pid);
          const modalElem = document.getElementById('modalEstado' + pid);
          
          if (!orcid || !container) return;

          $.ajax({
            url: 'panel_tutor.php',
            type: 'POST',
            data: { accion: 'get_estado_candidato', orcid: orcid },
            success: function(response) {
              if (response.status === 'success') {
                const d = response.data;
                
                // 1. Manejo del mensaje de "No evaluaciones"
                if (modalElem) {
                    const noEvalMsg = modalElem.querySelector('.no-eval-msg');
                    if (d.meta.total_evals > 0 && noEvalMsg) noEvalMsg.classList.add('d-none');
                }

                // 2. Construcción de Layout de Tarjetas Compacto
                const renderRow = (label, value, icon, color = 'white') => `
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light border-opacity-10">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi ${icon} text-white-50" style="font-size: .9rem;"></i>
                            <span class="text-white-50 small">${label}</span>
                        </div>
                        <span class="badge rounded-pill bg-light bg-opacity-10 text-${color} fw-bold" style="min-width: 35px;">${value}</span>
                    </div>
                `;

                const layoutHtml = `
                    <div class="row g-3">
                        <!-- BLOQUE 1: INVESTIGACIÓN -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 rounded-4" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08) !important;">
                                <div class="card-header bg-transparent border-light border-opacity-10 py-3">
                                    <h6 class="mb-0 text-info fw-bold"><i class="bi bi-search me-2"></i>BLOQUE 1: INVESTIGACIÓN</h6>
                                </div>
                                <div class="card-body p-3">
                                    ${renderRow('Publicaciones (1.A)', d.bloque1.publicaciones, 'bi-journal-text', 'info')}
                                    ${renderRow('Libros y Capítulos (1.B)', d.bloque1.libros, 'bi-book')}
                                    ${renderRow('Proyectos Invest. (1.C)', d.bloque1.proyectos, 'bi-diagram-3')}
                                    ${renderRow('Transferencia Res. (1.D)', d.bloque1.transferencia, 'bi-arrow-left-right')}
                                    ${renderRow('Tesis Dirigidas (1.E)', d.bloque1.tesis, 'bi-mortarboard')}
                                    ${renderRow('Congresos (1.F)', d.bloque1.congresos, 'bi-megaphone')}
                                    ${renderRow('Otros méritos (1.G)', d.bloque1.otros, 'bi-plus-circle')}
                                </div>
                            </div>
                        </div>

                        <!-- BLOQUE 2: DOCENCIA -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 rounded-4" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08) !important;">
                                <div class="card-header bg-transparent border-light border-opacity-10 py-3">
                                    <h6 class="mb-0 text-success fw-bold"><i class="bi bi-person-workspace me-2"></i>BLOQUE 2: DOCENCIA</h6>
                                </div>
                                <div class="card-body p-3">
                                    ${renderRow('Docencia Univ. (2.A)', d.bloque2.docencia, 'bi-easel')}
                                    ${renderRow('Evaluación Docente (2.B)', d.bloque2.evaluacion, 'bi-check-circle')}
                                    ${renderRow('Formación Docente (2.C)', d.bloque2.formacion, 'bi-mortarboard-fill')}
                                    ${renderRow('Material Docente (2.D)', d.bloque2.material, 'bi-file-earmark-pdf')}
                                    <div class="mt-3 p-2 rounded-3 text-center" style="background:rgba(74,222,128,0.1);">
                                        <div class="text-success small fw-bold text-uppercase" style="font-size:.6rem;">Total Horas Acumuladas</div>
                                        <div class="h4 mb-0 text-success fw-bold">${d.bloque2.total_horas} h</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- BLOQUE 3: FORMACIÓN Y EXPERIENCIA -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 rounded-4" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08) !important;">
                                <div class="card-header bg-transparent border-light border-opacity-10 py-3">
                                    <h6 class="mb-0 text-warning fw-bold"><i class="bi bi-briefcase me-2"></i>BLOQUE 3: FORMACIÓN / EXP.</h6>
                                </div>
                                <div class="card-body p-3">
                                    ${renderRow('Formación Acad. (3.A)', d.bloque3.formacion, 'bi-bank')}
                                    ${renderRow('Años Exp. Prof. (3.B)', d.bloque3.anios_exp, 'bi-calendar-check', 'warning')}
                                </div>
                            </div>
                        </div>

                        <!-- HISTORIAL ANECA (Sustituye Bloque 4) -->
                        <div class="col-md-6">
                            <div class="card h-100 border-0 rounded-4" style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08) !important;">
                                <div class="card-header bg-transparent border-light border-opacity-10 py-3">
                                    <h6 class="mb-0 text-white-50 fw-bold"><i class="bi bi-clock-history me-2"></i>HISTORIAL ANECA</h6>
                                </div>
                                <div class="card-body p-3 d-flex flex-column justify-content-center align-items-center">
                                    <div class="text-white-50 small mb-2 text-center">Cantidad de evaluaciones registradas</div>
                                    <div class="display-4 fw-bold text-info">${d.meta.total_evals}</div>
                                    <div class="badge rounded-pill bg-info bg-opacity-10 text-info mt-2 px-3">Registros Totales</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML = layoutHtml;
              } else {
                container.innerHTML = '<div class="alert alert-danger small rounded-4 mt-3">Error al renderizar inventario ejecutivo.</div>';
              }
            },
            error: function() {
              container.innerHTML = '<div class="alert alert-danger small rounded-4 mt-3">Error crítico de comunicación con el analista de datos.</div>';
            }
          });
        });
      });
    });

    // --------------------€â”€ AJAX: LOGÍSTICA GANTT (get_datos_gantt & SEMÁFORO ALERTAS) â”€â”€â”€â”€â”€â”€â”€
    document.addEventListener("DOMContentLoaded", () => {
      // Función auxiliar para parseo de fechas robusto (Cross-browser)
      const parseSafeDate = (dateStr) => {
        if (!dateStr) return null;
        let d = dateStr.includes(' ') ? dateStr.replace(' ', 'T') : dateStr;
        if (d.length === 10) d += 'T23:59:59';
        const ts = new Date(d).getTime();
        return isNaN(ts) ? null : ts;
      };

      // Delegación de eventos para botones "Marcar como hecha"
      $(document).on('click', '.btn-marcar-hecha', function() {
        const idTarea = $(this).data('id-tarea');
        const indice = $(this).data('indice');
        const btn = $(this);
        
        customConfirm('¿Deseas marcar esta entrega como completada hoy?', () => {
          $.ajax({
            url: 'panel_tutor.php',
            type: 'POST',
            data: { accion: 'marcar_entrega_hecha', id_tarea: idTarea, indice: indice },
            success: function(response) {
              if (response.status === 'success') {
                showNotification('Entrega registrada con éxito', 'success');
                // Feedback visual inmediato en el modal de tareas
                btn.closest('.d-flex').find('.badge').remove(); 
                btn.after('<span class="badge bg-success bg-opacity-10 text-success ms-1" style="font-size: .6rem;">HECHA</span>');
                btn.remove();
                
                // REFRESCO INTELIGENTE: Si el modal de tareas está abierto, buscamos su PID para refrescar el Gantt oculto
                const activeModal = document.querySelector('.modal.show');
                if (activeModal) {
                    let pid = '';
                    if (activeModal.id.startsWith('modalTarea')) pid = activeModal.id.replace('modalTarea', '');
                    else if (activeModal.id.startsWith('modalGrafico')) pid = activeModal.id.replace('modalGrafico', '');
                    
                    if (pid) {
                        const gBtn = document.querySelector(`.btn-gantt-prof[data-id="${pid}"]`);
                        if (gBtn) {
                            // Si el modal de gráfico no está abierto, el click lo abriría, pero solo queremos refrescar los datos
                            // Así que forzamos la carga de datos sin disparar el toggle de bootstrap si ya estamos en otro modal
                            actualizarDatosGantt(pid);
                        }
                    }
                }
              } else {
                showNotification(response.message, 'danger');
              }
            },
            error: function() {
              showNotification('Error de red al marcar entrega', 'danger');
            }
          });
        });
      });

      // Función aislada para cargar datos del Gantt
      window.actualizarDatosGantt = function(pid) {
        const container = document.getElementById('ganttContainer' + pid);
        if (!pid || !container) return;

        $.ajax({
          url: 'panel_tutor.php',
          type: 'POST',
          data: { accion: 'get_datos_gantt', id_profesor: pid },
          success: function(response) {
            if (response.status === 'success' && response.data) {
                let html = '';
                const hoy = new Date().getTime();

                response.data.forEach(t => {
                    try {
                        const inicioTarea = parseSafeDate(t.creacion);
                        if (!inicioTarea) return;

                        const fechasLimite = (t.teoricas || []).map(f => parseSafeDate(f)).filter(f => f !== null);
                        const fechasReales = (t.reales || []).map(f => parseSafeDate(f));
                        
                        if (fechasLimite.length === 0) return;

                        // Definimos el fin del gráfico: el mayor entre el último plazo o hoy
                        const maxPlazo = Math.max(...fechasLimite);
                        const finGrafico = Math.max(maxPlazo, hoy);
                        const totalDuracion = finGrafico - inicioTarea;
                        if (totalDuracion <= 0) return;

                        html += `<div class="gantt-wrapper mb-5 p-3 rounded-4" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.08);">
                                    <h6 class="text-white fw-bold mb-4"><i class="bi bi-grid-3x2-gap me-2 text-info"></i>${t.titulo}</h6>
                                    <div class="gantt-body d-flex flex-column gap-3">`;

                        fechasLimite.forEach((limite, idx) => {
                            const real = fechasReales[idx] || null;
                            
                            // Lógica de Relevos Mejorada:
                            // Si no hay entrega anterior, usamos inicioTarea para la primera o la fecha límite anterior para las siguientes
                            const inicioSegmento = (idx === 0) ? inicioTarea : 
                                                 (fechasReales[idx-1] ? fechasReales[idx-1] : fechasLimite[idx-1]);
                            
                            let finSegmento = real ? real : hoy;
                            if (finSegmento < inicioSegmento) finSegmento = inicioSegmento;

                            const esRetraso = finSegmento > limite;
                            let widthRetraso = 0;
                            let widthNormal = 0;
                            let infoRetrasoTxt = '';

                            if (esRetraso) {
                                const diff = finSegmento - limite;
                                const dias = Math.floor(diff / (1000 * 60 * 60 * 24));
                                const horas = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                infoRetrasoTxt = ` (Retraso: ${dias}d ${horas}h)`;
                                
                                widthNormal = ((Math.max(inicioSegmento, Math.min(finSegmento, limite)) - inicioSegmento) / totalDuracion) * 100;
                                widthRetraso = ((finSegmento - Math.max(inicioSegmento, limite)) / totalDuracion) * 100;
                            } else {
                                widthNormal = ((finSegmento - inicioSegmento) / totalDuracion) * 100;
                            }

                            const marginLeft = ((inicioSegmento - inicioTarea) / totalDuracion) * 100;
                            const colorBarra = real ? '#198754' : (esRetraso ? '#dc3545' : '#3b82f6');
                            const animation = (!real) ? 'progress-bar-animated progress-bar-striped' : '';
                            const fechaLimiteLegible = new Date(limite).toLocaleString();

                            html += `
                                <div class="gantt-row">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-white-50" style="font-size:0.75rem;">Entrega ${idx + 1}</span>
                                        ${esRetraso ? `<span class="text-danger fw-bold" style="font-size:0.7rem;">${real ? 'ENTREGA TARDÍA' : 'FUERA DE PLAZO'}${infoRetrasoTxt}</span>` : ''}
                                    </div>
                                    <div class="progress position-relative" style="height:10px; background:rgba(255,255,255,0.05); overflow:visible; border-radius:10px;">
                                        <div class="position-absolute" 
                                             style="left:${((limite - inicioTarea) / totalDuracion) * 100}%; top:50%; width:14px; height:14px; border:2px solid ${esRetraso ? '#f87171' : '#fff'}; background:#0f172a; transform: translate(-50%, -50%) rotate(45deg); z-index:10; box-shadow: ${esRetraso ? '0 0 8px #f87171' : 'none'}; cursor: pointer;" 
                                             title="Plazo: ${fechaLimiteLegible}"
                                             onclick="showNotification('📅 Plazo de Entrega ${idx + 1}: ${fechaLimiteLegible}', 'info');"></div>
                                        <div class="progress-bar ${animation}" 
                                             style="width:${widthNormal}%; margin-left:${marginLeft}%; background-color:${colorBarra}; transition:width 0.4s ease; border-radius:10px 0 0 10px;"></div>
                                        ${widthRetraso > 0 ? `
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                             style="width:${widthRetraso}%; background-color:#f87171; transition:width 0.4s ease; border-radius:0 10px 10px 0;"></div>` : ''}
                                    </div>
                                </div>`;
                        });

                        html += `</div></div>`;
                    } catch (err) { console.error("Error Gantt:", err); }
                });
                container.innerHTML = html || '<p class="text-white-50">No hay datos de entregas.</p>';
            }
          },
          error: function() {
              container.innerHTML = '<div class="alert alert-danger small rounded-4 mt-3">Error de comunicación Gantt.</div>';
          }
        });
      };

      // Delegación de eventos para el botón Gantt (más robusto)
      $(document).on('click', '.btn-gantt-prof', function() {
          actualizarDatosGantt($(this).data('id'));
      });
    });

    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('validated')) {
        showNotification('Validación correcta', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
      } else {
        const msg = urlParams.get('msg');
        if (msg) {
          const cleanUrl = window.location.pathname;
          window.history.replaceState({}, document.title, cleanUrl);
          
          if (msg === 'deleted') {
            showNotification('Tarea eliminada correctamente.', 'info');
          } else if (msg === 'error_delete') {
            showNotification('Error al eliminar la tarea.', 'danger');
          } else if (msg === 'readmitido') {
            showNotification('Profesor readmitido con éxito.', 'success');
          } else if (msg === 'rechazado') {
            showNotification('Petición de readmisión rechazada.', 'info');
          } else if (msg === 'task_created') {
            showNotification('Tarea asignada correctamente al profesor.', 'success');
          } else if (msg === 'task_error') {
            showNotification('Error al guardar la tarea. Verifica la base de datos.', 'danger');
          } else if (msg === 'task_updated') {
            showNotification('Tarea actualizada correctamente.', 'success');
          } else if (msg === 'task_update_error') {
            showNotification('Error al actualizar la tarea. Verifica la base de datos.', 'danger');
          } else if (msg === 'task_warning') {
            showNotification('Error: Cada entrega debe ser programada para una fecha posterior o igual a la anterior.', 'warning');
          } else if (msg === 'task_missing') {
            showNotification('Faltan datos obligatorios para asignar la tarea.', 'warning');
          }
        }
      }
    });

    // -- Countdown dinámico hacia entregas múltiples -----------------------
    document.addEventListener('DOMContentLoaded', () => {
      const items = document.querySelectorAll('.countdown-item');
      if (items.length === 0) return;

      setInterval(() => {
        const ahora = new Date().getTime();

        items.forEach(item => {
          const deadlineStr = item.getAttribute('data-deadline');
          if (!deadlineStr) return;

          let dateToParse = deadlineStr;
          if (dateToParse.includes(' ')) {
              dateToParse = dateToParse.replace(' ', 'T');
          } else if (!dateToParse.includes('T')) {
              dateToParse += 'T23:59:59';
          }
          
          const deadline = new Date(dateToParse).getTime();
          if (isNaN(deadline)) return;
          
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
    .bg-purple { background-color: #a78bfa !important; }

    /* Ajuste de posición de notificaciones solicitado: 60px + 10px a la izquierda */
    #toast-container {
      right: 95px !important; /* Original 25px + 70px shift */
    }
  </style>

  <link rel="stylesheet" href="css/notifications.css">
  <script src="js/notifications.js"></script>

  <script>
    // Lógica para el menú de hamburguesa / Drawer de perfil
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const profileDrawer = document.querySelector('.formulario');
    const overlayMenu = document.getElementById('overlayMenu');

    function toggleMenu() {
      if(!profileDrawer) return;
      profileDrawer.classList.toggle('active');
      overlayMenu.classList.toggle('active');
      document.body.classList.toggle('menu-open');
      document.body.style.overflow = profileDrawer.classList.contains('active') ? 'hidden' : '';
    }

    if(hamburgerBtn) hamburgerBtn.addEventListener('click', toggleMenu);
    if(overlayMenu) overlayMenu.addEventListener('click', toggleMenu);

    // Cerrar menú al hacer click en botones de acción dentro del drawer
    if(profileDrawer) {
      const drawerButtons = profileDrawer.querySelectorAll('.btn');
      drawerButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          if(profileDrawer.classList.contains('active')) toggleMenu();
        });
      });
    }
  </script>
</body>

</html>
