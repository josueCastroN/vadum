<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();

// /api/index.php?ruta=auth/login  (POST {usuario, password})
if ($_GET['ruta']==='auth/login') {
  $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $usr = trim($e['usuario'] ?? '');
  $pwd = trim($e['password'] ?? '');
  if ($usr==='' || $pwd==='') enviar_json(['ok'=>false,'error'=>'Faltan usuario/contraseña'],400);

  $st = $pdo->prepare("SELECT * FROM usuarios WHERE usuario=? AND activo=1 LIMIT 1");
  $st->execute([$usr]);
  $u = $st->fetch();
  if (!$u) enviar_json(['ok'=>false,'error'=>'Usuario/clave inválidos'],401);

  $guardado = $u['password_hash'];
  $ok = str_starts_with($guardado, '$2y$') ? password_verify($pwd, $guardado) : ($pwd === $guardado);

  if (!$ok) enviar_json(['ok'=>false,'error'=>'Usuario/clave inválidos'],401);

  iniciar_sesion_usuario($u);
  enviar_json(['ok'=>true,'usuario'=>usuario_actual()]);
}

// /api/index.php?ruta=auth/me (GET)
if ($_GET['ruta']==='auth/me') {
  $u = usuario_actual();
  if (!$u) enviar_json(['ok'=>false,'logeado'=>false], 200);
  enviar_json(['ok'=>true,'logeado'=>true,'usuario'=>$u]);
}

// /api/index.php?ruta=auth/logout (POST)
if ($_GET['ruta']==='auth/logout') {
  cerrar_sesion();
  enviar_json(['ok'=>true]);
}
