<?php

require_once __DIR__ . '/../utils/JsonResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';

class AdminController
{
    private $userRepository;

    public function __construct()
    {
        $this->userRepository = UserRepository::getInstance();
    }

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

        $updated = $this->userRepository->updateUserRole($userId, $newRole);

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

        $users = $this->userRepository->getAllUsers();

        return JsonResponse::create([
            'success' => true,
            'users' => $users
        ], 200);
    }
}