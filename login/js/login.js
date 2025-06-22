let showPass = false;

const showErrors = (id, idMensaje) => {
  const elementNombre = document.getElementById(id);
  const elementError = document.getElementById(idMensaje);

  if (!elementNombre?.value) {
    elementError.style.cssText =
      "position: relative; display: flex; color: #F00;";
    elementNombre.classList.add("error");
    elementError.textContent =
      elementNombre.id === "email"
        ? "Ingrese su email"
        : "Ingrese su contraseña";
  } else {
    elementError.style.visibility = "hidden";
    elementNombre.classList.remove("error");
  }
};
const showPassword = (input, showPass) => {
  showPass.addEventListener("change", function () {
    input.type = this.checked ? "text" : "password";
  });
};

window.addEventListener("load", () => {
  const input = document.getElementById("password");
  const showPass = document.getElementById("showPass");
  showPassword(input, showPass);
});
