<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'acelerador_staging_20260406');
$db->set_charset('utf8mb4');

function section(string $name): void {
    echo "\n=== $name ===\n";
}

function rows(mysqli $db, string $sql): void {
    $res = $db->query($sql);
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
}

function one(mysqli $db, string $sql): void {
    $res = $db->query($sql);
    $row = $res->fetch_assoc();
    echo json_encode($row ?: [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
}

$bcryptExpr = "(password IS NOT NULL AND CHAR_LENGTH(password)=60 AND (password LIKE '$2y$%' OR password LIKE '$2a$%' OR password LIKE '$2b$%'))";

section('show_create_tbl_usuario');
rows($db, "SHOW CREATE TABLE tbl_usuario");

section('show_create_tbl_profesor');
rows($db, "SHOW CREATE TABLE tbl_profesor");

section('password_presence_and_class_tbl_usuario');
one($db, "
SELECT
  COUNT(*) AS total,
  SUM(password IS NULL OR TRIM(password)='') AS vacias,
  SUM($bcryptExpr) AS bcrypt_like_60,
  SUM(NOT ($bcryptExpr) AND NOT (password IS NULL OR TRIM(password)='')) AS non_bcrypt_non_empty
FROM tbl_usuario
");

section('password_presence_and_class_tbl_profesor');
one($db, "
SELECT
  COUNT(*) AS total,
  SUM(password IS NULL OR TRIM(password)='') AS vacias,
  SUM($bcryptExpr) AS bcrypt_like_60,
  SUM(NOT ($bcryptExpr) AND NOT (password IS NULL OR TRIM(password)='')) AS non_bcrypt_non_empty
FROM tbl_profesor
");

section('bcrypt_accounts_tbl_profesor');
rows($db, "
SELECT id_profesor, correo, perfil, CHAR_LENGTH(password) AS len, LEFT(password, 12) AS prefix
FROM tbl_profesor
WHERE $bcryptExpr
ORDER BY correo, id_profesor
");

section('bcrypt_accounts_tbl_usuario');
rows($db, "
SELECT id_usuario, correo, CHAR_LENGTH(password) AS len, LEFT(password, 12) AS prefix
FROM tbl_usuario
WHERE $bcryptExpr
ORDER BY correo, id_usuario
");

section('password_cross_table_presence_by_correo');
one($db, "
SELECT
  (SELECT COUNT(*) FROM tbl_profesor) AS total_profesor_rows,
  (SELECT COUNT(*) FROM tbl_usuario) AS total_usuario_rows,
  (SELECT COUNT(*) FROM tbl_profesor p LEFT JOIN tbl_usuario u ON u.correo=p.correo WHERE u.correo IS NULL) AS profesor_sin_usuario,
  (SELECT COUNT(*) FROM tbl_usuario u LEFT JOIN tbl_profesor p ON p.correo=u.correo WHERE p.correo IS NULL) AS usuario_sin_profesor,
  (SELECT COUNT(*) FROM tbl_profesor p INNER JOIN tbl_usuario u ON u.correo=p.correo WHERE p.password=u.password) AS password_igual_por_join
");

section('profesor_sin_usuario_detalle');
rows($db, "
SELECT p.id_profesor, p.correo, p.perfil, LEFT(p.password, 12) AS prefix
FROM tbl_profesor p
LEFT JOIN tbl_usuario u ON u.correo = p.correo
WHERE u.correo IS NULL
ORDER BY p.id_profesor
");

section('usuario_sin_profesor_detalle');
rows($db, "
SELECT u.id_usuario, u.correo, LEFT(u.password, 12) AS prefix
FROM tbl_usuario u
LEFT JOIN tbl_profesor p ON p.correo = u.correo
WHERE p.correo IS NULL
ORDER BY u.id_usuario
");

section('duplicate_correos_tbl_profesor');
rows($db, "
SELECT
  correo,
  COUNT(*) AS total,
  GROUP_CONCAT(id_profesor ORDER BY id_profesor SEPARATOR ',') AS ids,
  GROUP_CONCAT(perfil ORDER BY id_profesor SEPARATOR ',') AS perfiles,
  GROUP_CONCAT(CASE WHEN $bcryptExpr THEN 'BCRYPT' ELSE 'LEGACY' END ORDER BY id_profesor SEPARATOR ',') AS class_password
FROM tbl_profesor
GROUP BY correo
HAVING COUNT(*) > 1
ORDER BY total DESC, correo
");

section('duplicate_correos_relational_detail');
rows($db, "
SELECT
  p.correo,
  p.id_profesor,
  p.ORCID,
  p.nombre,
  p.apellidos,
  p.perfil,
  p.rama,
  CASE WHEN $bcryptExpr THEN 'BCRYPT' ELSE 'LEGACY' END AS pass_class,
  COALESCE(gp.cnt_gp, 0) AS asignaciones_gp,
  COALESCE(gt.cnt_gt, 0) AS grupos_como_tutor
FROM tbl_profesor p
LEFT JOIN (
  SELECT id_profesor, COUNT(*) AS cnt_gp
  FROM tbl_grupo_profesor
  GROUP BY id_profesor
) gp ON gp.id_profesor = p.id_profesor
LEFT JOIN (
  SELECT id_tutor, COUNT(*) AS cnt_gt
  FROM tbl_grupo
  GROUP BY id_tutor
) gt ON gt.id_tutor = p.id_profesor
WHERE p.correo IN (
  SELECT correo
  FROM tbl_profesor
  GROUP BY correo
  HAVING COUNT(*) > 1
)
ORDER BY p.correo, p.id_profesor
");

section('duplicate_correos_tbl_usuario');
rows($db, "
SELECT correo, COUNT(*) AS total, GROUP_CONCAT(id_usuario ORDER BY id_usuario SEPARATOR ',') AS ids
FROM tbl_usuario
GROUP BY correo
HAVING COUNT(*) > 1
ORDER BY total DESC, correo
");

section('perfiles_distribution_raw');
rows($db, "
SELECT perfil, COUNT(*) AS total
FROM tbl_profesor
GROUP BY perfil
ORDER BY total DESC, perfil
");

section('perfiles_invalid_or_empty');
one($db, "
SELECT
  COUNT(*) AS total,
  SUM(perfil IS NULL OR TRIM(perfil)='') AS vacio,
  SUM(UPPER(TRIM(perfil)) NOT IN ('ADMIN','ADMINISTRADOR','PROFESOR','TUTOR') AND NOT (perfil IS NULL OR TRIM(perfil)='')) AS fuera_catalogo,
  SUM(UPPER(TRIM(perfil))='ADMIN') AS admin,
  SUM(UPPER(TRIM(perfil))='ADMINISTRADOR') AS administrador,
  SUM(UPPER(TRIM(perfil))='PROFESOR') AS profesor,
  SUM(UPPER(TRIM(perfil))='TUTOR') AS tutor
FROM tbl_profesor
");

section('perfiles_vacios_detalle');
rows($db, "
SELECT id_profesor, correo, perfil
FROM tbl_profesor
WHERE perfil IS NULL OR TRIM(perfil) = ''
ORDER BY id_profesor
");

section('admin_accounts');
rows($db, "
SELECT id_profesor, correo, perfil, CASE WHEN $bcryptExpr THEN 'BCRYPT' ELSE 'LEGACY' END AS pass_class
FROM tbl_profesor
WHERE UPPER(TRIM(perfil)) IN ('ADMIN','ADMINISTRADOR')
ORDER BY id_profesor
");

section('group_integrity_issues');
one($db, "
SELECT
  (SELECT COUNT(*) FROM tbl_grupo WHERE id_tutor = 0) AS grupos_tutor_0,
  (SELECT COUNT(*) FROM tbl_grupo g LEFT JOIN tbl_profesor p ON p.id_profesor = g.id_tutor WHERE p.id_profesor IS NULL) AS grupos_tutor_huerfano,
  (SELECT COUNT(*) FROM tbl_grupo_profesor WHERE id_grupo = 0) AS asig_id_grupo_0,
  (SELECT COUNT(*) FROM tbl_grupo_profesor gp LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo WHERE g.id_grupo IS NULL) AS asig_grupo_huerfano,
  (SELECT COUNT(*) FROM tbl_grupo_profesor gp LEFT JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor WHERE p.id_profesor IS NULL) AS asig_profesor_huerfano
");

section('group_rows_tutor_huerfano');
rows($db, "
SELECT g.id_grupo, g.nombre, g.id_tutor
FROM tbl_grupo g
LEFT JOIN tbl_profesor p ON p.id_profesor = g.id_tutor
WHERE p.id_profesor IS NULL
ORDER BY g.id_grupo
");

section('group_assignment_orphans');
rows($db, "
SELECT gp.id, gp.id_grupo, gp.id_profesor,
       CASE WHEN g.id_grupo IS NULL THEN 1 ELSE 0 END AS grupo_huerfano,
       CASE WHEN p.id_profesor IS NULL THEN 1 ELSE 0 END AS profesor_huerfano
FROM tbl_grupo_profesor gp
LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo
LEFT JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
WHERE gp.id_grupo = 0 OR g.id_grupo IS NULL OR p.id_profesor IS NULL
ORDER BY gp.id
");

section('rama_distribution');
rows($db, "
SELECT rama, COUNT(*) AS total
FROM tbl_profesor
GROUP BY rama
ORDER BY total DESC, rama
");

section('rama_non_standard_for_frontend_target');
rows($db, "
SELECT id_profesor, correo, rama
FROM tbl_profesor
WHERE rama IN ('TECNICA','S Y J') OR rama = ''
ORDER BY rama, correo, id_profesor
");
