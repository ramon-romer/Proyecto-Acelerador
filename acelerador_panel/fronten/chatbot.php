<?php
/**
 * ARQUITECTO DE UX Y SOPORTE INTELIGENTE: chatbot.php
 * Detección por sesión PHP y banco expandido de 12 preguntas críticas.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detección de rol (perfil_usuario es el estándar del proyecto, rol como fallback)
$perfilSesion = $_SESSION['perfil_usuario'] ?? ($_SESSION['rol'] ?? 'profesor');
$rolUsuario = strtolower(trim($perfilSesion));

// Gestión dinámica de rutas para assets locales
$currentDir = dirname($_SERVER['SCRIPT_NAME']);
$assetPath = (strpos($currentDir, 'acelerador_panel/fronten') !== false) ? '' : 'acelerador_panel/fronten/';
?>

<!-- Burbuja Flotante (Launcher) -->
<div id="cb-bubble-toggle" class="cb-bubble">
    <i class="bi bi-chat-dots-fill"></i>
</div>

<!-- Ventana del Chat -->
<div id="cb-chat-window" class="cb-window" data-role="<?php echo htmlspecialchars($rolUsuario); ?>">
    <div class="cb-header">
        <div class="cb-header-info">
            <span>Asistente Acelerador</span>
        </div>
        <div class="cb-header-actions">
            <button id="cb-chat-reset" class="cb-reset-btn" title="Reiniciar conversación">🔄</button>
            <button id="cb-chat-close" class="cb-close-btn" title="Cerrar">&times;</button>
        </div>
    </div>

    <div id="cb-chat-body" class="cb-body custom-scrollbar">
        <!-- El contenido se inyecta dinámicamente según el data-role -->
    </div>
</div>

<link rel="stylesheet" href="<?= $assetPath ?>css/chatbot.css?v=<?= time() ?>">
<script src="<?= $assetPath ?>js/chatbot.js?v=<?= time() ?>"></script>
