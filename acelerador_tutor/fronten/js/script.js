

$(document).ready(function () {

    const email = document.getElementById('exampleInputEmail1');
    const contraseña1 = document.getElementById('exampleInputPassword1');
    const boton2 = document.getElementById('boton');


    function validarFormulario() {

        if (email.value.trim() !== "" && contraseña1.value.trim() !== "") {
            boton2.disabled = false;
        } else {
            boton2.disabled = true;
        }
    }

    if (email && contraseña1) {
        email.addEventListener('input', validarFormulario);
        contraseña1.addEventListener('input', validarFormulario);
    }
});

document.addEventListener("DOMContentLoaded", () => {
    const buscador = document.getElementById("inputBusqueda");
    const desplegable = document.getElementById("campoBusqueda");
    const contenedor = document.getElementById("contenedorTarjetas");

    if (buscador && desplegable) {
        buscador.addEventListener("keyup", () => {
            const termino = buscador.value;
            const campo = desplegable.value;

            // Solo buscamos si hay al menos 1 carácter o si borra todo
            fetch(`buscar_profesores.php?termino=${termino}&campo=${campo}`)
                .then(response => response.text())
                .then(data => {
                    // Reemplazamos el contenido del contenedor con lo que diga PHP
                    contenedor.innerHTML = data;
                })
                .catch(error => console.error("Error:", error));
        });
    }
});


$(document).ready(function () {
    // Escondemos el segundo de inicio
    $('.formulario2').hide();

    // Cuando pulses cualquier botón que tenga la clase .btn-cambio
    $('.btn').click(function (e) {
        e.preventDefault(); // Que no se me escape el submit

        // El toggle hace la magia: el que se ve se va, y el que no se ve viene
        $('.formulario, .formulario2').toggle('fast'); 
    });
});
/*
<div class="formulario">
      
      <!-- Aquí va el buscador -->
      <div class="buscador-container">
        <!-- Menú desplegable para elegir el campo -->
        <select id="campoBusqueda">
          <option value="nombre">Nombre</option>
          <option value="DNI">DNI</option>
          <option value="correo">Correo</option>
          <option value="facultad">Facultad</option>
        </select>

        <!-- Input de texto -->
        <input type="text" id="inputBusqueda" placeholder="Escribe para buscar...">
      </div>

      <!-- Contenedor donde se mostrarán los resultados -->
      <div id="contenedorTarjetas" class="row">
        <!-- Aquí PHP inyectará las tarjetas -->
      </div>
*/