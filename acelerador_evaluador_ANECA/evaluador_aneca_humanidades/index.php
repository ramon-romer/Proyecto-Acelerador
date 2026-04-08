<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluador ANECA - Humanidades</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f5f5f5; }
        .contenedor { max-width: 1100px; margin: auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        textarea { width: 100%; height: 520px; font-family: Consolas, monospace; font-size: 14px; }
        input[type="text"] { width: 100%; padding: 8px; margin-bottom: 15px; }
        button { padding: 10px 18px; background: #1f6feb; color: white; border: 0; border-radius: 6px; cursor: pointer; }
        a { color: #1f6feb; text-decoration: none; }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>Evaluador ANECA - Humanidades (PCD/PUP)</h1>

    <p><a href="listado.php">Ver evaluaciones guardadas</a></p>

    <form action="guardar_evaluacion.php" method="post">
        <label>Nombre del candidato</label>
        <input type="text" name="nombre_candidato" required>

        <label>JSON de entrada</label>
        <textarea name="json_entrada" required>{
  "bloque_1": {
    "publicaciones": [
      {
        "tipo": "articulo",
        "es_valida": true,
        "tipo_indice": "ERIH",
        "subtipo_indice": "PLUS",
        "tipo_aportacion": "estudio_fuentes",
        "afinidad": "total",
        "posicion_autor": "autor_unico",
        "numero_autores": 1,
        "citas": 6
      }
    ],
    "libros": [
      {
        "tipo": "libro",
        "es_valido": true,
        "es_libro_investigacion": true,
        "es_autoedicion": false,
        "es_acta_congreso": false,
        "es_labor_edicion": false,
        "nivel_editorial": "prestigiosa",
        "afinidad": "total",
        "posicion_autor": "autor_unico"
      }
    ],
    "proyectos": [],
    "transferencia": [],
    "tesis_dirigidas": [],
    "congresos": [],
    "otros_meritos_investigacion": []
  },
  "bloque_2": {
    "docencia_universitaria": [],
    "evaluacion_docente": [],
    "formacion_docente": [],
    "material_docente": []
  },
  "bloque_3": {
    "formacion_academica": [],
    "experiencia_profesional": []
  },
  "bloque_4": []
}</textarea>

        <br><br>
        <button type="submit">Evaluar y guardar</button>
    </form>
</div>
</body>
</html>