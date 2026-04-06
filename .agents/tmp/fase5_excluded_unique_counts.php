<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$run = 'F5_PREP_20260406_114916';
$r = $m->query("SELECT source_table, COUNT(DISTINCT source_pk) AS excluded_records FROM mergeable_exclusions WHERE phase5_run_id='{$run}' GROUP BY source_table ORDER BY source_table");
while ($row = $r->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
}
?>
