<?php
// =============================================
// Conexión a MariaDB/MySQL con PDO
// =============================================
require_once __DIR__ . '/01_config.php';

/** Devuelve una conexión PDO lista para usar */
function obtener_conexion(): PDO {
    $dsn = 'mysql:host=' . BD_HOST . ';port=' . BD_PUERTO . ';dbname=' . BD_NOMBRE . ';charset=utf8mb4';
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    return new PDO($dsn, BD_USUARIO, BD_PASSWORD, $opciones);
}

/** Respuesta JSON estándar (si no existe ya en seguridad.php) */
if (!function_exists('enviar_json')) {
    function enviar_json(array $datos, int $codigo = 200): void {
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }
        @ini_set('default_charset','UTF-8');
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

