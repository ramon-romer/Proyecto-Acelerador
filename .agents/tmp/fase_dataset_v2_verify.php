<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');

function one(mysqli $db, string $sql): array {
  $r = $db->query($sql);
  $row = $r->fetch_assoc();
  return $row ?: [];
}

function all(mysqli $db, string $sql): array {
  $r = $db->query($sql);
  $rows = [];
  while ($row = $r->fetch_assoc()) $rows[] = $row;
  return $rows;
}

$meta = one($db, "SELECT DATABASE() AS db");

$datasetRun = one($db, "SELECT dataset_run_id FROM dataset_resolution_log WHERE dataset_run_id LIKE 'F6_DATASET_%' ORDER BY created_at DESC, id DESC LIMIT 1")['dataset_run_id'] ?? null;
$mergeRunRow = one($db, "SELECT phase5_run_id, source_f4c_run_id FROM mergeable_runs WHERE phase5_run_id LIKE 'F5V2_PREP_%' ORDER BY created_at DESC, id DESC LIMIT 1");
$mergeRun = $mergeRunRow['phase5_run_id'] ?? null;
$sourceF4 = $mergeRunRow['source_f4c_run_id'] ?? null;

if (!$datasetRun || !$mergeRun || !$sourceF4) {
  throw new RuntimeException('No se encontraron runs esperados F6/F5V2/F4.');
}

$counts = [
  'usuarios' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_tbl_usuario WHERE phase5_run_id='{$mergeRun}'")['c'] ?? 0),
  'profesores' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_tbl_profesor WHERE phase5_run_id='{$mergeRun}'")['c'] ?? 0),
  'grupos' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_tbl_grupo WHERE phase5_run_id='{$mergeRun}'")['c'] ?? 0),
  'asignaciones' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id='{$mergeRun}'")['c'] ?? 0),
];

$baseTotals = [
  'tbl_usuario' => (int)(one($db, "SELECT COUNT(*) c FROM tbl_usuario")['c'] ?? 0),
  'tbl_profesor' => (int)(one($db, "SELECT COUNT(*) c FROM tbl_profesor")['c'] ?? 0),
  'tbl_grupo' => (int)(one($db, "SELECT COUNT(*) c FROM tbl_grupo")['c'] ?? 0),
  'tbl_grupo_profesor' => (int)(one($db, "SELECT COUNT(*) c FROM tbl_grupo_profesor")['c'] ?? 0),
];

$resolutionByAction = all($db, "
SELECT action_code, status, COUNT(*) AS filas
FROM dataset_resolution_log
WHERE dataset_run_id='{$datasetRun}'
GROUP BY action_code, status
ORDER BY action_code, status
");

$luiAudit = all($db, "
SELECT a.source_pk AS id_profesor, a.canonical_status, a.perfil_state, a.password_class
FROM audit_accounts a
WHERE a.run_id='{$sourceF4}'
  AND a.source_table='tbl_profesor'
  AND a.correo='lui@gmail.com'
ORDER BY a.source_pk
");

$luiResolution = all($db, "
SELECT source_pk AS id_profesor, action_code, status
FROM dataset_resolution_log
WHERE dataset_run_id='{$datasetRun}'
  AND source_table='tbl_profesor'
  AND correo='lui@gmail.com'
ORDER BY source_pk
");

$luiMergeable = all($db, "
SELECT id_profesor, correo, canonical_status
FROM mergeable_tbl_profesor
WHERE phase5_run_id='{$mergeRun}'
  AND correo='lui@gmail.com'
ORDER BY id_profesor
");

$adsAudit = all($db, "
SELECT a.source_pk AS id_profesor, a.canonical_status, a.perfil_state, a.password_class
FROM audit_accounts a
WHERE a.run_id='{$sourceF4}'
  AND a.source_table='tbl_profesor'
  AND a.correo='ads@gmail.com'
ORDER BY a.source_pk
");

$adsResolution = all($db, "
SELECT source_pk AS id_profesor, action_code, status
FROM dataset_resolution_log
WHERE dataset_run_id='{$datasetRun}'
  AND source_table='tbl_profesor'
  AND correo='ads@gmail.com'
ORDER BY source_pk
");

$adsMergeable = (int)(one($db, "
SELECT COUNT(*) c
FROM mergeable_tbl_profesor
WHERE phase5_run_id='{$mergeRun}' AND correo='ads@gmail.com'
")['c'] ?? 0);

$emptyProfiles = all($db, "
SELECT a.source_pk AS id_profesor, a.correo
FROM audit_accounts a
WHERE a.run_id='{$sourceF4}'
  AND a.source_table='tbl_profesor'
  AND a.perfil_state='EMPTY'
ORDER BY a.source_pk
");

$emptyProfilesInMerge = (int)(one($db, "
SELECT COUNT(*) c
FROM mergeable_tbl_profesor m
INNER JOIN audit_accounts a
  ON a.run_id='{$sourceF4}'
 AND a.source_table='tbl_profesor'
 AND a.source_pk=m.id_profesor
WHERE m.phase5_run_id='{$mergeRun}'
  AND a.perfil_state='EMPTY'
")['c'] ?? 0);

$groupQuarantine = all($db, "
SELECT source_table, source_pk, id_grupo, id_profesor, id_tutor, quarantine_type, reason
FROM quarantine_groups
WHERE run_id='{$sourceF4}'
  AND status='ACTIVE'
ORDER BY source_table, source_pk
");

$groupExclByReason = all($db, "
SELECT source_table, reason_code, COUNT(*) AS filas, COUNT(DISTINCT source_pk) AS source_pk_unicos
FROM mergeable_exclusions
WHERE phase5_run_id='{$mergeRun}'
  AND source_table IN ('tbl_grupo','tbl_grupo_profesor')
GROUP BY source_table, reason_code
ORDER BY source_table, reason_code
");

$fixture = one($db, "
SELECT u.id_usuario, u.correo, p.id_profesor, p.perfil
FROM tbl_usuario u
INNER JOIN tbl_profesor p ON p.correo=u.correo
WHERE u.correo LIKE 'fixture.admin.%@staging.local'
ORDER BY u.id_usuario DESC
LIMIT 1
");

$fixtureEmail = $fixture['correo'] ?? null;
$fixtureChecks = [];
if ($fixtureEmail) {
  $fixtureChecks = [
    'en_mergeable_usuario' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_tbl_usuario WHERE phase5_run_id='{$mergeRun}' AND correo='".$db->real_escape_string($fixtureEmail)."'")['c'] ?? 0),
    'en_mergeable_profesor' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_tbl_profesor WHERE phase5_run_id='{$mergeRun}' AND correo='".$db->real_escape_string($fixtureEmail)."'")['c'] ?? 0),
    'exclusion_usuario_fixture' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_exclusions WHERE phase5_run_id='{$mergeRun}' AND source_table='tbl_usuario' AND correo='".$db->real_escape_string($fixtureEmail)."' AND reason_code='FIXTURE_STAGING_ONLY'")['c'] ?? 0),
    'exclusion_profesor_fixture' => (int)(one($db, "SELECT COUNT(*) c FROM mergeable_exclusions WHERE phase5_run_id='{$mergeRun}' AND source_table='tbl_profesor' AND correo='".$db->real_escape_string($fixtureEmail)."' AND reason_code='FIXTURE_STAGING_ONLY'")['c'] ?? 0),
  ];
}

$ambiguousRemaining = [
  'usuarios_duplicados_correo' => (int)(one($db, "SELECT COUNT(*) c FROM (SELECT correo FROM tbl_usuario GROUP BY correo HAVING COUNT(*)>1) t")['c'] ?? 0),
  'profesor_manual_review' => (int)(one($db, "SELECT COUNT(*) c FROM audit_accounts WHERE run_id='{$sourceF4}' AND source_table='tbl_profesor' AND canonical_status='MANUAL_REVIEW'")['c'] ?? 0),
];

$subsetMembers = [
  'usuarios' => all($db, "SELECT id_usuario, correo, linked_id_profesor, password_class FROM mergeable_tbl_usuario WHERE phase5_run_id='{$mergeRun}' ORDER BY id_usuario"),
  'profesores' => all($db, "SELECT id_profesor, correo, perfil, canonical_status, password_class FROM mergeable_tbl_profesor WHERE phase5_run_id='{$mergeRun}' ORDER BY id_profesor"),
  'grupos' => all($db, "SELECT id_grupo, nombre, id_tutor FROM mergeable_tbl_grupo WHERE phase5_run_id='{$mergeRun}' ORDER BY id_grupo"),
  'asignaciones' => all($db, "SELECT source_id, id_grupo, id_profesor FROM mergeable_tbl_grupo_profesor WHERE phase5_run_id='{$mergeRun}' ORDER BY source_id"),
];

$out = [
  'db' => $meta['db'] ?? null,
  'dataset_run_id' => $datasetRun,
  'mergeable_v2_run_id' => $mergeRun,
  'source_f4_run_id' => $sourceF4,
  'counts' => $counts,
  'base_totals' => $baseTotals,
  'resolution_by_action' => $resolutionByAction,
  'lui' => [
    'audit_accounts' => $luiAudit,
    'dataset_resolution' => $luiResolution,
    'mergeable' => $luiMergeable,
  ],
  'ads' => [
    'audit_accounts' => $adsAudit,
    'dataset_resolution' => $adsResolution,
    'mergeable_count' => $adsMergeable,
  ],
  'empty_profiles' => [
    'list' => $emptyProfiles,
    'in_mergeable_count' => $emptyProfilesInMerge,
  ],
  'groups' => [
    'quarantine_active' => $groupQuarantine,
    'exclusions_by_reason' => $groupExclByReason,
  ],
  'fixture' => [
    'record' => $fixture,
    'checks' => $fixtureChecks,
  ],
  'remaining_ambiguities' => $ambiguousRemaining,
  'subset_members' => $subsetMembers,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
