<?php
// =============================================
// Manejo de sesión y helpers de seguridad
// =============================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

function cerrar_sesion(): void {
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
  }
  session_destroy();
}

function requerir_roles(array $roles): void {
  require_once __DIR__ . '/02_bd.php';
  $u = usuario_actual();
  if (!$u) { enviar_json(['ok'=>false,'error'=>'No autenticado'], 401); }
  if (!in_array($u['rol'], $roles, true)) {
    enviar_json(['ok'=>false,'error'=>'Sin permiso (rol)'], 403);
  }
}
