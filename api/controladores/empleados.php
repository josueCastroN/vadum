<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';

// /empleados/por_punto?punto_id=#
if ($_GET['ruta']==='empleados/por_punto') {
  $u = usuario_actual();
  $p = isset($_GET['punto_id']) ? (int)$_GET['punto_id'] : 0;
  if ($u && $u['rol']==='cliente') {
    // cliente sólo ve su propio punto
    $p = (int)($u['punto_id'] ?? 0);
    if (!$p) enviar_json(['ok'=>false,'error'=>'Cliente sin punto asignado'],403);
  }
  if (!$p) enviar_json(['ok'=>false,'error'=>'Falta punto_id'],400);

  $pdo = obtener_conexion();
  $st = $pdo->prepare("SELECT no_emp, CONCAT(nombres,' ',apellidos) AS nombre, foto_url
                       FROM empleados
                       WHERE punto_id = ? AND estatus='ACTIVO'
                       ORDER BY apellidos, nombres");
  $st->execute([$p]);
  enviar_json(['ok'=>true,'resultados'=>$st->fetchAll()]);
}


$texto = trim($_GET['texto'] ?? '');
if ($texto === '') enviar_json(['ok'=>false,'error'=>'Falta parámetro texto'], 400);

$pdo = obtener_conexion();
$sql = "SELECT no_emp,
               CONCAT(nombres,' ',apellidos) AS nombre,
               foto_url
        FROM empleados
        WHERE no_emp = :exacto
           OR CONCAT(nombres,' ',apellidos) LIKE CONCAT('%', :like ,'%')
        ORDER BY apellidos, nombres
        LIMIT 50";
$st = $pdo->prepare($sql);
$st->execute([':exacto'=>$texto, ':like'=>$texto]);
enviar_json(['ok'=>true,'resultados'=>$st->fetchAll()]);
