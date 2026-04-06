<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$q = "SELECT p.correo, COUNT(*) AS prof_rows, SUM(u.correo IS NOT NULL) AS usuario_rows_match
      FROM tbl_profesor p
      LEFT JOIN (SELECT DISTINCT correo FROM tbl_usuario) u ON u.correo = p.correo
      GROUP BY p.correo
      HAVING COUNT(*) > 1
      ORDER BY p.correo";
$r = $m->query($q);
while ($row = $r->fetch_assoc()) {
  echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
}
?>
