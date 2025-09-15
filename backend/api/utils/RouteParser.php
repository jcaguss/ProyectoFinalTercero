<?php

class RouteParser
{
    /**
     * Devuelve el ID desde la URI para un recurso dado (por ejemplo 'estudiantes').
     */
    public static function getIdFromPath(array $path_parts, string $resource_name): ?string
    {
        $index = array_search($resource_name, $path_parts);
        return ($index !== false && isset($path_parts[$index + 1]))
            ? $path_parts[$index + 1]
            : null;
    }

    /**
     * Devuelve el recurso solicitado desde la ruta, útil para routing dinámico.
     */
    public static function getResourceName(array $path_parts): ?string
    {
        return $path_parts[0] ?? null;
    }

    /**
     * Devuelve los query params seguros.
     */
    public static function getQueryParams(): array
    {
        return $_GET ?? [];
    }

    /**
     * Devuelve el método HTTP en uso.
     */
    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Divide y devuelve los segmentos limpios del path.
     */
    public static function getPathParts(): array
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        return explode('/', trim($path, '/'));
    }
}