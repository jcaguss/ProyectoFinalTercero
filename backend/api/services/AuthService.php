<?php

require_once __DIR__ . '/../repositories/UserRepository.php';

/**
 * Responsabilidad:
 *  - Implementar la lógica de negocio para autenticación: registro y login.
 *  - Coordinar validaciones de negocio y llamar al repositorio de usuarios.
 *
 * Diseño:
 *  - Singleton: una sola instancia del servicio durante la ejecución.
 *  - El servicio NO conoce detalles de HTTP, retorna arrays que luego el controlador traduce.
 */

class AuthService
{
    /** Instancia única del servicio (patrón Singleton). */
    private static ?AuthService $instance = null; // instancia única

    /** Repositorio encargado del acceso a datos. */
    private ?UserRepository $userRepository;

    /**
     * Constructor privado: se inicializa el repositorio a utilizar.
     * Notar que UserRepository también es un Singleton y, a su vez, utiliza Database.
     */
    private function __construct()
    {
        // Inyectamos/obtenemos la dependencia del repositorio mediante su Singleton
        $this->userRepository = UserRepository::getInstance();
    }

    /**
     * Punto de acceso global a la instancia del servicio.
     */
    public static function getInstance(): ?AuthService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registra un nuevo usuario.
     * Flujo general:
     *  1) Normalizar/validar datos (username/email/password).
     *  2) Verificar duplicados (username y email).
     *  3) Hashear contraseña con password_hash.
     *  4) Delegar la creación al repositorio.
     * Devuelve un array con success, message y datos del usuario creado (sin contraseña).
     */
    public function register(string $username, string $email, string $password): array
    {
        $username = trim($username);
        $email = trim($email);
        $password = (string)$password;

        // Validación de presencia
        if ($username === '' || $email === '' || $password === '') {
            return ['success' => false, 'code' => 'invalid', 'message' => 'Username, email y contraseña son requeridos.'];
        }

        // Validación de formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'code' => 'invalid', 'message' => 'Email inválido.'];
        }

        // Validaciones simples adicionales (puedes endurecerlas según tu caso real)
        if (strlen($username) < 3) {
            return ['success' => false, 'code' => 'invalid', 'message' => 'El username debe tener al menos 3 caracteres.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'code' => 'invalid', 'message' => 'La contraseña debe tener al menos 6 caracteres.'];
        }

        // Verificar duplicados por username
        $existingUsername = $this->userRepository->findByUsername($username);
        if ($existingUsername) {
            return ['success' => false, 'code' => 'duplicate', 'message' => 'El username ya está registrado.'];
        }

        // Verificar duplicados por email
        $existingEmail = $this->userRepository->findByEmail($email);
        if ($existingEmail) {
            return ['success' => false, 'code' => 'duplicate', 'message' => 'El email ya está registrado.'];
        }

        // Hashear contraseña (PASSWORD_DEFAULT elige el algoritmo recomendado por PHP en tu versión)
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            return ['success' => false, 'code' => 'error', 'message' => 'No se pudo procesar la contraseña.'];
        }

        // Crear el usuario
        $created = $this->userRepository->create($username, $email, $hash);
        if ($created === false) {
            // Podría fallar por restricciones únicas u otros motivos
            return ['success' => false, 'code' => 'error', 'message' => 'No se pudo crear el usuario.'];
        }

        return [
            'success' => true,
            'message' => 'Usuario creado exitosamente.',
            'user' => [
                'id' => (int)$created['user_id'],
                'username' => $created['username'],
                'email' => $created['email'],
            ],
        ];
    }

    /**
     * Verifica credenciales (usuario puede identificarse con username o email).
     * Retorna false si no coincide, o un arreglo con datos no sensibles si es correcto.
     */
    private function verifyCredentials(string $identifier, string $plainPassword)
    {
        // Busca el usuario por username o email
        $user = $this->userRepository->findByUsernameOrEmail($identifier);

        // Validación de existencia y estructura mínima
        if (!$user || !isset($user['password_hash']) || !is_string($user['password_hash'])) {
            return false;
        }

        // Compara contraseña en texto plano con el hash almacenado
        if (!password_verify($plainPassword, $user['password_hash'])) {
            return false;
        }

        // Datos mínimos para identificar la sesión/usuario (sin password)
        return [
            'id' => (int)$user['user_id'],
            'username' => $user['username'] ?? null,
            'email' => $user['email'],
        ];
    }

    /**
     * Autenticación (login) usando email o username como identificador.
     */
    public function login(string $identifier, string $password): array
    {
        $basicUser = $this->verifyCredentials($identifier, $password);
        if ($basicUser === false) {
            return [
                'success' => false, 
                'code' => 'auth_failed', 
                'message' => 'Las credenciales proporcionadas no son válidas.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Login exitoso.',
            'user' => [
                'id' => $basicUser['id'],
                'email' => $basicUser['email'],
                'username' => $basicUser['username'],
            ],
        ];
    }
}
