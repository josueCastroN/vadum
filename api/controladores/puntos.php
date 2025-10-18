<?php
// =============================================
// Puntos de trabajo (CRUD simple)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';
$pdo = obtener_conexion();

/* LISTA: GET /puntos/lista?buscar=&region= */
if ($_GET['ruta'] === 'puntos/lista') {
  $buscar = trim($_GET['buscar'] ?? '');
  $region = trim($_GET['region'] ?? '');
  $sql = "SELECT id, nombre, region FROM puntos WHERE 1=1";
  $p = [];
  if ($buscar!==''){ $sql.=" AND nombre LIKE CONCAT('%',:b,'%')"; $p[':b']=$buscar; }
  if ($region!==''){ $sql.=" AND region = :r"; $p[':r']=$region; }
  $sql.=" ORDER BY nombre ASC LIMIT 1000";
  $st=$pdo->prepare($sql); $st->execute($p);
  enviar_json(['ok'=>true,'puntos'=>$st->fetchAll()]);
}

/* CREAR: POST /puntos/crear {nombre, region} */
if ($_GET['ruta'] === 'puntos/crear') {
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $nombre = trim($j['nombre'] ?? '');
  $region = trim($j['region'] ?? '');
  if ($nombre==='' || $region==='') enviar_json(['ok'=>false,'error'=>'Falta nombre o región'],400);

  $st=$pdo->prepare("INSERT INTO puntos (nombre, region) VALUES (?,?)");
  try {
    $st->execute([$nombre,$region]);
    enviar_json(['ok'=>true,'id'=>$pdo->lastInsertId(),'mensaje'=>'Punto creado']);
  } catch(Exception $e){
    enviar_json(['ok'=>false,'error'=>'Nombre de punto duplicado'],409);
  }
}

/* RENOMBRAR: POST /puntos/renombrar {id, nuevo_nombre, nueva_region?} */
if ($_GET['ruta'] === 'puntos/renombrar') {
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  $nuevo = trim($j['nuevo_nombre'] ?? '');
  $nreg = isset($j['nueva_region']) ? trim($j['nueva_region']) : null;
  if (!$id || $nuevo==='') enviar_json(['ok'=>false,'error'=>'Datos incompletos'],400);

  $sql = "UPDATE puntos SET nombre = :n" . ($nreg!==null ? ", region=:r" : "") . " WHERE id=:id";
  $p = [':n'=>$nuevo, ':id'=>$id];
  if ($nreg!==null) $p[':r']=$nreg;

  try {
    $st=$pdo->prepare($sql); $st->execute($p);
    enviar_json(['ok'=>true,'mensaje'=>'Punto actualizado']);
  } catch(Exception $e){
    enviar_json(['ok'=>false,'error'=>'Nombre duplicado'],409);
  }
}

/* ELIMINAR: POST /puntos/eliminar {id} */
if ($_GET['ruta'] === 'puntos/eliminar') {
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);

  // Evitar borrar si hay empleados usando ese punto
  $st=$pdo->prepare("SELECT COUNT(*) c FROM empleados WHERE punto_id=?");
  $st->execute([$id]);
  $c = (int)$st->fetchColumn();
  if ($c>0) enviar_json(['ok'=>false,'error'=>'No se puede eliminar: hay empleados asignados'],409);

  $pdo->prepare("DELETE FROM puntos WHERE id=?")->execute([$id]);
  enviar_json(['ok'=>true,'mensaje'=>'Punto eliminado']);
}
