<?php

if (!function_exists('acelerador_session_start_if_needed')) {
    function acelerador_session_start_if_needed()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('acelerador_normalize_profile')) {
    function acelerador_normalize_profile($perfil)
    {
        return strtoupper(trim((string)$perfil));
    }
}

if (!function_exists('acelerador_is_admin_profile')) {
    function acelerador_is_admin_profile($perfil)
    {
        $perfil = acelerador_normalize_profile($perfil);
        return $perfil === 'ADMIN' || $perfil === 'ADMINISTRADOR';
    }
}

if (!function_exists('require_authenticated_user')) {
    /**
     * @param mysqli $conn
     * @return string
     * @throws RuntimeException
     */
    function require_authenticated_user($conn)
    {
        acelerador_session_start_if_needed();

        $correo = $_SESSION['nombredelusuario'] ?? '';
        if (!is_string($correo) || trim($correo) === '') {
            throw new RuntimeException('UNAUTHORIZED');
        }
        $correo = trim($correo);

        $stmt = mysqli_prepare($conn, 'SELECT correo FROM tbl_usuario WHERE correo = ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('DB_ERROR');
        }
        mysqli_stmt_bind_param($stmt, 's', $correo);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        $exists = mysqli_stmt_num_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        if (!$exists) {
            throw new RuntimeException('UNAUTHORIZED');
        }

        return $correo;
    }
}

if (!function_exists('acelerador_fetch_profesor_context')) {
    /**
     * @param mysqli $conn
     * @param string $correo
     * @return array|null
     * @throws RuntimeException
     */
    function acelerador_fetch_profesor_context($conn, $correo)
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id_profesor, nombre, apellidos, DNI AS dni, ORCID AS orcid, correo, departamento, telefono, facultad, rama, perfil FROM tbl_profesor WHERE correo = ? LIMIT 1'
        );
        if (!$stmt) {
            throw new RuntimeException('DB_ERROR');
        }

        mysqli_stmt_bind_param($stmt, 's', $correo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return null;
        }

        return [
            'id_profesor' => (int)($row['id_profesor'] ?? 0),
            'nombre' => (string)($row['nombre'] ?? ''),
            'apellidos' => (string)($row['apellidos'] ?? ''),
            'dni' => (string)($row['dni'] ?? ''),
            'orcid' => (string)($row['orcid'] ?? ''),
            'correo' => (string)($row['correo'] ?? ''),
            'departamento' => (string)($row['departamento'] ?? ''),
            'telefono' => (string)($row['telefono'] ?? ''),
            'facultad' => (string)($row['facultad'] ?? ''),
            'rama' => (string)($row['rama'] ?? ''),
            'perfil' => acelerador_normalize_profile($row['perfil'] ?? ''),
        ];
    }
}

if (!function_exists('acelerador_require_profesor_context')) {
    /**
     * @param mysqli $conn
     * @param array<int,string> $allowedProfiles
     * @return array
     * @throws RuntimeException
     */
    function acelerador_require_profesor_context($conn, array $allowedProfiles = [])
    {
        $correo = require_authenticated_user($conn);
        $context = acelerador_fetch_profesor_context($conn, $correo);

        if ($context === null) {
            throw new RuntimeException('UNAUTHORIZED');
        }

        if (count($allowedProfiles) > 0) {
            $allowed = [];
            foreach ($allowedProfiles as $perfil) {
                $allowed[] = acelerador_normalize_profile($perfil);
            }

            if (!in_array($context['perfil'], $allowed, true)) {
                throw new RuntimeException('FORBIDDEN');
            }
        }

        $_SESSION['nombredelusuario'] = (string)$context['correo'];
        $_SESSION['perfil_usuario'] = (string)$context['perfil'];

        return $context;
    }
}

if (!function_exists('require_tutor_context')) {
    /**
     * @param mysqli $conn
     * @param string $correo
     * @return array{id_profesor:int,nombre:string,apellidos:string}
     * @throws RuntimeException
     */
    function require_tutor_context($conn, $correo)
    {
        $context = acelerador_fetch_profesor_context($conn, $correo);
        if ($context === null) {
            throw new RuntimeException('UNAUTHORIZED');
        }

        if ($context['perfil'] !== 'TUTOR') {
            throw new RuntimeException('FORBIDDEN');
        }

        $_SESSION['perfil_usuario'] = 'TUTOR';

        return [
            'id_profesor' => (int)$context['id_profesor'],
            'nombre' => (string)$context['nombre'],
            'apellidos' => (string)$context['apellidos'],
        ];
    }
}

if (!function_exists('acelerador_redirect_for_auth_error')) {
    /**
     * @param RuntimeException $e
     * @return void
     */
    function acelerador_redirect_for_auth_error($e)
    {
        if ($e->getMessage() === 'FORBIDDEN') {
            acelerador_session_start_if_needed();
            $perfilSesion = acelerador_normalize_profile($_SESSION['perfil_usuario'] ?? '');

            if ($perfilSesion === 'TUTOR') {
                header('Location: panel_tutor.php');
                exit();
            }

            if (acelerador_is_admin_profile($perfilSesion)) {
                header('Location: panel_admin.php');
                exit();
            }

            header('Location: panel_profesor.php');
            exit();
        }

        header('Location: ../../acelerador_login/fronten/index.php');
        exit();
    }
}
