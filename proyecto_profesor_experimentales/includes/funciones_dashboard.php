<?php
declare(strict_types=1);

function obtenerIdProfesor(): int {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    return ($id && $id > 0) ? $id : 1;
}

function obtenerEvaluacionPorId(mysqli $conexion, int $id): ?array {
    $sql = "SELECT * FROM evaluaciones WHERE id = ? LIMIT 1";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    $stmt->close();
    return $fila ?: null;
}

function decodificarJsonEntrada(?string $json): array {
    if (!$json || trim($json) === '') return [];
    $datos = json_decode($json, true);
    return is_array($datos) ? $datos : [];
}

function numero(mixed $valor): float { return is_numeric($valor) ? (float)$valor : 0.0; }

function porcentaje(float $actual, float $objetivo): float {
    if ($objetivo <= 0) return 0.0;
    $valor = ($actual / $objetivo) * 100;
    return max(0.0, min(100.0, $valor));
}

function claseEstado(float $porcentaje): string {
    if ($porcentaje >= 100) return 'estado-verde';
    if ($porcentaje >= 60) return 'estado-ambar';
    return 'estado-rojo';
}

function textoEstado(float $porcentaje): string {
    if ($porcentaje >= 100) return 'Cumplido';
    if ($porcentaje >= 75) return 'Cerca';
    if ($porcentaje >= 40) return 'En progreso';
    return 'Lejos';
}

function formatearFecha(?string $fecha): string {
    if (!$fecha) return 'Sin fecha';
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y H:i', $ts) : $fecha;
}

function contarRuta(array $datos, array $ruta): int {
    $actual = $datos;
    foreach ($ruta as $clave) {
        if (!is_array($actual) || !array_key_exists($clave, $actual)) return 0;
        $actual = $actual[$clave];
    }
    return is_array($actual) ? count($actual) : 0;
}

function obtenerResumenApartados(array $jsonEntrada): array {
    if (isset($jsonEntrada['resumen_apartados']) && is_array($jsonEntrada['resumen_apartados'])) return $jsonEntrada['resumen_apartados'];
    if (isset($jsonEntrada['resumen']['resumen_apartados']) && is_array($jsonEntrada['resumen']['resumen_apartados'])) return $jsonEntrada['resumen']['resumen_apartados'];
    return [];
}

function obtenerRecomendaciones(array $jsonEntrada): array {
    $candidatas = [
        $jsonEntrada['recomendaciones'] ?? null,
        $jsonEntrada['salida']['recomendaciones'] ?? null,
        $jsonEntrada['resumen']['recomendaciones'] ?? null,
        $jsonEntrada['analisis']['recomendaciones'] ?? null,
    ];
    foreach ($candidatas as $valor) {
        if (is_array($valor)) {
            return array_values(array_filter($valor, fn($item) => is_string($item) && trim($item) !== ''));
        }
    }
    return [];
}

function obtenerSimulaciones(array $jsonEntrada): array {
    $candidatas = [
        $jsonEntrada['simulaciones'] ?? null,
        $jsonEntrada['salida']['simulaciones'] ?? null,
        $jsonEntrada['analisis']['simulaciones'] ?? null,
    ];
    foreach ($candidatas as $valor) {
        if (is_array($valor)) return $valor;
    }
    return [];
}

function construirBloques(array $evaluacion, array $jsonEntrada): array {
    $objetivos = [
        'Investigación y transferencia de conocimiento' => 50.0,
        'Docencia' => 50.0,
        'Formación académica y experiencia profesional' => 25.0,
        'Otros méritos' => 25.0,
    ];
    $bloques = [
        [
            'clave' => 'bloque_1',
            'nombre' => 'Investigación y transferencia de conocimiento',
            'valor' => numero($evaluacion['bloque_1'] ?? 0),
            'objetivo' => $objetivos['Investigación y transferencia de conocimiento'],
            'detalle' => [
                'publicaciones' => contarRuta($jsonEntrada, ['bloque_1', 'publicaciones']),
                'libros' => contarRuta($jsonEntrada, ['bloque_1', 'libros']),
                'proyectos' => contarRuta($jsonEntrada, ['bloque_1', 'proyectos']),
            ],
        ],
        [
            'clave' => 'bloque_2',
            'nombre' => 'Docencia',
            'valor' => numero($evaluacion['bloque_2'] ?? 0),
            'objetivo' => $objetivos['Docencia'],
            'detalle' => [
                'apartados' => array_values(array_filter(
                    obtenerResumenApartados($jsonEntrada),
                    fn($item) => is_array($item) && isset($item['codigo']) && str_starts_with((string)$item['codigo'], '2.')
                )),
            ],
        ],
        [
            'clave' => 'bloque_3',
            'nombre' => 'Formación académica y experiencia profesional',
            'valor' => numero($evaluacion['bloque_3'] ?? 0),
            'objetivo' => $objetivos['Formación académica y experiencia profesional'],
            'detalle' => [],
        ],
        [
            'clave' => 'bloque_4',
            'nombre' => 'Otros méritos',
            'valor' => numero($evaluacion['bloque_4'] ?? 0),
            'objetivo' => $objetivos['Otros méritos'],
            'detalle' => [],
        ],
    ];
    foreach ($bloques as &$bloque) {
        $bloque['porcentaje'] = porcentaje($bloque['valor'], $bloque['objetivo']);
        $bloque['estado'] = textoEstado($bloque['porcentaje']);
        $bloque['clase_estado'] = claseEstado($bloque['porcentaje']);
        $bloque['faltante'] = max(0, $bloque['objetivo'] - $bloque['valor']);
    }
    unset($bloque);
    return $bloques;
}

function construirResumenGlobal(array $evaluacion, array $bloques): array {
    $totalFinal = numero($evaluacion['total_final'] ?? 0);
    $cumplimientoGlobal = porcentaje($totalFinal, 100.0);
    $bloquesCumplidos = 0;
    $bloqueMasDebil = null;
    foreach ($bloques as $bloque) {
        if ($bloque['porcentaje'] >= 100) $bloquesCumplidos++;
        if ($bloqueMasDebil === null || $bloque['porcentaje'] < $bloqueMasDebil['porcentaje']) $bloqueMasDebil = $bloque;
    }
    return [
        'cumplimiento_global' => $cumplimientoGlobal,
        'estado_global' => textoEstado($cumplimientoGlobal),
        'clase_estado' => claseEstado($cumplimientoGlobal),
        'total_final' => $totalFinal,
        'bloques_cumplidos' => $bloquesCumplidos,
        'bloques_totales' => count($bloques),
        'bloque_mas_debil' => $bloqueMasDebil,
        'resultado' => (string)($evaluacion['resultado'] ?? 'Sin resultado'),
        'cumple_regla_1' => !empty($evaluacion['cumple_regla_1']),
        'cumple_regla_2' => !empty($evaluacion['cumple_regla_2']),
    ];
}

function construirFaltantes(array $bloques, array $jsonEntrada): array {
    $faltantes = [];
    foreach ($bloques as $bloque) {
        if ($bloque['faltante'] > 0.01) {
            $faltantes[] = 'En ' . $bloque['nombre'] . ' faltan ' . number_format($bloque['faltante'], 2) . ' puntos.';
        }
    }
    foreach (obtenerRecomendaciones($jsonEntrada) as $recomendacion) {
        $faltantes[] = $recomendacion;
    }
    return array_slice(array_values(array_unique($faltantes)), 0, 6);
}

function obtenerTodosLosDetalles(array $jsonEntrada): array {
    $salida = [];
    foreach (['bloque_1', 'bloque_2', 'bloque_3', 'bloque_4'] as $bloque) {
        if (!isset($jsonEntrada[$bloque]) || !is_array($jsonEntrada[$bloque])) continue;
        foreach ($jsonEntrada[$bloque] as $subclave => $valor) {
            if (is_array($valor)) {
                $salida[] = ['bloque' => $bloque, 'apartado' => (string)$subclave, 'cantidad' => count($valor)];
            }
        }
    }
    return $salida;
}

function e(string $texto): string {
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}
