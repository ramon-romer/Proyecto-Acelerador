<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Evaluador ANECA</title>
    <style>
        :root {
            --azul-900:#173a77; --azul-700:#2a62bf; --blanco:#fff; --gris:#e8eef8; --texto:#162033;
        }
        * { box-sizing:border-box; }
        body {
            margin:0; font-family:Arial,sans-serif; min-height:100vh; color:white;
            background: radial-gradient(circle at top left, rgba(255,255,255,.14), transparent 28%), linear-gradient(135deg, #1e3c72, #2a5298);
            display:flex; align-items:center; justify-content:center; padding:24px;
        }
        .container { width:min(1040px, 100%); }
        .hero { text-align:center; margin-bottom:28px; }
        .hero h1 { margin:0 0 10px; font-size:42px; }
        .hero p { margin:0 auto; max-width:760px; color:rgba(255,255,255,.92); }
        .grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(210px,1fr)); gap:18px; }
        .card {
            background: rgba(255,255,255,.98); color:var(--texto); border-radius:20px; padding:22px;
            box-shadow:0 18px 45px rgba(7, 22, 52, .18); cursor:pointer; transition:.18s transform ease, .18s box-shadow ease;
        }
        .card:hover { transform:translateY(-3px); box-shadow:0 24px 52px rgba(7, 22, 52, .24); }
        .card h2 { margin:0 0 8px; color:var(--azul-900); font-size:24px; }
        .card p { margin:0; color:#475467; line-height:1.45; }
        .tag { display:inline-block; margin-bottom:12px; padding:6px 10px; border-radius:999px; background:#eaf1ff; color:var(--azul-900); font-weight:bold; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
    </style>
</head>
<body>
<div class="container">
    <div class="hero">
        <h1>Portal Evaluador ANECA</h1>
        <p>Acceso unificado a los módulos de evaluación. Todos los evaluadores mantienen ahora una navegación coherente entre portal, listado, expediente extraído y detalle de evaluación.</p>
    </div>
    <div class="grid">
        <div class="card" onclick="ir('humanidades')"><span class="tag">Área</span><h2>Humanidades</h2><p>Evaluación y revisión manual integrada con el portal.</p></div>
        <div class="card" onclick="ir('csyj')"><span class="tag">Área</span><h2>CSyJ</h2><p>Evaluación y revisión manual integrada con el portal.</p></div>
        <div class="card" onclick="ir('experimentales')"><span class="tag">Área</span><h2>Experimentales</h2><p>Evaluación y revisión manual integrada con el portal.</p></div>
        <div class="card" onclick="ir('salud')"><span class="tag">Área</span><h2>Salud</h2><p>Evaluación y revisión manual integrada con el portal.</p></div>
        <div class="card" onclick="ir('tecnicas')"><span class="tag">Área</span><h2>Técnicas</h2><p>Evaluación y revisión manual integrada con el portal.</p></div>
    </div>
</div>
<script>
const BASE_URL = '/Proyecto-Acelerador/evaluador/';
function ir(area) { window.location.href = BASE_URL + 'evaluador_aneca_' + area + '/'; }
</script>
</body>
</html>
