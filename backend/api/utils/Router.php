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
     * Ejecuta el ciclo principal de enrutamiento.
     *
     * Flujo:
     *  1. Aplica encabezados CORS básicos (setCorsHeaders()).
     *  2. Si el método es OPTIONS responde 200 (preflight) y termina.
     *  3. Normaliza la ruta (elimina query string).
     *  4. Intenta coincidencia exacta (método + path) en $this->routes.
     *     - Si coincide: procesa cuerpo (JSON o form) para métodos != GET y
     *       despacha al handler (controlador/método o callable).
     *  5. Si no hay coincidencia exacta, recorre rutas con parámetros {param}:
     *     - Convierte la ruta registrada en regex.
     *     - Extrae valores dinámicos y los combina con sus nombres.
     *     - Para métodos != GET agrega el cuerpo JSON decodificado (si existe).
     *     - Despacha al handler.
     *  6. Si ninguna ruta coincide, responde 404 con JSON {error:"Ruta no encontrada"}.
     *
     * Formato de handlers soportados:
     *  - [ $instanciaController, 'metodo' ]
     *  - [ 'NombreDeClaseController', 'metodo' ] (se instancia)
     *  - function($params) { ... } (callable)
     *
     * Parámetros entregados al handler:
     *  - Rutas exactas: $params (POST/PUT/DELETE: cuerpo decodificado; GET: vacío).
     *  - Rutas con parámetros: array asociativo con claves de {param} + datos del cuerpo si aplica.
     *
     * Carga de cuerpo:
     *  - Content-Type application/json: json_decode
     *  - Otros: intenta parse_str como fallback
     *
     * Side effects:
     *  - Envía salida directa (echo) del handler.
     *  - Ajusta http_response_code en casos especiales (OPTIONS / 404 / errores básicos).
     *
     * No retorna valor útil (flujo termina con output directo).
     *
     * @return void
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
                $controller = $handler[0];
                $methodName = $handler[1];
                
                if (is_object($controller)) {
                    echo $controller->$methodName($params);
                } else if (is_string($controller) && class_exists($controller)) {
                    $controllerInstance = new $controller();
                    echo $controllerInstance->$methodName($params);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Controlador no válido']);
                }
            } else if (is_callable($handler)) {
                echo $handler($params);
            }
            return;
        }
        
        // Si no se encuentra una ruta exacta, buscar rutas con parámetros
        foreach ($this->routes[$method] ?? [] as $routePath => $handler) {
            if (strpos($routePath, '{') !== false) {
                $routePathBase = explode('?', $routePath)[0];
                $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $routePathBase);
                $pattern = str_replace('/', '\/', $pattern);
                
                $pathBase = explode('?', $path)[0];
                
                if (preg_match('/^' . $pattern . '$/', $pathBase, $matches)) {
                    array_shift($matches);
                    
                    preg_match_all('/\{([^}]+)\}/', $routePath, $paramNames);
                    $paramNames = $paramNames[1];
                    $params = array_combine($paramNames, $matches);
                    
                    if ($method !== 'GET') {
                        $input = file_get_contents('php://input');
                        if (!empty($input)) {
                            $postParams = json_decode($input, true) ?: [];
                            $params = array_merge($params, $postParams);
                        }
                    }
                    
                    $controller = $handler[0];
                    $methodName = $handler[1];
                    
                    if (is_object($controller)) {
                        echo $controller->$methodName($params);
                    } else if (is_string($controller) && class_exists($controller)) {
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
        
        // 404 si ninguna ruta coincide
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
}
