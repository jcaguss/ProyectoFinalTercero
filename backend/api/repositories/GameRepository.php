<?php

require_once __DIR__ . '/../config/Database.php';

class GameRepository
{
    private static ?GameRepository $instance = null;
    private mysqli $conn;

    private function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public static function getInstance(): GameRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Crear una nueva partida
     */
    public function createGame(int $player1_id, ?int $player2_id = null, string $status = 'IN_PROGRESS'): ?int
    {
        $query = "INSERT INTO games (status, player1_user_id, player2_user_id, active_seat, current_turn, current_round, turn_started_at) 
                  VALUES (?, ?, ?, 0, 1, 1, CURRENT_TIMESTAMP)";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("sii", $status, $player1_id, $player2_id);
        $ok = $stmt->execute();

        if (!$ok) {
            $stmt->close();
            return null;
        }

        $game_id = $this->conn->insert_id;
        $stmt->close();

        return $game_id; // Devuelve el id de la partida creada
    }

    /**
     * Obtener datos de una partida por ID
     */
    public function getGameById(int $game_id): ?array
    {
        $query = "SELECT g.game_id, g.status, g.player1_user_id, g.player2_user_id, g.created_at, g.finished_at,
                         g.active_seat, g.current_turn, g.current_round, g.turn_started_at,
                         u1.username AS player1_name, u2.username AS player2_name
                  FROM games g
                  LEFT JOIN users u1 ON g.player1_user_id = u1.user_id
                  LEFT JOIN users u2 ON g.player2_user_id = u2.user_id
                  WHERE g.game_id = ?";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $game_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $game = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return $game ?: null;
    }

    /**
     * Actualizar estado de la partida
     */
    public function updateGameStatus(int $game_id, string $status): bool
    {
        $query = "UPDATE games SET status = ? WHERE game_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("si", $status, $game_id);
        $ok = $stmt->execute();

        $stmt->close();
        return $ok;
    }

    /**
     * Obtiene las partidas en progreso para un usuario
     * @param int $userId ID del usuario
     * @return array Lista de partidas en progreso
     */
    public function getInProgressGamesByUser(int $userId): array {
        $query = "SELECT g.game_id, g.player1_user_id, g.player2_user_id, g.active_seat, 
                         g.current_turn, g.current_round, g.created_at, g.turn_started_at
                  FROM games g
                  WHERE (g.player1_user_id = ? OR g.player2_user_id = ?)
                  AND g.status = 'IN_PROGRESS'
                  ORDER BY g.turn_started_at DESC";
        
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return [];
        
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $games = [];
        
        while ($row = $result->fetch_assoc()) {
            $games[] = $row;
        }
        
        $result->free();
        $stmt->close();
        
        return $games;
    }
    
    /**
     * Obtiene información de un usuario por ID
     * @param int $userId ID del usuario
     * @return array|null Datos del usuario o null si no existe
     */
    public function getUserById(int $userId): ?array {
        $query = "SELECT user_id, username FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;
        
        if ($result) $result->free();
        $stmt->close();
        
        return $user ?: null;
    }

    /**
     * Actualiza el estado del juego
     * @param int $gameId ID del juego
     * @param int $activeSeat Asiento del jugador activo (0 o 1)
     * @param int $currentTurn Turno actual (1-6)
     * @param int $currentRound Ronda actual (1-2)
     * @return bool true si la actualización fue exitosa
     */
    public function updateGameState(int $gameId, int $activeSeat, int $currentTurn, int $currentRound): bool {
        $query = "UPDATE games 
                 SET active_seat = ?, 
                     current_turn = ?, 
                     current_round = ?,
                     turn_started_at = CURRENT_TIMESTAMP
                 WHERE game_id = ?";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("iiii", $activeSeat, $currentTurn, $currentRound, $gameId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Obtiene las partidas en progreso de un usuario
     * @param int $userId ID del usuario
     * @return array|null Array de partidas o null si hay error
     */
    public function getInProgressGames(int $userId): ?array 
    {
        $query = "
            SELECT 
                g.game_id,
                g.current_round,
                g.current_turn,
                g.active_seat,
                g.created_at,
                CASE 
                    WHEN g.player1_user_id = ? THEN 0
                    ELSE 1
                END as player_seat,
                u1.username as player1_username,
                u2.username as player2_username
            FROM games g
            JOIN users u1 ON g.player1_user_id = u1.user_id
            JOIN users u2 ON g.player2_user_id = u2.user_id
            WHERE (g.player1_user_id = ? OR g.player2_user_id = ?)
            AND g.status = 'IN_PROGRESS'
            ORDER BY g.created_at DESC
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("iii", $userId, $userId, $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $games = $result ? $result->fetch_all(MYSQLI_ASSOC) : null;

        if ($result) $result->free();
        $stmt->close();

        return $games ?: null;
    }
}
