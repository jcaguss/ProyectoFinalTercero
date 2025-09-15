<?php
/**
 * Rutas relacionadas con la autenticación y gestión de usuarios
 */

require_once __DIR__ . '/../controllers/AuthController.php';

class AuthRoutes {
    /**
     * Registra todas las rutas de autenticación
     * @param Router $router Instancia del router
     */
    public static function register($router) {
        // Creamos una instancia del controlador
        $authController = new AuthController();
        
        // Rutas de autenticación
        $router->post('/api/auth/register', [$authController, 'register']);
        $router->post('/api/auth/login', [$authController, 'login']);
        $router->post('/api/auth/logout', [$authController, 'logout']);
    }
}
