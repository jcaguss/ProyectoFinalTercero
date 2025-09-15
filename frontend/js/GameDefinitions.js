/**
 * Definiciones de tipos para el juego Draftosaurus
 */

// Mapeo de tipos de dinosaurios
export const DinoTypes = {
  1: "Amarillo",
  2: "Rojo",
  3: "Verde",
  4: "Azul",
  5: "Rosa",
  6: "Naranja",
};

// Mapeo de tipos de recintos
export const EnclosureTypes = {
  SAME_SPECIES: "bosque_semejanza", // Bosque de semejanza
  DIFFERENT_SPECIES: "parado_diferencia", // Parado diferencia
  PAIRS_BONUS: "pradera_amor", // Pradera del amor
  TRIO_REQUIRED: "trio_frondoso", // Trio frondoso
  MAJORITY_SPECIES: "rey_selva", // Rey selva
  UNIQUE_SPECIES: "isla_solitaria", // Isla solitaria
  NO_RESTRICTIONS: "rio", // Río
};

// Mapeo de IDs de recintos a selectores CSS
export const EnclosureSelectors = {
  1: ".rec__igual", // Bosque semejanza
  2: ".rec__noigual", // Parado diferencia
  3: ".rec__pareja", // Pradera del amor
  4: ".rec__tres", // Trio frondoso
  5: ".rec__rey", // Rey selva
  6: ".rec__solo", // Isla solitaria
  7: ".rec__rio", // Río
};

// Mapeo de tipos de dado
export const DieFaces = {
  LEFT_SIDE: "Lado Izquierdo",
  RIGHT_SIDE: "Lado Derecho",
  FOREST: "Bosque",
  EMPTY: "Todos",
  NO_TREX: "No T-Rex",
  ROCKS: "Rocas",
};

// Rutas de imágenes para dinosaurios horizontales
export const DinoImages = {
  1: "./img/amarilloHori.PNG",
  2: "./img/rojoHori.PNG",
  3: "./img/verdeHori.PNG",
  4: "./img/azulHori.PNG",
  5: "./img/rosaHori.PNG",
  6: "./img/naranjaHori.PNG",
};

// Rutas de imágenes para dinosaurios verticales
export const DinoVerticalImages = {
  1: "./img/amarilloVerti.PNG",
  2: "./img/rojoVerti.PNG",
  3: "./img/verdeVerti.PNG",
  4: "./img/azulVerti.PNG",
  5: "./img/rosaVerti.PNG",
  6: "./img/naranjaVerti.PNG",
};

// Reglas de puntuación por tipo de recinto
export const ScoringRules = {
  same_type: [0, 2, 4, 8, 12, 18, 24], // Puntos por cantidad de dinos
  different_type: [0, 1, 3, 6, 10, 15, 21], // Puntos por cantidad de dinos
  pairs: 5, // Puntos por pareja
  trio: 7, // Puntos si hay exactamente 3
  king: 5, // Puntos si hay al menos 1
  solo: 7, // Puntos si hay 1 y es único
  river: 1, // Puntos por dino
};

export default {
  DinoTypes,
  EnclosureTypes,
  EnclosureSelectors,
  DieFaces,
  DinoImages,
  DinoVerticalImages,
  ScoringRules,
};
