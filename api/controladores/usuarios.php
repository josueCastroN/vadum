<?php
// =============================================
// Gestión de usuarios (crear/asignar a empleado, reset, desactivar)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();

function asegurar_tabla_usuarios(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(30) NOT NULL DEFAULT 'vigilante',
    empleado_no_emp VARCHAR(30) NULL,
    punto_id BIGINT UNSIGNED NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// LISTA: GET /usuarios/lista?buscar=
if ($_GET['ruta'] === 'usuarios/lista') {
  asegurar_tabla_usuarios($pdo);
  $b = trim($_GET['buscar'] ?? '');
  $sql = "SELECT u.id, u.usuario, u.rol, u.activo, u.empleado_no_emp,
                 CONCAT(e.nombres,' ',e.apellidos) AS nombre_empleado
          FROM usuarios u
          LEFT JOIN empleados e ON e.no_emp = u.empleado_no_emp
          WHERE 1=1";
  $p = [];
  if ($b !== '') {
    $sql .= " AND (u.usuario LIKE CONCAT('%',:b,'%') OR u.empleado_no_emp LIKE CONCAT('%',:b,'%') OR e.nombres LIKE CONCAT('%',:b,'%') OR e.apellidos LIKE CONCAT('%',:b,'%'))";
    $p[':b'] = $b;
  }
  $sql .= " ORDER BY u.usuario ASC LIMIT 1000";
  $st = $pdo->prepare($sql);
  $st->execute($p);
  enviar_json(['ok'=>true,'usuarios'=>$st->fetchAll()]);
}

// CREAR/ASIGNAR: POST /usuarios/crear {usuario, contrasena, rol, empleado_no_emp}
if ($_GET['ruta'] === 'usuarios/crear') {
  asegurar_tabla_usuarios($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $usuario = trim($j['usuario'] ?? '');
  $pwd     = trim($j['contrasena'] ?? '');
  $rol     = trim($j['rol'] ?? 'vigilante');
  $no_emp  = trim($j['empleado_no_emp'] ?? '');
  if ($usuario==='' || $pwd==='' || $rol==='') enviar_json(['ok'=>false,'error'=>'Faltan datos obligatorios'],400);
  $hash = password_hash($pwd, PASSWORD_BCRYPT);
  try {
    $st = $pdo->prepare("INSERT INTO usuarios (usuario,password_hash,rol,empleado_no_emp,activo) VALUES (?,?,?,?,1)");
    $st->execute([$usuario,$hash,$rol,($no_emp ?: null)]);
    enviar_json(['ok'=>true,'id'=>$pdo->lastInsertId(),'mensaje'=>'Usuario creado']);
  } catch (Exception $e) {
    enviar_json(['ok'=>false,'error'=>'Nombre de usuario duplicado'],409);
  }
}

// RESET PASSWORD: POST /usuarios/reset {id, nueva}
if ($_GET['ruta'] === 'usuarios/reset') {
  asegurar_tabla_usuarios($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  $n  = trim($j['nueva'] ?? '');
  if (!$id || $n==='') enviar_json(['ok'=>false,'error'=>'Datos incompletos'],400);
  $hash = password_hash($n, PASSWORD_BCRYPT);
  $pdo->prepare("UPDATE usuarios SET password_hash=? WHERE id=?")->execute([$hash,$id]);
  enviar_json(['ok'=>true,'mensaje'=>'Contraseña actualizada']);
}

// ACTUALIZAR: POST /usuarios/actualizar {id, usuario?, rol?, empleado_no_emp?}
if ($_GET['ruta'] === 'usuarios/actualizar') {
  asegurar_tabla_usuarios($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);
  $usuario = isset($j['usuario']) ? trim($j['usuario']) : null;
  $rol     = isset($j['rol']) ? trim($j['rol']) : null;
  $no_emp  = array_key_exists('empleado_no_emp',$j) ? (trim($j['empleado_no_emp']) ?: null) : null;
  $campos = [];$p=[];
  if ($usuario!==null) { $campos[]='usuario=?'; $p[]=$usuario; }
  if ($rol!==null)     { $campos[]='rol=?'; $p[]=$rol; }
  if ($no_emp!==null)  { $campos[]='empleado_no_emp=?'; $p[]=$no_emp; }
  if (!$campos) enviar_json(['ok'=>false,'error'=>'Nada que actualizar'],400);
  $p[]=$id;
  try {
    $pdo->prepare("UPDATE usuarios SET ".implode(',', $campos)." WHERE id=?")->execute($p);
    enviar_json(['ok'=>true,'mensaje'=>'Usuario actualizado']);
  } catch (Exception $e) {
    enviar_json(['ok'=>false,'error'=>'Usuario duplicado'],409);
  }
}

// DESACTIVAR: POST /usuarios/eliminar {id}
if ($_GET['ruta'] === 'usuarios/eliminar') {
  asegurar_tabla_usuarios($pdo);
  $j = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $id = (int)($j['id'] ?? 0);
  if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);
  $pdo->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$id]);
  enviar_json(['ok'=>true,'mensaje'=>'Usuario desactivado']);
}

