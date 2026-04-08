<?php
declare(strict_types=1);

/**
 * EVALUADOR ANECA - EXPERIMENTALES (PCD/PUP)
 *
 * Distribución orientativa aplicada:
 * 1A = 35
 * 1B = 7
 * 1C = 7
 * 1D = 4
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
    return in_array((string)$value, ['1', 'true', 'on', 'si', 'sí'], true);
}

function exp_round(float $value): float
{
    return round($value, 2);
}

function exp_clamp(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function exp_list(array $data, string $key): array
{
    $value = $data[$key] ?? [];
    return is_array($value) ? $value : [];
}

function exp_count_valid(array $items, string $field = 'es_valido'): int
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
 * FACTORES PUBLICACIONES
 * ========================================================= */

function exp_factor_posicion_autor(string $posicion, bool $ordenAlfabetico = false): float
{
    if ($ordenAlfabetico) {
        return 1.00;
    }

    return match (mb_strtolower(trim($posicion))) {
        'autor_unico' => 1.20,
        'primero' => 1.15,
        'ultimo' => 1.10,
        'correspondencia' => 1.10,
        'intermedio' => 1.00,
        'secundario' => 0.85,
        default => 1.00,
    };
}

function exp_factor_coautoria(int $numeroAutores): float
{
    $n = max(1, $numeroAutores);

    return match (true) {
        $n <= 2 => 1.10,
        $n <= 4 => 1.00,
        $n <= 6 => 0.92,
        $n <= 10 => 0.82,
        $n <= 20 => 0.70,
        default => 0.55,
    };
}

function exp_factor_citas(int $citas, int $anios): float
{
    $c = max(0, $citas);
    $a = max(0, $anios);

    if ($a < 2 && $c < 5) {
        return 1.00;
    }

    return match (true) {
        $c <= 1 => 0.90,
        $c <= 5 => 0.97,
        $c <= 15 => 1.00,
        $c <= 40 => 1.08,
        default => 1.15,
    };
}

function exp_base_publicacion_1a(array $pub): float
{
    $tipoIndice = strtoupper(exp_str($pub['tipo_indice'] ?? 'JCR'));
    $tercil = strtoupper(exp_str($pub['tercil'] ?? ''));

    if ($tercil === 'EXCELENTE' || $tipoIndice === 'MULTIDISCIPLINAR') {
        return 9.0;
    }

    return match ($tercil) {
        'T1' => 7.0,
        'T2' => 4.5,
        'T3' => 2.7,
        default => 1.0,
    };
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

    $base = exp_base_publicacion_1a($pub);
    if ($base <= 0.0) {
        return 0.0;
    }

    $ordenAlfabetico = exp_bool($pub['orden_alfabetico'] ?? false);
    $p = $base
        * exp_factor_posicion_autor(exp_str($pub['posicion_autor'] ?? 'intermedio'), $ordenAlfabetico)
        * exp_factor_coautoria(exp_to_int($pub['numero_autores'] ?? 1, 1))
        * exp_factor_citas(
            exp_to_int($pub['citas'] ?? 0, 0),
            exp_to_int($pub['anios_desde_publicacion'] ?? 3, 3)
        );

    if (exp_bool($pub['es_area_matematicas'] ?? false)) {
        $p *= 1.20;
    }

    return exp_round($p);
}

function calcular_1a_experimentales(array $publicaciones): float
{
    $total = 0.0;

    foreach ($publicaciones as $pub) {
        $total += exp_puntuar_item_1a($pub);
    }

    return exp_round(exp_clamp($total, 0.0, 35.0));
}

/* =========================================================
 * 1B LIBROS Y CAPÍTULOS
 * ========================================================= */

function exp_base_editorial_1b(string $nivel): float
{
    return match (mb_strtolower(trim($nivel))) {
        'internacional' => 3.0,
        'nacional' => 1.9,
        'menor' => 0.9,
        default => 0.7,
    };
}

function exp_puntuar_item_1b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'libro'));
    $nivel = exp_str($item['nivel_editorial'] ?? 'nacional');
    $base = exp_base_editorial_1b($nivel);

    $p = match ($tipo) {
        'libro' => $base * 1.20,
        'capitulo' => $base * 0.85,
        'resumen_extendido' => 0.70,
        'edicion_colectiva' => 0.90,
        'cartografia_tematica' => 1.60,
        default => 0.50,
    };

    $especialidad = mb_strtolower(exp_str($item['especialidad'] ?? ''));
    if (in_array($especialidad, ['botanica', 'zoologia'], true) && exp_bool($item['complejidad_alta'] ?? false)) {
        $p = max($p, 3.5);
    }
    if ($especialidad === 'tierra' && $tipo === 'cartografia_tematica') {
        $p = min(max($p, 1.8), 2.7);
    }

    return exp_round($p);
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
 * 1C PROYECTOS Y CONTRATOS
 * ========================================================= */

function exp_base_proyecto_1c(string $tipo): float
{
    return match (mb_strtolower(trim($tipo))) {
        'europeo' => 4.0,
        'nacional' => 3.2,
        'autonomico' => 2.0,
        'otro_competitivo' => 1.4,
        'art83_conocimiento' => 1.2,
        default => 0.0,
    };
}

function exp_factor_rol_proyecto_1c(string $rol): float
{
    return match (mb_strtolower(trim($rol))) {
        'ip' => 1.50,
        'coip' => 1.30,
        'investigador' => 1.00,
        default => 1.00,
    };
}

function exp_factor_duracion_1c(float $anios): float
{
    return match (true) {
        $anios >= 4.0 => 1.20,
        $anios >= 2.0 => 1.00,
        $anios > 0.0 => 0.80,
        default => 0.0,
    };
}

function exp_puntuar_item_1c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }
    if (!exp_bool($item['esta_certificado'] ?? true, true)) {
        return 0.0;
    }

    $base = exp_base_proyecto_1c(exp_str($item['tipo_proyecto'] ?? 'otro_competitivo'));
    if ($base <= 0.0) {
        return 0.0;
    }

    $p = $base
        * exp_factor_rol_proyecto_1c(exp_str($item['rol'] ?? 'investigador'))
        * exp_factor_duracion_1c(exp_to_float($item['anios_duracion'] ?? 0, 0));

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

    return match (mb_strtolower(exp_str($item['tipo'] ?? 'propiedad_intelectual'))) {
        'patente_obtenida_internacional' => 3.5,
        'patente_solicitada_internacional' => 2.7,
        'patente_obtenida_nacional' => 2.3,
        'patente_solicitada_nacional' => 1.7,
        'propiedad_intelectual' => 1.2,
        'art83_sin_conocimiento' => 1.0,
        default => 0.4,
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

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'dirigida'));
    $aprobada = exp_bool($item['proyecto_aprobado'] ?? true, true);

    if ($tipo === 'en_direccion' && !$aprobada) {
        return 0.0;
    }

    $base = match ($tipo) {
        'dirigida' => 2.0,
        'en_direccion' => 1.2,
        default => 0.0,
    };

    if (exp_bool($item['doctorado_europeo'] ?? false)) {
        $base += 0.6;
    }
    if (exp_bool($item['mencion_calidad'] ?? false)) {
        $base += 0.4;
    }

    $codirectores = max(0, exp_to_int($item['numero_codirectores'] ?? 0, 0));
    if ($codirectores > 0) {
        $base *= max(0.45, 1 - (0.15 * $codirectores));
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

    $ambito = mb_strtolower(exp_str($item['ambito'] ?? 'nacional'));
    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'comunicacion_oral'));

    $base = match ($ambito) {
        'internacional' => 0.90,
        'nacional' => 0.55,
        default => 0.20,
    };

    $factor = match ($tipo) {
        'ponencia_invitada' => 1.25,
        'comunicacion_oral' => 1.00,
        'poster' => 0.70,
        default => 0.70,
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
        if ($idEvento === '') {
            $total += exp_puntuar_item_1f($item);
            continue;
        }

        $p = exp_puntuar_item_1f($item);
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
 * 1G OTROS MÉRITOS INVESTIGACIÓN
 * ========================================================= */

function exp_puntuar_item_1g(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (mb_strtolower(exp_str($item['tipo'] ?? 'otro'))) {
        'revision_jcr' => 0.30,
        'evaluacion_proyectos' => 0.40,
        'otro' => 0.20,
        default => 0.10,
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
 * BLOQUE 2 DOCENCIA
 * ========================================================= */

function exp_puntuar_item_2a(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $horas = exp_to_float($item['horas'] ?? 0, 0);
    $tfg = exp_to_int($item['tfg'] ?? 0, 0);
    $tfm = exp_to_int($item['tfm'] ?? 0, 0);

    $baseHoras = match (true) {
        $horas >= 450 => 14.0,
        $horas >= 320 => 11.0,
        $horas >= 220 => 8.0,
        $horas >= 120 => 5.0,
        $horas >= 40 => 2.5,
        default => 0.0,
    };

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'grado'));
    if ($tipo === 'master') {
        $baseHoras *= 1.05;
    } elseif ($tipo === 'titulo_propio') {
        $baseHoras *= 0.80;
    }

    $etapa = mb_strtolower(exp_str($item['etapa'] ?? 'estable'));
    if ($etapa === 'predoctoral') {
        $baseHoras *= 0.95;
    } elseif ($etapa === 'posdoctoral') {
        $baseHoras *= 1.00;
    }

    $extras = min(3.0, ($tfg * 0.20) + ($tfm * 0.35));

    return exp_round($baseHoras + $extras);
}

function calcular_2a_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_2a($item);
    }
    return exp_round(exp_clamp($total, 0.0, 17.0));
}

function exp_puntuar_item_2b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $base = match (mb_strtolower(exp_str($item['resultado'] ?? 'aceptable'))) {
        'excelente' => 1.8,
        'muy_favorable' => 1.4,
        'favorable' => 1.0,
        'aceptable' => 0.5,
        default => 0.0,
    };

    if (!exp_bool($item['cobertura_amplia'] ?? false, false)) {
        $base *= 0.75;
    }

    return exp_round($base);
}

function calcular_2b_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_2b($item);
    }
    return exp_round(exp_clamp($total, 0.0, 3.0));
}

function exp_puntuar_item_2c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }
    if (!exp_bool($item['relacion_docente'] ?? false, false)) {
        return 0.0;
    }

    $horas = exp_to_float($item['horas'] ?? 0, 0);

    $base = match (true) {
        $horas >= 40 => 1.2,
        $horas >= 20 => 0.8,
        $horas > 0 => 0.4,
        default => 0.0,
    };

    if (mb_strtolower(exp_str($item['rol'] ?? 'asistente')) === 'ponente') {
        $base *= 1.25;
    }

    return exp_round($base);
}

function calcular_2c_experimentales(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += exp_puntuar_item_2c($item);
    }
    return exp_round(exp_clamp($total, 0.0, 3.0));
}

function exp_puntuar_item_2d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'material_original'));
    $nivel = mb_strtolower(exp_str($item['nivel_editorial'] ?? 'nacional'));

    $baseEditorial = match ($nivel) {
        'internacional' => 1.5,
        'nacional' => 1.1,
        'menor' => 0.7,
        default => 0.6,
    };

    return exp_round(match ($tipo) {
        'libro_docente' => $baseEditorial * 1.4,
        'capitulo_docente' => $baseEditorial * 1.0,
        'innovacion_docente' => 1.5,
        'material_original' => 1.1,
        default => 0.4,
    });
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
 * BLOQUE 3
 * ========================================================= */

function exp_puntuar_item_3a(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $tipo = mb_strtolower(exp_str($item['tipo'] ?? 'curso_especializacion'));
    $alta = exp_bool($item['alta_competitividad'] ?? false);

    return match ($tipo) {
        'doctorado_internacional' => 2.0,
        'mencion_calidad' => 1.2,
        'beca_predoc_fpu' => 1.8,
        'beca_predoc_fpi' => 1.7,
        'beca_predoc_autonomica' => 1.2,
        'beca_predoc_universidad' => 0.9,
        'premio_extra_doctorado' => 1.5,
        'beca_posdoc' => $alta ? 1.8 : 1.2,
        'estancia' => 1.0,
        'curso_especializacion' => 0.4,
        default => 0.2,
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

function exp_puntuar_item_3b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }
    if (!exp_bool($item['justificada'] ?? false, false)) {
        return 0.0;
    }
    if (exp_bool($item['no_valorable'] ?? false, false)) {
        return 0.0;
    }

    $anios = exp_to_float($item['anios'] ?? 0, 0);

    $base = match (true) {
        $anios >= 5 => 1.5,
        $anios >= 3 => 1.1,
        $anios >= 1 => 0.7,
        $anios > 0 => 0.3,
        default => 0.0,
    };

    $factor = match (mb_strtolower(exp_str($item['relacion'] ?? 'media'))) {
        'alta' => 1.20,
        'media' => 1.00,
        'baja' => 0.70,
        default => 1.00,
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
 * BLOQUE 4
 * ========================================================= */

function exp_puntuar_item_4(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }
    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (mb_strtolower(exp_str($item['tipo'] ?? 'otro'))) {
        'gestion' => 0.8,
        'divulgacion' => 0.6,
        'asesor_equipos' => 0.7,
        'beca_colaboracion' => 0.8,
        'premio_extra_grado' => 1.0,
        'otro' => 0.3,
        default => 0.2,
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
 * DIAGNÓSTICO / ASESOR
 * ========================================================= */

function exp_objetivos_orientativos(): array
{
    return [
        'B1' => 35.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 18.0,
        '1B' => 2.0,
        '1C' => 3.0,
        '1D' => 1.0,
        '1E' => 1.0,
        '1F' => 0.6,
        '1G' => 0.2,

        '2A' => 12.0,
        '2B' => 1.2,
        '2C' => 0.8,
        '2D' => 1.6,

        '3A' => 2.0,
        '3B' => 0.8,

        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function exp_clasificar_nivel(float $actual, float $objetivo): string
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

function exp_detectar_perfil(array $resultado): string
{
    $b1 = (float)$resultado['bloque_1']['B1'];
    $b2 = (float)$resultado['bloque_2']['B2'];
    $total12 = (float)$resultado['totales']['total_b1_b2'];
    $total = (float)$resultado['totales']['total_final'];

    if ($b1 >= 35 && $b2 < 10) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }
    if ($b2 >= 15 && $b1 < 25) {
        return 'Perfil docente razonable con investigación insuficiente';
    }
    if ($b1 >= 30 && $b2 >= 12 && $total12 >= 50 && $total >= 55) {
        return 'Perfil equilibrado y competitivo para acreditación en Experimentales';
    }
    if ($b1 < 20 && $b2 < 10) {
        return 'Perfil todavía inmaduro para acreditación en Experimentales';
    }

    return 'Perfil mixto con fortalezas parciales y necesidad de refuerzo estratégico';
}

function exp_contar_publicaciones(array $publicaciones): array
{
    $out = [
        'EXCELENTE' => 0,
        'T1' => 0,
        'T2' => 0,
        'T3' => 0,
        'MATEMATICAS' => 0,
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

        $tercil = strtoupper(exp_str($pub['tercil'] ?? ''));
        if (isset($out[$tercil])) {
            $out[$tercil]++;
        } else {
            $out['OTRAS']++;
        }

        if (exp_bool($pub['es_area_matematicas'] ?? false)) {
            $out['MATEMATICAS']++;
        }
    }

    return $out;
}

function exp_generar_diagnostico(array $datos, array $resultado): array
{
    $obj = exp_objetivos_orientativos();

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
        $fortalezas[] = 'La producción científica principal tiene un nivel razonable o fuerte.';
    } else {
        $debilidades[] = 'La producción científica principal (1A) necesita refuerzo.';
    }

    if ((float)$resultado['bloque_2']['2A'] >= $obj['2A']) {
        $fortalezas[] = 'La docencia universitaria tiene volumen suficiente o próximo al esperado.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable (2A) es escasa.';
    }

    if ((float)$resultado['bloque_1']['1C'] < $obj['1C']) {
        $debilidades[] = 'Conviene reforzar proyectos competitivos relevantes.';
    }

    if ((float)$resultado['bloque_1']['1D'] < $obj['1D']) {
        $alertas[] = 'La transferencia tecnológica es baja o inexistente.';
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
        'perfil_detectado' => exp_detectar_perfil($resultado),
        'reglas' => [
            [
                'nombre' => 'Regla principal B1 + B2 ≥ 50',
                'valor_actual' => exp_round($total12),
                'objetivo' => 50.0,
                'deficit' => exp_round($deficit1),
                'cumple' => $resultado['decision']['cumple_regla_1'],
            ],
            [
                'nombre' => 'Regla total final ≥ 55',
                'valor_actual' => exp_round($total),
                'objetivo' => 55.0,
                'deficit' => exp_round($deficit2),
                'cumple' => $resultado['decision']['cumple_regla_2'],
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
            'publicaciones' => exp_contar_publicaciones(exp_list(exp_list($datos, 'bloque_1'), 'publicaciones')),
            'num_proyectos_validos' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'proyectos')),
            'num_transferencia_valida' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'transferencia')),
            'num_tesis' => exp_count_valid(exp_list(exp_list($datos, 'bloque_1'), 'tesis_dirigidas')),
            'num_docencia_items' => exp_count_valid(exp_list(exp_list($datos, 'bloque_2'), 'docencia_universitaria')),
        ],
        'fortalezas' => $fortalezas,
        'debilidades' => $debilidades,
        'alertas' => $alertas,
    ];
}

function exp_generar_asesor(array $resultado): array
{
    $acciones = [];

    $total12 = (float)$resultado['totales']['total_b1_b2'];
    $total = (float)$resultado['totales']['total_final'];

    $p1a = (float)$resultado['bloque_1']['1A'];
    $p1c = (float)$resultado['bloque_1']['1C'];
    $p2a = (float)$resultado['bloque_2']['2A'];
    $p2b = (float)$resultado['bloque_2']['2B'];
    $p2d = (float)$resultado['bloque_2']['2D'];

    if ($total12 < 50.0 && $p1a < 18.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar publicaciones principales',
            'detalle' => 'En Experimentales, el retorno más claro suele venir de publicaciones excelentes o T1/T2 bien posicionadas.',
            'impacto_estimado' => '≈ 3 a 8 puntos por publicación fuerte.',
        ];
    }

    if ($total12 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Consolidar docencia universitaria',
            'detalle' => 'Aumentar horas acreditables, TFG/TFM y estabilidad docente mejora mucho 2A.',
            'impacto_estimado' => '≈ 2 a 5 puntos por tramo docente sólido.',
        ];
    }

    if ($p1c < 3.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Aumentar peso en proyectos competitivos',
            'detalle' => 'UE, Plan Nacional e IP/coIP son especialmente rentables para el expediente.',
            'impacto_estimado' => '≈ 2 a 4 puntos por mérito fuerte.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Aportar evaluación docente formal',
            'detalle' => 'La evaluación de calidad docente ayuda a cerrar carencias del bloque 2.',
            'impacto_estimado' => '≈ 1 a 1.5 puntos.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Añadir material o innovación docente',
            'detalle' => 'Libros/capítulos docentes, materiales originales o innovación docente ayudan a redondear 2D.',
            'impacto_estimado' => '≈ 1 a 2 puntos.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => ($a['prioridad'] <=> $b['prioridad']));

    if ($acciones === []) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Expediente equilibrado',
            'detalle' => 'No se aprecian debilidades severas. Conviene seguir consolidando publicaciones y estabilidad docente.',
            'impacto_estimado' => 'Impacto incremental.',
        ];
    }

    return [
        'resumen' => 'Asesor orientativo para identificar qué palancas pueden mejorar antes el expediente de Experimentales.',
        'acciones' => array_values($acciones),
        'simulaciones' => [
            [
                'escenario' => 'Añadir una publicación principal fuerte',
                'efecto_estimado' => '+5 puntos aprox.',
                'nuevo_b1_b2_aprox' => exp_round(min(90.0, $total12 + 5.0)),
                'nuevo_total_aprox' => exp_round(min(100.0, $total + 5.0)),
            ],
            [
                'escenario' => 'Añadir un proyecto competitivo relevante',
                'efecto_estimado' => '+3 puntos aprox.',
                'nuevo_b1_b2_aprox' => exp_round(min(90.0, $total12 + 3.0)),
                'nuevo_total_aprox' => exp_round(min(100.0, $total + 3.0)),
            ],
            [
                'escenario' => 'Mejorar docencia + evaluación docente',
                'efecto_estimado' => '+4 puntos aprox.',
                'nuevo_b1_b2_aprox' => exp_round(min(90.0, $total12 + 4.0)),
                'nuevo_total_aprox' => exp_round(min(100.0, $total + 4.0)),
            ],
        ],
    ];
}

/* =========================================================
 * FUNCIÓN PRINCIPAL
 * ========================================================= */

function evaluar_expediente(array $datos): array
{
    $bloque1 = exp_list($datos, 'bloque_1');
    $bloque2 = exp_list($datos, 'bloque_2');
    $bloque3 = exp_list($datos, 'bloque_3');
    $bloque4 = exp_list($datos, 'bloque_4');

    $p1a = calcular_1a_experimentales(exp_list($bloque1, 'publicaciones'));
    $p1b = calcular_1b_experimentales(exp_list($bloque1, 'libros'));
    $p1c = calcular_1c_experimentales(exp_list($bloque1, 'proyectos'));
    $p1d = calcular_1d_experimentales(exp_list($bloque1, 'transferencia'));
    $p1e = calcular_1e_experimentales(exp_list($bloque1, 'tesis_dirigidas'));
    $p1f = calcular_1f_experimentales(exp_list($bloque1, 'congresos'));
    $p1g = calcular_1g_experimentales(exp_list($bloque1, 'otros_meritos_investigacion'));
    $B1 = exp_round(exp_clamp($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 0.0, 60.0));

    $p2a = calcular_2a_experimentales(exp_list($bloque2, 'docencia_universitaria'));
    $p2b = calcular_2b_experimentales(exp_list($bloque2, 'evaluacion_docente'));
    $p2c = calcular_2c_experimentales(exp_list($bloque2, 'formacion_docente'));
    $p2d = calcular_2d_experimentales(exp_list($bloque2, 'material_docente'));
    $B2 = exp_round(exp_clamp($p2a + $p2b + $p2c + $p2d, 0.0, 30.0));

    $p3a = calcular_3a_experimentales(exp_list($bloque3, 'formacion_academica'));
    $p3b = calcular_3b_experimentales(exp_list($bloque3, 'experiencia_profesional'));
    $B3 = exp_round(exp_clamp($p3a + $p3b, 0.0, 8.0));

    $p4 = calcular_4_experimentales($bloque4);
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