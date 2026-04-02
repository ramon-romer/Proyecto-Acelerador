<?php
declare(strict_types=1);

function normalizar_numero_experimentales($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }

    if (is_string($valor)) {
        $valor = trim($valor);
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

function sumar_puntuaciones_experimentales(array $items, float $maximo): float
{
    $total = 0.0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $total += normalizar_numero_experimentales($item['puntuacion'] ?? 0);
    }

    if ($total > $maximo) {
        $total = $maximo;
    }

    return round($total, 2);
}

function calcular_bloque_1_experimentales(array $bloque1): array
{
    $resultado = [
        '1A' => sumar_puntuaciones_experimentales($bloque1['publicaciones'] ?? [], 35.0),
        '1B' => sumar_puntuaciones_experimentales($bloque1['libros'] ?? [], 7.0),
        '1C' => sumar_puntuaciones_experimentales($bloque1['proyectos'] ?? [], 7.0),
        '1D' => sumar_puntuaciones_experimentales($bloque1['transferencia'] ?? [], 4.0),
        '1E' => sumar_puntuaciones_experimentales($bloque1['tesis_dirigidas'] ?? [], 4.0),
        '1F' => sumar_puntuaciones_experimentales($bloque1['congresos'] ?? [], 2.0),
        '1G' => sumar_puntuaciones_experimentales($bloque1['otros_meritos_investigacion'] ?? [], 1.0),
    ];

    $resultado['B1'] = round(min(60.0,
        $resultado['1A'] +
        $resultado['1B'] +
        $resultado['1C'] +
        $resultado['1D'] +
        $resultado['1E'] +
        $resultado['1F'] +
        $resultado['1G']
    ), 2);

    return $resultado;
}

function calcular_bloque_2_experimentales(array $bloque2): array
{
    $resultado = [
        '2A' => sumar_puntuaciones_experimentales($bloque2['docencia_universitaria'] ?? [], 17.0),
        '2B' => sumar_puntuaciones_experimentales($bloque2['evaluacion_docente'] ?? [], 3.0),
        '2C' => sumar_puntuaciones_experimentales($bloque2['formacion_docente'] ?? [], 3.0),
        '2D' => sumar_puntuaciones_experimentales($bloque2['material_docente'] ?? [], 7.0),
    ];

    $resultado['B2'] = round(min(30.0,
        $resultado['2A'] +
        $resultado['2B'] +
        $resultado['2C'] +
        $resultado['2D']
    ), 2);

    return $resultado;
}

function calcular_bloque_3_experimentales(array $bloque3): array
{
    $resultado = [
        '3A' => sumar_puntuaciones_experimentales($bloque3['formacion_academica'] ?? [], 6.0),
        '3B' => sumar_puntuaciones_experimentales($bloque3['experiencia_profesional'] ?? [], 2.0),
    ];

    $resultado['B3'] = round(min(8.0,
        $resultado['3A'] +
        $resultado['3B']
    ), 2);

    return $resultado;
}

function calcular_bloque_4_experimentales(array $bloque4): array
{
    $resultado = [
        'B4' => sumar_puntuaciones_experimentales($bloque4, 2.0),
    ];

    return $resultado;
}

function calcular_totales_experimentales(array $bloque1, array $bloque2, array $bloque3, array $bloque4): array
{
    $total_b1_b2 = round(($bloque1['B1'] ?? 0) + ($bloque2['B2'] ?? 0), 2);
    $total_final = round($total_b1_b2 + ($bloque3['B3'] ?? 0) + ($bloque4['B4'] ?? 0), 2);

    return [
        'total_b1_b2' => $total_b1_b2,
        'total_final' => $total_final,
    ];
}

function evaluar_experimentales(array $totales): array
{
    $cumple_regla_1 = ($totales['total_b1_b2'] ?? 0) >= 50.0;
    $cumple_regla_2 = ($totales['total_final'] ?? 0) >= 55.0;

    return [
        'cumple_regla_1' => $cumple_regla_1,
        'cumple_regla_2' => $cumple_regla_2,
        'resultado' => ($cumple_regla_1 && $cumple_regla_2) ? 'POSITIVA' : 'NEGATIVA',
    ];
}