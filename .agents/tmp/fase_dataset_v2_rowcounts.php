<?php
$db=new mysqli('localhost','root','','acelerador_staging_20260406');
$db->set_charset('utf8mb4');
$run=$db->query("SELECT phase5_run_id FROM mergeable_runs WHERE phase5_run_id LIKE 'F5V2_PREP_%' ORDER BY created_at DESC,id DESC LIMIT 1")->fetch_assoc()['phase5_run_id'];
$c=$db->query("SELECT COUNT(*) c FROM mergeable_exclusions WHERE phase5_run_id='".$db->real_escape_string($run)."'")->fetch_assoc()['c'];
$rc=$db->query("SELECT COUNT(*) c FROM dataset_resolution_log WHERE dataset_run_id=(SELECT dataset_run_id FROM dataset_resolution_log WHERE dataset_run_id LIKE 'F6_DATASET_%' ORDER BY created_at DESC,id DESC LIMIT 1)")->fetch_assoc()['c'];
echo json_encode(['merge_run'=>$run,'mergeable_exclusions_rows'=>(int)$c,'dataset_resolution_rows'=>(int)$rc], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
