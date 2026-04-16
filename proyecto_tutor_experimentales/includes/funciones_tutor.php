<?php
declare(strict_types=1);

const RUTA_DASHBOARD_PROFESOR = '../proyecto_profesor_experimentales/resumen.php';

function numero(mixed $valor): float
{
    return is_numeric($valor) ? (float)$valor : 0.0;
}

function porcentaje(float $actual, float $objetivo = 100.0): float
{
    if ($objetivo <= 0) return 0.0;
    $valor = ($actual / $objetivo) * 100;
    return max(0.0, min(100.0, $valor));
}

function e(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}

function obtenerProfesores(mysqli $conexion): array
{
    $sql = "SELECT id, nombre_candidato, area, categoria, bloque_1, bloque_2, bloque_3, bloque_4,
                   total_final, resultado, cumple_regla_1, cumple_regla_2, fecha_creacion
            FROM evaluaciones
            ORDER BY fecha_creacion DESC, id DESC";
    $resultado = $conexion->query($sql);
    return $resultado->fetch_all(MYSQLI_ASSOC);
}

function obtenerClaseEstado(float $cumplimiento): string
{
    if ($cumplimiento >= 85) return 'estado-verde';
    if ($cumplimiento >= 55) return 'estado-ambar';
    return 'estado-rojo';
}

function obtenerTextoEstado(float $cumplimiento): string
{
    if ($cumplimiento >= 85) return 'Cerca';
    if ($cumplimiento >= 55) return 'En progreso';
    return 'Prioridad alta';
}

function obtenerBloqueMasDebil(array $fila): string
{
    $bloques = [
        'Investigación y transferencia' => numero($fila['bloque_1'] ?? 0),
        'Docencia' => numero($fila['bloque_2'] ?? 0),
        'Formación y experiencia' => numero($fila['bloque_3'] ?? 0),
        'Otros méritos' => numero($fila['bloque_4'] ?? 0),
    ];
    asort($bloques);
    return array_key_first($bloques) ?? 'Sin datos';
}

function construirFaltasBreves(array $fila): array
{
    $faltas = [];
    $faltas[] = 'Reforzar ' . mb_strtolower(obtenerBloqueMasDebil($fila));

    if (numero($fila['bloque_3'] ?? 0) < 15) $faltas[] = 'Mejorar formación y experiencia';
    if (numero($fila['bloque_4'] ?? 0) < 15) $faltas[] = 'Consolidar méritos diferenciales';
    if (numero($fila['total_final'] ?? 0) < 75) $faltas[] = 'Subir el total final';

    return array_slice(array_values(array_unique($faltas)), 0, 2);
}

function enriquecerProfesor(array $fila): array
{
    $cumplimiento = porcentaje(numero($fila['total_final'] ?? 0), 100.0);
    $fila['cumplimiento_global'] = $cumplimiento;
    $fila['clase_estado'] = obtenerClaseEstado($cumplimiento);
    $fila['texto_estado'] = obtenerTextoEstado($cumplimiento);
    $fila['bloque_mas_debil'] = obtenerBloqueMasDebil($fila);
    $fila['faltas_breves'] = construirFaltasBreves($fila);
    $fila['url_detalle'] = RUTA_DASHBOARD_PROFESOR . '?id=' . (int)$fila['id'];
    return $fila;
}

function obtenerProfesoresEnriquecidos(mysqli $conexion): array
{
    return array_map('enriquecerProfesor', obtenerProfesores($conexion));
}

function construirResumenTutor(array $profesores): array
{
    $total = count($profesores);
    $cerca = 0; $seguimiento = 0; $prioridadAlta = 0;

    foreach ($profesores as $profesor) {
        if ($profesor['clase_estado'] === 'estado-verde') $cerca++;
        elseif ($profesor['clase_estado'] === 'estado-ambar') $seguimiento++;
        else $prioridadAlta++;
    }

    $masAvanzados = $profesores;
    usort($masAvanzados, fn($a, $b) => $b['cumplimiento_global'] <=> $a['cumplimiento_global']);

    $masNecesitados = $profesores;
    usort($masNecesitados, fn($a, $b) => $a['cumplimiento_global'] <=> $b['cumplimiento_global']);

    return [
        'total' => $total,
        'cerca' => $cerca,
        'seguimiento' => $seguimiento,
        'prioridad_alta' => $prioridadAlta,
        'mas_avanzados' => array_slice($masAvanzados, 0, 3),
        'mas_necesitados' => array_slice($masNecesitados, 0, 3),
    ];
}

function obtenerAnchoBarra(float $cumplimiento): string
{
    return number_format($cumplimiento, 2, '.', '') . '%';
}
