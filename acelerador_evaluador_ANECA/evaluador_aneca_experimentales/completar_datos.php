<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['token_experimentales'])) {
    $_SESSION['token_experimentales'] = bin2hex(random_bytes(32));
}

$tokenFormulario = $_SESSION['token_experimentales'];

$nombre = trim($_POST['nombre_candidato'] ?? '');
$jsonEntrada = trim($_POST['json_entrada'] ?? '');

if ($nombre === '' || $jsonEntrada === '') {
    die('Faltan datos obligatorios para completar el expediente.');
}

$jsonExtraido = json_decode($jsonEntrada, true);

if (!is_array($jsonExtraido)) {
    die('El JSON extraído no es válido.');
}

$orientacion = [
    ['codigo' => '1A', 'texto' => 'Publicaciones científicas y patentes', 'maximo' => '35'],
    ['codigo' => '1B', 'texto' => 'Libros y capítulos de libros', 'maximo' => '7'],
    ['codigo' => '1C', 'texto' => 'Proyectos y contratos de investigación', 'maximo' => '7'],
    ['codigo' => '1D', 'texto' => 'Transferencia de tecnología', 'maximo' => '4'],
    ['codigo' => '1E', 'texto' => 'Dirección de tesis doctorales', 'maximo' => '4'],
    ['codigo' => '1F', 'texto' => 'Congresos, conferencias y seminarios', 'maximo' => '2'],
    ['codigo' => '1G', 'texto' => 'Otros méritos de investigación', 'maximo' => '1'],
    ['codigo' => '2A', 'texto' => 'Docencia universitaria', 'maximo' => '17'],
    ['codigo' => '2B', 'texto' => 'Evaluaciones sobre su calidad', 'maximo' => '3'],
    ['codigo' => '2C', 'texto' => 'Cursos y seminarios de formación docente universitaria', 'maximo' => '3'],
    ['codigo' => '2D', 'texto' => 'Material docente, proyectos y contribuciones al EEES', 'maximo' => '7'],
    ['codigo' => '3A', 'texto' => 'Tesis, becas, estancias, otros títulos', 'maximo' => '6'],
    ['codigo' => '3B', 'texto' => 'Trabajo en empresas e instituciones', 'maximo' => '2'],
    ['codigo' => '4', 'texto' => 'Otros méritos', 'maximo' => '2'],
];
require __DIR__ . '/ui.php';

$resumen = [
    '1 Investigación' => count($jsonExtraido['bloque_1'] ?? []),
    '2 Docencia' => count($jsonExtraido['bloque_2'] ?? []),
    '3 Formación/experiencia' => count($jsonExtraido['bloque_3'] ?? []),
    '4 Otros méritos' => count($jsonExtraido['bloque_4'] ?? []),
];

exp_render_layout_start(
    'Completar expediente manualmente',
    'Mantiene la lógica actual del módulo Experimental.',
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
                            <option value="otro">Otro</option>
                        </select>
                    `)}
                    ${crearCampo('Título', `<input type="text" name="publicaciones[${i}][titulo]">`)}
                    ${crearCampo('Año', `<input type="text" name="publicaciones[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="publicaciones[${i}][puntuacion]">`)}
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
                        </select>
                    `)}
                    ${crearCampo('Título', `<input type="text" name="libros[${i}][titulo]">`)}
                    ${crearCampo('Editorial / ISBN', `<input type="text" name="libros[${i}][editorial]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="libros[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarProyecto() {
            const c = document.getElementById('proyectos');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Proyecto / Contrato', `<input type="text" name="proyectos[${i}][titulo]">`)}
                    ${crearCampo('Rol', `<input type="text" name="proyectos[${i}][rol]">`)}
                    ${crearCampo('Año', `<input type="text" name="proyectos[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="proyectos[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarTransferencia() {
            const c = document.getElementById('transferencia');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Mérito de transferencia', `<input type="text" name="transferencia[${i}][descripcion]">`)}
                    ${crearCampo('Entidad', `<input type="text" name="transferencia[${i}][entidad]">`)}
                    ${crearCampo('Año', `<input type="text" name="transferencia[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="transferencia[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarTesis() {
            const c = document.getElementById('tesis');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Título tesis', `<input type="text" name="tesis_dirigidas[${i}][titulo]">`)}
                    ${crearCampo('Doctorando', `<input type="text" name="tesis_dirigidas[${i}][doctorando]">`)}
                    ${crearCampo('Año', `<input type="text" name="tesis_dirigidas[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="tesis_dirigidas[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarCongreso() {
            const c = document.getElementById('congresos');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Congreso / Seminario', `<input type="text" name="congresos[${i}][titulo]">`)}
                    ${crearCampo('Tipo participación', `<input type="text" name="congresos[${i}][tipo]">`)}
                    ${crearCampo('Año', `<input type="text" name="congresos[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="congresos[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarOtroInvestigacion() {
            const c = document.getElementById('otros_investigacion');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Descripción', `<input type="text" name="otros_meritos_investigacion[${i}][descripcion]">`)}
                    ${crearCampo('Año', `<input type="text" name="otros_meritos_investigacion[${i}][anio]">`)}
                    ${crearCampo('Entidad / detalle', `<input type="text" name="otros_meritos_investigacion[${i}][detalle]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="otros_meritos_investigacion[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarDocencia() {
            const c = document.getElementById('docencia');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Asignatura / docencia', `<input type="text" name="docencia_universitaria[${i}][asignatura]">`)}
                    ${crearCampo('Universidad / centro', `<input type="text" name="docencia_universitaria[${i}][centro]">`)}
                    ${crearCampo('Horas / años', `<input type="text" name="docencia_universitaria[${i}][dedicacion]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="docencia_universitaria[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarEvaluacionDocente() {
            const c = document.getElementById('evaluacion_docente');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Evaluación / programa', `<input type="text" name="evaluacion_docente[${i}][descripcion]">`)}
                    ${crearCampo('Resultado', `<input type="text" name="evaluacion_docente[${i}][resultado]">`)}
                    ${crearCampo('Año', `<input type="text" name="evaluacion_docente[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="evaluacion_docente[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarFormacionDocente() {
            const c = document.getElementById('formacion_docente');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Curso / seminario', `<input type="text" name="formacion_docente[${i}][titulo]">`)}
                    ${crearCampo('Entidad', `<input type="text" name="formacion_docente[${i}][entidad]">`)}
                    ${crearCampo('Horas', `<input type="text" name="formacion_docente[${i}][horas]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="formacion_docente[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarMaterialDocente() {
            const c = document.getElementById('material_docente');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Material / proyecto EEES', `<input type="text" name="material_docente[${i}][descripcion]">`)}
                    ${crearCampo('Tipo', `<input type="text" name="material_docente[${i}][tipo]">`)}
                    ${crearCampo('Año', `<input type="text" name="material_docente[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="material_docente[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarFormacionAcademica() {
            const c = document.getElementById('formacion_academica');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Mérito académico', `<input type="text" name="formacion_academica[${i}][descripcion]">`)}
                    ${crearCampo('Entidad', `<input type="text" name="formacion_academica[${i}][entidad]">`)}
                    ${crearCampo('Año', `<input type="text" name="formacion_academica[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="formacion_academica[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarExperienciaProfesional() {
            const c = document.getElementById('experiencia_profesional');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Empresa / institución', `<input type="text" name="experiencia_profesional[${i}][entidad]">`)}
                    ${crearCampo('Puesto', `<input type="text" name="experiencia_profesional[${i}][puesto]">`)}
                    ${crearCampo('Duración', `<input type="text" name="experiencia_profesional[${i}][duracion]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="experiencia_profesional[${i}][puntuacion]">`)}
                </div>
            `);
        }

        function agregarBloque4() {
            const c = document.getElementById('bloque4');
            const i = c.children.length;

            c.insertAdjacentHTML('beforeend', `
                <div class="fila">
                    ${crearCampo('Descripción', `<input type="text" name="bloque_4[${i}][descripcion]">`)}
                    ${crearCampo('Tipo', `<input type="text" name="bloque_4[${i}][tipo]">`)}
                    ${crearCampo('Año', `<input type="text" name="bloque_4[${i}][anio]">`)}
                    ${crearCampo('Puntuación orientativa', `<input type="number" step="0.01" name="bloque_4[${i}][puntuacion]">`)}
                </div>
            `);
        }
    </script>
<section class="card stack">
    <div class="meta-grid">
        <div class="metric"><span class="label">Candidato</span><span class="value" style="font-size:20px"><?= exp_h($nombre) ?></span></div>
        <div class="metric"><span class="label">Área</span><span class="value" style="font-size:20px">Experimentales</span></div>
        <div class="metric"><span class="label">Modo</span><span class="value" style="font-size:20px">Completar manualmente</span></div>
    </div>
</section>

<section class="split">
    <div class="stack">
        <section class="card">
<form action="guardar_complemento.php" method="post">
        <input type="hidden" name="token" value="<?= htmlspecialchars($tokenFormulario, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="nombre_candidato" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">
        <textarea name="json_entrada" style="display:none;"><?= htmlspecialchars($jsonEntrada, ENT_QUOTES, 'UTF-8') ?></textarea>

        <div style="margin: 8px 0 4px;">
            <h2>Bloque 1. Investigación</h2>
        </div>
        <section class="bloque">
            <div class="section-toolbar">
                <div>
                            <h3>1.A Publicaciones científicas y patentes</h3>
                            <p class="hint">Añade publicaciones y patentes adicionales que no hayan quedado reflejadas al procesar.</p>
                        </div>
                        <button type="button" onclick="agregarPublicacion()">Añadir publicación</button>
                </div>
                    <div id="publicaciones"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
        </section>
               
                
        <section class="bloque">
            <div class="section-toolbar">
                <div>
                            <h3>1.B. Libros y capítulos de libros</h3>
                            <p class="hint">Añade libros y capitulos adicionales que no hayan quedado reflejadas al procesar.</p>
                        </div>
                        <button type="button" onclick="agregarLibro()">Añadir libro</button>
                </div>
                    <div id="publicaciones"></div>
                    <div class="empty-note">Sin filas añadidas todavía.</div>
        </section>

        <section class="bloque">
            <div class="section-toolbar">
                <div>
                            <h3>1.C. Proyectos y contratos de investigación</h3>
                            <p class="hint">Añade Proyectos y contratos adicionales que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarProyecto()">Añadir proyecto</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
        </section>


        <section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>1.D. Transferencia de tecnología</h3>
                        <p class="hint">Añade Transferencia de Tecnología adicionales que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarTransferencia()">Añadir mérito de transferencia</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
        </section>


        <section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>1.E. Dirección de tesis doctorales</h3>
                        <p class="hint">Añade Direccion de tesis doctoral adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarTesis()">Añadir Direccion de Tesis Doctoral</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
        </section>

        <section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>1.F. Congresos, conferencias y seminarios</h3>
                        <p class="hint">Añade Congresos, conferencias y seminarios adicionales que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarCongreso()">Añadir mérito</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
        </section>

<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>1.G. Otros méritos de investigación</h3>
                        <p class="hint">Añade Otros meritos de Investigacion adicionales que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarOtroInvestigacion()">Añadir Otros Métodos</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


        <div class="bloque">
            <h2>Bloque 2. Experiencia docente</h2>

<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>2.A. Docencia universitaria</h3>
                        <p class="hint">Añade Docencia universitaria adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarDocencia()">Añadir Docencia</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>2.B. Evaluaciones sobre su calidad</h3>
                        <p class="hint">Añade Evaluaciones sobre su calidad adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarEvaluacionDocente()">Añadir evaluación</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>2.C. Cursos y seminarios de formación docente universitaria</h3>
                        <p class="hint">Añade cursos y seminarios de formación docente universitaria adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarFormacionDocente()">Añadir Curso y seminarios de Formación Docente</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>2.D. Material docente, proyectos y contribuciones al EEES</h3>
                        <p class="hint">Añade Material Docente adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarMaterialDocente()">Añadir Material Docente, proyectos y contribuciones al EEES</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


        <div class="bloque">
            <h2>Bloque 3. Formación académica y experiencia profesional</h2>


<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>3.A. Tesis, becas, estancias, otros títulos</h3>
                        <p class="hint">Añade Tesis, becas, estancias, otros títulos adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarFormacionAcademica()">Añadir Tesis, becas, estancias, otros títulos</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>3.B. Trabajo en empresas e instituciones</h3>
                        <p class="hint">Añade Trabajo en empresas e instituciones adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarExperienciaProfesional()">Añadir Trabajo en empresas e instituciones</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


<section class="bloque">
            <div class="section-toolbar">
                <div>
                        <h3>Bloque 4. Otros méritos</h3>
                        <p class="hint">Añade Bloque 4. Otros méritos adicional que no hayan quedado reflejadas al procesar.</p>
                </div>
                        <button type="button" onclick="agregarBloque4()">Añadir Otros méritos</button>
            </div>
                <div id="publicaciones"></div>
                <div class="empty-note">Sin filas añadidas todavía.</div>
</section>


<div class="acciones">
            <button type="submit">Fusionar, recalcular y guardar</button>
            <a class="btn outline" href="index.php">Cancelar</a>
        </div>
    </form>
        </section>
        <section class="card">
            <details>
                <summary>Ver JSON extraído</summary>
                <pre><?= exp_h($jsonEntrada) ?></pre>
            </details>
        </section>
    </div>

    <aside class="stack">
        <section class="card">
            <h2>Resumen orientativo de puntuaciones máximas</h2>
            <p class="muted">Umbral de evaluación positiva: <strong>1 + 2 ≥ 50</strong> y <strong>1 + 2 + 3 + 4 ≥ 55</strong>.</p>
            <table>
                <thead><tr><th>Código</th><th>Subapartado</th><th>Máximo</th></tr></thead>
                <tbody>
                <?php foreach ($orientacion as $fila): ?>
                    <tr>
                        <td><?= exp_h($fila['codigo']) ?></td>
                        <td><?= exp_h($fila['texto']) ?></td>
                        <td><?= exp_h($fila['maximo']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <section class="card">
            <h2>Resumen de extracción</h2>
            <div class="kpis">
                <div class="kpi"><span class="label">Bloque 1</span><strong><?= exp_h((string)$resumen['1 Investigación']) ?></strong></div>
                <div class="kpi"><span class="label">Bloque 2</span><strong><?= exp_h((string)$resumen['2 Docencia']) ?></strong></div>
                <div class="kpi"><span class="label">Bloque 3</span><strong><?= exp_h((string)$resumen['3 Formación/experiencia']) ?></strong></div>
                <div class="kpi"><span class="label">Bloque 4</span><strong><?= exp_h((string)$resumen['4 Otros méritos']) ?></strong></div>
            </div>
        </section>
    </aside>
</section>
<?php exp_render_layout_end(); ?>
