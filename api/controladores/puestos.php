<?php
// =============================================
// Catálogo de Puestos (CRUD simple)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';
$pdo = obtener_conexion();

function asegurar_tabla_puestos(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS puestos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// LISTA: GET /puestos/lista?buscar=&inactivos=0|1
if ($_GET['ruta'] === 'puestos/lista') {
  asegurar_tabla_puestos($pdo);
  $buscar = trim($_GET['buscar'] ?? '');
  $inact = (int)($_GET['inactivos'] ?? 0);
  $sql = "SELECT id, nombre, activo FROM puestos WHERE 1=1";
  $p = [];
  if (!$inact) { $sql .= " AND activo=1"; }
  if ($buscar!==''){ $sql .= " AND nombre LIKE CONCAT('%',:b,'%')"; $p[':b']=$buscar; }
  $sql .= " ORDER BY nombre ASC";
  $st = $pdo->prepare($sql);
  $st->execute($p);
  enviar_json(['ok'=>true,'puestos'=>$st->fetchAll()]);
}

// CREAR: POST /puestos/crear {nombre}
if ($_GET['ruta'] === 'puestos/crear') {
  asegurar_tabla_puestos($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $nombre = trim($j['nombre'] ?? '');
  if ($nombre==='') enviar_json(['ok'=>false,'error'=>'Falta nombre'],400);
  try {
    $st = $pdo->prepare("INSERT INTO puestos (nombre, activo) VALUES (?,1)");
    $st->execute([$nombre]);
    enviar_json(['ok'=>true,'id'=>$pdo->lastInsertId(),'mensaje'=>'Puesto creado']);
  } catch (Exception $e) {
    enviar_json(['ok'=>false,'error'=>'Nombre de puesto duplicado'],409);
  }
}

// RENOMBRAR: POST /puestos/renombrar {id, nuevo_nombre}
if ($_GET['ruta'] === 'puestos/renombrar') {
  asegurar_tabla_puestos($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  $nuevo = trim($j['nuevo_nombre'] ?? '');
  if (!$id || $nuevo==='') enviar_json(['ok'=>false,'error'=>'Datos incompletos'],400);
  try {
    $st = $pdo->prepare("UPDATE puestos SET nombre=? WHERE id=?");
    $st->execute([$nuevo,$id]);
    enviar_json(['ok'=>true,'mensaje'=>'Puesto actualizado']);
  } catch (Exception $e) {
    enviar_json(['ok'=>false,'error'=>'Nombre de puesto duplicado'],409);
  }
}

// ELIMINAR: POST /puestos/eliminar {id}
if ($_GET['ruta'] === 'puestos/eliminar') {
  asegurar_tabla_puestos($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);
  // Soft delete
  $pdo->prepare("UPDATE puestos SET activo=0 WHERE id=?")->execute([$id]);
  enviar_json(['ok'=>true,'mensaje'=>'Puesto eliminado']);
}
