/**
 * login.js - Lógica de validación personalizada para el formulario de acceso
 */

document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.querySelector("form");
    const termsCheck = document.getElementById("exampleCheck1");
    const valPopup = document.getElementById("custom-val-popup");
    const loginBtn = document.getElementById("boton");

    // Interceptar clic en el botón de ingresar o envío del formulario
    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            if (!termsCheck.checked) {
                // Prevenir envío
                e.preventDefault();
                // Mostrar notificación personalizada
                valPopup.style.display = "block";
                
                // Efecto de vibración suave en el checkbox para llamar la atención
                const checkContainer = document.getElementById("check");
                checkContainer.classList.add("shake-horizontal");
                setTimeout(() => {
                    checkContainer.classList.remove("shake-horizontal");
                }, 500);
            }
        });
    }

    // Desaparición inteligente: ocultar en cuanto se marque la casilla
    if (termsCheck) {
        termsCheck.addEventListener("change", function () {
            if (this.checked) {
                valPopup.style.display = "none";
            }
        });
    }
});
