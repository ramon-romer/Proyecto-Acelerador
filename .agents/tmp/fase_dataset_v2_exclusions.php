<?php
$db=new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');
$mergeRun = $db->query("SELECT phase5_run_id FROM mergeable_runs WHERE phase5_run_id LIKE 'F5V2_PREP_%' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['phase5_run_id'];
$r=$db->query("SELECT source_table, reason_code, COUNT(*) AS filas, COUNT(DISTINCT source_pk) AS source_pk_unicos FROM mergeable_exclusions WHERE phase5_run_id='".$db->real_escape_string($mergeRun)."' GROUP BY source_table, reason_code ORDER BY source_table, reason_code");
$out=[]; while($row=$r->fetch_assoc()) $out[]=$row;
$tot=$db->query("SELECT source_table, COUNT(DISTINCT source_pk) AS source_pk_unicos FROM mergeable_exclusions WHERE phase5_run_id='".$db->real_escape_string($mergeRun)."' GROUP BY source_table ORDER BY source_table");
$totals=[]; while($row=$tot->fetch_assoc()) $totals[]=$row;
echo json_encode(['mergeable_run_id'=>$mergeRun,'exclusions_by_reason'=>$out,'distinct_excluded_per_table'=>$totals], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
