<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$q = "SELECT p.id_profesor, p.correo, p.ORCID, p.perfil, CASE WHEN u.correo IS NULL THEN 0 ELSE 1 END AS existe_usuario
      FROM tbl_profesor p
      LEFT JOIN (SELECT DISTINCT correo FROM tbl_usuario) u ON u.correo = p.correo
      WHERE p.perfil IS NULL OR TRIM(p.perfil) = ''
      ORDER BY p.id_profesor";
$r = $m->query($q);
while ($row = $r->fetch_assoc()) {
  echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
}
?>
