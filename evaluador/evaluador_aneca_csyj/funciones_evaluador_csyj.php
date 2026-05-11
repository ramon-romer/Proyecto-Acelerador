<?php
declare(strict_types=1);

/**
 * Evaluador ANECA - Ciencias Sociales y Jurídicas (PCD/PUP)
 *
 * Ajustado a los criterios orientativos PEP-CSJ 1 y CSJ 2:
 * - Investigación = 60 puntos.
 * - Docencia = 30 puntos.
 * - Formación + experiencia profesional = 8 puntos.
 * - Otros méritos = 2 puntos.
 * - Evaluación positiva si (1+2) >= 50 y total >= 55.
 *
 * Esta versión se ha alineado estructuralmente con la rama de Experimentales,
 * manteniendo además claves planas para compatibilidad con código antiguo.
 */

/* =========================================================
 * HELPERS
 * ========================================================= */

function csyj_to_float(mixed $value, float $default = 0.0): float
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    return (float)str_replace(',', '.', (string)$value);
}

function csyj_to_int(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

function csyj_str(mixed $value, string $default = ''): string
{
    $text = trim((string)$value);
    return $text === '' ? $default : $text;
}

function csyj_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array(mb_strtolower((string)$value, 'UTF-8'), ['1', 'true', 'on', 'si', 'sí', 'yes'], true);
}

function csyj_round(float $value): float
{
    return round($value, 2);
}

function csyj_clamp(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function csyj_get_bloque(array $json, string $key): array
{
    $bloque = $json[$key] ?? [];
    return is_array($bloque) ? $bloque : [];
}

function csyj_get_lista(array $bloque, string $key): array
{
    $lista = $bloque[$key] ?? [];
    return is_array($lista) ? $lista : [];
}

function csyj_count_valid(array $items): int
{
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $isValid = $item['es_valido'] ?? $item['es_valida'] ?? 1;
        if ((string)$isValid !== '0') {
            $count++;
        }
    }
    return $count;
}

function csyj_normalized_event_key(array $item, int $fallbackIndex): string
{
    $candidate = csyj_str(
        $item['evento']
        ?? $item['nombre_evento']
        ?? $item['nombre_congreso']
        ?? $item['congreso']
        ?? ''
    );

    if ($candidate === '') {
        $numeroMismo = csyj_to_int($item['numero_mismo_congreso'] ?? 1, 1);
        if ($numeroMismo > 1) {
            return 'mismo_congreso_' . $fallbackIndex;
        }
        return 'evento_' . $fallbackIndex;
    }

    $candidate = mb_strtolower($candidate, 'UTF-8');
    $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
    return $candidate;
}

/* =========================================================
 * 1A - PUBLICACIONES CIENTÍFICAS
 * PDF CSYJ:
 * - artículos indexados en JCR / Scopus; Educación y Periodismo también
 *   ESI / CIRC / Latindex; Derecho atiende también a MIAR/ICDS.
 * - se pondera impacto, afinidad, número de autores, posición, citas y
 *   reiteración de revista.
 * - reseñas/recensiones no cuentan como artículos.
 * ========================================================= */

function csyj_base_publicacion_1a(array $pub): float
{
    $indice = strtoupper(csyj_str($pub['tipo_indice'] ?? 'OTRO'));
    $cuartil = strtoupper(csyj_str($pub['cuartil'] ?? ''));
    $subtipo = strtoupper(csyj_str($pub['subtipo_indice'] ?? ''));

    if (in_array($indice, ['JCR', 'SCOPUS', 'SJR'], true)) {
        return match ($cuartil) {
            'Q1' => 5.20,
            'Q2' => 3.50,
            'Q3' => 2.60,
            'Q4' => 1.20,
            default => 1.00,
        };
    }

    if ($indice === 'ESCI' || $indice === 'ESI') {
        return $cuartil === 'Q1' ? 2.00 : 1.70;
    }

    if ($indice === 'CIRC') {
        return match ($subtipo) {
            'A+', 'A' => 1.60,
            'B' => 1.20,
            'C' => 0.80,
            default => 1.00,
        };
    }

    if ($indice === 'LATINDEX') {
        return 0.90;
    }

    if ($indice === 'MIAR') {
        return match ($cuartil) {
            'Q1' => 2.00,
            'Q2' => 1.55,
            'Q3', 'Q4' => 1.25,
            default => ($subtipo === 'ICDS' ? 1.55 : 1.35),
        };
    }

    return 0.45;
}

function csyj_factor_afinidad_1a(string $afinidad): float
{
    return match (mb_strtolower(trim($afinidad), 'UTF-8')) {
        'total' => 1.10,
        'relacionada' => 1.00,
        'periferica', 'periférica' => 0.70,
        'ajena' => 0.25,
        default => 1.00,
    };
}

function csyj_factor_posicion_1a(string $posicion): float
{
    return match (mb_strtolower(trim($posicion), 'UTF-8')) {
        'autor_unico', 'unico', 'único' => 1.12,
        'primero' => 1.06,
        'ultimo', 'último' => 1.03,
        'correspondencia' => 1.03,
        'intermedio' => 0.88,
        'secundario' => 0.75,
        default => 0.88,
    };
}

function csyj_factor_autoria_1a(int $numeroAutores): float
{
    $n = max(1, $numeroAutores);

    return match (true) {
        $n <= 1 => 1.08,
        $n <= 3 => 1.00,
        $n <= 5 => 0.92,
        $n <= 8 => 0.80,
        $n <= 12 => 0.70,
        default => 0.58,
    };
}

function csyj_factor_citas_1a(int $citas): float
{
    return match (true) {
        $citas >= 75 => 1.12,
        $citas >= 30 => 1.08,
        $citas >= 10 => 1.03,
        $citas <= 1 => 0.95,
        default => 1.00,
    };
}

function csyj_puntuar_item_1a(array $pub): float
{
    if (!is_array($pub)) {
        return 0.0;
    }

    $esValida = $pub['es_valida'] ?? $pub['es_valido'] ?? 1;
    if ((string)$esValida === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($pub['tipo'] ?? 'articulo'), 'UTF-8');
    if ($tipo !== 'articulo') {
        return 0.0;
    }

    if (
        csyj_bool($pub['es_divulgacion'] ?? false)
        || csyj_bool($pub['es_docencia'] ?? false)
        || csyj_bool($pub['es_acta_congreso'] ?? false)
        || csyj_bool($pub['es_informe_proyecto'] ?? false)
        || csyj_bool($pub['es_resena'] ?? false)
        || csyj_bool($pub['es_recension'] ?? false)
    ) {
        return 0.0;
    }

    $base = csyj_base_publicacion_1a($pub);
    if ($base <= 0.0) {
        return 0.0;
    }

    $puntuacion = $base;
    $puntuacion *= csyj_factor_afinidad_1a((string)($pub['afinidad'] ?? 'relacionada'));
    $puntuacion *= csyj_factor_posicion_1a((string)($pub['posicion_autor'] ?? 'intermedio'));
    $puntuacion *= csyj_factor_autoria_1a(csyj_to_int($pub['numero_autores'] ?? 1, 1));
    $puntuacion *= csyj_factor_citas_1a(csyj_to_int($pub['citas'] ?? 0, 0));

    if (csyj_bool($pub['misma_revista_reiterada'] ?? false)) {
        $puntuacion *= 0.85;
    }

    return csyj_round($puntuacion);
}

function calcular_1a_csyj(array $publicaciones): float
{
    $total = 0.0;
    foreach ($publicaciones as $pub) {
        $total += csyj_puntuar_item_1a($pub);
    }
    return csyj_round(csyj_clamp($total, 0.0, 30.0));
}

/* =========================================================
 * 1B - LIBROS Y CAPÍTULOS
 * PDF CSYJ:
 * - más peso a editoriales SPI / BCI.
 * - no autoediciones.
 * - no actas de congresos.
 * - las labores de edición van a otros méritos.
 * ========================================================= */

function csyj_puntuar_item_1b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }
    if (csyj_bool($item['es_autoedicion'] ?? false)) {
        return 0.0;
    }
    if (csyj_bool($item['es_acta_congreso'] ?? false)) {
        return 0.0;
    }
    if (csyj_bool($item['es_labor_edicion'] ?? false)) {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'capitulo'), 'UTF-8');
    $nivel = mb_strtolower(csyj_str($item['nivel_editorial'] ?? 'secundaria'), 'UTF-8');

    if ($tipo === 'libro') {
        $base = match ($nivel) {
            'spi_alto' => 4.60,
            'spi_medio' => 3.50,
            'bci' => 3.00,
            'nacional' => 2.20,
            default => 1.40,
        };
    } else {
        $base = match ($nivel) {
            'spi_alto', 'internacional' => 2.30,
            'spi_medio' => 1.80,
            'bci' => 1.50,
            'nacional' => 1.25,
            default => 0.85,
        };
    }

    $base *= csyj_factor_afinidad_1a((string)($item['afinidad'] ?? 'relacionada'));
    $base *= match (mb_strtolower(csyj_str($item['posicion_autor'] ?? 'intermedio'), 'UTF-8')) {
        'autor_unico', 'unico', 'único' => 1.10,
        'primero' => 1.04,
        'ultimo', 'último' => 1.02,
        'intermedio' => 0.95,
        default => 0.85,
    };

    if (csyj_bool($item['coleccion_relevante'] ?? false)) {
        $base += 0.20;
    }
    if (csyj_bool($item['resenas_recibidas'] ?? false)) {
        $base += 0.20;
    }

    return csyj_round($base);
}

function calcular_1b_csyj(array $libros): float
{
    $total = 0.0;
    foreach ($libros as $item) {
        $total += csyj_puntuar_item_1b($item);
    }
    return csyj_round(csyj_clamp($total, 0.0, 12.0));
}

/* =========================================================
 * 1C - PROYECTOS DE INVESTIGACIÓN
 * PDF CSYJ:
 * - proyectos competitivos europeos/nacionales/autonómicos/universidad.
 * - debe acreditarse la participación.
 * - mejor ser IP y dedicación completa.
 * ========================================================= */

function csyj_puntuar_item_1c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $certificado = csyj_bool($item['esta_certificado'] ?? true);
    $ipJustificado = csyj_bool($item['justificacion_ip_documentada'] ?? false);
    if (!$certificado && !$ipJustificado) {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($item['tipo_proyecto'] ?? 'universidad'), 'UTF-8');
    $rol = mb_strtolower(csyj_str($item['rol'] ?? 'investigador'), 'UTF-8');
    $dedicacion = mb_strtolower(csyj_str($item['dedicacion'] ?? 'compartida'), 'UTF-8');
    $anios = max(0.0, csyj_to_float($item['anios_duracion'] ?? 1.0, 1.0));

    $base = match ($tipo) {
        'europeo' => 2.30,
        'nacional' => 1.90,
        'autonomico', 'autonómico' => 1.15,
        'universidad', 'otro_competitivo' => 1.00,
        'art83_conocimiento' => 0.85,
        default => 0.70,
    };

    $base *= match ($rol) {
        'ip' => 1.18,
        'coip' => 1.05,
        'investigador' => 0.85,
        'participacion_menor', 'participación_menor' => 0.55,
        default => 0.70,
    };

    if ($dedicacion === 'completa') {
        $base *= 1.10;
    }

    $base *= match (true) {
        $anios >= 4 => 1.10,
        $anios >= 2 => 1.00,
        $anios >= 1 => 0.90,
        default => 0.70,
    };

    return csyj_round($base);
}

function calcular_1c_csyj(array $proyectos): float
{
    $total = 0.0;
    foreach ($proyectos as $item) {
        $total += csyj_puntuar_item_1c($item);
    }
    return csyj_round(csyj_clamp($total, 0.0, 5.0));
}

/* =========================================================
 * 1D - TRANSFERENCIA
 * ========================================================= */

function csyj_puntuar_item_1d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'transferencia_conocimiento'), 'UTF-8');
    $impacto = mb_strtolower(csyj_str($item['impacto'] ?? 'medio'), 'UTF-8');
    $ambito = mb_strtolower(csyj_str($item['ambito'] ?? 'nacional'), 'UTF-8');

    $baseTipo = match ($tipo) {
        'transferencia_conocimiento' => 1.00,
        'contrato_transferencia' => 0.85,
        'impacto_social' => 0.70,
        'difusion_especializada' => 0.35,
        default => 0.30,
    };

    $baseImpacto = match ($impacto) {
        'alto' => 1.00,
        'medio' => 0.75,
        default => 0.50,
    };

    $bonusAmbito = match ($ambito) {
        'internacional' => 0.25,
        'nacional' => 0.15,
        'regional' => 0.08,
        default => 0.00,
    };

    return csyj_round(($baseTipo * $baseImpacto) + $bonusAmbito);
}

function calcular_1d_csyj(array $transferencia): float
{
    $total = 0.0;
    foreach ($transferencia as $item) {
        $total += csyj_puntuar_item_1d($item);
    }
    return csyj_round(csyj_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * 1E - DIRECCIÓN DE TESIS
 * ========================================================= */

function csyj_puntuar_item_1e(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $estado = mb_strtolower(csyj_str($item['estado'] ?? 'defendida'), 'UTF-8');
    $rol = mb_strtolower(csyj_str($item['rol'] ?? 'director'), 'UTF-8');
    $mencion = csyj_bool($item['mencion_internacional'] ?? false);

    $base = match ($estado) {
        'defendida' => 1.70,
        'en_proceso' => 0.55,
        default => 0.0,
    };

    if ($rol === 'codirector') {
        $base *= 0.65;
    }
    if ($mencion) {
        $base += 0.15;
    }

    return csyj_round($base);
}

function calcular_1e_csyj(array $tesis): float
{
    $total = 0.0;
    foreach ($tesis as $item) {
        $total += csyj_puntuar_item_1e($item);
    }
    return csyj_round(csyj_clamp($total, 0.0, 4.0));
}

/* =========================================================
 * 1F - CONGRESOS, SEMINARIOS Y JORNADAS
 * PDF CSYJ:
 * - mejor ponencias/comunicaciones que pósteres.
 * - internacional > nacional > regional > local.
 * - máximo dos participaciones por mismo congreso.
 * ========================================================= */

function csyj_puntuar_item_1f(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'comunicacion'), 'UTF-8');
    $ambito = mb_strtolower(csyj_str($item['ambito'] ?? 'nacional'), 'UTF-8');
    $invitada = csyj_bool($item['por_invitacion'] ?? false);

    $base = match ($tipo) {
        'ponencia', 'ponencia_invitada' => 0.72,
        'comunicacion', 'comunicación', 'comunicacion_oral' => 0.55,
        'seminario', 'jornada', 'seminario/jornada' => 0.38,
        'poster', 'póster' => 0.22,
        default => 0.32,
    };

    $base *= match ($ambito) {
        'internacional' => 1.20,
        'nacional' => 1.00,
        'regional' => 0.75,
        default => 0.50,
    };

    if ($invitada) {
        $base += 0.10;
    }

    return csyj_round($base);
}

function calcular_1f_csyj(array $congresos): float
{
    $agrupados = [];

    foreach ($congresos as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $key = csyj_normalized_event_key($item, (int)$index);
        $agrupados[$key][] = csyj_puntuar_item_1f($item);
    }

    $total = 0.0;
    foreach ($agrupados as $puntuaciones) {
        rsort($puntuaciones, SORT_NUMERIC);
        $total += array_sum(array_slice($puntuaciones, 0, 2));
    }

    return csyj_round(csyj_clamp($total, 0.0, 5.0));
}

/* =========================================================
 * 1G - OTROS MÉRITOS DE INVESTIGACIÓN
 * ========================================================= */

function csyj_puntuar_item_1g(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'otro'), 'UTF-8');
    $relevancia = mb_strtolower(csyj_str($item['relevancia'] ?? 'media'), 'UTF-8');

    $base = match ($tipo) {
        'premio_investigacion', 'premio_investigación' => 0.55,
        'consejo_redaccion' => 0.35,
        'revisor_revista' => 0.28,
        'coordinacion_libro', 'coordinación_libro' => 0.28,
        'organizacion_investigacion', 'organización_investigación' => 0.28,
        'tribunal_tesis' => 0.22,
        'grupo_investigacion', 'grupo_investigación' => 0.35,
        'publicacion_tesis', 'publicación_tesis' => 0.18,
        'resena', 'reseña' => 0.14,
        'divulgacion', 'divulgación' => 0.12,
        default => 0.18,
    };

    $base *= match ($relevancia) {
        'alta' => 1.15,
        'media' => 1.00,
        default => 0.80,
    };

    return csyj_round($base);
}

function calcular_1g_csyj(array $otros): float
{
    $total = 0.0;
    foreach ($otros as $item) {
        $total += csyj_puntuar_item_1g($item);
    }
    return csyj_round(csyj_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * 2A - DOCENCIA UNIVERSITARIA
 * PDF CSYJ:
 * - docencia reglada universitaria.
 * - horas justificadas.
 * - responsabilidad y nivel (grado/máster).
 * ========================================================= */

function calcular_2a_csyj(array $docencia): float
{
    $horas = 0.0;
    $bonusResponsabilidad = 0.0;
    $bonusMaster = 0.0;

    foreach ($docencia as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }
        if (!csyj_bool($item['acreditada'] ?? true, true)) {
            continue;
        }

        $horas += max(0.0, csyj_to_float($item['horas'] ?? 0));

        $responsabilidad = mb_strtolower(csyj_str($item['responsabilidad'] ?? 'media'), 'UTF-8');
        $nivel = mb_strtolower(csyj_str($item['nivel'] ?? 'grado'), 'UTF-8');

        $bonusResponsabilidad += match ($responsabilidad) {
            'alta' => 0.18,
            'media' => 0.10,
            default => 0.04,
        };

        if ($nivel === 'master' || $nivel === 'máster') {
            $bonusMaster += 0.12;
        }
    }

    $puntosHoras = min(17.0, ($horas / 450.0) * 17.0);
    $total = $puntosHoras + min(0.60, $bonusResponsabilidad) + min(0.45, $bonusMaster);

    return csyj_round(csyj_clamp($total, 0.0, 17.0));
}

/* =========================================================
 * 2B - EVALUACIÓN DOCENTE
 * ========================================================= */

function calcular_2b_csyj(array $evaluaciones): float
{
    $total = 0.0;

    foreach ($evaluaciones as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $sistema = mb_strtolower(csyj_str($item['sistema'] ?? 'encuesta'), 'UTF-8');
        $calificacion = mb_strtolower(csyj_str($item['calificacion'] ?? 'favorable'), 'UTF-8');
        $numero = max(1, csyj_to_int($item['numero'] ?? 1, 1));

        $base = match ($calificacion) {
            'excelente' => 1.05,
            'muy_favorable' => 0.82,
            'favorable' => 0.58,
            default => 0.30,
        };

        if ($sistema === 'docentia') {
            $base += 0.12;
        }

        $total += $base * $numero;
    }

    return csyj_round(csyj_clamp($total, 0.0, 3.0));
}

/* =========================================================
 * 2C - SEMINARIOS Y CURSOS ORIENTADOS A DOCENCIA
 * ========================================================= */

function calcular_2c_csyj(array $actividades): float
{
    $total = 0.0;

    foreach ($actividades as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }
        if (!csyj_bool($item['orientado_docencia'] ?? true, true)) {
            continue;
        }

        $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'seminario_docente'), 'UTF-8');
        $ambito = mb_strtolower(csyj_str($item['ambito'] ?? 'local'), 'UTF-8');

        $base = match ($tipo) {
            'curso_docente' => 0.42,
            'recibir_formacion_docente' => 0.28,
            default => 0.32,
        };

        $base *= match ($ambito) {
            'internacional' => 1.15,
            'nacional' => 1.00,
            default => 0.80,
        };

        $total += $base;
    }

    return csyj_round(csyj_clamp($total, 0.0, 3.0));
}

/* =========================================================
 * 2D - MATERIAL DOCENTE / INNOVACIÓN DOCENTE
 * PDF CSYJ:
 * - material docente publicado o con ISBN/ISSN.
 * - no apuntes ni guías docentes.
 * ========================================================= */

function calcular_2d_csyj(array $materiales): float
{
    $total = 0.0;

    foreach ($materiales as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }
        if (!csyj_bool($item['no_apuntes_guias'] ?? true, true)) {
            continue;
        }

        $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'material_publicado'), 'UTF-8');
        $isbn = csyj_bool($item['isbn_issn'] ?? false);
        $relevancia = mb_strtolower(csyj_str($item['relevancia'] ?? 'media'), 'UTF-8');

        $base = match ($tipo) {
            'publicacion_docente', 'publicación_docente' => 1.75,
            'proyecto_innovacion', 'proyecto_innovación' => 3.05,
            'material_publicado' => 1.15,
            default => 0.85,
        };

        if ($isbn) {
            $base += 0.20;
        }

        $base *= match ($relevancia) {
            'alta' => 1.15,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base;
    }

    return csyj_round(csyj_clamp($total, 0.0, 7.0));
}

/* =========================================================
 * 3A - FORMACIÓN ACADÉMICA
 * PDF CSYJ:
 * - doctorado con mención internacional.
 * - becas/ayudas competitivas después de grado.
 * - movilidad/estancias con duración mínima.
 * ========================================================= */

function csyj_puntuar_item_3a(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'curso_especializacion'), 'UTF-8');
    $duracion = max(0.0, csyj_to_float($item['duracion'] ?? 0));
    $posteriorGrado = csyj_bool($item['posterior_grado'] ?? true, true);

    return match ($tipo) {
        'doctorado_internacional' => 2.40,
        'doctorado_sin_mencion', 'doctorado_sin_mención' => 0.0,
        'beca_competitiva', 'beca_predoc_fpu', 'beca_predoc_fpi' => $posteriorGrado ? 1.40 : 0.0,
        'ayuda' => $posteriorGrado ? 0.70 : 0.0,
        'master', 'máster' => 1.00,
        'curso_especializacion', 'curso_especialización' => min(0.75, 0.75 * max(1.0, $duracion)),
        'movilidad', 'estancia' => ($duracion < 1.0) ? 0.0 : min(1.30, 0.28 * $duracion),
        default => 0.0,
    };
}

function calcular_3a_csyj(array $formacion): float
{
    $total = 0.0;
    foreach ($formacion as $item) {
        $total += csyj_puntuar_item_3a($item);
    }
    return csyj_round(csyj_clamp($total, 0.0, 6.0));
}

/* =========================================================
 * 3B - EXPERIENCIA PROFESIONAL
 * PDF CSYJ:
 * - solo la vinculada al ámbito del comité.
 * - debe estar documentada.
 * ========================================================= */

function calcular_3b_csyj(array $experiencia): float
{
    $total = 0.0;

    foreach ($experiencia as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }
        if (!csyj_bool($item['documentada'] ?? true, true)) {
            continue;
        }

        $anios = max(0.0, csyj_to_float($item['anios'] ?? 0));
        $relacion = mb_strtolower(csyj_str($item['relacion'] ?? 'media'), 'UTF-8');

        $factorRelacion = match ($relacion) {
            'alta' => 1.00,
            'media' => 0.65,
            default => 0.30,
        };

        $total += min(1.0, 0.34 * $anios) * $factorRelacion;
    }

    return csyj_round(csyj_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * 4 - OTROS MÉRITOS
 * ========================================================= */

function calcular_4_csyj(array $otros): float
{
    $total = 0.0;

    foreach ($otros as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $tipo = mb_strtolower(csyj_str($item['tipo'] ?? 'otro'), 'UTF-8');
        $relevancia = mb_strtolower(csyj_str($item['relevancia'] ?? 'media'), 'UTF-8');

        $base = match ($tipo) {
            'gestion', 'gestión' => 0.42,
            'cargo_unipersonal' => 0.48,
            'docencia_no_reglada' => 0.42,
            'tfg_tfm' => 0.34,
            'tutor_uned' => 0.28,
            'curso_extension', 'curso_extensión' => 0.24,
            default => 0.18,
        };

        $base *= match ($relevancia) {
            'alta' => 1.15,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base;
    }

    return csyj_round(csyj_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * CAPAS DE DIAGNÓSTICO / ASESOR
 * ========================================================= */

function csyj_build_asesor(array $puntuaciones, array $totales, bool $cumpleRegla1, bool $cumpleRegla2): array
{
    $acciones = [];

    if ($puntuaciones['1A'] < 18.0) {
        $acciones[] = [
            'titulo' => 'Subir 1.A Publicaciones científicas',
            'detalle' => 'En CSyJ pesa mucho la publicación indexada. Prioriza JCR / Scopus y, según área, CIRC, Latindex o MIAR/ICDS; cuida afinidad, coautoría, posición y citas.',
        ];
    }

    if ($puntuaciones['1B'] < 5.0) {
        $acciones[] = [
            'titulo' => 'Reforzar 1.B Libros y capítulos',
            'detalle' => 'Suman mejor las editoriales SPI / BCI. No cuentan autoediciones, actas de congreso ni labores editoriales metidas aquí.',
        ];
    }

    if ($puntuaciones['2B'] <= 0.0) {
        $acciones[] = [
            'titulo' => 'Añadir evidencias de calidad docente',
            'detalle' => 'El comité señala expresamente la importancia de adjuntar evaluaciones DOCENTIA o encuestas de docencia en 2.B.',
        ];
    }

    if ($puntuaciones['2D'] < 2.0) {
        $acciones[] = [
            'titulo' => 'Revisar 2.D Material docente / innovación',
            'detalle' => 'Solo deben entrar materiales publicados o con ISBN/ISSN y proyectos de innovación. No metas apuntes ni guías docentes.',
        ];
    }

    if ($puntuaciones['3A'] < 2.5) {
        $acciones[] = [
            'titulo' => 'Mejorar 3.A Formación académica',
            'detalle' => 'Puntúan especialmente la mención internacional, becas competitivas postgrado y estancias con duración suficiente.',
        ];
    }

    if (!$cumpleRegla1 || !$cumpleRegla2) {
        $acciones[] = [
            'titulo' => 'Objetivo mínimo de corte',
            'detalle' => 'La evaluación solo es positiva si 1+2 llega a 50 y el total final alcanza 55.',
        ];
    }

    $simulaciones = [];
    if (!$cumpleRegla1) {
        $faltan = max(0.0, 50.0 - $totales['total_b1_b2']);
        $simulaciones[] = [
            'escenario' => 'Cerrar la regla 1+2',
            'faltan' => csyj_round($faltan),
            'mensaje' => 'Necesitas sumar al menos ' . csyj_round($faltan) . ' puntos adicionales entre investigación y docencia.',
        ];
    }
    if (!$cumpleRegla2) {
        $faltan = max(0.0, 55.0 - $totales['total_final']);
        $simulaciones[] = [
            'escenario' => 'Cerrar la regla total',
            'faltan' => csyj_round($faltan),
            'mensaje' => 'Necesitas sumar al menos ' . csyj_round($faltan) . ' puntos adicionales en el total final.',
        ];
    }

    if ($acciones === [] && $simulaciones === []) {
        $resumen = 'El expediente ya supera los umbrales de corte con esta configuración.';
    } else {
        $resumen = 'El expediente aún tiene margen de mejora. Las palancas más rentables suelen estar en publicaciones indexadas, calidad docente, material docente acreditable y formación con estancias competitivas.';
    }

    return [
        'resumen' => $resumen,
        'acciones' => $acciones,
        'simulaciones' => $simulaciones,
    ];
}

/* =========================================================
 * EVALUACIÓN PRINCIPAL
 * ========================================================= */

function evaluar_expediente_csyj(array $json): array
{
    $bloque1 = csyj_get_bloque($json, 'bloque_1');
    $bloque2 = csyj_get_bloque($json, 'bloque_2');
    $bloque3 = csyj_get_bloque($json, 'bloque_3');
    $bloque4 = $json['bloque_4'] ?? [];
    if (!is_array($bloque4)) {
        $bloque4 = [];
    }

    $publicaciones = csyj_get_lista($bloque1, 'publicaciones');
    $libros = csyj_get_lista($bloque1, 'libros');
    $proyectos = csyj_get_lista($bloque1, 'proyectos');
    $transferencia = csyj_get_lista($bloque1, 'transferencia');
    $tesis = csyj_get_lista($bloque1, 'tesis_dirigidas');
    $congresos = csyj_get_lista($bloque1, 'congresos');
    $otrosInv = csyj_get_lista($bloque1, 'otros_meritos_investigacion');

    $docencia = csyj_get_lista($bloque2, 'docencia_universitaria');
    $evaluacionDocente = csyj_get_lista($bloque2, 'evaluacion_docente');
    $formacionDocente = csyj_get_lista($bloque2, 'formacion_docente');
    $materialDocente = csyj_get_lista($bloque2, 'material_docente');

    $formacion = csyj_get_lista($bloque3, 'formacion_academica');
    $expProfesional = csyj_get_lista($bloque3, 'experiencia_profesional');

    $p1a = calcular_1a_csyj($publicaciones);
    $p1b = calcular_1b_csyj($libros);
    $p1c = calcular_1c_csyj($proyectos);
    $p1d = calcular_1d_csyj($transferencia);
    $p1e = calcular_1e_csyj($tesis);
    $p1f = calcular_1f_csyj($congresos);
    $p1g = calcular_1g_csyj($otrosInv);
    $B1 = csyj_round(csyj_clamp($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 0.0, 60.0));

    $p2a = calcular_2a_csyj($docencia);
    $p2b = calcular_2b_csyj($evaluacionDocente);
    $p2c = calcular_2c_csyj($formacionDocente);
    $p2d = calcular_2d_csyj($materialDocente);
    $B2 = csyj_round(csyj_clamp($p2a + $p2b + $p2c + $p2d, 0.0, 30.0));

    $p3a = calcular_3a_csyj($formacion);
    $p3b = calcular_3b_csyj($expProfesional);
    $B3 = csyj_round(csyj_clamp($p3a + $p3b, 0.0, 8.0));

    $p4 = calcular_4_csyj($bloque4);
    $B4 = $p4;

    $totalB1B2 = csyj_round($B1 + $B2);
    $totalFinal = csyj_round($B1 + $B2 + $B3 + $B4);

    $cumpleRegla1 = $totalB1B2 >= 50.0;
    $cumpleRegla2 = $totalFinal >= 55.0;
    $evaluacionPositiva = $cumpleRegla1 && $cumpleRegla2;
    $resultadoTexto = $evaluacionPositiva ? 'POSITIVA' : 'NEGATIVA';

    $puntuaciones = [
        '1A' => $p1a,
        '1B' => $p1b,
        '1C' => $p1c,
        '1D' => $p1d,
        '1E' => $p1e,
        '1F' => $p1f,
        '1G' => $p1g,
        '2A' => $p2a,
        '2B' => $p2b,
        '2C' => $p2c,
        '2D' => $p2d,
        '3A' => $p3a,
        '3B' => $p3b,
        '4' => $p4,
    ];

    $totales = [
        'bloque_1' => $B1,
        'bloque_2' => $B2,
        'bloque_3' => $B3,
        'bloque_4' => $B4,
        'total_b1_b2' => $totalB1B2,
        'total_final' => $totalFinal,
        'global' => $totalFinal,
    ];

    $diagnostico = [
        'version' => 'pep_csyj_pcd_pup_v2_alineado_con_experimentales',
        'conteos' => [
            'publicaciones' => csyj_count_valid($publicaciones),
            'libros' => csyj_count_valid($libros),
            'proyectos' => csyj_count_valid($proyectos),
            'transferencia' => csyj_count_valid($transferencia),
            'tesis_dirigidas' => csyj_count_valid($tesis),
            'congresos' => csyj_count_valid($congresos),
            'otros_investigacion' => csyj_count_valid($otrosInv),
            'docencia_items' => csyj_count_valid($docencia),
            'evaluaciones_docentes' => csyj_count_valid($evaluacionDocente),
            'formacion_docente' => csyj_count_valid($formacionDocente),
            'material_docente' => csyj_count_valid($materialDocente),
            'formacion_academica' => csyj_count_valid($formacion),
            'exp_profesional' => csyj_count_valid($expProfesional),
            'otros_meritos' => csyj_count_valid($bloque4),
        ],
        'criterios_clave' => [
            '1.A pondera impacto, afinidad, autoría, citas y reiteración de revista.',
            '1.B prioriza SPI / BCI y excluye autoediciones, actas y labores editoriales.',
            '1.C exige acreditación de proyectos y favorece IP / coIP.',
            '1.F solo computa hasta dos participaciones por congreso.',
            '2.D excluye apuntes y guías docentes.',
            '3.A exige estancias con duración suficiente y valora mención internacional.',
        ],
    ];

    $asesor = csyj_build_asesor($puntuaciones, $totales, $cumpleRegla1, $cumpleRegla2);

    $resultado = [
        'puntuaciones' => $puntuaciones,
        'bloque_1' => [
            '1A' => $p1a,
            '1B' => $p1b,
            '1C' => $p1c,
            '1D' => $p1d,
            '1E' => $p1e,
            '1F' => $p1f,
            '1G' => $p1g,
            'B1' => $B1,
        ],
        'bloque_2' => [
            '2A' => $p2a,
            '2B' => $p2b,
            '2C' => $p2c,
            '2D' => $p2d,
            'B2' => $B2,
        ],
        'bloque_3' => [
            '3A' => $p3a,
            '3B' => $p3b,
            'B3' => $B3,
        ],
        'bloque_4' => [
            '4' => $p4,
            'B4' => $B4,
        ],
        'totales' => $totales,
        'cumplimientos' => [
            'cumple_bloques_1_2' => $cumpleRegla1,
            'cumple_total' => $cumpleRegla2,
        ],
        'decision' => [
            'cumple_regla_1' => $cumpleRegla1,
            'cumple_regla_2' => $cumpleRegla2,
            'evaluacion_positiva' => $evaluacionPositiva,
            'resultado' => $resultadoTexto,
        ],
        'evaluacion_positiva' => $evaluacionPositiva,
        'resultado' => $resultadoTexto,
        'diagnostico' => $diagnostico,
        'asesor' => $asesor,

        /* Compatibilidad hacia atrás */
        'puntuacion_1a' => $p1a,
        'puntuacion_1b' => $p1b,
        'puntuacion_1c' => $p1c,
        'puntuacion_1d' => $p1d,
        'puntuacion_1e' => $p1e,
        'puntuacion_1f' => $p1f,
        'puntuacion_1g' => $p1g,
        'puntuacion_2a' => $p2a,
        'puntuacion_2b' => $p2b,
        'puntuacion_2c' => $p2c,
        'puntuacion_2d' => $p2d,
        'puntuacion_3a' => $p3a,
        'puntuacion_3b' => $p3b,
        'bloque_1_total' => $B1,
        'bloque_2_total' => $B2,
        'bloque_3_total' => $B3,
        'bloque_4_total' => $B4,
        'B1' => $B1,
        'B2' => $B2,
        'B3' => $B3,
        'B4' => $B4,
        'total_b1_b2' => $totalB1B2,
        'total_final' => $totalFinal,
        'cumple_regla_1' => $cumpleRegla1 ? 1 : 0,
        'cumple_regla_2' => $cumpleRegla2 ? 1 : 0,
    ];

    /* Claves adicionales de compatibilidad */
    $resultado['bloque_1_flat'] = $B1;
    $resultado['bloque_2_flat'] = $B2;
    $resultado['bloque_3_flat'] = $B3;
    $resultado['bloque_4_flat'] = $B4;

    return $resultado;
}

/**
 * Alias de compatibilidad
 */
function evaluar_expediente(array $json): array
{
    return evaluar_expediente_csyj($json);
}
