<?php
/**
 * Rutas relacionadas con la recuperación de partidas
 */

require_once __DIR__ . '/../controllers/RecoveryGameController.php';

class RecoveryRoutes {
    /**
     * Registra todas las rutas de recuperación de partidas
     * @param Router $router Instancia del router
     */
    public static function register($router) {
        // Creamos una instancia del controlador
        $recoveryController = new RecoveryGameController();
        
        // Rutas para recuperación de partidas
        $router->get('/api/recovery/{game_id}', [$recoveryController, 'getGameHistory']);
        $router->get('/api/game/resume/{game_id}', [$recoveryController, 'resumeGame']);
        $router->get('/api/game/pending/{user_id}', [$recoveryController, 'getInProgressGames']);
    }
}
