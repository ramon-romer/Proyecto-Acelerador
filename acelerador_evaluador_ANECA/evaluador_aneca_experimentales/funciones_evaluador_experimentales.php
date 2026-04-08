<?php
declare(strict_types=1);

/**
 * ============================================================
 * EVALUADOR ANECA - EXPERIMENTALES (PCD/PUP)
 * ============================================================
 *
 * Reglas globales del proyecto:
 * - B1 + B2 >= 50
 * - Total final >= 55
 */

/* ============================================================
 * UTILIDADES
 * ============================================================
 */

function limitar(float $valor, float $minimo, float $maximo): float
{
    return max($minimo, min($valor, $maximo));
}

function redondear_2(float $valor): float
{
    return round($valor, 2);
}

function contar_items_validos(array $items, string $campoValidez = 'es_valido'): int
{
    $contador = 0;
    foreach ($items as $item) {
        if (!empty($item[$campoValidez])) {
            $contador++;
        }
    }
    return $contador;
}

/* ============================================================
 * FACTORES COMUNES
 * ============================================================
 */

function factor_afinidad_exp(string $afinidad): float
{
    return match (strtolower($afinidad)) {
        'total' => 1.20,
        'relacionada' => 1.00,
        'periferica' => 0.75,
        'ajena' => 0.40,
        default => 1.00,
    };
}

function factor_posicion_autor_exp(string $posicion): float
{
    return match (strtolower($posicion)) {
        'primero' => 1.20,
        'ultimo' => 1.15,
        'correspondencia' => 1.15,
        'intermedio' => 1.00,
        'secundario' => 0.90,
        default => 1.00,
    };
}

function factor_coautoria_exp(int $numero_autores): float
{
    return match (true) {
        $numero_autores <= 2 => 1.20,
        $numero_autores <= 5 => 1.10,
        $numero_autores <= 10 => 1.00,
        $numero_autores <= 20 => 0.90,
        default => 0.80,
    };
}

function factor_citas_exp(int $citas, int $anios_desde_publicacion): float
{
    if ($anios_desde_publicacion < 2 && $citas < 5) {
        return 1.00;
    }

    return match (true) {
        $citas <= 2 => 0.90,
        $citas <= 10 => 1.00,
        $citas <= 30 => 1.10,
        $citas <= 80 => 1.20,
        default => 1.30,
    };
}

/* ============================================================
 * BLOQUE 1 - 1A PUBLICACIONES Y PATENTES
 * ============================================================
 */

function base_publicacion_exp(array $publicacion): float
{
    $tipo_indice = strtoupper((string)($publicacion['tipo_indice'] ?? ''));
    $cuartil = strtoupper((string)($publicacion['cuartil'] ?? ''));
    $subtipo = strtoupper((string)($publicacion['subtipo_indice'] ?? ''));

    if ($tipo_indice === 'JCR') {
        return match ($cuartil) {
            'Q1' => 12.0,
            'Q2' => 8.0,
            'Q3' => 3.5,
            'Q4' => 1.0,
            default => 0.5,
        };
    }

    if ($tipo_indice === 'SJR') {
        return match ($cuartil) {
            'Q1' => 9.0,
            'Q2' => 6.0,
            'Q3' => 2.5,
            'Q4' => 1.0,
            default => 0.5,
        };
    }

    if ($tipo_indice === 'PATENTE') {
        return match ($subtipo) {
            'B1' => 7.0,
            'B2' => 5.0,
            default => 2.5,
        };
    }

    return 0.3;
}

function puntuar_publicacion_1a_exp(array $publicacion): float
{
    if (
        empty($publicacion['es_valida']) ||
        !in_array(($publicacion['tipo'] ?? ''), ['articulo', 'patente'], true)
    ) {
        return 0.0;
    }

    $base = base_publicacion_exp($publicacion);

    if (($publicacion['tipo'] ?? '') === 'patente') {
        $factor_liderazgo = !empty($publicacion['liderazgo']) ? 1.15 : 1.00;
        return redondear_2($base * $factor_liderazgo);
    }

    $factor_tipo = match (strtolower((string)($publicacion['tipo_aportacion'] ?? 'articulo'))) {
        'articulo' => 1.00,
        'review' => 0.95,
        'metaanalisis' => 1.08,
        'metodologico' => 1.05,
        'experimental' => 1.10,
        default => 1.00,
    };

    $puntuacion = $base
        * factor_afinidad_exp((string)($publicacion['afinidad'] ?? 'relacionada'))
        * factor_posicion_autor_exp((string)($publicacion['posicion_autor'] ?? 'intermedio'))
        * factor_coautoria_exp((int)($publicacion['numero_autores'] ?? 5))
        * factor_citas_exp(
            (int)($publicacion['citas'] ?? 0),
            (int)($publicacion['anios_desde_publicacion'] ?? 3)
        )
        * $factor_tipo;

    return redondear_2($puntuacion);
}

function puntuar_1a_exp(array $publicaciones): float
{
    $total = 0.0;

    foreach ($publicaciones as $publicacion) {
        $total += puntuar_publicacion_1a_exp($publicacion);
    }

    return limitar(redondear_2($total), 0.0, 40.0);
}

/* ============================================================
 * BLOQUE 1 - 1B LIBROS Y CAPÍTULOS
 * ============================================================
 */

function base_editorial_exp(string $nivel_editorial): float
{
    return match (strtolower($nivel_editorial)) {
        'prestigiosa' => 2.5,
        'secundaria' => 1.2,
        'baja' => 0.4,
        default => 0.0,
    };
}

function puntuar_item_1b_exp(array $item): float
{
    if (
        empty($item['es_valido']) ||
        empty($item['es_libro_investigacion']) ||
        !in_array(($item['tipo'] ?? ''), ['libro', 'capitulo'], true) ||
        !empty($item['es_autoedicion']) ||
        !empty($item['es_acta_congreso']) ||
        !empty($item['es_labor_edicion'])
    ) {
        return 0.0;
    }

    $factor_tipo = match (strtolower((string)($item['tipo'] ?? 'capitulo'))) {
        'libro' => 1.10,
        'capitulo' => 0.70,
        default => 1.00,
    };

    $puntuacion = base_editorial_exp((string)($item['nivel_editorial'] ?? 'baja'))
        * $factor_tipo
        * factor_afinidad_exp((string)($item['afinidad'] ?? 'relacionada'));

    return redondear_2($puntuacion);
}

function puntuar_1b_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1b_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

/* ============================================================
 * BLOQUE 1 - 1C PROYECTOS Y CONTRATOS
 * ============================================================
 */

function base_tipo_proyecto_exp(string $tipo_proyecto): float
{
    return match (strtolower($tipo_proyecto)) {
        'internacional' => 6.0,
        'nacional' => 5.0,
        'autonomico' => 3.0,
        'universidad' => 1.5,
        'contrato_empresa' => 2.0,
        'infraestructura' => 2.5,
        'red_tematica' => 0.0,
        'ayuda_grupo' => 0.0,
        'no_competitivo' => 0.0,
        default => 0.0,
    };
}

function factor_rol_proyecto_exp(string $rol): float
{
    return match (strtolower($rol)) {
        'ip' => 1.50,
        'coip' => 1.30,
        'investigador' => 1.00,
        'participacion_menor' => 0.70,
        default => 1.00,
    };
}

function factor_dedicacion_exp(string $dedicacion): float
{
    return match (strtolower($dedicacion)) {
        'completa' => 1.20,
        'parcial' => 1.00,
        'residual' => 0.80,
        default => 1.00,
    };
}

function factor_duracion_exp(float $anios): float
{
    return match (true) {
        $anios >= 4.0 => 1.20,
        $anios >= 2.0 => 1.00,
        $anios > 0.0 => 0.80,
        default => 0.0,
    };
}

function puntuar_item_1c_exp(array $item): float
{
    if (empty($item['es_valido']) || empty($item['esta_certificado'])) {
        return 0.0;
    }

    $puntuacion = base_tipo_proyecto_exp((string)($item['tipo_proyecto'] ?? 'no_competitivo'))
        * factor_rol_proyecto_exp((string)($item['rol'] ?? 'investigador'))
        * factor_dedicacion_exp((string)($item['dedicacion'] ?? 'parcial'))
        * factor_duracion_exp((float)($item['anios_duracion'] ?? 0));

    return redondear_2($puntuacion);
}

function puntuar_1c_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1c_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 12.0);
}

/* ============================================================
 * BLOQUE 1 - 1D TRANSFERENCIA
 * ============================================================
 */

function puntuar_item_1d_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $base = match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'patente_b1' => 2.5,
        'patente_b2' => 2.0,
        'contrato_empresa' => 2.0,
        'software_explotacion' => 1.8,
        'spin_off' => 2.2,
        'prototipo_transferido' => 2.0,
        'menor' => 0.5,
        default => 0.0,
    };

    $factor = 1.0;

    if (!empty($item['impacto_externo'])) {
        $factor *= 1.2;
    }

    if (!empty($item['liderazgo'])) {
        $factor *= 1.1;
    }

    if (!empty($item['participacion_menor'])) {
        $factor *= 0.8;
    }

    return redondear_2($base * $factor);
}

function puntuar_1d_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1d_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 4.0);
}

/* ============================================================
 * BLOQUE 1 - 1E TESIS DIRIGIDAS
 * ============================================================
 */

function puntuar_item_1e_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $base = match (strtolower((string)($item['tipo'] ?? 'codireccion'))) {
        'direccion_unica' => 2.0,
        'codireccion' => 1.2,
        default => 0.0,
    };

    if (!empty($item['calidad_especial'])) {
        $base += 0.5;
    }

    return redondear_2($base);
}

function puntuar_1e_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1e_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 4.0);
}

/* ============================================================
 * BLOQUE 1 - 1F CONGRESOS
 * ============================================================
 */

function puntuar_1f_exp(array $items): float
{
    $total = 0.0;
    $conteo_por_evento = [];

    foreach ($items as $item) {
        if (empty($item['es_valido'])) {
            continue;
        }

        $id_evento = (string)($item['id_evento'] ?? uniqid('evento_', true));
        $conteo_por_evento[$id_evento] = $conteo_por_evento[$id_evento] ?? 0;

        if ($conteo_por_evento[$id_evento] >= 2) {
            continue;
        }

        $base = match (strtolower((string)($item['ambito'] ?? 'local'))) {
            'internacional' => 1.0,
            'nacional' => 0.7,
            'regional' => 0.4,
            'local' => 0.2,
            default => 0.2,
        };

        $factor = match (strtolower((string)($item['tipo'] ?? 'poster'))) {
            'ponencia_invitada' => 1.3,
            'comunicacion_oral' => 1.1,
            'poster' => 0.8,
            default => 0.8,
        };

        $total += $base * $factor;
        $conteo_por_evento[$id_evento]++;
    }

    return limitar(redondear_2($total), 0.0, 2.0);
}

/* ============================================================
 * BLOQUE 1 - 1G OTROS MÉRITOS
 * ============================================================
 */

function puntuar_item_1g_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? ''))) {
        'revisor' => 0.3,
        'consejo_editorial' => 0.4,
        'tribunal_tesis' => 0.4,
        'premio' => 0.8,
        'grupo_investigacion' => 0.2,
        'sociedad_cientifica' => 0.3,
        default => 0.0,
    };
}

function puntuar_1g_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1g_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 1.0);
}

/* ============================================================
 * BLOQUE 1 COMPLETO
 * ============================================================
 */

function puntuar_bloque_1_exp(array $datos): array
{
    $p1a = puntuar_1a_exp($datos['publicaciones'] ?? []);
    $p1b = puntuar_1b_exp($datos['libros'] ?? []);
    $p1c = puntuar_1c_exp($datos['proyectos'] ?? []);
    $p1d = puntuar_1d_exp($datos['transferencia'] ?? []);
    $p1e = puntuar_1e_exp($datos['tesis_dirigidas'] ?? []);
    $p1f = puntuar_1f_exp($datos['congresos'] ?? []);
    $p1g = puntuar_1g_exp($datos['otros_meritos_investigacion'] ?? []);

    $b1 = limitar(redondear_2($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g), 0.0, 60.0);

    return [
        '1A' => $p1a,
        '1B' => $p1b,
        '1C' => $p1c,
        '1D' => $p1d,
        '1E' => $p1e,
        '1F' => $p1f,
        '1G' => $p1g,
        'B1' => $b1,
    ];
}

/* ============================================================
 * BLOQUE 2 - DOCENCIA
 * ============================================================
 */

function puntuar_item_2a_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $horas = (float)($item['horas'] ?? 0);

    $base = match (true) {
        $horas >= 240 => 4.0,
        $horas >= 180 => 3.0,
        $horas >= 120 => 2.0,
        $horas >= 60 => 1.0,
        $horas > 0 => 0.5,
        default => 0.0,
    };

    $factor_nivel = match (strtolower((string)($item['nivel'] ?? 'grado'))) {
        'master' => 1.10,
        'grado' => 1.00,
        default => 1.00,
    };

    $factor_responsabilidad = match (strtolower((string)($item['responsabilidad'] ?? 'media'))) {
        'alta' => 1.20,
        'media' => 1.00,
        'baja' => 0.85,
        default => 1.00,
    };

    return redondear_2($base * $factor_nivel * $factor_responsabilidad);
}

function puntuar_2a_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2a_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 17.0);
}

function puntuar_item_2b_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $base = match (strtolower((string)($item['resultado'] ?? 'aceptable'))) {
        'excelente' => 1.5,
        'positiva' => 1.0,
        'aceptable' => 0.5,
        default => 0.0,
    };

    if (strtolower((string)($item['tipo'] ?? 'encuestas')) === 'docentia') {
        $base *= 1.10;
    }

    return redondear_2($base);
}

function puntuar_2b_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2b_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2c_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $horas = (float)($item['horas'] ?? 0);

    $base = match (true) {
        $horas >= 100 => 1.5,
        $horas >= 50 => 1.0,
        $horas >= 20 => 0.6,
        $horas > 0 => 0.3,
        default => 0.0,
    };

    $factor_rol = match (strtolower((string)($item['rol'] ?? 'asistente'))) {
        'docente' => 1.20,
        'asistente' => 1.00,
        default => 1.00,
    };

    return redondear_2($base * $factor_rol);
}

function puntuar_2c_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2c_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2d_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'material_publicado' => 2.0,
        'proyecto_innovacion' => 1.5,
        'publicacion_docente' => 1.2,
        'practica_laboratorio' => 1.5,
        'menor' => 0.4,
        default => 0.0,
    };
}

function puntuar_2d_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2d_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 7.0);
}

function puntuar_bloque_2_exp(array $datos): array
{
    $p2a = puntuar_2a_exp($datos['docencia_universitaria'] ?? []);
    $p2b = puntuar_2b_exp($datos['evaluacion_docente'] ?? []);
    $p2c = puntuar_2c_exp($datos['formacion_docente'] ?? []);
    $p2d = puntuar_2d_exp($datos['material_docente'] ?? []);

    $b2 = limitar(redondear_2($p2a + $p2b + $p2c + $p2d), 0.0, 30.0);

    return [
        '2A' => $p2a,
        '2B' => $p2b,
        '2C' => $p2c,
        '2D' => $p2d,
        'B2' => $b2,
    ];
}

/* ============================================================
 * BLOQUE 3 - FORMACIÓN Y EXPERIENCIA
 * ============================================================
 */

function puntuar_item_3a_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'doctorado_internacional' => 2.0,
        'beca_competitiva' => 1.5,
        'estancia' => 1.5,
        'postdoc' => 1.8,
        'master' => 0.8,
        'curso_especializacion' => 0.5,
        'menor' => 0.2,
        default => 0.0,
    };
}

function puntuar_3a_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3a_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 6.0);
}

function puntuar_item_3b_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $anios = (float)($item['anios'] ?? 0);

    $base = match (true) {
        $anios >= 5 => 1.5,
        $anios >= 3 => 1.0,
        $anios >= 1 => 0.6,
        $anios > 0 => 0.3,
        default => 0.0,
    };

    $factor_relacion = match (strtolower((string)($item['relacion'] ?? 'media'))) {
        'alta' => 1.20,
        'media' => 1.00,
        'baja' => 0.70,
        default => 1.00,
    };

    return redondear_2($base * $factor_relacion);
}

function puntuar_3b_exp(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3b_exp($item);
    }
    return limitar(redondear_2($total), 0.0, 2.0);
}

function puntuar_bloque_3_exp(array $datos): array
{
    $p3a = puntuar_3a_exp($datos['formacion_academica'] ?? []);
    $p3b = puntuar_3b_exp($datos['experiencia_profesional'] ?? []);

    $b3 = limitar(redondear_2($p3a + $p3b), 0.0, 8.0);

    return [
        '3A' => $p3a,
        '3B' => $p3b,
        'B3' => $b3,
    ];
}

/* ============================================================
 * BLOQUE 4 - OTROS MÉRITOS
 * ============================================================
 */

function puntuar_item_4_exp(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'otro'))) {
        'gestion' => 0.8,
        'servicio_academico' => 0.5,
        'distincion' => 0.7,
        'sociedad_cientifica' => 0.5,
        'otro' => 0.3,
        default => 0.0,
    };
}

function puntuar_bloque_4_exp(array $items): array
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_4_exp($item);
    }

    $b4 = limitar(redondear_2($total), 0.0, 2.0);

    return [
        '4' => $b4,
        'B4' => $b4,
    ];
}

/* ============================================================
 * DIAGNÓSTICO Y ASESOR
 * ============================================================
 */

function contar_publicaciones_impacto_exp(array $publicaciones): array
{
    $conteo = [
        'Q1' => 0,
        'Q2' => 0,
        'Q3' => 0,
        'Q4' => 0,
        'PATENTES' => 0,
        'OTRAS' => 0,
    ];

    foreach ($publicaciones as $pub) {
        if (empty($pub['es_valida'])) {
            continue;
        }

        if (($pub['tipo'] ?? '') === 'patente') {
            $conteo['PATENTES']++;
            continue;
        }

        $tipo = strtoupper((string)($pub['tipo_indice'] ?? ''));
        $cuartil = strtoupper((string)($pub['cuartil'] ?? ''));

        if (in_array($tipo, ['JCR', 'SJR'], true) && isset($conteo[$cuartil])) {
            $conteo[$cuartil]++;
        } else {
            $conteo['OTRAS']++;
        }
    }

    return $conteo;
}

function objetivos_orientativos_exp(): array
{
    return [
        'B1' => 36.0,
        'B2' => 14.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 24.0,
        '1C' => 5.0,
        '1D' => 1.5,
        '2A' => 10.0,
        '2B' => 1.0,
        '2D' => 1.2,

        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function clasificar_nivel_exp(float $actual, float $objetivo): string
{
    if ($objetivo <= 0) {
        return 'sin referencia';
    }

    $ratio = $actual / $objetivo;

    return match (true) {
        $ratio >= 1.20 => 'muy fuerte',
        $ratio >= 1.00 => 'fuerte',
        $ratio >= 0.70 => 'aceptable',
        $ratio >= 0.40 => 'débil',
        default => 'muy débil',
    };
}

function detectar_perfil_exp(array $resultado): string
{
    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $p1a = (float)$resultado['bloque_1']['1A'];
    $p1c = (float)$resultado['bloque_1']['1C'];
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($p1a >= 24 && $b2 < 10) {
        return 'Perfil investigador fuerte en publicaciones con docencia insuficiente';
    }

    if ($p1a >= 20 && $p1c < 4) {
        return 'Perfil con buena producción científica pero proyectos competitivos insuficientes';
    }

    if ($b2 >= 15 && $b1 < 26) {
        return 'Perfil docente razonable con investigación experimental insuficiente';
    }

    if ($b1 >= 32 && $b2 >= 12 && $total_b1_b2 >= 50 && $total_final >= 55) {
        return 'Perfil equilibrado y competitivo en Ciencias Experimentales';
    }

    if ($b1 < 20 && $b2 < 10) {
        return 'Perfil aún inmaduro para acreditación en Experimentales';
    }

    return 'Perfil mixto en Experimentales con fortalezas parciales y necesidad de refuerzo estratégico';
}

function generar_diagnostico_exp(array $datos, array $resultado): array
{
    $obj = objetivos_orientativos_exp();

    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $deficit_regla_1 = max(0.0, $obj['TOTAL_B1_B2'] - $total_b1_b2);
    $deficit_regla_2 = max(0.0, $obj['TOTAL_FINAL'] - $total_final);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ($resultado['bloque_1']['1A'] >= $obj['1A']) {
        $fortalezas[] = 'Producción científica principal competitiva en revistas de impacto.';
    } else {
        $debilidades[] = 'La producción científica principal aún no alcanza el nivel fuerte esperado en Experimentales.';
    }

    if ($resultado['bloque_1']['1C'] >= $obj['1C']) {
        $fortalezas[] = 'La participación en proyectos competitivos aporta solidez al expediente.';
    } else {
        $debilidades[] = 'Faltan proyectos competitivos o liderazgo suficiente en ellos.';
    }

    if ($resultado['bloque_2']['2A'] >= $obj['2A']) {
        $fortalezas[] = 'La docencia universitaria tiene un peso razonable.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable todavía es insuficiente.';
    }

    if ($resultado['bloque_1']['1D'] < $obj['1D']) {
        $alertas[] = 'La transferencia tecnológica o aplicada es baja.';
    }

    if ($resultado['bloque_2']['2B'] <= 0.0) {
        $alertas[] = 'No consta evaluación docente relevante.';
    }

    if ($deficit_regla_1 > 0) {
        $alertas[] = 'No se cumple la regla principal B1 + B2 ≥ 50.';
    }

    if ($deficit_regla_2 > 0) {
        $alertas[] = 'No se cumple la regla total final ≥ 55.';
    }

    return [
        'perfil_detectado' => detectar_perfil_exp($resultado),
        'reglas' => [
            [
                'nombre' => 'Regla principal B1 + B2 ≥ 50',
                'valor_actual' => redondear_2($total_b1_b2),
                'objetivo' => 50.0,
                'deficit' => redondear_2($deficit_regla_1),
                'cumple' => $resultado['decision']['cumple_regla_1'],
            ],
            [
                'nombre' => 'Regla total final ≥ 55',
                'valor_actual' => redondear_2($total_final),
                'objetivo' => 55.0,
                'deficit' => redondear_2($deficit_regla_2),
                'cumple' => $resultado['decision']['cumple_regla_2'],
            ],
        ],
        'bloques' => [
            'B1' => [
                'actual' => redondear_2($b1),
                'objetivo_orientativo' => $obj['B1'],
                'deficit' => redondear_2(max(0.0, $obj['B1'] - $b1)),
                'nivel' => clasificar_nivel_exp($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => redondear_2($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => redondear_2(max(0.0, $obj['B2'] - $b2)),
                'nivel' => clasificar_nivel_exp($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => redondear_2($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => redondear_2(max(0.0, $obj['B3'] - $b3)),
                'nivel' => clasificar_nivel_exp($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => redondear_2($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => redondear_2(max(0.0, $obj['B4'] - $b4)),
                'nivel' => clasificar_nivel_exp($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones_impacto' => contar_publicaciones_impacto_exp($datos['bloque_1']['publicaciones'] ?? []),
            'num_proyectos_validos' => contar_items_validos($datos['bloque_1']['proyectos'] ?? []),
            'num_transferencia_valida' => contar_items_validos($datos['bloque_1']['transferencia'] ?? []),
            'num_tesis' => contar_items_validos($datos['bloque_1']['tesis_dirigidas'] ?? []),
            'num_docencia_items' => contar_items_validos($datos['bloque_2']['docencia_universitaria'] ?? []),
            'num_eval_docente' => contar_items_validos($datos['bloque_2']['evaluacion_docente'] ?? []),
        ],
        'fortalezas' => $fortalezas,
        'debilidades' => $debilidades,
        'alertas' => $alertas,
    ];
}

function generar_acciones_asesor_exp(array $datos, array $resultado): array
{
    $acciones = [];

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $p1a = (float)$resultado['bloque_1']['1A'];
    $p1c = (float)$resultado['bloque_1']['1C'];
    $p1d = (float)$resultado['bloque_1']['1D'];
    $p2a = (float)$resultado['bloque_2']['2A'];
    $p2b = (float)$resultado['bloque_2']['2B'];
    $p2d = (float)$resultado['bloque_2']['2D'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];

    if ($total_b1_b2 < 50.0 && $p1a < 24.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Priorizar publicaciones Q1/Q2 de impacto',
            'detalle' => 'En Experimentales el peso estructural de 1A es muy alto. Conviene reforzar publicaciones con buena posición autoral y citas.',
            'impacto_estimado' => '1 Q1 fuerte puede aportar ≈ 9 a 14 puntos; 1 Q2 sólido ≈ 6 a 9.',
        ];
    }

    if ($p1c < 5.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Entrar en proyectos competitivos relevantes',
            'detalle' => 'La participación o liderazgo en proyectos internacionales/nacionales mejora mucho la solidez del perfil experimental.',
            'impacto_estimado' => '≈ 4 a 8 puntos según tipo de proyecto y rol.',
        ];
    }

    if ($total_b1_b2 < 50.0 && $p2a < 10.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Reforzar docencia universitaria acreditable',
            'detalle' => 'Aunque la investigación es dominante, la docencia sigue siendo necesaria para sostener B1+B2.',
            'impacto_estimado' => '≈ 3 a 5 puntos por curso docente sólido.',
        ];
    }

    if ($p1d < 1.5) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Aumentar transferencia científica o tecnológica',
            'detalle' => 'Patentes, contratos, prototipos o resultados transferidos mejoran el perfil y aportan diferenciación.',
            'impacto_estimado' => '≈ 1.5 a 3 puntos por mérito fuerte.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Obtener evaluación docente formal',
            'detalle' => 'DOCENTIA u otros sistemas de evaluación docente fortalecen el bloque 2.',
            'impacto_estimado' => '≈ 1 a 1.5 puntos.',
        ];
    }

    if ($p2d < 1.2) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Documentar innovación docente y materiales de laboratorio',
            'detalle' => 'Materiales docentes y prácticas de laboratorio consolidadas ayudan a completar el bloque 2.',
            'impacto_estimado' => '≈ 1.2 a 2 puntos.',
        ];
    }

    if ($total_b1_b2 >= 50.0 && $total_final < 55.0 && ($b3 + $b4) < 5.0) {
        $acciones[] = [
            'prioridad' => 7,
            'titulo' => 'Cerrar el expediente con B3 y B4',
            'detalle' => 'Si el núcleo principal ya está casi resuelto, puede ser más eficiente completar formación, estancias, postdoc o gestión.',
            'impacto_estimado' => '≈ 1 a 4 puntos adicionales.',
        ];
    }

    if (empty($acciones)) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Mantener y consolidar el perfil',
            'detalle' => 'El expediente es competitivo. Conviene sostener equilibrio entre publicaciones, proyectos, docencia y méritos complementarios.',
            'impacto_estimado' => 'Mejora cualitativa y mayor robustez del perfil.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => $a['prioridad'] <=> $b['prioridad']);

    return $acciones;
}

function generar_simulaciones_exp(array $resultado): array
{
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    return [
        [
            'escenario' => 'Añadir 1 publicación Q1 fuerte',
            'efecto_estimado' => 'Subida aproximada de 9 a 14 puntos en 1A.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 11.0),
            'nuevo_total_aprox' => redondear_2($total_final + 11.0),
        ],
        [
            'escenario' => 'Añadir 1 proyecto nacional/internacional competitivo',
            'efecto_estimado' => 'Subida aproximada de 4 a 8 puntos en 1C.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 6.0),
            'nuevo_total_aprox' => redondear_2($total_final + 6.0),
        ],
        [
            'escenario' => 'Añadir 1 curso docente fuerte',
            'efecto_estimado' => 'Subida aproximada de 3 a 5 puntos en 2A.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 4.0),
            'nuevo_total_aprox' => redondear_2($total_final + 4.0),
        ],
    ];
}

function generar_asesor_exp(array $datos, array $resultado): array
{
    $perfil = detectar_perfil_exp($resultado);
    $acciones = generar_acciones_asesor_exp($datos, $resultado);
    $simulaciones = generar_simulaciones_exp($resultado);

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($resultado['decision']['positiva']) {
        $resumen = 'El expediente alcanza evaluación positiva según las reglas programadas. El asesor recomienda consolidar liderazgo científico, proyectos y docencia.';
    } elseif ($total_b1_b2 < 50.0) {
        $resumen = 'El bloqueo principal está en el núcleo duro B1 + B2. En Experimentales la prioridad suele estar en publicaciones y proyectos, sin descuidar la docencia.';
    } elseif ($total_b1_b2 >= 50.0 && $total_final < 55.0) {
        $resumen = 'El núcleo principal está casi resuelto. La vía más eficiente es cerrar el déficit restante con B3/B4 o con refuerzos puntuales.';
    } else {
        $resumen = 'El expediente necesita un plan equilibrado de refuerzo, priorizando los méritos con mejor retorno estratégico.';
    }

    return [
        'perfil' => $perfil,
        'resumen' => $resumen,
        'acciones' => $acciones,
        'simulaciones' => $simulaciones,
    ];
}

/* ============================================================
 * EVALUACIÓN FINAL
 * ============================================================
 */

function evaluar_expediente(array $datos): array
{
    $bloque_1 = puntuar_bloque_1_exp($datos['bloque_1'] ?? []);
    $bloque_2 = puntuar_bloque_2_exp($datos['bloque_2'] ?? []);
    $bloque_3 = puntuar_bloque_3_exp($datos['bloque_3'] ?? []);
    $bloque_4 = puntuar_bloque_4_exp($datos['bloque_4'] ?? []);

    $b1 = $bloque_1['B1'];
    $b2 = $bloque_2['B2'];
    $b3 = $bloque_3['B3'];
    $b4 = $bloque_4['B4'];

    $total_b1_b2 = redondear_2($b1 + $b2);
    $total_final = redondear_2($b1 + $b2 + $b3 + $b4);

    $cumple_regla_1 = $total_b1_b2 >= 50.0;
    $cumple_regla_2 = $total_final >= 55.0;
    $evaluacion_positiva = $cumple_regla_1 && $cumple_regla_2;

    $resultado = [
        'bloque_1' => $bloque_1,
        'bloque_2' => $bloque_2,
        'bloque_3' => $bloque_3,
        'bloque_4' => $bloque_4,
        'totales' => [
            'total_b1_b2' => $total_b1_b2,
            'total_final' => $total_final,
        ],
        'decision' => [
            'positiva' => $evaluacion_positiva,
            'resultado' => $evaluacion_positiva ? 'EVALUACIÓN POSITIVA' : 'EVALUACIÓN NEGATIVA',
            'cumple_regla_1' => $cumple_regla_1,
            'cumple_regla_2' => $cumple_regla_2,
        ],
    ];

    $resultado['diagnostico'] = generar_diagnostico_exp($datos, $resultado);
    $resultado['asesor'] = generar_asesor_exp($datos, $resultado);

    return $resultado;
}

/* ============================================================
 * COMPATIBILIDAD LEGACY (AREA PROBE / CONSUMIDORES ANTIGUOS)
 * ============================================================
 */

function calcular_bloque_1_experimentales(array $datos): array
{
    return puntuar_bloque_1_exp($datos);
}

function calcular_bloque_2_experimentales(array $datos): array
{
    return puntuar_bloque_2_exp($datos);
}

function calcular_bloque_3_experimentales(array $datos): array
{
    return puntuar_bloque_3_exp($datos);
}

function calcular_bloque_4_experimentales(array $datos): array
{
    return puntuar_bloque_4_exp($datos);
}

function calcular_totales_experimentales(array $bloque_1, array $bloque_2, array $bloque_3, array $bloque_4): array
{
    $b1 = (float)($bloque_1['B1'] ?? 0.0);
    $b2 = (float)($bloque_2['B2'] ?? 0.0);
    $b3 = (float)($bloque_3['B3'] ?? 0.0);
    $b4 = (float)($bloque_4['B4'] ?? 0.0);

    return [
        'total_b1_b2' => redondear_2($b1 + $b2),
        'total_final' => redondear_2($b1 + $b2 + $b3 + $b4),
    ];
}

function evaluar_experimentales(array $totales): array
{
    $total_b1_b2 = (float)($totales['total_b1_b2'] ?? 0.0);
    $total_final = (float)($totales['total_final'] ?? 0.0);

    $cumple_regla_1 = $total_b1_b2 >= 50.0;
    $cumple_regla_2 = $total_final >= 55.0;
    $positiva = $cumple_regla_1 && $cumple_regla_2;

    return [
        'positiva' => $positiva,
        'resultado' => $positiva ? 'EVALUACION POSITIVA' : 'EVALUACION NEGATIVA',
        'cumple_regla_1' => $cumple_regla_1,
        'cumple_regla_2' => $cumple_regla_2,
    ];
}
