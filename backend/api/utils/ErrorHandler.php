<?php

require_once __DIR__ . '/JsonResponse.php';

class ErrorHandler {
    private static $instance = null;

    public static function getInstance(): ErrorHandler {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Handle API errors consistently
     */
    public function handleApiError(Exception $e, string $context = ''): string {
        
        // Siempre proporcionar información detallada del error para facilitar depuración
        // (en producción esto debería cambiarse)
        return JsonResponse::create([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => [
                'context' => $context,
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ]
        ], 500);
        
        // In production, show minimal error details
        return JsonResponse::create([
            'success' => false,
            'error' => 'Internal server error',
            'message' => 'An unexpected error occurred. Please try again later.'
        ], 500);
    }
    
    /**
     * Handle validation errors
     */
    public function handleValidationError(string $message): string {
        
        return JsonResponse::create([
            'success' => false,
            'error' => 'Validation error',
            'message' => $message
        ], 400);
    }
}
