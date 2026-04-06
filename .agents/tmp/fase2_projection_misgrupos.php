<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$r = $m->query("SELECT COUNT(*) AS c FROM tbl_grupo_profesor gp INNER JOIN tbl_grupo g ON g.id_grupo=gp.id_grupo INNER JOIN tbl_profesor p ON p.id_profesor=gp.id_profesor INNER JOIN tbl_profesor t ON t.id_profesor=g.id_tutor WHERE gp.id_grupo<>0");
echo json_encode(['asignaciones_visibles_mis_grupos'=>$r->fetch_assoc()['c']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
