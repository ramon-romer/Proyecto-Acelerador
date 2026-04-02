<?php
declare(strict_types=1);

/**
 * ============================================================
 * EVALUADOR ANECA - TÉCNICAS (PCD/PUP)
 * ============================================================
 *
 * Reparto orientativo máximo del bloque 1 en Técnicas:
 * - 1A Publicaciones y patentes: 35
 * - 1B Libros y capítulos: 3
 * - 1C Proyectos y contratos: 12
 * - 1D Transferencia: 6
 * - 1E Dirección de tesis: 4
 * - 1F Congresos: 2
 * - 1G Otros méritos: 1
 *
 * Docencia:
 * - 2A: 17
 * - 2B: 3
 * - 2C: 3
 * - 2D: 7
 * - Bloque 2 total: 30
 *
 * Formación + experiencia profesional:
 * - 3A: 6
 * - 3B: 2
 * - Bloque 3 total: 8
 *
 * Bloque 4:
 * - Otros méritos: 2
 *
 * Decisión final:
 * - B1 + B2 >= 50
 * - B1 + B2 + B3 + B4 >= 55
 */

/* ============================================================
 * FUNCIONES GENERALES
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

/* ============================================================
 * FACTORES COMUNES
 * ============================================================
 */

function factor_afinidad(string $afinidad): float
{
    return match (strtolower($afinidad)) {
        'total' => 1.20,
        'relacionada' => 1.00,
        'periferica' => 0.75,
        'ajena' => 0.40,
        default => 1.00,
    };
}

function factor_posicion_autor(string $posicion): float
{
    return match (strtolower($posicion)) {
        'primero' => 1.20,
        'ultimo' => 1.10,
        'correspondencia' => 1.10,
        'intermedio' => 1.00,
        'secundario' => 0.85,
        default => 1.00,
    };
}

function factor_coautoria(int $numero_autores): float
{
    return match (true) {
        $numero_autores <= 1 => 1.20,
        $numero_autores <= 3 => 1.10,
        $numero_autores <= 5 => 1.00,
        $numero_autores <= 8 => 0.90,
        $numero_autores <= 12 => 0.75,
        default => 0.60,
    };
}

function factor_citas(int $citas, int $anios_desde_publicacion): float
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

/**
 * En Técnicas el documento prioriza JCR por terciles:
 * - T1 alto
 * - T2 medio
 * - T3 bajo
 * Además, en Informática CORE A/A+/CSIE clase 1 se equiparan a T3.
 * Aquí usamos una modelización programable.
 */
function base_publicacion_tecnica(array $publicacion): float
{
    $tipo_indice = strtoupper((string)($publicacion['tipo_indice'] ?? ''));
    $tercil = strtoupper((string)($publicacion['tercil'] ?? ''));
    $subtipo = strtoupper((string)($publicacion['subtipo_indice'] ?? ''));

    // JCR por terciles
    if ($tipo_indice === 'JCR') {
        return match ($tercil) {
            'T1' => 10.0,
            'T2' => 7.0,
            'T3' => 4.0,
            default => 1.5,
        };
    }

    // CORE / CSIE en Informática equivalentes a T3
    if ($tipo_indice === 'CORE' && in_array($subtipo, ['A', 'A+'], true)) {
        return 4.0;
    }

    if ($tipo_indice === 'CSIE' && $subtipo === 'CLASE_1') {
        return 4.0;
    }

    // Arquitectura / otros índices admitidos del área
    if (in_array($tipo_indice, ['RESH', 'AVERY', 'RIBA', 'ARTS_HUMANITIES'], true)) {
        return 3.0;
    }

    // Patentes dentro de 1A
    if ($tipo_indice === 'PATENTE') {
        return match ($subtipo) {
            'B1' => 8.0,
            'B2' => 6.0,
            default => 3.0,
        };
    }

    return 0.5;
}

function puntuar_publicacion_1a(array $publicacion): float
{
    if (
        empty($publicacion['es_valida']) ||
        !in_array(($publicacion['tipo'] ?? ''), ['articulo', 'patente'], true)
    ) {
        return 0.0;
    }

    $base = base_publicacion_tecnica($publicacion);

    // Para patentes, si quieres simplificar, se puede puntuar con base y liderazgo
    if (($publicacion['tipo'] ?? '') === 'patente') {
        $factor_liderazgo = !empty($publicacion['liderazgo']) ? 1.15 : 1.00;
        return redondear_2($base * $factor_liderazgo);
    }

    $puntuacion = $base
        * factor_afinidad((string)($publicacion['afinidad'] ?? 'relacionada'))
        * factor_posicion_autor((string)($publicacion['posicion_autor'] ?? 'intermedio'))
        * factor_coautoria((int)($publicacion['numero_autores'] ?? 1))
        * factor_citas(
            (int)($publicacion['citas'] ?? 0),
            (int)($publicacion['anios_desde_publicacion'] ?? 3)
        );

    return redondear_2($puntuacion);
}

function puntuar_1a(array $publicaciones): float
{
    $total = 0.0;

    foreach ($publicaciones as $publicacion) {
        $total += puntuar_publicacion_1a($publicacion);
    }

    return limitar(redondear_2($total), 0.0, 35.0);
}

/* ============================================================
 * BLOQUE 1 - 1B LIBROS Y CAPÍTULOS
 * ============================================================
 */

function base_editorial(string $nivel_editorial): float
{
    return match (strtolower($nivel_editorial)) {
        'prestigiosa' => 3.0,
        'secundaria' => 1.5,
        'baja' => 0.5,
        default => 0.0,
    };
}

function factor_tipo_libro(string $tipo): float
{
    return $tipo === 'libro' ? 1.10 : 0.70;
}

function puntuar_item_1b(array $item): float
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

    $puntuacion = base_editorial((string)($item['nivel_editorial'] ?? 'baja'))
        * factor_tipo_libro((string)($item['tipo'] ?? 'capitulo'))
        * factor_afinidad((string)($item['afinidad'] ?? 'relacionada'));

    return redondear_2($puntuacion);
}

function puntuar_1b(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1b($item);
    }

    return limitar(redondear_2($total), 0.0, 3.0);
}

/* ============================================================
 * BLOQUE 1 - 1C PROYECTOS Y CONTRATOS
 * ============================================================
 *
 * El documento indica:
 * - INT-NAC = alta
 * - AUT-UNI = media
 * - IP = alta
 * - No cuentan ayudas a grupos I+D o redes temáticas
 */

function base_tipo_proyecto(string $tipo_proyecto): float
{
    return match (strtolower($tipo_proyecto)) {
        'internacional' => 5.0,
        'nacional' => 4.0,
        'autonomico' => 2.5,
        'universidad' => 1.5,
        'contrato_empresa' => 1.5,
        'red_tematica' => 0.0,
        'ayuda_grupo' => 0.0,
        'no_competitivo' => 0.0,
        default => 0.0,
    };
}

function factor_rol_proyecto(string $rol): float
{
    return match (strtolower($rol)) {
        'ip' => 1.50,
        'coip' => 1.30,
        'investigador' => 1.00,
        'participacion_menor' => 0.70,
        default => 1.00,
    };
}

function factor_dedicacion(string $dedicacion): float
{
    return match (strtolower($dedicacion)) {
        'completa' => 1.20,
        'parcial' => 1.00,
        'residual' => 0.80,
        default => 1.00,
    };
}

function factor_duracion(float $anios): float
{
    return match (true) {
        $anios >= 4.0 => 1.20,
        $anios >= 2.0 => 1.00,
        $anios > 0.0 => 0.80,
        default => 0.0,
    };
}

function puntuar_item_1c(array $item): float
{
    if (empty($item['es_valido']) || empty($item['esta_certificado'])) {
        return 0.0;
    }

    $puntuacion = base_tipo_proyecto((string)($item['tipo_proyecto'] ?? 'no_competitivo'))
        * factor_rol_proyecto((string)($item['rol'] ?? 'investigador'))
        * factor_dedicacion((string)($item['dedicacion'] ?? 'parcial'))
        * factor_duracion((float)($item['anios_duracion'] ?? 0));

    return redondear_2($puntuacion);
}

function puntuar_1c(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1c($item);
    }

    return limitar(redondear_2($total), 0.0, 12.0);
}

/* ============================================================
 * BLOQUE 1 - 1D TRANSFERENCIA
 * ============================================================
 *
 * El documento destaca:
 * - patentes B1/B2
 * - contratos con empresa
 * - software registrado en explotación
 * - EBT
 */

function puntuar_item_1d(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    $base = match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'patente_b1' => 3.0,
        'patente_b2' => 2.5,
        'contrato_empresa' => 2.0,
        'software_explotacion' => 2.0,
        'ebt' => 2.0,
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

function puntuar_1d(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1d($item);
    }

    return limitar(redondear_2($total), 0.0, 6.0);
}

/* ============================================================
 * BLOQUE 1 - 1E TESIS DIRIGIDAS
 * ============================================================
 */

function puntuar_item_1e(array $item): float
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

function puntuar_1e(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1e($item);
    }

    return limitar(redondear_2($total), 0.0, 4.0);
}

/* ============================================================
 * BLOQUE 1 - 1F CONGRESOS
 * ============================================================
 */

function puntuar_1f(array $items): float
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

function puntuar_item_1g(array $item): float
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
        default => 0.0,
    };
}

function puntuar_1g(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += puntuar_item_1g($item);
    }

    return limitar(redondear_2($total), 0.0, 1.0);
}

/* ============================================================
 * BLOQUE 1 COMPLETO
 * ============================================================
 */

function puntuar_bloque_1(array $datos): array
{
    $p1a = puntuar_1a($datos['publicaciones'] ?? []);
    $p1b = puntuar_1b($datos['libros'] ?? []);
    $p1c = puntuar_1c($datos['proyectos'] ?? []);
    $p1d = puntuar_1d($datos['transferencia'] ?? []);
    $p1e = puntuar_1e($datos['tesis_dirigidas'] ?? []);
    $p1f = puntuar_1f($datos['congresos'] ?? []);
    $p1g = puntuar_1g($datos['otros_meritos_investigacion'] ?? []);

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

function puntuar_item_2a(array $item): float
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

function puntuar_2a(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2a($item);
    }
    return limitar(redondear_2($total), 0.0, 17.0);
}

function puntuar_item_2b(array $item): float
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

function puntuar_2b(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2b($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2c(array $item): float
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

function puntuar_2c(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2c($item);
    }
    return limitar(redondear_2($total), 0.0, 3.0);
}

function puntuar_item_2d(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'menor'))) {
        'material_publicado' => 2.0,
        'proyecto_innovacion' => 1.5,
        'publicacion_docente' => 1.2,
        'menor' => 0.4,
        default => 0.0,
    };
}

function puntuar_2d(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_2d($item);
    }
    return limitar(redondear_2($total), 0.0, 7.0);
}

function puntuar_bloque_2(array $datos): array
{
    $p2a = puntuar_2a($datos['docencia_universitaria'] ?? []);
    $p2b = puntuar_2b($datos['evaluacion_docente'] ?? []);
    $p2c = puntuar_2c($datos['formacion_docente'] ?? []);
    $p2d = puntuar_2d($datos['material_docente'] ?? []);

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
 * BLOQUE 3 - FORMACIÓN + EXPERIENCIA PROFESIONAL
 * ============================================================
 */

function puntuar_item_3a(array $item): float
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
        'menor' => 0.2,
        default => 0.0,
    };
}

function puntuar_3a(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3a($item);
    }
    return limitar(redondear_2($total), 0.0, 6.0);
}

function puntuar_item_3b(array $item): float
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

function puntuar_3b(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_3b($item);
    }
    return limitar(redondear_2($total), 0.0, 2.0);
}

function puntuar_bloque_3(array $datos): array
{
    $p3a = puntuar_3a($datos['formacion_academica'] ?? []);
    $p3b = puntuar_3b($datos['experiencia_profesional'] ?? []);

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

function puntuar_item_4(array $item): float
{
    if (empty($item['es_valido'])) {
        return 0.0;
    }

    return match (strtolower((string)($item['tipo'] ?? 'otro'))) {
        'gestion' => 0.8,
        'servicio_academico' => 0.5,
        'distincion' => 0.7,
        'otro' => 0.3,
        default => 0.0,
    };
}

function puntuar_bloque_4(array $items): array
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += puntuar_item_4($item);
    }

    $b4 = limitar(redondear_2($total), 0.0, 2.0);

    return [
        '4' => $b4,
        'B4' => $b4,
    ];
}

//<?php
//declare(strict_types=1);

/* ============================================================
 * DIAGNÓSTICO Y ASESOR INTELIGENTE - RAMA TÉCNICA
 * ============================================================
 */

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

function contar_publicaciones_impacto(array $publicaciones): array
{
    $conteo = [
        'T1' => 0,
        'T2' => 0,
        'T3' => 0,
        'CORE_A' => 0,
        'PATENTES' => 0,
        'OTRAS' => 0,
    ];

    foreach ($publicaciones as $pub) {
        if (empty($pub['es_valida'])) {
            continue;
        }

        $tipo = strtoupper((string)($pub['tipo_indice'] ?? ''));
        $tercil = strtoupper((string)($pub['tercil'] ?? ''));
        $subtipo = strtoupper((string)($pub['subtipo_indice'] ?? ''));

        if (($pub['tipo'] ?? '') === 'patente') {
            $conteo['PATENTES']++;
            continue;
        }

        if ($tipo === 'JCR') {
            if (isset($conteo[$tercil])) {
                $conteo[$tercil]++;
            } else {
                $conteo['OTRAS']++;
            }
            continue;
        }

        if ($tipo === 'CORE' && in_array($subtipo, ['A', 'A+'], true)) {
            $conteo['CORE_A']++;
            continue;
        }

        $conteo['OTRAS']++;
    }

    return $conteo;
}

function objetivos_orientativos_tecnicas(): array
{
    return [
        'B1' => 35.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 20.0,
        '1B' => 1.0,
        '1C' => 4.0,
        '1D' => 2.0,
        '1E' => 1.0,
        '1F' => 0.5,
        '1G' => 0.3,

        '2A' => 12.0,
        '2B' => 1.5,
        '2C' => 0.8,
        '2D' => 1.5,

        '3A' => 2.0,
        '3B' => 1.0,

        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function clasificar_nivel(float $actual, float $objetivo): string
{
    if ($objetivo <= 0) {
        return 'sin_referencia';
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

function detectar_perfil_tecnico(array $resultado): string
{
    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];
    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    if ($b1 >= 35 && $b2 < 10) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }

    if ($b2 >= 15 && $b1 < 25) {
        return 'Perfil docente razonable con investigación insuficiente';
    }

    if ($b1 >= 30 && $b2 >= 12 && $total_b1_b2 >= 50 && $total_final >= 55) {
        return 'Perfil equilibrado y competitivo para acreditación técnica';
    }

    if ($b1 >= 25 && $resultado['bloque_1']['1D'] < 1.0) {
        return 'Perfil técnico-investigador con transferencia débil';
    }

    if ($b1 < 20 && $b2 < 10) {
        return 'Perfil aún inmaduro para acreditación en rama técnica';
    }

    if (($b3 + $b4) >= 4.0 && $total_b1_b2 < 50) {
        return 'Perfil con méritos complementarios aceptables, pero núcleo B1+B2 insuficiente';
    }

    return 'Perfil mixto con fortalezas parciales y necesidad de refuerzo estratégico';
}

function generar_diagnostico_tecnico(array $datos, array $resultado): array
{
    $obj = objetivos_orientativos_tecnicas();

    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $deficit_regla_1 = max(0.0, $obj['TOTAL_B1_B2'] - $total_b1_b2);
    $deficit_regla_2 = max(0.0, $obj['TOTAL_FINAL'] - $total_final);

    $publicaciones = $datos['bloque_1']['publicaciones'] ?? [];
    $conteo_publicaciones = contar_publicaciones_impacto($publicaciones);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ($resultado['bloque_1']['1A'] >= $obj['1A']) {
        $fortalezas[] = 'Producción científica de impacto competitiva en 1A.';
    } else {
        $debilidades[] = 'La producción científica principal (1A) todavía no alcanza un nivel robusto.';
    }

    if ($resultado['bloque_2']['2A'] >= $obj['2A']) {
        $fortalezas[] = 'Docencia universitaria con volumen razonable o fuerte.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable (2A) es insuficiente para sostener el expediente.';
    }

    if ($resultado['bloque_1']['1C'] >= $obj['1C']) {
        $fortalezas[] = 'Participación en proyectos/contratos con buen peso relativo.';
    } else {
        $debilidades[] = 'Falta más peso en proyectos competitivos o contratos certificados.';
    }

    if ($resultado['bloque_1']['1D'] < $obj['1D']) {
        $alertas[] = 'La transferencia es baja para un perfil técnico. Conviene reforzarla.';
    }

    if ($resultado['bloque_2']['2B'] <= 0.0) {
        $alertas[] = 'No consta evaluación docente relevante. Esto penaliza la solidez del bloque 2.';
    }

    if ($deficit_regla_1 > 0) {
        $alertas[] = 'No se cumple la regla principal B1 + B2 ≥ 50.';
    }

    if ($deficit_regla_2 > 0) {
        $alertas[] = 'No se cumple la regla total B1 + B2 + B3 + B4 ≥ 55.';
    }

    return [
        'perfil_detectado' => detectar_perfil_tecnico($resultado),

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
                'nivel' => clasificar_nivel($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => redondear_2($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => redondear_2(max(0.0, $obj['B2'] - $b2)),
                'nivel' => clasificar_nivel($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => redondear_2($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => redondear_2(max(0.0, $obj['B3'] - $b3)),
                'nivel' => clasificar_nivel($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => redondear_2($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => redondear_2(max(0.0, $obj['B4'] - $b4)),
                'nivel' => clasificar_nivel($b4, $obj['B4']),
            ],
        ],

        'subbloques' => [
            '1A' => [
                'actual' => redondear_2((float)$resultado['bloque_1']['1A']),
                'objetivo_orientativo' => $obj['1A'],
                'deficit' => redondear_2(max(0.0, $obj['1A'] - (float)$resultado['bloque_1']['1A'])),
            ],
            '1C' => [
                'actual' => redondear_2((float)$resultado['bloque_1']['1C']),
                'objetivo_orientativo' => $obj['1C'],
                'deficit' => redondear_2(max(0.0, $obj['1C'] - (float)$resultado['bloque_1']['1C'])),
            ],
            '1D' => [
                'actual' => redondear_2((float)$resultado['bloque_1']['1D']),
                'objetivo_orientativo' => $obj['1D'],
                'deficit' => redondear_2(max(0.0, $obj['1D'] - (float)$resultado['bloque_1']['1D'])),
            ],
            '2A' => [
                'actual' => redondear_2((float)$resultado['bloque_2']['2A']),
                'objetivo_orientativo' => $obj['2A'],
                'deficit' => redondear_2(max(0.0, $obj['2A'] - (float)$resultado['bloque_2']['2A'])),
            ],
            '2B' => [
                'actual' => redondear_2((float)$resultado['bloque_2']['2B']),
                'objetivo_orientativo' => $obj['2B'],
                'deficit' => redondear_2(max(0.0, $obj['2B'] - (float)$resultado['bloque_2']['2B'])),
            ],
        ],

        'conteos' => [
            'publicaciones_impacto' => $conteo_publicaciones,
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

function generar_acciones_asesor_tecnico(array $datos, array $resultado): array
{
    $acciones = [];

    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $p1a = (float)$resultado['bloque_1']['1A'];
    $p1c = (float)$resultado['bloque_1']['1C'];
    $p1d = (float)$resultado['bloque_1']['1D'];
    $p2a = (float)$resultado['bloque_2']['2A'];
    $p2b = (float)$resultado['bloque_2']['2B'];
    $p2d = (float)$resultado['bloque_2']['2D'];

    if ($total_b1_b2 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar docencia universitaria acreditable',
            'detalle' => 'El cuello de botella principal parece estar en 2A. Conviene aumentar docencia reglada con responsabilidad media/alta.',
            'impacto_estimado' => '≈ 3 a 5 puntos por curso docente sólido (según horas, nivel y responsabilidad).',
        ];
    }

    if ($total_b1_b2 < 50.0 && $p1a < 20.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Priorizar publicaciones de impacto',
            'detalle' => 'El subbloque 1A necesita más peso estructural. En Técnicas esto suele ser decisivo.',
            'impacto_estimado' => '1 artículo T1 bien posicionado puede aportar ≈ 8 a 12 puntos; 1 T2 suele aportar ≈ 5 a 8.',
        ];
    }

    if ($p1c < 4.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Entrar en proyectos competitivos o contratos certificados',
            'detalle' => 'El expediente ganaría robustez con proyectos nacionales/internacionales o contratos de transferencia certificados.',
            'impacto_estimado' => 'Un proyecto nacional con rol relevante puede aportar ≈ 4 a 7 puntos; como IP, más.',
        ];
    }

    if ($p1d < 2.0) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Aumentar transferencia tecnológica',
            'detalle' => 'En rama técnica conviene reforzar contratos con empresa, software en explotación, patente o EBT.',
            'impacto_estimado' => 'Un mérito fuerte de transferencia puede aportar ≈ 2 a 3 puntos, además de mejorar el perfil.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Conseguir evaluación docente formal',
            'detalle' => 'Obtener resultados positivos en DOCENTIA o instrumentos equivalentes fortalece el bloque 2.',
            'impacto_estimado' => '≈ 1 a 1.5 puntos por evaluación positiva/excelente, con buen valor cualitativo.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Documentar innovación y materiales docentes',
            'detalle' => 'Publicar material docente o participar en proyectos de innovación ayuda a completar el bloque 2.',
            'impacto_estimado' => '≈ 1.2 a 2 puntos por mérito sólido en material/innovación.',
        ];
    }

    if ($total_b1_b2 >= 50.0 && $total_final < 55.0 && ($b3 + $b4) < 5.0) {
        $acciones[] = [
            'prioridad' => 7,
            'titulo' => 'Cerrar el expediente con B3 y B4',
            'detalle' => 'Ya casi se cumple el núcleo duro. La vía más eficiente puede ser completar formación, experiencia profesional o gestión.',
            'impacto_estimado' => '≈ 1 a 4 puntos adicionales si aún hay margen en B3/B4.',
        ];
    }

    if (empty($acciones)) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Mantener y consolidar el perfil',
            'detalle' => 'El expediente es competitivo. Conviene sostener el equilibrio entre publicaciones, docencia y transferencia.',
            'impacto_estimado' => 'Mejora cualitativa del perfil y mayor estabilidad ante evaluación detallada.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => $a['prioridad'] <=> $b['prioridad']);

    return $acciones;
}

function generar_simulaciones_tecnico(array $resultado): array
{
    $simulaciones = [];

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $simulaciones[] = [
        'escenario' => 'Añadir 1 artículo T1 bien posicionado',
        'efecto_estimado' => 'Subida aproximada de 8 a 12 puntos en 1A.',
        'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 10.0),
        'nuevo_total_aprox' => redondear_2($total_final + 10.0),
    ];

    $simulaciones[] = [
        'escenario' => 'Añadir 1 curso docente fuerte (2A)',
        'efecto_estimado' => 'Subida aproximada de 3 a 5 puntos en docencia universitaria.',
        'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 4.0),
        'nuevo_total_aprox' => redondear_2($total_final + 4.0),
    ];

    $simulaciones[] = [
        'escenario' => 'Añadir 1 proyecto nacional certificado con rol relevante',
        'efecto_estimado' => 'Subida aproximada de 4 a 7 puntos en 1C.',
        'nuevo_b1_b2_aprox' => redondear_2($total_b1_b2 + 5.5),
        'nuevo_total_aprox' => redondear_2($total_final + 5.5),
    ];

    return $simulaciones;
}

function generar_asesor_tecnico(array $datos, array $resultado): array
{
    $perfil = detectar_perfil_tecnico($resultado);
    $acciones = generar_acciones_asesor_tecnico($datos, $resultado);
    $simulaciones = generar_simulaciones_tecnico($resultado);

    $total_b1_b2 = (float)$resultado['totales']['total_b1_b2'];
    $total_final = (float)$resultado['totales']['total_final'];

    $resumen = '';

    if ($resultado['decision']['positiva']) {
        $resumen = 'El expediente ya alcanza evaluación positiva según las reglas programadas. El asesor recomienda consolidar equilibrio y reforzar méritos estratégicos de alto valor en rama técnica.';
    } elseif ($total_b1_b2 < 50.0) {
        $resumen = 'El bloqueo principal está en el núcleo duro B1 + B2. La estrategia debe centrarse primero en investigación y/o docencia, no en méritos accesorios.';
    } elseif ($total_b1_b2 >= 50.0 && $total_final < 55.0) {
        $resumen = 'El núcleo principal está casi resuelto. La estrategia óptima es cerrar el déficit restante con B3/B4 o con refuerzos puntuales eficientes.';
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
    $bloque_1 = puntuar_bloque_1($datos['bloque_1'] ?? []);
    $bloque_2 = puntuar_bloque_2($datos['bloque_2'] ?? []);
    $bloque_3 = puntuar_bloque_3($datos['bloque_3'] ?? []);
    $bloque_4 = puntuar_bloque_4($datos['bloque_4'] ?? []);

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

    $resultado['diagnostico'] = generar_diagnostico_tecnico($datos, $resultado);
    $resultado['asesor'] = generar_asesor_tecnico($datos, $resultado);

    return $resultado;
}