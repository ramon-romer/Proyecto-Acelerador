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

$resumen = [
    '1A Publicaciones' => count($jsonExtraido['bloque_1']['publicaciones'] ?? []),
    '1B Libros' => count($jsonExtraido['bloque_1']['libros'] ?? []),
    '1C Proyectos' => count($jsonExtraido['bloque_1']['proyectos'] ?? []),
    '1D Transferencia' => count($jsonExtraido['bloque_1']['transferencia'] ?? []),
    '2A Docencia' => count($jsonExtraido['bloque_2']['docencia_universitaria'] ?? []),
    '3A Formación' => count($jsonExtraido['bloque_3']['formacion_academica'] ?? []),
    '4 Otros méritos' => count($jsonExtraido['bloque_4'] ?? []),
];

salud_render_layout_start(
    'Completar expediente manualmente',
    'Mantiene la lógica actual.',
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
                    ${crearCampo('Tipo', `
                        <select name="publicaciones[${i}][tipo]">
                            <option value="articulo">Artículo</option>
                            <option value="patente">Patente</option>
                            <option value="guia_clinica">Guía clínica</option>
                        </select>`)}
                    ${crearCampo('Tipo índice', `
                        <select name="publicaciones[${i}][tipo_indice]">
                            <option value="JCR">JCR</option>
                            <option value="SJR">SJR</option>
                            <option value="PATENTE">PATENTE</option>
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
                        <input type="text" name="publicaciones[${i}][subtipo_indice]" placeholder="Ej. B1">`)}

                    ${crearCampo('Tipo aportación', `
                        <select name="publicaciones[${i}][tipo_aportacion]">
                            <option value="articulo">Artículo</option>
                            <option value="ensayo_clinico">Ensayo clínico</option>
                            <option value="revision_sistematica">Revisión sistemática</option>
                            <option value="metaanalisis">Metaanálisis</option>
                            <option value="estudio_observacional">Estudio observacional</option>
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
                            <option value="primero">Primero</option>
                            <option value="ultimo">Último</option>
                            <option value="correspondencia">Correspondencia</option>
                            <option value="intermedio">Intermedio</option>
                        </select>`)}
                    ${crearCampo('Número autores', `
                        <input type="number" name="publicaciones[${i}][numero_autores]" min="1" value="5">`)}

                    ${crearCampo('Citas', `
                        <input type="number" name="publicaciones[${i}][citas]" min="0" value="0">`)}
                    ${crearCampo('Años desde publicación', `
                        <input type="number" name="publicaciones[${i}][anios_desde_publicacion]" min="0" value="3">`)}
                    ${crearCampo('Liderazgo', `
                        <select name="publicaciones[${i}][liderazgo]">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>`)}

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

                    <input type="hidden" name="libros[${i}][es_valido]" value="1">
                    <input type="hidden" name="libros[${i}][es_libro_investigacion]" value="1">
                    <input type="hidden" name="libros[${i}][es_autoedicion]" value="0">
                    <input type="hidden" name="libros[${i}][es_acta_congreso]" value="0">
                    <input type="hidden" name="libros[${i}][es_labor_edicion]" value="0">
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
                            <option value="internacional">Internacional</option>
                            <option value="nacional">Nacional</option>
                            <option value="autonomico">Autonómico</option>
                            <option value="hospitalario">Hospitalario</option>
                            <option value="ensayo_multicentrico">Ensayo multicéntrico</option>
                        </select>`)}
                    ${crearCampo('Rol', `
                        <select name="proyectos[${i}][rol]">
                            <option value="ip">IP</option>
                            <option value="coip">Co-IP</option>
                            <option value="investigador">Investigador</option>
                            <option value="participacion_menor">Participación menor</option>
                        </select>`)}
                    ${crearCampo('Dedicación', `
                        <select name="proyectos[${i}][dedicacion]">
                            <option value="completa">Completa</option>
                            <option value="parcial">Parcial</option>
                            <option value="residual">Residual</option>
                        </select>`)}
                    ${crearCampo('Años duración', `
                        <input type="number" step="0.1" name="proyectos[${i}][anios_duracion]" min="0" value="1">`)}

                    ${crearCampo('Certificado', `
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
            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Tipo', `
                        <select name="transferencia[${i}][tipo]">
                            <option value="patente_b1">Patente B1</option>
                            <option value="patente_b2">Patente B2</option>
                            <option value="protocolo_clinico">Protocolo clínico</option>
                            <option value="software_sanitario">Software sanitario</option>
                            <option value="dispositivo_medico">Dispositivo médico</option>
                        </select>`)}
                    ${crearCampo('Impacto externo', `
                        <select name="transferencia[${i}][impacto_externo]">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>`)}
                    ${crearCampo('Liderazgo', `
                        <select name="transferencia[${i}][liderazgo]">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>`)}
                    ${crearCampo('Participación menor', `
                        <select name="transferencia[${i}][participacion_menor]">
                            <option value="0">No</option>
                            <option value="1">Sí</option>
                        </select>`)}
                    <input type="hidden" name="transferencia[${i}][es_valido]" value="1">
                </div>
            `);
        }

        function agregarTesis() {
            const c = document.getElementById('tesis');
            const i = c.children.length;
            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Tipo', `
                        <select name="tesis[${i}][tipo]">
                            <option value="direccion_unica">Dirección única</option>
                            <option value="codireccion">Codirección</option>
                        </select>`)}
                    ${crearCampo('Calidad especial', `
                        <select name="tesis[${i}][calidad_especial]">
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
            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Ámbito', `
                        <select name="congresos[${i}][ambito]">
                            <option value="internacional">Internacional</option>
                            <option value="nacional">Nacional</option>
                            <option value="regional">Regional</option>
                            <option value="local">Local</option>
                        </select>`)}
                    ${crearCampo('Tipo', `
                        <select name="congresos[${i}][tipo]">
                            <option value="ponencia_invitada">Ponencia invitada</option>
                            <option value="comunicacion_oral">Comunicación oral</option>
                            <option value="poster">Póster</option>
                        </select>`)}
                    ${crearCampo('ID evento', `
                        <input type="text" name="congresos[${i}][id_evento]" placeholder="salud_conf_1">`)}
                    <input type="hidden" name="congresos[${i}][es_valido]" value="1">
                </div>
            `);
        }

        function agregarOtroInvestigacion() {
            const c = document.getElementById('otros_investigacion');
            const i = c.children.length;
            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Tipo', `
                        <select name="otros_investigacion[${i}][tipo]">
                            <option value="revisor">Revisor</option>
                            <option value="consejo_editorial">Consejo editorial</option>
                            <option value="tribunal_tesis">Tribunal tesis</option>
                            <option value="premio">Premio</option>
                            <option value="grupo_investigacion">Grupo investigación</option>
                            <option value="sociedad_cientifica">Sociedad científica</option>
                        </select>`)}
                    <input type="hidden" name="otros_investigacion[${i}][es_valido]" value="1">
                </div>
            `);
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
                    <input type="hidden" name="docencia[${i}][es_valido]" value="1">
                </div>
            `);
        }

        function agregarEvalDocente() {
            const c = document.getElementById('eval_docente');
            const i = c.children.length;
            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Tipo', `
                        <select name="eval_docente[${i}][tipo]">
                            <option value="docentia">DOCENTIA</option>
                            <option value="encuestas">Encuestas</option>
                        </select>`)}
                    ${crearCampo('Resultado', `
                        <select name="eval_docente[${i}][resultado]">
                            <option value="excelente">Excelente</option>
                            <option value="positiva">Positiva</option>
                            <option value="aceptable">Aceptable</option>
                        </select>`)}
                    <input type="hidden" name="eval_docente[${i}][es_valido]" value="1">
                </div>
            `);
        }

        function agregarFormDocente() {
            const c = document.getElementById('form_docente');
            const i = c.children.length;
            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Horas', `<input type="number" name="form_docente[${i}][horas]" min="0" value="20">`)}
                    ${crearCampo('Rol', `
                        <select name="form_docente[${i}][rol]">
                            <option value="docente">Docente</option>
                            <option value="asistente">Asistente</option>
                        </select>`)}
                    <input type="hidden" name="form_docente[${i}][es_valido]" value="1">
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
                            <option value="material_publicado">Material publicado</option>
                            <option value="proyecto_innovacion">Proyecto innovación</option>
                            <option value="publicacion_docente">Publicación docente</option>
                            <option value="simulacion_clinica">Simulación clínica</option>
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
                            <option value="doctorado_internacional">Doctorado internacional</option>
                            <option value="beca_competitiva">Beca competitiva</option>
                            <option value="estancia">Estancia</option>
                            <option value="master">Máster</option>
                            <option value="especialidad_clinica">Especialidad clínica</option>
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
                            <option value="gestion">Gestión</option>
                            <option value="servicio_academico">Servicio académico</option>
                            <option value="distincion">Distinción</option>
                            <option value="sociedad_cientifica">Sociedad científica</option>
                            <option value="otro">Otro</option>
                        </select>`)}
                    <input type="hidden" name="bloque4[${i}][es_valido]" value="1">
                </div>
            `);
        }
    </script>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= salud_h($nombre) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Salud</span></div>
        <div class="metric"><span class="label">Modo</span><span class="value" style="font-size:20px">Completar manualmente</span></div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card">
<form action="guardar_complemento.php" method="post">
        <input type="hidden" name="nombre_candidato" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">
        <textarea name="json_entrada_base" style="display:none;"><?= htmlspecialchars($jsonEntrada, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 1. Investigación</h2>
        </div>

        <div class="bloque">
            <h3>1.A Publicaciones, patentes y guías clínicas</h3>
            <p class="hint">Añade publicaciones adicionales que no hayan quedado reflejadas al procesar.</p>
            <div id="publicaciones"></div>
            <button type="button" class="btn" onclick="agregarPublicacion()">Añadir publicación</button>
        </div>

        <div class="bloque">
            <h3>1.B Libros y capítulos</h3>
            <p class="hint">Usa este bloque para completar libros o capítulos no detectados al procesar.</p>
            <div id="libros"></div>
            <button type="button" class="btn" onclick="agregarLibro()">Añadir libro/capítulo</button>
        </div>

        <div class="bloque">
            <h3>1.C Proyectos</h3>
            <p class="hint">Usa este bloque para completar Proyectos.</p>
            <div id="proyectos"></div>
            <button type="button" class="btn" onclick="agregarProyecto()">Añadir proyecto</button>
        </div>

        <div class="bloque">
            <h3>1.D Transferencia</h3>
                <p class="hint">Usa este bloque para completar Transferencia adicional al procesar.</p>
            <div id="transferencia"></div>
            <button type="button" class="btn" onclick="agregarTransferencia()">Añadir transferencia</button>
        </div>

        <div class="bloque">
            <h3>1.E Tesis dirigidas</h3>
            <p class="hint">Usa este bloque para completar Tesis dirigidas al procesar.</p>
            <div id="tesis"></div>
            <button type="button" class="btn" onclick="agregarTesis()">Añadir tesis</button>
        </div>

        <div class="bloque">
            <h3>1.F Congresos</h3>
            <p class="hint">Usa este bloque para completar Congresos al procesar.</p>
            <div id="congresos"></div>
            <button type="button" class="btn" onclick="agregarCongreso()">Añadir congreso</button>
        </div>

        <div class="bloque">
            <h3>1.G Otros méritos de investigación</h3>
            <p class="hint">Usa este bloque para completar Otros méritos de ?Investigacion.</p>
            <div id="otros_investigacion"></div>
            <button type="button" class="btn" onclick="agregarOtroInvestigacion()">Añadir mérito</button>
        </div>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 2. Docencia</h2>
        </div>

        <div class="bloque">
            <h3>2.A Docencia universitaria</h3>
            <p class="hint">Usa este bloque para completar Docencia universitaria al procesar.</p>    
            <div id="docencia"></div>
            <button type="button" class="btn" onclick="agregarDocencia()">Añadir docencia</button>
        </div>

        <div class="bloque">
            <h3>2.B Evaluación docente</h3>
            <p class="hint">Usa este bloque para completar Evaluacion Docente al procesar.</p>    
            <div id="eval_docente"></div>
            <button type="button" class="btn" onclick="agregarEvalDocente()">Añadir evaluación docente</button>
        </div>

        <div class="bloque">
            <h3>2.C Formación docente</h3>
            <p class="hint">Usa este bloque para completar Formación Docente al procesar.</p>    
            <div id="form_docente"></div>
            <button type="button" class="btn" onclick="agregarFormDocente()">Añadir formación docente</button>
        </div>

        <div class="bloque">
            <h3>2.D Material docente</h3>
            <p class="hint">Usa este bloque para completar Material Docente al procesar.</p>    
            <div id="material_docente"></div>
            <button type="button" class="btn" onclick="agregarMaterialDocente()">Añadir material docente</button>
        </div>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 3. Formación y experiencia</h2>
        </div>

        <div class="bloque">
            <h3>3.A Formación académica</h3>
            <p class="hint">Usa este bloque para completar Formación Académica al procesar.</p>    
            <div id="formacion"></div>
            <button type="button" class="btn" onclick="agregarFormacion()">Añadir formación</button>
        </div>

        <div class="bloque">
            <h3>3.B Experiencia profesional</h3>
            <p class="hint">Usa este bloque para completar Experiencia Profesional al procesar.</p>    
            <div id="experiencia"></div>
            <button type="button" class="btn" onclick="agregarExperiencia()">Añadir experiencia</button>
        </div>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 4. Otros méritos</h2>
        </div>

        <div class="bloque">
            <h3>4. Otros méritos</h3>
            <p class="hint">Usa este bloque para completar Otros Meritos al procesar.</p>    
            <div id="bloque4"></div>
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
                <pre><?= salud_h(json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
            </details>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen de extracción</h2>
            <p class="muted">Vista rápida del expediente detectado antes de añadir o corregir datos manualmente.</p>
            <div class="kpis">
                    <div class="kpi"><span class="label">1A Publicaciones</span><strong><?= salud_h((string)$resumen['1A Publicaciones']) ?></strong></div>
                    <div class="kpi"><span class="label">1B Libros</span><strong><?= salud_h((string)$resumen['1B Libros']) ?></strong></div>
                    <div class="kpi"><span class="label">1C Proyectos</span><strong><?= salud_h((string)$resumen['1C Proyectos']) ?></strong></div>
                    <div class="kpi"><span class="label">1D Transferencia</span><strong><?= salud_h((string)$resumen['1D Transferencia']) ?></strong></div>
                    <div class="kpi"><span class="label">2A Docencia</span><strong><?= salud_h((string)$resumen['2A Docencia']) ?></strong></div>
                    <div class="kpi"><span class="label">3A Formación</span><strong><?= salud_h((string)$resumen['3A Formación']) ?></strong></div>
                    <div class="kpi"><span class="label">4 Otros méritos</span><strong><?= salud_h((string)$resumen['4 Otros méritos']) ?></strong></div>
            </div>
        </section>

        <section class="card">
            <h2>Ayuda rápida</h2>
            <ul>
                <li>El formulario mantiene la lógica actual del módulo.</li>
                <li>Para pruebas, el JSON técnico sigue accesible en el panel inferior.</li>
                <li>La navegación ya queda integrada con el portal general.</li>
            </ul>
        </section>
    </aside>
</section>
<?php salud_render_layout_end(); ?>
