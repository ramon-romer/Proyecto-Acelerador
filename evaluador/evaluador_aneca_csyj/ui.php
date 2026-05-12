<?php
declare(strict_types=1);

function csyj_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csyj_portal_url(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $perfil = $_SESSION['perfil_usuario'] ?? 'PROFESOR';
    
    if ($perfil === 'TUTOR') {
        return '../../acelerador_panel/fronten/panel_tutor.php';
    }
    return '../../acelerador_panel/fronten/panel_profesor.php';
}

function csyj_index_url(): string
{
    return 'index.php';
}

function csyj_listado_url(): string
{
    return 'listado.php';
}

function csyj_render_layout_start(string $title, string $subtitle = '', array $breadcrumbs = [], array $actions = []): void
{
    echo '<!DOCTYPE html>';
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . csyj_h($title) . '</title>';
    ?>
    <style>
        :root {
            --azul-900: #173a77;
            --azul-800: #1e4d9b;
            --azul-700: #2a62bf;
            --azul-glass: rgba(20, 88, 204, 0.4);
            --blanco: #ffffff;
            --blanco-alpha: rgba(255, 255, 255, 0.1);
            --verde: #4ade80;
            --verde-fondo: rgba(74, 222, 128, 0.15);
            --rojo: #f87171;
            --rojo-fondo: rgba(248, 113, 113, 0.15);
            --sombra: 0 8px 32px rgba(0, 0, 0, 0.37);
            --radio: 24px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('../../acelerador_panel/fronten/img/Image (3).jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
            background-attachment: fixed;
            color: #e2e8f0;
            min-height: 100vh;
        }
        a { color: #60a5fa; text-decoration: none; transition: color 0.2s; }
        a:hover { color: #93c5fd; text-decoration: none; }
        .shell { max-width: 1280px; margin: 0 auto; padding: 40px 24px; }
        .hero {
            background: var(--azul-glass);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: var(--blanco);
            border-radius: var(--radio);
            padding: 30px 35px;
            box-shadow: var(--sombra);
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .breadcrumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 14px;
            margin-bottom: 15px;
            opacity: .7;
            font-weight: 500;
        }
        .breadcrumbs a { color: #fff; }
        .breadcrumbs span { color: #94a3b8; }
        .hero h1 { margin: 0; font-size: 36px; font-weight: 800; letter-spacing: -0.02em; }
        .hero p { margin: 12px 0 0; max-width: 850px; color: rgba(255,255,255,.7); font-size: 18px; }
        .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .page-body { display: grid; gap: 24px; }
        .card {
            background: var(--azul-glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: var(--radio);
            box-shadow: var(--sombra);
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .card h2, .card h3 { margin-top: 0; font-weight: 700; color: #fff; }
        .muted { color: #94a3b8; }
        .meta-grid, .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 18px;
        }
        .metric {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            padding: 18px 20px;
            transition: background 0.2s;
        }
        .metric:hover { background: rgba(255, 255, 255, 0.08); }
        .metric .label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 8px;
            font-weight: 700;
        }
        .metric .value {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
        }
        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .bloque {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 22px;
            margin-bottom: 22px;
        }
        .bloque h2 { margin-top: 0; font-size: 20px; }
        .bloque p.hint { margin-top: -4px; color: #94a3b8; font-size: 14px; }
        .fila {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            padding: 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.02);
        }
        .section-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .empty-note {
            padding: 15px 18px;
            border: 1px dashed rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.01);
            margin-bottom: 15px;
            text-align: center;
        }
        .btn, button, input[type="submit"] {
            appearance: none;
            border: 0;
            border-radius: 50px;
            background: #1458cc;
            color: var(--blanco);
            padding: 12px 24px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(20, 88, 204, 0.3);
        }
        .btn:hover, button:hover, input[type="submit"]:hover { 
            transform: translateY(-2px); 
            background: #1e63e6; 
            box-shadow: 0 6px 16px rgba(20, 88, 204, 0.4);
            text-decoration: none; 
        }
        .btn.secondary, .secondary, .btn-sec { 
            background: rgba(255, 255, 255, 0.1); 
            color: #fff; 
            box-shadow: none;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn.secondary:hover { background: rgba(255, 255, 255, 0.2); }
        .btn.light { background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.2); }
        .btn.light:hover { background: rgba(255,255,255,.25); }
        .btn.outline {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .btn.outline:hover { background: rgba(255, 255, 255, 0.1); border-color: #fff; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: rgba(255, 255, 255, 0.8); font-size: 14px; }
        input[type="text"], input[type="file"], input[type="number"], select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            background: rgba(0, 0, 0, 0.2);
            color: #fff;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: rgba(255, 255, 255, 0.3);
            background: rgba(0, 0, 0, 0.3);
        }
        textarea { min-height: 120px; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }
        .form-actions { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 25px; }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 20px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        th, td {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 15px 20px;
            text-align: left;
            vertical-align: middle;
        }
        th { background: rgba(255, 255, 255, 0.05); color: rgba(255, 255, 255, 0.6); font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255, 255, 255, 0.03); }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 50px;
            padding: 6px 14px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .badge.success { color: #4ade80; background: rgba(74, 222, 128, 0.15); border: 1px solid rgba(74, 222, 128, 0.2); }
        .badge.danger { color: #f87171; background: rgba(248, 113, 113, 0.15); border: 1px solid rgba(248, 113, 113, 0.2); }
        .badge.neutral { color: #94a3b8; background: rgba(148, 163, 184, 0.1); border: 1px solid rgba(148, 163, 184, 0.2); }
        .stack { display: grid; gap: 24px; }
        details {
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            background: rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        summary { cursor: pointer; padding: 18px 22px; font-weight: 700; color: #fff; }
        pre {
            margin: 0;
            padding: 20px;
            overflow: auto;
            background: #0f172a;
            color: #94a3b8;
            font-size: 13px;
            white-space: pre-wrap;
            word-break: break-word;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        ul, ol { padding-left: 20px; }
        .split { display: grid; grid-template-columns: 1.2fr .8fr; gap: 24px; }
        .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; }
        .kpi { border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.03); border-radius: 20px; padding: 20px; text-align: center; }
        .kpi .label { font-size: 11px; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; font-weight: 700; margin-bottom: 5px; display: block; }
        .kpi strong { display: block; font-size: 28px; font-weight: 800; color: #fff; }
        .resumen { margin: 0; padding: 0; background: transparent; border: none; }
        .resumen .num { width: 120px; text-align: center; }
        @media (max-width: 1024px) { .split { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .shell { padding: 24px 16px; } .hero { padding: 25px; } .hero h1 { font-size: 28px; } }
    </style>
    <?php
    echo '</head><body><div class="shell">';
    echo '<header class="hero">';
    if ($breadcrumbs !== []) {
        echo '<nav class="breadcrumbs">';
        $lastIndex = count($breadcrumbs) - 1;
        foreach ($breadcrumbs as $index => $crumb) {
            if ($index > 0) { echo '<span>/</span>'; }
            $label = csyj_h($crumb['label'] ?? '');
            $url = $crumb['url'] ?? null;
            if ($url !== null && $index !== $lastIndex) {
                echo '<a href="' . csyj_h((string)$url) . '">' . $label . '</a>';
            } else {
                echo '<span>' . $label . '</span>';
            }
        }
        echo '</nav>';
    }
    echo '<div class="hero-top">';
    echo '<div><h1>' . csyj_h($title) . '</h1>';
    if ($subtitle !== '') { echo '<p>' . csyj_h($subtitle) . '</p>'; }
    echo '</div>';
    if ($actions !== []) {
        echo '<div class="hero-actions">';
        foreach ($actions as $action) {
            $label = csyj_h($action['label'] ?? 'Acción');
            $url = csyj_h((string)($action['url'] ?? '#'));
            $class = 'btn ' . csyj_h((string)($action['class'] ?? 'light'));
            echo '<a class="' . $class . '" href="' . $url . '">' . $label . '</a>';
        }
        echo '</div>';
    }
    echo '</div></header><main class="page-body">';
}

function csyj_render_layout_end(): void
{
    echo '</main></div></body></html>';
}

function csyj_render_result_badge(string $resultado): string
{
    $texto = strtoupper($resultado);
    if (str_contains($texto, 'NO APTO') || str_contains($texto, 'NEGATIVA') || $texto === 'NO') {
        $clase = 'danger';
    } elseif (str_contains($texto, 'APTO') || str_contains($texto, 'POSITIVA') || $texto === 'SI' || $texto === 'SÍ') {
        $clase = 'success';
    } else {
        $clase = 'neutral';
    }
    return '<span class="badge ' . $clase . '">' . csyj_h($resultado) . '</span>';
}
