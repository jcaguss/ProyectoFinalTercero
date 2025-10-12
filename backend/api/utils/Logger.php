<?php

class Logger {
    private static ?Logger $instance = null;
    private string $logFile;

    private function __construct() {
        $logsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0777, true);
        }
        $this->logFile = $logsDir . DIRECTORY_SEPARATOR . 'app.log';
    }

    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function write(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $line = sprintf("[%s] %s: %s\n", $timestamp, $level, $message);
        @file_put_contents($this->logFile, $line, FILE_APPEND);
        // Also send to PHP error log for convenience
        error_log("$level: $message");
    }

    public function info(string $message): void { $this->write('INFO', $message); }
    public function warning(string $message): void { $this->write('WARNING', $message); }
    public function error(string $message): void { $this->write('ERROR', $message); }

    public function exception(string $message, \Throwable $e): void {
        $details = $message . ' | ' . get_class($e) . ': ' . $e->getMessage() .
            ' in ' . $e->getFile() . ':' . $e->getLine();
        $this->write('EXCEPTION', $details);
        $this->write('TRACE', $e->getTraceAsString());
    }
}

/**
 * Simple logging utility class
 * This implementation works without external dependencies
 */
class Logger {
    private static ?Logger $instance = null;
    private string $logFile;
    private bool $debugMode;

    /**
     * Constructor privado (patrón Singleton)
     */
    private function __construct() {
        // Configuración simple sin dependencias externas
        $this->logFile = __DIR__ . '/../../logs/app.log';
        $this->debugMode = true; // En desarrollo mantenemos debug activado
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Obtiene la instancia única del Logger
     */
    public static function getInstance(): Logger {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log un mensaje de información
     */
    public function info(string $message): void {
        $this->log('INFO', $message);
    }

    /**
     * Log un mensaje de advertencia
     */
    public function warning(string $message): void {
        $this->log('WARNING', $message);
    }

    /**
     * Log un mensaje de error
     */
    public function error(string $message): void {
        $this->log('ERROR', $message);
    }

    /**
     * Log una excepción
     */
    public function exception(string $message, \Throwable $exception): void {
        $exceptionDetails = sprintf(
            "%s: %s in %s:%d\nStack trace:\n%s",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        $this->log('EXCEPTION', "$message\n$exceptionDetails");
    }

    /**
     * Log un mensaje de depuración (solo si debug está activado)
     */
    public function debug(string $message): void {
        if ($this->debugMode) {
            $this->log('DEBUG', $message);
        }
    }

    /**
     * Método interno para escribir en el log
     */
    private function log(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        
        // En desarrollo, también mostramos en error_log para depuración
        if ($this->debugMode) {
            error_log($formattedMessage);
        }
        
        // Intentar escribir en archivo de log, pero seguir funcionando si falla
        try {
            file_put_contents($this->logFile, $formattedMessage, FILE_APPEND);
        } catch (\Throwable $e) {
            error_log("No se pudo escribir en el archivo de log: " . $e->getMessage());
        }
    }
}
