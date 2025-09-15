"use strict";

import { obtenerIdUsuario, obtenerNombreUsuario, PAGES } from "./auth.js";

const API_BASE = "http://localhost:8000";

async function fetchJSON(url, options = {}) {
  const resp = await fetch(url, options);
  if (!resp.ok) {
    const text = await resp.text();
    throw new Error(`HTTP ${resp.status}: ${text}`);
  }
  return resp.json();
}

function setStatus(msg, type = "info") {
  const el = document.getElementById("status");
  if (!el) return;
  el.textContent = msg || "";
  el.className = `status ${type}`;
}

function renderOpponents(opponents) {
  const list = document.getElementById("opponents");
  const empty = document.getElementById("empty");
  if (!list || !empty) return;

  list.innerHTML = "";
  if (!opponents || opponents.length === 0) {
    empty.style.display = "block";
    return;
  }
  empty.style.display = "none";

  opponents.forEach((o) => {
    const card = document.createElement("div");
    card.className = "card";

    const name = document.createElement("div");
    name.className = "name";
    name.textContent = o.username || `Usuario #${o.user_id}`;

    const actions = document.createElement("div");
    actions.className = "actions";

    const btn = document.createElement("button");
    btn.className = "btn btn-primary";
    btn.textContent = "Jugar";
    btn.addEventListener("click", () => onStartGame(o));

    actions.appendChild(btn);
    card.appendChild(name);
    card.appendChild(actions);
    list.appendChild(card);
  });
}

async function loadOpponents() {
  try {
    const myId = obtenerIdUsuario();
    if (!myId) {
      setStatus("No hay sesión activa. Redirigiendo al login...", "error");
      setTimeout(() => (window.location.href = PAGES.login), 1000);
      return;
    }

    // Mostrar nombre del usuario en la barra
    const nameEl = document.getElementById("nombre-usuario");
    if (nameEl)
      nameEl.textContent = obtenerNombreUsuario() || `Usuario #${myId}`;

    setStatus("Cargando oponentes...", "info");
    const data = await fetchJSON(`${API_BASE}/api/user/opponents/${myId}`);
    if (!data || data.success === false) {
      setStatus(
        data?.message || "No se pudo cargar la lista de oponentes",
        "error"
      );
      return;
    }

    const opponents = Array.isArray(data.opponents) ? data.opponents : [];
    setStatus(
      opponents.length ? "" : "No hay oponentes disponibles",
      opponents.length ? "info" : "error"
    );
    renderOpponents(opponents);
  } catch (e) {
    console.error("[opponents] Error: ", e);
    setStatus("Error cargando oponentes. Intenta de nuevo más tarde.", "error");
  }
}

async function onStartGame(opponent) {
  try {
    const myId = obtenerIdUsuario();
    if (!myId) {
      setStatus("No hay sesión activa. Redirigiendo al login...", "error");
      setTimeout(() => (window.location.href = PAGES.login), 1000);
      return;
    }

    setStatus(
      `Creando partida con ${
        opponent.username || `Usuario #${opponent.user_id}`
      } ...`,
      "info"
    );

    const body = { player1_id: myId, player2_id: opponent.user_id };
    const res = await fetchJSON(`${API_BASE}/api/game/start`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });

    if (!res || res.success === false || !res.game_id) {
      setStatus(
        res?.error || res?.message || "No se pudo crear la partida",
        "error"
      );
      return;
    }

    // Guardar game_id para la vista de juego/seguir partida
    localStorage.setItem("currentGameId", String(res.game_id));

    // Redirigir a la pantalla de juego (preVolverAJugar para elegir asiento desde resume)
    window.location.href = PAGES.preVolverAJugar || "preVolverAJugar.html";
  } catch (e) {
    console.error("[start] Error: ", e);
    setStatus("Error al crear la partida. Intenta de nuevo.", "error");
  }
}

document.addEventListener("DOMContentLoaded", () => {
  // Dropdown simple
  const userButton = document.querySelector(".userButton");
  const dropdown = document.querySelector(".dropdown");
  if (userButton && dropdown) {
    userButton.addEventListener("click", function () {
      dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
    });
    document.addEventListener("click", function (event) {
      if (!event.target.closest(".userMenu")) {
        dropdown.style.display = "none";
      }
    });
  }

  const back = document.getElementById("btn-volver-menu");
  if (back)
    back.addEventListener("click", () => (window.location.href = PAGES.menu));

  loadOpponents();
});
