<?php
declare(strict_types=1);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Portal Evaluador ANECA</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
        }

        .container {
            text-align: center;
        }

        h1 {
            margin-bottom: 40px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 220px);
            gap: 20px;
            justify-content: center;
        }

        .btn {
            padding: 20px;
            border-radius: 10px;
            border: none;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            background: white;
            color: #2a5298;
            font-weight: bold;
        }

        .btn:hover {
            transform: scale(1.05);
            background: #f1f1f1;
        }

        .full {
            grid-column: span 2;
        }
    </style>
</head>

<body>

<div class="container">
    <h1>Portal Evaluador ANECA</h1>

    <div class="grid">
        <button class="btn" onclick="ir('humanidades')">Humanidades</button>
        <button class="btn" onclick="ir('csyj')">CSyJ</button>
        <button class="btn" onclick="ir('experimentales')">Experimentales</button>
        <button class="btn" onclick="ir('salud')">Salud</button>
        <button class="btn full" onclick="ir('tecnicas')">Técnicas</button>
    </div>
</div>

<script>
function ir(area) {
    switch(area) {
        case 'humanidades':
            window.location.href = '/evaluador_aneca_humanidades';
            break;
        case 'csyj':
            window.location.href = '/evaluador_aneca_csyj';
            break;
        case 'experimentales':
            window.location.href = '/evaluador_aneca_experimentales';
            break;
        case 'salud':
            window.location.href = '/evaluador_aneca_salud';
            break;
        case 'tecnicas':
            window.location.href = '/evaluador_aneca_tecnicas';
            break;
    }
}
</script>

</body>
</html>
