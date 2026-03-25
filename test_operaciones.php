<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulación - Operaciones BBDD</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #1a1a2e;
            color: #eee;
            padding: 30px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #e94560;
            font-size: 2em;
        }
        .paso {
            background: #16213e;
            border-left: 4px solid #e94560;
            border-radius: 8px;
            padding: 20px 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .paso h2 {
            color: #e94560;
            margin-bottom: 10px;
            font-size: 1.2em;
        }
        .paso .codigo {
            background: #0f3460;
            padding: 12px 16px;
            border-radius: 6px;
            font-family: 'Consolas', monospace;
            font-size: 0.9em;
            color: #a8d8ea;
            margin: 10px 0;
            overflow-x: auto;
        }
        .resultado {
            margin-top: 10px;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
        }
        .ok { background: #1b4332; color: #95d5b2; border: 1px solid #2d6a4f; }
        .error { background: #641220; color: #f5c6cb; border: 1px solid #a4161a; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px 14px;
            text-align: left;
            border: 1px solid #0f3460;
        }
        th { background: #e94560; color: #fff; }
        td { background: #16213e; }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <h1>🧪 Simulación de operacionesBBDD.php</h1>

    <?php
        // 1. Conexión
        include("acelerador_login/fronten/config.php");

        // 2. Cargar funciones
        include("operacionesBBDD.php");
    ?>

    <!-- ============================== -->
    <!-- PASO 1: INSERT                 -->
    <!-- ============================== -->
    <div class="paso">
        <h2>📥 PASO 1 — INSERT (Insertar un profesor)</h2>
        <div class="codigo">
            insertar($conn, "tbl_profesor", [<br>
            &nbsp;&nbsp;"nombre" => "Luis",<br>
            &nbsp;&nbsp;"apellidos" => "García López",<br>
            &nbsp;&nbsp;"password" => "mipass123",<br>
            &nbsp;&nbsp;"DNI" => "12345678A",<br>
            &nbsp;&nbsp;"perfil" => "Investigador",<br>
            &nbsp;&nbsp;"correo" => "luis@ceu.es"<br>
            ]);
        </div>
        <?php
            // Ejecutamos el INSERT
            $resultInsert = insertar($conn, "tbl_profesor", [
                "nombre"    => "Luis",
                "apellidos" => "García López",
                "password"  => "mipass123",
                "DNI"       => "12345678A",
                "perfil"    => "Investigador",
                "correo"    => "luis@ceu.es"
            ]);

            if ($resultInsert) {
                echo '<div class="resultado ok">✅ INSERT ejecutado correctamente. Profesor "Luis García López" insertado.</div>';
            } else {
                echo '<div class="resultado error">❌ Error al insertar: ' . mysqli_error($conn) . '</div>';
            }
        ?>
    </div>

    <!-- ============================== -->
    <!-- PASO 2: SELECT                 -->
    <!-- ============================== -->
    <div class="paso">
        <h2>🔍 PASO 2 — SELECT (Buscar el profesor insertado)</h2>
        <div class="codigo">
            $profesores = seleccionar($conn, "tbl_profesor", "DNI = '12345678A'");
        </div>
        <?php
            // Ejecutamos el SELECT
            $profesores = seleccionar($conn, "tbl_profesor", "DNI = '12345678A'");

            if (count($profesores) > 0) {
                echo '<div class="resultado ok">✅ SELECT ejecutado. Se encontraron ' . count($profesores) . ' resultado(s):</div>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Nombre</th><th>Apellidos</th><th>DNI</th><th>Perfil</th><th>Correo</th></tr>';
                foreach ($profesores as $prof) {
                    echo '<tr>';
                    echo '<td>' . $prof['id_profesor'] . '</td>';
                    echo '<td>' . $prof['nombre'] . '</td>';
                    echo '<td>' . $prof['apellidos'] . '</td>';
                    echo '<td>' . $prof['DNI'] . '</td>';
                    echo '<td>' . $prof['perfil'] . '</td>';
                    echo '<td>' . $prof['correo'] . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="resultado error">❌ No se encontraron resultados.</div>';
            }
        ?>
    </div>

    <!-- ============================== -->
    <!-- PASO 3: DELETE                 -->
    <!-- ============================== -->
    <div class="paso">
        <h2>🗑️ PASO 3 — DELETE (Eliminar el profesor)</h2>
        <div class="codigo">
            eliminar($conn, "tbl_profesor", "DNI = '12345678A'");
        </div>
        <?php
            // Ejecutamos el DELETE
            $resultDelete = eliminar($conn, "tbl_profesor", "DNI = '12345678A'");

            if ($resultDelete) {
                echo '<div class="resultado ok">✅ DELETE ejecutado. Profesor con DNI "12345678A" eliminado.</div>';
            } else {
                echo '<div class="resultado error">❌ Error al eliminar: ' . mysqli_error($conn) . '</div>';
            }
        ?>
    </div>

    <!-- ============================== -->
    <!-- PASO 4: VERIFICACION           -->
    <!-- ============================== -->
    <div class="paso">
        <h2>🔎 PASO 4 — Verificación (¿Se borró correctamente?)</h2>
        <div class="codigo">
            $verificacion = seleccionar($conn, "tbl_profesor", "DNI = '12345678A'");
        </div>
        <?php
            // Verificamos que se eliminó
            $verificacion = seleccionar($conn, "tbl_profesor", "DNI = '12345678A'");

            if (count($verificacion) === 0) {
                echo '<div class="resultado ok">✅ Verificado: el registro ya NO existe en la tabla. ¡Todo funciona!</div>';
            } else {
                echo '<div class="resultado error">❌ El registro aún existe. Algo falló en el DELETE.</div>';
            }
        ?>
    </div>

    <div class="footer">
        Simulación ejecutada el <?php echo date("d/m/Y H:i:s"); ?> | Proyecto Acelerador CEU
    </div>

    <?php mysqli_close($conn); ?>
</body>
</html>
