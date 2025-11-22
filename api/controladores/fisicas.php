<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';
// Utilidades opcionales (si existen); el cálculo tiene fallback abajo
$util = __DIR__ . '/../03_utilidades.php';
if (file_exists($util)) { require_once $util; }
$pdo = obtener_conexion();
$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) $entrada = $_POST;

// Fallbacks tempranos para evitar "undefined function" durante rutas tempranas
if (!function_exists('calcular_componentes_fisica')) {
    function calcular_componentes_fisica($pdo, $edad, $v) {
        // Valores reportados por el front
        $valores = [
            'corrida'    => (float)($v['m']  ?? 0),
            '100m'       => (float)($v['s']  ?? 0),
            'abdominales'=> (float)($v['ab'] ?? 0),
            'lagartijas' => (float)($v['lg'] ?? 0),
            'dominadas'  => (float)($v['br'] ?? 0),
            'burpees'    => (float)($v['bu'] ?? 0),
        ];
        $componentes = [];
        foreach ($valores as $clave => $valor) {
            // Buscar el ejercicio por clave
            $stId = $pdo->prepare("SELECT id FROM fisicas_ejercicio WHERE clave=?");
            try { $stId->execute([$clave]); } catch (Exception $e) { $componentes[$clave] = ['puntos'=>0,'etiqueta'=>'']; continue; }
            $eid = (int)($stId->fetchColumn() ?: 0);
            if (!$eid) { $componentes[$clave] = ['puntos'=>0,'etiqueta'=>'']; continue; }
            // Regla que empareje edad y valor
            $st = $pdo->prepare("SELECT puntos, etiqueta
                                   FROM fisicas_regla
                                  WHERE ejercicio_id=?
                                    AND edad_min<=? AND edad_max>=?
                                    AND valor_min<=? AND valor_max>=?
                               ORDER BY edad_min DESC, valor_min DESC
                                  LIMIT 1");
            try { $st->execute([$eid, (int)$edad, (int)$edad, (float)$valor, (float)$valor]); } catch (Exception $e) { $componentes[$clave] = ['puntos'=>0,'etiqueta'=>'']; continue; }
            $row = $st->fetch();
            $pts = $row ? (int)$row['puntos'] : 0;
            $etq = $row ? (string)$row['etiqueta'] : '';
            $componentes[$clave] = ['puntos'=>$pts,'etiqueta'=>$etq];
        }
        // Promedio 0-10: suma de las 6 calificaciones / 6
        // (corrida, 100m, abdominales, lagartijas, dominadas, burpees)
        $sumaPuntos = array_sum(array_map(fn($c) => (int)$c['puntos'], $componentes));
        $totalEjercicios = 6;
        $prom10 = $sumaPuntos / $totalEjercicios;
        $prom100 = $prom10 * 10.0;

        return [
            'componentes'     => $componentes,
            'por_ejercicio'   => array_map(fn($c) => $c['puntos'], $componentes),
            'promedio_0_10'   => $prom10,
            'promedio_0_100'  => $prom100,
        ];
    }
}
if (!function_exists('clasificacion_fisica_por_nota')) {
    function clasificacion_fisica_por_nota($pdo, $nota) {
        // Mapa simple por defecto 0-100
        $n = (float)$nota;
        if ($n < 60) return ['etiqueta'=>'IRREGULAR','compensacion'=>0];
        if ($n < 70) return ['etiqueta'=>'REGULAR','compensacion'=>2];
        if ($n < 80) return ['etiqueta'=>'BIEN','compensacion'=>4];
        if ($n < 90) return ['etiqueta'=>'MUY BIEN','compensacion'=>6];
        return ['etiqueta'=>'EXCELENTE','compensacion'=>8];
    }
}

// ====== CatÃ¡logo: ClasificaciÃ³n FÃ­sica ======
function asegurar_tabla_clasificacion(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS clasificacion_fisica (
        nota_min TINYINT PRIMARY KEY,
        nota_max TINYINT NOT NULL,
        etiqueta VARCHAR(20) NOT NULL,
        compensacion DECIMAL(5,2) DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ====== Tablas de sesión y registros ======
function asegurar_tabla_sesion(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fisicas_sesion (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        trimestre TINYINT NOT NULL,
        evaluador_id VARCHAR(32) NOT NULL,
        sede VARCHAR(64) NOT NULL,
        estado VARCHAR(16) NOT NULL DEFAULT 'BORRADOR',
        INDEX(fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function asegurar_tabla_registros(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fisicas_registro (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sesion_id BIGINT UNSIGNED NOT NULL,
        no_emp VARCHAR(32) NOT NULL,
        edad TINYINT NOT NULL,
        v_12min_m INT NOT NULL DEFAULT 0,
        v_100m_s DECIMAL(8,2) NOT NULL DEFAULT 0,
        v_abdom INT NOT NULL DEFAULT 0,
        v_lagart INT NOT NULL DEFAULT 0,
        v_barras INT NOT NULL DEFAULT 0,
        v_burpees INT NOT NULL DEFAULT 0,
        cal_fisica DECIMAL(6,2) NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_sesion_emp (sesion_id,no_emp),
        INDEX idx_sesion (sesion_id),
        CONSTRAINT fk_fisreg_ses FOREIGN KEY (sesion_id) REFERENCES fisicas_sesion(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// LISTA
if ($_GET['ruta'] === 'fisicas/clasificacion/lista') {
    asegurar_tabla_clasificacion($pdo);
    $st = $pdo->query("SELECT nota_min, nota_max, etiqueta, compensacion FROM clasificacion_fisica ORDER BY nota_min ASC");
    enviar_json(['ok'=>true,'rangos'=>$st->fetchAll()]);
}

// GUARDAR (reemplaza todo el catÃ¡logo)
if ($_GET['ruta'] === 'fisicas/clasificacion/guardar') {
    requerir_roles(['admin']);
    asegurar_tabla_clasificacion($pdo);
    $rows = $entrada['rangos'] ?? null;
    if (!is_array($rows)) enviar_json(['ok'=>false,'error'=>'Falta arreglo rangos'],400);
    $pdo->beginTransaction();
    try {
        $pdo->exec('DELETE FROM clasificacion_fisica');
        $ins = $pdo->prepare('INSERT INTO clasificacion_fisica (nota_min, nota_max, etiqueta, compensacion) VALUES (?,?,?,?)');
        foreach ($rows as $r) {
            $min = isset($r['nota_min']) ? (int)$r['nota_min'] : null;
            $max = isset($r['nota_max']) ? (int)$r['nota_max'] : null;
            $etq = trim($r['etiqueta'] ?? '');
            $comp = isset($r['compensacion']) ? (float)$r['compensacion'] : 0;
            if ($min===null || $max===null || $etq==='') throw new Exception('Rango invÃ¡lido');
            $ins->execute([$min,$max,$etq,$comp]);
        }
        $pdo->commit();
        enviar_json(['ok'=>true,'mensaje'=>'ClasificaciÃ³n guardada']);
    } catch (Exception $e) {
        $pdo->rollBack();
        enviar_json(['ok'=>false,'error'=>'Error guardando clasificaciÃ³n: '.$e->getMessage()],400);
    }
}

// Crear sesiÃ³n
if ($_GET['ruta'] === 'fisicas/sesion') {
    asegurar_tabla_sesion($pdo);
    $fecha = $entrada['fecha'] ?? date('Y-m-d');
    $tri   = (int)($entrada['trimestre'] ?? 1);
    $eval  = trim($entrada['evaluador_id'] ?? 'evaluador');
    $sede  = trim($entrada['sede'] ?? 'SIN_SEDE');

    $st = $pdo->prepare("INSERT INTO fisicas_sesion (fecha,trimestre,evaluador_id,sede,estado)
                         VALUES (?,?,?,?, 'BORRADOR')");
    $st->execute([$fecha,$tri,$eval,$sede]);
    enviar_json(['ok'=>true,'sesion_id'=>$pdo->lastInsertId()]);
}

// Insertar/actualizar renglÃ³n
if ($_GET['ruta'] === 'fisicas/registro') {
    asegurar_tabla_sesion($pdo);
    asegurar_tabla_registros($pdo);
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

    $detalles = calcular_componentes_fisica($pdo, $edad, $v);
    $cal = $detalles['promedio_0_100'];
    $clasificacion = clasificacion_fisica_por_nota($pdo, $cal);

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

    enviar_json([
        'ok'=>true,
        'sesion_id'=>$sesion_id,
        'no_emp'=>$no_emp,
        'afectadas'=>$st->rowCount(),
        'cal_fisica'=>$cal,
        'detalles'=>$detalles,
        'clasificacion'=>$clasificacion
    ]);
}

// Cerrar sesiÃ³n (bloquear ediciones)
if ($_GET['ruta'] === 'fisicas/cerrar') {
    asegurar_tabla_sesion($pdo);
    $sesion_id = (int)($entrada['sesion_id'] ?? 0);
    if (!$sesion_id) enviar_json(['ok'=>false,'error'=>'Falta sesion_id'],400);
    $pdo->prepare("UPDATE fisicas_sesion SET estado='CERRADA' WHERE id=?")->execute([$sesion_id]);
    enviar_json(['ok'=>true,'sesion_id'=>$sesion_id,'estado'=>'CERRADA']);
}

// Listar registros de una sesiÃ³n
if ($_GET['ruta'] === 'fisicas/sesion_registros') {
    asegurar_tabla_sesion($pdo);
    asegurar_tabla_registros($pdo);
    $sesion_id = (int)($_GET['sesion_id'] ?? 0);
    if (!$sesion_id) enviar_json(['ok'=>false,'error'=>'Falta sesion_id'],400);

    $sql = "SELECT fr.*, CONCAT(e.nombres,' ',e.apellidos) AS nombre_empleado
            FROM fisicas_registro fr
            LEFT JOIN empleados e ON e.no_emp = fr.no_emp
            WHERE fr.sesion_id = :sesion
            ORDER BY fr.id ASC";
    $st = $pdo->prepare($sql);
    $st->execute([':sesion'=>$sesion_id]);
    $rows = $st->fetchAll();

    $registros = array_map(function(array $row) use ($pdo) {
        $v = [
            'm'  => (int)$row['v_12min_m'],
            's'  => (float)$row['v_100m_s'],
            'ab' => (int)$row['v_abdom'],
            'lg' => (int)$row['v_lagart'],
            'br' => (int)$row['v_barras'],
            'bu' => (int)$row['v_burpees'],
        ];
        $detalles = calcular_componentes_fisica($pdo, (int)$row['edad'], $v);
        $clasificacion = clasificacion_fisica_por_nota($pdo, (float)$row['cal_fisica']);
        $row['detalles'] = $detalles;
        $row['clasificacion'] = $clasificacion;
        return $row;
    }, $rows);

    enviar_json(['ok'=>true,'total'=>count($registros),'registros'=>$registros]);
}

// Historial global
if ($_GET['ruta'] === 'fisicas/historial') {
    asegurar_tabla_sesion($pdo);
    asegurar_tabla_registros($pdo);
    $limite = (int)($_GET['limite'] ?? 100);
    if ($limite <= 0) $limite = 100;
    if ($limite > 500) $limite = 500;

    $sql = "SELECT fr.*, fs.fecha, fs.trimestre, fs.evaluador_id, fs.estado,
                   CONCAT(e.nombres,' ',e.apellidos) AS nombre_empleado
            FROM fisicas_registro fr
            JOIN fisicas_sesion fs ON fs.id = fr.sesion_id
            LEFT JOIN empleados e ON e.no_emp = fr.no_emp
            ORDER BY fs.fecha DESC, fr.id DESC
            LIMIT :limite";
    $st = $pdo->prepare($sql);
    $st->bindValue(':limite', $limite, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();

    $historial = array_map(function(array $row) use ($pdo) {
        $v = [
            'm'  => (int)$row['v_12min_m'],
            's'  => (float)$row['v_100m_s'],
            'ab' => (int)$row['v_abdom'],
            'lg' => (int)$row['v_lagart'],
            'br' => (int)$row['v_barras'],
            'bu' => (int)$row['v_burpees'],
        ];
        $detalles = calcular_componentes_fisica($pdo, (int)$row['edad'], $v);
        $clasificacion = clasificacion_fisica_por_nota($pdo, (float)$row['cal_fisica']);
        $row['detalles'] = $detalles;
        $row['clasificacion'] = $clasificacion;
        return $row;
    }, $rows);

    enviar_json(['ok'=>true,'historial'=>$historial]);
}

// ====== CatÃ¡logo: Ejercicios y Reglas por ejercicio ======
function asegurar_tabla_ejercicios(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fisicas_ejercicio (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(32) NOT NULL UNIQUE,
        nombre VARCHAR(100) NOT NULL,
        unidad VARCHAR(20) NOT NULL, -- metros|repeticiones|segundos
        mejor_mayor TINYINT(1) NOT NULL DEFAULT 1,
        activo TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function asegurar_tabla_reglas(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fisicas_regla (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ejercicio_id BIGINT UNSIGNED NOT NULL,
        edad_min TINYINT NOT NULL,
        edad_max TINYINT NOT NULL,
        valor_min DECIMAL(10,2) NOT NULL,
        valor_max DECIMAL(10,2) NOT NULL,
        etiqueta VARCHAR(20) NOT NULL,
        puntos TINYINT NOT NULL,
        INDEX (ejercicio_id),
        CONSTRAINT fk_fisreg_ej FOREIGN KEY (ejercicio_id) REFERENCES fisicas_ejercicio(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function seed_ejercicios(PDO $pdo) {
    asegurar_tabla_ejercicios($pdo);
    $def = [
        ['corrida','Corrida 12 min','metros',1],
        ['abdominales','Abdominales','repeticiones',1],
        ['lagartijas','Lagartijas','repeticiones',1],
        ['dominadas','Dominadas','repeticiones',1],
        ['burpees','Burpees','repeticiones',1],
        ['100m','100 metros planos','segundos',0],
    ];
    foreach($def as $e){
        $st = $pdo->prepare("INSERT IGNORE INTO fisicas_ejercicio (clave,nombre,unidad,mejor_mayor,activo) VALUES (?,?,?,?,1)");
        $st->execute($e);
    }
}

// GET lista de ejercicios
if ($_GET['ruta'] === 'fisicas/ejercicios') {
    seed_ejercicios($pdo);
    $inact = (int)($_GET['inactivos'] ?? 0);
    $sql = "SELECT id, clave, nombre, unidad, mejor_mayor, activo FROM fisicas_ejercicio WHERE 1=1";
    if (!$inact) { $sql .= " AND activo=1"; }
    $sql .= " ORDER BY id";
    $st = $pdo->query($sql);
    enviar_json(['ok'=>true,'ejercicios'=>$st->fetchAll()]);
}

// Instalación/aseguramiento de tablas y seed
if ($_GET['ruta'] === 'fisicas/install') {
    requerir_roles(['admin']);
    try {
        asegurar_tabla_sesion($pdo);
        asegurar_tabla_registros($pdo);
        asegurar_tabla_ejercicios($pdo);
        asegurar_tabla_reglas($pdo);
        seed_ejercicios($pdo);
        enviar_json(['ok'=>true,'mensaje'=>'Tablas creadas y ejercicios base instalados']);
    } catch (Exception $e) {
        enviar_json(['ok'=>false,'error'=>'Error instalando: '.$e->getMessage()],500);
    }
}

// CREAR ejercicio
if ($_GET['ruta'] === 'fisicas/ejercicios/crear') {
    requerir_roles(['admin']);
    try { asegurar_tabla_ejercicios($pdo); } catch (Exception $e) {}
    $j = $entrada ?? [];
    $clave = strtolower(trim($j['clave'] ?? ''));
    $nombre = trim($j['nombre'] ?? '');
    $unidad = trim($j['unidad'] ?? ''); // metros|repeticiones|segundos
    $mm = (int)($j['mejor_mayor'] ?? 1);
    if ($clave==='' || $nombre==='' || !in_array($unidad, ['metros','repeticiones','segundos'], true)) {
        enviar_json(['ok'=>false,'error'=>'Datos invÃ¡lidos'],400);
    }
    try {
        $st = $pdo->prepare("INSERT INTO fisicas_ejercicio (clave,nombre,unidad,mejor_mayor,activo) VALUES (?,?,?,?,1)");
        $st->execute([$clave,$nombre,$unidad,$mm]);
        enviar_json(['ok'=>true,'id'=>$pdo->lastInsertId(),'mensaje'=>'Ejercicio creado']);
    } catch (Exception $e) {
        enviar_json(['ok'=>false,'error'=>'Clave de ejercicio duplicada'],409);
    }
}

// ACTUALIZAR ejercicio
if ($_GET['ruta'] === 'fisicas/ejercicios/actualizar') {
    requerir_roles(['admin']);
    try { asegurar_tabla_ejercicios($pdo); } catch (Exception $e) {}
    $j = $entrada ?? [];
    $id = (int)($j['id'] ?? 0);
    if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);
    $campos = [];$p=[];
    if (isset($j['nombre'])) { $campos[]='nombre=?'; $p[] = trim($j['nombre']); }
    if (isset($j['unidad'])) { $u = trim($j['unidad']); if(!in_array($u,['metros','repeticiones','segundos'],true)) enviar_json(['ok'=>false,'error'=>'Unidad invÃ¡lida'],400); $campos[]='unidad=?'; $p[]=$u; }
    if (isset($j['mejor_mayor'])) { $campos[]='mejor_mayor=?'; $p[] = (int)$j['mejor_mayor']; }
    if (isset($j['activo'])) { $campos[]='activo=?'; $p[] = (int)$j['activo']; }
    if (!$campos) enviar_json(['ok'=>false,'error'=>'Nada que actualizar'],400);
    $p[]=$id;
    $pdo->prepare("UPDATE fisicas_ejercicio SET ".implode(',', $campos)." WHERE id=?")->execute($p);
    enviar_json(['ok'=>true,'mensaje'=>'Ejercicio actualizado']);
}

// ELIMINAR (desactivar) ejercicio
if ($_GET['ruta'] === 'fisicas/ejercicios/eliminar') {
    requerir_roles(['admin']);
    try { asegurar_tabla_ejercicios($pdo); } catch (Exception $e) {}
    $j = $entrada ?? [];
    $id = (int)($j['id'] ?? 0);
    if (!$id) enviar_json(['ok'=>false,'error'=>'Falta id'],400);
    $pdo->prepare("UPDATE fisicas_ejercicio SET activo=0 WHERE id=?")->execute([$id]);
    enviar_json(['ok'=>true,'mensaje'=>'Ejercicio desactivado']);
}

// GET reglas por ejercicio: fisicas/reglas/lista?ejercicio=clave
if ($_GET['ruta'] === 'fisicas/reglas/lista') {
    asegurar_tabla_reglas($pdo); seed_ejercicios($pdo);
    $clave = trim($_GET['ejercicio'] ?? '');
    if ($clave==='') enviar_json(['ok'=>false,'error'=>'Falta ejercicio'],400);
    $stId = $pdo->prepare("SELECT id FROM fisicas_ejercicio WHERE clave=?");
    $stId->execute([$clave]);
    $eid = (int)$stId->fetchColumn();
    if(!$eid) enviar_json(['ok'=>true,'reglas'=>[]]);
    $st = $pdo->prepare("SELECT id, edad_min, edad_max, valor_min, valor_max, etiqueta, puntos FROM fisicas_regla WHERE ejercicio_id=? ORDER BY edad_min, valor_min");
    $st->execute([$eid]);
    enviar_json(['ok'=>true,'reglas'=>$st->fetchAll()]);
}

// POST guardar reglas por ejercicio: fisicas/reglas/guardar {ejercicio, reglas[]}
if ($_GET['ruta'] === 'fisicas/reglas/guardar') {
    requerir_roles(['admin']); asegurar_tabla_reglas($pdo); seed_ejercicios($pdo);
    $j = $entrada; $clave = trim($j['ejercicio'] ?? ''); $reglas = $j['reglas'] ?? null;
    if ($clave==='' || !is_array($reglas)) enviar_json(['ok'=>false,'error'=>'Datos incompletos'],400);
    $stId = $pdo->prepare("SELECT id FROM fisicas_ejercicio WHERE clave=?"); $stId->execute([$clave]); $eid = (int)$stId->fetchColumn();
    if(!$eid) enviar_json(['ok'=>false,'error'=>'Ejercicio no encontrado'],404);
    $pdo->beginTransaction();
    try{
        $pdo->prepare("DELETE FROM fisicas_regla WHERE ejercicio_id=?")->execute([$eid]);
        $ins = $pdo->prepare("INSERT INTO fisicas_regla (ejercicio_id, edad_min, edad_max, valor_min, valor_max, etiqueta, puntos) VALUES (?,?,?,?,?,?,?)");
        foreach($reglas as $r){
            $emin = (int)($r['edad_min'] ?? 0); $emax=(int)($r['edad_max'] ?? 0);
            $vmin = (float)($r['valor_min'] ?? 0); $vmax=(float)($r['valor_max'] ?? 0);
            $etq = trim($r['etiqueta'] ?? ''); $pts = (int)($r['puntos'] ?? 0);
            if($emin<0||$emax<0||$etq==='') throw new Exception('Regla invÃ¡lida');
            $ins->execute([$eid,$emin,$emax,$vmin,$vmax,$etq,$pts]);
        }
        $pdo->commit(); enviar_json(['ok'=>true,'mensaje'=>'Reglas guardadas']);
    } catch(Exception $e){ $pdo->rollBack(); enviar_json(['ok'=>false,'error'=>'Error guardando reglas: '.$e->getMessage()],400); }
}

// ====== CÃ¡lculo por reglas (fallback si no existe util externo) ======
if (!function_exists('calcular_componentes_fisica')) {
    function calcular_componentes_fisica($pdo, $edad, $v) {
        seed_ejercicios($pdo);
        $valores = [
            'corrida'    => (float)($v['m']  ?? 0),
            '100m'       => (float)($v['s']  ?? 0),
            'abdominales'=> (float)($v['ab'] ?? 0),
            'lagartijas' => (float)($v['lg'] ?? 0),
            'dominadas'  => (float)($v['br'] ?? 0),
            'burpees'    => (float)($v['bu'] ?? 0),
        ];
        $componentes = [];
        foreach($valores as $clave=>$valor){
            $stId = $pdo->prepare("SELECT id FROM fisicas_ejercicio WHERE clave=?");
            $stId->execute([$clave]);
            $eid = (int)$stId->fetchColumn();
            if(!$eid) {
                $componentes[$clave] = ['puntos'=>0, 'etiqueta'=>''];
                continue;
            }
            $st = $pdo->prepare("SELECT puntos, etiqueta
                                   FROM fisicas_regla
                                  WHERE ejercicio_id=?
                                    AND edad_min<=? AND edad_max>=?
                                    AND valor_min<=? AND valor_max>=?
                               ORDER BY edad_min DESC, valor_min DESC
                                  LIMIT 1");
            $st->execute([$eid,$edad,$edad,$valor,$valor]);
            $row = $st->fetch();
            $pts = $row ? (int)$row['puntos'] : 0;
            $etq = $row ? (string)$row['etiqueta'] : '';
            $componentes[$clave] = ['puntos'=>$pts, 'etiqueta'=>$etq];
        }
        $vals = array_map(fn($c)=> (int)$c['puntos'], array_values($componentes));
        // promedio sobre ejercicios con valor reportado (>0)
        $presentes = array_filter($valores, fn($x)=>$x>0);
        $n = max(1, count($presentes));
        $prom = (array_sum($vals)) / $n;
        return [
            'componentes'     => $componentes,   // por ejercicio: puntos + etiqueta
            'por_ejercicio'   => array_map(fn($c)=>$c['puntos'], $componentes), // compat
            'promedio_0_100'  => $prom
        ];
    }
}



