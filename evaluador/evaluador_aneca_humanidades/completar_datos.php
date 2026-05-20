<?php
declare(strict_types=1);

require __DIR__ . '/ui.php';
if (!function_exists('h')) {
    function h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$nombre = trim($_POST['nombre_candidato'] ?? '');
$jsonEntrada = trim($_POST['json_entrada'] ?? '');

if ($nombre === '') {
    $nombre = '';
}
if ($jsonEntrada === '' || json_decode($jsonEntrada, true) === null) {
    $jsonEntrada = '{}';
}

$jsonExtraido = json_decode($jsonEntrada, true);

if (!is_array($jsonExtraido)) {
    $jsonExtraido = [];
}

$resumen = [
    '1A Publicaciones' => count($jsonExtraido['bloque_1']['publicaciones'] ?? []),
    '1B Libros' => count($jsonExtraido['bloque_1']['libros'] ?? []),
    '1C Proyectos' => count($jsonExtraido['bloque_1']['proyectos'] ?? []),
    '1D Transferencia' => count($jsonExtraido['bloque_1']['transferencia'] ?? []),
    '1E Tesis dirigidas' => count($jsonExtraido['bloque_1']['tesis_dirigidas'] ?? []),
    '1F Congresos' => count($jsonExtraido['bloque_1']['congresos'] ?? []),
    '1G Otros méritos inv.' => count($jsonExtraido['bloque_1']['otros_meritos_investigacion'] ?? []),
    '2A Docencia' => count($jsonExtraido['bloque_2']['docencia_universitaria'] ?? []),
    '2B Evaluación docente' => count($jsonExtraido['bloque_2']['evaluacion_docente'] ?? []),
    '2C Formación docente' => count($jsonExtraido['bloque_2']['formacion_docente'] ?? []),
    '2D Material docente' => count($jsonExtraido['bloque_2']['material_docente'] ?? []),
    '3A Formación' => count($jsonExtraido['bloque_3']['formacion_academica'] ?? []),
    '3B Experiencia' => count($jsonExtraido['bloque_3']['experiencia_profesional'] ?? []),
    '4 Otros méritos' => count($jsonExtraido['bloque_4'] ?? []),
];

hum_render_layout_start(
    'Completar expediente manualmente',
    'Añade los apartados que faltan antes de fusionar, recalcular y guardar.',
    [
        ['label' => 'Portal ANECA', 'url' => hum_portal_url()],
        ['label' => 'Humanidades', 'url' => hum_index_url()],
        ['label' => 'Completar expediente'],
    ],
    [
        ['label' => 'Volver a Humanidades', 'url' => hum_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => hum_portal_url(), 'class' => 'light'],
    ]
);
?>

<style>
    .bloque {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 24px;
        margin: 32px 0;
        background: rgba(255, 255, 255, 0.03);
    }
    .bloque h3 {
        margin-top: 0;
        color: #fff;
        font-size: 18px;
    }
    .fila {
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 15px;
        padding: 16px;
        margin: 12px 0;
        background: rgba(0, 0, 0, 0.2);
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .fila label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.5);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .fila input,
    .fila select,
    .fila textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        font-size: 14px;
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        box-sizing: border-box;
    }
    .hint {
        color: rgba(255, 255, 255, 0.4);
        font-size: 13px;
        margin: 6px 0 12px;
        line-height: 1.4;
    }
    .acciones {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 24px;
    }
    .subgrid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    .nota-maximos {
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 18px;
        padding: 18px;
        background: rgba(255, 255, 255, 0.03);
        font-size: 14px;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.7);
    }
    .nota-maximos strong {
        color: #fff;
        display: block;
        margin-bottom: 4px;
    }
    .vacio {
        color: rgba(255, 255, 255, 0.3);
        font-style: italic;
        margin: 12px 0;
        text-align: center;
        padding: 15px;
        border: 1px dashed rgba(255, 255, 255, 0.1);
        border-radius: 12px;
    }
    button.btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
        padding: 8px 16px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
    }
    button.btn:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.2);
    }
    .aside-card-short {
        padding: 16px !important;
    }
    .aside-card-short h2 {
        font-size: 18px !important;
        margin-bottom: 12px !important;
    }
    .aside-card-short .kpi {
        padding: 12px !important;
    }
    .help-item {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.08);
        padding: 10px;
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .help-item:hover {
        background: rgba(255, 255, 255, 0.08);
        transform: translateX(5px);
        border-color: rgba(255, 255, 255, 0.2);
    }
    .help-tag {
        font-size: 10px;
        text-transform: uppercase;
        font-weight: 800;
        letter-spacing: 0.1em;
        display: flex;
        align-items: center;
        gap: 6px;
    }
</style>

<script>
    function crearCampo(label, html) {
        return `<div><label>${label}</label>${html}</div>`;
    }

    function agregarPublicacion() {
        const c = document.getElementById('publicaciones');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo índice', `
                    <select name="publicaciones[${i}][tipo_indice]">
                        <option value="JCR">JCR</option>
                        <option value="SJR">SJR</option>
                        <option value="FECYT">FECYT</option>
                        <option value="RESH">RESH</option>
                        <option value="ERIH">ERIH</option>
                        <option value="MIAR">MIAR</option>
                        <option value="OTRO">Otro</option>
                    </select>`)}
                ${crearCampo('Cuartil', `
                    <select name="publicaciones[${i}][cuartil]">
                        <option value="">--</option>
                        <option value="Q1">Q1</option>
                        <option value="Q2">Q2</option>
                        <option value="Q3">Q3</option>
                        <option value="Q4">Q4</option>
                    </select>`)}
                ${crearCampo('Subtipo índice', `
                    <input type="text" name="publicaciones[${i}][subtipo_indice]" placeholder="Ej. C1 / PLUS">`)}
                ${crearCampo('Tipo aportación', `
                    <select name="publicaciones[${i}][tipo_aportacion]">
                        <option value="articulo">Artículo</option>
                        <option value="edicion_critica">Edición crítica</option>
                        <option value="estudio_fuentes">Estudio de fuentes</option>
                        <option value="traduccion_anotada">Traducción anotada</option>
                    </select>`)}
                ${crearCampo('Afinidad', `
                    <select name="publicaciones[${i}][afinidad]">
                        <option value="total">Total</option>
                        <option value="relacionada">Relacionada</option>
                        <option value="periferica">Periférica</option>
                        <option value="ajena">Ajena</option>
                    </select>`)}
                ${crearCampo('Posición autor', `
                    <select name="publicaciones[${i}][posicion_autor]">
                        <option value="autor_unico">Autor único</option>
                        <option value="primero">Primero</option>
                        <option value="ultimo">Último</option>
                        <option value="intermedio">Intermedio</option>
                        <option value="secundario">Secundario</option>
                    </select>`)}
                ${crearCampo('Número autores', `
                    <input type="number" name="publicaciones[${i}][numero_autores]" min="1" value="1">`)}
                ${crearCampo('Citas', `
                    <input type="number" name="publicaciones[${i}][citas]" min="0" value="0">`)}
                <input type="hidden" name="publicaciones[${i}][tipo]" value="articulo">
                <input type="hidden" name="publicaciones[${i}][es_valida]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarLibro() {
        const c = document.getElementById('libros');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="libros[${i}][tipo]">
                        <option value="libro">Libro</option>
                        <option value="capitulo">Capítulo</option>
                    </select>`)}
                ${crearCampo('Nivel editorial', `
                    <select name="libros[${i}][nivel_editorial]">
                        <option value="prestigiosa">Prestigiosa</option>
                        <option value="secundaria">Secundaria</option>
                        <option value="baja">Baja</option>
                    </select>`)}
                ${crearCampo('Afinidad', `
                    <select name="libros[${i}][afinidad]">
                        <option value="total">Total</option>
                        <option value="relacionada">Relacionada</option>
                        <option value="periferica">Periférica</option>
                        <option value="ajena">Ajena</option>
                    </select>`)}
                ${crearCampo('Posición autor', `
                    <select name="libros[${i}][posicion_autor]">
                        <option value="autor_unico">Autor único</option>
                        <option value="primero">Primero</option>
                        <option value="ultimo">Último</option>
                        <option value="intermedio">Intermedio</option>
                    </select>`)}
                ${crearCampo('Acta de congreso', `
                    <select name="libros[${i}][es_acta_congreso]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                <input type="hidden" name="libros[${i}][es_valido]" value="1">
                <input type="hidden" name="libros[${i}][es_libro_investigacion]" value="1">
                <input type="hidden" name="libros[${i}][es_autoedicion]" value="0">
                <input type="hidden" name="libros[${i}][es_labor_edicion]" value="0">
            </div>
        `);
        actualizarVacios();
    }

    function agregarProyecto() {
        const c = document.getElementById('proyectos');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo proyecto', `
                    <select name="proyectos[${i}][tipo_proyecto]">
                        <option value="nacional">Nacional</option>
                        <option value="autonomico">Autonómico</option>
                        <option value="internacional">Internacional</option>
                        <option value="universidad">Universidad</option>
                        <option value="contrato">Contrato</option>
                    </select>`)}
                ${crearCampo('Rol', `
                    <select name="proyectos[${i}][rol]">
                        <option value="ip">IP</option>
                        <option value="coip">Co-IP</option>
                        <option value="investigador">Investigador</option>
                        <option value="participacion_menor">Participación menor</option>
                    </select>`)}
                ${crearCampo('Años duración', `
                    <input type="number" step="0.1" name="proyectos[${i}][anios_duracion]" min="0" value="1">`)}
                ${crearCampo('Certificado', `
                    <select name="proyectos[${i}][esta_certificado]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Art. 83 / investigable', `
                    <select name="proyectos[${i}][caracter_investigador]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                <input type="hidden" name="proyectos[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarTransferencia() {
        const c = document.getElementById('transferencia');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="transferencia[${i}][tipo]">
                        <option value="transferencia_conocimiento">Transferencia de conocimiento</option>
                        <option value="difusion_social">Difusión social</option>
                        <option value="asesoria_especializada">Asesoría especializada</option>
                        <option value="comisariado_transferencia">Comisariado / mediación</option>
                    </select>`)}
                ${crearCampo('Impacto', `
                    <select name="transferencia[${i}][impacto]">
                        <option value="alto">Alto</option>
                        <option value="medio">Medio</option>
                        <option value="bajo">Bajo</option>
                    </select>`)}
                ${crearCampo('Ámbito', `
                    <select name="transferencia[${i}][ambito]">
                        <option value="internacional">Internacional</option>
                        <option value="nacional">Nacional</option>
                        <option value="regional">Regional</option>
                        <option value="local">Local</option>
                    </select>`)}
                ${crearCampo('Registro de propiedad solamente', `
                    <select name="transferencia[${i}][solo_registro_propiedad]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                <input type="hidden" name="transferencia[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarTesis() {
        const c = document.getElementById('tesis_dirigidas');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Estado', `
                    <select name="tesis_dirigidas[${i}][estado]">
                        <option value="defendida">Defendida</option>
                        <option value="en_proceso">En proceso</option>
                    </select>`)}
                ${crearCampo('Rol', `
                    <select name="tesis_dirigidas[${i}][rol]">
                        <option value="director">Director/a</option>
                        <option value="codirector">Codirector/a</option>
                    </select>`)}
                ${crearCampo('Anteproyecto aprobado', `
                    <select name="tesis_dirigidas[${i}][anteproyecto_aprobado]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                <input type="hidden" name="tesis_dirigidas[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarCongreso() {
        const c = document.getElementById('congresos');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo aportación', `
                    <select name="congresos[${i}][tipo]">
                        <option value="ponencia">Ponencia</option>
                        <option value="conferencia">Conferencia</option>
                        <option value="seminario">Seminario</option>
                    </select>`)}
                ${crearCampo('Ámbito', `
                    <select name="congresos[${i}][ambito]">
                        <option value="internacional">Internacional</option>
                        <option value="nacional">Nacional</option>
                        <option value="regional">Regional</option>
                    </select>`)}
                ${crearCampo('Admisión selectiva', `
                    <select name="congresos[${i}][admision_selectiva]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Invitación', `
                    <select name="congresos[${i}][por_invitacion]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                <input type="hidden" name="congresos[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarOtrosInvestigacion() {
        const c = document.getElementById('otros_meritos_investigacion');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="otros_meritos_investigacion[${i}][tipo]">
                        <option value="resena">Reseña / texto de difusión</option>
                        <option value="grupo_investigacion">Grupo de investigación</option>
                        <option value="premio_academico">Premio académico</option>
                        <option value="comite_cientifico">Comité científico / organización</option>
                        <option value="evaluador_articulos">Evaluador de artículos</option>
                        <option value="comite_editorial">Comité editorial</option>
                        <option value="otro">Otro mérito de investigación</option>
                    </select>`)}
                ${crearCampo('Relevancia', `
                    <select name="otros_meritos_investigacion[${i}][relevancia]">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>`)}
                <input type="hidden" name="otros_meritos_investigacion[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarDocencia() {
        const c = document.getElementById('docencia');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Horas', `<input type="number" name="docencia[${i}][horas]" min="0" value="60">`)}
                ${crearCampo('Nivel', `
                    <select name="docencia[${i}][nivel]">
                        <option value="grado">Grado</option>
                        <option value="master">Máster</option>
                    </select>`)}
                ${crearCampo('Responsabilidad', `
                    <select name="docencia[${i}][responsabilidad]">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>`)}
                ${crearCampo('Acreditada', `
                    <select name="docencia[${i}][acreditada]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                <input type="hidden" name="docencia[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarEvaluacionDocente() {
        const c = document.getElementById('evaluacion_docente');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Calificación', `
                    <select name="evaluacion_docente[${i}][calificacion]">
                        <option value="excelente">Excelente</option>
                        <option value="muy_favorable">Muy favorable</option>
                        <option value="favorable">Favorable</option>
                        <option value="suficiente">Suficiente</option>
                    </select>`)}
                ${crearCampo('Número de evaluaciones', `
                    <input type="number" name="evaluacion_docente[${i}][numero]" min="1" value="1">`)}
                <input type="hidden" name="evaluacion_docente[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarFormDocente() {
        const c = document.getElementById('formacion_docente');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="formacion_docente[${i}][tipo]">
                        <option value="seminario">Seminario</option>
                        <option value="curso">Curso</option>
                        <option value="congreso_docente">Congreso docente</option>
                    </select>`)}
                ${crearCampo('Por invitación', `
                    <select name="formacion_docente[${i}][por_invitacion]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                ${crearCampo('Ámbito', `
                    <select name="formacion_docente[${i}][ambito]">
                        <option value="internacional">Internacional</option>
                        <option value="nacional">Nacional</option>
                        <option value="regional">Regional</option>
                        <option value="local">Local</option>
                    </select>`)}
                <input type="hidden" name="formacion_docente[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarMaterialDocente() {
        const c = document.getElementById('material_docente');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="material_docente[${i}][tipo]">
                        <option value="publicacion_docente">Publicación docente</option>
                        <option value="proyecto_innovacion">Proyecto de innovación docente</option>
                        <option value="contribucion_eees">Contribución al EEES</option>
                        <option value="material_docente">Material docente</option>
                    </select>`)}
                ${crearCampo('ISBN/ISSN', `
                    <select name="material_docente[${i}][isbn_issn]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Relevancia', `
                    <select name="material_docente[${i}][relevancia]">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>`)}
                <input type="hidden" name="material_docente[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarFormacion() {
        const c = document.getElementById('formacion');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="formacion[${i}][tipo]">
                        <option value="doctorado_internacional">Doctorado internacional</option>
                        <option value="beca_competitiva">Beca competitiva</option>
                        <option value="estancia">Estancia</option>
                        <option value="master">Máster</option>
                        <option value="mencion_tesis">Mención de tesis</option>
                        <option value="curso_especializacion">Curso / seminario especialización</option>
                    </select>`)}
                ${crearCampo('Meses / duración', `
                    <input type="number" step="0.1" name="formacion[${i}][duracion]" min="0" value="1">`)}
                <input type="hidden" name="formacion[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarExperiencia() {
        const c = document.getElementById('experiencia');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Años', `<input type="number" step="0.1" name="experiencia[${i}][anios]" min="0" value="1">`)}
                ${crearCampo('Relación', `
                    <select name="experiencia[${i}][relacion]">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>`)}
                <input type="hidden" name="experiencia[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function agregarBloque4() {
        const c = document.getElementById('bloque4');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="bloque4[${i}][tipo]">
                        <option value="gestion">Gestión universitaria</option>
                        <option value="distincion">Distinción</option>
                        <option value="premio">Premio</option>
                        <option value="otro">Otro mérito</option>
                    </select>`)}
                ${crearCampo('Relevancia', `
                    <select name="bloque4[${i}][relevancia]">
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>`)}
                <input type="hidden" name="bloque4[${i}][es_valido]" value="1">
            </div>
        `);
        actualizarVacios();
    }

    function actualizarVacios() {
        document.querySelectorAll('.bloque').forEach(bloque => {
            const container = bloque.querySelector('div[id]');
            const emptyNote = bloque.querySelector('.empty-note');
            if (container && emptyNote) {
                emptyNote.style.display = container.children.length === 0 ? 'block' : 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', actualizarVacios);
</script>

<section class="card stack" style="margin-bottom: 32px;">
    <div class="meta-grid">
        <div class="metric">
            <span class="label">Candidato</span>
            <?php if ($nombre === ''): ?>
                <input type="text" name="nombre_candidato_nuevo" form="form-evaluacion" placeholder="Nombre del candidato..." required style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff; padding: 4px 8px; border-radius: 8px;">
            <?php else: ?>
                <span class="value" style="font-size:20px"><?= h($nombre) ?></span>
            <?php endif; ?>
        </div>
        <div class="metric">
            <span class="label">Área</span>
            <span class="value" style="font-size:20px">Humanidades</span>
        </div>
        <div class="metric">
            <span class="label">Modo</span>
            <span class="value" style="font-size:20px">Completar manualmente</span>
        </div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card stack">
            <form action="guardar_complemento.php" id="form-evaluacion" method="post">
                <input type="hidden" name="nombre_candidato" value="<?= h($nombre) ?>">
                <textarea name="json_entrada_base" style="display:none;"><?= h($jsonEntrada) ?></textarea>

                <div style="margin: 8px 0 4px;">
                    <h2 style="color:#fff;">Bloque 1. Investigación</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.A Publicaciones científicas</h3>
                            <p class="hint">Publicaciones con revisión por pares.</p>
                        </div>
                        <button type="button" onclick="agregarPublicacion()">Añadir publicación</button>
                    </div>
                    <div id="publicaciones"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.B Libros y capítulos</h3>
                            <p class="hint">Incluye también actas cuando proceda.</p>
                        </div>
                        <button type="button" onclick="agregarLibro()">Añadir libro/capítulo</button>
                    </div>
                    <div id="libros"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.C Proyectos y contratos de investigación</h3>
                            <p class="hint">Usa este bloque para proyectos y contratos con carácter investigador.</p>
                        </div>
                        <button type="button" onclick="agregarProyecto()">Añadir proyecto</button>
                    </div>
                    <div id="proyectos"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.D Transferencia</h3>
                            <p class="hint">Transferencia de conocimiento en sentido amplio.</p>
                        </div>
                        <button type="button" onclick="agregarTransferencia()">Añadir transferencia</button>
                    </div>
                    <div id="transferencia"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.E Dirección de tesis doctorales</h3>
                            <p class="hint">Tesis defendidas o en proceso con anteproyecto aprobado.</p>
                        </div>
                        <button type="button" onclick="agregarTesis()">Añadir tesis</button>
                    </div>
                    <div id="tesis_dirigidas"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.F Congresos, conferencias y seminarios</h3>
                            <p class="hint">Solo si cuentan con procedimiento selectivo.</p>
                        </div>
                        <button type="button" onclick="agregarCongreso()">Añadir congreso</button>
                    </div>
                    <div id="congresos"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.G Otros méritos de investigación</h3>
                            <p class="hint">Reseñas, grupos, premios, comités, evaluaciones, etc.</p>
                        </div>
                        <button type="button" onclick="agregarOtrosInvestigacion()">Añadir mérito</button>
                    </div>
                    <div id="otros_meritos_investigacion"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div style="margin: 8px 0 4px;">
                    <h2 style="color:#fff;">Bloque 2. Experiencia docente</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.A Docencia universitaria</h3>
                            <p class="hint">Horas de docencia reglada, grado o máster.</p>
                        </div>
                        <button type="button" onclick="agregarDocencia()">Añadir docencia</button>
                    </div>
                    <div id="docencia"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.B Evaluaciones sobre su docencia</h3>
                            <p class="hint">Número de evaluaciones y su calificación.</p>
                        </div>
                        <button type="button" onclick="agregarEvaluacionDocente()">Añadir evaluación</button>
                    </div>
                    <div id="evaluacion_docente"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.C Cursos y seminarios de formación docente</h3>
                            <p class="hint">Ponencias en seminarios, cursos o congresos orientados a formación docente.</p>
                        </div>
                        <button type="button" onclick="agregarFormacionDocente()">Añadir actividad</button>
                    </div>
                    <div id="formacion_docente"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.D Material docente, proyectos y contribuciones al EEES</h3>
                            <p class="hint">Publicaciones docentes, proyectos de innovación o materiales.</p>
                        </div>
                        <button type="button" onclick="agregarMaterialDocente()">Añadir material/proyecto</button>
                    </div>
                    <div id="material_docente"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div style="margin: 8px 0 4px;">
                    <h2 style="color:#fff;">Bloque 3. Formación académica y experiencia profesional</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>3.A Formación académica</h3>
                            <p class="hint">Menciones, posgrado, becas, estancias y cursos de especialización.</p>
                        </div>
                        <button type="button" onclick="agregarFormacion()">Añadir formación</button>
                    </div>
                    <div id="formacion"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>3.B Experiencia profesional</h3>
                            <p class="hint">Experiencia profesional relevante y específica.</p>
                        </div>
                        <button type="button" onclick="agregarExperiencia()">Añadir experiencia</button>
                    </div>
                    <div id="experiencia"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div style="margin: 8px 0 4px;">
                    <h2 style="color:#fff;">Bloque 4. Otros méritos</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>4. Otros méritos</h3>
                            <p class="hint">Gestión universitaria u otros méritos no contemplados antes.</p>
                        </div>
                        <button type="button" onclick="agregarBloque4()">Añadir mérito</button>
                    </div>
                    <div id="bloque4"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div class="form-actions">
                    <button type="submit">Fusionar, recalcular y guardar</button>
                    <a class="btn outline" href="<?= h(hum_index_url()) ?>">Cancelar</a>
                </div>
            </form>
        </section>
    </div>

    <aside class="stack">
        <section class="card aside-card-short">
            <h2 style="margin-bottom:8px;">Resumen</h2>
            <div class="kpis">
                <?php foreach ($resumen as $etiqueta => $cantidad): ?>
                    <div class="kpi">
                        <span class="label" style="font-size:12px;"><?= h($etiqueta) ?></span>
                        <strong style="font-size:24px;"><?= h((string)$cantidad) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card aside-card-short">
            <h2 style="margin-bottom:12px;"><i class="bi bi-lightbulb-fill text-warning me-2"></i>Ayuda rápida</h2>
            <div class="d-flex flex-column gap-2">
                <div class="help-item" style="border-left: 4px solid #f59e0b;">
                    <div class="help-tag" style="color: #f59e0b;">
                        <i class="bi bi-layers-half"></i> Fusión Inteligente
                    </div>
                    <div style="font-size: 14px; color: rgba(255,255,255,0.9);">
                        Los cambios se <strong>integran</strong> con los datos del PDF automáticamente.
                    </div>
                </div>
                <div class="help-item" style="border-left: 4px solid #10b981;">
                    <div class="help-tag" style="color: #10b981;">
                        <i class="bi bi-patch-check"></i> Normativa Oficial
                    </div>
                    <div style="font-size: 14px; color: rgba(255,255,255,0.9);">
                        Validado según los criterios actualizados de la rama <strong>Humanidades</strong>.
                    </div>
                </div>
                <div class="help-item" style="border-left: 4px solid #3b82f6;">
                    <div class="help-tag" style="color: #3b82f6;">
                        <i class="bi bi-cloud-arrow-up-fill"></i> Guardado Seguro
                    </div>
                    <div style="font-size: 14px; color: rgba(255,255,255,0.9);">
                        Pulsa <strong>"Fusionar y guardar"</strong> para persistir toda la información.
                    </div>
                </div>
            </div>
        </section>

        <section class="card">
            <details>
                <summary>Ver JSON extraído actualmente</summary>
                <pre><?= h((string)json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </details>
        </section>
    </aside>
</section>
<?php hum_render_layout_end(); ?>