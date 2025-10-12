<?php

require_once __DIR__ . '/../utils/JsonResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class AuthMiddleware
{
    private static $userRepository;

    public static function init()
    {
        if (self::$userRepository === null) {
            self::$userRepository = UserRepository::getInstance();
        }
    }

    /**
     * Verifica si hay un usuario autenticado.
     * Retorna el usuario si está autenticado, o null si no.
     */
    public static function authenticate(): ?array
    {
        self::init();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            $userInfo = isset($_COOKIE['user_info']) ? json_decode($_COOKIE['user_info'], true) : null;
            if (!$userInfo || empty($userInfo['id'])) {
                return null;
            }
            $_SESSION['user_id'] = $userInfo['id'];
            $_SESSION['username'] = $userInfo['username'];
        }

        $userId = $_SESSION['user_id'];
        $user = self::$userRepository->getById($userId);

        if (!$user) {
            session_destroy();
            setcookie('user_info', '', time() - 3600, '/');
            return null;
        }

        return $user;
    }

    /**
     * Verifica si el usuario autenticado tiene un rol específico.
     */
    public static function requireRole(string $requiredRole): ?array
    {
        $user = self::authenticate();

        if (!$user) {
            JsonResponse::create([
                'success' => false,
                'message' => 'No autorizado. Debes iniciar sesión.'
            ], 401);
            return null;
        }

        if (strtoupper($user['role']) !== strtoupper($requiredRole)) {
            JsonResponse::create([
                'success' => false,
                'message' => 'Acceso denegado. Rol insuficiente.'
            ], 403);
            return null;
        }

        return $user;
    }
}