<?php

if (!function_exists('acelerador_service_prepare')) {
    /**
     * @param mysqli $conn
     * @param string $sql
     * @return mysqli_stmt
     * @throws RuntimeException
     */
    function acelerador_service_prepare($conn, $sql)
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new RuntimeException('DB_ERROR');
        }
        return $stmt;
    }
}

if (!function_exists('get_group_name_for_tutor')) {
    /**
     * @param mysqli $conn
     * @param int $idGrupo
     * @param int $idTutor
     * @return string|null
     */
    function get_group_name_for_tutor($conn, $idGrupo, $idTutor)
    {
        $stmt = acelerador_service_prepare(
            $conn,
            'SELECT nombre FROM tbl_grupo WHERE id_grupo = ? AND id_tutor = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmt, 'ii', $idGrupo, $idTutor);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return null;
        }

        return (string)($row['nombre'] ?? '');
    }
}

if (!function_exists('get_tutor_groups_with_members')) {
    /**
     * @param mysqli $conn
     * @param int $idTutor
     * @return array<int, array<string,mixed>>
     */
    function get_tutor_groups_with_members($conn, $idTutor)
    {
        $sql = '
            SELECT
                p.id_profesor,
                p.ORCID,
                p.nombre,
                p.apellidos,
                p.DNI,
                p.telefono,
                p.perfil,
                p.facultad,
                p.departamento,
                p.correo,
                p.rama,
                g.nombre AS grupo_nombre,
                g.id_grupo
            FROM tbl_grupo g
            LEFT JOIN tbl_grupo_profesor gp ON g.id_grupo = gp.id_grupo
            LEFT JOIN tbl_profesor p ON gp.id_profesor = p.id_profesor
            WHERE g.id_tutor = ?
            ORDER BY g.nombre ASC, p.nombre ASC
        ';

        $stmt = acelerador_service_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $idTutor);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('create_group_for_tutor')) {
    /**
     * @param mysqli $conn
     * @param int $idTutor
     * @param string $nombreGrupo
     * @return array{ok:bool,type:string,message:string}
     */
    function create_group_for_tutor($conn, $idTutor, $nombreGrupo)
    {
        $nombreGrupo = trim($nombreGrupo);
        if ($nombreGrupo === '') {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'El nombre del grupo no puede estar vacio.',
            ];
        }

        $stmtCheck = acelerador_service_prepare(
            $conn,
            'SELECT id_grupo FROM tbl_grupo WHERE nombre = ? AND id_tutor = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmtCheck, 'si', $nombreGrupo, $idTutor);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);
        $exists = mysqli_stmt_num_rows($stmtCheck) > 0;
        mysqli_stmt_close($stmtCheck);

        if ($exists) {
            return [
                'ok' => false,
                'type' => 'warning',
                'message' => 'Ya tienes un grupo con este nombre. Por favor, elige otro.',
            ];
        }

        $stmtInsert = acelerador_service_prepare(
            $conn,
            'INSERT INTO tbl_grupo (nombre, id_tutor) VALUES (?, ?)'
        );
        mysqli_stmt_bind_param($stmtInsert, 'si', $nombreGrupo, $idTutor);
        $ok = mysqli_stmt_execute($stmtInsert);
        mysqli_stmt_close($stmtInsert);

        if (!$ok) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Error al crear el grupo.',
            ];
        }

        return [
            'ok' => true,
            'type' => 'success',
            'message' => 'El grupo <strong>' . htmlspecialchars($nombreGrupo) . '</strong> se ha creado correctamente.',
        ];
    }
}

if (!function_exists('get_group_members_for_tutor')) {
    /**
     * @param mysqli $conn
     * @param int $idGrupo
     * @param int $idTutor
     * @return array<int,array<string,mixed>>
     */
    function get_group_members_for_tutor($conn, $idGrupo, $idTutor)
    {
        if (get_group_name_for_tutor($conn, $idGrupo, $idTutor) === null) {
            return [];
        }

        $sql = '
            SELECT p.id_profesor, p.ORCID, p.nombre, p.apellidos, p.departamento, p.correo
            FROM tbl_profesor p
            INNER JOIN tbl_grupo_profesor gp ON p.id_profesor = gp.id_profesor
            WHERE gp.id_grupo = ?
            ORDER BY p.nombre ASC
        ';
        $stmt = acelerador_service_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $idGrupo);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('add_profesor_to_group_by_orcid')) {
    /**
     * @param mysqli $conn
     * @param int $idGrupo
     * @param int $idTutor
     * @param string $orcid
     * @return array{ok:bool,type:string,message:string}
     */
    function add_profesor_to_group_by_orcid($conn, $idGrupo, $idTutor, $orcid)
    {
        if (get_group_name_for_tutor($conn, $idGrupo, $idTutor) === null) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'El grupo no existe o no pertenece al tutor autenticado.',
            ];
        }

        $orcid = trim($orcid);
        if (!preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $orcid)) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'El ORCID no tiene el formato correcto (0000-0000-0000-0000).',
            ];
        }

        $stmtProfesor = acelerador_service_prepare(
            $conn,
            'SELECT id_profesor, nombre, apellidos FROM tbl_profesor WHERE ORCID = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmtProfesor, 's', $orcid);
        mysqli_stmt_execute($stmtProfesor);
        $resultProfesor = mysqli_stmt_get_result($stmtProfesor);
        $profesor = $resultProfesor ? mysqli_fetch_assoc($resultProfesor) : null;
        mysqli_stmt_close($stmtProfesor);

        if (!$profesor) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'No se encontro ningun profesor con ORCID <strong>' . htmlspecialchars($orcid) . '</strong>.',
            ];
        }

        $idProfesor = (int)($profesor['id_profesor'] ?? 0);
        $stmtDup = acelerador_service_prepare(
            $conn,
            'SELECT 1 FROM tbl_grupo_profesor WHERE id_grupo = ? AND id_profesor = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($stmtDup, 'ii', $idGrupo, $idProfesor);
        mysqli_stmt_execute($stmtDup);
        mysqli_stmt_store_result($stmtDup);
        $duplicated = mysqli_stmt_num_rows($stmtDup) > 0;
        mysqli_stmt_close($stmtDup);

        $nombreCompleto = htmlspecialchars(trim(($profesor['nombre'] ?? '') . ' ' . ($profesor['apellidos'] ?? '')));
        if ($duplicated) {
            return [
                'ok' => false,
                'type' => 'warning',
                'message' => 'El profesor <strong>' . $nombreCompleto . '</strong> ya esta en este grupo.',
            ];
        }

        $stmtInsert = acelerador_service_prepare(
            $conn,
            'INSERT INTO tbl_grupo_profesor (id_grupo, id_profesor) VALUES (?, ?)'
        );
        mysqli_stmt_bind_param($stmtInsert, 'ii', $idGrupo, $idProfesor);
        $ok = mysqli_stmt_execute($stmtInsert);
        mysqli_stmt_close($stmtInsert);

        if (!$ok) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'No se pudo anadir al profesor al grupo.',
            ];
        }

        return [
            'ok' => true,
            'type' => 'success',
            'message' => 'Profesor <strong>' . $nombreCompleto . '</strong> anadido correctamente.',
        ];
    }
}

if (!function_exists('remove_profesor_from_group')) {
    /**
     * @param mysqli $conn
     * @param int $idGrupo
     * @param int $idTutor
     * @param int $idProfesor
     * @return array{ok:bool,type:string,message:string}
     */
    function remove_profesor_from_group($conn, $idGrupo, $idTutor, $idProfesor)
    {
        if (get_group_name_for_tutor($conn, $idGrupo, $idTutor) === null) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'El grupo no existe o no pertenece al tutor autenticado.',
            ];
        }

        if ($idProfesor <= 0) {
            return [
                'ok' => false,
                'type' => 'danger',
                'message' => 'Profesor invalido.',
            ];
        }

        $stmtDelete = acelerador_service_prepare(
            $conn,
            'DELETE FROM tbl_grupo_profesor WHERE id_grupo = ? AND id_profesor = ?'
        );
        mysqli_stmt_bind_param($stmtDelete, 'ii', $idGrupo, $idProfesor);
        mysqli_stmt_execute($stmtDelete);
        $affected = mysqli_stmt_affected_rows($stmtDelete);
        mysqli_stmt_close($stmtDelete);

        if ($affected <= 0) {
            return [
                'ok' => false,
                'type' => 'warning',
                'message' => 'El profesor no estaba asignado a este grupo.',
            ];
        }

        return [
            'ok' => true,
            'type' => 'success',
            'message' => 'Profesor eliminado del grupo correctamente.',
        ];
    }
}

