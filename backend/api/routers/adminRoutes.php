<?php

require_once __DIR__ . '/../controllers/AdminController.php';

class AdminRoutes
{
    public static function register($router)
    {
        $adminController = new AdminController();

        // Gestionar usuarios
        $router->get('/api/admin/users', [$adminController, 'getAllUsers']);
        $router->post('/api/admin/user', [$adminController, 'createUser']);
        $router->put('/api/admin/user/{user_id}/role', [$adminController, 'updateRole']);
        $router->delete('/api/admin/user/{user_id}', [$adminController, 'deleteUser']);

        // Gestionar partidas
        $router->get('/api/admin/games', [$adminController, 'getAllGames']);
        $router->delete('/api/admin/game/{game_id}', [$adminController, 'deleteGame']);
    }
}