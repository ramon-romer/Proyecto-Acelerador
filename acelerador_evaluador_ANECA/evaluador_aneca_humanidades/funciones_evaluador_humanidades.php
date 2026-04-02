<?php
declare(strict_types=1);

/**
 * ============================================================
 * EVALUADOR ANECA - HUMANIDADES (PCD/PUP) - MODELO PROGRAMABLE
 * ============================================================
 *
 * Reglas globales del proyecto:
 * - B1 + B2 >= 50
 * - Total final >= 55
 *
 * Simulador orientativo y asesor estratégico.
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

function factor_afinidad_hum(string $afinidad): float
{
    return match (strtolower($afinidad)) {
        'total' => 1.20,
        'relacionada' => 1.00,
        'periferica' => 0.75,
        'ajena' => 0.40,
        default => 1.00,
    };
}

function factor_autoria_hum(string $posicion): float
{
    return match (strtolower($posicion)) {
        'autor_unico' => 1.25,
        'primero' => 1.15,
        'ultimo' => 1.10,
        'intermedio' => 1.00,
        'secundario' => 0.85,
        default => 1.00,
    };
}

function factor_coautoria_hum(int $numero_autores): float
{
    return match (true) {
        $numero_autores <= 1 => 1.25,
        $numero_autores <= 2 => 1.10,
        $numero_autores <= 4 => 1.00,
        $numero_autores <= 6 => 0.90,
        default => 0.75,
    };
}

function factor_citas_hum(int $citas): float
{
    return match (true) {
        $citas <= 0 => 0.90,
        $citas <= 3 => 1.00,
        $citas <= 10 => 1.08,
        $citas <= 25 => 1.15,
        default => 1.22,
    };
}

/* ============================================================
 * BLOQUE 1 - 1A PUBLICACIONES
 * ============================================================
 */

function base_publicacion_hum(array $publicacion): float
{
    $tipo_indice = strtoupper((string)($publicacion['tipo_indice'] ?? ''));
    $cuartil = strtoupper((string)($publicacion['cuartil'] ?? ''));
    $subtipo = strtoupper((string)($publicacion['subtipo_indice'] ?? ''));

    if ($tipo_indice === 'JCR') {
        return match ($cuartil) {
            'Q1' => 9.0,
            'Q2' => 6.5,
            'Q3' => 4.0,
            'Q4' => 2.5,
            default => 1.2,
        };
    }

    if ($tipo_indice === 'SJR') {
        return match ($cuartil) {
            'Q1' => 7.5,
            'Q2' => 5.0,
            'Q3' => 3.0,
            'Q4' => 1.8,
            default => 1.0,
        };
    }

    if ($tipo_indice === 'RESH') {
        return 4.5;
    }

    if ($tipo_indice === 'FECYT') {
        return match ($subtipo) {
            'C1' => 4.0,
            'C2' => 3.0,
            'C3' => 2.0,
            default => 1.5,
        };
    }

    if ($tipo_indice === 'ERIH') {
        return match ($subtipo) {
            'PLUS' => 4.0,
            'STANDARD' => 2.5,
            default => 1.5,
        };
    }

    return 0.8;
}

function puntuar_publicacion_1a_hum(array $publicacion): float
{
    if (empty($publicacion['es_valida']) || ($publicacion['tipo'] ?? '') !== 'articulo') {
        return 0.0;
    }

    $base = base_publicacion_hum($publicacion);

    $factor_tipo = match (strtolower((string)($publicacion['tipo_aportacion'] ?? 'articulo'))) {
        'articulo' => 1.00,
        'edicion_critica' => 1.10,
        'estudio_fuentes' => 1.08,
        'traduccion_anotada' => 1.05,
        default => 1.00,
    };

    $puntuacion = $base
        * factor_afinidad_hum((string)($publicacion['afinidad'] ?? 'relacionada'))
        * factor_autoria_hum((string)($publicacion['posicion_autor'] ?? 'intermedio'))
        * factor_coautoria_hum((int)($publicacion['numero_autores'] ?? 1))
        * factor_citas_hum((int)($publicacion['citas'] ?? 0))
        * $factor_tipo;

    return redondear_2($puntuacion);
}

function puntuar_1a_hum(array $publicaciones): float
{
    $total = 0.0;
    foreach ($publicaciones as $publicacion) {
        $total += puntuar_publicacion_1a_hum($publicacion);
    }
    return limitar(redondear_2($total), 0.0, 35.0);
}

/* ============================================================
 * BLOQUE 1 - 1B LIBROS Y CAPÍTULOS
 * ============================================================
 */

function base_editorial_hum(string $nivel_editorial): float
{
    return match (strtolower($nivel_editorial)) {
        'prestigiosa' => 3.5,
        'secundaria' => 2.0,
        'baja' => 0.8,
        default => 0.0,
    };
}

function puntuar_item_1b_hum(array $item): float
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
        'libro' => 1.20,
        'capitulo' => 0.75,
        default => 1.00,
    };

    $factor_autor = factor_autoria_hum((string)($item['posicion_autor'] ?? 'autor_unico'));

    $puntuacion = base_editorial_hum((string)($item['nivel_editorial'] ?? 'baja'))
        * $factor_tipo
        * factor_afinidad_hum((string)($item['afinidad'] ?? 'relacionada'))
        * $factor_autor;

    return redondear_2($puntuacion);
}

function puntuar_1b_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1b_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 8.0);
}

/* ============================================================
 * BLOQUE 1 - 1C PROYECTOS Y CONTRATOS
 * ============================================================
 */

function base_tipo_proyecto_hum(string $tipo_proyecto): float
{
    return match (strtolower($tipo_proyecto)) {
        'internacional' => 5.0,
        'nacional' => 4.0,
        'autonomico' => 2.5,
        'universidad' => 1.5,
        'contrato' => 1.8,
        'proyecto_patrimonio' => 2.8,
        'red_tematica' => 0.0,
        'ayuda_grupo' => 0.0,
        'no_competitivo' => 0.0,
        default => 0.0,
    };
}

function factor_rol_proyecto_hum(string $rol): float
{
    return match (strtolower($rol)) {
        'ip' => 1.50,
        'coip' => 1.30,
        'investigador' => 1.00,
        'participacion_menor' => 0.70,
        default => 1.00,
    };
}

function factor_duracion_hum(float $anios): float
{
    return match (true) {
        $anios >= 4 => 1.20,
        $anios >= 2 => 1.00,
        $anios > 0 => 0.80,
        default => 0.0,
    };
}

function puntuar_item_1c_hum(array $item): float
{
    if (empty($item['es_valido']) || empty($item['esta_certificado'])) {
        return 0.0;
    }

    $puntuacion = base_tipo_proyecto_hum((string)($item['tipo_proyecto'] ?? 'no_competitivo'))
        * factor_rol_proyecto_hum((string)($item['rol'] ?? 'investigador'))
        * factor_duracion_hum((float)($item['anios_duracion'] ?? 0));

    return redondear_2($puntuacion);
}

function puntuar_1c_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1c_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 8.0);
}

/* ============================================================
 * BLOQUE 1 - 1D TRANSFERENCIA
 * ============================================================
 */

function puntuar_item_1d_hum(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $base = match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'comisariado' => 2.0,
        'catalogo_critico' => 1.8,
        'proyecto_patrimonio' => 2.0,
        'transferencia_cultural' => 2.0,
        'contrato_institucion' => 1.8,
        'edicion_fuentes' => 1.5,
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

function puntuar_1d_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1d_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 4.0);
}

/* ============================================================
 * BLOQUE 1 - 1E TESIS DIRIGIDAS
 * ============================================================
 */

function puntuar_item_1e_hum(array $item): float
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

function puntuar_1e_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1e_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 4.0);
}

/* ============================================================
 * BLOQUE 1 - 1F CONGRESOS
 * ============================================================
 */

function puntuar_1f_hum(array $items): float
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

function puntuar_item_1g_hum(array $item): float
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

function puntuar_1g_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_1g_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 1.0);
}

/* ============================================================
 * BLOQUE 1 COMPLETO
 * ============================================================
 */

function puntuar_bloque_1_hum(array $datos): array
{
    $p1a = puntuar_1a_hum($datos['publicaciones'] ?? []);
    $p1b = puntuar_1b_hum($datos['libros'] ?? []);
    $p1c = puntuar_1c_hum($datos['proyectos'] ?? []);
    $p1d = puntuar_1d_hum($datos['transferencia'] ?? []);
    $p1e = puntuar_1e_hum($datos['tesis_dirigidas'] ?? []);
    $p1f = puntuar_1f_hum($datos['congresos'] ?? []);
    $p1g = puntuar_1g_hum($datos['otros_meritos_investigacion'] ?? []);

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

function puntuar_item_2a_hum(array $item): float
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

function puntuar_2a_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2a_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 17.0);
}

function puntuar_item_2b_hum(array $item): float
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

function puntuar_2b_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2b_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2c_hum(array $item): float
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

function puntuar_2c_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2c_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2d_hum(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'material_publicado' => 2.0,
        'proyecto_innovacion' => 1.5,
        'publicacion_docente' => 1.2,
        'recurso_digital' => 1.4,
        'menor' => 0.4,
        default => 0.0,
    };
}

function puntuar_2d_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2d_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 7.0);
}

function puntuar_bloque_2_hum(array $datos): array
{
    $p2a = puntuar_2a_hum($datos['docencia_universitaria'] ?? []);
    $p2b = puntuar_2b_hum($datos['evaluacion_docente'] ?? []);
    $p2c = puntuar_2c_hum($datos['formacion_docente'] ?? []);
    $p2d = puntuar_2d_hum($datos['material_docente'] ?? []);

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

function puntuar_item_3a_hum(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'doctorado_internacional' => 2.0,
        'beca_competitiva' => 1.5,
        'estancia' => 1.2,
        'master' => 0.8,
        'curso_especializacion' => 0.5,
        'acreditacion_lenguas' => 0.6,
        'menor' => 0.2,
        default => 0.0,
    };
}

function puntuar_3a_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3a_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 6.0);
}

function puntuar_item_3b_hum(array $item): float
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

function puntuar_3b_hum(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3b_hum($item);
    }
    return limitar(redondear_2($total), 0.0, 2.0);
}

function puntuar_bloque_3_hum(array $datos): array
{
    $p3a = puntuar_3a_hum($datos['formacion_academica'] ?? []);
    $p3b = puntuar_3b_hum($datos['experiencia_profesional'] ?? []);

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

function puntuar_item_4_hum(array $item): float
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

function puntuar_bloque_4_hum(array $items): array
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_4_hum($item);
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

function contar_publicaciones_impacto_hum(array $publicaciones): array
{
    $conteo = [
        'Q1' => 0,
        'Q2' => 0,
        'Q3' => 0,
        'Q4' => 0,
        'RESH' => 0,
        'ERIH' => 0,
        'FECYT' => 0,
        'OTRAS' => 0,
    ];

    foreach ($publicaciones as $pub) {
        if (empty($pub['es_valida'])) {
            continue;
        }

        $tipo = strtoupper((string)($pub['tipo_indice'] ?? ''));
        $cuartil = strtoupper((string)($pub['cuartil'] ?? ''));

        if (in_array($tipo, ['JCR', 'SJR'], true) && isset($conteo[$cuartil])) {
            $conteo[$cuartil]++;
            continue;
        }

        if (isset($conteo[$tipo])) {
            $conteo[$tipo]++;
            continue;
        }

        $conteo['OTRAS']++;
    }

    return $conteo;
}

function objetivos_orientativos_hum(): array
{
    return [
        'B1' => 34.0,
        'B2' => 16.0,
        'B3' => 3.0,
        'B4' => 1.0,
        '1A' => 16.0,
        '1B' => 5.0,
        '1C' => 3.0,
        '1D' => 1.5,
        '2A' => 12.0,
        '2B' => 1.5,
        '2D' => 1.5,
        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function clasificar_nivel_hum(float $actual, float $objetivo): string
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

function detectar_perfil_hum(array $resultado): string
{
    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $p1b = (float)$resultado['bloque_1']['1B'];
    $p1a = (float)$resultado['bloque_1']['1A'];
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($p1b >= 5 && $p1a < 12) {
        return 'Perfil humanístico fuerte en libros/capítulos, pero más débil en revistas indexadas';
    }

    if ($b1 >= 34 && $b2 < 10) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }

    if ($b2 >= 15 && $b1 < 24) {
        return 'Perfil docente sólido con investigación insuficiente';
    }

    if ($b1 >= 30 && $b2 >= 12 && $total_b1_b2 >= 50 && $total_final >= 55) {
        return 'Perfil equilibrado y competitivo en Humanidades';
    }

    if ($b1 < 20 && $b2 < 10) {
        return 'Perfil aún inmaduro para acreditación en Humanidades';
    }

    return 'Perfil mixto en Humanidades con fortalezas parciales y necesidad de refuerzo estratégico';
}

function generar_diagnostico_hum(array $datos, array $resultado): array
{
    $obj = objetivos_orientativos_hum();

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
        $fortalezas[] = 'Producción en revistas indexadas con nivel competitivo.';
    } else {
        $debilidades[] = 'La producción en revistas indexadas todavía no es suficientemente fuerte.';
    }

    if ($resultado['bloque_1']['1B'] >= $obj['1B']) {
        $fortalezas[] = 'Bloque de libros/capítulos bien desarrollado, algo especialmente valioso en Humanidades.';
    }

    if ($resultado['bloque_2']['2A'] >= $obj['2A']) {
        $fortalezas[] = 'Docencia universitaria con peso razonable.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable sigue siendo insuficiente.';
    }

    if ($resultado['bloque_1']['1C'] < $obj['1C']) {
        $debilidades[] = 'Conviene reforzar proyectos o contratos con base competitiva.';
    }

    if ($resultado['bloque_1']['1D'] < $obj['1D']) {
        $alertas[] = 'La transferencia cultural o social es baja para el perfil actual.';
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
        'perfil_detectado' => detectar_perfil_hum($resultado),
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
                'nivel' => clasificar_nivel_hum($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => redondear_2($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => redondear_2(max(0.0, $obj['B2'] - $b2)),
                'nivel' => clasificar_nivel_hum($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => redondear_2($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => redondear_2(max(0.0, $obj['B3'] - $b3)),
                'nivel' => clasificar_nivel_hum($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => redondear_2($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => redondear_2(max(0.0, $obj['B4'] - $b4)),
                'nivel' => clasificar_nivel_hum($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones_impacto' => contar_publicaciones_impacto_hum($datos['bloque_1']['publicaciones'] ?? []),
            'num_libros' => contar_items_validos($datos['bloque_1']['libros'] ?? []),
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

function generar_acciones_asesor_hum(array $datos, array $resultado): array
{
    $acciones = [];

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $p1a = (float)$resultado['bloque_1']['1A'];
    $p1b = (float)$resultado['bloque_1']['1B'];
    $p1c = (float)$resultado['bloque_1']['1C'];
    $p1d = (float)$resultado['bloque_1']['1D'];
    $p2a = (float)$resultado['bloque_2']['2A'];
    $p2b = (float)$resultado['bloque_2']['2B'];
    $p2d = (float)$resultado['bloque_2']['2D'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];

    if ($total_b1_b2 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar docencia universitaria acreditable',
            'detalle' => 'El subbloque 2A parece insuficiente. Conviene aumentar docencia reglada con trazabilidad y responsabilidad académica.',
            'impacto_estimado' => '≈ 3 a 5 puntos por curso docente sólido.',
        ];
    }

    if ($total_b1_b2 < 50.0 && $p1a < 16.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Subir el peso de revistas indexadas',
            'detalle' => 'En Humanidades sigue siendo estratégico reforzar publicaciones indexadas, especialmente si el perfil descansa demasiado en libros.',
            'impacto_estimado' => '1 publicación fuerte indexada puede aportar ≈ 4 a 8 puntos según calidad y autoría.',
        ];
    }

    if ($p1b < 5.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Potenciar libros o capítulos en editoriales de prestigio',
            'detalle' => 'En Humanidades los libros de investigación y capítulos de calidad siguen teniendo gran valor estructural.',
            'impacto_estimado' => 'Un libro fuerte puede aportar ≈ 4 a 6 puntos; un capítulo relevante ≈ 1.5 a 3.',
        ];
    }

    if ($p1c < 3.0) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Entrar en proyectos competitivos o contratos culturales',
            'detalle' => 'Los proyectos mejoran mucho la solidez del expediente y aportan contexto de liderazgo o integración en equipos.',
            'impacto_estimado' => '≈ 3 a 6 puntos según tipo y rol.',
        ];
    }

    if ($p1d < 1.5) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Reforzar transferencia cultural y social',
            'detalle' => 'Comisariados, catálogos críticos, proyectos patrimoniales o transferencia a instituciones ayudan a completar el perfil.',
            'impacto_estimado' => '≈ 1.5 a 3 puntos por mérito fuerte.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Obtener evaluación docente formal',
            'detalle' => 'DOCENTIA u otros sistemas de evaluación docente fortalecen claramente el bloque 2.',
            'impacto_estimado' => '≈ 1 a 1.5 puntos.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 7,
            'titulo' => 'Documentar innovación docente y materiales',
            'detalle' => 'Material docente, recursos digitales o proyectos de innovación ayudan a rematar el bloque 2.',
            'impacto_estimado' => '≈ 1.2 a 2 puntos.',
        ];
    }

    if ($total_b1_b2 >= 50.0 && $total_final < 55.0 && ($b3 + $b4) < 5.0) {
        $acciones[] = [
            'prioridad' => 8,
            'titulo' => 'Cerrar el expediente con B3 y B4',
            'detalle' => 'Si el núcleo principal está casi resuelto, puede ser más eficiente sumar formación, experiencia y gestión.',
            'impacto_estimado' => '≈ 1 a 4 puntos adicionales.',
        ];
    }

    if (empty($acciones)) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Mantener y consolidar el perfil',
            'detalle' => 'El expediente es competitivo. Conviene sostener equilibrio entre investigación, docencia y visibilidad académica.',
            'impacto_estimado' => 'Mejora cualitativa del perfil.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => $a['prioridad'] <=> $b['prioridad']);

    return $acciones;
}

function generar_simulaciones_hum(array $resultado): array
{
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    return [
        [
            'escenario' => 'Añadir 1 publicación indexada fuerte',
            'efecto_estimado' => 'Subida aproximada de 5 a 8 puntos en 1A.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 6.5),
            'nuevo_total_aprox' => redondear_2($total_final + 6.5),
        ],
        [
            'escenario' => 'Añadir 1 libro de investigación en editorial prestigiosa',
            'efecto_estimado' => 'Subida aproximada de 4 a 6 puntos en 1B.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 5.0),
            'nuevo_total_aprox' => redondear_2($total_final + 5.0),
        ],
        [
            'escenario' => 'Añadir 1 curso docente fuerte',
            'efecto_estimado' => 'Subida aproximada de 3 a 5 puntos en 2A.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 4.0),
            'nuevo_total_aprox' => redondear_2($total_final + 4.0),
        ],
    ];
}

function generar_asesor_hum(array $datos, array $resultado): array
{
    $perfil = detectar_perfil_hum($resultado);
    $acciones = generar_acciones_asesor_hum($datos, $resultado);
    $simulaciones = generar_simulaciones_hum($resultado);

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($resultado['decision']['positiva']) {
        $resumen = 'El expediente alcanza evaluación positiva según las reglas programadas. El asesor recomienda consolidar el equilibrio entre publicaciones, libros y docencia.';
    } elseif ($total_b1_b2 < 50.0) {
        $resumen = 'El bloqueo principal está en el núcleo duro B1 + B2. La prioridad estratégica debe centrarse en investigación y/o docencia.';
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
    $bloque_1 = puntuar_bloque_1_hum($datos['bloque_1'] ?? []);
    $bloque_2 = puntuar_bloque_2_hum($datos['bloque_2'] ?? []);
    $bloque_3 = puntuar_bloque_3_hum($datos['bloque_3'] ?? []);
    $bloque_4 = puntuar_bloque_4_hum($datos['bloque_4'] ?? []);

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

    $resultado['diagnostico'] = generar_diagnostico_hum($datos, $resultado);
    $resultado['asesor'] = generar_asesor_hum($datos, $resultado);

    return $resultado;
}