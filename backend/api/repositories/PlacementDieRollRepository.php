<?php

require_once __DIR__ . '/../config/Database.php';

class PlacementDieRollRepository
{
    private static ?PlacementDieRollRepository $instance = null;
    private mysqli $conn;

    private function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }
    
    public static function getInstance(): PlacementDieRollRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registrar la tirada del dado para un jugador
     */
    public function insertDieRoll(int $gameId, int $affectedPlayerSeat, string $dieFace): ?int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO placement_die_rolls (game_id, affected_player_seat, die_face) 
             VALUES (?, ?, ?)"
        );
        if (!$stmt) return null;

        $stmt->bind_param("iis", $gameId, $affectedPlayerSeat, $dieFace);
        $ok = $stmt->execute();
        $insertId = $ok ? $stmt->insert_id : null;
        $stmt->close();

        return $insertId;
    }

    /**
     * Obtener la última tirada de un jugador en una partida
     */
    public function getLastRoll(int $gameId, int $playerSeat): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT roll_id, die_face, created_at 
             FROM placement_die_rolls 
             WHERE game_id = ? AND affected_player_seat = ? 
             ORDER BY created_at DESC 
             LIMIT 1"
        );
        if (!$stmt) return null;

        $stmt->bind_param("ii", $gameId, $playerSeat);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Obtener la tirada inicial de la partida (jugador que empieza)
     * Permite ignorar restricciones si es la primera tirada
     */
    public function getInitialRoll(int $gameId): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT roll_id, die_face, affected_player_seat, created_at 
             FROM placement_die_rolls 
             WHERE game_id = ? 
             ORDER BY created_at ASC 
             LIMIT 1"
        );
        if (!$stmt) return null;

        $stmt->bind_param("i", $gameId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Obtener la última tirada de la partida
     */
    public function getLastGameRoll(int $gameId): ?array
    {
        $stmt = $this->conn->prepare(
            "SELECT roll_id, die_face, affected_player_seat, created_at 
             FROM placement_die_rolls 
             WHERE game_id = ? 
             ORDER BY created_at DESC 
             LIMIT 1"
        );
        if (!$stmt) return null;

        $stmt->bind_param("i", $gameId);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($result) $result->free();
        $stmt->close();

        return $row ?: null;
    }
}

?>