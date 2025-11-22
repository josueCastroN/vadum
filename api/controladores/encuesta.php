<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php';
require_once __DIR__ . '/../seguridad.php';

$pdo  = obtener_conexion();
$ruta = $_GET['ruta'] ?? '';

/* =====================
   GUARDAR (POST)
   /encuesta/guardar { edificio_id, no_emp, fecha?, servicio, actitud, respuesta, confiabilidad, comentarios? }
   Roles: admin, cliente (controlado también en index.php)
   ===================== */
if ($ruta === 'encuesta/guardar') {
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
  if ($u && ($u['rol'] ?? '') === 'cliente') {
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
}

/* =====================
   HISTORIAL (GET)
   /encuesta/historial?punto_id=&no_emp=&desde=YYYY-MM&hasta=YYYY-MM
   Roles: admin, supervisor, cliente (cliente restringido a su punto)
   ===================== */
if ($ruta === 'encuesta/historial') {
  requerir_roles(['admin','supervisor','cliente']);
  $u = usuario_actual();

  $punto = isset($_GET['punto_id']) ? (int)$_GET['punto_id'] : 0;
  $no_emp = trim($_GET['no_emp'] ?? '');
  $desdeM = trim($_GET['desde'] ?? '');
  $hastaM = trim($_GET['hasta'] ?? '');

  if ($u && ($u['rol'] ?? '') === 'cliente') {
    if (!($u['punto_id'] ?? null)) enviar_json(['ok'=>false,'error'=>'Cliente sin punto asignado'],403);
    $punto = (int)$u['punto_id'];
  }

  $sql = "SELECT ec.fecha, ec.edificio_id AS punto_id, p.nombre AS punto, p.region,
                 ec.no_emp,
                 CONCAT(e.nombres,' ',e.apellidos) AS nombre,
                 e.puesto,
                 ec.servicio, ec.actitud, ec.respuesta, ec.confiabilidad,
                 ec.prom_1_5, ec.cal_satisf_0_100, ec.comentarios, ec.cliente_id
          FROM encuesta_cliente ec
          LEFT JOIN empleados e ON e.no_emp = ec.no_emp
          LEFT JOIN puntos p ON p.id = ec.edificio_id
          WHERE 1=1";
  $p = [];
  if ($punto) { $sql .= " AND ec.edificio_id = :pid"; $p[':pid'] = $punto; }
  if ($no_emp !== '') { $sql .= " AND ec.no_emp = :ne"; $p[':ne'] = $no_emp; }
  if ($desdeM !== '') { $sql .= " AND ec.fecha >= DATE_FORMAT(:d,'%Y-%m-01')"; $p[':d'] = $desdeM.'-01'; }
  if ($hastaM !== '') { $sql .= " AND ec.fecha <= LAST_DAY(DATE_FORMAT(:h,'%Y-%m-01'))"; $p[':h'] = $hastaM.'-01'; }
  $sql .= " ORDER BY ec.fecha DESC, ec.no_emp ASC LIMIT 500";

  try {
    $st = $pdo->prepare($sql); $st->execute($p);
    enviar_json(['ok'=>true,'historial'=>$st->fetchAll()]);
  } catch (Throwable $ex) {
    enviar_json(['ok'=>false,'error'=>'DB: '.$ex->getMessage()],500);
  }
}

// Si llegamos aquí, no coincidió ruta
enviar_json(['ok'=>false,'error'=>'Ruta de encuesta no válida'],404);

