<?php

declare(strict_types=1);

class AnecaExtractor
{
    public function extraer(string $texto): array
    {
        $textoNormalizado = $this->normalizarTexto($texto);

        $resultado = [
            "bloque_1" => [
                "publicaciones" => $this->extraerPublicaciones($textoNormalizado),
                "libros" => $this->extraerLibros($textoNormalizado),
                "proyectos" => $this->extraerProyectos($textoNormalizado),
                "transferencia" => $this->extraerTransferencia($textoNormalizado),
                "tesis_dirigidas" => $this->extraerTesisDirigidas($textoNormalizado),
                "congresos" => $this->extraerCongresos($textoNormalizado),
                "otros_meritos_investigacion" => $this->extraerOtrosMeritosInvestigacion($textoNormalizado)
            ],
            "bloque_2" => [
                "docencia_universitaria" => $this->extraerDocenciaUniversitaria($textoNormalizado),
                "evaluacion_docente" => $this->extraerEvaluacionDocente($textoNormalizado),
                "formacion_docente" => $this->extraerFormacionDocente($textoNormalizado),
                "material_docente" => $this->extraerMaterialDocente($textoNormalizado)
            ],
            "bloque_3" => [
                "formacion_academica" => $this->extraerFormacionAcademica($textoNormalizado),
                "experiencia_profesional" => $this->extraerExperienciaProfesional($textoNormalizado)
            ],
            "bloque_4" => $this->extraerBloque4($textoNormalizado),
            "metadatos_extraccion" => [
                "comite" => "EXPERIMENTALES",
                "subcomite" => null,
                "archivo_pdf" => null,
                "fecha_extraccion" => date('c'),
                "version_esquema" => "2.1-cvn-proyectos-duros",
                "requiere_revision_manual" => true
            ]
        ];

        return $resultado;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    /* =========================================================
     * EXTRACTORES PRINCIPALES
     * ========================================================= */

    private function extraerPublicaciones(string $texto): array
    {
        $items = [];
        $bloques = $this->extraerBloquesEtiquetados($texto, 'Publicación');

        foreach ($bloques as $bloque) {
            $titulo = $this->capturarCampo($bloque, 'TITULO');
            $nombre = $this->capturarCampo($bloque, 'NOMBRE');
            $issn = $this->capturarCampo($bloque, 'ISSN');
            $base = $this->capturarCampo($bloque, 'CALIDAD BASE');
            $posicion = $this->capturarCampo($bloque, 'CALIDAD POSICION');

            if ($titulo === null && $nombre === null && $issn === null && $base === null && $posicion === null) {
                continue;
            }

            $cuartil = $this->detectarCuartil($bloque);
            $tipoIndice = $this->detectarTipoIndice($bloque);
            $anio = $this->capturarEntero($bloque, 'AÑO');
            $citas = $this->capturarEntero($bloque, 'CALIDAD CITAS');
            $numAutores = $this->capturarEntero($bloque, 'NUMERO AUTORES')
                ?? $this->capturarEntero($bloque, 'NÚMERO AUTORES')
                ?? $this->detectarNumeroAutores($bloque);
            $posicionAutor = $this->mapearPosicionAutorDesdeBloque($bloque);
            $anioActual = (int)date('Y');

            $items[] = [
                'id' => 'pub_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'tipo' => 'articulo',
                'es_valida' => true,
                'es_divulgacion' => false,
                'es_docencia' => false,
                'es_acta_congreso' => false,
                'es_informe_proyecto' => false,
                'tipo_indice' => $tipoIndice,
                'cuartil' => $cuartil,
                'tercil' => $this->cuartilATercil($cuartil),
                'es_area_matematicas' => $this->esAreaMatematicas($bloque),
                'afinidad' => $this->detectarAfinidad($bloque),
                'posicion_autor' => $posicionAutor,
                'numero_autores' => $numAutores,
                'citas' => $citas,
                'anios_desde_publicacion' => $anio !== null ? max(0, $anioActual - $anio) : null,
                'numero_trabajos_misma_revista' => 1,
                'fuente_texto' => trim($bloque),
                'confianza_extraccion' => 0.90,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerLibros(string $texto): array
    {
        $items = [];

        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['LIBROS Y CAPÍTULOS DE LIBRO', 'PROYECTOS Y/O CONTRATOS DE INVESTIGACION'],
            ['LIBROS Y CAPITULOS DE LIBRO', 'PROYECTOS Y/O CONTRATOS DE INVESTIGACION'],
            ['LIBROS Y CAPÍTULOS', 'PROYECTOS Y/O CONTRATOS DE INVESTIGACION'],
            ['LIBROS Y CAPITULOS', 'PROYECTOS Y/O CONTRATOS DE INVESTIGACION'],
            ['LIBROS Y CAPÍTULOS DE LIBRO', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS'],
            ['LIBROS Y CAPITULOS DE LIBRO', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS'],
        ]);

        $universo = $seccion !== '' ? $seccion : '';
        $bloques = $universo !== '' ? $this->extraerBloquesEtiquetados($universo, 'Libros') : [];

        foreach ($bloques as $bloque) {
            $items[] = [
                'id' => 'lib_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'tipo' => $this->contieneAlgunTermino($bloque, ['capítulo', 'capitulo']) ? 'capitulo' : 'libro',
                'es_valido' => true,
                'es_libro_investigacion' => true,
                'es_autoedicion' => false,
                'es_acta_congreso' => false,
                'es_labor_edicion' => false,
                'nivel_editorial' => $this->detectarNivelEditorial($bloque),
                'nivel_coleccion' => $this->detectarNivelColeccion($bloque),
                'afinidad' => $this->detectarAfinidad($bloque),
                'numero_autores' => $this->detectarNumeroAutores($bloque),
                'posicion_autor' => $this->detectarPosicionAutor($bloque),
                'nivel_resenas' => $this->detectarNivelResenas($bloque),
                'fuente_texto' => trim($bloque),
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        if ($items !== [] || $universo === '') {
            return $items;
        }

        $lineas = preg_split('/
+/u', $universo) ?: [];
        foreach ($lineas as $linea) {
            $l = trim($linea);
            if ($l === '') {
                continue;
            }
            $ll = mb_strtolower($l, 'UTF-8');
            $pareceLibro = $this->contieneAlgunTermino($ll, ['libro', 'capítulo', 'capitulo'])
                && $this->contieneAlgunTermino($ll, ['isbn', 'editorial', 'springer', 'elsevier', 'wiley', 'cambridge', 'oxford']);
            if (!$pareceLibro) {
                continue;
            }

            $items[] = [
                'id' => 'lib_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'tipo' => $this->contieneAlgunTermino($ll, ['capítulo', 'capitulo']) ? 'capitulo' : 'libro',
                'es_valido' => true,
                'es_libro_investigacion' => true,
                'es_autoedicion' => false,
                'es_acta_congreso' => false,
                'es_labor_edicion' => false,
                'nivel_editorial' => $this->detectarNivelEditorial($l),
                'nivel_coleccion' => $this->detectarNivelColeccion($l),
                'afinidad' => $this->detectarAfinidad($l),
                'numero_autores' => $this->detectarNumeroAutores($l),
                'posicion_autor' => $this->detectarPosicionAutor($l),
                'nivel_resenas' => $this->detectarNivelResenas($l),
                'fuente_texto' => $l,
                'confianza_extraccion' => 0.60,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }


    private function extraerProyectos(string $texto): array
    {
        $items = [];
        $seccion = $this->extraerSeccion($texto, 'PROYECTOS Y/O CONTRATOS DE INVESTIGACION', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS');
        $bloques = $this->extraerBloquesEtiquetados($seccion !== '' ? $seccion : $texto, 'Proyectos');

        foreach ($bloques as $bloque) {
            $titulo = $this->capturarCampo($bloque, 'TITULO');
            $entidad = $this->capturarCampo($bloque, 'ENTIDAD');
            $fdesde = $this->capturarCampo($bloque, 'FDESDE');
            $fhasta = $this->capturarCampo($bloque, 'FHASTA');
            $meses = $this->capturarEntero($bloque, 'MESES');
            $gradoTipo = $this->capturarCampo($bloque, 'GRADO TIPO');
            $gradoOtros = $this->capturarCampo($bloque, 'GRADO OTROS');
            $aportacion = $this->capturarCampo($bloque, 'APORTACION');
            $dedicacion = $this->capturarCampo($bloque, 'DEDICACION');
            $convTipo = $this->capturarCampo($bloque, 'CONV TIPO');
            $convOtros = $this->capturarCampo($bloque, 'CONV OTROS');

            if ($titulo === null && $entidad === null && $fdesde === null && $meses === null) {
                continue;
            }

            $tipoProyecto = $this->inferirTipoProyectoDesdeBloque($bloque, $entidad, $convTipo, $convOtros);
            $rolReal = $this->inferirRolProyectoDesdeBloque($bloque, $gradoTipo, $gradoOtros, $aportacion);
            $esContratoLaboral = $this->inferirSiEsContratoLaboralProyecto($bloque, $gradoTipo, $aportacion);
            $anios = $meses !== null ? round($meses / 12, 2) : $this->detectarDuracionEnAnios($bloque);

            $items[] = [
                'id' => 'proy_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'esta_certificado' => $this->proyectoPareceCertificado($bloque),
                'tipo_proyecto' => $tipoProyecto,
                'rol' => $rolReal,
                'rol_detectado' => $rolReal,
                'es_contrato_laboral' => $esContratoLaboral,
                'grado_tipo' => $gradoTipo,
                'grado_otros' => $gradoOtros,
                'dedicacion' => $this->mapearDedicacionProyecto($dedicacion),
                'anios_duracion' => $anios,
                'continuidad' => $this->detectarContinuidad($bloque),
                'fuente_texto' => trim($bloque),
                'confianza_extraccion' => 0.95,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerTransferencia(string $texto): array
    {
        $items = [];
        $seccion = $this->extraerPrimeraSeccionDisponible($texto, [
            ['TRANSFERENCIA TECNOLÓGICA', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS'],
            ['TRANSFERENCIA TECNOLOGICA', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS'],
            ['PATENTES', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS'],
            ['PROPIEDAD INTELECTUAL', 'CONGRESOS Y CONFERENCIAS CIENTIFICAS'],
        ]);

        if ($seccion === '') {
            return [];
        }

        $lineas = preg_split('/
+/u', $seccion) ?: [];
        foreach ($lineas as $linea) {
            $l = trim($linea);
            if ($l === '') {
                continue;
            }
            $ll = mb_strtolower($l, 'UTF-8');
            $pareceTransferencia = $this->contieneAlgunTermino($ll, [
                'patente',
                'propiedad intelectual',
                'registro de software',
                'transferencia tecnológica',
                'transferencia tecnologica',
                'art. 83',
                'art83',
            ]);
            if (!$pareceTransferencia) {
                continue;
            }

            $items[] = [
                'id' => 'trans_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $this->detectarTipoTransferencia($l),
                'impacto_externo' => true,
                'liderazgo' => $this->detectarLiderazgo($l),
                'participacion_menor' => false,
                'fuente_texto' => $l,
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }


    private function extraerTesisDirigidas(string $texto): array
    {
        $items = [];
        $lineas = preg_split('/\n+/u', $texto) ?: [];

        foreach ($lineas as $linea) {
            $l = trim($linea);
            if ($l === '') {
                continue;
            }
            $ll = mb_strtolower($l, 'UTF-8');
            $parece = str_contains($ll, 'tesis doctoral') || str_contains($ll, 'director de tesis') || str_contains($ll, 'codirector de tesis');
            if (!$parece) {
                continue;
            }

            $codirectores = 0;
            if (preg_match('/(\d{1,2})\s+codirectores?/iu', $l, $m)) {
                $codirectores = (int)$m[1];
            } elseif (str_contains($ll, 'codirector')) {
                $codirectores = 1;
            }

            $items[] = [
                'id' => 'tesis_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => str_contains($ll, 'en curso') ? 'en_direccion' : 'dirigida',
                'proyecto_aprobado' => str_contains($ll, 'aprobado') ? true : null,
                'doctorado_europeo' => $this->contieneAlgunTermino($ll, ['doctorado europeo', 'mención internacional', 'mencion internacional']),
                'mencion_calidad' => $this->contieneAlgunTermino($ll, ['mención de calidad', 'mencion de calidad']),
                'numero_codirectores' => $codirectores,
                'fuente_texto' => $l,
                'confianza_extraccion' => 0.60,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerCongresos(string $texto): array
    {
        $items = [];
        $bloque = $this->extraerSeccion($texto, 'CONGRESOS Y CONFERENCIAS CIENTIFICAS', 'EXPERIENCIA DOCENTE');
        $bloques = $this->extraerBloquesEtiquetados($bloque, 'Congresos');

        foreach ($bloques as $i => $b) {
            $titulo = $this->capturarCampo($b, 'TITULO');
            $evento = $this->capturarCampo($b, 'CONGRESO');
            $entidad = $this->capturarCampo($b, 'ENTIDAD');
            $lugar = $this->capturarCampo($b, 'LUGAR');
            $fecha = $this->capturarCampo($b, 'FCELEBRACION');

            if ($titulo === null && $evento === null && $entidad === null && $lugar === null) {
                continue;
            }

            $ambito = $this->inferirAmbitoCongresoDesdeBloque($b);
            $tipo = $this->inferirTipoCongresoDesdeBloque($b);
            $idEvento = $this->normalizarEventoId(($evento ?? $titulo ?? 'evento_' . ($i + 1)) . '|' . ($fecha ?? ''));

            $items[] = [
                'id' => 'cong_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'ambito' => $ambito,
                'tipo' => $tipo,
                'proceso_selectivo' => true,
                'id_evento' => $idEvento,
                'fuente_texto' => trim($b),
                'confianza_extraccion' => 0.95,
                'requiere_revision' => true,
            ];
        }

        if ($items !== []) {
            return $items;
        }

        $lineas = preg_split('/\n+/u', $bloque ?: $texto) ?: [];
        foreach ($lineas as $linea) {
            $l = trim($linea);
            if ($l === '') {
                continue;
            }
            $ll = mb_strtolower($l, 'UTF-8');
            $parece = str_contains($ll, 'congreso') || str_contains($ll, 'jornada') || str_contains($ll, 'seminario') || str_contains($ll, 'ponencia') || str_contains($ll, 'comunicación') || str_contains($ll, 'comunicacion') || str_contains($ll, 'póster') || str_contains($ll, 'poster');
            if (!$parece) {
                continue;
            }
            $items[] = [
                'id' => 'cong_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'ambito' => $this->detectarAmbitoCongreso($l) ?? 'nacional',
                'tipo' => $this->detectarTipoCongreso($l) ?? 'comunicacion_oral',
                'proceso_selectivo' => true,
                'id_evento' => $this->normalizarEventoId($l),
                'fuente_texto' => $l,
                'confianza_extraccion' => 0.55,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerOtrosMeritosInvestigacion(string $texto): array
    {
        $otros = [];
        $seccion = $this->extraerSeccion($texto, 'ESTANCIAS EN CENTROS ESPAÑOLES Y EXTRANJEROS', 'CURSOS Y SEMINARIOS DE ESPECIALIZACION');

        if ($seccion !== '') {
            $otros[] = [
                'id' => 'oinv_001',
                'es_valido' => true,
                'tipo' => 'otro',
                'fuente_texto' => trim($seccion),
                'confianza_extraccion' => 0.70,
                'requiere_revision' => true,
            ];
        }

        return $otros;
    }

    private function extraerDocenciaUniversitaria(string $texto): array
    {
        $items = [];
        $bloque = $this->extraerSeccion($texto, 'PUESTOS OCUPADOS Y DOCENCIA IMPARTIDA', 'CURSOS Y SEMINARIOS RECIBIDOS');
        $bloques = $this->extraerBloquesEtiquetados($bloque, 'Docencia Impartida');

        foreach ($bloques as $b) {
            $institucion = $this->capturarCampo($b, 'INSTITUCION');
            $asignatura = $this->capturarCampo($b, 'ASIGNATURA');
            $horas = $this->capturarDecimal($b, 'HORAS');
            $especificar = $this->capturarCampo($b, 'ESPECIFICAR');
            $denominacion = $this->capturarCampo($b, 'DENOMINACION');
            $tipoAsignatura = $this->capturarCampo($b, 'TIPO ASIGNATURA');

            if ($institucion === null && $asignatura === null && $horas === null) {
                continue;
            }

            $tipo = $this->inferirTipoDocencia($b, $especificar);
            $etapa = $this->inferirEtapaDocente($denominacion);
            $tfg = $this->capturarNumeroPatron($b, '/(\d+)\s*TFG/iu') ?? 0;
            $tfm = $this->capturarNumeroPatron($b, '/(\d+)\s*TFM/iu') ?? 0;

            if (($tipoAsignatura ?? '') === '15002' && $tipo === 'grado' && $this->contieneAlgunTermino((string)$especificar, ['máster', 'master', 'postgrado', 'posgrado'])) {
                $tipo = 'master';
            }

            $items[] = [
                'id' => 'doc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'horas' => $horas,
                'tipo' => $tipo,
                'etapa' => $etapa,
                'tfg' => $tfg,
                'tfm' => $tfm,
                'fuente_texto' => trim($b),
                'confianza_extraccion' => 0.95,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerEvaluacionDocente(string $texto): array
    {
        $items = [];
        $bloque = $this->extraerSeccion($texto, 'PUESTOS OCUPADOS Y DOCENCIA IMPARTIDA', 'CURSOS Y SEMINARIOS RECIBIDOS');
        $bloques = $this->extraerBloquesEtiquetados($bloque, 'Docencia Impartida');

        foreach ($bloques as $b) {
            $calific = $this->capturarDecimal($b, 'CALIFIC');
            $max = $this->capturarDecimal($b, 'CALIFIC MAX');
            $organismo = $this->capturarCampo($b, 'ORGANISMO');

            if ($calific === null) {
                continue;
            }

            $resultado = $this->mapearResultadoDocente($calific, $max);

            $items[] = [
                'id' => 'evdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'resultado' => $resultado,
                'cobertura_amplia' => true,
                'fuente_texto' => trim(($organismo ?? '') . ' ' . $b),
                'confianza_extraccion' => 0.95,
                'requiere_revision' => true,
            ];
        }

        if ($items === [] && preg_match('/evaluaci[oó]n\s+sobre\s+calidad\s+docente/iu', $texto)) {
            $items[] = [
                'id' => 'evdoc_001',
                'es_valido' => true,
                'resultado' => 'muy_favorable',
                'cobertura_amplia' => true,
                'fuente_texto' => 'AUTOEVALUACION: evaluación sobre calidad docente',
                'confianza_extraccion' => 0.60,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerFormacionDocente(string $texto): array
    {
        $items = [];
        $bloque = $this->extraerSeccion($texto, 'CURSOS Y SEMINARIOS RECIBIDOS', 'OTROS MERITOS DOCENTES');
        $bloques = $this->extraerBloquesEtiquetados($bloque, 'Cursos Recibidos');

        foreach ($bloques as $b) {
            $titulo = $this->capturarCampo($b, 'TITULO');
            $horas = $this->capturarDecimal($b, 'HORAS');
            $entidad = $this->capturarCampo($b, 'ENTIDAD');
            $perfil = $this->capturarCampo($b, 'PERFIL');
            $objetivos = $this->capturarCampo($b, 'OBJETIVOS');

            if ($titulo === null && $horas === null && $entidad === null) {
                continue;
            }

            $textoMerito = trim(($titulo ?? '') . ' ' . ($perfil ?? '') . ' ' . ($objetivos ?? '') . ' ' . ($entidad ?? ''));
            $relacion = $this->esFormacionDocenteUniversitaria($textoMerito);
            $rol = $this->contieneAlgunTermino($textoMerito, ['ponente', 'impartido por', 'coordinador']) ? 'ponente' : 'asistente';

            $items[] = [
                'id' => 'fdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'horas' => $horas,
                'rol' => $rol,
                'relacion_docente' => $relacion,
                'fuente_texto' => trim($b),
                'confianza_extraccion' => 0.90,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerMaterialDocente(string $texto): array
    {
        $items = [];

        $secciones = [
            $this->extraerSeccion($texto, 'OTROS MERITOS DOCENTES', 'FORMACION ACADEMICA'),
            $this->extraerSeccion($texto, 'OTROS MÉRITOS DOCENTES', 'FORMACION ACADEMICA'),
            $this->extraerSeccion($texto, 'MATERIAL DOCENTE', 'FORMACION ACADEMICA'),
            $this->extraerSeccion($texto, 'MATERIAL DOCENTE', 'FORMACIÓN ACADÉMICA'),
        ];

        $universo = trim(implode("
", array_filter($secciones, static fn($s) => trim((string)$s) !== '')));
        if ($universo === '') {
            return [];
        }

        $lineas = preg_split('/
+/u', $universo) ?: [];
        foreach ($lineas as $linea) {
            $l = trim($linea);
            if ($l === '') {
                continue;
            }
            $ll = mb_strtolower($l, 'UTF-8');
            $parece = $this->contieneAlgunTermino($ll, [
                'material docente original',
                'material docente',
                'manual docente',
                'libro docente',
                'capítulo docente',
                'capitulo docente',
                'publicación docente',
                'publicacion docente',
                'proyecto de innovación docente',
                'proyecto de innovacion docente',
            ]);
            if (!$parece) {
                continue;
            }

            $tipo = 'material_original';
            if ($this->contieneAlgunTermino($ll, ['libro docente'])) {
                $tipo = 'libro_docente';
            } elseif ($this->contieneAlgunTermino($ll, ['capítulo docente', 'capitulo docente'])) {
                $tipo = 'capitulo_docente';
            } elseif ($this->contieneAlgunTermino($ll, ['proyecto de innovación docente', 'proyecto de innovacion docente'])) {
                $tipo = 'innovacion_docente';
            }

            $items[] = [
                'id' => 'matdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $tipo,
                'nivel_editorial' => $this->detectarNivelEditorial($l) ?? 'nacional',
                'fuente_texto' => $l,
                'confianza_extraccion' => 0.70,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }


    private function extraerFormacionAcademica(string $texto): array
    {
        $items = [];

        $doctorado = $this->extraerSeccion($texto, 'DOCTORADO', 'OTROS TITULOS DE POSTGRADO');
        if ($doctorado !== '') {
            $items[] = [
                'id' => 'facad_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $this->contieneAlgunTermino($doctorado, ['mención internacional', 'mencion internacional', 'europeo s']) ? 'doctorado_internacional' : 'curso_especializacion',
                'alta_competitividad' => false,
                'fuente_texto' => trim($doctorado),
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        $ayudas = $this->extraerSeccion($texto, 'AYUDAS Y BECAS', 'ESTANCIAS EN CENTROS ESPAÑOLES Y EXTRANJEROS');
        if ($ayudas !== '') {
            if ($this->contieneAlgunTermino($ayudas, ['fpu'])) {
                $items[] = [
                    'id' => 'facad_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'beca_predoc_fpu',
                    'alta_competitividad' => true,
                    'fuente_texto' => trim($ayudas),
                    'confianza_extraccion' => 0.90,
                    'requiere_revision' => true,
                ];
            } elseif ($this->contieneAlgunTermino($ayudas, ['fpi'])) {
                $items[] = [
                    'id' => 'facad_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'beca_predoc_fpi',
                    'alta_competitividad' => true,
                    'fuente_texto' => trim($ayudas),
                    'confianza_extraccion' => 0.90,
                    'requiere_revision' => true,
                ];
            }
        }

        $estancias = $this->extraerSeccion($texto, 'ESTANCIAS EN CENTROS ESPAÑOLES Y EXTRANJEROS', 'CURSOS Y SEMINARIOS DE ESPECIALIZACION');
        if ($estancias !== '') {
            $items[] = [
                'id' => 'facad_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'estancia',
                'alta_competitividad' => $this->contieneAlgunTermino($estancias, ['competitive', 'competitiva', 'competitivo']),
                'fuente_texto' => trim($estancias),
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerExperienciaProfesional(string $texto): array
    {
        $items = [];
        $seccion = $this->extraerSeccion($texto, 'EXPERIENCIA LABORAL', 'OTROS MERITOS RELEVANTES');
        $resto = $this->extraerSeccion($texto, 'OTROS MERITOS RELEVANTES', '');
        $textoTrabajo = trim($seccion . "\n" . $resto);

        if ($textoTrabajo === '') {
            return [];
        }

        $partes = preg_split('/\s+x{5,}\s+/u', $textoTrabajo) ?: [$textoTrabajo];

        foreach ($partes as $parte) {
            $p = trim($parte);
            if ($p === '' || mb_strtolower($p, 'UTF-8') === 'otros meritos relevantes') {
                continue;
            }

            $justificada = $this->contieneAlgunTermino($p, ['institución', 'institucion', 'centro:', 'curso:', 'horas:', 'universidad']);
            $noValorable = $this->contieneAlgunTermino($p, ['participación en la actividad formativa', 'divulgación científica', 'divulgacion cientifica']);

            $anios = $this->detectarAniosExperienciaDesdeTexto($p);
            $relacion = $this->inferirRelacionExperiencia($p);

            if (!$justificada) {
                continue;
            }

            $items[] = [
                'id' => 'eprof_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'justificada' => true,
                'no_valorable' => $noValorable,
                'anios' => $anios,
                'relacion' => $relacion,
                'fuente_texto' => $p,
                'confianza_extraccion' => 0.85,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerBloque4(string $texto): array
    {
        $items = [];
        $seccion = $this->extraerSeccion($texto, 'OTROS MERITOS RELEVANTES', '');
        $partes = preg_split('/\s+x{5,}\s+/u', $seccion) ?: [];

        foreach ($partes as $parte) {
            $p = trim($parte);
            if ($p === '' || mb_strtolower($p, 'UTF-8') === 'otros meritos relevantes') {
                continue;
            }

            $tipo = 'otro';
            if ($this->contieneAlgunTermino($p, ['asesor científico', 'asesor cientifico', 'asesor instrumental'])) {
                $tipo = 'asesor_equipos';
            } elseif ($this->contieneAlgunTermino($p, ['unidad de calidad', 'organizador', 'gestión', 'gestion'])) {
                $tipo = 'gestion';
            } elseif ($this->contieneAlgunTermino($p, ['beca de colaboración', 'beca de colaboracion'])) {
                $tipo = 'beca_colaboracion';
            } elseif ($this->contieneAlgunTermino($p, ['premio extraordinario'])) {
                $tipo = 'premio_extra_grado';
            } elseif ($this->contieneAlgunTermino($p, ['divulgación', 'divulgacion'])) {
                $tipo = 'divulgacion';
            }

            $items[] = [
                'id' => 'b4_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $tipo,
                'fuente_texto' => $p,
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    /* =========================================================
     * HELPERS CVN / BLOQUES
     * ========================================================= */

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
                return $seccion;
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

        $sub = mb_substr($texto, $posInicio, null, 'UTF-8');
        if ($fin === '') {
            return trim($sub);
        }

        $posFin = mb_stripos($sub, $fin, 0, 'UTF-8');
        if ($posFin === false) {
            return trim($sub);
        }

        return trim(mb_substr($sub, 0, $posFin, 'UTF-8'));
    }

    private function extraerBloquesEtiquetados(string $texto, string $etiqueta): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }

        $pattern = '/(?:^|\n)' . preg_quote($etiqueta, '/') . '\s*\n(.*?)(?=(?:\n' . preg_quote($etiqueta, '/') . '\s*\n)|\z)/su';
        preg_match_all($pattern, $texto, $matches);
        return array_values(array_filter(array_map('trim', $matches[1] ?? []), static fn($v) => $v !== ''));
    }

    private function capturarCampo(string $texto, string $campo): ?string
    {
        $pattern = '/(?:^|\n)' . preg_quote($campo, '/') . '\s+(.+?)(?=\n[A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ0-9 #\/\.\-]{1,40}\s|\z)/su';
        if (preg_match($pattern, $texto, $m)) {
            $valor = trim(preg_replace('/\s+/u', ' ', $m[1]) ?? '');
            return $valor !== '' ? $valor : null;
        }
        return null;
    }

    private function capturarDecimal(string $texto, string $campo): ?float
    {
        $v = $this->capturarCampo($texto, $campo);
        if ($v === null) {
            return null;
        }
        if (preg_match('/-?\d+(?:[\.,]\d+)?/u', $v, $m)) {
            return (float)str_replace(',', '.', $m[0]);
        }
        return null;
    }

    private function capturarEntero(string $texto, string $campo): ?int
    {
        $v = $this->capturarCampo($texto, $campo);
        if ($v === null) {
            return null;
        }
        if (preg_match('/-?\d+/u', $v, $m)) {
            return (int)$m[0];
        }
        return null;
    }

    private function capturarNumeroPatron(string $texto, string $pattern): ?int
    {
        if (preg_match($pattern, $texto, $m)) {
            return isset($m[1]) ? (int)$m[1] : null;
        }
        return null;
    }

    private function normalizarEventoId(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = preg_replace('/[^a-z0-9]+/', '_', $texto) ?? $texto;
        return trim($texto, '_');
    }

    /* =========================================================
     * INFERENCIAS ESPECÍFICAS
     * ========================================================= */

    private function inferirTipoProyectoDesdeBloque(string $bloque, ?string $entidad, ?string $convTipo, ?string $convOtros): string
    {
        $texto = mb_strtolower(trim(($entidad ?? '') . ' ' . ($convTipo ?? '') . ' ' . ($convOtros ?? '') . ' ' . $bloque), 'UTF-8');

        if ($this->contieneAlgunTermino($texto, [
            'commission of the european communities',
            'horizon',
            'european',
            'europeo',
            'europea',
            '09001'
        ])) {
            return 'europeo';
        }

        if ($this->contieneAlgunTermino($texto, [
            'ministerio de ciencia',
            'ministerio de universidades',
            'ministerio de ciencia e innovación',
            'ministerio de ciencia e innovacion',
            'plan nacional',
            'pid20',
            'pgc20',
            'cns20',
            '09002'
        ])) {
            return 'nacional';
        }

        if ($this->contieneAlgunTermino($texto, [
            'autonóm',
            'autonom'
        ])) {
            return 'autonomico';
        }

        if ($this->contieneAlgunTermino($texto, [
            'fundación universitaria',
            'fundacion universitaria',
            'precompetitivos',
            'ceu',
            '09000'
        ])) {
            return 'otro_competitivo';
        }

        if ($this->contieneAlgunTermino($texto, [
            'art. 83',
            'art83'
        ])) {
            return 'art83_conocimiento';
        }

        return 'otro_competitivo';
    }

    private function inferirRolProyectoDesdeBloque(string $bloque, ?string $gradoTipo, ?string $gradoOtros, ?string $aportacion): string
    {
        $texto = mb_strtolower(trim(($gradoTipo ?? '') . ' ' . ($gradoOtros ?? '') . ' ' . ($aportacion ?? '') . ' ' . $bloque), 'UTF-8');

        if (($gradoTipo ?? '') === '10001') {
            return 'ip';
        }

        if ($this->contieneAlgunTermino($texto, ['coip', 'co-ip', 'co ip'])) {
            return 'coip';
        }

        if (($gradoTipo ?? '') === '10002') {
            return 'contrato_laboral';
        }

        if (($gradoTipo ?? '') === '10000') {
            return 'investigador';
        }

        if ($this->contieneAlgunTermino($texto, [
            'equipo de trabajo',
            'colaborador',
            'investigador colaborador',
            'miembro del equipo'
        ])) {
            return 'investigador';
        }

        if ($this->contieneAlgunTermino($texto, [
            'contrato laboral',
            'contratado por la upct',
            'investigador doctor contratado',
            'vinculado al proyecto a través de un contrato laboral'
        ])) {
            return 'contrato_laboral';
        }

        if ($this->contieneAlgunTermino($texto, [
            'investigador principal y',
            'soy investigador principal'
        ])) {
            return 'ip';
        }

        return 'investigador';
    }

    private function inferirSiEsContratoLaboralProyecto(string $bloque, ?string $gradoTipo, ?string $aportacion): bool
    {
        if (($gradoTipo ?? '') === '10002') {
            return true;
        }

        $texto = mb_strtolower(trim(($aportacion ?? '') . ' ' . $bloque), 'UTF-8');

        return $this->contieneAlgunTermino($texto, [
            'contrato laboral',
            'investigador doctor contratado',
            'contratado por la upct',
            'vinculado al proyecto a través de un contrato laboral',
        ]);
    }

    private function proyectoPareceCertificado(string $bloque): bool
    {
        $camposMinimos = [
            $this->capturarCampo($bloque, 'TITULO'),
            $this->capturarCampo($bloque, 'ENTIDAD'),
            $this->capturarCampo($bloque, 'FDESDE'),
            $this->capturarCampo($bloque, 'MESES'),
        ];

        $presentes = 0;
        foreach ($camposMinimos as $c) {
            if ($c !== null && trim((string)$c) !== '') {
                $presentes++;
            }
        }

        return $presentes >= 3;
    }

    private function mapearDedicacionProyecto(?string $dedicacion): ?string
    {
        $d = trim((string)$dedicacion);
        return match ($d) {
            'XX001' => 'completa',
            'XX002' => 'compartida',
            default => null,
        };
    }

    private function inferirAmbitoCongresoDesdeBloque(string $bloque): string
    {
        $evento = mb_strtolower(($this->capturarCampo($bloque, 'CONGRESO') ?? '') . ' ' . ($this->capturarCampo($bloque, 'LUGAR') ?? ''), 'UTF-8');
        if ($this->contieneAlgunTermino($evento, ['international', 'internacional', 'benin', 'prague', 'strasbourg', 'méxico', 'mexico', 'cotonou'])) {
            return 'internacional';
        }
        return 'nacional';
    }

    private function inferirTipoCongresoDesdeBloque(string $bloque): string
    {
        $titulo = mb_strtolower(($this->capturarCampo($bloque, 'TITULO') ?? '') . ' ' . ($this->capturarCampo($bloque, 'CONGRESO') ?? ''), 'UTF-8');
        if ($this->contieneAlgunTermino($titulo, ['poster', 'póster'])) {
            return 'poster';
        }
        if ($this->contieneAlgunTermino($titulo, ['ponencia invitada', 'invited'])) {
            return 'ponencia_invitada';
        }
        return 'comunicacion_oral';
    }

    private function inferirTipoDocencia(string $bloque, ?string $especificar): string
    {
        $texto = mb_strtolower(trim(($especificar ?? '') . ' ' . $bloque), 'UTF-8');
        if ($this->contieneAlgunTermino($texto, ['máster', 'master', 'postgrado', 'posgrado'])) {
            return 'master';
        }
        if ($this->contieneAlgunTermino($texto, ['título propio', 'titulo propio'])) {
            return 'titulo_propio';
        }
        return 'grado';
    }

    private function inferirEtapaDocente(?string $denominacion): string
    {
        $d = mb_strtolower((string)$denominacion, 'UTF-8');
        if ($this->contieneAlgunTermino($d, ['predoctoral', 'fpu', 'fpi'])) {
            return 'predoctoral';
        }
        if ($this->contieneAlgunTermino($d, ['postdoctoral', 'posdoctoral', 'juan de la cierva', 'ramón y cajal', 'ramon y cajal'])) {
            return 'posdoctoral';
        }
        return 'estable';
    }

    private function mapearResultadoDocente(float $calific, ?float $max): string
    {
        $escala = $max !== null && $max > 0 ? ($calific / $max) * 10 : $calific;
        if ($escala >= 9.5) {
            return 'excelente';
        }
        if ($escala >= 8.5) {
            return 'muy_favorable';
        }
        if ($escala >= 7.0) {
            return 'favorable';
        }
        return 'aceptable';
    }

    private function esFormacionDocenteUniversitaria(string $texto): bool
    {
        $t = mb_strtolower($texto, 'UTF-8');
        return $this->contieneAlgunTermino($t, [
            'docencia', 'docente', 'campus docente', 'actas', 'innovación docente', 'innovacion docente',
            'uso profesional de la voz', 'personal docente', 'profesorado', 'iniciación a la docencia', 'iniciacion a la docencia'
        ]);
    }

    private function detectarAniosExperienciaDesdeTexto(string $texto): float
    {
        if (preg_match('/horas?:\s*(\d+(?:[\.,]\d+)?)/iu', $texto, $m)) {
            return round(((float)str_replace(',', '.', $m[1])) / 120, 2);
        }
        if (preg_match('/curso:\s*(\d{4})\/(\d{2,4})/iu', $texto, $m)) {
            return 1.0;
        }
        if (preg_match('/(\d+(?:[\.,]\d+)?)\s*años?/iu', $texto, $m)) {
            return (float)str_replace(',', '.', $m[1]);
        }
        if (preg_match('/(\d+)\s*meses?/iu', $texto, $m)) {
            return round(((int)$m[1]) / 12, 2);
        }
        return 1.0;
    }

    private function inferirRelacionExperiencia(string $texto): string
    {
        $t = mb_strtolower($texto, 'UTF-8');
        if ($this->contieneAlgunTermino($t, ['asesor científico', 'asesor cientifico', 'estadísticos', 'estadisticos', 'machine learning', 'análisis', 'analisis', 'investigadores'])) {
            return 'alta';
        }
        if ($this->contieneAlgunTermino($t, ['organizador', 'seminario'])) {
            return 'media';
        }
        return 'media';
    }

    private function mapearPosicionAutorDesdeBloque(string $bloque): ?string
    {
        $pos = $this->capturarEntero($bloque, 'POSICION');
        if ($pos === 1) {
            return 'primero';
        }
        return $pos !== null ? 'intermedio' : $this->detectarPosicionAutor($bloque);
    }

    private function cuartilATercil(?string $cuartil): ?string
    {
        return match (strtoupper((string)$cuartil)) {
            'Q1' => 'T1',
            'Q2' => 'T2',
            'Q3', 'Q4' => 'T3',
            default => null,
        };
    }

    private function esAreaMatematicas(string $bloque): bool
    {
        $area = mb_strtolower(($this->capturarCampo($bloque, 'CALIDAD AREA') ?? ''), 'UTF-8');
        return str_contains($area, 'mathematical') || str_contains($area, 'matem');
    }

    /* =========================================================
     * DETECTORES LEGACY / APOYO
     * ========================================================= */

    private function detectarTipoIndice(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'jcr')) {
            return 'JCR';
        }
        if (str_contains($textoLower, 'sjr')) {
            return 'SJR';
        }
        if (str_contains($textoLower, 'scopus')) {
            return 'SCOPUS';
        }
        if (str_contains($textoLower, 'spi')) {
            return 'SPI';
        }
        if (str_contains($textoLower, 'bci')) {
            return 'BCI';
        }
        if (str_contains($textoLower, 'miar')) {
            return 'MIAR';
        }
        return null;
    }

    private function detectarCuartil(string $texto): ?string
    {
        if (preg_match('/\bQ([1-4])\b/i', $texto, $m)) {
            return 'Q' . $m[1];
        }
        return null;
    }

    private function detectarAfinidad(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'física') || str_contains($textoLower, 'fisica') || str_contains($textoLower, 'physics') || str_contains($textoLower, 'mathematical')) {
            return 'total';
        }
        return null;
    }

    private function detectarPosicionAutor(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (preg_match('/posici[oó]n\s*[:\-]?\s*1\b/iu', $texto) || str_contains($textoLower, 'primer autor') || str_contains($textoLower, 'primero')) {
            return 'primero';
        }
        if (str_contains($textoLower, 'último autor') || str_contains($textoLower, 'ultimo autor') || str_contains($textoLower, 'último') || str_contains($textoLower, 'ultimo')) {
            return 'ultimo';
        }
        if (str_contains($textoLower, 'autor único') || str_contains($textoLower, 'autor unico') || str_contains($textoLower, 'único autor') || str_contains($textoLower, 'unico autor')) {
            return 'autor_unico';
        }
        return null;
    }

    private function detectarNumeroAutores(string $texto): ?int
    {
        if (preg_match('/(\d{1,3})\s+autores?/iu', $texto, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/n[úu]mero\s+de\s+autores?\s*[:\-]?\s*(\d{1,3})/iu', $texto, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    private function detectarDuracionEnAnios(string $texto): ?float
    {
        if (preg_match('/(\d{1,2})\s+años?/iu', $texto, $m)) {
            return (float)$m[1];
        }
        if (preg_match('/(\d{1,3})\s+meses?/iu', $texto, $m)) {
            return round(((int)$m[1]) / 12, 2);
        }
        return null;
    }

    private function detectarNivelEditorial(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'springer') || str_contains($textoLower, 'elsevier') || str_contains($textoLower, 'aps') || str_contains($textoLower, 'iop')) {
            return 'internacional';
        }
        if (str_contains($textoLower, 'universidad') || str_contains($textoLower, 'ceu')) {
            return 'nacional';
        }
        return null;
    }

    private function detectarNivelColeccion(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'colección de referencia') || str_contains($textoLower, 'coleccion de referencia')) {
            return 'referencia';
        }
        if (str_contains($textoLower, 'colección') || str_contains($textoLower, 'coleccion')) {
            return 'normal';
        }
        return null;
    }

    private function detectarNivelResenas(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'varias reseñas') || str_contains($textoLower, 'varias resenas')) {
            return 'varias';
        }
        if (str_contains($textoLower, 'reseñas') || str_contains($textoLower, 'resenas')) {
            return 'algunas';
        }
        return null;
    }

    private function detectarContinuidad(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'continuidad') || str_contains($textoLower, 'encadenada') || str_contains($textoLower, 'proyecto en curso')) {
            return 'encadenada';
        }
        return null;
    }

    private function detectarTipoTransferencia(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'patente') && str_contains($textoLower, 'internacional')) {
            return 'patente_obtenida_internacional';
        }
        if (str_contains($textoLower, 'patente')) {
            return 'patente_obtenida_nacional';
        }
        if (str_contains($textoLower, 'propiedad intelectual') || str_contains($textoLower, 'registro de software')) {
            return 'propiedad_intelectual';
        }
        if (str_contains($textoLower, 'art. 83') || str_contains($textoLower, 'art83')) {
            return 'art83_sin_conocimiento';
        }
        return null;
    }

    private function detectarLiderazgo(string $texto): bool
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        return str_contains($textoLower, 'ip') || str_contains($textoLower, 'director') || str_contains($textoLower, 'responsable') || str_contains($textoLower, 'principal');
    }

    private function detectarAmbitoCongreso(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'internacional')) {
            return 'internacional';
        }
        if (str_contains($textoLower, 'nacional')) {
            return 'nacional';
        }
        if (str_contains($textoLower, 'regional')) {
            return 'regional';
        }
        if (str_contains($textoLower, 'local')) {
            return 'local';
        }
        return null;
    }

    private function detectarTipoCongreso(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');
        if (str_contains($textoLower, 'ponencia invitada')) {
            return 'ponencia_invitada';
        }
        if (str_contains($textoLower, 'comunicación oral') || str_contains($textoLower, 'comunicacion oral')) {
            return 'comunicacion_oral';
        }
        if (str_contains($textoLower, 'póster') || str_contains($textoLower, 'poster')) {
            return 'poster';
        }
        if (str_contains($textoLower, 'ponencia')) {
            return 'ponencia_invitada';
        }
        if (str_contains($textoLower, 'comunicación') || str_contains($textoLower, 'comunicacion')) {
            return 'comunicacion_oral';
        }
        return null;
    }

    private function contieneAlgunTermino(string $texto, array $terminos): bool
    {
        foreach ($terminos as $termino) {
            if (mb_stripos($texto, $termino, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }
}