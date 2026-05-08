<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$run = 'F4C_EXEC_20260406_114115';
$r = $m->query("SELECT COUNT(*) AS c FROM audit_rama_normalization WHERE run_id='{$run}' AND needs_manual_review=1");
$row = $r->fetch_assoc();
echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
