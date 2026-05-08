<?php
declare(strict_types=1);

/**
 * CSYJ — PCD/PUP
 *
 * Adaptado a la tabla orientativa:
 * 1A=30, 1B=12, 1C=5, 1D=2, 1E=4, 1F=5, 1G=2
 * 2A=17, 2B=3, 2C=3, 2D=7
 * 3A=6, 3B=2
 * 4 =2
 *
 * Regla positiva:
 * B1 + B2 >= 50
 * Total >= 55
 */

function csyj_to_float(mixed $value): float
{
    return is_numeric($value) ? (float)$value : 0.0;
}

function csyj_clamp(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
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

/* =========================================================
 * BLOQUE 1.A — Publicaciones científicas
 * Máximo CSYJ PCD/PUP: 30
 * ========================================================= */
function calcular_1a_csyj(array $publicaciones): float
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
        $cuartil = strtoupper(trim((string)($pub['cuartil'] ?? '')));
        $subtipo = strtoupper(trim((string)($pub['subtipo_indice'] ?? '')));
        $afinidad = strtolower(trim((string)($pub['afinidad'] ?? 'relacionada')));
        $posicion = strtolower(trim((string)($pub['posicion_autor'] ?? 'intermedio')));
        $numAutores = max(1, (int)($pub['numero_autores'] ?? 1));
        $citas = max(0, (int)($pub['citas'] ?? 0));
        $mismaRevista = (string)($pub['misma_revista_reiterada'] ?? '0') === '1';

        $base = match ($tipoIndice) {
            'JCR', 'SJR' => match ($cuartil) {
                'Q1' => 4.0,
                'Q2' => 3.1,
                'Q3' => 2.2,
                'Q4' => 1.5,
                default => 1.3,
            },
            'ESCI' => 2.0,
            'CIRC' => 1.8,
            'LATINDEX' => 1.5,
            'MIAR' => 1.8,
            default => 1.0,
        };

        if (in_array($subtipo, ['A', 'A+', 'B', 'ICDS', 'TOP'], true)) {
            $base += 0.25;
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
            $numAutores <= 3 => 1.10,
            $numAutores <= 5 => 1.00,
            $numAutores <= 8 => 0.90,
            default => 0.80,
        };

        $bonusCitas = match (true) {
            $citas >= 75 => 0.60,
            $citas >= 40 => 0.40,
            $citas >= 20 => 0.25,
            $citas >= 10 => 0.15,
            $citas >= 5 => 0.08,
            default => 0.00,
        };

        $penalizacionReiteracion = $mismaRevista ? 0.85 : 1.00;

        $puntos = ($base * $factorAfinidad * $factorPosicion * $factorCoautoria * $penalizacionReiteracion) + $bonusCitas;
        $total += $puntos;
    }

    return round(csyj_clamp($total, 0.0, 30.0), 2);
}

/* =========================================================
 * BLOQUE 1.B — Libros y capítulos
 * Máximo CSYJ PCD/PUP: 12
 * ========================================================= */
function calcular_1b_csyj(array $libros): float
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

        if ((string)($item['es_autoedicion'] ?? '0') === '1') {
            continue;
        }

        if ((string)($item['es_acta_congreso'] ?? '0') === '1') {
            continue;
        }

        if ((string)($item['es_labor_edicion'] ?? '0') === '1') {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'capitulo')));
        $nivelEditorial = strtolower(trim((string)($item['nivel_editorial'] ?? 'secundaria')));
        $afinidad = strtolower(trim((string)($item['afinidad'] ?? 'relacionada')));
        $posicion = strtolower(trim((string)($item['posicion_autor'] ?? 'intermedio')));
        $coleccionRelevante = (string)($item['coleccion_relevante'] ?? '0') === '1';

        $base = 0.0;
        if ($tipo === 'libro') {
            $base = match ($nivelEditorial) {
                'spi_alto' => 4.6,
                'spi_medio' => 3.7,
                'bci' => 3.2,
                default => 2.0,
            };
        } else {
            $base = match ($nivelEditorial) {
                'spi_alto' => 1.7,
                'spi_medio' => 1.4,
                'bci' => 1.2,
                default => 0.8,
            };
        }

        if ($coleccionRelevante) {
            $base += 0.25;
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

    return round(csyj_clamp($total, 0.0, 12.0), 2);
}

/* =========================================================
 * BLOQUE 1.C — Proyectos de investigación
 * Máximo CSYJ PCD/PUP: 5
 * ========================================================= */
function calcular_1c_csyj(array $proyectos): float
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

        $certificado = (string)($item['esta_certificado'] ?? '1') === '1';
        if (!$certificado) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo_proyecto'] ?? 'universidad')));
        $rol = strtolower(trim((string)($item['rol'] ?? 'investigador')));
        $dedicacion = strtolower(trim((string)($item['dedicacion'] ?? 'compartida')));
        $anios = max(0.0, csyj_to_float($item['anios_duracion'] ?? 1));

        $base = match ($tipo) {
            'europeo' => 2.5,
            'nacional' => 2.0,
            'autonomico' => 1.2,
            default => 0.8,
        };

        $factorRol = match ($rol) {
            'ip' => 1.20,
            'coip' => 1.05,
            'investigador' => 1.00,
            default => 0.80,
        };

        $factorDedicacion = ($dedicacion === 'completa') ? 1.10 : 1.00;

        $factorDuracion = match (true) {
            $anios >= 4 => 1.15,
            $anios >= 2 => 1.00,
            $anios >= 1 => 0.90,
            default => 0.75,
        };

        $total += $base * $factorRol * $factorDedicacion * $factorDuracion;
    }

    return round(csyj_clamp($total, 0.0, 5.0), 2);
}

/* =========================================================
 * BLOQUE 1.D — Transferencia
 * Máximo CSYJ PCD/PUP: 2
 * ========================================================= */
function calcular_1d_csyj(array $transferencia): float
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

    return round(csyj_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 1.E — Dirección de tesis
 * Máximo CSYJ PCD/PUP: 4
 * ========================================================= */
function calcular_1e_csyj(array $tesis): float
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
        $mencion = (string)($item['mencion_internacional'] ?? '0') === '1';

        $base = 0.0;
        if ($estado === 'defendida') {
            $base = 1.8;
        } elseif ($estado === 'en_proceso') {
            $base = 0.7;
        }

        if ($mencion) {
            $base += 0.2;
        }

        if ($rol === 'codirector') {
            $base *= 0.70;
        }

        $total += $base;
    }

    return round(csyj_clamp($total, 0.0, 4.0), 2);
}

/* =========================================================
 * BLOQUE 1.F — Congresos
 * Máximo CSYJ PCD/PUP: 5
 * ========================================================= */
function calcular_1f_csyj(array $congresos): float
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

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'comunicacion')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'nacional')));
        $invitacion = (string)($item['por_invitacion'] ?? '0') === '1';
        $numeroMismo = max(1, (int)($item['numero_mismo_congreso'] ?? 1));

        $base = match ($tipo) {
            'ponencia' => 0.45,
            'comunicacion' => 0.35,
            'poster' => 0.18,
            default => 0.20,
        };

        $factorAmbito = match ($ambito) {
            'internacional' => 1.25,
            'nacional' => 1.00,
            'regional' => 0.75,
            default => 0.55,
        };

        $bonusInvitacion = $invitacion ? 0.12 : 0.00;

        $factorMismoCongreso = match (true) {
            $numeroMismo <= 2 => 1.00,
            default => 0.50,
        };

        $total += (($base * $factorAmbito) + $bonusInvitacion) * $factorMismoCongreso;
    }

    return round(csyj_clamp($total, 0.0, 5.0), 2);
}

/* =========================================================
 * BLOQUE 1.G — Otros méritos de investigación
 * Máximo CSYJ PCD/PUP: 2
 * ========================================================= */
function calcular_1g_csyj(array $otros): float
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
            'premio_investigacion' => 0.60,
            'consejo_redaccion' => 0.40,
            'revisor_revista' => 0.30,
            'coordinacion_libro' => 0.30,
            'organizacion_investigacion' => 0.30,
            'tribunal_tesis' => 0.25,
            'grupo_investigacion' => 0.20,
            'publicacion_tesis' => 0.20,
            'resena' => 0.15,
            'divulgacion' => 0.12,
            default => 0.20,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.80,
        };

        $total += $base * $factor;
    }

    return round(csyj_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 2.A — Docencia universitaria
 * Máximo CSYJ PCD/PUP: 17
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

        $esValido = $item['es_valido'] ?? 1;
        if ((string)$esValido === '0') {
            continue;
        }

        $acreditada = (string)($item['acreditada'] ?? '1') === '1';
        if (!$acreditada) {
            continue;
        }

        $horas += max(0.0, csyj_to_float($item['horas'] ?? 0));

        $responsabilidad = strtolower(trim((string)($item['responsabilidad'] ?? 'media')));
        $nivel = strtolower(trim((string)($item['nivel'] ?? 'grado')));

        $bonusResponsabilidad += match ($responsabilidad) {
            'alta' => 0.25,
            'media' => 0.15,
            default => 0.05,
        };

        if ($nivel === 'master') {
            $bonusMaster += 0.20;
        }
    }

    $puntosHoras = min(17.0, ($horas / 450.0) * 17.0);
    $total = $puntosHoras + min(0.8, $bonusResponsabilidad) + min(0.6, $bonusMaster);

    return round(csyj_clamp($total, 0.0, 17.0), 2);
}

/* =========================================================
 * BLOQUE 2.B — Evaluación docente
 * Máximo CSYJ PCD/PUP: 3
 * ========================================================= */
function calcular_2b_csyj(array $evaluaciones): float
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

        $sistema = strtolower(trim((string)($item['sistema'] ?? 'encuesta')));
        $calificacion = strtolower(trim((string)($item['calificacion'] ?? 'favorable')));
        $numero = max(1, (int)($item['numero'] ?? 1));

        $base = match ($calificacion) {
            'excelente' => 1.10,
            'muy_favorable' => 0.85,
            'favorable' => 0.60,
            default => 0.35,
        };

        if ($sistema === 'docentia') {
            $base += 0.10;
        }

        $total += $base * $numero;
    }

    return round(csyj_clamp($total, 0.0, 3.0), 2);
}

/* =========================================================
 * BLOQUE 2.C — Formación docente
 * Máximo CSYJ PCD/PUP: 3
 * ========================================================= */
function calcular_2c_csyj(array $actividades): float
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

        $orientadoDocencia = (string)($item['orientado_docencia'] ?? '1') === '1';
        if (!$orientadoDocencia) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'seminario_docente')));
        $ambito = strtolower(trim((string)($item['ambito'] ?? 'local')));

        $base = match ($tipo) {
            'curso_docente' => 0.45,
            'recibir_formacion_docente' => 0.30,
            default => 0.35,
        };

        $factorAmbito = match ($ambito) {
            'internacional' => 1.20,
            'nacional' => 1.00,
            default => 0.80,
        };

        $total += $base * $factorAmbito;
    }

    return round(csyj_clamp($total, 0.0, 3.0), 2);
}

/* =========================================================
 * BLOQUE 2.D — Material docente / innovación
 * Máximo CSYJ PCD/PUP: 7
 * ========================================================= */
function calcular_2d_csyj(array $materiales): float
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

        $noApuntes = (string)($item['no_apuntes_guias'] ?? '1') === '1';
        if (!$noApuntes) {
            continue;
        }

        $tipo = strtolower(trim((string)($item['tipo'] ?? 'material_publicado')));
        $isbn = (string)($item['isbn_issn'] ?? '0') === '1';
        $relevancia = strtolower(trim((string)($item['relevancia'] ?? 'media')));

        $base = match ($tipo) {
            'publicacion_docente' => 1.60,
            'proyecto_innovacion' => 1.40,
            'material_publicado' => 1.10,
            default => 0.80,
        };

        if ($isbn) {
            $base += 0.25;
        }

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base * $factor;
    }

    return round(csyj_clamp($total, 0.0, 7.0), 2);
}

/* =========================================================
 * BLOQUE 3.A — Formación académica
 * Máximo CSYJ PCD/PUP: 6
 * ========================================================= */
function calcular_3a_csyj(array $formacion): float
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
        $duracion = max(0.0, csyj_to_float($item['duracion'] ?? 1));
        $posteriorGrado = (string)($item['posterior_grado'] ?? '1') === '1';

        $base = 0.0;
        switch ($tipo) {
            case 'doctorado_internacional':
                $base = 1.60;
                break;
            case 'beca_competitiva':
                $base = $posteriorGrado ? 1.30 : 0.00;
                break;
            case 'ayuda':
                $base = $posteriorGrado ? 0.70 : 0.00;
                break;
            case 'master':
                $base = 0.90;
                break;
            case 'curso_especializacion':
                $base = min(0.70, 0.12 * $duracion);
                break;
            case 'movilidad':
                $base = min(1.30, 0.25 * $duracion);
                break;
            default:
                $base = 0.0;
        }

        $total += $base;
    }

    return round(csyj_clamp($total, 0.0, 6.0), 2);
}

/* =========================================================
 * BLOQUE 3.B — Experiencia profesional
 * Máximo CSYJ PCD/PUP: 2
 * ========================================================= */
function calcular_3b_csyj(array $experiencia): float
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

        $documentada = (string)($item['documentada'] ?? '1') === '1';
        if (!$documentada) {
            continue;
        }

        $anios = max(0.0, csyj_to_float($item['anios'] ?? 0));
        $relacion = strtolower(trim((string)($item['relacion'] ?? 'media')));

        $factorRelacion = match ($relacion) {
            'alta' => 1.00,
            'media' => 0.70,
            default => 0.40,
        };

        $total += min(1.0, 0.35 * $anios) * $factorRelacion;
    }

    return round(csyj_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * BLOQUE 4 — Otros méritos
 * Máximo CSYJ PCD/PUP: 2
 * ========================================================= */
function calcular_4_csyj(array $otros): float
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
            'gestion' => 0.45,
            'cargo_unipersonal' => 0.50,
            'docencia_no_reglada' => 0.35,
            'tfg_tfm' => 0.25,
            'tutor_uned' => 0.30,
            'curso_extension' => 0.25,
            default => 0.20,
        };

        $factor = match ($relevancia) {
            'alta' => 1.20,
            'media' => 1.00,
            default => 0.75,
        };

        $total += $base * $factor;
    }

    return round(csyj_clamp($total, 0.0, 2.0), 2);
}

/* =========================================================
 * DIAGNÓSTICO Y ASESOR ORIENTATIVO
 * ========================================================= */
function csyj_round(float $value): float
{
    return round($value, 2);
}

function csyj_count_valid(array $items): int
{
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $esValida = $item['es_valida'] ?? $item['es_valido'] ?? 1;
        if ((string)$esValida === '0') {
            continue;
        }
        $count++;
    }
    return $count;
}

function csyj_objetivos_orientativos(): array
{
    return [
        'B1' => 32.0,
        'B2' => 15.0,
        'B3' => 3.0,
        'B4' => 1.0,

        '1A' => 20.0,
        '1B' => 4.0,
        '1C' => 2.5,
        '1D' => 1.0,
        '1E' => 1.5,
        '1F' => 2.0,
        '1G' => 0.8,

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

function csyj_clasificar_nivel(float $actual, float $objetivo): string
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

function csyj_detectar_perfil(array $resultado): string
{
    $b1 = (float)($resultado['bloque_1'] ?? 0.0);
    $b2 = (float)($resultado['bloque_2'] ?? 0.0);
    $b3 = (float)($resultado['bloque_3'] ?? 0.0);
    $b4 = (float)($resultado['bloque_4'] ?? 0.0);
    $total12 = (float)($resultado['total_b1_b2'] ?? 0.0);
    $total = (float)($resultado['total_final'] ?? 0.0);

    if ($b1 >= 35.0 && $b2 < 10.0) {
        return 'Perfil investigador fuerte con docencia insuficiente';
    }

    if ($b2 >= 15.0 && $b1 < 28.0) {
        return 'Perfil docente razonable con investigación insuficiente';
    }

    if ($b1 >= 30.0 && $b2 >= 12.0 && $total12 >= 50.0 && $total >= 55.0) {
        return 'Perfil equilibrado y competitivo para acreditación en CSYJ';
    }

    if ($b1 >= 24.0 && (float)($resultado['puntuacion_1c'] ?? 0.0) < 2.0) {
        return 'Perfil investigador con proyectos competitivos todavía escasos';
    }

    if ($b1 < 20.0 && $b2 < 10.0) {
        return 'Perfil aún inmaduro para acreditación en Ciencias Sociales y Jurídicas';
    }

    if (($b3 + $b4) >= 4.0 && $total12 < 50.0) {
        return 'Perfil con méritos complementarios aceptables, pero núcleo B1+B2 insuficiente';
    }

    return 'Perfil mixto con fortalezas parciales y necesidad de refuerzo estratégico';
}

function csyj_generar_diagnostico(array $datos, array $resultado): array
{
    $obj = csyj_objetivos_orientativos();

    $b1 = (float)($resultado['bloque_1'] ?? 0.0);
    $b2 = (float)($resultado['bloque_2'] ?? 0.0);
    $b3 = (float)($resultado['bloque_3'] ?? 0.0);
    $b4 = (float)($resultado['bloque_4'] ?? 0.0);
    $total12 = (float)($resultado['total_b1_b2'] ?? 0.0);
    $total = (float)($resultado['total_final'] ?? 0.0);

    $deficit1 = max(0.0, 50.0 - $total12);
    $deficit2 = max(0.0, 55.0 - $total);

    $fortalezas = [];
    $debilidades = [];
    $alertas = [];

    if ((float)($resultado['puntuacion_1a'] ?? 0.0) >= $obj['1A']) {
        $fortalezas[] = 'La producción científica principal (1A) presenta un peso competitivo.';
    } else {
        $debilidades[] = 'La producción científica principal (1A) aún necesita refuerzo.';
    }

    if ((float)($resultado['puntuacion_2a'] ?? 0.0) >= $obj['2A']) {
        $fortalezas[] = 'La docencia universitaria acreditable alcanza un nivel razonable.';
    } else {
        $debilidades[] = 'La docencia universitaria acreditable (2A) es insuficiente para consolidar el bloque docente.';
    }

    if ((float)($resultado['puntuacion_1c'] ?? 0.0) >= $obj['1C']) {
        $fortalezas[] = 'Existe respaldo aceptable en proyectos competitivos.';
    } else {
        $debilidades[] = 'Conviene reforzar proyectos competitivos y participación certificada.';
    }

    if ((float)($resultado['puntuacion_2b'] ?? 0.0) <= 0.0) {
        $alertas[] = 'No consta evaluación docente formal relevante.';
    }

    if ((float)($resultado['puntuacion_2d'] ?? 0.0) < $obj['2D']) {
        $alertas[] = 'La innovación o el material docente aportan poco al bloque 2.';
    }

    if ((float)($resultado['puntuacion_1f'] ?? 0.0) < 1.0) {
        $alertas[] = 'La visibilidad en congresos, seminarios o foros científicos aún es reducida.';
    }

    if ($deficit1 > 0.0) {
        $alertas[] = 'No se cumple la regla principal B1 + B2 ≥ 50.';
    }

    if ($deficit2 > 0.0) {
        $alertas[] = 'No se cumple la regla total ≥ 55.';
    }

    return [
        'version' => 'conservadora_csyj_v2_diagnostico_asesor',
        'perfil_detectado' => csyj_detectar_perfil($resultado),
        'reglas' => [
            [
                'nombre' => 'Regla principal B1 + B2 ≥ 50',
                'valor_actual' => csyj_round($total12),
                'objetivo' => 50.0,
                'deficit' => csyj_round($deficit1),
                'cumple' => (bool)($resultado['cumple_regla_1'] ?? false),
            ],
            [
                'nombre' => 'Regla total final ≥ 55',
                'valor_actual' => csyj_round($total),
                'objetivo' => 55.0,
                'deficit' => csyj_round($deficit2),
                'cumple' => (bool)($resultado['cumple_regla_2'] ?? false),
            ],
        ],
        'bloques' => [
            'B1' => [
                'actual' => csyj_round($b1),
                'objetivo_orientativo' => $obj['B1'],
                'deficit' => csyj_round(max(0.0, $obj['B1'] - $b1)),
                'nivel' => csyj_clasificar_nivel($b1, $obj['B1']),
            ],
            'B2' => [
                'actual' => csyj_round($b2),
                'objetivo_orientativo' => $obj['B2'],
                'deficit' => csyj_round(max(0.0, $obj['B2'] - $b2)),
                'nivel' => csyj_clasificar_nivel($b2, $obj['B2']),
            ],
            'B3' => [
                'actual' => csyj_round($b3),
                'objetivo_orientativo' => $obj['B3'],
                'deficit' => csyj_round(max(0.0, $obj['B3'] - $b3)),
                'nivel' => csyj_clasificar_nivel($b3, $obj['B3']),
            ],
            'B4' => [
                'actual' => csyj_round($b4),
                'objetivo_orientativo' => $obj['B4'],
                'deficit' => csyj_round(max(0.0, $obj['B4'] - $b4)),
                'nivel' => csyj_clasificar_nivel($b4, $obj['B4']),
            ],
        ],
        'conteos' => [
            'publicaciones' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_1'), 'publicaciones')),
            'libros' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_1'), 'libros')),
            'proyectos' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_1'), 'proyectos')),
            'transferencia' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_1'), 'transferencia')),
            'tesis' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_1'), 'tesis_dirigidas')),
            'congresos' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_1'), 'congresos')),
            'docencia' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_2'), 'docencia_universitaria')),
            'evaluacion_docente' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_2'), 'evaluacion_docente')),
            'formacion_docente' => csyj_count_valid(csyj_get_lista(csyj_get_bloque($datos, 'bloque_2'), 'formacion_docente')),
        ],
        'fortalezas' => array_values(array_unique($fortalezas)),
        'debilidades' => array_values(array_unique($debilidades)),
        'alertas' => array_values(array_unique($alertas)),
    ];
}

function csyj_generar_asesor(array $resultado): array
{
    $acciones = [];

    $p1a = (float)($resultado['puntuacion_1a'] ?? 0.0);
    $p1c = (float)($resultado['puntuacion_1c'] ?? 0.0);
    $p2a = (float)($resultado['puntuacion_2a'] ?? 0.0);
    $p2b = (float)($resultado['puntuacion_2b'] ?? 0.0);
    $p2d = (float)($resultado['puntuacion_2d'] ?? 0.0);
    $total12 = (float)($resultado['total_b1_b2'] ?? 0.0);
    $total = (float)($resultado['total_final'] ?? 0.0);

    if ($p1a < 20.0) {
        $acciones[] = [
            'titulo' => 'Reforzar publicaciones indexadas de impacto',
            'detalle' => 'Prioriza artículos alineados con el área, mejor posicionados por índice y cuartil, y con autoría relevante.',
            'impacto_estimado' => '+2 a +6 puntos en 1A a medio plazo',
        ];
    }

    if ($p1c < 2.5) {
        $acciones[] = [
            'titulo' => 'Aumentar participación en proyectos competitivos',
            'detalle' => 'Intenta consolidar participación documentada en proyectos nacionales o internacionales y reflejar rol y duración.',
            'impacto_estimado' => '+1 a +3 puntos en 1C',
        ];
    }

    if ($p2a < 12.0) {
        $acciones[] = [
            'titulo' => 'Elevar el volumen de docencia universitaria acreditable',
            'detalle' => 'Conviene incrementar horas, cursos o diversidad de asignaturas con certificación oficial.',
            'impacto_estimado' => '+2 a +5 puntos en 2A',
        ];
    }

    if ($p2b <= 0.0) {
        $acciones[] = [
            'titulo' => 'Incorporar evaluaciones docentes positivas',
            'detalle' => 'Aporta encuestas, DOCENTIA u otros indicadores institucionales de calidad docente siempre que estén certificados.',
            'impacto_estimado' => '+0,5 a +2 puntos en 2B',
        ];
    }

    if ($p2d < 1.5) {
        $acciones[] = [
            'titulo' => 'Mejorar material docente e innovación educativa',
            'detalle' => 'Refuerza publicaciones docentes, manuales, coordinación de innovación o aportaciones al EEES.',
            'impacto_estimado' => '+1 a +3 puntos en 2D',
        ];
    }

    if ($acciones === []) {
        $acciones[] = [
            'titulo' => 'Mantener consolidación del perfil',
            'detalle' => 'El expediente ya muestra una base competitiva; conviene sostener producción y estabilidad docente.',
            'impacto_estimado' => 'Mejora incremental y consolidación del perfil',
        ];
    }

    $simulaciones = [
        [
            'escenario' => 'Añadir una mejora docente moderada',
            'efecto_estimado' => 'Suponiendo un refuerzo de +2 en docencia universitaria y +1 en evaluación/material docente.',
            'nuevo_b1_b2_aprox' => csyj_round($total12 + 3.0),
            'nuevo_total_aprox' => csyj_round($total + 3.0),
        ],
        [
            'escenario' => 'Añadir una mejora investigadora moderada',
            'efecto_estimado' => 'Suponiendo +3 en publicaciones/proyectos del bloque investigador.',
            'nuevo_b1_b2_aprox' => csyj_round($total12 + 3.0),
            'nuevo_total_aprox' => csyj_round($total + 3.0),
        ],
    ];

    return [
        'version' => 'asesor_orientativo_csyj_v2',
        'resumen' => 'El asesor orientativo propone refuerzos priorizando el cierre de déficits en publicaciones, proyectos y docencia acreditable para acercar el expediente a los umbrales de 50 y 55 puntos.',
        'acciones' => $acciones,
        'simulaciones' => $simulaciones,
    ];
}

/* =========================================================
 * FUNCIÓN PRINCIPAL
 * ========================================================= */
function evaluar_expediente_csyj(array $json): array
{
    $bloque1 = csyj_get_bloque($json, 'bloque_1');
    $bloque2 = csyj_get_bloque($json, 'bloque_2');
    $bloque3 = csyj_get_bloque($json, 'bloque_3');
    $bloque4 = $json['bloque_4'] ?? [];

    $p1a = csyj_round(calcular_1a_csyj(csyj_get_lista($bloque1, 'publicaciones')));
    $p1b = csyj_round(calcular_1b_csyj(csyj_get_lista($bloque1, 'libros')));
    $p1c = csyj_round(calcular_1c_csyj(csyj_get_lista($bloque1, 'proyectos')));
    $p1d = csyj_round(calcular_1d_csyj(csyj_get_lista($bloque1, 'transferencia')));
    $p1e = csyj_round(calcular_1e_csyj(csyj_get_lista($bloque1, 'tesis_dirigidas')));
    $p1f = csyj_round(calcular_1f_csyj(csyj_get_lista($bloque1, 'congresos')));
    $p1g = csyj_round(calcular_1g_csyj(csyj_get_lista($bloque1, 'otros_meritos_investigacion')));
    $b1 = csyj_round(csyj_clamp($p1a + $p1b + $p1c + $p1d + $p1e + $p1f + $p1g, 0.0, 60.0));

    $p2a = csyj_round(calcular_2a_csyj(csyj_get_lista($bloque2, 'docencia_universitaria')));
    $p2b = csyj_round(calcular_2b_csyj(csyj_get_lista($bloque2, 'evaluacion_docente')));
    $p2c = csyj_round(calcular_2c_csyj(csyj_get_lista($bloque2, 'formacion_docente')));
    $p2d = csyj_round(calcular_2d_csyj(csyj_get_lista($bloque2, 'material_docente')));
    $b2 = csyj_round(csyj_clamp($p2a + $p2b + $p2c + $p2d, 0.0, 30.0));

    $p3a = csyj_round(calcular_3a_csyj(csyj_get_lista($bloque3, 'formacion_academica')));
    $p3b = csyj_round(calcular_3b_csyj(csyj_get_lista($bloque3, 'experiencia_profesional')));
    $b3 = csyj_round(csyj_clamp($p3a + $p3b, 0.0, 8.0));

    $p4 = csyj_round(calcular_4_csyj(is_array($bloque4) ? $bloque4 : []));
    $b4 = csyj_round(csyj_clamp($p4, 0.0, 2.0));

    $totalB1B2 = csyj_round($b1 + $b2);
    $totalFinal = csyj_round($b1 + $b2 + $b3 + $b4);

    $cumple1 = $totalB1B2 >= 50.0;
    $cumple2 = $totalFinal >= 55.0;
    $resultadoTexto = ($cumple1 && $cumple2) ? 'POSITIVA' : 'NEGATIVA';

    $resultado = [
        'puntuacion_1a' => $p1a,
        'puntuacion_1b' => $p1b,
        'puntuacion_1c' => $p1c,
        'puntuacion_1d' => $p1d,
        'puntuacion_1e' => $p1e,
        'puntuacion_1f' => $p1f,
        'puntuacion_1g' => $p1g,
        'bloque_1' => $b1,

        'puntuacion_2a' => $p2a,
        'puntuacion_2b' => $p2b,
        'puntuacion_2c' => $p2c,
        'puntuacion_2d' => $p2d,
        'bloque_2' => $b2,

        'puntuacion_3a' => $p3a,
        'puntuacion_3b' => $p3b,
        'bloque_3' => $b3,

        'puntuacion_4' => $p4,
        'bloque_4' => $b4,

        'total_b1_b2' => $totalB1B2,
        'total_final' => $totalFinal,

        'cumple_regla_1' => $cumple1 ? 1 : 0,
        'cumple_regla_2' => $cumple2 ? 1 : 0,
        'resultado' => $resultadoTexto,

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
            '4' => $p4,
        ],
        'totales' => [
            'bloque_1' => $b1,
            'bloque_2' => $b2,
            'bloque_3' => $b3,
            'bloque_4' => $b4,
            'total_b1_b2' => $totalB1B2,
            'total_final' => $totalFinal,
            'global' => $totalFinal,
        ],
        'decision' => [
            'cumple_regla_1' => $cumple1,
            'cumple_regla_2' => $cumple2,
            'evaluacion_positiva' => $cumple1 && $cumple2,
            'resultado' => $resultadoTexto,
        ],
    ];

    $resultado['diagnostico'] = csyj_generar_diagnostico($json, $resultado);
    $resultado['asesor'] = csyj_generar_asesor($resultado);

    return $resultado;
}

/**
 * Alias de compatibilidad
 */
function evaluar_expediente(array $json): array
{
    return evaluar_expediente_csyj($json);
}
