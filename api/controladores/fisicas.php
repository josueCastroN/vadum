<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php';

$pdo = obtener_conexion();
$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) $entrada = $_POST;

// Crear sesión
if ($_GET['ruta'] === 'fisicas/sesion') {
    $fecha = $entrada['fecha'] ?? date('Y-m-d');
    $tri   = (int)($entrada['trimestre'] ?? 1);
    $eval  = trim($entrada['evaluador_id'] ?? 'evaluador');
    $sede  = trim($entrada['sede'] ?? 'sede');

    $st = $pdo->prepare("INSERT INTO fisicas_sesion (fecha,trimestre,evaluador_id,sede,estado)
                         VALUES (?,?,?,?, 'BORRADOR')");
    $st->execute([$fecha,$tri,$eval,$sede]);
    enviar_json(['ok'=>true,'sesion_id'=>$pdo->lastInsertId()]);
}

// Insertar/actualizar renglón
if ($_GET['ruta'] === 'fisicas/registro') {
    $sesion_id = (int)($entrada['sesion_id'] ?? 0);
    $no_emp    = trim($entrada['no_emp'] ?? '');
    $edad      = (int)($entrada['edad'] ?? 0);

    $v = [
        'm'  => (int)($entrada['v_12min_m'] ?? 0),
        's'  => (float)($entrada['v_100m_s'] ?? 0),
        'ab' => (int)($entrada['v_abdom'] ?? 0),
        'lg' => (int)($entrada['v_lagart'] ?? 0),
        'br' => (int)($entrada['v_barras'] ?? 0),
        'bu' => (int)($entrada['v_burpees'] ?? 0),
    ];
    if (!$sesion_id || $no_emp==='') enviar_json(['ok'=>false,'error'=>'Faltan datos obligatorios'],400);

    $cal = calcular_calificacion_fisica($pdo, $edad, $v);

    $sql = "INSERT INTO fisicas_registro
            (sesion_id,no_emp,edad,v_12min_m,v_100m_s,v_abdom,v_lagart,v_barras,v_burpees,cal_fisica)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              edad=VALUES(edad),
              v_12min_m=VALUES(v_12min_m),
              v_100m_s=VALUES(v_100m_s),
              v_abdom=VALUES(v_abdom),
              v_lagart=VALUES(v_lagart),
              v_barras=VALUES(v_barras),
              v_burpees=VALUES(v_burpees),
              cal_fisica=VALUES(cal_fisica)";
    $st = $pdo->prepare($sql);
    $st->execute([$sesion_id,$no_emp,$edad,$v['m'],$v['s'],$v['ab'],$v['lg'],$v['br'],$v['bu'],$cal]);

    enviar_json(['ok'=>true,'cal_fisica'=>$cal]);
}

// Cerrar sesión (bloquear ediciones)
if ($_GET['ruta'] === 'fisicas/cerrar') {
    $sesion_id = (int)($entrada['sesion_id'] ?? 0);
    if (!$sesion_id) enviar_json(['ok'=>false,'error'=>'Falta sesion_id'],400);
    $pdo->prepare("UPDATE fisicas_sesion SET estado='CERRADA' WHERE id=?")->execute([$sesion_id]);
    enviar_json(['ok'=>true,'sesion_id'=>$sesion_id,'estado'=>'CERRADA']);
}
