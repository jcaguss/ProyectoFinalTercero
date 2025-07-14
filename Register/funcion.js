'use strict'


document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("form_registro");
  const mensajeError = document.getElementById("mensaje_error");
  const checkboxVer = document.getElementById("ver_password");
  const inputPassword = document.getElementById("password");
  const inputconfirm = document.getElementById("confirm");

    form.addEventListener("submit", function(e){
        e.preventDefault(); // Evita el envío del formulario por defecto

        //capturo los valores de los campos

        const nombre=document.getElementById("nombre").value.trim(); //el trim es para sacar los espacios que pueda dejar el usuario

        const email=document.getElementById("email").value.trim();

        const password=document.getElementById("password").value;  //este campo no lo necesita porque la contraseña puede tener espacios

        const confirm=document.getElementById("confirm").value;

        if( !nombre || !email || !password || !confirm){
            mostrarError("Todos los campos son obligatorios.");
            return;
        }

        if(password.length < 6){
            mostrarError("La contraseña debe tener al menos 6 caracteres.");
            return;
        }

        if(password !== confirm){
            mostrarError("Las contraseñas deben coincidir");
            return;
        }

        mensajeError.textContent="";
        mensajeError.classList.remove("error");
        mensajeError.style.color="green";
        mensajeError.textContent="¡Registro exitoso!"

         function mostrarError(mensaje) {
            mensajeError.textContent = mensaje;
            mensajeError.style.color = "red";
        }

    })


   checkboxVer.addEventListener("change", function() {
    if(checkboxVer.checked) {
        inputPassword.type = "text"; //muestra la contraseña
        inputconfirm.type = "text"; //muestra la contraseña
    }
    else{
        inputPassword.type = "password"; //oculta la contraseña
        inputconfirm.type = "password"; //oculta la contraseña
    }
   });

   


});