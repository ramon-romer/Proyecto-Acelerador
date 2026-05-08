<?php
declare(strict_types=1);

/**
 * EVALUADOR ANECA - TÉCNICAS (PCD/PUP)
 *
 * Distribución orientativa aplicada:
 * 1A = 32
 * 1B = 3
 * 1C = 12
 * 1D = 6
 * 1E = 4
 * 1F = 2
 * 1G = 1
 * B1 = 60
 *
 * 2A = 17
 * 2B = 3
 * 2C = 3
 * 2D = 7
 * B2 = 30
 *
 * 3A = 6
 * 3B = 2
 * B3 = 8
 *
 * 4 = 2
 *
 * Regla positiva:
 * B1 + B2 >= 50
 * B1 + B2 + B3 + B4 >= 55
 *
 * Versión conservadora:
 * - Se endurece 1A para evitar inflación por coautoría/posición.
 * - 1C se acerca a la lógica de Experimentales: el peso fuerte está en IP real
 *   y en proyectos competitivos; se excluyen contratos laborales y redes temáticas.
 * - 2A, 2B, 2C y 2D se calculan de forma global y prudente.
 * - 3B y 4 son restrictivos.
 */

/* =========================================================
 * HELPERS GENERALES
 * ========================================================= */

function tec_to_float(mixed $value, float $default = 0.0): float
{
    if ($value === null || $value === '') {
        return $default;
    }

    if (is_numeric($value)) {
        return (float)$value;
    }

    return (float)str_replace(',', '.', (string)$value);
}

function tec_to_int(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }

    return (int)$value;
}

function tec_str(mixed $value, string $default = ''): string
{
    $v = trim((string)$value);
    return $v === '' ? $default : $v;
}

function tec_bool(mixed $value, bool $default = false): bool
{
    if ($value === null || $value === '') {
        return $default;
    }

    return in_array((string)$value, ['1', 'true', 'on', 'si', 'sí', 'yes'], true);
}

function tec_lower(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($value, 'UTF-8')
        : strtolower($value);
}

function tec_round(float $value): float
{
    return round($value, 2);
}

function tec_clamp(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function tec_list(array $data, string $key): array
{
    $value = $data[$key] ?? [];
    return is_array($value) ? $value : [];
}

function tec_text(array $item): string
{
    return tec_lower((string)($item['fuente_texto'] ?? ''));
}

function tec_contains_any(string $text, array $terms): bool
{
    foreach ($terms as $term) {
        if (function_exists('mb_stripos')) {
            if (mb_stripos($text, $term, 0, 'UTF-8') !== false) {
                return true;
            }
        } else {
            if (stripos($text, $term) !== false) {
                return true;
            }
        }
    }

    return false;
}

function tec_count_valid(array $items, string $field = 'es_valido'): int
{
    $n = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $isValid = $item[$field] ?? ($item['es_valida'] ?? 1);
        if ((string)$isValid !== '0') {
            $n++;
        }
    }

    return $n;
}

/* =========================================================
 * FACTORES COMUNES
 * ========================================================= */

function tec_factor_afinidad(string $afinidad): float
{
    return match (tec_lower($afinidad)) {
        'total' => 1.05,
        'relacionada' => 1.00,
        'periferica' => 0.85,
        'ajena' => 0.50,
        default => 1.00,
    };
}

function tec_factor_posicion_autor(string $posicion): float
{
    return match (tec_lower($posicion)) {
        'autor_unico', 'unico' => 1.08,
        'primero' => 1.03,
        'ultimo' => 1.03,
        'correspondencia' => 1.03,
        'intermedio' => 0.85,
        'secundario' => 0.72,
        default => 0.85,
    };
}

function tec_factor_coautoria(int $numeroAutores): float
{
    $n = max(1, $numeroAutores);

    return match (true) {
        $n <= 2 => 1.03,
        $n <= 4 => 0.95,
        $n <= 6 => 0.85,
        $n <= 10 => 0.72,
        $n <= 20 => 0.58,
        default => 0.45,
    };
}

function tec_factor_citas(int $citas, int $anios): float
{
    $c = max(0, $citas);
    $a = max(0, $anios);

    if ($a <= 2 && $c <= 3) {
        return 1.00;
    }

    return match (true) {
        $c <= 1 => 0.95,
        $c <= 5 => 1.00,
        $c <= 15 => 1.03,
        $c <= 40 => 1.08,
        default => 1.12,
    };
}

/* =========================================================
 * 1A - PUBLICACIONES CIENTÍFICAS Y PATENTES
 * ========================================================= */

function tec_base_publicacion_1a(array $pub): float
{
    $tipo = tec_lower(tec_str($pub['tipo'] ?? 'articulo'));
    $tipoIndice = strtoupper(tec_str($pub['tipo_indice'] ?? ''));
    $tercil = strtoupper(tec_str($pub['tercil'] ?? ''));
    $cuartil = strtoupper(tec_str($pub['cuartil'] ?? ''));
    $subtipo = strtoupper(tec_str($pub['subtipo_indice'] ?? ''));

    if ($tipo === 'patente') {
        return match ($subtipo) {
            'B1' => 3.0,
            'B2' => 2.3,
            default => 1.5,
        };
    }

    if ($tipo === 'software') {
        return 1.2;
    }

    if ($tercil === 'T1') {
        return 4.5;
    }
    if ($tercil === 'T2') {
        return 3.0;
    }
    if ($tercil === 'T3') {
        return 1.4;
    }

    if ($cuartil === 'Q1') {
        return 4.2;
    }
    if ($cuartil === 'Q2') {
        return 2.8;
    }
    if ($cuartil === 'Q3' || $cuartil === 'Q4') {
        return 1.2;
    }

    if ($tipoIndice === 'CORE' && in_array($subtipo, ['A', 'A+'], true)) {
        return 1.4;
    }

    if ($tipoIndice === 'CSIE' && $subtipo === 'CLASE_1') {
        return 1.4;
    }

    if (in_array($tipoIndice, ['RESH', 'AVERY', 'RIBA', 'ARTS_HUMANITIES'], true)) {
        return 1.4;
    }

    if (in_array($tipoIndice, ['JCR', 'SCOPUS', 'SJR'], true)) {
        return 0.7;
    }

    if ($tipoIndice === 'PATENTE') {
        return match ($subtipo) {
            'B1' => 3.0,
            'B2' => 2.3,
            default => 1.5,
        };
    }

    return 0.0;
}

function tec_puntuar_item_1a(array $pub): float
{
    if (!is_array($pub)) {
        return 0.0;
    }

    $esValida = $pub['es_valida'] ?? $pub['es_valido'] ?? 1;
    if ((string)$esValida === '0') {
        return 0.0;
    }

    if (
        tec_bool($pub['es_divulgacion'] ?? false)
        || tec_bool($pub['es_docencia'] ?? false)
        || tec_bool($pub['es_acta_congreso'] ?? false)
        || tec_bool($pub['es_informe_proyecto'] ?? false)
    ) {
        return 0.0;
    }

    $tipo = tec_lower(tec_str($pub['tipo'] ?? 'articulo'));

    if (!in_array($tipo, ['articulo', 'patente', 'software'], true)) {
        return 0.0;
    }

    $base = tec_base_publicacion_1a($pub);
    if ($base <= 0.0) {
        return 0.0;
    }

    if ($tipo === 'patente' || $tipo === 'software') {
        $factor = tec_bool($pub['liderazgo'] ?? false) ? 1.05 : 1.00;
        return tec_round($base * $factor);
    }

    $ordenAlfabetico = tec_bool($pub['orden_alfabetico'] ?? false);
    $factorPosicion = $ordenAlfabetico
        ? 1.00
        : tec_factor_posicion_autor(tec_str($pub['posicion_autor'] ?? 'intermedio'));

    $p = $base
        * tec_factor_afinidad(tec_str($pub['afinidad'] ?? 'relacionada'))
        * $factorPosicion
        * tec_factor_coautoria(tec_to_int($pub['numero_autores'] ?? 1, 1))
        * tec_factor_citas(
            tec_to_int($pub['citas'] ?? 0, 0),
            tec_to_int($pub['anios_desde_publicacion'] ?? 3, 3)
        );

    if (tec_bool($pub['liderazgo'] ?? false)) {
        $p *= 1.03;
    }

    return tec_round($p);
}

function calcular_1a_tecnicas(array $publicaciones): float
{
    $total = 0.0;

    foreach ($publicaciones as $pub) {
        $total += tec_puntuar_item_1a($pub);
    }

    return tec_round(tec_clamp($total, 0.0, 32.0));
}

/* =========================================================
 * 1B - LIBROS Y CAPÍTULOS
 * ========================================================= */

function tec_puntuar_item_1b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $texto = tec_text($item);
    $tipo = tec_lower(tec_str($item['tipo'] ?? 'libro'));
    $nivel = tec_lower(tec_str($item['nivel_editorial'] ?? 'nacional'));

    $pareceLibroReal = tec_contains_any($texto, [
        'isbn',
        'libro',
        'capítulo',
        'capitulo',
        'editorial',
        'springer',
        'elsevier',
        'wiley',
        'cambridge',
        'oxford'
    ]);

    if (!$pareceLibroReal && !tec_bool($item['es_libro_investigacion'] ?? true, true)) {
        return 0.0;
    }

    $base = match ($nivel) {
        'internacional', 'prestigiosa' => ($tipo === 'libro' ? 1.6 : 0.8),
        'nacional', 'secundaria' => ($tipo === 'libro' ? 1.0 : 0.5),
        default => ($tipo === 'libro' ? 0.4 : 0.2),
    };

    if (tec_bool($item['es_autoedicion'] ?? false) || tec_bool($item['es_acta_congreso'] ?? false)) {
        $base *= 0.25;
    }

    if (tec_bool($item['es_labor_edicion'] ?? false)) {
        $base *= 0.60;
    }

    return tec_round($base);
}

function calcular_1b_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_1b($item);
    }

    return tec_round(tec_clamp($total, 0.0, 3.0));
}

/* =========================================================
 * 1C - PROYECTOS Y CONTRATOS DE INVESTIGACIÓN
 * ========================================================= */

function tec_inferir_rol_real_proyecto_1c(array $item): string
{
    $rolExtraido = tec_lower(tec_str($item['rol'] ?? 'investigador'));
    $texto = tec_text($item);

    if (tec_contains_any($texto, [
        'investigador principal y',
        'soy investigador principal',
        'investigador principal'
    ])) {
        return 'ip';
    }

    if (tec_contains_any($texto, [
        'coip',
        'co-ip',
        'co ip'
    ])) {
        return 'coip';
    }

    if (tec_contains_any($texto, [
        'equipo de trabajo',
        'colaborador',
        'investigador colaborador',
        'miembro del equipo de trabajo'
    ])) {
        return 'investigador';
    }

    if (tec_contains_any($texto, [
        'contrato laboral',
        'investigador doctor contratado',
        'contratado por',
        'vinculado al proyecto a través de un contrato laboral'
    ])) {
        return 'contrato_laboral';
    }

    if ($rolExtraido === 'ip' || $rolExtraido === 'coip') {
        return $rolExtraido;
    }

    return 'investigador';
}

function tec_es_proyecto_elegible_1c(array $item): bool
{
    $texto = tec_text($item);

    if (!tec_bool($item['esta_certificado'] ?? true, true)) {
        return false;
    }

    if (tec_contains_any($texto, [
        'contrato laboral',
        'investigador doctor contratado',
        'contratado por la universidad',
        'vinculado al proyecto a través de un contrato laboral',
        'ayuda a grupos de i+d',
        'ayudas a grupos de i+d',
        'red temática',
        'red tematica',
        'redes temáticas',
        'redes tematicas'
    ])) {
        return false;
    }

    return true;
}

function tec_puntuar_item_1c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    if (!tec_es_proyecto_elegible_1c($item)) {
        return 0.0;
    }

    $tipo = tec_lower(tec_str($item['tipo_proyecto'] ?? 'universidad'));
    $rol = tec_inferir_rol_real_proyecto_1c($item);
    $anios = tec_to_float($item['anios_duracion'] ?? 0, 0);

    if ($rol === 'contrato_laboral') {
        return 0.0;
    }

    $p = 0.0;

    if (in_array($tipo, ['internacional', 'europeo'], true)) {
        $p = match ($rol) {
            'ip' => 3.8,
            'coip' => 3.0,
            default => 0.9,
        };
    } elseif ($tipo === 'nacional') {
        $p = match ($rol) {
            'ip' => 3.2,
            'coip' => 2.6,
            default => 0.75,
        };
    } elseif ($tipo === 'autonomico') {
        $p = match ($rol) {
            'ip' => 1.8,
            'coip' => 1.4,
            default => 0.5,
        };
    } elseif ($tipo === 'universidad') {
        $p = match ($rol) {
            'ip' => 0.9,
            'coip' => 0.7,
            default => 0.3,
        };
    } elseif (in_array($tipo, ['empresa', 'desarrollo_industrial'], true)) {
        $p = match ($rol) {
            'ip' => 1.0,
            'coip' => 0.8,
            default => 0.4,
        };
    } elseif ($tipo === 'infraestructura') {
        $p = match ($rol) {
            'ip' => 0.5,
            'coip' => 0.4,
            default => 0.2,
        };
    }

    if ($anios >= 3.0) {
        $p *= 1.05;
    } elseif ($anios > 0.0 && $anios < 1.0) {
        $p *= 0.75;
    }

    return tec_round($p);
}

function calcular_1c_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_1c($item);
    }

    return tec_round(tec_clamp($total, 0.0, 12.0));
}

/* =========================================================
 * 1D - TRANSFERENCIA DE TECNOLOGÍA
 * ========================================================= */

function tec_puntuar_item_1d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = tec_lower(tec_str($item['tipo'] ?? 'otro'));
    $texto = tec_text($item);

    if (
        !in_array($tipo, [
            'patente_nacional',
            'contrato_empresa',
            'software_explotacion',
            'ebt',
            'otro'
        ], true)
        && !tec_contains_any($texto, [
            'patente',
            'contrato con empresa',
            'contrato empresa',
            'software',
            'explotación',
            'explotacion',
            'empresa de base tecnológica',
            'empresa de base tecnologica',
            'ebt'
        ])
    ) {
        return 0.0;
    }

    $base = match ($tipo) {
        'patente_nacional' => 2.0,
        'contrato_empresa' => 1.4,
        'software_explotacion' => 1.5,
        'ebt' => 1.6,
        'otro' => 0.4,
        default => 0.0,
    };

    if ($base <= 0.0) {
        return 0.0;
    }

    $factor = 1.0;

    if (tec_bool($item['impacto_externo'] ?? false)) {
        $factor *= 1.10;
    }
    if (tec_bool($item['liderazgo'] ?? false)) {
        $factor *= 1.10;
    }
    if (tec_bool($item['participacion_menor'] ?? false)) {
        $factor *= 0.80;
    }

    return tec_round($base * $factor);
}

function calcular_1d_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_1d($item);
    }

    return tec_round(tec_clamp($total, 0.0, 6.0));
}

/* =========================================================
 * 1E - DIRECCIÓN DE TESIS DOCTORALES
 * ========================================================= */

function tec_puntuar_item_1e(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = tec_lower(tec_str($item['tipo'] ?? 'codireccion'));

    $base = match ($tipo) {
        'direccion_principal', 'direccion_unica' => 1.7,
        'codireccion' => 1.0,
        default => 0.0,
    };

    if (tec_bool($item['calidad_especial'] ?? false)) {
        $base += 0.3;
    }

    return tec_round($base);
}

function calcular_1e_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_1e($item);
    }

    return tec_round(tec_clamp($total, 0.0, 4.0));
}

/* =========================================================
 * 1F - CONGRESOS, CONFERENCIAS, SEMINARIOS
 * ========================================================= */

function tec_puntuar_item_1f(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    if (!tec_bool($item['proceso_selectivo'] ?? false, false)) {
        return 0.0;
    }

    $ambito = tec_lower(tec_str($item['ambito'] ?? 'nacional'));
    $tipo = tec_lower(tec_str($item['tipo'] ?? 'comunicacion_oral'));

    $base = match ($ambito) {
        'internacional' => 0.35,
        'nacional' => 0.22,
        'autonomico', 'regional' => 0.12,
        default => 0.06,
    };

    $factor = match ($tipo) {
        'ponencia_invitada' => 1.20,
        'comunicacion_oral' => 1.00,
        'poster' => 0.70,
        'organizacion' => 0.60,
        default => 0.80,
    };

    return tec_round($base * $factor);
}

function calcular_1f_tecnicas(array $items): float
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

        $idEvento = tec_str($item['id_evento'] ?? '');
        $p = tec_puntuar_item_1f($item);

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

    return tec_round(tec_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * 1G - OTROS MÉRITOS DE INVESTIGACIÓN
 * ========================================================= */

function tec_puntuar_item_1g(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (tec_lower(tec_str($item['tipo'] ?? 'otro'))) {
        'grupo_investigacion' => 0.15,
        'comite_cientifico' => 0.25,
        'revision_revistas' => 0.20,
        'premio_investigacion' => 0.50,
        'otro' => 0.10,
        default => 0.0,
    };
}

function calcular_1g_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_1g($item);
    }

    return tec_round(tec_clamp($total, 0.0, 1.0));
}

/* =========================================================
 * BLOQUE 2 - EXPERIENCIA DOCENTE
 * ========================================================= */

function calcular_2a_tecnicas(array $items): float
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

        $horas = tec_to_float($item['horas'] ?? 0, 0);
        $tipo = tec_lower(tec_str($item['tipo'] ?? 'grado'));

        $factorTipo = match ($tipo) {
            'master' => 1.00,
            'grado' => 1.00,
            'titulo_propio' => 0.70,
            default => 0.90,
        };

        $horasTotal += ($horas * $factorTipo);
        $tfg += tec_to_int($item['tfg'] ?? 0, 0);
        $tfm += tec_to_int($item['tfm'] ?? 0, 0);
    }

    $pHoras = 17.0 * min(1.0, $horasTotal / 450.0);
    $pTF = min(1.5, ($tfg * 0.15) + ($tfm * 0.30));

    return tec_round(tec_clamp($pHoras + $pTF, 0.0, 17.0));
}

function calcular_2b_tecnicas(array $evaluaciones, array $docencia = []): float
{
    $horasDocencia = 0.0;

    foreach ($docencia as $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $horasDocencia += tec_to_float($item['horas'] ?? 0, 0);
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

        $resultado = tec_lower(tec_str($item['resultado'] ?? 'aceptable'));
        $p = match ($resultado) {
            'excelente' => 9.7,
            'muy_favorable' => 9.0,
            'favorable', 'positiva' => 7.8,
            default => 6.5,
        };

        $sumaNotas += $p;
        $n++;

        $texto = tec_text($item);
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

    return tec_round(tec_clamp($pCalidad * $factorCobertura, 0.0, 3.0));
}

function tec_es_formacion_docente_valida(array $item): bool
{
    $texto = tec_text($item);

    if (tec_contains_any($texto, [
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

    return tec_contains_any($texto, [
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

function calcular_2c_tecnicas(array $items): float
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

        if (!tec_es_formacion_docente_valida($item)) {
            continue;
        }

        $horas += tec_to_float($item['horas'] ?? 0, 0);

        if (tec_lower(tec_str($item['rol'] ?? 'asistente')) === 'ponente') {
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

    return tec_round(tec_clamp($p, 0.0, 3.0));
}

function tec_puntuar_item_2d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $texto = tec_text($item);
    $tipo = tec_lower(tec_str($item['tipo'] ?? 'material_original'));

    $esValido = tec_contains_any($texto, [
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

    $nivel = tec_lower(tec_str($item['nivel_editorial'] ?? 'nacional'));

    $base = match ($tipo) {
        'libro_docente' => ($nivel === 'internacional' ? 2.2 : 1.4),
        'capitulo_docente' => ($nivel === 'internacional' ? 1.2 : 0.7),
        'innovacion_docente' => 1.4,
        'material_original' => 0.7,
        default => 0.0,
    };

    return tec_round($base);
}

function calcular_2d_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_2d($item);
    }

    return tec_round(tec_clamp($total, 0.0, 7.0));
}

/* =========================================================
 * BLOQUE 3 - FORMACIÓN ACADÉMICA Y EXPERIENCIA PROFESIONAL
 * ========================================================= */

function tec_puntuar_item_3a(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (tec_lower(tec_str($item['tipo'] ?? 'otro'))) {
        'tesis_doctoral' => 1.6,
        'premio_doctorado' => 1.0,
        'beca' => 1.2,
        'estancia' => 0.9,
        'master' => 0.4,
        default => 0.1,
    };
}

function calcular_3a_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_3a($item);
    }

    return tec_round(tec_clamp($total, 0.0, 6.0));
}

function tec_puntuar_item_3b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $texto = tec_text($item);

    if (!tec_bool($item['justificada'] ?? false, false)) {
        return 0.0;
    }

    if (tec_contains_any($texto, [
        'proyecto',
        'investigador doctor',
        'predoctoral',
        'fpu',
        'fpi',
        'beca de colaboración',
        'beca de colaboracion',
        'divulgación científica',
        'divulgacion cientifica',
        'organizador del seminario',
    ])) {
        return 0.0;
    }

    $anios = tec_to_float($item['anios'] ?? 0, 0);
    $relacion = tec_lower(tec_str($item['relacion'] ?? 'media'));

    $base = match (true) {
        $anios >= 5 => 1.5,
        $anios >= 3 => 1.0,
        $anios >= 1 => 0.6,
        $anios > 0 => 0.25,
        default => 0.0,
    };

    $factor = match ($relacion) {
        'alta' => 1.00,
        'media' => 0.75,
        'baja' => 0.40,
        default => 0.60,
    };

    return tec_round($base * $factor);
}

function calcular_3b_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_3b($item);
    }

    return tec_round(tec_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * BLOQUE 4 - OTROS MÉRITOS
 * ========================================================= */

function tec_puntuar_item_4(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (tec_lower(tec_str($item['tipo'] ?? 'otro'))) {
        'gestion' => 0.50,
        'servicio_academico' => 0.30,
        'distincion' => 0.60,
        'sociedad_cientifica' => 0.40,
        'otro' => 0.15,
        default => 0.0,
    };
}

function calcular_4_tecnicas(array $items): float
{
    $total = 0.0;

    foreach ($items as $item) {
        $total += tec_puntuar_item_4($item);
    }

    return tec_round(tec_clamp($total, 0.0, 2.0));
}

/* =========================================================
 * DIAGNÓSTICO Y ASESOR
 * ========================================================= */

function tec_objetivos_orientativos(): array
{
    return [
        'B1' => 35.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 18.0,
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

function tec_clasificar_nivel(float $actual, float $objetivo): string
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

function tec_detectar_perfil(array $resultado): string
{
    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];
    $total12 = (float)$resultado['totales']['total_b1_b2'];
    $total = (float)$resultado['totales']['total_final'];

    if ($b1 >= 35 && $b2 < 10) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }

    if ($b2 >= 15 && $b1 < 25) {
        return 'Perfil docente razonable con investigación insuficiente';
    }

    if ($b1 >= 30 && $b2 >= 12 && $total12 >= 50 && $total >= 55) {
        return 'Perfil equilibrado y competitivo para acreditación técnica';
    }

    if ($b1 >= 25 && (float)$resultado['bloque_1']['1D'] < 1.0) {
        return 'Perfil técnico-investigador con transferencia débil';
    }

    if ($b1 < 20 && $b2 < 10) {
        return 'Perfil aún inmaduro para acreditación en rama técnica';
    }

    if (($b3 + $b4) >= 4.0 && $total12 < 50) {
        return 'Perfil con méritos complementarios aceptables, pero núcleo B1+B2 insuficiente';
    }

    return 'Perfil mixto con fortalezas parciales y necesidad de refuerzo estratégico';
}

function tec_contar_publicaciones_impacto(array $publicaciones): array
{
    $out = [
        'T1' => 0,
        'T2' => 0,
        'T3' => 0,
        'CORE_A' => 0,
        'PATENTES' => 0,
        'OTRAS' => 0,
    ];

    foreach ($publicaciones as $pub) {
        if (!is_array($pub)) {
            continue;
        }

        $esValida = $pub['es_valida'] ?? $pub['es_valido'] ?? 1;
        if ((string)$esValida === '0') {
            continue;
        }

        $tipo = tec_lower(tec_str($pub['tipo'] ?? 'articulo'));
        $tipoIndice = strtoupper(tec_str($pub['tipo_indice'] ?? 'OTRO'));
        $tercil = strtoupper(tec_str($pub['tercil'] ?? ''));
        $subtipo = strtoupper(tec_str($pub['subtipo_indice'] ?? ''));

        if ($tipo === 'patente') {
            $out['PATENTES']++;
            continue;
        }

        if ($tipoIndice === 'JCR' && isset($out[$tercil])) {
            $out[$tercil]++;
            continue;
        }

        if ($tipoIndice === 'CORE' && in_array($subtipo, ['A', 'A+'], true)) {
            $out['CORE_A']++;
            continue;
        }

        if ($tipoIndice === 'CSIE' && $subtipo === 'CLASE_1') {
            $out['T3']++;
            continue;
        }

        if (in_array($tipoIndice, ['RESH', 'AVERY', 'RIBA', 'ARTS_HUMANITIES'], true)) {
            $out['T3']++;
            continue;
        }

        $out['OTRAS']++;
    }

    return $out;
}

function tec_generar_diagnostico(array $datos, array $resultado): array
{
    $obj = tec_objetivos_orientativos();

    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $b3 = (float)$resultado['bloque_3']['B3'];
    $b4 = (float)$resultado['bloque_4']['B4'];
    $total12 = (float)$resultado['totales']['total_b1_b2'];
    $total = (float)$resultado['totales']['total_final'];

    $deficit1 = max(0.0, 50.0 - $total12);
    $deficit2 = max(0.0, 55.0 - $total);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ((float)$resultado['bloque_1']['1A'] >= $obj['1A']) {
        $fortalezas[] = 'Producción científica principal competitiva en 1A.';
    } else {
        $debilidades[] = 'La producción científica principal (1A) aún necesita refuerzo.';
    }

    if ((float)$resultado['bloque_2']['2A'] >= $obj['2A']) {
        $fortalezas[] = 'La docencia universitaria tiene un volumen razonable o fuerte.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable (2A) es insuficiente.';
    }

    if ((float)$resultado['bloque_1']['1C'] >= $obj['1C']) {
        $fortalezas[] = 'Existe peso razonable en proyectos y contratos.';
    } else {
        $debilidades[] = 'Conviene reforzar proyectos competitivos o contratos certificados.';
    }

    if ((float)$resultado['bloque_1']['1D'] < $obj['1D']) {
        $alertas[] = 'La transferencia es baja para un perfil técnico.';
    }

    if ((float)$resultado['bloque_2']['2B'] <= 0.0) {
        $alertas[] = 'No consta evaluación docente relevante.';
    }

    if ($deficit1 > 0) {
        $alertas[] = 'No se cumple la regla principal B1 + B2 ≥ 50.';
    }

    if ($deficit2 > 0) {
        $alertas[] = 'No se cumple la regla total ≥ 55.';
    }

    return [
        'version' => 'conservadora_tecnicas_v2_estilo_experimentales',
        'perfil_detectado' => tec_detectar_perfil($resultado),
        'reglas' => [
            [
                'nombre' => 'Regla principal B1 + B2 ≥ 50',
                'valor_actual' => tec_round($total12),
                'objetivo' => 50.0,
                'deficit' => tec_round($deficit1),
                'cumple' => $resultado['decision']['cumple_regla_1'],
            ],
            [
                'nombre' => 'Regla total final ≥ 55',
                'valor_actual' => tec_round($total),
                'objetivo' => 55.0,
                'deficit' => tec_round($deficit2),
                'cumple' => $resultado['decision']['cumple_regla_2'],
            ],
        ],
        'bloques' => [
            'B1' => [
                'actual' => tec_round($b1),
                'objetivo_orientativo' => $obj['B1'],
                'deficit' => tec_round(max(0.0, $obj['B1'] - $b1)),
                'nivel' => tec_clasificar_nivel($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => tec_round($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => tec_round(max(0.0, $obj['B2'] - $b2)),
                'nivel' => tec_clasificar_nivel($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => tec_round($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => tec_round(max(0.0, $obj['B3'] - $b3)),
                'nivel' => tec_clasificar_nivel($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => tec_round($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => tec_round(max(0.0, $obj['B4'] - $b4)),
                'nivel' => tec_clasificar_nivel($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones_impacto' => tec_contar_publicaciones_impacto(tec_list(tec_list($datos, 'bloque_1'), 'publicaciones')),
            'num_proyectos_validos' => tec_count_valid(tec_list(tec_list($datos, 'bloque_1'), 'proyectos')),
            'num_transferencia_valida' => tec_count_valid(tec_list(tec_list($datos, 'bloque_1'), 'transferencia')),
            'num_tesis' => tec_count_valid(tec_list(tec_list($datos, 'bloque_1'), 'tesis_dirigidas')),
            'num_docencia_items' => tec_count_valid(tec_list(tec_list($datos, 'bloque_2'), 'docencia_universitaria')),
            'num_eval_docente' => tec_count_valid(tec_list(tec_list($datos, 'bloque_2'), 'evaluacion_docente')),
            'num_formacion_docente' => tec_count_valid(tec_list(tec_list($datos, 'bloque_2'), 'formacion_docente')),
            'num_material_docente' => tec_count_valid(tec_list(tec_list($datos, 'bloque_2'), 'material_docente')),
            'num_exp_profesional' => tec_count_valid(tec_list(tec_list($datos, 'bloque_3'), 'experiencia_profesional')),
            'num_otros_b4' => tec_count_valid(is_array(tec_list($datos, 'bloque_4')) ? tec_list($datos, 'bloque_4') : []),
        ],
        'fortalezas' => $fortalezas,
        'debilidades' => $debilidades,
        'alertas' => $alertas,
    ];
}

function tec_generar_asesor(array $resultado): array
{
    $acciones = [];

    $total12 = (float)$resultado['totales']['total_b1_b2'];
    $total = (float)$resultado['totales']['total_final'];

    $p1a = (float)$resultado['bloque_1']['1A'];
    $p1c = (float)$resultado['bloque_1']['1C'];
    $p1d = (float)$resultado['bloque_1']['1D'];
    $p2a = (float)$resultado['bloque_2']['2A'];
    $p2b = (float)$resultado['bloque_2']['2B'];
    $p2d = (float)$resultado['bloque_2']['2D'];

    if ($total12 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar docencia universitaria acreditable',
            'detalle' => 'El cuello de botella parece estar en 2A. Conviene consolidar docencia reglada con volumen real y TFG/TFM dirigidos.',
            'impacto_estimado' => '≈ 2 a 4 puntos por docencia adicional bien acreditada.',
        ];
    }

    if ($total12 < 50.0 && $p1a < 18.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Subir el peso de publicaciones principales',
            'detalle' => 'El núcleo 1A sigue corto. En Técnicas conviene priorizar JCR T1/T2 con buena posición de autoría y coautoría no masiva.',
            'impacto_estimado' => '≈ 2 a 4.5 puntos por aportación fuerte.',
        ];
    }

    if ($p1c < 4.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Aumentar proyectos competitivos certificados',
            'detalle' => 'Los proyectos nacionales o internacionales como IP o co-IP mejoran mucho la consistencia del expediente.',
            'impacto_estimado' => '≈ 1 a 4 puntos por proyecto relevante.',
        ];
    }

    if ($p1d < 2.0) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Reforzar transferencia',
            'detalle' => 'En Técnicas suma aportar software en explotación, contratos empresa o patente nacional bien justificada.',
            'impacto_estimado' => '≈ 1 a 2 puntos por mérito claro.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Aportar evaluación docente formal',
            'detalle' => 'DOCENTIA o informes institucionales favorables ayudan a cerrar debilidades del bloque 2.',
            'impacto_estimado' => '≈ 0.8 a 1.8 puntos.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Añadir innovación o material docente',
            'detalle' => 'Material docente con ISBN, manuales o proyectos de innovación docente redondean el bloque 2.',
            'impacto_estimado' => '≈ 0.7 a 2 puntos.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => ($a['prioridad'] <=> $b['prioridad']));

    if ($acciones === []) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Expediente equilibrado',
            'detalle' => 'No se aprecian debilidades severas. Conviene seguir consolidando publicaciones principales y estabilidad docente.',
            'impacto_estimado' => 'Impacto incremental.',
        ];
    }

    $sim1 = [
        'escenario' => 'Añadir una publicación fuerte en 1A',
        'efecto_estimado' => '+3.5 puntos aprox.',
        'nuevo_b1_b2_aprox' => tec_round(min(90.0, $total12 + 3.5)),
        'nuevo_total_aprox' => tec_round(min(100.0, $total + 3.5)),
    ];

    $sim2 = [
        'escenario' => 'Añadir un proyecto competitivo relevante',
        'efecto_estimado' => '+2.5 puntos aprox.',
        'nuevo_b1_b2_aprox' => tec_round(min(90.0, $total12 + 2.5)),
        'nuevo_total_aprox' => tec_round(min(100.0, $total + 2.5)),
    ];

    $sim3 = [
        'escenario' => 'Consolidar docencia y evaluación docente',
        'efecto_estimado' => '+3 puntos aprox.',
        'nuevo_b1_b2_aprox' => tec_round(min(90.0, $total12 + 3.0)),
        'nuevo_total_aprox' => tec_round(min(100.0, $total + 3.0)),
    ];

    return [
        'resumen' => 'Asesor orientativo para identificar qué palancas pueden mejorar antes el expediente de Técnicas.',
        'acciones' => array_values($acciones),
        'simulaciones' => [$sim1, $sim2, $sim3],
    ];
}

/* =========================================================
 * FUNCIÓN PRINCIPAL
 * ========================================================= */

function evaluar_expediente(array $datos): array
{
    $bloque1 = tec_list($datos, 'bloque_1');
    $bloque2 = tec_list($datos, 'bloque_2');
    $bloque3 = tec_list($datos, 'bloque_3');
    $bloque4 = tec_list($datos, 'bloque_4');

    $publicaciones = tec_list($bloque1, 'publicaciones');
    $libros = tec_list($bloque1, 'libros');
    $proyectos = tec_list($bloque1, 'proyectos');
    $transferencia = tec_list($bloque1, 'transferencia');
    $tesis = tec_list($bloque1, 'tesis_dirigidas');
    $congresos = tec_list($bloque1, 'congresos');
    $otrosInv = tec_list($bloque1, 'otros_meritos_investigacion');

    $docencia = tec_list($bloque2, 'docencia_universitaria');
    $evaluacion = tec_list($bloque2, 'evaluacion_docente');
    $formacionDoc = tec_list($bloque2, 'formacion_docente');
    $materialDoc = tec_list($bloque2, 'material_docente');

    $formacion = tec_list($bloque3, 'formacion_academica');
    $expProfesional = tec_list($bloque3, 'experiencia_profesional');

    $p1a = calcular_1a_tecnicas($publicaciones);
    $p1b = calcular_1b_tecnicas($libros);
    $p1c = calcular_1c_tecnicas($proyectos);
    $p1d = calcular_1d_tecnicas($transferencia);
    $p1e = calcular_1e_tecnicas($tesis);
    $p1f = calcular_1f_tecnicas($congresos);
    $p1g = calcular_1g_tecnicas($otrosInv);
    $B1 = tec_round(tec_clamp($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 0.0, 60.0));

    $p2a = calcular_2a_tecnicas($docencia);
    $p2b = calcular_2b_tecnicas($evaluacion, $docencia);
    $p2c = calcular_2c_tecnicas($formacionDoc);
    $p2d = calcular_2d_tecnicas($materialDoc);
    $B2 = tec_round(tec_clamp($p2a + $p2b + $p2c + $p2d, 0.0, 30.0));

    $p3a = calcular_3a_tecnicas($formacion);
    $p3b = calcular_3b_tecnicas($expProfesional);
    $B3 = tec_round(tec_clamp($p3a + $p3b, 0.0, 8.0));

    $p4 = calcular_4_tecnicas(is_array($bloque4) ? $bloque4 : []);
    $B4 = $p4;

    $totalB1B2 = tec_round($B1 + $B2);
    $totalFinal = tec_round($B1 + $B2 + $B3 + $B4);

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

    $resultado['diagnostico'] = tec_generar_diagnostico($datos, $resultado);
    $resultado['asesor'] = tec_generar_asesor($resultado);

    return $resultado;
}