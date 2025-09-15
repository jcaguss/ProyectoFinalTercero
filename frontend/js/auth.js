/**
 * Funciones de autenticación básicas - Versión sin sesiones
 */

// URLs de la API
const API_URLS = {
  login: "http://localhost:8000/api/auth/login",
  register: "http://localhost:8000/api/auth/register",
};

// Páginas de la aplicación
const PAGES = {
  login: "login.html",
  register: "register.html",
  menu: "menu-player.html",
  juego: "juego.html",
  // Mantener la página de selección disponible, pero no usarla por defecto en el menú
  seleccionarOponente: "seleccionar-oponente.html",
  preVolverAJugar: "preVolverAJugar.html",
  volverAJugar: "volverAJugar.html",
};

/**
 * Inicia sesión
 * @param {string} identifier - Email o nombre de usuario
 * @param {string} password - Contraseña
 * @returns {Promise<Object>} Resultado del login con el ID del jugador
 */
async function iniciarSesion(identifier, password) {
  try {
    console.log("Intentando login con:", { identifier, password });
    console.log("URL de login:", API_URLS.login);

    const response = await fetch(API_URLS.login, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ identifier, password }),
      credentials: "include", // Incluir cookies y sesión
    });

    console.log("Status de respuesta:", response.status);
    console.log("Headers:", Object.fromEntries(response.headers.entries()));

    // Verificar primero si la respuesta es JSON válido
    const text = await response.text();
    console.log("Respuesta completa:", text);
    let data;

    try {
      // Intentar analizar el texto como JSON
      data = JSON.parse(text);
      console.log("Datos JSON parseados:", data);
    } catch (parseError) {
      console.error("Error al parsear la respuesta como JSON:", parseError);
      console.log("Respuesta del servidor (texto plano):", text);

      // Devolver un error controlado si no es JSON válido
      return {
        success: false,
        message: "El servidor respondió con un formato inválido",
        error: "parse_error",
        responseText: text.substring(0, 200) + "...", // Mostramos parte del texto para diagnóstico
      };
    }

    // Si el login es exitoso, guardar el ID del usuario en localStorage
    if (data.success === true && data.user) {
      console.log("Login exitoso, guardando datos en localStorage:", data.user);
      // El backend devuelve el ID como "id", no "user_id"
      localStorage.setItem("userId", data.user.id);
      localStorage.setItem("username", data.user.username);
      console.log("Datos guardados en localStorage:", {
        userId: localStorage.getItem("userId"),
        username: localStorage.getItem("username"),
      });
    }

    return {
      success: data.success === true,
      message: data.message || "Error al iniciar sesión",
      user: data.user || null,
      debug: data.debug_info || null, // Para mostrar información de depuración si existe
    };
  } catch (error) {
    console.error("Error al iniciar sesión:", error);
    return {
      success: false,
      message: "Error de conexión al servidor",
      user: null,
    };
  }
}

/**
 * Obtiene el ID del usuario actual desde localStorage
 * @returns {number|null} ID del usuario o null
 */
function obtenerIdUsuario() {
  return localStorage.getItem("userId")
    ? parseInt(localStorage.getItem("userId"))
    : null;
}

/**
 * Obtiene el nombre del usuario actual desde localStorage
 * @returns {string|null} Nombre del usuario o null
 */
function obtenerNombreUsuario() {
  return localStorage.getItem("username");
}

/**
 * Cierra la sesión del usuario eliminando datos de localStorage
 * @returns {void}
 */
function cerrarSesion() {
  localStorage.removeItem("userId");
  localStorage.removeItem("username");
  window.location.href = PAGES.login;
}

// Exportar funciones
export {
  iniciarSesion,
  obtenerIdUsuario,
  obtenerNombreUsuario,
  cerrarSesion,
  API_URLS,
  PAGES,
};
