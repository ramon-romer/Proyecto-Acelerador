<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hum_portal_url(): string
{
    return '../aneca_portal/index.php';
}

function hum_index_url(): string
{
    return 'index.php';
}

function hum_listado_url(): string
{
    return 'listado.php';
}

function hum_render_layout_start(string $title, string $subtitle = '', array $breadcrumbs = [], array $actions = []): void
{
    echo '<!DOCTYPE html>';
    echo '<html lang="es">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>
        :root {
            --azul-900: #173a77;
            --azul-800: #1e4d9b;
            --azul-700: #2a62bf;
            --azul-100: #eaf1ff;
            --gris-900: #162033;
            --gris-700: #475467;
            --gris-600: #667085;
            --gris-300: #d0d5dd;
            --gris-200: #eaecf0;
            --gris-100: #f2f4f7;
            --blanco: #ffffff;
            --verde: #16794a;
            --verde-fondo: #ecfdf3;
            --rojo: #b42318;
            --rojo-fondo: #fef3f2;
            --sombra: 0 12px 40px rgba(16, 24, 40, 0.08);
            --radio: 18px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(42, 82, 152, 0.18), transparent 32%),
                linear-gradient(180deg, #f7f9fc 0%, #eef3fb 100%);
            color: var(--gris-900);
        }
        a { color: var(--azul-800); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .shell { max-width: 1280px; margin: 0 auto; padding: 24px; }
        .hero {
            background: linear-gradient(135deg, var(--azul-900), var(--azul-700));
            color: var(--blanco);
            border-radius: 24px;
            padding: 22px 24px;
            box-shadow: var(--sombra);
            margin-bottom: 22px;
        }
        .hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }
        .breadcrumbs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 14px;
            margin-bottom: 12px;
            opacity: .95;
        }
        .breadcrumbs a { color: rgba(255,255,255,.92); }
        .hero h1 { margin: 0; font-size: 32px; }
        .hero p { margin: 10px 0 0; max-width: 850px; color: rgba(255,255,255,.92); }
        .hero-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .page-body { display: grid; gap: 18px; }
        .card {
            background: var(--blanco);
            border-radius: var(--radio);
            box-shadow: var(--sombra);
            padding: 22px;
        }
        .card h2, .card h3 { margin-top: 0; }
        .muted { color: var(--gris-700); }
        .meta-grid, .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .metric {
            background: var(--gris-100);
            border: 1px solid var(--gris-200);
            border-radius: 14px;
            padding: 14px 16px;
        }
        .metric .label {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--gris-600);
            margin-bottom: 6px;
        }
        .metric .value {
            font-size: 24px;
            font-weight: bold;
            color: var(--gris-900);
        }
        .toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }

        .bloque {
            background: var(--blanco);
            border: 1px solid var(--gris-200);
            border-radius: 18px;
            padding: 18px;
        }
        .bloque h2 { margin-top: 0; }
        .bloque p.hint { margin-top: -4px; color: var(--gris-700); }
        .fila {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
            padding: 14px;
            border: 1px dashed var(--gris-300);
            border-radius: 14px;
            background: #fbfcff;
        }
        .section-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .empty-note {
            padding: 12px 14px;
            border: 1px dashed var(--gris-300);
            border-radius: 12px;
            color: var(--gris-700);
            background: #fcfcfd;
            margin-bottom: 12px;
        }
        .btn, button, input[type="submit"] {
            appearance: none;
            border: 0;
            border-radius: 12px;
            background: var(--azul-800);
            color: var(--blanco);
            padding: 11px 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform .12s ease, background .12s ease;
        }
        .btn:hover, button:hover, input[type="submit"]:hover { transform: translateY(-1px); background: var(--azul-700); text-decoration: none; }
        .btn.secondary, .secondary { background: #344054; }
        .btn.light { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.25); }
        .btn.light:hover { background: rgba(255,255,255,.22); }
        .btn.outline {
            background: transparent;
            color: var(--azul-800);
            border: 1px solid var(--azul-800);
        }
        .btn.outline:hover { background: var(--azul-100); }
        label { display: block; font-weight: bold; margin-bottom: 6px; color: var(--gris-900); }
        input[type="text"], input[type="file"], input[type="number"], select, textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid var(--gris-300);
            border-radius: 12px;
            background: var(--blanco);
            color: var(--gris-900);
        }
        textarea { min-height: 120px; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
        }
        .form-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 14px;
            overflow: hidden;
            background: var(--blanco);
        }
        th, td {
            border-bottom: 1px solid var(--gris-200);
            padding: 12px 14px;
            text-align: left;
            vertical-align: top;
        }
        th { background: var(--gris-100); color: var(--gris-900); }
        tr:hover td { background: #fafcff; }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: bold;
            font-size: 13px;
        }
        .badge.success { color: var(--verde); background: var(--verde-fondo); }
        .badge.danger { color: var(--rojo); background: var(--rojo-fondo); }
        .badge.neutral { color: var(--azul-800); background: var(--azul-100); }
        .stack { display: grid; gap: 18px; }
        details {
            border: 1px solid var(--gris-200);
            border-radius: 14px;
            background: var(--gris-100);
            overflow: hidden;
        }
        summary {
            cursor: pointer;
            padding: 14px 16px;
            font-weight: bold;
        }
        pre {
            margin: 0;
            padding: 16px;
            overflow: auto;
            background: #101828;
            color: #d0d5dd;
            font-size: 13px;
        }
        ul, ol { padding-left: 20px; }
        .split {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 18px;
        }
        .kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .kpi {
            border: 1px solid var(--gris-200);
            background: linear-gradient(180deg, #fff 0%, #f9fbff 100%);
            border-radius: 16px;
            padding: 14px;
        }
        .kpi strong { display: block; font-size: 24px; margin-top: 6px; }
        @media (max-width: 900px) {
            .split { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .shell { padding: 16px; }
            .hero { padding: 18px; }
            .hero h1 { font-size: 26px; }
        }
    </style>';
    echo '</head><body><div class="shell">';
    echo '<header class="hero">';
    if ($breadcrumbs !== []) {
        echo '<nav class="breadcrumbs">';
        $lastIndex = count($breadcrumbs) - 1;
        foreach ($breadcrumbs as $index => $crumb) {
            if ($index > 0) {
                echo '<span>/</span>';
            }
            $label = h($crumb['label'] ?? '');
            $url = $crumb['url'] ?? null;
            if ($url !== null && $index !== $lastIndex) {
                echo '<a href="' . h((string)$url) . '">' . $label . '</a>';
            } else {
                echo '<span>' . $label . '</span>';
            }
        }
        echo '</nav>';
    }
    echo '<div class="hero-top">';
    echo '<div><h1>' . h($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . h($subtitle) . '</p>';
    }
    echo '</div>';
    if ($actions !== []) {
        echo '<div class="hero-actions">';
        foreach ($actions as $action) {
            $label = h($action['label'] ?? 'Acción');
            $url = h((string)($action['url'] ?? '#'));
            $class = 'btn ' . h((string)($action['class'] ?? 'light'));
            echo '<a class="' . $class . '" href="' . $url . '">' . $label . '</a>';
        }
        echo '</div>';
    }
    echo '</div></header><main class="page-body">';
}

function hum_render_layout_end(): void
{
    echo '</main></div></body></html>';
}

function hum_render_result_badge(string $resultado): string
{
    $texto = strtoupper($resultado);
    if (str_contains($texto, 'NO APTO') || $texto === 'NO') {
        $clase = 'danger';
    } elseif (str_contains($texto, 'APTO') || $texto === 'SI' || $texto === 'SÍ') {
        $clase = 'success';
    } else {
        $clase = 'neutral';
    }
    return '<span class="badge ' . $clase . '">' . h($resultado) . '</span>';
}
