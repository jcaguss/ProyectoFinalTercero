/**
 * Menú principal del jugador
 */
"use strict";

import { obtenerNombreUsuario, cerrarSesion, PAGES } from "./auth.js";

document.addEventListener("DOMContentLoaded", function () {
  // Mostrar nombre de usuario desde localStorage
  const nombreUsuarioElement = document.getElementById("nombre-usuario");
  if (nombreUsuarioElement) {
    const username = obtenerNombreUsuario();
    nombreUsuarioElement.textContent = username || "Jugador";
  }

  // Configurar eventos de los botones
  configurarBotones();

  // Mostrar mensajes en la URL si los hay
  mostrarMensajesURL();
});

// Eliminada la función mostrarDatosUsuario ya que no se necesita para la versión simplificada

/**
 * Configura todos los botones del men�
 */
function configurarBotones() {
  // Configurar dropdown del usuario
  const userButton = document.querySelector(".userButton");
  const dropdown = document.querySelector(".dropdown");

  if (userButton && dropdown) {
    userButton.addEventListener("click", function () {
      dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
    });

    // Cerrar dropdown al hacer clic fuera
    document.addEventListener("click", function (event) {
      if (!event.target.closest(".userMenu")) {
        dropdown.style.display = "none";
      }
    });
  }

  // Botón para cerrar sesión
  const btnLogout = document.getElementById("btn-logout");
  if (btnLogout) {
    btnLogout.addEventListener("click", function (e) {
      e.preventDefault();
      // Cerrar sesión y redirigir al login
      cerrarSesion();
    });
  }

  // Bot�n para juego nuevo
  const btnNuevo = document.getElementById("btn-nuevo-juego");
  if (btnNuevo) {
    btnNuevo.addEventListener("click", function (e) {
      e.preventDefault();
      // Redirigir a la selección de oponente
      window.location.href =
        PAGES.seleccionarOponente || "seleccionar-oponente.html";
    });
  }

  // Botón para volver a jugar
  const btnVolverJugar = document.getElementById("btn-volver-jugar");
  if (btnVolverJugar) {
    btnVolverJugar.addEventListener("click", function (e) {
      e.preventDefault();
      window.location.href = PAGES.preVolverAJugar;
    });
  }

  // Bot�n para continuar juegos
  const btnContinuar = document.getElementById("btn-continuar");
  if (btnContinuar) {
    btnContinuar.addEventListener("click", function (e) {
      e.preventDefault();
      window.location.href = "seguimiento.html";
    });
  }

  // Bot�n para ver manual
  const btnManual = document.getElementById("btn-manual");
  if (btnManual) {
    btnManual.addEventListener("click", function (e) {
      e.preventDefault();
      window.location.href = "manual.html";
    });
  }
}

/**
 * Muestra mensajes pasados por URL
 */
function mostrarMensajesURL() {
  const params = new URLSearchParams(window.location.search);
  const mensaje = params.get("mensaje");

  if (mensaje) {
    // Buscar elemento para mostrar mensajes
    const mensajeElement = document.getElementById("mensaje-sistema");
    if (mensajeElement) {
      mensajeElement.textContent = mensaje;
      mensajeElement.style.display = "block";

      // Ocultar despu�s de unos segundos
      setTimeout(() => {
        mensajeElement.style.display = "none";
      }, 5000);
    } else {
      // Si no hay elemento para mostrar, usar alert
      alert(mensaje);
    }
  }
}
