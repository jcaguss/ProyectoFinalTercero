/**
 * preVolverAJugar.js - Muestra la lista de partidas pendientes para continuar
 */

import {
  obtenerNombreUsuario,
  obtenerIdUsuario,
  cerrarSesion,
  PAGES,
} from "./auth.js";

document.addEventListener("DOMContentLoaded", function () {
  // Elementos del DOM
  const gameList = document.getElementById("game-list");
  const noGamesMessage = document.getElementById("no-games");
  const loadingMessage = document.getElementById("loading");
  const backButton = document.getElementById("back-button");
  const gameItemTemplate = document.getElementById("game-item-template");
  const nombreUsuarioElement = document.getElementById("nombre-usuario");
  const logoutButton = document.getElementById("btn-logout");

  // Obtener nombre de usuario
  const username = obtenerNombreUsuario();

  if (!username) {
    window.location.href = PAGES.login;
    return;
  }

  // Mostrar el nombre de usuario
  if (nombreUsuarioElement && username) {
    nombreUsuarioElement.textContent = username;
  }

  // Manejar el cierre de sesión
  if (logoutButton) {
    logoutButton.addEventListener("click", function () {
      cerrarSesion();
    });
  }

  // Botón para volver al menú
  backButton.addEventListener("click", function () {
    window.location.href = "menu-player.html";
  });

  // Cargar las partidas pendientes
  loadPendingGames();

  /**
   * Carga las partidas pendientes del usuario desde la API
   */
  function loadPendingGames() {
    // Mostrar mensaje de carga
    loadingMessage.style.display = "block";
    gameList.style.display = "none";
    noGamesMessage.style.display = "none";

    // Obtener el ID del usuario
    const userId = obtenerIdUsuario();

    // Hacer la petición a la API
    fetch(`http://localhost:8000/api/game/pending/${userId}`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => {
        console.log("Respuesta API:", response.status);
        if (!response.ok) {
          if (response.status === 404) {
            throw new Error("No se encontró la ruta en la API.");
          } else {
            throw new Error(`Error ${response.status}: ${response.statusText}`);
          }
        }
        return response.json();
      })
      .then((data) => {
        console.log("Datos recibidos:", data);

        if (data.success && data.games) {
          // Procesamos los datos recibidos de la API
          if (data.games.length > 0) {
            displayGames(data.games);
          } else {
            noGamesMessage.textContent = "No hay partidas pendientes.";
            noGamesMessage.style.display = "block";
            gameList.style.display = "none";
          }
        } else {
          noGamesMessage.textContent = "No hay partidas pendientes.";
          noGamesMessage.style.display = "block";
          gameList.style.display = "none";
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showError("Error al cargar las partidas: " + error.message);
        noGamesMessage.textContent =
          "No se pudieron cargar las partidas en este momento.";
        noGamesMessage.style.display = "block";
      })
      .finally(() => {
        // Ocultar mensaje de carga
        loadingMessage.style.display = "none";
      });
  }

  /**
   * Muestra las partidas en la lista
   * @param {Array} games - Lista de partidas
   */
  function displayGames(games) {
    gameList.innerHTML = "";

    if (!games || games.length === 0) {
      noGamesMessage.style.display = "block";
      gameList.style.display = "none";
      return;
    }

    gameList.style.display = "block";
    noGamesMessage.style.display = "none";

    const currentUserIdRaw = obtenerIdUsuario();
    const currentUserId = Number(currentUserIdRaw);
    const currentUsername = obtenerNombreUsuario();

    const resolveOpponent = (g, myId, myName) => {
      const p1Id = Number(g.player1_user_id ?? g.player1_id);
      const p2Id = Number(g.player2_user_id ?? g.player2_id);
      const p1Name = g.player1_username || g.player1_name || `Jugador ${p1Id}`;
      const p2Name = g.player2_username || g.player2_name || `Jugador ${p2Id}`;

      let opponent;
      if (!Number.isNaN(myId)) {
        if (p1Id === myId && p2Id !== myId) opponent = p2Name;
        else if (p2Id === myId && p1Id !== myId) opponent = p1Name;
        else if (p1Id !== myId) opponent = p1Name;
        else if (p2Id !== myId) opponent = p2Name;
      } else {
        // Si el ID del usuario no se pudo parsear, asumimos oponente = player2
        opponent = p2Name;
      }

      // Corrección: si terminó siendo mi propio nombre, forzar el otro
      if (opponent === myName) {
        if (opponent === p1Name && p2Name !== myName) opponent = p2Name;
        else if (opponent === p2Name && p1Name !== myName) opponent = p1Name;
      }
      return opponent || "Oponente";
    };

    games.forEach((game) => {
      const gameItem = gameItemTemplate.content.cloneNode(true);
      const li = gameItem.querySelector("li");
      li.dataset.gameId = game.game_id;

      const opponentName = resolveOpponent(
        game,
        currentUserId,
        currentUsername
      );
      gameItem.querySelector(
        ".game-opponent"
      ).textContent = `Contra: ${opponentName}`;

      const createdRaw = game.created_at || "";
      const gameDate = new Date(createdRaw.replace(" ", "T") + "Z");
      gameItem.querySelector(".game-date").textContent = formatDate(gameDate);

      const statusElement = gameItem.querySelector(".game-status");
      const activeSeat = game.active_seat;
      const p1Id = Number(game.player1_user_id ?? game.player1_id);
      const p2Id = Number(game.player2_user_id ?? game.player2_id);
      const isMyTurn =
        game.is_active_player === true ||
        (activeSeat === 0 && p1Id === currentUserId) ||
        (activeSeat === 1 && p2Id === currentUserId);

      if (isMyTurn) {
        statusElement.textContent = "Tu turno";
        statusElement.classList.add("status-my-turn");
      } else {
        statusElement.textContent = "Turno oponente";
        statusElement.classList.add("status-opponent-turn");
      }

      li.addEventListener("click", () => selectGame(game.game_id));
      gameList.appendChild(gameItem);
    });
  }

  /**
   * Formatea una fecha para mostrarla
   * @param {Date} date - Fecha a formatear
   * @returns {string} - Fecha formateada
   */
  function formatDate(date) {
    const now = new Date();
    const diff = Math.floor((now - date) / 1000); // diferencia en segundos

    if (diff < 60) {
      return "Hace unos segundos";
    } else if (diff < 3600) {
      const minutes = Math.floor(diff / 60);
      return `Hace ${minutes} minuto${minutes !== 1 ? "s" : ""}`;
    } else if (diff < 86400) {
      const hours = Math.floor(diff / 3600);
      return `Hace ${hours} hora${hours !== 1 ? "s" : ""}`;
    } else if (diff < 604800) {
      const days = Math.floor(diff / 86400);
      return `Hace ${days} día${days !== 1 ? "s" : ""}`;
    } else {
      // Formatear fecha completa para partidas más antiguas
      return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
    }
  }

  /**
   * Selecciona una partida para continuar
   * @param {number} gameId - ID de la partida seleccionada
   */
  function selectGame(gameId) {
    localStorage.setItem("currentGameId", gameId);

    // Buscar los datos del juego seleccionado en la lista de juegos cargados
    const gameElement = document.querySelector(`li[data-game-id="${gameId}"]`);
    if (gameElement) {
      // Guarda algunos datos extras para ayudar a la página de volver a jugar
      const opponentName = gameElement
        .querySelector(".game-opponent")
        .textContent.replace("Contra: ", "");
      const fecha = gameElement.querySelector(".game-date").textContent;
      const esMiTurno = gameElement.querySelector(".status-my-turn") !== null;

      const gameData = {
        game_id: gameId,
        opponent_name: opponentName,
        created_at: fecha,
        is_my_turn: esMiTurno,
      };

      localStorage.setItem("selectedGameData", JSON.stringify(gameData));
    }

    // Redirigir a la página de juego
    window.location.href = `${PAGES.juego}?game_id=${gameId}`; // antes PAGES.volverAJugar
  }

  /**
   * Muestra un mensaje de error
   * @param {string} message - Mensaje de error
   */
  function showError(message) {
    const mensajeSistema = document.getElementById("mensaje-sistema");
    mensajeSistema.textContent = message;
    mensajeSistema.style.display = "block";
    mensajeSistema.classList.add("error");

    setTimeout(() => {
      mensajeSistema.style.display = "none";
      mensajeSistema.classList.remove("error");
    }, 5000);
  }

  // La función cerrarSesion está importada desde auth.js

  // Cuando el usuario selecciona una partida para reanudar:
  function irAJuego(gameId) {
    localStorage.setItem("currentGameId", gameId);
    window.location.href = `${PAGES.juego}?game_id=${gameId}`; // antes volverAJugar.html
  }
});
