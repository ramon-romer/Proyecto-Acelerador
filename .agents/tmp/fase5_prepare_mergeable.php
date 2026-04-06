<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Europe/Madrid');
$dbName = 'acelerador_staging_20260406';
$phase5Run = 'F5_PREP_' . date('Ymd_His');

$db = new mysqli('localhost', 'root', '', $dbName);
$db->set_charset('utf8mb4');

$currentDb = $db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? null;
if ($currentDb !== $dbName) {
    throw new RuntimeException('Guard de seguridad: BD activa inesperada');
}

$sourceRun = $db->query("SELECT run_id FROM audit_log WHERE run_id LIKE 'F4C_EXEC_%' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['run_id'] ?? null;
if (!$sourceRun) {
    throw new RuntimeException('No se encontró run F4C_EXEC para construir subset mergeable.');
}

function execSql(mysqli $db, string $sql): int {
    $db->query($sql);
    return $db->affected_rows;
}

function logAction(mysqli $db, string $runId, string $step, string $action, ?string $targetTable, int $affectedRows, array $details = []): void {
    $stmt = $db->prepare('INSERT INTO audit_log (run_id, action_step, action_name, target_table, affected_rows, details_json) VALUES (?, ?, ?, ?, ?, ?)');
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->bind_param('ssssis', $runId, $step, $action, $targetTable, $affectedRows, $detailsJson);
    $stmt->execute();
    $stmt->close();
}

$ddl = [
"CREATE TABLE IF NOT EXISTS mergeable_runs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase5_run_id VARCHAR(64) NOT NULL,
  source_f4c_run_id VARCHAR(64) NOT NULL,
  criteria_version VARCHAR(32) NOT NULL,
  criteria_json LONGTEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mergeable_runs (phase5_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS mergeable_tbl_profesor (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase5_run_id VARCHAR(64) NOT NULL,
  source_f4c_run_id VARCHAR(64) NOT NULL,
  id_profesor INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  apellidos VARCHAR(200) NOT NULL,
  password VARCHAR(255) NOT NULL,
  DNI VARCHAR(9) NOT NULL,
  ORCID VARCHAR(19) NOT NULL,
  telefono INT(9) NOT NULL,
  perfil VARCHAR(50) NOT NULL,
  facultad VARCHAR(100) NOT NULL,
  departamento VARCHAR(100) NOT NULL,
  correo VARCHAR(255) NOT NULL,
  rama_original VARCHAR(64) NOT NULL,
  rama_normalizada VARCHAR(64) NOT NULL,
  canonical_status VARCHAR(32) NOT NULL,
  password_class VARCHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mergeable_profesor_run_id (phase5_run_id, id_profesor),
  KEY idx_mergeable_profesor_run_correo (phase5_run_id, correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS mergeable_tbl_usuario (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase5_run_id VARCHAR(64) NOT NULL,
  source_f4c_run_id VARCHAR(64) NOT NULL,
  id_usuario INT NOT NULL,
  correo VARCHAR(255) NOT NULL,
  password VARCHAR(255) NOT NULL,
  password_class VARCHAR(16) NOT NULL,
  linked_id_profesor INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mergeable_usuario_run_id (phase5_run_id, id_usuario),
  KEY idx_mergeable_usuario_run_correo (phase5_run_id, correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS mergeable_tbl_grupo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase5_run_id VARCHAR(64) NOT NULL,
  source_f4c_run_id VARCHAR(64) NOT NULL,
  id_grupo INT NOT NULL,
  nombre VARCHAR(255) NOT NULL,
  id_tutor INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mergeable_grupo_run_id (phase5_run_id, id_grupo),
  KEY idx_mergeable_grupo_run_tutor (phase5_run_id, id_tutor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS mergeable_tbl_grupo_profesor (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase5_run_id VARCHAR(64) NOT NULL,
  source_f4c_run_id VARCHAR(64) NOT NULL,
  source_id INT NOT NULL,
  id_grupo INT NOT NULL,
  id_profesor INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mergeable_gp_run_id (phase5_run_id, source_id),
  KEY idx_mergeable_gp_run_grupo (phase5_run_id, id_grupo),
  KEY idx_mergeable_gp_run_prof (phase5_run_id, id_profesor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS mergeable_exclusions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phase5_run_id VARCHAR(64) NOT NULL,
  source_f4c_run_id VARCHAR(64) NOT NULL,
  source_table ENUM('tbl_usuario','tbl_profesor','tbl_grupo','tbl_grupo_profesor') NOT NULL,
  source_pk INT NOT NULL,
  correo VARCHAR(255) NULL,
  id_grupo INT NULL,
  id_profesor INT NULL,
  reason_code VARCHAR(80) NOT NULL,
  reason_detail TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mergeable_exclusions_item_reason (phase5_run_id, source_table, source_pk, reason_code),
  KEY idx_mergeable_exclusions_run_table (phase5_run_id, source_table),
  KEY idx_mergeable_exclusions_reason (reason_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($ddl as $sql) {
    execSql($db, $sql);
}

$criteria = [
  'criteria_version' => 'v1_strict',
  'profesor_include' => [
      'correo reconciliado con tbl_usuario',
      'canonical_status en UNIQUE/CANONICAL_PROVISIONAL',
      'perfil_state VALID (no vacío)',
      'sin cuarentena activa incompatible: DUPLICADO_NO_CANONICO, DUPLICADO_REVISION_MANUAL, PERFIL_VACIO, PROFESOR_SIN_USUARIO, PASSWORD_MIGRATION_CANDIDATE',
      'rama sin pendiente manual (audit_rama_normalization.needs_manual_review=0)',
      'password_class BCRYPT'
  ],
  'usuario_include' => [
      'correo enlazado a profesor mergeable',
      'sin cuarentena activa incompatible: USUARIO_SIN_PROFESOR, PASSWORD_MIGRATION_CANDIDATE',
      'password_class BCRYPT'
  ],
  'grupo_include' => [
      'sin cuarentena relacional activa en quarantine_groups para tbl_grupo',
      'id_tutor existente en profesores mergeables',
      'id_tutor con perfil TUTOR en mergeable_tbl_profesor (evita ambigüedad de rol)'
  ],
  'grupo_profesor_include' => [
      'sin cuarentena relacional activa en quarantine_groups para tbl_grupo_profesor',
      'id_grupo en mergeable_tbl_grupo',
      'id_profesor en mergeable_tbl_profesor'
  ]
];

$stmt = $db->prepare('INSERT INTO mergeable_runs (phase5_run_id, source_f4c_run_id, criteria_version, criteria_json) VALUES (?, ?, ?, ?)');
$criteriaVersion = 'v1_strict';
$criteriaJson = json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$stmt->bind_param('ssss', $phase5Run, $sourceRun, $criteriaVersion, $criteriaJson);
$stmt->execute();
$stmt->close();
logAction($db, $phase5Run, 'FASE_5', 'CREATE_MERGEABLE_RUN', 'mergeable_runs', 1, ['source_f4c_run_id' => $sourceRun]);

// Temporary tables for deterministic selection
execSql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_mergeable_profesor');
execSql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_mergeable_usuario');
execSql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_mergeable_grupo');
execSql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_mergeable_gp');

execSql($db, "
CREATE TEMPORARY TABLE tmp_mergeable_profesor AS
SELECT p.id_profesor, p.correo
FROM tbl_profesor p
INNER JOIN audit_accounts a
  ON a.run_id = '{$sourceRun}'
 AND a.source_table = 'tbl_profesor'
 AND a.source_pk = p.id_profesor
INNER JOIN audit_rama_normalization rn
  ON rn.run_id = '{$sourceRun}'
 AND rn.id_profesor = p.id_profesor
WHERE a.in_tbl_usuario = 1
  AND a.perfil_state = 'VALID'
  AND a.password_class = 'BCRYPT'
  AND a.canonical_status IN ('UNIQUE', 'CANONICAL_PROVISIONAL')
  AND rn.needs_manual_review = 0
  AND NOT EXISTS (
      SELECT 1
      FROM quarantine_accounts q
      WHERE q.run_id = '{$sourceRun}'
        AND q.source_table = 'tbl_profesor'
        AND q.source_pk = p.id_profesor
        AND q.status = 'ACTIVE'
        AND q.quarantine_type IN (
          'DUPLICADO_NO_CANONICO',
          'DUPLICADO_REVISION_MANUAL',
          'PERFIL_VACIO',
          'PROFESOR_SIN_USUARIO',
          'PASSWORD_MIGRATION_CANDIDATE'
        )
  )
");

$affected = execSql($db, "
INSERT INTO mergeable_tbl_profesor (
  phase5_run_id, source_f4c_run_id, id_profesor, nombre, apellidos, password, DNI, ORCID, telefono,
  perfil, facultad, departamento, correo, rama_original, rama_normalizada, canonical_status, password_class
)
SELECT
  '{$phase5Run}',
  '{$sourceRun}',
  p.id_profesor, p.nombre, p.apellidos, p.password, p.DNI, p.ORCID, p.telefono,
  p.perfil, p.facultad, p.departamento, p.correo, p.rama,
  rn.rama_normalizada,
  a.canonical_status,
  a.password_class
FROM tbl_profesor p
INNER JOIN tmp_mergeable_profesor mp ON mp.id_profesor = p.id_profesor
INNER JOIN audit_accounts a
  ON a.run_id = '{$sourceRun}'
 AND a.source_table = 'tbl_profesor'
 AND a.source_pk = p.id_profesor
INNER JOIN audit_rama_normalization rn
  ON rn.run_id = '{$sourceRun}'
 AND rn.id_profesor = p.id_profesor
");
logAction($db, $phase5Run, 'FASE_5C', 'MATERIALIZE_MERGEABLE_PROFESOR', 'mergeable_tbl_profesor', $affected, []);

execSql($db, "
CREATE TEMPORARY TABLE tmp_mergeable_usuario AS
SELECT u.id_usuario, u.correo
FROM tbl_usuario u
INNER JOIN audit_accounts a
  ON a.run_id = '{$sourceRun}'
 AND a.source_table = 'tbl_usuario'
 AND a.source_pk = u.id_usuario
INNER JOIN tmp_mergeable_profesor mp
  ON mp.correo = u.correo
WHERE a.password_class = 'BCRYPT'
  AND NOT EXISTS (
      SELECT 1
      FROM quarantine_accounts q
      WHERE q.run_id = '{$sourceRun}'
        AND q.source_table = 'tbl_usuario'
        AND q.source_pk = u.id_usuario
        AND q.status = 'ACTIVE'
        AND q.quarantine_type IN ('USUARIO_SIN_PROFESOR', 'PASSWORD_MIGRATION_CANDIDATE')
  )
");

$affected = execSql($db, "
INSERT INTO mergeable_tbl_usuario (
  phase5_run_id, source_f4c_run_id, id_usuario, correo, password, password_class, linked_id_profesor
)
SELECT
  '{$phase5Run}',
  '{$sourceRun}',
  u.id_usuario,
  u.correo,
  u.password,
  a.password_class,
  mp.id_profesor
FROM tbl_usuario u
INNER JOIN tmp_mergeable_usuario mu ON mu.id_usuario = u.id_usuario
INNER JOIN tmp_mergeable_profesor mp ON mp.correo = u.correo
INNER JOIN audit_accounts a
  ON a.run_id = '{$sourceRun}'
 AND a.source_table = 'tbl_usuario'
 AND a.source_pk = u.id_usuario
");
logAction($db, $phase5Run, 'FASE_5C', 'MATERIALIZE_MERGEABLE_USUARIO', 'mergeable_tbl_usuario', $affected, []);

execSql($db, "
CREATE TEMPORARY TABLE tmp_mergeable_grupo AS
SELECT g.id_grupo, g.id_tutor
FROM tbl_grupo g
INNER JOIN tmp_mergeable_profesor mp ON mp.id_profesor = g.id_tutor
INNER JOIN mergeable_tbl_profesor mprof
  ON mprof.phase5_run_id = '{$phase5Run}'
 AND mprof.id_profesor = g.id_tutor
WHERE mprof.perfil = 'TUTOR'
  AND NOT EXISTS (
      SELECT 1
      FROM quarantine_groups qg
      WHERE qg.run_id = '{$sourceRun}'
        AND qg.source_table = 'tbl_grupo'
        AND qg.source_pk = g.id_grupo
        AND qg.status = 'ACTIVE'
  )
");

$affected = execSql($db, "
INSERT INTO mergeable_tbl_grupo (
  phase5_run_id, source_f4c_run_id, id_grupo, nombre, id_tutor
)
SELECT
  '{$phase5Run}',
  '{$sourceRun}',
  g.id_grupo, g.nombre, g.id_tutor
FROM tbl_grupo g
INNER JOIN tmp_mergeable_grupo mg ON mg.id_grupo = g.id_grupo
");
logAction($db, $phase5Run, 'FASE_5C', 'MATERIALIZE_MERGEABLE_GRUPO', 'mergeable_tbl_grupo', $affected, []);

execSql($db, "
CREATE TEMPORARY TABLE tmp_mergeable_gp AS
SELECT gp.id, gp.id_grupo, gp.id_profesor
FROM tbl_grupo_profesor gp
INNER JOIN tmp_mergeable_grupo mg ON mg.id_grupo = gp.id_grupo
INNER JOIN tmp_mergeable_profesor mp ON mp.id_profesor = gp.id_profesor
WHERE NOT EXISTS (
    SELECT 1
    FROM quarantine_groups qg
    WHERE qg.run_id = '{$sourceRun}'
      AND qg.source_table = 'tbl_grupo_profesor'
      AND qg.source_pk = gp.id
      AND qg.status = 'ACTIVE'
)
");

$affected = execSql($db, "
INSERT INTO mergeable_tbl_grupo_profesor (
  phase5_run_id, source_f4c_run_id, source_id, id_grupo, id_profesor
)
SELECT
  '{$phase5Run}',
  '{$sourceRun}',
  gp.id,
  gp.id_grupo,
  gp.id_profesor
FROM tmp_mergeable_gp gp
");
logAction($db, $phase5Run, 'FASE_5C', 'MATERIALIZE_MERGEABLE_GRUPO_PROFESOR', 'mergeable_tbl_grupo_profesor', $affected, []);

// Exclusions tbl_profesor
execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
       'DUPLICADO_NO_CANONICO', 'Duplicado no canónico'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_profesor'
  AND a.canonical_status = 'NON_CANONICAL'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
       'MANUAL_REVIEW', 'Registro en revisión manual sin decisión cerrada'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_profesor'
  AND a.canonical_status = 'MANUAL_REVIEW'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
       'PERFIL_VACIO', 'Perfil vacío'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_profesor'
  AND a.perfil_state = 'EMPTY'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
       'PROFESOR_SIN_USUARIO', 'Profesor sin usuario reconciliado'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_profesor'
  AND a.in_tbl_usuario = 0
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
       'PASSWORD_NO_BCRYPT', 'Password no bcrypt (candidata a migración)'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_profesor'
  AND a.password_class <> 'BCRYPT'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', p.id_profesor, p.correo, p.id_profesor,
       'RAMA_PENDIENTE', 'Rama pendiente de revisión manual'
FROM tbl_profesor p
INNER JOIN audit_rama_normalization rn
  ON rn.run_id = '{$sourceRun}'
 AND rn.id_profesor = p.id_profesor
WHERE rn.needs_manual_review = 1
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_profesor', p.id_profesor, p.correo, p.id_profesor,
       'NO_ENTRA_EN_SUBSET', 'No cumple criterios completos de inclusión mergeable'
FROM tbl_profesor p
LEFT JOIN tmp_mergeable_profesor mp ON mp.id_profesor = p.id_profesor
WHERE mp.id_profesor IS NULL
");

// Exclusions tbl_usuario
execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_usuario', a.source_pk, a.correo,
       'USUARIO_SIN_PROFESOR', 'Usuario sin profesor reconciliado'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_usuario'
  AND a.in_tbl_profesor = 0
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_usuario', a.source_pk, a.correo,
       'PASSWORD_NO_BCRYPT', 'Password no bcrypt (candidata a migración)'
FROM audit_accounts a
WHERE a.run_id = '{$sourceRun}'
  AND a.source_table = 'tbl_usuario'
  AND a.password_class <> 'BCRYPT'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_usuario', u.id_usuario, u.correo,
       'PROFESOR_NO_MERGEABLE', 'Existe profesor asociado pero no entra en subset mergeable'
FROM tbl_usuario u
WHERE EXISTS (SELECT 1 FROM tbl_profesor p WHERE p.correo = u.correo)
  AND NOT EXISTS (SELECT 1 FROM tmp_mergeable_profesor mp WHERE mp.correo = u.correo)
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_usuario', u.id_usuario, u.correo,
       'NO_ENTRA_EN_SUBSET', 'No cumple criterios completos de inclusión mergeable'
FROM tbl_usuario u
LEFT JOIN tmp_mergeable_usuario mu ON mu.id_usuario = u.id_usuario
WHERE mu.id_usuario IS NULL
");

// Exclusions tbl_grupo
execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo', g.id_grupo, g.id_grupo, g.id_tutor,
       'GRUPO_TUTOR_HUERFANO', 'Grupo con id_tutor huérfano en staging'
FROM tbl_grupo g
LEFT JOIN tbl_profesor p ON p.id_profesor = g.id_tutor
WHERE p.id_profesor IS NULL
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo', g.id_grupo, g.id_grupo, g.id_tutor,
       'TUTOR_NO_MERGEABLE', 'Tutor de grupo no entra en profesores mergeables'
FROM tbl_grupo g
LEFT JOIN tmp_mergeable_profesor mp ON mp.id_profesor = g.id_tutor
WHERE mp.id_profesor IS NULL
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo', g.id_grupo, g.id_grupo, g.id_tutor,
       'TUTOR_ROL_NO_TUTOR', 'id_tutor existe pero su perfil mergeable no es TUTOR'
FROM tbl_grupo g
INNER JOIN mergeable_tbl_profesor mprof
  ON mprof.phase5_run_id = '{$phase5Run}'
 AND mprof.id_profesor = g.id_tutor
WHERE mprof.perfil <> 'TUTOR'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo', qg.source_pk, qg.id_grupo, qg.id_tutor,
       qg.quarantine_type, qg.reason
FROM quarantine_groups qg
WHERE qg.run_id = '{$sourceRun}'
  AND qg.source_table = 'tbl_grupo'
  AND qg.status = 'ACTIVE'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo', g.id_grupo, g.id_grupo, g.id_tutor,
       'NO_ENTRA_EN_SUBSET', 'No cumple criterios completos de inclusión mergeable'
FROM tbl_grupo g
LEFT JOIN tmp_mergeable_grupo mg ON mg.id_grupo = g.id_grupo
WHERE mg.id_grupo IS NULL
");

// Exclusions tbl_grupo_profesor
execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo_profesor', gp.id, gp.id_grupo, gp.id_profesor,
       'GRUPO_NO_MERGEABLE', 'Asignación apunta a grupo no mergeable'
FROM tbl_grupo_profesor gp
LEFT JOIN tmp_mergeable_grupo mg ON mg.id_grupo = gp.id_grupo
WHERE mg.id_grupo IS NULL
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo_profesor', gp.id, gp.id_grupo, gp.id_profesor,
       'PROFESOR_NO_MERGEABLE', 'Asignación apunta a profesor no mergeable'
FROM tbl_grupo_profesor gp
LEFT JOIN tmp_mergeable_profesor mp ON mp.id_profesor = gp.id_profesor
WHERE mp.id_profesor IS NULL
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo_profesor', qg.source_pk, qg.id_grupo, qg.id_profesor,
       qg.quarantine_type, qg.reason
FROM quarantine_groups qg
WHERE qg.run_id = '{$sourceRun}'
  AND qg.source_table = 'tbl_grupo_profesor'
  AND qg.status = 'ACTIVE'
");

execSql($db, "
INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
SELECT '{$phase5Run}', '{$sourceRun}', 'tbl_grupo_profesor', gp.id, gp.id_grupo, gp.id_profesor,
       'NO_ENTRA_EN_SUBSET', 'No cumple criterios completos de inclusión mergeable'
FROM tbl_grupo_profesor gp
LEFT JOIN tmp_mergeable_gp mgp ON mgp.id = gp.id
WHERE mgp.id IS NULL
");

logAction($db, $phase5Run, 'FASE_5B', 'MATERIALIZE_EXCLUSIONS', 'mergeable_exclusions', $db->affected_rows, []);

$summary = [
    'phase5_run_id' => $phase5Run,
    'source_f4c_run_id' => $sourceRun,
    'counts' => [
        'mergeable_tbl_usuario' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_usuario WHERE phase5_run_id = '{$phase5Run}'")->fetch_assoc()['c'],
        'mergeable_tbl_profesor' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_profesor WHERE phase5_run_id = '{$phase5Run}'")->fetch_assoc()['c'],
        'mergeable_tbl_grupo' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_grupo WHERE phase5_run_id = '{$phase5Run}'")->fetch_assoc()['c'],
        'mergeable_tbl_grupo_profesor' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id = '{$phase5Run}'")->fetch_assoc()['c'],
        'mergeable_exclusions' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_exclusions WHERE phase5_run_id = '{$phase5Run}'")->fetch_assoc()['c'],
    ],
];

$outFile = __DIR__ . DIRECTORY_SEPARATOR . "fase5_prepare_result_{$phase5Run}.json";
file_put_contents($outFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode([
    'ok' => true,
    'phase5_run_id' => $phase5Run,
    'source_f4c_run_id' => $sourceRun,
    'result_file' => $outFile,
    'counts' => $summary['counts']
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
