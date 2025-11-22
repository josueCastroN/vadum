<?php
require_once __DIR__ . '/../02_bd.php';
require_once __DIR__ . '/../seguridad.php';

$pdo = obtener_conexion();
$ruta = $_GET['ruta'] ?? '';

function asegurar_tabla_faltas(PDO $pdo): void {
  $pdo->exec("CREATE TABLE IF NOT EXISTS faltas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_emp VARCHAR(30) NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(no_emp), INDEX(fecha)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  // Normaliza collation si venía distinta
  try { $pdo->exec("ALTER TABLE faltas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); } catch (Throwable $e) { }
  // Si la tabla ya existía con un esquema distinto, aseguramos columnas clave
  try {
    $existe = $pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='faltas' AND COLUMN_NAME='motivo'")->fetchColumn();
    if (!$existe) {
      $pdo->exec("ALTER TABLE faltas ADD COLUMN motivo VARCHAR(255) NOT NULL AFTER fecha");
    }
  } catch (Throwable $e) {
    // Silenciar: en hosting sin permisos sobre information_schema; la inserción revelará el problema si persiste
  }
}

if ($ruta === 'faltas/guardar') {
  try {
    requerir_roles(['admin','supervisor']);
    asegurar_tabla_faltas($pdo);
    $in = json_decode(file_get_contents('php://input'), true) ?? $_POST ?? [];
    $no_emp = trim($in['no_emp'] ?? '');
    $fecha  = trim($in['fecha'] ?? '');
    $motivo = trim($in['motivo'] ?? '');
    if ($no_emp === '' || $fecha === '' || $motivo === '') {
      enviar_json(['ok'=>false,'error'=>'Faltan datos (empleado, fecha y motivo son obligatorios)'], 400);
    }
    $st = $pdo->prepare("INSERT INTO faltas (no_emp, fecha, motivo) VALUES (?,?,?)");
    $st->execute([$no_emp, $fecha, $motivo]);
    enviar_json(['ok'=>true,'mensaje'=>'Falta registrada']);
  } catch (Throwable $e) {
    enviar_json(['ok'=>false,'error'=>'DB: '.$e->getMessage()], 500);
  }
}

if ($ruta === 'faltas/historial') {
  try {
    requerir_roles(['admin','supervisor']);
    asegurar_tabla_faltas($pdo);
    $no_emp = trim($_GET['no_emp'] ?? '');
    $desde  = trim($_GET['desde'] ?? '');
    $hasta  = trim($_GET['hasta'] ?? '');

    $sql = "SELECT f.id, f.fecha, f.no_emp, f.motivo,
                   CONCAT(e.nombres,' ',e.apellidos) AS nombre,
                   e.puesto, e.region, p.nombre AS punto
            FROM faltas f
            LEFT JOIN empleados e ON e.no_emp COLLATE utf8mb4_unicode_ci = f.no_emp COLLATE utf8mb4_unicode_ci
            LEFT JOIN puntos p ON p.id = e.punto_id
            WHERE 1=1";
    $p = [];
    if ($no_emp !== '') { $sql .= " AND f.no_emp = :ne"; $p[':ne'] = $no_emp; }
    if ($desde !== '') { $sql .= " AND f.fecha >= :d"; $p[':d'] = $desde; }
    if ($hasta !== '') { $sql .= " AND f.fecha <= :h"; $p[':h'] = $hasta; }
    $sql .= " ORDER BY f.fecha DESC, f.id DESC LIMIT 1000";
    $st = $pdo->prepare($sql); $st->execute($p);
    enviar_json(['ok'=>true,'faltas'=>$st->fetchAll()]);
  } catch (Throwable $e) {
    enviar_json(['ok'=>false,'error'=>'DB: '.$e->getMessage()], 500);
  }
}

enviar_json(['ok'=>false,'error'=>'Ruta faltas no válida'],404);
