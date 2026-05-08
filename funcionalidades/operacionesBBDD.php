<?php
    /**
     * INSERTAR - Inserta un registro en la tabla indicada
     * @param $conn     - Conexión a la base de datos (mysqli)
     * @param $tabla    - Nombre de la tabla (ej: "tbl_usuario")
     * @param $datos    - Array asociativo columna => valor (ej: ["correo" => "a@b.com", "password" => "123"])
     * @return bool     - true si se insertó correctamente, false si falló
     */
    function insertar($conn, $tabla, $datos) {
        // 0. Prevenir error si se llama a la función sin datos
        if (empty($datos)) {
            return false;
        }

        // 1. Obtenemos las columnas protegidas con backticks para evitar inyección SQL (ej: `correo`, `password`)
        $columnas = "`" . implode("`, `", array_keys($datos)) . "`";
        
        // 2. Protegemos los valores contra inyección SQL. Soporte explícito para valores NULL.
        $valores = implode(", ", array_map(function($v) use ($conn) {
            if ($v === null) return "NULL";
            return "'" . mysqli_real_escape_string($conn, $v) . "'";
        }, array_values($datos)));

        // 3. Construimos la consulta SQL final (protegiendo también la tabla)
        $sql = "INSERT INTO `$tabla` ($columnas) VALUES ($valores)";
        
        // 4. Ejecutamos la consulta y devolvemos true/false
        return mysqli_query($conn, $sql);
    }

    /**
     * SELECCIONAR - Obtiene registros de una tabla
     * @param $conn     - Conexión a la base de datos (mysqli)
     * @param $tabla    - Nombre de la tabla (ej: "tbl_usuario")
     * @param $where    - Condición WHERE como string, opcional (ej: "id_usuario = 1" o "correo = 'a@b.com' AND password = '123'")
     * @return array    - Array con todos los registros encontrados, cada uno como array asociativo
     */
    function seleccionar($conn, $tabla, $where = "") {
        // 1. Consulta base protegiendo la tabla
        $sql = "SELECT * FROM `$tabla`";
        
        // 2. Si nos pasan un filtro WHERE, lo pegamos al final de la consulta
        if ($where != "") {
            $sql .= " WHERE $where";
        }

        // 3. Ejecutamos la consulta
        $resultado = mysqli_query($conn, $sql);
        $filas = [];
        
        // 4. Transformamos cada fila de la tabla en un array (si la consulta fue exitosa)
        if ($resultado) {
            while ($fila = mysqli_fetch_assoc($resultado)) {
                $filas[] = $fila;
            }
        }
        
        // 5. Devolvemos todos los resultados juntos
        return $filas;
    }

    /**
     * ELIMINAR - Borra registros de una tabla
     * @param $conn     - Conexión a la base de datos (mysqli)
     * @param $tabla    - Nombre de la tabla (ej: "tbl_usuario")
     * @param $where    - Condición WHERE como string, obligatorio (ej: "id_usuario = 1")
     * @return bool     - true si se eliminó correctamente, false si falló
     */
    function eliminar($conn, $tabla, $where) {
        // 0. Seguridad CRÍTICA: Evitar que un WHERE vacío borre toda la tabla por accidente
        if (trim($where) === "") {
            return false;
        }

        // 1. Construimos la consulta de borrado protegiendo la tabla
        $sql = "DELETE FROM `$tabla` WHERE $where";
        
        // 2. Ejecutamos y devolvemos el resultado
        return mysqli_query($conn, $sql);
    }
?>