<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$r = $m->query('SELECT DATABASE() AS db');
echo json_encode($r->fetch_assoc(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
$r = $m->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('audit_log','audit_accounts','quarantine_accounts','quarantine_groups','audit_rama_normalization','mergeable_runs','mergeable_tbl_usuario','mergeable_tbl_profesor','mergeable_tbl_grupo','mergeable_tbl_grupo_profesor','mergeable_exclusions') ORDER BY table_name");
while ($row = $r->fetch_assoc()) {
  echo $row['table_name'], PHP_EOL;
}
?>
