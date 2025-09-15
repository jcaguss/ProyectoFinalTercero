<?php

require_once __DIR__ . '/../services/RecoveryGameService.php';
require_once __DIR__ . '/../utils/JsonResponse.php';

class RecoveryGameController {
    private RecoveryGameService $recoveryService;

    public function __construct() {
        $this->recoveryService = new RecoveryGameService();
    }

    /**
     * Obtiene las partidas en progreso de un jugador
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "games": [
     *     {
     *       "game_id": 123,
     *       "current_round": 1,
     *       "current_turn": 3,
     *       "active_seat": 0,
     *       "player_seat": 1,
     *       "player1_username": "player1",
     *       "player2_username": "player2",
     *       "created_at": "2023-12-01 10:00:00",
     *       "is_active_player": false,
     *       "can_play": false
     *     },
     *     // ...más juegos
     *   ]
     * }
     * 
     * Respuesta de error:
     * {
     *   "error": "Missing user_id"
     * }
     */
    public function getInProgressGames($request) {
        try {
            if (!isset($request['user_id'])) {
                return JsonResponse::create(['error' => 'Missing user_id'], 400);
            }

            $games = $this->recoveryService->getInProgressGames($request['user_id']);
            
            return JsonResponse::create([
                'success' => true,
                'games' => $games
            ]);

        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Recupera el estado completo de una partida
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "game_state": {
     *     "game": {
     *       "game_id": 123,
     *       "status": "IN_PROGRESS",
     *       "current_round": 1,
     *       "current_turn": 3,
     *       "active_seat": 0
     *     },
     *     "players": {
     *       "0": {
     *         "user_id": 1,
     *         "username": "player1",
     *         "bag": [
     *           {
     *             "bag_content_id": 1,
     *             "species_id": 1,
     *             "species_name": "TREX",
     *             "img": "trex.png"
     *           }
     *           // ...más dinosaurios
     *         ],
     *         "placements": [
     *           {
     *             "placement_id": 1,
     *             "enclosures_id": 1,
     *             "slot_index": 0,
     *             "species_id": 2,
     *             "species_name": "RAPTOR"
     *           }
     *           // ...más colocaciones
     *         ]
     *       },
     *       "1": {
     *         // ...datos del jugador 2
     *       }
     *     },
     *     "last_die_roll": {
     *       "roll_id": 456,
     *       "die_face": "LEFT_SIDE",
     *       "affected_player_seat": 1
     *     },
     *     "enclosures": [
     *       // ...lista de recintos disponibles
     *     ]
     *   }
     * }
     * 
     * Respuesta de error:
     * {
     *   "error": "Game not found or not in progress"
     * }
     */
    public function resumeGame($request) {
        try {
            if (!isset($request['game_id'])) {
                return JsonResponse::create(['error' => 'Missing game_id'], 400);
            }

            // Obtener user_id si está disponible (puede venir como parámetro GET o en la ruta)
            $userId = null;
            if (isset($request['user_id'])) {
                $userId = intval($request['user_id']);
            } elseif (isset($_GET['user_id'])) {
                $userId = intval($_GET['user_id']);
            }
            
            error_log("RecoveryGameController: Resuming game {$request['game_id']} for user $userId");
            error_log("Request params: " . json_encode($request));
            error_log("GET params: " . json_encode($_GET));

            $gameState = $this->recoveryService->resumeGame($request['game_id'], $userId);
            
            if (!$gameState) {
                error_log("RecoveryGameController: Game state is null for game {$request['game_id']}");
                return JsonResponse::create(['error' => 'Game not found or not in progress'], 404);
            }

            return JsonResponse::create([
                'success' => true,
                'game_state' => $gameState
            ]);

        } catch (Exception $e) {
            // Log detailed error information
            error_log("RecoveryGameController ERROR: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Return a more detailed error message to help debugging
            return JsonResponse::create([
                'error' => $e->getMessage(),
                'trace' => explode("\n", $e->getTraceAsString())[0]
            ], 500);
        }
    }

    /**
     * Alias para obtener el historial/estado del juego.
     * Actualmente reutiliza la lógica de resumeGame para devolver el estado completo.
     * Mantiene compatibilidad con la ruta /api/recovery/{game_id}
     */
    public function getGameHistory($request) {
        // Delegamos a resumeGame para evitar duplicar lógica y asegurar consistencia
        return $this->resumeGame($request);
    }
}