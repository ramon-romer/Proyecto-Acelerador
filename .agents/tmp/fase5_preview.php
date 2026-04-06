<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'acelerador_staging_20260406');
$db->set_charset('utf8mb4');

$run = $db->query("SELECT run_id FROM audit_log WHERE run_id LIKE 'F4C_EXEC_%' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['run_id'] ?? null;
if (!$run) {
    throw new RuntimeException('No se encontro run F4C_EXEC en audit_log.');
}

echo "RUN=" . $run . PHP_EOL;

echo "\n=== tbl_grupo ===\n";
$r = $db->query("SELECT * FROM tbl_grupo ORDER BY id_grupo");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

echo "\n=== tbl_grupo_profesor ===\n";
$r = $db->query("SELECT * FROM tbl_grupo_profesor ORDER BY id");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

echo "\n=== strict_profesor_candidates_preview ===\n";
$sql = "
SELECT
  p.id_profesor,
  p.correo,
  p.perfil,
  a.canonical_status,
  a.password_class,
  rn.rama_normalizada,
  rn.needs_manual_review
FROM tbl_profesor p
INNER JOIN audit_accounts a
  ON a.run_id = '{$run}'
 AND a.source_table = 'tbl_profesor'
 AND a.source_pk = p.id_profesor
INNER JOIN audit_rama_normalization rn
  ON rn.run_id = '{$run}'
 AND rn.id_profesor = p.id_profesor
WHERE a.in_tbl_usuario = 1
  AND a.perfil_state = 'VALID'
  AND a.canonical_status IN ('UNIQUE', 'CANONICAL_PROVISIONAL')
  AND rn.needs_manual_review = 0
  AND NOT EXISTS (
      SELECT 1
      FROM quarantine_accounts q
      WHERE q.run_id = '{$run}'
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
ORDER BY p.id_profesor
";
$r = $db->query($sql);
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
}

?>
