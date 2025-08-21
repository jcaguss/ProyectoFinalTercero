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

window.addEventListener("load", () => {
  const input = document.getElementById("password");
  const showPass = document.getElementById("showPass");
  const elementInput = document.getElementById("showMessage");

  const form = document.getElementById("submit");
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    try {
      const response = await fetch("http://localhost:8080/api", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: document.getElementById("username").value,
          password: input.value,
        }),
      });
      if (!response.ok) {
        showMessage(elementInput, false, "Error al enviar el formulario");
        return;
      }
      showMessage(elementInput, true, "Login exitoso");
    } catch (error) {
      showMessage(elementInput, false, "Error al enviar el formulario");
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
