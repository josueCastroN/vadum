<?php

ob_start();

// ==================================================================
// === IMPLEMENTACIÓN DE ENCABEZADOS CORS (Cross-Origin Resource Sharing) ===
// Esto permite que el frontend (ej. login.html) en tu servidor local
// pueda comunicarse correctamente con esta API.

// 1. Permite peticiones desde cualquier origen (necesario en desarrollo)
header("Access-Control-Allow-Origin: *"); 

// 2. Permite los métodos HTTP que usaremos (GET, POST, OPTIONS, DELETE, PUT)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");

// 3. Permite los encabezados necesarios (Content-Type para JSON, Autorización para sesiones)
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Manejo de la pre-petición OPTIONS (solicitud que el navegador envía primero)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$ruta  = $_GET['ruta'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once __DIR__ . '/02_bd.php';
require_once __DIR__ . '/seguridad.php'; // Contiene requerir_roles() y usuario_actual()

/* === Mapa de protecciones por ruta === */
$protecciones = [
    // Las rutas de empleados sensibles (alta/baja/reactivar/exportar) NO van aquí.
    // Su ACL detallado ('admin', 'gerente', 'jefe_seguridad') se maneja *dentro* de empleados.php.

    // Físicas (requieren un rol de gestión)
    'fisicas/sesion'  => ['supervisor','admin'],
    'fisicas/registro' => ['supervisor','admin'],
    'fisicas/cerrar' => ['supervisor','admin'],

    // Módulos
    'mensual/guardar' => ['supervisor','admin'],
    'encuesta/guardar'  => ['cliente','admin'],
    'incentivo/calcular' => ['admin','supervisor','vigilante'],

    // Empleados: Solo las rutas de búsqueda y listado general, ya que la lógica 
    // de roles detallados se mueve a empleados.php usando verificar_acceso().
    'empleados/buscar'  => ['supervisor','admin'],
    'empleados/por_punto'=> ['supervisor','admin','cliente'],
    
    // Catálogos (Alto Nivel de Acceso)
    'puntos/lista'=> ['admin','supervisor'], // Supervisor también puede ver puntos
    'puntos/crear' => ['admin'],
    'puntos/renombrar'=> ['admin'],
    'puntos/eliminar' => ['admin'],
    'regiones/lista' => ['admin','supervisor'], // Supervisor también puede ver regiones
    'regiones/crear' => ['admin'],
    'regiones/renombrar'=> ['admin'],
    'regiones/eliminar' => ['admin'],
];

// Requerir roles si la ruta está definida en el mapa de protecciones
if (isset($protecciones[$ruta])) { requerir_roles($protecciones[$ruta]); }

switch (true) {
    /* === AUTH === */
    case str_starts_with($ruta,'auth/') : require __DIR__ . '/controladores/auth.php'; break;

    /* === MÓDULOS Y FUNCIONES ESPECÍFICAS === */
    case $ruta === 'salud' && $metodo === 'GET':
        require __DIR__ . '/controladores/salud.php'; break;

    case str_starts_with($ruta,'fisicas/'):
        require __DIR__ . '/controladores/fisicas.php'; break;

    case str_starts_with($ruta,'mensual/'):
        require __DIR__ . '/controladores/mensual.php'; break;
    
    case str_starts_with($ruta,'encuesta/'):
        require __DIR__ . '/controladores/encuesta.php'; break;

    case str_starts_with($ruta,'firmas/'):
        require __DIR__ . '/controladores/firmas.php'; break;

    case str_starts_with($ruta,'incentivo/'):
        require __DIR__ . '/controladores/incentivo.php'; break;

    /* === CONTROLADORES DE EMPLEADOS Y CATÁLOGOS (RUTAS MÚLTIPLES) === */
    // Este case cubre todas las rutas de empleados/ lista/alta/baja/exportar/...
    case str_starts_with($ruta,'empleados/') :
        require __DIR__ . '/controladores/empleados.php'; break;
        
    case str_starts_with($ruta,'puntos/'):
        require __DIR__ . '/controladores/puntos.php'; break;
        
    case str_starts_with($ruta,'regiones/'):
        require __DIR__ . '/controladores/regiones.php'; break;

    default:
        // Asegúrate de que enviar_json() esté disponible (está en seguridad.php)
        enviar_json(['ok'=>false,'error'=>'Ruta no encontrada','ruta'=>$ruta], 404);
}