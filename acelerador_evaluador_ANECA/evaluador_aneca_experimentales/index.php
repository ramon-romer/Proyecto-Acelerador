<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Evaluador ANECA - Experimentales</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f5f5f5; }
        .contenedor { max-width: 1100px; margin: auto; background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        textarea { width: 100%; height: 520px; font-family: Consolas, monospace; font-size: 14px; }
        input[type="text"], select { width: 100%; padding: 8px; margin-bottom: 15px; }
        button { padding: 10px 18px; background: #1f6feb; color: white; border: 0; border-radius: 6px; cursor: pointer; }
        a { color: #1f6feb; text-decoration: none; }
    </style>
</head>
<body>
<div class="contenedor">
    <h1>Evaluador ANECA - Experimentales (PCD/PUP)</h1>

    <p><a href="listado.php">Ver evaluaciones guardadas</a></p>

    <form action="guardar_evaluacion.php" method="post">
        <label>Nombre del candidato</label>
        <input type="text" name="nombre_candidato" required>

        <label>Comité</label>
        <select name="comite" required>
            <option value="I">I</option>
            <option value="II">II</option>
            <option value="III">III</option>
            <option value="IV">IV</option>
        </select>

        <label>JSON de entrada</label>
        <textarea name="json_entrada" required>{
  "bloque_1": {
    "publicaciones": [
      {
        "tipo": "articulo",
        "es_valida": true,
        "tipo_indice": "JCR",
        "cuartil": "Q1",
        "tipo_aportacion": "experimental",
        "afinidad": "total",
        "posicion_autor": "intermedio",
        "numero_autores": 8,
        "citas": 35,
        "anios_desde_publicacion": 3
      }
    ],
    "libros": [],
    "proyectos": [
      {
        "es_valido": true,
        "esta_certificado": true,
        "tipo_proyecto": "nacional",
        "rol": "investigador",
        "dedicacion": "parcial",
        "anios_duracion": 3
      }
    ],
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