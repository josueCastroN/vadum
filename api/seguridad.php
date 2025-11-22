<?php
// =============================================
// Manejo de sesión y helpers de seguridad
// =============================================

// ¡LÍNEA ELIMINADA! Ahora session_start() solo ocurre en index.php.

/**
 * Helper para enviar respuestas JSON y terminar la ejecución.
 */
if (!function_exists('enviar_json')) {
    function enviar_json(array $datos, int $codigo = 200): void {
        // Limpia cualquier salida previa (avisos/errores) para no romper el JSON
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
        }
        @ini_set('default_charset','UTF-8');
        header('Content-Type: application/json; charset=utf-8');
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
 */
function requerir_roles(array $roles): void {
  $u = usuario_actual();
  
  // 🛑 Verificación de autenticación (responde 401 si no hay sesión válida)
  if (!$u) { enviar_json(['ok'=>false,'error'=>'No autenticado'], 401); } 
  
  $rol_actual = strtolower($u['rol']);
  $roles_permitidos = array_map('strtolower', $roles);
  
  // 🛑 Verificación de permiso (responde 403 si el rol no coincide)
  if (!in_array($rol_actual, $roles_permitidos, true)) {
    enviar_json(['ok'=>false,'error'=>'Sin permiso (rol)'], 403);
  }
}
