<?php
declare(strict_types=1);

/**
 * ============================================================
 * EVALUADOR ANECA - SALUD (PCD/PUP) - MODELO PROGRAMABLE
 * ============================================================
 *
 * Estructura análoga a Técnicas:
 * - Bloque 1: investigación y transferencia
 * - Bloque 2: docencia
 * - Bloque 3: formación y experiencia profesional/asistencial
 * - Bloque 4: otros méritos
 *
 * Reglas globales del proyecto:
 * - B1 + B2 >= 50
 * - Total final >= 55
 *
 * Este motor está diseñado como simulador/asesor orientativo.
 */

/* ============================================================
 * UTILIDADES GENERALES
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

function factor_afinidad_salud(string $afinidad): float
{
    return match (strtolower($afinidad)) {
        'total' => 1.20,
        'relacionada' => 1.00,
        'periferica' => 0.75,
        'ajena' => 0.40,
        default => 1.00,
    };
}

function factor_posicion_autor_salud(string $posicion): float
{
    return match (strtolower($posicion)) {
        'primero' => 1.20,
        'ultimo' => 1.15,
        'correspondencia' => 1.15,
        'intermedio' => 1.00,
        'secundario' => 0.85,
        default => 1.00,
    };
}

function factor_coautoria_salud(int $numero_autores): float
{
    return match (true) {
        $numero_autores <= 1 => 1.20,
        $numero_autores <= 3 => 1.10,
        $numero_autores <= 6 => 1.00,
        $numero_autores <= 10 => 0.90,
        $numero_autores <= 15 => 0.80,
        default => 0.65,
    };
}

function factor_citas_salud(int $citas, int $anios_desde_publicacion): float
{
    if ($anios_desde_publicacion < 2 && $citas < 6) {
        return 1.00;
    }

    return match (true) {
        $citas <= 1 => 0.85,
        $citas <= 5 => 0.95,
        $citas <= 15 => 1.00,
        $citas <= 40 => 1.10,
        default => 1.20,
    };
}

/* ============================================================
 * BLOQUE 1 - 1A PUBLICACIONES Y PATENTES
 * ============================================================
 */

function base_publicacion_salud(array $publicacion): float
{
    $tipo_indice = strtoupper((string)($publicacion['tipo_indice'] ?? ''));
    $cuartil = strtoupper((string)($publicacion['cuartil'] ?? ''));
    $subtipo = strtoupper((string)($publicacion['subtipo_indice'] ?? ''));

    if ($tipo_indice === 'JCR') {
        return match ($cuartil) {
            'Q1' => 10.0,
            'Q2' => 7.0,
            'Q3' => 4.0,
            'Q4' => 2.0,
            default => 1.0,
        };
    }

    if ($tipo_indice === 'SJR') {
        return match ($cuartil) {
            'Q1' => 8.0,
            'Q2' => 5.5,
            'Q3' => 3.0,
            'Q4' => 1.5,
            default => 1.0,
        };
    }

    if ($tipo_indice === 'PATENTE') {
        return match ($subtipo) {
            'B1' => 8.0,
            'B2' => 6.0,
            default => 3.0,
        };
    }

    return 0.5;
}

function puntuar_publicacion_1a_salud(array $publicacion): float
{
    if (
        empty($publicacion['es_valida']) ||
        !in_array(($publicacion['tipo'] ?? ''), ['articulo', 'patente'], true)
    ) {
        return 0.0;
    }

    $base = base_publicacion_salud($publicacion);

    if (($publicacion['tipo'] ?? '') === 'patente') {
        $factor_liderazgo = !empty($publicacion['liderazgo']) ? 1.15 : 1.00;
        return redondear_2($base * $factor_liderazgo);
    }

    $factor_estudio = match (strtolower((string)($publicacion['tipo_estudio'] ?? 'observacional'))) {
        'ensayo_clinico' => 1.15,
        'metaanalisis' => 1.12,
        'revision_sistematica' => 1.08,
        'cohorte' => 1.05,
        'observacional' => 1.00,
        'casos_controles' => 0.98,
        'serie_casos' => 0.90,
        default => 1.00,
    };

    $puntuacion = $base
        * factor_afinidad_salud((string)($publicacion['afinidad'] ?? 'relacionada'))
        * factor_posicion_autor_salud((string)($publicacion['posicion_autor'] ?? 'intermedio'))
        * factor_coautoria_salud((int)($publicacion['numero_autores'] ?? 1))
        * factor_citas_salud(
            (int)($publicacion['citas'] ?? 0),
            (int)($publicacion['anios_desde_publicacion'] ?? 3)
        )
        * $factor_estudio;

    return redondear_2($puntuacion);
}

function puntuar_1a_salud(array $publicaciones): float
{
    $total = 0.0;

    foreach ($publicaciones as $publicacion) {
        $total += puntuar_publicacion_1a_salud($publicacion);
    }

    return limitar(redondear_2($total), 0.0, 35.0);
}

/* ============================================================
 * BLOQUE 1 - 1B LIBROS Y CAPÍTULOS
 * ============================================================
 */

function base_editorial_salud(string $nivel_editorial): float
{
    return match (strtolower($nivel_editorial)) {
        'prestigiosa' => 3.0,
        'secundaria' => 1.5,
        'baja' => 0.5,
        default => 0.0,
    };
}

function factor_tipo_libro_salud(string $tipo): float
{
    return $tipo === 'libro' ? 1.10 : 0.70;
}

function puntuar_item_1b_salud(array $item): float
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

    $puntuacion = base_editorial_salud((string)($item['nivel_editorial'] ?? 'baja'))
        * factor_tipo_libro_salud((string)($item['tipo'] ?? 'capitulo'))
        * factor_afinidad_salud((string)($item['afinidad'] ?? 'relacionada'));

    return redondear_2($puntuacion);
}

function puntuar_1b_salud(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1b_salud($item);
    }

    return limitar(redondear_2($total), 0.0, 3.0);
}

/* ============================================================
 * BLOQUE 1 - 1C PROYECTOS Y CONTRATOS
 * ============================================================
 */

function base_tipo_proyecto_salud(string $tipo_proyecto): float
{
    return match (strtolower($tipo_proyecto)) {
        'internacional' => 5.0,
        'nacional' => 4.0,
        'autonomico' => 2.5,
        'hospitalario' => 2.0,
        'universidad' => 1.5,
        'contrato_empresa' => 1.5,
        'ensayo_clinico' => 3.5,
        'red_tematica' => 0.0,
        'ayuda_grupo' => 0.0,
        'no_competitivo' => 0.0,
        default => 0.0,
    };
}

function factor_rol_proyecto_salud(string $rol): float
{
    return match (strtolower($rol)) {
        'ip' => 1.50,
        'coip' => 1.30,
        'investigador' => 1.00,
        'participacion_menor' => 0.70,
        default => 1.00,
    };
}

function factor_dedicacion_salud(string $dedicacion): float
{
    return match (strtolower($dedicacion)) {
        'completa' => 1.20,
        'parcial' => 1.00,
        'residual' => 0.80,
        default => 1.00,
    };
}

function factor_duracion_salud(float $anios): float
{
    return match (true) {
        $anios >= 4.0 => 1.20,
        $anios >= 2.0 => 1.00,
        $anios > 0.0 => 0.80,
        default => 0.0,
    };
}

function puntuar_item_1c_salud(array $item): float
{
    if (empty($item['es_valido']) || empty($item['esta_certificado'])) {
        return 0.0;
    }

    $puntuacion = base_tipo_proyecto_salud((string)($item['tipo_proyecto'] ?? 'no_competitivo'))
        * factor_rol_proyecto_salud((string)($item['rol'] ?? 'investigador'))
        * factor_dedicacion_salud((string)($item['dedicacion'] ?? 'parcial'))
        * factor_duracion_salud((float)($item['anios_duracion'] ?? 0));

    return redondear_2($puntuacion);
}

function puntuar_1c_salud(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1c_salud($item);
    }

    return limitar(redondear_2($total), 0.0, 12.0);
}

/* ============================================================
 * BLOQUE 1 - 1D TRANSFERENCIA
 * ============================================================
 */

function puntuar_item_1d_salud(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $base = match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'patente_b1' => 3.0,
        'patente_b2' => 2.5,
        'guia_clinica' => 2.5,
        'protocolo_implantado' => 2.0,
        'software_explotacion' => 2.0,
        'contrato_empresa' => 2.0,
        'innovacion_asistencial' => 1.8,
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

function puntuar_1d_salud(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1d_salud($item);
    }

    return limitar(redondear_2($total), 0.0, 6.0);
}

/* ============================================================
 * BLOQUE 1 - 1E TESIS DIRIGIDAS
 * ============================================================
 */

function puntuar_item_1e_salud(array $item): float
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

function puntuar_1e_salud(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1e_salud($item);
    }

    return limitar(redondear_2($total), 0.0, 4.0);
}

/* ============================================================
 * BLOQUE 1 - 1F CONGRESOS
 * ============================================================
 */

function puntuar_1f_salud(array $items): float
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

function puntuar_item_1g_salud(array $item): float
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

function puntuar_1g_salud(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1g_salud($item);
    }

    return limitar(redondear_2($total), 0.0, 1.0);
}

/* ============================================================
 * BLOQUE 1 COMPLETO
 * ============================================================
 */

function puntuar_bloque_1_salud(array $datos): array
{
    $p1a = puntuar_1a_salud($datos['publicaciones'] ?? []);
    $p1b = puntuar_1b_salud($datos['libros'] ?? []);
    $p1c = puntuar_1c_salud($datos['proyectos'] ?? []);
    $p1d = puntuar_1d_salud($datos['transferencia'] ?? []);
    $p1e = puntuar_1e_salud($datos['tesis_dirigidas'] ?? []);
    $p1f = puntuar_1f_salud($datos['congresos'] ?? []);
    $p1g = puntuar_1g_salud($datos['otros_meritos_investigacion'] ?? []);

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

function puntuar_item_2a_salud(array $item): float
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

    $factor_clinico = !empty($item['docencia_clinica']) ? 1.05 : 1.00;

    return redondear_2($base * $factor_nivel * $factor_responsabilidad * $factor_clinico);
}

function puntuar_2a_salud(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2a_salud($item);
    }
    return limitar(redondear_2($total), 0.0, 17.0);
}

function puntuar_item_2b_salud(array $item): float
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

function puntuar_2b_salud(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2b_salud($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2c_salud(array $item): float
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

function puntuar_2c_salud(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2c_salud($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2d_salud(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'material_publicado' => 2.0,
        'proyecto_innovacion' => 1.5,
        'publicacion_docente' => 1.2,
        'simulacion_clinica' => 1.5,
        'menor' => 0.4,
        default => 0.0,
    };
}

function puntuar_2d_salud(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2d_salud($item);
    }
    return limitar(redondear_2($total), 0.0, 7.0);
}

function puntuar_bloque_2_salud(array $datos): array
{
    $p2a = puntuar_2a_salud($datos['docencia_universitaria'] ?? []);
    $p2b = puntuar_2b_salud($datos['evaluacion_docente'] ?? []);
    $p2c = puntuar_2c_salud($datos['formacion_docente'] ?? []);
    $p2d = puntuar_2d_salud($datos['material_docente'] ?? []);

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
 * BLOQUE 3 - FORMACIÓN + EXPERIENCIA PROFESIONAL/ASISTENCIAL
 * ============================================================
 */

function puntuar_item_3a_salud(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'doctorado_internacional' => 2.0,
        'especialidad_salud' => 1.5,
        'beca_competitiva' => 1.5,
        'estancia' => 1.2,
        'master' => 0.8,
        'curso_especializacion' => 0.5,
        'menor' => 0.2,
        default => 0.0,
    };
}

function puntuar_3a_salud(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3a_salud($item);
    }
    return limitar(redondear_2($total), 0.0, 6.0);
}

function puntuar_item_3b_salud(array $item): float
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

    $factor_asistencial = !empty($item['actividad_asistencial']) ? 1.10 : 1.00;

    return redondear_2($base * $factor_relacion * $factor_asistencial);
}

function puntuar_3b_salud(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3b_salud($item);
    }
    return limitar(redondear_2($total), 0.0, 2.0);
}

function puntuar_bloque_3_salud(array $datos): array
{
    $p3a = puntuar_3a_salud($datos['formacion_academica'] ?? []);
    $p3b = puntuar_3b_salud($datos['experiencia_profesional'] ?? []);

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

function puntuar_item_4_salud(array $item): float
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

function puntuar_bloque_4_salud(array $items): array
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_4_salud($item);
    }

    $b4 = limitar(redondear_2($total), 0.0, 2.0);

    return [
        '4' => $b4,
        'B4' => $b4,
    ];
}

/* ============================================================
 * DIAGNÓSTICO Y ASESOR - SALUD
 * ============================================================
 */

function contar_publicaciones_impacto_salud(array $publicaciones): array
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

function objetivos_orientativos_salud(): array
{
    return [
        'B1' => 35.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 20.0,
        '1C' => 4.0,
        '1D' => 2.0,
        '2A' => 12.0,
        '2B' => 1.5,
        '2D' => 1.5,

        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function clasificar_nivel_salud(float $actual, float $objetivo): string
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

function detectar_perfil_salud(array $resultado): string
{
    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($b1 >= 35 && $b2 < 10) {
        return 'Perfil investigador biomédico fuerte con docencia insuficiente';
    }

    if ($b2 >= 15 && $b1 < 25) {
        return 'Perfil docente-clínico razonable con investigación insuficiente';
    }

    if ($b1 >= 30 && $b2 >= 12 && $total_b1_b2 >= 50 && $total_final >= 55) {
        return 'Perfil equilibrado y competitivo en rama Salud';
    }

    if ($b1 >= 25 && $resultado['bloque_1']['1D'] < 1.0) {
        return 'Perfil investigador en Salud con transferencia baja';
    }

    if ($b1 < 20 && $b2 < 10) {
        return 'Perfil aún inmaduro para acreditación en Salud';
    }

    return 'Perfil mixto en Salud con fortalezas parciales y necesidad de refuerzo estratégico';
}

function generar_diagnostico_salud(array $datos, array $resultado): array
{
    $obj = objetivos_orientativos_salud();

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
        $fortalezas[] = 'Producción científica principal competitiva en Salud.';
    } else {
        $debilidades[] = 'La producción científica principal aún no alcanza un nivel fuerte.';
    }

    if ($resultado['bloque_2']['2A'] >= $obj['2A']) {
        $fortalezas[] = 'Docencia universitaria con peso razonable.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable sigue siendo insuficiente.';
    }

    if ($resultado['bloque_1']['1C'] >= $obj['1C']) {
        $fortalezas[] = 'Existe buen soporte en proyectos o estudios competitivos.';
    } else {
        $debilidades[] = 'Conviene reforzar proyectos, ensayos o contratos con evidencia formal.';
    }

    if ($resultado['bloque_1']['1D'] < $obj['1D']) {
        $alertas[] = 'La transferencia o implantación aplicada es baja para un perfil de Salud.';
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
        'perfil_detectado' => detectar_perfil_salud($resultado),
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
                'nivel' => clasificar_nivel_salud($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => redondear_2($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => redondear_2(max(0.0, $obj['B2'] - $b2)),
                'nivel' => clasificar_nivel_salud($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => redondear_2($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => redondear_2(max(0.0, $obj['B3'] - $b3)),
                'nivel' => clasificar_nivel_salud($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => redondear_2($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => redondear_2(max(0.0, $obj['B4'] - $b4)),
                'nivel' => clasificar_nivel_salud($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones_impacto' => contar_publicaciones_impacto_salud($datos['bloque_1']['publicaciones'] ?? []),
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

function generar_acciones_asesor_salud(array $datos, array $resultado): array
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

    if ($total_b1_b2 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar docencia universitaria acreditable',
            'detalle' => 'El subbloque 2A parece insuficiente. Conviene aumentar docencia reglada y trazable, especialmente con responsabilidad media/alta.',
            'impacto_estimado' => '≈ 3 a 5 puntos por curso docente sólido.',
        ];
    }

    if ($total_b1_b2 < 50.0 && $p1a < 20.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Priorizar publicaciones biomédicas/sanitarias de impacto',
            'detalle' => 'La producción principal necesita más peso estructural, idealmente con liderazgo autoral y continuidad temática.',
            'impacto_estimado' => '1 Q1 fuerte puede aportar ≈ 8 a 12 puntos; 1 Q2 sólido ≈ 5 a 8.',
        ];
    }

    if ($p1c < 4.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Entrar en proyectos competitivos, ensayos o estudios certificados',
            'detalle' => 'El perfil en Salud gana solidez con proyectos competitivos, liderazgo de estudios o participación relevante certificada.',
            'impacto_estimado' => '≈ 4 a 7 puntos según tipo de proyecto y rol.',
        ];
    }

    if ($p1d < 2.0) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Reforzar transferencia e implantación clínica/aplicada',
            'detalle' => 'Guías, protocolos implantados, innovación asistencial o software en uso pueden fortalecer mucho el perfil.',
            'impacto_estimado' => '≈ 2 a 3 puntos por mérito sólido de transferencia.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Obtener evaluación docente formal',
            'detalle' => 'DOCENTIA u otros instrumentos positivos refuerzan el bloque 2 y mejoran la coherencia global del expediente.',
            'impacto_estimado' => '≈ 1 a 1.5 puntos.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Documentar innovación docente y materiales',
            'detalle' => 'Material docente publicado, simulación clínica o proyectos de innovación completan mejor el bloque docente.',
            'impacto_estimado' => '≈ 1.2 a 2 puntos.',
        ];
    }

    if ($total_b1_b2 >= 50.0 && $total_final < 55.0 && ($b3 + $b4) < 5.0) {
        $acciones[] = [
            'prioridad' => 7,
            'titulo' => 'Cerrar el expediente con B3 y B4',
            'detalle' => 'Si el núcleo duro ya está casi resuelto, puede ser más eficiente completar formación, experiencia profesional/asistencial y gestión.',
            'impacto_estimado' => '≈ 1 a 4 puntos adicionales.',
        ];
    }

    if (empty($acciones)) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Mantener y consolidar el perfil',
            'detalle' => 'El expediente es competitivo. Conviene sostener el equilibrio entre publicaciones, docencia y méritos aplicados.',
            'impacto_estimado' => 'Mejora cualitativa y mayor robustez del perfil.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => $a['prioridad'] <=> $b['prioridad']);

    return $acciones;
}

function generar_simulaciones_salud(array $resultado): array
{
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    return [
        [
            'escenario' => 'Añadir 1 publicación Q1 con liderazgo autoral',
            'efecto_estimado' => 'Subida aproximada de 8 a 12 puntos en 1A.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 10.0),
            'nuevo_total_aprox' => redondear_2($total_final + 10.0),
        ],
        [
            'escenario' => 'Añadir 1 curso docente fuerte',
            'efecto_estimado' => 'Subida aproximada de 3 a 5 puntos en 2A.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 4.0),
            'nuevo_total_aprox' => redondear_2($total_final + 4.0),
        ],
        [
            'escenario' => 'Añadir 1 proyecto/ensayo competitivo con rol relevante',
            'efecto_estimado' => 'Subida aproximada de 4 a 7 puntos en 1C.',
            'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 5.5),
            'nuevo_total_aprox' => redondear_2($total_final + 5.5),
        ],
    ];
}

function generar_asesor_salud(array $datos, array $resultado): array
{
    $perfil = detectar_perfil_salud($resultado);
    $acciones = generar_acciones_asesor_salud($datos, $resultado);
    $simulaciones = generar_simulaciones_salud($resultado);

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($resultado['decision']['positiva']) {
        $resumen = 'El expediente alcanza evaluación positiva según las reglas programadas. El asesor recomienda consolidar liderazgo científico, docencia y méritos aplicados.';
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
    $bloque_1 = puntuar_bloque_1_salud($datos['bloque_1'] ?? []);
    $bloque_2 = puntuar_bloque_2_salud($datos['bloque_2'] ?? []);
    $bloque_3 = puntuar_bloque_3_salud($datos['bloque_3'] ?? []);
    $bloque_4 = puntuar_bloque_4_salud($datos['bloque_4'] ?? []);

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

    $resultado['diagnostico'] = generar_diagnostico_salud($datos, $resultado);
    $resultado['asesor'] = generar_asesor_salud($datos, $resultado);

    return $resultado;
}