<?php
declare(strict_types=1);

$nombre = trim($_POST['nombre_candidato'] ?? '');
$jsonEntrada = trim($_POST['json_entrada'] ?? '');

if ($nombre === '' || $jsonEntrada === '') {
    die('Faltan datos obligatorios para completar el expediente.');
}

$jsonExtraido = json_decode($jsonEntrada, true);

if (!is_array($jsonExtraido)) {
    die('El JSON extraído no es válido.');
}

require __DIR__ . '/ui.php';

function exp_count_list(mixed $value): int
{
    return is_array($value) ? count($value) : 0;
}

$resumen = [
    '1A Publicaciones científicas' => exp_count_list($jsonExtraido['bloque_1']['publicaciones'] ?? []),
    '1B Libros y capítulos' => exp_count_list($jsonExtraido['bloque_1']['libros'] ?? []),
    '1C Proyectos y contratos' => exp_count_list($jsonExtraido['bloque_1']['proyectos'] ?? []),
    '1D Transferencia' => exp_count_list($jsonExtraido['bloque_1']['transferencia'] ?? []),
    '1E Tesis dirigidas' => exp_count_list($jsonExtraido['bloque_1']['tesis_dirigidas'] ?? []),
    '1F Congresos' => exp_count_list($jsonExtraido['bloque_1']['congresos'] ?? []),
    '1G Otros méritos inv.' => exp_count_list($jsonExtraido['bloque_1']['otros_meritos_investigacion'] ?? []),

    '2A Docencia universitaria' => exp_count_list($jsonExtraido['bloque_2']['docencia_universitaria'] ?? []),
    '2B Evaluación docente' => exp_count_list($jsonExtraido['bloque_2']['evaluacion_docente'] ?? []),
    '2C Formación docente' => exp_count_list($jsonExtraido['bloque_2']['formacion_docente'] ?? []),
    '2D Material docente' => exp_count_list($jsonExtraido['bloque_2']['material_docente'] ?? []),

    '3A Formación académica' => exp_count_list($jsonExtraido['bloque_3']['formacion_academica'] ?? []),
    '3B Experiencia profesional' => exp_count_list($jsonExtraido['bloque_3']['experiencia_profesional'] ?? []),

    '4 Otros méritos' => exp_count_list($jsonExtraido['bloque_4'] ?? []),
];

exp_render_layout_start(
    'Completar expediente manualmente',
    'Rama Experimentales · formulario ampliado para completar todos los apartados PCD/PUP.',
    [
        ['label' => 'Portal ANECA', 'url' => exp_portal_url()],
        ['label' => 'Experimentales', 'url' => exp_index_url()],
        ['label' => 'Completar expediente'],
    ],
    [
        ['label' => 'Volver a Experimentales', 'url' => exp_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => exp_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .bloque {
        border: 1px solid #d9e2ec;
        border-radius: 12px;
        padding: 16px;
        margin: 14px 0;
        background: #fff;
    }
    .fila {
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px;
        margin: 10px 0;
        background: #f8fafc;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
    }
    .fila label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
        color: #334155;
    }
    .fila input,
    .fila select,
    .fila textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        font-size: 14px;
        background: #fff;
        box-sizing: border-box;
    }
    .hint {
        color: #64748b;
        font-size: 13px;
        margin: 6px 0 12px;
    }
    .acciones {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    .acciones button,
    .acciones .btn {
        display: inline-block;
        padding: 10px 16px;
        border-radius: 10px;
        border: 1px solid #0f766e;
        background: #0f766e;
        color: #fff;
        text-decoration: none;
        cursor: pointer;
        font-weight: 700;
    }
    .acciones .outline {
        background: #fff;
        color: #0f766e;
    }
    .subgrid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
    }
    .nota-maximos {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 14px;
        background: #f8fafc;
        font-size: 14px;
    }
    .nota-maximos strong {
        color: #0f766e;
    }
    .vacio {
        color: #94a3b8;
        font-style: italic;
        margin: 8px 0 0;
    }
</style>

<script>
    function crearCampo(label, html) {
        return `<div><label>${label}</label>${html}</div>`;
    }

    function insertarFila(containerId, html) {
        const c = document.getElementById(containerId);
        c.insertAdjacentHTML('beforeend', html);
        actualizarVacios();
    }

    function actualizarVacios() {
        document.querySelectorAll('[data-vacio-for]').forEach(el => {
            const id = el.getAttribute('data-vacio-for');
            const cont = document.getElementById(id);
            if (!cont) return;
            el.style.display = cont.children.length === 0 ? 'block' : 'none';
        });
    }

    function agregarPublicacion() {
        const c = document.getElementById('publicaciones');
        const i = c.children.length;

        insertarFila('publicaciones', `
            <div class="fila">
                ${crearCampo('Tipo índice', `
                    <select name="publicaciones[${i}][tipo_indice]">
                        <option value="JCR">JCR</option>
                        <option value="SCI">SCI</option>
                        <option value="MULTIDISCIPLINAR">Multidisciplinar</option>
                        <option value="OTRO">Otro</option>
                    </select>`)}

                ${crearCampo('Tercil', `
                    <select name="publicaciones[${i}][tercil]">
                        <option value="">--</option>
                        <option value="EXCELENTE">Excelente posición</option>
                        <option value="T1">T1</option>
                        <option value="T2">T2</option>
                        <option value="T3">T3</option>
                    </select>`)}

                ${crearCampo('Especialidad', `
                    <select name="publicaciones[${i}][especialidad]">
                        <option value="">General</option>
                        <option value="biologia">Biología</option>
                        <option value="geologia">Geología</option>
                        <option value="matematicas">Matemáticas</option>
                    </select>`)}

                ${crearCampo('Área matemática indexada', `
                    <select name="publicaciones[${i}][es_area_matematicas]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                ${crearCampo('Posición autor', `
                    <select name="publicaciones[${i}][posicion_autor]">
                        <option value="primero">Primero</option>
                        <option value="ultimo">Último</option>
                        <option value="correspondencia">Correspondencia</option>
                        <option value="intermedio">Intermedio</option>
                        <option value="secundario">Secundario</option>
                        <option value="autor_unico">Autor único</option>
                    </select>`)}

                ${crearCampo('Autores', `
                    <input type="number" name="publicaciones[${i}][numero_autores]" min="1" value="4">`)}

                ${crearCampo('Orden alfabético', `
                    <select name="publicaciones[${i}][orden_alfabetico]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                ${crearCampo('Citas', `
                    <input type="number" name="publicaciones[${i}][citas]" min="0" value="0">`)}

                ${crearCampo('Años desde publicación', `
                    <input type="number" name="publicaciones[${i}][anios_desde_publicacion]" min="0" value="3">`)}

                <input type="hidden" name="publicaciones[${i}][es_valida]" value="1">
            </div>
        `);
    }

    function agregarLibro() {
        const c = document.getElementById('libros');
        const i = c.children.length;

        insertarFila('libros', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="libros[${i}][tipo]">
                        <option value="libro">Libro</option>
                        <option value="capitulo">Capítulo</option>
                        <option value="resumen_extendido">Resumen extendido</option>
                        <option value="edicion_colectiva">Edición volumen colectivo</option>
                        <option value="cartografia_tematica">Cartografía temática</option>
                    </select>`)}

                ${crearCampo('Prestigio editorial', `
                    <select name="libros[${i}][nivel_editorial]">
                        <option value="internacional">Internacional</option>
                        <option value="nacional">Nacional</option>
                        <option value="menor">Menor difusión</option>
                    </select>`)}

                ${crearCampo('Especialidad', `
                    <select name="libros[${i}][especialidad]">
                        <option value="">General</option>
                        <option value="botanica">Botánica</option>
                        <option value="zoologia">Zoología</option>
                        <option value="tierra">Ciencias de la Tierra</option>
                    </select>`)}

                ${crearCampo('Complejidad alta / aportación relevante', `
                    <select name="libros[${i}][complejidad_alta]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                <input type="hidden" name="libros[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarProyecto() {
        const c = document.getElementById('proyectos');
        const i = c.children.length;

        insertarFila('proyectos', `
            <div class="fila">
                ${crearCampo('Tipo proyecto', `
                    <select name="proyectos[${i}][tipo_proyecto]">
                        <option value="europeo">Europeo</option>
                        <option value="nacional">Plan Nacional</option>
                        <option value="autonomico">Autonómico</option>
                        <option value="otro_competitivo">Otro competitivo</option>
                        <option value="art83_conocimiento">Art. 83 con generación de conocimiento</option>
                    </select>`)}

                ${crearCampo('Rol', `
                    <select name="proyectos[${i}][rol]">
                        <option value="ip">IP</option>
                        <option value="coip">Co-IP</option>
                        <option value="investigador">Investigador</option>
                    </select>`)}

                ${crearCampo('Años duración', `
                    <input type="number" step="0.1" name="proyectos[${i}][anios_duracion]" min="0" value="1">`)}

                ${crearCampo('Certificado oficial', `
                    <select name="proyectos[${i}][esta_certificado]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}

                <input type="hidden" name="proyectos[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarTransferencia() {
        const c = document.getElementById('transferencia');
        const i = c.children.length;

        insertarFila('transferencia', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="transferencia[${i}][tipo]">
                        <option value="patente_solicitada_nacional">Patente solicitada nacional</option>
                        <option value="patente_obtenida_nacional">Patente obtenida nacional</option>
                        <option value="patente_solicitada_internacional">Patente solicitada internacional</option>
                        <option value="patente_obtenida_internacional">Patente obtenida internacional</option>
                        <option value="propiedad_intelectual">Propiedad intelectual</option>
                        <option value="art83_sin_conocimiento">Art. 83 sin generación de conocimiento</option>
                    </select>`)}

                <input type="hidden" name="transferencia[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarTesis() {
        const c = document.getElementById('tesis');
        const i = c.children.length;

        insertarFila('tesis', `
            <div class="fila">
                ${crearCampo('Estado / tipo', `
                    <select name="tesis[${i}][tipo]">
                        <option value="dirigida">Tesis dirigida</option>
                        <option value="en_direccion">Tesis en dirección</option>
                    </select>`)}

                ${crearCampo('Doctorado europeo/internacional', `
                    <select name="tesis[${i}][doctorado_europeo]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                ${crearCampo('Mención de calidad (pre-2016)', `
                    <select name="tesis[${i}][mencion_calidad]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                ${crearCampo('Nº codirectores', `
                    <input type="number" name="tesis[${i}][numero_codirectores]" min="0" value="0">`)}

                ${crearCampo('Proyecto aprobado CAPD/ED', `
                    <select name="tesis[${i}][proyecto_aprobado]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}

                <input type="hidden" name="tesis[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarCongreso() {
        const c = document.getElementById('congresos');
        const i = c.children.length;

        insertarFila('congresos', `
            <div class="fila">
                ${crearCampo('Ámbito', `
                    <select name="congresos[${i}][ambito]">
                        <option value="internacional">Internacional</option>
                        <option value="nacional">Nacional</option>
                    </select>`)}

                ${crearCampo('Tipo participación', `
                    <select name="congresos[${i}][tipo]">
                        <option value="ponencia_invitada">Ponencia invitada</option>
                        <option value="comunicacion_oral">Comunicación oral</option>
                        <option value="poster">Póster</option>
                    </select>`)}

                ${crearCampo('Proceso selectivo de admisión', `
                    <select name="congresos[${i}][proceso_selectivo]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}

                ${crearCampo('ID evento', `
                    <input type="text" name="congresos[${i}][id_evento]" placeholder="evento_001">`)}

                <input type="hidden" name="congresos[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarOtroInvestigacion() {
        const c = document.getElementById('otros_investigacion');
        const i = c.children.length;

        insertarFila('otros_investigacion', `
            <div class="fila">
                ${crearCampo('Tipo mérito', `
                    <select name="otros_investigacion[${i}][tipo]">
                        <option value="revision_jcr">Revisión de manuscritos JCR</option>
                        <option value="evaluacion_proyectos">Evaluación de proyectos</option>
                        <option value="otro">Otro</option>
                    </select>`)}

                <input type="hidden" name="otros_investigacion[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarDocencia() {
        const c = document.getElementById('docencia');
        const i = c.children.length;

        insertarFila('docencia', `
            <div class="fila">
                ${crearCampo('Horas impartidas', `
                    <input type="number" name="docencia[${i}][horas]" min="0" value="60">`)}

                ${crearCampo('Tipo docencia', `
                    <select name="docencia[${i}][tipo]">
                        <option value="grado">Grado</option>
                        <option value="master">Máster</option>
                        <option value="titulo_propio">Título propio</option>
                    </select>`)}

                ${crearCampo('Etapa', `
                    <select name="docencia[${i}][etapa]">
                        <option value="predoctoral">Predoctoral</option>
                        <option value="posdoctoral">Posdoctoral</option>
                        <option value="estable">Estable</option>
                    </select>`)}

                ${crearCampo('TFG dirigidos', `
                    <input type="number" name="docencia[${i}][tfg]" min="0" value="0">`)}

                ${crearCampo('TFM dirigidos', `
                    <input type="number" name="docencia[${i}][tfm]" min="0" value="0">`)}

                <input type="hidden" name="docencia[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarEvalDocente() {
        const c = document.getElementById('eval_docente');
        const i = c.children.length;

        insertarFila('eval_docente', `
            <div class="fila">
                ${crearCampo('Resultado', `
                    <select name="eval_docente[${i}][resultado]">
                        <option value="excelente">Excelente</option>
                        <option value="muy_favorable">Muy favorable</option>
                        <option value="favorable">Favorable</option>
                        <option value="aceptable">Aceptable</option>
                    </select>`)}

                ${crearCampo('Cobertura mayor parte de docencia', `
                    <select name="eval_docente[${i}][cobertura_amplia]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}

                <input type="hidden" name="eval_docente[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarFormDocente() {
        const c = document.getElementById('form_docente');
        const i = c.children.length;

        insertarFila('form_docente', `
            <div class="fila">
                ${crearCampo('Rol', `
                    <select name="form_docente[${i}][rol]">
                        <option value="ponente">Ponente</option>
                        <option value="asistente">Asistente</option>
                    </select>`)}

                ${crearCampo('Horas', `
                    <input type="number" name="form_docente[${i}][horas]" min="0" value="10">`)}

                ${crearCampo('Relacionado con formación docente universitaria', `
                    <select name="form_docente[${i}][relacion_docente]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}

                <input type="hidden" name="form_docente[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarMaterialDocente() {
        const c = document.getElementById('material_docente');
        const i = c.children.length;

        insertarFila('material_docente', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="material_docente[${i}][tipo]">
                        <option value="libro_docente">Libro docente</option>
                        <option value="capitulo_docente">Capítulo docente</option>
                        <option value="innovacion_docente">Proyecto innovación docente</option>
                        <option value="material_original">Material docente original</option>
                    </select>`)}

                ${crearCampo('Prestigio editorial', `
                    <select name="material_docente[${i}][nivel_editorial]">
                        <option value="internacional">Internacional</option>
                        <option value="nacional">Nacional</option>
                        <option value="menor">Menor difusión</option>
                    </select>`)}

                <input type="hidden" name="material_docente[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarFormacion() {
        const c = document.getElementById('formacion');
        const i = c.children.length;

        insertarFila('formacion', `
            <div class="fila">
                ${crearCampo('Tipo mérito', `
                    <select name="formacion[${i}][tipo]">
                        <option value="doctorado_internacional">Doctorado internacional</option>
                        <option value="mencion_calidad">Mención calidad</option>
                        <option value="beca_predoc_fpu">Beca FPU</option>
                        <option value="beca_predoc_fpi">Beca FPI</option>
                        <option value="beca_predoc_autonomica">Beca autonómica</option>
                        <option value="beca_predoc_universidad">Beca universidad/fundación</option>
                        <option value="premio_extra_doctorado">Premio extraordinario doctorado</option>
                        <option value="beca_posdoc">Beca/contrato posdoctoral competitivo</option>
                        <option value="estancia">Estancia</option>
                        <option value="curso_especializacion">Curso especialización</option>
                    </select>`)}

                ${crearCampo('Competitividad alta', `
                    <select name="formacion[${i}][alta_competitividad]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                <input type="hidden" name="formacion[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarExperiencia() {
        const c = document.getElementById('experiencia');
        const i = c.children.length;

        insertarFila('experiencia', `
            <div class="fila">
                ${crearCampo('Años', `
                    <input type="number" step="0.1" name="experiencia[${i}][anios]" min="0" value="1">`)}

                ${crearCampo('Relevancia para docencia en la especialidad', `
                    <select name="experiencia[${i}][relacion]">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>`)}

                ${crearCampo('Vida laboral + certificado empresa', `
                    <select name="experiencia[${i}][justificada]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}

                ${crearCampo('Es contrato no valorable (obra/proyecto)', `
                    <select name="experiencia[${i}][no_valorable]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}

                <input type="hidden" name="experiencia[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarBloque4() {
        const c = document.getElementById('bloque4');
        const i = c.children.length;

        insertarFila('bloque4', `
            <div class="fila">
                ${crearCampo('Tipo mérito', `
                    <select name="bloque4[${i}][tipo]">
                        <option value="gestion">Gestión universitaria</option>
                        <option value="divulgacion">Divulgación científica</option>
                        <option value="asesor_equipos">Asesor científico de equipos instrumentales</option>
                        <option value="beca_colaboracion">Beca colaboración/iniciación</option>
                        <option value="premio_extra_grado">Premio extraordinario grado/licenciatura</option>
                        <option value="otro">Otro</option>
                    </select>`)}

                <input type="hidden" name="bloque4[${i}][es_valido]" value="1">
            </div>
        `);
    }

    document.addEventListener('DOMContentLoaded', actualizarVacios);
</script>

<section class="card stack">
    <div class="meta-grid">
        <div class="metric">
            <span class="label">Candidato</span>
            <span class="value" style="font-size:20px"><?= exp_h($nombre) ?></span>
        </div>
        <div class="metric">
            <span class="label">Área</span>
            <span class="value" style="font-size:20px">Experimentales</span>
        </div>
        <div class="metric">
            <span class="label">Modo</span>
            <span class="value" style="font-size:20px">Completar manualmente</span>
        </div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card">
            <div class="subgrid-2">
                <div class="nota-maximos">
                    <strong>Distribución orientativa PCD/PUP · Experimentales</strong><br>
                    B1: 1A=35, 1B=7, 1C=7, 1D=4, 1E=4, 1F=2, 1G=1<br>
                    B2: 30 · B3: 8 · B4: 2
                </div>
                <div class="nota-maximos">
                    <strong>Regla de evaluación positiva</strong><br>
                    1 + 2 ≥ 50<br>
                    1 + 2 + 3 + 4 ≥ 55
                </div>
            </div>

            <form action="guardar_complemento.php" method="post">
                <input type="hidden" name="nombre_candidato" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">
                <textarea name="json_entrada_base" style="display:none;"><?= htmlspecialchars($jsonEntrada, ENT_QUOTES, 'UTF-8') ?></textarea>

                <div style="margin: 18px 0 4px;">
                    <h2>Bloque 1. Experiencia investigadora</h2>
                </div>

                <div class="bloque">
                    <h3>1.A Publicaciones científicas</h3>
                    <p class="hint">Experimentales trabaja con calidad por posición de la revista, tercil JCR, coautoría y posición del solicitante. En Matemáticas se contempla el factor 1.2 en publicaciones indexadas del área.</p>
                    <div id="publicaciones"></div>
                    <p class="vacio" data-vacio-for="publicaciones">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarPublicacion()">Añadir publicación</button>
                </div>

                <div class="bloque">
                    <h3>1.B Libros y capítulos de libro</h3>
                    <div id="libros"></div>
                    <p class="vacio" data-vacio-for="libros">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarLibro()">Añadir libro/capítulo</button>
                </div>

                <div class="bloque">
                    <h3>1.C Proyectos y contratos de investigación</h3>
                    <div id="proyectos"></div>
                    <p class="vacio" data-vacio-for="proyectos">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarProyecto()">Añadir proyecto</button>
                </div>

                <div class="bloque">
                    <h3>1.D Transferencia tecnológica</h3>
                    <div id="transferencia"></div>
                    <p class="vacio" data-vacio-for="transferencia">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarTransferencia()">Añadir transferencia</button>
                </div>

                <div class="bloque">
                    <h3>1.E Dirección de tesis doctorales</h3>
                    <div id="tesis"></div>
                    <p class="vacio" data-vacio-for="tesis">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarTesis()">Añadir tesis</button>
                </div>

                <div class="bloque">
                    <h3>1.F Congresos, conferencias y seminarios</h3>
                    <div id="congresos"></div>
                    <p class="vacio" data-vacio-for="congresos">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarCongreso()">Añadir congreso</button>
                </div>

                <div class="bloque">
                    <h3>1.G Otros méritos de investigación</h3>
                    <div id="otros_investigacion"></div>
                    <p class="vacio" data-vacio-for="otros_investigacion">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarOtroInvestigacion()">Añadir mérito</button>
                </div>

                <div style="margin: 18px 0 4px;">
                    <h2>Bloque 2. Experiencia docente</h2>
                </div>

                <div class="bloque">
                    <h3>2.A Docencia universitaria</h3>
                    <div id="docencia"></div>
                    <p class="vacio" data-vacio-for="docencia">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarDocencia()">Añadir docencia</button>
                </div>

                <div class="bloque">
                    <h3>2.B Evaluaciones sobre su calidad</h3>
                    <div id="eval_docente"></div>
                    <p class="vacio" data-vacio-for="eval_docente">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarEvalDocente()">Añadir evaluación docente</button>
                </div>

                <div class="bloque">
                    <h3>2.C Cursos y seminarios de formación docente universitaria</h3>
                    <div id="form_docente"></div>
                    <p class="vacio" data-vacio-for="form_docente">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarFormDocente()">Añadir formación docente</button>
                </div>

                <div class="bloque">
                    <h3>2.D Material docente, proyectos y contribuciones al EEES</h3>
                    <div id="material_docente"></div>
                    <p class="vacio" data-vacio-for="material_docente">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarMaterialDocente()">Añadir material docente</button>
                </div>

                <div style="margin: 18px 0 4px;">
                    <h2>Bloque 3. Formación académica y experiencia profesional</h2>
                </div>

                <div class="bloque">
                    <h3>3.A Tesis, becas, estancias, otros títulos</h3>
                    <div id="formacion"></div>
                    <p class="vacio" data-vacio-for="formacion">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarFormacion()">Añadir mérito formativo</button>
                </div>

                <div class="bloque">
                    <h3>3.B Trabajo en empresas / instituciones / hospitales</h3>
                    <div id="experiencia"></div>
                    <p class="vacio" data-vacio-for="experiencia">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarExperiencia()">Añadir experiencia</button>
                </div>

                <div style="margin: 18px 0 4px;">
                    <h2>Bloque 4. Otros méritos</h2>
                </div>

                <div class="bloque">
                    <h3>4. Otros méritos</h3>
                    <div id="bloque4"></div>
                    <p class="vacio" data-vacio-for="bloque4">Sin filas añadidas todavía.</p>
                    <button type="button" class="btn" onclick="agregarBloque4()">Añadir mérito</button>
                </div>

                <div class="acciones">
                    <button type="submit">Fusionar, recalcular y guardar</button>
                    <a class="btn outline" href="index.php">Cancelar</a>
                </div>
            </form>
        </section>

        <section class="card">
            <details>
                <summary>Ver JSON extraído</summary>
                <pre><?= exp_h(json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
            </details>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen de extracción</h2>
            <p class="muted">Vista rápida del expediente detectado antes de añadir o corregir datos manualmente.</p>
            <div class="kpis">
                <?php foreach ($resumen as $label => $valor): ?>
                    <div class="kpi">
                        <span class="label"><?= exp_h($label) ?></span>
                        <strong><?= exp_h((string)$valor) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card">
            <h2>Ayuda rápida</h2>
            <ul>
                <li>Este formulario ya incorpora todos los apartados de Experimentales.</li>
                <li>He dejado campos específicos para terciles, coautoría, Matemáticas, tesis, congresos selectivos y docencia PCD.</li>
                <li>En el siguiente archivo ajustamos el guardado para que conserve todo esto al fusionar.</li>
            </ul>
        </section>
    </aside>
</section>

<?php exp_render_layout_end(); ?>