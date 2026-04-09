<?php

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
                "comite" => "CSYJ",
                "subcomite" => null,
                "archivo_pdf" => null,
                "fecha_extraccion" => date('c'),
                "version_esquema" => "1.0",
                "requiere_revision_manual" => true
            ]
        ];

        return $resultado;
    }

    private function normalizarTexto(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $texto = preg_replace('/[ \t]+/u', ' ', $texto);
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto);

        return trim($texto);
    }

    private function extraerPublicaciones(string $texto): array
    {
        $publicaciones = [];
        $lineas = preg_split('/\n+/u', $texto);

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') {
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            $pareceArticulo =
                str_contains($lineaLower, 'doi') ||
                str_contains($lineaLower, 'issn') ||
                str_contains($lineaLower, 'jcr') ||
                str_contains($lineaLower, 'sjr') ||
                str_contains($lineaLower, 'scopus') ||
                str_contains($lineaLower, 'revista') ||
                preg_match('/\bq[1-4]\b/i', $lineaTrim);

            if (!$pareceArticulo) {
                continue;
            }

            $tipoIndice = $this->detectarTipoIndice($lineaTrim);
            $cuartil = $this->detectarCuartil($lineaTrim);
            $numeroAutores = $this->detectarNumeroAutores($lineaTrim);
            $posicionAutor = $this->detectarPosicionAutor($lineaTrim);
            $citas = $this->detectarCitas($lineaTrim);
            $anio = $this->detectarAnio($lineaTrim);
            $aniosDesdePublicacion = $anio !== null ? max((int) date('Y') - $anio, 0) : null;

            $publicaciones[] = [
                "id" => "pub_" . str_pad((string) (count($publicaciones) + 1), 3, '0', STR_PAD_LEFT),
                "tipo" => "articulo",
                "es_valida" => !$this->esPublicacionNoValida($lineaTrim),
                "es_divulgacion" => $this->contieneAlgunTermino($lineaLower, ['divulgación', 'divulgacion']),
                "es_docencia" => $this->contieneAlgunTermino($lineaLower, ['docencia', 'didáctica', 'didactica']),
                "es_acta_congreso" => $this->contieneAlgunTermino($lineaLower, ['acta', 'actas', 'proceedings']),
                "es_informe_proyecto" => $this->contieneAlgunTermino($lineaLower, ['informe de proyecto', 'deliverable']),
                "tipo_indice" => $tipoIndice,
                "cuartil" => $cuartil,
                "afinidad" => $this->detectarAfinidad($lineaTrim),
                "posicion_autor" => $posicionAutor,
                "numero_autores" => $numeroAutores,
                "citas" => $citas,
                "anios_desde_publicacion" => $aniosDesdePublicacion,
                "numero_trabajos_misma_revista" => 1,
                "fuente_texto" => $lineaTrim,
                "confianza_extraccion" => 0.65,
                "requiere_revision" => true
            ];
        }

        return $publicaciones;
    }

    private function extraerLibros(string $texto): array
    {
        $libros = [];
        $lineas = preg_split('/\n+/u', $texto);

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') {
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            $pareceLibro =
                str_contains($lineaLower, 'isbn') ||
                str_contains($lineaLower, 'editorial') ||
                str_contains($lineaLower, 'capítulo') ||
                str_contains($lineaLower, 'capitulo') ||
                str_contains($lineaLower, 'libro');

            if (!$pareceLibro) {
                continue;
            }

            $tipo = str_contains($lineaLower, 'capítulo') || str_contains($lineaLower, 'capitulo')
                ? 'capitulo'
                : 'libro';

            $libros[] = [
                "id" => "lib_" . str_pad((string) (count($libros) + 1), 3, '0', STR_PAD_LEFT),
                "tipo" => $tipo,
                "es_valido" => !$this->contieneAlgunTermino($lineaLower, ['autoedición', 'autoedicion', 'acta', 'actas']),
                "es_libro_investigacion" => true,
                "es_autoedicion" => $this->contieneAlgunTermino($lineaLower, ['autoedición', 'autoedicion']),
                "es_acta_congreso" => $this->contieneAlgunTermino($lineaLower, ['acta', 'actas', 'proceedings']),
                "es_labor_edicion" => $this->contieneAlgunTermino($lineaLower, ['editor', 'coordinador', 'edición', 'edicion']),
                "nivel_editorial" => $this->detectarNivelEditorial($lineaTrim),
                "nivel_coleccion" => $this->detectarNivelColeccion($lineaTrim),
                "afinidad" => $this->detectarAfinidad($lineaTrim),
                "numero_autores" => $this->detectarNumeroAutores($lineaTrim),
                "posicion_autor" => $this->detectarPosicionAutor($lineaTrim),
                "nivel_resenas" => $this->detectarNivelResenas($lineaTrim),
                "fuente_texto" => $lineaTrim,
                "confianza_extraccion" => 0.55,
                "requiere_revision" => true
            ];
        }

        return $libros;
    }

    private function extraerProyectos(string $texto): array
    {
        $proyectos = [];
        $lineas = preg_split('/\n+/u', $texto);

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') {
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            $pareceProyecto =
                str_contains($lineaLower, 'proyecto') ||
                str_contains($lineaLower, 'research project') ||
                str_contains($lineaLower, 'ip ') ||
                str_contains($lineaLower, 'coip') ||
                str_contains($lineaLower, 'investigador principal');

            if (!$pareceProyecto) {
                continue;
            }

            $proyectos[] = [
                "id" => "proy_" . str_pad((string) (count($proyectos) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "esta_certificado" => null,
                "tipo_proyecto" => $this->detectarTipoProyecto($lineaTrim),
                "rol" => $this->detectarRolProyecto($lineaTrim),
                "dedicacion" => $this->detectarDedicacion($lineaTrim),
                "anios_duracion" => $this->detectarDuracionEnAnios($lineaTrim),
                "continuidad" => $this->detectarContinuidad($lineaTrim),
                "fuente_texto" => $lineaTrim,
                "confianza_extraccion" => 0.60,
                "requiere_revision" => true
            ];
        }

        return $proyectos;
    }

    private function extraerTransferencia(string $texto): array
    {
        $transferencia = [];
        $lineas = preg_split('/\n+/u', $texto);

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') {
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            $pareceTransferencia =
                str_contains($lineaLower, 'contrato') ||
                str_contains($lineaLower, 'transferencia') ||
                str_contains($lineaLower, 'spin-off') ||
                str_contains($lineaLower, 'patente') ||
                str_contains($lineaLower, 'software');

            if (!$pareceTransferencia) {
                continue;
            }

            $transferencia[] = [
                "id" => "trans_" . str_pad((string) (count($transferencia) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "tipo" => $this->detectarTipoTransferencia($lineaTrim),
                "impacto_externo" => true,
                "liderazgo" => $this->detectarLiderazgo($lineaTrim),
                "participacion_menor" => false,
                "fuente_texto" => $lineaTrim,
                "confianza_extraccion" => 0.50,
                "requiere_revision" => true
            ];
        }

        return $transferencia;
    }

    private function extraerTesisDirigidas(string $texto): array
    {
        $tesis = [];
        $lineas = preg_split('/\n+/u', $texto);

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') {
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            if (!str_contains($lineaLower, 'tesis')) {
                continue;
            }

            $tesis[] = [
                "id" => "tesis_" . str_pad((string) (count($tesis) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "tipo" => str_contains($lineaLower, 'codirección') || str_contains($lineaLower, 'codireccion')
                    ? 'codireccion'
                    : 'direccion_unica',
                "calidad_especial" => $this->contieneAlgunTermino($lineaLower, ['internacional', 'premio', 'mención', 'mencion']),
                "fuente_texto" => $lineaTrim,
                "confianza_extraccion" => 0.55,
                "requiere_revision" => true
            ];
        }

        return $tesis;
    }

    private function extraerCongresos(string $texto): array
    {
        $congresos = [];
        $lineas = preg_split('/\n+/u', $texto);

        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim === '') {
                continue;
            }

            $lineaLower = mb_strtolower($lineaTrim, 'UTF-8');

            $pareceCongreso =
                str_contains($lineaLower, 'congreso') ||
                str_contains($lineaLower, 'seminario') ||
                str_contains($lineaLower, 'jornada') ||
                str_contains($lineaLower, 'ponencia') ||
                str_contains($lineaLower, 'comunicación') ||
                str_contains($lineaLower, 'comunicacion');

            if (!$pareceCongreso) {
                continue;
            }

            $congresos[] = [
                "id" => "cong_" . str_pad((string) (count($congresos) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "ambito" => $this->detectarAmbitoCongreso($lineaTrim),
                "tipo" => $this->detectarTipoCongreso($lineaTrim),
                "id_evento" => "evento_" . (count($congresos) + 1),
                "fuente_texto" => $lineaTrim,
                "confianza_extraccion" => 0.60,
                "requiere_revision" => true
            ];
        }

        return $congresos;
    }

    private function extraerOtrosMeritosInvestigacion(string $texto): array
    {
        $otros = [];
        $tipos = [
            'revisor' => ['revisor', 'reviewer'],
            'consejo_editorial' => ['consejo editorial', 'editorial board'],
            'premio' => ['premio', 'award'],
            'grupo_investigacion' => ['grupo de investigación', 'grupo investigacion'],
            'tribunal_tesis' => ['tribunal de tesis'],
        ];

        foreach ($tipos as $tipo => $terminos) {
            foreach ($terminos as $termino) {
                if (mb_stripos($texto, $termino, 0, 'UTF-8') !== false) {
                    $otros[] = [
                        "id" => "omi_" . str_pad((string) (count($otros) + 1), 3, '0', STR_PAD_LEFT),
                        "es_valido" => true,
                        "tipo" => $tipo,
                        "fuente_texto" => $termino,
                        "confianza_extraccion" => 0.60,
                        "requiere_revision" => true
                    ];
                    break;
                }
            }
        }

        return $otros;
    }

    private function extraerDocenciaUniversitaria(string $texto): array
    {
        $docencia = [];

        if (preg_match('/(\d{2,6})\s*horas?.{0,30}\bgrado\b/iu', $texto, $m)) {
            $docencia[] = [
                "id" => "doc_" . str_pad((string) (count($docencia) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "horas" => (int) $m[1],
                "nivel" => "grado",
                "responsabilidad" => "alta",
                "fuente_texto" => $m[0],
                "confianza_extraccion" => 0.75,
                "requiere_revision" => false
            ];
        }

        if (preg_match('/(\d{2,6})\s*horas?.{0,30}(máster|master|posgrado)/iu', $texto, $m)) {
            $docencia[] = [
                "id" => "doc_" . str_pad((string) (count($docencia) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "horas" => (int) $m[1],
                "nivel" => "master",
                "responsabilidad" => "alta",
                "fuente_texto" => $m[0],
                "confianza_extraccion" => 0.75,
                "requiere_revision" => false
            ];
        }

        return $docencia;
    }

    private function extraerEvaluacionDocente(string $texto): array
    {
        $evaluaciones = [];

        if (preg_match('/docentia/iu', $texto)) {
            $resultado = "positiva";

            if (preg_match('/docentia.{0,25}(excelente|sobresaliente)/iu', $texto)) {
                $resultado = "excelente";
            }

            $evaluaciones[] = [
                "id" => "eval_" . str_pad((string) (count($evaluaciones) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "tipo" => "docentia",
                "resultado" => $resultado,
                "fuente_texto" => "DOCENTIA",
                "confianza_extraccion" => 0.80,
                "requiere_revision" => false
            ];
        }

        if (preg_match('/encuestas?|evaluación docente|evaluacion docente/iu', $texto)) {
            $evaluaciones[] = [
                "id" => "eval_" . str_pad((string) (count($evaluaciones) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "tipo" => "encuestas",
                "resultado" => "positiva",
                "fuente_texto" => "encuestas/evaluación docente",
                "confianza_extraccion" => 0.65,
                "requiere_revision" => true
            ];
        }

        return $evaluaciones;
    }

    private function extraerFormacionDocente(string $texto): array
    {
        $formacion = [];

        if (preg_match('/(\d{1,4})\s*horas?.{0,30}(formación docente|formacion docente|docencia)/iu', $texto, $m)) {
            $formacion[] = [
                "id" => "fdoc_" . str_pad((string) (count($formacion) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "horas" => (int) $m[1],
                "rol" => "docente",
                "fuente_texto" => $m[0],
                "confianza_extraccion" => 0.65,
                "requiere_revision" => true
            ];
        }

        return $formacion;
    }

    private function extraerMaterialDocente(string $texto): array
    {
        $material = [];

        if (preg_match('/isbn|issn|material publicado|manual docente/iu', $texto)) {
            $material[] = [
                "id" => "mat_" . str_pad((string) (count($material) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "tipo" => "material_publicado",
                "fuente_texto" => "material publicado",
                "confianza_extraccion" => 0.60,
                "requiere_revision" => true
            ];
        }

        if (preg_match('/innovación docente|innovacion docente/iu', $texto)) {
            $material[] = [
                "id" => "mat_" . str_pad((string) (count($material) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "tipo" => "proyecto_innovacion",
                "fuente_texto" => "proyecto de innovación docente",
                "confianza_extraccion" => 0.75,
                "requiere_revision" => false
            ];
        }

        return $material;
    }

    private function extraerFormacionAcademica(string $texto): array
    {
        $formacion = [];

        $tipos = [
            'doctorado_internacional' => ['mención internacional', 'mencion internacional', 'doctorado internacional'],
            'beca_competitiva' => ['fpu', 'fpi', 'beca competitiva'],
            'estancia' => ['estancia', 'movilidad'],
            'master' => ['máster', 'master']
        ];

        foreach ($tipos as $tipo => $terminos) {
            foreach ($terminos as $termino) {
                if (mb_stripos($texto, $termino, 0, 'UTF-8') !== false) {
                    $formacion[] = [
                        "id" => "form_" . str_pad((string) (count($formacion) + 1), 3, '0', STR_PAD_LEFT),
                        "es_valido" => true,
                        "tipo" => $tipo,
                        "fuente_texto" => $termino,
                        "confianza_extraccion" => 0.70,
                        "requiere_revision" => $tipo !== 'doctorado_internacional'
                    ];
                    break;
                }
            }
        }

        return $formacion;
    }

    private function extraerExperienciaProfesional(string $texto): array
    {
        $experiencia = [];

        if (preg_match('/(\d{1,2})\s*años?.{0,50}(experiencia profesional|empresa|sector)/iu', $texto, $m)) {
            $experiencia[] = [
                "id" => "exp_" . str_pad((string) (count($experiencia) + 1), 3, '0', STR_PAD_LEFT),
                "es_valido" => true,
                "anios" => (int) $m[1],
                "relacion" => "alta",
                "fuente_texto" => $m[0],
                "confianza_extraccion" => 0.70,
                "requiere_revision" => false
            ];
        }

        return $experiencia;
    }

    private function extraerBloque4(string $texto): array
    {
        $bloque4 = [];
        $tipos = [
            'gestion' => ['gestión', 'gestion', 'coordinador', 'director de departamento', 'cargo académico'],
            'distincion' => ['distinción', 'distincion', 'premio docente', 'reconocimiento']
        ];

        foreach ($tipos as $tipo => $terminos) {
            foreach ($terminos as $termino) {
                if (mb_stripos($texto, $termino, 0, 'UTF-8') !== false) {
                    $bloque4[] = [
                        "id" => "b4_" . str_pad((string) (count($bloque4) + 1), 3, '0', STR_PAD_LEFT),
                        "es_valido" => true,
                        "tipo" => $tipo,
                        "fuente_texto" => $termino,
                        "confianza_extraccion" => 0.60,
                        "requiere_revision" => true
                    ];
                    break;
                }
            }
        }

        return $bloque4;
    }

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

        if (str_contains($textoLower, 'comunicación') || str_contains($textoLower, 'comunicacion') || str_contains($textoLower, 'publicidad') || str_contains($textoLower, 'periodismo')) {
            return 'total';
        }

        return null;
    }

    private function detectarPosicionAutor(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (preg_match('/posición\s*[:\-]?\s*1\b/iu', $texto) || str_contains($textoLower, 'primer autor') || str_contains($textoLower, 'primero')) {
            return 'primero';
        }
        if (str_contains($textoLower, 'último autor') || str_contains($textoLower, 'ultimo autor') || str_contains($textoLower, 'último') || str_contains($textoLower, 'ultimo')) {
            return 'ultimo';
        }
        if (str_contains($textoLower, 'autor único') || str_contains($textoLower, 'autor unico') || str_contains($textoLower, 'único autor') || str_contains($textoLower, 'unico autor')) {
            return 'unico';
        }

        return null;
    }

    private function detectarNumeroAutores(string $texto): ?int
    {
        if (preg_match('/(\d{1,3})\s+autores?/iu', $texto, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/n[úu]mero\s+de\s+autores?\s*[:\-]?\s*(\d{1,3})/iu', $texto, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function detectarCitas(string $texto): ?int
    {
        if (preg_match('/(\d{1,6})\s+citas?/iu', $texto, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function detectarAnio(string $texto): ?int
    {
        if (preg_match('/\b(19\d{2}|20\d{2}|21\d{2})\b/u', $texto, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function detectarNivelEditorial(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (str_contains($textoLower, 'spi top')) {
            return 'spi_top';
        }
        if (str_contains($textoLower, 'bci')) {
            return 'bci';
        }
        if (str_contains($textoLower, 'spi')) {
            return 'prestigiosa';
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

    private function detectarTipoProyecto(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (str_contains($textoLower, 'europeo') || str_contains($textoLower, 'europea')) {
            return 'europeo';
        }
        if (str_contains($textoLower, 'nacional')) {
            return 'nacional';
        }
        if (str_contains($textoLower, 'autonómico') || str_contains($textoLower, 'autonomico')) {
            return 'autonomico';
        }
        if (str_contains($textoLower, 'universidad') || str_contains($textoLower, 'universitario')) {
            return 'universidad';
        }

        return null;
    }

    private function detectarRolProyecto(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (str_contains($textoLower, 'coip')) {
            return 'coip';
        }
        if (str_contains($textoLower, 'ip') || str_contains($textoLower, 'investigador principal')) {
            return 'ip';
        }
        if (str_contains($textoLower, 'colaborador')) {
            return 'colaborador';
        }

        return null;
    }

    private function detectarDedicacion(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (str_contains($textoLower, 'dedicación completa') || str_contains($textoLower, 'dedicacion completa') || str_contains($textoLower, 'tiempo completo')) {
            return 'completa';
        }
        if (str_contains($textoLower, 'dedicación compartida') || str_contains($textoLower, 'dedicacion compartida') || str_contains($textoLower, 'tiempo parcial')) {
            return 'compartida';
        }

        return null;
    }

    private function detectarDuracionEnAnios(string $texto): ?int
    {
        if (preg_match('/(\d{1,2})\s+años?/iu', $texto, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(\d{1,3})\s+meses?/iu', $texto, $m)) {
            return (int) max(1, round(((int) $m[1]) / 12));
        }

        return null;
    }

    private function detectarContinuidad(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (str_contains($textoLower, 'encadenada') || str_contains($textoLower, 'continuada') || str_contains($textoLower, 'continuidad')) {
            return 'encadenada';
        }

        return null;
    }

    private function detectarTipoTransferencia(string $texto): ?string
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        if (str_contains($textoLower, 'contrato')) {
            return 'contrato';
        }
        if (str_contains($textoLower, 'patente')) {
            return 'patente';
        }
        if (str_contains($textoLower, 'software')) {
            return 'resultado_aplicado';
        }

        return null;
    }

    private function detectarLiderazgo(string $texto): bool
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        return str_contains($textoLower, 'ip')
            || str_contains($textoLower, 'director')
            || str_contains($textoLower, 'responsable')
            || str_contains($textoLower, 'principal');
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
            return 'ponencia';
        }
        if (str_contains($textoLower, 'comunicación') || str_contains($textoLower, 'comunicacion')) {
            return 'comunicacion_oral';
        }

        return null;
    }

    private function esPublicacionNoValida(string $texto): bool
    {
        $textoLower = mb_strtolower($texto, 'UTF-8');

        return $this->contieneAlgunTermino($textoLower, [
            'divulgación',
            'divulgacion',
            'docencia',
            'acta',
            'actas',
            'informe de proyecto'
        ]);
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