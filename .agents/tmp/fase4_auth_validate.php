<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../acelerador_login/fronten/lib/auth_password.php';

date_default_timezone_set('Europe/Madrid');

$db = new mysqli('localhost', 'root', '', 'acelerador_staging_20260406');
$db->set_charset('utf8mb4');
$db->begin_transaction();

$runTag = 'F4_AUTH_' . date('Ymd_His');
$seed = (string)time();

$results = [];

function add_result(array &$results, string $case, bool $ok, array $details = []): void
{
    $results[] = [
        'case' => $case,
        'ok' => $ok,
        'details' => $details,
    ];
}

function route_for_profile(string $perfil): ?string
{
    $perfil = strtoupper(trim($perfil));
    if ($perfil === 'TUTOR') {
        return '../../acelerador_panel/fronten/panel_tutor.php';
    }
    if ($perfil === 'PROFESOR') {
        return '../../acelerador_panel/fronten/panel_profesor.php';
    }
    if ($perfil === 'ADMIN' || $perfil === 'ADMINISTRADOR') {
        return '../../acelerador_panel/fronten/panel_admin.php';
    }
    return null;
}

function insert_usuario(mysqli $db, string $correo, string $password): int
{
    $stmt = $db->prepare('INSERT INTO tbl_usuario (correo, password) VALUES (?, ?)');
    $stmt->bind_param('ss', $correo, $password);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function insert_profesor(
    mysqli $db,
    string $orcid,
    string $correo,
    string $perfil,
    string $password,
    string $nombre
): int {
    $apellidos = 'Test ' . $nombre;
    $dni = '1' . substr(preg_replace('/\D+/', '', $orcid), -7) . 'A';
    $telefono = 600000000 + random_int(1, 99999999) % 99999999;
    $facultad = 'Facultad QA';
    $departamento = 'Dept QA';
    $rama = 'SALUD';

    $stmt = $db->prepare('INSERT INTO tbl_profesor (ORCID, nombre, apellidos, password, DNI, telefono, perfil, facultad, departamento, correo, rama) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssisssss', $orcid, $nombre, $apellidos, $password, $dni, $telefono, $perfil, $facultad, $departamento, $correo, $rama);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

function get_usuario_password(mysqli $db, int $idUsuario): string
{
    $stmt = $db->prepare('SELECT password FROM tbl_usuario WHERE id_usuario = ? LIMIT 1');
    $stmt->bind_param('i', $idUsuario);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return (string)($row['password'] ?? '');
}

try {
    // Fixtures base
    $bcryptTutorEmail = "{$runTag}.tutor.{$seed}@qa.local";
    $bcryptTutorPass = 'Bcrypt#Tutor2026!';
    $bcryptTutorHash = password_hash($bcryptTutorPass, PASSWORD_BCRYPT);
    $bcryptTutorUserId = insert_usuario($db, $bcryptTutorEmail, $bcryptTutorHash);
    insert_profesor($db, '0000-0000-0000-9101', $bcryptTutorEmail, 'TUTOR', $bcryptTutorHash, 'TutorBcrypt');

    $legacyProfEmail = "{$runTag}.prof.{$seed}@qa.local";
    $legacyProfPass = 'Legacy#Prof2026!';
    $legacyProfUserId = insert_usuario($db, $legacyProfEmail, $legacyProfPass);
    insert_profesor($db, '0000-0000-0000-9102', $legacyProfEmail, 'PROFESOR', $legacyProfPass, 'ProfLegacy');

    $adminEmail = "{$runTag}.admin.{$seed}@qa.local";
    $adminPass = 'Bcrypt#Admin2026!';
    $adminHash = password_hash($adminPass, PASSWORD_BCRYPT);
    insert_usuario($db, $adminEmail, $adminHash);
    insert_profesor($db, '0000-0000-0000-9103', $adminEmail, 'ADMIN', $adminHash, 'AdminBcrypt');

    // 1) bcrypt valido
    $r1 = acelerador_authenticate_usuario($db, $bcryptTutorEmail, $bcryptTutorPass);
    add_result($results, 'bcrypt_valido',
        ($r1['ok'] ?? false) && ($r1['event'] ?? '') === 'AUTH_OK_BCRYPT' && ($r1['perfil'] ?? '') === 'TUTOR',
        ['event' => $r1['event'] ?? null, 'perfil' => $r1['perfil'] ?? null]
    );

    // 2) legacy valido con rehash
    $beforeLegacy = get_usuario_password($db, $legacyProfUserId);
    $r2 = acelerador_authenticate_usuario($db, $legacyProfEmail, $legacyProfPass);
    $afterLegacy = get_usuario_password($db, $legacyProfUserId);
    $rehashOk = ($beforeLegacy === $legacyProfPass)
        && ($r2['ok'] ?? false)
        && (($r2['event'] ?? '') === 'AUTH_OK_LEGACY_REHASH')
        && acelerador_auth_is_bcrypt_hash($afterLegacy)
        && password_verify($legacyProfPass, $afterLegacy);
    add_result($results, 'legacy_valido_con_rehash', $rehashOk, ['event' => $r2['event'] ?? null]);

    // 3) usuario inexistente
    $r3 = acelerador_authenticate_usuario($db, "{$runTag}.inexistente@qa.local", 'NoExiste#2026!');
    add_result($results, 'usuario_inexistente',
        !($r3['ok'] ?? false) && ($r3['event'] ?? '') === 'AUTH_FAIL_USER_NOT_FOUND',
        ['event' => $r3['event'] ?? null]
    );

    // 4) password incorrecta
    $r4 = acelerador_authenticate_usuario($db, $bcryptTutorEmail, 'Incorrecta#2026!');
    add_result($results, 'password_incorrecta',
        !($r4['ok'] ?? false) && ($r4['event'] ?? '') === 'AUTH_FAIL_PASSWORD',
        ['event' => $r4['event'] ?? null, 'mode' => $r4['context']['mode'] ?? null]
    );

    // 5) usuario duplicado en tbl_usuario
    $dupEmail = "{$runTag}.dupuser.{$seed}@qa.local";
    insert_usuario($db, $dupEmail, 'Dup#2026!');
    insert_usuario($db, $dupEmail, 'Dup#2026!');
    insert_profesor($db, '0000-0000-0000-9104', $dupEmail, 'TUTOR', 'Dup#2026!', 'DupUser');
    $r5 = acelerador_authenticate_usuario($db, $dupEmail, 'Dup#2026!');
    add_result($results, 'usuario_duplicado_tbl_usuario',
        !($r5['ok'] ?? false) && ($r5['event'] ?? '') === 'AUTH_FAIL_DUPLICATE_USER',
        ['event' => $r5['event'] ?? null, 'matches' => $r5['context']['matches'] ?? null]
    );

    // 6) perfil ambiguo en tbl_profesor
    $ambEmail = "{$runTag}.ambperfil.{$seed}@qa.local";
    $ambPass = 'Amb#2026!';
    $ambHash = password_hash($ambPass, PASSWORD_BCRYPT);
    insert_usuario($db, $ambEmail, $ambHash);
    insert_profesor($db, '0000-0000-0000-9105', $ambEmail, 'TUTOR', $ambHash, 'AmbTutor');
    insert_profesor($db, '0000-0000-0000-9106', $ambEmail, 'PROFESOR', $ambHash, 'AmbProfesor');
    $r6 = acelerador_authenticate_usuario($db, $ambEmail, $ambPass);
    add_result($results, 'perfil_ambiguo_tbl_profesor',
        !($r6['ok'] ?? false) && ($r6['event'] ?? '') === 'AUTH_FAIL_PROFILE_AMBIGUOUS',
        ['event' => $r6['event'] ?? null, 'profesor_rows' => $r6['context']['profesor_rows'] ?? null]
    );

    // 7) redireccion correcta por rol
    $rTutor = acelerador_authenticate_usuario($db, $bcryptTutorEmail, $bcryptTutorPass);
    $rProf = acelerador_authenticate_usuario($db, $legacyProfEmail, $legacyProfPass);
    $rAdmin = acelerador_authenticate_usuario($db, $adminEmail, $adminPass);

    $routeTutorOk = ($rTutor['ok'] ?? false) && route_for_profile((string)$rTutor['perfil']) === '../../acelerador_panel/fronten/panel_tutor.php';
    $routeProfOk = ($rProf['ok'] ?? false) && route_for_profile((string)$rProf['perfil']) === '../../acelerador_panel/fronten/panel_profesor.php';
    $routeAdminOk = ($rAdmin['ok'] ?? false) && route_for_profile((string)$rAdmin['perfil']) === '../../acelerador_panel/fronten/panel_admin.php';
    $routeAdminStrOk = route_for_profile('ADMINISTRADOR') === '../../acelerador_panel/fronten/panel_admin.php';

    add_result($results, 'redireccion_correcta_por_rol',
        $routeTutorOk && $routeProfOk && $routeAdminOk && $routeAdminStrOk,
        [
            'tutor_route_ok' => $routeTutorOk,
            'profesor_route_ok' => $routeProfOk,
            'admin_route_ok' => $routeAdminOk,
            'administrador_branch_ok' => $routeAdminStrOk,
            'nota_administrador' => 'No insertable en staging por ENUM actual; se valida rama de redireccion.'
        ]
    );

    $passed = count(array_filter($results, static fn($x) => $x['ok']));
    $total = count($results);

    $output = [
        'run_tag' => $runTag,
        'database' => 'acelerador_staging_20260406',
        'transaction' => 'ROLLBACK',
        'summary' => ['passed' => $passed, 'total' => $total],
        'results' => $results,
    ];

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

    $db->rollback();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
?>
