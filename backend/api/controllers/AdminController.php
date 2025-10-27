<?php

require_once __DIR__ . '/../utils/JsonResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/GameRepository.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';

class AdminController
{
    private $userRepository;
    private $gameRepository;

    public function __construct()
    {
        $this->userRepository = UserRepository::getInstance();
        $this->gameRepository = GameRepository::getInstance();
    }

    // --- GESTIÓN DE USUARIOS ---

    /**
     * Cambia el rol de un usuario (solo para ADMIN)
     * Endpoint: PUT /api/admin/user/{user_id}/role
     * Body: { "role": "PLAYER" | "ADMIN" }
     */
    public function updateRole($request)
    {
        $adminUser = AuthMiddleware::requireRole('ADMIN');
        if (!$adminUser) {
            return; // La respuesta 401 o 403 ya se envió
        }

        $userId = (int)($request['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'ID de usuario inválido.'
            ], 400);
        }

        $newRole = trim($request['role'] ?? '');
        if (!in_array(strtoupper($newRole), ['PLAYER', 'ADMIN'])) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'Rol inválido. Debe ser PLAYER o ADMIN.'
            ], 400);
        }

        $updated = $this->updateUserRoleInDB($userId, $newRole);

        if ($updated) {
            return JsonResponse::create([
                'success' => true,
                'message' => 'Rol actualizado exitosamente.'
            ], 200);
        } else {
            return JsonResponse::create([
                'success' => false,
                'message' => 'No se pudo actualizar el rol.'
            ], 500);
        }
    }

    /**
     * Obtiene todos los usuarios (solo para ADMIN)
     * Endpoint: GET /api/admin/users
     */
    public function getAllUsers()
    {
        $adminUser = AuthMiddleware::requireRole('ADMIN');
        if (!$adminUser) {
            return; // La respuesta 401 o 403 ya se envió
        }

        $users = $this->getAllUsersFromDB();

        return JsonResponse::create([
            'success' => true,
            'users' => $users
        ], 200);
    }

    /**
     * Elimina un usuario (solo para ADMIN)
     * Endpoint: DELETE /api/admin/user/{user_id}
     */
    public function deleteUser($request)
    {
        $adminUser = AuthMiddleware::requireRole('ADMIN');
        if (!$adminUser) {
            return; // La respuesta 401 o 403 ya se envió
        }

        $userId = (int)($request['user_id'] ?? 0);
        if ($userId <= 0) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'ID de usuario inválido.'
            ], 400);
        }

        $deleted = $this->deleteUserFromDB($userId);

        if ($deleted) {
            return JsonResponse::create([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente.'
            ], 200);
        } else {
            return JsonResponse::create([
                'success' => false,
                'message' => 'No se pudo eliminar el usuario.'
            ], 500);
        }
    }

    /**
     * Crea un nuevo usuario (solo para ADMIN)
     * Endpoint: POST /api/admin/user
     * Body: { "username": "...", "email": "...", "password": "..." }
     */
    public function createUser($request)
    {
        $adminUser = AuthMiddleware::requireRole('ADMIN');
        if (!$adminUser) {
            return; // La respuesta 401 o 403 ya se envió
        }

        $data = $request;

        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'Todos los campos son obligatorios: username, email, password.'
            ], 400);
        }

        $username = trim($data['username']);
        $email = trim($data['email']);
        $password = $data['password'];

        // Validaciones simples
        if (strlen($username) < 3) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'El username debe tener al menos 3 caracteres.'
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'Email inválido.'
            ], 400);
        }

        if (strlen($password) < 6) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'La contraseña debe tener al menos 6 caracteres.'
            ], 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'No se pudo procesar la contraseña.'
            ], 500);
        }

        // Creamos el usuario con rol PLAYER por defecto
        $created = $this->userRepository->create($username, $email, $hash);

        if ($created) {
            return JsonResponse::create([
                'success' => true,
                'message' => 'Usuario creado exitosamente.',
                'user' => [
                    'id' => (int)$created['user_id'],
                    'username' => $created['username'],
                    'email' => $created['email'],
                ]
            ], 201);
        } else {
            return JsonResponse::create([
                'success' => false,
                'message' => 'No se pudo crear el usuario.'
            ], 500);
        }
    }

    // --- GESTIÓN DE PARTIDAS ---

    /**
     * Elimina una partida (solo para ADMIN)
     * Endpoint: DELETE /api/admin/game/{game_id}
     */
    public function deleteGame($request)
    {
        $adminUser = AuthMiddleware::requireRole('ADMIN');
        if (!$adminUser) {
            return; // La respuesta 401 o 403 ya se envió
        }

        $gameId = (int)($request['game_id'] ?? 0);
        if ($gameId <= 0) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'ID de partida inválido.'
            ], 400);
        }

        $deleted = $this->deleteGameFromDB($gameId);

        if ($deleted) {
            return JsonResponse::create([
                'success' => true,
                'message' => 'Partida eliminada exitosamente.'
            ], 200);
        } else {
            return JsonResponse::create([
                'success' => false,
                'message' => 'No se pudo eliminar la partida.'
            ], 500);
        }
    }

    /**
     * Obtiene todas las partidas (solo para ADMIN)
     * Endpoint: GET /api/admin/games
     */
    public function getAllGames()
    {
        $adminUser = AuthMiddleware::requireRole('ADMIN');
        if (!$adminUser) {
            return; // La respuesta 401 o 403 ya se envió
        }

        $games = $this->getAllGamesFromDB();

        return JsonResponse::create([
            'success' => true,
            'games' => $games
        ], 200);
    }

    // --- MÉTODOS AUXILIARES ---

    private function updateUserRoleInDB(int $userId, string $newRole): bool
    {
        $query = "UPDATE users SET role = ? WHERE user_id = ?";
        $stmt = $this->userRepository->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("si", $newRole, $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    private function getAllUsersFromDB(): array
    {
        $query = "SELECT user_id, username, email, role, created_at FROM users";
        $result = $this->userRepository->conn->query($query);

        if (!$result) return [];

        $users = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $users;
    }

    private function deleteUserFromDB(int $userId): bool
    {
        $query = "DELETE FROM users WHERE user_id = ?";
        $stmt = $this->userRepository->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("i", $userId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    private function deleteGameFromDB(int $gameId): bool
    {
        $query = "DELETE FROM games WHERE game_id = ?";
        $stmt = $this->gameRepository->conn->prepare($query);
        if (!$stmt) return false;

        $stmt->bind_param("i", $gameId);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    private function getAllGamesFromDB(): array
    {
        $query = "
            SELECT g.*, u1.username as player1_username, u2.username as player2_username
            FROM games g
            LEFT JOIN users u1 ON g.player1_user_id = u1.user_id
            LEFT JOIN users u2 ON g.player2_user_id = u2.user_id
        ";
        $result = $this->gameRepository->conn->query($query);

        if (!$result) return [];

        $games = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $games;
    }
}