<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$runId = $argv[1] ?? '';
if ($runId === '') {
    fwrite(STDERR, "Uso: php fase4c_verify.php <RUN_ID>\n");
    exit(1);
}

$db = new mysqli('localhost', 'root', '', 'acelerador_staging_20260406');
$db->set_charset('utf8mb4');

function section(string $name): void { echo "\n=== {$name} ===\n"; }
function rows(mysqli $db, string $sql): void {
    $res = $db->query($sql);
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
}

section('run_info');
rows($db, "SELECT '{$runId}' AS run_id, DATABASE() AS database_name");

section('aux_tables_exist');
rows($db, "
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('audit_accounts','quarantine_accounts','quarantine_groups','audit_log','audit_rama_normalization')
ORDER BY table_name
");

section('audit_accounts_password_class');
rows($db, "
SELECT source_table, password_class, COUNT(*) AS total
FROM audit_accounts
WHERE run_id = '{$runId}'
GROUP BY source_table, password_class
ORDER BY source_table, password_class
");

section('audit_accounts_perfil_state_profesor');
rows($db, "
SELECT perfil_state, COUNT(*) AS total
FROM audit_accounts
WHERE run_id = '{$runId}'
  AND source_table = 'tbl_profesor'
GROUP BY perfil_state
ORDER BY perfil_state
");

section('canonical_status_profesor');
rows($db, "
SELECT canonical_status, COUNT(*) AS total
FROM audit_accounts
WHERE run_id = '{$runId}'
  AND source_table = 'tbl_profesor'
GROUP BY canonical_status
ORDER BY canonical_status
");

section('canonical_detail_lui_ads');
rows($db, "
SELECT correo, source_pk AS id_profesor, canonical_rank, canonical_status, notes
FROM audit_accounts
WHERE run_id = '{$runId}'
  AND source_table = 'tbl_profesor'
  AND correo IN ('lui@gmail.com', 'ads@gmail.com')
ORDER BY correo, source_pk
");

section('quarantine_accounts_by_type');
rows($db, "
SELECT quarantine_type, COUNT(*) AS total
FROM quarantine_accounts
WHERE run_id = '{$runId}'
GROUP BY quarantine_type
ORDER BY quarantine_type
");

section('quarantine_accounts_correo_detail');
rows($db, "
SELECT correo,
       GROUP_CONCAT(DISTINCT quarantine_type ORDER BY quarantine_type SEPARATOR ',') AS tipos,
       COUNT(*) AS total_marcas
FROM quarantine_accounts
WHERE run_id = '{$runId}'
GROUP BY correo
ORDER BY correo
");

section('quarantine_groups_by_type');
rows($db, "
SELECT quarantine_type, COUNT(*) AS total
FROM quarantine_groups
WHERE run_id = '{$runId}'
GROUP BY quarantine_type
ORDER BY quarantine_type
");

section('quarantine_groups_detail');
rows($db, "
SELECT source_table, source_pk, id_grupo, id_tutor, id_profesor, quarantine_type, reason
FROM quarantine_groups
WHERE run_id = '{$runId}'
ORDER BY source_table, source_pk
");

section('rama_normalization_summary');
rows($db, "
SELECT normalization_state, COUNT(*) AS total
FROM audit_rama_normalization
WHERE run_id = '{$runId}'
GROUP BY normalization_state
ORDER BY normalization_state
");

section('rama_pending_manual_detail');
rows($db, "
SELECT id_profesor, correo, rama_original, rama_normalizada
FROM audit_rama_normalization
WHERE run_id = '{$runId}'
  AND needs_manual_review = 1
ORDER BY id_profesor
");

section('pending_manual_decisions_summary');
rows($db, "
SELECT
  (SELECT COUNT(*) FROM audit_accounts WHERE run_id = '{$runId}' AND source_table='tbl_profesor' AND canonical_status='MANUAL_REVIEW') AS dup_manual_review,
  (SELECT COUNT(*) FROM quarantine_accounts WHERE run_id = '{$runId}' AND quarantine_type='PERFIL_VACIO') AS perfiles_vacios,
  (SELECT COUNT(*) FROM audit_rama_normalization WHERE run_id = '{$runId}' AND needs_manual_review=1) AS rama_pendiente,
  (SELECT COUNT(*) FROM quarantine_groups WHERE run_id = '{$runId}') AS incidencias_relacionales,
  (SELECT COUNT(*) FROM tbl_profesor WHERE UPPER(TRIM(perfil)) IN ('ADMIN','ADMINISTRADOR')) AS admins_validos
");
