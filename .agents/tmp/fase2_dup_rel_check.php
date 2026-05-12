<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$q = "SELECT p.correo,p.id_profesor,p.perfil,
             COALESCE(gp.cnt,0) AS asignaciones,
             COALESCE(gt.cnt,0) AS grupos_tutor
      FROM tbl_profesor p
      LEFT JOIN (SELECT id_profesor, COUNT(*) AS cnt FROM tbl_grupo_profesor GROUP BY id_profesor) gp ON gp.id_profesor=p.id_profesor
      LEFT JOIN (SELECT id_tutor, COUNT(*) AS cnt FROM tbl_grupo GROUP BY id_tutor) gt ON gt.id_tutor=p.id_profesor
      WHERE p.correo IN ('lui@gmail.com','ads@gmail.com')
      ORDER BY p.correo,p.id_profesor";
$r = $m->query($q);
while($row=$r->fetch_assoc()){
  echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
}
?>
