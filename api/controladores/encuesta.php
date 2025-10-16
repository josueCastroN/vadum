<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();
$e = json_decode(file_get_contents('php://input'), true);
if (!is_array($e)) $e = $_POST;

$edif  = (int)($e['edificio_id'] ?? 0);
$noemp = trim($e['no_emp'] ?? '');
$fecha = trim($e['fecha'] ?? date('Y-m-d'));
$s     = (int)($e['servicio'] ?? 0);
$a     = (int)($e['actitud'] ?? 0);
$r     = (int)($e['respuesta'] ?? 0);
$c     = (int)($e['confiabilidad'] ?? 0);
$cli   = trim($e['cliente_id'] ?? 'cli');
$com   = $e['comentarios'] ?? null;


$u = usuario_actual();
if ($u && $u['rol']==='cliente') {
  // Si es cliente, forzamos su punto asignado
  if (!($u['punto_id'] ?? null)) enviar_json(['ok'=>false,'error'=>'Cliente sin punto asignado'],403);
  $edif = (int)$u['punto_id'];
}

if (!$edif || $noemp==='') enviar_json(['ok'=>false,'error'=>'Faltan datos'],400);

$prom = round(($s+$a+$r+$c)/4, 3);
$cal0 = convertir_1a5_a_0a100($prom);

$sql = "INSERT INTO encuesta_cliente
(edificio_id,no_emp,fecha,servicio,actitud,respuesta,confiabilidad,prom_1_5,cal_satisf_0_100,cliente_id,comentarios)
VALUES (?,?,?,?,?,?,?,?,?,?,?)";
$st = $pdo->prepare($sql);
$st->execute([$edif,$noemp,$fecha,$s,$a,$r,$c,$prom,$cal0,$cli,$com]);

enviar_json(['ok'=>true,'prom_1_5'=>$prom,'cal_0_100'=>$cal0]);
