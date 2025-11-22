<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();
$ruta = $_GET['ruta'] ?? '';

function asegurar_tabla_cursos(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS cursos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_emp VARCHAR(30) NOT NULL,
    curso VARCHAR(120) NOT NULL,
    calificacion_final DECIMAL(6,2) NOT NULL,
    aprobado TINYINT(1) NOT NULL DEFAULT 0,
    fecha_fin DATE NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(no_emp), INDEX(curso), INDEX(fecha_fin)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

if ($ruta === 'cursos/guardar') {
  requerir_roles(['admin','supervisor']);
  asegurar_tabla_cursos($pdo);
  $e = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
  $no_emp = trim($e['no_emp'] ?? '');
  $curso  = trim($e['curso'] ?? 'Curso de Vigilante');
  $cal    = isset($e['calificacion']) ? (float)$e['calificacion'] : null;
  $fecha  = trim($e['fecha'] ?? date('Y-m-d'));
  if ($no_emp==='' || $curso==='' || $cal===null) enviar_json(['ok'=>false,'error'=>'Faltan datos'],400);
  $aprob = $cal >= 70 ? 1 : 0;
  $st = $pdo->prepare("INSERT INTO cursos (no_emp, curso, calificacion_final, aprobado, fecha_fin) VALUES (?,?,?,?,?)");
  $st->execute([$no_emp,$curso,$cal,$aprob,$fecha]);
  enviar_json(['ok'=>true,'mensaje'=>'Curso guardado','aprobado'=>$aprob]);
}

if ($ruta === 'cursos/historial') {
  requerir_roles(['admin','supervisor','cliente']);
  asegurar_tabla_cursos($pdo);
  $no_emp = trim($_GET['no_emp'] ?? '');
  $punto  = isset($_GET['punto_id']) ? (int)$_GET['punto_id'] : 0;
  $desde  = trim($_GET['desde'] ?? '');
  $hasta  = trim($_GET['hasta'] ?? '');

  $sql = "SELECT c.fecha_fin, c.curso, c.no_emp, c.calificacion_final, c.aprobado,
                 CONCAT(e.nombres,' ',e.apellidos) AS nombre, e.puesto, p.nombre AS punto, p.region
          FROM cursos c
          LEFT JOIN empleados e ON e.no_emp = c.no_emp
          LEFT JOIN puntos p ON p.id = e.punto_id
          WHERE 1=1";
  $p = [];
  if ($no_emp!==''){ $sql .= " AND c.no_emp = :ne"; $p[':ne']=$no_emp; }
  if ($punto){ $sql .= " AND e.punto_id = :pid"; $p[':pid']=$punto; }
  if ($desde!==''){ $sql .= " AND c.fecha_fin >= DATE_FORMAT(:d,'%Y-%m-01')"; $p[':d']=$desde.'-01'; }
  if ($hasta!==''){ $sql .= " AND c.fecha_fin <= LAST_DAY(DATE_FORMAT(:h,'%Y-%m-01'))"; $p[':h']=$hasta.'-01'; }
  $sql .= " ORDER BY c.fecha_fin DESC, c.id DESC LIMIT 500";
  $st = $pdo->prepare($sql); $st->execute($p);
  enviar_json(['ok'=>true,'historial'=>$st->fetchAll()]);
}

enviar_json(['ok'=>false,'error'=>'Ruta cursos no válida'],404);

