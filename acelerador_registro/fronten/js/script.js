

$(document).ready(function () {

    const email = document.getElementById('exampleInputEmail1');
    const contraseña1 = document.getElementById('exampleInputPassword1');
    const boton2 = document.querySelector('button[name="btn"]');


    function validarFormulario() {

        if (email.value.trim() !== "" && contraseña1.value.trim() !== "") {
            boton2.disabled = false;
        } else {
            boton2.disabled = true;
        }
    }

    if (email && contraseña1 && boton2) {
        email.addEventListener('input', validarFormulario);
        contraseña1.addEventListener('input', validarFormulario);
    }
});