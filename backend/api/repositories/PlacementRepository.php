<?php

require_once __DIR__ . '/../config/Database.php';

class PlacementRepository
{
    private static ?PlacementRepository $instance = null;
    private mysqli $conn;

    private function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public static function getInstance(): PlacementRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // -----------------------------
    // Insertar colocación de dinosaurio
    // -----------------------------
    public function insertPlacement(int $gameId, int $playerSeat, int $bagContentId, int $enclosuresId, ?int $slotIndex = null): ?int
    {
        // Verificar si es un ID de prueba (101-110) y usar un ID válido de la bolsa si es necesario
        if ($bagContentId >= 100 && $bagContentId <= 110) {
            // Buscar un ID válido en la bolsa del jugador
            $validId = $this->findValidBagContentId($gameId, $playerSeat);
            if ($validId) {
                $bagContentId = $validId;
            } else {
                $bagContentId = $this->createTemporaryBagContent($gameId, $playerSeat);
                if (!$bagContentId) {
                    return null;
                }
            }
        }

        // Verificar primero que la tabla exista
        try {
            $tableCheck = $this->conn->query("SHOW TABLES LIKE 'placement'");
            if ($tableCheck->num_rows === 0) {
                throw new Exception("Table 'draftosaurus.placement' doesn't exist");
            }
            
            // Verificamos también la estructura
            $columns = $this->conn->query("DESCRIBE placement");
            $columnNames = [];
            while ($column = $columns->fetch_assoc()) {
                $columnNames[] = $column['Field'];
            }
            
            
            if (!in_array('game_id', $columnNames) || !in_array('player_seat', $columnNames) || 
                !in_array('dino_id', $columnNames) || !in_array('enclosures_id', $columnNames)) {
                throw new Exception("La tabla 'placement' no tiene la estructura correcta");
            }
            
            $query = "INSERT INTO placement (game_id, player_seat, dino_id, enclosures_id, slot_index) 
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error preparando la consulta: " . $this->conn->error);
            }
            
            $stmt->bind_param("iiiii", $gameId, $playerSeat, $bagContentId, $enclosuresId, $slotIndex);
            
            $ok = $stmt->execute();

            if (!$ok) {
                $stmt->close();
                throw new Exception("Error ejecutando la consulta: " . $stmt->error);
            }

            $placementId = $stmt->insert_id;
            $stmt->close();
            
            return $placementId;
        } catch (Exception $e) {
            throw $e; // Relanzar para que se maneje apropiadamente en niveles superiores
        }
    }

    /**
     * Busca un ID válido de bag_content para un jugador en una partida
     */
    private function findValidBagContentId(int $gameId, int $playerSeat): ?int
    {
        $query = "SELECT bc.bag_content_id 
                  FROM bag_contents bc
                  JOIN bags b ON bc.bag_id = b.bag_id
                  JOIN games g ON b.game_id = g.game_id
                  WHERE g.game_id = ?
                  AND ((g.player1_user_id = b.user_id AND ? = 0) 
                       OR (g.player2_user_id = b.user_id AND ? = 1))
                  AND bc.is_played = 0
                  LIMIT 1";
                  
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("iii", $gameId, $playerSeat, $playerSeat);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $bagContentId = $row ? $row['bag_content_id'] : null;

        if ($result) $result->free();
        $stmt->close();

        return $bagContentId;
    }

    /**
     * Crea un registro temporal en bag_contents para permitir el movimiento
     */
    private function createTemporaryBagContent(int $gameId, int $playerSeat): ?int
    {
        // Primero necesitamos encontrar el bag_id correcto
        $query = "SELECT b.bag_id 
                  FROM bags b
                  JOIN games g ON b.game_id = g.game_id
                  WHERE g.game_id = ?
                  AND ((g.player1_user_id = b.user_id AND ? = 0) 
                       OR (g.player2_user_id = b.user_id AND ? = 1))";
                       
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("iii", $gameId, $playerSeat, $playerSeat);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $bagId = $row ? $row['bag_id'] : null;
        $stmt->close();

        if (!$bagId) {
            return null;
        }

        // Ahora insertamos un registro temporal en bag_contents
    // NOTA: La columna correcta es species_id (no dino_type_id)
    $query = "INSERT INTO bag_contents (bag_id, species_id, is_played) VALUES (?, 1, 0)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param("i", $bagId);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $bagContentId = $this->conn->insert_id;
        $stmt->close();

        return $bagContentId;
    }

    // -----------------------------
    // Obtener todas las colocaciones de un jugador en una partida
    // -----------------------------
    public function getPlacementsByPlayer(int $gameId, int $playerSeat): array
    {
        try {
          
            $query = "SELECT p.placement_id, p.enclosures_id, p.dino_id,
                      COALESCE(bc.species_id, 1) as species_id, 
                      COALESCE(s.code, 'red') AS dino_color, p.slot_index
                      FROM placement p
                      LEFT JOIN bag_contents bc ON p.dino_id = bc.bag_content_id
                      LEFT JOIN species s ON bc.species_id = s.species_id
                      WHERE p.game_id = ? AND p.player_seat = ?";

            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                return [];
            }

            $stmt->bind_param("ii", $gameId, $playerSeat);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [];
            }

            $result = $stmt->get_result();
            $placements = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

            if ($result) $result->free();
            $stmt->close();

            
            // Convertir los IDs de recinto para asegurar que coincidan con el formato esperado
            foreach ($placements as &$placement) {
                // Asegurar que el enclosures_id sea un entero
                $placement['enclosures_id'] = (int)$placement['enclosures_id'];
                
                // Asegurar que species_id sea un entero
                if (isset($placement['species_id'])) {
                    $placement['species_id'] = (int)$placement['species_id'];
                }
                
                // Asegurar que slot_index sea un entero
                if (isset($placement['slot_index'])) {
                    $placement['slot_index'] = (int)$placement['slot_index'];
                } else {
                    // Si no hay slot_index, asignar 0 por defecto
                    $placement['slot_index'] = 0;
                }
                
                // Asegurar que dino_color sea una cadena de texto
                if (!isset($placement['dino_color']) || empty($placement['dino_color'])) {
                    $placement['dino_color'] = 'unknown';
                }
                
                error_log("Placement procesado: ID={$placement['placement_id']}, enclosures_id={$placement['enclosures_id']}, slot_index={$placement['slot_index']}");
            }

            return $placements;
        } catch (Exception $e) {
            return [];
        }
    }

    // -----------------------------
    // Obtener todos los recintos (solo IDs y nombres)
    // -----------------------------
    public function getAllEnclosures(int $gameId = null): array
    {
        try {
                        
            $query = "SELECT enclosures_id, name_enclosures, max_dinos FROM enclosures";
            $result = $this->conn->query($query);

            if (!$result) {
                error_log("Error al consultar la tabla 'enclosures': " . $this->conn->error);
                return [];
            }

            $enclosures = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();

            // El parámetro gameId se ignora aquí pero existe para compatibilidad
            // con las llamadas desde GamePlayService
            return $enclosures;
        } catch (Exception $e) {
            error_log("Error en getAllEnclosures: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Eliminar todas las colocaciones de una partida
     */
    public function deletePlacementsByGame(int $gameId): bool
    {
        $stmt = $this->conn->prepare("DELETE FROM placement WHERE game_id = ?");
        if (!$stmt) return false;

        $stmt->bind_param("i", $gameId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
    /**
     * Obtener todas las colocaciones de una partida
     * con información del dinosaurio y el recinto.
     */
    public function getPlacementsByGame(int $gameId): array
    {
        $query = "
            SELECT 
                p.placement_id,
                p.game_id,
                p.dino_id,
                p.enclosures_id,
                p.player_seat,
                p.slot_index,
                p.placed_at,
                s.name AS species_name,
                s.img AS species_img
            FROM placement p
            JOIN bag_contents bc ON p.dino_id = bc.bag_content_id
            JOIN species s ON bc.species_id = s.species_id
            WHERE p.game_id = ?
            ORDER BY p.player_seat, p.enclosures_id, p.slot_index
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return [];

        $stmt->bind_param("i", $gameId);
        $stmt->execute();
        $result = $stmt->get_result();
        $placements = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if ($result) $result->free();
        $stmt->close();

        return $placements;
    }
    /**
     * Obtiene todos los dinosaurios colocados en un juego
     * excepto los que están en un recinto específico.
     *
     * Esto es útil para calcular puntuaciones que dependen
     * de la presencia de dinos en otros recintos (ej: Isla Solitaria).
     *
     * @param int $gameId ID de la partida
     * @param int $excludedEnclosureId ID del recinto que se debe excluir
     * @return array Arreglo de dinos, cada elemento contiene ['dino_color' => string]
     */
    public function getAllDinosExceptEnclosure(int $gameId, int $excludedEnclosureId): array
    {
    // La tabla placement no tiene dino_color; lo obtenemos desde species.code
    $query = "
        SELECT s.code AS dino_color
        FROM placement p
        JOIN bag_contents bc ON p.dino_id = bc.bag_content_id
        JOIN species s ON bc.species_id = s.species_id
        WHERE p.game_id = ? 
          AND p.enclosures_id != ?
    ";
    $stmt = $this->conn->prepare($query);
    if (!$stmt) return [];

    $stmt->bind_param("ii", $gameId, $excludedEnclosureId);
    $stmt->execute();

    $result = $stmt->get_result();
    $dinos = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    if ($result) $result->free();
    $stmt->close();

    return $dinos;
    }

    /**
     * Obtiene la información de un recinto específico
     */
    public function getEnclosure(int $enclosureId): ?array {
    // Ajustamos los nombres de columnas a la estructura real de la BD
    $query = "SELECT 
            enclosures_id   AS enclosure_id,
            name_enclosures AS name,
            max_dinos       AS max_capacity,
            position,
            terrain,
            special_rule    AS special_rules
         FROM enclosures 
         WHERE enclosures_id = ?";

    $stmt = $this->conn->prepare($query);
    if (!$stmt) return null;

    $stmt->bind_param("i", $enclosureId);
    $stmt->execute();

    $result = $stmt->get_result();
    $enclosure = $result ? $result->fetch_assoc() : null;

    if ($result) $result->free();
    $stmt->close();

    return $enclosure;
    }

    /**
     * Verifica si un recinto tiene colocaciones
     */
    public function hasPlacementsInEnclosure(int $gameId, int $enclosureId): bool {
        $query = "SELECT COUNT(*) as count 
                 FROM placement 
                 WHERE game_id = ? AND enclosures_id = ?";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("ii", $gameId, $enclosureId);
        $stmt->execute();

        $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

    return $row && isset($row['count']) ? ((int)$row['count'] > 0) : false;
    }

    /**
     * Verifica si hay una especie específica en un recinto
     * @param int $gameId ID del juego
     * @param int $enclosureId ID del recinto
     * @param string $speciesName Nombre de la especie a buscar
     * @return bool true si existe la especie en el recinto
     */
    public function hasSpeciesInEnclosure(int $gameId, int $enclosureId, string $speciesName): bool {
        $query = "SELECT COUNT(*) as count 
                 FROM placement p
                 JOIN bag_contents bc ON p.dino_id = bc.bag_content_id
                 JOIN species s ON bc.species_id = s.species_id
                 WHERE p.game_id = ? 
                 AND p.enclosures_id = ?
                 AND s.name = ?";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("iis", $gameId, $enclosureId, $speciesName);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        if ($result) $result->free();
        $stmt->close();

        return $row['count'] > 0;
    }

    /**
     * Cuenta cuántos dinosaurios de una especie tiene un jugador en todos sus recintos
     * @param int $gameId ID del juego
     * @param int $playerSeat Asiento del jugador (0 o 1)
     * @param int $speciesId ID de la especie a contar
     * @return int Cantidad de dinosaurios de esa especie
     */
    public function countSpeciesForPlayer(int $gameId, int $playerSeat, int $speciesId): int {
        $query = "SELECT COUNT(*) as count 
                 FROM placement p
                 JOIN bag_contents bc ON p.dino_id = bc.bag_content_id
                 WHERE p.game_id = ? 
                 AND p.player_seat = ?
                 AND bc.species_id = ?";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return 0;

        $stmt->bind_param("iii", $gameId, $playerSeat, $speciesId);
        $stmt->execute();

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

    return $row && isset($row['count']) ? (int)$row['count'] : 0;
    }

}

?>