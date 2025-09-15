<?php

require_once __DIR__ . '/../config/Database.php';

class FinalScoreRepository
{
    private static ?FinalScoreRepository $instance = null;
    private mysqli $conn;

    private function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public static function getInstance(): FinalScoreRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // -----------------------------
    // Guardar puntaje de un jugador en una partida
    // -----------------------------
    public function saveScore(int $gameId, int $playerSeat, int $totalPoints, int $riverPoints = 0, int $trexBonusPoints = 0, int $tiebreakerTrexCount = 0): bool
    {
        $query = "INSERT INTO final_score 
                  (game_id, player_seat, total_points, river_points, trex_bonus_points, tiebreaker_trex_count, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE
                  total_points = VALUES(total_points),
                  river_points = VALUES(river_points),
                  trex_bonus_points = VALUES(trex_bonus_points),
                  tiebreaker_trex_count = VALUES(tiebreaker_trex_count),
                  created_at = NOW()";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param(
            "iiiiii",
            $gameId,
            $playerSeat,
            $totalPoints,
            $riverPoints,
            $trexBonusPoints,
            $tiebreakerTrexCount
        );

        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    // -----------------------------
    // Obtener puntaje de un jugador en una partida
    // -----------------------------
    public function getScore(int $gameId, int $playerSeat): ?array
    {
        $query = "SELECT * FROM final_score WHERE game_id = ? AND player_seat = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("ii", $gameId, $playerSeat);
        $stmt->execute();

        $result = $stmt->get_result();
        $score = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return $score ?: null;
    }

    // -----------------------------
    // Obtener todos los puntajes de una partida
    // -----------------------------
    public function getScoresByGame(int $gameId): array
    {
        $query = "SELECT * FROM final_score WHERE game_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return [];

        $stmt->bind_param("i", $gameId);
        $stmt->execute();

        $result = $stmt->get_result();
        $scores = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if ($result) $result->free();
        $stmt->close();

        return $scores;
    }
}

?>