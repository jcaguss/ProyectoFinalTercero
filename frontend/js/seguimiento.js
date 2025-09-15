"use strict";

let puntos = 0;

const recintos = {
  bosque___semejanza: { dinos: [], max: 6 },
  parado__diferencia: { dinos: [], max: 6 },
  pradera__del__amor: { dinos: [], max: 6 },
  trio__frondoso: { dinos: [], max: 3 },
  rey__selva: { dinos: [], max: 1 },
  isla__solitaria: { dinos: [], max: 1 },
  rio: { dinos: [], max: 6 },
};

const imgsDinosColocados = {
  naranja: "./img/naranjaVerti.PNG",
  amarillo: "./img/amarilloVerti.PNG",
  rosa: "./img/rosaVerti.PNG",
  rojo: "./img/rojoVerti.PNG",
  azul: "./img/azulVerti.PNG",
  verde: "./img/verdeVerti.PNG",
};

function contarPorColor(arr) {
  return arr.reduce((acc, color) => {
    acc[color] = (acc[color] || 0) + 1;
    return acc;
  }, {});
}

function dinosEnRecintos(color) {
  return Object.values(recintos).reduce(
    (acc, arr) => acc + arr.dinos.filter((c) => c === color).length,
    0
  );
}

function totalDinosColocados() {
  return Object.values(recintos).reduce(
    (acc, arr) => acc + arr.dinos.length,
    0
  );
}

function actualizarPuntos(nuevosPuntos) {
  puntos = nuevosPuntos;
  document.getElementById(
    "puntos__user"
  ).innerHTML = `<p>Puntos: ${puntos}p</p>`;
}

function renderizarRecintos() {
  Object.keys(recintos).forEach((nombre) => {
    const contenedorDinos = document.getElementById(nombre);
    contenedorDinos.innerHTML = "";
    recintos[nombre].dinos.forEach((color) => {
      const dinoDiv = document.createElement("div");
      dinoDiv.classList.add("dinosaurio__recinto");
      dinoDiv.style.backgroundImage = `url('${imgsDinosColocados[color]}')`;
      dinoDiv.setAttribute("data-color", color);
      contenedorDinos.appendChild(dinoDiv);
    });
  });
}

// Funciones de puntuacion por recinto
function puntosBosqueSemejanza(dinos) {
  const tabla = [0, 2, 4, 8, 12, 18, 24];
  return tabla[dinos.length] || 0;
}

function puntosParadoDiferencia(dinos) {
  const tabla = [0, 1, 3, 6, 10, 15, 21];
  return tabla[dinos.length] || 0;
}

function puntosPraderaDelAmor(dinos) {
  const contador = contarPorColor(dinos);
  return Object.values(contador).reduce(
    (acc, cantidad) => acc + Math.floor(cantidad / 2) * 5,
    0
  );
}

function puntosTrioFrondoso(dinos) {
  return dinos.length === 3 ? 7 : 0;
}

function puntosRio(dinos) {
  return dinos.length;
}

function puntosIslaSolitaria(dinos) {
  if (dinos.length === 1) {
    const color = dinos[0];
    let cantidadColor = 0;
    Object.keys(recintos).forEach((recinto) => {
      if (recinto === "isla__solitaria") return;
      cantidadColor += recintos[recinto].dinos.filter(
        (c) => c === color
      ).length;
    });
    if (cantidadColor === 0) {
      return 7;
    }
  }
  return 0;
}

// Reglas de validación por recinto
const reglasRecintos = {
  bosque___semejanza: ({ dinos, max }, color) =>
    dinos.length < max &&
    (dinos.length === 0 || dinos.every((c) => c === color)),
  parado__diferencia: ({ dinos, max }, color) =>
    dinos.length < max && !dinos.includes(color),
  pradera__del__amor: ({ dinos, max }) => dinos.length < max,
  trio__frondoso: ({ dinos, max }) => dinos.length < max,
  rey__selva: ({ dinos, max }) => dinos.length < max,
  rio: ({ dinos, max }) => dinos.length < max,
  isla__solitaria: ({ dinos, max }, color) => {
    if (dinos.length >= max) return false;
    const existeEnOtroRecinto = Object.keys(recintos).some((recinto) => {
      if (recinto === "isla__solitaria") return false;
      return recintos[recinto].dinos.includes(color);
    });
    return !existeEnOtroRecinto;
  },
};

function puedeColocarDino(nombreRecinto, color) {
  const recinto = recintos[nombreRecinto];
  const regla = reglasRecintos[nombreRecinto];
  if (typeof regla === "function") {
    return regla(recinto, color);
  }
  return true;
}

function calcularPuntos() {
  let total = 0;
  Object.keys(recintos).forEach((nombre) => {
    const dinos = recintos[nombre].dinos;
    switch (nombre) {
      case "bosque___semejanza":
        total += puntosBosqueSemejanza(dinos);
        break;
      case "parado__diferencia":
        total += puntosParadoDiferencia(dinos);
        break;
      case "pradera__del__amor":
        total += puntosPraderaDelAmor(dinos);
        break;
      case "trio__frondoso":
        total += puntosTrioFrondoso(dinos);
        break;
      case "rio":
        total += puntosRio(dinos);
        break;
      case "isla__solitaria":
        total += puntosIslaSolitaria(dinos);
        break;
      default:
        break;
    }
  });
  total += dinosEnRecintos("rojo");
  return total;
}

function manejarSeleccionDino(img, dinoImgs, recintos, setDinoSeleccionado) {
  const seleccionado = img.style.opacity === "0.5";
  dinoImgs.forEach((i) => (i.style.opacity = "1"));
  recintos.forEach((rec) =>
    rec.classList.toggle("rec--parpadeo", !seleccionado)
  );
  img.style.opacity = seleccionado ? "1" : "0.5";
  setDinoSeleccionado(seleccionado ? null : img);
}

// Drag & Drop

function agregarEventosDragDrop(dinosASeleccionar, recintosElementos) {
  dinosASeleccionar.forEach((img) => {
    img.setAttribute("draggable", "true");
    img.addEventListener("dragstart", (e) => {
      e.dataTransfer.setData("color", img.dataset.color);
      img.style.opacity = "0.5";
    });
    img.addEventListener("dragend", () => {
      img.style.opacity = "1";
    });
  });

  recintosElementos.forEach((recinto) => {
    recinto.addEventListener("dragover", (e) => {
      e.preventDefault();
      recinto.classList.add("rec--parpadeo");
    });
    recinto.addEventListener("dragleave", () => {
      recinto.classList.remove("rec--parpadeo");
    });
    recinto.addEventListener("drop", (e) => {
      e.preventDefault();
      recinto.classList.remove("rec--parpadeo");
      const color = e.dataTransfer.getData("color");
      if (!color) return;
      if (totalDinosColocados() >= 12) {
        alert("No puedes colocar mas de 12 dinosaurios en total.");
        return;
      }
      // Buscar el primer hijo con id dentro del recinto (el contenedor de dinos)
      const contenedor = Array.from(recinto.children).find(
        (child) => child.id && recintos.hasOwnProperty(child.id)
      );
      if (!contenedor) return;
      const nombreRecinto = contenedor.id;
      if (!puedeColocarDino(nombreRecinto, color)) {
        alert("No puedes colocar mas dinos en este recinto.");
        return;
      }
      recintos[nombreRecinto].dinos.push(color);
      renderizarRecintos();
      actualizarPuntos(calcularPuntos());
    });
  });
}

// Inicialización
function inicializar() {
  const dinosASeleccionar = document.querySelectorAll(".dino img");
  const recintosElementos = document.querySelectorAll(".rec");
  actualizarPuntos(0);
  renderizarRecintos();
  agregarEventosDragDrop(dinosASeleccionar, recintosElementos);
}
function totalDinosColocados() {
  return Object.values(recintos).reduce((acc, arr) => acc + arr.length, 0);
}

window.addEventListener("load", (e) => {
  e.preventDefault();
  inicializar();
});
