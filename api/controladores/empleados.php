<?php
// =============================================
// Controlador de Empleados (alta/baja/lista/reactivar/exportar)
// =============================================
require_once __DIR__ . '/../02_bd.php'; // Conexión DB
require_once __DIR__ . '/../seguridad.php'; // Funciones de seguridad y helpers (ej. enviar_json, usuario_actual)

$pdo = obtener_conexion();

// ==============================================================
// FUNCIÓN HELPER DE ACL: Control de Acceso basado en el rol de usuario
// NOTA: Requiere que 'obtener_rol_usuario()' esté definida en seguridad.php
// ==============================================================
function verificar_acceso($roles_permitidos) {
    // Si la función de seguridad no existe, asume que está en desarrollo y permite el acceso.
    if (!function_exists('obtener_rol_usuario')) {
        // En un entorno de producción, DEBES asegurar que obtener_rol_usuario exista.
        // Si no la tienes, puedes simular un rol de admin para pruebas, pero no es seguro.
        // error_log("ADVERTENCIA: obtener_rol_usuario() no existe. Usando rol 'admin' por defecto.");
        return; 
    }
    
    $rol_actual = obtener_rol_usuario();
    
    // Convertir a minúsculas y verificar
    $rol_actual = strtolower(trim($rol_actual ?? ''));
    $roles_permitidos = array_map('strtolower', $roles_permitidos);
    
    if (!in_array($rol_actual, $roles_permitidos)) {
        enviar_json(['ok'=>false, 'error'=>'Acceso denegado: Rol no autorizado para esta acción'], 403);
        exit;
    }
}
// ==============================================================


/* ========== LISTA (ACTIVO o BAJA) ========== */
// GET /empleados/lista?estado=ACTIVO|BAJA&buscar=&region=&punto_id=
if ($_GET['ruta'] === 'empleados/lista') {
    // Permiso: Todos los roles (Sólo para listar/ver)
    
    $estado = $_GET['estado'] ?? 'ACTIVO';
    $buscar = trim($_GET['buscar'] ?? '');
    $region = trim($_GET['region'] ?? '');
    $punto = trim($_GET['punto_id'] ?? '');

    $sql = "SELECT
                e.no_emp,
                CONCAT(e.nombres,' ',e.apellidos) AS nombre,
                e.fecha_nacimiento,
                e.fecha_alta,
                p.nombre AS punto_nombre,
                e.region,
                e.puesto,
                e.foto_url
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
    // 🔒 ACL: Solo Admin y Gerente pueden dar de alta/editar.
    verificar_acceso(['admin', 'gerente']); 
    
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

    // Usamos INSERT...ON DUPLICATE KEY UPDATE para manejar alta y edición
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
             estatus=estatus"; // Mantiene el estatus actual
    $pdo->prepare($sql)->execute([$no_emp,$nombres,$apellidos,$puesto,$region,$punto_id,$foto_url,$fnac,$falta]);

    enviar_json(['ok'=>true,'mensaje'=>'Empleado dado de alta/actualizado','no_emp'=>$no_emp]);
}

/* ========== BAJA ========== */
// POST /empleados/baja {no_emp, fecha_baja, motivo}
if ($_GET['ruta'] === 'empleados/baja') {
    // 🔒 ACL: Solo Admin, Gerente y Jefe de Seguridad pueden dar de baja.
    verificar_acceso(['admin', 'gerente', 'jefe_seguridad']); 
    
    $u = usuario_actual();
    $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
    $no_emp = trim($e['no_emp'] ?? '');
    $fecha_baja = $e['fecha_baja'] ?? date('Y-m-d');
    $motivo = $e['motivo'] ?? null;
    if ($no_emp==='') enviar_json(['ok'=>false,'error'=>'Falta no_emp'],400);

    // INICIA TRANSACCIÓN para asegurar consistencia
    try {
        $pdo->beginTransaction();
        // 1. Actualiza el estatus del empleado
        $pdo->prepare("UPDATE empleados SET estatus='BAJA' WHERE no_emp=?")->execute([$no_emp]);
        // 2. Registra el historial de baja
        $pdo->prepare("INSERT INTO bajas_empleados (no_emp, fecha_baja, motivo, usuario_baja) VALUES (?,?,?,?)")
            ->execute([$no_emp, $fecha_baja, $motivo, $u['usuario'] ?? 'sistema']);
        $pdo->commit();
        enviar_json(['ok'=>true,'mensaje'=>'Empleado dado de baja','no_emp'=>$no_emp]);
    } catch (PDOException $ex) {
        $pdo->rollBack();
        enviar_json(['ok'=>false,'error'=>'Error en la base de datos: '.$ex->getMessage()], 500);
    }
}

/* ========== REACTIVAR ========== */
// POST /empleados/reactivar {no_emp}
if ($_GET['ruta'] === 'empleados/reactivar') {
    // 🔒 ACL: Solo Admin, Gerente y Jefe de Seguridad pueden reactivar.
    verificar_acceso(['admin', 'gerente', 'jefe_seguridad']); 
    
    $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
    $no_emp = trim($e['no_emp'] ?? '');
    if ($no_emp==='') enviar_json(['ok'=>false,'error'=>'Falta no_emp'],400);

    $pdo->prepare("UPDATE empleados SET estatus='ACTIVO' WHERE no_emp=?")->execute([$no_emp]);
    enviar_json(['ok'=>true,'mensaje'=>'Empleado reactivado','no_emp'=>$no_emp]);
}

/* ========== RESUMEN BAJAS ========== */
// GET /empleados/resumen_bajas?desde=YYYY-MM-DD&hasta=YYYY-MM-DD&buscar=
if ($_GET['ruta'] === 'empleados/resumen_bajas') {
    // Permiso: Todos los roles (Sólo para listar/ver historial)

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
    // 🔒 ACL: Solo Admin, Gerente y Supervisor pueden exportar.
    verificar_acceso(['admin', 'gerente', 'supervisor']); 
    
    $region = trim($_GET['region'] ?? '');
    $punto= isset($_GET['punto_id']) && $_GET['punto_id'] !== '' ? (int)$_GET['punto_id'] : null;
    $buscar = trim($_GET['buscar'] ?? '');

    $params = [':estado'=>'ACTIVO'];
    $sql = "SELECT e.no_emp, CONCAT(e.nombres,' ',e.apellidos) AS nombre, e.puesto, e.region, e.turno, p.nombre AS punto
            FROM empleados e 
            LEFT JOIN puntos p ON p.id = e.punto_id
            WHERE e.estatus=:estado";
    if ($region!==''){ $sql.=" AND e.region=:reg"; $params[':reg']=$region; }
    if ($punto!==null){ $sql.=" AND e.punto_id=:p"; $params[':p']=$punto; }
    if ($buscar!==''){
        $sql.=" AND (e.no_emp=:b OR CONCAT(e.nombres,' ',e.apellidos) LIKE CONCAT('%', :blike ,'%'))";
        $params[':b']=$buscar; $params[':blike']=$buscar;
    }
    $sql.=" ORDER BY e.apellidos,e.nombres";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=empleados_activos.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No.Emp','Nombre','Puesto','Región','Turno','Punto']);
    $st = $pdo->prepare($sql); $st->execute($params);
    while($f = $st->fetch()){ fputcsv($out, [$f['no_emp'],$f['nombre'],$f['puesto'],$f['region'],$f['turno'],$f['punto']]); }
    fclose($out); exit;
}

/* ========== EXPORTAR BAJAS CSV / HTML ========== */
// GET /empleados/exportar_bajas_csv?desde=&hasta=&buscar=
if ($_GET['ruta'] === 'empleados/exportar_bajas_csv') {
    // 🔒 ACL: Solo Admin, Gerente y Supervisor pueden exportar.
    verificar_acceso(['admin', 'gerente', 'supervisor']); 
    
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
    // 🔒 ACL: Solo Admin, Gerente y Supervisor pueden exportar.
    verificar_acceso(['admin', 'gerente', 'supervisor']); 
    
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

/* ========== CATÁLOGOS: LISTA DE PUNTOS (asumido por el JS) ========== */
if ($_GET['ruta'] === 'puntos/lista') {
    // Permiso: Todos los roles (Necesario para el formulario de Alta y filtros)
    $st = $pdo->query("SELECT id, nombre, region FROM puntos ORDER BY region, nombre");
    enviar_json(['ok'=>true, 'puntos'=>$st->fetchAll()]);
}

/* ========== CATÁLOGOS: LISTA DE REGIONES (asumido por el JS) ========== */
if ($_GET['ruta'] === 'regiones/lista') {
    // Permiso: Todos los roles (Necesario para el formulario de Alta y filtros)
    $st = $pdo->query("SELECT DISTINCT region AS nombre FROM empleados WHERE region IS NOT NULL AND region != '' ORDER BY region");
    enviar_json(['ok'=>true, 'regiones'=>$st->fetchAll()]);
}