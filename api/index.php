<?php

ob_start();

// ==================================================================
// === AJUSTE AVANZADO DE SESIÃ“N Y COOKIE ===
// Esto asegura que la cookie de sesiÃ³n sea visible para toda la aplicaciÃ³n.
ini_set('session.cookie_path', '/');
session_start();
// ==================================================================

// === IMPLEMENTACIÃ“N DE ENCABEZADOS CORS (Cross-Origin Resource Sharing) ===

// 1. Permite peticiones desde cualquier origen (necesario en desarrollo)
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Credentials: true");
// 2. Permite los mÃ©todos HTTP que usaremos (GET, POST, OPTIONS, DELETE, PUT)
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");

// 3. Permite los encabezados necesarios (Content-Type para JSON, AutorizaciÃ³n para sesiones)
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Manejo de la pre-peticiÃ³n OPTIONS (solicitud que el navegador envÃ­a primero)
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
    // Físicas (requieren un rol de gestión)
    'fisicas/sesion'     => ['supervisor','admin'],
    'fisicas/registro'   => ['supervisor','admin'],
    'fisicas/cerrar'     => ['supervisor','admin'],

    // Módulos
    'mensual/guardar'    => ['supervisor','admin'],
    'mensual/leer'       => ['supervisor','admin'],
    'mensual/historial'  => ['supervisor','admin'],
    'mensual/pdf_generar'=> ['supervisor','admin'],
    'mensual/pdf'        => ['supervisor','admin','vigilante'],
    'mensual/leer'       => ['supervisor','admin'],
    'encuesta/guardar'   => ['cliente','admin'],
    'incentivo/calcular' => ['admin','supervisor','vigilante'],

    // Empleados
    'empleados/buscar'        => ['supervisor','admin'],
    'empleados/por_punto'     => ['supervisor','admin','cliente'],
    'empleados/lista'         => ['supervisor','admin'],
    'empleados/sugerencias'   => ['supervisor','admin'],
    'empleados/resumen_bajas' => ['supervisor','admin'],

    // Catálogos
    'puntos/lista'       => ['admin','supervisor'],
    'puntos/crear'       => ['admin'],
    'puntos/renombrar'   => ['admin'],
    'puntos/eliminar'    => ['admin'],
    'regiones/lista'     => ['admin','supervisor'],
    'regiones/crear'     => ['admin'],
    'regiones/renombrar' => ['admin'],
    'regiones/eliminar'  => ['admin'],
    'puestos/lista'      => ['admin','supervisor'],
    'puestos/crear'      => ['admin'],
    'puestos/renombrar'  => ['admin'],
    'puestos/eliminar'   => ['admin'],
    'usuarios/lista'     => ['admin'],
    'usuarios/crear'     => ['admin'],
    'usuarios/reset'     => ['admin'],
    'usuarios/actualizar'=> ['admin'],

    // Faltas
    'faltas/guardar'     => ['admin','supervisor'],
    'faltas/historial'   => ['admin','supervisor'],

    // Actas administrativas
    'actas/guardar'      => ['admin','supervisor'],
    'actas/historial'    => ['admin','supervisor'],

    'cursos/guardar'    => ['admin','supervisor'],
    'cursos/historial'  => ['admin','supervisor','cliente'],
    // Físicas (catálogo de clasificación)
    'fisicas/clasificacion/lista'   => ['admin','supervisor'],
    'fisicas/clasificacion/guardar' => ['admin'],
    'fisicas/ejercicios'       => ['admin','supervisor'],
    'fisicas/reglas/lista'     => ['admin','supervisor'],
    'fisicas/reglas/guardar'   => ['admin'],
    'fisicas/install'          => ['admin'],
];

// Requerir roles si la ruta estÃ¡ definida en el mapa de protecciones
if (isset($protecciones[$ruta])) { requerir_roles($protecciones[$ruta]); }

switch (true) {
    /* === AUTH === */
    case str_starts_with($ruta,'auth/') : require __DIR__ . '/controladores/auth.php'; break;

    /* === MÃ“DULOS Y FUNCIONES ESPECÃFICAS === */
    case $ruta === 'salud' && $metodo === 'GET':
        require __DIR__ . '/controladores/salud.php'; break;

    case str_starts_with($ruta,'fisicas/'):
      require __DIR__ . '/controladores/fisicas.php'; break;

    case str_starts_with($ruta,'mensual/'):
        require __DIR__ . '/controladores/mensual.php'; break;

    case str_starts_with($ruta,'encuesta/'):
        require __DIR__ . '/controladores/encuesta.php'; break;

    case str_starts_with($ruta,'cursos/'):
        require __DIR__ . '/controladores/cursos.php'; break;

    case str_starts_with($ruta,'faltas/'):
        require __DIR__ . '/controladores/faltas.php'; break;

    case str_starts_with($ruta,'actas/'):
        require __DIR__ . '/controladores/actas.php'; break;

    case str_starts_with($ruta,'firmas/'):
    require __DIR__ . '/controladores/firmas.php'; break;

    case str_starts_with($ruta,'incentivo/'):
    require __DIR__ . '/controladores/incentivo.php'; break;

    /* === CONTROLADORES DE EMPLEADOS Y CATÃLOGOS (RUTAS MÃšLTIPLES) === */
    case str_starts_with($ruta,'empleados/') :
        require __DIR__ . '/controladores/empleados.php'; break;

    case str_starts_with($ruta,'puntos/'):
        require __DIR__ . '/controladores/puntos.php'; break;
        
    case str_starts_with($ruta,'regiones/'):
        require __DIR__ . '/controladores/regiones.php'; break;
    case str_starts_with($ruta,'puestos/'):
        require __DIR__ . '/controladores/puestos.php'; break;
    case str_starts_with($ruta,'usuarios/'):
        require __DIR__ . '/controladores/usuarios.php'; break;

    default:
        enviar_json(['ok'=>false,'error'=>'Ruta no encontrada','ruta'=>$ruta], 404);
}


















