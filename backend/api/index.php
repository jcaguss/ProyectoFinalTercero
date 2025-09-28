<?php
/**
 * Archivo principal de la API
 * Este archivo inicializa la aplicación, configura CORS y maneja las solicitudes
 */

// Incluir archivos necesarios
require_once __DIR__ . '/utils/Router.php';
require_once __DIR__ . '/routers/routes.php';

// Configuración CORS directamente en index.php
header('Content-Type: application/json');

// Configurar CORS para permitir credenciales desde el origen específico
$allowedOrigins = array(
    'http://127.0.0.1:5500',
    'http://localhost:5500'
);

// Obtenemos el origen de la solicitud
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Verificamos si el origen está permitido
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    // Si no está en la lista, usamos un valor predeterminado seguro
    header('Access-Control-Allow-Origin: http://127.0.0.1:5500');
    header('Access-Control-Allow-Credentials: true');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Preflight check para requests OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Manejo de errores
function errorHandler($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    exit();
}
set_error_handler('errorHandler');

try {
    // Crear el router
    $router = new Router();
    
    // Configurar rutas
    Routes::defineRoutes($router);
    
    // Ejecutar la aplicación
    $router->run();
} catch (Exception $e) {
    // Manejar excepciones no capturadas
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
    ]);
}
