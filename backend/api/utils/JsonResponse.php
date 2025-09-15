<?php

class JsonResponse {
    /**
     * Genera una respuesta JSON con el código de estado HTTP apropiado
     * @param mixed $data Datos a convertir a JSON
     * @param int $statusCode Código de estado HTTP (default 200)
     * @return string JSON codificado
     */
    public static function create($data, int $statusCode = 200): string {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        return json_encode($data);
    }
}