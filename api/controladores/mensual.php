<?php
// =============================================
// Controlador de Evaluación Mensual (Guardar/Leer)
// =============================================
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../03_utilidades.php'; // Contiene convertir_1a5_a_0a100
require_once __DIR__ . '/../seguridad.php'; // Contiene usuario_actual, enviar_json, obtener_rol_usuario

$pdo = obtener_conexion();

/* ========== POST /mensual/guardar: ALTA/ACTUALIZA ========== */
if ($_GET['ruta'] === 'mensual/guardar') {
    // 🔒 ACL: Doble chequeo. Solo Admin y Supervisor pueden guardar/actualizar evaluaciones.
    // Esto complementa el ACL en index.php. Usaremos la función de seguridad del otro archivo.
    requerir_roles(['admin', 'supervisor']); 

    $u = usuario_actual();
    $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];

    $no_emp = trim($e['no_emp'] ?? '');
    $mes = trim($e['mes'] ?? ''); // Formato esperado: YYYY-MM-01 (día 1)
    $punt = (int)($e['puntualidad'] ?? 0);
    $disc  = (int)($e['disciplina'] ?? 0);
    $imag = (int)($e['imagen'] ?? 0);
    $desem = (int)($e['desempeno'] ?? 0);
    
    // Usar el usuario actual si no se especifica uno, o el valor enviado si es Admin
    $super_actual = $u['usuario'] ?? 'sistema';
    $super = trim($e['supervisor_id'] ?? $super_actual); 

    if ($no_emp==='' || $mes==='') enviar_json(['ok'=>false,'error'=>'Faltan datos obligatorios (No. Empleado o Mes)'],400);

    // 1. CÁLCULO
    $prom = round(($punt+$disc+$imag+$desem)/4, 3);
    $cal0 = convertir_1a5_a_0a100($prom); // Asume que convertir_1a5_a_0a100(5) = 100 y convertir_1a5_a_0a100(1) = 0

    // 2. QUERY: INSERT ON DUPLICATE KEY UPDATE
    $sql = "INSERT INTO evaluacion_mensual
    (no_emp, mes, puntualidad, disciplina, imagen, desempeno, prom_1_5, cal_desemp_0_100, supervisor_id)
    VALUES (?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
    puntualidad=VALUES(puntualidad), disciplina=VALUES(disciplina), imagen=VALUES(imagen),
    desempeno=VALUES(desempeno), prom_1_5=VALUES(prom_1_5), cal_desemp_0_100=VALUES(cal_desemp_0_100),
    supervisor_id=VALUES(supervisor_id), fecha_modificacion=NOW()"; // Opcional: añadir fecha_modificacion

    $st = $pdo->prepare($sql);
    $st->execute([$no_emp, $mes, $punt, $disc, $imag, $desem, $prom, $cal0, $super]);

    enviar_json(['ok'=>true, 
                 'mensaje' => 'Evaluación guardada/actualizada',
                 'no_emp'=>$no_emp, 
                 'mes'=>$mes, 
                 'prom_1_5'=>$prom,
                 'cal_0_100'=>$cal0
                ]);
}

/* ========== GET /mensual/leer: CONSULTA DE EVALUACIÓN ========== */
// GET /mensual/leer?no_emp=XXXX&mes=YYYY-MM-01
if ($_GET['ruta'] === 'mensual/leer') {
    // 🔒 ACL: Admin y Supervisor pueden leer cualquier evaluación.
    requerir_roles(['admin', 'supervisor']); 

    $no_emp = trim($_GET['no_emp'] ?? '');
    $mes = trim($_GET['mes'] ?? '');

    if ($no_emp==='' || $mes==='') enviar_json(['ok'=>false,'error'=>'Faltan No. Empleado o Mes para consultar'],400);

    $sql = "SELECT * FROM evaluacion_mensual WHERE no_emp = ? AND mes = ?";
    $st = $pdo->prepare($sql);
    $st->execute([$no_emp, $mes]);
    $evaluacion = $st->fetch(PDO::FETCH_ASSOC);

    if (!$evaluacion) {
        enviar_json(['ok'=>false, 'error'=>'Evaluación no encontrada para el periodo especificado'], 404);
    }

    enviar_json(['ok'=>true, 'evaluacion'=>$evaluacion]);
}

// Opcional: Si no se encontró la ruta
if ($_GET['ruta'] === 'mensual/') {
     // Esto es solo un fallback si se intenta acceder a /mensual/ sin ruta específica
     enviar_json(['ok'=>false, 'error'=>'Acción no especificada para el módulo mensual'], 400);
}