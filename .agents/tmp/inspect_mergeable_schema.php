<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');
$tables = ['mergeable_runs','mergeable_tbl_usuario','mergeable_tbl_profesor','mergeable_tbl_grupo','mergeable_tbl_grupo_profesor','mergeable_exclusions','dataset_resolution_log'];
foreach ($tables as $t) {
  echo "=== $t ===\n";
  $exists = $db->query("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='".$db->real_escape_string($t)."'")->fetch_assoc()['c'];
  if ((int)$exists === 0) { echo "(no existe)\n\n"; continue; }
  $r = $db->query("SHOW COLUMNS FROM `{$t}`");
  while ($row = $r->fetch_assoc()) {
    echo $row['Field'],"\t",$row['Type'],"\t",$row['Null'],"\t",$row['Key'],"\t",(string)$row['Default'],"\n";
  }
  echo "\n";
}
?>
