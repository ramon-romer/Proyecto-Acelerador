<?php
$db=new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');
$datasetRun = $db->query("SELECT dataset_run_id FROM dataset_resolution_log WHERE dataset_run_id LIKE 'F6_DATASET_%' ORDER BY created_at DESC, id DESC LIMIT 1")->fetch_assoc()['dataset_run_id'];
$r=$db->query("SELECT action_step, action_name, target_table, affected_rows, details_json FROM audit_log WHERE run_id='".$db->real_escape_string($datasetRun)."' ORDER BY id");
$out=[]; while($row=$r->fetch_assoc()) $out[]=$row;
echo json_encode(['dataset_run_id'=>$datasetRun,'audit_log'=>$out], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
