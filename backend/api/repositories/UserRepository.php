<?php

require_once __DIR__ . '/../config/Database.php';

class UserRepository
{
    private static ?UserRepository $instance = null;
    private mysqli $conn;

    private function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
    }

    public static function getInstance(): UserRepository
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getById(int $id): ?array
    {
        $query = "SELECT user_id, username, email FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return $user ?: null;
    }

    public function create(string $username, string $email, string $password_hash): ?array
    {
        $query = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'PLAYER')";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("sss", $username, $email, $password_hash);
        $ok = $stmt->execute();

        if (!$ok) {
            $stmt->close();
            return null;
        }

        $user_id = $this->conn->insert_id;
        $stmt->close();

        return [
            'user_id' => $user_id,
            'username' => $username,
            'email' => $email,
        ];
    }

    /**
     * Buscar usuario por email.
     * Devuelve user_id, username, email, password_hash, role o null.
     */
    public function findByEmail(string $email): ?array
    {
        $query = "SELECT user_id, username, email, password_hash, role FROM users WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Buscar usuario por username.
     * Devuelve user_id, username, email, password_hash, role o null.
     */
    public function findByUsername(string $username): ?array
    {
        $query = "SELECT user_id, username, email, password_hash, role FROM users WHERE username = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("s", $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Buscar por username O email (útil para login con cualquiera de los dos).
     * Devuelve user_id, username, email, password_hash, role o null.
     */
    public function findByUsernameOrEmail(string $identifier): ?array
    {
        $query = "SELECT user_id, username, email, password_hash, role FROM users WHERE username = ? OR email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        if ($result) $result->free();
        $stmt->close();

        return $user ?: null;
    }

    /**
     * Devuelve oponentes disponibles.
     * Pasamos el user_id como parámetro.
     */
    public function getAvailableOpponents(int $user_id): ?array
    {
        $query = "
            SELECT u.user_id, u.username
            FROM users u
            WHERE u.role = 'PLAYER'
              AND u.user_id <> ?
              AND u.user_id NOT IN (
                  SELECT DISTINCT CASE
                      WHEN g.player1_user_id = ? THEN g.player2_user_id
                      ELSE g.player1_user_id
                  END
                  FROM games g
                  WHERE g.player1_user_id = ? OR g.player2_user_id = ?
              )
        ";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) return null;

        $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : null;

        if ($result) $result->free();
        $stmt->close();

        return $users ?: null;
    }
}

?>