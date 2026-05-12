/**
 * ACELERADOR - SUPERADMIN GOD MODE LOGIC
 * AJAX Handlers & UI Interactions
 */

$(document).ready(function() {
    
    // --- SISTEMA DE NOTIFICACIONES ESTILO PROYECTO ---
    window.showNotification = function(message, type = 'success') {
        const container = $('#toast-container');
        const id = 'toast-' + Date.now();
        const icon = type === 'success' ? 'check-circle-fill' : (type === 'danger' ? 'exclamation-octagon-fill' : 'info-circle-fill');
        
        const toast = `
            <div id="${id}" class="custom-toast ${type}">
                <i class="bi bi-${icon} me-3 fs-4"></i>
                <div class="toast-content">
                    <div class="fw-bold">${type === 'success' ? 'Éxito' : 'Aviso'}</div>
                    <div class="small opacity-75">${message}</div>
                </div>
                <div class="toast-progress"></div>
            </div>
        `;
        
        container.append(toast);
        
        // Auto-eliminar después de 4s
        setTimeout(() => {
            $(`#${id}`).addClass('fade-out');
            setTimeout(() => $(`#${id}`).remove(), 500);
        }, 4000);
    };

    // --- SISTEMA DE CONFIRMACIÓN ELITE ---
    window.customConfirm = function(title, msg, onConfirm) {
        $('#confirm-title').text(title);
        $('#confirm-msg').text(msg);
        $('#custom-confirm-container').fadeIn(200).css('display', 'flex');
        
        $('#confirm-ok').off('click').on('click', function() {
            $('#custom-confirm-container').fadeOut(200);
            onConfirm();
        });
        
        $('#confirm-cancel').off('click').on('click', function() {
            $('#custom-confirm-container').fadeOut(200);
        });
    };

    // --- USUARIOS ---

    window.openEditUser = function(userData) {
        $('#edit_user_id').val(userData.id);
        $('#edit_user_nombre').val(userData.nombre);
        $('#edit_user_apellidos').val(userData.apellidos);
        $('#edit_user_correo').val(userData.correo);
        $('#edit_user_dni').val(userData.DNI);
        $('#edit_user_orcid').val(userData.ORCID);
        $('#edit_user_telefono').val(userData.telefono);
        $('#edit_user_facultad').val(userData.facultad);
        $('#edit_user_departamento').val(userData.departamento);
        $('#edit_user_perfil').val(userData.perfil);
        $('#edit_user_rama').val(userData.rama);
        $('#edit_user_pass').val('');
        
        $('#modalEditUser').modal('show');
    };

    $('#formEditUser').on('submit', function(e) {
        e.preventDefault();
        const data = $(this).serialize() + '&action=edit_user';
        
        $.post('superadmin.php', data, function(res) {
            if (res.status === 'ok') {
                $('#modalEditUser').modal('hide');
                showNotification(res.message);
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification(res.message, 'danger');
            }
        }, 'json');
    });

    window.deleteUser = function(id) {
        customConfirm('¿ELIMINAR USUARIO?', 'Esta acción es irreversible y eliminará todos los datos asociados.', function() {
            $.post('superadmin.php', { action: 'delete_user', id: id }, function(res) {
                if (res.status === 'ok') {
                    $(`#u-${id}`).fadeOut();
                    showNotification(res.message);
                }
            }, 'json');
        });
    };

    // --- GESTIÓN DINÁMICA DE TAREAS ---

    window.loadUserTasks = function(profId, profName) {
        $('#task_prof_name').text(profName);
        const body = $('#user_tasks_body');
        body.html('<tr><td colspan="3" class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></td></tr>');
        
        $('#modalUserTasks').modal('show');

        $.post('superadmin.php', { action: 'get_user_tasks', prof_id: profId }, function(res) {
            if (res.status === 'ok') {
                body.empty();
                if (res.tasks.length === 0) {
                    body.append('<tr><td colspan="3" class="text-center p-4 text-white-50">No hay tareas asignadas a este profesor.</td></tr>');
                    return;
                }
                
                res.tasks.forEach(t => {
                    const row = `
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold">${t.titulo_tarea}</div>
                                <div class="small text-white-50 text-truncate" style="max-width: 250px;">${t.descripcion_tarea || 'Sin descripción'}</div>
                            </td>
                            <td>
                                <span class="badge bg-white bg-opacity-10">${t.num_entregas} Hitos</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn-action btn-edit" onclick='openIntervention(${t.id}, "${t.titulo_tarea.replace(/"/g, '&quot;')}", ${t.num_entregas}, ${JSON.stringify(t.fechas_entregas)}, ${JSON.stringify(t.fechas_reales_entregas || "[]")})' title="Intervenir">
                                        <i class="bi bi-gear-fill"></i>
                                    </button>
                                    <button class="btn-action btn-reset" onclick="resetTask(${t.id})" title="Resetear">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                    <button class="btn-action btn-delete" onclick="deleteTask(${t.id})" title="Eliminar Tarea">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                    body.append(row);
                });
            }
        }, 'json');
    };

    window.deleteTask = function(id) {
        customConfirm('¿ELIMINAR TAREA?', 'Esta acción es definitiva y borrará todos los plazos.', function() {
            $.post('superadmin.php', { action: 'delete_task', id: id }, function(res) {
                if (res.status === 'ok') {
                    showNotification(res.message);
                    setTimeout(() => location.reload(), 1000);
                }
            }, 'json');
        });
    };

    window.openIntervention = function(id, titulo, num_entregas, fechas, reales) {
        $('#inter_task_id').val(id);
        const container = $('#hitos_container');
        container.empty();
        
        const fechasArr = typeof fechas === 'string' ? JSON.parse(fechas || '[]') : fechas;
        const realesArr = typeof reales === 'string' ? JSON.parse(reales || '[]') : reales;

        for (let i = 0; i < num_entregas; i++) {
            const dateVal = fechasArr[i] || '';
            const isDone = (realesArr[i] && realesArr[i] !== null) ? 'checked' : '';
            
            container.append(`
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-1 text-center fw-bold text-white-50">${i+1}</div>
                    <div class="col-7">
                        <input type="datetime-local" class="form-control hito-fecha" data-idx="${i}" value="${dateVal.replace(' ', 'T')}">
                    </div>
                    <div class="col-4 d-flex align-items-center gap-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input hito-done" type="checkbox" data-idx="${i}" ${isDone}>
                            <label class="small text-white-50">Hecha</label>
                        </div>
                    </div>
                </div>
            `);
        }
        
        $('#modalIntervention').modal('show');
    };

    $('#btnSaveIntervention').on('click', function() {
        const id = $('#inter_task_id').val();
        const fechas = [];
        const reales = [];
        
        $('.hito-fecha').each(function() {
            fechas.push($(this).val().replace('T', ' '));
        });
        
        $('.hito-done').each(function() {
            if ($(this).is(':checked')) {
                reales.push(new Date().toISOString().slice(0, 19).replace('T', ' '));
            } else {
                reales.push(null);
            }
        });

        $.post('superadmin.php', {
            action: 'intervene_task',
            id: id,
            fechas: JSON.stringify(fechas),
            reales: JSON.stringify(reales)
        }, function(res) {
            if (res.status === 'ok') {
                $('#modalIntervention').modal('hide');
                showNotification(res.message);
                // Opcional: refrescar el listado de tareas en el modal previo
                // Para simplificar, refrescamos la página o cerramos ambos
                setTimeout(() => location.reload(), 1000);
            }
        }, 'json');
    });

    window.resetTask = function(id) {
        customConfirm('¿RESET TAREA?', '¿Deseas resetear los plazos de esta tarea?', function() {
            $.post('superadmin.php', { action: 'reset_task', id: id }, function(res) {
                if (res.status === 'ok') {
                    showNotification(res.message);
                    setTimeout(() => location.reload(), 1000);
                }
            }, 'json');
        });
    };

    // --- GESTIÓN DE USUARIOS ELIMINADOS ---

    window.loadDeletedUsers = function() {
        const body = $('#deleted_users_body');
        body.html('<tr><td colspan="6" class="text-center p-4"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Cargando...</td></tr>');

        $.post('superadmin.php', { action: 'get_deleted_users' }, function(res) {
            if (res.status === 'ok') {
                body.empty();
                if (res.users.length === 0) {
                    body.append('<tr><td colspan="6" class="text-center p-4 text-white-50"><i class="bi bi-inbox-fill me-2" style="font-size:1.2rem;"></i>No hay usuarios en la papelera.</td></tr>');
                    return;
                }
                res.users.forEach(u => {
                    const badgeClass = u.perfil === 'ADMIN' ? 'bg-danger' : (u.perfil === 'TUTOR' ? 'bg-primary' : 'bg-info');
                    const row = `
                        <tr>
                            <td class="fw-bold text-white-50">${u.id_original}</td>
                            <td class="fw-medium">${u.nombre}</td>
                            <td class="text-white-50">${u.correo}</td>
                            <td><span class="badge ${badgeClass} bg-opacity-25 text-white">${u.perfil}</span></td>
                            <td class="text-white-50 small">${u.fecha}</td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-sm btn-success rounded-pill px-3 fw-bold d-flex align-items-center gap-1" onclick="restoreUser(${u.id})">
                                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                    </button>
                                    <button class="btn-action btn-delete" onclick="purgeUser(${u.id})" title="Purgar permanentemente">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                    body.append(row);
                });
            }
        }, 'json');
    };

    window.restoreUser = function(id) {
        customConfirm('¿RESTAURAR USUARIO?', 'El usuario recuperará toda su información y volverá a tener acceso al sistema.', function() {
            $.post('superadmin.php', { action: 'restore_user', id: id }, function(res) {
                if (res.status === 'ok') {
                    showNotification(res.message);
                    loadDeletedUsers();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(res.message, 'danger');
                }
            }, 'json');
        });
    };

    window.purgeUser = function(id) {
        customConfirm('¿PURGAR PERMANENTEMENTE?', 'Esta acción destruirá la copia de seguridad de este usuario PARA SIEMPRE. No se podrá recuperar.', function() {
            $.post('superadmin.php', { action: 'purge_user', id: id }, function(res) {
                if (res.status === 'ok') {
                    showNotification(res.message);
                    loadDeletedUsers();
                } else {
                    showNotification(res.message, 'danger');
                }
            }, 'json');
        });
    };

    // Auto-cargar usuarios eliminados al abrir la página
    loadDeletedUsers();
});