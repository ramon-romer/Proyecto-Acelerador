<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
// grupos relacionalmente validos (tutor existe)
$r1 = $m->query("SELECT COUNT(*) AS c FROM tbl_grupo g INNER JOIN tbl_profesor p ON p.id_profesor=g.id_tutor");
echo json_encode(['grupos_tutor_existente'=>$r1->fetch_assoc()['c']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
// grupos con tutor perfil TUTOR
$r2 = $m->query("SELECT COUNT(*) AS c FROM tbl_grupo g INNER JOIN tbl_profesor p ON p.id_profesor=g.id_tutor WHERE UPPER(TRIM(p.perfil))='TUTOR'");
echo json_encode(['grupos_tutor_perfil_tutor'=>$r2->fetch_assoc()['c']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
// asignaciones relacionalmente validas (grupo y profesor existen)
$r3 = $m->query("SELECT COUNT(*) AS c FROM tbl_grupo_profesor gp INNER JOIN tbl_grupo g ON g.id_grupo=gp.id_grupo INNER JOIN tbl_profesor p ON p.id_profesor=gp.id_profesor WHERE gp.id_grupo<>0");
echo json_encode(['asignaciones_relacionales_validas'=>$r3->fetch_assoc()['c']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
