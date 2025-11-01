<?php
// =============================================
// Manejo de sesión y helpers de seguridad
// =============================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * Helper para enviar respuestas JSON y terminar la ejecución.
 * (Asumo que esta función existe en algún require o la coloco aquí)
 */
if (!function_exists('enviar_json')) {
    function enviar_json(array $datos, int $codigo = 200): void {
        header('Content-Type: application/json');
        http_response_code($codigo);
        echo json_encode($datos);
        exit;
    }
}

function iniciar_sesion_usuario(array $u): void {
  $_SESSION['usuario'] = [
    'id' => (int)$u['id'],
    'usuario' => $u['usuario'],
    'rol' => $u['rol'],
    'empleado_no_emp' => $u['empleado_no_emp'] ?? null,
    'punto_id' => isset($u['punto_id']) ? (int)$u['punto_id'] : null,
  ];
}

function usuario_actual(): ?array { return $_SESSION['usuario'] ?? null; }

/**
 * Obtiene el rol del usuario actual.
 * Función requerida por la lógica de ACL en empleados.php.
 */
function obtener_rol_usuario(): ?string {
    $u = usuario_actual();
    return $u['rol'] ?? null;
}

function cerrar_sesion(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

/**
 * Verifica si el usuario actual tiene alguno de los roles permitidos.
 * Si no está autenticado o no tiene el rol, termina la ejecución con un error JSON.
 */
function requerir_roles(array $roles): void {
  // Nota: require_once __DIR__ . '/02_bd.php'; no siempre es necesario aquí, 
  // pero lo mantengo si se utiliza DB para algo en el futuro.
  // Sin embargo, en tu lógica actual, requerir_roles solo necesita usuario_actual() y enviar_json().
  $u = usuario_actual();
  if (!$u) { enviar_json(['ok'=>false,'error'=>'No autenticado'], 401); }
  
  $rol_actual = strtolower($u['rol']);
  $roles_permitidos = array_map('strtolower', $roles);
  
  if (!in_array($rol_actual, $roles_permitidos, true)) {
    enviar_json(['ok'=>false,'error'=>'Sin permiso (rol)'], 403);
  }
}