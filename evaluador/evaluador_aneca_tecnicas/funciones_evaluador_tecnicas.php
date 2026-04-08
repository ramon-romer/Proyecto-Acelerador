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
    return in_array((string)$value, ['1', 'true', 'on', 'si', 'sí'], true);
}

function tec_round(float $value): float
{
    return round($value, 2);
}

function tec_clamp(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function tec_list(array $data, string $key): array
{
    $value = $data[$key] ?? [];
    return is_array($value) ? $value : [];
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
    return match (mb_strtolower(trim($afinidad))) {
        'total' => 1.20,
        'relacionada' => 1.00,
        'periferica' => 0.75,
        'ajena' => 0.40,
        default => 1.00,
    };
}

function tec_factor_posicion_autor(string $posicion): float
{
    return match (mb_strtolower(trim($posicion))) {
        'autor_unico' => 1.25,
        'primero' => 1.20,
        'ultimo' => 1.10,
        'correspondencia' => 1.10,
        'intermedio' => 1.00,
        'secundario' => 0.85,
        default => 1.00,
    };
}

function tec_factor_coautoria(int $numeroAutores): float
{
    $n = max(1, $numeroAutores);

    return match (true) {
        $n <= 1 => 1.20,
        $n <= 3 => 1.10,
        $n <= 5 => 1.00,
        $n <= 8 => 0.90,
        $n <= 12 => 0.75,
        default => 0.60,
    };
}

function tec_factor_citas(int $citas, int $anios): float
{
    $c = max(0, $citas);
    $a = max(0, $anios);

    if ($a < 2 && $c < 6) {
        return 1.00;
    }

    return match (true) {
        $c <= 1 => 0.85,
        $c <= 5 => 0.95,
        $c <= 15 => 1.00,
        $c <= 40 => 1.10,
        default => 1.20,
    };
}

/* =========================================================
 * 1A - PUBLICACIONES CIENTÍFICAS Y PATENTES
 * ========================================================= */

function tec_base_publicacion_1a(array $pub): float
{
    $tipo = mb_strtolower(tec_str($pub['tipo'] ?? 'articulo'));
    $tipoIndice = strtoupper(tec_str($pub['tipo_indice'] ?? 'OTRO'));
    $tercil = strtoupper(tec_str($pub['tercil'] ?? ''));
    $subtipo = strtoupper(tec_str($pub['subtipo_indice'] ?? ''));

    if ($tipo === 'patente') {
        return match ($subtipo) {
            'B1' => 8.0,
            'B2' => 6.0,
            default => 4.0,
        };
    }

    if ($tipoIndice === 'JCR') {
        return match ($tercil) {
            'T1' => 10.0,
            'T2' => 7.0,
            'T3' => 4.0,
            default => 1.5,
        };
    }

    if ($tipoIndice === 'CORE' && in_array($subtipo, ['A', 'A+'], true)) {
        return 4.0;
    }

    if ($tipoIndice === 'CSIE' && $subtipo === 'CLASE_1') {
        return 4.0;
    }

    if (in_array($tipoIndice, ['RESH', 'AVERY', 'RIBA', 'ARTS_HUMANITIES'], true)) {
        return 3.0;
    }

    if ($tipoIndice === 'PATENTE') {
        return match ($subtipo) {
            'B1' => 8.0,
            'B2' => 6.0,
            default => 4.0,
        };
    }

    return 0.75;
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

    $tipo = mb_strtolower(tec_str($pub['tipo'] ?? 'articulo'));

    if (!in_array($tipo, ['articulo', 'patente', 'software'], true)) {
        return 0.0;
    }

    $base = tec_base_publicacion_1a($pub);

    if ($tipo === 'patente') {
        $factor = 1.0;
        if (tec_bool($pub['liderazgo'] ?? false)) {
            $factor *= 1.15;
        }
        return tec_round($base * $factor);
    }

    if ($tipo === 'software') {
        $factor = 1.0;
        if (tec_bool($pub['liderazgo'] ?? false)) {
            $factor *= 1.10;
        }
        return tec_round(max(1.0, $base) * $factor);
    }

    $p = $base
        * tec_factor_afinidad(tec_str($pub['afinidad'] ?? 'relacionada'))
        * tec_factor_posicion_autor(tec_str($pub['posicion_autor'] ?? 'intermedio'))
        * tec_factor_coautoria(tec_to_int($pub['numero_autores'] ?? 1, 1))
        * tec_factor_citas(
            tec_to_int($pub['citas'] ?? 0, 0),
            tec_to_int($pub['anios_desde_publicacion'] ?? 3, 3)
        );

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

function tec_base_editorial_1b(string $nivel): float
{
    return match (mb_strtolower(trim($nivel))) {
        'prestigiosa' => 3.0,
        'secundaria' => 1.5,
        'baja' => 0.5,
        default => 0.5,
    };
}

function tec_factor_tipo_libro_1b(string $tipo): float
{
    return mb_strtolower(trim($tipo)) === 'libro' ? 1.10 : 0.70;
}

function tec_puntuar_item_1b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    if (!tec_bool($item['es_libro_investigacion'] ?? true, true)) {
        return 0.0;
    }

    if (tec_bool($item['es_autoedicion'] ?? false)) {
        return 0.0;
    }

    if (tec_bool($item['es_acta_congreso'] ?? false)) {
        return 0.0;
    }

    if (tec_bool($item['es_labor_edicion'] ?? false)) {
        return 0.0;
    }

    $base = tec_base_editorial_1b(tec_str($item['nivel_editorial'] ?? 'secundaria'))
        * tec_factor_tipo_libro_1b(tec_str($item['tipo'] ?? 'capitulo'))
        * tec_factor_afinidad(tec_str($item['afinidad'] ?? 'relacionada'))
        * tec_factor_posicion_autor(tec_str($item['posicion_autor'] ?? 'intermedio'));

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

function tec_base_proyecto_1c(string $tipo): float
{
    return match (mb_strtolower(trim($tipo))) {
        'internacional' => 5.0,
        'nacional' => 4.0,
        'autonomico' => 2.5,
        'universidad' => 1.5,
        'empresa' => 1.7,
        'desarrollo_industrial' => 1.8,
        'infraestructura' => 1.2,
        'red_tematica' => 0.0,
        default => 0.0,
    };
}

function tec_factor_rol_proyecto_1c(string $rol): float
{
    return match (mb_strtolower(trim($rol))) {
        'ip' => 1.50,
        'coip' => 1.30,
        'investigador' => 1.00,
        'participacion_menor' => 0.70,
        default => 1.00,
    };
}

function tec_factor_dedicacion_1c(string $dedicacion): float
{
    return match (mb_strtolower(trim($dedicacion))) {
        'completa' => 1.20,
        'parcial' => 1.00,
        'residual' => 0.80,
        default => 1.00,
    };
}

function tec_factor_duracion_1c(float $anios): float
{
    return match (true) {
        $anios >= 4.0 => 1.20,
        $anios >= 2.0 => 1.00,
        $anios > 0.0 => 0.80,
        default => 0.0,
    };
}

function tec_puntuar_item_1c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    if (!tec_bool($item['esta_certificado'] ?? true, true)) {
        return 0.0;
    }

    $base = tec_base_proyecto_1c(tec_str($item['tipo_proyecto'] ?? 'universidad'));

    if ($base <= 0.0) {
        return 0.0;
    }

    $p = $base
        * tec_factor_rol_proyecto_1c(tec_str($item['rol'] ?? 'investigador'))
        * tec_factor_dedicacion_1c(tec_str($item['dedicacion'] ?? 'parcial'))
        * tec_factor_duracion_1c(tec_to_float($item['anios_duracion'] ?? 0, 0));

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

function tec_base_transferencia_1d(string $tipo): float
{
    return match (mb_strtolower(trim($tipo))) {
        'patente_nacional' => 3.0,
        'contrato_empresa' => 2.0,
        'software_explotacion' => 2.0,
        'ebt' => 2.2,
        'otro' => 0.5,
        default => 0.0,
    };
}

function tec_puntuar_item_1d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $base = tec_base_transferencia_1d(tec_str($item['tipo'] ?? 'otro'));
    if ($base <= 0.0) {
        return 0.0;
    }

    $factor = 1.0;

    if (tec_bool($item['impacto_externo'] ?? false)) {
        $factor *= 1.20;
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

    $tipo = mb_strtolower(tec_str($item['tipo'] ?? 'codireccion'));

    $base = match ($tipo) {
        'direccion_principal', 'direccion_unica' => 2.0,
        'codireccion' => 1.2,
        default => 0.0,
    };

    if (tec_bool($item['calidad_especial'] ?? false)) {
        $base += 0.5;
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

    $ambito = mb_strtolower(tec_str($item['ambito'] ?? 'local'));
    $tipo = mb_strtolower(tec_str($item['tipo'] ?? 'poster'));

    $base = match ($ambito) {
        'internacional' => 1.0,
        'nacional' => 0.7,
        'autonomico', 'regional' => 0.4,
        'local' => 0.2,
        default => 0.2,
    };

    $factor = match ($tipo) {
        'ponencia_invitada' => 1.30,
        'comunicacion_oral' => 1.10,
        'poster' => 0.80,
        'organizacion' => 0.90,
        default => 0.80,
    };

    return tec_round($base * $factor);
}

function calcular_1f_tecnicas(array $items): float
{
    $total = 0.0;
    $conteoEvento = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((string)($item['es_valido'] ?? 1) === '0') {
            continue;
        }

        $idEvento = tec_str($item['id_evento'] ?? '');
        if ($idEvento === '') {
            $idEvento = uniqid('evento_', true);
        }

        $conteoEvento[$idEvento] = $conteoEvento[$idEvento] ?? 0;

        if ($conteoEvento[$idEvento] >= 2) {
            continue;
        }

        $total += tec_puntuar_item_1f($item);
        $conteoEvento[$idEvento]++;
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

    return match (mb_strtolower(tec_str($item['tipo'] ?? 'otro'))) {
        'grupo_investigacion' => 0.20,
        'comite_cientifico' => 0.40,
        'revision_revistas' => 0.30,
        'premio_investigacion' => 0.80,
        'otro' => 0.20,
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

function tec_puntuar_item_2a(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $horas = tec_to_float($item['horas'] ?? 0, 0);

    $base = match (true) {
        $horas >= 240 => 4.0,
        $horas >= 180 => 3.0,
        $horas >= 120 => 2.0,
        $horas >= 60 => 1.0,
        $horas > 0 => 0.5,
        default => 0.0,
    };

    $factorNivel = match (mb_strtolower(tec_str($item['nivel'] ?? 'grado'))) {
        'doctorado' => 1.15,
        'master' => 1.10,
        'grado' => 1.00,
        default => 1.00,
    };

    $factorResp = match (mb_strtolower(tec_str($item['responsabilidad'] ?? 'media'))) {
        'alta' => 1.20,
        'media' => 1.00,
        'baja' => 0.85,
        default => 1.00,
    };

    return tec_round($base * $factorNivel * $factorResp);
}

function calcular_2a_tecnicas(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += tec_puntuar_item_2a($item);
    }
    return tec_round(tec_clamp($total, 0.0, 17.0));
}

function tec_puntuar_item_2b(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $resultado = mb_strtolower(tec_str($item['resultado'] ?? 'aceptable'));
    $tipo = mb_strtolower(tec_str($item['tipo'] ?? 'encuestas'));

    $base = match ($resultado) {
        'excelente' => 1.5,
        'muy_favorable' => 1.2,
        'favorable', 'positiva' => 1.0,
        'aceptable' => 0.5,
        default => 0.0,
    };

    if ($tipo === 'docentia') {
        $base *= 1.10;
    }

    return tec_round($base);
}

function calcular_2b_tecnicas(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += tec_puntuar_item_2b($item);
    }
    return tec_round(tec_clamp($total, 0.0, 3.0));
}

function tec_puntuar_item_2c(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    $horas = tec_to_float($item['horas'] ?? 0, 0);

    $base = match (true) {
        $horas >= 100 => 1.5,
        $horas >= 50 => 1.0,
        $horas >= 20 => 0.6,
        $horas > 0 => 0.3,
        default => 0.0,
    };

    $factorRol = match (mb_strtolower(tec_str($item['rol'] ?? 'asistente'))) {
        'director' => 1.25,
        'ponente', 'docente' => 1.20,
        'asistente' => 1.00,
        default => 1.00,
    };

    return tec_round($base * $factorRol);
}

function calcular_2c_tecnicas(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += tec_puntuar_item_2c($item);
    }
    return tec_round(tec_clamp($total, 0.0, 3.0));
}

function tec_puntuar_item_2d(array $item): float
{
    if (!is_array($item)) {
        return 0.0;
    }

    if ((string)($item['es_valido'] ?? 1) === '0') {
        return 0.0;
    }

    return match (mb_strtolower(tec_str($item['tipo'] ?? 'otro'))) {
        'material_publicado' => 2.0,
        'proyecto_innovacion' => 1.5,
        'recurso_digital' => 1.2,
        'contribucion_eees' => 1.4,
        default => 0.4,
    };
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

    return match (mb_strtolower(tec_str($item['tipo'] ?? 'otro'))) {
        'tesis_doctoral' => 2.0,
        'premio_doctorado' => 1.6,
        'beca' => 1.5,
        'estancia' => 1.2,
        'master' => 0.8,
        default => 0.3,
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

    $anios = tec_to_float($item['anios'] ?? 0, 0);

    $base = match (true) {
        $anios >= 5 => 1.5,
        $anios >= 3 => 1.0,
        $anios >= 1 => 0.6,
        $anios > 0 => 0.3,
        default => 0.0,
    };

    $factorRelacion = match (mb_strtolower(tec_str($item['relacion'] ?? 'media'))) {
        'alta' => 1.20,
        'media' => 1.00,
        'baja' => 0.70,
        default => 1.00,
    };

    return tec_round($base * $factorRelacion);
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

    return match (mb_strtolower(tec_str($item['tipo'] ?? 'otro'))) {
        'gestion' => 0.8,
        'servicio_academico' => 0.5,
        'distincion' => 0.7,
        'sociedad_cientifica' => 0.6,
        default => 0.3,
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

        $tipo = mb_strtolower(tec_str($pub['tipo'] ?? 'articulo'));
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
            'detalle' => 'El cuello de botella parece estar en 2A. Conviene consolidar docencia reglada con responsabilidad media/alta.',
            'impacto_estimado' => '≈ 3 a 5 puntos por un curso docente sólido.',
        ];
    }

    if ($total12 < 50.0 && $p1a < 18.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Subir el peso de publicaciones principales',
            'detalle' => 'El núcleo 1A sigue corto. En Técnicas, el mejor retorno suele venir de publicaciones JCR T1/T2 bien posicionadas.',
            'impacto_estimado' => '≈ 4 a 10 puntos por aportación fuerte, según tercil y autoría.',
        ];
    }

    if ($p1c < 4.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Aumentar proyectos competitivos certificados',
            'detalle' => 'Los proyectos IP/nacionales/internacionales mejoran mucho la consistencia del expediente.',
            'impacto_estimado' => '≈ 2 a 6 puntos por proyecto relevante.',
        ];
    }

    if ($p1d < 2.0) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Reforzar transferencia',
            'detalle' => 'En Técnicas ayuda bastante aportar software en explotación, contratos empresa o patente nacional bien justificada.',
            'impacto_estimado' => '≈ 1.5 a 3 puntos por mérito claro de transferencia.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Aportar evaluación docente formal',
            'detalle' => 'DOCENTIA o informes institucionales favorables ayudan a cerrar debilidades del bloque 2.',
            'impacto_estimado' => '≈ 1 a 1.5 puntos.',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Añadir innovación/material docente',
            'detalle' => 'Material docente publicado, innovación o contribuciones al EEES pueden redondear el bloque 2.',
            'impacto_estimado' => '≈ 1 a 2 puntos.',
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
        'escenario' => 'Añadir una publicación potente en 1A',
        'efecto_estimado' => '+6 puntos aprox.',
        'nuevo_b1_b2_aprox' => tec_round(min(90.0, $total12 + 6.0)),
        'nuevo_total_aprox' => tec_round(min(100.0, $total + 6.0)),
    ];

    $sim2 = [
        'escenario' => 'Añadir un proyecto competitivo relevante',
        'efecto_estimado' => '+3 puntos aprox.',
        'nuevo_b1_b2_aprox' => tec_round(min(90.0, $total12 + 3.0)),
        'nuevo_total_aprox' => tec_round(min(100.0, $total + 3.0)),
    ];

    $sim3 = [
        'escenario' => 'Consolidar docencia + evaluación docente',
        'efecto_estimado' => '+4 puntos aprox.',
        'nuevo_b1_b2_aprox' => tec_round(min(90.0, $total12 + 4.0)),
        'nuevo_total_aprox' => tec_round(min(100.0, $total + 4.0)),
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

    $p1a = calcular_1a_tecnicas(tec_list($bloque1, 'publicaciones'));
    $p1b = calcular_1b_tecnicas(tec_list($bloque1, 'libros'));
    $p1c = calcular_1c_tecnicas(tec_list($bloque1, 'proyectos'));
    $p1d = calcular_1d_tecnicas(tec_list($bloque1, 'transferencia'));
    $p1e = calcular_1e_tecnicas(tec_list($bloque1, 'tesis_dirigidas'));
    $p1f = calcular_1f_tecnicas(tec_list($bloque1, 'congresos'));
    $p1g = calcular_1g_tecnicas(tec_list($bloque1, 'otros_meritos_investigacion'));
    $B1 = tec_round(tec_clamp($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 0.0, 60.0));

    $p2a = calcular_2a_tecnicas(tec_list($bloque2, 'docencia_universitaria'));
    $p2b = calcular_2b_tecnicas(tec_list($bloque2, 'evaluacion_docente'));
    $p2c = calcular_2c_tecnicas(tec_list($bloque2, 'formacion_docente'));
    $p2d = calcular_2d_tecnicas(tec_list($bloque2, 'material_docente'));
    $B2 = tec_round(tec_clamp($p2a + $p2b + $p2c + $p2d, 0.0, 30.0));

    $p3a = calcular_3a_tecnicas(tec_list($bloque3, 'formacion_academica'));
    $p3b = calcular_3b_tecnicas(tec_list($bloque3, 'experiencia_profesional'));
    $B3 = tec_round(tec_clamp($p3a + $p3b, 0.0, 8.0));

    $p4 = calcular_4_tecnicas($bloque4);
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