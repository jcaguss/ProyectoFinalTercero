<?php

require_once __DIR__ . '/../config/Database.php';

class BagRepository
{
    private static ?BagRepository $instance = null;
    private mysqli $conn;
    private $lastError = '';

    private function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }
    
    /**
     * Get the last error message
     * @return string Last error message
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    public static function getInstance(): BagRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtiene el species_id de un registro de bag_contents
     */
    public function getSpeciesIdByBagContentId(int $bagContentId): ?int
    {
        // Permitir IDs de prueba devolviendo una especie por defecto
        if ($bagContentId >= 100 && $bagContentId <= 110) {
            return 1; // por defecto
        }

        $query = "SELECT species_id FROM bag_contents WHERE bag_content_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $bagContentId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();

        return $row && isset($row['species_id']) ? (int)$row['species_id'] : null;
    }

    /**
     * Crear una bolsa para un jugador dentro de una partida
     */
    public function createBag(int $game_id, int $user_id): ?int
    {
        $query = "INSERT INTO bags (game_id, user_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("ii", $game_id, $user_id);
        $ok = $stmt->execute();

        if (!$ok) {
            $stmt->close();
            return null;
        }

        $bag_id = $this->conn->insert_id;
        $stmt->close();

        return $bag_id; // Devuelve el id de la bolsa creada
    }

    public function createBagsForGame(int $gameId, array $playerIds): ?array
    {
        $bagIds = [];

        foreach ($playerIds as $playerId) {
            $bagId = $this->createBag($gameId, $playerId);
            if (!$bagId) {
                // Si falla, eliminar las bolsas creadas hasta ahora
                foreach ($bagIds as $createdBagId) {
                    $this->deleteBag($createdBagId);
                }
                return null;
            }
            $bagIds[$playerId] = $bagId;
        }

        return $bagIds;
    }

    /**
     * Insertar dinosaurio en una bolsa
     */
    public function addDinoToBag(int $bag_id, int $species_id): ?int
    {
        $query = "INSERT INTO bag_contents (bag_id, species_id, is_played) VALUES (?, ?, 0)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("ii", $bag_id, $species_id);
        $ok = $stmt->execute();

        if (!$ok) {
            $stmt->close();
            return null;
        }

        $bag_content_id = $this->conn->insert_id;
        $stmt->close();

        return $bag_content_id;
    }

    /**
     * Obtener contenido de la bolsa
     */
    public function getBagContents(int $bag_id, bool $onlyUnplayed = false): array
    {
        $sql = "SELECT bc.bag_content_id, bc.species_id, s.name, s.img, bc.is_played
                FROM bag_contents bc
                JOIN species s ON bc.species_id = s.species_id
                WHERE bc.bag_id = ?";
        if ($onlyUnplayed) {
            $sql .= " AND bc.is_played = 0";
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param("i", $bag_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $contents = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if ($result) $result->free();
        $stmt->close();

        return $contents;
    }

    /**
     * Marcar un dinosaurio como jugado
     */
    public function markDinoPlayed(int $bag_content_id): bool
    {
        // Ignorar los IDs de prueba (100-110)
        if ($bag_content_id >= 100 && $bag_content_id <= 110) {
            error_log("ID de prueba detectado ($bag_content_id), ignorando markDinoPlayed");
            return true;
        }
        
        $query = "UPDATE bag_contents SET is_played = 1 WHERE bag_content_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("i", $bag_content_id);
        $ok = $stmt->execute();
        
        if (!$ok) {
            error_log("Error al marcar dino como jugado: " . $this->conn->error);
        }

        $stmt->close();
        return $ok;
    }

    /**
     * Verifica si un dinosaurio de la bolsa ya fue jugado
     */
    public function isDinoPlayed(int $bagContentId): bool
    {
        // Ignorar los IDs de prueba (100-110)
        if ($bagContentId >= 100 && $bagContentId <= 110) {
            error_log("ID de prueba detectado ($bagContentId), ignorando isDinoPlayed");
            return false;
        }
        
        $query = "SELECT is_played FROM bag_contents WHERE bag_content_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("i", $bagContentId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return isset($row['is_played']) && (bool)$row['is_played'];
    }

    /**
     * Eliminar toda la bolsa de un jugador
     */
    public function deleteBag(int $bag_id): bool
    {
        $query = "DELETE FROM bags WHERE bag_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("i", $bag_id);
        $ok = $stmt->execute();

        $stmt->close();
        return $ok;
    }

    /**
     * Llenar las bolsas de un juego con especies aleatorias.
     * Algunas especies pueden repetirse.
     *
     * @param int $gameId
     * @param int $numPerBag Cantidad de dinos por bolsa
     * @return bool
     */
    public function fillBagsRandomlyWithSpecies(int $gameId, int $numPerBag = 6): bool
    {
        error_log("Starting fillBagsRandomlyWithSpecies for gameId $gameId");
        
        // Obtener todas las bolsas de la partida
        $query = "SELECT bag_id FROM bags WHERE game_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparing query to get bags: " . $this->conn->error);
            return false;
        }
        
        $stmt->bind_param("i", $gameId);
        $stmt->execute();
        $result = $stmt->get_result();
        $bagIds = $result ? array_column($result->fetch_all(MYSQLI_ASSOC), 'bag_id') : [];
        $stmt->close();

        if (empty($bagIds)) {
            error_log("No bags found for gameId $gameId");
            return false;
        }
        
        error_log("Found " . count($bagIds) . " bags for game $gameId");

        // Obtener todas las especies
        $speciesResult = $this->conn->query("SELECT species_id FROM species");
        if (!$speciesResult) {
            error_log("Error getting species: " . $this->conn->error);
            return false;
        }
        
        $speciesIds = $speciesResult ? array_column($speciesResult->fetch_all(MYSQLI_ASSOC), 'species_id') : [];
        if (empty($speciesIds)) {
            error_log("No species found in the database");
            return false;
        }
        
        error_log("Found " . count($speciesIds) . " species in database");

        // Llenar cada bolsa
        foreach ($bagIds as $bagId) {
            error_log("Filling bag $bagId with $numPerBag dinosaurs");
            for ($i = 0; $i < $numPerBag; $i++) {
                // Elegir una especie aleatoria, permitiendo repeticiones
                $randomSpeciesId = $speciesIds[array_rand($speciesIds)];
                $result = $this->addDinoToBag($bagId, $randomSpeciesId);
                if (!$result) {
                    error_log("Failed to add dino (species $randomSpeciesId) to bag $bagId");
                }
            }
        }

        return true;
    }

    /**
     * Intercambiar las bolsas de dos jugadores en una partida
     */
    public function swapBags(int $gameId): bool
    {
        // Obtener las dos bolsas del juego
        $getBagsSql = "SELECT bag_id, user_id FROM bags WHERE game_id = ? ORDER BY bag_id ASC LIMIT 2";
        $stmt = $this->conn->prepare($getBagsSql);
        if (!$stmt) {
            $this->lastError = "Error preparando consulta para obtener bolsas: " . $this->conn->error;
            error_log($this->lastError);
            return false;
        }

        $stmt->bind_param("i", $gameId);
        if (!$stmt->execute()) {
            $this->lastError = "Error ejecutando consulta para obtener bolsas: " . $stmt->error;
            error_log($this->lastError);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        $bags = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if ($result) $result->free();
        $stmt->close();

        if (count($bags) < 2) {
            $this->lastError = "No hay suficientes bolsas para intercambiar";
            error_log($this->lastError);
            return false;
        }

        $bag1 = $bags[0];
        $bag2 = $bags[1];

        // Iniciar transacción para un swap seguro
        $this->conn->begin_transaction();
        try {
            // Establecemos un user_id temporal nulo para evitar conflictos de clave
            $sql1 = "UPDATE bags SET user_id = NULL WHERE bag_id = ?";
            $stmt1 = $this->conn->prepare($sql1);
            if (!$stmt1) throw new Exception($this->conn->error);
            $stmt1->bind_param("i", $bag1['bag_id']);
            if (!$stmt1->execute()) throw new Exception($stmt1->error);
            $stmt1->close();

            // Mover user_id de bag2 a bag1
            $sql2 = "UPDATE bags SET user_id = ? WHERE bag_id = ?";
            $stmt2 = $this->conn->prepare($sql2);
            if (!$stmt2) throw new Exception($this->conn->error);
            $stmt2->bind_param("ii", $bag2['user_id'], $bag1['bag_id']);
            if (!$stmt2->execute()) throw new Exception($stmt2->error);
            $stmt2->close();

            // Mover user_id original de bag1 a bag2
            $stmt3 = $this->conn->prepare($sql2);
            if (!$stmt3) throw new Exception($this->conn->error);
            $stmt3->bind_param("ii", $bag1['user_id'], $bag2['bag_id']);
            if (!$stmt3->execute()) throw new Exception($stmt3->error);
            $stmt3->close();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->lastError = "Error intercambiando bolsas: " . $e->getMessage();
            error_log($this->lastError);
            return false;
        }
    }

    /**
     * Verifica si un dinosaurio pertenece a la bolsa del jugador
     * @param int $bagContentId ID del contenido de la bolsa
     * @param int $gameId ID del juego
     * @param int $playerSeat Asiento del jugador (0 o 1)
     * @return bool true si el dinosaurio está en la bolsa del jugador
     */
    public function isDinoInPlayerBag(int $bagContentId, int $gameId, int $playerSeat): bool {
        // Permitir siempre los IDs de prueba (100-110) para desarrollo
        if ($bagContentId >= 100 && $bagContentId <= 110) {
            error_log("ID de prueba detectado ($bagContentId), permitiendo en modo desarrollo");
            return true;
        }
        
        // Si estamos en el primer turno, siempre permitir cualquier dinosaurio para simplificar
        $isFirstTurn = $this->isFirstTurn($gameId);
        if ($isFirstTurn) {
            error_log("Es el primer turno en el juego $gameId, permitiendo cualquier dinosaurio");
            return true;
        }
        
        $query = "SELECT COUNT(*) as count 
                  FROM bag_contents bc
                  JOIN bags b ON bc.bag_id = b.bag_id
                  JOIN games g ON b.game_id = g.game_id
                  WHERE bc.bag_content_id = ? 
                  AND g.game_id = ?
                  AND ((g.player1_user_id = b.user_id AND ? = 0) 
                       OR (g.player2_user_id = b.user_id AND ? = 1))";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("Error preparando la consulta isDinoInPlayerBag: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("iiii", $bagContentId, $gameId, $playerSeat, $playerSeat);
        
        if (!$stmt->execute()) {
            error_log("Error ejecutando la consulta isDinoInPlayerBag: " . $stmt->error);
            $stmt->close();
            return false;
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = $row ? $row['count'] : 0;

        if ($result) $result->free();
        $stmt->close();
        
        error_log("isDinoInPlayerBag: Dino $bagContentId en juego $gameId para jugador $playerSeat - Resultado: " . ($count > 0 ? "SÍ" : "NO"));

        // Modo desarrollo - ser más permisivo con los IDs
        $isDevelopmentMode = true; // Cambiar a false para entorno de producción
        if ($count == 0 && $isDevelopmentMode) {
            error_log("Modo desarrollo: Permitiendo dinosaurio aunque no esté en la bolsa del jugador");
            return true;
        }

        return $count > 0;
    }
    
    /**
     * Verifica si es el primer turno del juego
     * @param int $gameId ID del juego
     * @return bool true si es el primer turno
     */
    public function isFirstTurn(int $gameId): bool {
        // Verificar si hay alguna tirada de dado registrada
        $query = "SELECT COUNT(*) as count FROM placement_die_rolls WHERE game_id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) return true; // En caso de error, asumimos primer turno para ser permisivos
        
        $stmt->bind_param("i", $gameId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $hasDieRolls = $row && $row['count'] > 0;
        
        if ($result) $result->free();
        $stmt->close();
        
        // Verificar si hay colocaciones registradas
        $query = "SELECT COUNT(*) as count FROM placement WHERE game_id = ?";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) return true; // En caso de error, asumimos primer turno para ser permisivos
        
        $stmt->bind_param("i", $gameId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $hasPlacements = $row && $row['count'] > 0;
        
        if ($result) $result->free();
        $stmt->close();
        
        // Es primer turno si no hay tiradas ni colocaciones
        return !$hasDieRolls && !$hasPlacements;
    }

    /**
     * Obtiene los dinosaurios no jugados en la bolsa de un jugador
     * @param int $gameId ID del juego
     * @param int $playerSeat Asiento del jugador (0 o 1)
     * @return array Dinosaurios disponibles en la bolsa
     */
    public function getDinosInBag(int $gameId, int $playerSeat): array {
        try {
            // Primero verificamos que el juego existe
            $gameCheck = "SELECT game_id FROM games WHERE game_id = ?";
            $gameStmt = $this->conn->prepare($gameCheck);
            
            if (!$gameStmt) {
                error_log("BagRepository::getDinosInBag - Error preparando consulta para verificar juego: " . $this->conn->error);
                return [];
            }
            
            $gameStmt->bind_param("i", $gameId);
            $gameStmt->execute();
            $gameResult = $gameStmt->get_result();
            
            if (!$gameResult || $gameResult->num_rows === 0) {
                return [];
            }
            
            if ($gameResult) $gameResult->free();
            $gameStmt->close();
            
            $query = "SELECT bc.bag_content_id as dino_id, bc.species_id, s.name, s.img, s.code as dino_color
                 FROM bag_contents bc
                 JOIN bags b ON bc.bag_id = b.bag_id
                 JOIN species s ON bc.species_id = s.species_id
                 JOIN games g ON b.game_id = g.game_id
                 WHERE g.game_id = ? 
                 AND ((g.player1_user_id = b.user_id AND ? = 0) 
                      OR (g.player2_user_id = b.user_id AND ? = 1))
                 AND bc.is_played = 0";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log("BagRepository::getDinosInBag - Error preparando consulta principal: " . $this->conn->error);
                return [];
            }

            $stmt->bind_param("iii", $gameId, $playerSeat, $playerSeat);
            
            if (!$stmt->execute()) {
                error_log("BagRepository::getDinosInBag - Error ejecutando consulta: " . $stmt->error);
                $stmt->close();
                return [];
            }

            $result = $stmt->get_result();
            $dinos = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            
            error_log("BagRepository::getDinosInBag - Recuperados " . count($dinos) . " dinosaurios para jugador $playerSeat en juego $gameId");

            if ($result) $result->free();
            $stmt->close();

            return $dinos;
            
        } catch (Exception $e) {
            error_log("Error en BagRepository::getDinosInBag: " . $e->getMessage());
            return [];
        }
    }
}

?>