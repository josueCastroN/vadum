<?php
// =============================================
// Regiones (CRUD simple)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';
$pdo = obtener_conexion();

/* LISTA: GET /regiones/lista?buscar= */
if ($_GET['ruta'] === 'regiones/lista') {
  $buscar = trim($_GET['buscar'] ?? '');
  $sql = "SELECT id, nombre FROM regiones";
  $p = [];
  if ($buscar!==''){ $sql.=" WHERE nombre LIKE CONCAT('%',:b,'%')"; $p[':b']=$buscar; }
  $sql.=" ORDER BY nombre ASC";
  $st=$pdo->prepare($sql); $st->execute($p);
  enviar_json(['ok'=>true,'regiones'=>$st->fetchAll()]);
}

/* CREAR: POST /regiones/crear {nombre} */
if ($_GET['ruta'] === 'regiones/crear') {
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $nombre = trim($j['nombre'] ?? '');
  if ($nombre==='') enviar_json(['ok'=>false,'error'=>'Falta nombre'],400);

  try{
    $pdo->prepare("INSERT INTO regiones (nombre) VALUES (?)")->execute([$nombre]);
    enviar_json(['ok'=>true,'id'=>$pdo->lastInsertId(),'mensaje'=>'Región creada']);
  }catch(Exception $e){
    enviar_json(['ok'=>false,'error'=>'Nombre de región duplicado'],409);
  }
}

/* RENOMBRAR: POST /regiones/renombrar {id, nuevo_nombre} */
if ($_GET['ruta'] === 'regiones/renombrar') {
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  $nuevo = trim($j['nuevo_nombre'] ?? '');
  if (!$id || $nuevo==='') enviar_json(['ok'=>false,'error'=>'Datos incompletos'],400);

  try{
    $pdo->prepare("UPDATE regiones SET nombre=? WHERE id=?")->execute([$nuevo,$id]);
    enviar_json(['ok'=>true,'mensaje'=>'Región actualizada']);
  }catch(Exception $e){
    enviar_json(['ok'=>false,'error'=>'Nombre duplicado'],409);
  }
}

/* ELIMINAR: POST /regiones/eliminar {id} */
if ($_GET['ruta'] === 'regiones/eliminar') {
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);

  // Como empleados.region es texto, no hay FK; solo borramos.
  $pdo->prepare("DELETE FROM regiones WHERE id=?")->execute([$id]);
  enviar_json(['ok'=>true,'mensaje'=>'Región eliminada']);
}

