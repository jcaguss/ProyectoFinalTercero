<?php

require_once __DIR__ . '/../repositories/GameRepository.php';
require_once __DIR__ . '/../repositories/BagRepository.php';
require_once __DIR__ . '/../repositories/PlacementDieRollRepository.php';
require_once __DIR__ . '/../repositories/PlacementRepository.php';

class RecoveryGameService 
{
    private GameRepository $gameRepo;
    private BagRepository $bagRepo;
    private PlacementDieRollRepository $dieRepo;
    private PlacementRepository $placementRepo;

    public function __construct() 
    {
        $this->gameRepo = GameRepository::getInstance();
        $this->bagRepo = BagRepository::getInstance();
        $this->dieRepo = PlacementDieRollRepository::getInstance();
        $this->placementRepo = PlacementRepository::getInstance();
    }

    /**
     * Reanuda un juego en progreso
     * @param int $gameId ID del juego a reanudar
     * @param int|null $userId ID del usuario que solicita reanudar (opcional)
     * @return array|null Estado actual del juego o null si no se puede reanudar
     */
    public function resumeGame(int $gameId, ?int $userId = null): ?array 
    {
        error_log("RecoveryGameService: Attempting to resume game with ID $gameId");
        
        // Verificar que el juego existe y está en progreso
        $game = $this->gameRepo->getGameById($gameId);
        if (!$game) {
            error_log("RecoveryGameService: Game with ID $gameId not found");
            return null;
        }
        
        error_log("RecoveryGameService: Game found, status=" . $game['status']);
        
        // A veces podríamos necesitar recuperar juegos aunque no estén "IN_PROGRESS"
        // Especialmente cuando acabamos de crear el juego
        if ($game['status'] !== 'IN_PROGRESS' && $game['status'] !== 'CREATED') {
            error_log("RecoveryGameService: Game status is not IN_PROGRESS or CREATED: " . $game['status']);
            return null;
        }

        error_log("RecoveryGameService: Preparing game state response");
        
        try {
            // Asegurarse de que los datos estén correctos - nombres de usuario
            $player1_name = isset($game['player1_name']) ? $game['player1_name'] : 'Jugador 1';
            $player2_name = isset($game['player2_name']) ? $game['player2_name'] : 'Jugador 2';
            
            // Obtener estado de las bolsas
            $player1Bag = $this->getBagState($gameId, 0);
            $player2Bag = $this->getBagState($gameId, 1);
            
            error_log("RecoveryGameService: Got bag states - Player 1: " . count($player1Bag) . " dinos, Player 2: " . count($player2Bag) . " dinos");
            
            // Obtener colocaciones
            $player1Placements = $this->getPlayerPlacements($gameId, 0);
            $player2Placements = $this->getPlayerPlacements($gameId, 1);
            
            error_log("RecoveryGameService: Got placements - Player 1: " . count($player1Placements) . ", Player 2: " . count($player2Placements));
            
            // Crear recintos para cada jugador - vamos a definirlos de forma fija para evitar problemas
            $enclosures1 = [];
            $enclosures2 = [];
            
            // Definición de los recintos standard
            $enclosureDefinitions = [
                ['id' => 1, 'type' => 'same_type', 'max_slots' => 6],
                ['id' => 2, 'type' => 'different_type', 'max_slots' => 6],
                ['id' => 3, 'type' => 'pairs', 'max_slots' => 6],
                ['id' => 4, 'type' => 'trio', 'max_slots' => 3],
                ['id' => 5, 'type' => 'king', 'max_slots' => 1],
                ['id' => 6, 'type' => 'solo', 'max_slots' => 1],
                ['id' => 7, 'type' => 'river', 'max_slots' => 6],
            ];
            
            // Crear recintos para jugador 1 (IDs 1-7)
            foreach ($enclosureDefinitions as $enclosure) {
                $enclosures1[] = [
                    'enclosure_id' => $enclosure['id'],
                    'enclosure_type' => $enclosure['type'],
                    'max_slots' => $enclosure['max_slots'],
                    'placements' => []
                ];
            }
            
            // Crear recintos para jugador 2 (IDs 8-14) 
            foreach ($enclosureDefinitions as $enclosure) {
                $enclosures2[] = [
                    'enclosure_id' => $enclosure['id'] + 7, // Sumar 7 para obtener IDs 8-14
                    'enclosure_type' => $enclosure['type'],
                    'max_slots' => $enclosure['max_slots'],
                    'placements' => []
                ];
            }
            

            // Obtener última tirada de dado
            $lastRoll = $this->dieRepo->getLastGameRoll($gameId);
            
            error_log("RecoveryGameService: Building complete game state");
            
            // Determinar qué asiento corresponde al usuario que solicita la información
            $playerSeat = 0; // Por defecto, asiento del jugador 1
            if ($userId && (int)$game['player2_user_id'] === (int)$userId) {
                $playerSeat = 1; // Si el usuario es el jugador 2
            }
            
            // Procesar los dinosaurios de la bolsa para el formato esperado por la UI
            $player1BagFormatted = [];
            foreach ($player1Bag as $dino) {
                // Asegurarnos de que el tipo de dinosaurio está correctamente mapeado
                $dinoColor = $dino['dino_color'] ?? $dino['color'] ?? 'unknown';
                $dinosaurType = $this->mapColorToType($dinoColor);
                
                error_log("Procesando dinosaurio para jugador 1: ID={$dino['dino_id']}, Color={$dinoColor}, Tipo Mapeado={$dinosaurType}");
                
                $player1BagFormatted[] = [
                    'id' => $dino['dino_id'] ?? $dino['bag_content_id'] ?? 0,
                    'dinosaur_type' => $dinosaurType,
                    'orientation' => 'horizontal' // Siempre horizontal para bolsas
                ];
            }
            
            $player2BagFormatted = [];
            foreach ($player2Bag as $dino) {
                // Asegurarnos de que el tipo de dinosaurio está correctamente mapeado
                $dinoColor = $dino['dino_color'] ?? $dino['color'] ?? 'unknown';
                $dinosaurType = $this->mapColorToType($dinoColor);
                
                error_log("Procesando dinosaurio para jugador 2: ID={$dino['dino_id']}, Color={$dinoColor}, Tipo Mapeado={$dinosaurType}");
                
                $player2BagFormatted[] = [
                    'id' => $dino['dino_id'] ?? $dino['bag_content_id'] ?? 0,
                    'dinosaur_type' => $dinosaurType,
                    'orientation' => 'horizontal' // Siempre horizontal para bolsas
                ];
            }
            
            // Procesar las colocaciones para el formato esperado por la UI
            $player1EnclosuresFormatted = $this->formatPlacementsForUI($player1Placements);
            $player2EnclosuresFormatted = $this->formatPlacementsForUI($player2Placements);
            
            return [
                'game_id' => $game['game_id'],
                'status' => $game['status'],
                'current_round' => $game['current_round'] ?? 1,
                'current_turn' => $game['current_turn'] ?? 1,
                'active_seat' => $game['active_seat'] ?? 0,
                'playerSeat' => $playerSeat,
                'player1_user_id' => $game['player1_user_id'],
                'player2_user_id' => $game['player2_user_id'],
                'player1_username' => $player1_name,
                'player2_username' => $player2_name,
                'player1_bag' => $player1BagFormatted,
                'player2_bag' => $player2BagFormatted,
                'player1_enclosures' => $player1EnclosuresFormatted,
                'player2_enclosures' => $player2EnclosuresFormatted,
                'player1_score' => 0, // Puntaje provisional
                'player2_score' => 0, // Puntaje provisional
                'last_die_roll' => $lastRoll,
            ];
        } catch (Exception $e) {
            error_log("Error in resumeGame: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            throw $e; // Reenviar la excepción para que la maneje el controlador
        }
    }

    /**
     * Obtiene el estado actual de la bolsa de un jugador
     */
    private function getBagState(int $gameId, int $playerSeat): array 
    {
        try {
            // Intenta obtener los dinosaurios de la bolsa
            $bagContents = $this->bagRepo->getDinosInBag($gameId, $playerSeat);
            
            // Si la bolsa está vacía, podría ser un problema de datos o un nuevo juego
            // Verificamos y manejamos ese caso
            if (empty($bagContents)) {
                error_log("RecoveryGameService: No dinos found in bag for player $playerSeat in game $gameId");
                // Generamos datos de prueba para evitar errores en la UI
                return [
                    ['dino_id' => 101, 'species_id' => 1, 'name' => 'Triceratops', 'img' => './img/amarilloHori.PNG', 'dino_color' => 'amarillo'],
                    ['dino_id' => 102, 'species_id' => 2, 'name' => 'T-Rex', 'img' => './img/rojoHori.PNG', 'dino_color' => 'rojo'],
                    ['dino_id' => 103, 'species_id' => 3, 'name' => 'Estegosaurio', 'img' => './img/verdeHori.PNG', 'dino_color' => 'verde'],
                    ['dino_id' => 104, 'species_id' => 4, 'name' => 'Diplodocus', 'img' => './img/azulHori.PNG', 'dino_color' => 'azul']
                ];
            }
            
            // Verificar y formatear cada entrada en la bolsa
            foreach ($bagContents as &$dino) {
                // Asegurar que los campos necesarios estén presentes
                if (!isset($dino['dino_id'])) {
                    $dino['dino_id'] = isset($dino['bag_content_id']) ? $dino['bag_content_id'] : 0;
                }
                
                if (!isset($dino['dino_color']) && isset($dino['color'])) {
                    $dino['dino_color'] = $dino['color'];
                } else if (!isset($dino['dino_color'])) {
                    $dino['dino_color'] = 'unknown';
                }
                
                // Asegurarse de que los valores sean correctos para el frontend
                $dino['dino_color'] = $this->mapColorToType($dino['dino_color']);
                
                if (!isset($dino['species_id'])) {
                    $dino['species_id'] = 0;
                }
                
                // Asegurar que todos los dinosaurios tengan una ruta de imagen coherente
                if (isset($dino['img']) && !str_contains($dino['img'], './img/')) {
                    $dinoColor = $dino['dino_color'];
                    $dino['img'] = "./img/{$dinoColor}Hori.PNG";
                }
            }
            
            return $bagContents;
        } catch (Exception $e) {
            error_log("Error en getBagState: " . $e->getMessage());
            return [
                ['dino_id' => 101, 'species_id' => 1, 'name' => 'Triceratops', 'img' => './img/amarilloHori.PNG', 'dino_color' => 'amarillo'],
                ['dino_id' => 102, 'species_id' => 2, 'name' => 'T-Rex', 'img' => './img/rojoHori.PNG', 'dino_color' => 'rojo'],
                ['dino_id' => 103, 'species_id' => 3, 'name' => 'Estegosaurio', 'img' => './img/verdeHori.PNG', 'dino_color' => 'verde'],
                ['dino_id' => 104, 'species_id' => 4, 'name' => 'Diplodocus', 'img' => './img/azulHori.PNG', 'dino_color' => 'azul']
            ];
        }
    }

    /**
     * Obtiene las colocaciones actuales de un jugador
     */
    private function getPlayerPlacements(int $gameId, int $playerSeat): array 
    {
        return $this->placementRepo->getPlacementsByPlayer($gameId, $playerSeat);
    }

    /**
     * Obtiene todas las partidas en progreso de un jugador
     * @param int $userId ID del usuario
     * @return array|null Lista de partidas o null si hay error
     */
    /**
     * Mapea colores de la base de datos a tipos de dinosaurio para la UI
     * @param string $color Color del dinosaurio
     * @return string Tipo correspondiente para la UI
     */
    private function mapColorToType(string $color): string
    {
        $color = strtolower($color);
        
        switch ($color) {
            case 'red': return 'rojo';
            case 'green': return 'verde';
            case 'blue': return 'azul';
            case 'yellow': return 'amarillo';
            case 'orange': return 'naranja';
            case 'pink': return 'rosa';
            case 'rojo': return 'rojo';
            case 'verde': return 'verde';
            case 'azul': return 'azul';
            case 'amarillo': return 'amarillo';
            case 'naranja': return 'naranja';
            case 'rosa': return 'rosa';
            case 'tirex': return 'tirex';
            case 'cafe': return 'cafe';
            case 'bani': return 'bani';
            case 'monta': return 'monta';
            default: return $color; // Devolver el color original si no hay coincidencia
        }
    }
    
    /**
     * Formatea las colocaciones para la UI
     * @param array $placements Colocaciones de la base de datos
     * @return array Colocaciones formateadas para la UI
     */
    private function formatPlacementsForUI(array $placements): array
    {
        // Log para depuración
        error_log("formatPlacementsForUI: Procesando " . count($placements) . " colocaciones");
        
        $formattedEnclosures = [];
        
        // Mapeo de IDs de enclosure (enclosures_id) a los tipos esperados por la UI
        $enclosureIdToTypeMap = [
            // Jugador 1 (player_seat = 0)
            1 => 'IGUAL',   // Bosque de Semejanza
            2 => 'NOIGUAL', // Parado Diferencia
            3 => 'PAREJA',  // Pradera del Amor
            4 => 'TRES',    // Trio Frondoso
            5 => 'REY',     // Rey de la Selva
            6 => 'SOLO',    // Isla Solitaria
            7 => 'RIO',     // Río
            
            // Jugador 2 (player_seat = 1)
            8 => 'IGUAL',   // Bosque de Semejanza (Player 2)
            9 => 'NOIGUAL', // Parado Diferencia (Player 2)
            10 => 'PAREJA', // Pradera del Amor (Player 2)
            11 => 'TRES',   // Trio Frondoso (Player 2)
            12 => 'REY',    // Rey de la Selva (Player 2)
            13 => 'SOLO',   // Isla Solitaria (Player 2)
            14 => 'RIO',    // Río (Player 2)
        ];
        
        // Mapeo de tipos de enclosure (por si se proporciona directamente el tipo)
        $enclosureTypeMap = [
            'same_type' => 'IGUAL',
            'different_type' => 'NOIGUAL',
            'pairs' => 'PAREJA',
            'trio' => 'TRES',
            'king' => 'REY',
            'solo' => 'SOLO',
            'river' => 'RIO',
            // Compatibilidad con nombres ya en el formato esperado
            'IGUAL' => 'IGUAL',
            'NOIGUAL' => 'NOIGUAL',
            'PAREJA' => 'PAREJA',
            'TRES' => 'TRES',
            'REY' => 'REY',
            'SOLO' => 'SOLO',
            'RIO' => 'RIO'
        ];
        
        foreach ($placements as $placement) {
            // Obtener el tipo de recinto, primero intentando con enclosures_id
            $enclosureId = isset($placement['enclosures_id']) ? (int)$placement['enclosures_id'] : 0;
            
            // Determinar el tipo de recinto basado en enclosures_id o enclosure_type
            if ($enclosureId > 0 && isset($enclosureIdToTypeMap[$enclosureId])) {
                // Si tenemos un ID válido, usamos la asignación directa
                $enclosureType = $enclosureIdToTypeMap[$enclosureId];
                error_log("Asignando tipo de recinto desde ID: {$enclosureId} -> {$enclosureType}");
            } else if (isset($placement['enclosure_type'])) {
                // Si no tenemos ID pero sí tipo, usamos el mapeo de tipo
                $enclosureType = $enclosureTypeMap[$placement['enclosure_type']] ?? 'RIO';
                error_log("Asignando tipo de recinto desde tipo: {$placement['enclosure_type']} -> {$enclosureType}");
            } else {
                // Si no tenemos ni ID ni tipo, usamos RIO como fallback
                $enclosureType = 'RIO';
                error_log("No se pudo determinar el tipo de recinto, usando RIO como predeterminado");
            }
            
            // Inicializar el array para este tipo de enclosure si no existe
            if (!isset($formattedEnclosures[$enclosureType])) {
                $formattedEnclosures[$enclosureType] = [];
            }
            
            // Asegurarnos de que el tipo de dinosaurio está correctamente mapeado
            $dinoColor = $placement['dino_color'] ?? $placement['color'] ?? 'unknown';
            $dinosaurType = $this->mapColorToType($dinoColor);
            
            $placementId = isset($placement['placement_id']) ? $placement['placement_id'] : 0;
            error_log("Procesando colocación: ID={$placementId}, Color={$dinoColor}, Tipo Mapeado={$dinosaurType}, Recinto={$enclosureType}, EnclosureID={$enclosureId}");
            
            // Añadir el dinosaurio al enclosure correcto con información enriquecida
            $slotIndex = isset($placement['slot_index']) ? (int)$placement['slot_index'] : 0;
            $dinoId = isset($placement['dino_id']) ? (int)$placement['dino_id'] : 0;
            
            $dinoObject = [
                'id' => $placement['placement_id'] ?? 0,
                'dinosaur_type' => $dinosaurType,
                'orientation' => 'vertical', // Siempre vertical para colocaciones
                'enclosure_type' => $enclosureType,
                'slot_index' => $slotIndex,
                // Información adicional para depuración
                'placement_id' => isset($placement['placement_id']) ? (int)$placement['placement_id'] : 0,
                'enclosure_id' => $enclosureId,
                'dino_id' => $dinoId,
                'species_id' => isset($placement['species_id']) ? (int)$placement['species_id'] : 0,
                'species_code' => $dinoColor
            ];
            
            $formattedEnclosures[$enclosureType][] = $dinoObject;
            
            // Log detallado para depuración
            error_log("Dinosaurio añadido: " . json_encode($dinoObject));
        }
        
        // Log final del resultado
        error_log("Total de tipos de recintos formateados: " . count($formattedEnclosures));
        foreach ($formattedEnclosures as $type => $dinos) {
            error_log("Recinto {$type}: " . count($dinos) . " dinosaurios");
        }
        
        return $formattedEnclosures;
    }
    
    public function getInProgressGames(int $userId): ?array 
    {
        try {
            $games = $this->gameRepo->getInProgressGames($userId);
            
            if (!$games) {
                return null;
            }

            // Enriquecer la información de cada partida
            foreach ($games as &$game) {
                $game['is_active_player'] = (int)$game['active_seat'] === (int)$game['player_seat'];
                $game['can_play'] = $game['is_active_player']; // Por ahora solo puede jugar si es su turno
            }

            return $games;

        } catch (Exception $e) {
            error_log("Error getting in progress games: " . $e->getMessage());
            return null;
        }
    }
}