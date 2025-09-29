import { obtenerIdUsuario, PAGES } from "./auth.js";
/**
 * Archivo: logicaDeJuego.js
 * Responsable de controlar el flujo del juego en curso:
 *  - Carga de estado (bolsa, recintos, puntajes, turno)
 *  - Drag & Drop para colocar dinosaurios
 *  - Actualización de encabezados y paneles de jugadores
 *  - Detección de fin de partida
 *
 * NOTA: Este archivo asume que el backend valida reglas de recintos y puntajes.
 */

/**
 * @typedef {Object} DinoBagItem
 * @property {number} id              ID interno (a veces igual a bag_content_id)
 * @property {number} [bag_content_id] ID contenido bolsa
 * @property {number} [species_id]
 * @property {string} dinosaur_type   Color / tipo (ej: 'rojo', 'verde')
 * @property {string} [orientation]   'horizontal'|'vertical'
 */

/**
 * @typedef {Object} EnclosureDino
 * @property {number} id
 * @property {number} [dino_id]
 * @property {string} dinosaur_type
 * @property {string} orientation
 * @property {number} enclosure_id
 */

/**
 * @typedef {Object} Scores
 * @property {number} player1 Puntaje jugador asiento 0
 * @property {number} player2 Puntaje jugador asiento 1
 */

// --- Config ---
const API_BASE = "http://localhost:8000";
const MAX_ROUNDS = 2;
const TURNS_PER_ROUND = 12;

// Mapeo de contenedores de recintos -> enclosure_id (1..7)
const ENCLOSURE_CONTAINER_TO_ID = {
  dinos__igual: 1,
  dinos__noigual: 2,
  dinos__pareja: 3,
  dinos__tres: 4,
  dinos__rey: 5,
  dinos__solo: 6,
  dinos__rio: 7,
};

// Inverso: enclosure_id -> id de contenedor interno dinos__*
const ENCLOSURE_ID_TO_CONTAINER = Object.fromEntries(
  Object.entries(ENCLOSURE_CONTAINER_TO_ID).map(([k, v]) => [v, k])
);

// Estado mínimo en front
const state = {
  gameId: null,
  playerSeat: 0, // 0 o 1 (se determina con resume)
  enclosuresMax: {}, // { enclosureId: max_dinos }
  isPlacing: false,
  activeSeat: null,
  playerNames: { 0: null, 1: null },
};

/**
 * Crea (si no existe) y retorna el overlay de carga.
 * @returns {HTMLDivElement} Overlay
 */
function createLoadingOverlay() {
  let overlay = document.getElementById("loading-overlay");
  if (overlay) return overlay;
  overlay = document.createElement("div");
  overlay.id = "loading-overlay";
  overlay.style.cssText = `
    position: fixed; inset: 0; background: rgba(0, 0, 0, 0.34);
    display: none; align-items: center; justify-content: center; z-index: 9999; color: #fff; font-size: 20px;
  `;
  const box = document.createElement("div");
  box.style.cssText =
    "background: rgba(0,0,0,0.7); padding: 20px 28px; border-radius: 8px;";
  box.id = "loading-overlay-text";
  box.textContent = "Cargando...";
  overlay.appendChild(box);
  document.body.appendChild(overlay);
  return overlay;
}

/**
 * Muestra el overlay de carga con un mensaje y lo oculta automáticamente
 * después de 'ms' milisegundos (no lo oculta si quien llama no hace hide).
 * @param {string} [msg="Cargando..."] Mensaje a mostrar
 * @param {number} [ms=500] Delay antes de resolver la promesa
 * @returns {Promise<void>}
 */
function showLoading(msg = "Cargando...", ms = 500) {
  const overlay = createLoadingOverlay();
  const box = document.getElementById("loading-overlay-text");
  if (box) box.textContent = msg;
  overlay.style.display = "flex";
  return new Promise((res) => setTimeout(() => res(), ms));
}

/**
 * Oculta el overlay de carga si está visible.
 * @returns {void}
 */
function hideLoading() {
  const overlay = document.getElementById("loading-overlay");
  if (overlay) overlay.style.display = "none";
}

/**
 * Realiza un fetch y parsea JSON lanzando error con cuerpo si status !ok.
 * @param {string} url
 * @param {RequestInit} [options]
 * @throws {Error} con el status y cuerpo al fallar
 * @returns {Promise<any>}
 */
async function fetchJSON(url, options = {}) {
  const resp = await fetch(url, options);
  if (!resp.ok) {
    const text = await resp.text();
    throw new Error(`HTTP ${resp.status}: ${text}`);
  }
  return resp.json();
}

/**
 * Construye la ruta de la imagen horizontal según color.
 * @param {string} color
 * @returns {string}
 */
function imageForHorizontal(color) {
  return `./img/${color}Hori.PNG`;
}

/**
 * Construye la ruta de la imagen vertical según color.
 * @param {string} color
 * @returns {string}
 */
function imageForVertical(color) {
  return `./img/${color}Verti.PNG`;
}

// --- Header (jugador activo) ---
/**
 * Carga el estado del juego (game/state) y actualiza:
 *  - Nombre de jugador activo
 *  - Ronda y turno actuales
 *  - Nombres base de los paneles (sin ordenar por líder)
 * Usa ensurePlayerNames para cachear nombres reales.
 * @returns {Promise<void>}
 */
async function loadAndRenderHeader() {
  try {
    // Asegurar que tengamos nombres cacheados
    await ensurePlayerNames();
    const data = await fetchJSON(`${API_BASE}/api/game/state/${state.gameId}`);
    const gs = data && data.success ? data.game_state : null;
    if (!gs) {
      console.warn("[header] No se pudo obtener game_state para header:", data);
      return;
    }
    // Compatibilidad de nombres de campos
    const activeSeat =
      typeof gs.active_seat === "number"
        ? gs.active_seat
        : typeof gs.activeSeat === "number"
        ? gs.activeSeat
        : null;
    state.activeSeat = activeSeat;

    const playerLabel = document.getElementById("player");
    if (playerLabel) {
      const name =
        activeSeat === 0
          ? state.playerNames[0] || "Jugador 1"
          : state.playerNames[1] || "Jugador 2";
      playerLabel.textContent = name || "Jugador";
    }
    // Actualizar ronda y turno
    const rondaEl = document.getElementById("ronda");
    if (rondaEl) {
      const round =
        typeof gs.current_round === "number"
          ? gs.current_round
          : gs.currentRound || 1;
      rondaEl.textContent = `Ronda ${round}/${MAX_ROUNDS}`;
    }
    const turnEl = document.getElementById("colocados");
    if (turnEl) {
      const turn =
        typeof gs.current_turn === "number"
          ? gs.current_turn
          : gs.currentTurn || 1;
      turnEl.textContent = `Turno ${turn}/${TURNS_PER_ROUND}`;
    }
    // Inicialmente mostrar en orden por asiento; luego loadAndRenderScores decidirá el orden por líder
    const p1El = document.getElementById("player1-name");
    if (p1El) p1El.textContent = state.playerNames[0] || "Jugador 1";
    const p2El = document.getElementById("player2-name");
    if (p2El) p2El.textContent = state.playerNames[1] || "Jugador 2";
  } catch (e) {
    console.warn("[header] Error cargando header:", e.message);
  }
}

// --- Carga inicial ---
/**
 * Determina el seat (0/1) del usuario logueado usando /game/resume.
 * @param {number} gameId
 * @returns {Promise<number>} Seat (0 o 1) o 0 por fallback
 */
async function determineSeat(gameId) {
  const userId = obtenerIdUsuario();
  try {
    const data = await fetchJSON(
      `${API_BASE}/api/game/resume/${gameId}?user_id=${userId}`
    );
    console.log("[resume] response", data);
    // Esperamos playerSeat en respuesta
    if (
      data &&
      data.success &&
      data.game_state &&
      typeof data.game_state.playerSeat === "number"
    ) {
      console.log("[resume] playerSeat:", data.game_state.playerSeat);
      // Cachear nombres de jugadores si están disponibles
      const n1 =
        data.game_state.player1_username || data.game_state.player1_name;
      const n2 =
        data.game_state.player2_username || data.game_state.player2_name;
      if (n1 || n2) {
        state.playerNames[0] = n1 || state.playerNames[0];
        state.playerNames[1] = n2 || state.playerNames[1];
      }
      return data.game_state.playerSeat;
    }
    console.warn("[resume] No playerSeat in response; using default 0");
  } catch (e) {
    console.warn(
      "[resume] No se pudo determinar seat desde resume:",
      e.message
    );
  }
  // Fallback: seat 0
  return 0;
}

/**
 * Garantiza que los nombres de los jugadores estén cacheados.
 * No sobrescribe nombres reales con placeholders vacíos.
 * @returns {Promise<void>}
 */
async function ensurePlayerNames() {
  // Si ya están cacheados (nombres reales no vacíos), no hacer nada
  const have0 = !!(state.playerNames[0] && String(state.playerNames[0]).trim());
  const have1 = !!(state.playerNames[1] && String(state.playerNames[1]).trim());
  if (have0 && have1) return;
  try {
    const data = await fetchJSON(`${API_BASE}/api/game/state/${state.gameId}`);
    const gs = data && data.success ? data.game_state : null;
    if (gs) {
      if (!have0) {
        const n0 = gs.player1_username || gs.player1_name;
        if (n0) state.playerNames[0] = n0;
      }
      if (!have1) {
        const n1 = gs.player2_username || gs.player2_name;
        if (n1) state.playerNames[1] = n1;
      }
    }
  } catch (e) {
    // Ignorar; no cachear placeholders
  }
}

/**
 * Carga metadatos de recintos (capacidades) para un asiento.
 * Si falla, asigna valores por defecto.
 * @param {number} gameId
 * @param {number} playerSeat
 * @returns {Promise<void>}
 */
async function loadEnclosuresMeta(gameId, playerSeat) {
  try {
    const meta = await fetchJSON(
      `${API_BASE}/api/game/enclosures/${gameId}/${playerSeat}`
    );
    // meta.enclosures: [{ enclosures_id, max_dinos, ... }]
    const map = {};
    if (meta && meta.success && Array.isArray(meta.enclosures)) {
      meta.enclosures.forEach((e) => {
        const id = parseInt(e.enclosures_id, 10);
        if (!isNaN(id)) map[id] = parseInt(e.max_dinos ?? 6, 10);
      });
      console.log("[enclosures meta] loaded:", map);
    } else {
      console.warn("[enclosures meta] Invalid response shape:", meta);
    }
    state.enclosuresMax = map;
  } catch (e) {
    console.warn(
      "[enclosures meta] No se pudo cargar meta de recintos, usando valores por defecto.",
      e.message
    );
    state.enclosuresMax = { 1: 6, 2: 6, 3: 6, 4: 3, 5: 1, 6: 1, 7: 6 };
  }
}

/**
 * Renderiza la bolsa del jugador (drag start en cada dino).
 * @param {number} gameId
 * @param {number} playerSeat
 * @returns {Promise<void>}
 */
async function renderBag(gameId, playerSeat) {
  const cont = document.getElementById("player-bag");
  cont.innerHTML = "";
  try {
    const data = await fetchJSON(
      `${API_BASE}/api/game/bag/${gameId}/${playerSeat}`
    );
    if (!data || data.success === false) {
      console.warn("[bag] No se pudo cargar la bolsa:", data);
    }
    const bag = Array.isArray(data.bag) ? data.bag : [];
    console.log(`[bag] dinos=${bag.length}`, bag);
    bag.forEach((dino) => {
      const wrapper = document.createElement("div");
      wrapper.className = "dino";

      const img = document.createElement("img");
      img.draggable = true;
      img.src = imageForHorizontal(dino.dinosaur_type || "amarillo");
      img.alt = dino.dinosaur_type || "dino";
      img.dataset.dinoId = dino.id || dino.bag_content_id;
      img.dataset.color = dino.dinosaur_type || "amarillo";
      img.addEventListener("dragstart", (e) => {
        try {
          e.dataTransfer.effectAllowed = "move";
        } catch {}
        e.dataTransfer.setData("dinoId", String(img.dataset.dinoId));
        e.dataTransfer.setData("color", String(img.dataset.color));
        console.log(
          "[dragstart] dinoId=",
          img.dataset.dinoId,
          "color=",
          img.dataset.color
        );
      });

      wrapper.appendChild(img);
      cont.appendChild(wrapper);
    });
  } catch (e) {
    console.error("[bag] Error al renderizar bolsa:", e.message);
  }
}

/**
 * Renderiza un recinto específico: limpia y coloca dinosaurios existentes.
 * @param {number} gameId
 * @param {number} playerSeat
 * @param {number} enclosureId
 * @returns {Promise<void>}
 */
async function renderEnclosure(gameId, playerSeat, enclosureId) {
  const containerId = Object.keys(ENCLOSURE_CONTAINER_TO_ID).find(
    (k) => ENCLOSURE_CONTAINER_TO_ID[k] === enclosureId
  );
  if (!containerId) return;
  const cont = document.getElementById(containerId);
  if (!cont) return;
  cont.innerHTML = "";
  try {
    const data = await fetchJSON(
      `${API_BASE}/api/game/enclosure/${gameId}/${playerSeat}/${enclosureId}`
    );
    if (!data || data.success === false) {
      console.warn(
        `[/enclosure ${enclosureId}] No se pudo cargar los datos:`,
        data
      );
    }
    const dinos = Array.isArray(data.dinos) ? data.dinos : [];
    console.log(`[enclosure ${enclosureId}] dinos=${dinos.length}`, dinos);
    dinos.forEach((d) => {
      const div = document.createElement("div");
      div.className = "dinosaurio__recinto";
      div.style.backgroundImage = `url('${imageForVertical(
        d.dinosaur_type || "amarillo"
      )}')`;
      div.title = `${d.dinosaur_type || ""} #${d.dino_id || d.id || ""}`;
      cont.appendChild(div);
    });
  } catch (e) {
    console.error(`[enclosure ${enclosureId}] Error al renderizar:`, e.message);
  }
}

/**
 * Renderiza todos los recintos en paralelo (Promise.all).
 * @param {number} gameId
 * @param {number} playerSeat
 * @returns {Promise<void>}
 */
async function renderAllEnclosures(gameId, playerSeat) {
  const promises = Object.values(ENCLOSURE_CONTAINER_TO_ID).map((id) =>
    renderEnclosure(gameId, playerSeat, id)
  );
  await Promise.all(promises);
}

/**
 * Adjunta eventos de dragover / drop a los elementos .rec
 * para permitir la colocación según su clase rec__X.
 * @returns {void}
 */
function attachDropHandlers() {
  // Permitir drop en los recintos (rec__*) en lugar de los contenedores internos
  const recs = Array.from(document.querySelectorAll(".rec"));
  recs.forEach((rec) => {
    // Detectar el tipo rec__X de este elemento
    const recClass = Array.from(rec.classList).find((c) =>
      c.startsWith("rec__")
    );
    if (!recClass) return;

    rec.addEventListener("dragover", (e) => {
      e.preventDefault();
      try {
        e.dataTransfer.dropEffect = "move";
      } catch {}
      rec.classList.add("rec--parpadeo");
    });

    rec.addEventListener("dragleave", () => {
      rec.classList.remove("rec--parpadeo");
    });

    rec.addEventListener("drop", async (e) => {
      e.preventDefault();
      rec.classList.remove("rec--parpadeo");
      if (state.isPlacing) return;

      const dinoId = e.dataTransfer.getData("dinoId");
      const color = e.dataTransfer.getData("color");
      if (!dinoId) {
        console.warn("[drop] No dinoId in dataTransfer; ignoring drop");
        return;
      }

      // Mapear rec__X -> enclosureId
      const key = "dinos__" + recClass.split("__")[1];
      const enclosureId = ENCLOSURE_CONTAINER_TO_ID[key];
      if (!enclosureId) {
        console.warn("[drop] No enclosureId mapped for", recClass);
        return;
      }
      console.log(
        `[drop] dinoId=${dinoId} color=${color} -> ${recClass} (id=${enclosureId})`
      );

      // Contenedor interno para conteo y render en este rec
      const innerId = ENCLOSURE_ID_TO_CONTAINER[enclosureId];
      const inner = innerId ? document.getElementById(innerId) : null;
      const currentCount = inner
        ? inner.querySelectorAll(".dinosaurio__recinto").length
        : 0;
      const max = state.enclosuresMax[enclosureId] ?? 6;
      console.log(`[drop] currentCount=${currentCount} max=${max}`);
      if (currentCount >= max) {
        alert("Este recinto está lleno.");
        return;
      }

      try {
        state.isPlacing = true;
        await placeDino(
          state.gameId,
          state.playerSeat,
          parseInt(dinoId, 10),
          enclosureId
        );
        await showLoading("Turno del siguiente jugador...");
        state.playerSeat = state.playerSeat === 0 ? 1 : 0;
        await reloadView();
      } catch (err) {
        console.error("Error al colocar dino:", err.message);
        alert(
          "No se pudo colocar el dinosaurio. Revisa la conexión o intenta de nuevo."
        );
      } finally {
        state.isPlacing = false;
        hideLoading();
      }
    });
  });
}

/**
 * Envía un movimiento de turno colocando un dinosaurio en un recinto.
 * Lanza error si el backend indica fallo (validación de reglas, etc.).
 * @param {number} gameId
 * @param {number} playerSeat
 * @param {number} dinoId
 * @param {number} enclosureId
 * @returns {Promise<any>} Respuesta cruda del backend (JSON)
 */
async function placeDino(gameId, playerSeat, dinoId, enclosureId) {
  const body = {
    game_id: gameId,
    player_seat: playerSeat,
    dino_id: dinoId,
    enclosure_id: enclosureId,
  };
  const resp = await fetchJSON(`${API_BASE}/api/game/turn`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
    credentials: "include",
  });
  if (!resp.success) {
    throw new Error(resp.message || resp.error || "Error en turno");
  }
  return resp;
}

/**
 * Recarga la vista completa:
 *  - Metadatos de recintos
 *  - Bolsa
 *  - Recintos
 *  - Header (turno, ronda)
 *  - Puntajes y paneles (orden por líder)
 *  - Detección de fin de juego
 * @returns {Promise<void>}
 */
async function reloadView() {
  await loadEnclosuresMeta(state.gameId, state.playerSeat);
  await Promise.all([
    renderBag(state.gameId, state.playerSeat),
    renderAllEnclosures(state.gameId, state.playerSeat),
  ]);
  await loadAndRenderHeader();
  await loadAndRenderScores();
  await checkAndShowGameOver();
}

/**
 * Obtiene y pinta puntajes (GET /game/scores/{id}) y luego
 * aplica updatePlayerPanels para reflejar líder.
 * @returns {Promise<void>}
 */
async function loadAndRenderScores() {
  try {
    const data = await fetchJSON(`${API_BASE}/api/game/scores/${state.gameId}`);
    if (!data || data.success === false) {
      console.warn("[scores] No se pudo cargar puntajes:", data);
      return;
    }
    updatePlayerPanels(data.scores);
  } catch (e) {
    console.warn("[scores] Error cargando puntajes:", e.message);
  }
}

/**
 * Actualiza los paneles visuales superior/inferior.
 * El panel superior siempre muestra al líder (o seat 0 si empate).
 * @param {Scores} scores
 * @returns {void}
 */
function updatePlayerPanels(scores) {
  // scores.player1 -> asiento 0; scores.player2 -> asiento 1
  const score0 = scores.player1 ?? 0;
  const score1 = scores.player2 ?? 0;
  const name0 = state.playerNames[0] || "Jugador 1";
  const name1 = state.playerNames[1] || "Jugador 2";

  // Determinar líder para ocupar el panel superior ('.user.king')
  const leaderSeat = score0 >= score1 ? 0 : 1;
  const followerSeat = leaderSeat === 0 ? 1 : 0;

  const topNameEl = document.getElementById("player1-name");
  const topScoreEl = document.getElementById("player1-score");
  const bottomNameEl = document.getElementById("player2-name");
  const bottomScoreEl = document.getElementById("player2-score");

  if (topNameEl) topNameEl.textContent = leaderSeat === 0 ? name0 : name1;
  if (topScoreEl)
    topScoreEl.textContent = `Puntaje: ${leaderSeat === 0 ? score0 : score1}p`;
  if (bottomNameEl)
    bottomNameEl.textContent = followerSeat === 0 ? name0 : name1;
  if (bottomScoreEl)
    bottomScoreEl.textContent = `Puntaje: ${
      followerSeat === 0 ? score0 : score1
    }p`;
}

/**
 * Obtiene la cantidad de dinos restantes en bolsa para un asiento.
 * Retorna 0 si error (considerado vacío).
 * @param {number} gameId
 * @param {number} seat
 * @returns {Promise<number>}
 */
async function getBagCount(gameId, seat) {
  try {
    const data = await fetchJSON(`${API_BASE}/api/game/bag/${gameId}/${seat}`);
    return Array.isArray(data.bag) ? data.bag.length : 0;
  } catch (_) {
    return 0;
  }
}

/**
 * Comprueba si ambas bolsas están vacías y, de ser así,
 * obtiene puntajes y muestra modal de fin de juego.
 * NOTA: Esta heurística depende de que el backend vacíe bolsas al final.
 * @returns {Promise<void>}
 */
async function checkAndShowGameOver() {
  // Consider game over when both bags are empty
  const [c0, c1] = await Promise.all([
    getBagCount(state.gameId, 0),
    getBagCount(state.gameId, 1),
  ]);
  if (c0 === 0 && c1 === 0) {
    // Fetch scores to determine winner
    let scores = { player1: 0, player2: 0 };
    try {
      const data = await fetchJSON(
        `${API_BASE}/api/game/scores/${state.gameId}`
      );
      if (data && data.success) scores = data.scores;
    } catch (_) {}

    const name0 = state.playerNames[0] || "Jugador 1";
    const name1 = state.playerNames[1] || "Jugador 2";
    const s0 = scores.player1 ?? 0;
    const s1 = scores.player2 ?? 0;

    let title = "\u00A1Fin del juego!";
    let message = "";
    if (s0 > s1) {
      message = `Ganador: ${name0} con ${s0} puntos (vs ${name1} ${s1}p)`;
    } else if (s1 > s0) {
      message = `Ganador: ${name1} con ${s1} puntos (vs ${name0} ${s0}p)`;
    } else {
      message = `Empate: ${name0} ${s0}p y ${name1} ${s1}p`;
    }

    showGameOverModal(title, message);
  }
}

/**
 * Muestra el modal de fin de juego con título, mensaje y botón para menu.
 * @param {string} titleText
 * @param {string} messageHtml (se inserta dentro de <p>)
 * @returns {void}
 */
function showGameOverModal(titleText, messageHtml) {
  const modal = document.getElementById("game-over-modal");
  if (!modal) return;
  const titleEl = modal.querySelector("h2");
  const contentEl = document.getElementById("final-scores");
  const btn = document.getElementById("close-modal-btn");
  if (titleEl) titleEl.textContent = titleText || "\u00A1Fin del juego!";
  if (contentEl) contentEl.innerHTML = `<p>${messageHtml}</p>`;
  if (btn) {
    btn.textContent = "Volver al menú";
    btn.onclick = () => {
      window.location.href = PAGES.menu;
    };
  }
  modal.style.display = "flex";
}

// --- Inicio ---
/**
 * Listener principal DOMContentLoaded:
 *  - Lee game_id de query o localStorage
 *  - Determina seat
 *  - Prepara drop handlers
 *  - Lanza primera recarga
 */
document.addEventListener("DOMContentLoaded", async () => {
  // game_id por query o localStorage
  const params = new URLSearchParams(window.location.search);
  const fromQS = params.get("game_id");
  const fromLS = localStorage.getItem("currentGameId");
  const gameId = parseInt(fromQS || fromLS || "0", 10);
  if (!gameId) {
    alert("No se encontró game_id. Volviendo al menú.");
    window.location.href = PAGES.preVolverAJugar;
    return;
  }
  state.gameId = gameId;

  // Determinar asiento del usuario actual
  state.playerSeat = await determineSeat(gameId);
  console.log("[init] gameId=", state.gameId, "playerSeat=", state.playerSeat);

  // Preparar droppables
  attachDropHandlers();

  // Render inicial
  await reloadView();
});
