window.addEventListener("load", () => {
  const dinoImgs = document.querySelectorAll(".dino img");
  const recintos = document.querySelectorAll(".rec");
  let dinoSeleccionado = null;

  dinoImgs.forEach((img) => {
    img.addEventListener("click", () => {
      if (img.style.opacity === "0.5") {
        img.style.opacity = "1";
        recintos.forEach((rec) => rec.classList.remove("rec--parpadeo"));
        dinoSeleccionado = null;
      } else {
        dinoImgs.forEach((i) => (i.style.opacity = "1"));
        img.style.opacity = "0.5";
        recintos.forEach((rec) => rec.classList.add("rec--parpadeo"));
        dinoSeleccionado = img;
      }
    });
  });
});
