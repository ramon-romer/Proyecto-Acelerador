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
    '2A Docencia' => count($jsonExtraido['bloque_2']['docencia_universitaria'] ?? []),
    '3A Formación' => count($jsonExtraido['bloque_3']['formacion_academica'] ?? []),
    '3B Experiencia' => count($jsonExtraido['bloque_3']['experiencia_profesional'] ?? []),
    '4 Otros méritos' => count($jsonExtraido['bloque_4'] ?? []),
];

csyj_render_layout_start(
    'Completar expediente manualmente',
    'Mantiene la lógica actual.',
    [
        ['label' => 'Portal ANECA', 'url' => csyj_portal_url()],
        ['label' => 'CSyJ', 'url' => csyj_index_url()],
        ['label' => 'Completar expediente'],
    ],
    [
        ['label' => 'Volver a CSyJ', 'url' => csyj_index_url(), 'class' => 'light'],
        ['label' => 'Portal principal', 'url' => csyj_portal_url(), 'class' => 'light'],
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
                    ${crearCampo('Tipo índice',
                        `<select name="publicaciones[${i}][tipo_indice]">
                            <option value="JCR">JCR</option>
                            <option value="SJR">SJR</option>
                            <option value="FECYT">FECYT</option>
                            <option value="RESH">RESH</option>
                            <option value="MIAR">MIAR</option>
                        </select>`)}
                    ${crearCampo('Cuartil',
                        `<select name="publicaciones[${i}][cuartil]">
                            <option value="">--</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>`)}
                    ${crearCampo('Tipo aportación',
                        `<select name="publicaciones[${i}][tipo_aportacion]">
                            <option value="articulo">Artículo</option>
                            <option value="analisis_empirico">Análisis empírico</option>
                            <option value="estudio_juridico">Estudio jurídico</option>
                            <option value="revision">Revisión</option>
                        </select>`)}
                    ${crearCampo('Afinidad',
                        `<select name="publicaciones[${i}][afinidad]">
                            <option value="total">Total</option>
                            <option value="relacionada">Relacionada</option>
                            <option value="periferica">Periférica</option>
                            <option value="ajena">Ajena</option>
                        </select>`)}

                    ${crearCampo('Posición autor',
                        `<select name="publicaciones[${i}][posicion_autor]">
                            <option value="autor_unico">Autor único</option>
                            <option value="primero">Primero</option>
                            <option value="ultimo">Último</option>
                            <option value="correspondencia">Correspondencia</option>
                            <option value="intermedio">Intermedio</option>
                            <option value="secundario">Secundario</option>
                        </select>`)}
                    ${crearCampo('Número autores',
                        `<input type="number" name="publicaciones[${i}][numero_autores]" min="1" value="1">`)}
                    ${crearCampo('Citas',
                        `<input type="number" name="publicaciones[${i}][citas]" min="0" value="0">`)}
                    ${crearCampo('Subtipo índice',
                        `<input type="text" name="publicaciones[${i}][subtipo_indice]" placeholder="Ej. C1">`)}

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
                    ${crearCampo('Tipo',
                        `<select name="libros[${i}][tipo]">
                            <option value="libro">Libro</option>
                            <option value="capitulo">Capítulo</option>
                        </select>`)}
                    ${crearCampo('Nivel editorial',
                        `<select name="libros[${i}][nivel_editorial]">
                            <option value="prestigiosa">Prestigiosa</option>
                            <option value="secundaria">Secundaria</option>
                            <option value="baja">Baja</option>
                        </select>`)}
                    ${crearCampo('Afinidad',
                        `<select name="libros[${i}][afinidad]">
                            <option value="total">Total</option>
                            <option value="relacionada">Relacionada</option>
                            <option value="periferica">Periférica</option>
                            <option value="ajena">Ajena</option>
                        </select>`)}
                    ${crearCampo('Posición autor',
                        `<select name="libros[${i}][posicion_autor]">
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
                    ${crearCampo('Tipo proyecto',
                        `<select name="proyectos[${i}][tipo_proyecto]">
                            <option value="internacional">Internacional</option>
                            <option value="nacional">Nacional</option>
                            <option value="autonomico">Autonómico</option>
                            <option value="universidad">Universidad</option>
                            <option value="contrato">Contrato</option>
                        </select>`)}
                    ${crearCampo('Rol',
                        `<select name="proyectos[${i}][rol]">
                            <option value="ip">IP</option>
                            <option value="coip">Co-IP</option>
                            <option value="investigador">Investigador</option>
                            <option value="participacion_menor">Participación menor</option>
                        </select>`)}
                    ${crearCampo('Años duración',
                        `<input type="number" step="0.1" name="proyectos[${i}][anios_duracion]" min="0" value="1">`)}
                    ${crearCampo('Certificado',
                        `<select name="proyectos[${i}][esta_certificado]">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>`)}

                    <input type="hidden" name="proyectos[${i}][es_valido]" value="1">
                </div>
            `);
        }

        function agregarDocencia() {
            const c = document.getElementById('docencia');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Horas',
                        `<input type="number" name="docencia[${i}][horas]" min="0" value="60">`)}
                    ${crearCampo('Nivel',
                        `<select name="docencia[${i}][nivel]">
                            <option value="grado">Grado</option>
                            <option value="master">Máster</option>
                        </select>`)}
                    ${crearCampo('Responsabilidad',
                        `<select name="docencia[${i}][responsabilidad]">
                            <option value="alta">Alta</option>
                            <option value="media">Media</option>
                            <option value="baja">Baja</option>
                        </select>`)}
                    <input type="hidden" name="docencia[${i}][es_valido]" value="1">
                </div>
            `);
        }

        function agregarFormacion() {
            const c = document.getElementById('formacion');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Tipo',
                        `<select name="formacion[${i}][tipo]">
                            <option value="doctorado_internacional">Doctorado internacional</option>
                            <option value="beca_competitiva">Beca competitiva</option>
                            <option value="estancia">Estancia</option>
                            <option value="master">Máster</option>
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
                    ${crearCampo('Años',
                        `<input type="number" step="0.1" name="experiencia[${i}][anios]" min="0" value="1">`)}
                    ${crearCampo('Relación',
                        `<select name="experiencia[${i}][relacion]">
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
                    ${crearCampo('Tipo',
                        `<select name="bloque4[${i}][tipo]">
                            <option value="gestion">Gestión</option>
                            <option value="distincion">Distinción</option>
                        </select>`)}
                    <input type="hidden" name="bloque4[${i}][es_valido]" value="1">
                </div>
            `);
        }
    </script>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= csyj_h($nombre) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Ciencias Sociales y Jurídicas</span></div>
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
            <h3>1.A Publicaciones científicas</h3>
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
            <p class="hint">Usa este bloque para completar Proyectos de Investigacion o contratos relevantes al procesar.</p>
            <div id="proyectos"></div>
            <button type="button" class="btn" onclick="agregarProyecto()">Añadir proyecto</button>
        </div>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 2. Docencia</h2>
        </div>

        <div class="bloque">
            <h3>2.A Docencia universitaria</h3>
            <p class="hint">Usa este bloque para completar actividad docente que quieras incorporar antes del recálculo.</p>
            <div id="docencia"></div>
            <button type="button" class="btn" onclick="agregarDocencia()">Añadir docencia</button>
        </div>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 3. Formación y experiencia</h2>
        </div>

        <div class="bloque">
            <h3>3.A Formación académica</h3>
            <p class="hint">Usa este bloque para completar doctorado internacional, becas, estancias o máster.</p>
            <div id="formacion"></div>
            <button type="button" class="btn" onclick="agregarFormacion()">Añadir formación</button>
        </div>

        <div class="bloque">
            <h3>3.B Experiencia profesional</h3>
             <p class="hint">Usa este bloque para completar experiencia profesional vinculada al área.</p>
            <div id="experiencia"></div>
            <button type="button" class="btn" onclick="agregarExperiencia()">Añadir experiencia</button>
        </div>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 4. Otros méritos</h2>
        </div>

        <div class="bloque">
            <h3>4. Otros méritos</h3>
             <p class="hint">Usa este bloque para completar Gestión, distinciones u otros méritos finales.</p>
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
                <pre><?= csyj_h(json_encode($jsonExtraido, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
            </details>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen de extracción</h2>
            <p class="muted">Vista rápida del expediente detectado antes de añadir o corregir datos manualmente.</p>
            <div class="kpis">
                    <div class="kpi"><span class="label">1A Publicaciones</span><strong><?= csyj_h((string)$resumen['1A Publicaciones']) ?></strong></div>
                    <div class="kpi"><span class="label">1B Libros</span><strong><?= csyj_h((string)$resumen['1B Libros']) ?></strong></div>
                    <div class="kpi"><span class="label">1C Proyectos</span><strong><?= csyj_h((string)$resumen['1C Proyectos']) ?></strong></div>
                    <div class="kpi"><span class="label">2A Docencia</span><strong><?= csyj_h((string)$resumen['2A Docencia']) ?></strong></div>
                    <div class="kpi"><span class="label">3A Formación</span><strong><?= csyj_h((string)$resumen['3A Formación']) ?></strong></div>
                    <div class="kpi"><span class="label">3B Experiencia</span><strong><?= csyj_h((string)$resumen['3B Experiencia']) ?></strong></div>
                    <div class="kpi"><span class="label">4 Otros méritos</span><strong><?= csyj_h((string)$resumen['4 Otros méritos']) ?></strong></div>
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
<?php csyj_render_layout_end(); ?>
