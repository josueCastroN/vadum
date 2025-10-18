<?php
// =============================================
// Controlador de Empleados (alta/baja/lista/reactivar/exportar)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();

/* ========== LISTA (ACTIVO o BAJA) ========== */
// GET /empleados/lista?estado=ACTIVO|BAJA&buscar=&region=&punto_id=
// api/controladores/empleados.php
// api/controladores/empleados.php
if ($_GET['ruta'] === 'empleados/lista') {
  $estado = $_GET['estado'] ?? 'ACTIVO';
  $buscar = trim($_GET['buscar'] ?? '');
  $region = trim($_GET['region'] ?? '');
  $punto  = trim($_GET['punto_id'] ?? '');

  $sql = "SELECT
            e.no_emp,
            CONCAT(e.nombres,' ',e.apellidos) AS nombre,
            e.fecha_nacimiento,
            e.fecha_alta,
            p.nombre AS punto_nombre,
            e.region
          FROM empleados e
          LEFT JOIN puntos p ON p.id = e.punto_id
          WHERE e.estatus = :estado";
  $p = [':estado'=>$estado];

  if ($buscar !== '') {
    $sql .= " AND (e.no_emp LIKE CONCAT('%',:b,'%') OR e.nombres LIKE CONCAT('%',:b,'%') OR e.apellidos LIKE CONCAT('%',:b,'%'))";
    $p[':b'] = $buscar;
  }
  if ($region !== '') {
    $sql .= " AND e.region = :r"; $p[':r'] = $region;
  }
  if ($punto !== '') {
    $sql .= " AND e.punto_id = :p"; $p[':p'] = (int)$punto;
  }
  $sql .= " ORDER BY nombre ASC LIMIT 1000";

  $st = $pdo->prepare($sql);
  $st->execute($p);
  enviar_json(['ok'=>true, 'empleados'=>$st->fetchAll()]);
}

/* ========== ALTA/ACTUALIZA ========== */
// POST /empleados/alta {no_emp,...}
if ($_GET['ruta'] === 'empleados/alta') {
  $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $no_emp = trim($e['no_emp'] ?? '');
$nombres = trim($e['nombres'] ?? '');
$apellidos = trim($e['apellidos'] ?? '');
$puesto = trim($e['puesto'] ?? 'Vigilante');
$region = trim($e['region'] ?? 'N/A');
$punto_id = (int)($e['punto_id'] ?? 0);
$foto_url = $e['foto_url'] ?? null;
$fnac = $e['fecha_nacimiento'] ?? null;
$falta = $e['fecha_alta'] ?? date('Y-m-d');

if ($no_emp==='' || $nombres==='' || $apellidos==='' || !$punto_id) {
  enviar_json(['ok'=>false,'error'=>'Datos obligatorios faltantes'],400);
}

// fragmento en empleados/alta
$fnac  = $e['fecha_nacimiento'] ?? null;
$falta = $e['fecha_alta'] ?? date('Y-m-d');

$sql = "INSERT INTO empleados
          (no_emp, nombres, apellidos, puesto, region, punto_id, foto_url, fecha_nacimiento, fecha_alta, estatus)
        VALUES (?,?,?,?,?,?,?,?,?, 'ACTIVO')
        ON DUPLICATE KEY UPDATE
          nombres=VALUES(nombres),
          apellidos=VALUES(apellidos),
          puesto=VALUES(puesto),
          region=VALUES(region),
          punto_id=VALUES(punto_id),
          foto_url=VALUES(foto_url),
          fecha_nacimiento=VALUES(fecha_nacimiento),
          fecha_alta=VALUES(fecha_alta),
          estatus='ACTIVO'";
$pdo->prepare($sql)->execute([$no_emp,$nombres,$apellidos,$puesto,$region,$punto_id,$foto_url,$fnac,$falta]);

enviar_json(['ok'=>true,'mensaje'=>'Empleado dado de alta/actualizado','no_emp'=>$no_emp]);
}

/* ========== BAJA ========== */
// POST /empleados/baja {no_emp, fecha_baja, motivo}
if ($_GET['ruta'] === 'empleados/baja') {
  $u = usuario_actual();
  $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $no_emp = trim($e['no_emp'] ?? '');
  $fecha_baja = $e['fecha_baja'] ?? date('Y-m-d');
  $motivo = $e['motivo'] ?? null;
  if ($no_emp==='') enviar_json(['ok'=>false,'error'=>'Falta no_emp'],400);

  $pdo->prepare("UPDATE empleados SET estatus='BAJA' WHERE no_emp=?")->execute([$no_emp]);
  $pdo->prepare("INSERT INTO bajas_empleados (no_emp, fecha_baja, motivo, usuario_baja) VALUES (?,?,?,?)")
      ->execute([$no_emp, $fecha_baja, $motivo, $u['usuario'] ?? 'sistema']);

  enviar_json(['ok'=>true,'mensaje'=>'Empleado dado de baja','no_emp'=>$no_emp]);
}

/* ========== REACTIVAR ========== */
// POST /empleados/reactivar {no_emp}
if ($_GET['ruta'] === 'empleados/reactivar') {
  $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $no_emp = trim($e['no_emp'] ?? '');
  if ($no_emp==='') enviar_json(['ok'=>false,'error'=>'Falta no_emp'],400);

  $pdo->prepare("UPDATE empleados SET estatus='ACTIVO' WHERE no_emp=?")->execute([$no_emp]);
  enviar_json(['ok'=>true,'mensaje'=>'Empleado reactivado','no_emp'=>$no_emp]);
}

/* ========== RESUMEN BAJAS ========== */
// GET /empleados/resumen_bajas?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&buscar=
if ($_GET['ruta'] === 'empleados/resumen_bajas') {
  $desde = $_GET['desde'] ?? date('Y-m-01');
  $hasta = $_GET['hasta'] ?? date('Y-m-t');
  $buscar = trim($_GET['buscar'] ?? '');

  $sql = "SELECT b.id, b.no_emp, CONCAT(e.nombres,' ',e.apellidos) AS nombre,
                 b.fecha_baja, b.motivo, b.usuario_baja
          FROM bajas_empleados b
          LEFT JOIN empleados e ON e.no_emp = b.no_emp
          WHERE b.fecha_baja BETWEEN :desde AND :hasta";
  $params = [':desde'=>$desde, ':hasta'=>$hasta];

  if ($buscar!=='') {
    $sql .= " AND (b.no_emp = :b OR CONCAT(e.nombres,' ',e.apellidos) LIKE CONCAT('%', :blike ,'%'))";
    $params[':b'] = $buscar;
    $params[':blike'] = $buscar;
  }

  $sql .= " ORDER BY b.fecha_baja DESC, b.id DESC LIMIT 1000";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  enviar_json(['ok'=>true,'bajas'=>$st->fetchAll()]);
}

/* ========== EXPORTAR ACTIVOS CSV ========== */
// GET /empleados/exportar_activos_csv?region=&punto_id=&buscar=
if ($_GET['ruta'] === 'empleados/exportar_activos_csv') {
  $region = trim($_GET['region'] ?? '');
  $punto  = isset($_GET['punto_id']) && $_GET['punto_id'] !== '' ? (int)$_GET['punto_id'] : null;
  $buscar = trim($_GET['buscar'] ?? '');

  $params = [':estado'=>'ACTIVO'];
  $sql = "SELECT no_emp, CONCAT(nombres,' ',apellidos) AS nombre, puesto, region, turno, punto_id
          FROM empleados WHERE estatus=:estado";
  if ($region!==''){ $sql.=" AND region=:reg"; $params[':reg']=$region; }
  if ($punto!==null){ $sql.=" AND punto_id=:p"; $params[':p']=$punto; }
  if ($buscar!==''){
    $sql.=" AND (no_emp=:b OR CONCAT(nombres,' ',apellidos) LIKE CONCAT('%', :blike ,'%'))";
    $params[':b']=$buscar; $params[':blike']=$buscar;
  }
  $sql.=" ORDER BY apellidos,nombres";

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=empleados_activos.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['No.Emp','Nombre','Puesto','Región','Turno','Punto']);
  $st = $pdo->prepare($sql); $st->execute($params);
  while($f = $st->fetch()){ fputcsv($out, [$f['no_emp'],$f['nombre'],$f['puesto'],$f['region'],$f['turno'],$f['punto_id']]); }
  fclose($out); exit;
}

/* ========== EXPORTAR BAJAS CSV / HTML ========== */
// GET /empleados/exportar_bajas_csv?desde=&hasta=&buscar=
if ($_GET['ruta'] === 'empleados/exportar_bajas_csv') {
  $desde = $_GET['desde'] ?? date('Y-m-01');
  $hasta = $_GET['hasta'] ?? date('Y-m-t');
  $buscar = trim($_GET['buscar'] ?? '');

  $params = [':desde'=>$desde, ':hasta'=>$hasta];
  $sql = "SELECT b.id, b.no_emp, CONCAT(e.nombres,' ',e.apellidos) AS nombre,
                 b.fecha_baja, b.motivo, b.usuario_baja
          FROM bajas_empleados b
          LEFT JOIN empleados e ON e.no_emp=b.no_emp
          WHERE b.fecha_baja BETWEEN :desde AND :hasta";
  if ($buscar!==''){ $sql.=" AND (b.no_emp=:b OR CONCAT(e.nombres,' ',e.apellidos) LIKE CONCAT('%', :blike ,'%'))";
    $params[':b']=$buscar; $params[':blike']=$buscar; }
  $sql.=" ORDER BY b.fecha_baja DESC, b.id DESC";

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=bajas_empleados.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['ID','No.Emp','Nombre','Fecha baja','Motivo','Usuario']);
  $st=$pdo->prepare($sql); $st->execute($params);
  while($f=$st->fetch()){ fputcsv($out, [$f['id'],$f['no_emp'],$f['nombre'],$f['fecha_baja'],$f['motivo'],$f['usuario_baja']]); }
  fclose($out); exit;
}

// GET /empleados/exportar_bajas_html?desde=&hasta=&buscar=
if ($_GET['ruta'] === 'empleados/exportar_bajas_html') {
  $desde = $_GET['desde'] ?? date('Y-m-01');
  $hasta = $_GET['hasta'] ?? date('Y-m-t');
  $buscar = trim($_GET['buscar'] ?? '');

  $params = [':desde'=>$desde, ':hasta'=>$hasta];
  $sql = "SELECT b.id, b.no_emp, CONCAT(e.nombres,' ',e.apellidos) AS nombre,
                 b.fecha_baja, b.motivo, b.usuario_baja
          FROM bajas_empleados b
          LEFT JOIN empleados e ON e.no_emp=b.no_emp
          WHERE b.fecha_baja BETWEEN :desde AND :hasta";
  if ($buscar!==''){ $sql.=" AND (b.no_emp=:b OR CONCAT(e.nombres,' ',e.apellidos) LIKE CONCAT('%', :blike ,'%'))";
    $params[':b']=$buscar; $params[':blike']=$buscar; }
  $sql.=" ORDER BY b.fecha_baja DESC, b.id DESC";

  $st=$pdo->prepare($sql); $st->execute($params);
  $rows = $st->fetchAll();

  // Plantilla simple imprimible
  echo "<!doctype html><html lang='es'><head><meta charset='utf-8'><title>Bajas empleados</title>
        <style>body{font-family:system-ui;margin:20px} h1{margin:0 0 10px}
        table{border-collapse:collapse;width:100%} th,td{border:1px solid #ccc;padding:6px}
        th{background:#f3f3f3;text-align:left}</style></head><body>";
  echo "<h1>Resumen de bajas</h1>";
  echo "<p>Rango: <b>$desde</b> a <b>$hasta</b></p>";
  echo "<table><thead><tr><th>ID</th><th>No.Emp</th><th>Nombre</th><th>Fecha baja</th><th>Motivo</th><th>Usuario</th></tr></thead><tbody>";
  foreach($rows as $f){
    echo "<tr><td>{$f['id']}</td><td>{$f['no_emp']}</td><td>".htmlspecialchars($f['nombre']??'')."</td>
              <td>{$f['fecha_baja']}</td><td>".htmlspecialchars($f['motivo']??'')."</td><td>{$f['usuario_baja']}</td></tr>";
  }
  echo "</tbody></table></body></html>";
  exit;
}
