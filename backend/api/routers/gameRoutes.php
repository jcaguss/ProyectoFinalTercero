<?php
/**
 * Rutas relacionadas con la mecánica del juego
 */

require_once __DIR__ . '/../controllers/GamePlayController.php';

class GameRoutes {
    /**
     * Registra todas las rutas del juego
     * @param Router $router Instancia del router
     */
    public static function register($router) {
        // Creamos una instancia del controlador
        $gameController = new GamePlayController();
        
        // Rutas para juego
        $router->post('/api/game/start', [$gameController, 'startGame']);
        $router->post('/api/game/turn', [$gameController, 'processTurn']);
        $router->post('/api/game/place-dinosaur', [$gameController, 'processTurn']); // Alias para colocar dinosaurios
        $router->post('/api/game/roll', [$gameController, 'rollDie']);
        $router->get('/api/game/enclosures/{game_id}/{player_seat}', [$gameController, 'getValidEnclosures']);
        $router->get('/api/game/bag/{game_id}/{player_seat}', [$gameController, 'getPlayerBag']);
        $router->get('/api/game/enclosure/{game_id}/{player_seat}/{enclosure_id}', [$gameController, 'getEnclosureContents']);
        $router->get('/api/game/scores/{game_id}', [$gameController, 'getScores']);
        $router->get('/api/game/inprogress/{player_id}', [$gameController, 'getInProgressGames']);
        $router->get('/api/game/state/{game_id}', [$gameController, 'getGameState']);
        $router->get('/api/game/opponents/{user_id}', [$gameController, 'getAvailableOpponents']);
        $router->get('/api/gameplay/pending-games', [$gameController, 'getPendingGames']);
           
    }
}
