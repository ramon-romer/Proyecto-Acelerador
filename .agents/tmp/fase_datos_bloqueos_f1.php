<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');

function section(string $t): void { echo "\n=== {$t} ===\n"; }
function rows(mysqli $db, string $sql): void {
  $r = $db->query($sql);
  while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "\n";
  }
}

$f4 = $db->query("SELECT run_id FROM audit_log WHERE run_id LIKE 'F4C_EXEC_%' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['run_id'] ?? '';
$f5 = $db->query("SELECT phase5_run_id FROM mergeable_runs ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['phase5_run_id'] ?? '';

section('run_context');
rows($db, "SELECT DATABASE() AS db, '{$f4}' AS f4_run, '{$f5}' AS f5_run");

section('pending_manual_duplicates');
rows($db, "
SELECT a.correo, a.source_pk AS id_profesor, p.ORCID, p.nombre, p.apellidos, p.perfil,
       a.canonical_status, a.notes
FROM audit_accounts a
INNER JOIN tbl_profesor p ON p.id_profesor = a.source_pk
WHERE a.run_id = '{$f4}'
  AND a.source_table = 'tbl_profesor'
  AND a.canonical_status = 'MANUAL_REVIEW'
ORDER BY a.correo, a.source_pk
");

section('duplicate_by_correo_overview');
rows($db, "
SELECT correo, COUNT(*) AS total,
       GROUP_CONCAT(id_profesor ORDER BY id_profesor SEPARATOR ',') AS ids,
       GROUP_CONCAT(perfil ORDER BY id_profesor SEPARATOR ',') AS perfiles
FROM tbl_profesor
GROUP BY correo
HAVING COUNT(*) > 1
ORDER BY total DESC, correo
");

section('perfiles_vacios');
rows($db, "
SELECT p.id_profesor, p.correo, p.ORCID, p.nombre, p.apellidos, p.perfil
FROM tbl_profesor p
INNER JOIN audit_accounts a
  ON a.run_id = '{$f4}'
 AND a.source_table = 'tbl_profesor'
 AND a.source_pk = p.id_profesor
WHERE a.perfil_state = 'EMPTY'
ORDER BY p.id_profesor
");

section('admins_validos_estado');
rows($db, "
SELECT
  SUM(UPPER(TRIM(perfil))='ADMIN') AS admins,
  SUM(UPPER(TRIM(perfil))='ADMINISTRADOR') AS administradores,
  SUM(UPPER(TRIM(perfil)) IN ('ADMIN','ADMINISTRADOR')) AS total_admin_valido
FROM tbl_profesor
");

section('grupos_referencias_rotas');
rows($db, "
SELECT g.id_grupo, g.nombre, g.id_tutor,
       p.id_profesor AS tutor_existente,
       p.perfil AS tutor_perfil
FROM tbl_grupo g
LEFT JOIN tbl_profesor p ON p.id_profesor = g.id_tutor
ORDER BY g.id_grupo
");

section('asignaciones_grupo_rotas');
rows($db, "
SELECT gp.id, gp.id_grupo, gp.id_profesor,
       g.id_grupo AS grupo_existente,
       p.id_profesor AS profesor_existente
FROM tbl_grupo_profesor gp
LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo
LEFT JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
ORDER BY gp.id
");

section('group_quarantine_active');
rows($db, "
SELECT source_table, source_pk, id_grupo, id_tutor, id_profesor, quarantine_type, reason
FROM quarantine_groups
WHERE run_id = '{$f4}' AND status='ACTIVE'
ORDER BY source_table, source_pk
");

section('accounts_quarantine_active_summary');
rows($db, "
SELECT quarantine_type, COUNT(*) AS total
FROM quarantine_accounts
WHERE run_id = '{$f4}' AND status='ACTIVE'
GROUP BY quarantine_type
ORDER BY quarantine_type
");

section('mergeable_v2_previous_state');
rows($db, "
SELECT
  (SELECT COUNT(*) FROM mergeable_tbl_usuario WHERE phase5_run_id='{$f5}') AS mergeable_usuarios,
  (SELECT COUNT(*) FROM mergeable_tbl_profesor WHERE phase5_run_id='{$f5}') AS mergeable_profesores,
  (SELECT COUNT(*) FROM mergeable_tbl_grupo WHERE phase5_run_id='{$f5}') AS mergeable_grupos,
  (SELECT COUNT(*) FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id='{$f5}') AS mergeable_asignaciones
");

section('login_blockers_from_data');
rows($db, "
SELECT
  (SELECT COUNT(*) FROM (SELECT correo FROM tbl_usuario GROUP BY correo HAVING COUNT(*)>1) udup) AS correos_duplicados_tbl_usuario,
  (SELECT COUNT(*) FROM (SELECT correo FROM tbl_profesor GROUP BY correo HAVING COUNT(*)>1) pdup) AS correos_duplicados_tbl_profesor,
  (SELECT COUNT(*) FROM tbl_profesor WHERE perfil IS NULL OR TRIM(perfil)='') AS perfiles_vacios,
  (SELECT COUNT(*) FROM tbl_profesor WHERE UPPER(TRIM(perfil)) NOT IN ('ADMIN','ADMINISTRADOR','PROFESOR','TUTOR') AND NOT (perfil IS NULL OR TRIM(perfil)='')) AS perfiles_fuera_catalogo
");

section('mis_grupos_blockers_from_data');
rows($db, "
SELECT
  (SELECT COUNT(*) FROM tbl_grupo g LEFT JOIN tbl_profesor t ON t.id_profesor = g.id_tutor WHERE t.id_profesor IS NULL) AS grupos_tutor_huerfano,
  (SELECT COUNT(*) FROM tbl_grupo_profesor gp LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo WHERE g.id_grupo IS NULL OR gp.id_grupo=0) AS asig_grupo_huerfano_o_0,
  (SELECT COUNT(*) FROM tbl_grupo g INNER JOIN tbl_profesor t ON t.id_profesor = g.id_tutor WHERE UPPER(TRIM(t.perfil)) <> 'TUTOR') AS grupos_tutor_no_tutor
");
?>
