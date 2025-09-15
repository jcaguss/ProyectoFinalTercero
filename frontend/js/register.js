"use strict";

// Constante para el endpoint de registro
const REGISTER_API = "http://localhost:8000/api/auth/register";

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("form_registro");
  const mensajeError = document.getElementById("mensaje_error");
  const checkboxVer = document.getElementById("ver_password");
  const inputPassword = document.getElementById("password");
  const inputconfirm = document.getElementById("confirm");

  form.addEventListener("submit", async function (e) {
    e.preventDefault(); // Evita el envío del formulario por defecto

    //capturo los valores de los campos
    const nombre = document.getElementById("nombre").value.trim(); //el trim es para sacar los espacios que pueda dejar el usuario
    const email = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value; //este campo no lo necesita porque la contraseña puede tener espacios
    const confirm = document.getElementById("confirm").value;

    // Validaciones del lado del cliente
    if (!nombre || !email || !password || !confirm) {
      mostrarError("Todos los campos son obligatorios.");
      return;
    }

    if (nombre.length < 3) {
      mostrarError("El nombre de usuario debe tener al menos 3 caracteres.");
      return;
    }

    if (!validarEmail(email)) {
      mostrarError("Por favor ingresa un correo electrónico válido.");
      return;
    }

    if (password.length < 6) {
      mostrarError("La contraseña debe tener al menos 6 caracteres.");
      return;
    }

    if (password !== confirm) {
      mostrarError("Las contraseñas deben coincidir");
      return;
    }

    // Mostrar estado de carga
    mostrarMensaje("Enviando registro...", "orange");

    try {
      // Enviar datos al servidor
      const response = await fetch(REGISTER_API, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          username: nombre,
          email: email,
          password: password,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Registro exitoso
        mostrarMensaje("¡Registro exitoso! Redirigiendo al login...", "green");

        // Redirigir al login después de 2 segundos
        setTimeout(() => {
          window.location.href = "login.html";
        }, 2000);
      } else {
        // Error devuelto por el servidor
        // Guardamos el mensaje real para depuración
        console.log("Error del servidor:", data.message);

        // Para mayor seguridad, usamos mensajes específicos solo para códigos conocidos
        if (data.code === "duplicate" && data.message.includes("username")) {
          mostrarError(
            "Este nombre de usuario ya está registrado. Por favor elige otro."
          );
        } else if (
          data.code === "duplicate" &&
          data.message.includes("email")
        ) {
          mostrarError(
            "Este correo electrónico ya está registrado. ¿Olvidaste tu contraseña?"
          );
        } else if (data.code === "invalid") {
          mostrarError("Por favor verifica los datos proporcionados.");
        } else {
          // Para cualquier otro error, mostramos un mensaje genérico
          mostrarError(
            "No se pudo completar el registro. Por favor inténtalo más tarde."
          );
        }
      }
    } catch (error) {
      // Registrar el error real para depuración
      console.error("Error al registrar:", error);

      // Mensaje genérico para el usuario
      mostrarError(
        "No pudimos procesar tu solicitud en este momento. Por favor, inténtalo de nuevo más tarde."
      );
    }

    function mostrarError(mensaje) {
      mensajeError.textContent = mensaje;
      mensajeError.style.color = "red";
    }

    function mostrarMensaje(mensaje, color) {
      mensajeError.textContent = mensaje;
      mensajeError.style.color = color;
    }

    function validarEmail(email) {
      const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return re.test(email);
    }
  });

  // Evento para mostrar/ocultar contraseñas
  checkboxVer.addEventListener("change", function () {
    if (checkboxVer.checked) {
      inputPassword.type = "text"; //muestra la contraseña
      inputconfirm.type = "text"; //muestra la contraseña
    } else {
      inputPassword.type = "password"; //oculta la contraseña
      inputconfirm.type = "password"; //oculta la contraseña
    }
  });

  // Eventos para limpiar mensajes de error cuando el usuario comienza a escribir
  const inputs = form.querySelectorAll("input");
  inputs.forEach((input) => {
    input.addEventListener("input", function () {
      if (mensajeError.textContent !== "") {
        mensajeError.textContent = "";
      }
    });
  });
});
