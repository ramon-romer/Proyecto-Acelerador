<?php
$m = new mysqli('localhost','root','','acelerador_staging_20260406');
$run = 'F4C_EXEC_20260406_114115';
$sql = "
SELECT COUNT(*) AS c
FROM tbl_profesor p
INNER JOIN audit_accounts a
  ON a.run_id = '{$run}'
 AND a.source_table = 'tbl_profesor'
 AND a.source_pk = p.id_profesor
INNER JOIN audit_rama_normalization rn
  ON rn.run_id = '{$run}'
 AND rn.id_profesor = p.id_profesor
WHERE a.in_tbl_usuario = 1
  AND a.perfil_state = 'VALID'
  AND a.canonical_status IN ('UNIQUE','CANONICAL_PROVISIONAL')
  AND rn.needs_manual_review = 0
  AND NOT EXISTS (
      SELECT 1 FROM quarantine_accounts q
      WHERE q.run_id = '{$run}'
        AND q.source_table = 'tbl_profesor'
        AND q.source_pk = p.id_profesor
        AND q.status='ACTIVE'
        AND q.quarantine_type IN ('DUPLICADO_NO_CANONICO','DUPLICADO_REVISION_MANUAL','PERFIL_VACIO','PROFESOR_SIN_USUARIO')
  )
";
$r = $m->query($sql);
echo json_encode(['profesores_v2'=>$r->fetch_assoc()['c']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;

$sqlu = "
SELECT COUNT(*) AS c
FROM tbl_usuario u
INNER JOIN audit_accounts a
  ON a.run_id = '{$run}'
 AND a.source_table = 'tbl_usuario'
 AND a.source_pk = u.id_usuario
WHERE a.in_tbl_profesor = 1
  AND NOT EXISTS (
      SELECT 1 FROM audit_accounts ap
      WHERE ap.run_id = '{$run}'
        AND ap.source_table = 'tbl_profesor'
        AND ap.correo = u.correo
        AND (
          ap.perfil_state <> 'VALID'
          OR ap.canonical_status NOT IN ('UNIQUE','CANONICAL_PROVISIONAL')
        )
  )
";
$ru = $m->query($sqlu);
echo json_encode(['usuarios_v2_approx'=>$ru->fetch_assoc()['c']], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
?>
