<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');
$run = 'F5_PREP_20260406_114916';

function section($n){ echo "\n=== $n ===\n"; }
function rows($db,$sql){ $r=$db->query($sql); while($row=$r->fetch_assoc()){ echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL; } }

section('run_meta');
rows($db, "SELECT phase5_run_id, source_f4c_run_id, criteria_version, created_at FROM mergeable_runs WHERE phase5_run_id='$run'");

section('mergeable_counts');
rows($db, "
SELECT
 (SELECT COUNT(*) FROM mergeable_tbl_usuario WHERE phase5_run_id='$run') AS mergeable_usuarios,
 (SELECT COUNT(*) FROM mergeable_tbl_profesor WHERE phase5_run_id='$run') AS mergeable_profesores,
 (SELECT COUNT(*) FROM mergeable_tbl_grupo WHERE phase5_run_id='$run') AS mergeable_grupos,
 (SELECT COUNT(*) FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id='$run') AS mergeable_asignaciones
");

section('mergeable_profesor_detail');
rows($db, "SELECT id_profesor, correo, perfil, canonical_status, password_class, rama_original, rama_normalizada FROM mergeable_tbl_profesor WHERE phase5_run_id='$run' ORDER BY id_profesor");

section('mergeable_usuario_detail');
rows($db, "SELECT id_usuario, correo, password_class, linked_id_profesor FROM mergeable_tbl_usuario WHERE phase5_run_id='$run' ORDER BY id_usuario");

section('exclusions_by_table_reason');
rows($db, "
SELECT source_table, reason_code, COUNT(*) AS total
FROM mergeable_exclusions
WHERE phase5_run_id='$run'
GROUP BY source_table, reason_code
ORDER BY source_table, total DESC, reason_code
");

section('excluded_correos_profesor');
rows($db, "
SELECT correo, GROUP_CONCAT(DISTINCT reason_code ORDER BY reason_code SEPARATOR ',') AS reasons
FROM mergeable_exclusions
WHERE phase5_run_id='$run'
  AND source_table='tbl_profesor'
GROUP BY correo
ORDER BY correo
");

section('excluded_correos_usuario');
rows($db, "
SELECT correo, GROUP_CONCAT(DISTINCT reason_code ORDER BY reason_code SEPARATOR ',') AS reasons
FROM mergeable_exclusions
WHERE phase5_run_id='$run'
  AND source_table='tbl_usuario'
GROUP BY correo
ORDER BY correo
");

section('excluded_groups_relations');
rows($db, "
SELECT source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail
FROM mergeable_exclusions
WHERE phase5_run_id='$run'
  AND source_table IN ('tbl_grupo','tbl_grupo_profesor')
ORDER BY source_table, source_pk, reason_code
");

section('role_ambiguity_checks');
rows($db, "
SELECT
 (SELECT COUNT(*) FROM (
    SELECT correo
    FROM mergeable_tbl_profesor
    WHERE phase5_run_id='$run'
    GROUP BY correo
    HAVING COUNT(*) > 1
 ) t) AS correos_duplicados_en_subset,
 (SELECT COUNT(*) FROM (
    SELECT correo
    FROM mergeable_tbl_profesor
    WHERE phase5_run_id='$run'
    GROUP BY correo
    HAVING COUNT(DISTINCT perfil) > 1
 ) t) AS correos_con_ambiguedad_rol
");

section('login_misgrupos_risk_indicators');
rows($db, "
SELECT
  (SELECT COUNT(*) FROM mergeable_tbl_usuario WHERE phase5_run_id='$run' AND password_class <> 'BCRYPT') AS usuarios_no_bcrypt_en_subset,
  (SELECT COUNT(*) FROM mergeable_tbl_usuario WHERE phase5_run_id='$run' AND password_class = 'BCRYPT') AS usuarios_bcrypt_en_subset,
  (SELECT COUNT(*) FROM mergeable_tbl_grupo WHERE phase5_run_id='$run') AS grupos_en_subset,
  (SELECT COUNT(*) FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id='$run') AS asignaciones_en_subset,
  (SELECT COUNT(*) FROM mergeable_tbl_profesor WHERE phase5_run_id='$run' AND perfil='TUTOR') AS tutores_en_subset
");
