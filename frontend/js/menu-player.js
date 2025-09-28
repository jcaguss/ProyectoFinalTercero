/**
 * menu-player.js
 * Menú principal del jugador tras autenticarse.
 * Funciones:
 *  - Mostrar nombre de usuario
 *  - Navegar a creación de partida (seleccionar oponente)
 *  - Navegar a partidas pendientes (preVolverAJugar)
 *  - Acceso a simulador / seguimiento
 *  - Logout
 *
 * Dependencias:
 *  - auth.js (obtenerNombreUsuario, cerrarSesion, PAGES)
 *
 * NOTA: Hay dos listeners distintos para #btn-continuar; el último redirige a PAGES.seguimiento.
 *       Elimina uno si no es lo deseado.
 */

"use strict";

import { obtenerNombreUsuario, cerrarSesion, PAGES } from "./auth.js";

/**
 * Punto de entrada: inicializa al cargar DOM.
 */
document.addEventListener("DOMContentLoaded", function () {
  mostrarNombreUsuario();
  configurarBotones();
  mostrarMensajesURL();
});

/**
 * Inserta el nombre del usuario logueado en el span correspondiente.
 * Fallback: 'Jugador'.
 * @returns {void}
 */
function mostrarNombreUsuario() {
  const nombreUsuarioElement = document.getElementById("nombre-usuario");
  if (!nombreUsuarioElement) return;
  const username = obtenerNombreUsuario();
  nombreUsuarioElement.textContent = username || "Jugador";
}

/**
 * Configura todos los botones del menú (event listeners).
 * Incluye:
 *  - Dropdown user
 *  - Logout
 *  - Nuevo juego (seleccionar oponente)
 *  - Reanudar / continuar (preVolverAJugar)
 *  - Manual
 *  - Botón jugar (alias de nuevo)
 *  - Botón continuar (SEGUNDO listener lo envía a seguimiento)
 * @returns {void}
 */
function configurarBotones() {
  // Dropdown usuario
  const userButton = document.querySelector(".userButton");
  const dropdown = document.querySelector(".dropdown");
  if (userButton && dropdown) {
    userButton.addEventListener("click", () => {
      dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
    });
    document.addEventListener("click", (event) => {
      if (!event.target.closest(".userMenu")) dropdown.style.display = "none";
    });
  }

  // Logout
  const btnLogout = document.getElementById("btn-logout");
  if (btnLogout) {
    btnLogout.addEventListener("click", (e) => {
      e.preventDefault();
      cerrarSesion(); // auth.js debe manejar la redirección final
    });
  }

  // Nuevo juego → seleccionar oponente
  const btnNuevo = document.getElementById("btn-nuevo-juego");
  if (btnNuevo) {
    btnNuevo.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href =
        PAGES.seleccionarOponente || "seleccionar-oponente.html";
    });
  }

  // Volver a jugar (alias reanudar)
  const btnVolverJugar = document.getElementById("btn-volver-jugar");
  if (btnVolverJugar) {
    btnVolverJugar.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = PAGES.preVolverAJugar;
    });
  }

  // Continuar juegos (primer listener)
  const btnContinuar = document.getElementById("btn-continuar");
  if (btnContinuar) {
    btnContinuar.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = PAGES.preVolverAJugar || "preVolverAJugar.html";
    });
  }

  // Manual / reglas
  const btnManual = document.getElementById("btn-manual");
  if (btnManual) {
    btnManual.addEventListener("click", (e) => {
      e.preventDefault();
      window.location.href = "manual.html";
    });
  }

  // Botón jugar (otro acceso a selección de oponente)
  document.getElementById("btn-jugar")?.addEventListener("click", () => {
    window.location.href = "seleccionar-oponente.html";
  });

  // Continuar (SEGUNDO listener: sobrescribe intención previa → seguimiento)
  document.getElementById("btn-continuar")?.addEventListener("click", () => {
    window.location.href = PAGES.seguimiento;
  });
}

/**
 * Lee parámetros de la URL y muestra un mensaje temporal si existe 'mensaje'.
 * @returns {void}
 */
function mostrarMensajesURL() {
  const params = new URLSearchParams(window.location.search);
  const mensaje = params.get("mensaje");
  if (!mensaje) return;

  const mensajeElement = document.getElementById("mensaje-sistema");
  if (mensajeElement) {
    mensajeElement.textContent = mensaje;
    mensajeElement.style.display = "block";
    setTimeout(() => (mensajeElement.style.display = "none"), 5000);
  } else {
    alert(mensaje);
  }
}
