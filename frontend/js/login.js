let showPass = false;

const showMessage = (elementInput, success, message) => {
  if (success) {
    elementInput.classList.add("success");
    elementInput.style.backgroundColor = "#090";
    elementInput.style.padding = "5px 0 5px 7px";
    elementInput.innerHTML =
      "<p> " + message + " <p><button class='exitMessage'>X</button>";
  } else {
    elementInput.classList.add("success");
    elementInput.style.backgroundColor = "#900";
    elementInput.style.padding = "5px 0 5px 7px";
    elementInput.innerHTML =
      "<p>" + message + "<p><button class='exitMessage'>X</button>";
  }
};
const showPassword = (input, showPass) => {
  showPass.addEventListener("change", function () {
    input.type = this.checked ? "text" : "password";
  });
};

// Importamos las funciones que necesitamos del módulo auth
import { iniciarSesion } from "./auth.js";

window.addEventListener("load", () => {
  const input = document.getElementById("password");
  const showPass = document.getElementById("showPass");
  const elementInput = document.getElementById("showMessage");

  const form = document.getElementById("submit");
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      // Limpiar mensajes previos
      elementInput.innerHTML = "";
      elementInput.classList.remove("success");

      // Mostrar mensaje de carga
      showMessage(elementInput, true, "Iniciando sesión...");

      // Obtener los valores de los campos
      const identifier = document.getElementById("email").value;
      const password = input.value;

      // Validar que se hayan ingresado los campos
      if (!identifier || !password) {
        showMessage(elementInput, false, "Todos los campos son obligatorios");
        return;
      }

      // Mostrar datos para depuración (solo en desarrollo)
      console.log("Intentando login con:", { identifier, password });

      // Usar la función importada para iniciar sesión
      console.log("Enviando petición a:", identifier, password);
      const result = await iniciarSesion(identifier, password);
      console.log("Resultado del login:", result);

      if (!result.success) {
        // Mensaje detallado para depuración (solo durante desarrollo)
        let mensajeError =
          result.message || "Credenciales inválidas. Verifica tus datos.";

        // Si hay un error de parsing, mostrar más detalles
        if (result.error === "parse_error") {
          mensajeError += " (Error de formato en la respuesta)";
          console.log("Texto de la respuesta:", result.responseText);
        }

        showMessage(elementInput, false, mensajeError);
        console.log("Error de servidor:", result);
        return;
      }

      // Login exitoso
      showMessage(elementInput, true, "Login exitoso! Redirigiendo...");
      console.log("Login exitoso:", result);

      // Los datos del usuario ya se guardaron en la función iniciarSesion

      // Redirigir a la página de menú después de 1 segundo
      setTimeout(() => {
        window.location.href = "menu-player.html";
      }, 1000);
    } catch (error) {
      // Mensaje genérico para cualquier error técnico
      console.error("Error al iniciar sesión:", error);
      showMessage(
        elementInput,
        false,
        "Error de conexión. Inténtalo nuevamente más tarde."
      );
    }
  });

  elementInput.addEventListener("click", (e) => {
    if (e.target.classList.contains("exitMessage")) {
      elementInput.innerHTML = "";
      elementInput.classList.remove("success");
      elementInput.style.backgroundColor = "";
    }
  });

  showPassword(input, showPass);
});
