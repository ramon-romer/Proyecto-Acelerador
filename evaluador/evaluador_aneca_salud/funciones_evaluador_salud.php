<?php
declare(strict_types=1);

/**
 * SALUD — PCD/PUP
 *
 * Tabla orientativa:
 * 1A=35, 1B=7, 1C=7, 1D=4, 1E=4, 1F=2, 1G=1
 * Bloque 1 = 60
 * Bloque 2 = 30
 * Bloque 3 = 8
 * Bloque 4 = 2
 *
 * Regla positiva:
 * B1 + B2 >= 50
 * Total >= 55
 */

function salud_to_float(mixed $value): float
{
    return is_numeric($value) ? (float)$value : 0.0;
}

function salud_clamp(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function salud_get_bloque(array $json, string $key): array
{
    $bloque = $json[$key] ?? [];
    return is_array($bloque) ? $bloque : [];
}

function salud_get_lista(array $bloque, string $key): array
{
    $lista = $bloque[$key] ?? [];
    return is_array($lista) ? $lista : [];
}

/* =========================================================
 * BLOQUE 1.A — Publicaciones científicas
 * Salud: JCR como referente principal, por DECILES
 * Máximo: 35
 * ========================================================= */
function calcular_1a_salud(array $publicaciones): float
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

        $tipo = strtolower(trim((string)($pub['tipo'] ?? 'articulo')));
        if ($tipo !== 'articulo') {
            continue;
        }

        $tipoIndice = strtoupper(trim((string)($pub['tipo_indice'] ?? 'OTRO')));
        $decil = strtoupper(trim((string)($pub['decil'] ?? '')));
        $cuartil = strtoupper(trim((string)($pub['cuartil'] ?? '')));
        $tipoAportacion = strtolower(trim((string)($pub['tipo_aportacion'] ?? 'original')));
        $afinidad = strtolower(trim((string)($pub['afinidad'] ?? 'relacionada')));
        $posicion = strtolower(trim((string)($pub['posicion_autor'] ?? 'intermedio')));
        $numAutores = max(1, (int)($pub['numero_autores'] ?? 1));
        $citas = max(0, (int)($pub['citas'] ?? 0));
        $acreditacionPaginas = (string)($pub['acreditacion_paginas'] ?? '1') === '1';

        if (!$acreditacionPaginas) {
            continue;
        }

        $base = 0.0;

        if ($tipoIndice === 'JCR') {
            $base = match ($decil) {
                'D1' => 5.0,
                'D2' => 4.6,
                'D3' => 4.0,
                'D4' => 3.5,
                'D5' => 3.0,
                'D6' => 2.5,
                'D7' => 2.1,
                'D8' => 1.7,
                'D9' => 1.3,
                'D10' => 1.0,
                default => match ($cuartil) {
                    'Q1' => 4.0,
                    'Q2' => 2.8,
                    'Q3' => 1.8,
                    'Q4' => 1.0,
                    default => 0.8,
                },
            };
        } else {
            $base = 0.5;
        }

        $factorTipo = match ($tipoAportacion) {
            'original' => 1.20,
            'revision' => 0.95,
            'nota_clinica' => 0.70,
            'carta' => 0.40,
            default => 0.60,
        };

        $factorAfinidad = match ($afinidad) {
            'total' => 1.20,
            'relacionada' => 1.00,
            'periferica' => 0.75,
            'ajena' => 0.40,
            default => 1.00,
        };

        $factorPosicion = match ($posicion) {
            'autor_unico' => 1.30,
            'primero' => 1.20,
            'ultimo' => 1.15,
            'correspondencia' => 1.10,
            'intermedio' => 1.00,
            'secundario' => 0.80,
            default => 1.00,
        };

        $factorCoautoria = match (true) {
            $numAutores <= 1 => 1.15,
            $numAutores <= 3 => 1.08,
            $numAutores <= 5 => 1.00,
            $numAutores <= 8 => 0.92,
            $numAutores <= 12 => 0.85,
            default => 0.75,
        };

        $bonusCitas = match (true) {
            $citas >= 150 => 0.80,
            $citas >= 75 => 0.55,
            $citas >= 40 => 0.35,
            $citas >= 20 => 0.20,
            $citas >= 10 => 0.10,
            default => 0.00,
        };

        $puntos = ($base * $factorTipo * $factorAfinidad * $factorPosicion * $factorCoautoria) + $bonusCitas;
        $total += $puntos;
    }

    return round(salud_clamp($total, 0.0, 35.0), 2);
}

/* =========================================================
 * BLOQUE 1.B — Libros y capítulos
 * Máximo: 7
 * ========================================================= */
function calcular_1b_salud(array $libros): float
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

        $editorialConsignada = (string)($item['editorial_consignada'] ?? '1') === '1';
        if (!$editorialConsignada) {
            continue;
        }

        if ((string)($item['es_autoedicion'] ?? '0') === '1') {
            continue;
        }
        if ((string)($item['pago_por_publicar'] ?? '0') === '1') {
            continue;
        }
        if ((string)($item['tesis_publicada'] ?? '0') === '1') {
            continue;
        }
        if ((string)($item['es_acta_congreso'] ?? '0') === '1') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'capitulo')));
        $nivelEditorial = strtolower(trim((string)($item['nivel_editorial'] ?? 'media_difusion')));
        $afinidad = strtolower(trim((string)($item['afinidad'] ?? 'relacionada')));
        $posicion = strtolower(trim((string)($item['posicion_autor'] ?? 'intermedio')));

        $base = 0.0;
        if ($tipo === 'libro') {
            $base = match ($nivelEditorial) {
                'alta_difusion' => 2.8,
                'media_difusion' => 1.8,
                default => 0.0,
            };
        } else {
            $base = match ($nivelEditorial) {
                'alta_difusion' => 1.2,
                'media_difusion' => 0.8,
                default => 0.0,
            };
        }

        if ($base <= 0.0) {
            continue;
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

    return round(salud_clamp($total, 0.0, 7.0), 2);
}

/* =========================================================
 * BLOQUE 1.C — Proyectos de investigación
 * Máximo: 7
 * ========================================================= */
function calcular_1c_salud(array $proyectos): float
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

        $competitivo = (string)($item['competitivo'] ?? '1') === '1';
        $ensayo = (string)($item['es_ensayo_clinico'] ?? '0') === '1';
        $certificado = (string)($item['esta_certificado'] ?? '1') === '1';
        $soloIP = (string)($item['solo_certificado_ip'] ?? '0') === '1';

        if (!$competitivo || $ensayo || !$certificado || $soloIP) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo_proyecto'] ?? 'universidad')));
        $rol = strtolower(trim((string)($item['rol'] ?? 'investigador')));
        $anios = max(0.0, salud_to_float($item['anios_duracion'] ?? 1));

        $base = match ($tipo) {
            'europeo' => 2.6,
            'nacional' => 2.0,
            'autonomico' => 1.4,
            'instituto_investigacion' => 1.2,
            default => 0.9,
        };

        $factorRol = match ($rol) {
            'ip' => 1.25,
            'ic' => 1.12,
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

    return round(salud_clamp($total, 0.0, 7.0), 2);
}

/* =========================================================
 * BLOQUE 1.D — Transferencia
 * Máximo: 4
 * ========================================================= */
function calcular_1d_salud(array $transferencia): float
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

        $impacto = strtolower(trim((string)($item['impacto'] ?? 'medio')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'nacional')));

        $baseImpacto = match ($impacto) {
            'alto' => 1.10,
            'medio' => 0.75,
            default => 0.40,
        };

        $bonusAmbito = match ($ambito) {
            'internacional' => 0.45,
            'nacional' => 0.30,
            'regional' => 0.15,
            default => 0.00,
        };

        $total += $baseImpacto + $bonusAmbito;
    }

    return round(salud_clamp($total, 0.0, 4.0), 2);
}

/* =========================================================
 * BLOQUE 1.E — Dirección de tesis doctorales
 * Máximo: 4
 * ========================================================= */
function calcular_1e_salud(array $tesis): float
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

        $presentadaAprobada = (string)($item['presentada_aprobada'] ?? '0') === '1';
        $certificadoUni = (string)($item['certificado_universidad'] ?? '0') === '1';

        if (!$presentadaAprobada || !$certificadoUni) {
            continue;
        }

        $rol = strtolower(trim((string)($item['rol'] ?? 'director')));
        $base = ($rol === 'codirector') ? 1.2 : 1.8;
        $total += $base;
    }

    return round(salud_clamp($total, 0.0, 4.0), 2);
}

/* =========================================================
 * BLOQUE 1.F — Congresos y reuniones
 * Máximo: 2
 * ========================================================= */
function calcular_1f_salud(array $congresos): float
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

        $sociedad = (string)($item['sociedad_referencia'] ?? '0') === '1';
        $selectiva = (string)($item['admision_selectiva'] ?? '0') === '1';
        if (!$sociedad || !$selectiva) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'comunicacion')));
        $numeroMismo = max(1, (int)($item['numero_mismo_congreso'] ?? 1));

        $base = match ($tipo) {
            'ponencia_invitada' => 0.55,
            'comunicacion' => 0.35,
            'poster' => 0.18,
            default => 0.20,
        };

        $factorMismoEvento = match (true) {
            $numeroMismo <= 2 => 1.00,
            default => 0.50,
        };

        $total += $base * $factorMismoEvento;
    }

    return round(salud_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 1.G — Otros méritos investigación
 * Máximo: 1
 * ========================================================= */
function calcular_1g_salud(array $otros): float
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
            'premio_investigacion' => 0.45,
            'actividad_cientifica' => 0.30,
            'grupo_investigacion' => 0.20,
            'revision' => 0.18,
            default => 0.15,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.80,
        };

        $total += $base * $factor;
    }

    return round(salud_clamp($total, 0.0, 1.0), 2);
}

/* =========================================================
 * BLOQUE 2.A — Docencia universitaria
 * Máximo: 17
 * ========================================================= */
function calcular_2a_salud(array $docencia): float
{
    $horas = 0.0;
    $bonusMaster = 0.0;
    $bonusPuesto = 0.0;

    foreach ($docencia as $item) {
        if (!is_array($item)) {
            continue;
        }

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $acreditada = (string)($item['acreditada'] ?? '1') === '1';
        $soloDpto = (string)($item['solo_dpto'] ?? '0') === '1';

        if (!$acreditada || $soloDpto) {
            continue;
        }

        $horas += max(0.0, salud_to_float($item['horas'] ?? 0));
        $puesto = strtolower(trim((string)($item['puesto'] ?? 'contratado')));
        $nivel = strtolower(trim((string)($item['nivel'] ?? 'grado')));

        $bonusPuesto += match ($puesto) {
            'contratado' => 0.20,
            'venia_docendi' => 0.10,
            'colaborador_honorario' => 0.08,
            'tutor_practicas' => 0.06,
            default => 0.00,
        };

        if ($nivel === 'master') {
            $bonusMaster += 0.20;
        }
    }

    $puntosHoras = min(17.0, ($horas / 450.0) * 17.0);
    $total = $puntosHoras + min(0.8, $bonusMaster) + min(0.6, $bonusPuesto);

    return round(salud_clamp($total, 0.0, 17.0), 2);
}

/* =========================================================
 * BLOQUE 2.B — Evaluación docente
 * Máximo: 3
 * ========================================================= */
function calcular_2b_salud(array $evaluaciones): float
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
            'excelente' => 1.10,
            'muy_favorable' => 0.85,
            'favorable' => 0.60,
            default => 0.35,
        };

        $total += $base * $numero;
    }

    return round(salud_clamp($total, 0.0, 3.0), 2);
}

/* =========================================================
 * BLOQUE 2.C — Formación docente
 * Máximo: 3
 * ========================================================= */
function calcular_2c_salud(array $actividades): float
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

        $esTecnica = (string)($item['es_tecnica'] ?? '0') === '1';
        if ($esTecnica) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'discente')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'local')));

        $base = match ($tipo) {
            'docente' => 0.45,
            default => 0.30,
        };

        $factorAmbito = match ($ambito) {
            'internacional' => 1.20,
            'nacional' => 1.00,
            default => 0.80,
        };

        $total += $base * $factorAmbito;
    }

    return round(salud_clamp($total, 0.0, 3.0), 2);
}

/* =========================================================
 * BLOQUE 2.D — Material docente / innovación
 * Máximo: 7
 * ========================================================= */
function calcular_2d_salud(array $materiales): float
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
        $relevancia = strtolower(trim((string)($item['relevancia'] ?? 'media')));

        $base = match ($tipo) {
            'publicacion_docente' => 1.70,
            'proyecto_innovacion' => 1.45,
            default => 1.00,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base * $factor;
    }

    return round(salud_clamp($total, 0.0, 7.0), 2);
}

/* =========================================================
 * BLOQUE 3.A — Formación académica
 * Máximo: 6
 * En PCD no contar cursos de especialización
 * ========================================================= */
function calcular_3a_salud(array $formacion): float
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

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'master_universitario')));
        $duracion = max(0.0, salud_to_float($item['duracion'] ?? 1));
        $excluirEnPcd = (string)($item['excluir_en_pcd'] ?? '0') === '1';

        if ($excluirEnPcd) {
            continue;
        }

        $base = match ($tipo) {
            'mir_equivalente' => 1.60,
            'doctorado_internacional' => 1.40,
            'doble_titulacion' => 1.00,
            'master_universitario' => 0.90,
            'board_europeo' => 1.00,
            'master_titulo_propio' => 0.60,
            'beca_competitiva' => 1.20,
            'estancia' => min(1.30, 0.25 * $duracion),
            'curso_especializacion' => 0.00,
            default => 0.00,
        };

        $total += $base;
    }

    return round(salud_clamp($total, 0.0, 6.0), 2);
}

/* =========================================================
 * BLOQUE 3.B — Experiencia profesional
 * Máximo: 2
 * ========================================================= */
function calcular_3b_salud(array $experiencia): float
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

        $anios = max(0.0, salud_to_float($item['anios'] ?? 0));
        $relacion = strtolower(trim((string)($item['relacion'] ?? 'media')));

        $factorRelacion = match ($relacion) {
            'alta' => 1.00,
            'media' => 0.70,
            default => 0.40,
        };

        $total += min(1.0, 0.35 * $anios) * $factorRelacion;
    }

    return round(salud_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 4 — Otros méritos
 * Máximo: 2
 * ========================================================= */
function calcular_4_salud(array $otros): float
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
            'premio' => 0.60,
            'distincion' => 0.50,
            'gestion' => 0.40,
            default => 0.25,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base * $factor;
    }

    return round(salud_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * FUNCIÓN PRINCIPAL
 * ========================================================= */
function evaluar_expediente_salud(array $json): array
{
    $bloque1 = salud_get_bloque($json, 'bloque_1');
    $bloque2 = salud_get_bloque($json, 'bloque_2');
    $bloque3 = salud_get_bloque($json, 'bloque_3');
    $bloque4 = $json['bloque_4'] ?? [];

    $p1a = calcular_1a_salud(salud_get_lista($bloque1, 'publicaciones'));
    $p1b = calcular_1b_salud(salud_get_lista($bloque1, 'libros'));
    $p1c = calcular_1c_salud(salud_get_lista($bloque1, 'proyectos'));
    $p1d = calcular_1d_salud(salud_get_lista($bloque1, 'transferencia'));
    $p1e = calcular_1e_salud(salud_get_lista($bloque1, 'tesis_dirigidas'));
    $p1f = calcular_1f_salud(salud_get_lista($bloque1, 'congresos'));
    $p1g = calcular_1g_salud(salud_get_lista($bloque1, 'otros_meritos_investigacion'));
    $b1 = round($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 2);
    $b1 = salud_clamp($b1, 0.0, 60.0);

    $p2a = calcular_2a_salud(salud_get_lista($bloque2, 'docencia_universitaria'));
    $p2b = calcular_2b_salud(salud_get_lista($bloque2, 'evaluacion_docente'));
    $p2c = calcular_2c_salud(salud_get_lista($bloque2, 'formacion_docente'));
    $p2d = calcular_2d_salud(salud_get_lista($bloque2, 'material_docente'));
    $b2 = round($p2a + $p2b + $p2c + $p2d, 2);
    $b2 = salud_clamp($b2, 0.0, 30.0);

    $p3a = calcular_3a_salud(salud_get_lista($bloque3, 'formacion_academica'));
    $p3b = calcular_3b_salud(salud_get_lista($bloque3, 'experiencia_profesional'));
    $b3 = round($p3a + $p3b, 2);
    $b3 = salud_clamp($b3, 0.0, 8.0);

    $p4 = calcular_4_salud(is_array($bloque4) ? $bloque4 : []);
    $b4 = salud_clamp($p4, 0.0, 2.0);

    $totalB1B2 = round($b1 + $b2, 2);
    $totalFinal = round($b1 + $b2 + $b3 + $b4, 2);

    $cumple1 = $totalB1B2 >= 50.0;
    $cumple2 = $totalFinal >= 55.0;
    $resultado = ($cumple1 && $cumple2) ? 'POSITIVA' : 'NEGATIVA';

    return [
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
}

/**
 * Alias de compatibilidad
 */
function evaluar_expediente(array $json): array
{
    return evaluar_expediente_salud($json);
}