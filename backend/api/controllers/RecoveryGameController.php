<?php

require_once __DIR__ . '/../services/RecoveryGameService.php';
require_once __DIR__ . '/../utils/JsonResponse.php';
require_once __DIR__ . '/../repositories/GameRepository.php';

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
            // Verificar autenticación y propiedad
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $authenticatedUserId = $_SESSION['user_id'] ?? null;
            if (!$authenticatedUserId) {
                return JsonResponse::create(['error' => 'Usuario no autenticado'], 401);
            }

            if (!isset($request['user_id'])) {
                return JsonResponse::create(['error' => 'Missing user_id'], 400);
            }

            $requestedUserId = (int)$request['user_id'];
            // Verificar que el usuario autenticado esté solicitando SUS propias partidas
            if ($requestedUserId !== $authenticatedUserId) {
                return JsonResponse::create(['error' => 'No autorizado'], 403);
            }

            $games = $this->recoveryService->getInProgressGames($requestedUserId);
            return JsonResponse::create(['success' => true, 'games' => $games]);

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
            // Verificar autenticación
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return JsonResponse::create(['error' => 'Usuario no autenticado'], 401);
            }

            if (!isset($request['game_id'])) {
                return JsonResponse::create(['error' => 'Missing game_id'], 400);
            }

            $gameId = (int)$request['game_id'];
            
            // Verificar que el usuario pertenezca a la partida
            $gameRepo = GameRepository::getInstance();
            $game = $gameRepo->getGameById($gameId);
            if (!$game || ((int)$game['player1_user_id'] !== $userId && (int)$game['player2_user_id'] !== $userId)) {
                return JsonResponse::create(['error' => 'No autorizado'], 403);
            }

            error_log("RecoveryGameController: Resuming game $gameId for user $userId");

            $gameState = $this->recoveryService->resumeGame($gameId, $userId);
            if (!$gameState) {
                return JsonResponse::create(['error' => 'Game not found or not in progress'], 404);
            }

            return JsonResponse::create(['success' => true, 'game_state' => $gameState]);

        } catch (Exception $e) {
            error_log("RecoveryGameController ERROR: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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