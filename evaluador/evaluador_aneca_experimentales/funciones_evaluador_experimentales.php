<?php
declare(strict_types=1);

/**
 * Evaluador ANECA - Experimentales
 * Versión conservadora ajustada a PEP
 *
 * Cambios clave:
 * - 1A endurecido: bases menores, más castigo por coautoría y posición intermedia.
 * - 1C endurecido: distingue mejor IP real vs. IP nominal en el certificado;
 *   excluye contratos laborales con cargo a proyecto y baja mucho "equipo de trabajo".
 * - 2A por horas acumuladas (máximo 450h).
 * - 2B exige cobertura amplia.
 * - 2C solo formación docente universitaria real.
 * - 2D solo material/innovación docente explícitos.
 * - 3B muy restrictivo.
 * - 4 prudente.
 */

/* =========================================================
 * HELPERS
 * ========================================================= */

function exp_to_float(mixed $value, float $default = 0.0): float
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_numeric($value)) {
        return (float)$value;
    }
    return (float)str_replace(',', '.', (string)$value);
}

function exp_to_int(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

function exp_str(mixed $value, string $default = ''): string
{
    $v = trim((string)$value);
    return $v === '' ? $default : $v;
}

function exp_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    return in_array(mb_strtolower((string)$value, 'UTF-8'), ['1', 'true', 'on', 'si', 'sí', 'yes'], true);
}

function exp_round(float $value): float
{
    return round($value, 2);
}

function exp_clamp(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function exp_list(array $data, string $key): array
{
    $value = $data[$key] ?? [];
    return is_array($value) ? $value : [];
}

function exp_text(array $item): string
{
    return mb_strtolower(trim((string)($item['fuente_texto'] ?? '')), 'UTF-8');
}

function exp_contains_any(string $text, array $terms): bool
{
    foreach ($terms as $term) {
        if (mb_stripos($text, $term, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

function exp_count_valid(array $items): int
{
    $n = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $isValid = $item['es_valido'] ?? $item['es_valida'] ?? 1;
        if ((string)$isValid !== '0') {
            $n++;
        }
    }
    return $n;
}

/* =========================================================
 * 1A PUBLICACIONES
 * Regla de tres basada en la rúbrica PEP de Experimentales:
 * - estándar: 12 publicaciones SCI/JCR para el máximo.
 * - si son de alta calidad, el equivalente puede alcanzar el máximo con 8-9.
 * - si son excelentes, puede alcanzarse con 5-6.
 * ========================================================= */

function exp_factor_posicion_autor_1a(string $posicion, bool $ordenAlfabetico = false): float
{
    if ($ordenAlfabetico) {
        return 1.00;
    }

    return match (mb_strtolower(trim($posicion), 'UTF-8')) {
        'autor_unico', 'unico' => 1.08,
        'primero' => 1.05,
        'ultimo' => 1.05,
        'correspondencia' => 1.05,
        'intermedio' => 0.95,
        'secundario' => 0.88,
        default => 0.95,
    };
}

function exp_factor_coautoria_1a(int $numeroAutores): float
{
    $n = max(1, $numeroAutores);

    return match (true) {
        $n <= 2 => 1.05,
        $n <= 4 => 1.00,
        $n <= 6 => 0.95,
        $n <= 10 => 0.90,
        $n <= 20 => 0.82,
        default => 0.72,
    };
}

function exp_factor_citas_1a(int $citas, int $anios): float
{
    $c = max(0, $citas);
    $a = max(0, $anios);

    if ($a <= 2 && $c <= 3) {
        return 1.00;
    }

    return match (true) {
        $c <= 1 => 0.97,
        $c <= 5 => 1.00,
        $c <= 15 => 1.03,
        $c <= 40 => 1.06,
        default => 1.10,
    };
}

function exp_es_publicacion_excelente_1a(array $pub): bool
{
    $tercil = strtoupper(exp_str($pub['tercil'] ?? ''));
    $cuartil = strtoupper(exp_str($pub['cuartil'] ?? ''));
    $numAutores = exp_to_int($pub['numero_autores'] ?? 1, 1);
    $citas = exp_to_int($pub['citas'] ?? 0, 0);
    $posicion = mb_strtolower(exp_str($pub['posicion_autor'] ?? ''), 'UTF-8');

    $esTop = ($tercil === 'T1' || $cuartil === 'Q1');
    $posicionRelevante = in_array($posicion, ['autor_unico', 'unico', 'primero', 'ultimo', 'correspondencia'], true);

    return $esTop && $numAutores <= 5 && ($posicionRelevante || $citas >= 10);
}

function exp_unidades_publicacion_1a(array $pub): float
{
    $tercil = strtoupper(exp_str($pub['tercil'] ?? ''));
    $cuartil = strtoupper(exp_str($pub['cuartil'] ?? ''));
    $tipoIndice = strtoupper(exp_str($pub['tipo_indice'] ?? ''));

    $base = 0.0;
    if (exp_es_publicacion_excelente_1a($pub)) {
        $base = 2.20;
    } elseif ($tercil === 'T1' || $cuartil === 'Q1') {
        $base = 1.40;
    } elseif ($tercil === 'T2' || $cuartil === 'Q2') {
        $base = 1.00;
    } elseif ($tercil === 'T3' || $cuartil === 'Q3' || $cuartil === 'Q4') {
        $base = 0.75;
    } elseif ($tipoIndice === 'JCR' || $tipoIndice === 'SCOPUS' || $tipoIndice === 'SJR') {
        $base = 0.60;
    }

    if ($base <= 0.0) {
        return 0.0;
    }

    $ordenAlfabetico = exp_bool($pub['orden_alfabetico'] ?? false);
    $unidades = $base
        * exp_factor_posicion_autor_1a(exp_str($pub['posicion_autor'] ?? 'intermedio'), $ordenAlfabetico)
        * exp_factor_coautoria_1a(exp_to_int($pub['numero_autores'] ?? 1, 1))
        * exp_factor_citas_1a(
            exp_to_int($pub['citas'] ?? 0, 0),
            exp_to_int($pub['anios_desde_publicacion'] ?? 3, 3)
        );

    if (exp_bool($pub['es_area_matematicas'] ?? false)) {
        $unidades *= 1.20;
    }

    return exp_round(min(2.40, $unidades));
}

function exp_puntuar_item_1a(array $pub): float
{
    if (!is_array($pub)) {
        return 0.0;
    }

    $esValida = $pub['es_valida'] ?? $pub['es_valido'] ?? 1;
    if ((string)$esValida === '0') {
        return 0.0;
    }

    if (
        exp_bool($pub['es_divulgacion'] ?? false)
        || exp_bool($pub['es_docencia'] ?? false)
        || exp_bool($pub['es_acta_congreso'] ?? false)
        || exp_bool($pub['es_informe_proyecto'] ?? false)
    ) {
        return 0.0;
    }

    return exp_unidades_publicacion_1a($pub);
}

function calcular_1a_experimentales(array $publicaciones): float
{
    $equivalentes = 0.0;
    foreach ($publicaciones as $pub) {
        $equivalentes += exp_puntuar_item_1a($pub);
    }

    // Regla de tres sobre el estándar PCD: 12 publicaciones equivalentes = 35 puntos.
    $puntuacion = 35.0 * min(1.0, $equivalentes / 12.0);

    return exp_round(exp_clamp($puntuacion, 0.0, 35.0));
}

/* =========================================================
 * 1B LIBROS Y CAPÍTULOS
 * ========================================================= */

function exp_puntuar_item_1b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $texto = exp_text($item);
    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'libro'), 'UTF-8');
    $nivel = mb_strtolower(exp_str($item['nivel_editorial'] ?? 'nacional'), 'UTF-8');

    $pareceLibroReal = exp_contains_any($texto, ['isbn', 'libro', 'capítulo', 'capitulo', 'editorial', 'springer', 'elsevier', 'wiley', 'cambridge', 'oxford']);
    if (!$pareceLibroReal) {
        return 0.0;
    }

    $base = match ($nivel) {
        'internacional' => ($tipo === 'libro' ? 2.0 : 1.0),
        'nacional' => ($tipo === 'libro' ? 1.2 : 0.6),
        default => ($tipo === 'libro' ? 0.5 : 0.25),
    };

    if (exp_bool($item['es_autoedicion'] ?? false) || exp_bool($item['es_acta_congreso'] ?? false)) {
        $base *= 0.25;
    }

    if (exp_bool($item['es_labor_edicion'] ?? false)) {
        $base *= 0.60;
    }

    return exp_round($base);
}

function calcular_1b_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_1b($item);
    }
    return exp_round(exp_clamp($total, 0.0, 7.0));
}

/* =========================================================
 * 1C PROYECTOS
 * Versión más dura
 * ========================================================= */

function exp_inferir_rol_real_proyecto_1c(array $item): string
{
    $rolExtraido = mb_strtolower(exp_str($item['rol'] ?? 'participante'), 'UTF-8');
    $texto = exp_text($item);

    if (exp_contains_any($texto, [
        'grado tipo 10001',
        'investigador principal y',
        'soy investigador principal',
    ])) {
        return 'ip';
    }

    if (exp_contains_any($texto, ['coip', 'co-ip', 'co ip'])) {
        return 'coip';
    }

    if (in_array($rolExtraido, ['ip', 'coip'], true)) {
        return $rolExtraido;
    }

    return 'participante';
}

function exp_es_proyecto_elegible_1c(array $item): bool
{
    if (!exp_bool($item['esta_certificado'] ?? true, true)) {
        return false;
    }

    $tipo = mb_strtolower(exp_str($item['tipo_proyecto'] ?? ''), 'UTF-8');
    return in_array($tipo, ['europeo', 'nacional', 'autonomico', 'otro_competitivo', 'art83_conocimiento'], true);
}

function exp_puntuar_item_1c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }
    if (!exp_es_proyecto_elegible_1c($item)) {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo_proyecto'] ?? 'otro_competitivo'), 'UTF-8');
    $rol  = exp_inferir_rol_real_proyecto_1c($item);
    $anios = exp_to_float($item['anios_duracion'] ?? 0, 0);

    $p = match ($tipo) {
        'europeo' => match ($rol) {
            'ip' => 4.80,
            'coip' => 4.10,
            default => 0.90,
        },
        'nacional' => match ($rol) {
            'ip' => 4.20,
            'coip' => 3.60,
            default => 0.60,
        },
        'autonomico' => match ($rol) {
            'ip' => 2.20,
            'coip' => 1.80,
            default => 0.40,
        },
        'art83_conocimiento' => match ($rol) {
            'ip' => 1.00,
            'coip' => 0.80,
            default => 0.35,
        },
        default => match ($rol) {
            'ip' => 1.20,
            'coip' => 1.00,
            default => 0.25,
        },
    };

    if ($anios >= 3.0) {
        $p *= 1.05;
    } elseif ($anios > 0 && $anios < 1.0) {
        $p *= 0.85;
    }

    return exp_round($p);
}

function calcular_1c_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_1c($item);
    }
    return exp_round(exp_clamp($total, 0.0, 7.0));
}

/* =========================================================
 * 1D TRANSFERENCIA
 * ========================================================= */

function exp_puntuar_item_1d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? ''), 'UTF-8');
    $texto = exp_text($item);

    if (
        !in_array($tipo, [
            'patente_obtenida_internacional',
            'patente_solicitada_internacional',
            'patente_obtenida_nacional',
            'patente_solicitada_nacional',
            'propiedad_intelectual',
            'art83_sin_conocimiento'
        ], true)
        &&
        !exp_contains_any($texto, ['patente', 'propiedad intelectual', 'art. 83', 'art83'])
    ) {
        return 0.0;
    }

    return match ($tipo) {
        'patente_obtenida_internacional' => 3.5,
        'patente_solicitada_internacional' => 2.5,
        'patente_obtenida_nacional' => 2.0,
        'patente_solicitada_nacional' => 1.4,
        'propiedad_intelectual' => 1.0,
        'art83_sin_conocimiento' => 0.8,
        default => 0.0,
    };
}

function calcular_1d_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_1d($item);
    }
    return exp_round(exp_clamp($total, 0.0, 4.0));
}

/* =========================================================
 * 1E TESIS
 * ========================================================= */

function exp_puntuar_item_1e(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'dirigida'), 'UTF-8');
    $aprobada = exp_bool($item['proyecto_aprobado'] ?? true, true);

    if ($tipo === 'en_direccion' && !$aprobada) {
        return 0.0;
    }

    $base = match ($tipo) {
        'dirigida' => 1.7,
        'en_direccion' => 1.0,
        default => 0.0,
    };

    if (exp_bool($item['doctorado_europeo'] ?? false)) {
        $base += 0.5;
    }
    if (exp_bool($item['mencion_calidad'] ?? false)) {
        $base += 0.3;
    }

    $codirectores = max(0, exp_to_int($item['numero_codirectores'] ?? 0, 0));
    if ($codirectores > 0) {
        $base *= max(0.40, 1 - (0.18 * $codirectores));
    }

    return exp_round($base);
}

function calcular_1e_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_1e($item);
    }
    return exp_round(exp_clamp($total, 0.0, 4.0));
}

/* =========================================================
 * 1F CONGRESOS
 * ========================================================= */

function exp_puntuar_item_1f(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }
    if (!exp_bool($item['proceso_selectivo'] ?? false, false)) {
        return 0.0;
    }

    $ambito = mb_strtolower(exp_str($item['ambito'] ?? 'nacional'), 'UTF-8');
    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'comunicacion_oral'), 'UTF-8');

    $base = match ($ambito) {
        'internacional' => 0.35,
        'nacional' => 0.22,
        default => 0.10,
    };

    $factor = match ($tipo) {
        'ponencia_invitada' => 1.20,
        'comunicacion_oral' => 1.00,
        'poster' => 0.70,
        default => 0.80,
    };

    return exp_round($base * $factor);
}

function calcular_1f_experimentales(array $items): float
{
    $total = 0.0;
    $mejorPorEvento = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $idEvento = exp_str($item['id_evento'] ?? '');
        $p = exp_puntuar_item_1f($item);

        if ($idEvento === '') {
            $total += $p;
            continue;
        }

        if (!isset($mejorPorEvento[$idEvento]) || $p > $mejorPorEvento[$idEvento]) {
            $mejorPorEvento[$idEvento] = $p;
        }
    }

    foreach ($mejorPorEvento as $p) {
        $total += $p;
    }

    return exp_round(exp_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * 1G OTROS MÉRITOS DE INVESTIGACIÓN
 * ========================================================= */

function exp_puntuar_item_1g(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (mb_strtolower(exp_str($item['tipo'] ?? 'otro'), 'UTF-8')) {
        'revision_jcr' => 0.25,
        'evaluacion_proyectos' => 0.35,
        'premio' => 0.10,
        'estancia' => 0.00,
        'otro' => 0.05,
        default => 0.00,
    };
}

function calcular_1g_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_1g($item);
    }
    return exp_round(exp_clamp($total, 0.0, 1.0));
}

/* =========================================================
 * 2A DOCENCIA UNIVERSITARIA
 * ========================================================= */

function calcular_2a_experimentales(array $items): float
{
    $horasTotal = 0.0;
    $tfg = 0;
    $tfm = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $horas = exp_to_float($item['horas'] ?? 0, 0);
        $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'grado'), 'UTF-8');

        $factorTipo = match ($tipo) {
            'master' => 1.00,
            'grado' => 1.00,
            'titulo_propio' => 0.70,
            default => 0.90,
        };

        $horasTotal += ($horas * $factorTipo);
        $tfg += exp_to_int($item['tfg'] ?? 0, 0);
        $tfm += exp_to_int($item['tfm'] ?? 0, 0);
    }

    $pHoras = 17.0 * min(1.0, $horasTotal / 450.0);
    $pTF = min(1.5, ($tfg * 0.15) + ($tfm * 0.30));

    return exp_round(exp_clamp($pHoras + $pTF, 0.0, 17.0));
}

/* =========================================================
 * 2B EVALUACIÓN DOCENTE
 * ========================================================= */

function calcular_2b_experimentales(array $evaluaciones, array $docencia): float
{
    $horasDocencia = 0.0;
    foreach ($docencia as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }
        $horasDocencia += exp_to_float($item['horas'] ?? 0, 0);
    }

    $horasEvaluadas = 0.0;
    $sumaNotas = 0.0;
    $n = 0;

    foreach ($evaluaciones as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $resultado = mb_strtolower(exp_str($item['resultado'] ?? 'aceptable'), 'UTF-8');
        $p = match ($resultado) {
            'excelente' => 9.7,
            'muy_favorable' => 9.0,
            'favorable' => 7.8,
            default => 6.5,
        };

        $sumaNotas += $p;
        $n++;

        $texto = exp_text($item);
        if (preg_match('/horas?\s+(\d+(?:[.,]\d+)?)/iu', $texto, $m)) {
            $horasEvaluadas += (float)str_replace(',', '.', $m[1]);
        } else {
            $horasEvaluadas += 22.0;
        }
    }

    if ($n === 0) {
        return 0.0;
    }

    $media = $sumaNotas / $n;
    $cobertura = $horasDocencia > 0 ? min(1.0, $horasEvaluadas / $horasDocencia) : 0.50;

    $pCalidad = match (true) {
        $media >= 9.5 => 2.7,
        $media >= 8.8 => 2.2,
        $media >= 8.0 => 1.7,
        default => 1.0,
    };

    $factorCobertura = match (true) {
        $cobertura >= 0.75 => 1.00,
        $cobertura >= 0.50 => 0.80,
        $cobertura >= 0.30 => 0.60,
        default => 0.40,
    };

    return exp_round(exp_clamp($pCalidad * $factorCobertura, 0.0, 3.0));
}

/* =========================================================
 * 2C FORMACIÓN DOCENTE UNIVERSITARIA
 * ========================================================= */

function exp_es_formacion_docente_valida(array $item): bool
{
    $texto = exp_text($item);

    if (exp_contains_any($texto, [
        'hackathon',
        'design thinking',
        'ia aplicada a la investigación',
        'ia aplicada a la investigacion',
        'emergencia',
        'divulgación científica',
        'divulgacion cientifica',
    ])) {
        return false;
    }

    return exp_contains_any($texto, [
        'docencia',
        'docente',
        'campus docente',
        'iniciación a la docencia',
        'iniciacion a la docencia',
        'innovación docente',
        'innovacion docente',
        'uso profesional de la voz',
        'profesorado',
        'personal docente',
        'actas',
    ]);
}

function calcular_2c_experimentales(array $items): float
{
    $horas = 0.0;
    $bonusPonente = 0.0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }
        if (!exp_es_formacion_docente_valida($item)) {
            continue;
        }

        $horas += exp_to_float($item['horas'] ?? 0, 0);

        if (mb_strtolower(exp_str($item['rol'] ?? 'asistente'), 'UTF-8') === 'ponente') {
            $bonusPonente += 0.25;
        }
    }

    $p = match (true) {
        $horas >= 80 => 3.0,
        $horas >= 50 => 2.3,
        $horas >= 25 => 1.6,
        $horas >= 10 => 0.9,
        $horas > 0 => 0.4,
        default => 0.0,
    };

    $p += min(0.5, $bonusPonente);

    return exp_round(exp_clamp($p, 0.0, 3.0));
}

/* =========================================================
 * 2D MATERIAL DOCENTE / INNOVACIÓN DOCENTE
 * ========================================================= */

function exp_puntuar_item_2d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $texto = exp_text($item);
    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'material_original'), 'UTF-8');

    $esValido = exp_contains_any($texto, [
        'libro docente',
        'capítulo docente',
        'capitulo docente',
        'manual docente',
        'material docente',
        'innovación docente',
        'innovacion docente',
        'proyecto de innovación docente',
        'proyecto de innovacion docente',
        'isbn',
    ]);

    if (!$esValido) {
        return 0.0;
    }

    $nivel = mb_strtolower(exp_str($item['nivel_editorial'] ?? 'nacional'), 'UTF-8');

    $base = match ($tipo) {
        'libro_docente' => ($nivel === 'internacional' ? 2.2 : 1.4),
        'capitulo_docente' => ($nivel === 'internacional' ? 1.2 : 0.7),
        'innovacion_docente' => 1.4,
        'material_original' => 0.7,
        default => 0.0,
    };

    return exp_round($base);
}

function calcular_2d_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_2d($item);
    }
    return exp_round(exp_clamp($total, 0.0, 7.0));
}

/* =========================================================
 * 3A FORMACIÓN ACADÉMICA
 * ========================================================= */

function exp_puntuar_item_3a(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'curso_especializacion'), 'UTF-8');
    $alta = exp_bool($item['alta_competitividad'] ?? false);

    return match ($tipo) {
        'doctorado_internacional' => 1.6,
        'mencion_calidad' => 0.6,
        'beca_predoc_fpu' => 1.8,
        'beca_predoc_fpi' => 1.7,
        'beca_predoc_autonomica' => 1.1,
        'beca_predoc_universidad' => 0.8,
        'premio_extra_doctorado' => 1.0,
        'beca_posdoc' => $alta ? 1.4 : 1.0,
        'estancia' => 0.9,
        'curso_especializacion' => 0.25,
        default => 0.0,
    };
}

function calcular_3a_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_3a($item);
    }
    return exp_round(exp_clamp($total, 0.0, 6.0));
}

/* =========================================================
 * 3B EXPERIENCIA PROFESIONAL
 * ========================================================= */

function exp_puntuar_item_3b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $texto = exp_text($item);

    if (!exp_bool($item['justificada'] ?? false, false)) {
        return 0.0;
    }

    if (exp_contains_any($texto, [
        'proyecto',
        'investigador doctor',
        'predoctoral',
        'fpu',
        'fpi',
        'universidad de granada',
        'organizador del seminario',
        'asesor científico de equipos instrumentales',
        'asesor cientifico de equipos instrumentales',
        'unidad de calidad',
        'beca de colaboración',
        'beca de colaboracion',
        'divulgación científica',
        'divulgacion cientifica',
    ])) {
        return 0.0;
    }

    $anios = exp_to_float($item['anios'] ?? 0, 0);
    $relacion = mb_strtolower(exp_str($item['relacion'] ?? 'media'), 'UTF-8');

    $base = match (true) {
        $anios >= 5 => 1.5,
        $anios >= 3 => 1.0,
        $anios >= 1 => 0.6,
        $anios > 0 => 0.25,
        default => 0.0,
    };

    $factor = match ($relacion) {
        'alta' => 1.0,
        'media' => 0.75,
        'baja' => 0.40,
        default => 0.60,
    };

    return exp_round($base * $factor);
}

function calcular_3b_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_3b($item);
    }
    return exp_round(exp_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * 4 OTROS MÉRITOS
 * ========================================================= */

function exp_puntuar_item_4(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (mb_strtolower(exp_str($item['tipo'] ?? 'otro'), 'UTF-8')) {
        'gestion' => 0.50,
        'divulgacion' => 0.30,
        'asesor_equipos' => 0.70,
        'beca_colaboracion' => 0.25,
        'premio_extra_grado' => 0.60,
        'otro' => 0.15,
        default => 0.0,
    };
}

function calcular_4_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_4($item);
    }
    return exp_round(exp_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * DIAGNÓSTICO Y ASESOR
 * ========================================================= */

function exp_objetivos_orientativos(): array
{
    return [
        'B1' => 35.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 18.0,
        '1B' => 1.0,
        '1C' => 4.0,
        '1D' => 1.5,
        '1E' => 1.0,
        '1F' => 0.5,
        '1G' => 0.3,

        '2A' => 12.0,
        '2B' => 1.0,
        '2C' => 0.8,
        '2D' => 1.5,

        '3A' => 2.0,
        '3B' => 1.0,

        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function exp_clasificar_nivel(float $actual, float $objetivo): string
{
    if ($objetivo <= 0.0) {
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

function exp_detectar_perfil(array $resultado): string
{
    $b1 = (float)($resultado['bloque_1']['B1'] ?? 0.0);
    $b2 = (float)($resultado['bloque_2']['B2'] ?? 0.0);
    $b3 = (float)($resultado['bloque_3']['B3'] ?? 0.0);
    $b4 = (float)($resultado['bloque_4']['B4'] ?? 0.0);
    $total12 = (float)($resultado['totales']['total_b1_b2'] ?? 0.0);
    $total = (float)($resultado['totales']['total_final'] ?? 0.0);

    if ($b1 >= 35.0 && $b2 < 10.0) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }

    if ($b2 >= 15.0 && $b1 < 25.0) {
        return 'Perfil docente razonable con investigación insuficiente';
    }

    if ($b1 >= 30.0 && $b2 >= 12.0 && $total12 >= 50.0 && $total >= 55.0) {
        return 'Perfil equilibrado y competitivo para acreditación en Experimentales';
    }

    if ($b1 >= 25.0 && (float)($resultado['bloque_1']['1C'] ?? 0.0) < 2.0) {
        return 'Perfil investigador con proyectos competitivos todavía escasos';
    }

    if ($b1 < 20.0 && $b2 < 10.0) {
        return 'Perfil aún inmaduro para acreditación en rama experimental';
    }

    if (($b3 + $b4) >= 4.0 && $total12 < 50.0) {
        return 'Perfil con méritos complementarios aceptables, pero núcleo B1+B2 insuficiente';
    }

    return 'Perfil mixto con fortalezas parciales y necesidad de refuerzo estratégico';
}

function exp_generar_diagnostico(array $datos, array $resultado): array
{
    $obj = exp_objetivos_orientativos();

    $b1 = (float)($resultado['bloque_1']['B1'] ?? 0.0);
    $b2 = (float)($resultado['bloque_2']['B2'] ?? 0.0);
    $b3 = (float)($resultado['bloque_3']['B3'] ?? 0.0);
    $b4 = (float)($resultado['bloque_4']['B4'] ?? 0.0);
    $total12 = (float)($resultado['totales']['total_b1_b2'] ?? 0.0);
    $total = (float)($resultado['totales']['total_final'] ?? 0.0);

    $deficit1 = max(0.0, 50.0 - $total12);
    $deficit2 = max(0.0, 55.0 - $total);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ((float)($resultado['bloque_1']['1A'] ?? 0.0) >= $obj['1A']) {
        $fortalezas[] = 'La producción científica principal (1A) presenta un peso competitivo.';
    } else {
        $debilidades[] = 'La producción científica principal (1A) aún necesita refuerzo.';
    }

    if ((float)($resultado['bloque_2']['2A'] ?? 0.0) >= $obj['2A']) {
        $fortalezas[] = 'La docencia universitaria acreditable alcanza un nivel razonable.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable (2A) es insuficiente para consolidar el bloque docente.';
    }

    if ((float)($resultado['bloque_1']['1C'] ?? 0.0) >= $obj['1C']) {
        $fortalezas[] = 'Existe respaldo aceptable en proyectos competitivos.';
    } else {
        $debilidades[] = 'Conviene reforzar proyectos competitivos y participación certificada.';
    }

    if ((float)($resultado['bloque_2']['2B'] ?? 0.0) <= 0.0) {
        $alertas[] = 'No consta evaluación docente formal relevante.';
    }

    if ((float)($resultado['bloque_2']['2D'] ?? 0.0) < $obj['2D']) {
        $alertas[] = 'La innovación o el material docente aportan poco al bloque 2.';
    }

    if ($deficit1 > 0.0) {
        $alertas[] = 'No se cumple la regla principal B1 + B2 ≥ 50.';
    }

    if ($deficit2 > 0.0) {
        $alertas[] = 'No se cumple la regla total ≥ 55.';
    }

    return [
        'version' => 'conservadora_experimentales_v2_diagnostico_asesor',
        'perfil_detectado' => exp_detectar_perfil($resultado),
        'reglas' => [
            [
                'nombre' => 'Regla principal B1 + B2 ≥ 50',
                'valor_actual' => exp_round($total12),
                'objetivo' => 50.0,
                'deficit' => exp_round($deficit1),
                'cumple' => (bool)($resultado['decision']['cumple_regla_1'] ?? false),
            ],
            [
                'nombre' => 'Regla total final ≥ 55',
                'valor_actual' => exp_round($total),
                'objetivo' => 55.0,
                'deficit' => exp_round($deficit2),
                'cumple' => (bool)($resultado['decision']['cumple_regla_2'] ?? false),
            ],
        ],
        'bloques' => [
            'B1' => [
                'actual' => exp_round($b1),
                'objetivo_orientativo' => $obj['B1'],
                'deficit' => exp_round(max(0.0, $obj['B1'] - $b1)),
                'nivel' => exp_clasificar_nivel($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => exp_round($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => exp_round(max(0.0, $obj['B2'] - $b2)),
                'nivel' => exp_clasificar_nivel($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => exp_round($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => exp_round(max(0.0, $obj['B3'] - $b3)),
                'nivel' => exp_clasificar_nivel($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => exp_round($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => exp_round(max(0.0, $obj['B4'] - $b4)),
                'nivel' => exp_clasificar_nivel($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'publicaciones')),
            'libros' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'libros')),
            'proyectos' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'proyectos')),
            'transferencia' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'transferencia')),
            'tesis' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'tesis_dirigidas')),
            'congresos' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'congresos')),
            'docencia_items' => exp_count_valid(exp_list(exp_list($datos, 'bloque_2'), 'docencia_universitaria')),
            'evaluaciones_docentes' => exp_count_valid(exp_list(exp_list($datos, 'bloque_2'), 'evaluacion_docente')),
            'formacion_docente' => exp_count_valid(exp_list(exp_list($datos, 'bloque_2'), 'formacion_docente')),
            'material_docente' => exp_count_valid(exp_list(exp_list($datos, 'bloque_2'), 'material_docente')),
            'formacion_academica' => exp_count_valid(exp_list(exp_list($datos, 'bloque_3'), 'formacion_academica')),
            'exp_profesional' => exp_count_valid(exp_list(exp_list($datos, 'bloque_3'), 'experiencia_profesional')),
            'otros_meritos' => exp_count_valid(is_array(exp_list($datos, 'bloque_4')) ? exp_list($datos, 'bloque_4') : []),
        ],
        'fortalezas' => $fortalezas,
        'debilidades' => $debilidades,
        'alertas' => $alertas,
    ];
}

function exp_generar_asesor(array $resultado): array
{
    $acciones = [];

    $total12 = (float)($resultado['totales']['total_b1_b2'] ?? 0.0);
    $total = (float)($resultado['totales']['total_final'] ?? 0.0);

    $p1a = (float)($resultado['bloque_1']['1A'] ?? 0.0);
    $p1c = (float)($resultado['bloque_1']['1C'] ?? 0.0);
    $p1d = (float)($resultado['bloque_1']['1D'] ?? 0.0);
    $p2a = (float)($resultado['bloque_2']['2A'] ?? 0.0);
    $p2b = (float)($resultado['bloque_2']['2B'] ?? 0.0);
    $p2d = (float)($resultado['bloque_2']['2D'] ?? 0.0);
    $b3 = (float)($resultado['bloque_3']['B3'] ?? 0.0);
    $b4 = (float)($resultado['bloque_4']['B4'] ?? 0.0);

    if ($total12 < 50.0 && $p1a < 18.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar publicaciones principales',
            'detalle' => 'En Experimentales suele pesar mucho añadir artículos JCR competitivos, preferiblemente originales, con buena posición de autoría y acreditación clara.',
            'impacto_estimado' => '≈ 2.5 a 4.5 puntos por aportación fuerte.',
        ];
    }

    if ($total12 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Consolidar docencia universitaria acreditable',
            'detalle' => 'El cuello de botella puede estar en 2A. Conviene aumentar docencia reglada, horas y dirección/tutorización bien certificadas.',
            'impacto_estimado' => '≈ 2 a 4 puntos.',
        ];
    }

    if ($p1c < 4.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Aumentar proyectos competitivos',
            'detalle' => 'Los proyectos nacionales o internacionales con papel claro y certificación institucional mejoran mucho la consistencia del expediente.',
            'impacto_estimado' => '≈ 1 a 4 puntos por proyecto relevante.',
        ];
    }

    if ($p1d < 1.5) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Reforzar transferencia',
            'detalle' => 'Patentes, contratos, resultados transferibles o explotación acreditada ayudan a cerrar el bloque investigador.',
            'impacto_estimado' => '≈ 0.8 a 2 puntos.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Aportar evaluación docente formal',
            'detalle' => 'DOCENTIA o informes institucionales favorables fortalecen el bloque 2 y mejoran la lectura global del expediente.',
            'impacto_estimado' => '≈ 0.8 a 1.8 puntos.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Añadir innovación o material docente',
            'detalle' => 'Material docente, manuales, innovación educativa o recursos docentes acreditados ayudan a redondear el bloque docente.',
            'impacto_estimado' => '≈ 0.7 a 2 puntos.',
        ];
    }

    if ($total < 55.0 && ($b3 + $b4) < 3.0) {
        $acciones[] = [
            'prioridad' => 7,
            'titulo' => 'Mejorar méritos complementarios',
            'detalle' => 'La formación académica adicional, estancias, experiencia profesional y otros méritos pueden ayudar a cerrar el tramo final hasta 55.',
            'impacto_estimado' => '≈ 1 a 3 puntos.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => (($a['prioridad'] ?? 999) <=> ($b['prioridad'] ?? 999)));

    if ($acciones === []) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Expediente equilibrado',
            'detalle' => 'No se aprecian debilidades severas. Conviene seguir consolidando publicaciones principales, proyectos y estabilidad docente.',
            'impacto_estimado' => 'Impacto incremental.',
        ];
    }

    return [
        'resumen' => 'Asesor orientativo para identificar qué palancas pueden mejorar antes el expediente de Experimentales.',
        'acciones' => array_values($acciones),
        'simulaciones' => [
            [
                'escenario' => 'Añadir una publicación fuerte en 1A',
                'efecto_estimado' => '+3.5 puntos aprox.',
                'nuevo_b1_b2_aprox' => exp_round(min(90.0, $total12 + 3.5)),
                'nuevo_total_aprox' => exp_round(min(100.0, $total + 3.5)),
            ],
            [
                'escenario' => 'Añadir un proyecto competitivo relevante',
                'efecto_estimado' => '+2.5 puntos aprox.',
                'nuevo_b1_b2_aprox' => exp_round(min(90.0, $total12 + 2.5)),
                'nuevo_total_aprox' => exp_round(min(100.0, $total + 2.5)),
            ],
            [
                'escenario' => 'Consolidar docencia y evaluación docente',
                'efecto_estimado' => '+3 puntos aprox.',
                'nuevo_b1_b2_aprox' => exp_round(min(90.0, $total12 + 3.0)),
                'nuevo_total_aprox' => exp_round(min(100.0, $total + 3.0)),
            ],
        ],
    ];
}

/* =========================================================
 * EVALUACIÓN PRINCIPAL
 * ========================================================= */

function evaluar_expediente(array $datos): array
{
    $bloque1 = exp_list($datos, 'bloque_1');
    $bloque2 = exp_list($datos, 'bloque_2');
    $bloque3 = exp_list($datos, 'bloque_3');
    $bloque4 = exp_list($datos, 'bloque_4');

    $publicaciones = exp_list($bloque1, 'publicaciones');
    $libros = exp_list($bloque1, 'libros');
    $proyectos = exp_list($bloque1, 'proyectos');
    $transferencia = exp_list($bloque1, 'transferencia');
    $tesis = exp_list($bloque1, 'tesis_dirigidas');
    $congresos = exp_list($bloque1, 'congresos');
    $otrosInv = exp_list($bloque1, 'otros_meritos_investigacion');

    $docencia = exp_list($bloque2, 'docencia_universitaria');
    $evaluacion = exp_list($bloque2, 'evaluacion_docente');
    $formacionDoc = exp_list($bloque2, 'formacion_docente');
    $materialDoc = exp_list($bloque2, 'material_docente');

    $formacion = exp_list($bloque3, 'formacion_academica');
    $expProfesional = exp_list($bloque3, 'experiencia_profesional');

    $p1a = calcular_1a_experimentales($publicaciones);
    $p1b = calcular_1b_experimentales($libros);
    $p1c = calcular_1c_experimentales($proyectos);
    $p1d = calcular_1d_experimentales($transferencia);
    $p1e = calcular_1e_experimentales($tesis);
    $p1f = calcular_1f_experimentales($congresos);
    $p1g = calcular_1g_experimentales($otrosInv);
    $B1 = exp_round(exp_clamp($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 0.0, 60.0));

    $p2a = calcular_2a_experimentales($docencia);
    $p2b = calcular_2b_experimentales($evaluacion, $docencia);
    $p2c = calcular_2c_experimentales($formacionDoc);
    $p2d = calcular_2d_experimentales($materialDoc);
    $B2 = exp_round(exp_clamp($p2a + $p2b + $p2c + $p2d, 0.0, 30.0));

    $p3a = calcular_3a_experimentales($formacion);
    $p3b = calcular_3b_experimentales($expProfesional);
    $B3 = exp_round(exp_clamp($p3a + $p3b, 0.0, 8.0));

    $p4 = calcular_4_experimentales(is_array($bloque4) ? $bloque4 : []);
    $B4 = $p4;

    $totalB1B2 = exp_round($B1 + $B2);
    $totalFinal = exp_round($B1 + $B2 + $B3 + $B4);

    $cumpleRegla1 = $totalB1B2 >= 50.0;
    $cumpleRegla2 = $totalFinal >= 55.0;
    $evaluacionPositiva = $cumpleRegla1 && $cumpleRegla2;
    $resultadoTexto = $evaluacionPositiva ? 'POSITIVA' : 'NEGATIVA';

    $resultado = [
        'puntuaciones' => [
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
            '4'  => $p4,
        ],
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
        'totales' => [
            'bloque_1' => $B1,
            'bloque_2' => $B2,
            'bloque_3' => $B3,
            'bloque_4' => $B4,
            'total_b1_b2' => $totalB1B2,
            'total_final' => $totalFinal,
            'global' => $totalFinal,
        ],
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
    ];

    $resultado['diagnostico'] = exp_generar_diagnostico($datos, $resultado);
    $resultado['asesor'] = exp_generar_asesor($resultado);

    return $resultado;
}