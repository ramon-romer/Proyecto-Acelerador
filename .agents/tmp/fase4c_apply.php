<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Europe/Madrid');
$targetDb = 'acelerador_staging_20260406';
$runId = 'F4C_EXEC_' . date('Ymd_His');

$db = new mysqli('localhost', 'root', '', $targetDb);
$db->set_charset('utf8mb4');

$currentDbRes = $db->query('SELECT DATABASE() AS db');
$currentDb = $currentDbRes->fetch_assoc()['db'] ?? null;
if ($currentDb !== $targetDb) {
    throw new RuntimeException('Guard de seguridad: BD activa inesperada: ' . (string)$currentDb);
}

$backupDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'db';
$metaFiles = glob($backupDir . DIRECTORY_SEPARATOR . 'snapshot_' . $targetDb . '_*.meta.json') ?: [];
rsort($metaFiles);
$snapshotMeta = null;
if (!empty($metaFiles)) {
    $snapshotMeta = json_decode((string)file_get_contents($metaFiles[0]), true);
}

function j(array $data): string {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function logAction(mysqli $db, string $runId, string $step, string $action, ?string $targetTable, int $affectedRows, array $details = []): void {
    $sql = 'INSERT INTO audit_log (run_id, action_step, action_name, target_table, affected_rows, details_json) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $db->prepare($sql);
    $detailsJson = j($details);
    $stmt->bind_param('ssssis', $runId, $step, $action, $targetTable, $affectedRows, $detailsJson);
    $stmt->execute();
    $stmt->close();
}

function execSql(mysqli $db, string $sql): int {
    $db->query($sql);
    return $db->affected_rows;
}

function scalarInt(mysqli $db, string $sql): int {
    $res = $db->query($sql);
    $row = $res->fetch_row();
    return isset($row[0]) ? (int)$row[0] : 0;
}

$results = [
    'run_id' => $runId,
    'database' => $targetDb,
    'snapshot_meta' => $snapshotMeta,
    'actions' => [],
];

// PASO 2: tablas auxiliares
$ddlStatements = [
    "CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id VARCHAR(64) NOT NULL,
        action_step VARCHAR(64) NOT NULL,
        action_name VARCHAR(128) NOT NULL,
        target_table VARCHAR(128) NULL,
        affected_rows INT NOT NULL DEFAULT 0,
        details_json LONGTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_audit_log_run (run_id),
        KEY idx_audit_log_step (action_step)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS audit_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id VARCHAR(64) NOT NULL,
        source_table ENUM('tbl_profesor','tbl_usuario') NOT NULL,
        source_pk INT NOT NULL,
        correo VARCHAR(255) NOT NULL,
        id_profesor INT NULL,
        id_usuario INT NULL,
        perfil_raw VARCHAR(50) NULL,
        perfil_state ENUM('VALID','EMPTY','INVALID','N_A') NOT NULL,
        password_class ENUM('BCRYPT','LEGACY','VACIA','OTRA') NOT NULL,
        password_len INT NULL,
        in_tbl_profesor TINYINT(1) NOT NULL DEFAULT 0,
        in_tbl_usuario TINYINT(1) NOT NULL DEFAULT 0,
        duplicate_count_profesor INT NOT NULL DEFAULT 0,
        canonical_rank INT NULL,
        canonical_status ENUM('UNIQUE','CANONICAL_PROVISIONAL','NON_CANONICAL','MANUAL_REVIEW','N_A') NOT NULL DEFAULT 'N_A',
        migration_candidate TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_audit_accounts_run_source (run_id, source_table, source_pk),
        KEY idx_audit_accounts_run_correo (run_id, correo),
        KEY idx_audit_accounts_run_status (run_id, canonical_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS quarantine_accounts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id VARCHAR(64) NOT NULL,
        source_table ENUM('tbl_profesor','tbl_usuario') NOT NULL,
        source_pk INT NOT NULL,
        correo VARCHAR(255) NOT NULL,
        quarantine_type VARCHAR(80) NOT NULL,
        severity ENUM('HIGH','MEDIUM','LOW') NOT NULL DEFAULT 'MEDIUM',
        reason TEXT NOT NULL,
        status ENUM('ACTIVE','RESOLVED') NOT NULL DEFAULT 'ACTIVE',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_quarantine_accounts_run_item (run_id, source_table, source_pk, quarantine_type),
        KEY idx_quarantine_accounts_run (run_id),
        KEY idx_quarantine_accounts_type (quarantine_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS quarantine_groups (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id VARCHAR(64) NOT NULL,
        source_table ENUM('tbl_grupo','tbl_grupo_profesor') NOT NULL,
        source_pk INT NOT NULL,
        id_grupo INT NULL,
        id_tutor INT NULL,
        id_profesor INT NULL,
        quarantine_type VARCHAR(80) NOT NULL,
        severity ENUM('HIGH','MEDIUM','LOW') NOT NULL DEFAULT 'MEDIUM',
        reason TEXT NOT NULL,
        status ENUM('ACTIVE','RESOLVED') NOT NULL DEFAULT 'ACTIVE',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_quarantine_groups_run_item (run_id, source_table, source_pk, quarantine_type),
        KEY idx_quarantine_groups_run (run_id),
        KEY idx_quarantine_groups_type (quarantine_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS audit_rama_normalization (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        run_id VARCHAR(64) NOT NULL,
        id_profesor INT NOT NULL,
        correo VARCHAR(255) NOT NULL,
        rama_original VARCHAR(64) NOT NULL,
        rama_normalizada VARCHAR(64) NOT NULL,
        normalization_state ENUM('MAPPED','PENDIENTE_MANUAL') NOT NULL,
        needs_manual_review TINYINT(1) NOT NULL DEFAULT 0,
        overwrite_applied TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_audit_rama_run_prof (run_id, id_profesor),
        KEY idx_audit_rama_run (run_id),
        KEY idx_audit_rama_state (normalization_state)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($ddlStatements as $sql) {
    execSql($db, $sql);
}
$results['actions'][] = ['step' => 'PASO_2', 'action' => 'create_aux_tables', 'created_or_verified' => 5];

logAction(
    $db,
    $runId,
    'PASO_1',
    'SNAPSHOT_PREVIO_REFERENCIADO',
    null,
    1,
    [
        'snapshot_meta_file' => $metaFiles[0] ?? null,
        'snapshot_file' => $snapshotMeta['snapshot_file'] ?? null,
        'snapshot_sha256' => $snapshotMeta['sha256'] ?? null,
        'snapshot_run_id' => $snapshotMeta['run_id'] ?? null,
    ]
);

// PASO 3A: clasificación de contraseñas y cuentas (tbl_profesor)
$affected = execSql($db, "
INSERT INTO audit_accounts (
    run_id, source_table, source_pk, correo, id_profesor, id_usuario,
    perfil_raw, perfil_state, password_class, password_len,
    in_tbl_profesor, in_tbl_usuario, duplicate_count_profesor,
    canonical_rank, canonical_status, migration_candidate, notes
)
SELECT
    '{$runId}' AS run_id,
    'tbl_profesor' AS source_table,
    p.id_profesor AS source_pk,
    p.correo,
    p.id_profesor,
    (SELECT MIN(u.id_usuario) FROM tbl_usuario u WHERE u.correo = p.correo) AS id_usuario,
    p.perfil AS perfil_raw,
    CASE
        WHEN p.perfil IS NULL OR TRIM(p.perfil) = '' THEN 'EMPTY'
        WHEN UPPER(TRIM(p.perfil)) IN ('ADMIN','ADMINISTRADOR','PROFESOR','TUTOR') THEN 'VALID'
        ELSE 'INVALID'
    END AS perfil_state,
    CASE
        WHEN p.password IS NULL OR TRIM(p.password) = '' THEN 'VACIA'
        WHEN CHAR_LENGTH(p.password) = 60 AND (p.password LIKE '$2y$%' OR p.password LIKE '$2a$%' OR p.password LIKE '$2b$%') THEN 'BCRYPT'
        WHEN LEFT(p.password,1) = '$' THEN 'OTRA'
        ELSE 'LEGACY'
    END AS password_class,
    CHAR_LENGTH(p.password) AS password_len,
    1 AS in_tbl_profesor,
    CASE WHEN EXISTS(SELECT 1 FROM tbl_usuario u2 WHERE u2.correo = p.correo) THEN 1 ELSE 0 END AS in_tbl_usuario,
    (SELECT COUNT(*) FROM tbl_profesor p2 WHERE p2.correo = p.correo) AS duplicate_count_profesor,
    NULL AS canonical_rank,
    'N_A' AS canonical_status,
    CASE
        WHEN p.password IS NULL OR TRIM(p.password) = '' THEN 1
        WHEN CHAR_LENGTH(p.password) = 60 AND (p.password LIKE '$2y$%' OR p.password LIKE '$2a$%' OR p.password LIKE '$2b$%') THEN 0
        ELSE 1
    END AS migration_candidate,
    NULL AS notes
FROM tbl_profesor p
");
logAction($db, $runId, 'PASO_3A', 'AUDIT_ACCOUNTS_FROM_PROFESOR', 'audit_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3A', 'action' => 'audit_from_tbl_profesor', 'affected' => $affected];

// PASO 3A: clasificación de contraseñas y cuentas (tbl_usuario)
$affected = execSql($db, "
INSERT INTO audit_accounts (
    run_id, source_table, source_pk, correo, id_profesor, id_usuario,
    perfil_raw, perfil_state, password_class, password_len,
    in_tbl_profesor, in_tbl_usuario, duplicate_count_profesor,
    canonical_rank, canonical_status, migration_candidate, notes
)
SELECT
    '{$runId}' AS run_id,
    'tbl_usuario' AS source_table,
    u.id_usuario AS source_pk,
    u.correo,
    (SELECT MIN(p.id_profesor) FROM tbl_profesor p WHERE p.correo = u.correo) AS id_profesor,
    u.id_usuario,
    NULL AS perfil_raw,
    'N_A' AS perfil_state,
    CASE
        WHEN u.password IS NULL OR TRIM(u.password) = '' THEN 'VACIA'
        WHEN CHAR_LENGTH(u.password) = 60 AND (u.password LIKE '$2y$%' OR u.password LIKE '$2a$%' OR u.password LIKE '$2b$%') THEN 'BCRYPT'
        WHEN LEFT(u.password,1) = '$' THEN 'OTRA'
        ELSE 'LEGACY'
    END AS password_class,
    CHAR_LENGTH(u.password) AS password_len,
    CASE WHEN EXISTS(SELECT 1 FROM tbl_profesor p2 WHERE p2.correo = u.correo) THEN 1 ELSE 0 END AS in_tbl_profesor,
    1 AS in_tbl_usuario,
    COALESCE((SELECT COUNT(*) FROM tbl_profesor p3 WHERE p3.correo = u.correo), 0) AS duplicate_count_profesor,
    NULL AS canonical_rank,
    'N_A' AS canonical_status,
    CASE
        WHEN u.password IS NULL OR TRIM(u.password) = '' THEN 1
        WHEN CHAR_LENGTH(u.password) = 60 AND (u.password LIKE '$2y$%' OR u.password LIKE '$2a$%' OR u.password LIKE '$2b$%') THEN 0
        ELSE 1
    END AS migration_candidate,
    NULL AS notes
FROM tbl_usuario u
");
logAction($db, $runId, 'PASO_3A', 'AUDIT_ACCOUNTS_FROM_USUARIO', 'audit_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3A', 'action' => 'audit_from_tbl_usuario', 'affected' => $affected];

// PASO 3C: canonical provisional en duplicados de profesor
$affected = execSql($db, "
UPDATE audit_accounts a
SET a.canonical_status = CASE
    WHEN a.source_table = 'tbl_profesor' AND a.duplicate_count_profesor > 1 THEN 'MANUAL_REVIEW'
    WHEN a.source_table = 'tbl_profesor' AND a.duplicate_count_profesor = 1 THEN 'UNIQUE'
    ELSE 'N_A'
END,
    a.canonical_rank = NULL
WHERE a.run_id = '{$runId}'
");
logAction($db, $runId, 'PASO_3C', 'SET_DEFAULT_CANONICAL_STATUS', 'audit_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3C', 'action' => 'set_default_canonical_status', 'affected' => $affected];

$affected = execSql($db, "
UPDATE audit_accounts a
INNER JOIN (
    SELECT
        p.id_profesor,
        ROW_NUMBER() OVER (
            PARTITION BY p.correo
            ORDER BY
                CASE WHEN CHAR_LENGTH(p.password) = 60 AND (p.password LIKE '$2y$%' OR p.password LIKE '$2a$%' OR p.password LIKE '$2b$%') THEN 1 ELSE 0 END DESC,
                CASE WHEN p.perfil IS NULL OR TRIM(p.perfil) = '' THEN 0 ELSE 1 END DESC,
                p.id_profesor DESC
        ) AS rn
    FROM tbl_profesor p
    INNER JOIN (
        SELECT correo
        FROM tbl_profesor
        GROUP BY correo
        HAVING COUNT(*) > 1
    ) d ON d.correo = p.correo
    WHERE p.correo <> 'ads@gmail.com'
) ranked ON ranked.id_profesor = a.source_pk
SET
    a.canonical_rank = ranked.rn,
    a.canonical_status = CASE WHEN ranked.rn = 1 THEN 'CANONICAL_PROVISIONAL' ELSE 'NON_CANONICAL' END,
    a.notes = CASE WHEN ranked.rn = 1 THEN 'CANONICO_PROVISIONAL_POR_REGLA' ELSE 'NO_CANONICO_POR_REGLA' END
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_profesor'
");
logAction($db, $runId, 'PASO_3C', 'APPLY_CANONICAL_RULE_DUPLICATES', 'audit_accounts', $affected, ['rule' => 'BCRYPT > PERFIL_VALIDO > MAYOR_ID']);
$results['actions'][] = ['step' => 'PASO_3C', 'action' => 'apply_canonical_rule_duplicates', 'affected' => $affected];

$affected = execSql($db, "
UPDATE audit_accounts a
SET
    a.canonical_status = 'MANUAL_REVIEW',
    a.canonical_rank = NULL,
    a.notes = 'REVISION_MANUAL_SIN_EVIDENCIA_SUFICIENTE'
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_profesor'
  AND a.correo = 'ads@gmail.com'
  AND a.duplicate_count_profesor > 1
");
logAction($db, $runId, 'PASO_3C', 'FORCE_MANUAL_REVIEW_ADS_GMAIL', 'audit_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3C', 'action' => 'force_manual_review_ads', 'affected' => $affected];

// PASO 3B: cuarentena perfiles vacíos
$affected = execSql($db, "
INSERT INTO quarantine_accounts (
    run_id, source_table, source_pk, correo, quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    a.source_table,
    a.source_pk,
    a.correo,
    'PERFIL_VACIO',
    'HIGH',
    'Perfil vacío en tbl_profesor; no se asigna perfil automáticamente'
FROM audit_accounts a
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_profesor'
  AND a.perfil_state = 'EMPTY'
");
logAction($db, $runId, 'PASO_3B', 'QUARANTINE_EMPTY_PROFILE', 'quarantine_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3B', 'action' => 'quarantine_empty_profile', 'affected' => $affected];

// PASO 3A: cuarentena contraseñas candidatas a migración futura
$affected = execSql($db, "
INSERT INTO quarantine_accounts (
    run_id, source_table, source_pk, correo, quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    a.source_table,
    a.source_pk,
    a.correo,
    'PASSWORD_MIGRATION_CANDIDATE',
    'MEDIUM',
    'Cuenta con password no bcrypt; candidata a migración futura'
FROM audit_accounts a
WHERE a.run_id = '{$runId}'
  AND a.migration_candidate = 1
");
logAction($db, $runId, 'PASO_3A', 'MARK_PASSWORD_MIGRATION_CANDIDATES', 'quarantine_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3A', 'action' => 'mark_password_migration_candidates', 'affected' => $affected];

// PASO 3C: cuarentena de duplicados
$affected = execSql($db, "
INSERT INTO quarantine_accounts (
    run_id, source_table, source_pk, correo, quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    a.source_table,
    a.source_pk,
    a.correo,
    'DUPLICADO_NO_CANONICO',
    'HIGH',
    'Registro duplicado no canónico según regla provisional'
FROM audit_accounts a
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_profesor'
  AND a.canonical_status = 'NON_CANONICAL'
");
logAction($db, $runId, 'PASO_3C', 'QUARANTINE_NON_CANONICAL_DUPLICATES', 'quarantine_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3C', 'action' => 'quarantine_non_canonical_duplicates', 'affected' => $affected];

$affected = execSql($db, "
INSERT INTO quarantine_accounts (
    run_id, source_table, source_pk, correo, quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    a.source_table,
    a.source_pk,
    a.correo,
    'DUPLICADO_REVISION_MANUAL',
    'HIGH',
    'Duplicado sin evidencia suficiente para decisión automática final'
FROM audit_accounts a
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_profesor'
  AND a.duplicate_count_profesor > 1
  AND a.canonical_status = 'MANUAL_REVIEW'
");
logAction($db, $runId, 'PASO_3C', 'QUARANTINE_MANUAL_REVIEW_DUPLICATES', 'quarantine_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3C', 'action' => 'quarantine_manual_review_duplicates', 'affected' => $affected];

// PASO 3A/B: cuentas huérfanas entre tablas
$affected = execSql($db, "
INSERT INTO quarantine_accounts (
    run_id, source_table, source_pk, correo, quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    a.source_table,
    a.source_pk,
    a.correo,
    'PROFESOR_SIN_USUARIO',
    'MEDIUM',
    'Fila en tbl_profesor sin correspondencia en tbl_usuario'
FROM audit_accounts a
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_profesor'
  AND a.in_tbl_usuario = 0
");
logAction($db, $runId, 'PASO_3A', 'QUARANTINE_PROFESOR_WITHOUT_USUARIO', 'quarantine_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3A', 'action' => 'quarantine_profesor_without_usuario', 'affected' => $affected];

$affected = execSql($db, "
INSERT INTO quarantine_accounts (
    run_id, source_table, source_pk, correo, quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    a.source_table,
    a.source_pk,
    a.correo,
    'USUARIO_SIN_PROFESOR',
    'MEDIUM',
    'Fila en tbl_usuario sin correspondencia en tbl_profesor'
FROM audit_accounts a
WHERE a.run_id = '{$runId}'
  AND a.source_table = 'tbl_usuario'
  AND a.in_tbl_profesor = 0
");
logAction($db, $runId, 'PASO_3A', 'QUARANTINE_USUARIO_WITHOUT_PROFESOR', 'quarantine_accounts', $affected, []);
$results['actions'][] = ['step' => 'PASO_3A', 'action' => 'quarantine_usuario_without_profesor', 'affected' => $affected];

// PASO 3D: cuarentena relacional de grupos
$affected = execSql($db, "
INSERT INTO quarantine_groups (
    run_id, source_table, source_pk, id_grupo, id_tutor, id_profesor,
    quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    'tbl_grupo',
    g.id_grupo,
    g.id_grupo,
    g.id_tutor,
    NULL,
    'GRUPO_TUTOR_HUERFANO',
    'HIGH',
    CONCAT('Grupo con id_tutor huérfano: ', g.id_tutor)
FROM tbl_grupo g
LEFT JOIN tbl_profesor p ON p.id_profesor = g.id_tutor
WHERE p.id_profesor IS NULL
");
logAction($db, $runId, 'PASO_3D', 'QUARANTINE_GROUPS_WITH_ORPHAN_TUTOR', 'quarantine_groups', $affected, []);
$results['actions'][] = ['step' => 'PASO_3D', 'action' => 'quarantine_groups_with_orphan_tutor', 'affected' => $affected];

$affected = execSql($db, "
INSERT INTO quarantine_groups (
    run_id, source_table, source_pk, id_grupo, id_tutor, id_profesor,
    quarantine_type, severity, reason
)
SELECT
    '{$runId}',
    'tbl_grupo_profesor',
    gp.id,
    gp.id_grupo,
    NULL,
    gp.id_profesor,
    CASE
        WHEN gp.id_grupo = 0 THEN 'ASIGNACION_ID_GRUPO_0'
        WHEN g.id_grupo IS NULL THEN 'ASIGNACION_GRUPO_HUERFANO'
        WHEN p.id_profesor IS NULL THEN 'ASIGNACION_PROFESOR_HUERFANO'
        ELSE 'ASIGNACION_ANOMALA'
    END,
    'HIGH',
    CASE
        WHEN gp.id_grupo = 0 THEN 'Asignación con id_grupo=0'
        WHEN g.id_grupo IS NULL THEN 'Asignación a grupo inexistente'
        WHEN p.id_profesor IS NULL THEN 'Asignación con profesor inexistente'
        ELSE 'Asignación con anomalía relacional'
    END
FROM tbl_grupo_profesor gp
LEFT JOIN tbl_grupo g ON g.id_grupo = gp.id_grupo
LEFT JOIN tbl_profesor p ON p.id_profesor = gp.id_profesor
WHERE gp.id_grupo = 0 OR g.id_grupo IS NULL OR p.id_profesor IS NULL
");
logAction($db, $runId, 'PASO_3D', 'QUARANTINE_ORPHAN_GROUP_ASSIGNMENTS', 'quarantine_groups', $affected, []);
$results['actions'][] = ['step' => 'PASO_3D', 'action' => 'quarantine_orphan_group_assignments', 'affected' => $affected];

// PASO 3E: normalización auxiliar de rama
$affected = execSql($db, "
INSERT INTO audit_rama_normalization (
    run_id, id_profesor, correo,
    rama_original, rama_normalizada,
    normalization_state, needs_manual_review, overwrite_applied
)
SELECT
    '{$runId}',
    p.id_profesor,
    p.correo,
    p.rama,
    CASE
        WHEN p.rama IS NULL OR TRIM(p.rama) = '' THEN 'PENDIENTE'
        WHEN p.rama = 'TECNICA' THEN 'TECNICAS'
        WHEN p.rama = 'S Y J' THEN 'SYJ'
        ELSE p.rama
    END AS rama_normalizada,
    CASE
        WHEN p.rama IS NULL OR TRIM(p.rama) = '' THEN 'PENDIENTE_MANUAL'
        ELSE 'MAPPED'
    END AS normalization_state,
    CASE
        WHEN p.rama IS NULL OR TRIM(p.rama) = '' THEN 1
        ELSE 0
    END AS needs_manual_review,
    0 AS overwrite_applied
FROM tbl_profesor p
");
logAction($db, $runId, 'PASO_3E', 'CREATE_RAMA_NORMALIZATION_AUDIT', 'audit_rama_normalization', $affected, ['mapping' => ['TECNICA' => 'TECNICAS', 'S Y J' => 'SYJ', '' => 'PENDIENTE']]);
$results['actions'][] = ['step' => 'PASO_3E', 'action' => 'create_rama_normalization_audit', 'affected' => $affected];

// PASO 3B: constancia de ausencia de admins válidos
$adminCount = scalarInt($db, "SELECT COUNT(*) FROM tbl_profesor WHERE UPPER(TRIM(perfil)) IN ('ADMIN', 'ADMINISTRADOR')");
logAction($db, $runId, 'PASO_3B', 'CHECK_VALID_ADMINS', 'tbl_profesor', $adminCount, ['valid_admin_count' => $adminCount]);
$results['actions'][] = ['step' => 'PASO_3B', 'action' => 'check_valid_admins', 'valid_admin_count' => $adminCount];

// Guardar resumen de ejecución para trazabilidad local
$outFile = __DIR__ . DIRECTORY_SEPARATOR . "fase4c_apply_result_{$runId}.json";
file_put_contents($outFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode(['ok' => true, 'run_id' => $runId, 'result_file' => $outFile], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
