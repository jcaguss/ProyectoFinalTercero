<?php

require_once __DIR__ . '/../utils/JsonResponse.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class UserController {
    private UserRepository $userRepository;
    
    public function __construct() {
        $this->userRepository = UserRepository::getInstance();
    }
    
    /**
     * Obtiene la información del usuario actual basado en la sesión
     */
    public function getCurrentUser() {
        // Iniciar o reanudar la sesión existente
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar si hay una sesión activa con ID de usuario
        if (empty($_SESSION['user_id'])) {
            // Intentar obtener información de la cookie adicional como respaldo
            $userInfo = isset($_COOKIE['user_info']) ? json_decode($_COOKIE['user_info'], true) : null;
            
            // Si tampoco hay cookie, no hay autenticación
            if (!$userInfo || empty($userInfo['id'])) {
                return JsonResponse::create([
                    'success' => false,
                    'message' => 'No hay usuario autenticado'
                ], 401);
            }
            
            // Si hay cookie pero no hay sesión, recreamos la sesión (menos seguro pero funcional)
            $_SESSION['user_id'] = $userInfo['id'];
            $_SESSION['username'] = $userInfo['username'];
            $_SESSION['restored'] = true;
        }
        
        // A este punto tenemos una sesión con ID de usuario
        $userId = $_SESSION['user_id'];
        $user = $this->userRepository->getById($userId);
        
        if (!$user) {
            // Si llegamos aquí, algo raro pasó (sesión con ID de usuario que ya no existe)
            session_destroy();
            setcookie('user_info', '', time() - 3600, '/');
            return JsonResponse::create([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
        
        // Verificar si la sesión está por expirar y renovarla si es necesario
        if (isset($_SESSION['auth_time']) && (time() - $_SESSION['auth_time'] > 3600)) {
            // Renovar sesión si ha pasado más de una hora
            session_regenerate_id(true);
            $_SESSION['auth_time'] = time();
        }
        
        // Devolver información del usuario
        return JsonResponse::create([
            'success' => true,
            'user' => $user,
            'session_valid' => true
        ]);
    }
    
    /**
     * Devuelve la lista de oponentes disponibles para un usuario
     */
    public function getAvailableOpponents($params) {
        try {
            if (!isset($params['user_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Falta user_id']);
                return;
            }
            $uid = (int)$params['user_id'];
            $repo = $this->userRepository ?? new UserRepository();
            $list = $repo->getOpponentsWithoutPending($uid);
            echo json_encode([
                'success' => true,
                'count' => count($list),
                'opponents' => $list
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno']);
        }
    }
    
    /**
     * Obtiene información de un usuario específico por su ID
     */
    public function getUserInfo($request) {
        $userId = (int)$request['user_id'];
        
        if ($userId <= 0) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'ID de usuario inválido'
            ], 400);
        }
        
        $user = $this->userRepository->getById($userId);
        
        if (!$user) {
            return JsonResponse::create([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
        
        return JsonResponse::create([
            'success' => true,
            'user' => $user
        ]);
    }
}

?>
