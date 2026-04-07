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

if ($nombre === '' || $jsonEntrada === '') {
    die('Faltan datos obligatorios para completar el expediente.');
}

$jsonExtraido = json_decode($jsonEntrada, true);

if (!is_array($jsonExtraido)) {
    die('El JSON extraído no es válido.');
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

salud_render_layout_start(
    'Completar expediente manualmente',
    'Añade los apartados que faltan antes de fusionar, recalcular y guardar.',
    [
        ['label' => 'Portal ANECA', 'url' => salud_portal_url()],
        ['label' => 'Salud', 'url' => salud_index_url()],
        ['label' => 'Completar expediente'],
    ],
    [
        ['label' => 'Volver a Salud', 'url' => salud_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => salud_portal_url(), 'class' => 'light'],
    ]
);
?>
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
                        <option value="OTRO">Otro</option>
                    </select>`)}
                ${crearCampo('Decil JCR', `
                    <select name="publicaciones[${i}][decil]">
                        <option value="D1">D1</option>
                        <option value="D2">D2</option>
                        <option value="D3">D3</option>
                        <option value="D4">D4</option>
                        <option value="D5">D5</option>
                        <option value="D6">D6</option>
                        <option value="D7">D7</option>
                        <option value="D8">D8</option>
                        <option value="D9">D9</option>
                        <option value="D10">D10</option>
                    </select>`)}
                ${crearCampo('Cuartil JCR', `
                    <select name="publicaciones[${i}][cuartil]">
                        <option value="Q1">Q1</option>
                        <option value="Q2">Q2</option>
                        <option value="Q3">Q3</option>
                        <option value="Q4">Q4</option>
                    </select>`)}
                ${crearCampo('Tipo de artículo', `
                    <select name="publicaciones[${i}][tipo_aportacion]">
                        <option value="original">Original</option>
                        <option value="revision">Revisión</option>
                        <option value="nota_clinica">Nota clínica</option>
                        <option value="carta">Carta</option>
                        <option value="otro">Otro</option>
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
                        <option value="correspondencia">Correspondencia</option>
                        <option value="intermedio">Intermedio</option>
                        <option value="secundario">Secundario</option>
                    </select>`)}
                ${crearCampo('Número autores', `
                    <input type="number" name="publicaciones[${i}][numero_autores]" min="1" value="1">`)}
                ${crearCampo('Citas', `
                    <input type="number" name="publicaciones[${i}][citas]" min="0" value="0">`)}
                ${crearCampo('Primera y última página acreditadas', `
                    <select name="publicaciones[${i}][acreditacion_paginas]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                <input type="hidden" name="publicaciones[${i}][tipo]" value="articulo">
                <input type="hidden" name="publicaciones[${i}][es_valida]" value="1">
            </div>
        `);
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
                        <option value="alta_difusion">Alta difusión</option>
                        <option value="media_difusion">Media difusión</option>
                        <option value="baja_difusion">Baja difusión</option>
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
                ${crearCampo('Editorial consignada', `
                    <select name="libros[${i}][editorial_consignada]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Autoedición', `
                    <select name="libros[${i}][es_autoedicion]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                ${crearCampo('Pago por publicar', `
                    <select name="libros[${i}][pago_por_publicar]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                ${crearCampo('Tesis doctoral publicada', `
                    <select name="libros[${i}][tesis_publicada]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                ${crearCampo('Libro de actas de congreso', `
                    <select name="libros[${i}][es_acta_congreso]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                <input type="hidden" name="libros[${i}][es_valido]" value="1">
                <input type="hidden" name="libros[${i}][es_libro_investigacion]" value="1">
            </div>
        `);
    }

    function agregarProyecto() {
        const c = document.getElementById('proyectos');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo proyecto', `
                    <select name="proyectos[${i}][tipo_proyecto]">
                        <option value="europeo">Europeo</option>
                        <option value="nacional">Nacional</option>
                        <option value="autonomico">Autonómico</option>
                        <option value="universidad">Universidad</option>
                        <option value="instituto_investigacion">Instituto de investigación</option>
                    </select>`)}
                ${crearCampo('Rol', `
                    <select name="proyectos[${i}][rol]">
                        <option value="ip">IP</option>
                        <option value="ic">IC</option>
                        <option value="investigador">Investigador</option>
                        <option value="participacion_menor">Participación menor</option>
                    </select>`)}
                ${crearCampo('Años duración', `
                    <input type="number" step="0.1" name="proyectos[${i}][anios_duracion]" min="0" value="1">`)}
                ${crearCampo('Competitivo', `
                    <select name="proyectos[${i}][competitivo]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Ensayo clínico', `
                    <select name="proyectos[${i}][es_ensayo_clinico]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                ${crearCampo('Certificación válida', `
                    <select name="proyectos[${i}][esta_certificado]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Certificado solo por IP', `
                    <select name="proyectos[${i}][solo_certificado_ip]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                <input type="hidden" name="proyectos[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarTransferencia() {
        const c = document.getElementById('transferencia');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="transferencia[${i}][tipo]">
                        <option value="transferencia_conocimiento">Transferencia de conocimiento</option>
                        <option value="resultado_transferible">Resultado transferible</option>
                        <option value="impacto_asistencial">Impacto asistencial</option>
                        <option value="impacto_social">Impacto social</option>
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
                <input type="hidden" name="transferencia[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarTesis() {
        const c = document.getElementById('tesis_dirigidas');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Presentada y aprobada', `
                    <select name="tesis_dirigidas[${i}][presentada_aprobada]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Rol', `
                    <select name="tesis_dirigidas[${i}][rol]">
                        <option value="director">Director/a</option>
                        <option value="codirector">Codirector/a</option>
                    </select>`)}
                ${crearCampo('Certificado universidad', `
                    <select name="tesis_dirigidas[${i}][certificado_universidad]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                <input type="hidden" name="tesis_dirigidas[${i}][estado]" value="defendida">
                <input type="hidden" name="tesis_dirigidas[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarCongreso() {
        const c = document.getElementById('congresos');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo aportación', `
                    <select name="congresos[${i}][tipo]">
                        <option value="ponencia_invitada">Ponencia invitada</option>
                        <option value="comunicacion">Comunicación</option>
                        <option value="poster">Póster</option>
                        <option value="reunion">Reunión científica</option>
                    </select>`)}
                ${crearCampo('Sociedad científica de referencia', `
                    <select name="congresos[${i}][sociedad_referencia]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Aceptación selectiva', `
                    <select name="congresos[${i}][admision_selectiva]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Nº participaciones mismo evento', `
                    <input type="number" name="congresos[${i}][numero_mismo_congreso]" min="1" value="1">`)}
                <input type="hidden" name="congresos[${i}][es_valido]" value="1">
            </div>
        `);
    }

    function agregarOtrosInvestigacion() {
        const c = document.getElementById('otros_meritos_investigacion');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="otros_meritos_investigacion[${i}][tipo]">
                        <option value="premio_investigacion">Premio investigación</option>
                        <option value="actividad_cientifica">Actividad científica relevante</option>
                        <option value="grupo_investigacion">Grupo investigación</option>
                        <option value="revision">Revisión/colaboración científica</option>
                        <option value="otro">Otro mérito</option>
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
    }

    function agregarDocencia() {
        const c = document.getElementById('docencia');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Horas', `<input type="number" name="docencia[${i}][horas]" min="0" value="60">`)}
                ${crearCampo('Puesto ocupado', `
                    <select name="docencia[${i}][puesto]">
                        <option value="contratado">Profesor con contrato</option>
                        <option value="venia_docendi">Venia Docendi</option>
                        <option value="colaborador_honorario">Colaborador Honorario</option>
                        <option value="tutor_practicas">Tutor de clases prácticas</option>
                    </select>`)}
                ${crearCampo('Nivel', `
                    <select name="docencia[${i}][nivel]">
                        <option value="grado">Grado</option>
                        <option value="master">Máster</option>
                    </select>`)}
                ${crearCampo('Certificación válida', `
                    <select name="docencia[${i}][acreditada]">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>`)}
                ${crearCampo('Certificado por director/secretario depto.', `
                    <select name="docencia[${i}][solo_dpto]">
                        <option value="0">No</option>
                        <option value="1">Sí</option>
                    </select>`)}
                <input type="hidden" name="docencia[${i}][es_valido]" value="1">
            </div>
        `);
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
    }

    function agregarFormacionDocente() {
        const c = document.getElementById('formacion_docente');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="formacion_docente[${i}][tipo]">
                        <option value="docente">Actividad docente impartida</option>
                        <option value="discente">Actividad docente recibida</option>
                    </select>`)}
                ${crearCampo('Actividad técnica', `
                    <select name="formacion_docente[${i}][es_tecnica]">
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
    }

    function agregarMaterialDocente() {
        const c = document.getElementById('material_docente');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="material_docente[${i}][tipo]">
                        <option value="material_docente">Material docente</option>
                        <option value="publicacion_docente">Publicación docente</option>
                        <option value="proyecto_innovacion">Proyecto innovación educativa</option>
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
    }

    function agregarFormacion() {
        const c = document.getElementById('formacion');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="formacion[${i}][tipo]">
                        <option value="mir_equivalente">MIR o equivalente</option>
                        <option value="doctorado_internacional">Tesis con mención internacional</option>
                        <option value="doble_titulacion">Dobles titulaciones/especialidades</option>
                        <option value="master_universitario">Máster universitario</option>
                        <option value="board_europeo">Board europeo</option>
                        <option value="master_titulo_propio">Máster título propio / Experto universitario</option>
                        <option value="beca_competitiva">Beca FPI/FPU o análoga</option>
                        <option value="estancia">Estancia en centro de prestigio</option>
                        <option value="curso_especializacion">Curso especialización</option>
                    </select>`)}
                ${crearCampo('Duración', `
                    <input type="number" step="0.1" name="formacion[${i}][duracion]" min="0" value="1">`)}
                ${crearCampo('PCD: excluir curso especialización', `
                    <select name="formacion[${i}][excluir_en_pcd]">
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
    }

    function agregarBloque4() {
        const c = document.getElementById('bloque4');
        const i = c.children.length;
        c.insertAdjacentHTML('beforeend', `
            <div class="fila">
                ${crearCampo('Tipo', `
                    <select name="bloque4[${i}][tipo]">
                        <option value="otro">Otro mérito</option>
                        <option value="distincion">Distinción</option>
                        <option value="premio">Premio</option>
                        <option value="gestion">Gestión</option>
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
    }
</script>

<section class="split">
    <div class="stack">
        <section class="card">
            <h2>Contexto del expediente</h2>
            <div class="meta-grid">
                <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= h($nombre) ?></span></div>
                <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Salud</span></div>
                <div class="metric"><span class="label">Modo</span><span class="value" style="font-size:20px">Completar manualmente</span></div>
            </div>
        </section>

        <section class="card stack">
            <div>
                <h2>Formulario</h2>
                <p class="muted">Añade los méritos que falten antes de recalcular.</p>
            </div>

            <form action="guardar_complemento.php" method="post" class="stack">
                <input type="hidden" name="nombre_candidato" value="<?= h($nombre) ?>">
                <textarea name="json_entrada_base" style="display:none;"><?= h($jsonEntrada) ?></textarea>

                <div style="margin: 8px 0 4px;">
                    <h2>Bloque 1. Investigación</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.A Publicaciones científicas</h3>
                            <p class="hint">JCR por deciles como criterio principal.</p>
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
                            <p class="hint">Editoriales de amplia difusión relacionadas con la investigación.</p>
                        </div>
                        <button type="button" onclick="agregarLibro()">Añadir libro/capítulo</button>
                    </div>
                    <div id="libros"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.C Proyectos de investigación</h3>
                            <p class="hint">Competitivos, con rol y duración acreditados correctamente.</p>
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
                            <p class="hint">Transferencia e impacto relevante.</p>
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
                            <p class="hint">Solo tesis presentadas y aprobadas.</p>
                        </div>
                        <button type="button" onclick="agregarTesis()">Añadir tesis</button>
                    </div>
                    <div id="tesis_dirigidas"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>1.F Congresos y reuniones</h3>
                            <p class="hint">Aportaciones de calidad en sociedades científicas de referencia.</p>
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
                            <p class="hint">Premios y actividades de relevancia científica.</p>
                        </div>
                        <button type="button" onclick="agregarOtrosInvestigacion()">Añadir mérito</button>
                    </div>
                    <div id="otros_meritos_investigacion"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div style="margin: 8px 0 4px;">
                    <h2>Bloque 2. Experiencia docente</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.A Docencia universitaria</h3>
                            <p class="hint">Horas impartidas y acreditación válida.</p>
                        </div>
                        <button type="button" onclick="agregarDocencia()">Añadir docencia</button>
                    </div>
                    <div id="docencia"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.B Evaluación docente</h3>
                            <p class="hint">Evaluaciones positivas de la actividad realizada.</p>
                        </div>
                        <button type="button" onclick="agregarEvaluacionDocente()">Añadir evaluación</button>
                    </div>
                    <div id="evaluacion_docente"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.C Formación en docencia</h3>
                            <p class="hint">Discente y docente; no cuentan actividades técnicas.</p>
                        </div>
                        <button type="button" onclick="agregarFormacionDocente()">Añadir actividad</button>
                    </div>
                    <div id="formacion_docente"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>2.D Material docente y publicaciones/proyectos</h3>
                            <p class="hint">Material docente, publicaciones docentes e innovación educativa.</p>
                        </div>
                        <button type="button" onclick="agregarMaterialDocente()">Añadir material/proyecto</button>
                    </div>
                    <div id="material_docente"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div style="margin: 8px 0 4px;">
                    <h2>Bloque 3. Formación académica y experiencia profesional</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>3.A Formación académica</h3>
                            <p class="hint">MIR/equivalente, mención internacional, board, becas, estancias, etc.</p>
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
                            <p class="hint">Experiencia profesional relevante.</p>
                        </div>
                        <button type="button" onclick="agregarExperiencia()">Añadir experiencia</button>
                    </div>
                    <div id="experiencia"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div style="margin: 8px 0 4px;">
                    <h2>Bloque 4. Otros méritos</h2>
                </div>

                <section class="bloque">
                    <div class="section-toolbar">
                        <div>
                            <h3>4. Otros méritos</h3>
                            <p class="hint">Otros méritos no encajados en apartados anteriores.</p>
                        </div>
                        <button type="button" onclick="agregarBloque4()">Añadir mérito</button>
                    </div>
                    <div id="bloque4"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
                </section>

                <div class="form-actions">
                    <button type="submit">Fusionar, recalcular y guardar</button>
                    <a class="btn outline" href="<?= h(salud_index_url()) ?>">Cancelar</a>
                    <a class="btn outline" href="<?= h(salud_portal_url()) ?>">Ir al portal</a>
                </div>
            </form>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen de extracción</h2>
            <div class="kpis">
                <?php foreach ($resumen as $etiqueta => $cantidad): ?>
                    <div class="kpi">
                        <span class="label"><?= h($etiqueta) ?></span>
                        <strong><?= h((string)$cantidad) ?></strong>
                    </div>
                <?php endforeach; ?>
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
<?php salud_render_layout_end(); ?>