<?php
declare(strict_types=1);

require_once __DIR__ . '/compat_mbstring.php';

/**
 * Extractor común para CVN FECYT.
 *
 * NO evalúa por rama.
 * Solo convierte el texto del CVN en una estructura JSON común.
 *
 * Compatible con:
 * - Experimentales
 * - Humanidades
 */
class FecytCvnExtractor
{
    public function extraer(string $texto): array
    {
        $texto = $this->normalizar($texto);

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
                'formato' => 'CVN_FECYT',
                'version_esquema' => '3.5-cvn-fecyt-comun',
                'fecha_extraccion' => date('c'),
                'requiere_revision_manual' => true,
            ],
        ];
    }

    private function extraerPublicaciones(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Publicaciones, documentos científicos y técnicos',
            'Publicaciones, documentos cientificos y tecnicos',
        ], [
            'Trabajos presentados en congresos nacionales o internacionales',
            'Trabajos presentados en jornadas',
            'Trabajos presentados en jornadas, seminarios',
            'Actividades de divulgación',
            'Actividades de divulgacion',
            'Otros méritos',
            'Otros meritos',
        ]);

        $items = [];

        if (trim($sec) === '') {
            return $items;
        }

        $sec = preg_replace(
            '/^.*?Publicaciones,\s*documentos\s*cient[ií]ficos\s*y\s*t[eé]cnicos\s*/isu',
            '',
            $sec
        ) ?? $sec;

        $registros = $this->registrosPublicaciones($sec);

        foreach ($registros as $r) {
            $low = $this->lower($r);

            if (
                !str_contains($low, 'tipo de producción') &&
                !str_contains($low, 'tipo de produccion')
            ) {
                continue;
            }

            $esArticuloCientifico =
                str_contains($low, 'tipo de producción: artículo científico') ||
                str_contains($low, 'tipo de produccion: articulo cientifico') ||
                str_contains($low, 'tipo de producción: articulo científico') ||
                str_contains($low, 'tipo de produccion: artículo cientifico') ||
                str_contains($low, 'artículo científico') ||
                str_contains($low, 'articulo cientifico');

            $esDivulgacion =
                str_contains($low, 'artículo de divulgación') ||
                str_contains($low, 'articulo de divulgacion') ||
                str_contains($low, 'libro de divulgación') ||
                str_contains($low, 'libro de divulgacion');

            $esLibro =
                str_contains($low, 'tipo de producción: libro') ||
                str_contains($low, 'tipo de produccion: libro') ||
                str_contains($low, 'libro de divulgación') ||
                str_contains($low, 'libro de divulgacion');

            if (!$esArticuloCientifico || $esDivulgacion || $esLibro) {
                continue;
            }

            $items[] = $this->crearPublicacionItem($r, count($items) + 1);
        }

        if (count($items) === 0 && $this->contiene($sec, ['artículo científico', 'articulo cientifico'])) {
            $items = $this->extraerPublicacionesPorTipoProduccion($sec);
        }

        return $items;
    }

    private function registrosPublicaciones(string $sec): array
    {
        $sec = trim($sec);

        if ($sec === '') {
            return [];
        }

        /*
         * Normalización específica:
         *
         * Convierte:
         * 1
         * Autor...
         *
         * en:
         * 1 Autor...
         */
        $sec = preg_replace('/(^|\n)\s*(\d{1,3})\s*\n\s*/u', "\n$2 ", $sec) ?? $sec;

        $sec = preg_replace('/\n\s*[a-f0-9]{32}\s*\n/iu', "\n", $sec) ?? $sec;
        $sec = preg_replace('/\n\s*\d+\s*\n/u', "\n", $sec) ?? $sec;

        $partes = preg_split('/\n\s*(?=\d{1,3}\s+[A-ZÁÉÍÓÚÑa-záéíóúñ])/u', "\n" . $sec) ?: [];

        $registros = [];

        foreach ($partes as $parte) {
            $parte = trim($parte);

            if ($parte === '') {
                continue;
            }

            $low = $this->lower($parte);

            if (
                !str_contains($low, 'tipo de producción') &&
                !str_contains($low, 'tipo de produccion') &&
                !str_contains($low, 'issn') &&
                !str_contains($low, 'doi')
            ) {
                continue;
            }

            if (mb_strlen($parte, 'UTF-8') < 40) {
                continue;
            }

            $registros[] = $parte;
        }

        if (count($registros) === 0) {
            if (preg_match_all(
                '/(?:^|\n)\s*\d{1,3}\s+.*?(?=(?:\n\s*\d{1,3}\s+[A-ZÁÉÍÓÚÑa-záéíóúñ])|\z)/su',
                "\n" . $sec,
                $m
            )) {
                $registros = array_values(array_filter(array_map('trim', $m[0])));
            }
        }

        if (count($registros) === 0) {
            return [$sec];
        }

        return $registros;
    }

    private function crearPublicacionItem(string $r, int $n): array
    {
        $numAutores = $this->enteroCampo($r, 'Nº total de autores')
            ?? $this->enteroCampo($r, 'No total de autores')
            ?? $this->enteroCampo($r, 'Número total de autores')
            ?? $this->contarAutores($r);

        $pos = $this->enteroCampo($r, 'Posición de firma')
            ?? $this->enteroCampo($r, 'Posicion de firma')
            ?? 1;

        $autorCorrespondenciaTxt = (string)($this->campo($r, 'Autor de correspondencia') ?? '');

        $tipoIndice = $this->inferirIndice($r);
        $cuartil = $this->inferirCuartil($r);
        $tercil = $this->inferirTercil($cuartil);

        return [
            'id' => 'pub_' . str_pad((string)$n, 3, '0', STR_PAD_LEFT),

            'tipo' => 'articulo',
            'tipo_publicacion' => 'articulo_cientifico',
            'es_valido' => true,
            'es_valida' => true,
            'es_articulo_cientifico' => true,
            'es_divulgacion' => false,
            'es_docencia' => false,

            /*
             * Campos compatibles con Experimentales y Humanidades.
             */
            'tipo_indice' => $tipoIndice,
            'indice' => $tipoIndice,
            'indexada' => $tipoIndice !== 'OTRO',
            'jcr' => $tipoIndice === 'JCR',
            'cuartil' => $cuartil,
            'q' => $cuartil,
            'tercil' => $tercil,
            'subtipo_indice' => $tercil,
            'calidad' => (
                $tipoIndice === 'JCR'
                    ? 'alta'
                    : (in_array($tipoIndice, ['SCOPUS', 'SJR', 'FECYT'], true) ? 'media' : 'baja')
            ),

            'afinidad' => $this->inferirAfinidad($r),
            'posicion_autor' => $this->posicionAutor(
                $pos,
                max(1, $numAutores),
                $autorCorrespondenciaTxt
            ),
            'numero_autores' => max(1, $numAutores),
            'num_autores' => max(1, $numAutores),
            'autor_correspondencia' => $this->contiene($autorCorrespondenciaTxt, ['sí', 'si', 'yes']),

            'es_area_matematicas' => $this->contiene($r, [
                'matemática',
                'matematica',
                'mathematical',
                'mathematics',
                'quantum',
                'topological',
                'symmetry',
                'phase transition',
                'lipkin',
                'graphene',
                'qudit',
                'qudit',
                'hilbert',
                'coherent states',
                'quantum information'
            ]),

            'anio' => $this->anio($r),
            'fuente_texto' => trim($r),
            'confianza_extraccion' => 0.92,
            'requiere_revision' => true,
        ];
    }

    private function extraerPublicacionesPorTipoProduccion(string $sec): array
    {
        $items = [];
        $bloques = preg_split('/\n\s*(?=\d+\s+)/u', trim($sec)) ?: [];

        foreach ($bloques as $r) {
            $r = trim($r);
            $low = $this->lower($r);

            if ($r === '') {
                continue;
            }

            if (
                !str_contains($low, 'artículo científico') &&
                !str_contains($low, 'articulo cientifico')
            ) {
                continue;
            }

            if (
                str_contains($low, 'artículo de divulgación') ||
                str_contains($low, 'articulo de divulgacion') ||
                str_contains($low, 'libro de divulgación') ||
                str_contains($low, 'libro de divulgacion')
            ) {
                continue;
            }

            $items[] = $this->crearPublicacionItem($r, count($items) + 1);
        }

        return $items;
    }

    private function extraerLibros(string $texto): array
    {
        $items = [];

        $secLibros = $this->seccion($texto, [
            'Libros y capítulos de libro',
            'Libros y capitulos de libro',
        ], [
            'Proyectos de I+D+i',
            'Trabajos presentados',
            'Experiencia científica y tecnológica',
            'Experiencia cientifica y tecnologica',
        ]);

        foreach ($this->registros($secLibros) as $r) {
            if (!$this->contiene($r, ['isbn', 'editorial', 'libro', 'capítulo', 'capitulo'])) {
                continue;
            }

            $items[] = $this->crearLibroItem($r, count($items) + 1);
        }

        $secPubs = $this->seccion($texto, [
            'Publicaciones, documentos científicos y técnicos',
            'Publicaciones, documentos cientificos y tecnicos',
        ], [
            'Trabajos presentados en congresos nacionales o internacionales',
            'Trabajos presentados en jornadas',
            'Actividades de divulgación',
            'Actividades de divulgacion',
            'Otros méritos',
            'Otros meritos',
        ]);

        foreach ($this->registrosPublicaciones($secPubs) as $r) {
            $low = $this->lower($r);

            if (
                str_contains($low, 'tipo de producción: libro') ||
                str_contains($low, 'tipo de produccion: libro') ||
                str_contains($low, 'libro de divulgación') ||
                str_contains($low, 'libro de divulgacion')
            ) {
                $items[] = $this->crearLibroItem($r, count($items) + 1);
            }
        }

        return $items;
    }

    private function crearLibroItem(string $r, int $n): array
    {
        $low = $this->lower($r);

        $tipo = 'libro';

        if (str_contains($low, 'capítulo') || str_contains($low, 'capitulo')) {
            $tipo = 'capitulo';
        }

        $esDivulgacion = str_contains($low, 'divulgación') || str_contains($low, 'divulgacion');

        return [
            'id' => 'lib_' . str_pad((string)$n, 3, '0', STR_PAD_LEFT),
            'tipo' => $tipo,
            'es_valido' => true,
            'es_libro_investigacion' => !$esDivulgacion,
            'es_divulgacion' => $esDivulgacion,
            'nivel_editorial' => $this->nivelEditorial($r),
            'afinidad' => $this->inferirAfinidad($r),
            'numero_autores' => $this->contarAutores($r),
            'anio' => $this->anio($r),
            'fuente_texto' => trim($r),
            'confianza_extraccion' => 0.75,
            'requiere_revision' => true,
        ];
    }

    private function extraerProyectos(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Proyectos de I+D+i financiados en convocatorias competitivas',
            'Proyectos de investigación y contratos de investigación',
            'Proyectos de investigacion y contratos de investigacion',
        ], [
            'Resultados',
            'Propiedad industrial e intelectual',
            'Actividades científicas y tecnológicas',
            'Actividades cientificas y tecnologicas',
            'Producción científica',
            'Produccion cientifica',
            'Publicaciones, documentos científicos y técnicos',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            if (!$this->contiene($r, [
                'nombre del proyecto',
                'grado de contribución',
                'grado de contribucion',
                'entidad de realización',
                'entidad de realizacion',
                'cuantía total',
                'cuantia total',
                'fecha de inicio-fin'
            ])) {
                continue;
            }

            $rol = (string)($this->campo($r, 'Grado de contribución')
                ?? $this->campo($r, 'Grado de contribucion')
                ?? $this->campo($r, 'Tipo de participación')
                ?? $this->campo($r, 'Tipo de participacion')
                ?? 'investigador');

            $items[] = [
                'id' => 'proy_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'esta_certificado' => true,
                'tipo_proyecto' => $this->tipoProyecto($r),
                'rol' => $this->rolProyecto($rol . ' ' . $r),
                'rol_detectado' => $this->rolProyecto($rol . ' ' . $r),
                'es_contrato_laboral' => false,
                'anios_duracion' => $this->duracionAnios($r),
                'cuantia' => $this->extraerCuantia($r),
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerTransferencia(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Propiedad industrial e intelectual',
            'Transferencia',
        ], [
            'Actividades científicas y tecnológicas',
            'Actividades cientificas y tecnologicas',
            'Producción científica',
            'Produccion cientifica',
            'Publicaciones, documentos científicos y técnicos',
            'Trabajos presentados',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            if (!$this->contiene($r, ['propiedad', 'patente', 'registro', 'registrada', 'concesión', 'concesion'])) {
                continue;
            }

            $items[] = [
                'id' => 'trans_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'propiedad_industrial_intelectual',
                'estado' => $this->contiene($r, ['fecha de concesión', 'fecha de concesion', 'concedida'])
                    ? 'concedida'
                    : 'registrada',
                'ambito' => $this->contiene($r, ['internacional', 'europea', 'europeo'])
                    ? 'internacional'
                    : 'nacional',
                'relevancia' => 'media',
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.78,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerTesisDirigidas(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Dirección de tesis doctorales y/o trabajos fin de estudios',
            'Direccion de tesis doctorales y/o trabajos fin de estudios',
        ], [
            'Tutorías académicas de estudiantes',
            'Tutorias academicas de estudiantes',
            'Cursos y seminarios impartidos',
            'Pluralidad, interdisciplinariedad',
            'Experiencia científica y tecnológica',
            'Experiencia cientifica y tecnologica',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            $low = $this->lower($r);

            if (str_contains($low, 'tesina') || str_contains($low, 'tfm') || str_contains($low, 'tfg')) {
                continue;
            }

            if (!str_contains($low, 'tesis') && !str_contains($low, 'doctor')) {
                continue;
            }

            $items[] = [
                'id' => 'tesis_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo_trabajo' => 'tesis_doctoral',
                'estado' => str_contains($low, 'fecha de defensa') ? 'defendida' : 'en_proceso',
                'rol' => str_contains($low, 'codirector') ? 'codirector' : 'director',
                'anteproyecto_aprobado' => true,
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.85,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerCongresos(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Trabajos presentados en congresos nacionales o internacionales',
        ], [
            'Trabajos presentados en jornadas',
            'Trabajos presentados en jornadas, seminarios',
            'Actividades de divulgación',
            'Actividades de divulgacion',
            'Otros méritos',
            'Otros meritos',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            if (!$this->contiene($r, [
                'nombre del congreso',
                'título del trabajo',
                'titulo del trabajo',
                'ciudad de celebración',
                'ciudad de celebracion',
                'fecha de celebración',
                'fecha de celebracion'
            ])) {
                continue;
            }

            $items[] = [
                'id' => 'cong_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $this->contiene($r, ['poster', 'póster']) ? 'poster' : 'ponencia',
                'ambito' => $this->ambito($r),
                'admision_selectiva' => true,
                'proceso_selectivo' => true,
                'por_invitacion' => $this->contiene($r, ['invitado', 'invitada', 'keynote', 'plenary']),
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.78,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerOtrosMeritosInvestigacion(string $texto): array
    {
        $items = [];

        $grupo = $this->seccion($texto, [
            'Grupos/equipos de investigación, desarrollo o innovación',
            'Grupos/equipos de investigacion, desarrollo o innovacion',
        ], [
            'Actividad científica o tecnológica',
            'Actividad cientifica o tecnologica',
            'Resultados',
            'Propiedad industrial',
            'Actividades científicas',
            'Actividades cientificas',
        ]);

        foreach ($this->registros($grupo) as $r) {
            if ($this->contiene($r, ['nombre del grupo', 'entidad de afiliación', 'entidad de afiliacion'])) {
                $items[] = [
                    'id' => 'omi_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'grupo_investigacion',
                    'relevancia' => 'media',
                    'fuente_texto' => trim($r),
                    'confianza_extraccion' => 0.75,
                    'requiere_revision' => true,
                ];
            }
        }

        $div = $this->seccion($texto, [
            'Actividades de divulgación',
            'Actividades de divulgacion',
        ], [
            'Otros méritos',
            'Otros meritos',
        ]);

        foreach ($this->registros($div) as $r) {
            if ($this->contiene($r, ['título del trabajo', 'titulo del trabajo', 'nombre del evento', 'tipo de evento'])) {
                $items[] = [
                    'id' => 'omi_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'divulgacion',
                    'relevancia' => 'media',
                    'fuente_texto' => trim($r),
                    'confianza_extraccion' => 0.75,
                    'requiere_revision' => true,
                ];
            }
        }

        return $items;
    }

    private function extraerDocenciaUniversitaria(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Formación académica impartida',
            'Formacion academica impartida',
        ], [
            'Dirección de tesis doctorales',
            'Direccion de tesis doctorales',
            'Tutorías académicas',
            'Tutorias academicas',
            'Cursos y seminarios impartidos',
            'Pluralidad, interdisciplinariedad',
            'Experiencia científica y tecnológica',
            'Experiencia cientifica y tecnologica',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            if (!$this->contiene($r, [
                'nombre de la asignatura',
                'nombre de la asignatura/curso',
                'titulación universitaria',
                'titulacion universitaria',
                'entidad de realización',
                'entidad de realizacion'
            ])) {
                continue;
            }

            $horas = $this->decimalCampo($r, 'Nº de horas/créditos ECTS')
                ?? $this->decimalCampo($r, 'No de horas/créditos ECTS')
                ?? $this->decimalCampo($r, 'Nº de horas')
                ?? $this->decimalCampo($r, 'No de horas')
                ?? $this->decimalCampo($r, 'Horas');

            $horasEstimadas = false;

            if ($horas === null || $horas <= 0) {
                $horas = $this->estimarHorasDocencia($r);
                $horasEstimadas = true;
            }

            $titulacion = (string)($this->campo($r, 'Titulación universitaria')
                ?? $this->campo($r, 'Titulacion universitaria')
                ?? '');

            $esMaster = $this->contiene($titulacion, ['máster', 'master']);

            $items[] = [
                'id' => 'doc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'acreditada' => true,
                'horas' => $horas,
                'horas_estimadas' => $horasEstimadas,
                'nivel' => $esMaster ? 'master' : 'grado',
                'tipo' => $esMaster ? 'master' : 'grado',
                'etapa' => $esMaster ? 'postgrado' : 'grado',
                'tfg' => 0,
                'tfm' => 0,
                'fuente_texto' => trim($r),
                'confianza_extraccion' => $horasEstimadas ? 0.60 : 0.85,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerEvaluacionDocente(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Evaluaciones sobre su calidad',
            'Evaluaciones sobre la calidad',
            'Evaluación docente',
            'Evaluacion docente',
        ], [
            'Cursos y seminarios',
            'Material docente',
            'Publicaciones docentes',
            'Proyectos de innovación docente',
        ]);

        if ($sec === '' || !$this->contiene($sec, ['excelente', 'muy favorable', 'favorable', 'positiva'])) {
            return [];
        }

        return [[
            'id' => 'evdoc_001',
            'es_valido' => true,
            'calificacion' => $this->contiene($sec, ['excelente'])
                ? 'excelente'
                : ($this->contiene($sec, ['muy favorable']) ? 'muy_favorable' : 'favorable'),
            'numero' => 1,
            'fuente_texto' => trim($sec),
            'confianza_extraccion' => 0.65,
            'requiere_revision' => true,
        ]];
    }

    private function extraerFormacionDocente(string $texto): array
    {
        $items = [];

        $recibidos = $this->seccion($texto, [
            'Cursos y seminarios recibidos de perfeccionamiento, innovación y mejora docente',
            'Cursos y seminarios recibidos de perfeccionamiento, innovacion y mejora docente',
        ], [
            'Conocimiento de idiomas',
            'Actividad docente',
        ]);

        foreach ($this->registros($recibidos) as $r) {
            if (!$this->contiene($r, ['título del curso', 'titulo del curso', 'curso/seminario', 'duración en horas', 'duracion en horas'])) {
                continue;
            }

            $items[] = [
                'id' => 'fdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'curso',
                'rol' => 'asistente',
                'horas' => $this->decimalCampo($r, 'Duración en horas')
                    ?? $this->decimalCampo($r, 'Duracion en horas')
                    ?? 0,
                'ambito' => 'nacional',
                'relacion_docente' => true,
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.80,
                'requiere_revision' => true,
            ];
        }

        $impartidos = $this->seccion($texto, [
            'Cursos y seminarios impartidos',
        ], [
            'Proyectos de innovación docente',
            'Proyectos de innovacion docente',
            'Experiencia científica',
            'Experiencia cientifica',
        ]);

        foreach ($this->registros($impartidos) as $r) {
            if (!$this->contiene($r, ['tipo de evento', 'nombre del evento', 'horas impartidas'])) {
                continue;
            }

            $items[] = [
                'id' => 'fdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $this->contiene($r, ['curso']) ? 'curso' : 'seminario',
                'rol' => 'ponente',
                'horas' => $this->decimalCampo($r, 'Horas impartidas') ?? 0,
                'ambito' => $this->ambito($r),
                'por_invitacion' => false,
                'relacion_docente' => $this->contiene($r, ['formación docente', 'formacion docente', 'docente']),
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.78,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerMaterialDocente(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Proyectos de innovación docente',
            'Proyectos de innovacion docente',
        ], [
            'Experiencia científica y tecnológica',
            'Experiencia cientifica y tecnologica',
            'Grupos/equipos',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            if (!$this->contiene($r, ['título del proyecto', 'titulo del proyecto', 'tipo de participación', 'tipo de participacion'])) {
                continue;
            }

            $items[] = [
                'id' => 'mdoc_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'proyecto_innovacion',
                'rol' => $this->contiene($r, ['investigador principal', 'ip']) ? 'ip' : 'participante',
                'isbn_issn' => false,
                'relevancia' => 'media',
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.78,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerFormacionAcademica(string $texto): array
    {
        $items = [];

        $secFormacion = $this->seccion($texto, [
            'Formación académica recibida',
            'Formacion academica recibida',
        ], [
            'Conocimiento de idiomas',
            'Actividad docente',
        ]);

        $tit = $this->seccion($secFormacion, [
            'Titulación universitaria',
            'Titulacion universitaria',
        ], [
            'Doctorados',
            'Cursos y seminarios recibidos',
        ]);

        if ($tit !== '' && $this->contiene($tit, ['nombre del título', 'nombre del titulo', 'graduado', 'licenciado', 'doctor', 'máster', 'master'])) {
            $items[] = [
                'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => $this->contiene($tit, ['máster', 'master']) ? 'master' : 'titulacion_universitaria',
                'alta_competitividad' => false,
                'fuente_texto' => trim($tit),
                'confianza_extraccion' => 0.82,
                'requiere_revision' => true,
            ];
        }

        $doc = $this->seccion($secFormacion, [
            'Doctorados',
        ], [
            'Cursos y seminarios recibidos',
            'Conocimiento de idiomas',
            'Actividad docente',
        ]);

        if ($doc !== '' && $this->contiene($doc, ['programa de doctorado', 'doctorado', 'doctor'])) {
            $items[] = [
                'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'doctorado',
                'alta_competitividad' => false,
                'fuente_texto' => trim($doc),
                'confianza_extraccion' => 0.90,
                'requiere_revision' => true,
            ];

            if ($this->contiene($doc, ['doctorado europeo: sí', 'doctorado europeo: si', 'doctorado internacional'])) {
                $items[] = [
                    'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'doctorado_internacional',
                    'alta_competitividad' => true,
                    'fuente_texto' => trim($doc),
                    'confianza_extraccion' => 0.90,
                    'requiere_revision' => true,
                ];
            }

            if ($this->contiene($doc, ['mención de calidad: sí', 'mencion de calidad: si'])) {
                $items[] = [
                    'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'mencion_calidad',
                    'alta_competitividad' => false,
                    'fuente_texto' => trim($doc),
                    'confianza_extraccion' => 0.90,
                    'requiere_revision' => true,
                ];
            }
        }

        $ayudas = $this->seccion($texto, [
            'Ayudas y becas obtenidas',
        ], [
            'Actividades científicas',
            'Actividades cientificas',
            'Otros méritos',
            'Otros meritos',
        ]);

        foreach ($this->registros($ayudas) as $r) {
            if (!$this->contiene($r, ['nombre de la ayuda', 'beca', 'fpu', 'fpi'])) {
                continue;
            }

            $items[] = [
                'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'beca_competitiva',
                'alta_competitividad' => $this->contiene($r, ['fpu', 'fpi']),
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.82,
                'requiere_revision' => true,
            ];
        }

        $estancias = $this->seccion($texto, [
            'Estancias en centros públicos o privados',
            'Estancias en centros publicos o privados',
        ], [
            'Ayudas y becas',
            'Otros méritos',
            'Otros meritos',
        ]);

        foreach ($this->registros($estancias) as $r) {
            if (!$this->contiene($r, ['entidad de realización', 'entidad de realizacion', 'duración', 'duracion'])) {
                continue;
            }

            $items[] = [
                'id' => 'form_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'tipo' => 'estancia',
                'duracion' => $this->duracionMeses($r),
                'alta_competitividad' => false,
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.78,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerExperienciaProfesional(string $texto): array
    {
        $sec = $this->seccion($texto, [
            'Situación profesional actual',
            'Situacion profesional actual',
        ], [
            'Cargos y actividades desempeñados con anterioridad',
            'Formación académica recibida',
            'Formacion academica recibida',
        ]);

        $sec .= "\n" . $this->seccion($texto, [
            'Cargos y actividades desempeñados con anterioridad',
        ], [
            'Resumen de la actividad profesional',
            'Formación académica recibida',
            'Formacion academica recibida',
        ]);

        $items = [];

        foreach ($this->registros($sec) as $r) {
            if (!$this->contiene($r, ['entidad empleadora', 'categoría profesional', 'categoria profesional', 'fecha de inicio'])) {
                continue;
            }

            $items[] = [
                'id' => 'exp_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                'es_valido' => true,
                'relacion' => $this->contiene($r, ['universidad', 'facultad', 'departamento']) ? 'alta' : 'media',
                'anios' => max(0.2, $this->duracionAnios($r)),
                'fuente_texto' => trim($r),
                'confianza_extraccion' => 0.72,
                'requiere_revision' => true,
            ];
        }

        return $items;
    }

    private function extraerBloque4(string $texto): array
    {
        $items = [];

        $div = $this->seccion($texto, [
            'Actividades de divulgación',
            'Actividades de divulgacion',
        ], [
            'Otros méritos',
            'Otros meritos',
        ]);

        foreach ($this->registros($div) as $r) {
            if ($this->contiene($r, ['título del trabajo', 'titulo del trabajo', 'nombre del evento', 'tipo de evento'])) {
                $items[] = [
                    'id' => 'b4_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => 'divulgacion',
                    'relevancia' => 'media',
                    'fuente_texto' => trim($r),
                    'confianza_extraccion' => 0.75,
                    'requiere_revision' => true,
                ];
            }
        }

        $otros = $this->seccion($texto, [
            'Otros méritos',
            'Otros meritos',
        ], []);

        foreach ($this->registros($otros) as $r) {
            if ($this->contiene($r, ['sexenio', 'gestión', 'gestion', 'actividad investigadora', 'actividad docente'])) {
                $items[] = [
                    'id' => 'b4_' . str_pad((string)(count($items) + 1), 3, '0', STR_PAD_LEFT),
                    'es_valido' => true,
                    'tipo' => $this->contiene($r, ['gestión', 'gestion']) ? 'gestion_universitaria' : 'otros_meritos',
                    'relevancia' => $this->contiene($r, ['sexenio']) ? 'alta' : 'media',
                    'fuente_texto' => trim($r),
                    'confianza_extraccion' => 0.72,
                    'requiere_revision' => true,
                ];
            }
        }

        return $items;
    }

    private function normalizar(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r", "\f"], ["\n", "\n", "\n"], $texto);
        $texto = preg_replace('/\n\s*[a-f0-9]{32}\s*\n/iu', "\n", $texto) ?? $texto;
        $texto = preg_replace('/[ \t]+/u', ' ', $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto) ?? $texto;
        return trim($texto);
    }

    private function seccion(string $texto, array $inicios, array $finales = []): string
    {
        $ini = null;

        foreach ($inicios as $inicio) {
            $pos = mb_stripos($texto, $inicio, 0, 'UTF-8');

            if ($pos !== false && ($ini === null || $pos < $ini)) {
                $ini = $pos;
            }
        }

        if ($ini === null) {
            return '';
        }

        $fin = mb_strlen($texto, 'UTF-8');

        foreach ($finales as $final) {
            $pos = mb_stripos($texto, $final, $ini + 20, 'UTF-8');

            if ($pos !== false && $pos > $ini && $pos < $fin) {
                $fin = $pos;
            }
        }

        return trim(mb_substr($texto, $ini, $fin - $ini, 'UTF-8'));
    }

    private function registros(string $sec): array
    {
        $sec = trim($sec);

        if ($sec === '') {
            return [];
        }

        $sec = preg_replace('/(^|\n)\s*(\d{1,3})\s*\n\s*/u', "\n$2 ", $sec) ?? $sec;

        $partes = preg_split(
            '/\n\s*(?=\d{1,3}\s+(?:Título|Titulo|Nombre|Entidad|Tipo|Rosa|Ramon|Ramón|Alberto|Manuel|Julio|Programa|Titulación|Titulacion|Autor|Universidad))/u',
            "\n" . $sec
        ) ?: [];

        $partes = array_values(array_filter(array_map('trim', $partes)));

        if (count($partes) <= 1) {
            return [$sec];
        }

        $limpias = [];

        foreach ($partes as $p) {
            if (mb_strlen($p, 'UTF-8') < 20) {
                continue;
            }

            $limpias[] = $p;
        }

        return $limpias ?: [$sec];
    }

    private function campo(string $txt, string $campo): ?string
    {
        $campoQ = preg_quote($campo, '/');

        if (preg_match(
            '/' . $campoQ . '\s*:\s*(.+?)(?=\s+[A-ZÁÉÍÓÚÑa-záéíóúñºÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑa-záéíóúñºÁÉÍÓÚÑ\s\/]{1,80}:|\n\s*\d{1,3}\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]|$)/su',
            $txt,
            $m
        )) {
            return trim($m[1]);
        }

        return null;
    }

    private function enteroCampo(string $txt, string $campo): ?int
    {
        $v = $this->campo($txt, $campo);

        if ($v === null) {
            return null;
        }

        if (preg_match('/\d+/u', $v, $m)) {
            return (int)$m[0];
        }

        return null;
    }

    private function decimalCampo(string $txt, string $campo): ?float
    {
        $v = $this->campo($txt, $campo);

        if ($v === null) {
            return null;
        }

        if (preg_match('/\d+(?:[\.,]\d+)?/u', $v, $m)) {
            return (float)str_replace(',', '.', $m[0]);
        }

        return null;
    }

    private function contiene(string $txt, array $needles): bool
    {
        $low = $this->lower($txt);

        foreach ($needles as $n) {
            if (str_contains($low, $this->lower($n))) {
                return true;
            }
        }

        return false;
    }

    private function lower(string $s): string
    {
        return mb_strtolower($s, 'UTF-8');
    }

    private function anio(string $txt): ?int
    {
        if (preg_match_all('/(?:19|20)\d{2}/u', $txt, $m) && !empty($m[0])) {
            return (int)max($m[0]);
        }

        return null;
    }

    private function contarAutores(string $txt): int
    {
        $primeraLinea = trim(strtok($txt, "\n") ?: $txt);

        if (str_contains($primeraLinea, ';')) {
            return max(1, substr_count($primeraLinea, ';') + 1);
        }

        return 1;
    }

    private function posicionAutor(int $pos, int $total, string $corresp = ''): string
    {
        if ($this->contiene($corresp, ['sí', 'si', 'yes'])) {
            return 'correspondencia';
        }

        if ($pos <= 1) {
            return 'primero';
        }

        if ($pos >= $total && $total > 1) {
            return 'ultimo';
        }

        return 'intermedio';
    }

    private function inferirIndice(string $txt): string
    {
        if ($this->contiene($txt, [
            'jcr',
            'web of science',
            'science citation',
            'scipost',
            'physical review',
            'phys. rev',
            'phys rev',
            'physica e',
            'journal of physics',
            'quantum information processing',
            'american physical society',
            'iop publishing',
            'elsevier',
            'springer',
            'springer nature',
            'mdpi',
            'symmetry',
            'sciPost physics'
        ])) {
            return 'JCR';
        }

        if ($this->contiene($txt, ['scopus', 'sjr'])) {
            return 'SCOPUS';
        }

        if ($this->contiene($txt, ['fecyt'])) {
            return 'FECYT';
        }

        if ($this->contiene($txt, ['resh'])) {
            return 'RESH';
        }

        if ($this->contiene($txt, ['erih'])) {
            return 'ERIH';
        }

        if ($this->contiene($txt, ['miar'])) {
            return 'MIAR';
        }

        if ($this->contiene($txt, ['issn', 'doi', 'revista', 'journal'])) {
            return 'SJR';
        }

        return 'OTRO';
    }

    private function inferirCuartil(string $txt): string
    {
        if (preg_match('/\bQ\s*([1-4])\b/iu', $txt, $m)) {
            return 'Q' . $m[1];
        }

        if ($this->contiene($txt, [
            'scipost phys',
            'physical review',
            'phys. rev',
            'phys rev',
            'american physical society',
            'journal of physics a'
        ])) {
            return 'Q1';
        }

        if ($this->contiene($txt, [
            'physica e',
            'elsevier',
            'quantum information processing',
            'springer',
            'springer nature',
            'iop publishing'
        ])) {
            return 'Q2';
        }

        if ($this->contiene($txt, [
            'symmetry',
            'mdpi'
        ])) {
            return 'Q3';
        }

        if ($this->contiene($txt, [
            'doi',
            'issn',
            'revista',
            'journal'
        ])) {
            return 'Q3';
        }

        return '';
    }

    private function inferirTercil(string $cuartil): string
    {
        $q = strtoupper(trim($cuartil));

        return match ($q) {
            'Q1' => 'T1',
            'Q2' => 'T2',
            'Q3', 'Q4' => 'T3',
            default => '',
        };
    }

    private function inferirAfinidad(string $txt): string
    {
        return $this->contiene($txt, [
            'arqueolog',
            'historia',
            'arte',
            'filolog',
            'filosof',
            'geograf',
            'humanidades',
            'física',
            'fisica',
            'matemática',
            'matematica',
            'química',
            'quimica',
            'biología',
            'biologia',
            'quantum',
            'cuántic',
            'cuantic',
            'topological',
            'topológic',
            'topologic',
            'graphene',
            'nanoelectr',
            'symmetric',
            'entanglement',
            'phase transition',
            'lipkin'
        ])
            ? 'alta'
            : 'media';
    }

    private function nivelEditorial(string $txt): string
    {
        if ($this->contiene($txt, ['cambridge', 'oxford', 'springer', 'routledge', 'brill', 'wiley', 'elsevier'])) {
            return 'internacional';
        }

        if ($this->contiene($txt, ['universidad', 'csic', 'cátedra', 'catedra', 'tirant', 'aranzadi', 'comares'])) {
            return 'nacional';
        }

        return 'desconocido';
    }

    private function tipoProyecto(string $txt): string
    {
        if ($this->contiene($txt, ['unión europea', 'union europea', 'europeo', 'horizon', 'international'])) {
            return 'internacional';
        }

        if ($this->contiene($txt, ['nacional', 'ministerio', 'aei', 'plan nacional'])) {
            return 'nacional';
        }

        if ($this->contiene($txt, ['autonómica', 'autonomica', 'junta de andalucía', 'junta de andalucia'])) {
            return 'autonomico';
        }

        return 'otros';
    }

    private function rolProyecto(string $txt): string
    {
        if ($this->contiene($txt, ['investigador principal', 'ip', 'co-ip', 'coip'])) {
            return 'ip';
        }

        return 'investigador';
    }

    private function ambito(string $txt): string
    {
        if ($this->contiene($txt, [
            'internacional',
            'unión europea',
            'union europea',
            'méxico',
            'mexico',
            'francia',
            'italia',
            'alemania',
            'portugal',
            'benín',
            'benin',
            'praga',
            'strasbourg',
            'cotonou',
            'república checa',
            'republica checa'
        ])) {
            return 'internacional';
        }

        if ($this->contiene($txt, ['nacional', 'españa', 'spain'])) {
            return 'nacional';
        }

        return 'autonomico';
    }

    private function duracionAnios(string $txt): float
    {
        if (preg_match('/Duración\s*:\s*(\d+)\s*años?/iu', $txt, $m)) {
            return (float)$m[1];
        }

        if (preg_match('/Duración\s*:\s*(\d+)\s*meses?/iu', $txt, $m)) {
            return round(((float)$m[1]) / 12, 2);
        }

        if (preg_match_all('/(?:19|20)\d{2}/u', $txt, $m) && count($m[0]) >= 2) {
            $years = array_map('intval', $m[0]);
            return max(0.2, (float)(max($years) - min($years)));
        }

        return 0.5;
    }

    private function duracionMeses(string $txt): float
    {
        if (preg_match('/Duración\s*:\s*(\d+)\s*meses?/iu', $txt, $m)) {
            return (float)$m[1];
        }

        if (preg_match('/Duración\s*:\s*(\d+)\s*años?/iu', $txt, $m)) {
            return (float)$m[1] * 12;
        }

        return max(1, $this->duracionAnios($txt) * 12);
    }

    private function estimarHorasDocencia(string $txt): float
    {
        if (preg_match_all('/(?:19|20)\d{2}/u', $txt, $m) && count($m[0]) >= 2) {
            $years = array_map('intval', $m[0]);
            $dur = max(1, max($years) - min($years));

            return min(225.0, $dur * 45.0);
        }

        return 45.0;
    }

    private function extraerCuantia(string $txt): float
    {
        if (!preg_match('/Cuantía total\s*:\s*([0-9\.\,]+)/iu', $txt, $m) &&
            !preg_match('/Cuantia total\s*:\s*([0-9\.\,]+)/iu', $txt, $m)) {
            return 0.0;
        }

        $n = str_replace('.', '', $m[1]);
        $n = str_replace(',', '.', $n);

        return is_numeric($n) ? (float)$n : 0.0;
    }
}