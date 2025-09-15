<?php

require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../utils/JsonResponse.php';

class AuthController {
    private AuthService $authService;

    public function __construct() {
        $this->authService = AuthService::getInstance();
    }

    /**
     * Registra un nuevo usuario
     * 
     * Endpoint: POST /api/auth/register
     * 
     * Parámetros JSON:
     * - username: Nombre de usuario (obligatorio)
     * - email: Correo electrónico (obligatorio)
     * - password: Contraseña (obligatoria)
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "message": "Usuario creado exitosamente",
     *   "user": {
     *     "id": 123,
     *     "username": "nombreusuario",
     *     "email": "correo@ejemplo.com"
     *   }
     * }
     * 
     * Respuesta de error:
     * {
     *   "success": false,
     *   "code": "invalid|duplicate|error",
     *   "message": "Mensaje descriptivo del error"
     * }
     */
    public function register($request) {
        // Verificar que la petición incluya todos los campos necesarios
        $data = is_array($request) ? $request : [];
        
        if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            return JsonResponse::create([
                'success' => false, 
                'code' => 'invalid',
                'message' => 'Todos los campos son obligatorios'
            ], 400);
        }
        
        // Llamar al servicio de autenticación para registrar al usuario
        $result = $this->authService->register(
            $data['username'],
            $data['email'],
            $data['password']
        );
        
        // Determinar el código HTTP según el resultado
        $statusCode = $result['success'] ? 201 : 400;
        
        // Devolver la respuesta
        return JsonResponse::create($result, $statusCode);
    }

    /**
     * Inicia sesión de usuario
     * 
     * Endpoint: POST /api/auth/login
     * 
     * Parámetros JSON:
     * - identifier: Email o nombre de usuario (obligatorio)
     * - password: Contraseña (obligatoria)
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "message": "Login exitoso",
     *   "user": {
     *     "id": 123,
     *     "username": "nombreusuario",
     *     "email": "correo@ejemplo.com"
     *   }
     * }
     * 
     * Respuesta de error:
     * {
     *   "success": false,
     *   "message": "Credenciales incorrectas"
     * }
     */
    public function login($request) {
        // Verificar que la petición incluya todos los campos necesarios
        $data = is_array($request) ? $request : [];
        
        if (!isset($data['identifier']) || !isset($data['password'])) {
            // Registramos qué campos faltan para depuración
            $missingFields = [];
            if (!isset($data['identifier'])) $missingFields[] = 'identifier';
            if (!isset($data['password'])) $missingFields[] = 'password';
            error_log("Intento de login con campos faltantes: " . implode(", ", $missingFields));
            
            // Devolvemos un mensaje genérico sin revelar qué campo específico falta
            return JsonResponse::create([
                'success' => false, 
                'message' => 'Por favor proporciona todos los datos necesarios para iniciar sesión',
                'debug' => [
                    'received' => $data,
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? ''),
                    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? ''
                ]
            ], 400);
        }
        
        // Llamar al servicio de autenticación para iniciar sesión
        $result = $this->authService->login(
            $data['identifier'],
            $data['password']
        );
        
        // Determinar el código HTTP según el resultado
        $statusCode = $result['success'] ? 200 : 401;
        
        // Por seguridad, siempre devolvemos un mensaje genérico en caso de error
        if (!$result['success']) {
            // Registramos el mensaje original para depuración (logs de servidor)
            $errorCode = isset($result['code']) ? $result['code'] : 'no_code';
            error_log("Error de autenticación: {$result['message']} (Código: $errorCode)");
            
            // Reemplazamos el mensaje con uno genérico para el cliente
            $result['message'] = 'No se pudo iniciar sesión. Verifica tus credenciales.';
            
            // Eliminamos información sensible como códigos de error específicos
            if (isset($result['code'])) {
                unset($result['code']);
            }
        }
        
        // Si el login es exitoso, establecer una sesión adecuada
        if ($result['success']) {
            // Configurar parámetros de sesión ANTES de iniciar la sesión
            // Para desarrollo permitimos cross-origin
            // En producción cambiar samesite a 'Strict' y secure a true
            session_set_cookie_params(
                86400, // 24 horas
                '/',
                '',
                false,
                true
            );
            
            // Configurar la cookie de sesión con más seguridad
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            
            // Iniciar o reanudar la sesión
            session_start();
            
            // Regenerar el ID de sesión para prevenir fixation attacks
            session_regenerate_id(true);
            
            // Guardar datos esenciales del usuario en la sesión
            $_SESSION['user_id'] = $result['user']['id'];
            $_SESSION['username'] = $result['user']['username'];
            $_SESSION['email'] = $result['user']['email'];
            $_SESSION['auth_time'] = time();
            
            // También podemos guardar una cookie adicional con los datos básicos
            // Configuración de cookies compatible con navegadores modernos
            setcookie('user_info', json_encode([
                'id' => $result['user']['id'],
                'username' => $result['user']['username']
            ]), [
                'expires' => time() + 86400, // 24 horas
                'path' => '/',
                'secure' => false,    // Cambiar a true en producción con HTTPS
                'httponly' => false,  // Accesible por JavaScript para UX
                'samesite' => 'Lax'   // Más compatible con navegadores modernos
            ]);
            
            // También guardamos la información del usuario en el resultado para el frontend
            $result['session_started'] = true;
            
            // Establecer headers específicos para la sesión
            header('X-Session-Status: active');
        }
        
        // Devolver la respuesta
        return JsonResponse::create($result, $statusCode);
    }
    
    /**
     * Cierra la sesión del usuario
     * 
     * Endpoint: POST /api/auth/logout
     * 
     * Respuesta exitosa:
     * {
     *   "success": true,
     *   "message": "Sesión cerrada exitosamente"
     * }
     */
    public function logout() {
        // Iniciar la sesión para poder destruirla
        session_start();
        
        // Limpiar variables de sesión
        $_SESSION = array();
        
        // Destruir la cookie de sesión
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión
        session_destroy();
        
        // Eliminar también la cookie personalizada
        setcookie('user_session', '', time() - 3600, '/');
        
        return JsonResponse::create([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
}
