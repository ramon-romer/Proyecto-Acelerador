<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$run = 'F5_PREP_20260406_114845';
$tables = ['mergeable_exclusions','mergeable_tbl_grupo_profesor','mergeable_tbl_grupo','mergeable_tbl_usuario','mergeable_tbl_profesor','mergeable_runs'];
foreach ($tables as $t) {
    $safeRun = $m->real_escape_string($run);
    $m->query("DELETE FROM `{$t}` WHERE phase5_run_id='{$safeRun}'");
    echo $t . ':' . $m->affected_rows . PHP_EOL;
}
$safeRun = $m->real_escape_string($run);
$m->query("DELETE FROM audit_log WHERE run_id='{$safeRun}'");
echo 'audit_log:' . $m->affected_rows . PHP_EOL;
?>
