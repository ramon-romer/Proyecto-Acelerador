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

// ── ACCIÓN: eliminar_cuenta_propia (TUTOR) ──────────────────────────────
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

// ── ACCIÓN: decision_readmision (TUTOR) ──────────────────────────────
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
                $mensaje = "Profesor readmitido con éxito.";
                $tipo_mensaje = "success";
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
                $mensaje = "Petición de readmisión rechazada.";
                $tipo_mensaje = "info";
            }
            // Eliminar la notificación del tutor una vez tomada la decisión
            mysqli_query($conn, "DELETE FROM tbl_notificacion_tutor WHERE id_tutor = $idTutor AND tipo = 'readmision' AND id_referencia = $id_prof_r");
        }
    }
}

// ── Notificaciones del tutor (incluyendo readmisiones) ────────────────
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

// ── ACCIÓN: get_estado_candidato (ADITIVO - DISEÑADOR SENIOR / TARJETAS) ─
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

                // 1. Conteo total de evaluaciones históricas
                $stCount = $pdo_e->prepare("SELECT COUNT(*) as total FROM evaluaciones WHERE json_entrada LIKE :o1 OR json_entrada LIKE :o2");
                $stCount->execute([':o1' => '%'.$orcid_req.'%', ':o2' => '%'.$orcid_limpio.'%']);
                $totalEvals = (int)$stCount->fetch()['total'];

                // 2. Última evaluación para extraer JSON bruto integral
                $stLast = $pdo_e->prepare("SELECT json_entrada FROM evaluaciones WHERE json_entrada LIKE :o1 OR json_entrada LIKE :o2 ORDER BY fecha_creacion DESC LIMIT 1");
                $stLast->execute([':o1' => '%'.$orcid_req.'%', ':o2' => '%'.$orcid_limpio.'%']);
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

                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'data' => $meritData]);
                exit;

            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar el inventario.']);
    exit;
}

// ── ACCIÓN: marcar_entrega_hecha (ARQUITECTURA DE SISTEMAS CRÍTICOS) ──
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

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'timestamp' => $ahora]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
}

// ── ACCIÓN: get_datos_gantt (DIFUSIÓN DE DATOS CRÍTICOS - ALTO RENDIMIENTO) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'get_datos_gantt') {
    $pid_req = (int)($_POST['id_profesor'] ?? 0);
    if ($pid_req > 0) {
        try {
            // SELECT quirúrgico: capturamos solo lo indispensable para el gráfico
            $resTareas = mysqli_query($conn, 
                "SELECT id, titulo_tarea, fecha_creacion, num_entregas, fechas_entregas, fechas_reales_entregas, fechas_inicio_entregas 
                 FROM tbl_tarea_entrega 
                 WHERE id_profesor = $pid_req AND id_tutor = $idTutor 
                 ORDER BY fecha_creacion DESC"
            );
            
            $gData = [];
            while ($t = mysqli_fetch_assoc($resTareas)) {
                $gData[] = [
                    'id' => $t['id'], 
                    'titulo' => $t['titulo_tarea'], 
                    'creacion' => $t['fecha_creacion'],
                    'n' => (int)$t['num_entregas'], 
                    'teoricas' => json_decode($t['fechas_entregas'] ?? '[]', true),
                    'reales' => json_decode($t['fechas_reales_entregas'] ?? '[]', true),
                    'inicios' => json_decode($t['fechas_inicio_entregas'] ?? '[]', true)
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

// ── Procesar actualización de tarea (POST) ──────────────────────────────
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
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'success', 'message' => 'Tarea actualizada con éxito.']);
                    exit;
                }
                $mensaje = 'Tarea actualizada correctamente.';
                $tipo_mensaje = 'success';
            } catch (Exception $e) {
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Error al actualizar: ' . $e->getMessage()]);
                    exit;
                }
                $mensaje = 'Error al actualizar la tarea.';
                $tipo_mensaje = 'danger';
            }
        } else {
            if (isset($_POST['ajax'])) {
                header('Content-Type: application/json');
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
            $titulo_esc = mysqli_real_escape_string($conn, $titulo_tarea);
            $desc_esc   = mysqli_real_escape_string($conn, $desc_tarea);
            $fechas_json = mysqli_real_escape_string($conn, json_encode($fechas_arr, JSON_UNESCAPED_UNICODE));
            
            try {
                mysqli_query($conn,
                    "INSERT INTO tbl_tarea_entrega (id_grupo, id_profesor, id_tutor, titulo_tarea, descripcion_tarea, num_entregas, fechas_entregas)
                     VALUES ($idGrupoAsignado, $idProfAsignado, $idTutor, '$titulo_esc', '$desc_esc', $num_entregas, '$fechas_json')"
                );
                $mensaje = 'Tarea asignada correctamente al profesor.';
                $tipo_mensaje = 'success';
            } catch (Exception $e) {
                $mensaje = 'Error al guardar la tarea. Verifica la base de datos.';
                $tipo_mensaje = 'danger';
            }
        } else {
            $mensaje = 'Faltan datos obligatorios para asignar la tarea.';
            $tipo_mensaje = 'warning';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'borrar_tarea') {
    $id_tarea = (int)($_POST['id_tarea'] ?? 0);
    if ($id_tarea > 0) {
        try {
            mysqli_query($conn, "DELETE FROM tbl_tarea_entrega WHERE id = $id_tarea AND id_tutor = $idTutor");
            $mensaje = 'Tarea eliminada correctamente.';
            $tipo_mensaje = 'info';
        } catch (Exception $e) {
            $mensaje = 'Error al eliminar la tarea.';
            $tipo_mensaje = 'danger';
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
            $mensaje = 'Tarea actualizada correctamente.';
            $tipo_mensaje = 'success';
        } catch (Exception $e) {
            $mensaje = 'Error al actualizar la tarea. Verifica la base de datos.';
            $tipo_mensaje = 'danger';
        }
    } else {
        $mensaje = 'Faltan datos obligatorios para actualizar la tarea.';
        $tipo_mensaje = 'warning';
    }
}

// Grupos del tutor
$resGrupos = mysqli_query($conn, "SELECT id_grupo, nombre FROM tbl_grupo WHERE id_tutor = $idTutor ORDER BY nombre");
$grupos = [];
if ($resGrupos) { while ($g = mysqli_fetch_assoc($resGrupos)) $grupos[] = $g; }
$totalGrupos = count($grupos);

// Profesores asignados al tutor a través de sus grupos
$resProfesores = mysqli_query($conn,
  "SELECT DISTINCT p.id_profesor, p.nombre, p.apellidos, p.ORCID AS orcid, p.correo, p.departamento, p.facultad, p.rama, g.nombre AS grupo
   FROM tbl_grupo g
   INNER JOIN tbl_grupo_profesor gp ON gp.id_grupo = g.id_grupo
   INNER JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
   WHERE g.id_tutor = $idTutor AND p.perfil = 'PROFESOR'
   ORDER BY p.apellidos, p.nombre"
);
$profesores = [];
if ($resProfesores) { while ($p = mysqli_fetch_assoc($resProfesores)) $profesores[] = $p; }
$totalProfesores = count($profesores);

// Datos por profesor: evaluaciones, tareas, publicaciones
$profEvals   = [];  // id_profesor => [todas las evaluaciones]
$profTareas  = [];  // id_profesor => [tareas]
$profPubs    = [];  // id_profesor => count publicaciones

foreach ($profesores as $prof) {
    $pid = (int)$prof['id_profesor'];

    // -- Evaluaciones ANECA --
    $profEvals[$pid] = [];
    $ramaNorm = strtoupper(trim($prof['rama'] ?? ''));
    $dbName = $mapaDB[$ramaNorm] ?? null;
    if ($dbName) {
        try {
            $pdoEval = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $stEval = $pdoEval->prepare("SELECT * FROM evaluaciones WHERE nombre_candidato LIKE :n OR nombre_candidato LIKE :na ORDER BY fecha_creacion DESC");
            $nombreCompleto = trim($prof['nombre'] . ' ' . $prof['apellidos']);
            $stEval->execute(['n' => '%' . trim($prof['nombre']) . '%', 'na' => '%' . $nombreCompleto . '%']);
            $profEvals[$pid] = $stEval->fetchAll();
        } catch (Exception $e) { /* BD no disponible */ }
    }

    // -- Tareas de entrega --
    $profTareas[$pid] = [];
    try {
        $resTareas = mysqli_query($conn,
            "SELECT * FROM tbl_tarea_entrega WHERE id_profesor = $pid AND id_tutor = $idTutor ORDER BY fecha_creacion DESC"
        );
        if ($resTareas) { while ($t = mysqli_fetch_assoc($resTareas)) $profTareas[$pid][] = $t; }
    } catch (Exception $e) { /* tabla puede no existir */ }

    // -- Publicaciones --
    $profPubs[$pid] = 0;
    $resPub = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_publicacion WHERE ORCID_autor = '" . mysqli_real_escape_string($conn, $prof['correo']) . "'");
    if (!$resPub) {
        // Intentar por ORCID en tbl_publicacion_profesor
        $resOrcid = mysqli_query($conn, "SELECT ORCID FROM tbl_profesor WHERE id_profesor = $pid LIMIT 1");
        if ($resOrcid && $rowO = mysqli_fetch_assoc($resOrcid)) {
            $orcidProf = mysqli_real_escape_string($conn, $rowO['ORCID']);
            $resPub2 = mysqli_query($conn, "SELECT COUNT(*) AS total FROM tbl_publicacion_profesor WHERE orcid_profesor = '$orcidProf'");
            if ($resPub2) $profPubs[$pid] = (int)mysqli_fetch_assoc($resPub2)['total'];
        }
    } else {
        $profPubs[$pid] = (int)mysqli_fetch_assoc($resPub)['total'];
    }
}

// ── AJAX: Obtener Histórico ANECA ─────────────────────────────────────
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
                           OR `json_entrada` LIKE :orcid_limpio)
                        ORDER BY `fecha_creacion` DESC";
                
                $st_h = $pdo_h->prepare($sql);
                $st_h->execute([
                    ':orcid_original' => '%' . $orcid_original . '%',
                    ':orcid_limpio'   => '%' . $orcid_limpio . '%'
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
        <img src="../../acelerador_login/fronten/img/AcademyAccelerator_def.png" id="academy" alt="academy" />
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

          <button type="button" class="btn btn-danger px-4 py-2 rounded-pill fw-medium d-inline-flex align-items-center gap-2" onclick="customConfirm('¿Estás totalmente seguro de que deseas eliminar tu cuenta permanentemente? Toda tu información y grupos creados serán archivados y perderás el acceso.', () => document.getElementById('formEliminarCuenta').submit());">
              <i class="bi bi-person-x-fill"></i> Eliminar cuenta
          </button>

          <form id="formEliminarCuenta" method="POST" class="d-none">
              <input type="hidden" name="accion" value="eliminar_cuenta_propia">
          </form>

        </div>

      </div>

      <!-- ════════════════════════════════════════════════════════
           DASHBOARD TUTOR — Grupos y profesores asignados
      ════════════════════════════════════════════════════════ -->
      <div class="dashboard">

        <?php if (!empty($mensaje)): ?>
          <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <!-- Notificaciones de Decisiones Pendientes (Readmisiones) -->
        <?php if (!empty($notifsTutor)): ?>
        <div class="row g-3 mb-4 w-100">
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
          <div class="col-md-4">
            <div class="dashboard-stat-card h-100">
              <div class="stat-label"><i class="bi bi-collection me-1"></i> Grupos asignados</div>
              <div class="stat-value"><?= $totalGrupos ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card h-100">
              <div class="stat-label"><i class="bi bi-person-workspace me-1"></i> Profesores tutorizados</div>
              <div class="stat-value"><?= $totalProfesores ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="dashboard-stat-card h-100">
              <div class="stat-label"><i class="bi bi-envelope me-1"></i> Correo del tutor</div>
              <div class="stat-value stat-email"><?= htmlspecialchars($correo) ?></div>
            </div>
          </div>
        </div>

        <!-- Tarjetas de profesores -->
        <?php if ($totalProfesores > 0): ?>
        <h2 class="dashboard-section-title mb-3"><i class="bi bi-people-fill me-2"></i>Mis profesores</h2>
        <div class="row g-3 w-100">
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
                    <a href="../../dashboard_profesor.php?nombre=<?= urlencode($prof['nombre']) ?>&rama=<?= urlencode($prof['rama']) ?>" class="btn btn-sm btn-primary rounded-3 d-flex flex-column align-items-center" style="min-width:68px; padding:8px 10px; gap:4px;">
                      <i class="bi bi-diagram-3" style="font-size:1.25rem;"></i>
                      <span style="font-size:.65rem; line-height:1;">Dashboard</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center" style="min-width:68px; padding:8px 10px; gap:4px;" data-bs-toggle="modal" data-bs-target="#modalTarea<?= $pid ?>">
                      <i class="bi bi-clipboard-check" style="font-size:1.25rem;"></i>
                      <span style="font-size:.65rem; line-height:1;">Ver tarea</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center btn-estado-prof" style="min-width:68px; padding:8px 10px; gap:4px;" data-bs-toggle="modal" data-bs-target="#modalEstado<?= $pid ?>" data-orcid="<?= htmlspecialchars($prof['orcid']) ?>">
                      <i class="bi bi-speedometer2" style="font-size:1.25rem;"></i>
                      <span style="font-size:.65rem; line-height:1;">Estado</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center btn-gantt-prof" style="min-width:68px; padding:8px 10px; gap:4px;" data-bs-toggle="modal" data-bs-target="#modalGrafico<?= $pid ?>" data-id="<?= $pid ?>">
                      <i class="bi bi-bar-chart-line" style="font-size:1.25rem;"></i>
                      <span style="font-size:.65rem; line-height:1;">Gráfico</span>
                    </button>                    <button type="button" class="btn btn-sm btn-outline-light rounded-3 d-flex flex-column align-items-center" style="min-width:68px; padding:8px 10px; gap:4px;" data-bs-toggle="modal" data-bs-target="#modalOpciones<?= $pid ?>">
                      <i class="bi bi-three-dots" style="font-size:1.25rem;"></i>
                      <span style="font-size:.65rem; line-height:1;">Opciones</span>
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

    <!-- ═══════════════════════════════════════════════════════════════
         MODALES POR PROFESOR (fuera de panel-wrapper, dentro de main)
    ═══════════════════════════════════════════════════════════════ -->
    <?php foreach ($profesores as $prof):
      $pid = (int)$prof['id_profesor'];
      $evalsList = $profEvals[$pid] ?? [];
      $tareasList = $profTareas[$pid] ?? [];
      $lastEval = !empty($evalsList) ? $evalsList[0] : null;
      $numEvals = count($evalsList);
      $numPubs = $profPubs[$pid] ?? 0;
      $totalFinal = $lastEval ? (float)$lastEval['total_final'] : 0;
      $profNombreCompleto = htmlspecialchars($prof['nombre'] . ' ' . $prof['apellidos']);
    ?>

    <!-- MODAL 1: Ver tarea -->
    <div class="modal fade" id="modalTarea<?= $pid ?>" tabindex="-1">
      <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
          <div class="modal-header border-bottom border-light border-opacity-25">
            <h5 class="modal-title"><i class="bi bi-clipboard-check me-2"></i>Tareas — <?= $profNombreCompleto ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body d-flex flex-column custom-scrollbar pe-2" style="max-width: 420px; margin: 0 auto; width: 100%; min-height: 70vh; overflow-y: auto; overflow-x: hidden; align-items: center;">
            
            <!-- Mostrar todas las tareas activas (múltiples simultáneas) -->
            <?php if (!empty($tareasList)): ?>
              <h6 class="text-white fw-bold mb-3 text-center w-100"><i class="bi bi-list-task me-2"></i>Tareas Activas</h6>
              <div class="d-flex flex-column gap-3 mb-4">
              <?php foreach ($tareasList as $tareaActual): 
                $fechasT = json_decode($tareaActual['fechas_entregas'] ?? '[]', true) ?: [];
                $hechas = json_decode($tareaActual['entregas_realizadas'] ?? '{}', true) ?: [];
                $tiempoPrincipal = !empty($fechasT) ? $fechasT[0] : '';
              ?>
                <div class="p-3 rounded-3 w-100" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.10);">
                  <!-- Cabecera centrada: título + acciones -->
                  <div class="text-center mb-2">
                    <h6 class="text-white fw-bold mb-1"><?= htmlspecialchars($tareaActual['titulo_tarea']) ?></h6>
                    <span class="badge rounded-pill" style="background:rgba(74,222,128,0.2); color:#4ade80; font-size:.7rem;"><?= $tareaActual['num_entregas'] ?> entregas</span>
                  </div>
                  <!-- Botones editar/borrar centrados -->
                  <form method="POST" class="m-0 d-flex justify-content-center gap-3 mb-2" onsubmit="event.preventDefault(); customConfirm('¿Seguro que deseas eliminar esta tarea?', () => this.submit());">
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
                    ?>
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
            <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>Estado Ejecutivo — <?= $profNombreCompleto ?></h5>
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
            <h5 class="modal-title"><i class="bi bi-bar-chart-line me-2"></i>Logística de Entregas — <?= $profNombreCompleto ?></h5>
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
            <h5 class="modal-title"><i class="bi bi-three-dots me-2"></i>Opciones — <?= $profNombreCompleto ?></h5>
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
            <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Histórico ANECA — <?= $profNombreCompleto ?></h5>
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

    <?php endforeach; ?>

  </main>

  <?php include('chatbot.php'); ?>

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

  <!-- MODAL GLOBAL: Editar Tarea -->
  <div class="modal fade" id="modalEditarTarea" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="background:rgba(15,23,42,0.95); border:1px solid rgba(255,255,255,0.15); color:#fff;">
        <div class="modal-header border-bottom border-light border-opacity-25">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Tarea</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="formEditarTarea">
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

    // Función para generar fechas dinámicas en el modal "Ver tarea"
    function generarFechasModal(pid) {
      const inputVal = parseInt(document.getElementById('inputNumEntregas' + pid).value, 10);
      const numFechas = isNaN(inputVal) || inputVal < 1 ? 1 : inputVal;
      const container = document.getElementById('fechasContainer' + pid);
      
      let html = '';
      for (let i = 1; i <= numFechas; i++) {
        html += `
          <div>
            <label class="form-label text-white-50 small mb-1">Fecha y hora de entrega ${i}</label>
            <input type="datetime-local" name="fecha_entrega[]" class="form-control form-control-sm text-white" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.2);" required>
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
        const valor = fechasValores[i - 1] || '';
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

    // ── AJAX: ANALISTA DE DATOS (DISEÑO EJECUTIVO REESTRUCTURADO) ───────
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

    // ── AJAX: LOGÍSTICA GANTT (get_datos_gantt & SEMÁFORO ALERTAS) ───────
    document.addEventListener("DOMContentLoaded", () => {
      // Delegación de eventos para botones "Marcar como hecha" (SOLO DESDE MODAL TAREAS)
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
                // Feedback visual inmediato
                btn.closest('.d-flex').find('.badge').remove(); 
                btn.after('<span class="badge bg-success bg-opacity-10 text-success ms-1" style="font-size: .6rem;">HECHA</span>');
                btn.remove();
                
                const activeModal = document.querySelector('.modal.show[id^="modalGrafico"]');
                if (activeModal) {
                    const pid = activeModal.id.replace('modalGrafico', '');
                    const gBtn = document.querySelector(`.btn-gantt-prof[data-id="${pid}"]`);
                    if (gBtn) gBtn.click();
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

      document.querySelectorAll('.btn-gantt-prof').forEach(btn => {
        btn.addEventListener('click', function() {
          const pid = this.getAttribute('data-id');
          const container = document.getElementById('ganttContainer' + pid);
          
          if (!pid || !container) return;

          $.ajax({
            url: 'panel_tutor.php',
            type: 'POST',
            data: { accion: 'get_datos_gantt', id_profesor: pid },
            success: function(response) {
    if (response.status === 'success' && response.data) {
        let html = '';
        const hoy = new Date();

        response.data.forEach(t => {
            try {
                const fechasLimite = t.teoricas || [];
                const fechasReales = t.reales || [];
                const inicioTarea = new Date(t.creacion).getTime();
                
                // Definimos el fin del gráfico: el mayor entre el último plazo o hoy
                const maxPlazo = Math.max(...fechasLimite.map(f => new Date(f).getTime()));
                const finGrafico = Math.max(maxPlazo, hoy.getTime());
                const totalDuracion = finGrafico - inicioTarea;

                html += `<div class="gantt-wrapper mb-5 p-3 rounded-4" style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.08);">
                            <h6 class="text-white fw-bold mb-4"><i class="bi bi-grid-3x2-gap me-2 text-info"></i>${t.titulo}</h6>
                            <div class="gantt-body d-flex flex-column gap-3">`;

                fechasLimite.forEach((limiteStr, idx) => {
                    const limite = new Date(limiteStr).getTime();
                    const real = fechasReales[idx] ? new Date(fechasReales[idx]).getTime() : null;
                    
                    // Lógica de Relevos: El inicio es el fin de la entrega anterior (o la creación si es la primera)
                    const inicioSegmento = (idx === 0) ? inicioTarea : 
                                         (fechasReales[idx-1] ? new Date(fechasReales[idx-1]).getTime() : hoy.getTime());
                    
                    // Fin del segmento: si está hecha, su fecha real. Si es la activa, hoy.
                    let finSegmento = real ? real : hoy.getTime();
                    if (finSegmento < inicioSegmento) finSegmento = inicioSegmento;

                    // Cálculo de Retraso (Aditivo)
                    const esRetraso = finSegmento > limite;
                    let infoRetraso = '';
                    let widthRetraso = 0;
                    let widthNormal = 0;

                    if (esRetraso) {
                        const diff = finSegmento - limite;
                        const dias = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const horas = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        infoRetraso = ` — Retraso: ${dias}d ${horas}h ${mins}m`;
                        
                        // Si empezó antes del límite, la parte normal llega hasta el límite
                        const finNormal = Math.max(inicioSegmento, limite);
                        widthNormal = ((Math.min(finSegmento, limite) - inicioSegmento) / totalDuracion) * 100;
                        widthRetraso = ((finSegmento - Math.max(inicioSegmento, limite)) / totalDuracion) * 100;
                    } else {
                        widthNormal = ((finSegmento - inicioSegmento) / totalDuracion) * 100;
                    }

                    const marginLeft = ((inicioSegmento - inicioTarea) / totalDuracion) * 100;
                    const colorBarra = real ? '#198754' : '#3b82f6';
                    const animation = (!real) ? 'progress-bar-animated progress-bar-striped' : '';

                    html += `
                        <div class="gantt-row">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-white-50" style="font-size:0.75rem;">Entrega ${idx + 1}</span>
                                ${esRetraso ? `<span class="text-danger fw-bold" style="font-size:0.7rem;">${real ? 'ENTREGA TARDÍA' : 'FUERA DE PLAZO'}${infoRetraso}</span>` : ''}
                            </div>
                            <div class="progress position-relative" style="height:10px; background:rgba(255,255,255,0.05); overflow:visible;">
                                <!-- Barra Normal -->
                                <div class="progress-bar ${animation}" 
                                     style="width:${widthNormal}%; margin-left:${marginLeft}%; background-color:${colorBarra}; transition:width 0.4s ease; border-radius:10px 0 0 10px;"></div>
                                <!-- Barra de Retraso (Roja) -->
                                ${widthRetraso > 0 ? `
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     style="width:${widthRetraso}%; background-color:#f87171; transition:width 0.4s ease; border-radius:0 10px 10px 0;"></div>` : ''}
                                
                                <!-- Rombo de Plazo Límite (Fin de Plazo) -->
                                <div class="position-absolute" 
                                     style="left:${((limite - inicioTarea) / totalDuracion) * 100}%; top:50%; width:16px; height:16px; border:3px solid ${esRetraso ? '#f87171' : '#fff'}; background:#0f172a; transform: translate(-50%, -50%) rotate(45deg); z-index:10; box-shadow: ${esRetraso ? '0 0 15px #f87171' : 'none'};" 
                                     title="Plazo: ${limiteStr}"></div>
                            </div>
                        </div>`;
                });

                html += `</div></div>`;
            } catch (err) { console.error("Error procesando tarea:", err); }
        });
        container.innerHTML = html || '<p class="text-white-50">No hay datos de entregas.</p>';
    }
},
            error: function() {
                container.innerHTML = '<div class="alert alert-danger small rounded-4 mt-3">Error de comunicación Gantt.</div>';
            }
          });
        });
      });
    });

    document.addEventListener('DOMContentLoaded', () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('validated')) {
        showNotification('Validación correcta', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
      }
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
</body>
</html>
