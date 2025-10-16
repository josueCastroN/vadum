<?php
$ruta   = $_GET['ruta']   ?? '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

require_once __DIR__ . '/02_bd.php';
require_once __DIR__ . '/seguridad.php';

/* === Mapa de protecciones por ruta === */
$protecciones = [
  'fisicas/sesion'     => ['supervisor','admin'],
  'fisicas/registro'   => ['supervisor','admin'],
  'fisicas/cerrar'     => ['supervisor','admin'],
  'mensual/guardar'    => ['supervisor','admin'],
  'encuesta/guardar'   => ['cliente','admin'],
  'empleados/buscar'   => ['supervisor','admin'],
  'empleados/por_punto'=> ['supervisor','admin','cliente'],
  'incentivo/calcular' => ['admin','supervisor','vigilante'],
];

if (isset($protecciones[$ruta])) { requerir_roles($protecciones[$ruta]); }

switch (true) {
  /* AUTH */
  case str_starts_with($ruta,'auth/') : require __DIR__ . '/controladores/auth.php'; break;

  /* EXISTENTES */
  case $ruta === 'salud' && $metodo === 'GET':
    require __DIR__ . '/controladores/salud.php'; break;

  case $ruta === 'empleados/buscar' && $metodo === 'GET':
  case $ruta === 'empleados/por_punto' && $metodo === 'GET':
    require __DIR__ . '/controladores/empleados.php'; break;

  case $ruta === 'fisicas/sesion' && $metodo === 'POST':
  case $ruta === 'fisicas/registro' && $metodo === 'POST':
  case $ruta === 'fisicas/cerrar' && $metodo === 'POST':
    require __DIR__ . '/controladores/fisicas.php'; break;

  case $ruta === 'mensual/guardar' && $metodo === 'POST':
    require __DIR__ . '/controladores/mensual.php'; break;

  case $ruta === 'encuesta/guardar' && $metodo === 'POST':
    require __DIR__ . '/controladores/encuesta.php'; break;

  case $ruta === 'firmas/guardar' && $metodo === 'POST':
    require __DIR__ . '/controladores/firmas.php'; break;

  case $ruta === 'incentivo/calcular' && $metodo === 'GET':
    require __DIR__ . '/controladores/incentivo.php'; break;

  default:
    enviar_json(['ok'=>false,'error'=>'Ruta no encontrada','ruta'=>$ruta], 404);
}
