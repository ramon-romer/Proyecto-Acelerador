/* === START JS: js/chatbot.js === */
document.addEventListener('DOMContentLoaded', function() {
    var bubble = document.getElementById('cb-bubble-toggle');
    var windowChat = document.getElementById('cb-chat-window');
    var closeBtn = document.getElementById('cb-chat-close');
    var resetBtn = document.getElementById('cb-chat-reset');
    var body = document.getElementById('cb-chat-body');
    
    // Detección de rol desde el atributo inyectado por PHP
    var rol = windowChat ? windowChat.getAttribute('data-role') : 'profesor';

    // BANCO DE PREGUNTAS EXPANDIDO (6 por Rol)
    var bank = {
        profesor: [
            { q: '¿Por qué mi notificación de cuenta atrás no desaparece?', r: 'Las alertas de tiempo son persistentes para garantizar que no pases por alto ningún plazo límite. Solo desaparecerán si haces clic manualmente en la "X" de la alerta o si el contador llega a cero.' },
            { q: '¿Qué significa la línea verde gruesa en mis entregas?', r: '¡Enhorabuena! Significa que el tutor ha revisado y aprobado formalmente esa entrega. La línea de 4px verde éxito se aplica para dejar claro que ese hito está superado.' },
            { q: '¿Por qué no puedo empezar la Entrega 2 si ya la tengo lista?', r: 'El sistema es estrictamente secuencial. Hasta que el tutor no marque como "Hecha" la Entrega 1 en su panel, la Entrega 2 permanecerá bloqueada. En cuanto sea aprobada, se iniciará tu nuevo cronómetro.' },
            { q: '¿Qué pasa si el contador de mi alerta llega a cero y no he subido nada?', r: 'El plazo habrá expirado oficialmente. La alerta se cerrará, el sistema registrará el retraso en la base de datos y se notificará automáticamente al tutor, reflejándose como "FUERA DE PLAZO" en el gráfico global.' },
            { q: 'Ya he corregido y subido los cambios, ¿por qué la cuenta atrás sigue corriendo?', r: 'La cuenta atrás no se detiene automáticamente al subir un archivo. El reloj seguirá corriendo en tiempo real hasta que el tutor entre a su panel, revise tu trabajo y pulse el botón oficial de "Marcar como hecha". ¡Asegúrate de avisarle!' },
            { q: '¿Por qué me sale que una entrega está "Activa" si yo todavía no he empezado a trabajar en ella?', r: 'Porque el sistema es automatizado: en el segundo exacto en el que el tutor aprobó tu entrega anterior, el servidor le dio el relevo a la siguiente fase y clavó la fecha de inicio. Tu tiempo para este nuevo hito ya está corriendo.' }
        ],
        tutor: [
            { q: '¿Cómo activo el tiempo límite de la siguiente entrega de un profesor?', r: 'Ve al modal "Ver tareas" y haz clic en "Marcar como hecha" en la entrega actual. El backend actualizará la base de datos y activará la Entrega N+1, iniciando su cuenta atrás con la fecha y hora de este instante.' },
            { q: '¿Qué significa el letrero rojo FUERA DE PLAZO en el Gantt?', r: 'Es un aviso del semáforo temporal. Significa que la fecha actual ha superado el plazo teórico estipulado en el JSON de la tarea y el profesor sigue pendiente. Se quitará en cuanto marques la tarea como hecha.' },
            { q: 'Hago clic en las barras del gráfico de Gantt y no pasa nada, ¿por qué?', r: 'El modal del gráfico es estrictamente visual e informativo para ver la cascada de tiempos completa en un solo lienzo. Para realizar acciones operativas como aprobar entregas, debes usar obligatoriamente el botón de "Ver tareas".' },
            { q: '¿Por qué la barra de una entrega pendiente se hace más larga cada día en el gráfico?', r: 'Es una de las mejores funciones del sistema: si una entrega está "Activa" pero sigue pendiente, su barra en el Gantt crece dinámicamente día a día usando la fecha de hoy como punto final provisional, permitiéndote medir visualmente el retraso real.' },
            { q: '¿Dónde puedo consultar el histórico de evaluaciones ANECA de este profesor?', r: 'Se ha optimizado el modal de estado eliminando las tres tarjetas superiores para limpiar la interfaz. Ahora, en la columna izquierda, dispones de una tarjeta limpia y exclusiva que renderiza el contador histórico ANECA consultado en tiempo real en la base de datos.' },
            { q: 'Si marco una entrega como hecha por error, ¿puedo deshacerlo?', r: 'Atención: el flujo está blindado para ser estrictamente secuencial y disparar el relevo del cronómetro de la siguiente entrega en el acto. Antes de hacer clic en "Marcar como hecha", asegúrate bien de que el profesor ha cumplido con los requisitos.' }
        ]
    };

    function initChat() {
        if (!body) return;
        var welcome = (rol === 'tutor') ? '¡Hola Tutor! ¿En qué puedo asistirte con tus grupos hoy?' : '¡Hola Profesor! ¿Qué duda puedo resolverte sobre tus entregas?';
        body.innerHTML = '<p class="bot-msg"><b>Asistente:</b> ' + welcome + '</p>';
        
        var container = document.createElement('div');
        container.className = 'cb-options-container';
        container.innerHTML = '<p class="cb-menu-label">Preguntas frecuentes del Rol:</p>';
        
        var questions = bank[rol] || bank['profesor'];
        questions.forEach(function(item) {
            var btn = document.createElement('button');
            btn.className = 'cb-option-btn';
            btn.innerText = item.q;
            btn.addEventListener('click', function() {
                addMessage('Tú', item.q, 'user-msg');
                // Simulamos respuesta rápida
                setTimeout(function() {
                    addMessage('Asistente', item.r, 'bot-msg');
                }, 400);
            });
            container.appendChild(btn);
        });
        body.appendChild(container);
    }

    function addMessage(who, text, className) {
        var p = document.createElement('p');
        p.className = className;
        p.innerHTML = '<b>' + who + ':</b> ' + text;
        body.appendChild(p);
        body.scrollTop = body.scrollHeight;
    }

    // Toggle de Apertura / Cierre
    if(bubble && windowChat) {
        bubble.addEventListener('click', function(e) {
            e.preventDefault();
            if(windowChat.style.display === 'none' || windowChat.style.display === '') {
                windowChat.style.display = 'flex';
                body.scrollTop = body.scrollHeight;
            } else {
                windowChat.style.display = 'none';
            }
        });
    }

    if(closeBtn && windowChat) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            windowChat.style.display = 'none';
        });
    }

    // Lógica de Reseteo (🔄)
    if(resetBtn) {
        resetBtn.addEventListener('click', function(e) {
            e.preventDefault();
            initChat();
        });
    }

    // Arranque Inicial
    initChat();
});
/* === END JS === */
