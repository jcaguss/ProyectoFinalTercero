<?php
/**
 * Archivo principal que define todas las rutas de la API
 * Este archivo importa y registra todas las rutas específicas de cada módulo
 */

// Importamos los archivos de rutas específicas
require_once __DIR__ . '/authRoutes.php';
require_once __DIR__ . '/gameRoutes.php';
require_once __DIR__ . '/userRoutes.php';
require_once __DIR__ . '/recoveryRoutes.php';

/**
 * Clase que registra todas las rutas de la aplicación
 */
class Routes {
    /**
     * Configura todas las rutas en el router
     * @param Router $router Instancia del router
     * @return Router Router configurado
     */
    public static function defineRoutes($router) {
        // Registramos todas las rutas específicas
        AuthRoutes::register($router);
        GameRoutes::register($router);
        RecoveryRoutes::register($router);
        UserRoutes::register($router);
        
        return $router;
    }
}
