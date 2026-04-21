<?php

declare(strict_types=1);

class FecytCvnExtractor
{
    public function extraer(string $texto): array
    {
        $texto = $this->normalizarTexto($texto);

        return [
            'bloque_1' => [
                'publicaciones' => $this->extraerPublicaciones($texto),
                'libros' => $this->extraerLibros($texto),
                'proyectos' => $this->extraerProyectos($texto),
                'transferencia' => $this->extraerTransferencia($texto),
                'tesis_dirigidas' => $this->extraerTesisDirigidas($texto),
                'congresos' => $this->extraerCongresos($texto),
                'otros_meritos_investigacion' => $this->extraerOtrosMeritosInvestigacion($texto),
            ],
            'bloque_2' => [
                'docencia_universitaria' => $this->extraerDocenciaUniversitaria($texto),
                'evaluacion_docente' => $this->extraerEvaluacionDocente($texto),
                'formacion_docente' => $this->extraerFormacionDocente($texto),
                'material_docente' => $this->extraerMaterialDocente($texto),
            ],
            'bloque_3' => [
                'formacion_academica' => $this->extraerFormacionAcademica($texto),
                'experiencia_profesional' => $this->extraerExperienciaProfesional($texto),
            ],
            'bloque_4' => $this->extraerBloque4($texto),
            'metadatos_extraccion' => [
                'comite' => 'EXPERIMENTALES',
                'subcomite' => 'CVN_FECYT',
                'archivo_pdf' => null,
                'fecha_extraccion' => date('c'),
                'version_esquema' => '2.2-cvn-fecyt',
                'requiere_revision_manual' => true,
            ],
        ];
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n"], $texto);
        $texto = preg_replace('/\n\s*\d{1,3}\s*\n\s*[a-f0-9]{32}\s*\n/iu', "\n", $texto) ?? $texto;
        $texto = preg_replace('/\n\s*[a-f0-9]{32}\s*\n/iu', "\n", $texto) ?? $texto;
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    private function extraerPublicaciones(string $texto): array
    {
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Publicaciones, documentos científicos y técnicos', 'Trabajos presentados en congresos nacionales o internacionales'],
            ['Publicaciones, documentos cientificos y tecnicos', 'Trabajos presentados en congresos nacionales o internacionales'],
        ]);

        $items = [];
        foreach ($this->extraerBloquesNumerados($seccion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }

            $tipoProduccion = $this->capturarCampo($bloqueLimpio, 'Tipo de producción');
            $tipoSoporte = $this->capturarCampo($bloqueLimpio, 'Tipo de soporte');
            if (!$this->contieneAlgunTermino((string)$tipoProduccion, ['artículo', 'articulo']) || !$this->contieneAlgunTermino((string)$tipoSoporte, ['revista'])) {
                continue;
            }

            $numAutores = $this->capturarEntero($bloqueLimpio, 'Nº total de autores')
                ?? $this->capturarEntero($bloqueLimpio, 'No total de autores')
                ?? $this->capturarEntero($bloqueLimpio, 'N total de autores')
                ?? 1;
            $posFirma = $this->capturarEntero($bloqueLimpio, 'Posición de firma')
                ?? $this->capturarEntero($bloqueLimpio, 'Posicion de firma')
                ?? 1;
            $correspondencia = $this->capturarCampo($bloqueLimpio, 'Autor de correspondencia');
            $anio = $this->detectarAnioPublicacion($bloqueLimpio);
            $anioActual = (int)date('Y');
            $tipoIndice = $this->inferirTipoIndicePublicacion($bloqueLimpio);
            $cuartil = $this->inferirCuartilPublicacion($bloqueLimpio);

            $items[] = [
                'id' => 'pub_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'tipo' => 'articulo',
                'es_valida' => true,
                'es_divulgacion' => false,
                'es_docencia' => false,
                'es_acta_congreso' => false,
                'es_informe_proyecto' => false,
                'tipo_indice' => $tipoIndice,
                'cuartil' => $cuartil,
                'tercil' => $this->cuartilATercil($cuartil),
                'es_area_matematicas' => $this->esAreaMatematicas($bloqueLimpio),
                'afinidad' => $this->detectarAfinidad($bloqueLimpio),
                'posicion_autor' => $this->mapearPosicionAutor($posFirma, $numAutores, $correspondencia),
                'numero_autores' => $numAutores,
                'citas' => 0,
                'anios_desde_publicacion' => $anio !== null ? max(0, $anioActual - $anio) : 3,
                'numero_trabajos_misma_revista' => 1,
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.78,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerLibros(string $texto): array
    {
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Libros y capítulos de libro', 'Proyectos de I+D+i financiados en convocatorias competitivas de Administraciones o entidades públicas y privadas'],
            ['Libros y capitulos de libro', 'Proyectos de I+D+i financiados en convocatorias competitivas de Administraciones o entidades públicas y privadas'],
            ['Libros y capítulos de libro', 'Trabajos presentados en congresos nacionales o internacionales'],
        ]);

        $items = [];
        foreach ($this->extraerBloquesNumerados($seccion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }

            $tipo = 'libro';
            if ($this->contieneAlgunTermino($bloqueLimpio, ['capítulo', 'capitulo'])) {
                $tipo = 'capitulo';
            }

            $items[] = [
                'id' => 'lib_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'tipo' => $tipo,
                'es_valido' => true,
                'es_libro_investigacion' => true,
                'es_autoedicion' => false,
                'es_acta_congreso' => false,
                'es_labor_edicion' => false,
                'nivel_editorial' => $this->detectarNivelEditorial($bloqueLimpio),
                'nivel_coleccion' => 'general',
                'afinidad' => $this->detectarAfinidad($bloqueLimpio),
                'numero_autores' => max(1, $this->contarAutoresPorPrimeraLinea($bloqueLimpio)),
                'posicion_autor' => 'intermedio',
                'nivel_resenas' => 'sin_datos',
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.65,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerProyectos(string $texto): array
    {
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Proyectos de I+D+i financiados en convocatorias competitivas de Administraciones o entidades públicas y privadas', 'Actividades científicas y tecnológicas'],
            ['Proyectos de I+D+i financiados en convocatorias competitivas de Administraciones o entidades publicas y privadas', 'Actividades científicas y tecnológicas'],
            ['Proyectos de I+D+i financiados en convocatorias competitivas', 'Actividades científicas y tecnológicas'],
        ]);

        $items = [];
        foreach ($this->extraerBloquesNumerados($seccion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }

            $ambito = mb_strtolower((string)$this->capturarCampo($bloqueLimpio, 'Ámbito geográfico'), 'UTF-8');
            if ($ambito === '') {
                $ambito = mb_strtolower((string)$this->capturarCampo($bloqueLimpio, 'Ambito geográfico'), 'UTF-8');
            }
            $rolFuente = (string)$this->capturarCampo($bloqueLimpio, 'Grado de contribución');
            if ($rolFuente === '') {
                $rolFuente = (string)$this->capturarCampo($bloqueLimpio, 'Grado de contribucion');
            }

            $items[] = [
                'id' => 'proy_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'esta_certificado' => true,
                'tipo_proyecto' => $this->mapearTipoProyecto($ambito),
                'rol' => $this->mapearRolProyecto($rolFuente, $bloqueLimpio),
                'rol_detectado' => $this->mapearRolProyecto($rolFuente, $bloqueLimpio),
                'es_contrato_laboral' => false,
                'grado_tipo' => null,
                'grado_otros' => $rolFuente,
                'dedicacion' => 'completa',
                'anios_duracion' => $this->detectarDuracionEnAnios($bloqueLimpio),
                'continuidad' => false,
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.90,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerTransferencia(string $texto): array
    {
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Patentes', 'Trabajos presentados en congresos nacionales o internacionales'],
            ['Transferencia tecnológica', 'Trabajos presentados en congresos nacionales o internacionales'],
            ['Transferencia tecnologica', 'Trabajos presentados en congresos nacionales o internacionales'],
        ]);

        $items = [];
        foreach ($this->extraerBloquesNumerados($seccion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }

            $items[] = [
                'id' => 'trans_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $this->detectarTipoTransferencia($bloqueLimpio),
                'impacto_externo' => true,
                'liderazgo' => $this->contieneAlgunTermino($bloqueLimpio, ['investigador principal', 'responsable', 'titular']),
                'participacion_menor' => false,
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerTesisDirigidas(string $texto): array
    {
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Dirección de tesis doctorales y/o trabajos fin de estudios', 'Pluralidad, interdisciplinariedad y complejidad docente'],
            ['Dirección de tesis doctorales y/o trabajos fin de estudios', 'Experiencia científica y tecnológica'],
        ]);

        if ($seccion === '') {
            return [];
        }

        $tipoProyecto = mb_strtolower((string)$this->capturarCampo($seccion, 'Tipo de proyecto'), 'UTF-8');
        if ($tipoProyecto !== '' && !$this->contieneAlgunTermino($tipoProyecto, ['tesis doctoral', 'doctorado'])) {
            return [];
        }

        $codirector = $this->capturarCampo($seccion, 'Codirector/a tesis');

        return [[
            'id' => 'tesis_001',
            'es_valido' => true,
            'tipo' => 'dirigida',
            'proyecto_aprobado' => true,
            'doctorado_europeo' => false,
            'mencion_calidad' => false,
            'numero_codirectores' => $codirector !== null && trim($codirector) !== '' ? 1 : 0,
            'fuente_texto' => trim($seccion),
            'confianza_extraccion' => 0.80,
            'requiere_revision' => true,
        ]];
    }

    private function extraerCongresos(string $texto): array
    {
        $items = [];

        $seccionCongresos = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Trabajos presentados en congresos nacionales o internacionales', 'Trabajos presentados en jornadas, seminarios, talleres de trabajo y/o cursos nacionales o internacionales'],
            ['Trabajos presentados en congresos nacionales o internacionales', 'Otros méritos'],
            ['Trabajos presentados en congresos nacionales o internacionales', 'Otros meritos'],
        ]);

        foreach ($this->extraerBloquesNumerados($seccionCongresos) as $idx => $bloque) {
            $items[] = $this->crearCongresoDesdeBloque($bloque, $idx + 1);
        }

        $seccionEventos = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Trabajos presentados en jornadas, seminarios, talleres de trabajo y/o cursos nacionales o internacionales', 'Otros méritos'],
            ['Trabajos presentados en jornadas, seminarios, talleres de trabajo y/o cursos nacionales o internacionales', 'Otros meritos'],
        ]);

        $offset = count($items);
        foreach ($this->extraerBloquesNumerados($seccionEventos) as $idx => $bloque) {
            $items[] = $this->crearCongresoDesdeBloque($bloque, $offset + $idx + 1, true);
        }

        return array_values(array_filter($items, static fn($item) => is_array($item)));
    }

    private function crearCongresoDesdeBloque(string $bloque, int $numero, bool $fallbackNacional = false): ?array
    {
        $bloqueLimpio = trim($bloque);
        if ($bloqueLimpio === '') {
            return null;
        }

        $ambito = (string)$this->capturarCampo($bloqueLimpio, 'Ámbito geográfico');
        if ($ambito === '') {
            $ambito = $fallbackNacional ? 'Nacional' : 'Internacional';
        }

        $tipoParticipacion = (string)$this->capturarCampo($bloqueLimpio, 'Tipo de participación');
        if ($tipoParticipacion === '') {
            $tipoParticipacion = (string)$this->capturarCampo($bloqueLimpio, 'Tipo de participacion');
        }

        $nombreEvento = $this->capturarCampo($bloqueLimpio, 'Nombre del congreso')
            ?? $this->capturarCampo($bloqueLimpio, 'Nombre del evento')
            ?? ('evento_' . $numero);

        $fecha = $this->capturarCampo($bloqueLimpio, 'Fecha de celebración')
            ?? $this->capturarCampo($bloqueLimpio, 'Fecha de celebracion');

        return [
            'id' => 'cong_' . str_pad((string)$numero, 3, '0', STR_PAD_LEFT),
            'es_valido' => true,
            'ambito' => $this->mapearAmbitoCongreso($ambito),
            'tipo' => $this->mapearTipoCongreso($tipoParticipacion),
            'proceso_selectivo' => true,
            'id_evento' => $this->normalizarEventoId($nombreEvento . '|' . (string)$fecha),
            'fuente_texto' => $bloqueLimpio,
            'confianza_extraccion' => 0.88,
            'requiere_revision' => true,
        ];
    }

    private function extraerOtrosMeritosInvestigacion(string $texto): array
    {
        $items = [];
        $estancias = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Estancias en centros públicos o privados', 'Ayudas y becas obtenidas'],
            ['Estancias en centros publicos o privados', 'Ayudas y becas obtenidas'],
        ]);

        if (trim($estancias) !== '') {
            $items[] = [
                'id' => 'oinv_001',
                'es_valido' => true,
                'tipo' => 'estancia',
                'fuente_texto' => trim($estancias),
                'confianza_extraccion' => 0.85,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerDocenciaUniversitaria(string $texto): array
    {
        $items = [];

        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Formación académica impartida', 'Dirección de tesis doctorales y/o trabajos fin de estudios'],
            ['Formación academica impartida', 'Dirección de tesis doctorales y/o trabajos fin de estudios'],
            ['Formación académica impartida', 'Pluralidad, interdisciplinariedad y complejidad docente'],
        ]);

        foreach ($this->extraerBloquesNumerados($seccion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }

            $horas = $this->capturarDecimal($bloqueLimpio, 'Nº de horas/créditos ECTS')
                ?? $this->capturarDecimal($bloqueLimpio, 'No de horas/créditos ECTS')
                ?? $this->capturarDecimal($bloqueLimpio, 'N de horas/créditos ECTS')
                ?? 0.0;
            $titulacion = (string)$this->capturarCampo($bloqueLimpio, 'Titulación universitaria');
            if ($titulacion === '') {
                $titulacion = (string)$this->capturarCampo($bloqueLimpio, 'Titulacion universitaria');
            }

            $items[] = [
                'id' => 'doc_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'horas' => $horas,
                'tipo' => $this->contieneAlgunTermino($titulacion, ['máster', 'master']) ? 'master' : 'grado',
                'etapa' => $this->contieneAlgunTermino($titulacion, ['máster', 'master']) ? 'postgrado' : 'grado',
                'tfg' => 0,
                'tfm' => 0,
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.92,
                'requiere_revision' => true,
            ];
        }

        $direccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Dirección de tesis doctorales y/o trabajos fin de estudios', 'Pluralidad, interdisciplinariedad y complejidad docente'],
            ['Dirección de tesis doctorales y/o trabajos fin de estudios', 'Experiencia científica y tecnológica'],
        ]);
        if (trim($direccion) !== '') {
            $tipoProyecto = mb_strtolower((string)$this->capturarCampo($direccion, 'Tipo de proyecto'), 'UTF-8');
            if ($this->contieneAlgunTermino($tipoProyecto, ['tesina', 'tfm', 'trabajo fin de máster', 'trabajo fin de master'])) {
                $items[] = [
                    'id' => 'doc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'horas' => 0,
                    'tipo' => 'master',
                    'etapa' => 'postgrado',
                    'tfg' => 0,
                    'tfm' => 1,
                    'fuente_texto' => trim($direccion),
                    'confianza_extraccion' => 0.72,
                    'requiere_revision' => true,
                ];
            } elseif ($this->contieneAlgunTermino($tipoProyecto, ['tfg', 'trabajo fin de grado'])) {
                $items[] = [
                    'id' => 'doc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'horas' => 0,
                    'tipo' => 'grado',
                    'etapa' => 'grado',
                    'tfg' => 1,
                    'tfm' => 0,
                    'fuente_texto' => trim($direccion),
                    'confianza_extraccion' => 0.72,
                    'requiere_revision' => true,
                ];
            }
        }

        return $items;
    }

    private function extraerEvaluacionDocente(string $texto): array
    {
        $items = [];

        if (preg_match('/evaluaci[oó]n docente.*?(excelente|muy favorable|favorable|positivo)/iu', $texto, $m)) {
            $resultado = mb_strtolower((string)$m[1], 'UTF-8');
            $resultadoMapeado = match (true) {
                str_contains($resultado, 'excelente') => 'excelente',
                str_contains($resultado, 'muy favorable') => 'muy_favorable',
                str_contains($resultado, 'favorable'), str_contains($resultado, 'positivo') => 'favorable',
                default => 'aceptable',
            };

            $items[] = [
                'id' => 'evdoc_001',
                'es_valido' => true,
                'resultado' => $resultadoMapeado,
                'cobertura_amplia' => true,
                'fuente_texto' => trim($m[0]),
                'confianza_extraccion' => 0.65,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerFormacionDocente(string $texto): array
    {
        $items = [];
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Cursos y seminarios recibidos de perfeccionamiento, innovación y mejora docente, nuevas tecnologías, etc., cuyo objetivo sea la mejora de la docencia', 'Actividad docente'],
            ['Cursos y seminarios recibidos de perfeccionamiento, innovación y mejora docente, nuevas tecnologias, etc., cuyo objetivo sea la mejora de la docencia', 'Actividad docente'],
            ['Cursos y seminarios impartidos', 'Material y otras publicaciones docentes o de carácter pedagógico'],
        ]);

        foreach ($this->extraerBloquesNumerados($seccion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }

            $horas = $this->capturarDecimal($bloqueLimpio, 'Duración en horas')
                ?? $this->capturarDecimal($bloqueLimpio, 'Horas impartidas')
                ?? $this->capturarDecimal($bloqueLimpio, 'Num horas')
                ?? 0.0;

            $items[] = [
                'id' => 'fdoc_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'horas' => $horas,
                'rol' => $this->contieneAlgunTermino($bloqueLimpio, ['Horas impartidas', 'impartición', 'imparticion', 'ponente']) ? 'ponente' : 'asistente',
                'relacion_docente' => true,
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.70,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerMaterialDocente(string $texto): array
    {
        $items = [];

        $seccionMaterial = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Material y otras publicaciones docentes o de carácter pedagógico', 'Proyectos de innovación docente'],
            ['Material y otras publicaciones docentes o de caracter pedagogico', 'Proyectos de innovación docente'],
            ['Material y otras publicaciones docentes o de carácter pedagógico', 'Otros méritos de docencia'],
        ]);
        foreach ($this->extraerBloquesNumerados($seccionMaterial) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }
            $items[] = [
                'id' => 'mdoc_' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'material_original',
                'nivel_editorial' => $this->detectarNivelEditorial($bloqueLimpio),
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.72,
                'requiere_revision' => true,
            ];
        }

        $seccionInnovacion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Proyectos de innovación docente', 'Otros méritos de docencia'],
            ['Proyectos de innovacion docente', 'Otros méritos de docencia'],
            ['Proyectos de innovación docente', 'Pluralidad, interdisciplinariedad y complejidad docente'],
        ]);
        foreach ($this->extraerBloquesNumerados($seccionInnovacion) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }
            $items[] = [
                'id' => 'mdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'proyecto_innovacion_docente',
                'nivel_editorial' => 'nacional',
                'fuente_texto' => $bloqueLimpio,
                'confianza_extraccion' => 0.74,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerFormacionAcademica(string $texto): array
    {
        $items = [];

        $doctorado = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Doctorados', 'Conocimiento de idiomas'],
            ['Doctorados', 'Actividad docente'],
        ]);
        if (trim($doctorado) !== '') {
            $europeo = mb_strtolower((string)$this->capturarCampo($doctorado, 'Doctorado Europeo'), 'UTF-8');
            $mencion = mb_strtolower((string)$this->capturarCampo($doctorado, 'Mención de calidad'), 'UTF-8');
            $premio = mb_strtolower((string)$this->capturarCampo($doctorado, 'Premio extraordinario doctor'), 'UTF-8');
            if ($this->esRespuestaAfirmativa($europeo)) {
                $items[] = [
                    'id' => 'form_001',
                    'es_valido' => true,
                    'tipo' => 'doctorado_internacional',
                    'alta_competitividad' => true,
                    'fuente_texto' => trim($doctorado),
                    'confianza_extraccion' => 0.95,
                    'requiere_revision' => true,
                ];
            }
            if ($this->esRespuestaAfirmativa($mencion)) {
                $items[] = [
                    'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'mencion_calidad',
                    'alta_competitividad' => true,
                    'fuente_texto' => trim($doctorado),
                    'confianza_extraccion' => 0.90,
                    'requiere_revision' => true,
                ];
            }
            if ($this->esRespuestaAfirmativa($premio)) {
                $items[] = [
                    'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'premio_extra_doctorado',
                    'alta_competitividad' => true,
                    'fuente_texto' => trim($doctorado),
                    'confianza_extraccion' => 0.90,
                    'requiere_revision' => true,
                ];
            }
        }

        $estancias = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Estancias en centros públicos o privados', 'Ayudas y becas obtenidas'],
            ['Estancias en centros publicos o privados', 'Ayudas y becas obtenidas'],
        ]);
        if (trim($estancias) !== '') {
            $items[] = [
                'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'estancia',
                'alta_competitividad' => $this->contieneAlgunTermino($estancias, ['competitivo', 'horizon', 'europea', 'europeo']),
                'fuente_texto' => trim($estancias),
                'confianza_extraccion' => 0.86,
                'requiere_revision' => true,
            ];
        }

        $ayudas = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Ayudas y becas obtenidas', ''],
        ]);
        if (trim($ayudas) !== '') {
            $tipoBeca = $this->inferirTipoBeca($ayudas);
            if ($tipoBeca !== null) {
                $items[] = [
                    'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => $tipoBeca,
                    'alta_competitividad' => $tipoBeca === 'beca_posdoc',
                    'fuente_texto' => trim($ayudas),
                    'confianza_extraccion' => 0.92,
                    'requiere_revision' => true,
                ];
            }
        }

        return $items;
    }

    private function extraerExperienciaProfesional(string $texto): array
    {
        $items = [];

        $actual = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Situación profesional actual', 'Cargos y actividades desempeñados con anterioridad'],
            ['Situacion profesional actual', 'Cargos y actividades desempeñados con anterioridad'],
        ]);
        if (trim($actual) !== '') {
            $entidad = $this->capturarCampo($actual, 'Entidad empleadora');
            $categoria = $this->capturarCampo($actual, 'Categoría profesional') ?? $this->capturarCampo($actual, 'Categoria profesional');
            $inicio = $this->capturarCampo($actual, 'Fecha de inicio');
            $funciones = $this->capturarCampo($actual, 'Funciones desempeñadas') ?? '';

            if ($entidad !== null || $categoria !== null) {
                $textoFuente = trim((string)$categoria . ' en ' . (string)$entidad);
                if ($textoFuente !== '') {
                    $items[] = [
                        'id' => 'eprof_001',
                        'es_valido' => true,
                        'justificada' => true,
                        'no_valorable' => false,
                        'anios' => $inicio !== null ? $this->calcularAniosDesdeFecha((string)$inicio) : 0.0,
                        'relacion' => $this->inferirRelacionExperiencia($textoFuente . ' ' . (string)$funciones),
                        'fuente_texto' => $textoFuente,
                        'confianza_extraccion' => 0.82,
                        'requiere_revision' => true,
                    ];
                }
            }
        }

        $profesional = $this->extraerPrimeraSeccionDisponible($texto, [
            ['Actividades de carácter profesional', 'Otras actividades de carácter profesional'],
            ['Actividades de caracter profesional', 'Otras actividades de carácter profesional'],
            ['Actividades de carácter profesional', 'Otros méritos relevantes'],
        ]);
        foreach ($this->extraerBloquesNumerados($profesional) as $idx => $bloque) {
            $bloqueLimpio = trim($bloque);
            if ($bloqueLimpio === '') {
                continue;
            }
            $institucion = $this->capturarCampo($bloqueLimpio, 'Institución') ?? $this->capturarCampo($bloqueLimpio, 'Institucion');
            $categoria = $this->capturarCampo($bloqueLimpio, 'Categoría') ?? $this->capturarCampo($bloqueLimpio, 'Categoria');
            if ($institucion === null && $categoria === null) {
                continue;
            }
            $items[] = [
                'id' => 'eprof_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'justificada' => true,
                'no_valorable' => false,
                'anios' => $this->detectarDuracionEnAnios($bloqueLimpio),
                'relacion' => $this->inferirRelacionExperiencia($bloqueLimpio),
                'fuente_texto' => trim((string)$categoria . ' ' . (string)$institucion),
                'confianza_extraccion' => 0.76,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerBloque4(string $texto): array
    {
        $items = [];

        if ($this->contieneAlgunTermino($texto, ['unidad de calidad'])) {
            $items[] = [
                'id' => 'b4_001',
                'es_valido' => true,
                'tipo' => 'gestion',
                'fuente_texto' => 'Participación en Unidad de Calidad',
                'confianza_extraccion' => 0.70,
                'requiere_revision' => true,
            ];
        }

        if ($this->contieneAlgunTermino($texto, ['beca de colaboración', 'beca de colaboracion'])) {
            $items[] = [
                'id' => 'b4_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'beca_colaboracion',
                'fuente_texto' => 'Beca de colaboración',
                'confianza_extraccion' => 0.70,
                'requiere_revision' => true,
            ];
        }

        if ($this->contieneAlgunTermino($texto, ['divulgación', 'divulgacion'])) {
            $items[] = [
                'id' => 'b4_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'divulgacion',
                'fuente_texto' => 'Actividad de divulgación detectada en CVN',
                'confianza_extraccion' => 0.55,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerPrimeraSeccionDisponible(string $texto, array $pares): string
    {
        foreach ($pares as $par) {
            $inicio = (string)($par[0] ?? '');
            $fin = (string)($par[1] ?? '');
            if ($inicio === '') {
                continue;
            }
            $seccion = $this->extraerSeccion($texto, $inicio, $fin);
            if (trim($seccion) !== '') {
                return trim($seccion);
            }
        }

        return '';
    }

    private function extraerSeccion(string $texto, string $inicio, string $fin = ''): string
    {
        $posInicio = mb_stripos($texto, $inicio, 0, 'UTF-8');
        if ($posInicio === false) {
            return '';
        }

        $sub = trim(mb_substr($texto, $posInicio + mb_strlen($inicio, 'UTF-8'), null, 'UTF-8'));
        if ($fin === '') {
            return trim($sub);
        }

        $posFin = mb_stripos($sub, $fin, 0, 'UTF-8');
        if ($posFin === false) {
            return trim($sub);
        }

        return trim(mb_substr($sub, 0, $posFin, 'UTF-8'));
    }

    private function extraerBloquesNumerados(string $texto): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        preg_match_all('/(?:^|\n)\s*(\d{1,3})\s*\n(.*?)(?=(?:\n\s*\d{1,3}\s*\n)|\z)/su', $texto, $matches);
        $bloques = array_map('trim', $matches[2] ?? []);
        $bloques = array_values(array_filter($bloques, static fn($b) => $b !== ''));

        if ($bloques !== []) {
            return $bloques;
        }

        return [trim($texto)];
    }

    private function capturarCampo(string $texto, string $campo): ?string
    {
        $pattern = '/(?:^|\n)' . preg_quote($campo, '/') . '\s*:\s*(.+?)(?=(?:\n[^\n:]{1,120}:)|\z)/su';
        if (preg_match($pattern, $texto, $m)) {
            $valor = trim(preg_replace('/\s+/u', ' ', $m[1]) ?? '');
            return $valor !== '' ? $valor : null;
        }
        return null;
    }

    private function capturarDecimal(string $texto, string $campo): ?float
    {
        $valor = $this->capturarCampo($texto, $campo);
        if ($valor === null) {
            return null;
        }
        if (preg_match('/-?\d+(?:[\.,]\d+)?/u', $valor, $m)) {
            return (float)str_replace(',', '.', $m[0]);
        }
        return null;
    }

    private function capturarEntero(string $texto, string $campo): ?int
    {
        $valor = $this->capturarCampo($texto, $campo);
        if ($valor === null) {
            return null;
        }
        if (preg_match('/-?\d+/u', $valor, $m)) {
            return (int)$m[0];
        }
        return null;
    }

    private function detectarAnioPublicacion(string $bloque): ?int
    {
        if (preg_match('/(?:\b|\/)(20\d{2}|19\d{2})(?:\b|\/)/u', $bloque, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function inferirTipoIndicePublicacion(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        if ($this->contieneAlgunTermino($t, ['physical review', 'journal of physics', 'physica', 'scipost', 'springer', 'elsevier'])) {
            return 'JCR';
        }
        if ($this->contieneAlgunTermino($t, ['mdpi', 'symmetry'])) {
            return 'SCOPUS';
        }
        if ($this->contieneAlgunTermino($t, ['issn', 'revista'])) {
            return 'SCOPUS';
        }

        return 'OTRO';
    }

    private function inferirCuartilPublicacion(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');

        return match (true) {
            $this->contieneAlgunTermino($t, ['physical review']) => 'Q1',
            $this->contieneAlgunTermino($t, ['journal of physics']) => 'Q1',
            $this->contieneAlgunTermino($t, ['physica e', 'scipost', 'quantum information processing']) => 'Q2',
            $this->contieneAlgunTermino($t, ['symmetry', 'mdpi']) => 'Q3',
            default => '',
        };
    }

    private function cuartilATercil(string $cuartil): string
    {
        return match (strtoupper(trim($cuartil))) {
            'Q1' => 'T1',
            'Q2' => 'T2',
            'Q3', 'Q4' => 'T3',
            default => '',
        };
    }

    private function esAreaMatematicas(string $texto): bool
    {
        return $this->contieneAlgunTermino($texto, ['mathematical', 'matemática', 'matematica', 'theoretical']);
    }

    private function detectarAfinidad(string $texto): string
    {
        return $this->contieneAlgunTermino($texto, ['quantum', 'cuántic', 'cuantic', 'physics', 'física', 'fisica']) ? 'alta' : 'media';
    }

    private function mapearPosicionAutor(int $posFirma, int $numAutores, ?string $correspondencia): string
    {
        if ($numAutores <= 1) {
            return 'autor_unico';
        }
        if ($this->esRespuestaAfirmativa((string)$correspondencia) && $posFirma > 1) {
            return 'correspondencia';
        }
        if ($posFirma <= 1) {
            return 'primero';
        }
        if ($posFirma >= $numAutores) {
            return 'ultimo';
        }
        return 'intermedio';
    }

    private function mapearTipoProyecto(string $ambito): string
    {
        $ambito = mb_strtolower(trim($ambito), 'UTF-8');
        return match (true) {
            str_contains($ambito, 'unión europea'), str_contains($ambito, 'union europea'), str_contains($ambito, 'europe') => 'europeo',
            str_contains($ambito, 'nacional') => 'nacional',
            str_contains($ambito, 'auton') => 'autonomico',
            default => 'otro_competitivo',
        };
    }

    private function mapearRolProyecto(string $rolFuente, string $bloque): string
    {
        $texto = mb_strtolower(trim($rolFuente . ' ' . $bloque), 'UTF-8');
        return match (true) {
            $this->contieneAlgunTermino($texto, ['investigador principal']) => 'ip',
            $this->contieneAlgunTermino($texto, ['co-ip', 'coip', 'co ip']) => 'coip',
            $this->contieneAlgunTermino($texto, ['contrato laboral']) => 'contrato_laboral',
            default => 'investigador',
        };
    }

    private function detectarDuracionEnAnios(string $texto): float
    {
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/u', $texto, $m)) {
            $inicio = DateTime::createFromFormat('d/m/Y', $m[1]);
            $fin = DateTime::createFromFormat('d/m/Y', $m[2]);
            if ($inicio instanceof DateTime && $fin instanceof DateTime) {
                $dias = (int)$inicio->diff($fin)->format('%a');
                return round(max(0, $dias) / 365, 2);
            }
        }

        if (preg_match('/(20\d{2}|19\d{2})\s*-\s*(20\d{2}|19\d{2})/u', $texto, $m)) {
            return round(max(0, ((int)$m[2]) - ((int)$m[1])), 2);
        }

        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*años?/iu', $texto, $m) || preg_match('/(\d+(?:[\.,]\d+)?)\s*anos?/iu', $texto, $m)) {
            return (float)str_replace(',', '.', $m[1]);
        }

        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*meses?/iu', $texto, $m)) {
            return round(((float)str_replace(',', '.', $m[1])) / 12, 2);
        }

        return 1.0;
    }

    private function detectarTipoTransferencia(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        return match (true) {
            $this->contieneAlgunTermino($t, ['patente']) => 'patente',
            $this->contieneAlgunTermino($t, ['propiedad intelectual', 'software']) => 'propiedad_intelectual',
            $this->contieneAlgunTermino($t, ['art. 83', 'art83']) => 'art83',
            default => 'otro',
        };
    }

    private function mapearAmbitoCongreso(string $ambito): string
    {
        $ambito = mb_strtolower(trim($ambito), 'UTF-8');
        return match (true) {
            str_contains($ambito, 'internacional'), str_contains($ambito, 'unión europea'), str_contains($ambito, 'union europea') => 'internacional',
            str_contains($ambito, 'nacional') => 'nacional',
            default => 'nacional',
        };
    }

    private function mapearTipoCongreso(string $tipo): string
    {
        $tipo = mb_strtolower(trim($tipo), 'UTF-8');
        return match (true) {
            $this->contieneAlgunTermino($tipo, ['invitada', 'plenaria']) => 'ponencia_invitada',
            $this->contieneAlgunTermino($tipo, ['poster', 'póster']) => 'poster',
            default => 'comunicacion_oral',
        };
    }

    private function detectarNivelEditorial(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        return match (true) {
            $this->contieneAlgunTermino($t, ['springer', 'elsevier', 'wiley', 'oxford', 'cambridge', 'iop', 'aps']) => 'internacional',
            $this->contieneAlgunTermino($t, ['universidad', 'editorial', 'ministerio']) => 'nacional',
            default => 'nacional',
        };
    }

    private function contarAutoresPorPrimeraLinea(string $bloque): int
    {
        $linea = trim((string)preg_split('/\n/u', $bloque)[0]);
        if ($linea === '') {
            return 1;
        }
        $partes = array_filter(array_map('trim', preg_split('/;/u', $linea) ?: []), static fn($v) => $v !== '');
        return max(1, count($partes));
    }

    private function inferirTipoBeca(string $texto): ?string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        return match (true) {
            $this->contieneAlgunTermino($t, ['fpu']) => 'beca_predoc_fpu',
            $this->contieneAlgunTermino($t, ['fpi']) => 'beca_predoc_fpi',
            $this->contieneAlgunTermino($t, ['predoctoral']) && $this->contieneAlgunTermino($t, ['universidad']) => 'beca_predoc_universidad',
            $this->contieneAlgunTermino($t, ['predoctoral']) => 'beca_predoc_autonomica',
            $this->contieneAlgunTermino($t, ['posdoctoral', 'posdoc']) => 'beca_posdoc',
            default => null,
        };
    }

    private function inferirRelacionExperiencia(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        return match (true) {
            $this->contieneAlgunTermino($t, ['profesor', 'docente', 'big data', 'ingeniería', 'ingenieria', 'informática', 'informatica']) => 'alta',
            $this->contieneAlgunTermino($t, ['análisis', 'analisis', 'datos']) => 'media',
            default => 'media',
        };
    }

    private function calcularAniosDesdeFecha(string $fecha): float
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return 0.0;
        }
        $dt = DateTime::createFromFormat('d/m/Y', $fecha);
        if (!$dt instanceof DateTime) {
            return 0.0;
        }
        $ahora = new DateTime();
        $dias = (int)$dt->diff($ahora)->format('%a');
        return round(max(0, $dias) / 365, 2);
    }

    private function normalizarEventoId(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = preg_replace('/[^a-z0-9]+/', '_', $texto) ?? $texto;
        return trim($texto, '_');
    }

    private function contieneAlgunTermino(string $texto, array $terminos): bool
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        foreach ($terminos as $termino) {
            if (mb_stripos($texto, mb_strtolower((string)$termino, 'UTF-8'), 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    private function esRespuestaAfirmativa(string $valor): bool
    {
        $valor = mb_strtolower(trim($valor), 'UTF-8');
        return in_array($valor, ['sí', 'si', 's', 'yes', 'true', '1'], true);
    }
}
