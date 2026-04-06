<?php

if (!function_exists('acelerador_auth_mask_email')) {
    function acelerador_auth_mask_email($correo)
    {
        $correo = trim((string)$correo);
        if ($correo === '') {
            return '';
        }

        $parts = explode('@', $correo, 2);
        if (count($parts) !== 2) {
            return substr($correo, 0, 2) . '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        if ($local === '') {
            return '***@' . $domain;
        }

        return substr($local, 0, 2) . '***@' . $domain;
    }
}

if (!function_exists('acelerador_auth_log_event')) {
    function acelerador_auth_log_event($event, $correo, array $context = [])
    {
        $safeContext = [];
        foreach ($context as $key => $value) {
            if (stripos((string)$key, 'pass') !== false) {
                continue;
            }
            $safeContext[$key] = $value;
        }

        $payload = [
            'event' => (string)$event,
            'email_masked' => acelerador_auth_mask_email($correo),
            'context' => $safeContext,
        ];

        error_log('[AUTH] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('acelerador_auth_is_bcrypt_hash')) {
    function acelerador_auth_is_bcrypt_hash($value)
    {
        $value = (string)$value;
        if (strlen($value) !== 60) {
            return false;
        }

        return preg_match('/^\$2[aby]\$\d{2}\$[\.\/A-Za-z0-9]{53}$/', $value) === 1;
    }
}

if (!function_exists('acelerador_auth_fetch_usuario_by_correo')) {
    function acelerador_auth_fetch_usuario_by_correo($conn, $correo)
    {
        $stmt = mysqli_prepare($conn, 'SELECT id_usuario, correo, password FROM tbl_usuario WHERE correo = ? LIMIT 2');
        if (!$stmt) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_DB_QUERY', 'context' => ['stage' => 'fetch_usuario_prepare']];
        }

        mysqli_stmt_bind_param($stmt, 's', $correo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        $count = count($rows);
        if ($count === 0) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_USER_NOT_FOUND', 'context' => []];
        }

        if ($count > 1) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_DUPLICATE_USER', 'context' => ['matches' => $count]];
        }

        return ['ok' => true, 'row' => $rows[0]];
    }
}

if (!function_exists('acelerador_auth_resolve_unique_profile')) {
    function acelerador_auth_resolve_unique_profile($conn, $correo)
    {
        $stmt = mysqli_prepare($conn, 'SELECT perfil FROM tbl_profesor WHERE correo = ? LIMIT 3');
        if (!$stmt) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_DB_QUERY', 'context' => ['stage' => 'resolve_profile_prepare']];
        }

        mysqli_stmt_bind_param($stmt, 's', $correo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        $totalRows = count($rows);
        $routableProfiles = ['TUTOR', 'PROFESOR', 'ADMIN', 'ADMINISTRADOR'];
        $normalizedRoutable = [];

        foreach ($rows as $row) {
            $perfil = strtoupper(trim((string)($row['perfil'] ?? '')));
            if (in_array($perfil, $routableProfiles, true)) {
                $normalizedRoutable[] = $perfil;
            }
        }

        if ($totalRows !== 1 || count($normalizedRoutable) !== 1) {
            return [
                'ok' => false,
                'event' => 'AUTH_FAIL_PROFILE_AMBIGUOUS',
                'context' => [
                    'profesor_rows' => $totalRows,
                    'routable_rows' => count($normalizedRoutable),
                ],
            ];
        }

        return ['ok' => true, 'perfil' => $normalizedRoutable[0]];
    }
}

if (!function_exists('acelerador_auth_rehash_legacy_password')) {
    function acelerador_auth_rehash_legacy_password($conn, $idUsuario, $legacyStoredPassword, $plainPassword)
    {
        $newHash = password_hash($plainPassword, PASSWORD_BCRYPT);
        if ($newHash === false) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_REHASH_HASH'];
        }

        $stmt = mysqli_prepare($conn, 'UPDATE tbl_usuario SET password = ? WHERE id_usuario = ? AND password = ?');
        if (!$stmt) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_REHASH_PREPARE'];
        }

        mysqli_stmt_bind_param($stmt, 'sis', $newHash, $idUsuario, $legacyStoredPassword);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected === 1) {
            return ['ok' => true, 'rehash_applied' => true];
        }

        $stmtVerify = mysqli_prepare($conn, 'SELECT password FROM tbl_usuario WHERE id_usuario = ? LIMIT 1');
        if (!$stmtVerify) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_REHASH_VERIFY_PREPARE'];
        }

        mysqli_stmt_bind_param($stmtVerify, 'i', $idUsuario);
        mysqli_stmt_execute($stmtVerify);
        $result = mysqli_stmt_get_result($stmtVerify);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmtVerify);

        $current = (string)($row['password'] ?? '');
        if (acelerador_auth_is_bcrypt_hash($current) && password_verify($plainPassword, $current)) {
            return ['ok' => true, 'rehash_applied' => true];
        }

        return ['ok' => false, 'event' => 'AUTH_FAIL_REHASH_NOT_APPLIED'];
    }
}

if (!function_exists('acelerador_authenticate_usuario')) {
    function acelerador_authenticate_usuario($conn, $correo, $plainPassword)
    {
        $correo = trim((string)$correo);
        $plainPassword = (string)$plainPassword;

        if ($correo === '' || $plainPassword === '') {
            return ['ok' => false, 'event' => 'AUTH_FAIL_INPUT', 'context' => []];
        }

        $usuarioLookup = acelerador_auth_fetch_usuario_by_correo($conn, $correo);
        if (!$usuarioLookup['ok']) {
            return $usuarioLookup;
        }

        $profileLookup = acelerador_auth_resolve_unique_profile($conn, $correo);
        if (!$profileLookup['ok']) {
            return $profileLookup;
        }

        $usuario = $usuarioLookup['row'];
        $idUsuario = (int)($usuario['id_usuario'] ?? 0);
        $storedPassword = (string)($usuario['password'] ?? '');

        if ($idUsuario <= 0) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_USER_NOT_FOUND', 'context' => []];
        }

        if (acelerador_auth_is_bcrypt_hash($storedPassword)) {
            if (!password_verify($plainPassword, $storedPassword)) {
                return ['ok' => false, 'event' => 'AUTH_FAIL_PASSWORD', 'context' => ['mode' => 'bcrypt']];
            }

            return [
                'ok' => true,
                'event' => 'AUTH_OK_BCRYPT',
                'perfil' => $profileLookup['perfil'],
                'id_usuario' => $idUsuario,
                'rehash_applied' => false,
            ];
        }

        if (!hash_equals($storedPassword, $plainPassword)) {
            return ['ok' => false, 'event' => 'AUTH_FAIL_PASSWORD', 'context' => ['mode' => 'legacy']];
        }

        $rehash = acelerador_auth_rehash_legacy_password($conn, $idUsuario, $storedPassword, $plainPassword);
        if (!$rehash['ok']) {
            return ['ok' => false, 'event' => $rehash['event'], 'context' => []];
        }

        return [
            'ok' => true,
            'event' => 'AUTH_OK_LEGACY_REHASH',
            'perfil' => $profileLookup['perfil'],
            'id_usuario' => $idUsuario,
            'rehash_applied' => true,
        ];
    }
}

