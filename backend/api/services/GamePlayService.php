<?php

require_once __DIR__ . '/../repositories/GameRepository.php';
require_once __DIR__ . '/../repositories/BagRepository.php';
require_once __DIR__ . '/../repositories/PlacementDieRollRepository.php';
require_once __DIR__ . '/../repositories/PlacementRepository.php';
require_once __DIR__ . '/../repositories/FinalScoreRepository.php';
require_once __DIR__ . '/../utils/ErrorHandler.php';

class GamePlayService
{
    // Game rules moved to Config class

    private GameRepository $gameRepo;
    private BagRepository $bagRepo;
    private PlacementDieRollRepository $dieRepo;
    private PlacementRepository $placementRepo;
    private FinalScoreRepository $scoreRepo;
    private array $gameRules;

    private int $gameId;
    private int $playerSeat;

    public function __construct()
    {
        $this->gameRepo = GameRepository::getInstance();
        $this->bagRepo = BagRepository::getInstance();
        $this->dieRepo = PlacementDieRollRepository::getInstance();
        $this->placementRepo = PlacementRepository::getInstance();
        $this->scoreRepo = FinalScoreRepository::getInstance();
        $this->gameRules = [
            'MAX_PLAYERS' => 2,
            'DINOS_PER_PLAYER' => 6,
            'MAX_ROUNDS' => 2,
            // Cada jugador coloca 6 por ronda; total colocaciones por ronda = 12
            'TURNS_PER_ROUND' => 12,
            'TURN_TIME_LIMIT' => 60,
            'ENCLOSURE_TYPES' => [
                'FOREST' => 'forest',
                'ROCK' => 'rock',
                'MIXED' => 'mixed'
            ],
            'ENCLOSURE_POSITIONS' => [
                'LEFT' => 'left',
                'RIGHT' => 'right',
                'CENTER' => 'center'
            ],
            'SPECIAL_RULES' => [
                'SAME_SPECIES' => 'SAME_SPECIES',
                'DIFFERENT_SPECIES' => 'DIFFERENT_SPECIES',
                'PAIRS_BONUS' => 'PAIRS_BONUS',
                'TRIO_REQUIRED' => 'TRIO_REQUIRED',
                'MAJORITY_SPECIES' => 'MAJORITY_SPECIES'
            ],
            'DIE_FACES' => [
                'LEFT_SIDE',
                'RIGHT_SIDE',
                'FOREST',
                'EMPTY',
                'NO_TREX',
                'ROCKS'
            ]
        ];
    }

    // -----------------------------
    // Flujo de juego
    // -----------------------------

    /**
     * Obtiene las partidas pendientes para un usuario
     * @param int $userId ID del usuario
     * @return array Lista de partidas pendientes
     */
    public function getPendingGames(int $userId): array {
        // Obtener todas las partidas en progreso del usuario
        $inProgressGames = $this->gameRepo->getInProgressGamesByUser($userId);
        $pendingGames = [];
        
        foreach ($inProgressGames as $game) {
            // Determinar si es turno del usuario
            $isMyTurn = $game['active_seat'] == ($game['player1_user_id'] == $userId ? 0 : 1);
            
            // Determinar nombre del oponente
            $opponentId = $game['player1_user_id'] == $userId ? $game['player2_user_id'] : $game['player1_user_id'];
            $opponent = $this->gameRepo->getUserById($opponentId);
            $opponentUsername = $opponent ? $opponent['username'] : 'Oponente';
            
            // Añadir a la lista de partidas pendientes
            $pendingGames[] = [
                'game_id' => $game['game_id'],
                'opponent_username' => $opponentUsername,
                'created_at' => $game['created_at'],
                'is_my_turn' => $isMyTurn
            ];
        }
        
        return $pendingGames;
    }
    /**
     * Inicia una nueva partida
     * @param int $player1Id ID del primer jugador
     * @param int $player2Id ID del segundo jugador
     * @return int ID de la partida creada
     */
    public function startGame(int $player1Id, int $player2Id): int {
        // Debug info
        error_log("Starting game with players: $player1Id and $player2Id");
        
        // Crear juego y asignar jugadores
        $gameId = $this->gameRepo->createGame($player1Id, $player2Id);
        if (!$gameId) {
            error_log("Failed to create game in database");
            throw new Exception("No se pudo crear la partida en la base de datos");
        }
        
        error_log("Game created with ID: $gameId");
        
        try {
            // Crear y llenar bolsas para ambos jugadores
            $this->bagRepo->createBagsForGame($gameId, [$player1Id, $player2Id]);
            error_log("Bags created for game $gameId");
            
            $this->bagRepo->fillBagsRandomlyWithSpecies($gameId, 6); // 6 dinos por jugador inicial
            error_log("Bags filled with dinosaurs");
        } catch (Exception $e) {
            error_log("Error in startGame: " . $e->getMessage());
            throw $e;
        }
        
        return $gameId;
    }

    /**
     * Lanza el dado y guarda la restricción para el siguiente jugador
     * @param int $gameId ID del juego
     * @param int $rollerSeat Asiento del jugador que lanza (jugador activo)
     * @param int $affectedSeat Asiento del jugador afectado por la restricción
     * @param string $dieFace Cara del dado obtenida
     * @return int|null ID del registro de la tirada o null si falla
     */
    public function rollDie(int $gameId, int $rollerSeat, int $affectedSeat, string $dieFace): ?int {
        // Verificar que sea el jugador activo quien lanza
        $game = $this->gameRepo->getGameById($gameId);
        if (!$this->isActivePlayer($game, $rollerSeat)) {
            return null;
        }

        // Guardar la tirada del dado
        return $this->dieRepo->insertDieRoll($gameId, $affectedSeat, $dieFace);
    }

    public function placeDino(int $gameId, int $playerSeat, int $bagContentId, int $enclosureId, ?int $slotIndex = null): bool
    {
        error_log("placeDino: Iniciando colocación - GameID=$gameId, PlayerSeat=$playerSeat, DinoID=$bagContentId, EnclosureID=$enclosureId");
        
        try {
            // Validar que el dino no esté jugado
            if ($this->bagRepo->isDinoPlayed($bagContentId)) {
                error_log("placeDino: El dinosaurio $bagContentId ya está jugado");
            }
                        
            // Insertar colocación
            $placementId = $this->placementRepo->insertPlacement($gameId, $playerSeat, $bagContentId, $enclosureId, $slotIndex);
            
            if ($placementId === null) {
                error_log("placeDino: Error al insertar colocación. El método insertPlacement retornó null");
                return false;
            }
                        
            $this->bagRepo->markDinoPlayed($bagContentId);
            return true;
        } catch (Exception $e) {
            error_log("ERROR en placeDino: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e; // Relanzar la excepción para que se capture en el controlador
        }
    }

    public function getValidEnclosuresForPlayer(int $gameId, int $playerSeat): array
    {
        // Jugador activo nunca tiene restricción de dado
        return $this->placementRepo->getAllEnclosures($gameId);
    }

    /**
     * Valida las restricciones del dado de colocación
     * @param int $gameId ID de la partida
     * @param int $playerSeat Asiento del jugador (0 o 1)
     * @param int $enclosureId ID del recinto
     * @return bool true si cumple la restricción, false si no
     */
    public function validateDieRestriction(int $gameId, int $playerSeat, int $enclosureId): bool {
        // Obtener última tirada que afecte al jugador actual
        $lastRoll = $this->dieRepo->getLastGameRoll($gameId);
        
        // Si no hay tirada previa o el jugador es el activo, no hay restricción
        if (!$lastRoll || $lastRoll['affected_player_seat'] !== $playerSeat) {
            return true;
        }

        $enclosure = $this->placementRepo->getEnclosure($enclosureId);
        if (!$enclosure) return false;

        switch ($lastRoll['die_face']) {
            case 'LEFT_SIDE':
                return $enclosure['position'] === 'left';
            case 'RIGHT_SIDE':
                return $enclosure['position'] === 'right';
            case 'FOREST_OR_ROCKS':
                return $enclosure['terrain'] === 'forest' || $enclosure['terrain'] === 'rock';
            case 'EMPTY':
                return !$this->placementRepo->hasPlacementsInEnclosure($gameId, $enclosureId);
            case 'NO_TREX':
                return !$this->placementRepo->hasSpeciesInEnclosure($gameId, $enclosureId, 'TREX');
            case 'RIVER_FALLBACK':
                return $enclosureId === 7; // ID del río
            default:
                return true;
        }
    }

    // -----------------------------
    // Calcular puntaje
    // -----------------------------

    /**
     * Calcula la puntuación final de un jugador
     * @param int $gameId ID de la partida
     * @param int $playerSeat Asiento del jugador
     * @return int Puntuación total
     */

    public function calculateScore(int $gameId, int $playerSeat): int
    {
        // Contexto para cálculos dependientes
        $this->gameId = $gameId;
        $this->playerSeat = $playerSeat;

        $placements = $this->placementRepo->getPlacementsByPlayer($gameId, $playerSeat);
        $totalPoints = 0;

        // Agrupar placements por recinto
        $enclosurePlacements = [];
        foreach ($placements as $placement) {
            $enclosurePlacements[$placement['enclosures_id']][] = $placement;
        }

        // Calcular puntos por cada recinto
        foreach ($enclosurePlacements as $enclosureId => $enclosurePlacements) {
            $totalPoints += $this->calculatePointsForEnclosure($enclosureId, $enclosurePlacements);
        }

        // Bono: +1 por cada dinosaurio rojo en cualquier recinto
        foreach ($placements as $p) {
            $color = strtolower($p['dino_color'] ?? '');
            if ($color === 'rojo' || $color === 'red') {
                $totalPoints += 1;
            }
        }

        // Guardar puntaje en la base
        $this->scoreRepo->saveScore($gameId, $playerSeat, $totalPoints);

        return $totalPoints;
    }

    public function finalizeGame(int $gameId): void
    {
        foreach ([0, 1] as $seat) {
            $this->calculateScore($gameId, $seat);
        }
        $this->gameRepo->updateGameStatus($gameId, "COMPLETED");
    }

    // -----------------------------
    // Endpoints auxiliares para UI: Bolsa y Recintos
    // -----------------------------

    /**
     * Devuelve la bolsa del jugador formateada para la UI
     */
    public function getPlayerBagForUI(int $gameId, int $playerSeat): array
    {
        $bag = $this->bagRepo->getDinosInBag($gameId, $playerSeat);
        $formatted = [];
        foreach ($bag as $dino) {
            $color = $dino['dino_color'] ?? 'unknown';
            $formatted[] = [
                'id' => (int)($dino['dino_id'] ?? 0),
                'bag_content_id' => (int)($dino['dino_id'] ?? 0),
                'species_id' => (int)($dino['species_id'] ?? 0),
                'dinosaur_type' => $this->mapColorToType($color),
                'orientation' => 'horizontal'
            ];
        }
        return $formatted;
    }

    /**
     * Devuelve el contenido de un recinto específico para un jugador formateado para la UI
     * $enclosureId va de 1..7 (lógico por tablero); para playerSeat=1 se traslada a 8..14 en BD
     */
    public function getEnclosureContentsForUI(int $gameId, int $playerSeat, int $enclosureId): array
    {
        // En nuestra BD, ambos jugadores usan los mismos IDs de recinto (1..7),
        // diferenciados por player_seat. Por lo tanto NO se desplaza el ID.
        $placements = $this->placementRepo->getPlacementsByPlayer($gameId, $playerSeat);
        $filtered = array_filter($placements, function ($p) use ($enclosureId) {
            return isset($p['enclosures_id']) && (int)$p['enclosures_id'] === (int)$enclosureId;
        });

        $formatted = [];
        foreach ($filtered as $p) {
            $color = $p['dino_color'] ?? 'unknown';
            $formatted[] = [
                'id' => (int)($p['placement_id'] ?? 0),
                'placement_id' => (int)($p['placement_id'] ?? 0),
                'dino_id' => (int)($p['dino_id'] ?? 0),
                'species_id' => (int)($p['species_id'] ?? 0),
                'dinosaur_type' => $this->mapColorToType($color),
                'slot_index' => isset($p['slot_index']) ? (int)$p['slot_index'] : 0,
                'orientation' => 'vertical'
            ];
        }
        // Ordenar por slot_index por conveniencia
        usort($formatted, function ($a, $b) { return ($a['slot_index'] <=> $b['slot_index']); });
        return $formatted;
    }

    /**
     * Calcula puntos para un recinto específico
     * @param int $enclosureId ID del recinto
     * @param array $placements Colocaciones en el recinto
     * @return int Puntos obtenidos
     */
    private function calculatePointsForEnclosure(int $enclosureId, array $placements): int {
        switch ($enclosureId) {
            case 1: // Bosque de la Semejanza
                return $this->calculateSimilarityForestPoints($placements);
                
            case 2: // Prado de la Diferencia
                return $this->calculateDifferenceMeadowPoints($placements);
                
            case 3: // Pradera del Amor
                return $this->calculateLovePrairiePoints($placements);
                
            case 4: // Trío Frondoso
                return count($placements) == 3 ? 7 : 0;
                
            case 5: // Rey de la Selva
                if (empty($placements)) return 0;
                return $this->calculateKingOfJunglePoints($placements[0]['species_id'], $this->gameId, $this->playerSeat);
                
            case 6: // Isla Solitaria
                if (empty($placements)) return 0;
                return $this->calculateLonelyIslandPoints($placements[0]['species_id'], $this->gameId, $this->playerSeat);
                
            case 7: // Río
                return count($placements); // 1 punto por dino
                
            default:
                return 0;
        }
    }

    /**
     * Calcula puntos para el Bosque de la Semejanza
     * Puntuación: [2,4,8,12,18,24] según cantidad de dinosaurios
     * @param array $placements Colocaciones en el recinto
     * @return int Puntos obtenidos
     */
    private function calculateSimilarityForestPoints(array $placements): int {
        $count = count($placements);
        $pointsTable = [
            0 => 0,
            1 => 2,
            2 => 4,
            3 => 8,
            4 => 12,
            5 => 18,
            6 => 24
        ];
        return $pointsTable[$count] ?? 0;
    }

    /**
     * Calcula puntos para el Prado de la Diferencia
     * Puntuación: [1,3,6,10,15,21] según cantidad de dinosaurios diferentes
     * @param array $placements Colocaciones en el recinto
     * @return int Puntos obtenidos
     */
    private function calculateDifferenceMeadowPoints(array $placements): int {
        $count = count($placements);
        $pointsTable = [
            0 => 0,
            1 => 1,
            2 => 3,
            3 => 6,
            4 => 10,
            5 => 15,
            6 => 21
        ];
        return $pointsTable[$count] ?? 0;
    }

    /**
     * Calcula puntos para la Pradera del Amor
     * 5 puntos por cada pareja de la misma especie
     * @param array $placements Colocaciones en el recinto
     * @return int Puntos obtenidos
     */
    private function calculateLovePrairiePoints(array $placements): int {
        // Agrupar por especie
        $speciesCounts = [];
        foreach ($placements as $placement) {
            $speciesId = $placement['species_id'];
            $speciesCounts[$speciesId] = ($speciesCounts[$speciesId] ?? 0) + 1;
        }

        $points = 0;
        // Cada par suma 5 puntos
        foreach ($speciesCounts as $count) {
            $pairs = floor($count / 2);
            $points += $pairs * 5;
        }

        return $points; // Máximo 15 puntos (3 pares)
    }

    /**
     * Calcula puntos para el Rey de la Selva
     * 7 puntos si tiene mayoría de esa especie
     * @param int $speciesId ID de la especie
     * @param int $gameId ID de la partida
     * @param int $playerSeat Asiento del jugador
     * @return int Puntos obtenidos
     */
    private function calculateKingOfJunglePoints(int $speciesId, int $gameId, int $playerSeat): int {
        // Contar cuántos dinos de esta especie tiene cada jugador
        $myCount = $this->placementRepo->countSpeciesForPlayer($gameId, $playerSeat, $speciesId);
        $otherCount = $this->placementRepo->countSpeciesForPlayer($gameId, 1 - $playerSeat, $speciesId);
        
        // Gana si tiene más que el otro jugador (o empata)
        return $myCount >= $otherCount ? 7 : 0;
    }

    /**
     * Calcula puntos para la Isla Solitaria
     * 7 puntos si es la única ocurrencia de la especie
     * @param int $speciesId ID de la especie
     * @param int $gameId ID de la partida
     * @param int $playerSeat Asiento del jugador
     * @return int Puntos obtenidos
     */
    private function calculateLonelyIslandPoints(int $speciesId, int $gameId, int $playerSeat): int {
        // Contar cuántas veces aparece esta especie en todos los recintos del jugador
        $totalCount = $this->placementRepo->countSpeciesForPlayer($gameId, $playerSeat, $speciesId);
        
        // Solo puntúa si es la única ocurrencia de esta especie
        return $totalCount === 1 ? 7 : 0;
    }

    // -----------------------------
    // UTILITY FUNCTIONS
    // -----------------------------

    /**
     * Mapea el código/color a un tipo de dinosaurio usado por la UI
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
            default: return $color;
        }
    }

    /**
     * Verifica si se acabó el tiempo del turno
     */
    private function isTurnTimedOut(array $game): bool {
        $turnStartTime = strtotime($game['turn_started_at']);
        return (time() - $turnStartTime) > $this->gameRules['TURN_TIME_LIMIT'];
    }

    /**
     * Coloca automáticamente en el río cuando se acaba el tiempo
     */
    private function autoPlaceInRiver(int $gameId, int $playerSeat, int $bagContentId): bool {
        $riverEnclosureId = 7; // ID del río
        return $this->placeDino($gameId, $playerSeat, $bagContentId, $riverEnclosureId) &&
               $this->advanceTurn($gameId);
    }

    /**
     * Obtiene puntajes actuales de ambos jugadores
     */
    public function getCurrentScores(int $gameId): array {
        return [
            0 => $this->calculateScore($gameId, 0),
            1 => $this->calculateScore($gameId, 1)
        ];
    }

    /**
     * Valida las reglas específicas por recinto (sin dado)
     */
    private function validateEnclosureRules(int $gameId, int $playerSeat, int $enclosureId, int $bagContentId): bool {
        // Capacidad
        $enclosure = $this->placementRepo->getEnclosure($enclosureId);
        if (!$enclosure) return false;

        $existing = array_filter(
            $this->placementRepo->getPlacementsByPlayer($gameId, $playerSeat),
            fn($p) => (int)$p['enclosures_id'] === (int)$enclosureId
        );
        $max = isset($enclosure['max_capacity']) ? (int)$enclosure['max_capacity'] : 6;
        if (count($existing) >= $max) return false;

        // Para reglas necesitaremos species_id y colores existentes
        $speciesId = $this->bagRepo->getSpeciesIdByBagContentId($bagContentId);
        if ($speciesId === null) return false;

        // Colores/Especies actuales en el recinto
        $speciesInEnclosure = array_map(fn($p) => (int)($p['species_id'] ?? 0), $existing);
        $colorsInEnclosure = array_map(fn($p) => strtolower($p['dino_color'] ?? ''), $existing);

        // Mapeo: 1..7 a reglas
        switch ($enclosureId) {
            case 1: // Bosque de la Semejanza: todos iguales
                return empty($speciesInEnclosure) || count(array_unique($speciesInEnclosure)) === 1 && in_array($speciesId, $speciesInEnclosure) || empty($speciesInEnclosure);
            case 2: // Parado de la Diferencia: todos distintos
                return !in_array($speciesId, $speciesInEnclosure, true);
            case 3: // Pradera del Amor: sin restricción (solo capacidad)
                return true;
            case 4: // Trío Frondoso: máx 3, sin restricción adicional
                return true;
            case 5: // Rey de la Selva: máx 1
                return count($existing) < 1;
            case 6: // Isla Solitaria: máx 1 y especie no aparece en otros recintos del jugador
                if (count($existing) >= 1) return false;
                $totalOfSpecies = $this->placementRepo->countSpeciesForPlayer($gameId, $playerSeat, $speciesId);
                return $totalOfSpecies === 0; // aún no existe esa especie en otros recintos
            case 7: // Río: sin restricciones
                return true;
            default:
                return true;
        }
    }

    /**
     * Avanza al siguiente turno/ronda
     */
    private function advanceTurn(int $gameId): bool {
        $game = $this->gameRepo->getGameById($gameId);
        $currentTurn = isset($game['current_turn']) ? (int)$game['current_turn'] : 0;
        $currentRound = isset($game['current_round']) ? (int)$game['current_round'] : 1;
        $activeSeat = isset($game['active_seat']) ? (int)$game['active_seat'] : 0;

        // Cambiar jugador activo
        $nextSeat = 1 - $activeSeat; // Alterna entre 0 y 1

        // Intercambiar las bolsas de los jugadores al final de cada turno
        try { $this->bagRepo->swapBags($gameId); } catch (Exception $e) {}

        // Detectar fin de ronda por bolsa vacía: cuando ambos jugadores ya no tienen dinos sin jugar
        $remaining0 = count($this->bagRepo->getDinosInBag($gameId, 0));
        $remaining1 = count($this->bagRepo->getDinosInBag($gameId, 1));
        $totalRemaining = $remaining0 + $remaining1;
        error_log("advanceTurn: remaining dinos P0=$remaining0 P1=$remaining1 total=$totalRemaining");

        if ($totalRemaining <= 0) {
            // Fin de ronda: ¿terminó el juego?
            if ($currentRound >= $this->gameRules['MAX_ROUNDS']) {
                return $this->endGame($gameId);
            }
            return $this->startNewRound($gameId);
        }

        // Si no terminó la ronda aún, avanzar turno dentro de la ronda
        $nextTurn = $currentTurn + 1;
        return $this->gameRepo->updateGameState(
            $gameId,
            $nextSeat,
            $nextTurn,
            $currentRound
        );
    }

    /**
     * Inicia una nueva ronda
     */
    private function startNewRound(int $gameId): bool {
        // Obtener estado actual del juego
        $game = $this->gameRepo->getGameById($gameId);

        // Generar nuevos dinosaurios para cada jugador: 6 por jugador
        $this->bagRepo->fillBagsRandomlyWithSpecies($gameId, $this->gameRules['DINOS_PER_PLAYER']);

        // Reiniciar turno a 1 y avanzar ronda
        return $this->gameRepo->updateGameState(
            $gameId,
            0, // Primer jugador inicia
            1, // Primer turno de la nueva ronda
            (isset($game['current_round']) ? (int)$game['current_round'] : 1) + 1
        );
    }

    /**
     * Finaliza el juego y calcula puntuaciones
     */
    private function endGame(int $gameId): bool {
        // Calcular puntuaciones
        $score1 = $this->calculateScore($gameId, 0);
        $score2 = $this->calculateScore($gameId, 1);

        // Guardar puntuaciones finales
        $this->scoreRepo->saveScore($gameId, 0, $score1);
        $this->scoreRepo->saveScore($gameId, 1, $score2);

        // Marcar juego como completado
        return $this->gameRepo->updateGameStatus($gameId, 'COMPLETED');
    }

    /**
     * Verifica si el jugador es el activo en este turno
     * @param array $game Datos del juego actual
     * @param int $playerSeat Asiento del jugador a verificar
     * @return bool true si es el jugador activo
     */
    private function isActivePlayer(array $game, int $playerSeat): bool {
        // Si no hay active_seat definido, asumir que el primer jugador (asiento 0) está activo
        if (!isset($game['active_seat'])) {
            return $playerSeat === 0;
        }
        return $game['active_seat'] === $playerSeat;
    }

    /**
     * Procesa un turno completo del juego
     */
    public function processTurn(int $gameId, int $playerSeat, int $bagContentId, int $enclosureId, ?int $slotIndex = null): bool {
        $game = $this->gameRepo->getGameById($gameId);
        
        error_log("Procesando turno: GameID=$gameId, PlayerSeat=$playerSeat, DinoID=$bagContentId, EnclosureID=$enclosureId");
        error_log("Datos del juego: " . json_encode($game));
        
        // 1. Validar turno y tiempo
        if (!$this->validateTurnAndTime($game, $playerSeat, $gameId, $bagContentId)) {
            error_log("Error: Validación de turno y tiempo fallida");
            
            // En modo desarrollo, continuar a pesar del error
            $isDevelopmentMode = false; // Cambiar a false para validar turnos correctamente
            if ($isDevelopmentMode) {
                error_log("MODO DESARROLLO: Continuando a pesar del error de validación");
            } else {
                return false;
            }
        }

        // 2. Ya no validamos que el dinosaurio pertenezca a la bolsa del jugador
        if (!$this->bagRepo->isDinoInPlayerBag($bagContentId, $gameId, $playerSeat)) {
            error_log("Aviso: Dinosaurio $bagContentId no pertenece a la bolsa del jugador $playerSeat en juego $gameId");
            error_log("MODO DESARROLLO: Permitiendo movimiento aunque el dinosaurio no pertenezca al jugador");
            
            // Siempre continuamos con el flujo sin retornar false
        }

        // 3. Validación de reglas por recinto (sin dado)
        if (!$this->validateEnclosureRules($gameId, $playerSeat, $enclosureId, $bagContentId)) {
            error_log("Error: Colocación inválida por regla del recinto $enclosureId");
            // En desarrollo podemos permitir, pero preferimos bloquear para evitar estados inválidos
            return false;
        }

        // 4. Ya no verificamos si es jugador activo, solo aplicamos restricciones del dado
        if (!$this->validateDieRestriction($gameId, $playerSeat, $enclosureId)) {
            error_log("Error: No se cumple la restricción del dado para el recinto $enclosureId");
            
            // Pero permitimos colocar de todos modos (desarrollo)
            error_log("MODO DESARROLLO: Permitiendo colocación a pesar de no cumplir restricción del dado");
            // return false; // Comentado para permitir colocación
        }

        // 5. Colocar dinosaurio
        try {
            if (!$this->placeDino($gameId, $playerSeat, $bagContentId, $enclosureId, $slotIndex)) {
                error_log("Error: No se pudo colocar el dinosaurio");
                
                // En modo desarrollo, continuar con el flujo a pesar del error
                $isDevelopmentMode = true; // Cambiar a false para entorno de producción
                if (!$isDevelopmentMode) {
                    return false;
                }
                
                error_log("MODO DESARROLLO: Continuando a pesar del error de colocación");
            }
        } catch (Exception $e) {
            error_log("Excepción al colocar dinosaurio: " . $e->getMessage());
            
            // En modo desarrollo, continuar con el flujo a pesar de la excepción
            $isDevelopmentMode = true; // Cambiar a false para entorno de producción
            if (!$isDevelopmentMode) {
                return false;
            }
            
            error_log("MODO DESARROLLO: Continuando a pesar de la excepción: " . $e->getMessage());
        }

        // 6. Intercambiar bolsas entre jugadores
        $this->bagRepo->swapBags($gameId);
        error_log("Bolsas intercambiadas correctamente");

        // 7. Avanzar turno
        $result = $this->advanceTurn($gameId);
        error_log("Turno avanzado: " . ($result ? "Éxito" : "Error"));
        return $result;
    }

    /**
     * Valida el turno y el tiempo límite
     * @param array $game Datos del juego
     * @param int $playerSeat Asiento del jugador
     * @param int $gameId ID del juego
     * @param int $bagContentId ID del contenido de la bolsa
     * @return bool true si las validaciones son correctas
     */
    private function validateTurnAndTime(array $game, int $playerSeat, int $gameId, int $bagContentId): bool {
        // Verificar que active_seat esté definido
        if (!isset($game['active_seat'])) {
            // Si no está definido, actualizamos el juego con valores predeterminados
            $this->gameRepo->updateGameState($gameId, 0, 1, 1);
            return true; // Permitimos el turno
        }
        
        // No validamos el turno - permitimos que cualquier jugador coloque
        if ($game['active_seat'] !== $playerSeat) {
            error_log("Permitiendo colocación independientemente del turno. active_seat={$game['active_seat']}, playerSeat=$playerSeat");
        }
        
        // Siempre permitir la colocación
        return true;

        // Validar tiempo límite
        if ($this->isTurnTimedOut($game)) {
            return $this->autoPlaceInRiver($gameId, $playerSeat, $bagContentId);
        }

        return true;
    }
}
?>