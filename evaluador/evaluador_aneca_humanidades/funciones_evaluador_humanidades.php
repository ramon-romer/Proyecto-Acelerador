<?php
declare(strict_types=1);

/**
 * Humanidades — PCD/PUP
 * Adaptado a la tabla orientativa aportada por el usuario:
 * 1A=26, 1B=16, 1C=5, 1D=2, 1E=4, 1F=5, 1G=2
 * 2A=17, 2B=3, 2C=3, 2D=7
 * 3A=6, 3B=2
 * 4 =2
 * Regla positiva: B1+B2 >= 50 y total >= 55
 */

function hum_to_float(mixed $value): float
{
    if (is_numeric($value)) {
        return (float)$value;
    }
    return 0.0;
}

function hum_clamp(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function hum_count_valid(array $items): int
{
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $esValido = $item['es_valido'] ?? $item['es_valida'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }
        $count++;
    }
    return $count;
}

function hum_get_bloque(array $json, string $key): array
{
    $bloque = $json[$key] ?? [];
    return is_array($bloque) ? $bloque : [];
}

function hum_get_lista(array $bloque, string $key): array
{
    $lista = $bloque[$key] ?? [];
    return is_array($lista) ? $lista : [];
}

/* =========================================================
 * BLOQUE 1.A — Publicaciones científicas y patentes
 * Máximo Humanidades PCD/PUP: 26
 * ========================================================= */
function calcular_1a_humanidades(array $publicaciones): float
{
    $total = 0.0;

    foreach ($publicaciones as $pub) {
        if (!is_array($pub)) {
            continue;
        }

        $esValida = $pub['es_valida'] ?? $pub['es_valido'] ?? 1;
        if ((string)$esValida === '0') {
            continue;
        }

        $tipoIndice = strtoupper(trim((string)($pub['tipo_indice'] ?? 'OTRO')));
        $cuartil = strtoupper(trim((string)($pub['cuartil'] ?? '')));
        $subtipo = strtoupper(trim((string)($pub['subtipo_indice'] ?? '')));
        $afinidad = strtolower(trim((string)($pub['afinidad'] ?? 'relacionada')));
        $posicion = strtolower(trim((string)($pub['posicion_autor'] ?? 'intermedio')));
        $numAutores = max(1, (int)($pub['numero_autores'] ?? 1));
        $citas = max(0, (int)($pub['citas'] ?? 0));

        $base = 0.8;

        switch ($tipoIndice) {
            case 'JCR':
            case 'SJR':
                $base = match ($cuartil) {
                    'Q1' => 3.5,
                    'Q2' => 2.8,
                    'Q3' => 2.0,
                    'Q4' => 1.4,
                    default => 1.2,
                };
                break;

            case 'FECYT':
                $base = 2.2;
                break;

            case 'RESH':
            case 'ERIH':
            case 'MIAR':
                $base = 1.6;
                break;

            case 'OTRO':
            default:
                $base = 0.9;
                break;
        }

        if (in_array($subtipo, ['A', 'A+', 'TOP', 'C1', 'PLUS'], true)) {
            $base += 0.4;
        }

        $factorAfinidad = match ($afinidad) {
            'total' => 1.20,
            'relacionada' => 1.00,
            'periferica' => 0.75,
            'ajena' => 0.40,
            default => 1.00,
        };

        $factorPosicion = match ($posicion) {
            'autor_unico' => 1.25,
            'primero' => 1.15,
            'ultimo' => 1.10,
            'intermedio' => 1.00,
            'secundario' => 0.85,
            default => 1.00,
        };

        $factorCoautoria = match (true) {
            $numAutores <= 1 => 1.20,
            $numAutores <= 3 => 1.05,
            $numAutores <= 5 => 1.00,
            $numAutores <= 8 => 0.90,
            default => 0.80,
        };

        $bonusCitas = match (true) {
            $citas >= 50 => 0.50,
            $citas >= 20 => 0.30,
            $citas >= 10 => 0.20,
            $citas >= 5 => 0.10,
            default => 0.00,
        };

        $puntos = ($base * $factorAfinidad * $factorPosicion * $factorCoautoria) + $bonusCitas;
        $total += $puntos;
    }

    return round(hum_clamp($total, 0.0, 26.0), 2);
}

/* =========================================================
 * BLOQUE 1.B — Libros y capítulos
 * Máximo Humanidades PCD/PUP: 16
 * ========================================================= */
function calcular_1b_humanidades(array $libros): float
{
    $total = 0.0;

    foreach ($libros as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'capitulo')));
        $nivelEditorial = strtolower(trim((string)($item['nivel_editorial'] ?? 'secundaria')));
        $afinidad = strtolower(trim((string)($item['afinidad'] ?? 'relacionada')));
        $posicion = strtolower(trim((string)($item['posicion_autor'] ?? 'intermedio')));
        $esActa = (string)($item['es_acta_congreso'] ?? '0') === '1';

        $base = 0.0;

        if ($tipo === 'libro') {
            $base = match ($nivelEditorial) {
                'prestigiosa' => 5.0,
                'secundaria' => 3.5,
                default => 2.0,
            };
        } else {
            $base = match ($nivelEditorial) {
                'prestigiosa' => 1.8,
                'secundaria' => 1.2,
                default => 0.7,
            };
        }

        if ($esActa) {
            $base *= 0.75;
        }

        $factorAfinidad = match ($afinidad) {
            'total' => 1.20,
            'relacionada' => 1.00,
            'periferica' => 0.75,
            'ajena' => 0.40,
            default => 1.00,
        };

        $factorPosicion = match ($posicion) {
            'autor_unico' => 1.20,
            'primero' => 1.10,
            'ultimo' => 1.05,
            'intermedio' => 1.00,
            default => 0.90,
        };

        $total += $base * $factorAfinidad * $factorPosicion;
    }

    return round(hum_clamp($total, 0.0, 16.0), 2);
}

/* =========================================================
 * BLOQUE 1.C — Proyectos y contratos de investigación
 * Máximo Humanidades PCD/PUP: 5
 * ========================================================= */
function calcular_1c_humanidades(array $proyectos): float
{
    $total = 0.0;

    foreach ($proyectos as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo_proyecto'] ?? 'universidad')));
        $rol = strtolower(trim((string)($item['rol'] ?? 'investigador')));
        $anios = max(0.0, hum_to_float($item['anios_duracion'] ?? 1));
        $certificado = (string)($item['esta_certificado'] ?? '1') === '1';
        $caracterInvestigador = (string)($item['caracter_investigador'] ?? '1') === '1';

        if (!$certificado) {
            continue;
        }

        if ($tipo === 'contrato' && !$caracterInvestigador) {
            continue;
        }

        $base = match ($tipo) {
            'internacional' => 2.5,
            'nacional' => 2.0,
            'autonomico' => 1.2,
            'contrato' => 1.0,
            default => 0.7,
        };

        $factorRol = match ($rol) {
            'ip' => 1.20,
            'coip' => 1.05,
            'investigador' => 1.00,
            default => 0.80,
        };

        $factorDuracion = match (true) {
            $anios >= 4 => 1.15,
            $anios >= 2 => 1.00,
            $anios >= 1 => 0.90,
            default => 0.75,
        };

        $total += $base * $factorRol * $factorDuracion;
    }

    return round(hum_clamp($total, 0.0, 5.0), 2);
}

/* =========================================================
 * BLOQUE 1.D — Transferencia
 * Máximo Humanidades PCD/PUP: 2
 * ========================================================= */
function calcular_1d_humanidades(array $transferencia): float
{
    $total = 0.0;

    foreach ($transferencia as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $soloRegistro = (string)($item['solo_registro_propiedad'] ?? '0') === '1';
        if ($soloRegistro) {
            continue;
        }

        $impacto = strtolower(trim((string)($item['impacto'] ?? 'medio')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'nacional')));

        $baseImpacto = match ($impacto) {
            'alto' => 0.90,
            'medio' => 0.60,
            default => 0.30,
        };

        $bonusAmbito = match ($ambito) {
            'internacional' => 0.30,
            'nacional' => 0.20,
            'regional' => 0.10,
            default => 0.00,
        };

        $total += $baseImpacto + $bonusAmbito;
    }

    return round(hum_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 1.E — Dirección de tesis doctorales
 * Máximo Humanidades PCD/PUP: 4
 * ========================================================= */
function calcular_1e_humanidades(array $tesis): float
{
    $total = 0.0;

    foreach ($tesis as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $estado = strtolower(trim((string)($item['estado'] ?? 'defendida')));
        $rol = strtolower(trim((string)($item['rol'] ?? 'director')));
        $anteproyecto = (string)($item['anteproyecto_aprobado'] ?? '1') === '1';

        $base = 0.0;

        if ($estado === 'defendida') {
            $base = 2.0;
        } elseif ($estado === 'en_proceso' && $anteproyecto) {
            $base = 0.8;
        }

        if ($rol === 'codirector') {
            $base *= 0.70;
        }

        $total += $base;
    }

    return round(hum_clamp($total, 0.0, 4.0), 2);
}

/* =========================================================
 * BLOQUE 1.F — Congresos, conferencias, seminarios
 * Máximo Humanidades PCD/PUP: 5
 * ========================================================= */
function calcular_1f_humanidades(array $congresos): float
{
    $total = 0.0;

    foreach ($congresos as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $admision = (string)($item['admision_selectiva'] ?? '0') === '1';
        if (!$admision) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'ponencia')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'nacional')));
        $invitacion = (string)($item['por_invitacion'] ?? '0') === '1';

        $base = match ($tipo) {
            'conferencia' => 0.45,
            'ponencia' => 0.35,
            default => 0.25,
        };

        $factorAmbito = match ($ambito) {
            'internacional' => 1.20,
            'nacional' => 1.00,
            default => 0.80,
        };

        $bonusInvitacion = $invitacion ? 0.10 : 0.00;

        $total += ($base * $factorAmbito) + $bonusInvitacion;
    }

    return round(hum_clamp($total, 0.0, 5.0), 2);
}

/* =========================================================
 * BLOQUE 1.G — Otros méritos de investigación
 * Máximo Humanidades PCD/PUP: 2
 * ========================================================= */
function calcular_1g_humanidades(array $otros): float
{
    $total = 0.0;

    foreach ($otros as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'otro')));
        $relevancia = strtolower(trim((string)($item['relevancia'] ?? 'media')));

        $base = match ($tipo) {
            'premio_academico' => 0.60,
            'comite_editorial' => 0.40,
            'evaluador_articulos' => 0.30,
            'comite_cientifico' => 0.30,
            'grupo_investigacion' => 0.25,
            'resena' => 0.15,
            default => 0.20,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.80,
        };

        $total += $base * $factor;
    }

    return round(hum_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 2.A — Docencia universitaria
 * Máximo Humanidades PCD/PUP: 17
 * ========================================================= */
function calcular_2a_humanidades(array $docencia): float
{
    $horas = 0.0;
    $bonus = 0.0;

    foreach ($docencia as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $acreditada = (string)($item['acreditada'] ?? '1') === '1';
        if (!$acreditada) {
            continue;
        }

        $horas += max(0.0, hum_to_float($item['horas'] ?? 0));
        $nivel = strtolower(trim((string)($item['nivel'] ?? 'grado')));

        if ($nivel === 'master') {
            $bonus += 0.40;
        }
    }

    $puntosHoras = min(17.0, ($horas / 450.0) * 17.0);
    $total = $puntosHoras + min(1.0, $bonus);

    return round(hum_clamp($total, 0.0, 17.0), 2);
}

/* =========================================================
 * BLOQUE 2.B — Evaluaciones sobre la docencia
 * Máximo Humanidades PCD/PUP: 3
 * ========================================================= */
function calcular_2b_humanidades(array $evaluaciones): float
{
    $total = 0.0;

    foreach ($evaluaciones as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $calificacion = strtolower(trim((string)($item['calificacion'] ?? 'favorable')));
        $numero = max(1, (int)($item['numero'] ?? 1));

        $base = match ($calificacion) {
            'excelente' => 1.20,
            'muy_favorable' => 0.90,
            'favorable' => 0.60,
            default => 0.35,
        };

        $total += $base * $numero;
    }

    return round(hum_clamp($total, 0.0, 3.0), 2);
}

/* =========================================================
 * BLOQUE 2.C — Formación docente
 * Máximo Humanidades PCD/PUP: 3
 * ========================================================= */
function calcular_2c_humanidades(array $actividades): float
{
    $total = 0.0;

    foreach ($actividades as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'seminario')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'local')));
        $invitacion = (string)($item['por_invitacion'] ?? '0') === '1';

        $base = match ($tipo) {
            'congreso_docente' => 0.60,
            'curso' => 0.45,
            default => 0.35,
        };

        $factorAmbito = match ($ambito) {
            'internacional' => 1.20,
            'nacional' => 1.00,
            default => 0.80,
        };

        $bonus = $invitacion ? 0.15 : 0.00;

        $total += ($base * $factorAmbito) + $bonus;
    }

    return round(hum_clamp($total, 0.0, 3.0), 2);
}

/* =========================================================
 * BLOQUE 2.D — Material docente / innovación / EEES
 * Máximo Humanidades PCD/PUP: 7
 * ========================================================= */
function calcular_2d_humanidades(array $materiales): float
{
    $total = 0.0;

    foreach ($materiales as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'material_docente')));
        $isbn = (string)($item['isbn_issn'] ?? '0') === '1';
        $relevancia = strtolower(trim((string)($item['relevancia'] ?? 'media')));

        $base = match ($tipo) {
            'publicacion_docente' => 1.60,
            'proyecto_innovacion' => 1.40,
            'contribucion_eees' => 1.00,
            default => 0.80,
        };

        if ($isbn) {
            $base += 0.30;
        }

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base * $factor;
    }

    return round(hum_clamp($total, 0.0, 7.0), 2);
}

/* =========================================================
 * BLOQUE 3.A — Formación académica
 * Máximo Humanidades PCD/PUP: 6
 * ========================================================= */
function calcular_3a_humanidades(array $formacion): float
{
    $total = 0.0;

    foreach ($formacion as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'curso_especializacion')));
        $duracion = max(0.0, hum_to_float($item['duracion'] ?? 1));

        $base = match ($tipo) {
            'doctorado_internacional' => 1.50,
            'beca_competitiva' => 1.30,
            'estancia' => min(1.50, 0.30 * $duracion),
            'master' => 0.90,
            'mencion_tesis' => 0.80,
            default => min(0.60, 0.10 * $duracion),
        };

        $total += $base;
    }

    return round(hum_clamp($total, 0.0, 6.0), 2);
}

/* =========================================================
 * BLOQUE 3.B — Experiencia profesional
 * Máximo Humanidades PCD/PUP: 2
 * ========================================================= */
function calcular_3b_humanidades(array $experiencia): float
{
    $total = 0.0;

    foreach ($experiencia as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $anios = max(0.0, hum_to_float($item['anios'] ?? 0));
        $relacion = strtolower(trim((string)($item['relacion'] ?? 'media')));

        $factorRelacion = match ($relacion) {
            'alta' => 1.00,
            'media' => 0.70,
            default => 0.40,
        };

        $total += min(1.0, 0.35 * $anios) * $factorRelacion;
    }

    return round(hum_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 4 — Otros méritos
 * Máximo Humanidades PCD/PUP: 2
 * ========================================================= */
function calcular_4_humanidades(array $otros): float
{
    $total = 0.0;

    foreach ($otros as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'otro')));
        $relevancia = strtolower(trim((string)($item['relevancia'] ?? 'media')));

        $base = match ($tipo) {
            'gestion' => 0.50,
            'distincion' => 0.45,
            'premio' => 0.60,
            default => 0.30,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base * $factor;
    }

    return round(hum_clamp($total, 0.0, 2.0), 2);
}


/* =========================================================
 * DIAGNÓSTICO Y ASESOR ORIENTATIVO — HUMANIDADES PCD/PUP
 * Basado en los máximos orientativos de la rama:
 * 1A=26, 1B=16, 1C=5, 1D=2, 1E=4, 1F=5, 1G=2
 * 2A=17, 2B=3, 2C=3, 2D=7
 * 3A=6, 3B=2, B4=2
 * Reglas PCD/PUP: B1+B2 >= 50 y total >= 55
 * ========================================================= */
function hum_round(float $value): float
{
    return round($value, 2);
}

function hum_objetivos_orientativos(): array
{
    return [
        'B1' => 35.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 18.0,
        '1B' => 9.0,
        '1C' => 2.5,
        '1D' => 1.0,
        '1E' => 1.5,
        '1F' => 2.0,
        '1G' => 0.8,

        '2A' => 12.0,
        '2B' => 1.5,
        '2C' => 1.0,
        '2D' => 2.0,

        '3A' => 2.0,
        '3B' => 0.8,

        'TOTAL_B1_B2' => 50.0,
        'TOTAL_FINAL' => 55.0,
    ];
}

function hum_clasificar_nivel(float $actual, float $objetivo): string
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

function hum_detectar_perfil(array $resultado): string
{
    $b1 = (float)($resultado['bloque_1'] ?? 0);
    $b2 = (float)($resultado['bloque_2'] ?? 0);
    $b3 = (float)($resultado['bloque_3'] ?? 0);
    $b4 = (float)($resultado['bloque_4'] ?? 0);
    $total12 = (float)($resultado['total_b1_b2'] ?? 0);
    $total = (float)($resultado['total_final'] ?? 0);

    if ($b1 >= 35.0 && $b2 < 10.0) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }

    if ($b2 >= 15.0 && $b1 < 25.0) {
        return 'Perfil docente razonable con investigación insuficiente';
    }

    if ($b1 >= 30.0 && $b2 >= 12.0 && $total12 >= 50.0 && $total >= 55.0) {
        return 'Perfil equilibrado y competitivo para Humanidades';
    }

    if ($b1 >= 25.0 && (float)($resultado['puntuacion_1b'] ?? 0) < 4.0) {
        return 'Perfil investigador apoyado en artículos, con libros/capítulos mejorables';
    }

    if ($b1 >= 25.0 && (float)($resultado['puntuacion_1a'] ?? 0) < 10.0) {
        return 'Perfil investigador apoyado en libros/capítulos, con publicaciones periódicas mejorables';
    }

    if ($b1 < 20.0 && $b2 < 10.0) {
        return 'Perfil aún inmaduro para acreditación en Humanidades';
    }

    if (($b3 + $b4) >= 4.0 && $total12 < 50.0) {
        return 'Perfil con méritos complementarios aceptables, pero núcleo investigación+docencia insuficiente';
    }

    return 'Perfil mixto con fortalezas parciales y necesidad de refuerzo estratégico';
}

function hum_contar_publicaciones_impacto(array $publicaciones): array
{
    $out = [
        'Q1' => 0,
        'Q2' => 0,
        'Q3' => 0,
        'Q4' => 0,
        'FECYT' => 0,
        'RESH_ERIH_MIAR' => 0,
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

        $tipoIndice = strtoupper(trim((string)($pub['tipo_indice'] ?? 'OTRO')));
        $cuartil = strtoupper(trim((string)($pub['cuartil'] ?? '')));

        if (in_array($cuartil, ['Q1', 'Q2', 'Q3', 'Q4'], true)) {
            $out[$cuartil]++;
            continue;
        }

        if ($tipoIndice === 'FECYT') {
            $out['FECYT']++;
            continue;
        }

        if (in_array($tipoIndice, ['RESH', 'ERIH', 'MIAR'], true)) {
            $out['RESH_ERIH_MIAR']++;
            continue;
        }

        $out['OTRAS']++;
    }

    return $out;
}

function hum_contar_libros_capitulos(array $libros): array
{
    $out = [
        'libros' => 0,
        'capitulos' => 0,
        'actas_congreso' => 0,
        'editorial_prestigiosa' => 0,
    ];

    foreach ($libros as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'capitulo')));
        $nivelEditorial = strtolower(trim((string)($item['nivel_editorial'] ?? '')));
        $esActa = (string)($item['es_acta_congreso'] ?? '0') === '1';

        if ($tipo === 'libro') {
            $out['libros']++;
        } else {
            $out['capitulos']++;
        }

        if ($esActa) {
            $out['actas_congreso']++;
        }

        if ($nivelEditorial === 'prestigiosa') {
            $out['editorial_prestigiosa']++;
        }
    }

    return $out;
}

function hum_generar_diagnostico(array $datos, array $resultado): array
{
    $obj = hum_objetivos_orientativos();

    $b1 = (float)($resultado['bloque_1'] ?? 0);
    $b2 = (float)($resultado['bloque_2'] ?? 0);
    $b3 = (float)($resultado['bloque_3'] ?? 0);
    $b4 = (float)($resultado['bloque_4'] ?? 0);
    $total12 = (float)($resultado['total_b1_b2'] ?? 0);
    $total = (float)($resultado['total_final'] ?? 0);

    $deficit1 = max(0.0, 50.0 - $total12);
    $deficit2 = max(0.0, 55.0 - $total);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ((float)($resultado['puntuacion_1a'] ?? 0) >= $obj['1A']) {
        $fortalezas[] = 'Producción científica principal sólida en publicaciones revisadas por pares.';
    } else {
        $debilidades[] = 'La producción científica principal en revistas o publicaciones revisadas por pares necesita refuerzo.';
    }

    if ((float)($resultado['puntuacion_1b'] ?? 0) >= $obj['1B']) {
        $fortalezas[] = 'Buen peso de libros, capítulos o contribuciones editoriales relevantes.';
    } else {
        $debilidades[] = 'Conviene reforzar libros, capítulos o editoriales con indicios de calidad.';
    }

    if ((float)($resultado['puntuacion_2a'] ?? 0) >= $obj['2A']) {
        $fortalezas[] = 'La docencia universitaria acreditable tiene volumen razonable.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable es insuficiente o está poco consolidada.';
    }

    if ((float)($resultado['puntuacion_1c'] ?? 0) < $obj['1C']) {
        $alertas[] = 'Los proyectos y contratos de investigación tienen bajo peso en el expediente.';
    }

    if ((float)($resultado['puntuacion_1f'] ?? 0) < $obj['1F']) {
        $alertas[] = 'La presencia en congresos con proceso selectivo es baja.';
    }

    if ((float)($resultado['puntuacion_2b'] ?? 0) <= 0.0) {
        $alertas[] = 'No consta evaluación docente formal relevante.';
    }

    if ($deficit1 > 0.0) {
        $alertas[] = 'No se cumple la regla principal: Investigación + Docencia ≥ 50.';
    }

    if ($deficit2 > 0.0) {
        $alertas[] = 'No se cumple la regla total: puntuación final ≥ 55.';
    }

    $bloque1 = hum_get_bloque($datos, 'bloque_1');
    $bloque2 = hum_get_bloque($datos, 'bloque_2');
    $bloque3 = hum_get_bloque($datos, 'bloque_3');
    $bloque4 = $datos['bloque_4'] ?? [];

    return [
        'version' => 'conservadora_humanidades_v1_diagnostico_asesor',
        'perfil_detectado' => hum_detectar_perfil($resultado),
        'reglas' => [
            [
                'nombre' => 'Regla principal B1 + B2 ≥ 50',
                'valor_actual' => hum_round($total12),
                'objetivo' => 50.0,
                'deficit' => hum_round($deficit1),
                'cumple' => (bool)($resultado['cumple_regla_1'] ?? false),
            ],
            [
                'nombre' => 'Regla total final ≥ 55',
                'valor_actual' => hum_round($total),
                'objetivo' => 55.0,
                'deficit' => hum_round($deficit2),
                'cumple' => (bool)($resultado['cumple_regla_2'] ?? false),
            ],
        ],
        'bloques' => [
            'B1' => [
                'actual' => hum_round($b1),
                'objetivo_orientativo' => $obj['B1'],
                'deficit' => hum_round(max(0.0, $obj['B1'] - $b1)),
                'nivel' => hum_clasificar_nivel($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => hum_round($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => hum_round(max(0.0, $obj['B2'] - $b2)),
                'nivel' => hum_clasificar_nivel($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => hum_round($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => hum_round(max(0.0, $obj['B3'] - $b3)),
                'nivel' => hum_clasificar_nivel($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => hum_round($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => hum_round(max(0.0, $obj['B4'] - $b4)),
                'nivel' => hum_clasificar_nivel($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones_impacto' => hum_contar_publicaciones_impacto(hum_get_lista($bloque1, 'publicaciones')),
            'libros_capitulos' => hum_contar_libros_capitulos(hum_get_lista($bloque1, 'libros')),
            'num_proyectos_validos' => hum_count_valid(hum_get_lista($bloque1, 'proyectos')),
            'num_transferencia_valida' => hum_count_valid(hum_get_lista($bloque1, 'transferencia')),
            'num_tesis' => hum_count_valid(hum_get_lista($bloque1, 'tesis_dirigidas')),
            'num_congresos' => hum_count_valid(hum_get_lista($bloque1, 'congresos')),
            'num_docencia_items' => hum_count_valid(hum_get_lista($bloque2, 'docencia_universitaria')),
            'num_eval_docente' => hum_count_valid(hum_get_lista($bloque2, 'evaluacion_docente')),
            'num_formacion_docente' => hum_count_valid(hum_get_lista($bloque2, 'formacion_docente')),
            'num_material_docente' => hum_count_valid(hum_get_lista($bloque2, 'material_docente')),
            'num_formacion_academica' => hum_count_valid(hum_get_lista($bloque3, 'formacion_academica')),
            'num_exp_profesional' => hum_count_valid(hum_get_lista($bloque3, 'experiencia_profesional')),
            'num_otros_b4' => hum_count_valid(is_array($bloque4) ? $bloque4 : []),
        ],
        'fortalezas' => $fortalezas,
        'debilidades' => $debilidades,
        'alertas' => $alertas,
    ];
}

function hum_generar_asesor(array $resultado): array
{
    $acciones = [];

    $total12 = (float)($resultado['total_b1_b2'] ?? 0);
    $total = (float)($resultado['total_final'] ?? 0);

    $p1a = (float)($resultado['puntuacion_1a'] ?? 0);
    $p1b = (float)($resultado['puntuacion_1b'] ?? 0);
    $p1c = (float)($resultado['puntuacion_1c'] ?? 0);
    $p1f = (float)($resultado['puntuacion_1f'] ?? 0);
    $p2a = (float)($resultado['puntuacion_2a'] ?? 0);
    $p2b = (float)($resultado['puntuacion_2b'] ?? 0);
    $p2d = (float)($resultado['puntuacion_2d'] ?? 0);

    if ($total12 < 50.0 && $p1a < 18.0) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Reforzar publicaciones científicas revisadas por pares',
            'detalle' => 'En Humanidades el apartado 1A puede llegar a 26 puntos. Conviene priorizar revistas con buenos indicios de calidad, revisión externa, citas, reseñas y autoría destacada.',
            'impacto_estimado' => '≈ 1,5 a 3,5 puntos por publicación fuerte bien acreditada.',
        ];
    }

    if ($total12 < 50.0 && $p1b < 9.0) {
        $acciones[] = [
            'prioridad' => 2,
            'titulo' => 'Aumentar libros y capítulos con editorial relevante',
            'detalle' => 'En Humanidades el 1B pesa mucho. Interesan editoriales con prestigio, SPI o proceso riguroso de evaluación, además de autoría destacada y coherencia con la línea investigadora.',
            'impacto_estimado' => '≈ 1 a 5 puntos según sea capítulo, libro completo y nivel editorial.',
        ];
    }

    if ($total12 < 50.0 && $p2a < 12.0) {
        $acciones[] = [
            'prioridad' => 3,
            'titulo' => 'Consolidar docencia universitaria reglada',
            'detalle' => 'La docencia impartida es clave en el bloque 2. Debe estar acreditada por órgano universitario competente y sumar volumen suficiente.',
            'impacto_estimado' => '≈ 2 a 4 puntos por incremento relevante de docencia acreditada.',
        ];
    }

    if ($p1c < 2.5) {
        $acciones[] = [
            'prioridad' => 4,
            'titulo' => 'Reforzar proyectos o contratos de investigación',
            'detalle' => 'Aportan solidez al bloque investigador. En Humanidades se valoran proyectos nacionales/autonómicos y contratos de investigación con generación clara de conocimiento.',
            'impacto_estimado' => '≈ 1 a 2,5 puntos por proyecto o contrato relevante.',
        ];
    }

    if ($p1f < 2.0) {
        $acciones[] = [
            'prioridad' => 5,
            'titulo' => 'Mejorar congresos con proceso selectivo',
            'detalle' => 'Se deben priorizar congresos con admisión selectiva de ponencias, preferiblemente nacionales o internacionales y coherentes con la línea investigadora.',
            'impacto_estimado' => '≈ 0,3 a 1 punto por aportación relevante.',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'prioridad' => 6,
            'titulo' => 'Aportar evaluación docente formal',
            'detalle' => 'DOCENTIA, encuestas institucionales o certificados de calidad docente pueden cerrar una debilidad clara del bloque 2.',
            'impacto_estimado' => '≈ 0,8 a 1,8 puntos.',
        ];
    }

    if ($p2d < 2.0) {
        $acciones[] = [
            'prioridad' => 7,
            'titulo' => 'Añadir material docente o innovación educativa',
            'detalle' => 'Material docente con ISBN/ISSN, proyectos de innovación y contribuciones al EEES ayudan a redondear la experiencia docente.',
            'impacto_estimado' => '≈ 0,7 a 2 puntos por mérito claro.',
        ];
    }

    usort($acciones, static fn(array $a, array $b): int => ($a['prioridad'] <=> $b['prioridad']));

    if ($acciones === []) {
        $acciones[] = [
            'prioridad' => 1,
            'titulo' => 'Expediente equilibrado',
            'detalle' => 'No se aprecian debilidades severas. Conviene seguir consolidando publicaciones, libros/capítulos y estabilidad docente.',
            'impacto_estimado' => 'Impacto incremental.',
        ];
    }

    $sim1 = [
        'escenario' => 'Añadir una publicación fuerte en 1A',
        'efecto_estimado' => '+2,5 puntos aprox.',
        'nuevo_b1_b2_aprox' => hum_round(min(90.0, $total12 + 2.5)),
        'nuevo_total_aprox' => hum_round(min(100.0, $total + 2.5)),
    ];

    $sim2 = [
        'escenario' => 'Añadir un capítulo/libro relevante en 1B',
        'efecto_estimado' => '+2 puntos aprox.',
        'nuevo_b1_b2_aprox' => hum_round(min(90.0, $total12 + 2.0)),
        'nuevo_total_aprox' => hum_round(min(100.0, $total + 2.0)),
    ];

    $sim3 = [
        'escenario' => 'Consolidar docencia y evaluación docente',
        'efecto_estimado' => '+3 puntos aprox.',
        'nuevo_b1_b2_aprox' => hum_round(min(90.0, $total12 + 3.0)),
        'nuevo_total_aprox' => hum_round(min(100.0, $total + 3.0)),
    ];

    return [
        'resumen' => 'Asesor orientativo para identificar qué palancas pueden mejorar antes el expediente de Humanidades.',
        'acciones' => array_values($acciones),
        'simulaciones' => [$sim1, $sim2, $sim3],
    ];
}

/* =========================================================
 * FUNCIÓN PRINCIPAL
 * ========================================================= */
function evaluar_expediente_humanidades(array $json): array
{
    $bloque1 = hum_get_bloque($json, 'bloque_1');
    $bloque2 = hum_get_bloque($json, 'bloque_2');
    $bloque3 = hum_get_bloque($json, 'bloque_3');
    $bloque4 = $json['bloque_4'] ?? [];

    $p1a = calcular_1a_humanidades(hum_get_lista($bloque1, 'publicaciones'));
    $p1b = calcular_1b_humanidades(hum_get_lista($bloque1, 'libros'));
    $p1c = calcular_1c_humanidades(hum_get_lista($bloque1, 'proyectos'));
    $p1d = calcular_1d_humanidades(hum_get_lista($bloque1, 'transferencia'));
    $p1e = calcular_1e_humanidades(hum_get_lista($bloque1, 'tesis_dirigidas'));
    $p1f = calcular_1f_humanidades(hum_get_lista($bloque1, 'congresos'));
    $p1g = calcular_1g_humanidades(hum_get_lista($bloque1, 'otros_meritos_investigacion'));
    $b1 = round($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 2);
    $b1 = hum_clamp($b1, 0.0, 60.0);

    $p2a = calcular_2a_humanidades(hum_get_lista($bloque2, 'docencia_universitaria'));
    $p2b = calcular_2b_humanidades(hum_get_lista($bloque2, 'evaluacion_docente'));
    $p2c = calcular_2c_humanidades(hum_get_lista($bloque2, 'formacion_docente'));
    $p2d = calcular_2d_humanidades(hum_get_lista($bloque2, 'material_docente'));
    $b2 = round($p2a + $p2b + $p2c + $p2d, 2);
    $b2 = hum_clamp($b2, 0.0, 30.0);

    $p3a = calcular_3a_humanidades(hum_get_lista($bloque3, 'formacion_academica'));
    $p3b = calcular_3b_humanidades(hum_get_lista($bloque3, 'experiencia_profesional'));
    $b3 = round($p3a + $p3b, 2);
    $b3 = hum_clamp($b3, 0.0, 8.0);

    $p4 = calcular_4_humanidades(is_array($bloque4) ? $bloque4 : []);
    $b4 = hum_clamp($p4, 0.0, 2.0);

    $totalB1B2 = round($b1 + $b2, 2);
    $totalFinal = round($b1 + $b2 + $b3 + $b4, 2);

    $cumple1 = $totalB1B2 >= 50.0;
    $cumple2 = $totalFinal >= 55.0;
    $resultado = ($cumple1 && $cumple2) ? 'POSITIVA' : 'NEGATIVA';

    $salida = [
        'puntuacion_1a' => round($p1a, 2),
        'puntuacion_1b' => round($p1b, 2),
        'puntuacion_1c' => round($p1c, 2),
        'puntuacion_1d' => round($p1d, 2),
        'puntuacion_1e' => round($p1e, 2),
        'puntuacion_1f' => round($p1f, 2),
        'puntuacion_1g' => round($p1g, 2),
        'bloque_1' => round($b1, 2),

        'puntuacion_2a' => round($p2a, 2),
        'puntuacion_2b' => round($p2b, 2),
        'puntuacion_2c' => round($p2c, 2),
        'puntuacion_2d' => round($p2d, 2),
        'bloque_2' => round($b2, 2),

        'puntuacion_3a' => round($p3a, 2),
        'puntuacion_3b' => round($p3b, 2),
        'bloque_3' => round($b3, 2),

        'bloque_4' => round($b4, 2),

        'total_b1_b2' => round($totalB1B2, 2),
        'total_final' => round($totalFinal, 2),

        'cumple_regla_1' => $cumple1 ? 1 : 0,
        'cumple_regla_2' => $cumple2 ? 1 : 0,
        'resultado' => $resultado,
    ];

    $salida['diagnostico'] = hum_generar_diagnostico($json, $salida);
    $salida['asesor'] = hum_generar_asesor($salida);

    return $salida;
}

/**
 * Alias para mantener compatibilidad con archivos que llamen a evaluar_expediente()
 */
function evaluar_expediente(array $json): array
{
    return evaluar_expediente_humanidades($json);
}