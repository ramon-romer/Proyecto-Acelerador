<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Europe/Madrid');

$targetDb = 'acelerador_staging_20260406';
$datasetRun = 'F6_DATASET_' . date('Ymd_His');
$mergeableRun = 'F5V2_PREP_' . date('Ymd_His');

$db = new mysqli('localhost', 'root', '', $targetDb);
$db->set_charset('utf8mb4');

$currentDb = $db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? null;
if ($currentDb !== $targetDb) {
    throw new RuntimeException('Guard DB failed. Current DB: ' . (string)$currentDb);
}

$sourceF4 = $db->query("SELECT run_id FROM audit_log WHERE run_id LIKE 'F4C_EXEC_%' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['run_id'] ?? null;
if (!$sourceF4) {
    throw new RuntimeException('No source F4 run found.');
}

function exec_sql(mysqli $db, string $sql): int
{
    $db->query($sql);
    return $db->affected_rows;
}

function log_audit(mysqli $db, string $runId, string $step, string $action, ?string $targetTable, int $affectedRows, array $details = []): void
{
    $stmt = $db->prepare('INSERT INTO audit_log (run_id, action_step, action_name, target_table, affected_rows, details_json) VALUES (?, ?, ?, ?, ?, ?)');
    $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->bind_param('ssssis', $runId, $step, $action, $targetTable, $affectedRows, $json);
    $stmt->execute();
    $stmt->close();
}

exec_sql($db, "
CREATE TABLE IF NOT EXISTS dataset_resolution_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_run_id VARCHAR(64) NOT NULL,
    source_f4_run_id VARCHAR(64) NOT NULL,
    source_table VARCHAR(64) NOT NULL,
    source_pk INT NULL,
    correo VARCHAR(255) NULL,
    action_code VARCHAR(80) NOT NULL,
    status VARCHAR(32) NOT NULL,
    details_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dataset_resolution_item (dataset_run_id, source_table, source_pk, action_code),
    KEY idx_dataset_resolution_run (dataset_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$db->begin_transaction();

try {
    // 1) Fixture admin staging only
    $fixtureEmail = strtolower('fixture.admin.' . date('YmdHis') . '@staging.local');
    $fixturePassPlain = 'FixtureAdmin#2026!';
    $fixturePassHash = password_hash($fixturePassPlain, PASSWORD_BCRYPT);
    if ($fixturePassHash === false) {
        throw new RuntimeException('Could not hash fixture password.');
    }

    $stmtU = $db->prepare('INSERT INTO tbl_usuario (correo, password) VALUES (?, ?)');
    $stmtU->bind_param('ss', $fixtureEmail, $fixturePassHash);
    $stmtU->execute();
    $fixtureUserId = (int)$stmtU->insert_id;
    $stmtU->close();

    $fixtureOrcid = '9999-0000-0000-' . substr(date('His'), -4);
    $fixtureNombre = 'Fixture';
    $fixtureApellidos = 'Admin Staging';
    $fixtureDni = '9' . substr(date('YmdHis'), -7) . 'Z';
    $fixtureTelefono = 699000111;
    $fixturePerfil = 'ADMIN';
    $fixtureFacultad = 'STAGING';
    $fixtureDepartamento = 'QA';
    $fixtureRama = 'SALUD';

    $stmtP = $db->prepare('INSERT INTO tbl_profesor (ORCID, nombre, apellidos, password, DNI, telefono, perfil, facultad, departamento, correo, rama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmtP->bind_param(
        'sssssisssss',
        $fixtureOrcid,
        $fixtureNombre,
        $fixtureApellidos,
        $fixturePassHash,
        $fixtureDni,
        $fixtureTelefono,
        $fixturePerfil,
        $fixtureFacultad,
        $fixtureDepartamento,
        $fixtureEmail,
        $fixtureRama
    );
    $stmtP->execute();
    $fixtureProfesorId = (int)$stmtP->insert_id;
    $stmtP->close();

    // 2) mergeable v2 run metadata
    $criteria = [
        'criteria_version' => 'v2_auth_compatible_data_unblock',
        'source_f4_run_id' => $sourceF4,
        'rules' => [
            'lui_noncanonical' => 'cerrados tecnicamente por exclusion',
            'ads' => 'manual_review mantenido',
            'perfiles_vacios' => 'exclusion mantenida',
            'grupos_rotos' => 'exclusion formal mantenida',
            'auth' => 'allow bcrypt and legacy (canon tbl_usuario), fixture admin excluded',
        ],
    ];

    $stmtRun = $db->prepare('INSERT INTO mergeable_runs (phase5_run_id, source_f4c_run_id, criteria_version, criteria_json) VALUES (?, ?, ?, ?)');
    $criteriaVersion = 'v2_auth_compatible_data_unblock';
    $criteriaJson = json_encode($criteria, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmtRun->bind_param('ssss', $mergeableRun, $sourceF4, $criteriaVersion, $criteriaJson);
    $stmtRun->execute();
    $stmtRun->close();

    // 3) Resolution logs (traceability)
    exec_sql($db, "
    INSERT IGNORE INTO dataset_resolution_log (dataset_run_id, source_f4_run_id, source_table, source_pk, correo, action_code, status, details_json)
    SELECT '{$datasetRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo,
           CASE WHEN a.canonical_status='CANONICAL_PROVISIONAL' THEN 'KEEP_CANONICAL_LUI' ELSE 'EXCLUDE_NON_CANONICAL_LUI' END,
           CASE WHEN a.canonical_status='CANONICAL_PROVISIONAL' THEN 'ACTIVE' ELSE 'CLOSED_TECHNICAL' END,
           JSON_OBJECT('canonical_status', a.canonical_status)
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.correo = 'lui@gmail.com'
      AND a.canonical_status IN ('CANONICAL_PROVISIONAL','NON_CANONICAL')
    ");

    exec_sql($db, "
    INSERT IGNORE INTO dataset_resolution_log (dataset_run_id, source_f4_run_id, source_table, source_pk, correo, action_code, status, details_json)
    SELECT '{$datasetRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo,
           'KEEP_MANUAL_REVIEW_ADS',
           'PENDING_MANUAL',
           JSON_OBJECT('canonical_status', a.canonical_status, 'notes', a.notes)
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.correo = 'ads@gmail.com'
      AND a.canonical_status = 'MANUAL_REVIEW'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO dataset_resolution_log (dataset_run_id, source_f4_run_id, source_table, source_pk, correo, action_code, status, details_json)
    SELECT '{$datasetRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo,
           'KEEP_EXCLUDED_EMPTY_PROFILE',
           'ACTIVE',
           JSON_OBJECT('perfil_state', a.perfil_state)
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.perfil_state = 'EMPTY'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO dataset_resolution_log (dataset_run_id, source_f4_run_id, source_table, source_pk, correo, action_code, status, details_json)
    SELECT '{$datasetRun}', '{$sourceF4}', qg.source_table, qg.source_pk, NULL,
           'KEEP_EXCLUDED_RELATIONAL_BROKEN',
           'ACTIVE',
           JSON_OBJECT('quarantine_type', qg.quarantine_type, 'reason', qg.reason)
    FROM quarantine_groups qg
    WHERE qg.run_id = '{$sourceF4}'
      AND qg.status = 'ACTIVE'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO dataset_resolution_log (dataset_run_id, source_f4_run_id, source_table, source_pk, correo, action_code, status, details_json)
    VALUES
      ('{$datasetRun}', '{$sourceF4}', 'tbl_usuario', {$fixtureUserId}, '{$fixtureEmail}', 'FIXTURE_STAGING_ONLY', 'ACTIVE', JSON_OBJECT('scope','staging_only','role','ADMIN')),
      ('{$datasetRun}', '{$sourceF4}', 'tbl_profesor', {$fixtureProfesorId}, '{$fixtureEmail}', 'FIXTURE_STAGING_ONLY', 'ACTIVE', JSON_OBJECT('scope','staging_only','role','ADMIN'))
    ");

    // 4) Build temporary mergeable v2 selections
    exec_sql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_v2_profesor');
    exec_sql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_v2_usuario');
    exec_sql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_v2_grupo');
    exec_sql($db, 'DROP TEMPORARY TABLE IF EXISTS tmp_v2_gp');

    exec_sql($db, "
    CREATE TEMPORARY TABLE tmp_v2_profesor AS
    SELECT p.id_profesor, p.correo
    FROM tbl_profesor p
    INNER JOIN audit_accounts a
      ON a.run_id = '{$sourceF4}'
     AND a.source_table = 'tbl_profesor'
     AND a.source_pk = p.id_profesor
    INNER JOIN audit_rama_normalization rn
      ON rn.run_id = '{$sourceF4}'
     AND rn.id_profesor = p.id_profesor
    WHERE a.in_tbl_usuario = 1
      AND a.perfil_state = 'VALID'
      AND a.canonical_status IN ('UNIQUE','CANONICAL_PROVISIONAL')
      AND rn.needs_manual_review = 0
      AND NOT EXISTS (
            SELECT 1
            FROM dataset_resolution_log dr
            WHERE dr.dataset_run_id = '{$datasetRun}'
              AND dr.source_table = 'tbl_profesor'
              AND dr.source_pk = p.id_profesor
              AND dr.action_code = 'FIXTURE_STAGING_ONLY'
      )
    ");

    exec_sql($db, "
    CREATE TEMPORARY TABLE tmp_v2_usuario AS
    SELECT u.id_usuario, u.correo, mp.id_profesor
    FROM tbl_usuario u
    INNER JOIN tmp_v2_profesor mp ON mp.correo = u.correo
    WHERE (SELECT COUNT(*) FROM tbl_usuario ux WHERE ux.correo = u.correo) = 1
      AND NOT EXISTS (
            SELECT 1
            FROM dataset_resolution_log dr
            WHERE dr.dataset_run_id = '{$datasetRun}'
              AND dr.source_table = 'tbl_usuario'
              AND dr.source_pk = u.id_usuario
              AND dr.action_code = 'FIXTURE_STAGING_ONLY'
      )
    ");

    exec_sql($db, "
    CREATE TEMPORARY TABLE tmp_v2_grupo AS
    SELECT g.id_grupo, g.id_tutor
    FROM tbl_grupo g
    INNER JOIN tmp_v2_profesor mp ON mp.id_profesor = g.id_tutor
    INNER JOIN tbl_profesor tp ON tp.id_profesor = g.id_tutor
    WHERE UPPER(TRIM(tp.perfil)) = 'TUTOR'
      AND NOT EXISTS (
          SELECT 1
          FROM quarantine_groups qg
          WHERE qg.run_id = '{$sourceF4}'
            AND qg.source_table = 'tbl_grupo'
            AND qg.source_pk = g.id_grupo
            AND qg.status = 'ACTIVE'
      )
    ");

    exec_sql($db, "
    CREATE TEMPORARY TABLE tmp_v2_gp AS
    SELECT gp.id, gp.id_grupo, gp.id_profesor
    FROM tbl_grupo_profesor gp
    INNER JOIN tmp_v2_grupo mg ON mg.id_grupo = gp.id_grupo
    INNER JOIN tmp_v2_profesor mp ON mp.id_profesor = gp.id_profesor
    WHERE gp.id_grupo <> 0
      AND NOT EXISTS (
          SELECT 1
          FROM quarantine_groups qg
          WHERE qg.run_id = '{$sourceF4}'
            AND qg.source_table = 'tbl_grupo_profesor'
            AND qg.source_pk = gp.id
            AND qg.status = 'ACTIVE'
      )
    ");

    // 5) Materialize mergeable v2
    $aff = exec_sql($db, "
    INSERT INTO mergeable_tbl_profesor (
      phase5_run_id, source_f4c_run_id, id_profesor, nombre, apellidos, password, DNI, ORCID, telefono,
      perfil, facultad, departamento, correo, rama_original, rama_normalizada, canonical_status, password_class
    )
    SELECT
      '{$mergeableRun}', '{$sourceF4}', p.id_profesor, p.nombre, p.apellidos, p.password, p.DNI, p.ORCID, p.telefono,
      p.perfil, p.facultad, p.departamento, p.correo, p.rama,
      rn.rama_normalizada,
      a.canonical_status,
      a.password_class
    FROM tbl_profesor p
    INNER JOIN tmp_v2_profesor mp ON mp.id_profesor = p.id_profesor
    INNER JOIN audit_accounts a
      ON a.run_id = '{$sourceF4}'
     AND a.source_table = 'tbl_profesor'
     AND a.source_pk = p.id_profesor
    INNER JOIN audit_rama_normalization rn
      ON rn.run_id = '{$sourceF4}'
     AND rn.id_profesor = p.id_profesor
    ");
    log_audit($db, $datasetRun, 'FASE_EXEC', 'MATERIALIZE_V2_PROFESOR', 'mergeable_tbl_profesor', $aff, ['mergeable_run' => $mergeableRun]);

    $aff = exec_sql($db, "
    INSERT INTO mergeable_tbl_usuario (
      phase5_run_id, source_f4c_run_id, id_usuario, correo, password, password_class, linked_id_profesor
    )
    SELECT
      '{$mergeableRun}', '{$sourceF4}', u.id_usuario, u.correo, u.password,
      a.password_class,
      mu.id_profesor
    FROM tbl_usuario u
    INNER JOIN tmp_v2_usuario mu ON mu.id_usuario = u.id_usuario
    INNER JOIN audit_accounts a
      ON a.run_id = '{$sourceF4}'
     AND a.source_table = 'tbl_usuario'
     AND a.source_pk = u.id_usuario
    ");
    log_audit($db, $datasetRun, 'FASE_EXEC', 'MATERIALIZE_V2_USUARIO', 'mergeable_tbl_usuario', $aff, ['mergeable_run' => $mergeableRun]);

    $aff = exec_sql($db, "
    INSERT INTO mergeable_tbl_grupo (
      phase5_run_id, source_f4c_run_id, id_grupo, nombre, id_tutor
    )
    SELECT
      '{$mergeableRun}', '{$sourceF4}', g.id_grupo, g.nombre, g.id_tutor
    FROM tbl_grupo g
    INNER JOIN tmp_v2_grupo mg ON mg.id_grupo = g.id_grupo
    ");
    log_audit($db, $datasetRun, 'FASE_EXEC', 'MATERIALIZE_V2_GRUPO', 'mergeable_tbl_grupo', $aff, ['mergeable_run' => $mergeableRun]);

    $aff = exec_sql($db, "
    INSERT INTO mergeable_tbl_grupo_profesor (
      phase5_run_id, source_f4c_run_id, source_id, id_grupo, id_profesor
    )
    SELECT
      '{$mergeableRun}', '{$sourceF4}', gp.id, gp.id_grupo, gp.id_profesor
    FROM tmp_v2_gp gp
    ");
    log_audit($db, $datasetRun, 'FASE_EXEC', 'MATERIALIZE_V2_GRUPO_PROFESOR', 'mergeable_tbl_grupo_profesor', $aff, ['mergeable_run' => $mergeableRun]);

    // 6) Formal exclusions for this v2 run
    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
           'DUPLICADO_NO_CANONICO', 'Duplicado no canónico cerrado técnicamente por exclusión'
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.canonical_status = 'NON_CANONICAL'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
           'MANUAL_REVIEW', 'Caso en revisión manual pendiente (ads@gmail.com)'
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.canonical_status = 'MANUAL_REVIEW'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
           'PERFIL_VACIO', 'Perfil vacío mantenido fuera del subset'
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.perfil_state = 'EMPTY'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', a.source_pk, a.correo, a.source_pk,
           'PROFESOR_SIN_USUARIO', 'Profesor sin usuario reconciliado'
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_profesor'
      AND a.in_tbl_usuario = 0
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', rn.id_profesor, p.correo, rn.id_profesor,
           'RAMA_PENDIENTE', 'Rama pendiente de revisión manual'
    FROM audit_rama_normalization rn
    INNER JOIN tbl_profesor p ON p.id_profesor = rn.id_profesor
    WHERE rn.run_id = '{$sourceF4}'
      AND rn.needs_manual_review = 1
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    VALUES
      ('{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', {$fixtureProfesorId}, '{$fixtureEmail}', {$fixtureProfesorId}, 'FIXTURE_STAGING_ONLY', 'Fixture admin solo staging para pruebas')
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_usuario', a.source_pk, a.correo,
           'USUARIO_SIN_PROFESOR', 'Usuario sin profesor reconciliado'
    FROM audit_accounts a
    WHERE a.run_id = '{$sourceF4}'
      AND a.source_table = 'tbl_usuario'
      AND a.in_tbl_profesor = 0
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_usuario', u.id_usuario, u.correo,
           'PROFESOR_NO_MERGEABLE', 'Correo con profesor no incluido en subset v2'
    FROM tbl_usuario u
    WHERE EXISTS (SELECT 1 FROM tbl_profesor p WHERE p.correo = u.correo)
      AND NOT EXISTS (SELECT 1 FROM tmp_v2_profesor mp WHERE mp.correo = u.correo)
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
    VALUES
      ('{$mergeableRun}', '{$sourceF4}', 'tbl_usuario', {$fixtureUserId}, '{$fixtureEmail}', 'FIXTURE_STAGING_ONLY', 'Fixture admin solo staging para pruebas')
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo', qg.source_pk, qg.id_grupo, qg.id_tutor,
           qg.quarantine_type, qg.reason
    FROM quarantine_groups qg
    WHERE qg.run_id = '{$sourceF4}'
      AND qg.status='ACTIVE'
      AND qg.source_table='tbl_grupo'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo', g.id_grupo, g.id_grupo, g.id_tutor,
           'TUTOR_ROL_NO_TUTOR', 'Tutor del grupo no tiene perfil TUTOR'
    FROM tbl_grupo g
    INNER JOIN tbl_profesor p ON p.id_profesor = g.id_tutor
    WHERE UPPER(TRIM(p.perfil)) <> 'TUTOR'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo_profesor', qg.source_pk, qg.id_grupo, qg.id_profesor,
           qg.quarantine_type, qg.reason
    FROM quarantine_groups qg
    WHERE qg.run_id = '{$sourceF4}'
      AND qg.status='ACTIVE'
      AND qg.source_table='tbl_grupo_profesor'
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo_profesor', gp.id, gp.id_grupo, gp.id_profesor,
           'GRUPO_NO_MERGEABLE', 'Asignación con grupo no mergeable'
    FROM tbl_grupo_profesor gp
    LEFT JOIN tmp_v2_grupo mg ON mg.id_grupo = gp.id_grupo
    WHERE mg.id_grupo IS NULL
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo_profesor', gp.id, gp.id_grupo, gp.id_profesor,
           'PROFESOR_NO_MERGEABLE', 'Asignación con profesor no mergeable'
    FROM tbl_grupo_profesor gp
    LEFT JOIN tmp_v2_profesor mp ON mp.id_profesor = gp.id_profesor
    WHERE mp.id_profesor IS NULL
    ");

    // NO_ENTRA_EN_SUBSET formal for all tables
    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_profesor', p.id_profesor, p.correo, p.id_profesor,
           'NO_ENTRA_EN_SUBSET', 'No cumple criterios de inclusion v2'
    FROM tbl_profesor p
    LEFT JOIN tmp_v2_profesor mp ON mp.id_profesor = p.id_profesor
    WHERE mp.id_profesor IS NULL
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, correo, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_usuario', u.id_usuario, u.correo,
           'NO_ENTRA_EN_SUBSET', 'No cumple criterios de inclusion v2'
    FROM tbl_usuario u
    LEFT JOIN tmp_v2_usuario mu ON mu.id_usuario = u.id_usuario
    WHERE mu.id_usuario IS NULL
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo', g.id_grupo, g.id_grupo, g.id_tutor,
           'NO_ENTRA_EN_SUBSET', 'No cumple criterios de inclusion v2'
    FROM tbl_grupo g
    LEFT JOIN tmp_v2_grupo mg ON mg.id_grupo = g.id_grupo
    WHERE mg.id_grupo IS NULL
    ");

    exec_sql($db, "
    INSERT IGNORE INTO mergeable_exclusions (phase5_run_id, source_f4c_run_id, source_table, source_pk, id_grupo, id_profesor, reason_code, reason_detail)
    SELECT '{$mergeableRun}', '{$sourceF4}', 'tbl_grupo_profesor', gp.id, gp.id_grupo, gp.id_profesor,
           'NO_ENTRA_EN_SUBSET', 'No cumple criterios de inclusion v2'
    FROM tbl_grupo_profesor gp
    LEFT JOIN tmp_v2_gp mgp ON mgp.id = gp.id
    WHERE mgp.id IS NULL
    ");

    // Summary counts
    $counts = [
        'usuarios' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_usuario WHERE phase5_run_id='{$mergeableRun}'")->fetch_assoc()['c'],
        'profesores' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_profesor WHERE phase5_run_id='{$mergeableRun}'")->fetch_assoc()['c'],
        'grupos' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_grupo WHERE phase5_run_id='{$mergeableRun}'")->fetch_assoc()['c'],
        'asignaciones' => (int)$db->query("SELECT COUNT(*) c FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id='{$mergeableRun}'")->fetch_assoc()['c'],
    ];

    log_audit($db, $datasetRun, 'FASE_EXEC', 'SUMMARY_COUNTS_V2', null, array_sum($counts), $counts);

    $db->commit();

    echo json_encode([
        'ok' => true,
        'dataset_run_id' => $datasetRun,
        'mergeable_v2_run_id' => $mergeableRun,
        'source_f4_run_id' => $sourceF4,
        'fixture' => [
            'email' => $fixtureEmail,
            'id_usuario' => $fixtureUserId,
            'id_profesor' => $fixtureProfesorId,
        ],
        'counts' => $counts,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
?>
