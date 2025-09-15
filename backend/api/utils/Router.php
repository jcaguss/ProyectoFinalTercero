<?php

/**
 * Clase Router que maneja el enrutamiento de la aplicación
 */
class Router {
    private $routes = [];

    /**
     * Registra una ruta GET
     * @param string $path Ruta a registrar
     * @param array $handler Controlador y método que maneja la ruta
     */
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registra una ruta POST
     * @param string $path Ruta a registrar
     * @param array $handler Controlador y método que maneja la ruta
     */
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Registra una ruta PUT
     * @param string $path Ruta a registrar
     * @param array $handler Controlador y método que maneja la ruta
     */
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Registra una ruta DELETE
     * @param string $path Ruta a registrar
     * @param array $handler Controlador y método que maneja la ruta
     */
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Agrega una ruta al registro de rutas
     * @param string $method Método HTTP
     * @param string $path Ruta a registrar
     * @param array $handler Controlador y método que maneja la ruta
     */
    private function addRoute($method, $path, $handler) {
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Configura los encabezados CORS para permitir solicitudes cruzadas
     */
    private function setCorsHeaders() {
        // Los encabezados CORS ya se configuraron en index.php
        // Este método se mantiene por compatibilidad pero no duplica los headers
        // Cache de preflight por 1 hora
        header('Access-Control-Max-Age: 3600');
    }

    /**
     * Ejecuta el router para procesar la solicitud actual
     */
    public function run() {
        // Configurar encabezados CORS para todas las respuestas
        $this->setCorsHeaders();
        
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Manejar solicitudes OPTIONS (preflight CORS)
        if ($method === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        // Quitar parámetros de consulta si existen
        $uriParts = explode('?', $uri);
        $path = $uriParts[0];
        
        // Intentar encontrar una ruta exacta
        if (isset($this->routes[$method][$path])) {
            $handler = $this->routes[$method][$path];
            $params = [];
            
            // Obtener datos POST para solicitudes no GET
            if ($method !== 'GET') {
                $input = file_get_contents('php://input');
                $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
                
                // Intentar decodificar JSON si el Content-Type lo indica
                if (!empty($input) && stripos($contentType, 'application/json') !== false) {
                    $decoded = json_decode($input, true);
                    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                        // Fallback a formulario
                        parse_str($input, $formParams);
                        $params = is_array($formParams) ? $formParams : [];
                    } else {
                        $params = $decoded ?: [];
                    }
                } else if (!empty($_POST)) {
                    // Content-Type no JSON: usar $_POST
                    $params = $_POST;
                } else if (!empty($input)) {
                    // Último recurso: intentar parsear como querystring
                    parse_str($input, $formParams);
                    $params = is_array($formParams) ? $formParams : [];
                }
            }
            
            // Llamar al controlador o la función
            if (is_array($handler)) {
                // Es un controlador con método
                $controller = $handler[0];
                $methodName = $handler[1];
                
                // Verificar si el controlador es un objeto o una clase
                if (is_object($controller)) {
                    // Es un objeto (instancia ya creada)
                    echo $controller->$methodName($params);
                } else if (is_string($controller) && class_exists($controller)) {
                    // Es una cadena de clase, instanciar
                    $controllerInstance = new $controller();
                    echo $controllerInstance->$methodName($params);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Controlador no válido']);
                }
            } else if (is_callable($handler)) {
                // Es una función anónima o closure
                echo $handler($params);
            }
            return;
        }
        
        // Si no se encuentra una ruta exacta, buscar rutas con parámetros
        foreach ($this->routes[$method] ?? [] as $routePath => $handler) {
            if (strpos($routePath, '{') !== false) {
                $routePathBase = explode('?', $routePath)[0]; // Quitar query params si existen
                $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePathBase);
                $pattern = str_replace('/', '\/', $pattern);
                
                // Quitar query params de la URL actual para comparar solo la ruta base
                $pathBase = explode('?', $path)[0];
                
                error_log("Router: Comparing route pattern '^$pattern$' with path '$pathBase'");
                
                if (preg_match('/^' . $pattern . '$/', $pathBase, $matches)) {
                    error_log("Router: Route matched. Processing parameters...");
                    array_shift($matches); // Eliminar la coincidencia completa
                    
                    // Extraer nombres de parámetros
                    preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
                    $paramNames = $paramNames[1];
                    
                    // Combinar nombres y valores
                    $params = array_combine($paramNames, $matches);
                    
                    // Obtener datos POST para solicitudes no GET
                    if ($method !== 'GET') {
                        $input = file_get_contents('php://input');
                        if (!empty($input)) {
                            $postParams = json_decode($input, true) ?: [];
                            $params = array_merge($params, $postParams);
                        }
                    }
                    
                    // Llamar al controlador
                    $controller = $handler[0];
                    $methodName = $handler[1];
                    
                    // Verificar si el controlador es un objeto o una clase
                    if (is_object($controller)) {
                        // Es un objeto (instancia ya creada)
                        echo $controller->$methodName($params);
                    } else if (is_string($controller) && class_exists($controller)) {
                        // Es una cadena de clase, instanciar
                        $controllerInstance = new $controller();
                        echo $controllerInstance->$methodName($params);
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Controlador no válido']);
                    }
                    return;
                }
            }
        }
        
        // Si no se encuentra ninguna ruta, retornar 404
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
}
