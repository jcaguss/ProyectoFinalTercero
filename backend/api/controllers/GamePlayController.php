<?php

require_once __DIR__ . '/../services/GamePlayService.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../utils/JsonResponse.php';

class GamePlayController {
    private GamePlayService $gamePlayService;

    public function __construct() {
        $this->gamePlayService = new GamePlayService();
    }

    /**
     * Inicia una nueva partida
     * startGame Response:
     * {
     *   "success": true,
     *   "game_id": 123
     * }
     * Error Response:
     * {
     *   "error": "Missing player IDs"
     * }
     */
    public function startGame($request) {
        try {
            // Debug received data
            error_log("StartGame received request: " . json_encode($request));
            
            // Validar datos de entrada
            if (!isset($request['player1_id']) || !isset($request['player2_id'])) {
                error_log("Missing player IDs in request");
                return JsonResponse::create(['error' => 'Missing player IDs'], 400);
            }
            
            // Validate player IDs are integers
            $player1Id = intval($request['player1_id']);
            $player2Id = intval($request['player2_id']);
            
            if ($player1Id <= 0 || $player2Id <= 0) {
                error_log("Invalid player IDs: player1_id=$player1Id, player2_id=$player2Id");
                return JsonResponse::create(['error' => 'Invalid player IDs'], 400);
            }

            error_log("Starting game with player1_id=$player1Id, player2_id=$player2Id");
            
            try {
                $gameId = $this->gamePlayService->startGame($player1Id, $player2Id);
                
                error_log("Game started successfully with ID: $gameId");
                return JsonResponse::create([
                    'success' => true,
                    'game_id' => $gameId
                ]);
            } catch (Exception $e) {
                error_log("Exception in GamePlayService->startGame: " . $e->getMessage());
                return JsonResponse::create(['error' => $e->getMessage()], 500);
            }

        } catch (Exception $e) {
            error_log("Exception in GamePlayController->startGame: " . $e->getMessage());
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene las partidas pendientes para un usuario
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "games": [
     *     {
     *       "game_id": 123,
     *       "opponent_username": "Player2",
     *       "created_at": "2023-12-01T10:00:00",
     *       "is_my_turn": true
     *     },
     *     // ...más juegos
     *   ]
     * }
     */
    public function getPendingGames() {
        try {
            // Verificar que el usuario esté autenticado
            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                return JsonResponse::create(['error' => 'Usuario no autenticado'], 401);
            }

            // Obtener las partidas pendientes
            $games = $this->gamePlayService->getPendingGames($userId);
            
            return JsonResponse::create([
                'success' => true,
                'games' => $games
            ]);
        } catch (Exception $e) {
            error_log("Exception in GamePlayController->getPendingGames: " . $e->getMessage());
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Procesa un turno del juego
     * processTurn Response:
     * {
     *   "success": true
     * }
     * Error Response:
     * {
     *   "error": "Missing field_name"
     * }
     */
    public function processTurn($request) {
        try {
            // Validar datos de entrada
            $requiredFields = ['game_id', 'player_seat', 'dino_id', 'enclosure_id'];
            foreach ($requiredFields as $field) {
                if (!isset($request[$field])) {
                    return JsonResponse::create([
                        'success' => false, 
                        'error' => "Missing $field",
                        'message' => "Falta el campo $field en la solicitud"
                    ], 400);
                }
            }

            // Registro detallado para depuración
            error_log("PROCESS TURN - Datos recibidos: " . json_encode($request));
            error_log("Tipos de datos: game_id=" . gettype($request['game_id']) . 
                      ", player_seat=" . gettype($request['player_seat']) . 
                      ", dino_id=" . gettype($request['dino_id']) . 
                      ", enclosure_id=" . gettype($request['enclosure_id']));
            
            // Asegurarse que sean enteros
            $gameId = (int) $request['game_id'];
            $playerSeat = (int) $request['player_seat'];
            $dinoId = (int) $request['dino_id'];
            $enclosureId = (int) $request['enclosure_id'];
            $slotIndex = isset($request['slot_index']) ? (int) $request['slot_index'] : null;
            
            error_log("Valores convertidos: game_id=$gameId, player_seat=$playerSeat, dino_id=$dinoId, enclosure_id=$enclosureId");

            // Validación: recinto debe estar en el rango válido 1..7 (según esquema)
            if ($enclosureId < 1 || $enclosureId > 7) {
                return JsonResponse::create([
                    'success' => false,
                    'error' => 'Invalid enclosure_id',
                    'message' => 'El enclosure_id debe estar en el rango 1..7',
                    'details' => [
                        'enclosure_id' => $enclosureId
                    ]
                ], 400);
            }
            
            // Capturar errores específicos en processTurn
            try {
                // Debug adicional antes de llamar al servicio
                error_log("PROCESS TURN - Llamando a gamePlayService->processTurn con los parámetros");
                error_log("PROCESS TURN - Parámetros detallados: " . 
                          "game_id=$gameId (" . gettype($gameId) . "), " . 
                          "player_seat=$playerSeat (" . gettype($playerSeat) . "), " . 
                          "dino_id=$dinoId (" . gettype($dinoId) . "), " . 
                          "enclosure_id=$enclosureId (" . gettype($enclosureId) . "), " .
                          "slot_index=" . ($slotIndex === null ? "NULL" : $slotIndex . " (" . gettype($slotIndex) . ")"));
                
                $result = $this->gamePlayService->processTurn(
                    $gameId,
                    $playerSeat,
                    $dinoId,
                    $enclosureId,
                    $slotIndex
                );
                
                error_log("PROCESS TURN - Resultado: " . ($result ? "true" : "false"));

                if ($result) {
                    return JsonResponse::create([
                        'success' => true,
                        'message' => "Turno procesado exitosamente",
                        'details' => [
                            'game_id' => $gameId,
                            'player_seat' => $playerSeat,
                            'dino_id' => $dinoId,
                            'enclosure_id' => $enclosureId,
                            'slot_index' => $slotIndex
                        ]
                    ]);
                } else {
                    error_log("PROCESS TURN - El método processTurn retornó false sin lanzar excepción");
                    return JsonResponse::create([
                        'success' => false,
                        'error' => "Error al procesar turno",
                        'message' => "No se pudo procesar el turno. Verifique los logs del servidor para más detalles.",
                        'details' => [
                            'game_id' => $gameId,
                            'player_seat' => $playerSeat,
                            'dino_id' => $dinoId, 
                            'enclosure_id' => $enclosureId,
                            'slot_index' => $slotIndex
                        ]
                    ]);
                }
            } catch (Exception $serviceException) {
                error_log("PROCESS TURN - Excepción capturada en GamePlayService->processTurn: " . $serviceException->getMessage());
                error_log("PROCESS TURN - Stack trace: " . $serviceException->getTraceAsString());
                
                return JsonResponse::create([
                    'success' => false,
                    'error' => "Error en el servicio: " . $serviceException->getMessage(),
                    'message' => "Error interno al procesar el turno.",
                    'debug_info' => [
                        'exception_type' => get_class($serviceException),
                        'file' => $serviceException->getFile(),
                        'line' => $serviceException->getLine(),
                        'params' => [
                            'game_id' => $gameId,
                            'player_seat' => $playerSeat,
                            'dino_id' => $dinoId,
                            'enclosure_id' => $enclosureId,
                            'slot_index' => $slotIndex
                        ]
                    ]
                ], 500);
            }

        } catch (Exception $e) {
            error_log("ERROR CRÍTICO en processTurn: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // Detectar errores específicos de bases de datos
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, "doesn't exist") !== false) {
                // Error de tabla que no existe
                error_log("ERROR DE BASE DE DATOS DETECTADO: Tabla inexistente");
                
                // Extraer el nombre de la tabla del mensaje de error
                preg_match("/Table '(.*?)' doesn't exist/", $errorMsg, $matches);
                $tableName = isset($matches[1]) ? $matches[1] : "desconocida";
                
                return JsonResponse::create([
                    'success' => false, 
                    'error' => $errorMsg,
                    'message' => "Error del servidor: " . $errorMsg,
                    'debug_info' => [
                        'error_type' => 'missing_table',
                        'table_name' => $tableName,
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'suggestion' => "Verifique que la tabla '$tableName' exista en la base de datos"
                    ]
                ], 500);
            } else {
                // Otros errores
                return JsonResponse::create([
                    'success' => false, 
                    'error' => $errorMsg,
                    'message' => "Error del servidor: " . $errorMsg,
                    'debug_info' => [
                        'exception_type' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ], 500);
            }
        }
    }

    /**
     * Lanza el dado
     * rollDie Response:
     * {
     *   "success": true,
     *   "roll_id": 456
     * }
     * Error Response:
     * {
     *   "error": "Missing field_name"
     * }
     */
    public function rollDie($request) {
        try {
            $requiredFields = ['game_id', 'roller_seat', 'affected_seat', 'die_face'];
            foreach ($requiredFields as $field) {
                if (!isset($request[$field])) {
                    return JsonResponse::create(['error' => "Missing $field"], 400);
                }
            }

            $rollId = $this->gamePlayService->rollDie(
                $request['game_id'],
                $request['roller_seat'],
                $request['affected_seat'],
                $request['die_face']
            );

            return JsonResponse::create([
                'success' => $rollId !== null,
                'roll_id' => $rollId
            ]);

        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene los recintos válidos para un jugador
     * getValidEnclosures Response:
     * {
     *   "success": true,
     *   "enclosures": [
     *     {
     *       "enclosures_id": 1,
     *       "name_enclosures": "Bosque de la Semejanza",
     *       "position": "left",
     *       "terrain": "forest",
     *       "max_dinos": 4,
     *       "special_rule": "SAME_SPECIES"
     *     },
     *     // ...más recintos
     *   ]
     * }
     * Error Response:
     * {
     *   "error": "Missing game_id or player_seat"
     * }
     */
    public function getValidEnclosures($request) {
        try {
            if (!isset($request['game_id']) || !isset($request['player_seat'])) {
                return JsonResponse::create(['error' => 'Missing game_id or player_seat'], 400);
            }

            $enclosures = $this->gamePlayService->getValidEnclosuresForPlayer(
                $request['game_id'],
                $request['player_seat']
            );

            return JsonResponse::create([
                'success' => true,
                'enclosures' => $enclosures
            ]);

        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene la lista de oponentes disponibles para un usuario
     * getAvailableOpponents Response:
     * {
     *   "success": true,
     *   "opponents": [
     *     {
     *       "user_id": 2,
     *       "username": "Player2"
     *     },
     *     // ...más oponentes
     *   ]
     * }
     * Error Response:
     * {
     *   "error": "Missing user_id"
     * }
     */
    public function getAvailableOpponents($request) {
        try {
            if (!isset($request['user_id'])) {
                return JsonResponse::create(['error' => 'Missing user_id'], 400);
            }

            $userRepository = UserRepository::getInstance();
            $opponents = $userRepository->getAvailableOpponents($request['user_id']);

            return JsonResponse::create([
                'success' => true,
                'opponents' => $opponents
            ]);

        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el estado actual de un juego
     * getGameState Response:
     * {
     *   "success": true,
     *   "game_state": {
     *     // Estado completo del juego
     *   }
     * }
     * Error Response:
     * {
     *   "error": "Game not found"
     * }
     */
    public function getGameState($request) {
        try {
            if (!isset($request['game_id'])) {
                error_log("getGameState: Missing game_id in request");
                return JsonResponse::create(['error' => 'Missing game_id'], 400);
            }
            
            $gameId = intval($request['game_id']);
            error_log("getGameState: Processing request for game_id=$gameId");

            // Verificar que el juego existe primero
            $gameRepo = GameRepository::getInstance();
            $game = $gameRepo->getGameById($gameId);
            
            if (!$game) {
                error_log("getGameState: Game with ID $gameId not found");
                return JsonResponse::create(['error' => 'Game not found'], 404);
            }
            
            error_log("getGameState: Game found, status=" . $game['status']);

            // Usamos el servicio de recuperación para obtener el estado completo
            // Esto permite reutilizar el mismo formato que usa resumeGame
            require_once __DIR__ . '/../services/RecoveryGameService.php';
            $recoveryService = new RecoveryGameService();
            
            try {
                // En modo desarrollo, manejo especial de errores
                $isDevelopmentMode = true;
                
                try {
                    $gameState = $recoveryService->resumeGame($gameId);
                    
                    if (!$gameState) {
                        error_log("getGameState: Unable to get game state for game ID $gameId");
                        
                        if ($isDevelopmentMode) {
                            // En modo desarrollo, crear un estado de juego de emergencia
                            error_log("getGameState: Creating emergency game state (development mode)");
                            $gameState = $this->createEmergencyGameState($gameId);
                        } else {
                            return JsonResponse::create(['error' => 'Unable to get game state'], 500);
                        }
                    }
                    
                    error_log("getGameState: Successfully retrieved game state");
                    return JsonResponse::create([
                        'success' => true,
                        'game_state' => $gameState
                    ]);
                } catch (Exception $e) {
                    error_log("getGameState: Error in resumeGame: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    
                    if ($isDevelopmentMode) {
                        // En modo desarrollo, crear un estado de juego de emergencia
                        error_log("getGameState: Creating emergency game state after exception (development mode)");
                        $gameState = $this->createEmergencyGameState($gameId);
                        
                        return JsonResponse::create([
                            'success' => true,
                            'game_state' => $gameState,
                            'debug_note' => 'Estado de emergencia creado debido a un error: ' . $e->getMessage()
                        ]);
                    } else {
                        return JsonResponse::create(['error' => 'Error retrieving game state: ' . $e->getMessage()], 500);
                    }
                }
            } catch (Exception $e) {
                error_log("getGameState: Critical error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                return JsonResponse::create(['error' => 'Critical error in getGameState: ' . $e->getMessage()], 500);
            }

        } catch (Exception $e) {
            error_log("Error in getGameState: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve la bolsa de un jugador en formato UI
     * GET /api/game/bag/{game_id}/{player_seat}
     */
    public function getPlayerBag($request) {
        try {
            if (!isset($request['game_id']) || !isset($request['player_seat'])) {
                return JsonResponse::create(['error' => 'Missing game_id or player_seat'], 400);
            }

            $gameId = (int)$request['game_id'];
            $playerSeat = (int)$request['player_seat'];
            if ($playerSeat !== 0 && $playerSeat !== 1) {
                return JsonResponse::create(['error' => 'Invalid player_seat (must be 0 or 1)'], 400);
            }

            $bag = $this->gamePlayService->getPlayerBagForUI($gameId, $playerSeat);
            return JsonResponse::create([
                'success' => true,
                'bag' => $bag
            ]);
        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve el contenido de un recinto específico para un jugador en formato UI
     * GET /api/game/enclosure/{game_id}/{player_seat}/{enclosure_id}
     */
    public function getEnclosureContents($request) {
        try {
            $required = ['game_id', 'player_seat', 'enclosure_id'];
            foreach ($required as $f) {
                if (!isset($request[$f])) {
                    return JsonResponse::create(['error' => "Missing $f"], 400);
                }
            }

            $gameId = (int)$request['game_id'];
            $playerSeat = (int)$request['player_seat'];
            $enclosureId = (int)$request['enclosure_id'];

            if ($playerSeat !== 0 && $playerSeat !== 1) {
                return JsonResponse::create(['error' => 'Invalid player_seat (must be 0 or 1)'], 400);
            }
            if ($enclosureId < 1 || $enclosureId > 7) {
                return JsonResponse::create(['error' => 'Invalid enclosure_id (1..7)'], 400);
            }

            error_log("getEnclosureContents params: game_id=$gameId seat=$playerSeat enc=$enclosureId");
            $dinos = $this->gamePlayService->getEnclosureContentsForUI($gameId, $playerSeat, $enclosureId);
            error_log("getEnclosureContents found " . count($dinos) . " dinos");
            return JsonResponse::create([
                'success' => true,
                'enclosure_id' => $enclosureId,
                'player_seat' => $playerSeat,
                'dinos' => $dinos
            ]);
        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve puntajes actuales de ambos jugadores
     * GET /api/game/scores/{game_id}
     */
    public function getScores($request) {
        try {
            if (!isset($request['game_id'])) {
                return JsonResponse::create(['error' => 'Missing game_id'], 400);
            }
            $gameId = (int)$request['game_id'];
            $scores = $this->gamePlayService->getCurrentScores($gameId);
            return JsonResponse::create([
                'success' => true,
                'scores' => [
                    'player1' => $scores[0] ?? 0,
                    'player2' => $scores[1] ?? 0,
                ]
            ]);
        } catch (Exception $e) {
            return JsonResponse::create(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Crea un estado de juego de emergencia para desarrollo
     * @param int $gameId ID del juego
     * @return array Estado del juego de emergencia
     */
    private function createEmergencyGameState(int $gameId): array 
    {
        error_log("Creating emergency game state for game $gameId");
        
        // Obtener datos básicos del juego
        $gameRepo = GameRepository::getInstance();
        $game = $gameRepo->getGameById($gameId);
        
        if (!$game) {
            // Crear datos básicos del juego
            $game = [
                'game_id' => $gameId,
                'player1_user_id' => 1,
                'player2_user_id' => 2,
                'player1_name' => 'Player 1',
                'player2_name' => 'Player 2',
                'status' => 'IN_PROGRESS',
                'round' => 1,
                'turn' => 1,
                'active_player' => 0,
                'active_bag' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        // Definición de los recintos standard
        $enclosureDefinitions = [
            ['id' => 1, 'type' => 'same_type', 'max_slots' => 8],
            ['id' => 2, 'type' => 'different_type', 'max_slots' => 6],
            ['id' => 3, 'type' => 'pairs', 'max_slots' => 6],
            ['id' => 4, 'type' => 'trio', 'max_slots' => 3],
            ['id' => 5, 'type' => 'king', 'max_slots' => 1],
            ['id' => 6, 'type' => 'solo', 'max_slots' => 1],
            ['id' => 7, 'type' => 'river', 'max_slots' => 4],
        ];
        
        // Crear recintos para jugador 1 (IDs 1-7)
        $enclosures1 = [];
        foreach ($enclosureDefinitions as $enclosure) {
            $enclosures1[] = [
                'enclosure_id' => $enclosure['id'],
                'enclosure_type' => $enclosure['type'],
                'max_slots' => $enclosure['max_slots'],
                'placements' => []
            ];
        }
        
        // Crear recintos para jugador 2 (IDs 8-14) 
        $enclosures2 = [];
        foreach ($enclosureDefinitions as $enclosure) {
            $enclosures2[] = [
                'enclosure_id' => $enclosure['id'] + 7, // Sumar 7 para obtener IDs 8-14
                'enclosure_type' => $enclosure['type'],
                'max_slots' => $enclosure['max_slots'],
                'placements' => []
            ];
        }
        
        // Crear datos de bolsa de prueba
        $defaultBag = [
            ['dino_id' => 101, 'species_id' => 1, 'name' => 'T-Rex', 'img' => 'trex.png', 'dino_color' => 'red'],
            ['dino_id' => 102, 'species_id' => 2, 'name' => 'Stegosaurus', 'img' => 'stego.png', 'dino_color' => 'green'],
            ['dino_id' => 103, 'species_id' => 3, 'name' => 'Triceratops', 'img' => 'trice.png', 'dino_color' => 'blue'],
            ['dino_id' => 104, 'species_id' => 4, 'name' => 'Brachiosaurus', 'img' => 'brachio.png', 'dino_color' => 'yellow']
        ];
        
        return [
            'game' => $game,
            'players' => [
                0 => [
                    'user_id' => $game['player1_user_id'],
                    'username' => isset($game['player1_name']) ? $game['player1_name'] : 'Player 1',
                    'bag' => ['dinos' => $defaultBag],
                    'board' => ['enclosures' => $enclosures1],
                    'score' => 0
                ],
                1 => [
                    'user_id' => $game['player2_user_id'],
                    'username' => isset($game['player2_name']) ? $game['player2_name'] : 'Player 2',
                    'bag' => ['dinos' => $defaultBag],
                    'board' => ['enclosures' => $enclosures2],
                    'score' => 0
                ]
            ],
            'last_die_roll' => [
                'die_roll_id' => 1,
                'game_id' => $gameId,
                'player_seat' => 0,
                'roll_value' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ],
            'is_emergency_state' => true,
            'debug_note' => 'Este es un estado de emergencia creado para desarrollo'
        ];
    }
}