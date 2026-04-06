<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'acelerador_staging_20260406');
$db->set_charset('utf8mb4');

function oneRow(mysqli $db, string $sql): array {
    $r = $db->query($sql);
    $row = $r->fetch_assoc();
    return $row ?: [];
}

function printSection(string $title): void {
    echo "\n=== {$title} ===\n";
}

function printRows(mysqli $db, string $sql): void {
    $r = $db->query($sql);
    while ($row = $r->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
}

printSection('tables');
printRows($db, "SHOW TABLES");

printSection('describe tbl_usuario');
printRows($db, "DESCRIBE tbl_usuario");

printSection('describe tbl_profesor');
printRows($db, "DESCRIBE tbl_profesor");

printSection('password_summary tbl_usuario');
$usuarioSummary = oneRow($db, "
SELECT
  COUNT(*) AS total,
  SUM(password IS NULL OR TRIM(password)='') AS vacias,
  SUM(password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$') AS bcrypt,
  SUM(NOT (password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$') AND NOT (password IS NULL OR TRIM(password)='')) AS no_bcrypt
FROM tbl_usuario
");
echo json_encode($usuarioSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

printSection('password_summary tbl_profesor');
$profSummary = oneRow($db, "
SELECT
  COUNT(*) AS total,
  SUM(password IS NULL OR TRIM(password)='') AS vacias,
  SUM(password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$') AS bcrypt,
  SUM(NOT (password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$') AND NOT (password IS NULL OR TRIM(password)='')) AS no_bcrypt
FROM tbl_profesor
");
echo json_encode($profSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

printSection('password_cross_table_by_correo');
printRows($db, "
SELECT
  p.correo,
  p.id_profesor,
  p.perfil,
  LEFT(p.password, 12) AS pfx_prof,
  LEFT(u.password, 12) AS pfx_usr,
  (p.password = u.password) AS same_password,
  (p.password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$') AS p_bcrypt,
  (u.password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$') AS u_bcrypt
FROM tbl_profesor p
LEFT JOIN tbl_usuario u ON u.correo = p.correo
ORDER BY p.correo, p.id_profesor
");

printSection('profesores_password_classification');
printRows($db, "
SELECT
  id_profesor,
  correo,
  perfil,
  CASE
    WHEN password IS NULL OR TRIM(password) = '' THEN 'EMPTY'
    WHEN password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$' THEN 'BCRYPT'
    ELSE 'LEGACY_TEXT_OR_OTHER'
  END AS clase,
  CHAR_LENGTH(password) AS len,
  LEFT(password, 18) AS pfx
FROM tbl_profesor
ORDER BY clase, correo, id_profesor
");

printSection('duplicate_correos_tbl_profesor');
printRows($db, "
SELECT correo, COUNT(*) AS total,
       GROUP_CONCAT(id_profesor ORDER BY id_profesor SEPARATOR ',') AS ids,
       GROUP_CONCAT(perfil ORDER BY id_profesor SEPARATOR ',') AS perfiles,
       GROUP_CONCAT(LEFT(password,12) ORDER BY id_profesor SEPARATOR '|') AS pass_prefixes
FROM tbl_profesor
GROUP BY correo
HAVING COUNT(*) > 1
ORDER BY total DESC, correo
");

printSection('perfil_distribution_raw');
printRows($db, "
SELECT perfil, COUNT(*) AS total
FROM tbl_profesor
GROUP BY perfil
ORDER BY total DESC, perfil
");

printSection('perfil_invalid_check');
$perfilInvalid = oneRow($db, "
SELECT
  COUNT(*) AS total,
  SUM(perfil IS NULL OR TRIM(perfil)='') AS vacio,
  SUM(UPPER(TRIM(perfil)) NOT IN ('ADMIN','ADMINISTRADOR','PROFESOR','TUTOR') AND NOT (perfil IS NULL OR TRIM(perfil)='')) AS fuera_catalogo,
  SUM(UPPER(TRIM(perfil))='ADMINISTRADOR') AS administrador_literal
FROM tbl_profesor
");
echo json_encode($perfilInvalid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

printSection('grupos_zero_orphans');
$groupIssues = oneRow($db, "
SELECT
  (SELECT COUNT(*) FROM tbl_grupo WHERE id_tutor = 0) AS grupos_con_tutor_0,
  (SELECT COUNT(*) FROM tbl_grupo g LEFT JOIN tbl_profesor p ON p.id_profesor = g.id_tutor WHERE p.id_profesor IS NULL) AS grupos_con_tutor_huerfano,
  (SELECT COUNT(*) FROM tbl_grupo_profesor WHERE id_grupo = 0) AS asig_con_grupo_0,
  (SELECT COUNT(*) FROM tbl_grupo_profesor gp LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo WHERE g.id_grupo IS NULL) AS asig_grupo_huerfano,
  (SELECT COUNT(*) FROM tbl_grupo_profesor gp LEFT JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor WHERE p.id_profesor IS NULL) AS asig_profesor_huerfano
");
echo json_encode($groupIssues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

printSection('rama_distribution');
printRows($db, "
SELECT rama, COUNT(*) AS total
FROM tbl_profesor
GROUP BY rama
ORDER BY total DESC, rama
");

printSection('potential_quarantine_candidates');
printRows($db, "
SELECT p.id_profesor, p.correo, p.perfil,
       CASE WHEN p.password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$' THEN 'BCRYPT' ELSE 'NON_BCRYPT' END AS pass_class,
       CASE WHEN gpx.id_profesor IS NOT NULL THEN 1 ELSE 0 END AS has_asig_grupo_huerfana
FROM tbl_profesor p
LEFT JOIN (
  SELECT DISTINCT gp.id_profesor
  FROM tbl_grupo_profesor gp
  LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo
  WHERE g.id_grupo IS NULL OR gp.id_grupo = 0
) gpx ON gpx.id_profesor = p.id_profesor
WHERE (p.password IS NULL OR TRIM(p.password)='' OR NOT (p.password REGEXP '^\\$2[aby]?\\$[0-9]{2}\\$'))
   OR p.correo IN (SELECT correo FROM tbl_profesor GROUP BY correo HAVING COUNT(*) > 1)
   OR gpx.id_profesor IS NOT NULL
ORDER BY p.correo, p.id_profesor
");
