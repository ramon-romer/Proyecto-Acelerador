<?php

if (!function_exists('acelerador_session_start_if_needed')) {
    function acelerador_session_start_if_needed()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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

if (!function_exists('require_tutor_context')) {
    /**
     * @param mysqli $conn
     * @param string $correo
     * @return array{id_profesor:int,nombre:string,apellidos:string}
     * @throws RuntimeException
     */
    function require_tutor_context($conn, $correo)
    {
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id_profesor, nombre, apellidos, perfil FROM tbl_profesor WHERE correo = ? LIMIT 1'
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
            throw new RuntimeException('UNAUTHORIZED');
        }

        $perfil = strtoupper(trim((string)($row['perfil'] ?? '')));
        if ($perfil !== 'TUTOR') {
            throw new RuntimeException('FORBIDDEN');
        }

        return [
            'id_profesor' => (int)($row['id_profesor'] ?? 0),
            'nombre' => (string)($row['nombre'] ?? ''),
            'apellidos' => (string)($row['apellidos'] ?? ''),
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
            header('Location: panel_profesor.php');
            exit();
        }

        header('Location: ../../acelerador_login/fronten/index.php');
        exit();
    }
}

