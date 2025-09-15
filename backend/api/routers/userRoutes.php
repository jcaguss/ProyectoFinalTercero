<?php
/**
 * Rutas relacionadas con la gestión de usuarios
 */

require_once __DIR__ . '/../controllers/UserController.php';

class UserRoutes {
    /**
     * Registra todas las rutas de usuario
     * @param Router $router Instancia del router
     */
    public static function register($router) {
        // Creamos una instancia del controlador
        $userController = new UserController();
        
        // Rutas para usuario
        $router->get('/api/user/current', [$userController, 'getCurrentUser']);
        $router->get('/api/user/opponents/{user_id}', [$userController, 'getAvailableOpponents']);
        $router->get('/api/user/{user_id}', [$userController, 'getUserInfo']);
    }
}
